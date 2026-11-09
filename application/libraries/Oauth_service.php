<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Oauth_service
 * Servicio para autenticación OAuth 2.0 con Google y Apple en CodeIgniter 3.
 * Soporta tanto cURL como stream context con OpenSSL.
 */
class Oauth_service
{
    /**
     * Genera la URL de autorización de Google
     */
    public function get_google_auth_url($client_id, $redirect_uri, $state)
    {
        $params = [
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'response_type'         => 'code',
            'scope'                 => 'openid profile email',
            'access_type'           => 'online',
            'include_granted_scopes'=> 'true',
            'prompt'                => 'select_account',
            'state'                 => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Intercambia el código de Google por tokens y perfil de usuario
     */
    public function handle_google_callback($code, $client_id, $client_secret, $redirect_uri)
    {
        $token_response = $this->_request('POST', 'https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($token_response['ok']) || empty($token_response['data']['access_token'])) {
            $err_msg = $token_response['data']['error_description'] ?? ($token_response['data']['error'] ?? 'No se pudo obtener el token de Google');
            return ['ok' => FALSE, 'error' => $err_msg];
        }

        $access_token = $token_response['data']['access_token'];
        $id_token     = $token_response['data']['id_token'] ?? '';

        // Consultar el perfil de usuario a Google
        $user_response = $this->_request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [], [
            'Authorization: Bearer ' . $access_token,
        ]);

        $user_data = $user_response['data'] ?? [];

        // Si falló userinfo por red, intentar decodificar el id_token JWT
        if (empty($user_data['email']) && !empty($id_token)) {
            $user_data = $this->_decode_jwt_payload($id_token);
        }

        if (empty($user_data['email'])) {
            return ['ok' => FALSE, 'error' => 'No se pudo obtener el correo de Google'];
        }

        $email       = strtolower(trim((string)$user_data['email']));
        $nombre      = trim((string)($user_data['given_name'] ?? ($user_data['name'] ?? 'Usuario')));
        $apellido    = trim((string)($user_data['family_name'] ?? ''));
        $foto        = trim((string)($user_data['picture'] ?? ''));
        $oauth_id    = (string)($user_data['sub'] ?? '');

        return [
            'ok'             => TRUE,
            'provider'       => 'Google',
            'oauth_id'       => $oauth_id,
            'email'          => $email,
            'email_verified' => !empty($user_data['email_verified']),
            'nombre'         => $nombre,
            'apellido'       => $apellido,
            'foto'           => $foto,
        ];
    }

    /**
     * Genera la URL de autorización de Apple (Sign in with Apple)
     */
    public function get_apple_auth_url($client_id, $redirect_uri, $state)
    {
        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code id_token',
            'response_mode' => 'form_post',
            'scope'         => 'name email',
            'state'         => $state,
        ];

        return 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);
    }

    /**
     * Procesa la respuesta de Apple (POST con id_token, code y opcionalmente user)
     */
    public function handle_apple_callback(array $post_data, $client_id)
    {
        $id_token = $post_data['id_token'] ?? '';
        if (empty($id_token)) {
            return ['ok' => FALSE, 'error' => 'No se recibió el token de identificación de Apple'];
        }

        $payload = $this->_verify_apple_jwt($id_token, $client_id);
        if (empty($payload)) {
            return ['ok' => FALSE, 'error' => 'No se pudo verificar el token de Apple'];
        }

        $email    = strtolower(trim((string)($payload['email'] ?? '')));
        $oauth_id = (string)($payload['sub'] ?? '');

        // En el primer inicio de sesión, Apple puede enviar datos de nombre en el parámetro 'user'
        $nombre   = 'Usuario';
        $apellido = '';
        if (!empty($post_data['user'])) {
            $user_info = is_array($post_data['user']) ? $post_data['user'] : json_decode($post_data['user'], TRUE);
            if (!empty($user_info['name']['firstName'])) {
                $nombre = trim((string)$user_info['name']['firstName']);
            }
            if (!empty($user_info['name']['lastName'])) {
                $apellido = trim((string)$user_info['name']['lastName']);
            }
            if (empty($email) && !empty($user_info['email'])) {
                $email = strtolower(trim((string)$user_info['email']));
            }
        }

        if (empty($email)) {
            return ['ok' => FALSE, 'error' => 'No se obtuvo un correo electrónico válido de Apple'];
        }

        return [
            'ok'             => TRUE,
            'provider'       => 'Apple',
            'oauth_id'       => $oauth_id,
            'email'          => $email,
            'email_verified' => filter_var($payload['email_verified'] ?? FALSE, FILTER_VALIDATE_BOOLEAN),
            'nombre'         => $nombre,
            'apellido'       => $apellido,
            'foto'           => '',
        ];
    }

    /**
     * Verifica criptográficamente el id_token de Apple (RS256 + JWKS),
     * y valida emisor, audiencia y vigencia temporal.
     *
     * @return array payload verificado o [] si es inválido
     */
    private function _verify_apple_jwt($jwt, $client_id)
    {
        $parts = explode('.', (string)$jwt);
        if (count($parts) !== 3) {
            return [];
        }

        $header  = json_decode($this->_b64url_decode($parts[0]), TRUE);
        $payload = json_decode($this->_b64url_decode($parts[1]), TRUE);

        if (!is_array($header) || !is_array($payload)) {
            return [];
        }

        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            return [];
        }

        $jwk = NULL;
        foreach ($this->_apple_jwks() as $candidate) {
            if (is_array($candidate) && ($candidate['kid'] ?? '') === $header['kid']) {
                $jwk = $candidate;
                break;
            }
        }

        if ($jwk === NULL) {
            return [];
        }

        $public_key = $this->_rsa_public_key_from_jwk($jwk);
        $signature  = $this->_b64url_decode($parts[2]);
        if ($public_key === NULL || $signature === '') {
            return [];
        }

        $verified = openssl_verify($parts[0] . '.' . $parts[1], $signature, $public_key, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return [];
        }

        $now = time();
        if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            return [];
        }

        $aud = $payload['aud'] ?? '';
        if (is_array($aud)) {
            if (!in_array((string)$client_id, array_map('strval', $aud), TRUE)) {
                return [];
            }
        } elseif ((string)$aud !== (string)$client_id) {
            return [];
        }

        if (!isset($payload['exp']) || (int)$payload['exp'] <= $now) {
            return [];
        }

        if (isset($payload['iat']) && (int)$payload['iat'] > $now + 300) {
            return [];
        }

        return $payload;
    }

    /** Descarga y cachea por 1 hora las claves públicas (JWKS) de Apple. */
    private function _apple_jwks()
    {
        $cache_file = APPPATH . 'cache/apple_jwks.json';

        if (is_file($cache_file) && (time() - (int)filemtime($cache_file)) < 3600) {
            $cached = json_decode((string)@file_get_contents($cache_file), TRUE);
            if (!empty($cached['keys']) && is_array($cached['keys'])) {
                return $cached['keys'];
            }
        }

        $response = $this->_request('GET', 'https://appleid.apple.com/auth/keys');
        $keys = $response['ok'] ? ($response['data']['keys'] ?? []) : [];

        if (is_array($keys) && !empty($keys)) {
            @file_put_contents($cache_file, json_encode(['keys' => $keys]), LOCK_EX);
            return $keys;
        }

        if (is_file($cache_file)) {
            $cached = json_decode((string)@file_get_contents($cache_file), TRUE);
            if (!empty($cached['keys']) && is_array($cached['keys'])) {
                return $cached['keys'];
            }
        }

        return [];
    }

    /** Construye una clave pública PEM a partir de un JWK RSA (n, e). */
    private function _rsa_public_key_from_jwk(array $jwk)
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            return NULL;
        }

        $modulus  = $this->_b64url_decode($jwk['n']);
        $exponent = $this->_b64url_decode($jwk['e']);
        if ($modulus === '' || $exponent === '') {
            return NULL;
        }

        $rsa_public_key = $this->_asn1_sequence(
            $this->_asn1_integer("\x00" . $modulus) . $this->_asn1_integer($exponent)
        );

        $algorithm = $this->_asn1_sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $spki      = $this->_asn1_sequence($algorithm . $this->_asn1_bit_string($rsa_public_key));

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        return $key === FALSE ? NULL : $key;
    }

    private function _b64url_decode($data)
    {
        $data = strtr((string)$data, '-_', '+/');
        $pad  = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($data, TRUE);
        return $decoded === FALSE ? '' : $decoded;
    }

    private function _asn1($tag, $value)
    {
        return chr($tag) . $this->_asn1_length(strlen($value)) . $value;
    }

    private function _asn1_length($length)
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function _asn1_sequence($value)
    {
        return $this->_asn1(0x30, $value);
    }

    private function _asn1_integer($value)
    {
        return $this->_asn1(0x02, $value);
    }

    private function _asn1_bit_string($value)
    {
        return $this->_asn1(0x03, "\x00" . $value);
    }

    /**
     * Ejecuta una petición HTTP (POST o GET) usando cURL si está disponible,
     * o stream_context_create con OpenSSL en caso contrario.
     */
    private function _request($method, $url, array $params = [], array $headers = [])
    {
        $method = strtoupper($method);

        // Si cURL está disponible
        if (extension_loaded('curl')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }

            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($response === FALSE) {
                return ['ok' => FALSE, 'error' => 'Error cURL: ' . $curl_err];
            }

            $json = @json_decode($response, TRUE);
            return [
                'ok'        => ($http_code >= 200 && $http_code < 300),
                'http_code' => $http_code,
                'data'      => is_array($json) ? $json : ['raw' => $response],
            ];
        }

        // Fallback a file_get_contents con stream context
        $header_str = '';
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        foreach ($headers as $h) {
            $header_str .= $h . "\r\n";
        }

        $opts = [
            'http' => [
                'method'  => $method,
                'header'  => $header_str,
                'timeout' => 15,
                'ignore_errors' => TRUE,
            ],
            'ssl' => [
                'verify_peer'      => TRUE,
                'verify_peer_name' => TRUE,
            ],
        ];

        if ($method === 'POST') {
            $opts['http']['content'] = http_build_query($params);
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, FALSE, $context);

        if ($response === FALSE) {
            return ['ok' => FALSE, 'error' => 'Error al conectar con ' . $url];
        }

        $json = @json_decode($response, TRUE);
        return [
            'ok'   => TRUE,
            'data' => is_array($json) ? $json : ['raw' => $response],
        ];
    }
}

