<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('app_resolver_client_ip')) {
    function app_resolver_client_ip($input)
    {
        if (!is_object($input) || !method_exists($input, 'server') || !method_exists($input, 'ip_address')) {
            return '';
        }

        $remote_ip = trim((string)$input->ip_address());

        $cloudflare_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
        ];

        foreach ($cloudflare_headers as $header) {
            $candidate = app_normalizar_ip_header((string)$input->server($header, TRUE));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                return $candidate;
            }
        }

        if (app_ip_es_privada_o_loopback($remote_ip)) {
            $forwarded_headers = [
                'HTTP_X_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'HTTP_X_REAL_IP',
                'HTTP_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
            ];

            foreach ($forwarded_headers as $header) {
                foreach (app_extraer_ips_header((string)$input->server($header, TRUE)) as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                        return $candidate;
                    }
                }
            }
        }

        if (filter_var($remote_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return $remote_ip;
        }

        return '';
    }
}

if (!function_exists('app_extraer_ips_header')) {
    function app_extraer_ips_header($header_value)
    {
        $header_value = trim((string)$header_value);
        if ($header_value === '') {
            return [];
        }

        $candidatos = [];
        foreach (explode(',', $header_value) as $parte) {
            $parte = trim((string)$parte);
            if ($parte === '') {
                continue;
            }

            if (stripos($parte, 'for=') !== FALSE) {
                $segmentos = preg_split('/;\s*/', $parte);
                foreach ($segmentos as $segmento) {
                    $segmento = trim((string)$segmento);
                    if (stripos($segmento, 'for=') === 0) {
                        $parte = substr($segmento, 4);
                        break;
                    }
                }
            }

            $ip = app_normalizar_ip_header($parte);
            if ($ip !== '') {
                $candidatos[] = $ip;
            }
        }

        return $candidatos;
    }
}

if (!function_exists('app_normalizar_ip_header')) {
    function app_normalizar_ip_header($value)
    {
        $value = trim((string)$value, " \t\n\r\0\x0B\"'");
        if ($value === '' || strtolower($value) === 'unknown') {
            return '';
        }

        if (substr($value, 0, 1) === '[' && substr($value, -1) === ']') {
            $value = substr($value, 1, -1);
        }

        if (preg_match('/^\[(.*)\]:(\d+)$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (substr_count($value, ':') === 1 && strpos($value, '.') !== FALSE) {
            $parts = explode(':', $value, 2);
            if (isset($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $value = $parts[0];
            }
        }

        return trim($value);
    }
}

if (!function_exists('app_ip_es_privada_o_loopback')) {
    function app_ip_es_privada_o_loopback($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return FALSE;
        }

        if ($ip === '127.0.0.1' || $ip === '::1') {
            return TRUE;
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}