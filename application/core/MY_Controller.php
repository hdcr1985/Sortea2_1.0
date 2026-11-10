<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — versión actualizada
 * ─────────────────────────────────────────────────────────────
 * Cambios respecto a la versión anterior:
 *   · Agrega verificación de acceso por URL después de cargar el menú
 *   · URLs fijas (libres) definidas en $urls_libres
 *   · Redirige a vista 403 si no tiene permiso
 */
class MY_Controller extends CI_Controller
{
    protected $layout_data  = [];
    protected $page_title   = 'Dashboard';
    protected $breadcrumb   = [];
    protected $usuario      = [];
    protected $page_scripts = [];
    protected $page_styles  = [];
    protected $page_vendor_scripts = [];
    protected $url_actual   = '';
    protected $url_base     = '';
    protected $menu_estructura = [];
    protected $menu_actual  = NULL;
    protected $es_master    = FALSE;

    /**
     * URLs que NO pasan por verificación de permisos en la BD.
     * Son rutas del sistema que todo usuario autenticado puede usar.
     */
    private $urls_libres = [
        '',             // dashboard / raíz
        'dashboard',
        'ping-sesion',
        'logout',
        'sin-permisos',
        'perfil',
        'mantenimiento',
        'media',
        'errors',
        'mi-vitrina',
        'mi-tienda', // alias retrocompatible
        'mis-rifas', // autoservicio del organizador (M03)
    ];

    /**
     * Métodos de controlador accesibles a cualquier usuario autenticado,
     * aunque el módulo completo esté restringido por menú.
     * Ej: el feed de notificaciones del header es global.
     */
    private $metodos_libres = [
        'notificaciones' => ['feed', 'marcar_leida', 'marcar_todas'],
    ];

    // ─────────────────────────────────────────────────────────
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('network');

        // Cargar modelos requeridos para configuración y sesión
        $this->load->model('Configuracion_general_model');
        $this->load->model('Configuracion_tema_model');
        $this->load->model('Menu_model');

        // 1. Verificar sesión activa e inactividad
        $this->_verificar_sesion();

        // 2. Datos del usuario en sesión
        $this->usuario = $this->session->userdata('usuario');
        $theme_settings = $this->Configuracion_tema_model->get_settings();
        $general_settings = $this->Configuracion_general_model->get_settings();

        // 4. URL actual
        $this->url_actual = trim($this->uri->uri_string(), '/');
        $this->url_base   = $this->_resolver_url_base($this->url_actual);

        // 5. Estructura de menús y rol resueltos desde la sesión.
        //    No se declaran IDs de menú ni de rol en el código.
        $this->menu_estructura = $this->_cargar_estructura_permisos();
        $this->es_master       = (bool)$this->session->userdata('es_master');
        $this->menu_actual     = $this->Menu_model->resolver_menu_por_url(
            $this->menu_estructura,
            $this->url_actual
        );

        // 6. Modo de mantenimiento (el rol con cobertura total y logout no se bloquean)
        if (($general_settings['mantenimiento_modo'] ?? '0') === '1' && !$this->es_master) {
            if ($this->url_actual !== 'logout' && $this->url_actual !== 'mantenimiento') {
                if ($this->input->is_ajax_request()) {
                    $this->output
                        ->set_status_header(503)
                        ->set_content_type('application/json', 'utf-8')
                        ->set_output(json_encode([
                            'ok' => false,
                            'msg' => !empty($general_settings['mantenimiento_mensaje']) ? $general_settings['mantenimiento_mensaje'] : 'El sistema se encuentra en mantenimiento programado.'
                        ]))
                        ->_display();
                    exit;
                }

                redirect('mantenimiento');
                return;
            }
        }

        // 7. Árbol de menús para el layout (construido desde el snapshot de sesión)
        $menu = ($this->url_actual === 'mantenimiento')
            ? []
            : $this->Menu_model->construir_arbol($this->menu_estructura);

