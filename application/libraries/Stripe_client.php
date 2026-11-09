<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stripe_client
 * -------------------------------------------------------------------------
 * Minimal, hardened Stripe API client used by the hosted Checkout flow.
 *
 * Security rules:
 * - Only talks to https://api.stripe.com over TLS with peer verification.
 * - Never follows redirects and never accepts absolute/foreign endpoints.
 * - Sends the secret key only in the Authorization header, never in URLs.
 * - Does not log secrets, raw payloads or full API error bodies.
 * - Verifies Stripe webhook signatures (t=...,v1=...) with hash_equals()
 *   and a bounded timestamp tolerance to reduce replay risk.
 * - Converts amounts to the smallest currency unit using integer math.
 *
 * The official Stripe SDK is intentionally not required. This keeps the
 * deployment free of a new Composer dependency and makes the security
 * boundary auditable in a single file.
 */
class Stripe_client
{
    const API_BASE             = 'https://api.stripe.com';
    const MAX_RESPONSE_BYTES   = 2097152; // 2 MB
    const CONNECT_TIMEOUT      = 10;
    const REQUEST_TIMEOUT      = 30;
    const WEBHOOK_TOLERANCE    = 300;     // 5 minutes

    private $last_status = 0;
    private $last_error  = '';

    /**
     * Validates the minimum configuration required to call the Stripe API.
     *
     * @param  array $config
     * @return bool
     */
    public function configuracion_api_valida(array $config)
    {
        $api_key   = trim((string)($config['pas_api_key'] ?? ''));
        $ambiente  = strtolower(trim((string)($config['pas_ambiente'] ?? '')));

        if (!$this->clave_api_valida($api_key)) {
            return FALSE;
        }

        return $this->ambiente_coincide($api_key, $ambiente);
    }

    /**
     * Validates the configuration required to verify webhooks.
     *
     * @param  array $config
     * @return bool
     */
    public function configuracion_webhook_valida(array $config)
    {
        return $this->configuracion_api_valida($config)
            && $this->clave_webhook_valida((string)($config['pas_webhook_secret'] ?? ''));
    }

