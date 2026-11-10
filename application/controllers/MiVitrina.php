<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MiVitrina
 * ─────────────────────────────────────────────────────────────
 * Autoservicio del organizador (M01).
 *
 * Cualquier usuario autenticado puede crear/editar su vitrina. La URL
 * "mi-vitrina" está registrada como libre en MY_Controller, por lo que este
 * controlador NO usa autorizar_accion(): el propietario siempre se resuelve
 * desde la sesión y nunca desde el request.
 */
class MiVitrina extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Organizador_model');

        $this->page_title = 'Mi Vitrina';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Mi Vitrina', 'url' => ''],
        ];
    }

    /**
     * GET /mi-vitrina
     *
     * @return void
     */
    public function index()
    {
        $usuario_id  = (string)($this->usuario['usu_id'] ?? '');
        $perfil      = $this->Organizador_model->get_por_usuario($usuario_id);
        $base_domain = $this->Organizador_model->base_domain();

        $url_publica = '';
        $host        = '';
        $plan_info   = NULL;

        if (!empty($perfil)) {
            $host        = (string)$perfil['org_slug'] . '.' . $base_domain;
            $url_publica = $this->_scheme() . '://' . $host . '/';

            $this->load->model('Plan_asignacion_model');
            $plan_info = $this->Plan_asignacion_model->get_estado_por_org((int)$perfil['org_id']);
        }

        $datos = [
            'perfil'      => $perfil,
            'plan_info'   => $plan_info,
            'url_publica' => $url_publica,
            'host'        => $host,
            'base_domain' => $base_domain,
            'reservados'  => $this->Organizador_model->slugs_reservados(),
            'flash'       => $this->session->flashdata('flash'),
            'path_resolution_enabled' => $this->_path_resolution_enabled(),
        ];

        $this->render('pages/mi_vitrina/index', $datos);
    }

    /**
     * POST /mi-vitrina/crear (AJAX)
     *
     * @return void
     */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect('mi-vitrina');
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');

        // Cada usuario puede tener un único organizador.
        if ($this->Organizador_model->get_por_usuario($usuario_id)
            || $this->Organizador_model->existe_usuario($usuario_id)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Ya existe una vitrina registrada para tu cuenta.',
            ], 409);
        }

        $data    = $this->_recoger_post(TRUE);
        $errores = $this->_validar($data, 0, TRUE);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $base_domain = $this->Organizador_model->base_domain();
        $slug        = (string)$data['org_slug'];
        $host        = $slug . '.' . $base_domain;

        if ($this->Organizador_model->existe_host($host)) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['org_slug' => 'Ese subdominio ya está en uso.'],
            ], 422);
        }

        $org = array_merge($this->_mapear_data_guardado($data, TRUE), [
            'org_usuario_id' => $usuario_id,
            'org_status'     => 1,
            'org_verificado' => 0,
        ]);

        $org_id = $this->Organizador_model->crear_con_dominio($org, $host);

        if (!$org_id) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo crear la vitrina. Inténtalo nuevamente.'], 500);
        }

        $this->bitacora(
            'ORGANIZADOR_CREAR',
            "Organizador creado: {$org['org_nombre_publico']} (ID: {$org_id}) | Slug: {$slug} | Host: {$host}"
        );

        $url = $this->_scheme() . '://' . $host . '/';

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Vitrina creada correctamente.',
            'data' => [
                'id'   => (int)$org_id,
                'slug' => $slug,
                'host' => $host,
                'url'  => $url,
            ],
        ]);
    }

    /**
     * POST /mi-vitrina/actualizar (AJAX)
     *
     * @return void
     */
    public function actualizar()
    {
        if (!$this->input->is_ajax_request()) {
            redirect('mi-vitrina');
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil     = $this->Organizador_model->get_por_usuario($usuario_id);

        if (empty($perfil)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No tienes una vitrina registrada.'], 404);
        }

        $data    = $this->_recoger_post(FALSE);
        $errores = $this->_validar($data, (int)$perfil['org_id'], FALSE);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $guardado = $this->Organizador_model->actualizar(
            (int)$perfil['org_id'],
            $this->_mapear_data_guardado($data, FALSE)
        );

        if (!$guardado) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los cambios.'], 500);
        }

        $this->bitacora(
            'ORGANIZADOR_ACTUALIZAR',
            "Organizador actualizado: {$data['org_nombre_publico']} (ID: {$perfil['org_id']}) | Slug: {$perfil['org_slug']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Datos de la vitrina actualizados correctamente.',
        ]);
    }

    /**
     * POST /mi-vitrina/cambiar-slug (AJAX)
     *
     * @return void
     */
    public function cambiar_slug()
    {
        if (!$this->input->is_ajax_request()) {
            redirect('mi-vitrina');
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil     = $this->Organizador_model->get_por_usuario($usuario_id);

        if (empty($perfil)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No tienes una vitrina registrada.'], 404);
        }

        $slug    = trim((string)$this->input->post('org_slug', TRUE));
        $errores = $this->_validar_slug($slug, (int)$perfil['org_id']);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $base_domain = $this->Organizador_model->base_domain();
        $host        = $slug . '.' . $base_domain;

        if ($this->Organizador_model->existe_host($host, (int)$perfil['org_id'])) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['org_slug' => 'Ese subdominio ya está en uso.'],
            ], 422);
        }

        if (!$this->Organizador_model->cambiar_slug((int)$perfil['org_id'], $slug, $host)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el identificador de la vitrina.'], 500);
        }

        $this->bitacora(
            'ORGANIZADOR_SLUG',
            "Slug actualizado: {$perfil['org_slug']} -> {$slug} (ID: {$perfil['org_id']}) | Host: {$host}"
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Identificador de la vitrina actualizado correctamente.',
            'data' => [
                'slug' => $slug,
                'host' => $host,
                'url'  => $this->_scheme() . '://' . $host . '/',
            ],
        ]);
    }

    /**
     * GET /mi-vitrina/ver
     * Redirige a la URL pública del tenant.
     *
     * @return void
     */
    public function ver()
    {
        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil     = $this->Organizador_model->get_por_usuario($usuario_id);

        if (empty($perfil)) {
            show_404();
        }

        redirect($this->_url_publica($perfil), 'auto', 302);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Recoge los campos editables del POST.
     *
     * @param  bool $incluir_slug
     * @return array
     */
    private function _recoger_post($incluir_slug = TRUE)
    {
        $data = [
            'org_nombre_publico' => trim((string)$this->input->post('org_nombre_publico', TRUE)),
            'org_email'          => trim((string)$this->input->post('org_email', TRUE)),
            'org_telefono'       => trim((string)$this->input->post('org_telefono', TRUE)),
        ];

        if ($incluir_slug) {
            $data['org_slug'] = trim((string)$this->input->post('org_slug', TRUE));
        }

        return $data;
    }

    /**
     * Mapea los datos validados al esquema de rif_organizadores.
     *
     * @param  array $data
     * @param  bool  $incluir_slug
     * @return array
     */
    private function _mapear_data_guardado(array $data, $incluir_slug = TRUE)
    {
        $guardado = [
            'org_nombre_publico' => (string)$data['org_nombre_publico'],
            'org_email'          => $data['org_email'] !== '' ? (string)$data['org_email'] : NULL,
            'org_telefono'       => $data['org_telefono'] !== '' ? (string)$data['org_telefono'] : NULL,
        ];

        if ($incluir_slug) {
            $guardado['org_slug'] = (string)$data['org_slug'];
        }

        return $guardado;
    }

    /**
     * Valida los datos de la vitrina.
     *
     * @param  array $data
     * @param  int   $id
     * @param  bool  $incluir_slug
     * @return array
     */
    private function _validar(array $data, $id = 0, $incluir_slug = TRUE)
    {
        $errores = [];

        $nombre = trim((string)($data['org_nombre_publico'] ?? ''));
        if ($nombre === '') {
            $errores['org_nombre_publico'] = 'El nombre público es obligatorio.';
        } elseif ($this->_longitud($nombre) < 3) {
            $errores['org_nombre_publico'] = 'El nombre público debe tener al menos 3 caracteres.';
        } elseif ($this->_longitud($nombre) > 150) {
            $errores['org_nombre_publico'] = 'El nombre público no puede exceder los 150 caracteres.';
        }

        $email = trim((string)($data['org_email'] ?? ''));
        if ($email !== '') {
            if ($this->_longitud($email) > 150) {
                $errores['org_email'] = 'El correo no puede exceder los 150 caracteres.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores['org_email'] = 'Ingresa un correo electrónico válido.';
            }
        }

        $telefono = trim((string)($data['org_telefono'] ?? ''));
        if ($telefono !== '' && $this->_longitud($telefono) > 30) {
            $errores['org_telefono'] = 'El teléfono no puede exceder los 30 caracteres.';
        }

        if ($incluir_slug) {
            $errores = array_merge(
                $errores,
                $this->_validar_slug((string)($data['org_slug'] ?? ''), (int)$id)
            );
        }

        return $errores;
    }

    /**
     * Valida el slug público y su disponibilidad.
     *
     * @param  string $slug
     * @param  int    $ignorar_id
     * @return array
     */
    private function _validar_slug($slug, $ignorar_id = 0)
    {
        $errores = [];
        $slug = (string)$slug;

        if ($slug === '') {
            $errores['org_slug'] = 'El identificador de la vitrina es obligatorio.';
            return $errores;
        }

        if (strlen($slug) < 3 || strlen($slug) > 63) {
            $errores['org_slug'] = 'El identificador debe tener entre 3 y 63 caracteres.';
            return $errores;
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errores['org_slug'] = 'El identificador solo admite minúsculas, números y guiones simples (sin guiones al inicio, al final ni consecutivos).';
            return $errores;
        }

        if ($this->Organizador_model->slug_reservado($slug)) {
            $errores['org_slug'] = 'Ese identificador está reservado por el sistema.';
            return $errores;
        }

        if ($this->Organizador_model->existe_slug($slug, $ignorar_id)) {
            $errores['org_slug'] = 'Ese identificador ya está en uso.';
        }

        return $errores;
    }

    /**
     * Longitud segura en caracteres.
     *
     * @param  string $valor
     * @return int
     */
    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
    }

    /**
     * URL pública del tenant. En local se usa /sitio/<slug>/ porque los
     * subdominios no resuelven en el entorno de desarrollo.
     *
     * @param  array $perfil
     * @return string
     */
    private function _url_publica(array $perfil)
    {
        $slug = (string)$perfil['org_slug'];

        if ($this->_path_resolution_enabled()) {
            return base_url('sitio/' . rawurlencode($slug) . '/');
        }

        return $this->_scheme() . '://' . $slug . '.' . $this->Organizador_model->base_domain() . '/';
    }

    /**
     * Habilita la URL local /sitio/<slug>/ solo en desarrollo o por config.
     *
     * @return bool
     */
    private function _path_resolution_enabled()
    {
        $default = (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') ? 'true' : 'false';
        $valor   = strtolower(trim((string)env_value('TENANT_ALLOW_PATH_RESOLUTION', $default)));

        return in_array($valor, ['1', 'true', 'yes', 'on'], TRUE);
    }

    /**
     * Esquema de la petición actual.
     *
     * @return string
     */
    private function _scheme()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return 'https';
        }

        $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwarded !== '' && trim(explode(',', $forwarded)[0]) === 'https') {
            return 'https';
        }

        return 'http';
    }
}
