<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Perfil extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Usuarios_model');
        $this->load->model('Configuracion_correos_model');
        $this->load->model('User_2fa_model');
        $this->load->library('Totp_service');
        $this->load->library('Secret_crypto');
        $this->page_title = 'Mi Perfil';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Mi Perfil', 'url' => ''],
        ];
    }

    public function index()
    {
        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil = $this->_obtener_perfil($usuario_id);

        if (empty($perfil)) {
            show_404();
        }

        // Solo exponer una URL si el archivo existe realmente; de lo contrario
        // la vista usa el avatar por defecto.
        $perfil['usu_foto_url'] = $this->_resolver_foto_usuario_url(
            $perfil['usu_id'] ?? '',
            $perfil['usu_foto'] ?? ''
        );

        $puede_ver_bitacora = $this->es_master;

        $datos = [
            'perfil' => $perfil,
            'success' => $this->session->flashdata('success'),
            'error' => $this->session->flashdata('error'),
            'errores' => $this->session->flashdata('errores') ?: [],
            'sesiones_login' => $this->_obtener_sesiones_login($perfil['usu_usuario']),
            'puede_ver_bitacora' => $puede_ver_bitacora,
            'actividad' => $puede_ver_bitacora ? $this->_obtener_bitacora_usuario($perfil['usu_usuario']) : [],
            'totp_enabled' => (int)($perfil['usu_2fa_totp_enabled'] ?? 0) === 1,
            'totp_recovery_count' => $this->User_2fa_model->count_recovery_codes($usuario_id),
        ];

        $this->render('pages/perfil/index', $datos);
    }

    public function guardar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            show_404();
            return;
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil = $this->_obtener_perfil($usuario_id);
        if (empty($perfil)) {
            show_404();
        }

        $data = $this->_recoger_post_datos();
        $errores = $this->_validar_datos($data, $usuario_id);

        $foto = $this->_procesar_foto_subida($usuario_id, $perfil['usu_foto'] ?? '');
        if (!$foto['ok']) {
            $errores['usu_foto'] = $foto['error'];
        }

        if (!empty($errores)) {
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('error', 'Corrige los datos del perfil y vuelve a intentar.');
            return redirect('perfil');
        }

        $guardado = [
            'usu_nombre' => $data['usu_nombre'],
            'usu_correo' => $data['usu_correo'],
            'usu_celular' => $data['usu_celular'],
            'usu_fechan' => $data['usu_fechan'] !== '' ? $data['usu_fechan'] : NULL,
            'usu_genero' => $data['usu_genero'],
            'usu_foto' => $foto['archivo'],
        ];

        if (!$this->Usuarios_model->actualizar($usuario_id, $guardado)) {
            $this->session->set_flashdata('error', 'No se pudo actualizar la información del perfil.');
            return redirect('perfil');
        }

        $this->_refrescar_sesion($usuario_id);
        $this->bitacora('PERFIL_ACTUALIZAR', 'El usuario actualizó su información de perfil.');
        $this->session->set_flashdata('success', 'Tu perfil fue actualizado correctamente.');
        redirect('perfil');
    }

    public function cambiar_clave()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            show_404();
            return;
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil = $this->Usuarios_model->get_por_id($usuario_id);
        if (empty($perfil)) {
            show_404();
        }

        $clave_actual = (string)$this->input->post('clave_actual');
        $nueva_clave = (string)$this->input->post('nueva_clave');
        $confirmacion = (string)$this->input->post('nueva_clave_confirm');
        $errores = [];

        $clave_valida = password_verify($clave_actual, (string)($perfil['usu_clave'] ?? ''));

        // Compatibilidad con hashes MD5 heredados; la nueva clave se guarda con password_hash.
        if (!$clave_valida
            && strlen((string)($perfil['usu_clave'] ?? '')) === 32
            && hash_equals((string)($perfil['usu_clave'] ?? ''), md5($clave_actual))) {
            $clave_valida = TRUE;
        }

        if ($clave_actual === '' || !$clave_valida) {
            $errores['clave_actual'] = 'La contraseña actual no es válida.';
        }

        if ($nueva_clave === '' || strlen($nueva_clave) < 8) {
            $errores['nueva_clave'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        if ($nueva_clave !== $confirmacion) {
            $errores['nueva_clave_confirm'] = 'La confirmación de contraseña no coincide.';
        }

        if (!empty($errores)) {
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('error', 'No se pudo actualizar la contraseña.');
            return redirect('perfil');
        }

        $guardado = $this->Usuarios_model->actualizar($usuario_id, [
            'usu_clave' => password_hash($nueva_clave, PASSWORD_DEFAULT),
        ]);

        if (!$guardado) {
            $this->session->set_flashdata('error', 'No se pudo actualizar la contraseña.');
            return redirect('perfil');
        }

        $this->bitacora('PERFIL_CLAVE', 'El usuario actualizó su contraseña.');
        $this->session->set_flashdata('success', 'Tu contraseña fue actualizada correctamente.');
        redirect('perfil');
    }

    /**
     * POST /perfil/toggle_2fa o /Perfil/toggle_2fa
     * Habilita o deshabilita la autenticación de dos pasos (2FA) para el usuario autenticado.
     */
    public function toggle_2fa()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            if ($this->input->is_ajax_request()) {
                return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
            }
            show_404();
            return;
        }

        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil = $this->_obtener_perfil($usuario_id);
        if (empty($perfil)) {
            if ($this->input->is_ajax_request()) {
                return $this->json(['ok' => FALSE, 'msg' => 'Usuario no encontrado.'], 404);
            }
            show_404();
            return;
        }

        $estado_raw = $this->input->post('estado');
        if (!in_array((string)$estado_raw, ['0', '1'], TRUE)) {
            $msg = 'Estado de 2FA inválido.';
            if ($this->input->is_ajax_request()) {
                return $this->json(['ok' => FALSE, 'msg' => $msg], 422);
            }
            $this->session->set_flashdata('error', $msg);
            return redirect('perfil');
        }

        $nuevo_estado = (int)$estado_raw;

        // Si se va a activar, comprobar que el usuario tenga un correo válido configurado
        if ($nuevo_estado === 1) {
            $correo = trim((string)($perfil['usu_correo'] ?? ''));
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $msg = 'Debes configurar un correo electrónico válido en tu perfil antes de activar la autenticación 2FA.';
                if ($this->input->is_ajax_request()) {
                    return $this->json(['ok' => FALSE, 'msg' => $msg], 422);
                }
                $this->session->set_flashdata('error', $msg);
                return redirect('perfil');
            }

            // Comprobar que el servicio de correo (SMTP) esté activo
            $mail_settings = $this->Configuracion_correos_model->get_settings();
            if (empty($mail_settings['mail_enabled_bool'])) {
                $msg = 'El servicio de envío de correos (SMTP) no está activo en el sistema. Consulta al administrador.';
                if ($this->input->is_ajax_request()) {
                    return $this->json(['ok' => FALSE, 'msg' => $msg], 422);
                }
                $this->session->set_flashdata('error', $msg);
                return redirect('perfil');
            }
        }

        $guardado = $this->Usuarios_model->actualizar($usuario_id, [
            'usu_2fa' => $nuevo_estado,
        ]);

        if (!$guardado) {
            $msg = 'No se pudo actualizar el estado de 2FA. Intenta de nuevo.';
            if ($this->input->is_ajax_request()) {
                return $this->json(['ok' => FALSE, 'msg' => $msg], 500);
            }
            $this->session->set_flashdata('error', $msg);
            return redirect('perfil');
        }

        $this->_refrescar_sesion($usuario_id);
        $accion_bitacora = ($nuevo_estado === 1) ? 'PERFIL_2FA_ACTIVADO' : 'PERFIL_2FA_DESACTIVADO';
        $detalle_bitacora = ($nuevo_estado === 1)
            ? 'El usuario activó la autenticación en dos pasos (2FA).'
            : 'El usuario desactivó la autenticación en dos pasos (2FA).';
        $this->bitacora($accion_bitacora, $detalle_bitacora);

        $msg_exito = ($nuevo_estado === 1)
            ? '¡Autenticación de dos pasos (2FA) activada correctamente! En tu próximo inicio de sesión se enviará un código de verificación a tu correo (' . htmlspecialchars($perfil['usu_correo']) . ').'
            : 'Autenticación de dos pasos (2FA) desactivada correctamente.';

        if ($this->input->is_ajax_request()) {
            return $this->json([
                'ok' => TRUE,
                'msg' => $msg_exito,
                'usu_2fa' => $nuevo_estado,
            ]);
        }

        $this->session->set_flashdata('success', $msg_exito);
        redirect('perfil');
    }



    public function totp_iniciar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->input->is_ajax_request()) {
            show_404();
            return;
        }
        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil = $this->_obtener_perfil($usuario_id);
        if (empty($perfil) || (int)($perfil['usu_2fa_totp_enabled'] ?? 0) === 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'La aplicación autenticadora ya está habilitada.'], 422);
        }
        $secret = $this->totp_service->generate_secret();
        $account = trim((string)($perfil['usu_correo'] ?? '')) ?: (string)$perfil['usu_usuario'];
        $this->session->set_userdata('totp_setup_secret', $secret);
        $this->session->set_userdata('totp_setup_at', time());
        return $this->json(['ok' => TRUE, 'secret' => $secret, 'otpauth_uri' => $this->totp_service->otpauth_uri($secret, $account, 'VerifyCode'), 'account' => $account]);
    }

    public function totp_confirmar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->input->is_ajax_request()) {
            show_404();
            return;
        }
        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $secret = (string)$this->session->userdata('totp_setup_secret');
        $started_at = (int)$this->session->userdata('totp_setup_at');
        $codigo = trim((string)$this->input->post('codigo', TRUE));
        if ($secret === '' || $started_at <= 0 || (time() - $started_at) > 600) {
            return $this->json(['ok' => FALSE, 'msg' => 'La sesión de configuración expiró. Inicia de nuevo.'], 422);
        }
        $step = $this->totp_service->verify($secret, $codigo, 1);
        if ($step === FALSE) {
            return $this->json(['ok' => FALSE, 'msg' => 'El código de la aplicación no es válido. Intenta de nuevo.'], 422);
        }
        $encrypted = $this->secret_crypto->encrypt($secret);
        if ($encrypted === '') {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo guardar el secreto de la aplicación.'], 500);
        }
        $codes = $this->totp_service->generate_recovery_codes(8);
        $hashes = [];
        foreach ($codes as $code) {
            $hashes[] = password_hash($this->totp_service->normalize_recovery_code($code), PASSWORD_BCRYPT);
        }
        if (!$this->User_2fa_model->enable_totp($usuario_id, $encrypted, (int)$step, $hashes)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo habilitar la aplicación autenticadora.'], 500);
        }
        $this->session->unset_userdata(['totp_setup_secret', 'totp_setup_at']);
        $this->_refrescar_sesion($usuario_id);
        $this->bitacora('PERFIL_2FA_TOTP_ACTIVADO', 'El usuario activó el 2FA con aplicación autenticadora.');
        return $this->json(['ok' => TRUE, 'msg' => 'Aplicación autenticadora habilitada correctamente.', 'recovery_codes' => $codes]);
    }

    public function totp_desactivar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->input->is_ajax_request()) {
            show_404();
            return;
        }
        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil = $this->_obtener_perfil($usuario_id);
        if (empty($perfil) || (int)($perfil['usu_2fa_totp_enabled'] ?? 0) !== 1) {
            return $this->json(['ok' => FALSE, 'msg' => 'La aplicación autenticadora no está habilitada.'], 422);
        }
        $clave = (string)$this->input->post('clave_actual', FALSE);
        $credentials = $this->db->select('usu_clave')->where('usu_id', $usuario_id)->limit(1)->get('cab_usuarios')->row_array();
        if ($clave === '' || empty($credentials) || !password_verify($clave, (string)$credentials['usu_clave'])) {
            return $this->json(['ok' => FALSE, 'msg' => 'La contraseña actual es incorrecta.'], 422);
        }
        if (!$this->User_2fa_model->disable_totp($usuario_id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo deshabilitar la aplicación autenticadora.'], 500);
        }
        $this->_refrescar_sesion($usuario_id);
        $this->bitacora('PERFIL_2FA_TOTP_DESACTIVADO', 'El usuario desactivó el 2FA con aplicación autenticadora.');
        return $this->json(['ok' => TRUE, 'msg' => 'Aplicación autenticadora deshabilitada correctamente.']);
    }

    private function _obtener_perfil($usuario_id)
    {
        return $this->db
            ->select('u.usu_id, u.usu_nombre, u.usu_usuario, u.usu_correo, u.usu_tipoper, u.usu_perfil, u.usu_foto, u.usu_pais, u.usu_celular, u.usu_fechan, u.usu_genero, u.usu_created_at, u.usu_updated_at, u.usu_status, u.usu_2fa, u.usu_2fa_totp_enabled, u.usu_2fa_totp_confirmed_at, r.per_nombre AS rol_nombre')
            ->from('cab_usuarios u')
            ->join('cab_grupopermi r', 'r.per_id = u.usu_tipoper', 'left')
            ->where('u.usu_id', (string)$usuario_id)
            ->limit(1)
            ->get()
            ->row_array();
    }
    private function _obtener_bitacora_usuario($usuario)
    {
        return $this->db
            ->select('bit_date, bit_accion, bit_detalles, bit_navegador, bit_ip')
            ->where('bit_usuario', (string)$usuario)
            ->order_by('bit_date', 'DESC')
            ->limit(30)
            ->get('cab_bitacora')
            ->result_array();
    }

    private function _obtener_sesiones_login($usuario)
    {
        if (!$this->db->table_exists('cab_bitacora_ip')) {
            return [];
        }

        $rows = $this->db
            ->select('bit_date, bit_navegador, bit_ip, bit_proxy, bit_tor, bit_vpn, bit_mobile, bit_country_code, bit_region, bit_city, bit_host, bit_is_crawler, bit_asn, bit_isp, bit_organization, bit_latitude, bit_longitude')
            ->where('bit_usuario', (string)$usuario)
            ->order_by('bit_date', 'DESC')
            ->limit(20)
            ->get('cab_bitacora_ip')
            ->result_array();

        foreach ($rows as &$row) {
            $row['status'] = $this->_resolver_estado_sesion($row);
            $row['location'] = $this->_resolver_ubicacion_sesion($row);
            $row['network'] = $this->_resolver_red_sesion($row);
            $row['coordinates'] = $this->_resolver_coordenadas_sesion($row);
            $row['device'] = $this->_resolver_dispositivo_sesion($row);
            $row['time_human'] = $this->_formatear_fecha_relativa($row['bit_date'] ?? '');
        }
        unset($row);

        return $rows;
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

    private function _recoger_post_datos()
    {
        return [
            'usu_nombre' => trim((string)$this->input->post('usu_nombre', TRUE)),
            'usu_correo' => trim((string)$this->input->post('usu_correo', TRUE)),
            'usu_celular' => $this->normalizar_celular((string)$this->input->post('usu_celular', TRUE)),
            'usu_fechan' => trim((string)$this->input->post('usu_fechan', TRUE)),
            'usu_genero' => trim((string)$this->input->post('usu_genero', TRUE)),
        ];
    }

    private function _validar_datos(array $data, $usuario_id)
    {
        $errores = [];

        if ($data['usu_nombre'] === '') {
            $errores['usu_nombre'] = 'El nombre es requerido.';
        } elseif (strlen($data['usu_nombre']) > 150) {
            $errores['usu_nombre'] = 'El nombre no puede superar 150 caracteres.';
        }

        if ($data['usu_correo'] === '') {
            $errores['usu_correo'] = 'El correo es requerido.';
        } elseif (!filter_var($data['usu_correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['usu_correo'] = 'Ingresa un correo válido.';
        } elseif ($this->Usuarios_model->existe_correo($data['usu_correo'], $usuario_id)) {
            $errores['usu_correo'] = 'Ese correo ya está registrado.';
        }

        $validacionCelular = $this->validar_celular_ve($data['usu_celular'] ?? '', TRUE);
        if (!$validacionCelular['ok']) {
            $errores['usu_celular'] = $validacionCelular['msg'];
        }

        if ($data['usu_fechan'] !== '' && !$this->_es_fecha_valida($data['usu_fechan'])) {
            $errores['usu_fechan'] = 'La fecha de nacimiento debe tener formato YYYY-MM-DD.';
        }

        if ($data['usu_genero'] !== '' && strlen($data['usu_genero']) > 30) {
            $errores['usu_genero'] = 'El género no puede superar 30 caracteres.';
        }

        return $errores;
    }

    private function _refrescar_sesion($usuario_id)
    {
        $sesion = $this->Usuarios_model->get_sesion_por_id($usuario_id);
        if (!empty($sesion)) {
            $sesion['usu_foto'] = $this->_resolver_foto_usuario_url($sesion['usu_id'], $sesion['usu_foto'] ?? '');
            $this->session->set_userdata('usuario', $sesion);
            $this->usuario = $sesion;
        }
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

    private function _es_fecha_valida($fecha)
    {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt && $dt->format('Y-m-d') === $fecha;
    }

    private function _procesar_foto_subida($usuario_id, $foto_actual = '')
    {
        if (empty($_FILES['usu_foto_archivo']) || !isset($_FILES['usu_foto_archivo']['error'])) {
            return ['ok' => TRUE, 'archivo' => $foto_actual];
        }

        $archivo = $_FILES['usu_foto_archivo'];

        if ((int)$archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => TRUE, 'archivo' => $foto_actual];
        }

        if ((int)$archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => FALSE, 'error' => 'No se pudo subir la foto.'];
        }


        if ((int)$archivo['size'] <= 0 || (int)$archivo['size'] > 3 * 1024 * 1024) {
            return ['ok' => FALSE, 'error' => 'La foto no puede superar 3 MB.'];
        }

        $tmp = $archivo['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return ['ok' => FALSE, 'error' => 'Archivo de subida inválido.'];
        }

        $info = @getimagesize($tmp);
        if ($info === FALSE || empty($info['mime'])) {
            return ['ok' => FALSE, 'error' => 'El archivo debe ser una imagen válida.'];
        }

        $mime = $info['mime'];
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($permitidos[$mime])) {
            return ['ok' => FALSE, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.'];
        }

        if (($info[0] ?? 0) > 5000 || ($info[1] ?? 0) > 5000) {
            return ['ok' => FALSE, 'error' => 'La imagen excede las dimensiones permitidas.'];
        }

        $directorio = $this->_directorio_fotos_usuario($usuario_id);
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, TRUE) && !is_dir($directorio)) {
            return ['ok' => FALSE, 'error' => 'No se pudo preparar el almacenamiento de la foto.'];
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;

        $guardado = FALSE;

        // Si GD está disponible, optimizar y re-escalar
        if (function_exists('imagecreatefromstring')) {
            $contenido = @file_get_contents($tmp);
            $imagen = $contenido !== FALSE ? @imagecreatefromstring($contenido) : FALSE;
            if ($imagen !== FALSE) {
                $ancho = imagesx($imagen);
                if ($ancho > 1200) {
                    $alto = imagesy($imagen);
                    $nuevo_alto = (int)round(($alto * 1200) / $ancho);
                    $escalada = @imagescale($imagen, 1200, $nuevo_alto, IMG_BICUBIC_FIXED);
                    if ($escalada !== FALSE) {
                        @imagedestroy($imagen);
                        $imagen = $escalada;
                    }
                }

                switch ($mime) {
                    case 'image/jpeg':
                        $guardado = @imagejpeg($imagen, $ruta, 85);
                        break;
                    case 'image/png':
                        @imagealphablending($imagen, FALSE);
                        @imagesavealpha($imagen, TRUE);
                        $guardado = @imagepng($imagen, $ruta, 6);
                        break;
                    case 'image/webp':
                        $guardado = function_exists('imagewebp') ? @imagewebp($imagen, $ruta, 85) : FALSE;
                        break;
                }
                @imagedestroy($imagen);
            }
        }

        // Si GD no está disponible o falló, mover archivo validado nativamente
        if (!$guardado) {
            $guardado = @move_uploaded_file($tmp, $ruta) || @copy($tmp, $ruta);
        }

        if (!$guardado) {
            return ['ok' => FALSE, 'error' => 'No se pudo guardar la imagen procesada.'];
        }

        if (!empty($foto_actual) && $foto_actual !== $nombre) {
            $anterior = $directorio . DIRECTORY_SEPARATOR . basename($foto_actual);
            if (is_file($anterior)) {
                @unlink($anterior);
            }
        }

        return ['ok' => TRUE, 'archivo' => $nombre];
    }

    private function _directorio_fotos_usuario($usuario_id)
    {
        return $this->_storage_base()
            . DIRECTORY_SEPARATOR . 'usuarios'
            . DIRECTORY_SEPARATOR . $usuario_id;
    }

    private function _storage_base()
    {
        return rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'upload';
    }
}