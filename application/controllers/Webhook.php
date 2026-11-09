<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Webhook
 * ─────────────────────────────────────────────────────────────
 * Public gateway webhook listener (extends CI_Controller).
 * Protected exclusively by cryptographic HMAC signatures, strict HTTPS
 * API verification and server-side order/invoice binding.
 */
class Webhook extends CI_Controller
{
    const MAX_PAYLOAD_BYTES = 1048576; // 1 MB

    public function __construct()
    {
        parent::__construct();
        // Public endpoint: never render database errors or SQL to the caller.
        $this->load->database();
        $this->db->db_debug = FALSE;
        $this->load->model('Configuracion_pasarelas_model');
        $this->load->model('Buy_credits_model');
        $this->load->library('btcpay_client');
        $this->load->library('stripe_client');
    }

    /**
     * POST /webhook/btcpay or /BuyCredits/btcpay_webhook
     * Processes BTCPay Server webhook notifications.
     */
    public function btcpay()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            set_status_header(405);
            return $this->_json(['error' => 'Method not allowed']);
        }

        $content_length = (int)($this->input->server('CONTENT_LENGTH') ?? 0);
        if ($content_length > self::MAX_PAYLOAD_BYTES) {
            log_message('error', 'BTCPay Webhook: Payload exceeds the allowed size.');
            set_status_header(413);
            return $this->_json(['error' => 'Payload too large']);
        }

        $raw_body = file_get_contents('php://input');
        if ($raw_body === FALSE || $raw_body === '' || strlen($raw_body) > self::MAX_PAYLOAD_BYTES) {
            log_message('error', 'BTCPay Webhook: Empty or oversized payload.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid payload']);
        }

        $btcpay = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');
        $btcpay = is_array($btcpay) ? $btcpay : [];

        if (!$this->btcpay_client->configuracion_webhook_valida($btcpay)) {
            log_message('error', 'BTCPay Webhook: Gateway configuration is missing or insecure.');
            set_status_header(500);
            return $this->_json(['error' => 'Gateway configuration unavailable']);
        }

        // 1. Validate signature header (BTCPay-Sig: sha256=<hash>)
        $sig_header = trim((string)($this->input->server('HTTP_BTCPAY_SIG') ?? ''));
        if ($sig_header === '') {
            log_message('error', 'BTCPay Webhook: Missing BTCPay-Sig header.');
            set_status_header(403);
            return $this->_json(['error' => 'Missing cryptographic signature']);
        }

        if (stripos($sig_header, 'sha256=') === 0) {
            $sig_header = substr($sig_header, 7);
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $sig_header)) {
            log_message('error', 'BTCPay Webhook: Malformed BTCPay-Sig header.');
            set_status_header(403);
            return $this->_json(['error' => 'Invalid signature format']);
        }

        $expected = hash_hmac('sha256', $raw_body, trim((string)$btcpay['pas_webhook_secret']));
        if (!hash_equals($expected, strtolower($sig_header))) {
            log_message('error', 'BTCPay Webhook: Invalid signature hash.');
            set_status_header(403);
            return $this->_json(['error' => 'Invalid signature']);
        }

        // 2. Parse JSON payload
        $event = json_decode($raw_body, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($event)) {
            log_message('error', 'BTCPay Webhook: Invalid JSON payload.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid JSON structure']);
        }

        $event_type = isset($event['type']) && is_string($event['type']) ? trim($event['type']) : '';
        $invoice_id = isset($event['invoiceId']) && is_scalar($event['invoiceId']) ? trim((string)$event['invoiceId']) : '';

        if ($event_type === '' || $invoice_id === '' || strlen($event_type) > 100 || strlen($invoice_id) > 100) {
            log_message('error', 'BTCPay Webhook: Invalid or incomplete JSON payload.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid JSON structure']);
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $invoice_id)) {
            log_message('error', 'BTCPay Webhook: Invalid invoiceId format.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid invoice reference']);
        }

        // 3. Verify Store ID from the signed event
        $event_store = isset($event['storeId']) && is_scalar($event['storeId']) ? (string)$event['storeId'] : '';
        if ($event_store === '' || !hash_equals((string)$btcpay['pas_store_id'], $event_store)) {
            log_message('error', 'BTCPay Webhook: Store ID mismatch.');
            set_status_header(403);
            return $this->_json(['error' => 'Store ID mismatch']);
        }

        // 4. Log incoming webhook for forensic traceability
        $this->Configuracion_pasarelas_model->registrar_webhook([
            'deliveryId'        => isset($event['deliveryId']) && is_scalar($event['deliveryId']) ? (string)$event['deliveryId'] : '',
            'webhookId'         => isset($event['webhookId']) && is_scalar($event['webhookId']) ? (string)$event['webhookId'] : '',
            'orignalDeliveryId' => isset($event['originalDeliveryId']) && is_scalar($event['originalDeliveryId']) ? (string)$event['originalDeliveryId'] : '',
            'isRedelivery'      => (!empty($event['isRedelivery'])) ? '1' : '0',
            'type'              => $event_type,
            'timestamp'         => isset($event['timestamp']) && is_scalar($event['timestamp']) ? (string)$event['timestamp'] : (string)time(),
            'storeId'           => $event_store,
            'invoiceId'         => $invoice_id,
        ]);

        // 5. Query the authoritative invoice from BTCPay Server over strict HTTPS
        $store_path   = rawurlencode((string)$btcpay['pas_store_id']);
        $invoice_path = '/api/v1/stores/' . $store_path . '/invoices/' . rawurlencode($invoice_id);

        $invoice = $this->btcpay_client->request('GET', $invoice_path, $btcpay);
        if (!is_array($invoice) || empty($invoice['id']) || !is_scalar($invoice['id'])) {
            log_message('error', 'BTCPay Webhook: Unable to retrieve invoice ' . $invoice_id . ' from BTCPay API.');
            set_status_header(502);
            return $this->_json(['error' => 'Unable to verify invoice with BTCPay']);
        }

        $methods = $this->btcpay_client->request('GET', $invoice_path . '/payment-methods', $btcpay);
        if (!is_array($methods)) {
            $methods = [];
        }

        // 6. Validate that the received invoice is the one referenced by the event
        if ((string)($invoice['id'] ?? '') !== $invoice_id) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Invoice ID mismatch. Event: ' . $invoice_id . ' | API: ' . (string)($invoice['id'] ?? ''),
                'SYSTEM'
            );
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'invoice_id_mismatch']);
        }

        if (!isset($invoice['storeId'])
            || !is_scalar($invoice['storeId'])
            || (string)$invoice['storeId'] !== (string)$btcpay['pas_store_id']) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Invoice store mismatch for invoice ' . $invoice_id,
                'SYSTEM'
            );
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'store_mismatch']);
        }

        $payment_method = 'BTC';
        $btc_price = '';
        $btc_paid = '';
        $btc_due = '';
        $total_paid = '';

        foreach ($methods as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((float)($m['paymentMethodPaid'] ?? 0) > 0) {
                $candidate      = (string)($m['paymentMethodId'] ?? 'BTC');
                $candidate      = preg_replace('/[^A-Za-z0-9_-]/', '', $candidate);
                $payment_method = $candidate !== '' ? substr($candidate, 0, 20) : 'BTC';
                $total_paid     = (string)($m['totalPaid'] ?? '');
                $btc_price      = (string)($m['rate'] ?? '');
                $btc_paid       = (string)($m['paymentMethodPaid'] ?? '');
                $btc_due        = (string)($m['due'] ?? '');
                break;
            }
        }

        $metadata = isset($invoice['metadata']) && is_array($invoice['metadata']) ? $invoice['metadata'] : [];
        $order_id = isset($metadata['orderId']) && is_scalar($metadata['orderId'])
            ? trim((string)$metadata['orderId'])
            : '';

        // 7. Record detailed invoice row in cab_mov_btcpay (forensic trail)
        $this->Configuracion_pasarelas_model->registrar_factura_btcpay([
            'id_invoice'          => (string)$invoice['id'],
            'url'                 => (string)($invoice['checkoutLink'] ?? ''),
            'status'              => (string)($invoice['status'] ?? ''),
            'btcPrice'            => $btc_price,
            'price'               => (float)($invoice['amount'] ?? 0),
            'currency'            => (string)($invoice['currency'] ?? 'USD'),
            'invoiceTime'         => (string)($invoice['createdTime'] ?? ''),
            'expirationTime'      => (string)($invoice['expirationTime'] ?? ''),
            'currentTime'         => (string)($invoice['monitoringExpiration'] ?? ''),
            'btcPaid'             => $btc_paid,
            'btcDue'              => $btc_due,
            'rate'                => $btc_price,
            'exceptionStatus'     => (string)($invoice['additionalStatus'] ?? ''),
            'buyerFields'         => json_encode($metadata),
            'transactionCurrency' => (string)($invoice['currency'] ?? 'USD'),
            'paymentSubtotals'    => $btc_due,
            'paymentTotals'       => $btc_due,
            'amountPaid'          => $btc_paid,
            'orderId'             => $order_id,
            'paymentMethods'      => $payment_method,
            'json_ent'            => json_encode($invoice),
            'json_method'         => json_encode($methods),
        ]);

        if ($order_id === '' || !preg_match('/^[A-Za-z0-9_-]{1,20}$/', $order_id)) {
            log_message('info', 'BTCPay Webhook: Invoice has no valid orderId in metadata.');
            set_status_header(200);
            return $this->_json(['status' => 'ok', 'msg' => 'No orderId associated']);
        }

        $order = $this->Buy_credits_model->get_orden_por_id($order_id);
        if (empty($order) || (string)$order['mov_id'] !== $order_id) {
            log_message('error', 'BTCPay Webhook: Order ' . $order_id . ' not found in database.');
            set_status_header(200);
            return $this->_json(['status' => 'ok', 'msg' => 'Order not found']);
        }

        // 8. Server-side binding: reject invoices not issued for this order.
        $stored_invoice = trim((string)($order['mov_idpago'] ?? ''));
        if ($stored_invoice !== '' && !hash_equals($stored_invoice, $invoice_id)) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Invoice/order binding mismatch. Order #' . $order_id . ' expected invoice ' . $stored_invoice . ' but received ' . $invoice_id . '.',
                $order['mov_idusuario']
            );
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'invoice_not_bound_to_order']);
        }

        // 9. Verify amount and currency against the immutably stored order snapshot.
        $order_currency   = strtoupper(trim((string)($order['mov_moneda'] ?? '')));
        $invoice_currency = isset($invoice['currency']) && is_scalar($invoice['currency'])
            ? strtoupper(trim((string)$invoice['currency']))
            : '';
        $amount_ok        = $this->btcpay_client->montos_coinciden(
            $order['mov_precioplan'] ?? null,
            $invoice['amount'] ?? null
        );

        if ((float)($order['mov_precioplan'] ?? 0) <= 0
            || !$this->btcpay_client->moneda_valida($order_currency)
            || $order_currency !== $invoice_currency
            || !$amount_ok) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Amount/currency mismatch on Order #' . $order_id
                . '. Expected: ' . (string)($order['mov_precioplan'] ?? '') . ' ' . $order_currency
                . ' | Invoice: ' . (string)($invoice['amount'] ?? '') . ' ' . $invoice_currency
                . ' | Invoice ID: ' . $invoice_id,
                $order['mov_idusuario']
            );
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'amount_currency_mismatch']);
        }

        // 10. Validate event type against the authoritative invoice status.
        $invoice_status = isset($invoice['status']) && is_scalar($invoice['status'])
            ? (string)$invoice['status']
            : '';
        $pending_statuses = ['NoPagado', 'waiting', 'in_process', 'in_process_partial'];
        $current_status   = (string)($order['mov_statuspag'] ?? '');

        if ($event_type === 'InvoiceSettled') {
            if ($invoice_status !== 'Settled') {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'SECURITY WARNING: InvoiceSettled event with non-Settled invoice. Invoice: ' . $invoice_id . ' | Status: ' . $invoice_status,
                    $order['mov_idusuario']
                );
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'invoice_not_settled']);
            }

            if ($current_status === 'approved') {
                set_status_header(200);
                return $this->_json(['status' => 'ok', 'msg' => 'Order already approved']);
            }

            if (!in_array((string)($order['mov_status'] ?? ''), ['A', 'V'], TRUE)
                || !in_array($current_status, $pending_statuses, TRUE)) {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'SECURITY WARNING: Settlement attempted on a non-payable order #' . $order_id . ' with status ' . $current_status . '.',
                    $order['mov_idusuario']
                );
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            $result = $this->Buy_credits_model->liquidar_pago_aprobado($order, $invoice_id, $payment_method);

            if ($result === FALSE) {
                log_message('error', 'BTCPay Webhook: Failed to settle order ' . $order_id . '.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to settle order']);
            }

            if ((int)$result === 1) {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_CONFIRMADO',
                    'Payment settled via BTCPay Server for Order #' . $order_id
                    . '. Invoice: ' . $invoice_id
                    . ' | Amount: ' . (string)$invoice['amount'] . ' ' . $invoice_currency
                    . ' | Coin: ' . $payment_method
                    . ' | User: ' . $order['mov_idusuario'],
                    $order['mov_idusuario']
                );
            } else {
                // A concurrent delivery already credited this order.
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_DUPLICADO',
                    'Duplicate settlement delivery ignored for Order #' . $order_id . ' | Invoice: ' . $invoice_id,
                    $order['mov_idusuario']
                );
            }
        } elseif (in_array($event_type, ['InvoiceReceivedPayment', 'InvoiceProcessing', 'InvoicePaymentSettled'], TRUE)) {
            if (!in_array($invoice_status, ['New', 'Processing'], TRUE)) {
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'invoice_status_mismatch']);
            }

            if (!in_array($current_status, $pending_statuses, TRUE)) {
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            $substatus = (($invoice['additionalStatus'] ?? '') === 'PaidPartial') ? 'in_process_partial' : 'in_process';
            if (!$this->Buy_credits_model->actualizar_estado_pago($order_id, $invoice_id, $substatus, $payment_method)) {
                log_message('error', 'BTCPay Webhook: Failed to update in-process status for order ' . $order_id . '.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to update order']);
            }
            $this->_registrar_bitacora(
                'COMPRA_PAGO_RECIBIDO',
                'BTCPay payment in process for Order #' . $order_id . '. Status: ' . $substatus . ' | Coin: ' . $payment_method,
                $order['mov_idusuario']
            );
        } elseif ($event_type === 'InvoiceExpired') {
            if ($invoice_status !== 'Expired' || !in_array($current_status, $pending_statuses, TRUE)) {
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            if (!$this->Buy_credits_model->actualizar_estado_pago($order_id, $invoice_id, 'Expired')) {
                log_message('error', 'BTCPay Webhook: Failed to mark order ' . $order_id . ' as expired.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to update order']);
            }
            $this->_registrar_bitacora('COMPRA_INVOICE_EXPIRADO', 'BTCPay invoice expired for Order #' . $order_id, $order['mov_idusuario']);
        } elseif ($event_type === 'InvoiceInvalid') {
            if ($invoice_status !== 'Invalid' || !in_array($current_status, $pending_statuses, TRUE)) {
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            if (!$this->Buy_credits_model->actualizar_estado_pago($order_id, $invoice_id, 'cancelled')) {
                log_message('error', 'BTCPay Webhook: Failed to mark order ' . $order_id . ' as cancelled.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to update order']);
            }
            $this->_registrar_bitacora('COMPRA_INVOICE_INVALIDO', 'BTCPay invoice marked invalid for Order #' . $order_id, $order['mov_idusuario']);
        }

        set_status_header(200);
        return $this->_json(['status' => 'ok', 'event' => $event_type]);
    }

    /**
     * POST /webhook/stripe
     * Processes Stripe Checkout webhook notifications.
     *
     * Security model:
     * - Stripe-Signature is verified against the raw body (HMAC-SHA256).
     * - The event is deduplicated by Stripe event id.
     * - The Checkout Session is re-fetched from Stripe over strict HTTPS;
     *   the event body is never trusted for amount, currency or status.
     * - The session must match the order snapshot and the bound session id.
     * - Settlement is idempotent; the order and user credit commit in one
     *   InnoDB transaction, so a failed credit update rolls both back.
     */
    public function stripe()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            set_status_header(405);
            return $this->_json(['error' => 'Method not allowed']);
        }

        $content_length = (int)($this->input->server('CONTENT_LENGTH') ?? 0);
        if ($content_length > self::MAX_PAYLOAD_BYTES) {
            log_message('error', 'Stripe Webhook: Payload exceeds the allowed size.');
            set_status_header(413);
            return $this->_json(['error' => 'Payload too large']);
        }

        $raw_body = file_get_contents('php://input');
        if ($raw_body === FALSE || $raw_body === '' || strlen($raw_body) > self::MAX_PAYLOAD_BYTES) {
            log_message('error', 'Stripe Webhook: Empty or oversized payload.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid payload']);
        }

        $stripe = $this->Configuracion_pasarelas_model->get_pasarela('stripe');
        $stripe = is_array($stripe) ? $stripe : [];

        if (!$this->stripe_client->configuracion_webhook_valida($stripe)) {
            log_message('error', 'Stripe Webhook: Gateway configuration is missing or insecure.');
            set_status_header(500);
            return $this->_json(['error' => 'Gateway configuration unavailable']);
        }

        $signature = trim((string)($this->input->server('HTTP_STRIPE_SIGNATURE') ?? ''));
        if ($signature === '') {
            log_message('error', 'Stripe Webhook: Missing Stripe-Signature header.');
            set_status_header(403);
            return $this->_json(['error' => 'Missing cryptographic signature']);
        }

        if (!$this->stripe_client->verificar_webhook(
            $raw_body,
            $signature,
            (string)$stripe['pas_webhook_secret']
        )) {
            log_message('error', 'Stripe Webhook: Invalid or expired signature.');
            set_status_header(403);
            return $this->_json(['error' => 'Invalid signature']);
        }

        $event = json_decode($raw_body, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($event)) {
            log_message('error', 'Stripe Webhook: Invalid JSON payload.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid JSON structure']);
        }

        $event_id   = isset($event['id']) && is_scalar($event['id']) ? trim((string)$event['id']) : '';
        $event_type = isset($event['type']) && is_scalar($event['type']) ? trim((string)$event['type']) : '';
        $object     = isset($event['data']['object']) && is_array($event['data']['object'])
            ? $event['data']['object']
            : NULL;

        if ($event_id === ''
            || !preg_match('/^evt_[A-Za-z0-9_]+$/', $event_id)
            || $event_type === ''
            || strlen($event_type) > 100
            || !is_array($object)) {
            log_message('error', 'Stripe Webhook: Invalid or incomplete event payload.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid event structure']);
        }

        // Acknowledge unrelated event types without touching the database.
        if (strpos($event_type, 'checkout.session.') !== 0) {
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'unsupported_event']);
        }

        $session_id = isset($object['id']) && is_scalar($object['id']) ? trim((string)$object['id']) : '';
        if ($session_id === '' || !preg_match('/^cs_[A-Za-z0-9_]+$/', $session_id)) {
            log_message('error', 'Stripe Webhook: Invalid Checkout Session id.');
            set_status_header(400);
            return $this->_json(['error' => 'Invalid session reference']);
        }

        $metadata   = isset($object['metadata']) && is_array($object['metadata']) ? $object['metadata'] : [];
        $order_id   = isset($object['client_reference_id']) && is_scalar($object['client_reference_id'])
            ? trim((string)$object['client_reference_id'])
            : '';
        $meta_order = isset($metadata['order_id']) && is_scalar($metadata['order_id'])
            ? trim((string)$metadata['order_id'])
            : '';

        if ($order_id === '' && $meta_order !== '') {
            $order_id = $meta_order;
        }

        $payment_intent = isset($object['payment_intent']) && is_scalar($object['payment_intent'])
            ? trim((string)$object['payment_intent'])
            : '';
        $livemode = !empty($object['livemode']) ? 1 : 0;

        // Deduplicate by Stripe event id. A processed event is acknowledged
        // immediately; an unprocessed event is retried safely.
        $evento = $this->Configuracion_pasarelas_model->get_evento_stripe($event_id);
        if (!empty($evento) && (int)($evento['processed'] ?? 0) === 1) {
            set_status_header(200);
            return $this->_json(['status' => 'duplicate', 'event' => $event_id]);
        }

        if (empty($evento)) {
            $created = $this->Configuracion_pasarelas_model->crear_evento_stripe([
                'event_id'       => $event_id,
                'type'           => $event_type,
                'session_id'     => $session_id,
                'payment_intent' => $payment_intent,
                'order_id'       => $order_id,
                'livemode'       => $livemode,
                'payload_hash'   => hash('sha256', $raw_body),
                'payload_json'   => $raw_body,
            ]);

            if (!$created) {
                log_message('error', 'Stripe Webhook: Unable to persist event ' . $event_id . '.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to persist event']);
            }
        }

        // Re-fetch the authoritative session from Stripe. The signed event is
        // only a trigger; it is never the source of truth for money.
        $session = $this->stripe_client->recuperar_sesion_checkout($stripe, $session_id);
        if (!is_array($session)) {
            log_message('error', 'Stripe Webhook: Unable to retrieve session ' . $session_id . ' from Stripe.');
            set_status_header(500);
            return $this->_json(['error' => 'Unable to verify session with Stripe']);
        }

        $session_status = isset($session['status']) && is_scalar($session['status'])
            ? (string)$session['status']
            : '';
        $payment_status = isset($session['payment_status']) && is_scalar($session['payment_status'])
            ? (string)$session['payment_status']
            : '';
        $session_pi     = isset($session['payment_intent']) && is_scalar($session['payment_intent'])
            ? trim((string)$session['payment_intent'])
            : $payment_intent;

        if ((string)($session['id'] ?? '') !== $session_id
            || !$this->stripe_client->modo_coincide($session, $stripe)) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Stripe session id or livemode mismatch. Event: ' . $event_id . ' | Session: ' . $session_id,
                'SYSTEM'
            );
            $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'session_identity_mismatch']);
        }

        if ($order_id === '' || !preg_match('/^[A-Za-z0-9_-]{1,20}$/', $order_id)) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Stripe session without a valid order reference. Session: ' . $session_id,
                'SYSTEM'
            );
            $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'missing_order_reference']);
        }

        $order = $this->Buy_credits_model->get_orden_por_id($order_id);
        if (empty($order) || (string)$order['mov_id'] !== $order_id) {
            log_message('info', 'Stripe Webhook: Order ' . $order_id . ' not found.');
            $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'order_not_found']);
        }

        $order_currency = strtoupper(trim((string)($order['mov_moneda'] ?? '')));
        if (!$this->stripe_client->sesion_coincide(
            $session,
            $order,
            $order['mov_precioplan'] ?? 0,
            $order_currency
        )) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Stripe amount/currency/order mismatch. Order #' . $order_id
                . ' | Session: ' . $session_id
                . ' | Expected: ' . (string)($order['mov_precioplan'] ?? '') . ' ' . $order_currency
                . ' | Session: ' . (string)($session['amount_total'] ?? '') . ' ' . strtoupper((string)($session['currency'] ?? '')),
                $order['mov_idusuario']
            );
            $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'amount_currency_mismatch']);
        }

        // Forensics: keep one row per Checkout Session with the latest status.
        $this->Configuracion_pasarelas_model->registrar_transaccion_stripe([
            'order_id'       => $order_id,
            'session_id'     => $session_id,
            'payment_intent' => $session_pi,
            'amount'         => (float)($order['mov_precioplan'] ?? 0),
            'currency'       => $order_currency,
            'amount_total'   => (int)($session['amount_total'] ?? 0),
            'payment_status' => $payment_status,
            'status'         => $session_status,
            'livemode'       => !empty($session['livemode']) ? 1 : 0,
            'customer_email' => (string)($session['customer_details']['email'] ?? ''),
            'raw_json'       => json_encode($session),
        ]);

        $stored_session = trim((string)($order['mov_stripe_session_id'] ?? ''));
        $legacy_ref     = trim((string)($order['mov_idpago'] ?? ''));

        if ($stored_session === '') {
            // A paid session can be late-bound only when the order has no
            // payment reference at all. A legacy Stripe PaymentIntent, a
            // BTCPay invoice, or any other reference means the order is
            // already owned and must not be rebound.
            if ($legacy_ref !== '') {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'SECURITY WARNING: Stripe session cannot bind to Order #' . $order_id
                    . ' because it already has payment reference ' . $legacy_ref . '. Session: ' . $session_id,
                    $order['mov_idusuario']
                );
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_already_has_payment_reference']);
            }
        } elseif (!hash_equals($stored_session, $session_id)) {
            $this->_registrar_bitacora(
                'COMPRA_PAGO_FRAUDE',
                'SECURITY WARNING: Stripe session/order binding mismatch. Order #' . $order_id
                . ' expected ' . $stored_session . ' but received ' . $session_id . '.',
                $order['mov_idusuario']
            );
            $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'session_not_bound_to_order']);
        }

        $pending_statuses = ['NoPagado', 'waiting', 'in_process'];
        $current_status   = (string)($order['mov_statuspag'] ?? '');

        if (in_array($event_type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], TRUE)) {
            if ($payment_status !== 'paid' || $session_status !== 'complete') {
                // Card 3DS/async edge case: wait for the async success event.
                if ($event_type === 'checkout.session.completed'
                    && in_array($current_status, ['NoPagado', 'waiting'], TRUE)
                    && $stored_session !== '') {
                    if (!$this->Buy_credits_model->actualizar_estado_stripe($order_id, $session_id, 'in_process')) {
                        log_message('error', 'Stripe Webhook: Failed to set in_process for order ' . $order_id . '.');
                        set_status_header(500);
                        return $this->_json(['error' => 'Unable to update order']);
                    }
                    $this->_registrar_bitacora(
                        'COMPRA_STRIPE_PROCESANDO',
                        'Stripe payment processing for Order #' . $order_id . ' | Session: ' . $session_id,
                        $order['mov_idusuario']
                    );
                }
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ok', 'msg' => 'Payment not settled yet']);
            }

            if ($current_status === 'approved') {
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ok', 'msg' => 'Order already approved']);
            }

            if ((string)($order['mov_status'] ?? '') !== 'A'
                || !in_array($current_status, $pending_statuses, TRUE)) {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_TARDIO',
                    'SECURITY WARNING: Paid Stripe session arrived for non-payable Order #' . $order_id
                    . ' with status ' . $current_status . '. Session: ' . $session_id,
                    $order['mov_idusuario']
                );
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            if ($stored_session === '') {
                if (!$this->Buy_credits_model->vincular_sesion_stripe_pagada(
                    $order_id,
                    $session_id,
                    $session_pi,
                    $order_currency
                )) {
                    $fresh = $this->Buy_credits_model->get_orden_por_id($order_id);
                    if (empty($fresh) || (string)($fresh['mov_statuspag'] ?? '') !== 'approved') {
                        log_message('error', 'Stripe Webhook: Unable to late-bind session ' . $session_id . ' to order ' . $order_id . '.');
                        set_status_header(500);
                        return $this->_json(['error' => 'Unable to bind session to order']);
                    }
                }
            }

            $result = $this->Buy_credits_model->liquidar_pago_stripe(
                $order,
                $session_id,
                $session_pi,
                $order_currency
            );

            if ($result === FALSE) {
                log_message('error', 'Stripe Webhook: Failed to settle order ' . $order_id . '.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to settle order']);
            }

            if ((int)$result === 1) {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_CONFIRMADO',
                    'Payment settled via Stripe for Order #' . $order_id
                    . '. Session: ' . $session_id
                    . ' | PaymentIntent: ' . $session_pi
                    . ' | Amount: ' . (string)($order['mov_precioplan'] ?? '') . ' ' . $order_currency
                    . ' | User: ' . $order['mov_idusuario'],
                    $order['mov_idusuario']
                );
            } else {
                $this->_registrar_bitacora(
                    'COMPRA_PAGO_DUPLICADO',
                    'Duplicate Stripe settlement delivery ignored for Order #' . $order_id . ' | Session: ' . $session_id,
                    $order['mov_idusuario']
                );
            }
        } elseif ($event_type === 'checkout.session.expired') {
            if ($stored_session === '' || $session_status !== 'expired') {
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'session_not_expirable']);
            }

            if ($current_status === 'approved' || !in_array($current_status, $pending_statuses, TRUE)) {
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            if (!$this->Buy_credits_model->actualizar_estado_stripe($order_id, $session_id, 'expired')) {
                log_message('error', 'Stripe Webhook: Failed to mark order ' . $order_id . ' as expired.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to update order']);
            }
            $this->_registrar_bitacora(
                'COMPRA_STRIPE_EXPIRADO',
                'Stripe Checkout Session expired for Order #' . $order_id . ' | Session: ' . $session_id,
                $order['mov_idusuario']
            );
        } elseif ($event_type === 'checkout.session.async_payment_failed') {
            if ($stored_session === '' || $payment_status === 'paid') {
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'session_not_failed']);
            }

            if ($current_status === 'approved' || !in_array($current_status, $pending_statuses, TRUE)) {
                $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
                set_status_header(200);
                return $this->_json(['status' => 'ignored', 'reason' => 'order_not_payable']);
            }

            if (!$this->Buy_credits_model->actualizar_estado_stripe($order_id, $session_id, 'rejected')) {
                log_message('error', 'Stripe Webhook: Failed to mark order ' . $order_id . ' as rejected.');
                set_status_header(500);
                return $this->_json(['error' => 'Unable to update order']);
            }
            $this->_registrar_bitacora(
                'COMPRA_STRIPE_RECHAZADO',
                'Stripe asynchronous payment failed for Order #' . $order_id . ' | Session: ' . $session_id,
                $order['mov_idusuario']
            );
        } else {
            $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
            set_status_header(200);
            return $this->_json(['status' => 'ignored', 'reason' => 'event_not_actionable']);
        }

        $this->Configuracion_pasarelas_model->marcar_evento_stripe_procesado($event_id);
        set_status_header(200);
        return $this->_json(['status' => 'ok', 'event' => $event_type]);
    }

    /**
     * Helper to insert audit event in cab_bitacora without requiring session
     */
    private function _registrar_bitacora($accion, $detalle, $usuario_id = 'SYSTEM')
    {
        if (!$this->db->table_exists('cab_bitacora')) {
            return;
        }

        $this->db->insert('cab_bitacora', [
            'bit_usuario' => (string)$usuario_id,
            'bit_accion'  => (string)$accion,
            'bit_detalles'=> (string)$detalle,
            'bit_ip'      => (string)$this->input->ip_address(),
            'bit_date'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Helper to render JSON response
     */
    private function _json($data)
    {
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
