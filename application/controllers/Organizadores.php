<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Organizadores
 * ─────────────────────────────────────────────────────────────
 * CRUD administrativo del núcleo multi-tenant (M01).
 *
 * Patrón canónico: controlador + modelo + DataTables server-side + RBAC
 * por acción + bitácora. La URL/menu se resuelve desde cab_menu.
 */
class Organizadores extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Organizador_model');

        $this->page_title = 'Organizadores';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Organizadores', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /organizadores
     *
     * @return void
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los organizadores.');

        $datos = [
            'url_base'    => $this->url_base,
            'base_domain' => $this->Organizador_model->base_domain(),
        ];

        $this->render('pages/organizadores/index', $datos);
    }

    /**
     * POST /organizadores/tabla (AJAX DataTables Server-Side)
     *
     * @return void
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los organizadores.');

        $draw       = (int)$this->input->post('draw');
        $start      = max(0, (int)$this->input->post('start'));
        $length     = (int)$this->input->post('length');
        $length     = ($length <= 0) ? 10 : min(100, $length);
        $searchData = $this->input->post('search');
        $search     = is_array($searchData) ? trim((string)($searchData['value'] ?? '')) : '';

        $order_col = (int)($this->input->post('order')[0]['column'] ?? 0);
        $order_dir = (string)($this->input->post('order')[0]['dir'] ?? 'asc');

        $total_records    = $this->Organizador_model->count_todos();
        $filtered_records = $this->Organizador_model->count_filtrados($search);
        $rows             = $this->Organizador_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    /**
     * GET /organizadores/datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar este organizador.');

        $organizador = $this->Organizador_model->get_por_id((int)$id);

        if (empty($organizador)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El organizador no existe o fue eliminado.'], 404);
        }

        $data = array_intersect_key($organizador, array_flip([
            'org_id',
            'org_nombre_publico',
            'org_email',
            'org_telefono',
            'org_slug',
            'org_status',
            'org_verificado',
        ]));

        $data['host'] = (string)$data['org_slug']
            . '.' . $this->Organizador_model->base_domain();

        $this->json(['ok' => TRUE, 'data' => $data]);
    }

    /**
     * POST /organizadores/actualizar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar organizadores.');

        $id          = (int)$id;
        $organizador = $this->Organizador_model->get_por_id($id);

        if (empty($organizador)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Organizador no encontrado.'], 404);
        }

        $data    = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if (!$this->Organizador_model->actualizar($id, $data)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los cambios.'], 500);
        }

        $this->bitacora(
            'ORGANIZADOR_ACTUALIZAR',
            "Organizador actualizado: {$data['org_nombre_publico']} (ID: {$id}) | Slug: {$organizador['org_slug']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Organizador actualizado correctamente.',
        ]);
    }

    /**
     * POST /organizadores/cambiar_status/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function cambiar_status($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el estado de los organizadores.');

        $id          = (int)$id;
        $organizador = $this->Organizador_model->get_por_id($id);

        if (empty($organizador)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Organizador no encontrado.'], 404);
        }

        $res = $this->Organizador_model->toggle_status($id);

        if (!$res['ok']) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado del organizador.'], 500);
        }

        $estado_txt = ($res['nuevo_status'] === 1) ? 'activado' : 'desactivado';
        $this->bitacora(
            'ORGANIZADOR_STATUS',
            "Organizador {$estado_txt}: {$organizador['org_nombre_publico']} (ID: {$id})"
        );

        $this->json([
            'ok'           => TRUE,
            'msg'          => "El organizador ha sido {$estado_txt} correctamente.",
            'nuevo_status' => $res['nuevo_status'],
        ]);
    }

    /**
     * POST /organizadores/cambiar_slug/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function cambiar_slug($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el identificador de los organizadores.');

        $id          = (int)$id;
        $organizador = $this->Organizador_model->get_por_id($id);

        if (empty($organizador)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Organizador no encontrado.'], 404);
        }

        $slug    = trim((string)$this->input->post('org_slug', TRUE));
        $errores = $this->_validar_slug($slug, $id);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $host = $slug . '.' . $this->Organizador_model->base_domain();

        if ($this->Organizador_model->existe_host($host, $id)) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['org_slug' => 'Ese subdominio ya está en uso.'],
            ], 422);
        }

        if (!$this->Organizador_model->cambiar_slug($id, $slug, $host)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el identificador del organizador.'], 500);
        }

        $this->bitacora(
            'ORGANIZADOR_SLUG',
            "Slug actualizado: {$organizador['org_slug']} -> {$slug} (ID: {$id}) | Host: {$host}"
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Identificador actualizado correctamente.',
            'data' => [
                'slug' => $slug,
                'host' => $host,
            ],
        ]);
    }

    /**
     * POST /organizadores/eliminar/:id (AJAX, borrado lógico)
     *
     * @param  int $id
     * @return void
     */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar organizadores.');

        $id          = (int)$id;
        $organizador = $this->Organizador_model->get_por_id($id);

        if (empty($organizador)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Organizador no encontrado.'], 404);
        }

        if (!$this->Organizador_model->eliminar($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al eliminar el organizador.'], 500);
        }

        $this->bitacora(
            'ORGANIZADOR_ELIMINAR',
            "Organizador eliminado lógicamente: {$organizador['org_nombre_publico']} (ID: {$id}) | Slug: {$organizador['org_slug']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Organizador eliminado correctamente.',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Recoge y normaliza los campos editables por el administrador.
     * org_usuario_id y org_slug nunca se modifican por esta vía.
     *
     * @return array
     */
    private function _recoger_post()
    {
        $verificado = $this->input->post('org_verificado');

        return [
            'org_nombre_publico' => trim((string)$this->input->post('org_nombre_publico', TRUE)),
            'org_email'          => trim((string)$this->input->post('org_email', TRUE)),
            'org_telefono'       => trim((string)$this->input->post('org_telefono', TRUE)),
            'org_verificado'     => ($verificado === '1' || $verificado === 'on' || $verificado === 'true') ? 1 : 0,
        ];
    }

    /**
     * Valida los datos editables por el administrador.
     *
     * @param  array $data
     * @return array
     */
    private function _validar(array $data)
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

        if (!in_array($data['org_verificado'] ?? -1, [0, 1], TRUE)) {
            $errores['org_verificado'] = 'El estado de verificación no es válido.';
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
            $errores['org_slug'] = 'El identificador es obligatorio.';
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
     * Formatea las filas para DataTables con HTML escapado y acciones compactas.
     *
     * Las claves coinciden con las columnas mostradas por la vista:
     * organizador, contacto, dominio, estado, acciones.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];
        $base_domain = $this->Organizador_model->base_domain();

        foreach ($rows as $item) {
            $id         = (int)$item['org_id'];
            $nombre     = htmlspecialchars((string)$item['org_nombre_publico'], ENT_QUOTES, 'UTF-8');
            $slug       = htmlspecialchars((string)$item['org_slug'], ENT_QUOTES, 'UTF-8');
            $host       = htmlspecialchars((string)$item['org_slug'] . '.' . $base_domain, ENT_QUOTES, 'UTF-8');
            $email      = trim((string)($item['org_email'] ?? ''));
            $telefono   = trim((string)($item['org_telefono'] ?? ''));
            $emailHtml  = $email !== '' ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : 'Sin correo';
            $telHtml    = $telefono !== '' ? htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') : 'Sin teléfono';
            $activo     = ((int)($item['org_status'] ?? 0) === 1);
            $verificado = ((int)($item['org_verificado'] ?? 0) === 1);

            // Columna 0: organizador
            $organizadorHtml = ''
                . '<div class="d-flex align-items-center">'
                . '    <div class="symbol symbol-40px me-3">'
                . '        <span class="symbol-label bg-light-primary text-primary">'
                . '            <i class="ki-duotone ki-people fs-3 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>'
                . '        </span>'
                . '    </div>'
                . '    <div class="d-flex flex-column">'
                . '        <span class="fw-bold text-gray-900 fs-6">' . $nombre . '</span>'
                . '        <span class="text-muted fw-semibold fs-7">ID #' . $id . '</span>'
                . '    </div>'
                . '</div>';

            // Columna 1: dominio
            $dominioHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="badge badge-light-info fw-semibold px-2 py-1 align-self-start mb-1">' . $slug . '</span>'
                . '    <span class="text-muted fs-8"><i class="ki-duotone ki-route fs-7 text-primary me-1"><span class="path1"></span><span class="path2"></span></i>' . $host . '</span>'
                . '</div>';

            // Columna 2: contacto
            $contactoHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="text-gray-900 fs-7">' . $emailHtml . '</span>'
                . '    <span class="text-muted fs-8">' . $telHtml . '</span>'
                . '</div>';

            // Columna 3: estado
            $estadoHtml = ''
                . '<div class="d-flex flex-column gap-1 align-items-start">'
                . ($activo
                    ? '    <span class="badge badge-light-success fw-bold px-3 py-2"><span class="bullet bullet-dot bg-success me-1"></span>Activo</span>'
                    : '    <span class="badge badge-light-danger fw-bold px-3 py-2"><span class="bullet bullet-dot bg-danger me-1"></span>Inactivo</span>')
                . ($verificado
                    ? '    <span class="badge badge-light-primary fw-semibold px-2 py-1">Verificado</span>'
                    : '    <span class="badge badge-light-secondary text-muted fw-semibold px-2 py-1">Pendiente</span>')
                . '</div>';

            // Columna 4: acciones
            $accionesHtml = ''
                . '<div class="d-flex justify-content-end align-items-center gap-2">'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar organizador" aria-label="Editar organizador">'
                . '        <i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-info btn-slug shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-slug="' . $slug . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Cambiar identificador" aria-label="Cambiar identificador">'
                . '        <i class="ki-duotone ki-route fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon ' . ($activo ? 'btn-light-warning' : 'btn-light-success') . ' btn-status shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-status="' . ($activo ? '1' : '0') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . ($activo ? 'Desactivar organizador' : 'Activar organizador') . '" aria-label="' . ($activo ? 'Desactivar organizador' : 'Activar organizador') . '">'
                . '        <i class="ki-duotone ' . ($activo ? 'ki-cross-circle' : 'ki-check-circle') . ' fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar organizador" aria-label="Eliminar organizador">'
                . '        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>'
                . '    </button>'
                . '</div>';

            $data[] = [
                'organizador' => $organizadorHtml,
                'contacto'    => $contactoHtml,
                'dominio'     => $dominioHtml,
                'estado'      => $estadoHtml,
                'acciones'    => $accionesHtml,
            ];
        }

        return $data;
    }
}