    /**
     * Checks the shape of a Stripe secret/restricted key.
     *
     * @param  string $api_key
     * @return bool
     */
    public function clave_api_valida($api_key)
    {
        $api_key = trim((string)$api_key);

        return (bool)preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]{8,}$/', $api_key);
    }

    /**
     * Checks the shape of a Stripe webhook signing secret.
     *
     * @param  string $secret
     * @return bool
     */
    public function clave_webhook_valida($secret)
    {
        // Stripe uses the endpoint secret verbatim as the HMAC key. Do not
        // assume an alphanumeric-only body: base64-style characters such as
        // +, / and = are valid and must be accepted.
        return (bool)preg_match('/^whsec_[\x21-\x7E]{8,}$/', trim((string)$secret));
    }

    /**
     * Prevents using live keys in sandbox mode and test keys in production.
     *
     * @param  string $api_key
     * @param  string $ambiente
     * @return bool
     */
    public function ambiente_coincide($api_key, $ambiente)
    {
        $ambiente = strtolower(trim((string)$ambiente));
        if (!in_array($ambiente, ['sandbox', 'production'], TRUE)) {
            return FALSE;
        }

        $es_live = (bool)preg_match('/^(sk|rk)_live_/', trim((string)$api_key));

        return ($ambiente === 'production') === $es_live;
    }

    /**
     * Validates a supported ISO currency for Stripe Checkout.
     *
     * @param  string $currency
     * @return bool
     */
    public function moneda_valida($currency)
    {
        $currency = strtoupper(trim((string)$currency));

        return (bool)preg_match('/^[A-Z]{3}$/', $currency);
    }

    /**
     * Converts a decimal amount to Stripe's smallest currency unit.
     *
     * Stripe uses zero-decimal currencies (JPY, KRW, ...), three-decimal
     * currencies (BHD, JOD, KWD, OMR, TND) and standard two-decimal ones.
     *
     * @param  mixed  $amount
     * @param  string $currency
     * @return int|null
     */
    public function importe_a_centavos($amount, $currency)
    {
        if (!is_numeric($amount)) {
            return NULL;
        }

        $currency = strtoupper(trim((string)$currency));
        if (!$this->moneda_valida($currency)) {
            return NULL;
        }

        $amount = (float)$amount;
        if ($amount <= 0) {
            return NULL;
        }

        $zero_decimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        $three_decimal = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

        if (in_array($currency, $zero_decimal, TRUE)) {
            $factor = 1;
        } elseif (in_array($currency, $three_decimal, TRUE)) {
            $factor = 1000;
        } else {
            $factor = 100;
        }

        $minor = (int)round($amount * $factor);

        // Stripe rejects zero and amounts above the documented maximum.
        if ($minor <= 0 || $minor > 99999999) {
            return NULL;
        }

        return $minor;
    }

    /**
     * Verifies a Stripe-Signature header against the raw request body.
     *
     * Header format: t=timestamp,v1=signature[,v1=signature...]
     *
     * @param  string $payload
     * @param  string $signature_header
     * @param  string $secret
     * @param  int    $tolerance
     * @return bool
     */
    public function verificar_webhook($payload, $signature_header, $secret, $tolerance = self::WEBHOOK_TOLERANCE)
    {
        $payload          = (string)$payload;
        $signature_header = trim((string)$signature_header);
        $secret           = trim((string)$secret);

        if ($payload === '' || $signature_header === '' || !$this->clave_webhook_valida($secret)) {
            return FALSE;
        }

        $timestamp  = NULL;
        $signatures = [];

        foreach (explode(',', $signature_header) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, '=') === FALSE) {
                continue;
            }

            list($key, $value) = explode('=', $part, 2);
            $key   = trim($key);
            $value = trim($value);

            if ($key === 't') {
                if (!preg_match('/^[0-9]{1,12}$/', $value)) {
                    return FALSE;
                }
                $timestamp = (int)$value;
            } elseif ($key === 'v1') {
                if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
                    return FALSE;
                }
                $signatures[] = strtolower($value);
            }
        }

        if ($timestamp === NULL || empty($signatures)) {
            return FALSE;
        }

        $tolerance = max(0, (int)$tolerance);
        if (abs(time() - $timestamp) > $tolerance) {
            return FALSE;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Creates a hosted Stripe Checkout Session.
     *
     * @param  array  $config
     * @param  array  $payload
     * @param  string $idempotency_key
     * @return array|null
     */
    public function crear_sesion_checkout(array $config, array $payload, $idempotency_key = '')
    {
        return $this->request('POST', '/v1/checkout/sessions', $config, $payload, $idempotency_key);
    }

    /**
     * Retrieves a Checkout Session from Stripe.
     *
     * @param  array  $config
     * @param  string $session_id
     * @return array|null
     */
    public function recuperar_sesion_checkout(array $config, $session_id)
    {
        $session_id = trim((string)$session_id);
        if (!$this->_referencia_stripe_valida($session_id, 'cs_')) {
            return NULL;
        }

        return $this->request('GET', '/v1/checkout/sessions/' . rawurlencode($session_id), $config);
    }

    /**
     * Expires an open Checkout Session so it cannot be paid later.
     *
     * @param  array  $config
     * @param  string $session_id
     * @return array|null
     */
    public function expirar_sesion_checkout(array $config, $session_id)
    {
        $session_id = trim((string)$session_id);
        if (!$this->_referencia_stripe_valida($session_id, 'cs_')) {
            return NULL;
        }

        return $this->request('POST', '/v1/checkout/sessions/' . rawurlencode($session_id) . '/expire', $config);
    }

    /**
     * Checks that a Checkout Session belongs to the given order and amount.
     *
     * @param  array  $session
     * @param  array  $order
     * @param  mixed  $amount
     * @param  string $currency
     * @return bool
     */
    public function sesion_coincide(array $session, array $order, $amount, $currency)
    {
        $order_id = (string)($order['mov_id'] ?? '');
        if ($order_id === '') {
            return FALSE;
        }

        $session_id = isset($session['id']) && is_scalar($session['id'])
            ? trim((string)$session['id'])
            : '';
        $client_reference = isset($session['client_reference_id']) && is_scalar($session['client_reference_id'])
            ? trim((string)$session['client_reference_id'])
            : '';
        $metadata = isset($session['metadata']) && is_array($session['metadata'])
            ? $session['metadata']
            : [];
        $metadata_order = isset($metadata['order_id']) && is_scalar($metadata['order_id'])
            ? trim((string)$metadata['order_id'])
            : '';
        $session_currency = isset($session['currency']) && is_scalar($session['currency'])
            ? strtoupper(trim((string)$session['currency']))
            : '';
        $session_mode = isset($session['mode']) && is_scalar($session['mode'])
            ? strtolower(trim((string)$session['mode']))
            : '';
        $expected_minor = $this->importe_a_centavos($amount, $currency);

        if (!$this->_referencia_stripe_valida($session_id, 'cs_')
            || $client_reference !== $order_id
            || $metadata_order !== $order_id
            || $session_mode !== 'payment'
            || $expected_minor === NULL
            || $session_currency !== strtoupper(trim((string)$currency))
            || !isset($session['amount_total'])
            || !is_numeric($session['amount_total'])
            || (int)$session['amount_total'] !== $expected_minor) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Checks that the Stripe object mode matches the configured environment.
     *
     * @param  array $object
     * @param  array $config
     * @return bool
     */
    public function modo_coincide(array $object, array $config)
    {
        if (!array_key_exists('livemode', $object)) {
            return FALSE;
        }

        $livemode = (bool)$object['livemode'];
        $ambiente = strtolower(trim((string)($config['pas_ambiente'] ?? '')));

        return ($ambiente === 'production') === $livemode;
    }

    /**
     * Validates that a URL is HTTPS, has a host and no embedded credentials.
     *
     * @param  string $url
     * @return bool
     */
    public function es_url_https($url)
    {
        $url = trim((string)$url);
        if ($url === '' || preg_match('/[\x00-\x20]/', $url)) {
            return FALSE;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return FALSE;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host   = (string)($parts['host'] ?? '');

        if ($scheme !== 'https' || $host === '') {
            return FALSE;
        }

        return !isset($parts['user']) && !isset($parts['pass']);
    }

    /**
     * Executes a request against the Stripe API.
     *
     * @param  string $method
     * @param  string $endpoint
     * @param  array  $config
     * @param  array  $params
     * @param  string $idempotency_key
     * @return array|null
     */
    public function request($method, $endpoint, array $config, array $params = [], $idempotency_key = '')
    {
        $this->last_status = 0;
        $this->last_error  = '';

        $method = strtoupper(trim((string)$method));
        if (!in_array($method, ['GET', 'POST'], TRUE)) {
            return $this->_fail('Unsupported HTTP method.');
        }

        if (!$this->configuracion_api_valida($config)) {
            return $this->_fail('Invalid or incomplete Stripe configuration.');
        }

        if (!$this->_endpoint_valido($endpoint)) {
            return $this->_fail('Invalid Stripe API endpoint.');
        }

        $url = self::API_BASE . $endpoint;
        $body = '';

        if ($method === 'GET') {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            }
        } else {
            $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Authorization: Bearer ' . trim((string)$config['pas_api_key']),
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Expect:',
        ];

        $idempotency_key = trim((string)$idempotency_key);
        if ($method === 'POST' && $idempotency_key !== '') {
            if (!preg_match('/^[A-Za-z0-9:_\-]{8,255}$/', $idempotency_key)) {
                return $this->_fail('Invalid idempotency key.');
            }
            $headers[] = 'Idempotency-Key: ' . $idempotency_key;
        }

        if (function_exists('curl_init')) {
            return $this->_request_curl($method, $url, $headers, $body);
        }

        return $this->_request_stream($method, $url, $headers, $body);
    }

    /**
     * @return int HTTP status of the last request (0 when no response).
     */
    public function get_last_status()
    {
        return (int)$this->last_status;
    }

    /**
     * @return string Safe error message from the last request.
     */
    public function get_last_error()
    {
        return (string)$this->last_error;
    }

    // ---------------------------------------------------------------------
    private function _request_curl($method, $url, array $headers, $body)
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::REQUEST_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => TRUE,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => FALSE,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'proyecto_base-Stripe-Client/1.0',
        ];

        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        $ca_bundle = $this->_ca_bundle_path();
        if ($ca_bundle !== '') {
            $options[CURLOPT_CAINFO] = $ca_bundle;
        }

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = TRUE;
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is a no-op and deprecated since PHP 8.0/8.5. The handle
        // is released when it goes out of scope.

        $this->last_status = $status;

        if ($response === FALSE || $errno !== 0) {
            return $this->_fail('Stripe connection error.');
        }

        return $this->_procesar_respuesta((string)$response, $status);
    }

    private function _request_stream($method, $url, array $headers, $body)
    {
        $options = [
            'http' => [
                'method'          => $method,
                'header'          => implode("\r\n", $headers) . "\r\n",
                'timeout'         => self::REQUEST_TIMEOUT,
                'ignore_errors'   => TRUE,
                'follow_location' => 0,
                'max_redirects'   => 0,
            ],
            'ssl' => [
                'verify_peer'       => TRUE,
                'verify_peer_name'  => TRUE,
                'allow_self_signed' => FALSE,
            ],
        ];

        $ca_bundle = $this->_ca_bundle_path();
        if ($ca_bundle !== '') {
            $options['ssl']['cafile'] = $ca_bundle;
        }

        if ($method === 'POST') {
            $options['http']['content'] = $body;
        }

        $context = stream_context_create($options);
        $handle  = @fopen($url, 'rb', FALSE, $context);

        if ($handle === FALSE) {
            return $this->_fail('Stripe stream connection error.');
        }

        $meta     = stream_get_meta_data($handle);
        $response = stream_get_contents($handle, self::MAX_RESPONSE_BYTES + 1);
        fclose($handle);

        if ($response === FALSE) {
            return $this->_fail('Stripe stream read error.');
        }

        $status = 0;
        $response_headers = isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])
            ? $meta['wrapper_data']
            : [];

        if (!empty($response_headers[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#i', $response_headers[0], $m)) {
            $status = (int)$m[1];
        }

        $this->last_status = $status;

        return $this->_procesar_respuesta((string)$response, $status);
    }

    private function _procesar_respuesta($response, $status)
    {
        if (strlen($response) > self::MAX_RESPONSE_BYTES) {
            return $this->_fail('Stripe response exceeds the allowed size.');
        }

        $decoded = json_decode($response, TRUE);
        $json_ok = json_last_error() === JSON_ERROR_NONE && is_array($decoded);

        if ($status < 200 || $status >= 300) {
            $safe = 'Stripe API returned HTTP ' . (int)$status . '.';
            if ($json_ok
                && isset($decoded['error'])
                && is_array($decoded['error'])
                && isset($decoded['error']['type'])
                && is_scalar($decoded['error']['type'])) {
                $safe .= ' Type: ' . preg_replace('/[^A-Za-z0-9_]/', '', (string)$decoded['error']['type']) . '.';
            }
            return $this->_fail($safe);
        }

        if (!$json_ok) {
            return $this->_fail('Stripe returned an invalid JSON response.');
        }

        return $decoded;
    }

    private function _endpoint_valido($endpoint)
    {
        if (!is_string($endpoint) || strpos($endpoint, '/v1/') !== 0) {
            return FALSE;
        }

        if (strpos($endpoint, '..') !== FALSE) {
            return FALSE;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $endpoint)) {
            return FALSE;
        }

        return (bool)preg_match('#^/v1/[A-Za-z0-9_/\-]+$#', $endpoint);
    }

    private function _referencia_stripe_valida($reference, $prefix)
    {
        $reference = trim((string)$reference);

        return $reference !== ''
            && strlen($reference) <= 255
            && strpos($reference, $prefix) === 0
            && (bool)preg_match('/^[A-Za-z0-9_]+$/', $reference);
    }

    /**
     * Locates a CA bundle without hardcoding machine-specific paths.
     *
     * Priority:
     *   1. CURL_CA_BUNDLE / SSL_CA_BUNDLE / CA_BUNDLE from .env or environment.
     *   2. curl.cainfo / openssl.cafile from php.ini.
     *   3. The default certificate file reported by OpenSSL.
     *
     * @return string
     */
    private function _ca_bundle_path()
    {
        $candidates = [
            $this->_env_value('CURL_CA_BUNDLE'),
            $this->_env_value('SSL_CA_BUNDLE'),
            $this->_env_value('CA_BUNDLE'),
            (string)ini_get('curl.cainfo'),
            (string)ini_get('openssl.cafile'),
        ];

        if (function_exists('openssl_get_cert_locations')) {
            $locations = openssl_get_cert_locations();
            if (is_array($locations)) {
                $candidates[] = (string)($locations['default_cert_file'] ?? '');
            }
        }

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Reads an environment value without depending on the app bootstrap helper.
     *
     * @param  string $key
     * @return string
     */
    private function _env_value($key)
    {
        if (array_key_exists($key, $_ENV)) {
            return trim((string)$_ENV[$key]);
        }

        if (array_key_exists($key, $_SERVER)) {
            return trim((string)$_SERVER[$key]);
        }

        $value = getenv($key);

        return $value === FALSE ? '' : trim((string)$value);
    }

    private function _fail($message)
    {
        $this->last_error = (string)$message;
        log_message('error', 'Stripe client: ' . $message);
        return NULL;
    }
}
