<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Monedas
 * ─────────────────────────────────────────────────────────────
 * M04: catálogo administrativo de monedas y tasas de cambio.
 *
 * La moneda base es USD. `tasa_valor` representa unidades de la moneda
 * por 1 USD. Los endpoints de mutación exigen permisos RBAC y registran
 * bitácora.
 */
class Monedas extends MY_Controller
{
    private $api_fuentes = [
        'manual',
        'exchangerate.host',
        'frankfurter',
        'open.er-api.com',
        'custom',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Moneda_model');

        $this->page_title = 'Monedas';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Monedas', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /monedas
     *
     * @return void
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las monedas.');

        $this->render('pages/monedas/index', [
            'url_base'        => $this->url_base,
            'puede_incluir'   => $this->tiene_permiso('incluir'),
            'puede_modificar' => $this->tiene_permiso('modificar'),
            'puede_eliminar'  => $this->tiene_permiso('eliminar'),
        ]);
    }

    /**
     * POST /monedas/tabla (AJAX DataTables server-side)
     *
     * @return void
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las monedas.');

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Moneda_model->tabla_disponible()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'El módulo de monedas aún no está disponible.',
            ]);
        }

        $total_records    = $this->Moneda_model->count_todos();
        $filtered_records = $this->Moneda_model->count_filtrados($search);
        $rows             = $this->Moneda_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_datatable($rows, [
                'consultar' => $this->tiene_permiso('consultar'),
                'incluir'   => $this->tiene_permiso('incluir'),
                'modificar' => $this->tiene_permiso('modificar'),
                'eliminar'  => $this->tiene_permiso('eliminar'),
            ]),
        ]);
    }

    /**
     * GET /monedas/datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar esta moneda.');

        $moneda = $this->Moneda_model->get_por_id((int)$id);
        if (empty($moneda)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La moneda no existe o fue eliminada.'], 404);
        }

        $data = $this->_formatear_datos($moneda);
        $data['tasa_actual'] = $this->Moneda_model->get_tasa_actual((int)$id);

        $this->json(['ok' => TRUE, 'data' => $data]);
    }

    /**
     * POST /monedas/crear (AJAX)
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

        $this->autorizar_accion('incluir', 'No tienes permiso para crear monedas.');

        $errores_escalares = $this->_validar_campos_escalares([
            'mon_codigo', 'mon_nombre', 'mon_simbolo', 'mon_decimales',
            'mon_orden', 'mon_status', 'mon_api_fuente', 'mon_api_codigo',
            'mon_tasa_inicial',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        list($data, $tasa_inicial) = $this->_recoger_post();

        $errores = $this->_validar($data, 0, NULL, $tasa_inicial);
        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $tasa_final = ($data['mon_codigo'] === 'USD')
            ? 1.0
            : round((float)$tasa_inicial, 10);

        $id = $this->Moneda_model->crear($data, $tasa_final);

        if (!$id) {
            if ($this->Moneda_model->existe_codigo($data['mon_codigo'])) {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['mon_codigo' => 'Ese código de moneda ya existe.'],
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo crear la moneda.'], 500);
        }

        $this->bitacora(
            'MONEDA_CREAR',
            "Moneda creada: {$data['mon_codigo']} - {$data['mon_nombre']} (ID: {$id})"
            . " | Base: " . ($data['mon_codigo'] === 'USD' ? 'Sí' : 'No')
            . " | Tasa inicial: {$tasa_final}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Moneda creada correctamente.',
            'id'  => (int)$id,
        ]);
    }

    /**
     * POST /monedas/actualizar/:id (AJAX)
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

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar monedas.');

        $id     = (int)$id;
        $actual = $this->Moneda_model->get_por_id($id);

        if (empty($actual)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Moneda no encontrada.'], 404);
        }

        $errores_escalares = $this->_validar_campos_escalares([
            'mon_codigo', 'mon_nombre', 'mon_simbolo', 'mon_decimales',
            'mon_orden', 'mon_api_fuente', 'mon_api_codigo',
        ]);

        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        list($data) = $this->_recoger_post();

        // El estado se controla exclusivamente desde el endpoint de status.
        $data['mon_status'] = (int)$actual['mon_status'];

        $errores = $this->_validar($data, $id, $actual, NULL);
        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if (!$this->Moneda_model->actualizar($id, $data)) {
            if ($this->Moneda_model->existe_codigo($data['mon_codigo'], $id)) {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['mon_codigo' => 'Ese código de moneda ya existe.'],
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo actualizar la moneda.'], 500);
        }

        $this->bitacora(
            'MONEDA_ACTUALIZAR',
            "Moneda actualizada: {$data['mon_codigo']} - {$data['mon_nombre']} (ID: {$id})"
        );

        $this->json(['ok' => TRUE, 'msg' => 'Moneda actualizada correctamente.']);
    }

    /**
     * POST /monedas/eliminar/:id (AJAX, borrado lógico)
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

        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar monedas.');

        $id     = (int)$id;
        $moneda = $this->Moneda_model->get_por_id($id);

        if (empty($moneda)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Moneda no encontrada.'], 404);
        }

        if ((int)$moneda['mon_es_base'] === 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'La moneda base USD no puede eliminarse.'], 422);
        }

        if ($this->Moneda_model->esta_en_uso((string)$moneda['mon_codigo'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes eliminar una moneda usada por rifas, planes o planes de crédito.',
            ], 422);
        }

        if (!$this->Moneda_model->eliminar($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo eliminar la moneda.'], 500);
        }

        $this->bitacora(
            'MONEDA_ELIMINAR',
            "Moneda eliminada lógicamente: {$moneda['mon_codigo']} - {$moneda['mon_nombre']} (ID: {$id})"
        );

        $this->json(['ok' => TRUE, 'msg' => 'Moneda eliminada correctamente.']);
    }

    /**
     * POST /monedas/cambiar-status/:id (AJAX)
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

        $this->autorizar_accion('modificar', 'No tienes permiso para cambiar el estado de las monedas.');

        $id     = (int)$id;
        $moneda = $this->Moneda_model->get_por_id($id);

        if (empty($moneda)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Moneda no encontrada.'], 404);
        }

        $nuevo = ((int)$moneda['mon_status'] === 1) ? 0 : 1;

        if ($nuevo === 0 && (int)$moneda['mon_es_base'] === 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'La moneda base USD no puede desactivarse.'], 422);
        }

        if ($nuevo === 0 && $this->Moneda_model->esta_en_uso((string)$moneda['mon_codigo'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes desactivar una moneda usada por rifas, planes o planes de crédito.',
            ], 422);
        }

        $res = $this->Moneda_model->toggle_status($id);
        if (!$res['ok']) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado de la moneda.'], 500);
        }

        $estado = ((int)$res['nuevo_status'] === 1) ? 'activada' : 'desactivada';
        $this->bitacora(
            'MONEDA_STATUS',
            "Moneda {$estado}: {$moneda['mon_codigo']} - {$moneda['mon_nombre']} (ID: {$id})"
        );

        $this->json([
            'ok'           => TRUE,
            'msg'          => "La moneda fue {$estado} correctamente.",
            'nuevo_status' => (int)$res['nuevo_status'],
        ]);
    }

    /**
     * GET /monedas/tasas/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function tasas($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las tasas de cambio.');

        $moneda = $this->Moneda_model->get_por_id((int)$id);
        if (empty($moneda)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Moneda no encontrada.'], 404);
        }

        $this->json([
            'ok'   => TRUE,
            'data' => [
                'moneda'    => $this->_formatear_datos($moneda),
                'actual'    => $this->Moneda_model->get_tasa_actual((int)$id),
                'historial' => $this->Moneda_model->get_tasas((int)$id, 100),
            ],
        ]);
    }

    /**
     * POST /monedas/guardar-tasa/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function guardar_tasa($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar las tasas de cambio.');

        $id     = (int)$id;
        $moneda = $this->Moneda_model->get_por_id($id);

        if (empty($moneda)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Moneda no encontrada.'], 404);
        }

        $errores_escalares = $this->_validar_campos_escalares(['tasa_valor', 'tasa_fecha']);
        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $valor = trim($this->_post_escalar('tasa_valor'));
        $fecha = trim($this->_post_escalar('tasa_fecha'));

        $errores = [];

        if (!preg_match('/^\d{1,9}(?:\.\d{1,10})?$/', $valor)
            || (float)$valor <= 0
            || round((float)$valor, 10) <= 0) {
            $errores['tasa_valor'] = 'La tasa debe ser un número válido mayor que cero (máx. 9 enteros y 10 decimales).';
        }

        if (!$this->_fecha_valida($fecha)) {
            $errores['tasa_fecha'] = 'La fecha de la tasa no es válida (formato YYYY-MM-DD).';
        }

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $valor_final = ((string)$moneda['mon_codigo'] === 'USD') ? 1.0 : round((float)$valor, 10);
        $anterior    = $this->Moneda_model->get_tasa_actual($id);

        $res = $this->Moneda_model->guardar_tasa($id, $valor_final, $fecha, 'manual');

        if (!$res['ok']) {
            if (($res['motivo'] ?? '') === 'valor_invalido') {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['tasa_valor' => 'La tasa debe ser un número válido mayor que cero.'],
                ], 422);
            }

            $motivos = [
                'no_disponible' => 'El módulo de tasas no está disponible.',
                'no_encontrada' => 'Moneda no encontrada.',
            ];

            return $this->json([
                'ok'  => FALSE,
                'msg' => $motivos[$res['motivo'] ?? ''] ?? 'No se pudo guardar la tasa.',
            ], 500);
        }

        $detalle_tasa = "Tasa {$res['accion']}: {$moneda['mon_codigo']} | Fecha: {$fecha}"
            . " | Valor: {$valor_final} (unidades por 1 USD)";

        if (!empty($anterior)) {
            $detalle_tasa .= " | Anterior: {$anterior['tasa_valor']} ({$anterior['tasa_fecha']})";
        }

        $this->bitacora('MONEDA_TASA_GUARDAR', $detalle_tasa);

        $this->json([
            'ok'        => TRUE,
            'msg'       => 'Tasa guardada correctamente.',
            'accion'    => $res['accion'],
            'actual'    => $this->Moneda_model->get_tasa_actual($id),
            'historial' => $this->Moneda_model->get_tasas($id, 100),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * @return array [data, tasa_inicial]
     */
    private function _recoger_post()
    {
        $codigo = strtoupper(trim($this->_post_escalar('mon_codigo')));
        $fuente = strtolower(trim($this->_post_escalar('mon_api_fuente', 'manual')));
        if ($fuente === '') {
            $fuente = 'manual';
        }

        $status = $this->_post_escalar('mon_status', '1');

        return [
            [
                'mon_codigo'       => $codigo,
                'mon_nombre'       => trim($this->_post_escalar('mon_nombre')),
                'mon_simbolo'      => trim($this->_post_escalar('mon_simbolo')),
                'mon_decimales'    => (int)$this->_post_escalar('mon_decimales', '2'),
                'mon_orden'        => (int)$this->_post_escalar('mon_orden', '1'),
                'mon_status'       => in_array($status, ['1', 'on', 'true'], TRUE) ? 1 : 0,
                'mon_api_fuente'   => $fuente,
                'mon_api_codigo'   => trim($this->_post_escalar('mon_api_codigo')),
            ],
            trim($this->_post_escalar('mon_tasa_inicial', '')),
        ];
    }