        // 8. Verificar acceso a la URL actual
        //    Solo si NO está en la lista de URLs libres (comprueba tanto la URL completa como la URL base)
        if (!in_array($this->url_actual, $this->urls_libres, TRUE) && !in_array($this->url_base, $this->urls_libres, TRUE)) {
            $this->_verificar_acceso_url($this->url_actual);
        }

        // 9. Datos globales del layout
        $this->layout_data = [
            'menu'             => $menu,
            'url_actual'       => $this->url_actual,
            'url_base'         => $this->url_base,
            'usuario'          => $this->usuario,
            'es_master'        => $this->es_master,
            'rol_nombre'       => (string)$this->session->userdata('rol_nombre'),
            'app_name'         => $theme_settings['project_name'],
            'theme_settings'   => $theme_settings,
            'theme_css'        => $theme_settings['theme_css'],
            'general_settings' => $general_settings,
        ];
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Renderiza una vista dentro del layout de Metronic.
     */
    protected function render($vista, $datos = [])
    {
        $datos = array_merge($this->layout_data, $datos, [
            'page_title'   => $this->page_title,
            'breadcrumb'   => $this->breadcrumb,
            'page_styles'  => $this->page_styles,
            'page_scripts' => $this->page_scripts,
            'page_vendor_scripts' => $this->page_vendor_scripts,
        ]);

        $datos['content'] = $this->load->view($vista, $datos, TRUE);
        $this->load->view('layouts/main', $datos);
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Verifica si el usuario tiene permiso CRUD sobre el módulo actual.
     *
     * Firma nueva: tiene_permiso('modificar')
     * Firma heredada: tiene_permiso($men_id, 'modificar') — se conserva por
     * compatibilidad, pero resuelve el menú desde el snapshot de sesión.
     */
    protected function tiene_permiso($accion = 'consultar', $men_id = NULL)
    {
        // Compatibilidad con la firma anterior (men_id, accion)
        if (is_numeric($accion)) {
            $legacy_men = (int)$accion;
            $accion     = $men_id ?: 'consultar';
            $men_id     = $legacy_men;
        }

        // El rol con cobertura total conserva privilegios globales.
        if ($this->es_master) {
            return TRUE;
        }

        $menu = NULL;
        if ($men_id !== NULL) {
            $menu = $this->menu_estructura[(int)$men_id] ?? NULL;
        } else {
            $menu = $this->menu_actual;
        }

        if (empty($menu)) {
            return FALSE;
        }

        $col = 'acc_' . $accion;
        return isset($menu[$col]) && (int)$menu[$col] === 1;
    }

    /**
     * Verifica un permiso CRUD sobre otro módulo identificado por su URL,
     * sin declarar su ID. Útil para controles cruzados (ej. bitácora).
     */
    protected function tiene_permiso_url($url, $accion = 'consultar')
    {
        if ($this->es_master) {
            return TRUE;
        }

        $menu = $this->Menu_model->resolver_menu_por_url($this->menu_estructura, $url);
        if (empty($menu)) {
            return FALSE;
        }

        $col = 'acc_' . $accion;
        return isset($menu[$col]) && (int)$menu[$col] === 1;
    }

    /**
     * Exige un permiso CRUD y corta la petición si no se cumple.
     * Respuesta JSON 403 para AJAX o redirección a sin-permisos.
     *
     * Firma nueva: autorizar_accion('modificar', 'Mensaje')
     * Firma heredada: autorizar_accion($men_id, 'modificar', 'Mensaje')
     */
    protected function autorizar_accion($accion = 'consultar', $mensaje = 'No tienes permiso para realizar esta acción.', $men_id = NULL)
    {
        if (is_numeric($accion)) {
            $legacy_men = (int)$accion;
            $legacy_acc = (string)$mensaje;
            $legacy_msg = func_num_args() >= 3 ? (string)func_get_arg(2) : 'No tienes permiso para realizar esta acción.';
            $accion     = $legacy_acc;
            $mensaje    = $legacy_msg;
            $men_id     = $legacy_men;
        }

        if ($this->tiene_permiso($accion, $men_id)) {
            return TRUE;
        }

        if ($this->input->is_ajax_request()) {
            $this->json(['ok' => FALSE, 'msg' => $mensaje], 403);
            $this->output->_display();
            exit;
        }

        redirect('sin-permisos');
        exit;
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Responde con JSON para llamadas AJAX.
     */
    protected function json($data, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Verifica que exista una tabla requerida antes de ejecutar lógica AJAX.
     */
    protected function require_table($table, $datatable = FALSE)
    {
        if ($this->db->table_exists($table)) {
            return TRUE;
        }

        $msg = 'La tabla ' . $table . ' no existe en la base de datos.';

        if ($datatable) {
            $this->json([
                'draw' => (int)$this->input->post('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $msg,
            ]);

            return FALSE;
        }

        $this->json([
            'ok' => FALSE,
            'msg' => $msg,
        ], 500);

        return FALSE;
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Devuelve la ruta base del módulo actual con un segmento opcional.
     */
    protected function module_path($path = '')
    {
        $base = trim($this->url_base, '/');
        $path = trim($path, '/');

        if ($base === '') {
            return $path;
        }

        return ($path === '') ? $base : $base . '/' . $path;
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Registra un evento en cab_bitacora.
     */
    protected function bitacora($accion, $detalle = '')
    {
        // cab_bitacora.bit_detalles es VARCHAR(255). Truncar evita fallos en
        // entornos estrictos y mantiene la trazabilidad de la mutación.
        $detalle = (string)$detalle;
        if (function_exists('mb_substr')) {
            $detalle = mb_substr($detalle, 0, 255, 'UTF-8');
        } else {
            $detalle = substr($detalle, 0, 255);
        }

        // CI3 termina el proceso con exit(8) si db_debug está activo y la
        // escritura falla. La auditoría no debe romper la mutación principal.
        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $ok = $this->db->insert('cab_bitacora', [
                'bit_usuario'   => $this->usuario['usu_usuario'] ?? 'sistema',
                'bit_date'      => date('Y-m-d H:i:s'),
                'bit_accion'    => $accion,
                'bit_detalles'  => $detalle,
                'bit_navegador' => $this->input->user_agent(),
                'bit_ip'        => app_resolver_client_ip($this->input),
            ]);
            $error = $this->db->error();
        } catch (Throwable $e) {
            $ok    = FALSE;
            $error = ['code' => -1, 'message' => ''];
        } finally {
            $this->db->db_debug = $debug_previo;
        }

        if (!$ok || !empty($error['code'])) {
            log_message('error', 'No se pudo registrar el evento en cab_bitacora.');
        }
    }

    /**
     * Obtiene la configuracion centralizada de telefonia.
     */
    protected function telefono_config()
    {
        $this->config->load('telefonia', TRUE);
        $config = $this->config->item('telefonia', 'telefonia');

        return is_array($config) ? $config : [];
    }

    /**
     * Normaliza un celular dejando solo digitos.
     */
    protected function normalizar_celular($valor)
    {
        return preg_replace('/\D+/', '', trim((string)$valor));
    }

    /**
     * Valida celulares de Venezuela segun prefijos configurados y longitud fija.
     */
    protected function validar_celular_ve($valor, $required = TRUE)
    {
        $valor = $this->normalizar_celular($valor);
        $config = $this->telefono_config();
        $prefijos = array_values(array_filter(array_map('strval', $config['celular_ve_prefijos'] ?? [])));
        $longitud = (int)($config['celular_ve_longitud'] ?? 11);

        if ($valor === '') {
            return [
                'ok' => !$required,
                'valor' => '',
                'msg' => 'El celular es requerido.',
            ];
        }

        if (!ctype_digit($valor)) {
            return [
                'ok' => FALSE,
                'valor' => $valor,
                'msg' => 'El celular solo debe contener números.',
            ];
        }

        if ($longitud > 0 && strlen($valor) !== $longitud) {
            return [
                'ok' => FALSE,
                'valor' => $valor,
                'msg' => 'El celular debe tener exactamente ' . $longitud . ' dígitos.',
            ];
        }

        if (!empty($prefijos) && !in_array(substr($valor, 0, 4), $prefijos, TRUE)) {
            return [
                'ok' => FALSE,
                'valor' => $valor,
                'msg' => 'El celular debe comenzar por uno de estos prefijos: ' . implode(', ', $prefijos) . '.',
            ];
        }

        return [
            'ok' => TRUE,
            'valor' => $valor,
            'msg' => '',
        ];
    }

    // ─────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Carga la estructura de menús desde la sesión. Si no existe o la versión
     * global de permisos cambió, la reconstruye desde la base de datos y
     * actualiza el snapshot de la sesión.
     */
    private function _cargar_estructura_permisos()
    {
        $version         = (string)$this->Configuracion_general_model->get_valor('permisos_version', '0');
        $json            = $this->session->userdata('menu_estructura');
        $session_version = (string)$this->session->userdata('menu_version');

        if ($json === NULL || $json === FALSE || $json === '' || $session_version !== $version) {
            $rol_id     = (string)($this->usuario['usu_tipoper'] ?? '');
            $estructura = $this->Menu_model->get_estructura_permisos($rol_id);

            $this->session->set_userdata([
                'menu_estructura' => json_encode($estructura, JSON_UNESCAPED_UNICODE),
                'menu_version'    => $version,
                'es_master'       => $this->Menu_model->rol_es_master($rol_id) ? 1 : 0,
            ]);

            return $estructura;
        }

        $estructura = json_decode((string)$json, TRUE);
        return is_array($estructura) ? $estructura : [];
    }

    /**
     * Verifica sesión activa y controla la expiración por inactividad.
     * Implementa Sliding Expiration: el tiempo de inactividad se renueva
     * con cada acción del usuario, y solo expira si no hay actividad.
     */
    private function _verificar_sesion()
    {
        $usuario = $this->session->userdata('usuario');
        if (empty($usuario) || empty($usuario['usu_id'])) {
            if ($this->input->is_ajax_request()) {
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'ok' => FALSE,
                        'msg' => 'Tu sesión no está activa o ha expirado. Debes iniciar sesión nuevamente.',
                        'session_expired' => TRUE,
                    ]))
                    ->_display();
                exit;
            }

            redirect('login');
            return;
        }

        // 1. Obtener límite de inactividad permitido (en segundos)
        $minutos_inactividad = 120;
        if (isset($this->Configuracion_general_model)) {
            $minutos_inactividad = (int)($this->Configuracion_general_model->get_valor('sesion_inactividad_minutos', '120') ?: 120);
        }
        if ($minutos_inactividad <= 0) {
            $inactivity_limit = (int)(config_item('sess_expiration') ?: 7200);
        } else {
            $inactivity_limit = $minutos_inactividad * 60;
        }

        $now = time();
        $last_activity = (int) $this->session->userdata('last_activity');

        // 2. Comprobar si superó el tiempo máximo de inactividad sin realizar acciones
        if ($last_activity > 0 && ($now - $last_activity) > $inactivity_limit) {
            $this->session->sess_destroy();

            if ($this->input->is_ajax_request()) {
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'ok' => FALSE,
                        'msg' => 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.',
                        'session_expired' => TRUE,
                    ]))
                    ->_display();
                exit;
            }

            $this->session->set_flashdata('error', 'Tu sesión ha expirado por inactividad.');
            redirect('login?expired=1');
            return;
        }

