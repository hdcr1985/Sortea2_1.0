<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReportesSesionesActivas extends MY_Controller
{
    private $url_menu = 'sesiones-activas';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reportes_sesiones_activas_model');
        $this->page_styles = ['assets/plugins/custom/datatables/datatables.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/datatables/datatables.bundle.js'];

        $this->page_title = 'Sesiones Activas';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Sesiones Activas', 'url' => ''],
        ];
    }

    public function index()
    {
        $this->_autorizar_modulo();
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las sesiones activas.');
        $this->render('pages/reportes_sesiones_activas/index');
    }

    public function tabla()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        $this->_autorizar_modulo();
        $this->autorizar_accion('consultar', 'No tienes permiso para consultar las sesiones activas.');

        $draw = (int)$this->input->post('draw');
        $start = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $searchData = $this->input->post('search');
        $search = is_array($searchData) ? (string)($searchData['value'] ?? '') : '';
        $order = $this->input->post('order');

        $order_column = 2;
        $order_dir = 'desc';

        if (!empty($order[0])) {
            $order_column = (int)($order[0]['column'] ?? 2);
            $order_dir = (string)($order[0]['dir'] ?? 'desc');
        }

        $rows = $this->Reportes_sesiones_activas_model->get_datatable($start, $length, $search, $order_column, $order_dir);

        $this->json([
            'draw' => $draw,
            'recordsTotal' => $this->Reportes_sesiones_activas_model->count_todos(),
            'recordsFiltered' => $this->Reportes_sesiones_activas_model->count_filtrados($search),
            'data' => $this->_formatear_filas_datatable($rows),
        ]);
    }

    public function detalle()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        $this->_autorizar_modulo();
        $this->autorizar_accion('consultar', 'No tienes permiso para ver el detalle de sesiones.');

        $session_token = trim((string)$this->input->post('session_token', TRUE));
        if ($session_token === '') {
            return $this->json(['ok' => FALSE, 'msg' => 'Sesión inválida.'], 422);
        }

        $row = $this->Reportes_sesiones_activas_model->get_detalle_activo($session_token);
        if (empty($row)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La sesión activa ya no existe.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $this->_formatear_detalle($row)]);
    }

    public function banear($usuario_id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        $this->_autorizar_modulo();
        $this->_requiere_post();
        $this->autorizar_accion('eliminar', 'No tienes permiso para banear usuarios.');

        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return $this->json(['ok' => FALSE, 'msg' => 'Usuario inválido.'], 422);
        }

        if ($usuario_id === (string)($this->usuario['usu_id'] ?? '0')) {
            return $this->json(['ok' => FALSE, 'msg' => 'No puedes banear tu propia sesión activa.'], 422);
        }

        $result = $this->Reportes_sesiones_activas_model->banear_y_cerrar_sesiones(
            $usuario_id,
            (string)($this->usuario['usu_tipoper'] ?? '')
        );
        if (!empty($result['protegido'])) {
            return $this->json(['ok' => FALSE, 'msg' => 'No puedes banear a un usuario con rol Administrador Master.'], 403);
        }
        if (!$result['ok']) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo banear al usuario.'], 500);
        }

        $usuario = $result['usuario'];
        $this->bitacora('USUARIO_BANEAR', 'Usuario baneado desde sesiones activas: ' . ($usuario['usu_usuario'] ?? $usuario_id));

        $msg = 'Usuario baneado correctamente.';
        if ((int)$result['sesiones_cerradas'] > 0) {
            $msg .= ' Se cerraron ' . (int)$result['sesiones_cerradas'] . ' sesión(es) activas.';
        }

        $this->json([
            'ok' => TRUE,
            'msg' => $msg,
            'sesiones_cerradas' => (int)$result['sesiones_cerradas'],
        ]);
    }

    public function cerrar_sesion($usuario_id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        $this->_autorizar_modulo();
        $this->_requiere_post();
        $this->autorizar_accion('modificar', 'No tienes permiso para cerrar sesiones.');

        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return $this->json(['ok' => FALSE, 'msg' => 'Usuario inválido.'], 422);
        }

        if ($usuario_id === (string)($this->usuario['usu_id'] ?? '0')) {
            return $this->json(['ok' => FALSE, 'msg' => 'No puedes cerrar tu propia sesión desde este módulo.'], 422);
        }

        $result = $this->Reportes_sesiones_activas_model->cerrar_sesiones_usuario(
            $usuario_id,
            (string)($this->usuario['usu_tipoper'] ?? '')
        );
        if (!empty($result['protegido'])) {
            return $this->json(['ok' => FALSE, 'msg' => 'No puedes cerrar la sesión de un usuario con rol Administrador Master.'], 403);
        }
        if (!$result['ok']) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cerrar la sesión del usuario.'], 500);
        }

        $usuario = $result['usuario'];
        $this->bitacora('USUARIO_CERRAR_SESION', 'Sesión forzada desde sesiones activas: ' . ($usuario['usu_usuario'] ?? $usuario_id));

        $msg = 'Sesión cerrada correctamente.';
        if ((int)$result['sesiones_cerradas'] > 1) {
            $msg = 'Se cerraron ' . (int)$result['sesiones_cerradas'] . ' sesiones activas del usuario.';
        } elseif ((int)$result['sesiones_cerradas'] === 0) {
            $msg = 'El usuario no tenía sesiones activas para cerrar.';
        }

        $this->json([
            'ok' => TRUE,
            'msg' => $msg,
            'sesiones_cerradas' => (int)$result['sesiones_cerradas'],
        ]);
    }

    /**
     * Refuerza el control de acceso del módulo. MY_Controller solo compara la
     * raíz de la URL, por lo que la URL directa del controlador no queda
     * cubierta; aquí se exige que el rol tenga el menú asignado.
     */
    private function _autorizar_modulo()
    {
        $rol = (string)($this->usuario['usu_tipoper'] ?? '');
        if ($rol !== '' && $this->Menu_model->usuario_tiene_acceso($rol, $this->url_menu)) {
            return;
        }

        if ($this->input->is_ajax_request()) {
            $this->json(['ok' => FALSE, 'msg' => 'No tienes permiso para realizar esta acción.'], 403);
            $this->output->_display();
            exit;
        }

        redirect('sin-permisos');
    }

    private function _requiere_post()
    {
        if ($this->input->method(TRUE) === 'POST') {
            return;
        }

        $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        $this->output->_display();
        exit;
    }

    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $nombre = $this->_resolver_nombre_usuario($item, 'Usuario');
            $username = trim((string)($item['usu_usuario'] ?? ''));
            $iniciales = strtoupper(substr($nombre !== '' ? $nombre : $username, 0, 1));
            $foto = $this->_resolver_foto_usuario_url($item['usu_id'] ?? '', $item['usu_foto'] ?? '');
            $es_actual = (string)($item['usu_id'] ?? '') === (string)($this->usuario['usu_id'] ?? '0');

            $avatar = $foto !== ''
                ? '<img src="' . htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" class="object-fit-cover" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">'
                    . '<span class="symbol-label bg-light-primary text-primary fw-bold" style="display:none;">' . htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="symbol-label bg-light-primary text-primary fw-bold">' . htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') . '</span>';

            $usuarioHtml = ''
                . '<div class="d-flex align-items-center">'
                . '    <div class="symbol symbol-45px me-3">' . $avatar . '</div>'
                . '    <div class="d-flex flex-column">'
                . '        <span class="fw-bold text-gray-900 fs-6">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</span>'
                . '        <span class="text-muted fw-semibold fs-7">@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</span>'
                . '    </div>'
                . '</div>';

            $rolHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars((string)($item['rol_nombre'] ?? 'Sin rol'), ENT_QUOTES, 'UTF-8') . '</div>';
            if (!empty($item['usu_perfil'])) {
                $rolHtml .= '<div class="text-muted fs-8 mt-1">' . htmlspecialchars((string)$item['usu_perfil'], ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $sesionHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars($this->_formatear_fecha_relativa($item['sesion_desde'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div class="text-muted fs-8 mt-1">Inicio: ' . htmlspecialchars((string)($item['sesion_desde'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div class="text-muted fs-8">Actividad: ' . htmlspecialchars((string)($item['ultima_actividad'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</div>';

            if ((int)($item['active_sessions_count'] ?? 1) > 1) {
                $sesionHtml .= '<div class="text-muted fs-8">Sesiones agrupadas: ' . (int)$item['active_sessions_count'] . '</div>';
            }

            $accesoHtml = '<div class="fw-bold text-gray-900 fs-7">' . htmlspecialchars((string)($item['bit_ip'] ?: '—'), ENT_QUOTES, 'UTF-8') . '</div>';
            $ubicacion = $this->_resolver_ubicacion_sesion($item);
            if ($ubicacion !== '') {
                $accesoHtml .= '<div class="text-muted fs-8 mt-1">' . htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $red = $this->_resolver_red_sesion($item);
            if ($red !== '') {
                $accesoHtml .= '<div class="text-muted fs-8">' . htmlspecialchars($red, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $estado = $this->_resolver_estado_sesion($item);
            $estadoHtml = '<span class="badge badge-light-' . htmlspecialchars($estado['class'], ENT_QUOTES, 'UTF-8') . ' fw-bold px-3 py-2">'
                . '<span class="bullet bullet-dot bg-' . htmlspecialchars($estado['class'], ENT_QUOTES, 'UTF-8') . ' me-1"></span>'
                . htmlspecialchars($estado['label'], ENT_QUOTES, 'UTF-8')
                . '</span>';

            $es_master = $this->Menu_model->rol_es_master($item['usu_tipoper'] ?? 0);
            $actor_master = $this->es_master;
            $protegido = $es_master && !$actor_master;

            $acciones = ''
                . '<div class="d-flex justify-content-end align-items-center gap-2">'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-detalle-sesion" data-session="' . htmlspecialchars((string)($item['session_token'] ?? ''), ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Ver detalles" aria-label="Ver detalles">'
                . '        <i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-warning shadow-xs btn-cerrar-sesion" data-id="' . htmlspecialchars((string)$item['usu_id'], ENT_QUOTES, 'UTF-8') . '" data-nombre="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top"' . (($es_actual || $protegido) ? ' disabled title="' . ($protegido ? 'Rol protegido' : 'Sesión actual') . '"' : ' title="Cerrar sesión"') . '>'
                . '        <i class="ki-duotone ki-exit-right fs-4"><span class="path1"></span><span class="path2"></span></i>'
                . '    </button>'
                . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger shadow-xs btn-banear-sesion" data-id="' . htmlspecialchars((string)$item['usu_id'], ENT_QUOTES, 'UTF-8') . '" data-nombre="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top"' . (($es_actual || $protegido) ? ' disabled title="' . ($protegido ? 'Rol protegido' : 'Sesión actual') . '"' : ' title="Banear usuario"') . '>'
                . '        <i class="ki-duotone ki-shield-cross fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                . '    </button>'
                . '</div>';

            $data[] = [
                'usuario' => $usuarioHtml,
                'rol' => $rolHtml,
                'sesion' => $sesionHtml,
                'acceso' => $accesoHtml,
                'estado' => $estadoHtml,
                'acciones' => $acciones,
            ];
        }

        return $data;
    }

    private function _formatear_detalle(array $row)
    {
        return [
            'usuario' => $this->_resolver_nombre_usuario($row, 'Usuario'),
            'username' => trim((string)($row['usu_usuario'] ?? '')),
            'correo' => trim((string)($row['usu_correo'] ?? '')),
            'rol' => trim((string)($row['rol_nombre'] ?? 'Sin rol')),
            'perfil' => trim((string)($row['usu_perfil'] ?? '')),
            'sesion_desde' => trim((string)($row['sesion_desde'] ?? '')),
            'ultima_actividad' => trim((string)($row['ultima_actividad'] ?? '')),
            'sesiones_activas' => (int)($row['active_sessions_count'] ?? 1),
            'ip' => trim((string)($row['bit_ip'] ?? '')),
            'host' => trim((string)($row['bit_host'] ?? '')),
            'ubicacion' => $this->_resolver_ubicacion_sesion($row),
            'red' => $this->_resolver_red_sesion($row),
            'asn' => trim((string)($row['bit_asn'] ?? '')),
            'isp' => trim((string)($row['bit_isp'] ?? '')),
            'organizacion' => trim((string)($row['bit_organization'] ?? '')),
            'navegador' => trim((string)($row['bit_navegador'] ?? '')),
            'proxy' => (string)($row['bit_proxy'] ?? '0') === '1' ? 'Sí' : 'No',
            'vpn' => (string)($row['bit_vpn'] ?? '0') === '1' ? 'Sí' : 'No',
            'tor' => (string)($row['bit_tor'] ?? '0') === '1' ? 'Sí' : 'No',
            'mobile' => (string)($row['bit_mobile'] ?? '0') === '1' ? 'Sí' : 'No',
            'crawler' => (string)($row['bit_is_crawler'] ?? '0') === '1' ? 'Sí' : 'No',
            'coordenadas' => $this->_resolver_coordenadas_sesion($row),
            'request_id' => trim((string)($row['bit_request_id'] ?? '')),
        ];
    }

    private function _resolver_nombre_usuario(array $row, $fallback = 'Usuario')
    {
        $nombre = trim((string)($row['usu_nombre'] ?? ''));
        $apellido = trim((string)($row['usu_apellido'] ?? ''));
        $completo = trim($nombre . ' ' . $apellido);

        if ($completo !== '') {
            return $completo;
        }

        $fallback = trim((string)$fallback);
        return $fallback !== '' ? $fallback : 'Usuario';
    }

    private function _resolver_estado_sesion(array $row)
    {
        if ((string)($row['bit_is_crawler'] ?? '0') === '1' || (string)($row['bit_tor'] ?? '0') === '1') {
            return ['label' => 'Riesgo alto', 'class' => 'danger'];
        }

        if ((string)($row['bit_proxy'] ?? '0') === '1' || (string)($row['bit_vpn'] ?? '0') === '1') {
            return ['label' => 'Revisar', 'class' => 'warning'];
        }

        return ['label' => 'En línea', 'class' => 'success'];
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

        return trim((string)($row['bit_host'] ?? ''));
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
}