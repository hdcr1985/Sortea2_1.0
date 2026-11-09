<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Usuarios_model
 * CRUD de usuarios del sistema sobre cab_usuarios.
 */
class Usuarios_model extends CI_Model
{
    private $tabla = 'cab_usuarios';
    private $columnas_ordenables = [
        'u.usu_nombre',
        'u.usu_apellido',
        'u.usu_correo',
        'r.per_nombre',
        'u.usu_status',
    ];

    /** Lista todos los usuarios con el nombre del rol. */
    public function get_todos()
    {
        return $this->db
            ->select('u.*, r.per_nombre AS rol_nombre, r.per_activo AS rol_activo')
            ->from($this->tabla . ' u')
            ->join('cab_grupopermi r', 'r.per_id = u.usu_tipoper', 'left')
            ->order_by('u.usu_nombre', 'ASC')
            ->order_by('u.usu_apellido', 'ASC')
            ->get()
            ->result_array();
    }

    /** Total de usuarios registrados. */
    public function count_todos()
    {
        return $this->db->count_all($this->tabla);
    }

    /** Total filtrado por búsqueda global. */
    public function count_filtrados($search = '')
    {
        $this->_datatable_query($search);
        return $this->db->count_all_results();
    }

    /** Datos paginados para DataTables server-side. */
    public function get_datatable($start, $length, $search = '', $order_column = 0, $order_dir = 'asc')
    {
        $this->_datatable_query($search);

        $order_column = isset($this->columnas_ordenables[$order_column])
            ? $this->columnas_ordenables[$order_column]
            : 'u.usu_nombre';

        $order_dir = strtolower($order_dir) === 'desc' ? 'DESC' : 'ASC';

        $this->db->order_by($order_column, $order_dir);
        $this->db->order_by('u.usu_id', 'DESC');

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        return $this->db->get()->result_array();
    }

    /** Devuelve un usuario por ID. */
    public function get_por_id($id)
    {
        $this->db
            ->from($this->tabla)
            ->where('usu_id', (string)$id);

        return $this->db->get()->row_array();
    }

    /** Lista roles activos para el selector del formulario. */
    public function get_roles_activos($incluir_admin_master = TRUE)
    {
        $total_menus = (int)$this->db->count_all_results('cab_menu');

        $this->db
            ->select('g.per_id, g.per_nombre')
            ->from('cab_grupopermi g')
            ->where('g.per_activo', '1')
            ->order_by('g.per_nombre', 'ASC');

        if (!$incluir_admin_master && $total_menus > 0) {
            // Excluye roles con cobertura total (nivel maestro) sin usar IDs.
            $this->db->where(
                "(SELECT COUNT(*)
                    FROM cab_permisologia p
                    INNER JOIN cab_grupopermi g2 ON g2.per_id = p.mis_permiso
                   WHERE p.mis_permiso = g.per_id
                     AND p.mis_activo = '1'
                     AND g2.per_activo = '1') < {$total_menus}",
                NULL,
                FALSE
            );
        }