    /**
     * @param  array      $data
     * @param  int        $id
     * @param  array|null $actual
     * @param  float|null $tasa_inicial
     * @return array
     */
    private function _validar(array $data, $id = 0, $actual = NULL, $tasa_inicial = NULL)
    {
        $errores = [];

        $codigo = (string)($data['mon_codigo'] ?? '');
        if (!preg_match('/^[A-Z0-9]{3,10}$/', $codigo)) {
            $errores['mon_codigo'] = 'El código debe tener entre 3 y 10 caracteres alfanuméricos en mayúsculas.';
        } elseif ($this->Moneda_model->existe_codigo($codigo, (int)$id)) {
            $errores['mon_codigo'] = 'Ese código de moneda ya existe.';
        }

        $nombre = trim((string)($data['mon_nombre'] ?? ''));
        if ($this->_longitud($nombre) < 2 || $this->_longitud($nombre) > 80) {
            $errores['mon_nombre'] = 'El nombre debe tener entre 2 y 80 caracteres.';
        }

        $simbolo = trim((string)($data['mon_simbolo'] ?? ''));
        if ($simbolo === '' || $this->_longitud($simbolo) > 10) {
            $errores['mon_simbolo'] = 'El símbolo es obligatorio y no puede exceder 10 caracteres.';
        }

        $decimales = (int)($data['mon_decimales'] ?? -1);
        if ($decimales < 0 || $decimales > 6) {
            $errores['mon_decimales'] = 'Los decimales deben estar entre 0 y 6.';
        }

        $orden = (int)($data['mon_orden'] ?? -1);
        if ($orden < 0 || $orden > 9999) {
            $errores['mon_orden'] = 'El orden debe estar entre 0 y 9999.';
        }

        if (!in_array((int)($data['mon_status'] ?? -1), [0, 1], TRUE)) {
            $errores['mon_status'] = 'El estado no es válido.';
        }

        $fuente = (string)($data['mon_api_fuente'] ?? '');
        if (!in_array($fuente, $this->api_fuentes, TRUE)) {
            $errores['mon_api_fuente'] = 'La fuente API no es válida.';
        }

        if ($this->_longitud((string)($data['mon_api_codigo'] ?? '')) > 30) {
            $errores['mon_api_codigo'] = 'El código API no puede exceder 30 caracteres.';
        }

        if ($id > 0 && !empty($actual)) {
            if ((int)$actual['mon_es_base'] === 1 && $codigo !== 'USD') {
                $errores['mon_codigo'] = 'La moneda base debe seguir siendo USD.';
            }

            if ($codigo !== (string)$actual['mon_codigo']
                && $this->Moneda_model->esta_en_uso((string)$actual['mon_codigo'])) {
                $errores['mon_codigo'] = 'No puedes cambiar el código de una moneda que ya está en uso.';
            }
        }

        if ($id === 0) {
            $raw_tasa = trim((string)($tasa_inicial ?? ''));

            if ($codigo === 'USD') {
                if ($raw_tasa !== '' && !preg_match('/^1(?:\.0+)?$/', $raw_tasa)) {
                    $errores['mon_tasa_inicial'] = 'USD debe tener tasa 1.0000.';
                }
            } elseif (!preg_match('/^\d{1,9}(?:\.\d{1,10})?$/', $raw_tasa)) {
                $errores['mon_tasa_inicial'] = 'La tasa inicial debe ser un número válido (máx. 9 enteros y 10 decimales).';
            } elseif ((float)$raw_tasa <= 0 || round((float)$raw_tasa, 10) <= 0) {
                $errores['mon_tasa_inicial'] = 'Indica una tasa inicial mayor que cero (unidades por 1 USD).';
            }
        }

        return $errores;
    }

