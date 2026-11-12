<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rifas
 * ─────────────────────────────────────────────────────────────
 * Controlador administrativo del módulo Rifas: consulta y moderación M09.
 *
 * M09 expone la aprobación/rechazo de rifas gratuitas y la aprobación/rechazo
 * de solicitudes de cancelación. Las mutaciones se delegan en Rifa_model.
 */
class Rifas extends MY_Controller
{
    private $estados = [
        'borrador'                => 'Borrador',
        'pendiente_aprobacion'    => 'Pendiente de aprobación',
        'activa'                  => 'Activa',
        'pausada'                 => 'Pausada',
        'cancelacion_solicitada'  => 'Cancelación solicitada',
        'finalizada'              => 'Finalizada',
        'cancelada'               => 'Cancelada',
        'rechazada'               => 'Rechazada',
    ];

    private $puede_modificar = FALSE;

    public function __construct()
    {
        parent::__construct();

        $this->puede_modificar = $this->tiene_permiso('modificar');

        $this->load->model('Rifa_model');
        $this->load->model('Rifa_imagen_model');
        $this->load->model('Rifa_ticket_model');
        $this->load->model('Numeracion_model');

        $this->page_title = 'Rifas';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Rifas', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /rifas
     *
     * @return void
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las rifas.');

        $this->render('pages/rifas/index', [
            'url_base'     => $this->url_base,
            'numeraciones' => $this->Numeracion_model->get_activas(),
        ]);
    }

    /**
     * POST /rifas/tabla (AJAX DataTables server-side)
     *
     * @return void
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las rifas.');

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Rifa_model->tabla_disponible()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'El módulo de rifas aún no está disponible.',
            ]);
        }

        $total_records    = $this->Rifa_model->count_todos();
        $filtered_records = $this->Rifa_model->count_filtrados($search);
        $rows             = $this->Rifa_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    /**
     * GET /rifas/datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar esta rifa.');

        $rifa = $this->Rifa_model->get_por_id((int)$id);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $this->_formatear_datos($rifa)]);
    }

    /**
     * GET /rifas/tickets/:id (AJAX, solo lectura)
     * Resumen de ocupación de números para el administrador.
     *
     * @param  int $id
     * @return void
     */
    public function tickets($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para consultar los números de esta rifa.');

        $rifa = $this->Rifa_model->get_por_id((int)$id);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }

        if (!$this->Rifa_ticket_model->tabla_disponible()) {
            return $this->json(['ok' => FALSE, 'msg' => 'El inventario de números no está disponible.'], 500);
        }

        $this->json([
            'ok'   => TRUE,
            'data' => [
                'resumen'    => $this->Rifa_ticket_model->get_resumen((int)$id),
                'bloqueados' => $this->Rifa_ticket_model->get_bloqueados_agrupados((int)$id),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * POST /rifas/aprobar-gratuita/:id (AJAX)
     * Aprueba una rifa gratuita y le asigna una numeración activa.
     *
     * @param  int $id
     * @return void
     */
    public function aprobar_gratuita($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para aprobar rifas.');

        $id     = (int)$id;
        $num_id = (int)$this->_post_escalar('num_id');

        if ($id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }
        if ($num_id <= 0) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['num_id' => 'Selecciona una numeración activa.'],
            ], 422);
        }

        $res = $this->Rifa_model->aprobar_gratuita($id, $num_id);

        if (empty($res['ok'])) {
            return $this->_respuesta_moderacion($res, 'No se pudo aprobar la rifa gratuita.');
        }

        $this->bitacora(
            'RIFA_APROBAR_GRATUITA',
            "Rifa gratuita aprobada: #{$id} | Numeración: {$res['num_label']} (ID: {$res['num_id']})"
            . " | Total: {$res['total']} números"
        );

        $this->json([
            'ok'        => TRUE,
            'msg'       => 'Rifa gratuita aprobada y publicada correctamente.',
            'estado'    => $res['estado'],
            'num_id'    => (int)$res['num_id'],
            'num_label' => (string)$res['num_label'],
            'total'     => (int)$res['total'],
        ]);
    }

