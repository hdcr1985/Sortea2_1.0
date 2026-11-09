<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reportes_sesiones_activas_model extends CI_Model
{
    private $tabla_usuarios = 'cab_usuarios';
    private $tabla_roles = 'cab_grupopermi';
    private $tabla_logins = 'cab_bitacora_ip';
    private $tiene_apellido = NULL;
    private $columnas_ordenables = [
        0 => 'usu_nombre',
        1 => 'rol_nombre',
        2 => 'ultima_actividad',
        3 => 'bit_ip',
    ];
    private $cached_sessions = NULL;
    private $cached_raw_sessions = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
    }

    public function count_todos()
    {
        return count($this->_get_active_sessions());
    }

    public function count_filtrados($search = '')
    {
        return count($this->_filtrar_rows($this->_get_active_sessions(), $search));
    }

    public function get_datatable($start, $length, $search = '', $order_column = 0, $order_dir = 'desc')
    {
        $rows = $this->_filtrar_rows($this->_get_active_sessions(), $search);
        $column = $this->columnas_ordenables[$order_column] ?? 'ultima_actividad';
        $direction = strtolower((string)$order_dir) === 'asc' ? 'asc' : 'desc';

        usort($rows, function ($left, $right) use ($column, $direction) {
            $a = strtolower(trim((string)($left[$column] ?? '')));
            $b = strtolower(trim((string)($right[$column] ?? '')));

            if (in_array($column, ['sesion_desde', 'ultima_actividad'], TRUE)) {
                $a = strtotime($a) ?: 0;
                $b = strtotime($b) ?: 0;
            }

            if ($a === $b) {
                return 0;
            }

            if ($direction === 'asc') {
                return ($a < $b) ? -1 : 1;
            }

            return ($a > $b) ? -1 : 1;
        });

        if ((int)$length > 0) {
            $rows = array_slice($rows, (int)$start, (int)$length);
        }

        return array_values($rows);
    }

    public function get_detalle_activo($session_token)
    {
        $session_token = trim((string)$session_token);
        if ($session_token === '') {
            return [];
        }

        foreach ($this->_get_active_sessions() as $row) {
            $expected = (string)($row['session_token'] ?? '');
            if ($expected !== '' && hash_equals($expected, $session_token)) {
                return $row;
            }
        }

        return [];
    }

    public function banear_y_cerrar_sesiones($usuario_id, $actor_tipoper = '')
    {
        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return ['ok' => FALSE, 'sesiones_cerradas' => 0, 'usuario' => []];
        }

        $usuario = $this->db
            ->select('usu_id, usu_nombre, usu_usuario, usu_status, usu_tipoper')
            ->where('usu_id', $usuario_id)
            ->limit(1)
            ->get($this->tabla_usuarios)
            ->row_array();

        if (empty($usuario)) {
            return ['ok' => FALSE, 'sesiones_cerradas' => 0, 'usuario' => []];
        }

        if ($this->Menu_model->rol_es_master($usuario['usu_tipoper'] ?? 0)
            && !$this->Menu_model->rol_es_master($actor_tipoper)) {
            return ['ok' => FALSE, 'protegido' => TRUE, 'sesiones_cerradas' => 0, 'usuario' => $usuario];
        }

        $updated = $this->db
            ->where('usu_id', $usuario_id)
            ->update($this->tabla_usuarios, [
                'usu_status' => '0',
                'usu_updated_at' => date('Y-m-d H:i:s'),
            ]);

        if (!$updated) {
            return ['ok' => FALSE, 'sesiones_cerradas' => 0, 'usuario' => $usuario];
        }

        // El estado se persiste primero: si la eliminación de archivos falla,
        // MY_Controller rechazará igualmente la sesión en la siguiente petición.
        $sesiones_cerradas = $this->_cerrar_sesiones_usuario($usuario_id);

        $this->cached_sessions = NULL;
        $this->cached_raw_sessions = NULL;

        return [
            'ok' => TRUE,
            'sesiones_cerradas' => $sesiones_cerradas,
            'usuario' => $usuario,
        ];
    }

    public function cerrar_sesiones_usuario($usuario_id, $actor_tipoper = '')
    {
        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return ['ok' => FALSE, 'sesiones_cerradas' => 0, 'usuario' => []];
        }

        $usuario = $this->db
            ->select('usu_id, usu_nombre, usu_usuario, usu_status, usu_tipoper')
            ->where('usu_id', $usuario_id)
            ->limit(1)
            ->get($this->tabla_usuarios)
            ->row_array();

        if (empty($usuario)) {
            return ['ok' => FALSE, 'sesiones_cerradas' => 0, 'usuario' => []];
        }

        if ($this->Menu_model->rol_es_master($usuario['usu_tipoper'] ?? 0)
            && !$this->Menu_model->rol_es_master($actor_tipoper)) {
            return ['ok' => FALSE, 'protegido' => TRUE, 'sesiones_cerradas' => 0, 'usuario' => $usuario];
        }

        $sesiones_cerradas = $this->_cerrar_sesiones_usuario($usuario_id);
        $this->cached_sessions = NULL;
        $this->cached_raw_sessions = NULL;

        return [
            'ok' => TRUE,
            'sesiones_cerradas' => $sesiones_cerradas,
            'usuario' => $usuario,
        ];
    }

    /**
     * Genera una referencia opaca para una sesión. Nunca se expone al cliente
     * el nombre real del archivo ni el session_id (equivalente a la cookie).
     */
    private function _token_sesion($session_key)
    {
        $clave = (string)$this->config->item('encryption_key');
        if ($clave === '') {
            $clave = (string)$this->config->item('sess_cookie_name') . '|reporte-sesiones';
        }

        return hash_hmac('sha256', (string)$session_key, $clave);
    }

    private function _filtrar_rows(array $rows, $search = '')
    {
        $search = strtolower(trim((string)$search));
        if ($search === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($search) {
            $haystack = strtolower(implode(' ', array_filter([
                (string)($row['usu_nombre'] ?? ''),
                (string)($row['usu_apellido'] ?? ''),
                (string)($row['usu_usuario'] ?? ''),
                (string)($row['rol_nombre'] ?? ''),
                (string)($row['usu_correo'] ?? ''),
                (string)($row['bit_ip'] ?? ''),
                (string)($row['bit_organization'] ?? ''),
                (string)($row['bit_isp'] ?? ''),
                (string)($row['bit_city'] ?? ''),
                (string)($row['bit_region'] ?? ''),
                (string)($row['bit_country_code'] ?? ''),
                (string)($row['bit_navegador'] ?? ''),
            ])));

            return strpos($haystack, $search) !== FALSE;
        }));
    }

    private function _get_active_sessions()
    {
        if (is_array($this->cached_sessions)) {
            return $this->cached_sessions;
        }

        $session_dir = $this->_resolver_directorio_sesiones();
        if ($session_dir === '' || !is_dir($session_dir)) {
            return $this->cached_sessions = [];
        }

        $cookie_name = trim((string)$this->config->item('sess_cookie_name'));
        if ($cookie_name === '') {
            $cookie_name = 'ci_session';
        }

        $pattern = rtrim($session_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cookie_name . '*';
        $files = glob($pattern) ?: [];
        if (empty($files)) {
            return $this->cached_sessions = [];
        }

        $expiration = (int)$this->config->item('sess_expiration');
        if ($expiration <= 0) {
            $expiration = 7200;
        }

        $raw_sessions = [];
        $user_ids = [];
        $usernames = [];

        // El driver de sesiones de CI mantiene un flock exclusivo sobre el archivo
        // de la petición en curso. En Windows esa lectura devuelve contenido vacío,
        // por lo que la sesión del usuario actual se resuelve desde $_SESSION.
        $own_file = '';
        $current_session_id = (string)session_id();
        if ($current_session_id !== '') {
            $own_file = $cookie_name . $current_session_id;
        }

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $last_mtime = @filemtime($file);
            if ($last_mtime !== FALSE && ($last_mtime + $expiration) < time()) {
                continue;
            }

            $payload = $this->_leer_payload_sesion($file, $own_file);
            $session_user = is_array($payload['usuario'] ?? NULL) ? $payload['usuario'] : [];
            if (empty($session_user['usu_id'])) {
                continue;
            }

            $last_activity = !empty($payload['last_activity']) ? (int)$payload['last_activity'] : $last_mtime;
            $session_key = basename($file);
            $raw_sessions[$session_key] = [
                'session_key' => $session_key,
                'session_file' => $file,
                'ultima_actividad' => $last_activity ? date('Y-m-d H:i:s', $last_activity) : '',
                'sesion_desde' => (string)($payload['login_meta']['bit_date'] ?? ''),
                'session_user' => $session_user,
                'login_meta' => is_array($payload['login_meta'] ?? NULL) ? $payload['login_meta'] : [],
            ];

            $user_ids[] = (string)$session_user['usu_id'];
            $usernames[] = trim((string)($session_user['usu_usuario'] ?? ''));
        }

        if (empty($raw_sessions)) {
            return $this->cached_sessions = [];
        }

        $usuarios = $this->_cargar_usuarios(array_values(array_unique($user_ids)));
        $logins = $this->_cargar_ultimos_logins(array_values(array_unique(array_filter($usernames))));
        $rows = [];

        foreach ($raw_sessions as $session_key => $session_row) {
            $session_user = $session_row['session_user'];
            $usuario_id = (string)$session_user['usu_id'];
            $username = trim((string)($session_user['usu_usuario'] ?? ''));
            $usuario_db = $usuarios[$usuario_id] ?? [];
            $login_meta = $this->_resolver_login_meta_sesion($session_row['login_meta'] ?? [], $logins[$username] ?? []);

            $rows[] = [
                'session_key' => $session_key,
                'session_token' => $this->_token_sesion($session_key),
                'session_file' => $session_row['session_file'],
                'usu_id' => $usuario_id,
                'usu_nombre' => (string)($usuario_db['usu_nombre'] ?? $session_user['usu_nombre'] ?? 'Usuario'),
                'usu_apellido' => (string)($usuario_db['usu_apellido'] ?? $session_user['usu_apellido'] ?? ''),
                'usu_usuario' => $username,
                'usu_correo' => (string)($usuario_db['usu_correo'] ?? $session_user['usu_correo'] ?? ''),
                'usu_tipoper' => (string)($usuario_db['usu_tipoper'] ?? $session_user['usu_tipoper'] ?? ''),
                'rol_nombre' => (string)($usuario_db['rol_nombre'] ?? 'Sin rol'),
                'usu_perfil' => (string)($usuario_db['usu_perfil'] ?? $session_user['usu_perfil'] ?? ''),
                'usu_foto' => (string)($usuario_db['usu_foto'] ?? $session_user['usu_foto'] ?? ''),
                'usu_status' => (string)($usuario_db['usu_status'] ?? '1'),
                'sesion_desde' => $session_row['sesion_desde'] !== '' ? $session_row['sesion_desde'] : (string)($login_meta['bit_date'] ?? $session_row['ultima_actividad']),
                'ultima_actividad' => $session_row['ultima_actividad'],
                'bit_ip' => (string)($login_meta['bit_ip'] ?? ''),
                'bit_host' => (string)($login_meta['bit_host'] ?? ''),
                'bit_asn' => (string)($login_meta['bit_asn'] ?? ''),
                'bit_isp' => (string)($login_meta['bit_isp'] ?? ''),
                'bit_navegador' => (string)($login_meta['bit_navegador'] ?? ''),
                'bit_proxy' => (string)($login_meta['bit_proxy'] ?? '0'),
                'bit_tor' => (string)($login_meta['bit_tor'] ?? '0'),
                'bit_vpn' => (string)($login_meta['bit_vpn'] ?? '0'),
                'bit_mobile' => (string)($login_meta['bit_mobile'] ?? '0'),
                'bit_country_code' => (string)($login_meta['bit_country_code'] ?? ''),
                'bit_region' => (string)($login_meta['bit_region'] ?? ''),
                'bit_city' => (string)($login_meta['bit_city'] ?? ''),
                'bit_organization' => (string)($login_meta['bit_organization'] ?? ''),
                'bit_latitude' => (string)($login_meta['bit_latitude'] ?? ''),
                'bit_longitude' => (string)($login_meta['bit_longitude'] ?? ''),
                'bit_request_id' => (string)($login_meta['bit_request_id'] ?? ''),
                'bit_is_crawler' => (string)($login_meta['bit_is_crawler'] ?? '0'),
            ];
        }

        $this->cached_raw_sessions = $rows;

        $rows = array_values(array_filter($rows, function ($row) {
            return (string)($row['usu_status'] ?? '1') === '1';
        }));

        return $this->cached_sessions = $this->_consolidar_sesiones_por_usuario($rows);
    }

    private function _resolver_login_meta_sesion(array $session_meta, array $db_meta)
    {
        if (empty($session_meta)) {
            return $db_meta;
        }

        if (empty($db_meta)) {
            return $session_meta;
        }

        $session_date = strtotime((string)($session_meta['bit_date'] ?? '')) ?: 0;
        $db_date = strtotime((string)($db_meta['bit_date'] ?? '')) ?: 0;

        if ($db_date >= $session_date) {
            return array_merge($session_meta, $db_meta);
        }

        return array_merge($db_meta, $session_meta);
    }

    private function _cargar_usuarios(array $ids)
    {
        if (empty($ids)) {
            return [];
        }

        $select = 'u.usu_id, u.usu_nombre, u.usu_usuario, u.usu_correo, u.usu_tipoper, u.usu_perfil, u.usu_foto, u.usu_status, r.per_nombre AS rol_nombre';
        if ($this->_tiene_apellido()) {
            $select .= ', u.usu_apellido';
        }

        $rows = $this->db
            ->select($select)
            ->from($this->tabla_usuarios . ' u')
            ->join($this->tabla_roles . ' r', 'r.per_id = u.usu_tipoper', 'left')
            ->where_in('u.usu_id', $ids)
            ->get()
            ->result_array();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string)$row['usu_id']] = $row;
        }

        return $indexed;
    }

    private function _tiene_apellido()
    {
        if ($this->tiene_apellido === NULL) {
            $this->tiene_apellido = $this->db->field_exists('usu_apellido', $this->tabla_usuarios);
        }

        return $this->tiene_apellido;
    }

    private function _cargar_ultimos_logins(array $usernames)
    {
        if (empty($usernames) || !$this->db->table_exists($this->tabla_logins)) {
            return [];
        }

        $rows = $this->db
            ->select('bit_usuario, bit_date, bit_asn, bit_isp, bit_navegador, bit_ip, bit_proxy, bit_tor, bit_vpn, bit_mobile, bit_country_code, bit_region, bit_city, bit_organization, bit_latitude, bit_longitude, bit_request_id, bit_is_crawler, bit_host')
            ->where_in('bit_usuario', $usernames)
            ->order_by('bit_date', 'DESC')
            ->get($this->tabla_logins)
            ->result_array();

        $indexed = [];
        foreach ($rows as $row) {
            $username = trim((string)($row['bit_usuario'] ?? ''));
            if ($username === '' || isset($indexed[$username])) {
                continue;
            }

            $indexed[$username] = $row;
        }

        return $indexed;
    }

    private function _cerrar_sesiones_usuario($usuario_id)
    {
        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return 0;
        }

        $cerradas = 0;
        foreach ($this->_get_raw_active_sessions() as $row) {
            if ((string)$row['usu_id'] !== $usuario_id) {
                continue;
            }

            $file = (string)($row['session_file'] ?? '');
            if ($file !== '' && is_file($file) && @unlink($file)) {
                $cerradas++;
            }
        }

        return $cerradas;
    }

    private function _get_raw_active_sessions()
    {
        if (!is_array($this->cached_raw_sessions)) {
            $this->_get_active_sessions();
        }

        return is_array($this->cached_raw_sessions) ? $this->cached_raw_sessions : [];
    }

    private function _resolver_directorio_sesiones()
    {
        $path = trim((string)$this->config->item('sess_save_path'));
        if ($path === '') {
            $path = trim((string)session_save_path());
        }

        if ($path === '') {
            $path = trim((string)ini_get('session.save_path'));
        }

        if (strpos($path, ';') !== FALSE && !$this->_es_ruta_absoluta($path)) {
            $segments = explode(';', $path);
            $path = trim((string)end($segments));
        }

        if ($path === '') {
            return '';
        }

        if (!$this->_es_ruta_absoluta($path)) {
            $path = rtrim(FCPATH, '\/') . DIRECTORY_SEPARATOR . trim($path, '\/');
        }

        return $path;
    }

    private function _consolidar_sesiones_por_usuario(array $rows)
    {
        $grouped = [];

        foreach ($rows as $row) {
            $usuario_id = (string)($row['usu_id'] ?? '');
            if ($usuario_id === '') {
                continue;
            }

            if (!isset($grouped[$usuario_id])) {
                $row['active_sessions_count'] = 1;
                $grouped[$usuario_id] = $row;
                continue;
            }

            $grouped[$usuario_id]['active_sessions_count'] = (int)($grouped[$usuario_id]['active_sessions_count'] ?? 1) + 1;

            $currentActivity = strtotime((string)($grouped[$usuario_id]['ultima_actividad'] ?? '')) ?: 0;
            $candidateActivity = strtotime((string)($row['ultima_actividad'] ?? '')) ?: 0;

            if ($candidateActivity > $currentActivity) {
                $row['active_sessions_count'] = $grouped[$usuario_id]['active_sessions_count'];
                $grouped[$usuario_id] = $row;
                continue;
            }

            if ((string)($grouped[$usuario_id]['sesion_desde'] ?? '') === '' && (string)($row['sesion_desde'] ?? '') !== '') {
                $grouped[$usuario_id]['sesion_desde'] = $row['sesion_desde'];
            }
        }

        return array_values($grouped);
    }

    private function _es_ruta_absoluta($path)
    {
        $path = trim((string)$path);
        if ($path === '') {
            return FALSE;
        }

        if (strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/')) {
            return TRUE;
        }

        if (isset($path[0], $path[1]) && (($path[0] === '\\' && $path[1] === '\\') || ($path[0] === '/' && $path[1] === '/'))) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Lee el payload de un archivo de sesión sin bloquear ni alterar la sesión
     * activa. Si el archivo está bloqueado por una petición concurrente se
     * reintenta brevemente y, si sigue bloqueado, se omite esa sesión.
     */
    private function _leer_payload_sesion($file, $own_file = '')
    {
        if ($own_file !== '' && basename($file) === $own_file) {
            return isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
        }

        $handle = @fopen($file, 'rb');
        if ($handle === FALSE) {
            return [];
        }

        $raw = '';
        $bloqueado = @flock($handle, LOCK_SH | LOCK_NB);
        if (!$bloqueado) {
            for ($intento = 0; $intento < 3; $intento++) {
                usleep(15000);
                $bloqueado = @flock($handle, LOCK_SH | LOCK_NB);
                if ($bloqueado) {
                    break;
                }
            }
        }

        if ($bloqueado) {
            $raw = (string)stream_get_contents($handle);
            @flock($handle, LOCK_UN);
        }

        fclose($handle);

        if ($raw === '') {
            return [];
        }

        return $this->_decodificar_payload_sesion($raw);
    }

    /**
     * Decodifica el formato nativo de sesión PHP (`clave|valor_serializado`)
     * sin usar session_decode(), evitando sobrescribir $_SESSION.
     */
    private function _decodificar_payload_sesion($raw)
    {
        $raw = (string)$raw;
        if ($raw === '') {
            return [];
        }

        $payload = [];
        $offset = 0;
        $length = strlen($raw);

        while ($offset < $length) {
            $pipe = strpos($raw, '|', $offset);
            if ($pipe === FALSE) {
                break;
            }

            $name = substr($raw, $offset, $pipe - $offset);
            if ($name === '') {
                break;
            }

            $end = $this->_fin_valor_sesion($raw, $pipe + 1);
            if ($end === FALSE) {
                break;
            }

            $serializado = substr($raw, $pipe + 1, $end - $pipe - 1);
            $payload[$name] = @unserialize($serializado, ['allowed_classes' => FALSE]);
            $offset = $end;
        }

        return $payload;
    }

    /**
     * Devuelve la posición inmediatamente posterior a un valor serializado de
     * PHP para poder avanzar dentro del payload de sesión.
     */
    private function _fin_valor_sesion($raw, $pos)
    {
        $length = strlen($raw);
        if ($pos >= $length) {
            return FALSE;
        }

        $tipo = $raw[$pos];

        if ($tipo === 'N') {
            return ($pos + 1 < $length && $raw[$pos + 1] === ';') ? $pos + 2 : FALSE;
        }

        if (in_array($tipo, ['b', 'i', 'd', 'r', 'R'], TRUE)) {
            $semi = strpos($raw, ';', $pos);
            return $semi === FALSE ? FALSE : $semi + 1;
        }

        if ($tipo === 's' || $tipo === 'E') {
            $colon = strpos($raw, ':', $pos);
            if ($colon === FALSE) {
                return FALSE;
            }
            $quote = strpos($raw, '"', $colon);
            if ($quote === FALSE) {
                return FALSE;
            }
            $strlen = (int)substr($raw, $colon + 1, $quote - $colon - 1);
            $end = $quote + 1 + $strlen;
            return ($end + 1 < $length && $raw[$end] === '"' && $raw[$end + 1] === ';') ? $end + 2 : FALSE;
        }

        if ($tipo === 'a') {
            $colon = strpos($raw, ':', $pos);
            if ($colon === FALSE) {
                return FALSE;
            }
            $brace = strpos($raw, '{', $colon + 1);
            if ($brace === FALSE) {
                return FALSE;
            }
            $count = (int)substr($raw, $colon + 1, $brace - $colon - 2);
            return $this->_fin_estructura_sesion($raw, $brace, $count);
        }

        if ($tipo === 'O') {
            $brace = strpos($raw, '{', $pos);
            if ($brace === FALSE) {
                return FALSE;
            }
            $prev_colon = strrpos(substr($raw, 0, $brace), ':');
            if ($prev_colon === FALSE) {
                return FALSE;
            }
            $count = (int)substr($raw, $prev_colon + 1, $brace - $prev_colon - 1);
            return $this->_fin_estructura_sesion($raw, $brace, $count);
        }

        if ($tipo === 'C') {
            $colon = strpos($raw, ':', $pos);
            if ($colon === FALSE) {
                return FALSE;
            }
            $quote = strpos($raw, '"', $colon);
            if ($quote === FALSE) {
                return FALSE;
            }
            $classlen = (int)substr($raw, $colon + 1, $quote - $colon - 1);
            $after_class = $quote + 1 + $classlen;
            if ($after_class >= $length || $raw[$after_class] !== '"') {
                return FALSE;
            }
            $data_colon = strpos($raw, ':', $after_class);
            if ($data_colon === FALSE) {
                return FALSE;
            }
            $brace = strpos($raw, '{', $data_colon);
            if ($brace === FALSE) {
                return FALSE;
            }
            $datalen = (int)substr($raw, $data_colon + 1, $brace - $data_colon - 1);
            $end = $brace + 1 + $datalen;
            return ($end < $length && $raw[$end] === '}') ? $end + 1 : FALSE;
        }

        return FALSE;
    }

    private function _fin_estructura_sesion($raw, $brace, $count)
    {
        $length = strlen($raw);
        $cursor = $brace + 1;

        for ($i = 0; $i < $count; $i++) {
            $cursor = $this->_fin_valor_sesion($raw, $cursor);
            if ($cursor === FALSE) {
                return FALSE;
            }
            $cursor = $this->_fin_valor_sesion($raw, $cursor);
            if ($cursor === FALSE) {
                return FALSE;
            }
        }

        return ($cursor < $length && $raw[$cursor] === '}') ? $cursor + 1 : FALSE;
    }
}