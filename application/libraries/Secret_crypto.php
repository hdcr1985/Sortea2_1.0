<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Secret_crypto
 *
 * AES-256-GCM envelope used for TOTP secrets. It uses the application
 * encryption_key as key material and never stores plaintext secrets.
 */
class Secret_crypto
{
    private $prefix = 'enc:v1:';

    /**
     * @param  string $plain
     * @return string
     */
    public function encrypt($plain)
    {
        $plain = (string)$plain;

        if ($plain === '' || !function_exists('openssl_encrypt')) {
            return '';
        }

        $key = $this->key();

        if ($key === '') {
            return '';
        }

        $cipher  = 'aes-256-gcm';
        $iv_size = openssl_cipher_iv_length($cipher);
        $iv_size = ($iv_size === FALSE || $iv_size <= 0) ? 12 : (int)$iv_size;
        $iv      = random_bytes($iv_size);
        $tag     = '';
        $ciphertext = openssl_encrypt($plain, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($ciphertext === FALSE || strlen((string)$tag) !== 16) {
            return '';
        }

        return $this->prefix . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * @param  string $stored
     * @return string
     */
    public function decrypt($stored)
    {
        $stored = (string)$stored;

        if ($stored === '') {
            return '';
        }

        if (strpos($stored, $this->prefix) !== 0) {
            return $stored;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $key = $this->key();

        if ($key === '') {
            return '';
        }

        $decoded = base64_decode(substr($stored, strlen($this->prefix)), TRUE);

        if ($decoded === FALSE || strlen($decoded) < 12 + 16 + 1) {
            return '';
        }

        $cipher  = 'aes-256-gcm';
        $iv_size = openssl_cipher_iv_length($cipher);
        $iv_size = ($iv_size === FALSE || $iv_size <= 0) ? 12 : (int)$iv_size;
        $iv         = substr($decoded, 0, $iv_size);
        $tag        = substr($decoded, $iv_size, 16);
        $ciphertext = substr($decoded, $iv_size + 16);
        $plain      = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return ($plain === FALSE) ? '' : (string)$plain;
    }

    /**
     * @return string
     */
    private function key()
    {
        $CI = function_exists('get_instance') ? get_instance() : NULL;

        if (!is_object($CI) || !isset($CI->config) || !is_object($CI->config)) {
            return '';
        }

        $configured = (string)($CI->config->item('encryption_key') ?? '');

        if ($configured === '') {
            return '';
        }

        return hash('sha256', $configured, TRUE);
    }
}
