<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * BuyCredits
 * ─────────────────────────────────────────────────────────────
 * Controller for selecting plans, purchasing credits, and managing
 * purchase orders (Phase 1: Order Generation & Transactions).
 * Extends MY_Controller.
 */
class BuyCredits extends MY_Controller
{
    protected $stripe_disponible  = FALSE;
    protected $btcpay_disponible  = FALSE;

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Buy_credits_model');
        $this->load->model('Configuracion_pasarelas_model');
        $this->load->library('btcpay_client');
        $this->load->library('stripe_client');

        $stripe = $this->Configuracion_pasarelas_model->get_pasarela('stripe');
        $this->stripe_disponible = is_array($stripe)
            && (int)($stripe['pas_activo'] ?? 0) === 1
            // El checkout con tarjeta debe liquidarse mediante un webhook verificado.
            && $this->stripe_client->configuracion_webhook_valida($stripe);

        $btcpay = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');
        $this->btcpay_disponible = is_array($btcpay)
            && (int)($btcpay['pas_activo'] ?? 0) === 1
            && $this->btcpay_client->configuracion_webhook_valida($btcpay);

        $this->page_title = 'Comprar Créditos';
        $this->breadcrumb = [
            ['titulo' => 'Inicio',        'url' => base_url('dashboard')],
            ['titulo' => 'Comprar Créditos', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /comprar-creditos or /BuyCredits/index
     * Renders the plans showcase and user's order history.
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para ver los planes.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');

        $datos = [
            'url_base'        => $this->url_base,
            'planes'            => $this->Buy_credits_model->get_planes_activos(),
            'orden_pendiente'   => $this->Buy_credits_model->get_orden_pendiente_usuario($id_usuario),
            'usuario'           => $this->usuario,
            'stripe_disponible' => $this->stripe_disponible,
            'btcpay_disponible' => $this->btcpay_disponible,
        ];

        $this->render('pages/buy_credits/index', $datos);
    }

    /**
     * POST /BuyCredits/confirmar/:id (AJAX)
     * Returns plan details for the purchase confirmation modal.
     */
    public function confirmar($id_plan = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para ver este plan.');

        $id_plan = (int)$id_plan;
        $plan = $this->Buy_credits_model->get_plan_por_id($id_plan);

        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El plan seleccionado no existe o no está disponible.'], 404);
        }

        $this->json([
            'ok'   => TRUE,
            'plan' => [
                'pla_id'          => (int)$plan['pla_id'],
                'pla_nombre'      => (string)$plan['pla_nombre'],
                'pla_descripcion' => (string)($plan['pla_descripcion'] ?? ''),
                'pla_numero'      => (int)$plan['pla_numero'],
                'pla_precio'      => number_format((float)$plan['pla_precio'], 2, ',', '.'),
                'pla_precio_raw'  => (float)$plan['pla_precio'],
                'pla_moneda'      => (string)($plan['pla_moneda'] ?? 'USD'),
                'pla_paypal'      => (int)($plan['pla_paypal'] ?? 0),
                'pla_crypto'      => (int)($plan['pla_crypto'] ?? 0),
            ],
        ]);
    }

    /**
     * POST /BuyCredits/procesar_compra (AJAX)
     * Generates purchase order transaction in cab_movplanes and cab_movsms.
     */
    public function procesar_compra()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para comprar planes o créditos.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');
        $id_plan    = (int)$this->_post_escalar('id_plan');

