<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PlanesRifa
 * ─────────────────────────────────────────────────────────────
 * Controlador administrativo del módulo M02: planes de rifa y
 * asignaciones de cupos a organizadores.
 *
 * Sigue el patrón canónico: DataTables server-side, RBAC por acción,
 * bitácora de mutaciones y respuestas JSON con códigos HTTP explícitos.
 */
class PlanesRifa extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Plan_rifa_model');
        $this->load->model('Plan_asignacion_model');
        $this->load->model('Numeracion_model');
        $this->load->model('Moneda_model');
        $this->load->model('Organizador_model');

        $this->page_title = 'Planes de Rifa';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Planes de Rifa', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /planes-rifa
     *
     * @return void
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los planes de rifa.');

        $monedas = $this->Moneda_model->get_activas();
        if (empty($monedas)) {
            foreach (['USD', 'EUR', 'VES', 'COP', 'MXN', 'USDT', 'GBP'] as $codigo) {
                $monedas[] = [
                    'mon_codigo'  => $codigo,
                    'mon_nombre'  => $codigo,
                    'mon_simbolo' => $codigo === 'USD' ? '$' : $codigo,
                ];
            }
        }

        $this->render('pages/planes_rifa/index', [
            'url_base' => $this->url_base,
            'monedas'  => $monedas,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // PLANES
    // ─────────────────────────────────────────────────────────

    /**
     * POST /planes-rifa/tabla (AJAX DataTables server-side)
     *
     * @return void
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los planes de rifa.');

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Plan_rifa_model->tabla_disponible()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'El módulo de planes de rifa aún no está disponible.',
            ]);
        }

        $total_records    = $this->Plan_rifa_model->count_todos();
        $filtered_records = $this->Plan_rifa_model->count_filtrados($search);
        $rows             = $this->Plan_rifa_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_plan_datatable($rows),
        ]);
    }

    /**
     * GET /planes-rifa/datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar este plan de rifa.');

        $plan = $this->Plan_rifa_model->get_por_id((int)$id);

        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El plan no existe o fue eliminado.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $plan]);
    }

    /**
     * POST /planes-rifa/crear (AJAX)
     *
     * @return void
     */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para crear planes de rifa.');

        $errores_escalares = $this->_validar_campos_escalares([
            'plan_nombre', 'plan_descripcion', 'plan_tipo', 'plan_limite',
            'plan_periodo', 'plan_precio', 'plan_moneda', 'plan_status',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $data    = $this->_recoger_post_plan();
        $errores = $this->_validar_plan($data, 0);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if ((string)$data['plan_tipo'] !== 'rifas') {
            unset($data['numeraciones']);
        }

        $nuevo_id = $this->Plan_rifa_model->crear($data);
        if (!$nuevo_id) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al guardar el plan en la base de datos.'], 500);
        }

        $this->bitacora(
            'PLAN_RIFA_CREAR',
            "Plan de rifa creado: {$data['plan_nombre']} (ID: {$nuevo_id})"
            . " | Tipo: {$data['plan_tipo']} | Límite: {$data['plan_limite']}"
            . " | Periodo: {$data['plan_periodo']} | Precio: {$data['plan_precio']} {$data['plan_moneda']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Plan de rifa creado correctamente.',
            'id'  => (int)$nuevo_id,
        ]);
    }

    /**
     * POST /planes-rifa/actualizar/:id (AJAX)
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

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar planes de rifa.');

        $errores_escalares = $this->_validar_campos_escalares([
            'plan_nombre', 'plan_descripcion', 'plan_tipo', 'plan_limite',
            'plan_periodo', 'plan_precio', 'plan_moneda', 'plan_status',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $id   = (int)$id;
        $plan = $this->Plan_rifa_model->get_por_id($id);

        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Plan no encontrado.'], 404);
        }

        $data    = $this->_recoger_post_plan();
        $errores = $this->_validar_plan($data, $id, $plan);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if ((string)$data['plan_tipo'] !== 'rifas') {
            unset($data['numeraciones']);
        }

        if (!$this->Plan_rifa_model->actualizar($id, $data)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los cambios en la base de datos.'], 500);
        }

        $this->bitacora(
            'PLAN_RIFA_ACTUALIZAR',
            "Plan de rifa actualizado: {$data['plan_nombre']} (ID: {$id})"
            . " | Tipo: {$data['plan_tipo']} | Límite: {$data['plan_limite']}"
            . " | Periodo: {$data['plan_periodo']} | Precio: {$data['plan_precio']} {$data['plan_moneda']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Plan de rifa actualizado correctamente.',
        ]);
    }

    /**
     * POST /planes-rifa/eliminar/:id (AJAX, borrado lógico)
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

        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar planes de rifa.');

        $id   = (int)$id;
        $plan = $this->Plan_rifa_model->get_por_id($id);

        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Plan no encontrado.'], 404);
        }

        if ($this->Plan_rifa_model->tiene_asignaciones_activas($id) > 0) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes eliminar un plan con asignaciones activas.',
            ], 422);
        }

        if (!$this->Plan_rifa_model->eliminar($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al eliminar el plan.'], 500);
        }

        $this->bitacora(
            'PLAN_RIFA_ELIMINAR',
            "Plan de rifa eliminado lógicamente: {$plan['plan_nombre']} (ID: {$id})"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Plan de rifa eliminado correctamente.',
        ]);
    }

    /**
     * POST /planes-rifa/cambiar-status/:id (AJAX)
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

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el estado de los planes de rifa.');

        $id  = (int)$id;
        $res = $this->Plan_rifa_model->toggle_status($id);

        if (!$res['ok']) {
            if (empty($res['plan'])) {
                return $this->json(['ok' => FALSE, 'msg' => 'Plan no encontrado.'], 404);
            }
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado del plan.'], 500);
        }

        $estado_txt = ($res['nuevo_status'] === 1) ? 'activado' : 'desactivado';
        $this->bitacora('PLAN_RIFA_STATUS', "Plan de rifa {$estado_txt}: {$res['plan']['plan_nombre']} (ID: {$id})");

        $this->json([
            'ok'           => TRUE,
            'msg'          => "El plan ha sido {$estado_txt} correctamente.",
            'nuevo_status' => (int)$res['nuevo_status'],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // NUMERACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * POST /planes-rifa/numeraciones (AJAX DataTables server-side)
     *
     * @return void
     */
    public function numeraciones()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las numeraciones.');

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Numeracion_model->tabla_disponible()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'El catálogo de numeraciones aún no está disponible.',
            ]);
        }

        $total_records    = $this->Numeracion_model->count_todos();
        $filtered_records = $this->Numeracion_model->count_filtrados($search);
        $rows             = $this->Numeracion_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_numeracion_datatable($rows),
        ]);
    }

    /**
     * GET /planes-rifa/numeracion-datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function numeracion_datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar esta numeración.');

        $numeracion = $this->Numeracion_model->get_por_id((int)$id);

        if (empty($numeracion)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La numeración no existe.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $numeracion]);
    }

    /**
     * POST /planes-rifa/numeracion-crear (AJAX)
     *
     * @return void
     */
    public function numeracion_crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para crear numeraciones.');

        $errores_escalares = $this->_validar_campos_escalares([
            'num_label', 'num_cantidad', 'num_desde', 'num_hasta',
            'num_padding', 'num_status', 'num_orden',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $data    = $this->_recoger_post_numeracion();
        $errores = $this->_validar_numeracion($data, 0);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $nuevo_id = $this->Numeracion_model->crear($data);
        if (!$nuevo_id) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al guardar la numeración en la base de datos.'], 500);
        }

        $this->bitacora(
            'NUMERACION_CREAR',
            "Numeración creada: {$data['num_label']} (ID: {$nuevo_id})"
            . " | Rango: {$data['num_desde']} - {$data['num_hasta']}"
            . " | Cantidad: {$data['num_cantidad']} | Padding: {$data['num_padding']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Numeración creada correctamente.',
            'id'  => (int)$nuevo_id,
        ]);
    }

    /**
     * POST /planes-rifa/numeracion-actualizar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function numeracion_actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar numeraciones.');

        $id = (int)$id;
        $numeracion = $this->Numeracion_model->get_por_id($id);

        if (empty($numeracion)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Numeración no encontrada.'], 404);
        }

        $errores_escalares = $this->_validar_campos_escalares([
            'num_label', 'num_cantidad', 'num_desde', 'num_hasta',
            'num_padding', 'num_status', 'num_orden',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $data    = $this->_recoger_post_numeracion();
        $errores = $this->_validar_numeracion($data, $id);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if (!$this->Numeracion_model->actualizar($id, $data)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los cambios de la numeración.'], 500);
        }

        $this->bitacora(
            'NUMERACION_ACTUALIZAR',
            "Numeración actualizada: {$data['num_label']} (ID: {$id})"
            . " | Rango: {$data['num_desde']} - {$data['num_hasta']}"
            . " | Cantidad: {$data['num_cantidad']} | Padding: {$data['num_padding']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Numeración actualizada correctamente.',
        ]);
    }

    /**
     * POST /planes-rifa/numeracion-eliminar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function numeracion_eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar numeraciones.');

        $id = (int)$id;
        $numeracion = $this->Numeracion_model->get_por_id($id);

        if (empty($numeracion)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Numeración no encontrada.'], 404);
        }

        if ($this->Numeracion_model->esta_en_uso($id)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes eliminar una numeración usada por planes, lotes o rifas. Puedes desactivarla.',
            ], 422);
        }

        if (!$this->Numeracion_model->eliminar($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo desactivar la numeración.'], 500);
        }

        $this->bitacora(
            'NUMERACION_ELIMINAR',
            "Numeración desactivada: {$numeracion['num_label']} (ID: {$id})"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Numeración desactivada correctamente.',
        ]);
    }

    /**
     * POST /planes-rifa/numeracion-status/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function numeracion_status($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el estado de las numeraciones.');

        $res = $this->Numeracion_model->toggle_status((int)$id);

        if (!$res['ok']) {
            if (empty($res['numeracion'])) {
                return $this->json(['ok' => FALSE, 'msg' => 'Numeración no encontrada.'], 404);
            }
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado de la numeración.'], 500);
        }

        $estado_txt = ((int)$res['nuevo_status'] === 1) ? 'activada' : 'desactivada';

        $this->bitacora(
            'NUMERACION_STATUS',
            "Numeración {$estado_txt}: {$res['numeracion']['num_label']} (ID: " . (int)$id . ')'
        );

        $this->json([
            'ok'           => TRUE,
            'msg'          => "La numeración ha sido {$estado_txt} correctamente.",
            'nuevo_status' => (int)$res['nuevo_status'],
        ]);
    }

    /**
     * GET /planes-rifa/numeraciones-select (AJAX Select2)
     *
     * @return void
     */
    public function numeraciones_select()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar numeraciones.');

        $rows    = $this->Numeracion_model->get_activas();
        $results = [];

        foreach ($rows as $row) {
            $cantidad = (int)$row['num_cantidad'];
            $results[] = [
                'id'       => (int)$row['num_id'],
                'text'     => (string)$row['num_label'],
                'cantidad' => $cantidad,
                'desde'    => (int)$row['num_desde'],
                'hasta'    => (int)$row['num_hasta'],
                'padding'  => (int)$row['num_padding'],
            ];
        }

        $this->json(['results' => $results]);
    }

    // ─────────────────────────────────────────────────────────
    // ASIGNACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * POST /planes-rifa/asignaciones (AJAX DataTables server-side)
     *
     * @return void
     */
    public function asignaciones()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las asignaciones de planes.');

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Plan_asignacion_model->tablas_disponibles()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'El módulo de asignaciones aún no está disponible.',
            ]);
        }

        $total_records    = $this->Plan_asignacion_model->count_todos();
        $filtered_records = $this->Plan_asignacion_model->count_filtrados($search);
        $rows             = $this->Plan_asignacion_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_asignacion_datatable($rows),
        ]);
    }

    /**
     * GET /planes-rifa/asignacion-datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function asignacion_datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar esta asignación.');

        $asignacion = $this->Plan_asignacion_model->get_por_id((int)$id);

        if (empty($asignacion)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La asignación no existe.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $asignacion]);
    }

    /**
     * POST /planes-rifa/asignar (AJAX)
     *
     * @return void
     */
    public function asignar()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('incluir', 'No tienes permiso para asignar planes.');

        $errores_escalares = $this->_validar_campos_escalares([
            'org_id', 'plan_id', 'num_id', 'cantidad', 'notas',
            'asig_org_id', 'asig_plan_id', 'asig_num_id', 'asig_cantidad',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $data    = $this->_recoger_post_asignacion();
        $errores = $this->_validar_asignacion($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $res = $this->Plan_asignacion_model->asignar(
            (int)$data['org_id'],
            (int)$data['plan_id'],
            $data['num_id'] !== NULL ? (int)$data['num_id'] : NULL,
            $data['cantidad'] !== NULL ? (int)$data['cantidad'] : NULL,
            'admin',
            (string)$data['notas']
        );

        if (!$res['ok']) {
            $motivo = (string)($res['motivo'] ?? '');
            if (in_array($motivo, ['org_invalido', 'plan_invalido', 'numeracion_invalida', 'datos_invalidos'], TRUE)) {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'Los datos del lote no son válidos. Revisa el organizador, el plan y la numeración.',
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo asignar el lote de plan.'], 500);
        }

        $org  = $this->Organizador_model->get_por_id((int)$data['org_id']);
        $plan = $this->Plan_rifa_model->get_por_id((int)$data['plan_id']);
        $asig = $this->Plan_asignacion_model->get_por_id((int)$res['asig_id']);

        $org_nombre  = !empty($org['org_nombre_publico']) ? $org['org_nombre_publico'] : ('ID ' . $data['org_id']);
        $plan_nombre = !empty($plan['plan_nombre']) ? $plan['plan_nombre'] : ('ID ' . $data['plan_id']);
        $num_nombre  = !empty($asig['num_label']) ? $asig['num_label'] : 'Libre por rifa';
        $cantidad    = !empty($asig['ilimitado']) ? 'ilimitado' : (string)(int)($asig['asig_cantidad'] ?? 0);

        $vigencia = empty($asig['asig_fin'])
            ? 'sin caducidad'
            : 'hasta ' . $asig['asig_fin'];

        $this->bitacora(
            'PLAN_ASIGNAR',
            "Lote asignado: {$plan_nombre} (ID: {$data['plan_id']})"
            . " | Numeración: {$num_nombre} | Cantidad: {$cantidad}"
            . " | Organizador: {$org_nombre} (ID: {$data['org_id']})"
            . " | Origen: admin | Vigencia: {$vigencia}"
        );

        $this->json([
            'ok'         => TRUE,
            'msg'        => 'Lote de plan asignado correctamente.',
            'asignacion' => [
                'asig_id'   => (int)$res['asig_id'],
                'num_id'    => $asig['num_id'] ?? NULL,
                'cantidad'  => $asig['asig_cantidad'] ?? NULL,
                'total'     => $asig['asig_total'] ?? 0,
                'ilimitado' => !empty($asig['ilimitado']),
            ],
        ]);
    }

    /**
     * GET /planes-rifa/plan-opciones/:plan_id (AJAX)
     *
     * Devuelve las numeraciones activas del plan con su precio por rifa.
     *
     * @param  int $plan_id
     * @return void
     */
    public function plan_opciones($plan_id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las opciones del plan.');

        $plan = $this->Plan_rifa_model->get_por_id((int)$plan_id);
        if (empty($plan)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El plan no existe o fue eliminado.'], 404);
        }

        $rows    = $this->Plan_rifa_model->get_opciones_activas((int)$plan_id);
        $results = [];
        $data    = [];

        foreach ($rows as $row) {
            $label = (string)($row['num_label'] ?? '');
            $capacidad = (int)($row['num_cantidad'] ?? 0);

            $results[] = [
                'id'       => (int)$row['num_id'],
                'text'     => $label . ' (' . number_format((float)$row['pnum_precio'], 2, '.', ',') . ' por rifa)',
                'precio'   => (float)$row['pnum_precio'],
                'cantidad' => $capacidad,
                'desde'    => (int)$row['num_desde'],
                'hasta'    => (int)$row['num_hasta'],
                'padding'  => (int)$row['num_padding'],
            ];

            $data[] = [
                'num_id'       => (int)$row['num_id'],
                'num_label'    => $label,
                'pnum_precio'  => (float)$row['pnum_precio'],
                'num_cantidad' => $capacidad,
                'num_desde'    => (int)$row['num_desde'],
                'num_hasta'    => (int)$row['num_hasta'],
                'num_padding'  => (int)$row['num_padding'],
            ];
        }

        $this->json([
            'ok'      => TRUE,
            'results' => $results,
            'data'    => $data,
        ]);
    }

    /**
     * POST /planes-rifa/cancelar-asignacion/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function cancelar_asignacion($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cancelar asignaciones.');

        $errores_escalares = $this->_validar_campos_escalares(['notas']);
        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $id = (int)$id;
        $asignacion = $this->Plan_asignacion_model->get_por_id($id);

        if (empty($asignacion)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Asignación no encontrada.'], 404);
        }

        if ((string)$asignacion['asig_estado'] !== 'activa') {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Solo se pueden cancelar asignaciones activas.',
            ], 422);
        }

        $notas = trim((string)$this->input->post('notas', TRUE));

        if (!$this->Plan_asignacion_model->cancelar($id, $notas)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cancelar la asignación.'], 500);
        }

        $this->bitacora(
            'PLAN_ASIGNACION_CANCELAR',
            "Asignación cancelada: {$asignacion['plan_nombre']} (ID: {$id})"
            . " | Organizador: {$asignacion['org_nombre_publico']} (ID: {$asignacion['asig_org_id']})"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Asignación cancelada correctamente.',
        ]);
    }

    /**
     * GET /planes-rifa/consumos/:asig_id (AJAX)
     *
     * @param  int $asig_id
     * @return void
     */
    public function consumos($asig_id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los consumos.');

        $rows = $this->Plan_asignacion_model->get_consumos_por_asignacion((int)$asig_id);

        $this->json([
            'ok'   => TRUE,
            'data' => $this->_formatear_consumos($rows),
        ]);
    }

    /**
     * GET /planes-rifa/organizadores-select (AJAX Select2)
     *
     * @return void
     */
    public function organizadores_select()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar organizadores.');

        $q = $this->_get_escalar('q');
        if ($q === '') {
            $q = $this->_get_escalar('term');
        }
        if ($q === '') {
            $q = $this->_post_escalar('q');
        }
        if ($q === '') {
            $q = $this->_post_escalar('term');
        }

        $rows    = $this->Organizador_model->get_activos_para_select($q, 50);
        $results = [];

        foreach ($rows as $row) {
            $results[] = [
                'id'   => (int)$row['org_id'],
                'text' => (string)$row['org_nombre_publico'] . ' (' . (string)$row['org_slug'] . ')',
            ];
        }

        $this->json(['results' => $results]);
    }

    /**
     * GET /planes-rifa/planes-select (AJAX Select2)
     *
     * @return void
     */
    public function planes_select()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar planes de rifa.');

        $rows    = $this->Plan_rifa_model->get_activos_para_select();
        $results = [];

        foreach ($rows as $row) {
            $nombre = (string)$row['plan_nombre'];

            if ((string)$row['plan_tipo'] === 'ilimitado') {
                $periodo = $this->_etiqueta_periodo((string)$row['plan_periodo']);
                $texto   = $nombre . ' (Ilimitado' . ($periodo !== '' ? ' ' . strtolower($periodo) : '') . ')';
            } elseif ((int)($row['opciones_count'] ?? 0) > 0) {
                $texto = $nombre . ' (por numeración)';
            } else {
                $limite = (int)$row['plan_limite'];
                $texto  = $nombre . ' (' . $limite . ' ' . ($limite === 1 ? 'rifa' : 'rifas') . ')';
            }

            $results[] = [
                'id'   => (int)$row['plan_id'],
                'text' => $texto,
            ];
        }

        $this->json(['results' => $results]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Recoge y normaliza los campos del formulario de plan.
     *
     * @return array
     */
    private function _recoger_post_plan()
    {
        $raw_precio = str_replace(',', '.', $this->_post_escalar('plan_precio'));

        $precio = 0.00;
        if (is_numeric($raw_precio)) {
            $precio_float = (float)$raw_precio;
            if (is_finite($precio_float)) {
                $precio = round($precio_float, 2);
            }
        }

        $tipo     = strtolower(trim($this->_post_escalar('plan_tipo')));
        $periodo  = strtolower(trim((string)$this->input->post('plan_periodo', TRUE)));
        $limite   = abs((int)$this->_post_escalar('plan_limite'));
        $moneda   = strtoupper(trim($this->_post_escalar('plan_moneda')));
        $status   = $this->input->post('plan_status');

        if ($moneda === '') {
            $moneda = 'USD';
        }

        $status = ($status === '1' || $status === 'on' || $status === 'true' || $status === 1 || $status === TRUE)
            ? 1
            : 0;

        return [
            'plan_nombre'      => trim($this->_post_escalar('plan_nombre')),
            'plan_descripcion' => trim($this->_post_escalar('plan_descripcion')),
            'plan_tipo'        => $tipo,
            'plan_limite'      => $limite,
            'plan_periodo'     => $periodo,
            'plan_precio'      => $precio,
            'plan_moneda'      => $moneda,
            'plan_status'      => $status,
        ] + $this->_opciones_post();
    }

    /**
     * Extrae las opciones de numeración enviadas en el POST del plan.
     *
     * Devuelve NULL cuando el formulario no envía la clave, para conservar
     * las opciones actuales al actualizar. Acepta array o JSON.
     *
     * @return array|null
     */
    private function _opciones_post()
    {
        $raw = $this->input->post('numeraciones');

        if ($raw === NULL) {
            return [];
        }

        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return ['numeraciones' => []];
            }

            $decodificado = json_decode($raw, TRUE);
            if (!is_array($decodificado)) {
                return ['numeraciones' => []];
            }

            $raw = $decodificado;
        }

        if (!is_array($raw)) {
            return ['numeraciones' => []];
        }

        $opciones = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                $opciones[] = [
                    'num_id'      => 0,
                    'pnum_precio' => 0.00,
                    'pnum_status' => 0,
                    'pnum_orden'  => 0,
                ];
                continue;
            }

            $num_id_raw   = $item['num_id'] ?? NULL;
            $precio_raw   = $item['pnum_precio'] ?? NULL;
            $status_raw   = $item['pnum_status'] ?? NULL;
            $orden_raw    = $item['pnum_orden'] ?? NULL;

            // Los valores enviados como array se marcan inválidos para que
            // _validar_plan responda 422 en lugar de castear silenciosamente.
            $num_id = ($num_id_raw === NULL)
                ? 0
                : (is_scalar($num_id_raw) ? (int)$num_id_raw : -1);

            if ($precio_raw === NULL) {
                $precio = '0';
            } elseif (is_scalar($precio_raw)) {
                $precio = str_replace(',', '.', trim((string)$precio_raw));
            } else {
                $precio = '__invalid__';
            }

            $status = ($status_raw === NULL)
                ? 1
                : (is_scalar($status_raw) ? (int)$status_raw : -1);
            if (!in_array($status, [0, 1], TRUE)) {
                $status = -1;
            }

            $orden = ($orden_raw === NULL)
                ? 0
                : (is_scalar($orden_raw) ? (int)$orden_raw : -1);

            $opciones[] = [
                'num_id'      => $num_id,
                'pnum_precio' => ($precio !== '__invalid__' && is_numeric($precio))
                    ? round((float)$precio, 2)
                    : '__invalid__',
                'pnum_status' => $status,
                'pnum_orden'  => $orden,
            ];
        }

        return ['numeraciones' => $opciones];
    }

    /**
     * Valida los datos de un plan de rifa.
     *
     * @param  array $data
     * @param  int   $id
     * @return array
     */
    private function _validar_plan(array $data, $id = 0, $plan_actual = NULL)
    {
        $errores = [];

        $nombre  = trim((string)($data['plan_nombre'] ?? ''));
        $desc    = trim((string)($data['plan_descripcion'] ?? ''));
        $tipo    = (string)($data['plan_tipo'] ?? '');
        $limite  = (int)($data['plan_limite'] ?? 0);
        $periodo = (string)($data['plan_periodo'] ?? '');

        if ($nombre === '') {
            $errores['plan_nombre'] = 'El nombre del plan es obligatorio.';
        } elseif ($this->_longitud($nombre) < 3) {
            $errores['plan_nombre'] = 'El nombre del plan debe tener al menos 3 caracteres.';
        } elseif ($this->_longitud($nombre) > 100) {
            $errores['plan_nombre'] = 'El nombre del plan no puede exceder los 100 caracteres.';
        } elseif ($this->Plan_rifa_model->existe_nombre($nombre, $id)) {
            $errores['plan_nombre'] = 'Ya existe un plan registrado con ese nombre.';
        }

        if ($this->_longitud($desc) > 255) {
            $errores['plan_descripcion'] = 'La descripción no puede exceder los 255 caracteres.';
        }

        // ── Opciones de numeración ───────────────────────────
        $opciones_payload = array_key_exists('numeraciones', $data);
        $opciones_activas = 0;
        $max_cantidad     = 0;

        if ($opciones_payload && $tipo === 'rifas') {
            $opciones = is_array($data['numeraciones']) ? $data['numeraciones'] : [];
            $vistos   = [];

            foreach ($opciones as $indice => $opcion) {
                if (!is_array($opcion)) {
                    $errores['numeraciones'] = 'Las opciones de numeración no son válidas.';
                    continue;
                }

                $num_id = (int)($opcion['num_id'] ?? 0);
                if ($num_id <= 0) {
                    $errores['numeraciones'] = 'Selecciona una numeración válida en todas las opciones.';
                    continue;
                }

                if (isset($vistos[$num_id])) {
                    $errores['numeraciones'] = 'No puedes repetir la misma numeración en un plan.';
                    continue;
                }
                $vistos[$num_id] = TRUE;

                $numeracion = $this->Numeracion_model->get_por_id($num_id);
                if (empty($numeracion) || (int)$numeracion['num_status'] !== 1) {
                    $errores['numeraciones'] = 'Una de las numeraciones no existe o está inactiva.';
                    continue;
                }

                $precio_raw = $opcion['pnum_precio'] ?? NULL;
                if (!is_numeric($precio_raw)) {
                    $errores['numeraciones'] = 'El precio por rifa de cada numeración debe ser un número válido.';
                } else {
                    $precio_opcion = (float)$precio_raw;
                    if (!is_finite($precio_opcion) || $precio_opcion < 0 || $precio_opcion > 99999999.99) {
                        $errores['numeraciones'] = 'El precio por rifa de cada numeración debe estar entre 0.00 y 99,999,999.99.';
                    }
                }

                if (!in_array((int)($opcion['pnum_status'] ?? -1), [0, 1], TRUE)) {
                    $errores['numeraciones'] = 'El estado de una opción de numeración no es válido.';
                }

                if ((int)($opcion['pnum_orden'] ?? 0) < 0) {
                    $errores['numeraciones'] = 'El orden de las opciones no puede ser negativo.';
                }

                if ((int)($opcion['pnum_status'] ?? 0) === 1) {
                    $opciones_activas++;
                    $max_cantidad = max($max_cantidad, (int)$numeracion['num_cantidad']);
                }
            }
        } elseif ($id > 0 && !empty($plan_actual['numeraciones'])) {
            foreach ($plan_actual['numeraciones'] as $opcion) {
                $num_activa = !isset($opcion['num_status']) || (int)$opcion['num_status'] === 1;
                if ((int)($opcion['pnum_status'] ?? 0) === 1 && $num_activa) {
                    $opciones_activas++;
                    $max_cantidad = max($max_cantidad, (int)($opcion['num_cantidad'] ?? 0));
                }
            }
        }

        if (!in_array($tipo, ['rifas', 'ilimitado'], TRUE)) {
            $errores['plan_tipo'] = 'El tipo de plan no es válido.';
        } elseif ($tipo === 'rifas') {
            if ($opciones_activas < 1) {
                $errores['numeraciones'] = 'Un plan por rifas debe tener al menos una numeración activa.';
            }

            if ($limite < 0 || $limite > 1000000) {
                $errores['plan_limite'] = 'El cupo de rifas debe estar entre 0 y 1,000,000.';
            } elseif ($limite > 0 && $max_cantidad > 0 && $limite !== $max_cantidad) {
                $errores['plan_limite'] = 'El cupo debe ser 0 o coincidir con el máximo de las numeraciones activas ('
                    . $max_cantidad . ').';
            }

            if ($periodo !== 'sin_limite') {
                $errores['plan_periodo'] = 'Los planes por rifas deben tener periodo sin límite.';
            }
        } else {
            if ($limite !== 0) {
                $errores['plan_limite'] = 'Los planes ilimitados deben tener cupo 0.';
            }
            if (!in_array($periodo, ['mensual', 'anual'], TRUE)) {
                $errores['plan_periodo'] = 'Los planes ilimitados deben ser mensuales o anuales.';
            }
        }

        $raw_precio = str_replace(',', '.', $this->_post_escalar('plan_precio'));
        if ($raw_precio === '' || !is_numeric($raw_precio)) {
            $errores['plan_precio'] = 'El precio debe ser un número válido.';
        } else {
            $precio = (float)$raw_precio;
            if (!is_finite($precio) || $precio < 0 || $precio > 99999999.99) {
                $errores['plan_precio'] = 'El precio debe estar entre 0.00 y 99,999,999.99.';
            }
        }

        $moneda = (string)($data['plan_moneda'] ?? '');
        $monedas_validas = $this->Moneda_model->get_codigos_activos();

        if (empty($monedas_validas)) {
            $errores['plan_moneda'] = 'El catálogo de monedas no está disponible. Contacta al administrador.';
        } elseif (!preg_match('/^[A-Z0-9]{3,10}$/', $moneda) || !in_array($moneda, $monedas_validas, TRUE)) {
            $errores['plan_moneda'] = 'La moneda no está permitida o no está activa.';
        }

        if (!in_array((int)($data['plan_status'] ?? -1), [0, 1], TRUE)) {
            $errores['plan_status'] = 'El estado del plan no es válido.';
        }

        if ($id > 0 && empty($errores) && $this->Plan_rifa_model->tiene_asignaciones_activas($id) > 0) {
            $actual = !empty($plan_actual) ? $plan_actual : $this->Plan_rifa_model->get_por_id($id);

            if (!empty($actual)) {
                if ((string)$actual['plan_tipo'] !== $tipo && !isset($errores['plan_tipo'])) {
                    $errores['plan_tipo'] = 'No puedes cambiar el tipo de un plan con asignaciones activas.';
                }
                if ((int)$actual['plan_limite'] !== $limite && !isset($errores['plan_limite'])) {
                    $errores['plan_limite'] = 'No puedes cambiar el cupo de un plan con asignaciones activas.';
                }
                if ((string)$actual['plan_periodo'] !== $periodo && !isset($errores['plan_periodo'])) {
                    $errores['plan_periodo'] = 'No puedes cambiar la vigencia de un plan con asignaciones activas.';
                }
            }
        }

        return $errores;
    }

    /**
     * Recoge y normaliza los campos del formulario de asignación.
     *
     * @return array
     */
    private function _recoger_post_asignacion()
    {
        $org_id  = $this->input->post('org_id', TRUE);
        $plan_id = $this->input->post('plan_id', TRUE);
        $num_id  = $this->input->post('num_id', TRUE);
        $cantidad = $this->input->post('cantidad', TRUE);

        if ($org_id === NULL || $org_id === '') {
            $org_id = $this->input->post('asig_org_id', TRUE);
        }
        if ($plan_id === NULL || $plan_id === '') {
            $plan_id = $this->input->post('asig_plan_id', TRUE);
        }
        if ($num_id === NULL || $num_id === '') {
            $num_id = $this->input->post('asig_num_id', TRUE);
        }
        if ($cantidad === NULL || $cantidad === '') {
            $cantidad = $this->input->post('asig_cantidad', TRUE);
        }

        $num_id   = is_scalar($num_id) ? abs((int)$num_id) : 0;
        $cantidad = is_scalar($cantidad) ? abs((int)$cantidad) : 0;

        return [
            'org_id'   => is_scalar($org_id) ? abs((int)$org_id) : 0,
            'plan_id'  => is_scalar($plan_id) ? abs((int)$plan_id) : 0,
            'num_id'   => $num_id > 0 ? $num_id : NULL,
            'cantidad' => $cantidad > 0 ? $cantidad : NULL,
            'notas'    => trim((string)$this->input->post('notas', TRUE)),
        ];
    }

    /**
     * Valida los datos de una asignación/lote.
     *
     * @param  array $data
     * @return array
     */
    private function _validar_asignacion(array $data)
    {
        $errores = [];

        if ((int)$data['org_id'] <= 0) {
            $errores['org_id'] = 'Debes seleccionar un organizador.';
        } else {
            $org = $this->Organizador_model->get_por_id((int)$data['org_id']);
            if (empty($org)) {
                $errores['org_id'] = 'El organizador no existe o fue eliminado.';
            } elseif ((int)$org['org_status'] !== 1) {
                $errores['org_id'] = 'El organizador está inactivo.';
            }
        }

        $plan = NULL;

        if ((int)$data['plan_id'] <= 0) {
            $errores['plan_id'] = 'Debes seleccionar un plan.';
        } else {
            $plan = $this->Plan_rifa_model->get_por_id((int)$data['plan_id']);
            if (empty($plan)) {
                $errores['plan_id'] = 'El plan no existe o fue eliminado.';
            } elseif ((int)$plan['plan_status'] !== 1) {
                $errores['plan_id'] = 'El plan está inactivo.';
            }
        }

        if (!empty($plan)) {
            if ((string)$plan['plan_tipo'] === 'rifas') {
                $num_id = (int)($data['num_id'] ?? 0);

                if ($num_id <= 0) {
                    $errores['num_id'] = 'Debes seleccionar una numeración para el lote.';
                } else {
                    $opciones = $this->Plan_rifa_model->get_opciones_activas((int)$data['plan_id']);
                    $encontrada = FALSE;
                    $precio_opcion = 0.0;

                    foreach ($opciones as $opcion) {
                        if ((int)$opcion['num_id'] === $num_id) {
                            $encontrada    = TRUE;
                            $precio_opcion = (float)$opcion['pnum_precio'];
                            break;
                        }
                    }

                    if (!$encontrada) {
                        $errores['num_id'] = 'La numeración seleccionada no está activa en este plan.';
                    } elseif ($precio_opcion * max(0, (int)($data['cantidad'] ?? 0)) > 9999999999.99) {
                        $errores['cantidad'] = 'El total del lote excede el máximo permitido.';
                    }
                }

                $cantidad = (int)($data['cantidad'] ?? 0);
                if ($cantidad < 1) {
                    $errores['cantidad'] = 'La cantidad de rifas debe ser mayor que cero.';
                } elseif ($cantidad > 1000000) {
                    $errores['cantidad'] = 'La cantidad de rifas no puede exceder 1,000,000.';
                }
            }
        }

        if ($this->_longitud((string)$data['notas']) > 255) {
            $errores['notas'] = 'Las notas no pueden exceder los 255 caracteres.';
        }

        return $errores;
    }

    /**
     * Formatea las filas de planes para DataTables.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_plan_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $id             = (int)$item['plan_id'];
            $nombre         = htmlspecialchars((string)$item['plan_nombre'], ENT_QUOTES, 'UTF-8');
            $descripcion    = htmlspecialchars((string)($item['plan_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
            $tipo           = (string)($item['plan_tipo'] ?? 'rifas');
            $limite         = (int)($item['plan_limite'] ?? 0);
            $periodo        = (string)($item['plan_periodo'] ?? 'sin_limite');
            $precio         = (float)($item['plan_precio'] ?? 0);
            $moneda         = htmlspecialchars((string)($item['plan_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
            $activas        = (int)($item['asignaciones_activas'] ?? 0);
            $opciones_count = (int)($item['opciones_count'] ?? 0);
            $opciones_txt   = trim((string)($item['opciones_resumen'] ?? ''));
            $activo         = ((int)($item['plan_status'] ?? 0) === 1);

            $planHtml = ''
                . '<div class="d-flex align-items-center">'
                . '    <div class="symbol symbol-40px me-3">'
                . '        <span class="symbol-label bg-light-primary text-primary">'
                . '            <i class="ki-duotone ki-crown fs-3 text-primary"><span class="path1"></span><span class="path2"></span></i>'
                . '        </span>'
                . '    </div>'
                . '    <div class="d-flex flex-column">'
                . '        <span class="fw-bold text-gray-900 fs-6">' . $nombre . '</span>'
                . '        <span class="text-muted fw-semibold fs-7">' . ($descripcion !== '' ? $descripcion : 'Sin descripción') . '</span>'
                . '    </div>'
                . '</div>';

            $tipoHtml = ($tipo === 'ilimitado')
                ? '<span class="badge badge-light-info fw-bold px-3 py-2"><i class="ki-duotone ki-arrow-circle-right fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>Ilimitado</span>'
                : '<span class="badge badge-light-primary fw-bold px-3 py-2">' . $limite . ' ' . ($limite === 1 ? 'rifa' : 'rifas') . '</span>';

            if ($tipo === 'ilimitado') {
                $opcionesHtml = '<span class="badge badge-light-info fw-semibold px-3 py-2">Libre por rifa</span>';
            } elseif ($opciones_count > 0) {
                $opcionesHtml = '<div class="d-flex flex-column gap-1">'
                    . '<span class="badge badge-light-primary fw-semibold px-3 py-2">'
                    . $opciones_count . ' ' . ($opciones_count === 1 ? 'numeración' : 'numeraciones')
                    . '</span>'
                    . '<span class="text-muted fs-8">' . htmlspecialchars($opciones_txt, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '</div>';
            } else {
                $opcionesHtml = '<span class="badge badge-light-warning fw-semibold px-3 py-2">Sin numeraciones</span>';
            }

            $vigenciaHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-semibold text-gray-900 fs-7">' . $this->_etiqueta_periodo($periodo) . '</span>'
                . '    <span class="text-muted fs-8">' . ($tipo === 'ilimitado' ? 'Vigencia del cupo' : 'Cupo sin caducidad') . '</span>'
                . '</div>';

            $precioHtml = ''
                . '<span class="fw-bold text-gray-900 fs-6">' . number_format($precio, 2, '.', ',') . '</span>'
                . ' <span class="fs-7 text-primary fw-semibold">' . $moneda . '</span>';

            $asignacionesHtml = ''
                . '<span class="badge badge-light-success fw-semibold px-3 py-2">'
                . '    ' . $activas . ' ' . ($activas === 1 ? 'lote activo' : 'lotes activos')
                . '</span>';

            $estadoHtml = $activo
                ? '<span class="badge badge-light-success fw-bold px-3 py-2"><span class="bullet bullet-dot bg-success me-1"></span>Activo</span>'
                : '<span class="badge badge-light-danger fw-bold px-3 py-2"><span class="bullet bullet-dot bg-danger me-1"></span>Inactivo</span>';

            $accionesHtml = ''
                . '<div class="d-flex justify-content-end align-items-center gap-2">'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar plan" aria-label="Editar plan">'
                . '        <i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon ' . ($activo ? 'btn-light-warning' : 'btn-light-success') . ' btn-status shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-status="' . ($activo ? '1' : '0') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . ($activo ? 'Desactivar plan' : 'Activar plan') . '" aria-label="' . ($activo ? 'Desactivar plan' : 'Activar plan') . '">'
                . '        <i class="ki-duotone ' . ($activo ? 'ki-cross-circle' : 'ki-check-circle') . ' fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar shadow-xs" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar plan" aria-label="Eliminar plan">'
                . '        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>'
                . '    </button>'
                . '</div>';

            $data[] = [
                'plan'         => $planHtml,
                'tipo'         => $tipoHtml,
                'opciones'     => $opcionesHtml,
                'vigencia'     => $vigenciaHtml,
                'precio'       => $precioHtml,
                'asignaciones' => $asignacionesHtml,
                'estado'       => $estadoHtml,
                'acciones'     => $accionesHtml,
            ];
        }

        return $data;
    }

    /**
     * Formatea las filas de asignaciones para DataTables.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_asignacion_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $id           = (int)$item['asig_id'];
            $org          = htmlspecialchars((string)$item['org_nombre_publico'], ENT_QUOTES, 'UTF-8');
            $slug         = htmlspecialchars((string)$item['org_slug'], ENT_QUOTES, 'UTF-8');
            $plan         = htmlspecialchars((string)$item['plan_nombre'], ENT_QUOTES, 'UTF-8');
            $tipo         = (string)($item['plan_tipo'] ?? 'rifas');
            $ilimitado    = ($tipo === 'ilimitado');
            $moneda       = htmlspecialchars((string)($item['plan_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
            $cantidad     = $ilimitado ? NULL : (int)($item['asig_cantidad'] ?? 0);
            $usadas       = (int)($item['asig_rifas_usadas'] ?? 0);
            $restantes    = $ilimitado ? NULL : (int)($item['restantes'] ?? max(0, (int)$cantidad - $usadas));
            $num_label    = htmlspecialchars((string)($item['num_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $precio_unit  = (float)($item['asig_precio_unitario'] ?? 0);
            $total        = (float)($item['asig_total'] ?? 0);
            $inicio       = $item['asig_inicio'] ?? NULL;
            $fin          = $item['asig_fin'] ?? NULL;
            $estado       = (string)($item['asig_estado'] ?? 'activa');

            // Estado efectivo sin escribir en la BD durante un GET de tabla.
            if ($estado === 'activa' && !empty($fin)) {
                $fin_ts = strtotime((string)$fin);
                if ($fin_ts !== FALSE && $fin_ts < time()) {
                    $estado = 'vencida';
                }
            }

            $organizadorHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-bold text-gray-900 fs-6">' . $org . '</span>'
                . '    <span class="text-muted fs-8">' . $slug . '</span>'
                . '</div>';

            $planHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-semibold text-gray-900 fs-7">' . $plan . '</span>'
                . '    <span class="text-muted fs-8">' . ($ilimitado ? 'Ilimitado' : 'Por rifas') . '</span>'
                . '</div>';

            $numeracionHtml = $num_label !== ''
                ? '<span class="badge badge-light-primary fw-semibold px-3 py-2">' . $num_label . '</span>'
                : '<span class="badge badge-light-info fw-semibold px-3 py-2">Libre por rifa</span>';

            $cantidadHtml = $ilimitado
                ? '<span class="badge badge-light-info fw-semibold px-3 py-2">Ilimitado</span>'
                : '<span class="fw-bold text-gray-900 fs-6">' . $cantidad . '</span>';

            if ($ilimitado) {
                $usoHtml = '<span class="badge badge-light-info fw-semibold px-3 py-2">' . $usadas . ' usadas · Ilimitado</span>';
            } else {
                $clase = ($restantes <= 0) ? 'badge-light-danger' : 'badge-light-primary';
                $usoHtml = '<span class="badge ' . $clase . ' fw-semibold px-3 py-2">' . $usadas . ' / ' . $cantidad . '</span>';
            }

            $precioHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-bold text-gray-900 fs-7">' . number_format($precio_unit, 2, '.', ',') . ' <span class="text-primary">' . $moneda . '</span></span>'
                . '    <span class="text-muted fs-8">Total: ' . number_format($total, 2, '.', ',') . ' ' . $moneda . '</span>'
                . '</div>';

            $vigenciaHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-semibold text-gray-900 fs-8">Inicio: ' . htmlspecialchars($this->_fecha($inicio), ENT_QUOTES, 'UTF-8') . '</span>'
                . '    <span class="text-muted fs-8">Fin: ' . (!empty($fin) ? htmlspecialchars($this->_fecha($fin), ENT_QUOTES, 'UTF-8') : 'Sin caducidad') . '</span>'
                . '</div>';

            switch ($estado) {
                case 'vencida':
                    $estadoHtml = '<span class="badge badge-light-warning fw-bold px-3 py-2">Vencida</span>';
                    break;
                case 'reemplazada':
                    $estadoHtml = '<span class="badge badge-light-secondary fw-bold px-3 py-2">Reemplazada</span>';
                    break;
                case 'cancelada':
                    $estadoHtml = '<span class="badge badge-light-danger fw-bold px-3 py-2">Cancelada</span>';
                    break;
                default:
                    $estadoHtml = '<span class="badge badge-light-success fw-bold px-3 py-2"><span class="bullet bullet-dot bg-success me-1"></span>Activa</span>';
                    break;
            }

            $accionesHtml = ''
                . '<div class="d-flex justify-content-end align-items-center gap-2">'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-info btn-consumos shadow-xs" data-id="' . $id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Ver consumos" aria-label="Ver consumos">'
                . '        <i class="ki-duotone ki-list fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                . '    </button>';

            if ($estado === 'activa') {
                $accionesHtml .= ''
                    . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-cancelar shadow-xs" data-id="' . $id . '" data-nombre="' . $org . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancelar asignación" aria-label="Cancelar asignación">'
                    . '        <i class="ki-duotone ki-cross-circle fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '    </button>';
            }

            $accionesHtml .= '</div>';

            $data[] = [
                'organizador' => $organizadorHtml,
                'plan'        => $planHtml,
                'numeracion'  => $numeracionHtml,
                'cantidad'    => $cantidadHtml,
                'uso'         => $usoHtml,
                'precio'      => $precioHtml,
                'vigencia'    => $vigenciaHtml,
                'estado'      => $estadoHtml,
                'acciones'    => $accionesHtml,
            ];
        }

        return $data;
    }

    /**
     * Formatea los consumos de una asignación para el modal.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_consumos(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $estado = (string)($item['cons_estado'] ?? 'consumido');

            $estadoHtml = ($estado === 'devuelto')
                ? '<span class="badge badge-light-warning fw-semibold px-2 py-1">Devuelto</span>'
                : '<span class="badge badge-light-success fw-semibold px-2 py-1">Consumido</span>';

            $data[] = [
                'cons_id'       => (int)($item['cons_id'] ?? 0),
                'cons_rifa_id'  => isset($item['cons_rifa_id']) && $item['cons_rifa_id'] !== NULL
                    ? (int)$item['cons_rifa_id']
                    : NULL,
                'cons_estado'   => $estado,
                'estado_html'   => $estadoHtml,
                'cons_motivo'   => htmlspecialchars((string)($item['cons_motivo'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'fecha'         => htmlspecialchars($this->_fecha($item['created_at'] ?? ($item['cons_created_at'] ?? '')), ENT_QUOTES, 'UTF-8'),
            ];
        }

        return $data;
    }

    /**
     * Recoge y normaliza los campos del formulario de numeración.
     *
     * @return array
     */
    private function _recoger_post_numeracion()
    {
        $status = $this->input->post('num_status');

        $status = ($status === '1' || $status === 'on' || $status === 'true'
            || $status === 1 || $status === TRUE) ? 1 : 0;

        return [
            'num_label'    => trim($this->_post_escalar('num_label')),
            'num_cantidad' => (int)$this->_post_escalar('num_cantidad', '0'),
            'num_desde'    => (int)$this->_post_escalar('num_desde', '0'),
            'num_hasta'    => (int)$this->_post_escalar('num_hasta', '0'),
            'num_padding'  => (int)$this->_post_escalar('num_padding', '0'),
            'num_status'   => $status,
            'num_orden'    => (int)$this->_post_escalar('num_orden', '0'),
        ];
    }

    /**
     * Valida una numeración.
     *
     * @param  array $data
     * @param  int   $id
     * @return array
     */
    private function _validar_numeracion(array $data, $id = 0)
    {
        $errores = [];

        $label    = trim((string)($data['num_label'] ?? ''));
        $cantidad = (int)($data['num_cantidad'] ?? 0);
        $desde    = (int)($data['num_desde'] ?? 0);
        $hasta    = (int)($data['num_hasta'] ?? 0);
        $padding  = (int)($data['num_padding'] ?? 0);
        $status   = (int)($data['num_status'] ?? -1);
        $orden    = (int)($data['num_orden'] ?? 0);

        if ($label === '') {
            $errores['num_label'] = 'La etiqueta de la numeración es obligatoria.';
        } elseif ($this->_longitud($label) < 3) {
            $errores['num_label'] = 'La etiqueta debe tener al menos 3 caracteres.';
        } elseif ($this->_longitud($label) > 60) {
            $errores['num_label'] = 'La etiqueta no puede exceder los 60 caracteres.';
        }

        if ($cantidad < 1) {
            $errores['num_cantidad'] = 'La cantidad debe ser mayor que cero.';
        } elseif ($cantidad > 1000000) {
            $errores['num_cantidad'] = 'La cantidad no puede exceder 1,000,000.';
        }

        if ($desde < 0) {
            $errores['num_desde'] = 'El número inicial no puede ser negativo.';
        }

        if ($hasta <= $desde) {
            $errores['num_hasta'] = 'El número final debe ser mayor que el inicial.';
        } elseif ($hasta > 999999) {
            $errores['num_hasta'] = 'El número final no puede exceder 999,999.';
        } else {
            $total = $hasta - $desde + 1;
            if ($total > 1000000) {
                $errores['num_hasta'] = 'El rango no puede contener más de 1,000,000 de números.';
            }
        }

        if ($padding < 1 || $padding > 6) {
            $errores['num_padding'] = 'El padding debe estar entre 1 y 6.';
        }

        if (!in_array($status, [0, 1], TRUE)) {
            $errores['num_status'] = 'El estado de la numeración no es válido.';
        }

        if ($orden < 0) {
            $errores['num_orden'] = 'El orden no puede ser negativo.';
        }

        $rango_valido = !isset($errores['num_desde'])
            && !isset($errores['num_hasta'])
            && !isset($errores['num_padding']);

        if ($rango_valido) {
            $total_rango = $hasta - $desde + 1;

            if (!isset($errores['num_cantidad']) && $cantidad !== $total_rango) {
                $errores['num_cantidad'] = 'La cantidad debe coincidir con el rango (desde–hasta = '
                    . $total_rango . ').';
            }

            if (!isset($errores['num_cantidad'])
                && $this->Numeracion_model->existe_rango($desde, $hasta, $padding, $id)) {
                $errores['num_hasta'] = 'Ya existe una numeración con ese rango y padding.';
            }
        }

        return $errores;
    }

    /**
     * Formatea las filas de numeraciones para DataTables.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_numeracion_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $id       = (int)$item['num_id'];
            $label    = htmlspecialchars((string)$item['num_label'], ENT_QUOTES, 'UTF-8');
            $cantidad = (int)$item['num_cantidad'];
            $desde    = (int)$item['num_desde'];
            $hasta    = (int)$item['num_hasta'];
            $padding  = (int)$item['num_padding'];
            $activo   = ((int)($item['num_status'] ?? 0) === 1);

            $rango = str_pad((string)$desde, $padding, '0', STR_PAD_LEFT)
                . ' - ' . str_pad((string)$hasta, $padding, '0', STR_PAD_LEFT);

            $numeracionHtml = ''
                . '<div class="d-flex flex-column">'
                . '    <span class="fw-bold text-gray-900 fs-6">' . $label . '</span>'
                . '    <span class="text-muted fs-8">ID ' . $id . '</span>'
                . '</div>';

            $rangoHtml = '<code class="text-gray-800 fw-semibold">' . htmlspecialchars($rango, ENT_QUOTES, 'UTF-8') . '</code>';

            $cantidadHtml = '<span class="badge badge-light-primary fw-semibold px-3 py-2">'
                . number_format($cantidad, 0, '.', ',') . ' números</span>';

            $estadoHtml = $activo
                ? '<span class="badge badge-light-success fw-bold px-3 py-2"><span class="bullet bullet-dot bg-success me-1"></span>Activa</span>'
                : '<span class="badge badge-light-danger fw-bold px-3 py-2"><span class="bullet bullet-dot bg-danger me-1"></span>Inactiva</span>';

            $accionesHtml = ''
                . '<div class="d-flex justify-content-end align-items-center gap-2">'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar-numeracion shadow-xs" data-id="' . $id . '" data-nombre="' . $label . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar numeración" aria-label="Editar numeración">'
                . '        <i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon ' . ($activo ? 'btn-light-warning' : 'btn-light-success') . ' btn-status-numeracion shadow-xs" data-id="' . $id . '" data-nombre="' . $label . '" data-status="' . ($activo ? '1' : '0') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . ($activo ? 'Desactivar numeración' : 'Activar numeración') . '" aria-label="' . ($activo ? 'Desactivar numeración' : 'Activar numeración') . '">'
                . '        <i class="ki-duotone ' . ($activo ? 'ki-cross-circle' : 'ki-check-circle') . ' fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar-numeracion shadow-xs" data-id="' . $id . '" data-nombre="' . $label . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Desactivar numeración" aria-label="Desactivar numeración">'
                . '        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>'
                . '    </button>'
                . '</div>';

            $data[] = [
                'numeracion' => $numeracionHtml,
                'rango'      => $rangoHtml,
                'cantidad'   => $cantidadHtml,
                'estado'     => $estadoHtml,
                'acciones'   => $accionesHtml,
            ];
        }

        return $data;
    }

    /**
     * Etiqueta legible de un periodo de plan.
     *
     * @param  string $periodo
     * @return string
     */
    private function _etiqueta_periodo($periodo)
    {
        switch ((string)$periodo) {
            case 'mensual':
                return 'Mensual';
            case 'anual':
                return 'Anual';
            default:
                return 'Sin límite';
        }
    }

    /**
     * Formatea una fecha de base de datos.
     *
     * @param  string|null $valor
     * @return string
     */
    private function _fecha($valor)
    {
        if (empty($valor)) {
            return '';
        }

        $timestamp = strtotime((string)$valor);

        return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$valor;
    }

    /**
     * Longitud segura en caracteres.
     *
     * @param  string $valor
     * @return int
     */
    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }

    /**
     * Rechaza campos POST enviados como array o valores no escalares.
     *
     * @param  array $campos
     * @return array
     */
    private function _validar_campos_escalares(array $campos)
    {
        $errores = [];

        foreach ($campos as $campo) {
            if (is_array($this->input->post($campo, TRUE))) {
                $errores[$campo] = 'Valor inválido.';
            }
        }

        return $errores;
    }

    /**
     * @param  string $key
     * @param  string $default
     * @return string
     */
    private function _get_escalar($key, $default = '')
    {
        $valor = $this->input->get($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    /**
     * @param  string $key
     * @param  string $default
     * @return string
     */
    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    /**
     * Normaliza search/order de DataTables sin castear arrays.
     *
     * @return array [search, order_col, order_dir]
     */
    private function _datatable_input()
    {
        $searchData = $this->input->post('search');
        $search     = '';

        if (is_array($searchData)
            && isset($searchData['value'])
            && is_scalar($searchData['value'])) {
            $search = trim((string)$searchData['value']);
        }

        $order     = $this->input->post('order');
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
}
