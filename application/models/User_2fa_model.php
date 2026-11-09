<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User_2fa_model
 *
 * Persistence for optional TOTP two-factor authentication. The email-based
 * `usu_2fa` flag remains independent and supported.
 */
class User_2fa_model extends CI_Model
{
    private $users = 'cab_usuarios';
    private $recovery = 'cab_usuario_2fa_recovery';

    /**
     * @param  string $user_id
     * @return array|null
     */
    public function get_status($user_id)
    {
        if (!$this->db->table_exists($this->users)) {
            return NULL;
        }

        $row = $this->db
            ->select('usu_id, usu_usuario, usu_correo, usu_2fa, usu_2fa_intentos, usu_2fa_bloqueado_hasta,
                      usu_2fa_totp_enabled, usu_2fa_totp_confirmed_at, usu_2fa_totp_last_step')
            ->where('usu_id', (string)$user_id)
            ->limit(1)
            ->get($this->users)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    /**
     * Enables TOTP and replaces the recovery-code set atomically.
     *
     * @param  string $user_id
     * @param  string $encrypted_secret
     * @param  int    $step
     * @param  array  $recovery_hashes
     * @return bool
     */
    public function enable_totp($user_id, $encrypted_secret, $step, array $recovery_hashes)
    {
        if (!$this->db->table_exists($this->users)
            || !$this->db->table_exists($this->recovery)) {
            return FALSE;
        }

        $this->db->trans_begin();

        $updated = $this->db
            ->where('usu_id', (string)$user_id)
            ->update($this->users, [
                'usu_2fa_totp_enabled'      => 1,
                'usu_2fa_totp_secret'       => (string)$encrypted_secret,
                'usu_2fa_totp_confirmed_at' => date('Y-m-d H:i:s'),
                'usu_2fa_totp_last_step'    => (int)$step,
                'usu_2fa_intentos'          => 0,
                'usu_2fa_bloqueado_hasta'   => NULL,
            ]);

        if (!$updated) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->where('rec_usuario_id', (string)$user_id)->delete($this->recovery);

        foreach ($recovery_hashes as $hash) {
            $hash = (string)$hash;

            if ($hash === '') {
                continue;
            }

            $this->db->insert($this->recovery, [
                'rec_usuario_id' => (string)$user_id,
                'rec_code_hash'  => $hash,
                'rec_created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    /**
     * @param  string $user_id
     * @return bool
     */
    public function disable_totp($user_id)
    {
        if (!$this->db->table_exists($this->users)) {
            return FALSE;
        }

        $this->db->trans_begin();

        $updated = $this->db
            ->where('usu_id', (string)$user_id)
            ->update($this->users, [
                'usu_2fa_totp_enabled'      => 0,
                'usu_2fa_totp_secret'       => NULL,
                'usu_2fa_totp_confirmed_at' => NULL,
                'usu_2fa_totp_last_step'    => NULL,
                'usu_2fa_intentos'          => 0,
                'usu_2fa_bloqueado_hasta'   => NULL,
            ]);

        if ($updated && $this->db->table_exists($this->recovery)) {
            $this->db->where('rec_usuario_id', (string)$user_id)->delete($this->recovery);
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return (bool)$updated;
    }

    /**
     * Consumes one unused recovery code.
     *
     * @param  string $user_id
     * @param  string $hash
     * @return bool
     */
    public function consume_recovery_code($user_id, $code)
    {
        if (!$this->db->table_exists($this->recovery)) {
            return FALSE;
        }

        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$code));

        if ($code === '') {
            return FALSE;
        }

        $rows = $this->db
            ->select('rec_id, rec_code_hash')
            ->where('rec_usuario_id', (string)$user_id)
            ->where('rec_used_at IS NULL', NULL, FALSE)
            ->get($this->recovery)
            ->result_array();

        foreach ($rows as $row) {
            if (!password_verify($code, (string)($row['rec_code_hash'] ?? ''))) {
                continue;
            }

            $this->db->query(
                'UPDATE ' . $this->recovery . '
                    SET rec_used_at = ?
                  WHERE rec_id = ?
                    AND rec_used_at IS NULL',
                [date('Y-m-d H:i:s'), (int)$row['rec_id']]
            );

            return $this->db->affected_rows() === 1;
        }

        return FALSE;
    }

    /**
     * @param  string $user_id
     * @return int
     */
    public function count_recovery_codes($user_id)
    {
        if (!$this->db->table_exists($this->recovery)) {
            return 0;
        }

        return (int)$this->db
            ->where('rec_usuario_id', (string)$user_id)
            ->where('rec_used_at IS NULL', NULL, FALSE)
            ->count_all_results($this->recovery);
    }
}
