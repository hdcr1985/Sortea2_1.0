<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReportesLogins extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reportes_logins_model');
        $this->page_styles = ['assets/plugins/custom/datatables/datatables.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/datatables/datatables.bundle.js'];

        $this->page_title = 'Reporte de Logins';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Reporte de Logins', 'url' => ''],
        ];
    }

    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar el registro de eventos.');

        $datos = [
            'usuarios_filtro' => $this->Reportes_logins_model->get_usuarios_filtro(),
        ];

        $this->render('pages/reportes_logins/index', $datos);
    }

    public function tabla()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar el registro de eventos.');

        $draw = (int)$this->input->post('draw');
        $start = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $searchData = $this->input->post('search');
        $search = is_array($searchData) ? (string)($searchData['value'] ?? '') : '';
        $usuario = trim((string)$this->input->post('usuario', TRUE));
        $order = $this->input->post('order');

        $order_column = 6;
        $order_dir = 'desc';

        if (!empty($order[0])) {
            $order_column = (int)($order[0]['column'] ?? 6);
            $order_dir = (string)($order[0]['dir'] ?? 'desc');
        }

        $rows = $this->Reportes_logins_model->get_datatable($start, $length, $search, $usuario, $order_column, $order_dir);

        $this->json([
            'draw' => $draw,
            'recordsTotal' => $this->Reportes_logins_model->count_todos(),
            'recordsFiltered' => $this->Reportes_logins_model->count_filtrados($search, $usuario),
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

            $locationHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars($this->_resolver_ubicacion_sesion($item), ENT_QUOTES, 'UTF-8') . '</div>';
            $network = $this->_resolver_red_sesion($item);
            if ($network !== '') {
                $locationHtml .= '<div class="text-muted fw-semibold fs-8 mt-1">' . htmlspecialchars($network, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $coordinates = $this->_resolver_coordenadas_sesion($item);
            if ($coordinates !== '') {
                $locationHtml .= '<div class="text-muted fs-8">Coords: ' . htmlspecialchars($coordinates, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $status = $this->_resolver_estado_sesion($item);
            $statusHtml = '<span class="badge badge-light-' . htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8') . ' fw-bold">' . htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') . '</span>';

            $ipHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars((string)($item['bit_ip'] ?: '—'), ENT_QUOTES, 'UTF-8') . '</div>';
            if (!empty($item['bit_host'])) {
                $ipHtml .= '<div class="text-muted fs-8 mt-1">' . htmlspecialchars((string)$item['bit_host'], ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $fechaHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars($this->_formatear_fecha_relativa($item['bit_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div class="text-muted fs-8 mt-1">' . htmlspecialchars($this->_formatear_fecha_absoluta($item['bit_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';

            $data[] = [
                'usuario' => $usuarioHtml,
                'ubicacion' => $locationHtml,
                'estado' => $statusHtml,
                'dispositivo' => htmlspecialchars($this->_resolver_dispositivo_sesion($item), ENT_QUOTES, 'UTF-8'),
                'ip' => $ipHtml,
                'red' => htmlspecialchars($network !== '' ? $network : '—', ENT_QUOTES, 'UTF-8'),
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

    private function _resolver_estado_sesion(array $row)
    {
        if ((string)($row['bit_is_crawler'] ?? '0') === '1' || (string)($row['bit_tor'] ?? '0') === '1') {
            return ['label' => 'ERR', 'class' => 'danger'];
        }

        if ((string)($row['bit_proxy'] ?? '0') === '1' || (string)($row['bit_vpn'] ?? '0') === '1') {
            return ['label' => 'WRN', 'class' => 'warning'];
        }

        return ['label' => 'OK', 'class' => 'success'];
    }

    private function _resolver_ubicacion_sesion(array $row)
    {
        $parts = array_filter([
            trim((string)($row['bit_city'] ?? '')),
            trim((string)($row['bit_region'] ?? '')),
            trim((string)($row['bit_country_code'] ?? '')),
        ]);

        if (!empty($parts)) {
            return implode(', ', $parts);
        }

        return trim((string)($row['bit_host'] ?? '')) !== '' ? (string)$row['bit_host'] : 'Desconocida';
    }

    private function _resolver_dispositivo_sesion(array $row)
    {
        $ua = trim((string)($row['bit_navegador'] ?? ''));
        $browser = 'Navegador';
        $platform = 'Dispositivo';

        $browsers = [
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'MSIE' => 'Internet Explorer',
            'Trident' => 'Internet Explorer',
        ];

        foreach ($browsers as $needle => $label) {
            if (stripos($ua, $needle) !== FALSE) {
                $browser = $label;
                break;
            }
        }

        if ((string)($row['bit_mobile'] ?? '0') === '1') {
            if (stripos($ua, 'iPhone') !== FALSE) {
                $platform = 'iPhone';
            } elseif (stripos($ua, 'iPad') !== FALSE) {
                $platform = 'iPad';
            } elseif (stripos($ua, 'Android') !== FALSE) {
                $platform = 'Android';
            } else {
                $platform = 'Mobile';
            }
        } else {
            if (stripos($ua, 'Windows') !== FALSE) {
                $platform = 'Windows';
            } elseif (stripos($ua, 'Mac OS') !== FALSE || stripos($ua, 'Macintosh') !== FALSE) {
                $platform = 'Mac OS';
            } elseif (stripos($ua, 'Linux') !== FALSE) {
                $platform = 'Linux';
            }
        }

        return $browser . ' - ' . $platform;
    }

    private function _resolver_red_sesion(array $row)
    {
        $parts = [];

        $organization = trim((string)($row['bit_organization'] ?? ''));
        $isp = trim((string)($row['bit_isp'] ?? ''));
        $asn = trim((string)($row['bit_asn'] ?? ''));

        if ($organization !== '') {
            $parts[] = $organization;
        } elseif ($isp !== '') {
            $parts[] = $isp;
        }

        if ($asn !== '') {
            $parts[] = 'ASN ' . $asn;
        }

        return !empty($parts) ? implode(' · ', $parts) : '';
    }

    private function _resolver_coordenadas_sesion(array $row)
    {
        $latitude = trim((string)($row['bit_latitude'] ?? ''));
        $longitude = trim((string)($row['bit_longitude'] ?? ''));

        if ($latitude === '' || $longitude === '') {
            return '';
        }

        return $latitude . ', ' . $longitude;
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