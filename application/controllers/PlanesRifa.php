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

        $this->render('pages/planes_rifa/index', [
            'url_base' => $this->url_base,
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
        $errores = $this->_validar_plan($data, $id);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
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
            'org_id', 'plan_id', 'notas', 'reemplazar', 'asig_org_id', 'asig_plan_id',
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
            'admin',
            (string)$data['notas'],
            (bool)$data['reemplazar']
        );

        if (!$res['ok']) {
            if (($res['motivo'] ?? '') === 'ya_tiene_plan') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'El organizador ya tiene un plan activo. Marca "reemplazar" para sustituirlo.',
                ], 409);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo asignar el plan.'], 500);
        }

        $org  = $this->Organizador_model->get_por_id((int)$data['org_id']);
        $plan = $this->Plan_rifa_model->get_por_id((int)$data['plan_id']);
        $asig = $this->Plan_asignacion_model->get_por_id((int)$res['asig_id']);

        $org_nombre  = !empty($org['org_nombre_publico']) ? $org['org_nombre_publico'] : ('ID ' . $data['org_id']);
        $plan_nombre = !empty($plan['plan_nombre']) ? $plan['plan_nombre'] : ('ID ' . $data['plan_id']);

        $vigencia = empty($asig['asig_fin'])
            ? 'sin caducidad'
            : 'hasta ' . $asig['asig_fin'];

        $this->bitacora(
            'PLAN_ASIGNAR',
            "Plan asignado: {$plan_nombre} (ID: {$data['plan_id']})"
            . " al organizador {$org_nombre} (ID: {$data['org_id']})"
            . " | Origen: admin | Vigencia: {$vigencia}"
        );

        $this->json([
            'ok'         => TRUE,
            'msg'        => 'Plan asignado correctamente.',
            'asignacion' => ['asig_id' => (int)$res['asig_id']],
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
        ];
    }

    /**
     * Valida los datos de un plan de rifa.
     *
     * @param  array $data
     * @param  int   $id
     * @return array
     */
    private function _validar_plan(array $data, $id = 0)
    {
        $errores = [];

        $nombre = trim((string)($data['plan_nombre'] ?? ''));
        $desc   = trim((string)($data['plan_descripcion'] ?? ''));
        $tipo   = (string)($data['plan_tipo'] ?? '');
        $limite = (int)($data['plan_limite'] ?? 0);
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

        if (!in_array($tipo, ['rifas', 'ilimitado'], TRUE)) {
            $errores['plan_tipo'] = 'El tipo de plan no es válido.';
        } elseif ($tipo === 'rifas') {
            if ($limite < 1 || $limite > 10000) {
                $errores['plan_limite'] = 'El cupo de rifas debe estar entre 1 y 10000.';
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
        if (!preg_match('/^[A-Z]{3}$/', $moneda)) {
            $errores['plan_moneda'] = 'La moneda debe ser un código ISO de 3 letras (ej: USD).';
        }

        if (!in_array((int)($data['plan_status'] ?? -1), [0, 1], TRUE)) {
            $errores['plan_status'] = 'El estado del plan no es válido.';
        }

        if ($id > 0 && empty($errores) && $this->Plan_rifa_model->tiene_asignaciones_activas($id) > 0) {
            $actual = $this->Plan_rifa_model->get_por_id($id);

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

        if ($org_id === NULL || $org_id === '') {
            $org_id = $this->input->post('asig_org_id', TRUE);
        }
        if ($plan_id === NULL || $plan_id === '') {
            $plan_id = $this->input->post('asig_plan_id', TRUE);
        }

        $reemplazar = $this->input->post('reemplazar');

        return [
            'org_id'     => abs((int)$org_id),
            'plan_id'    => abs((int)$plan_id),
            'notas'      => trim((string)$this->input->post('notas', TRUE)),
            'reemplazar' => ($reemplazar === '1' || $reemplazar === 'on' || $reemplazar === 'true'
                || $reemplazar === 1 || $reemplazar === TRUE),
        ];
    }

    /**
     * Valida los datos de una asignación.
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
            $id          = (int)$item['plan_id'];
            $nombre      = htmlspecialchars((string)$item['plan_nombre'], ENT_QUOTES, 'UTF-8');
            $descripcion = htmlspecialchars((string)($item['plan_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
            $tipo        = (string)($item['plan_tipo'] ?? 'rifas');
            $limite      = (int)($item['plan_limite'] ?? 0);
            $periodo     = (string)($item['plan_periodo'] ?? 'sin_limite');
            $precio      = (float)($item['plan_precio'] ?? 0);
            $moneda      = htmlspecialchars((string)($item['plan_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
            $activas     = (int)($item['asignaciones_activas'] ?? 0);
            $activo      = ((int)($item['plan_status'] ?? 0) === 1);

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
                . '    ' . $activas . ' ' . ($activas === 1 ? 'activa' : 'activas')
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
            $id       = (int)$item['asig_id'];
            $org      = htmlspecialchars((string)$item['org_nombre_publico'], ENT_QUOTES, 'UTF-8');
            $slug     = htmlspecialchars((string)$item['org_slug'], ENT_QUOTES, 'UTF-8');
            $plan     = htmlspecialchars((string)$item['plan_nombre'], ENT_QUOTES, 'UTF-8');
            $tipo     = (string)($item['plan_tipo'] ?? 'rifas');
            $limite   = (int)($item['plan_limite'] ?? 0);
            $usadas   = (int)($item['asig_rifas_usadas'] ?? 0);
            $inicio   = $item['asig_inicio'] ?? NULL;
            $fin      = $item['asig_fin'] ?? NULL;
            $estado   = (string)($item['asig_estado'] ?? 'activa');

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
                . '    <span class="text-muted fs-8">' . ($tipo === 'ilimitado' ? 'Ilimitado' : 'Por rifas') . '</span>'
                . '</div>';

            if ($tipo === 'ilimitado') {
                $usoHtml = '<span class="badge badge-light-info fw-semibold px-3 py-2">' . $usadas . ' usadas · Ilimitado</span>';
            } else {
                $clase = ($usadas >= $limite) ? 'badge-light-danger' : 'badge-light-primary';
                $usoHtml = '<span class="badge ' . $clase . ' fw-semibold px-3 py-2">' . $usadas . ' / ' . $limite . '</span>';
            }

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
                'uso'         => $usoHtml,
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
