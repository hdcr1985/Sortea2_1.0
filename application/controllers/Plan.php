<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan
 * ─────────────────────────────────────────────────────────────
 * Controlador administrativo para la gestión de Planes de Suscripción/Créditos.
 * Extiende MY_Controller y sigue el patrón canónico de Usuarios.
 */
class Plan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Plan_model');

        $this->page_title = 'Planes de Suscripción';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Planes', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /planes o /Plan/index
     * Renderiza la vista principal del módulo.
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los planes.');

        $datos = [
            'url_base' => $this->url_base,
        ];

        $this->render('pages/planes/index', $datos);
    }

    /**
     * POST /Plan/tabla (AJAX DataTables Server-Side)
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los planes.');

        $draw       = (int)$this->input->post('draw');
        $start      = (int)$this->input->post('start');
        $length     = (int)$this->input->post('length');
        $searchData = $this->input->post('search');
        $search     = is_array($searchData) ? trim((string)($searchData['value'] ?? '')) : '';

        $order_col  = (int)($this->input->post('order')[0]['column'] ?? 0);
        $order_dir  = (string)($this->input->post('order')[0]['dir'] ?? 'asc');

        $total_records    = $this->Plan_model->count_todos();
        $filtered_records = $this->Plan_model->count_filtrados($search);
        $rows             = $this->Plan_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    /**
     * GET /Plan/datos/:id (AJAX para cargar modal de edición)
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar este plan.');

        $id = (int)$id;
        $plan = $this->Plan_model->get_por_id($id);

        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El plan no existe o fue eliminado.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $plan]);
    }

    /**
     * POST /Plan/crear (AJAX)
     */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para crear planes.');

        $data = $this->_recoger_post();
        $errores = $this->_validar($data, 0);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $nuevo_id = $this->Plan_model->crear($data);
        if ($nuevo_id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al guardar el plan en la base de datos.'], 500);
        }

        $this->bitacora('PLAN_CREAR', "Plan creado: {$data['pla_nombre']} (ID: {$nuevo_id}) | Precio: {$data['pla_precio']} {$data['pla_moneda']} | Créditos: {$data['pla_numero']}");

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Plan creado correctamente.',
            'id'  => $nuevo_id,
        ]);
    }

    /**
     * POST /Plan/actualizar/:id (AJAX)
     */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar planes.');

        $id = (int)$id;
        $plan = $this->Plan_model->get_por_id($id);
        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Plan no encontrado.'], 404);
        }

        $data = $this->_recoger_post();
        $errores = $this->_validar($data, $id);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if (!$this->Plan_model->actualizar($id, $data)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los cambios en la base de datos.'], 500);
        }

        $this->bitacora('PLAN_ACTUALIZAR', "Plan editado: {$data['pla_nombre']} (ID: {$id}) | Precio: {$data['pla_precio']} {$data['pla_moneda']} | Créditos: {$data['pla_numero']}");

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Plan actualizado correctamente.',
        ]);
    }

    /**
     * POST /Plan/eliminar/:id (AJAX borrado lógico)
     */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar planes.');

        $id = (int)$id;
        $plan = $this->Plan_model->get_por_id($id);
        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Plan no encontrado.'], 404);
        }

        if (!$this->Plan_model->eliminar($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al eliminar el plan.'], 500);
        }

        $this->bitacora('PLAN_ELIMINAR', "Plan eliminado lógicamente: {$plan['pla_nombre']} (ID: {$id})");

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Plan eliminado correctamente.',
        ]);
    }

    /**
     * POST /Plan/cambiar_status/:id (AJAX alternar estado activo/inactivo)
     */
    public function cambiar_status($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el estado del plan.');

        $id = (int)$id;
        $res = $this->Plan_model->toggle_status($id);

        if (!$res['ok']) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado del plan.'], 500);
        }

        $estado_txt = ($res['nuevo_status'] === 1) ? 'activado' : 'desactivado';
        $this->bitacora('PLAN_STATUS', "Plan {$estado_txt}: {$res['plan']['pla_nombre']} (ID: {$id})");

        $this->json([
            'ok'           => TRUE,
            'msg'          => "El plan ha sido {$estado_txt} correctamente.",
            'nuevo_status' => $res['nuevo_status'],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Recoge y sanitiza los campos del formulario POST.
     *
     * @return array
     */
    private function _recoger_post()
    {
        $raw_precio = str_replace(',', '.', (string)$this->input->post('pla_precio', TRUE));
        $moneda = strtoupper(trim((string)$this->input->post('pla_moneda', TRUE)));

        return [
            'pla_nombre'      => trim((string)$this->input->post('pla_nombre', TRUE)),
            'pla_descripcion' => trim((string)$this->input->post('pla_descripcion', TRUE)),
            'pla_numero'      => abs((int)$this->input->post('pla_numero', TRUE)),
            'pla_precio'      => is_numeric($raw_precio) ? round(abs((float)$raw_precio), 2) : 0.00,
            'pla_moneda'      => $moneda !== '' ? substr($moneda, 0, 10) : 'USD',
            'pla_paypal'      => ($this->input->post('pla_paypal') === '1' || $this->input->post('pla_paypal') === 'on') ? 1 : 0,
            'pla_crypto'      => ($this->input->post('pla_crypto') === '1' || $this->input->post('pla_crypto') === 'on') ? 1 : 0,
            'pla_status'      => ($this->input->post('pla_status') === '1' || $this->input->post('pla_status') === 'on') ? 1 : 0,
        ];
    }

    /**
     * Valida los datos del plan.
     *
     * @param  array $data
     * @param  int   $id
     * @return array
     */
    private function _validar(array $data, $id = 0)
    {
        $errores = [];

        if ($data['pla_nombre'] === '') {
            $errores['pla_nombre'] = 'El nombre del plan es obligatorio.';
        } elseif (strlen($data['pla_nombre']) > 100) {
            $errores['pla_nombre'] = 'El nombre no puede exceder los 100 caracteres.';
        } elseif ($this->Plan_model->existe_nombre($data['pla_nombre'], $id)) {
            $errores['pla_nombre'] = 'Ya existe un plan registrado con ese nombre.';
        }

        if (strlen($data['pla_descripcion']) > 255) {
            $errores['pla_descripcion'] = 'La descripción no puede exceder los 255 caracteres.';
        }

        if ($data['pla_numero'] < 0) {
            $errores['pla_numero'] = 'La cantidad de créditos debe ser un número entero mayor o igual a 0.';
        }

        $raw_precio = str_replace(',', '.', (string)$this->input->post('pla_precio', TRUE));
        if ($raw_precio === '' || !is_numeric($raw_precio) || (float)$raw_precio < 0) {
            $errores['pla_precio'] = 'El precio debe ser un número mayor o igual a 0.00.';
        }

        if (empty($data['pla_moneda'])) {
            $errores['pla_moneda'] = 'La moneda es obligatoria (ej: USD, EUR).';
        } elseif (strlen($data['pla_moneda']) > 10) {
            $errores['pla_moneda'] = 'La moneda no puede exceder los 10 caracteres.';
        }

        return $errores;
    }

    /**
     * Formatea las filas para DataTables server-side con contrato asociativo estricto y botones de acción.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $id          = (int)$item['pla_id'];
            $nombre      = htmlspecialchars((string)$item['pla_nombre'], ENT_QUOTES, 'UTF-8');
            $descripcion = htmlspecialchars((string)($item['pla_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
            $creditos    = (int)($item['pla_numero'] ?? 0);
            $precio      = (float)($item['pla_precio'] ?? 0);
            $moneda      = htmlspecialchars((string)($item['pla_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
            $es_activo   = ((int)($item['pla_status'] ?? 0) === 1);

            // Columna 1: Información del plan con icono de diamante/suscripción
            $planHtml = ''
                . '<div class="d-flex align-items-center">'
                . '    <div class="symbol symbol-40px me-3">'
                . '        <span class="symbol-label bg-light-primary text-primary fw-bold">'
                . '            <i class="ki-duotone ki-element-11 fs-3 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>'
                . '        </span>'
                . '    </div>'
                . '    <div class="d-flex flex-column">'
                . '        <span class="fw-bold text-gray-900 fs-6">' . $nombre . '</span>'
                . '        <span class="text-muted fw-semibold fs-7">' . ($descripcion !== '' ? $descripcion : 'Sin descripción') . '</span>'
                . '    </div>'
                . '</div>';

            // Columna 2: Precio monetario
            $precioHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-bold text-gray-900 fs-6">' . number_format($precio, 2, '.', ',') . ' <span class="fs-7 text-primary fw-semibold">' . $moneda . '</span></span>'
                . '    <span class="text-muted fs-8">Pasarelas de pago</span>'
                . '</div>';

            // Columna 3: Créditos / Diamantes
            $creditosHtml = ''
                . '<div class="d-inline-flex align-items-center gap-1 bg-light-info border border-info border-opacity-25 px-3 py-1 rounded-pill">'
                . '    <i class="ki-duotone ki-diamond fs-6 text-info"><span class="path1"></span><span class="path2"></span></i>'
                . '    <span class="fw-bold text-gray-900 fs-7">' . number_format($creditos, 0, ',', '.') . ' créditos</span>'
                . '</div>';

            // Columna 4: Pasarelas habilitadas
            $stripeActivo = ((int)($item['pla_paypal'] ?? 0) === 1);
            $cryptoActivo = ((int)($item['pla_crypto'] ?? 0) === 1);

            $pasarelas = [];
            if ($stripeActivo) {
                $pasarelas[] = '<span class="badge badge-light-primary fw-semibold px-2 py-1"><i class="ki-duotone ki-credit-cart fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>Stripe</span>';
            }
            if ($cryptoActivo) {
                $pasarelas[] = '<span class="badge badge-light-warning fw-semibold px-2 py-1"><i class="ki-duotone ki-bitcoin fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>BTCPay</span>';
            }
            if (empty($pasarelas)) {
                $pasarelas[] = '<span class="badge badge-light-secondary text-muted fw-semibold px-2 py-1">Ninguna</span>';
            }
            $metodosHtml = '<div class="d-flex flex-wrap gap-1">' . implode('', $pasarelas) . '</div>';

            // Columna 5: Estado
            $estadoHtml = $es_activo
                ? '<span class="badge badge-light-success fw-bold px-3 py-2"><span class="bullet bullet-dot bg-success me-1"></span>Activo</span>'
                : '<span class="badge badge-light-danger fw-bold px-3 py-2"><span class="bullet bullet-dot bg-danger me-1"></span>Inactivo</span>';

            // Columna 6: Botones de Acción (estándar canónico: btn-sm btn-icon con tooltips)
            $accionesHtml = ''
                . '<div class="d-flex justify-content-end align-items-center gap-2">'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar shadow-xs" data-id="' . $id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar plan" aria-label="Editar plan">'
                . '        <i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon ' . ($es_activo ? 'btn-light-warning' : 'btn-light-success') . ' btn-status shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-status="' . ($es_activo ? '1' : '0') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . ($es_activo ? 'Desactivar plan' : 'Activar plan') . '" aria-label="' . ($es_activo ? 'Desactivar plan' : 'Activar plan') . '">'
                . '        <i class="ki-duotone ' . ($es_activo ? 'ki-cross-circle' : 'ki-check-circle') . ' fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar plan" aria-label="Eliminar plan">'
                . '        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>'
                . '    </button>'
                . '</div>';

            $data[] = [
                'plan'     => $planHtml,
                'precio'   => $precioHtml,
                'creditos' => $creditosHtml,
                'metodos'  => $metodosHtml,
                'estado'   => $estadoHtml,
                'acciones' => $accionesHtml,
            ];
        }

        return $data;
    }
}
