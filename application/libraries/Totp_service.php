<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Totp_service
 *
 * RFC 6238 TOTP implementation (SHA-1, 6 digits, 30-second period).
 * No external dependency is required.
 */
class Totp_service
{
    private $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private $period = 30;
    private $digits = 6;

    /**
     * Generates a Base32 secret with 160 bits of entropy.
     *
     * @return string
     */
    public function generate_secret()
    {
        return $this->base32_encode(random_bytes(20));
    }

    /**
     * Builds the otpauth URI used by authenticator applications.
     *
     * @param  string $secret
     * @param  string $account
     * @param  string $issuer
     * @return string
     */
    public function otpauth_uri($secret, $account, $issuer)
    {
        $issuer  = trim((string)$issuer);
        $account = trim((string)$account);

        if ($issuer === '') {
            $issuer = 'VerifyCode';
        }

        if ($account === '') {
            $account = 'user';
        }

        $label = rawurlencode($issuer . ':' . $account);

        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode(strtoupper(trim((string)$secret)))
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . $this->digits
            . '&period=' . $this->period;
    }

    /**
     * Verifies a TOTP code and returns the matched time step.
     *
     * @param  string   $secret
     * @param  string   $code
     * @param  int      $window
     * @param  int|null $last_step
     * @return int|false
     */
    public function verify($secret, $code, $window = 1, $last_step = null)
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', (string)$secret));
        $code   = preg_replace('/\D+/', '', (string)$code);

        if ($secret === '' || strlen($code) !== $this->digits) {
            return FALSE;
        }

        $current = (int)floor(time() / $this->period);
        $window  = max(0, min(5, (int)$window));

        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $current + $offset;

            if ($step <= 0) {
                continue;
            }

            if ($last_step !== null && $step <= (int)$last_step) {
                continue;
            }

            if (hash_equals($this->code_at_step($secret, $step), $code)) {
                return $step;
            }
        }

        return FALSE;
    }

    /**
     * Generates one-time recovery codes in XXXX-XXXX format.
     *
     * @param  int $count
     * @return array
     */
    public function generate_recovery_codes($count = 8)
    {
        $count  = max(1, min(20, (int)$count));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes  = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = '';

            for ($j = 0; $j < 8; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }

        return $codes;
    }

    /**
     * Normalizes a recovery code before hashing/comparison.
     *
     * @param  string $code
     * @return string
     */
    public function normalize_recovery_code($code)
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$code));
    }

    /**
     * @param  string $secret
     * @param  int    $step
     * @return string
     */
    private function code_at_step($secret, $step)
    {
        $key  = $this->base32_decode($secret);
        $hash = hash_hmac('sha1', pack('J', (int)$step), $key, TRUE);
        $offset = ord($hash[19]) & 0x0f;
        $value  = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string)($value % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * @param  string $data
     * @return string
     */
    private function base32_encode($data)
    {
        $data     = (string)$data;
        $bits     = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= $this->alphabet[bindec($chunk)];
        }

        return $output;
    }

    /**
     * @param  string $encoded
     * @return string
     */
    private function base32_decode($encoded)
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', (string)$encoded));
        $bits    = '';

        for ($i = 0; $i < strlen($encoded); $i++) {
            $position = strpos($this->alphabet, $encoded[$i]);

            if ($position === FALSE) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }

            $output .= chr(bindec($chunk));
        }

        return $output;
    }
}
