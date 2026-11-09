<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Btcpay_client
 * ─────────────────────────────────────────────────────────────
 * Cliente HTTP endurecido para la Greenfield API de BTCPay Server.
 *
 * Reglas de seguridad:
 * - Solo admite endpoints relativos bajo /api/v1/.
 * - Solo admite URLs HTTPS con host válido y sin credenciales embebidas.
 * - Verifica certificado y hostname TLS en cURL y en el fallback de streams.
 * - No sigue redirecciones ni acepta protocolos distintos de HTTPS.
 * - Rechaza respuestas que no sean 2xx o que no sean JSON válido.
 * - No registra API keys, secretos ni cuerpos de respuesta en logs.
 */
class Btcpay_client
{
    const MAX_RESPONSE_BYTES = 2097152; // 2 MB
    const CONNECT_TIMEOUT    = 10;
    const REQUEST_TIMEOUT    = 30;

    private $last_status = 0;
    private $last_error  = '';

    /**
     * Valida la configuración mínima para llamar a la API.
     *
     * @param  array $config
     * @return bool
     */
    public function configuracion_api_valida(array $config)
    {
        return $this->_base_url_valida($config['pas_url'] ?? '')
            && trim((string)($config['pas_store_id'] ?? '')) !== ''
            && trim((string)($config['pas_api_key'] ?? '')) !== '';
    }

    /**
     * Valida la configuración necesaria para verificar webhooks.
     *
     * @param  array $config
     * @return bool
     */
    public function configuracion_webhook_valida(array $config)
    {
        return $this->configuracion_api_valida($config)
            && trim((string)($config['pas_webhook_secret'] ?? '')) !== '';
    }

    /**
     * Valida que una URL sea HTTPS, tenga host y no incluya credenciales.
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

        if (isset($parts['user']) || isset($parts['pass'])) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Valida la URL base de la API: HTTPS y sin query string ni fragmento,
     * porque el endpoint se concatena directamente a la base.
     *
     * @param  string $url
     * @return bool
     */
    private function _base_url_valida($url)
    {
        if (!$this->es_url_https($url)) {
            return FALSE;
        }

        $parts = parse_url(trim((string)$url));
        if (!is_array($parts)) {
            return FALSE;
        }

        return !isset($parts['query']) && !isset($parts['fragment']);
    }

    /**
     * Valida un código de moneda de factura (ISO-like, 3-10 caracteres).
     *
     * @param  string $currency
     * @return bool
     */
    public function moneda_valida($currency)
    {
        return (bool)preg_match('/^[A-Z]{3,10}$/', strtoupper(trim((string)$currency)));
    }

    /**
     * Compara dos montos usando aritmética de enteros (centavos) para
     * evitar igualdades frágiles entre floats.
     *
     * @param  mixed $expected
     * @param  mixed $received
     * @param  int   $decimals
     * @return bool
     */
    public function montos_coinciden($expected, $received, $decimals = 2)
    {
        if (!is_numeric($expected) || !is_numeric($received)) {
            return FALSE;
        }

        $factor = (int)pow(10, max(0, (int)$decimals));
        $a = (int)round(((float)$expected) * $factor);
        $b = (int)round(((float)$received) * $factor);

        return $a === $b;
    }