    /**
     * POST /rifas/rechazar-gratuita/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function rechazar_gratuita($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para rechazar rifas.');

        $id     = (int)$id;
        $motivo = trim((string)$this->_post_escalar('motivo'));

        if ($id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }
        if ($this->_longitud_texto($motivo) < 5) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'Indica un motivo de al menos 5 caracteres.'],
            ], 422);
        }
        if ($this->_longitud_texto($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'El motivo no puede superar 255 caracteres.'],
            ], 422);
        }

        $res = $this->Rifa_model->rechazar_gratuita($id, $motivo);

        if (empty($res['ok'])) {
            return $this->_respuesta_moderacion($res, 'No se pudo rechazar la rifa gratuita.');
        }

        $this->bitacora(
            'RIFA_RECHAZAR_GRATUITA',
            "Rifa gratuita rechazada: #{$id} | Motivo: {$motivo}"
        );

        $this->json([
            'ok'     => TRUE,
            'msg'    => 'Rifa gratuita rechazada correctamente.',
            'estado' => $res['estado'],
        ]);
    }

    /**
     * POST /rifas/aprobar-cancelacion/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function aprobar_cancelacion($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para cancelar rifas.');

        $id     = (int)$id;
        $motivo = trim((string)$this->_post_escalar('motivo'));

        if ($id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }
        if ($this->_longitud_texto($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'La nota no puede superar 255 caracteres.'],
            ], 422);
        }

        $res = $this->Rifa_model->cancelar_aprobada($id, $motivo);

        if (empty($res['ok'])) {
            return $this->_respuesta_moderacion($res, 'No se pudo aprobar la cancelación.');
        }

        $this->bitacora(
            'RIFA_CANCELACION_APROBAR',
            "Cancelación de rifa aprobada: #{$id}" . ($motivo !== '' ? " | Nota: {$motivo}" : '')
        );

        $this->json([
            'ok'     => TRUE,
            'msg'    => 'Cancelación aprobada. La rifa fue cerrada y el cupo devuelto.',
            'estado' => $res['estado'],
        ]);
    }

    /**
     * POST /rifas/rechazar-cancelacion/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function rechazar_cancelacion($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para rechazar cancelaciones.');

        $id     = (int)$id;
        $motivo = trim((string)$this->_post_escalar('motivo'));

        if ($id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }
        if ($this->_longitud_texto($motivo) < 5) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'Indica un motivo de al menos 5 caracteres.'],
            ], 422);
        }
        if ($this->_longitud_texto($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'El motivo no puede superar 255 caracteres.'],
            ], 422);
        }

        $res = $this->Rifa_model->rechazar_cancelacion($id, $motivo);

        if (empty($res['ok'])) {
            return $this->_respuesta_moderacion($res, 'No se pudo rechazar la cancelación.');
        }

        $this->bitacora(
            'RIFA_CANCELACION_RECHAZAR',
            "Solicitud de cancelación rechazada: #{$id} | Motivo: {$motivo}"
        );

        $this->json([
            'ok'     => TRUE,
            'msg'    => 'Solicitud de cancelación rechazada. La rifa conserva su estado anterior.',
            'estado' => $res['estado'],
        ]);
    }

    /**
     * Formatea las filas para DataTables administrativo.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $id      = (int)$item['rifa_id'];
            $titulo  = htmlspecialchars((string)$item['rifa_titulo'], ENT_QUOTES, 'UTF-8');
            $slug    = htmlspecialchars((string)$item['rifa_slug'], ENT_QUOTES, 'UTF-8');
            $org     = htmlspecialchars((string)($item['org_nombre_publico'] ?? ''), ENT_QUOTES, 'UTF-8');
            $orgSlug = htmlspecialchars((string)($item['org_slug'] ?? ''), ENT_QUOTES, 'UTF-8');
            $estado  = (string)($item['rifa_estado'] ?? 'borrador');
            $tipo    = (string)($item['rifa_tipo'] ?? 'paga');
            $imagen  = (string)($item['rifa_imagen'] ?? '');

            $organizadorHtml = '<div class="d-flex flex-column">'
                . '<span class="fw-bold text-gray-900 fs-6">' . ($org !== '' ? $org : 'Sin organizador') . '</span>'
                . '<span class="text-muted fs-8">' . $orgSlug . '</span>'
                . '</div>';

            if ($imagen !== '') {
                $url = htmlspecialchars(base_url('media/rifa/' . $id), ENT_QUOTES, 'UTF-8');
                $miniatura = '<img src="' . $url . '" data-rifa-thumb="1" data-src="' . $url . '"'
                    . ' alt="' . $titulo . '" class="me-3"'
                    . ' style="width:48px;height:48px;object-fit:cover;border-radius:.475rem;cursor:zoom-in;flex-shrink:0;">';
            } else {
                $miniatura = '<div class="me-3 d-flex align-items-center justify-content-center bg-light-primary text-primary"'
                    . ' style="width:48px;height:48px;border-radius:.475rem;flex-shrink:0;">'
                    . '<i class="ki-duotone ki-gift fs-3"><span class="path1"></span><span class="path2"></span></i>'
                    . '</div>';
            }

            $rifaHtml = '<div class="d-flex align-items-center">' . $miniatura
                . '<div class="d-flex flex-column">'
                . '<span class="fw-bold text-gray-900 fs-6">' . $titulo . '</span>'
                . '<span class="text-muted fw-semibold fs-7">' . $slug . '</span>'
                . '</div></div>';

            $tipoHtml = $this->_tipo_html($item);

            $num_label = trim((string)($item['num_label'] ?? ''));
            $texto_num = $num_label !== '' ? $num_label : $this->_numeracion_texto($item);

            if ($estado === 'pendiente_aprobacion' && $num_label === '' && (int)($item['rifa_total_numeros'] ?? 0) === 0) {
                $texto_num = 'Pendiente de numeración';
            }

            $numeracionHtml = '<div class="d-flex flex-column">'
                . '<span class="fw-semibold text-gray-900 fs-7">' . htmlspecialchars($texto_num, ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="text-muted fs-8">' . number_format((int)($item['rifa_total_numeros'] ?? 0), 0, '.', ',') . ' números</span>'
                . '</div>';

            $estadoHtml = $this->_estado_badge($estado);

            $fechasHtml = '<div class="d-flex flex-column">'
                . '<span class="fw-semibold text-gray-900 fs-8">Sorteo: ' . $this->_fecha($item['rifa_fecha_sorteo'] ?? '') . '</span>'
                . '<span class="text-muted fs-8">Publicada: ' . $this->_fecha($item['rifa_publicada_at'] ?? '') . '</span>'
                . '</div>';

            $acciones = [
                '<button type="button" class="btn btn-sm btn-icon btn-light-info btn-ver shadow-xs"'
                . ' data-id="' . $id . '" data-titulo="' . $titulo . '"'
                . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                . ' title="Ver detalle" aria-label="Ver detalle">'
                . '<i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                . '</button>',
            ];

            if ($this->puede_modificar && $tipo === 'gratuita' && $estado === 'pendiente_aprobacion') {
                $acciones[] = '<button type="button" class="btn btn-sm btn-icon btn-light-success btn-aprobar-gratuita shadow-xs"'
                    . ' data-id="' . $id . '" data-titulo="' . $titulo . '"'
                    . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                    . ' title="Aprobar rifa gratuita" aria-label="Aprobar rifa gratuita">'
                    . '<i class="ki-duotone ki-check-circle fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '</button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-light-danger btn-rechazar-gratuita shadow-xs"'
                    . ' data-id="' . $id . '" data-titulo="' . $titulo . '"'
                    . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                    . ' title="Rechazar rifa gratuita" aria-label="Rechazar rifa gratuita">'
                    . '<i class="ki-duotone ki-cross-circle fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '</button>';
            }

            if ($this->puede_modificar && $estado === 'cancelacion_solicitada') {
                $acciones[] = '<button type="button" class="btn btn-sm btn-icon btn-light-danger btn-aprobar-cancelacion shadow-xs"'
                    . ' data-id="' . $id . '" data-titulo="' . $titulo . '"'
                    . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                    . ' title="Aprobar cancelación" aria-label="Aprobar cancelación">'
                    . '<i class="ki-duotone ki-cross-circle fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '</button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-light-warning btn-rechazar-cancelacion shadow-xs"'
                    . ' data-id="' . $id . '" data-titulo="' . $titulo . '"'
                    . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                    . ' title="Rechazar cancelación" aria-label="Rechazar cancelación">'
                    . '<i class="ki-duotone ki-arrow-circle-left fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '</button>';
            }

            $accionesHtml = '<div class="d-flex justify-content-end align-items-center gap-2">'
                . implode('', $acciones)
                . '</div>';

            $data[] = [
                'organizador' => $organizadorHtml,
                'rifa'        => $rifaHtml,
                'tipo'        => $tipoHtml,
                'numeracion'  => $numeracionHtml,
                'estado'      => $estadoHtml,
                'fechas'      => $fechasHtml,
                'acciones'    => $accionesHtml,
                'rifa_id'     => $id,
                'rifa_titulo' => (string)$item['rifa_titulo'],
                'rifa_tipo'   => $tipo,
                'rifa_estado' => $estado,
                'rifa_estado_previo' => (string)($item['rifa_estado_previo'] ?? ''),
                'rifa_cancelacion_motivo' => (string)($item['rifa_cancelacion_motivo'] ?? ''),
                'rifa_aprobacion_motivo' => (string)($item['rifa_aprobacion_motivo'] ?? ''),
                'rifa_total_numeros' => (int)($item['rifa_total_numeros'] ?? 0),
                'num_id'      => (int)($item['rifa_num_id'] ?? 0),
                'asig_id'     => isset($item['asig_id']) && $item['asig_id'] !== NULL ? (int)$item['asig_id'] : NULL,
                'num_label'   => (string)($item['num_label'] ?? ''),
            ];
        }

        return $data;
    }

    /**
     * Agrega etiquetas y URLs al detalle administrativo de una rifa.
     *
     * @param  array $rifa
     * @return array
     */
    private function _formatear_datos(array $rifa)
    {
        $id     = (int)$rifa['rifa_id'];
        $estado = (string)($rifa['rifa_estado'] ?? 'borrador');
        $tipo   = (string)($rifa['rifa_tipo'] ?? 'paga');

        $rifa['imagen_url'] = !empty($rifa['rifa_imagen']) ? base_url('media/rifa/' . $id) : '';

        $imagenes = [];

        if ($this->Rifa_imagen_model->tabla_disponible()) {
            foreach ($this->Rifa_imagen_model->get_por_rifa($id) as $fila) {
                $img_id = (int)$fila['img_id'];

                $imagenes[] = [
                    'img_id'    => $img_id,
                    'url'       => base_url('media/rifa-imagen/' . $img_id),
                    'principal' => ((int)$fila['img_principal'] === 1),
                    'orden'     => (int)$fila['img_orden'],
                ];
            }
        }

        $rifa['imagenes']    = $imagenes;
        $rifa['portada_url'] = !empty($rifa['imagen_url'])
            ? $rifa['imagen_url']
            : (!empty($imagenes) ? base_url('media/rifa/' . $id) : '');
        $rifa['estado_label'] = $this->estados[$estado] ?? $estado;
        $rifa['tipo_label'] = ($tipo === 'gratuita') ? 'Gratuita' : 'Paga';
        $rifa['modo_asignacion_label'] = (string)($rifa['rifa_modo_asignacion'] ?? 'manual') === 'automatica'
            ? 'Automática (aleatoria)'
            : 'Manual';
        $rifa['mostrar_reservados_label'] = (int)($rifa['rifa_mostrar_reservados'] ?? 1) === 1
            ? 'Sí'
            : 'Solo disponibles';
        $rifa['numeracion_label'] = $this->_numeracion_texto($rifa);
        $rifa['precio_fmt'] = number_format((float)($rifa['rifa_precio'] ?? 0), 2, '.', ',');
        $rifa['fecha_sorteo_fmt'] = $this->_fecha($rifa['rifa_fecha_sorteo'] ?? '');
        $rifa['publicada_at_fmt'] = $this->_fecha($rifa['rifa_publicada_at'] ?? '');

        $rifa['tickets_resumen'] = $this->Rifa_ticket_model->tabla_disponible()
            ? $this->Rifa_ticket_model->get_resumen($id)
            : NULL;

        $rifa['asig_id'] = isset($rifa['asig_id']) && $rifa['asig_id'] !== NULL ? (int)$rifa['asig_id'] : NULL;
        $rifa['num_label'] = (string)($rifa['num_label'] ?? '');
        $rifa['asig_plan_nombre'] = (string)($rifa['asig_plan_nombre'] ?? '');
        $rifa['lote_label'] = trim($rifa['asig_plan_nombre'] . ' · ' . $rifa['num_label'], ' ·');

        if ($rifa['lote_label'] === '') {
            $rifa['lote_label'] = $tipo === 'gratuita' ? 'Pendiente de numeración' : 'Sin lote';
        }

        return $rifa;
    }