    private function _formatear_filas_datatable(array $rows, array $permisos = [])
    {
        $data = [];

        if (empty($permisos)) {
            $permisos = ['consultar' => TRUE, 'incluir' => TRUE, 'modificar' => TRUE, 'eliminar' => TRUE];
        }

        $permitir_consultar = !empty($permisos['consultar']);
        $permitir_modificar = !empty($permisos['modificar']);
        $permitir_eliminar  = !empty($permisos['eliminar']);

        foreach ($rows as $item) {
            $id      = (int)$item['mon_id'];
            $codigo  = htmlspecialchars((string)$item['mon_codigo'], ENT_QUOTES, 'UTF-8');
            $nombre  = htmlspecialchars((string)$item['mon_nombre'], ENT_QUOTES, 'UTF-8');
            $simbolo = htmlspecialchars((string)$item['mon_simbolo'], ENT_QUOTES, 'UTF-8');
            $base    = (int)$item['mon_es_base'] === 1;
            $status  = (int)$item['mon_status'] === 1;
            $tasa    = isset($item['tasa_valor']) && $item['tasa_valor'] !== NULL
                ? (float)$item['tasa_valor']
                : NULL;
            $fecha   = (string)($item['tasa_fecha'] ?? '');

            $monedaHtml = '<div class="d-flex align-items-center">'
                . '<div class="symbol symbol-40px symbol-circle bg-light-primary me-3">'
                . '<span class="symbol-label fw-bold text-primary fs-6">' . $codigo . '</span>'
                . '</div>'
                . '<div class="d-flex flex-column">'
                . '<span class="fw-bold text-gray-900 fs-6">' . $nombre . '</span>'
                . '<span class="text-muted fs-8">' . $codigo . ' · ' . $simbolo . '</span>'
                . '</div></div>';

            $baseHtml = $base
                ? '<span class="badge badge-light-primary fw-bold px-3 py-2">Base USD</span>'
                : '<span class="text-muted fs-8">—</span>';

            if ($tasa !== NULL) {
                $tasaHtml = '<div class="d-flex flex-column">'
                    . '<span class="fw-bold text-gray-900 fs-7">1 USD = '
                    . number_format($tasa, 4, '.', ',') . ' ' . $codigo . '</span>'
                    . '<span class="text-muted fs-8">Tasa vigente</span>'
                    . '</div>';
            } else {
                $tasaHtml = '<span class="badge badge-light-warning fw-bold px-3 py-2">Sin tasa</span>';
            }

            $vigenciaHtml = $fecha !== ''
                ? '<span class="fw-semibold text-gray-900 fs-7">' . htmlspecialchars($this->_fecha($fecha), ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="text-muted fs-8">—</span>';

            $estadoHtml = $status
                ? '<span class="badge badge-light-success fw-bold px-3 py-2">Activa</span>'
                : '<span class="badge badge-light-danger fw-bold px-3 py-2">Inactiva</span>';

            $btnEditar = $permitir_modificar
                ? '<button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar shadow-xs"'
                    . ' data-id="' . $id . '" data-codigo="' . $codigo . '" data-es-base="' . ($base ? '1' : '0') . '"'
                    . ' data-bs-toggle="tooltip" title="Editar moneda" aria-label="Editar moneda">'
                    . '<i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i></button>'
                : '';

            $btnTasas = ($permitir_consultar || $permitir_modificar)
                ? '<button type="button" class="btn btn-sm btn-icon btn-light-info btn-tasas shadow-xs"'
                    . ' data-id="' . $id . '" data-codigo="' . $codigo . '" data-es-base="' . ($base ? '1' : '0') . '"'
                    . ' data-bs-toggle="tooltip" title="Tasas de cambio" aria-label="Tasas de cambio">'
                    . '<i class="ki-duotone ki-chart-simple fs-4"><span class="path1"></span><span class="path2"></span></i></button>'
                : '';

            $btnStatus = $permitir_modificar
                ? '<button type="button" class="btn btn-sm btn-icon ' . ($status ? 'btn-light-warning' : 'btn-light-success') . ' btn-status shadow-xs"'
                    . ' data-id="' . $id . '" data-codigo="' . $codigo . '" data-status="' . ($status ? '1' : '0') . '" data-es-base="' . ($base ? '1' : '0') . '"'
                    . ($base ? ' disabled' : '')
                    . ' data-bs-toggle="tooltip" title="' . ($status ? 'Desactivar' : 'Activar') . '" aria-label="' . ($status ? 'Desactivar' : 'Activar') . '">'
                    . '<i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></button>'
                : '';

            $btnEliminar = $permitir_eliminar
                ? '<button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar shadow-xs"'
                    . ' data-id="' . $id . '" data-codigo="' . $codigo . '" data-es-base="' . ($base ? '1' : '0') . '"'
                    . ($base ? ' disabled' : '')
                    . ' data-bs-toggle="tooltip" title="Eliminar moneda" aria-label="Eliminar moneda">'
                    . '<i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></button>'
                : '';

            $accionesHtml = '<div class="d-flex justify-content-end align-items-center gap-2">'
                . $btnEditar . $btnTasas . $btnStatus . $btnEliminar . '</div>';

            if ($btnEditar === '' && $btnTasas === '' && $btnStatus === '' && $btnEliminar === '') {
                $accionesHtml = '<span class="text-muted fs-8">—</span>';
            }

            $data[] = [
                'moneda'   => $monedaHtml,
                'base'     => $baseHtml,
                'tasa'     => $tasaHtml,
                'vigencia' => $vigenciaHtml,
                'estado'   => $estadoHtml,
                'acciones' => $accionesHtml,
            ];
        }

        return $data;
    }

    private function _formatear_datos(array $moneda)
    {
        $moneda['es_base']       = (int)$moneda['mon_es_base'] === 1;
        $moneda['activa']        = (int)$moneda['mon_status'] === 1;
        $moneda['estado_label']  = $moneda['activa'] ? 'Activa' : 'Inactiva';
        $moneda['base_label']    = $moneda['es_base'] ? 'Base USD' : '—';

        return $moneda;
    }

    private function _fecha($valor)
    {
        if (empty($valor)) {
            return '—';
        }

        $ts = strtotime((string)$valor);
        return $ts ? date('d/m/Y', $ts) : (string)$valor;
    }

    private function _fecha_valida($fecha)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$fecha, $m)) {
            return FALSE;
        }

        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return FALSE;
        }

        return (string)$fecha >= '2000-01-01' && (string)$fecha <= '2100-12-31';
    }

    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

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

    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
