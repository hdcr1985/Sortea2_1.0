<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Consulta la Data API con una cuenta de servicio almacenada fuera del sitio web. */
class Ga4_reporting
{
    private function request($url, $body, array $headers)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => TRUE, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_SSL_VERIFYPEER => TRUE, CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($response === FALSE || $status < 200 || $status >= 300) throw new RuntimeException('No se pudo completar la consulta a Google Analytics. Verifica los permisos, la API y la conexión.');
        $result = json_decode($response, TRUE);
        if (!is_array($result)) throw new RuntimeException('Google Analytics devolvió una respuesta inválida.');
        return $result;
    }

    private function access_token()
    {
        if (!function_exists('curl_init') || !function_exists('openssl_sign')) throw new RuntimeException('Faltan extensiones necesarias para consultar Google Analytics.');
        $path = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        $path = is_string($path) ? realpath($path) : FALSE;
        $webroot = realpath(FCPATH);
        if (!$path || !$webroot || !is_file($path) || !is_readable($path)
            || stripos(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $webroot), '/') . '/') === 0) {
            throw new RuntimeException('Configura GOOGLE_APPLICATION_CREDENTIALS con un archivo accesible fuera del sitio web.');
        }
        $credentials = json_decode((string)file_get_contents($path), TRUE);
        if (!is_array($credentials) || ($credentials['type'] ?? '') !== 'service_account'
            || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Las credenciales de la cuenta de servicio no son válidas.');
        }
        $encode = function ($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); };
        $now = time();
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $encode(json_encode([
            'iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ]));
        $signature = '';
        $private_key = @openssl_pkey_get_private($credentials['private_key']);
        if ($private_key === FALSE || !openssl_sign($header . '.' . $payload, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo utilizar la cuenta de servicio configurada.');
        }
        $token = $this->request('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $header . '.' . $payload . '.' . $encode($signature),
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        if (empty($token['access_token']) || !is_string($token['access_token'])) throw new RuntimeException('Google no devolvió un token de acceso válido.');
        return $token['access_token'];
    }

    public function consultar($property_id, $days)
    {
        if (!preg_match('/\A[0-9]{1,20}\z/', (string)$property_id) || !in_array((int)$days, [7, 30, 90], TRUE)) throw new InvalidArgumentException('Parámetros de reporte inválidos.');
        $range = [['startDate' => ((int)$days - 1) . 'daysAgo', 'endDate' => 'today']];
        $metrics = [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'eventCount']];
        $body = ['requests' => [
            ['dateRanges' => $range, 'metrics' => $metrics],
            ['dateRanges' => $range, 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'activeUsers']], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]], 'limit' => '90'],
            ['dateRanges' => $range, 'dimensions' => [['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews']], 'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => TRUE]], 'limit' => '10'],
        ]];
        $result = $this->request('https://analyticsdata.googleapis.com/v1beta/properties/' . $property_id . ':batchRunReports', json_encode($body), [
            'Content-Type: application/json', 'Authorization: Bearer ' . $this->access_token(),
        ]);
        if (!isset($result['reports']) || !is_array($result['reports']) || count($result['reports']) !== 3) throw new RuntimeException('Google Analytics devolvió un reporte incompleto.');
        return $result;
    }
}