    /**
     * Ejecuta una petición GET/POST contra la Greenfield API.
     *
     * @param  string $method   GET|POST
     * @param  string $endpoint Ruta que empieza con /api/v1/
     * @param  array  $config   Configuración de la pasarela
     * @param  array  $data     Payload JSON para POST
     * @return array|null
     */
    public function request($method, $endpoint, array $config, array $data = [])
    {
        $this->last_status = 0;
        $this->last_error  = '';

        $method = strtoupper((string)$method);
        if (!in_array($method, ['GET', 'POST'], TRUE)) {
            return $this->_fail('Unsupported HTTP method.');
        }

        if (!$this->configuracion_api_valida($config)) {
            return $this->_fail('Invalid or incomplete BTCPay configuration.');
        }

        if (!$this->_endpoint_valido($endpoint)) {
            return $this->_fail('Invalid BTCPay API endpoint.');
        }

        $url = rtrim((string)$config['pas_url'], '/') . $endpoint;
        if (!$this->es_url_https($url)) {
            return $this->_fail('BTCPay API URL must use HTTPS.');
        }

        $headers = [
            $this->_authorization_header((string)$config['pas_api_key']),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (function_exists('curl_init')) {
            return $this->_request_curl($method, $url, $headers, $data);
        }

        return $this->_request_stream($method, $url, $headers, $data);
    }

    /**
     * @return int HTTP status de la última petición (0 si no hubo respuesta).
     */
    public function get_last_status()
    {
        return (int)$this->last_status;
    }

    /**
     * @return string Error seguro de la última petición (sin secretos).
     */
    public function get_last_error()
    {
        return (string)$this->last_error;
    }

    // ─────────────────────────────────────────────────────────
    private function _request_curl($method, $url, array $headers, array $data)
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
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
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
            return $this->_fail('BTCPay connection error.');
        }

        if (strlen((string)$response) > self::MAX_RESPONSE_BYTES) {
            return $this->_fail('BTCPay response exceeds the allowed size.');
        }

        if ($status < 200 || $status >= 300) {
            return $this->_fail('BTCPay API returned HTTP ' . $status . '.');
        }

        return $this->_decode_json((string)$response);
    }

    private function _request_stream($method, $url, array $headers, array $data)
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
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $handle  = @fopen($url, 'rb', FALSE, $context);

        if ($handle === FALSE) {
            return $this->_fail('BTCPay stream connection error.');
        }

        $meta     = stream_get_meta_data($handle);
        $response = stream_get_contents($handle, self::MAX_RESPONSE_BYTES + 1);
        fclose($handle);

        if ($response === FALSE) {
            return $this->_fail('BTCPay stream read error.');
        }

        if (strlen((string)$response) > self::MAX_RESPONSE_BYTES) {
            return $this->_fail('BTCPay response exceeds the allowed size.');
        }

        $status = 0;
        $response_headers = isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])
            ? $meta['wrapper_data']
            : [];

        if (!empty($response_headers[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#i', $response_headers[0], $m)) {
            $status = (int)$m[1];
        }

        $this->last_status = $status;

        if ($status < 200 || $status >= 300) {
            return $this->_fail('BTCPay API returned HTTP ' . $status . '.');
        }

        return $this->_decode_json((string)$response);
    }

    private function _decode_json($response)
    {
        $decoded = json_decode($response, TRUE);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->_fail('BTCPay returned an invalid JSON response.');
        }

        return $decoded;
    }

    private function _endpoint_valido($endpoint)
    {
        if (!is_string($endpoint) || strpos($endpoint, '/api/v1/') !== 0) {
            return FALSE;
        }

        if (strpos($endpoint, '..') !== FALSE) {
            return FALSE;
        }

        return !preg_match('#^[a-z][a-z0-9+.-]*://#i', $endpoint);
    }

    private function _authorization_header($api_key)
    {
        $api_key = trim((string)$api_key);

        if (preg_match('/^(token|bearer|basic)\s+/i', $api_key)) {
            return 'Authorization: ' . $api_key;
        }

        // Raw user:password Basic credentials.
        if (strpos($api_key, ':') !== FALSE) {
            return 'Authorization: Basic ' . base64_encode($api_key);
        }

        // Already-base64 Basic credentials (printable user:password payload).
        $decoded = base64_decode($api_key, TRUE);
        if ($decoded !== FALSE
            && strpos($decoded, ':') !== FALSE
            && preg_match('/^[\x20-\x7E]+$/', $decoded)) {
            return 'Authorization: Basic ' . $api_key;
        }

        // BTCPay Greenfield API key.
        return 'Authorization: token ' . $api_key;
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
        log_message('error', 'BTCPay client: ' . $message);
        return NULL;
    }
}
