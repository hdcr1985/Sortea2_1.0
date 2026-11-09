<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notificaciones extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Notificaciones_model');
        $this->page_title = 'Notificaciones';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Notificaciones', 'url' => ''],
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
        $datos = [
            'tabla_disponible' => $this->Notificaciones_model->tablas_existen(),
            'usuarios'         => $this->Notificaciones_model->get_usuarios_activos(),
        ];

        $this->render('pages/notificaciones/index', $datos);
    }

    public function tabla()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());

        $draw   = (int)$this->input->post('draw');
        $start  = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $search = (string)($this->input->post('search')['value'] ?? '');
        $order  = $this->input->post('order');

        $order_column = 5;
        $order_dir = 'desc';

        if (!empty($order[0])) {
            $order_column = (int)($order[0]['column'] ?? 5);
            $order_dir = (string)($order[0]['dir'] ?? 'desc');
        }

        $rows = $this->Notificaciones_model->get_datatable($start, $length, $search, $order_column, $order_dir);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $this->Notificaciones_model->count_todos(),
            'recordsFiltered' => $this->Notificaciones_model->count_filtrados($search),
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    public function crear()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('incluir', 'No tienes permiso para enviar notificaciones.');

        if (!$this->Notificaciones_model->tablas_existen()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Debes ejecutar primero el script SQL de notificaciones.'], 422);
        }

        $data = $this->_recoger_post();
        $errores = $this->_validar($data);
        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $destinatarios = $this->_resolver_destinatarios($data);
        if (empty($destinatarios)) {
            return $this->json(['ok' => FALSE, 'errores' => ['destinatarios' => 'No hay destinatarios válidos para la notificación.']], 422);
        }

        $payload = [
            'noti_titulo'          => $data['noti_titulo'],
            'noti_mensaje'         => $data['noti_mensaje'],
            'noti_tipo'            => $data['noti_tipo'],
            'noti_enlace'          => $data['noti_enlace'],
            'noti_es_global'       => $data['noti_es_global'],
            'noti_usuario_creador' => (string)($this->usuario['usu_id'] ?? ''),
            'noti_created_at'      => date('Y-m-d H:i:s'),
        ];

        $id = $this->Notificaciones_model->crear($payload, $destinatarios);
        if (!$id) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo enviar la notificación.'], 500);
        }

        $this->bitacora('NOTIFICACION_CREAR', 'Notificación enviada: ' . $data['noti_titulo']);
        return $this->json(['ok' => TRUE, 'msg' => 'Notificación enviada correctamente.']);
    }

    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar notificaciones.');

        $notificacion = $this->Notificaciones_model->get_por_id($id);
        if (empty($notificacion)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Notificación no encontrada.'], 404);
        }

        if (!$this->Notificaciones_model->eliminar($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo eliminar la notificación.'], 500);
        }

        $this->bitacora('NOTIFICACION_ELIMINAR', 'Notificación eliminada: ' . $notificacion['noti_titulo']);
        return $this->json(['ok' => TRUE, 'msg' => 'Notificación eliminada correctamente.']);
    }

    public function feed()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $items = $this->Notificaciones_model->get_feed_usuario($usuario_id, 10);
        $this->json([
            'ok'     => TRUE,
            'items'  => $items,
            'unread' => $this->Notificaciones_model->count_no_leidas($usuario_id),
        ]);
    }

    public function marcar_leida($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $ok = $this->Notificaciones_model->marcar_leida($id, $usuario_id);
        $this->json(['ok' => (bool)$ok, 'unread' => $this->Notificaciones_model->count_no_leidas($usuario_id)]);
    }

    public function marcar_todas()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $ok = $this->Notificaciones_model->marcar_todas_leidas($usuario_id);
        $this->json(['ok' => (bool)$ok, 'unread' => $this->Notificaciones_model->count_no_leidas($usuario_id)]);
    }

    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $titulo = htmlspecialchars((string)$item['noti_titulo'], ENT_QUOTES, 'UTF-8');
            $mensaje = htmlspecialchars((string)$item['noti_mensaje'], ENT_QUOTES, 'UTF-8');
            $enlace = !empty($item['noti_enlace']) ? htmlspecialchars((string)$item['noti_enlace'], ENT_QUOTES, 'UTF-8') : '';

            $notificacionHtml = '<div class="d-flex flex-column">';
            $notificacionHtml .= '    <span class="fw-bold text-gray-900 fs-6 mb-1">' . $titulo . '</span>';
            $notificacionHtml .= '    <span class="text-muted fs-7 text-truncate" style="max-width: 320px;" title="' . $mensaje . '">' . $mensaje . '</span>';
            if ($enlace !== '') {
                $notificacionHtml .= '    <a href="' . $enlace . '" target="_blank" class="text-primary fs-8 text-hover-underline mt-1 d-inline-flex align-items-center">';
                $notificacionHtml .= '        <i class="ki-duotone ki-exit-right-corner fs-8 me-1"><span class="path1"></span><span class="path2"></span></i>' . $enlace;
                $notificacionHtml .= '    </a>';
            }
            $notificacionHtml .= '</div>';

            if ((string)$item['noti_es_global'] === '1') {
                $alcanceHtml = '<span class="badge badge-light-primary fw-bold"><span class="bullet bullet-dot bg-primary me-1"></span>Global</span>';
            } else {
                $alcanceHtml = '<span class="badge badge-light-info fw-bold"><span class="bullet bullet-dot bg-info me-1"></span>Específica</span>';
            }

            $tipo = $item['noti_tipo'] ?? 'info';
            $badgeColor = [
                'danger'  => 'danger',
                'warning' => 'warning',
                'success' => 'success',
                'info'    => 'primary',
            ][$tipo] ?? 'primary';
            $tipoNombre = [
                'danger'  => 'Crítica',
                'warning' => 'Advertencia',
                'success' => 'Éxito',
                'info'    => 'Información',
            ][$tipo] ?? ucfirst($tipo);

            $tipoHtml = '<span class="badge badge-light-' . $badgeColor . ' fw-bold"><span class="bullet bullet-dot bg-' . $badgeColor . ' me-1"></span>' . $tipoNombre . '</span>';

            $destinatariosHtml = '<div class="d-flex justify-content-center">';
            $destinatariosHtml .= '    <span class="badge badge-light fw-bold fs-7 text-gray-700 px-3 py-2 border border-gray-200">' . (int)$item['total_destinatarios'] . ' usu.</span>';
            $destinatariosHtml .= '</div>';

            $creadorNombre = htmlspecialchars((string)($item['creador_nombre'] ?: 'Sistema'), ENT_QUOTES, 'UTF-8');
            $creadorUser   = htmlspecialchars((string)($item['creador_usuario'] ?? $item['noti_usuario_creador']), ENT_QUOTES, 'UTF-8');
            $creadorHtml   = '<div class="d-flex flex-column">';
            $creadorHtml   .= '    <span class="fw-semibold text-gray-800">' . $creadorNombre . '</span>';
            $creadorHtml   .= '    <span class="text-muted fs-8">@' . $creadorUser . '</span>';
            $creadorHtml   .= '</div>';

            $fecha = !empty($item['noti_created_at']) ? date('d/m/Y H:i', strtotime($item['noti_created_at'])) : '-';
            $fechaHtml = '<span class="text-gray-600 fw-semibold fs-7">' . $fecha . '</span>';

            $accionesHtml = '<div class="d-flex justify-content-end align-items-center gap-2">';
            $accionesHtml .= '    <button type="button" class="btn btn-sm btn-icon btn-light-danger shadow-xs btn-eliminar" data-id="' . (int)$item['noti_id'] . '" data-titulo="' . $titulo . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar" aria-label="Eliminar">';
            $accionesHtml .= '        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>';
            $accionesHtml .= '    </button>';
            $accionesHtml .= '</div>';

            $data[] = [
                'notificacion'  => $notificacionHtml,
                'alcance'       => $alcanceHtml,
                'tipo'          => $tipoHtml,
                'destinatarios' => $destinatariosHtml,
                'creador'       => $creadorHtml,
                'fecha'         => $fechaHtml,
                'acciones'      => $accionesHtml,
            ];
        }

        return $data;
    }

    private function _recoger_post()
    {
        $destinatarios = $this->input->post('destinatarios');
        if (!is_array($destinatarios)) {
            $destinatarios = [];
        }

        return [
            'noti_titulo'    => trim((string)$this->input->post('noti_titulo', TRUE)),
            'noti_mensaje'   => trim((string)$this->input->post('noti_mensaje', TRUE)),
            'noti_tipo'      => trim((string)$this->input->post('noti_tipo', TRUE)),
            'noti_enlace'    => trim((string)$this->input->post('noti_enlace', TRUE)),
            'noti_es_global' => $this->input->post('noti_es_global', TRUE) === '1' ? '1' : '0',
            'destinatarios'  => array_values(array_unique(array_map('strval', $destinatarios))),
        ];
    }

    private function _validar(array $data)
    {
        $errores = [];

        if ($data['noti_titulo'] === '') {
            $errores['noti_titulo'] = 'El título es requerido.';
        } elseif (strlen($data['noti_titulo']) > 150) {
            $errores['noti_titulo'] = 'El título no puede superar 150 caracteres.';
        }

        if ($data['noti_mensaje'] === '') {
            $errores['noti_mensaje'] = 'El mensaje es requerido.';
        } elseif (strlen($data['noti_mensaje']) > 1000) {
            $errores['noti_mensaje'] = 'El mensaje no puede superar 1000 caracteres.';
        }

        if (!in_array($data['noti_tipo'], ['info', 'success', 'warning', 'danger'], TRUE)) {
            $errores['noti_tipo'] = 'Selecciona un tipo válido.';
        }

        if ($data['noti_enlace'] !== '' && filter_var($data['noti_enlace'], FILTER_VALIDATE_URL) === FALSE && strpos($data['noti_enlace'], '/') !== 0) {
            $errores['noti_enlace'] = 'El enlace debe ser una URL completa o una ruta relativa que empiece con /. ';
        }

        if ($data['noti_es_global'] === '0' && empty($data['destinatarios'])) {
            $errores['destinatarios'] = 'Selecciona al menos un usuario destinatario.';
        }

        return $errores;
    }

    private function _resolver_destinatarios(array $data)
    {
        $usuarios = $this->Notificaciones_model->get_usuarios_activos();
        $ids_validos = array_column($usuarios, 'usu_id');

        if ($data['noti_es_global'] === '1') {
            return $ids_validos;
        }

        return array_values(array_intersect($data['destinatarios'], $ids_validos));
    }
}
