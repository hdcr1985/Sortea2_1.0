<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MetodosPago
 * ─────────────────────────────────────────────────────────────
 * M05: catálogo administrativo de métodos de pago globales y sus campos.
 */
class MetodosPago extends MY_Controller
{
    private $iconos = [
        'ki-bill',
        'ki-dollar',
        'ki-bank',
        'ki-wallet',
        'ki-credit-cart',
        'ki-phone',
        'ki-abstract-26',
        'ki-gift',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Metodo_pago_model');
        $this->load->library('metodo_logo');

        $this->page_title = 'Métodos de Pago';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Métodos de Pago', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los métodos de pago.');

        $this->render('pages/metodos_pago/index', [
            'url_base'        => $this->url_base,
            'puede_incluir'   => $this->tiene_permiso('incluir'),
            'puede_modificar' => $this->tiene_permiso('modificar'),
            'puede_eliminar'  => $this->tiene_permiso('eliminar'),
        ]);
    }

    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los métodos de pago.');

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Metodo_pago_model->tabla_disponible()) {
            return $this->json([
                'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                'error' => 'El módulo de métodos de pago aún no está disponible.',
            ]);
        }

        $total    = $this->Metodo_pago_model->count_todos();
        $filtered = $this->Metodo_pago_model->count_filtrados($search);
        $rows     = $this->Metodo_pago_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $this->_formatear_filas($rows, [
                'consultar' => $this->tiene_permiso('consultar'),
                'incluir'   => $this->tiene_permiso('incluir'),
                'modificar' => $this->tiene_permiso('modificar'),
                'eliminar'  => $this->tiene_permiso('eliminar'),
            ]),
        ]);
    }

    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar este método de pago.');

        $metodo = $this->Metodo_pago_model->get_global_por_id((int)$id);
        if (empty($metodo)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El método de pago no existe.'], 404);
        }

        $metodo['campos'] = $this->Metodo_pago_model->get_campos((int)$id, TRUE);

        $this->json(['ok' => TRUE, 'data' => $metodo]);
    }

    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para crear métodos de pago.');

        $data   = $this->_recoger_post();
        $campos = $this->_recoger_campos();
        $errores = $this->_validar($data, $campos, 0);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $logo = $this->metodo_logo->subir('met_logo');
        if (!$logo['ok']) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['met_logo' => (string)$logo['error']],
            ], 422);
        }

        $data['met_imagen'] = $logo['filename'];

        $actor_id = isset($this->usuario['usu_id']) ? (int)$this->usuario['usu_id'] : NULL;
        $id = $this->Metodo_pago_model->crear_global($data, $campos, $actor_id);

        if (!$id) {
            if (!empty($logo['filename'])) {
                $this->metodo_logo->borrar($logo['filename']);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo crear el método de pago.'], 500);
        }

        $this->bitacora(
            'METODO_PAGO_CREAR',
            "Método de pago global creado: {$data['met_nombre']} (ID: {$id}) | Campos: " . count($campos)
        );

        $this->json(['ok' => TRUE, 'msg' => 'Método de pago creado correctamente.', 'id' => (int)$id]);
    }

    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar métodos de pago.');

        $id = (int)$id;
        $actual = $this->Metodo_pago_model->get_global_por_id($id);
        if (empty($actual)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Método de pago no encontrado.'], 404);
        }

        $data   = $this->_recoger_post();
        $campos = $this->_recoger_campos();

        // El estado se controla exclusivamente desde cambiar-status. Evita
        // desactivar/reactivar por el formulario general.
        $data['met_status'] = (int)$actual['met_status'];

        $errores = $this->_validar($data, $campos, $id);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if ($this->Metodo_pago_model->tiene_rifas_activas($id)
            && $this->Metodo_pago_model->campos_cambiaron($id, $campos)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes modificar los campos de un método vinculado a una rifa activa o pausada. Desactiva o cierra esas rifas primero.',
            ], 422);
        }

        $logo = $this->metodo_logo->subir('met_logo');
        if (!$logo['ok']) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['met_logo' => (string)$logo['error']],
            ], 422);
        }

        $imagen_anterior = (string)($actual['met_imagen'] ?? '');
        $imagen_nueva    = $imagen_anterior;

        if (!empty($logo['filename'])) {
            $imagen_nueva = (string)$logo['filename'];
        } elseif ($this->input->post('met_quitar_imagen') === '1') {
            $imagen_nueva = '';
        }

        $data['met_imagen'] = $imagen_nueva !== '' ? $imagen_nueva : NULL;

        if (!$this->Metodo_pago_model->actualizar_global($id, $data, $campos)) {
            if (!empty($logo['filename'])) {
                $this->metodo_logo->borrar($logo['filename']);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo actualizar el método de pago.'], 500);
        }

        if ($imagen_anterior !== '' && $imagen_anterior !== $imagen_nueva) {
            $this->metodo_logo->borrar($imagen_anterior);
        }

        $this->bitacora(
            'METODO_PAGO_ACTUALIZAR',
            "Método de pago global actualizado: {$data['met_nombre']} (ID: {$id}) | Campos: " . count($campos)
        );

        $this->json(['ok' => TRUE, 'msg' => 'Método de pago actualizado correctamente.']);
    }

    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar métodos de pago.');

        $id = (int)$id;
        $metodo = $this->Metodo_pago_model->get_global_por_id($id);
        if (empty($metodo)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Método de pago no encontrado.'], 404);
        }

        if ($this->Metodo_pago_model->esta_en_uso($id)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes eliminar un método usado por organizadores o rifas activas.',
            ], 422);
        }

        if (!$this->Metodo_pago_model->eliminar_global($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo eliminar el método de pago.'], 500);
        }

        $this->bitacora('METODO_PAGO_ELIMINAR', "Método de pago global eliminado: {$metodo['met_nombre']} (ID: {$id})");

        $this->json(['ok' => TRUE, 'msg' => 'Método de pago eliminado correctamente.']);
    }

    public function cambiar_status($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el estado de métodos de pago.');

        $id = (int)$id;
        $metodo = $this->Metodo_pago_model->get_global_por_id($id);
        if (empty($metodo)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Método de pago no encontrado.'], 404);
        }

        $res = $this->Metodo_pago_model->toggle_global($id);
        if (!$res['ok']) {
            if (!empty($res['metodo']) && (int)$res['nuevo_status'] === 0) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No puedes desactivar un método usado por organizadores o rifas activas.',
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado del método.'], 500);
        }

        $estado = ((int)$res['nuevo_status'] === 1) ? 'activado' : 'desactivado';
        $this->bitacora('METODO_PAGO_STATUS', "Método de pago {$estado}: {$metodo['met_nombre']} (ID: {$id})");

        $this->json([
            'ok'           => TRUE,
            'msg'          => "El método de pago fue {$estado} correctamente.",
            'nuevo_status' => (int)$res['nuevo_status'],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────

    private function _recoger_post()
    {
        return [
            'met_nombre'        => trim($this->_post_escalar('met_nombre')),
            'met_descripcion'   => trim($this->_post_escalar('met_descripcion')),
            'met_instrucciones' => trim($this->_post_html('met_instrucciones')),
            'met_icono'         => trim($this->_post_escalar('met_icono', 'ki-bill')),
            'met_status'        => in_array($this->_post_escalar('met_status', '1'), ['1', 'on', 'true'], TRUE) ? 1 : 0,
            'met_orden'         => (int)$this->_post_escalar('met_orden', '1'),
        ];
    }

    private function _recoger_campos()
    {
        $raw = $this->input->post('campos');

        if (!is_array($raw)) {
            return [];
        }

        $campos = [];
        foreach ($raw as $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $campos[] = [
                'campo_id'          => (int)($campo['campo_id'] ?? 0),
                'campo_clave'       => trim((string)($campo['campo_clave'] ?? '')),
                'campo_etiqueta'    => trim((string)($campo['campo_etiqueta'] ?? '')),
                'campo_tipo'        => trim((string)($campo['campo_tipo'] ?? 'text')),
                'campo_requerido'   => in_array((string)($campo['campo_requerido'] ?? '0'), ['1', 'on', 'true'], TRUE) ? 1 : 0,
                'campo_placeholder' => trim((string)($campo['campo_placeholder'] ?? '')),
                'campo_ayuda'       => trim((string)($campo['campo_ayuda'] ?? '')),
                'campo_opciones'    => trim((string)($campo['campo_opciones'] ?? '')),
                'campo_orden'       => (int)($campo['campo_orden'] ?? 1),
            ];
        }

        return $campos;
    }

    private function _validar(array $data, array $campos, $id = 0)
    {
        $errores = [];

        $nombre = (string)($data['met_nombre'] ?? '');
        if ($this->_longitud($nombre) < 2 || $this->_longitud($nombre) > 120) {
            $errores['met_nombre'] = 'El nombre debe tener entre 2 y 120 caracteres.';
        }

        if ($this->_longitud((string)($data['met_descripcion'] ?? '')) > 255) {
            $errores['met_descripcion'] = 'La descripción no puede exceder 255 caracteres.';
        }

        if ($this->_longitud((string)($data['met_instrucciones'] ?? '')) > 5000) {
            $errores['met_instrucciones'] = 'Las instrucciones no pueden exceder 5000 caracteres.';
        }

        if (!in_array((string)($data['met_icono'] ?? ''), $this->iconos, TRUE)) {
            $errores['met_icono'] = 'Selecciona un icono válido.';
        }

        if (!in_array((int)($data['met_status'] ?? -1), [0, 1], TRUE)) {
            $errores['met_status'] = 'El estado no es válido.';
        }

        if ((int)($data['met_orden'] ?? -1) < 0 || (int)($data['met_orden'] ?? -1) > 9999) {
            $errores['met_orden'] = 'El orden debe estar entre 0 y 9999.';
        }

        if (empty($campos)) {
            $errores['campos'] = 'Agrega al menos un campo al método de pago.';
        } elseif (count($campos) > 50) {
            $errores['campos'] = 'No puedes agregar más de 50 campos.';
        } else {
            $tipos = ['text', 'email', 'tel', 'number', 'url', 'textarea', 'select'];
            foreach ($campos as $i => $campo) {
                if ($this->_longitud((string)$campo['campo_etiqueta']) < 1
                    || $this->_longitud((string)$campo['campo_etiqueta']) > 120) {
                    $errores['campos_' . $i . '_etiqueta'] = 'Cada campo debe tener una etiqueta de 1 a 120 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_clave']) > 60) {
                    $errores['campos_' . $i . '_clave'] = 'La clave del campo no puede exceder 60 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_placeholder']) > 150) {
                    $errores['campos_' . $i . '_placeholder'] = 'El placeholder no puede exceder 150 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_ayuda']) > 255) {
                    $errores['campos_' . $i . '_ayuda'] = 'La ayuda no puede exceder 255 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_opciones']) > 2000) {
                    $errores['campos_' . $i . '_opciones'] = 'Las opciones no pueden exceder 2000 caracteres.';
                }

                if (!in_array((string)$campo['campo_tipo'], $tipos, TRUE)) {
                    $errores['campos_' . $i . '_tipo'] = 'El tipo de campo no es válido.';
                }

                if ((string)$campo['campo_tipo'] === 'select'
                    && trim((string)$campo['campo_opciones']) === '') {
                    $errores['campos_' . $i . '_opciones'] = 'Un campo de selección necesita opciones.';
                }

                if ((int)$campo['campo_orden'] < 0 || (int)$campo['campo_orden'] > 9999) {
                    $errores['campos_' . $i . '_orden'] = 'El orden del campo no es válido.';
                }
            }
        }

        return $errores;
    }

    private function _formatear_filas(array $rows, array $permisos = [])
    {
        $permitir_consultar = !empty($permisos['consultar']);
        $permitir_modificar = !empty($permisos['modificar']);
        $permitir_eliminar  = !empty($permisos['eliminar']);
        $data = [];

        foreach ($rows as $item) {
            $id      = (int)$item['met_id'];
            $nombre  = htmlspecialchars((string)$item['met_nombre'], ENT_QUOTES, 'UTF-8');
            $desc    = htmlspecialchars((string)($item['met_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
            $status  = (int)$item['met_status'] === 1;
            $campos  = (int)($item['campos_count'] ?? 0);
            $orgs    = (int)($item['orgs_count'] ?? 0);
            $rifas   = $this->Metodo_pago_model->tiene_rifas_activas($id);

            $nombreHtml = '<div class="d-flex align-items-center">'
                . $this->_media_html($item)
                . '<div class="d-flex flex-column">'
                . '<span class="fw-bold text-gray-900 fs-6">' . $nombre . '</span>'
                . '<span class="text-muted fs-8">' . ($desc !== '' ? $desc : 'Sin descripción') . '</span>'
                . '</div></div>';

            $camposHtml = '<span class="badge badge-light-primary fw-bold px-3 py-2">' . $campos . ' campo(s)</span>';
            $orgsHtml   = '<span class="badge badge-light-info fw-bold px-3 py-2">' . $orgs . ' organización(es)</span>';
            $estadoHtml = $status
                ? '<span class="badge badge-light-success fw-bold px-3 py-2">Activo</span>'
                : '<span class="badge badge-light-danger fw-bold px-3 py-2">Inactivo</span>';

            $btnEditar = $permitir_modificar
                ? '<button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar shadow-xs" data-id="' . $id . '" data-bs-toggle="tooltip" title="Editar método"><i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i></button>'
                : '';

            $btnStatus = $permitir_modificar
                ? '<button type="button" class="btn btn-sm btn-icon ' . ($status ? 'btn-light-warning' : 'btn-light-success') . ' btn-status shadow-xs" data-id="' . $id . '" data-status="' . ($status ? '1' : '0') . '" data-rifas="' . ($rifas ? '1' : '0') . '" data-bs-toggle="tooltip" title="' . ($status ? 'Desactivar' : 'Activar') . '"><i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></button>'
                : '';

            $btnEliminar = $permitir_eliminar
                ? '<button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar shadow-xs" data-id="' . $id . '" data-uso="' . (($orgs > 0 || $rifas) ? '1' : '0') . '" data-bs-toggle="tooltip" title="Eliminar método"><i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></button>'
                : '';

            $acciones = $btnEditar . $btnStatus . $btnEliminar;
            if ($acciones === '') {
                $acciones = '<span class="text-muted fs-8">—</span>';
            }

            $data[] = [
                'metodo'  => $nombreHtml,
                'campos'  => $camposHtml,
                'orgs'    => $orgsHtml,
                'estado'  => $estadoHtml,
                'acciones' => '<div class="d-flex justify-content-end align-items-center gap-2">' . $acciones . '</div>',
            ];
        }

        return $data;
    }

    private function _media_html(array $item)
    {
        $imagen = trim((string)($item['met_imagen'] ?? ''));
        if ($imagen !== '') {
            $url = base_url('media/metodo/' . (int)$item['met_id']);
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
                . ' alt="" class="me-3 rounded-3" loading="lazy" referrerpolicy="no-referrer"'
                . ' style="width:40px;height:40px;object-fit:cover;flex-shrink:0;" />';
        }

        $icono = (string)($item['met_icono'] ?? 'ki-bill');
        if (!in_array($icono, $this->iconos, TRUE)) {
            $icono = 'ki-bill';
        }

        return '<div class="symbol symbol-40px symbol-circle bg-light-primary me-3 flex-shrink-0">'
            . '<span class="symbol-label">'
            . '<i class="ki-duotone ' . $icono . ' fs-4 text-primary">'
            . '<span class="path1"></span><span class="path2"></span><span class="path3"></span>'
            . '</i></span></div>';
    }

    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    private function _post_html($key, $default = '')
    {
        $valor = $this->input->post($key, FALSE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    private function _datatable_input()
    {
        $searchData = $this->input->post('search');
        $search = '';
        if (is_array($searchData) && isset($searchData['value']) && is_scalar($searchData['value'])) {
            $search = trim((string)$searchData['value']);
        }

        $order = $this->input->post('order');
        $order_col = 0;
        $order_dir = 'asc';
        if (is_array($order) && isset($order[0]) && is_array($order[0])) {
            if (isset($order[0]['column']) && is_scalar($order[0]['column'])) {
                $order_col = (int)$order[0]['column'];
            }
            if (isset($order[0]['dir']) && is_scalar($order[0]['dir'])) {
                $order_dir = (string)$order[0]['dir'];
            }
        }

        return [$search, $order_col, $order_dir];
    }

    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