    /**
     * Badge HTML de tipo/precio.
     *
     * @param  array $rifa
     * @return string
     */
    private function _tipo_html(array $rifa)
    {
        $es_gratuita = (string)($rifa['rifa_tipo'] ?? 'paga') === 'gratuita';
        $moneda      = htmlspecialchars((string)($rifa['rifa_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
        $precio      = number_format((float)($rifa['rifa_precio'] ?? 0), 2, '.', ',');
        $badge       = $es_gratuita ? 'badge-light-success' : 'badge-light-primary';
        $label       = $es_gratuita ? 'Gratuita' : 'Paga';

        return '<div class="d-flex flex-column">'
            . '<span class="badge ' . $badge . ' fw-bold px-3 py-2 align-self-start">' . $label . '</span>'
            . '<span class="text-muted fs-8 mt-1">' . $precio . ' ' . $moneda . '</span>'
            . '</div>';
    }

    /**
     * Badge HTML de estado.
     *
     * @param  string $estado
     * @return string
     */
    private function _estado_badge($estado)
    {
        $mapa = [
            'borrador'               => ['badge-light-secondary', 'Borrador'],
            'pendiente_aprobacion'   => ['badge-light-warning', 'Pendiente'],
            'activa'                 => ['badge-light-success', 'Activa'],
            'pausada'                => ['badge-light-warning', 'Pausada'],
            'cancelacion_solicitada' => ['badge-light-info', 'Cancelación'],
            'finalizada'             => ['badge-light-primary', 'Finalizada'],
            'cancelada'              => ['badge-light-danger', 'Cancelada'],
            'rechazada'              => ['badge-light-danger', 'Rechazada'],
        ];

        $item = $mapa[(string)$estado] ?? ['badge-light-secondary', (string)$estado];

        return '<span class="badge ' . $item[0] . ' fw-bold px-3 py-2">'
            . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /**
     * Texto legible de la numeración.
     *
     * @param  array $rifa
     * @return string
     */
    private function _numeracion_texto(array $rifa)
    {
        $desde   = (int)($rifa['rifa_numero_desde'] ?? 0);
        $hasta   = (int)($rifa['rifa_numero_hasta'] ?? 0);
        $padding = (int)($rifa['rifa_padding'] ?? 1);

        if ($padding < 1 || $padding > 20) {
            $padding = max(1, strlen((string)max($desde, $hasta)));
        }

        return str_pad((string)$desde, $padding, '0', STR_PAD_LEFT)
            . '–' . str_pad((string)$hasta, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Fecha legible o texto por defecto.
     *
     * @param  string|null $valor
     * @return string
     */
    private function _fecha($valor)
    {
        if (empty($valor)) {
            return 'Sin fecha';
        }

        $timestamp = strtotime((string)$valor);

        return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$valor;
    }

    /**
     * Traduce el resultado de una transición de moderación a una respuesta
     * JSON con el código HTTP adecuado.
     *
     * @param  array  $res
     * @param  string $mensaje_error
     * @return void
     */
    private function _respuesta_moderacion(array $res, $mensaje_error)
    {
        $motivo = (string)($res['motivo'] ?? 'error');
        $estado = isset($res['estado']) ? (string)$res['estado'] : '';

        $mensajes = [
            'numeracion_invalida' => 'Selecciona una numeración activa válida.',
            'sin_portada'          => 'La rifa no tiene portada; no se puede publicar.',
            'fecha_sorteo'         => 'La fecha del sorteo debe ser futura.',
            'titulo_invalido'      => 'El título de la rifa no es válido.',
            'precio_invalido'      => 'El precio del ticket no es válido.',
            'sin_lote'             => 'La rifa no tiene un lote válido.',
            'ventas_activas'       => 'La rifa tiene números vendidos u órdenes pagadas. Gestiona los reembolsos antes de cancelar.',
        ];

        if (isset($mensajes[$motivo])) {
            $mensaje = $mensajes[$motivo];
            $status  = $motivo === 'ventas_activas' ? 409 : 422;
        } elseif ($motivo === 'no_encontrada') {
            $mensaje = 'La rifa no existe o fue eliminada.';
            $status  = 404;
        } elseif ($motivo === 'estado_invalido') {
            $mensaje = 'La rifa ya cambió de estado. Actualiza la tabla.';
            if ($estado !== '') {
                $mensaje .= ' Estado actual: ' . $estado . '.';
            }
            $status = 409;
        } else {
            $mensaje = $mensaje_error;
            $status  = 500;
        }

        return $this->json([
            'ok'     => FALSE,
            'msg'    => $mensaje,
            'motivo' => $motivo,
            'estado' => $estado !== '' ? $estado : NULL,
        ], $status);
    }

    /**
     * Longitud de texto UTF-8 con fallback.
     *
     * @param  string $texto
     * @return int
     */
    private function _longitud_texto($texto)
    {
        return function_exists('mb_strlen')
            ? mb_strlen((string)$texto, 'UTF-8')
            : strlen((string)$texto);
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