        $plan = $this->Buy_credits_model->get_plan_por_id($id_plan);
        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El plan seleccionado no es válido o fue retirado.'], 404);
        }

        $plan_currency = strtoupper(trim((string)($plan['pla_moneda'] ?? '')));
        if ((float)($plan['pla_precio'] ?? 0) <= 0
            || (int)($plan['pla_numero'] ?? 0) <= 0
            || !preg_match('/^[A-Z]{3,10}$/', $plan_currency)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Este plan tiene precio, créditos o moneda inválidos. Contacta al administrador.'], 422);
        }

        $plan_card   = (int)($plan['pla_paypal'] ?? 0) === 1 && $this->stripe_disponible;
        $plan_crypto = (int)($plan['pla_crypto'] ?? 0) === 1 && $this->btcpay_disponible;

        if (!$plan_card && !$plan_crypto) {
            return $this->json(['ok' => FALSE, 'msg' => 'No hay un método de pago disponible para este plan en este momento. Contacta al administrador.'], 422);
        }

        // Verify if the user already has an active pending unpaid order
        $orden_pendiente = $this->Buy_credits_model->get_orden_pendiente_usuario($id_usuario);
        if (!empty($orden_pendiente)) {
            return $this->json([
                'ok'              => FALSE,
                'msg'             => "Ya tienes una orden de compra pendiente (#{$orden_pendiente['mov_id']}). Completa el pago o cancélala antes de crear otra.",
                'orden_pendiente' => $orden_pendiente['mov_id'],
            ], 422);
        }

        // Generate order in DB
        $mov_id = $this->Buy_credits_model->procesar_compra($id_usuario, $plan);

        if (!$mov_id) {
            // A concurrent request may have created the pending order while
            // this request was waiting for the per-user lock.
            $orden_pendiente = $this->Buy_credits_model->get_orden_pendiente_usuario($id_usuario);
            if (!empty($orden_pendiente)) {
                return $this->json([
                    'ok'              => FALSE,
                    'msg'             => "Ya tienes una orden de compra pendiente (#{$orden_pendiente['mov_id']}). Completa el pago o cancélala antes de crear otra.",
                    'orden_pendiente' => $orden_pendiente['mov_id'],
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'Ocurrió un error al crear la orden de compra. Inténtalo de nuevo.'], 500);
        }

        // Audit log
        $this->bitacora(
            'COMPRA_ORDEN_CREAR',
            "Orden de compra creada: #{$mov_id} | Plan: {$plan['pla_nombre']} (ID: {$id_plan}) | Monto: {$plan['pla_precio']} {$plan['pla_moneda']} | Créditos: {$plan['pla_numero']}"
        );

        $this->json([
            'ok'       => TRUE,
            'msg'      => '¡Orden de compra generada correctamente! Continúa con tu método de pago.',
            'orden_id' => $mov_id,
        ]);
    }

    /**
     * POST /BuyCredits/cancelar_orden (AJAX)
     * Allows user to cancel an order that is still in 'NoPagado' status.
     */
    public function cancelar_orden()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar esta orden.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');
        $mov_id     = $this->_post_escalar('mov_id');

        $orden = $this->Buy_credits_model->get_orden_por_id($mov_id, $id_usuario);
        if (empty($orden)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La orden no existe o no pertenece a tu cuenta.'], 404);
        }

        if (!in_array($orden['mov_statuspag'], ['NoPagado', 'waiting'], TRUE)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Solo se pueden cancelar órdenes impagas o pendientes de pago.'], 422);
        }

        if (!in_array(strtoupper((string)($orden['mov_status'] ?? '')), ['A', 'V'], TRUE)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Esta orden no está activa y no se puede cancelar.'], 422);
        }

        // A Stripe Checkout Session can still be paid after the order is
        // cancelled. Expire it first; never leave a live payable link behind.
        $stripe_session_id = trim((string)($orden['mov_stripe_session_id'] ?? ''));
        $legacy_ref        = trim((string)($orden['mov_idpago'] ?? ''));
        $gateway           = strtolower(trim((string)($orden['mov_tippago'] ?? '')));
        $gateway_crypto    = in_array($gateway, ['btcpay', 'btc_pay', 'crypto', 'bitcoin'], TRUE);

        // A legacy Stripe/PayPal reference cannot be expired through the
        // Checkout API. Refuse cancellation instead of leaving a payable link
        // behind. Known BTCPay invoices keep their existing cancellation flow.
        if ($stripe_session_id === '' && $legacy_ref !== '' && !$gateway_crypto) {
            $this->bitacora(
                'COMPRA_PAGO_CONFLICTO',
                'Cancelación bloqueada para orden de pago heredada #' . $mov_id . ' con referencia: ' . $legacy_ref
            );
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Esta orden está vinculada a un pago heredado y no se puede cancelar desde aquí. Contacta al administrador.',
            ], 409);
        }

        if ($stripe_session_id !== '') {
            $stripe = $this->Configuracion_pasarelas_model->get_pasarela('stripe');
            if (!is_array($stripe) || !$this->stripe_client->configuracion_api_valida($stripe)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'La pasarela de tarjeta no está disponible. La orden no se puede cancelar de forma segura en este momento.',
                ], 503);
            }

            $session = $this->stripe_client->recuperar_sesion_checkout($stripe, $stripe_session_id);
            if (!is_array($session)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No se pudo verificar el estado del pago con tarjeta. Inténtalo de nuevo en un momento.',
                ], 503);
            }

            $session_status = isset($session['status']) && is_scalar($session['status'])
                ? (string)$session['status']
                : '';

            $session_ok = $this->stripe_client->sesion_coincide(
                $session,
                $orden,
                $orden['mov_precioplan'] ?? 0,
                $orden['mov_moneda'] ?? ''
            ) && $this->stripe_client->modo_coincide($session, $stripe);

            if (!$session_ok) {
                if ($this->_stripe_sesion_de_orden($session, $mov_id) && $session_status === 'open') {
                    $expired = $this->stripe_client->expirar_sesion_checkout($stripe, $stripe_session_id);
                    if (!is_array($expired) || (string)($expired['status'] ?? '') !== 'expired') {
                        log_message('error', 'Stripe: unable to expire mismatched session ' . $stripe_session_id . ' for order ' . $mov_id . '.');
                    }
                }

                $this->bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'La sesión de Stripe no coincide con la orden #' . $mov_id . ' durante la cancelación. Sesión: ' . $stripe_session_id
                );
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago con tarjeta no pudo validarse para esta orden. Contacta al administrador.',
                ], 409);
            }

            if ($session_status === 'complete') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Este pago con tarjeta ya fue completado y se está confirmando. La orden no se puede cancelar.',
                ], 409);
            }

            if ($session_status === 'open') {
                $expired = $this->stripe_client->expirar_sesion_checkout($stripe, $stripe_session_id);
                if (!is_array($expired) || (string)($expired['status'] ?? '') !== 'expired') {
                    return $this->json([
                        'ok'  => FALSE,
                        'msg' => 'El pago con tarjeta sigue abierto y no se pudo cancelar de forma segura. Inténtalo de nuevo.',
                    ], 503);
                }
            } elseif ($session_status !== 'expired') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago con tarjeta está en un estado que no permite cancelarlo de forma segura.',
                ], 409);
            }
        } elseif ($gateway_crypto && $legacy_ref !== '') {
            // A BTCPay invoice must be invalidated before the order is
            // cancelled, otherwise the customer could still pay a live link.
            $btcpay = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');
            if (!is_array($btcpay) || (int)($btcpay['pas_activo'] ?? 0) !== 1
                || !$this->btcpay_client->configuracion_api_valida($btcpay)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'La pasarela de criptomonedas no está disponible. La orden no se puede cancelar de forma segura en este momento.',
                ], 503);
            }

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $legacy_ref)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'La referencia de factura cripto almacenada no es válida. Contacta al administrador.',
                ], 409);
            }

            $store_path = rawurlencode((string)$btcpay['pas_store_id']);
            $invoice = $this->btcpay_client->request(
                'GET',
                '/api/v1/stores/' . $store_path . '/invoices/' . rawurlencode($legacy_ref),
                $btcpay
            );
            if (!is_array($invoice) || empty($invoice['id'])) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No se pudo verificar la factura cripto. Inténtalo de nuevo en un momento.',
                ], 502);
            }

            $invoice_metadata = isset($invoice['metadata']) && is_array($invoice['metadata'])
                ? $invoice['metadata']
                : [];
            $invoice_order = isset($invoice_metadata['orderId']) && is_scalar($invoice_metadata['orderId'])
                ? trim((string)$invoice_metadata['orderId'])
                : '';
            $invoice_currency = isset($invoice['currency']) && is_scalar($invoice['currency'])
                ? strtoupper(trim((string)$invoice['currency']))
                : '';
            $invoice_ok = is_scalar($invoice['id'])
                && (string)$invoice['id'] === $legacy_ref
                && isset($invoice['storeId'])
                && is_scalar($invoice['storeId'])
                && (string)$invoice['storeId'] === (string)$btcpay['pas_store_id']
                && $invoice_order === (string)$orden['mov_id']
                && $invoice_currency === strtoupper(trim((string)($orden['mov_moneda'] ?? '')))
                && $this->btcpay_client->montos_coinciden(
                    $orden['mov_precioplan'] ?? null,
                    $invoice['amount'] ?? null
                );

            if (!$invoice_ok) {
                if ($this->_btcpay_invoice_de_orden($invoice, $mov_id)
                    && (string)($invoice['status'] ?? '') === 'New') {
                    if (!$this->_invalidar_invoice_btcpay($btcpay, $store_path, $legacy_ref)) {
                        log_message('error', 'BTCPay: unable to invalidate mismatched invoice ' . $legacy_ref . ' for order ' . $mov_id . '.');
                    }
                }

                $this->bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'La factura de BTCPay no coincide con la orden #' . $mov_id . ' durante la cancelación. Factura: ' . $legacy_ref
                );
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago cripto no pudo validarse para esta orden. Contacta al administrador.',
                ], 409);
            }

            $invoice_status = isset($invoice['status']) && is_scalar($invoice['status'])
                ? (string)$invoice['status']
                : '';
            $additional_status = isset($invoice['additionalStatus']) && is_scalar($invoice['additionalStatus'])
                ? (string)$invoice['additionalStatus']
                : '';

            if ($invoice_status === 'Settled') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Este pago cripto ya fue liquidado y se está confirmando. La orden no se puede cancelar.',
                ], 409);
            }

            // Never invalidate an invoice that already holds customer funds.
            // A partial/late/over payment must be reconciled by an operator.
            $methods = $this->btcpay_client->request(
                'GET',
                '/api/v1/stores/' . $store_path . '/invoices/' . rawurlencode($legacy_ref) . '/payment-methods',
                $btcpay
            );
            if (!is_array($methods)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No se pudieron verificar los pagos cripto de esta factura. Inténtalo de nuevo en un momento.',
                ], 502);
            }

            $paid_total = 0.0;
            foreach ($methods as $method) {
                if (is_array($method)) {
                    $paid_total += max(0.0, (float)($method['paymentMethodPaid'] ?? 0));
                }
            }

            if ($paid_total <= 0 && isset($invoice['amountPaid']) && is_numeric($invoice['amountPaid'])) {
                $paid_total = max(0.0, (float)$invoice['amountPaid']);
            }

            if ($paid_total > 0
                || in_array($additional_status, ['PaidPartial', 'PaidOver', 'PaidLate'], TRUE)) {
                $this->bitacora(
                    'COMPRA_PAGO_CONFLICTO',
                    'Cancelación de BTCPay bloqueada porque la factura ' . $legacy_ref . ' ya tiene pagos para la orden #' . $mov_id
                );
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Esta factura cripto ya tiene un pago. Contacta al administrador para conciliarlo.',
                ], 409);
            }

            if ($invoice_status === 'Processing') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago cripto se está procesando y aún no se puede cancelar. Inténtalo más tarde.',
                ], 409);
            }

            if ($invoice_status === 'New') {
                $marked = $this->btcpay_client->request(
                    'POST',
                    '/api/v1/stores/' . $store_path . '/invoices/' . rawurlencode($legacy_ref) . '/status',
                    $btcpay,
                    ['status' => 'Invalid']
                );
                if (!is_array($marked) || (string)($marked['status'] ?? '') !== 'Invalid') {
                    return $this->json([
                        'ok'  => FALSE,
                        'msg' => 'La factura cripto sigue abierta y no se pudo cancelar de forma segura. Inténtalo de nuevo.',
                    ], 503);
                }
            } elseif (!in_array($invoice_status, ['Expired', 'Invalid'], TRUE)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago cripto está en un estado que no permite cancelarlo de forma segura.',
                ], 409);
            }
        }

        if (!$this->Buy_credits_model->cancelar_orden($mov_id, $id_usuario)) {
            // A concurrent webhook may have cancelled or approved the order
            // between our checks and the final UPDATE. Report the real state.
            $fresh = $this->Buy_credits_model->get_orden_por_id($mov_id, $id_usuario);
            $fresh_status = strtolower(trim((string)($fresh['mov_statuspag'] ?? '')));

            if ($fresh_status === 'cancelled') {
                $this->bitacora('COMPRA_ORDEN_CANCELAR', "Cancelación de orden confirmada concurrentemente: #{$mov_id}");
                return $this->json([
                    'ok'  => TRUE,
                    'msg' => "La orden #{$mov_id} se canceló correctamente.",
                ]);
            }

            if (in_array($fresh_status, ['approved', 'in_process', 'in_process_partial'], TRUE)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Esta orden cambió de estado mientras se cancelaba. Actualiza la página.',
                ], 409);
            }

            if (in_array($fresh_status, ['expired', 'rejected', 'refunded'], TRUE)) {
                return $this->json([
                    'ok'  => TRUE,
                    'msg'  => 'Esta orden ya no está pendiente de pago.',
                ]);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'Error al cancelar la orden.'], 500);
        }

        $this->bitacora('COMPRA_ORDEN_CANCELAR', "Orden de compra cancelada: #{$mov_id}");

        $this->json([
            'ok'  => TRUE,
            'msg' => "La orden #{$mov_id} se canceló correctamente.",
        ]);
    }

    /**
     * POST /BuyCredits/stripe_checkout (AJAX)
     * Creates or reuses a hosted Stripe Checkout Session for an order.
     *
     * The order snapshot in cab_movplanes is the only source of truth for the
     * amount and currency. Nothing sent by the browser is used as a price.
     */
    public function stripe_checkout()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para procesar pagos.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');
        $mov_id     = $this->_post_escalar('mov_id');

        $orden = $this->Buy_credits_model->get_orden_por_id($mov_id, $id_usuario);
        if (empty($orden)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Orden no encontrada o no pertenece a tu cuenta.'], 404);
        }

        if ((int)($orden['pla_paypal'] ?? 0) !== 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'Este plan no es elegible para pagos con tarjeta.'], 400);
        }

        if ((string)($orden['mov_status'] ?? '') !== 'A'
            || !in_array((string)($orden['mov_statuspag'] ?? ''), ['NoPagado', 'waiting'], TRUE)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Esta orden no está pendiente de pago con tarjeta.'], 400);
        }

        $order_amount   = (float)($orden['mov_precioplan'] ?? 0);
        $order_currency = strtoupper(trim((string)($orden['mov_moneda'] ?? '')));

        if ($order_amount <= 0
            || !$this->stripe_client->moneda_valida($order_currency)
            || $this->stripe_client->importe_a_centavos($order_amount, $order_currency) === NULL) {
            return $this->json(['ok' => FALSE, 'msg' => 'El monto o la moneda de la orden no son válidos. Crea una nueva orden.'], 422);
        }

        $stripe = $this->Configuracion_pasarelas_model->get_pasarela('stripe');
        if (!is_array($stripe) || (int)($stripe['pas_activo'] ?? 0) !== 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'Los pagos con tarjeta no están disponibles en este momento. Contacta al administrador.'], 503);
        }

        if (!$this->stripe_client->configuracion_webhook_valida($stripe)) {
            log_message('error', 'BuyCredits: Stripe configuration is missing, insecure, or its environment does not match the key mode.');
            return $this->json(['ok' => FALSE, 'msg' => 'Los pagos con tarjeta no están disponibles ahora. Contacta al administrador.'], 503);
        }

        $stored_session = trim((string)($orden['mov_stripe_session_id'] ?? ''));
        $legacy_ref     = trim((string)($orden['mov_idpago'] ?? ''));

        // Never create a second payable reference for an order that already
        // has one, even when the old reference came from the legacy Stripe
        // integration. Otherwise a customer could pay twice and only one
        // payment could ever be credited.
        if ($stored_session === '' && $legacy_ref !== '') {
            $this->bitacora(
                'COMPRA_PAGO_CONFLICTO',
                'Pago con tarjeta bloqueado porque la orden #' . $orden['mov_id'] . ' ya tiene una referencia de pago: ' . $legacy_ref
            );
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Ya hay un pago en curso para esta orden. Complétalo o contacta al administrador.',
            ], 409);
        }

        // Reuse an existing open Checkout Session when possible.
        if ($stored_session !== '') {
            $session = $this->stripe_client->recuperar_sesion_checkout($stripe, $stored_session);
            if (!is_array($session)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No se pudo verificar tu pago con tarjeta existente. Inténtalo de nuevo en un momento.',
                ], 502);
            }

            $session_status = isset($session['status']) && is_scalar($session['status'])
                ? (string)$session['status']
                : '';

            if (!$this->stripe_client->sesion_coincide($session, $orden, $order_amount, $order_currency)
                || !$this->stripe_client->modo_coincide($session, $stripe)) {
                if ($this->_stripe_sesion_de_orden($session, $orden['mov_id']) && $session_status === 'open') {
                    $expired = $this->stripe_client->expirar_sesion_checkout($stripe, $stored_session);
                    if (!is_array($expired) || (string)($expired['status'] ?? '') !== 'expired') {
                        log_message('error', 'Stripe: unable to expire mismatched session ' . $stored_session . ' for order ' . $orden['mov_id'] . '.');
                    }
                }

                $this->bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'La sesión de Stripe existente no coincide con la orden #' . $orden['mov_id'] . ' | Sesión: ' . $stored_session
                );
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago con tarjeta existente no pudo validarse para esta orden. Contacta al administrador.',
                ], 409);
            }

            $payment_status = isset($session['payment_status']) && is_scalar($session['payment_status'])
                ? (string)$session['payment_status']
                : '';

            if ($payment_status === 'paid' || $session_status === 'complete') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Este pago con tarjeta ya fue recibido y está pendiente de confirmación.',
                ], 409);
            }

            $checkout_url = isset($session['url']) && is_scalar($session['url'])
                ? (string)$session['url']
                : '';

            if ($session_status === 'open' && $this->_checkout_stripe_valido($checkout_url)) {
                return $this->json([
                    'ok'           => TRUE,
                    'checkout_url' => $checkout_url,
                    'msg'          => 'El pago seguro con tarjeta está listo.',
                ]);
            }

            if ($session_status !== 'expired') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El pago con tarjeta existente no es pagable. Contacta al administrador.',
                ], 409);
            }
            // Las sesiones expiradas se pueden reemplazar por una nueva.
        }

        $buyer_email = trim((string)($this->usuario['usu_correo'] ?? ''));
        if (!filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
            $buyer_email = '';
        }

        $plan_name = trim((string)($orden['mov_nomplan'] ?? 'Plan de créditos'));
        $plan_desc = trim((string)($orden['pla_descripcion'] ?? ''));
        if ($plan_desc === '') {
            $plan_desc = (int)($orden['mov_sms'] ?? 0) . ' créditos para tu cuenta.';
        }

        $success_url = base_url('comprar-creditos') . '?stripe=success&session_id={CHECKOUT_SESSION_ID}';
        $cancel_url  = base_url('comprar-creditos') . '?stripe=cancelled';

        $payload = [
            'mode'                 => 'payment',
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower($order_currency),
                    'unit_amount'  => $this->stripe_client->importe_a_centavos($order_amount, $order_currency),
                    'product_data' => [
                        'name'        => mb_substr($plan_name, 0, 120),
                        'description' => mb_substr($plan_desc, 0, 250),
                    ],
                ],
            ]],
            'client_reference_id'  => (string)$orden['mov_id'],
            'metadata'             => [
                'order_id'     => (string)$orden['mov_id'],
                'order_number' => (string)$orden['mov_id'],
                'plan_id'      => (string)$orden['mov_idplan'],
                'user_id'      => (string)$orden['mov_idusuario'],
            ],
            'payment_intent_data'  => [
                'metadata'    => ['order_id' => (string)$orden['mov_id']],
                'description' => mb_substr('Orden #' . $orden['mov_id'] . ' - ' . $plan_name, 0, 250),
            ],
            'success_url'          => $success_url,
            'cancel_url'           => $cancel_url,
            // Stripe requires byte-identical parameters when an idempotency
            // key is retried. Do not send a time-dependent expires_at here.
            // Sessions are expired explicitly when an order is cancelled.
            'billing_address_collection' => 'auto',
            'locale'               => 'auto',
            'submit_type'          => 'pay',
        ];

        if ($buyer_email !== '') {
            $payload['customer_email'] = $buyer_email;
        }

        // Deterministic idempotency: concurrent/retried requests for the same
        // order and previous session reference resolve to the same session.
        $idempotency_key = hash(
            'sha256',
            'stripe_checkout|' . (string)$orden['mov_id'] . '|' . number_format($order_amount, 2, '.', '')
            . '|' . $order_currency . '|' . $stored_session
        );

        $session = $this->stripe_client->crear_sesion_checkout($stripe, $payload, $idempotency_key);
        if (!is_array($session) || empty($session['id']) || empty($session['url'])) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo iniciar el pago con tarjeta. Inténtalo más tarde.'], 502);
        }

        $session_id   = is_scalar($session['id']) ? trim((string)$session['id']) : '';
        $checkout_url = is_scalar($session['url']) ? (string)$session['url'] : '';

        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $session_id)
            || !$this->_checkout_stripe_valido($checkout_url)
            || !$this->stripe_client->sesion_coincide($session, $orden, $order_amount, $order_currency)
            || !$this->stripe_client->modo_coincide($session, $stripe)) {
            $this->bitacora(
                'COMPRA_PAGO_FRAUDE',
                'La respuesta de la sesión de Stripe no coincide con la orden #' . $orden['mov_id']
                . ' | Sesión: ' . $session_id
                . ' | Expected: ' . number_format($order_amount, 2, '.', '') . ' ' . $order_currency
                . ' | Received: ' . (string)($session['amount_total'] ?? '') . ' ' . strtoupper((string)($session['currency'] ?? ''))
            );

            if (preg_match('/^cs_[A-Za-z0-9_]+$/', $session_id)) {
                $expired = $this->stripe_client->expirar_sesion_checkout($stripe, $session_id);
                if (!is_array($expired) || (string)($expired['status'] ?? '') !== 'expired') {
                    log_message('error', 'Stripe: CRITICAL unable to expire rejected session ' . $session_id . ' for order ' . $orden['mov_id'] . '.');
                }
            }

            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La respuesta del pago con tarjeta no coincide con esta orden. Contacta al administrador.',
            ], 502);
        }

        $new_session_status = isset($session['status']) && is_scalar($session['status'])
            ? (string)$session['status']
            : '';
        $new_payment_status = isset($session['payment_status']) && is_scalar($session['payment_status'])
            ? (string)$session['payment_status']
            : '';

        if ($new_session_status === 'complete' || $new_payment_status === 'paid') {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Este pago con tarjeta ya fue recibido y está pendiente de confirmación.',
            ], 409);
        }

        $payment_intent = isset($session['payment_intent']) && is_scalar($session['payment_intent'])
            ? trim((string)$session['payment_intent'])
            : '';

        if (!$this->Buy_credits_model->actualizar_sesion_stripe(
            $orden['mov_id'],
            $session_id,
            $payment_intent,
            $stored_session,
            $order_currency
        )) {
            // Another request may have bound the very same idempotent session
            // between our read and the optimistic update. In that case the
            // payment is valid and must not be expired.
            $fresh = $this->Buy_credits_model->get_orden_por_id($orden['mov_id']);
            $fresh_session = trim((string)($fresh['mov_stripe_session_id'] ?? ''));
            if ($fresh_session === $session_id) {
                if ((string)($fresh['mov_status'] ?? '') === 'A'
                    && in_array((string)($fresh['mov_statuspag'] ?? ''), ['NoPagado', 'waiting'], TRUE)) {
                    $this->bitacora(
                        'COMPRA_STRIPE_CHECKOUT',
                        'Vinculación concurrente aceptada para la sesión de Stripe ' . $session_id . ' en la orden #' . $orden['mov_id']
                    );
                    return $this->json([
                        'ok'           => TRUE,
                        'checkout_url' => $checkout_url,
                        'msg'          => 'El pago seguro con tarjeta está listo.',
                    ]);
                }

                if ($new_session_status === 'open') {
                    $expired = $this->stripe_client->expirar_sesion_checkout($stripe, $session_id);
                    if (!is_array($expired) || (string)($expired['status'] ?? '') !== 'expired') {
                        log_message('error', 'Stripe: unable to expire session ' . $session_id . ' after a non-payable order state.');
                    }
                }

                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Este pago con tarjeta ya fue recibido o se está procesando.',
                ], 409);
            }

            // The session was created but could not be stored. Expire it so a
            // concurrent cancellation cannot leave a live payable link behind.
            $expired = $this->stripe_client->expirar_sesion_checkout($stripe, $session_id);
            if (!is_array($expired) || (string)($expired['status'] ?? '') !== 'expired') {
                log_message('error', 'Stripe: CRITICAL unable to expire unbound session ' . $session_id . ' for order ' . $orden['mov_id'] . '.');
            }
            $this->bitacora(
                'COMPRA_INVOICE_RACE',
                'La sesión de Stripe ' . $session_id . ' no se vinculó a la orden #' . $orden['mov_id']
                . ' porque el estado de la orden o la vinculación de pago cambió concurrentemente.'
            );
            return $this->json(['ok' => FALSE, 'msg' => 'Esta orden ya no está pendiente de pago. Actualiza la página.'], 409);
        }

        $this->bitacora(
            'COMPRA_STRIPE_CHECKOUT',
            'Sesión de pago de Stripe creada: ' . $session_id . ' para la orden: #' . $orden['mov_id']
            . ' | Monto: ' . number_format($order_amount, 2, '.', '') . ' ' . $order_currency
        );

        $this->json([
            'ok'           => TRUE,
            'checkout_url' => $checkout_url,
            'msg'          => 'El pago seguro con tarjeta está listo.',
        ]);
    }

    /**
     * POST /BuyCredits/tabla (AJAX DataTables Server-Side)
     * Returns paginated purchase orders with search and sorting.
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para ver las órdenes.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');

        $draw       = max(0, (int)$this->input->post('draw'));
        $start      = max(0, (int)$this->input->post('start'));
        $length     = (int)$this->input->post('length');
        $length     = ($length > 0 && $length <= 100) ? $length : 10;
        $search     = mb_substr((string)$this->input->post('search[value]', TRUE), 0, 100);
        $estado     = strtolower(trim((string)$this->input->post('estado', TRUE)));
        $order_col  = (int)$this->input->post('order[0][column]');
        $order_dir  = (string)$this->input->post('order[0][dir]');

        if (!in_array($estado, ['unpaid', 'waiting', 'processing', 'paid', 'refunded', 'expired', 'cancelled', 'rejected'], TRUE)) {
            $estado = '';
        }

        $records_total    = $this->Buy_credits_model->count_todos($id_usuario);
        $records_filtered = $this->Buy_credits_model->count_filtrados($id_usuario, $search, $estado);
        $rows             = $this->Buy_credits_model->get_datatable($id_usuario, $start, $length, $search, $order_col, $order_dir, $estado);

        $data = $this->_formatear_filas_datatable($rows);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $records_total,
            'recordsFiltered' => $records_filtered,
            'data'            => $data,
        ]);
    }

    /**
     * Formats database rows for Metronic DataTables display.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_datatable(array $rows)
    {
        $salida = [];

        foreach ($rows as $r) {
            $mov_id        = htmlspecialchars((string)($r['mov_id'] ?? ''), ENT_QUOTES, 'UTF-8');
            $plan_id       = (int)($r['mov_idplan'] ?? 0);
            $nomplan       = htmlspecialchars((string)($r['mov_nomplan'] ?? ''), ENT_QUOTES, 'UTF-8');
            $descplan      = htmlspecialchars(trim((string)($r['pla_descripcion'] ?? '')), ENT_QUOTES, 'UTF-8');
            $sms           = (int)($r['mov_sms'] ?? 0);
            $precio        = number_format((float)($r['mov_precioplan'] ?? 0), 2, ',', '.');
            $moneda        = htmlspecialchars((string)($r['mov_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
            $pago_status   = strtolower(trim((string)($r['mov_statuspag'] ?? '')));
            $pago_status   = ($pago_status === '') ? 'nopagado' : $pago_status;
            $mov_status    = strtoupper(trim((string)($r['mov_status'] ?? '')));
            $fecha_compra  = trim((string)($r['mov_fechcomp'] ?? ''));
            $hora_compra   = trim((string)($r['mov_hora'] ?? ''));
            $fecha_pago    = trim((string)($r['mov_fechpago'] ?? ''));
            $hora_pago     = trim((string)($r['mov_horapag'] ?? ''));
            $tippago       = strtolower(trim((string)($r['mov_tippago'] ?? '')));
            $coin          = htmlspecialchars(trim((string)($r['mov_coin'] ?? '')), ENT_QUOTES, 'UTF-8');
            $admite_crypto = (int)($r['pla_crypto'] ?? 0) === 1;
            $admite_stripe = (int)($r['pla_paypal'] ?? 0) === 1;
            $stored_session = trim((string)($r['mov_stripe_session_id'] ?? ''));
            $referencia     = trim((string)($r['mov_idpago'] ?? ''));
            $es_stripe      = $tippago === 'stripe' || $stored_session !== '';
            $gateway_crypto = in_array($tippago, ['btcpay', 'btc_pay', 'crypto', 'bitcoin'], TRUE);
            $legacy_stripe  = $stored_session === '' && $es_stripe && $referencia !== '';
            $ref_desconocida = $stored_session === '' && $referencia !== ''
                && !$es_stripe && !$gateway_crypto;
            $plan_activo   = (int)($r['pla_status'] ?? 0) === 1 && (int)($r['pla_eliminado'] ?? 0) === 0;

            $fecha_compra = ($fecha_compra === '0000-00-00') ? '' : htmlspecialchars($fecha_compra, ENT_QUOTES, 'UTF-8');
            $fecha_pago   = ($fecha_pago === '0000-00-00') ? '' : htmlspecialchars($fecha_pago, ENT_QUOTES, 'UTF-8');
            $hora_compra  = htmlspecialchars($hora_compra, ENT_QUOTES, 'UTF-8');
            $hora_pago    = htmlspecialchars($hora_pago, ENT_QUOTES, 'UTF-8');

            $estado_pago = $this->_mapear_estado_pago($pago_status);
            $icon_badge  = '<i class="ki-duotone ' . $estado_pago['icon'] . ' fs-7 ' . $estado_pago['color'] . ' me-1" aria-hidden="true">'
                         . '<span class="path1"></span><span class="path2"></span><span class="path3"></span>'
                         . '</i>';
            $badge_estado = '<span class="badge ' . $estado_pago['badge'] . ' fw-bold px-2 py-1 text-nowrap">'
                          . $icon_badge . $estado_pago['label']
                          . '</span>';

            // Legacy PayPal orders may store the provider in either field. Never
            // combine them with the crypto label.
            $coin_lower  = strtolower($coin);
            $es_paypal   = $tippago === 'paypal-account'
                || strpos($tippago, 'paypal') !== false
                || strpos($coin_lower, 'paypal') !== false;
            $metodo_generico = '';
            $metodo_detalle  = '';

            if ($es_paypal) {
                $metodo_generico = 'PayPal';
            } elseif ($tippago === 'stripe') {
                $metodo_generico = 'Tarjeta';
            } elseif (in_array($tippago, ['btcpay', 'btc_pay', 'crypto', 'bitcoin'], TRUE)) {
                $metodo_generico = 'Cripto';
                $metodo_detalle  = $coin;
            } elseif ($tippago !== '' || $coin !== '') {
                $metodo_generico = 'Pago en Línea';
            }

            $metodo_partes = array_filter([$metodo_generico, $metodo_detalle], static function ($value) {
                return $value !== '';
            });
            $metodo_html = empty($metodo_partes)
                ? ''
                : '<span class="text-muted fs-8 text-nowrap">' . implode(' · ', $metodo_partes) . '</span>';

            $acciones = [];

            if ($this->stripe_disponible
                && $admite_stripe
                && $mov_status === 'A'
                && !$gateway_crypto
                && !$legacy_stripe
                && !$ref_desconocida
                && ($pago_status === 'nopagado' || ($pago_status === 'waiting' && $es_stripe))) {
                $card_title = ($pago_status === 'waiting') ? 'Reanudar pago con tarjeta' : 'Pagar con tarjeta';
                $acciones[] = '<button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-pagar-stripe" data-id="' . $mov_id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $card_title . '" aria-label="' . $card_title . '">'
                            . '<i class="ki-duotone ki-credit-cart fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>'
                            . '</button>';
            }

            if ($this->btcpay_disponible
                && $admite_crypto && !$es_stripe && !$ref_desconocida
                && in_array($mov_status, ['A', 'V'], TRUE)
                && in_array($pago_status, ['nopagado', 'waiting', 'in_process_partial'], TRUE)) {
                $pay_title = ($pago_status === 'in_process_partial') ? 'Completar pago cripto' : 'Pagar con criptomonedas';
                $acciones[] = '<button type="button" class="btn btn-sm btn-icon btn-light-warning shadow-xs btn-pagar-crypto" data-id="' . $mov_id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $pay_title . '" aria-label="' . $pay_title . '">'
                            . '<i class="ki-duotone ki-bitcoin fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>'
                            . '</button>';
            }

            if (in_array($pago_status, ['nopagado', 'waiting'], TRUE)
                && in_array($mov_status, ['A', 'V'], TRUE)
                && !$legacy_stripe && !$ref_desconocida) {
                $acciones[] = '<button type="button" class="btn btn-sm btn-icon btn-light-danger shadow-xs btn-cancelar-orden" data-id="' . $mov_id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancelar orden" aria-label="Cancelar orden">'
                            . '<i class="ki-duotone ki-trash fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>'
                            . '</button>';
            }

            if ($plan_id > 0 && $plan_activo && in_array($pago_status, ['approved', 'expired', 'cancelled', 'rejected'], TRUE)) {
                $acciones[] = '<button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-recomprar" data-id="' . $plan_id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Comprar de nuevo" aria-label="Comprar de nuevo">'
                            . '<i class="ki-duotone ki-arrows-circle fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>'
                            . '</button>';
            }

            $btn_accion = empty($acciones)
                ? '<span class="text-muted fs-8">—</span>'
                : '<div class="d-flex justify-content-end align-items-center gap-2">' . implode('', $acciones) . '</div>';

            $fecha_html = '<div class="d-flex flex-column">'
                        . '<span class="text-gray-800 fw-semibold fs-7 text-nowrap">' . ($fecha_compra !== '' ? $fecha_compra : '—') . '</span>'
                        . ($hora_compra !== '' ? '<span class="text-muted fs-8 text-nowrap">' . $hora_compra . '</span>' : '')
                        . ($pago_status === 'approved' && $fecha_pago !== ''
                            ? '<span class="text-success fs-8 text-nowrap mt-1">Pagada ' . $fecha_pago . ($hora_pago !== '' ? ' ' . $hora_pago : '') . '</span>'
                            : '')
                        . '</div>';

            $plan_html = '<div class="d-flex flex-column">'
                       . '<span class="fw-bolder text-gray-800 fs-7">' . ($nomplan !== '' ? $nomplan : '—') . '</span>'
                       . ($descplan !== ''
                           ? '<span class="text-muted fs-8 d-block text-truncate mw-200px" title="' . $descplan . '">' . $descplan . '</span>'
                           : '')
                       . '</div>';

            $salida[] = [
                'orden'    => '<div class="d-flex align-items-center">
                                  <div class="symbol symbol-35px symbol-circle bg-light-primary me-3">
                                      <i class="ki-duotone ki-bill fs-3 text-primary" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span></i>
                                  </div>
                                  <span class="fw-bold text-gray-900 fs-7 text-nowrap">#' . $mov_id . '</span>
                              </div>',
                'plan'     => $plan_html,
                'creditos' => '<span class="badge badge-light-primary fw-bold px-2 py-1 text-nowrap">
                                  <i class="ki-duotone ki-diamond fs-6 text-primary me-1" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                                  ' . number_format($sms, 0, ',', '.') . ' Créditos
                              </span>',
                'monto'    => '<div class="d-flex align-items-baseline justify-content-end gap-1 text-nowrap">
                                  <span class="fw-bolder text-gray-900 fs-6">' . $precio . '</span>
                                  <span class="badge badge-light-primary fw-bold fs-8">' . $moneda . '</span>
                              </div>',
                'estado'   => '<div class="d-flex flex-column align-items-center gap-1">' . $badge_estado . $metodo_html . '</div>',
                'fecha'    => $fecha_html,
                'acciones' => $btn_accion,
            ];
        }

        return $salida;
    }

    /**
     * Reads a scalar POST value without triggering array-to-string warnings.
     *
     * @param  string $key
     * @return string
     */
    private function _post_escalar($key)
    {
        $value = $this->input->post($key, TRUE);

        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * Checks whether a La sesión de Stripe was created for a specific order.
     *
     * @param  array  $session
     * @param  string $order_id
     * @return bool
     */
    private function _stripe_sesion_de_orden(array $session, $order_id)
    {
        $order_id = (string)$order_id;
        $client   = isset($session['client_reference_id']) && is_scalar($session['client_reference_id'])
            ? trim((string)$session['client_reference_id'])
            : '';
        $metadata = isset($session['metadata']) && is_array($session['metadata'])
            ? $session['metadata']
            : [];
        $meta_order = isset($metadata['order_id']) && is_scalar($metadata['order_id'])
            ? trim((string)$metadata['order_id'])
            : '';

        return $order_id !== '' && ($client === $order_id || $meta_order === $order_id);
    }

    /**
     * Checks whether a BTCPay invoice was created for a specific order.
     *
     * @param  array  $invoice
     * @param  string $order_id
     * @return bool
     */
    private function _btcpay_invoice_de_orden(array $invoice, $order_id)
    {
        $order_id = (string)$order_id;
        $metadata = isset($invoice['metadata']) && is_array($invoice['metadata'])
            ? $invoice['metadata']
            : [];
        $invoice_order = isset($metadata['orderId']) && is_scalar($metadata['orderId'])
            ? trim((string)$metadata['orderId'])
            : '';

        return $order_id !== '' && $invoice_order === $order_id;
    }

    /**
     * Invalidates an unbound BTCPay invoice so it cannot be paid later.
     *
     * @param  array  $btcpay
     * @param  string $store_path
     * @param  string $invoice_id
     * @return bool
     */
    private function _invalidar_invoice_btcpay(array $btcpay, $store_path, $invoice_id)
    {
        $invoice_id = trim((string)$invoice_id);
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $invoice_id)) {
            return FALSE;
        }

        $invalidated = $this->btcpay_client->request(
            'POST',
            '/api/v1/stores/' . $store_path . '/invoices/' . rawurlencode($invoice_id) . '/status',
            $btcpay,
            ['status' => 'Invalid']
        );

        return is_array($invalidated) && (string)($invalidated['status'] ?? '') === 'Invalid';
    }

    /**
     * Validates a hosted Stripe Checkout URL and its host.
     *
     * @param  string $url
     * @return bool
     */
    private function _checkout_stripe_valido($url)
    {
        if (!$this->stripe_client->es_url_https($url)) {
            return FALSE;
        }

        $host = strtolower(rtrim((string)(parse_url((string)$url, PHP_URL_HOST) ?? ''), '.'));

        return $host === 'checkout.stripe.com';
    }

    /**
     * Validates a BTCPay checkout URL against the configured store host.
     *
     * @param  string $url
     * @param  string $base
     * @return bool
     */
    private function _checkout_btcpay_valido($url, $base)
    {
        if (!$this->btcpay_client->es_url_https($url)) {
            return FALSE;
        }

        $url_host  = strtolower(rtrim((string)(parse_url((string)$url, PHP_URL_HOST) ?? ''), '.'));
        $base_host = strtolower(rtrim((string)(parse_url((string)$base, PHP_URL_HOST) ?? ''), '.'));

        return $url_host !== '' && $base_host !== '' && hash_equals($base_host, $url_host);
    }

    /**
     * Maps a stored payment status to its user-facing badge configuration.
     *
     * @param  string $status
     * @return array
     */
    private function _mapear_estado_pago($status)
    {
        $estados = [
            'nopagado'           => ['label' => 'Impagada',           'badge' => 'badge-light-danger',    'icon' => 'ki-cross-circle', 'color' => 'text-danger'],
            'waiting'            => ['label' => 'Pendiente de Pago', 'badge' => 'badge-light-primary',   'icon' => 'ki-time',         'color' => 'text-primary'],
            'in_process'         => ['label' => 'Procesando',       'badge' => 'badge-light-info',      'icon' => 'ki-loading',      'color' => 'text-info'],
            'in_process_partial' => ['label' => 'Pago Parcial',  'badge' => 'badge-light-info',      'icon' => 'ki-loading',      'color' => 'text-info'],
            'approved'           => ['label' => 'Pagada',             'badge' => 'badge-light-success',   'icon' => 'ki-check-circle', 'color' => 'text-success'],
            'refunded'           => ['label' => 'Reembolsada',         'badge' => 'badge-light-dark',      'icon' => 'ki-information',  'color' => 'text-muted'],
            'expired'            => ['label' => 'Expirada',          'badge' => 'badge-light-warning',   'icon' => 'ki-information',  'color' => 'text-warning'],
            'cancelled'          => ['label' => 'Cancelada',        'badge' => 'badge-light-secondary', 'icon' => 'ki-minus-circle', 'color' => 'text-muted'],
            'rejected'           => ['label' => 'Rechazada',         'badge' => 'badge-light-danger',    'icon' => 'ki-cross-circle', 'color' => 'text-danger'],
        ];

        return $estados[$status] ?? [
            'label' => 'Desconocido',
            'badge' => 'badge-light-dark',
            'icon'  => 'ki-information',
            'color' => 'text-muted',
        ];
    }

    /**
     * GET /BuyCredits/mis_ordenes_json (AJAX)
     * Returns updated list of user invoices for reactive UI updates.
     */
    public function mis_ordenes_json()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para ver las órdenes.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');
        $ordenes = $this->Buy_credits_model->get_historial_ordenes($id_usuario, 20);

        // Do not expose the underlying payment provider or invoice references.
        foreach ($ordenes as &$orden) {
            unset(
                $orden['mov_tippago'],
                $orden['mov_idpago'],
                $orden['mov_refid'],
                $orden['mov_stripe_session_id'],
                $orden['mov_stripe_payment_intent']
            );
        }
        unset($orden);

        $this->json([
            'ok'      => TRUE,
            'ordenes' => $ordenes,
        ]);
    }

    /**
     * POST /BuyCredits/pay_crypto (AJAX)
     * Generates or retrieves an active cryptocurrency invoice checkout URL.
     */
    public function pay_crypto()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para procesar pagos.');

        $id_usuario = (string)($this->usuario['usu_id'] ?? '');
        $mov_id     = $this->_post_escalar('mov_id');

        $orden = $this->Buy_credits_model->get_orden_por_id($mov_id, $id_usuario);
        if (empty($orden)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Orden no encontrada o no pertenece a tu cuenta.'], 404);
        }

        if ((int)($orden['pla_crypto'] ?? 0) !== 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'Este plan no es elegible para pagos con criptomonedas.'], 400);
        }

        if (!in_array($orden['mov_statuspag'], ['NoPagado', 'waiting', 'in_process_partial'], TRUE)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Esta orden no está pendiente de pago.'], 400);
        }

        if (!in_array(strtoupper((string)($orden['mov_status'] ?? '')), ['A', 'V'], TRUE)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Esta orden no está activa y no se puede pagar.'], 400);
        }

        $gateway         = strtolower(trim((string)($orden['mov_tippago'] ?? '')));
        $gateway_crypto  = in_array($gateway, ['btcpay', 'btc_pay', 'crypto', 'bitcoin'], TRUE);
        $existing_ref    = trim((string)($orden['mov_idpago'] ?? ''));

        if (trim((string)($orden['mov_stripe_session_id'] ?? '')) !== ''
            || trim((string)($orden['mov_stripe_payment_intent'] ?? '')) !== ''
            || $gateway === 'stripe') {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Ya hay un pago con tarjeta en curso para esta orden. Complétalo o cancélalo primero.',
            ], 409);
        }

        // Never let a crypto invoice overwrite a reference from another
        // gateway (for example a legacy PayPal order). Only a known BTCPay
        // invoice may be resumed.
        if ($existing_ref !== '' && !$gateway_crypto) {
            $this->bitacora(
                'COMPRA_PAGO_CONFLICTO',
                'Pago cripto bloqueado porque la orden #' . $orden['mov_id'] . ' ya tiene una referencia de pago: ' . $existing_ref
            );
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Ya hay un pago en curso para esta orden. Complétalo o contacta al administrador.',
            ], 409);
        }

        $order_currency = strtoupper(trim((string)($orden['mov_moneda'] ?? '')));
        $order_amount   = (float)($orden['mov_precioplan'] ?? 0);

        if ($order_amount <= 0 || !$this->btcpay_client->moneda_valida($order_currency)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El monto o la moneda de la orden no son válidos. Crea una nueva orden.'], 422);
        }

        $this->load->model('Configuracion_pasarelas_model');
        $btcpay = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');

        if (!is_array($btcpay) || (int)($btcpay['pas_activo'] ?? 0) !== 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'Los pagos con criptomonedas no están disponibles en este momento. Contacta al administrador.'], 503);
        }

        if (!$this->btcpay_client->configuracion_webhook_valida($btcpay)) {
            log_message('error', 'BuyCredits: BTCPay configuration is missing, insecure, or lacks a webhook secret.');
            return $this->json(['ok' => FALSE, 'msg' => 'Los pagos con criptomonedas no están disponibles ahora. Contacta al administrador.'], 503);
        }

        $store_path = rawurlencode((string)$btcpay['pas_store_id']);

        // Expected invoice binding at the time we read the order. The model
        // uses it as an optimistic lock so two concurrent requests cannot
        // overwrite each other's invoice.
        $expected_invoice = $existing_ref;

        // Check if an existing invoice is still active and valid.
        if ($existing_ref !== '') {
            $invoice_id = $existing_ref;

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $invoice_id)) {
                $this->bitacora('COMPRA_PAGO_FRAUDE', 'Referencia de factura almacenada malformada para la orden #' . $orden['mov_id']);
                return $this->json(['ok' => FALSE, 'msg' => 'La referencia de factura almacenada no es válida. Contacta al administrador.'], 409);
            }

            $existing_invoice = $this->btcpay_client->request(
                'GET',
                '/api/v1/stores/' . $store_path . '/invoices/' . rawurlencode($invoice_id),
                $btcpay
            );

            if (!is_array($existing_invoice) || empty($existing_invoice['id'])) {
                return $this->json(['ok' => FALSE, 'msg' => 'No se pudo verificar tu pago existente. Inténtalo más tarde.'], 502);
            }

            $existing_order_id = isset($existing_invoice['metadata']['orderId']) && is_scalar($existing_invoice['metadata']['orderId'])
                ? trim((string)$existing_invoice['metadata']['orderId'])
                : '';
            $existing_currency = isset($existing_invoice['currency']) && is_scalar($existing_invoice['currency'])
                ? strtoupper(trim((string)$existing_invoice['currency']))
                : '';
            $existing_amount_ok = $this->btcpay_client->montos_coinciden(
                $orden['mov_precioplan'],
                $existing_invoice['amount'] ?? null
            );

            $identity_ok = is_scalar($existing_invoice['id'])
                && (string)$existing_invoice['id'] === $invoice_id
                && isset($existing_invoice['storeId'])
                && is_scalar($existing_invoice['storeId'])
                && (string)$existing_invoice['storeId'] === (string)$btcpay['pas_store_id']
                && $existing_order_id === (string)$orden['mov_id']
                && $existing_currency === $order_currency
                && $existing_amount_ok;

            $existing_status = isset($existing_invoice['status']) && is_scalar($existing_invoice['status'])
                ? (string)$existing_invoice['status']
                : '';

            if (!$identity_ok) {
                if ($this->_btcpay_invoice_de_orden($existing_invoice, $orden['mov_id'])
                    && $existing_status === 'New') {
                    if (!$this->_invalidar_invoice_btcpay($btcpay, $store_path, $invoice_id)) {
                        log_message('error', 'BTCPay: unable to invalidate mismatched invoice ' . $invoice_id . ' for order ' . $orden['mov_id'] . '.');
                    }
                }

                $this->bitacora(
                    'COMPRA_PAGO_FRAUDE',
                    'La factura de BTCPay existente no coincide con la orden #' . $orden['mov_id'] . ' | Factura: ' . $invoice_id
                );
                return $this->json(['ok' => FALSE, 'msg' => 'La factura existente no pudo validarse para esta orden. Contacta al administrador.'], 409);
            }
            $existing_url = isset($existing_invoice['checkoutLink']) && is_scalar($existing_invoice['checkoutLink'])
                ? (string)$existing_invoice['checkoutLink']
                : '';

            if (in_array($existing_status, ['New', 'Processing'], TRUE)
                && $this->_checkout_btcpay_valido($existing_url, (string)$btcpay['pas_url'])) {
                return $this->json([
                    'ok'           => TRUE,
                    'checkout_url' => $existing_url,
                    'invoice_id'   => $invoice_id,
                ]);
            }

            if ($existing_status === 'Settled') {
                return $this->json(['ok' => FALSE, 'msg' => 'Esta factura ya fue liquidada. Tu pago está pendiente de confirmación.'], 409);
            }

            if (!in_array($existing_status, ['Expired', 'Invalid'], TRUE)) {
                return $this->json(['ok' => FALSE, 'msg' => 'La factura existente no es pagable. Contacta al administrador.'], 409);
            }

            // Las facturas expiradas o inválidas se pueden reemplazar por una nueva.
        }

        // Build Greenfield API invoice request using the immutable order snapshot.
        $buyer_name = trim(($this->usuario['usu_nombre'] ?? '') . ' ' . ($this->usuario['usu_apellido'] ?? ''));
        if ($buyer_name === '') {
            $buyer_name = (string)($this->usuario['usu_usuario'] ?? 'Customer');
        }

        $buyer_country = strtoupper(trim((string)($orden['mov_pais'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $buyer_country)) {
            $buyer_country = 'US';
        }

        $amount = number_format(round($order_amount, 2), 2, '.', '');

        $payload = [
            'amount'   => $amount,
            'currency' => $order_currency,
            'metadata' => [
                'orderId'      => (string)$orden['mov_id'],
                'itemCode'     => (string)$orden['mov_idplan'],
                'itemDesc'     => (string)$orden['mov_nomplan'] . ' incluye ' . (int)$orden['mov_sms'] . ' créditos',
                'buyerName'    => $buyer_name,
                'buyerEmail'   => (string)($this->usuario['usu_correo'] ?? ''),
                'buyerCountry' => $buyer_country,
            ],
            'checkout' => [
                'speedPolicy'           => in_array(($btcpay['pas_speed_policy'] ?? ''), ['HighSpeed', 'MediumSpeed', 'LowSpeed'], TRUE)
                    ? $btcpay['pas_speed_policy']
                    : 'MediumSpeed',
                'paymentMethods'        => ['BTC', 'LTC'],
                'expirationMinutes'     => max(5, min(1440, (int)($btcpay['pas_expiration_minutes'] ?: 60))),
                'monitoringMinutes'     => max(5, min(1440, (int)($btcpay['pas_expiration_minutes'] ?: 60))),
                'paymentTolerance'      => 0,
                'redirectURL'           => base_url('comprar-creditos'),
                'redirectAutomatically' => FALSE,
                'defaultLanguage'       => 'es-ES',
            ],
        ];

        $new_invoice = $this->btcpay_client->request(
            'POST',
            '/api/v1/stores/' . $store_path . '/invoices',
            $btcpay,
            $payload
        );

        if (!is_array($new_invoice) || empty($new_invoice['id']) || empty($new_invoice['checkoutLink'])) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo iniciar el pago cripto. Inténtalo más tarde.'], 502);
        }

        if (!is_scalar($new_invoice['id'])
            || !preg_match('/^[A-Za-z0-9_-]+$/', (string)$new_invoice['id'])
            || !is_scalar($new_invoice['checkoutLink'])
            || !$this->_checkout_btcpay_valido((string)$new_invoice['checkoutLink'], (string)$btcpay['pas_url'])) {
            if (is_scalar($new_invoice['id'])
                && !$this->_invalidar_invoice_btcpay($btcpay, $store_path, (string)$new_invoice['id'])) {
                log_message('error', 'BTCPay: CRITICAL unable to invalidate rejected invoice ' . (string)$new_invoice['id'] . ' for order ' . $orden['mov_id'] . '.');
            }
            log_message('error', 'BuyCredits: BTCPay returned an invalid invoice id or non-HTTPS checkout link.');
            return $this->json(['ok' => FALSE, 'msg' => 'El servicio de pago devolvió una respuesta inválida. Inténtalo de nuevo.'], 502);
        }

        $returned_metadata = isset($new_invoice['metadata']) && is_array($new_invoice['metadata'])
            ? $new_invoice['metadata']
            : [];
        $returned_order_id = isset($returned_metadata['orderId']) && is_scalar($returned_metadata['orderId'])
            ? trim((string)$returned_metadata['orderId'])
            : '';
        $returned_currency = isset($new_invoice['currency']) && is_scalar($new_invoice['currency'])
            ? strtoupper(trim((string)$new_invoice['currency']))
            : '';
        $returned_amount_ok = $this->btcpay_client->montos_coinciden($order_amount, $new_invoice['amount'] ?? null);
        $returned_store_ok = isset($new_invoice['storeId'])
            && is_scalar($new_invoice['storeId'])
            && (string)$new_invoice['storeId'] === (string)$btcpay['pas_store_id'];
        $returned_status = isset($new_invoice['status']) && is_scalar($new_invoice['status'])
            ? (string)$new_invoice['status']
            : '';

        if ($returned_order_id !== (string)$orden['mov_id']
            || $returned_currency !== $order_currency
            || !$returned_amount_ok
            || !$returned_store_ok
            || !in_array($returned_status, ['New', 'Processing'], TRUE)) {
            if (!$this->_invalidar_invoice_btcpay($btcpay, $store_path, (string)$new_invoice['id'])) {
                log_message('error', 'BTCPay: CRITICAL unable to invalidate mismatched invoice ' . (string)$new_invoice['id'] . ' for order ' . $orden['mov_id'] . '.');
            }

            $this->bitacora(
                'COMPRA_PAGO_FRAUDE',
                'La respuesta de la factura de BTCPay no coincide con la orden #' . $orden['mov_id']
                . ' | Factura: ' . (string)$new_invoice['id']
                . ' | Expected: ' . $amount . ' ' . $order_currency
                . ' | Received: ' . (string)($new_invoice['amount'] ?? '') . ' ' . $returned_currency
            );
            return $this->json(['ok' => FALSE, 'msg' => 'La respuesta del pago no coincide con esta orden. Contacta al administrador.'], 502);
        }

        // Store the invoice only if the order is still pending and its binding
        // has not changed since we read it (optimistic lock).
        if (!$this->Buy_credits_model->actualizar_invoice_btcpay($orden['mov_id'], $new_invoice['id'], $expected_invoice)) {
            // The invoice is payable even though it is not bound. Invalidate it
            // so a concurrent state change cannot leave an orphan checkout.
            if (!$this->_invalidar_invoice_btcpay($btcpay, $store_path, (string)$new_invoice['id'])) {
                log_message('error', 'BTCPay: CRITICAL unable to invalidate unbound invoice ' . (string)$new_invoice['id'] . ' for order ' . $orden['mov_id'] . '.');
            }

            $this->bitacora(
                'COMPRA_INVOICE_RACE',
                'La factura ' . (string)$new_invoice['id'] . ' no se vinculó a la orden #' . $orden['mov_id'] . ' porque el estado de la orden o la vinculación de la factura cambió concurrentemente.'
            );
            return $this->json(['ok' => FALSE, 'msg' => 'Esta orden ya no está pendiente de pago. Actualiza la página.'], 409);
        }

        $this->bitacora(
            'COMPRA_CRYPTO_INVOICE',
            'Factura de BTCPay creada: ' . (string)$new_invoice['id'] . ' para la orden: #' . $orden['mov_id']
            . ' | Monto: ' . $amount . ' ' . $order_currency
        );

        $this->json([
            'ok'           => TRUE,
            'checkout_url' => (string)$new_invoice['checkoutLink'],
            'invoice_id'   => (string)$new_invoice['id'],
            'msg'          => 'Factura generada correctamente.',
        ]);
    }
}

