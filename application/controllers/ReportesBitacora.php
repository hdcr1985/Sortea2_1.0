<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReportesBitacora extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reportes_bitacora_model');
        $this->page_styles = ['assets/plugins/custom/datatables/datatables.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/datatables/datatables.bundle.js'];

        $this->page_title = 'Reporte de Bitácora';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Reporte de Bitácora', 'url' => ''],
        ];
    }

    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar la bitácora.');

        $datos = [
            'usuarios_filtro' => $this->Reportes_bitacora_model->get_usuarios_filtro(),
        ];

        $this->render('pages/reportes_bitacora/index', $datos);
    }

    public function tabla()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar la bitácora.');

        $draw = (int)$this->input->post('draw');
        $start = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $searchData = $this->input->post('search');
        $search = is_array($searchData) ? (string)($searchData['value'] ?? '') : '';
        $usuario = trim((string)$this->input->post('usuario', TRUE));
        $order = $this->input->post('order');

        $order_column = 5;
        $order_dir = 'desc';

        if (!empty($order[0])) {
            $order_column = (int)($order[0]['column'] ?? 5);
            $order_dir = (string)($order[0]['dir'] ?? 'desc');
        }

        $rows = $this->Reportes_bitacora_model->get_datatable($start, $length, $search, $usuario, $order_column, $order_dir);

        $this->json([
            'draw' => $draw,
            'recordsTotal' => $this->Reportes_bitacora_model->count_todos(),
            'recordsFiltered' => $this->Reportes_bitacora_model->count_filtrados($search, $usuario),
            'data' => $this->_formatear_filas_datatable($rows),
        ]);
    }

    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $nombre = $this->_resolver_nombre_usuario($item, (string)($item['bit_usuario'] ?? 'Usuario'));
            $username = trim((string)($item['bit_usuario'] ?? '—'));
            $iniciales = strtoupper(substr($nombre !== '' ? $nombre : $username, 0, 1));
            $foto = $this->_resolver_foto_usuario_url($item['usu_id'] ?? '', $item['usu_foto'] ?? '');
            $avatar = $foto !== ''
                ? '<img src="' . htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" class="object-fit-cover" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">'
                    . '<span class="symbol-label bg-light-primary text-primary fw-bold" style="display:none;">' . htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="symbol-label bg-light-primary text-primary fw-bold">' . htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') . '</span>';

            $usuarioHtml = ''
                . '<div class="d-flex align-items-center">'
                . '    <div class="symbol symbol-45px me-3">' . $avatar . '</div>'
                . '    <div class="d-flex flex-column">'
                . '        <span class="fw-bold text-dark fs-6">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</span>'
                . '        <span class="text-muted fw-semibold fs-7">@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</span>'
                . '    </div>'
                . '</div>';

            $accion = trim((string)($item['bit_accion'] ?? ''));
            $badgeClass = 'badge-light-primary';
            if (stripos($accion, 'LOGIN_FALLIDO') !== FALSE) {
                $badgeClass = 'badge-light-danger';
            } elseif (stripos($accion, 'LOGIN') !== FALSE || stripos($accion, 'CREAR') !== FALSE || stripos($accion, 'ACTUALIZAR') !== FALSE || stripos($accion, 'EDITAR') !== FALSE) {
                $badgeClass = 'badge-light-success';
            } elseif (stripos($accion, 'ELIMINAR') !== FALSE || stripos($accion, 'BORRAR') !== FALSE) {
                $badgeClass = 'badge-light-danger';
            } elseif (stripos($accion, 'LOGOUT') !== FALSE || stripos($accion, 'RECUPERAR') !== FALSE) {
                $badgeClass = 'badge-light-warning';
            }

            $detalle = trim((string)($item['bit_detalles'] ?? ''));
            $detalleHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars($detalle !== '' ? $detalle : 'Sin detalle.', ENT_QUOTES, 'UTF-8') . '</div>';
            if (!empty($item['bit_navegador'])) {
                $detalleHtml .= '<div class="text-muted fs-8 mt-1">' . htmlspecialchars((string)$item['bit_navegador'], ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $fechaHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars($this->_formatear_fecha_relativa($item['bit_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div class="text-muted fs-8 mt-1">' . htmlspecialchars($this->_formatear_fecha_absoluta($item['bit_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';

            $data[] = [
                'usuario' => $usuarioHtml,
                'accion' => '<span class="badge ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . ' fw-bold">' . htmlspecialchars($accion !== '' ? $accion : '—', ENT_QUOTES, 'UTF-8') . '</span>',
                'detalle' => $detalleHtml,
                'navegador' => htmlspecialchars((string)($item['bit_navegador'] ?: '—'), ENT_QUOTES, 'UTF-8'),
                'ip' => htmlspecialchars((string)($item['bit_ip'] ?: '—'), ENT_QUOTES, 'UTF-8'),
                'fecha' => $fechaHtml,
            ];
        }

        return $data;
    }

    private function _resolver_nombre_usuario(array $item, $fallback = 'Usuario')
    {
        $nombre = trim((string)($item['usu_nombre'] ?? ''));
        $apellido = trim((string)($item['usu_apellido'] ?? ''));
        $completo = trim($nombre . ' ' . $apellido);

        if ($completo !== '') {
            return $completo;
        }

        $fallback = trim((string)$fallback);
        return $fallback !== '' ? $fallback : 'Usuario';
    }

    private function _resolver_foto_usuario_url($usuario_id, $foto)
    {
        $foto = trim((string)$foto);
        $usuario_id = trim((string)$usuario_id);
        if ($foto === '' || $usuario_id === '') {
            return '';
        }

        $ruta = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'usuarios'
            . DIRECTORY_SEPARATOR . basename($usuario_id)
            . DIRECTORY_SEPARATOR . basename($foto);

        $info = is_file($ruta) ? @getimagesize($ruta) : FALSE;
        $mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
        if ($info === FALSE || !in_array($info['mime'] ?? '', $mimes_permitidos, TRUE)) {
            return '';
        }

        return base_url('media/usuario/' . rawurlencode($usuario_id));
    }

    private function _formatear_fecha_relativa($datetime)
    {
        if (trim((string)$datetime) === '') {
            return '—';
        }

        $timestamp = strtotime((string)$datetime);
        if ($timestamp === FALSE) {
            return (string)$datetime;
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Ahora';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' mins';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' hrs';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . ' days';
        }

        return date('d-m-Y H:i', $timestamp);
    }

    private function _formatear_fecha_absoluta($datetime)
    {
        $timestamp = strtotime((string)$datetime);
        if ($timestamp === FALSE) {
            return '—';
        }

        return date('d-m-Y H:i:s', $timestamp);
    }
}