        return $this->db->get()->result_array();
    }

    public function get_destinatarios_promocionales($search = '')
    {
        $this->db
            ->select('u.usu_id, u.usu_nombre, u.usu_apellido, u.usu_usuario, u.usu_correo, u.usu_tipoper, r.per_nombre AS rol_nombre')
            ->from($this->tabla . ' u')
            ->join('cab_grupopermi r', 'r.per_id = u.usu_tipoper', 'left')
            ->where('u.usu_status', '1')
            ->where('TRIM(COALESCE(u.usu_correo, "")) != ""', NULL, FALSE)
            ->order_by('u.usu_nombre', 'ASC')
            ->order_by('u.usu_apellido', 'ASC');

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('u.usu_nombre', $search);
            $this->db->or_like('u.usu_apellido', $search);
            $this->db->or_like('u.usu_usuario', $search);
            $this->db->or_like('u.usu_correo', $search);
            $this->db->or_like('r.per_nombre', $search);
            $this->db->group_end();
        }

        $rows = $this->db->get()->result_array();

        return array_values(array_filter($rows, function ($row) {
            return filter_var((string)($row['usu_correo'] ?? ''), FILTER_VALIDATE_EMAIL) !== FALSE;
        }));
    }

    public function get_destinatarios_promocionales_por_ids(array $ids)
    {
        $ids = array_values(array_filter(array_map('strval', $ids), function ($id) {
            return $id !== '';
        }));

        if (empty($ids)) {
            return [];
        }

        $rows = $this->db
            ->select('u.usu_id, u.usu_nombre, u.usu_apellido, u.usu_usuario, u.usu_correo, u.usu_tipoper, r.per_nombre AS rol_nombre')
            ->from($this->tabla . ' u')
            ->join('cab_grupopermi r', 'r.per_id = u.usu_tipoper', 'left')
            ->where('u.usu_status', '1')
            ->where_in('u.usu_id', $ids)
            ->order_by('u.usu_nombre', 'ASC')
            ->order_by('u.usu_apellido', 'ASC')
            ->get()
            ->result_array();

        return array_values(array_filter($rows, function ($row) {
            return filter_var((string)($row['usu_correo'] ?? ''), FILTER_VALIDATE_EMAIL) !== FALSE;
        }));
    }

    /** Crea un usuario y devuelve el ID insertado. */
    public function crear(array $data)
    {
        $data['usu_id'] = $data['usu_id'] ?? $this->_siguiente_id();
        $data['usu_created_at'] = $data['usu_created_at'] ?? date('Y-m-d H:i:s');
        $data['usu_updated_at'] = $data['usu_updated_at'] ?? date('Y-m-d H:i:s');

        if ($this->db->insert($this->tabla, $data)) {
            return $data['usu_id'];
        }

        return FALSE;
    }

    /** Devuelve el siguiente ID disponible con formato 000001. */
    public function generar_nuevo_id()
    {
        return $this->_siguiente_id();
    }

    /** Actualiza un usuario existente. */
    public function actualizar($id, array $data)
    {
        $data['usu_updated_at'] = date('Y-m-d H:i:s');

        return $this->db
            ->where('usu_id', (string)$id)
            ->update($this->tabla, $data);
    }

    /** Elimina un usuario por ID. */
    public function eliminar($id)
    {
        return $this->db
            ->where('usu_id', (string)$id)
            ->delete($this->tabla);
    }

    /** Verifica si el nombre de usuario ya existe. */
    public function existe_usuario($usuario, $ignorar_id = 0)
    {
        $this->db->where('usu_usuario', trim($usuario));

        if ((string)$ignorar_id !== '' && (string)$ignorar_id !== '0') {
            $this->db->where('usu_id !=', (string)$ignorar_id);
        }

        return $this->db->count_all_results($this->tabla) > 0;
    }

    /** Verifica si el correo ya existe. */
    public function existe_correo($correo, $ignorar_id = 0)
    {
        $this->db->where('usu_correo', trim($correo));

        if ((string)$ignorar_id !== '' && (string)$ignorar_id !== '0') {
            $this->db->where('usu_id !=', (string)$ignorar_id);
        }

        return $this->db->count_all_results($this->tabla) > 0;
    }

    public function get_usuario_activo_por_correo($correo)
    {
        $select = 'usu_id, usu_nombre, usu_usuario, usu_correo';
        if ($this->db->field_exists('usu_apellido', $this->tabla)) {
            $select .= ', usu_apellido';
        }

        return $this->db
            ->select($select)
            ->where('usu_correo', trim((string)$correo))
            ->where('usu_status', '1')
            ->limit(1)
            ->get($this->tabla)
            ->row_array();
    }

    public function crear_token_recuperacion($usuario_id, $token_hash, $expira, $ip_address)
    {
        $this->db->trans_begin();
        $existing = $this->db->query(
            'SELECT uver_send_count, uver_window_started_at FROM cab_usuario_verificacion
             WHERE uver_usuario_id = ? AND uver_tipo = ? LIMIT 1 FOR UPDATE',
            [(string)$usuario_id, 'password_reset']
        )->row_array();
        $now = time();

        if (!empty($existing)) {
            $window_start = strtotime((string)$existing['uver_window_started_at']);
            $attempts = (int)$existing['uver_send_count'];
            if ($window_start === FALSE || $now - $window_start >= 3600) {
                $window_start = $now;
                $attempts = 1;
            } elseif ($attempts >= 5) {
                if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                    $this->db->trans_rollback();
                    return 'error';
                }
                return 'limited';
            } else {
                $attempts++;
            }

            $saved = $this->db
                ->where('uver_usuario_id', (string)$usuario_id)
                ->where('uver_tipo', 'password_reset')
                ->update('cab_usuario_verificacion', [
                    'uver_token_hash' => $token_hash,
                    'uver_expira' => $expira,
                    'uver_ip_address' => $ip_address,
                    'uver_send_count' => $attempts,
                    'uver_window_started_at' => date('Y-m-d H:i:s', $window_start),
                    'uver_last_sent_at' => date('Y-m-d H:i:s', $now),
                    'uver_updated_at' => date('Y-m-d H:i:s', $now),
                ]);
        } else {
            $saved = $this->db->insert('cab_usuario_verificacion', [
                'uver_usuario_id' => (string)$usuario_id,
                'uver_tipo' => 'password_reset',
                'uver_token_hash' => $token_hash,
                'uver_expira' => $expira,
                'uver_ip_address' => $ip_address,
                'uver_send_count' => 1,
                'uver_window_started_at' => date('Y-m-d H:i:s', $now),
                'uver_last_sent_at' => date('Y-m-d H:i:s', $now),
                'uver_created_at' => date('Y-m-d H:i:s', $now),
                'uver_updated_at' => date('Y-m-d H:i:s', $now),
            ]);
        }

        if (!$saved || !$this->db->trans_status() || !$this->db->trans_commit()) {
            $this->db->trans_rollback();
            return 'error';
        }

        return 'saved';
    }

    public function estado_token_recuperacion($token_hash)
    {
        $row = $this->db->query(
            'SELECT v.uver_expira FROM cab_usuario_verificacion v
             INNER JOIN ' . $this->db->escape_identifiers($this->tabla) . ' u ON u.usu_id = v.uver_usuario_id
             WHERE v.uver_token_hash = ? AND v.uver_tipo = ? AND u.usu_status = ? LIMIT 1',
            [$token_hash, 'password_reset', '1']
        )->row_array();

        if (empty($row)) {
            return 'invalid';
        }

        return strtotime((string)$row['uver_expira']) >= time() ? 'valid' : 'expired';
    }

    public function restablecer_clave_por_token($token_hash, $password_hash)
    {
        $this->db->trans_begin();
        $recovery = $this->db->query(
            'SELECT uver_usuario_id, uver_expira FROM cab_usuario_verificacion
             WHERE uver_token_hash = ? AND uver_tipo = ? LIMIT 1 FOR UPDATE',
            [$token_hash, 'password_reset']
        )->row_array();

        if (empty($recovery)) {
            $this->db->trans_rollback();
            return 'invalid';
        }

        $user = $this->db->query(
            'SELECT usu_status FROM ' . $this->db->escape_identifiers($this->tabla) . ' WHERE usu_id = ? LIMIT 1 FOR UPDATE',
            [(string)$recovery['uver_usuario_id']]
        )->row_array();
        if (empty($user) || (string)$user['usu_status'] !== '1') {
            $this->db->where('uver_usuario_id', (string)$recovery['uver_usuario_id'])
                ->where('uver_tipo', 'password_reset')
                ->delete('cab_usuario_verificacion');
            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                $this->db->trans_rollback();
                return 'error';
            }
            return 'invalid';
        }

        if (strtotime((string)$recovery['uver_expira']) < time()) {
            if (!$this->db->trans_commit()) {
                $this->db->trans_rollback();
                return 'error';
            }
            return 'expired';
        }

        $updated = $this->db
            ->where('usu_id', (string)$recovery['uver_usuario_id'])
            ->where('usu_status', '1')
            ->update($this->tabla, [
                'usu_clave' => $password_hash,
                'usu_updated_at' => date('Y-m-d H:i:s'),
            ]);
        $deleted = $this->db
            ->where('uver_usuario_id', (string)$recovery['uver_usuario_id'])
            ->where('uver_tipo', 'password_reset')
            ->where('uver_token_hash', $token_hash)
            ->delete('cab_usuario_verificacion');

        if (!$updated || !$deleted || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            return 'error';
        }

        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            return 'error';
        }

        return 'updated';
    }

    /** Verifica si el rol existe y está activo. */
    public function rol_valido($rol_id)
    {
        return $this->db
            ->where('per_id', (int)$rol_id)
            ->where('per_activo', '1')
            ->count_all_results('cab_grupopermi') > 0;
    }

    public function rol_registro_publico_valido($rol_id)
    {
        $rol_id = (int)$rol_id;
        $this->load->model('Menu_model');

        if ($rol_id <= 0 || $this->Menu_model->rol_es_master($rol_id) || !$this->rol_valido($rol_id)) {
            return FALSE;
        }

        // El rol de registro público nunca debe tener acceso a módulos
        // administrativos sensibles (usuarios, permisos, menús, configuración,
        // correos masivos, notificaciones o reportes). Los IDs se resuelven
        // dinámicamente desde las URLs amigables, sin constantes.
        $menus_sensibles = $this->Menu_model->ids_menus_por_slugs([
            'creacion-de-menus',
            'roles-y-permisos',
            'usuarios',
            'notificaciones',
            'configuracion-tema',
            'configuracion-correos',
            'correos-promocionales',
            'configuracion-general',
            'plantillas-correo',
        ]);

        if (empty($menus_sensibles)) {
            return TRUE;
        }

        $tiene_menus_sensibles = $this->db
            ->from('cab_permisologia')
            ->where('mis_permiso', $rol_id)
            ->where_in('mis_menu', $menus_sensibles)
            ->count_all_results() > 0;

        return !$tiene_menus_sensibles;
    }

    public function get_verificacion_pendiente_por_correo($correo)
    {
        $select = 'u.usu_id, u.usu_nombre, u.usu_correo, v.uver_send_count, v.uver_window_started_at, v.uver_last_sent_at';
        if ($this->db->field_exists('usu_apellido', $this->tabla)) {
            $select .= ', u.usu_apellido';
        }

        return $this->db
            ->select($select)
            ->from($this->tabla . ' u')
            ->join('cab_usuario_verificacion v', 'v.uver_usuario_id = u.usu_id')
            ->where('v.uver_tipo', 'email_verification')
            ->where('u.usu_correo', trim((string)$correo))
            ->where('u.usu_status', '0')
            ->limit(1)
            ->get()
            ->row_array();
    }

    public function verificar_correo_por_token($token_hash)
    {
        $this->db->trans_begin();
        $verification = $this->db->query(
            'SELECT uver_usuario_id, uver_expira FROM cab_usuario_verificacion
             WHERE uver_token_hash = ? AND uver_tipo = ? LIMIT 1 FOR UPDATE',
            [$token_hash, 'email_verification']
        )->row_array();

        if (empty($verification)) {
            $this->db->trans_rollback();
            return 'invalid';
        }

        $user = $this->db->query(
            'SELECT usu_status FROM ' . $this->db->escape_identifiers($this->tabla) . ' WHERE usu_id = ? LIMIT 1 FOR UPDATE',
            [(string)$verification['uver_usuario_id']]
        )->row_array();
        if (empty($user)) {
            $this->db->where('uver_usuario_id', (string)$verification['uver_usuario_id'])
                ->where('uver_tipo', 'email_verification')
                ->delete('cab_usuario_verificacion');
            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                $this->db->trans_rollback();
                return 'error';
            }
            return 'invalid';
        }

        if (strtotime((string)$verification['uver_expira']) < time()) {
            if (!$this->db->trans_commit()) {
                $this->db->trans_rollback();
                return 'error';
            }
            return 'expired';
        }

        $updated = $this->db
            ->where('usu_id', (string)$verification['uver_usuario_id'])
            ->where('usu_status', '0')
            ->update($this->tabla, ['usu_status' => '1', 'usu_updated_at' => date('Y-m-d H:i:s')]);
        $deleted = $this->db
            ->where('uver_usuario_id', (string)$verification['uver_usuario_id'])
            ->where('uver_tipo', 'email_verification')
            ->delete('cab_usuario_verificacion');

        if (!$updated || !$deleted || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            return 'error';
        }

        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            return 'error';
        }

        return 'verified';
    }

    public function estado_token_verificacion($token_hash)
    {
        $verification = $this->db
            ->select('uver_expira')
            ->where('uver_token_hash', $token_hash)
            ->where('uver_tipo', 'email_verification')
            ->limit(1)
            ->get('cab_usuario_verificacion')
            ->row_array();

        if (empty($verification)) {
            return 'invalid';
        }

        return strtotime((string)$verification['uver_expira']) >= time() ? 'valid' : 'expired';
    }

    /** Obtiene los datos de sesión de un usuario. */
    public function get_sesion_por_id($id)
    {
        $select = 'usu_id, usu_nombre, usu_usuario, usu_correo, usu_tipoper, usu_foto, usu_celular, usu_fechan, usu_genero, usu_2fa';

        if ($this->db->field_exists('usu_apellido', $this->tabla)) {
            $select .= ', usu_apellido';
        }

        

        return $this->db
            ->select($select)
            ->where('usu_id', (string)$id)
            ->where('usu_status', '1')
            ->limit(1)
            ->get($this->tabla)
            ->row_array();
    }

    /** Genera el siguiente ID manual con formato 000001. */
    private function _siguiente_id()
    {
        $row = $this->db
            ->select('COALESCE(MAX(CAST(usu_id AS UNSIGNED)), 0) AS max_id', FALSE)
            ->get($this->tabla)
            ->row_array();

        $siguiente = ((int)($row['max_id'] ?? 0)) + 1;
        return str_pad((string)$siguiente, 6, '0', STR_PAD_LEFT);
    }

    private function _datatable_query($search = '')
    {
        $this->db
            ->select('u.usu_id, u.usu_nombre, u.usu_apellido, u.usu_usuario, u.usu_correo, u.usu_tipoper, u.usu_foto, u.usu_celular, u.usu_fechan, u.usu_genero, u.usu_created_at, u.usu_updated_at, u.usu_status, r.per_nombre AS rol_nombre, r.per_activo AS rol_activo')
            ->from($this->tabla . ' u')
            ->join('cab_grupopermi r', 'r.per_id = u.usu_tipoper', 'left');

        $search = trim((string)$search);
        if ($search === '') {
            return;
        }

        $this->db->group_start();
        $this->db->like('u.usu_nombre', $search);
        $this->db->or_like('u.usu_apellido', $search);
        $this->db->or_like('u.usu_usuario', $search);
        $this->db->or_like('u.usu_correo', $search);
        $this->db->or_like('u.usu_celular', $search);
        $this->db->or_like('u.usu_genero', $search);
        $this->db->or_like('r.per_nombre', $search);
        $this->db->group_end();
    }
}