        // 3. Manejo de actividad del usuario vs sondeos automáticos en segundo plano
        //    Los sondeos automáticos (como X-Background-Poll de notificaciones) no renuevan
        //    el contador de inactividad para no mantener la sesión abierta si el usuario dejó la PC.
        $is_background_poll = ($this->input->get_request_header('X-Background-Poll') === '1');

        if (!$is_background_poll) {
            // Actividad genuina del usuario: actualizar marca de tiempo y deslizar cookie en navegador
            $this->session->set_userdata('last_activity', $now);
            $this->_renovar_cookie_sesion($inactivity_limit);
        }

        // 4. Verificar que el usuario siga existiendo y activo en la base de datos
        $usuario_activo = $this->db
            ->select('usu_id')
            ->where('usu_id', (string)$usuario['usu_id'])
            ->where('usu_status', '1')
            ->limit(1)
            ->get('cab_usuarios')
            ->row_array();

        if (empty($usuario_activo)) {
            $this->session->sess_destroy();

            if ($this->input->is_ajax_request()) {
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'ok' => FALSE,
                        'msg' => 'Tu usuario ya no está activo en el sistema. Debes iniciar sesión nuevamente.',
                        'session_expired' => TRUE,
                    ]))
                    ->_display();
                exit;
            }

            redirect('login');
            return;
        }
    }

    /**
     * Renueva el cookie de sesión en el navegador deslizando su tiempo de expiración
     * para que coincida con la actividad del usuario (Sliding Expiration).
     */
    protected function _renovar_cookie_sesion($lifetime)
    {
        if (headers_sent()) {
            return;
        }

        $cookie_name = config_item('sess_cookie_name') ?: 'ci_session';
        $session_id  = session_id();
        if (empty($session_id)) {
            return;
        }

        $path     = config_item('cookie_path') ?: '/';
        $domain   = config_item('cookie_domain') ?: '';
        $secure   = (bool) config_item('cookie_secure');
        $samesite = config_item('sess_samesite') ?: 'Lax';
        $expires  = time() + (int)$lifetime;

        if (PHP_VERSION_ID >= 70300) {
            @setcookie($cookie_name, $session_id, [
                'expires'  => $expires,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => TRUE,
                'samesite' => $samesite,
            ]);
        } else {
            @setcookie($cookie_name, $session_id, $expires, $path, $domain, $secure, TRUE);
        }
    }

    /**
     * Verifica que el controlador actual esté registrado en cab_menu y que el
     * rol del usuario tenga acceso. Aplica deny-by-default para controladores
     * administrativos no registrados y compara por prefijo de men_url, no solo
     * por coincidencia exacta (evita el bypass por URL directa).
     */
    private function _verificar_acceso_url($url_actual)
    {
        $controlador = strtolower(trim((string)$this->router->fetch_class()));
        $metodo      = strtolower(trim((string)$this->router->fetch_method()));
        $amigable    = strtolower(trim((string)(explode('/', (string)$url_actual)[0] ?? '')));

        // Rutas y controladores libres
        if ($controlador === ''
            || in_array($controlador, $this->urls_libres, TRUE)
            || in_array($amigable, $this->urls_libres, TRUE)) {
            return;
        }

        // Métodos globales permitidos dentro de un módulo restringido
        if (isset($this->metodos_libres[$controlador])
            && in_array($metodo, $this->metodos_libres[$controlador], TRUE)) {
            return;
        }

        // El rol con cobertura total no requiere asignación explícita
        if ($this->es_master) {
            return;
        }

        // Deny-by-default: el módulo debe existir en el snapshot de menús
        // del rol. La resolución se hizo por URL, sin IDs codificados.
        if (empty($this->menu_actual)) {
            $this->_denegar_acceso();
        }
    }

    /**
     * Corta la petición por falta de autorización.
     */
    private function _denegar_acceso()
    {
        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'ok'  => FALSE,
                    'msg' => 'No tienes permiso para realizar esta acción.',
                ]))
                ->_display();
            exit;
        }

        redirect('sin-permisos');
        exit;
    }

    /**
     * Obtiene el primer segmento de la URL actual.
     */
    private function _resolver_url_base($url_actual)
    {
        if ($url_actual === '') {
            return '';
        }

        return explode('/', $url_actual)[0];
    }
}
