<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Home
 * Dashboard principal del sistema base.
 */
class Home extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Usuarios_model');
    }

    public function index()
    {
        $this->page_title = 'Dashboard';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
        ];

        $datos = [
            'dashboard' => $this->_armar_dashboard(),
        ];

        $this->render('pages/dashboard', $datos);
    }

    /**
     * GET /ping-sesion o /Home/ping_sesion
     * Endpoint ligero de actividad: al pasar por MY_Controller renueva
     * automáticamente last_activity y desliza el cookie de sesión.
     */
    public function ping_sesion()
    {
        return $this->json([
            'ok'        => TRUE,
            'timestamp' => time(),
            'msg'       => 'Sesión activa y renovada',
        ]);
    }

    private function _armar_dashboard()
    {
        // Contar usuarios totales y activos
        $total_usuarios   = $this->_contar_registros('cab_usuarios');
        $usuarios_activos = $this->_contar_registros('cab_usuarios', ['usu_status' => '1']);
        $total_roles      = $this->_contar_registros('cab_grupopermi', ['per_activo' => '1']);
        $total_bitacora   = $this->_contar_registros('cab_bitacora');

        // Sesiones activas desde archivos CI
        $total_sesiones   = $this->_contar_sesiones_activas();

        // Actividad reciente de bitácora (últimos 8 registros).
        // Se limita primero cab_bitacora para que el JOIN no recorra las 92k filas
        // de bitácora contra las 5k de usuarios; sin este límite el dashboard tarda >120s.
        $actividad_reciente = [];
        if ($this->db->table_exists('cab_bitacora')) {
            $actividad_reciente = $this->db->query(
                'SELECT b.bit_id, b.bit_accion, b.bit_detalles, b.bit_date, b.bit_ip, b.bit_usuario, u.usu_nombre, u.usu_foto
                 FROM (
                     SELECT bit_id, bit_accion, bit_detalles, bit_date, bit_ip, bit_usuario
                     FROM cab_bitacora
                     ORDER BY bit_id DESC
                     LIMIT 8
                 ) b
                 LEFT JOIN cab_usuarios u
                   ON CONVERT(u.usu_usuario USING utf8) COLLATE utf8_unicode_ci =
                      CONVERT(b.bit_usuario USING utf8) COLLATE utf8_unicode_ci
                 ORDER BY b.bit_id DESC'
            )->result_array();
        }

        // Inicios de sesión recientes (últimos 6 registros de cab_bitacora_ip)
        $logins_recientes = [];
        if ($this->db->table_exists('cab_bitacora_ip')) {
            $logins_recientes = $this->db
                ->select('bi.bit_id, bi.bit_usuario, bi.bit_ip, bi.bit_navegador, bi.bit_city, bi.bit_country_code, bi.bit_date, u.usu_nombre, u.usu_foto')
                ->from('cab_bitacora_ip bi')
                ->join('cab_usuarios u', 'CONVERT(u.usu_usuario USING utf8) COLLATE utf8_unicode_ci = CONVERT(bi.bit_usuario USING utf8) COLLATE utf8_unicode_ci', 'left', FALSE)
                ->order_by('bi.bit_id', 'DESC')
                ->limit(6)
                ->get()
                ->result_array();
        }

        return [
            'total_usuarios'     => $total_usuarios,
            'usuarios_activos'   => $usuarios_activos,
            'total_roles'        => $total_roles,
            'total_bitacora'     => $total_bitacora,
            'total_sesiones'     => $total_sesiones,
            'actividad_reciente' => $actividad_reciente,
            'logins_recientes'   => $logins_recientes,
        ];
    }

    private function _contar_registros($tabla, array $where = [])
    {
        if (!$this->db->table_exists($tabla)) {
            return 0;
        }

        foreach ($where as $campo => $valor) {
            $this->db->where($campo, $valor);
        }

        return (int)$this->db->count_all_results($tabla);
    }

    private function _contar_sesiones_activas()
    {
        $sess_path = config_item('sess_save_path') ?: ini_get('session.save_path');
        if (empty($sess_path) || !is_dir($sess_path)) {
            return 1;
        }

        $files = @glob(rtrim($sess_path, '\\/') . DIRECTORY_SEPARATOR . 'ci_session*');
        if (!is_array($files)) {
            return 1;
        }

        $expiration = (int)(config_item('sess_expiration') ?: 7200);
        $now = time();
        $count = 0;

        foreach ($files as $file) {
            if ($now - @filemtime($file) <= $expiration) {
                $count++;
            }
        }

        return max($count, 1);
    }
}