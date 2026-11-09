<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth
 * Maneja todas las pantallas de autenticación.
 * NO extiende MY_Controller — acceso público sin sesión.
 */
class Auth extends CI_Controller
{
    /** Nombre del sistema, se pasa a todas las vistas auth */
    private $app_name = 'My System';
    private $theme_settings = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->library(['form_validation', 'session']);
        $this->load->library('user_agent');
        $this->load->library('Geoip_service');
        $this->load->helper(['url', 'form', 'network']);
        $this->load->model('Menus_model');
        $this->load->model('Configuracion_tema_model');
        $this->load->model('Usuarios_model');
        $this->load->model('Menu_model');
        $this->load->model('Configuracion_correos_model');
        $this->load->model('Configuracion_general_model');
        $this->load->model('User_2fa_model');
        $this->load->library('Totp_service');
        $this->load->library('Secret_crypto');
        $this->theme_settings = $this->Configuracion_tema_model->get_settings();
        $this->app_name = $this->theme_settings['project_name'];

        // Si ya hay sesión activa, redirigir al dashboard
        $metodo_actual = $this->router->fetch_method();
        if ($this->session->userdata('usuario') && !in_array($metodo_actual, ['logout', 'verificar_correo', 'confirmar_correo'], TRUE)) {
            redirect($this->_resolver_redirect_inicio($this->session->userdata('usuario')));
        }
    }

    // ─────────────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────────────

    /** GET /login — Formulario de inicio de sesión */
    public function index()
    {
        $gen_cfg = $this->Configuracion_general_model->get_settings();
        $error = $this->session->flashdata('error');

        if (empty($error) && $this->input->get('expired') === '1') {
            $error = 'Your session has expired due to inactivity. Please sign in again.';
        }

        // Mostrar aviso informativo si el sistema está en mantenimiento
        if (empty($error) && ($gen_cfg['mantenimiento_modo'] ?? '0') === '1') {
            $error = !empty($gen_cfg['mantenimiento_mensaje'])
                ? $gen_cfg['mantenimiento_mensaje']
                : 'Notice: The system is currently undergoing scheduled maintenance.';
        }

        $datos = $this->_auth_view_data([
            'error'    => $error,
            'success'  => $this->session->flashdata('success'),
            'public_registration_available' => $this->_registro_publico_habilitado(),
            'login_csrf' => $this->_crear_token_csrf_login(),
        ]);
        $this->load->view('auth/login', $datos);
    }

    public function registro()
    {
        $gen_cfg = $this->Configuracion_general_model->get_settings();
        if (($gen_cfg['mantenimiento_modo'] ?? '0') === '1') {
            $this->session->set_flashdata('error', 'Registration is temporarily unavailable due to scheduled maintenance.');
            return redirect('login');
        }

        if (!$this->_registro_publico_habilitado()) {
            show_404();
            return;
        }

        $this->load->view('auth/registro', $this->_auth_view_data([
            'errores' => $this->session->flashdata('errores') ?: [],
            'old' => $this->session->flashdata('old') ?: [],
            'error' => $this->session->flashdata('error'),
            'success' => $this->session->flashdata('success'),
            'registration_csrf' => $this->_crear_token_csrf_registro(),
        ]));
    }

    public function registrar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->_registro_publico_habilitado()) {
            show_404();
            return;
        }
        if (!$this->_validar_token_csrf_registro()) {
            $this->session->set_flashdata('error', 'The request has expired. Please try again.');
            return redirect('registro');
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        if (empty($mail_settings['ci_email_config']['enabled'])
            || empty($mail_settings['ci_email_config']['from_email'])
            || !$this->_tabla_tokens_disponible()) {
            $this->session->set_flashdata('error', 'Registration is temporarily unavailable. Please try again later.');
            return redirect('registro');
        }

        $data = [
            'usu_nombre' => trim((string)$this->input->post('usu_nombre', TRUE)),
            'usu_apellido' => trim((string)$this->input->post('usu_apellido', TRUE)),
            'usu_usuario' => trim((string)$this->input->post('usu_usuario', TRUE)),
            'usu_correo' => strtolower(trim((string)$this->input->post('usu_correo', TRUE))),
            'usu_clave' => (string)$this->input->post('usu_clave'),
            'clave_confirm' => (string)$this->input->post('clave_confirm'),
        ];
        $errores = $this->_validar_registro_publico($data);

        if (!empty($errores)) {
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('old', [
                'usu_nombre' => $data['usu_nombre'],
                'usu_apellido' => $data['usu_apellido'],
                'usu_usuario' => $data['usu_usuario'],
                'usu_correo' => $data['usu_correo'],
            ]);
            return redirect('registro');
        }

        $usuario_id = $this->Usuarios_model->generar_nuevo_id();
        $usuario = [
            'usu_id' => $usuario_id,
            'usu_nombre' => $data['usu_nombre'],
            'usu_apellido' => $data['usu_apellido'],
            'usu_usuario' => $data['usu_usuario'],
            'usu_correo' => $data['usu_correo'],
            'usu_tipoper' => (string)$this->theme_settings['public_registration_role_id'],
            'usu_celular' => '',
            'usu_fechan' => NULL,
            'usu_genero' => '',
            'usu_foto' => '',
            'usu_status' => '0',
            'usu_clave' => password_hash($data['usu_clave'], PASSWORD_DEFAULT),
        ];
        if (!$this->db->field_exists('usu_apellido', 'cab_usuarios')) {
            unset($usuario['usu_apellido']);
        }
        if ($this->db->field_exists('usu_sucursal_id', 'cab_usuarios')) {
            $usuario['usu_sucursal_id'] = NULL;
        }

        $client_ip = (string)$this->input->ip_address();
        $recent_registrations = $this->db
            ->where('uver_ip_address', $client_ip)
            ->where('uver_tipo', 'email_verification')
            ->where('uver_created_at >=', date('Y-m-d H:i:s', time() - 3600))
            ->count_all_results('cab_usuario_verificacion');
        if ($recent_registrations >= 5) {
            $this->session->set_flashdata('error', 'The temporary registration limit for this connection has been reached. Please try again later.');
            return redirect('registro');
        }

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        $created = $this->Usuarios_model->crear($usuario);
        $verification_created = $created !== FALSE && $this->db->insert('cab_usuario_verificacion', [
            'uver_usuario_id' => (string)$usuario_id,
            'uver_tipo' => 'email_verification',
            'uver_token_hash' => hash('sha256', $token),
            'uver_expira' => date('Y-m-d H:i:s', time() + 86400),
            'uver_ip_address' => $client_ip,
            'uver_send_count' => 1,
            'uver_window_started_at' => $now,
            'uver_last_sent_at' => $now,
            'uver_created_at' => $now,
            'uver_updated_at' => $now,
        ]);

        if (!$verification_created || !$this->db->trans_status() || !$this->db->trans_commit()) {
            $this->db->trans_rollback();
            log_message('error', 'No se pudo crear un registro público ni su token de verificación.');
            $this->session->set_flashdata('error', 'We could not complete your registration. Please try again.');
            return redirect('registro');
        }

        $email_sent = $this->_enviar_verificacion_correo($usuario, $token, $mail_settings);
        if (!$email_sent) {
            $this->session->set_flashdata('error', 'Your account is pending verification, but we could not send the email. Use the resend verification option.');
        } else {
            $this->session->set_flashdata('success', 'Registration received. Check your email and confirm the link to activate your account.');
        }

        return redirect('registro/resultado');
    }

    public function reenviar_verificacion()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            show_404();
            return;
        }
        if (!$this->_validar_token_csrf_registro()) {
            $this->session->set_flashdata('error', 'The request has expired. Please try again.');
            return redirect('registro/resultado');
        }

        $correo = strtolower(trim((string)$this->input->post('usu_correo', TRUE)));
        if (filter_var($correo, FILTER_VALIDATE_EMAIL) !== FALSE && $this->_tabla_tokens_disponible()) {
            $pending = $this->Usuarios_model->get_verificacion_pendiente_por_correo($correo);
            if (!empty($pending)) {
                $now = time();
                $window_start = strtotime((string)$pending['uver_window_started_at']);
                $last_sent = strtotime((string)$pending['uver_last_sent_at']);
                $send_count = (int)$pending['uver_send_count'];
                if ($window_start === FALSE || $now - $window_start >= 86400) {
                    $window_start = $now;
                    $send_count = 0;
                }

                if ($send_count < 5 && ($last_sent === FALSE || $now - $last_sent >= 300)) {
                    $mail_settings = $this->Configuracion_correos_model->get_settings();
                    if (!empty($mail_settings['ci_email_config']['enabled']) && !empty($mail_settings['ci_email_config']['from_email'])) {
                        $token = bin2hex(random_bytes(32));
                        $updated = $this->db
                            ->where('uver_usuario_id', (string)$pending['usu_id'])
                            ->where('uver_tipo', 'email_verification')
                            ->update('cab_usuario_verificacion', [
                                'uver_token_hash' => hash('sha256', $token),
                                'uver_expira' => date('Y-m-d H:i:s', $now + 86400),
                                'uver_send_count' => $send_count + 1,
                                'uver_window_started_at' => date('Y-m-d H:i:s', $window_start),
                                'uver_last_sent_at' => date('Y-m-d H:i:s', $now),
                                'uver_updated_at' => date('Y-m-d H:i:s', $now),
                            ]);
                        if ($updated) {
                            $this->_enviar_verificacion_correo($pending, $token, $mail_settings);
                        }
                    }
                }
            }
        }

        $this->session->set_flashdata('success', 'If a pending account exists for that email, you will receive verification instructions.');
        return redirect('registro/resultado');
    }

    public function verificar_correo($token = '')
    {
        $token = (string)$token;
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || !$this->_tabla_tokens_disponible()) {
            $token_status = 'invalid';
        } else {
            $token_status = $this->Usuarios_model->estado_token_verificacion(hash('sha256', $token));
        }

        $this->load->view('auth/verificar_correo', $this->_auth_view_data([
            'token' => $token,
            'token_status' => $token_status,
            'registration_csrf' => $this->_crear_token_csrf_registro(),
        ]));
    }

    public function confirmar_correo()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->_validar_token_csrf_registro()) {
            $this->session->set_flashdata('error', 'The request has expired or is invalid. Please reopen the link from your email.');
            return redirect('registro/resultado');
        }

        $token = (string)$this->input->post('token', TRUE);
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || !$this->_tabla_tokens_disponible()) {
            $result = 'invalid';
        } else {
            $result = $this->Usuarios_model->verificar_correo_por_token(hash('sha256', $token));
        }

        if ($result === 'verified') {
            $this->session->set_flashdata('success', 'Email verified. Your account is now active and you can sign in.');
        } elseif ($result === 'expired') {
            $this->session->set_flashdata('error', 'The link has expired. Please request a new one.');
        } elseif ($result === 'error') {
            $this->session->set_flashdata('error', 'We could not activate your account. Please try again.');
        } else {
            $this->session->set_flashdata('error', 'The verification link is invalid or has already been used.');
        }
        return redirect('registro/resultado');
    }

    public function resultado_registro()
    {
        $this->load->view('auth/registro_resultado', $this->_auth_view_data([
            'error' => $this->session->flashdata('error'),
            'success' => $this->session->flashdata('success'),
            'registration_csrf' => $this->_crear_token_csrf_registro(),
        ]));
    }

    /** POST /login/entrar — Procesar autenticación */
    public function autenticar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->_validar_token_csrf_login()) {
            $this->session->set_flashdata('error', 'The sign-in request has expired or is invalid. Please try again.');
            return redirect('login');
        }

        $usuario_input = $this->input->post('usuario', TRUE);
        $clave_input   = $this->input->post('clave',   TRUE);

        if (empty($usuario_input) || empty($clave_input)) {
            $this->session->set_flashdata('error', 'Username and password are required.');
            redirect('login');
        }

        // Buscar usuario activo en cab_usuarios
        $usuario = $this->db
            ->where('usu_usuario', $usuario_input)
            ->where('usu_status',  '1')
            ->limit(1)
            ->get('cab_usuarios')
            ->row_array();

        if (empty($usuario)) {
            $this->session->set_flashdata('error', 'User not found or inactive.');
            redirect('login');
        }

        // Verificar contraseña — password_hash y, solo para cuentas heredadas,
        // MD5 con migración inmediata a password_hash.
        $clave_valida = password_verify($clave_input, (string)$usuario['usu_clave']);

        if (!$clave_valida
            && strlen((string)$usuario['usu_clave']) === 32
            && hash_equals((string)$usuario['usu_clave'], md5($clave_input))) {
            $clave_valida = TRUE;
            $nuevo_hash = password_hash($clave_input, PASSWORD_DEFAULT);
            if ($nuevo_hash !== FALSE) {
                $this->db->where('usu_id', $usuario['usu_id'])->update('cab_usuarios', [
                    'usu_clave' => $nuevo_hash,
                ]);
                $this->_bitacora($usuario['usu_usuario'], 'LOGIN_REHASH', 'Hash de contraseña migrado de MD5 a password_hash');
            }
        }

        if ($clave_valida && password_needs_rehash((string)$usuario['usu_clave'], PASSWORD_DEFAULT)) {
            $nuevo_hash = password_hash($clave_input, PASSWORD_DEFAULT);
            if ($nuevo_hash !== FALSE) {
                $this->db->where('usu_id', $usuario['usu_id'])->update('cab_usuarios', [
                    'usu_clave' => $nuevo_hash,
                ]);
            }
        }

        if (!$clave_valida) {
            $this->session->set_flashdata('error', 'Incorrect password.');
            $this->_bitacora($usuario['usu_usuario'], 'LOGIN_FALLIDO', 'Contraseña incorrecta');
            redirect('login');
        }

        // Si el sistema está en mantenimiento, solo ingresa el Admin Master (rol 1000)
        $gen_cfg = $this->Configuracion_general_model->get_settings();
        if (($gen_cfg['mantenimiento_modo'] ?? '0') === '1' && !$this->Menu_model->rol_es_master($usuario['usu_tipoper'])) {
            $msg = !empty($gen_cfg['mantenimiento_mensaje']) ? $gen_cfg['mantenimiento_mensaje'] : 'The system is undergoing scheduled maintenance.';
            $this->session->set_flashdata('error', $msg);
            $this->_bitacora($usuario['usu_usuario'], 'LOGIN_BLOQUEADO_MANTENIMIENTO', 'Acceso bloqueado por mantenimiento activo');
            return redirect('login');
        }

        // Si el usuario tiene habilitada la Autenticación de Dos Pasos (2FA)
        $email_2fa    = ((int)($usuario['usu_2fa'] ?? 0) === 1);
        $totp_enabled = ((int)($usuario['usu_2fa_totp_enabled'] ?? 0) === 1);

        if ($email_2fa || $totp_enabled) {
            if ($this->_usuario_esta_bloqueado_2fa($usuario)) {
                $minutos = $this->_minutos_restantes_bloqueo_2fa($usuario);
                $min_texto = $minutos <= 1 ? '1 minuto' : "{$minutos} minutos";
                $this->session->set_flashdata('error', "Tu cuenta está bloqueada temporalmente por demasiados intentos fallidos de 2FA. Intenta nuevamente en {$min_texto}.");
                $this->_bitacora($usuario['usu_usuario'], 'LOGIN_2FA_BLOQUEADO', "Acceso denegado: cuenta bloqueada temporalmente por 2FA ({$min_texto} restantes)");
                return redirect('login');
            }

            $metodo_2fa = $totp_enabled ? 'totp' : 'email';

            $usuario_data_2fa = [
                'usu_id'          => $usuario['usu_id'],
                'usu_nombre'      => $usuario['usu_nombre'],
                'usu_apellido'    => $usuario['usu_apellido'] ?? '',
                'usu_usuario'     => $usuario['usu_usuario'],
                'usu_correo'      => $usuario['usu_correo'],
                'usu_tipoper'     => $usuario['usu_tipoper'],
                'usu_sucursal_id' => isset($usuario['usu_sucursal_id']) ? (int)$usuario['usu_sucursal_id'] : 0,
                'usu_perfil'      => $usuario['usu_perfil'],
                'usu_foto'        => $this->_resolver_foto_usuario_url($usuario['usu_id'], $usuario['usu_foto'] ?? ''),
                'usu_pais'        => $usuario['usu_pais'],
                'usu_2fa'         => $email_2fa ? 1 : 0,
                'usu_2fa_totp_enabled' => $totp_enabled ? 1 : 0,
            ];

            $this->session->set_userdata('usuario_2fa', $usuario_data_2fa);
            $this->session->set_userdata('metodo_2fa', $metodo_2fa);

            if ($metodo_2fa === 'email') {
                $codigo_2fa = (string)random_int(100000, 999999);
                $this->session->set_userdata('codigo_2fa', $codigo_2fa);
                $this->session->set_userdata('codigo_2fa_expira', time() + 600);
                $this->_enviar_correo_2fa($usuario, $codigo_2fa);
                $this->_bitacora($usuario['usu_usuario'], 'LOGIN_2FA_SOLICITADO', 'Código de verificación 2FA enviado a ' . ($usuario['usu_correo'] ?? 'correo'));
            } else {
                $this->_bitacora($usuario['usu_usuario'], 'LOGIN_2FA_TOTP_SOLICITADO', 'Verificación con aplicación autenticadora solicitada');
            }

            return redirect('dos-factores');
        }

        // Regenerar ID de sesión al autenticar para evitar session fixation
        $this->session->sess_regenerate(FALSE);
        $now = time();

        // Guardar sesión con los campos de cab_usuarios
        $this->session->set_userdata('usuario', [
            'usu_id'      => $usuario['usu_id'],
            'usu_nombre'  => $usuario['usu_nombre'],
            'usu_apellido' => $usuario['usu_apellido'] ?? '',
            'usu_usuario' => $usuario['usu_usuario'],
            'usu_correo'  => $usuario['usu_correo'],
            'usu_tipoper' => $usuario['usu_tipoper'],
            'usu_sucursal_id' => isset($usuario['usu_sucursal_id']) ? (int)$usuario['usu_sucursal_id'] : 0,
            'usu_perfil'  => $usuario['usu_perfil'],
            'usu_foto'    => $this->_resolver_foto_usuario_url($usuario['usu_id'], $usuario['usu_foto'] ?? ''),
            'usu_pais'    => $usuario['usu_pais'],
        ]);
        $this->_guardar_estructura_sesion($usuario['usu_tipoper']);
        $this->session->set_userdata('last_activity', $now);
        $this->session->set_userdata('login_time', $now);

        $this->_bitacora($usuario['usu_usuario'], 'LOGIN', 'Inicio de sesión exitoso');
        $this->session->set_userdata('login_meta', $this->_bitacora_ip($usuario['usu_usuario']));
        redirect($this->_resolver_redirect_inicio($usuario));
    }

    /** GET /logout — Cerrar sesión */
    public function logout()
    {
        $usuario = $this->session->userdata('usuario');
        if (!empty($usuario)) {
            $this->_bitacora($usuario['usu_usuario'], 'LOGOUT', 'Cierre de sesión');
        }
        $this->session->sess_destroy();
        redirect('login');
    }

    // ─────────────────────────────────────────────────────────
    // OAUTH: GOOGLE & APPLE
    // ─────────────────────────────────────────────────────────

    /** GET /auth/oauth-google — Redirigir a Google OAuth */
    public function oauth_google()
    {
        if (empty($this->theme_settings['google_auth_enabled'])) {
            $this->session->set_flashdata('error', 'Google sign-in is not enabled.');
            return redirect('login');
        }

        $client_id = trim((string)($this->theme_settings['google_client_id'] ?? ''));
        if ($client_id === '') {
            $this->session->set_flashdata('error', 'Google sign-in requires a Client ID to be configured in the Theme settings.');
            return redirect('login');
        }

        $this->load->library('Oauth_service');
        $state = bin2hex(random_bytes(16));
        $this->session->set_userdata('oauth_state_google', $state);

        $redirect_uri = base_url('auth/oauth-google-callback');
        $auth_url = $this->oauth_service->get_google_auth_url($client_id, $redirect_uri, $state);
        redirect($auth_url);
    }

    /** GET /auth/oauth-google-callback — Recibir respuesta de Google */
    public function oauth_google_callback()
    {
        if (empty($this->theme_settings['google_auth_enabled'])) {
            $this->session->set_flashdata('error', 'Google sign-in is not enabled.');
            return redirect('login');
        }

        $code  = $this->input->get('code', TRUE);
        $state = $this->input->get('state', TRUE);
        $error = $this->input->get('error', TRUE);

        if (!empty($error)) {
            $this->session->set_flashdata('error', 'Authorization canceled or denied by Google: ' . htmlspecialchars($error));
            return redirect('login');
        }

        $saved_state = $this->session->userdata('oauth_state_google');
        $this->session->unset_userdata('oauth_state_google');

        if (empty($code) || empty($state) || $state !== $saved_state) {
            $this->session->set_flashdata('error', 'Google security verification failed (CSRF state mismatch). Please try again.');
            return redirect('login');
        }

        $client_id     = trim((string)($this->theme_settings['google_client_id'] ?? ''));
        $client_secret = trim((string)($this->theme_settings['google_client_secret'] ?? ''));
        $redirect_uri  = base_url('auth/oauth-google-callback');

        $this->load->library('Oauth_service');
        $res = $this->oauth_service->handle_google_callback($code, $client_id, $client_secret, $redirect_uri);

        if (empty($res['ok'])) {
            $this->session->set_flashdata('error', 'Error authenticating with Google: ' . ($res['error'] ?? 'unknown'));
            return redirect('login');
        }

        $this->_procesar_login_oauth('Google', $res);
    }

    /** GET /auth/oauth-apple — Redirigir a Apple OAuth */
    public function oauth_apple()
    {
        if (empty($this->theme_settings['apple_auth_enabled'])) {
            $this->session->set_flashdata('error', 'Apple sign-in is not enabled.');
            return redirect('login');
        }

        $client_id = trim((string)($this->theme_settings['apple_client_id'] ?? ''));
        if ($client_id === '') {
            $this->session->set_flashdata('error', 'Apple sign-in requires a Services ID to be configured in the Theme settings.');
            return redirect('login');
        }

        $this->load->library('Oauth_service');
        $state = bin2hex(random_bytes(16));
        $this->session->set_userdata('oauth_state_apple', $state);

        $redirect_uri = base_url('auth/oauth-apple-callback');
        $auth_url = $this->oauth_service->get_apple_auth_url($client_id, $redirect_uri, $state);
        redirect($auth_url);
    }

    /** POST /auth/oauth-apple-callback — Recibir respuesta de Apple (form_post) */
    public function oauth_apple_callback()
    {
        if (empty($this->theme_settings['apple_auth_enabled'])) {
            $this->session->set_flashdata('error', 'Apple sign-in is not enabled.');
            return redirect('login');
        }

        $post_data = $this->input->post(NULL, TRUE) ?: [];
        $state     = $post_data['state'] ?? $this->input->get('state', TRUE);
        $error     = $post_data['error'] ?? $this->input->get('error', TRUE);

        if (!empty($error)) {
            $this->session->set_flashdata('error', 'Authorization canceled or denied by Apple: ' . htmlspecialchars($error));
            return redirect('login');
        }

        $saved_state = $this->session->userdata('oauth_state_apple');
        $this->session->unset_userdata('oauth_state_apple');

        if (empty($state) || $state !== $saved_state) {
            $this->session->set_flashdata('error', 'Apple security verification failed. Please try again.');
            return redirect('login');
        }

        $client_id = trim((string)($this->theme_settings['apple_client_id'] ?? ''));
        $this->load->library('Oauth_service');
        $res = $this->oauth_service->handle_apple_callback($post_data, $client_id);

        if (empty($res['ok'])) {
            $this->session->set_flashdata('error', 'Error authenticating with Apple: ' . ($res['error'] ?? 'unknown'));
            return redirect('login');
        }

        $this->_procesar_login_oauth('Apple', $res);
    }

    /**
     * Procesa el login o registro automático para usuarios autenticados vía OAuth
     */
    private function _procesar_login_oauth($proveedor, array $oauth_user)
    {
        $email = strtolower(trim((string)($oauth_user['email'] ?? '')));
        if (empty($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
            $this->session->set_flashdata('error', "Could not obtain a valid email address from {$proveedor}.");
            return redirect('login');
        }

        // 1. Buscar usuario existente por email
        $usuario = $this->db
            ->where('usu_correo', $email)
            ->limit(1)
            ->get('cab_usuarios')
            ->row_array();

        if (!empty($usuario)) {
            if ((string)$usuario['usu_status'] !== '1') {
                $this->session->set_flashdata('error', 'Your account exists but is inactive or suspended. Please contact the administrator.');
                return redirect('login');
            }

            // OAuth no debe saltarse la autenticación de dos pasos.
            if ((int)($usuario['usu_2fa'] ?? 0) === 1
                || (int)($usuario['usu_2fa_totp_enabled'] ?? 0) === 1) {
                $this->session->set_flashdata('error', 'Your account has two-step verification enabled. Please sign in with your username and password.');
                return redirect('login');
            }

            $session_data = [
                'usu_id'          => $usuario['usu_id'],
                'usu_nombre'      => $usuario['usu_nombre'],
                'usu_apellido'    => $usuario['usu_apellido'] ?? '',
                'usu_usuario'     => $usuario['usu_usuario'],
                'usu_correo'      => $usuario['usu_correo'],
                'usu_tipoper'     => $usuario['usu_tipoper'],
                'usu_sucursal_id' => isset($usuario['usu_sucursal_id']) ? (int)$usuario['usu_sucursal_id'] : 0,
                'usu_perfil'      => $usuario['usu_perfil'] ?? '',
                'usu_foto'        => $this->_resolver_foto_usuario_url($usuario['usu_id'], $usuario['usu_foto'] ?? ''),
                'usu_pais'        => $usuario['usu_pais'] ?? '',
            ];

            $this->session->sess_regenerate(FALSE);
            $now = time();
            $this->session->set_userdata('usuario', $session_data);
            $this->_guardar_estructura_sesion($session_data['usu_tipoper'] ?? '');
            $this->session->set_userdata('last_activity', $now);
            $this->session->set_userdata('login_time', $now);
            $this->_bitacora($usuario['usu_usuario'], 'LOGIN_' . strtoupper($proveedor), "Inicio de sesión mediante {$proveedor} ({$email})");
            $this->session->set_userdata('login_meta', $this->_bitacora_ip($usuario['usu_usuario']));

            return redirect($this->_resolver_redirect_inicio($usuario));
        }

        // 2. Usuario NO existe: verificar si el registro público está habilitado
        if ($this->_registro_publico_habilitado()) {
            $rol_id = (string)($this->theme_settings['public_registration_role_id'] ?? '');
            if (!$this->Usuarios_model->rol_registro_publico_valido($rol_id)) {
                $this->session->set_flashdata('error', 'Automatic registration is not available because no valid role has been configured.');
                return redirect('login');
            }

            $base_user = preg_replace('/[^a-zA-Z0-9_\.]/', '', explode('@', $email)[0]);
            if (empty($base_user)) {
                $base_user = 'user_' . substr(md5(uniqid()), 0, 6);
            }
            $username = $base_user;
            $counter = 1;
            while ($this->db->where('usu_usuario', $username)->count_all_results('cab_usuarios') > 0) {
                $username = $base_user . $counter;
                $counter++;
            }

            $nuevo_id = $this->Usuarios_model->generar_nuevo_id();
            $nuevo_usuario = [
                'usu_id'         => $nuevo_id,
                'usu_nombre'     => !empty($oauth_user['nombre']) ? $oauth_user['nombre'] : $username,
                'usu_apellido'   => !empty($oauth_user['apellido']) ? $oauth_user['apellido'] : '',
                'usu_usuario'    => $username,
                'usu_correo'     => $email,
                'usu_tipoper'    => $rol_id,
                'usu_celular'    => '',
                'usu_fechan'     => NULL,
                'usu_genero'     => '',
                'usu_foto'       => '',
                'usu_status'     => '1',
                'usu_clave'      => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'usu_created_at' => date('Y-m-d H:i:s'),
                'usu_updated_at' => date('Y-m-d H:i:s'),
            ];

            if (!$this->db->field_exists('usu_apellido', 'cab_usuarios')) {
                unset($nuevo_usuario['usu_apellido']);
            }
            if ($this->db->field_exists('usu_sucursal_id', 'cab_usuarios')) {
                $nuevo_usuario['usu_sucursal_id'] = NULL;
            }

            $guardado = $this->Usuarios_model->crear($nuevo_usuario);
            if (!$guardado) {
                $this->session->set_flashdata('error', 'Could not create the account automatically. Please try again later.');
                return redirect('login');
            }

            $session_data = [
                'usu_id'          => $nuevo_usuario['usu_id'],
                'usu_nombre'      => $nuevo_usuario['usu_nombre'],
                'usu_apellido'    => $nuevo_usuario['usu_apellido'] ?? '',
                'usu_usuario'     => $nuevo_usuario['usu_usuario'],
                'usu_correo'      => $nuevo_usuario['usu_correo'],
                'usu_tipoper'     => $nuevo_usuario['usu_tipoper'],
                'usu_sucursal_id' => 0,
                'usu_perfil'      => '',
                'usu_foto'        => '',
                'usu_pais'        => '',
            ];

            $this->session->sess_regenerate(FALSE);
            $now = time();
            $this->session->set_userdata('usuario', $session_data);
            $this->_guardar_estructura_sesion($session_data['usu_tipoper'] ?? '');
            $this->session->set_userdata('last_activity', $now);
            $this->session->set_userdata('login_time', $now);
            $this->_bitacora($username, 'REGISTRO_' . strtoupper($proveedor), "Registro automático vía {$proveedor} ({$email})");
            $this->_bitacora($username, 'LOGIN_' . strtoupper($proveedor), "Primer inicio de sesión vía {$proveedor}");
            $this->session->set_userdata('login_meta', $this->_bitacora_ip($username));

            return redirect($this->_resolver_redirect_inicio($nuevo_usuario));
        }

        $this->session->set_flashdata('error', "The {$proveedor} account ({$email}) is not registered in the system. Public registration is not enabled; please contact the administrator.");
        return redirect('login');
    }

    // ─────────────────────────────────────────────────────────
    // RECUPERAR CONTRASEÑA
    // ─────────────────────────────────────────────────────────

    /** GET /recuperar-clave — Formulario de recuperación */
    public function recuperar()
    {
        $datos = $this->_auth_view_data([
            'error'    => $this->session->flashdata('error'),
            'enviado'  => $this->session->flashdata('enviado'),
            'correo_enviado' => $this->session->flashdata('correo_enviado'),
            'reset_csrf' => $this->_crear_token_csrf_recuperacion(),
        ]);
        $this->load->view('auth/recuperar', $datos);
    }

    /** POST /recuperar-clave/enviar — Procesar recuperación */
    public function enviar_recuperacion()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->_validar_token_csrf_recuperacion()) {
            $this->session->set_flashdata('error', 'The request has expired. Please try again.');
            return redirect('recuperar-clave');
        }

        $correo = strtolower(trim((string)$this->input->post('correo', TRUE)));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error', 'Enter a valid email address.');
            return redirect('recuperar-clave');
        }

        if (!$this->_tabla_tokens_disponible()) {
            log_message('error', 'No se pudo procesar una recuperación de clave: falta la tabla cab_usuario_verificacion.');
        } else {
            $usuario = $this->Usuarios_model->get_usuario_activo_por_correo($correo);
            if (!empty($usuario)) {
                $mail_settings = $this->Configuracion_correos_model->get_settings();
                if (empty($mail_settings['ci_email_config']['enabled'])
                    || empty($mail_settings['ci_email_config']['from_email'])) {
                    log_message('error', 'No se pudo enviar la recuperación de clave: el correo saliente no está configurado.');
                } else {
                    $token = bin2hex(random_bytes(32));
                    $created = $this->Usuarios_model->crear_token_recuperacion(
                        (string)$usuario['usu_id'],
                        hash('sha256', $token),
                        date('Y-m-d H:i:s', time() + 3600),
                        (string)$this->input->ip_address()
                    );

                    if ($created === 'saved') {
                        if ($this->_enviar_correo_recuperacion($usuario, $token, $mail_settings)) {
                            $this->_bitacora($usuario['usu_usuario'], 'RECUPERAR_CLAVE', 'Se envió un enlace de recuperación');
                        }
                    } elseif ($created === 'error') {
                        log_message('error', 'No se pudo guardar el token de recuperación para el usuario ' . (string)$usuario['usu_id'] . '.');
                    }
                }
            }
        }

        $this->session->set_flashdata('enviado', TRUE);
        $this->session->set_flashdata('correo_enviado', $correo);
        return redirect('recuperar-clave');
    }

    // ─────────────────────────────────────────────────────────
    // NUEVA CONTRASEÑA
    // ─────────────────────────────────────────────────────────

    /** GET /nueva-clave/:token — Formulario para establecer nueva contraseña */
    public function nueva_clave($token = NULL)
    {
        $token = (string)$token;
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || !$this->_tabla_tokens_disponible()) {
            $token_status = 'invalid';
        } else {
            $token_status = $this->Usuarios_model->estado_token_recuperacion(hash('sha256', $token));
        }

        $datos = $this->_auth_view_data([
            'token'    => $token,
            'token_status' => $token_status,
            'reset_csrf' => $this->_crear_token_csrf_recuperacion(),
            'error'    => $this->session->flashdata('error'),
        ]);
        $this->load->view('auth/nueva_clave', $datos);
    }

    /** POST /nueva-clave/guardar — Guardar nueva contraseña */
    public function guardar_clave()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST' || !$this->_validar_token_csrf_recuperacion()) {
            $this->session->set_flashdata('error', 'The request has expired. Please reopen the recovery link.');
            return redirect('recuperar-clave');
        }

        $token = (string)$this->input->post('token', TRUE);
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || !$this->_tabla_tokens_disponible()) {
            $this->session->set_flashdata('error', 'The recovery link is invalid or has expired.');
            return redirect('recuperar-clave');
        }

        $clave = (string)$this->input->post('clave');
        $clave_confirm = (string)$this->input->post('clave_confirm');
        if (strlen($clave) < 8 || strlen($clave) > 72) {
            $this->session->set_flashdata('error', 'The password must be between 8 and 72 characters.');
            return redirect('nueva-clave/' . rawurlencode($token));
        }

        if ($clave !== $clave_confirm) {
            $this->session->set_flashdata('error', 'The passwords do not match.');
            return redirect('nueva-clave/' . rawurlencode($token));
        }

        $result = $this->Usuarios_model->restablecer_clave_por_token(
            hash('sha256', $token),
            password_hash($clave, PASSWORD_DEFAULT)
        );
        if ($result === 'expired') {
            $this->session->set_flashdata('error', 'The recovery link has expired. Please request a new one.');
            return redirect('recuperar-clave');
        }
        if ($result !== 'updated') {
            if ($result === 'error') {
                log_message('error', 'No se pudo actualizar la clave desde el flujo de recuperación.');
                $this->session->set_flashdata('error', 'We could not update your password. Please try again.');
            } else {
                $this->session->set_flashdata('error', 'The recovery link is invalid or has already been used.');
            }
            return redirect('recuperar-clave');
        }

        $this->session->set_flashdata('success', 'Your password has been updated successfully.');
        return redirect('login');
    }

    // ─────────────────────────────────────────────────────────
    // DOS FACTORES
    // ─────────────────────────────────────────────────────────

    /** GET /dos-factores — Pantalla de verificación en dos pasos */
    public function dos_factores()
    {
        // El usuario debe tener una sesión temporal (pendiente de 2FA)
        $usuario_2fa = $this->session->userdata('usuario_2fa');
        if (empty($usuario_2fa)) {
            redirect('login');
        }

        // Consultar estado actualizado en base de datos
        $usuario_db = $this->db->get_where('cab_usuarios', ['usu_id' => $usuario_2fa['usu_id']])->row_array();
        if (empty($usuario_db) || (int)$usuario_db['usu_status'] !== 1) {
            $this->session->unset_userdata('usuario_2fa');
            $this->session->unset_userdata('codigo_2fa');
            $this->session->unset_userdata('codigo_2fa_expira');
            $this->session->set_flashdata('error', 'User is unavailable or inactive.');
            return redirect('login');
        }

        if ($this->_usuario_esta_bloqueado_2fa($usuario_db)) {
            $minutos = $this->_minutos_restantes_bloqueo_2fa($usuario_db);
            $min_texto = $minutos <= 1 ? '1 minute' : "{$minutos} minutes";
            $this->session->unset_userdata('usuario_2fa');
            $this->session->unset_userdata('codigo_2fa');
            $this->session->unset_userdata('codigo_2fa_expira');
            $this->session->set_flashdata('error', "Your account is temporarily locked due to too many failed 2FA attempts. Try again in {$min_texto}.");
            return redirect('login');
        }

        // Mostrar parcialmente el correo: j***@gmail.com
        $correo = $usuario_2fa['usu_correo'] ?? '';
        $contacto_parcial = $this->_ocultar_correo($correo);

        $metodo_2fa = (string)($this->session->userdata('metodo_2fa') ?: 'email');
        $email_2fa  = ((int)($usuario_db['usu_2fa'] ?? 0) === 1);
        $totp_enabled = ((int)($usuario_db['usu_2fa_totp_enabled'] ?? 0) === 1);

        if (!$totp_enabled && !$email_2fa) {
            $this->session->unset_userdata('usuario_2fa');
            redirect('login');
        }

        if ($metodo_2fa === 'totp' && !$totp_enabled) {
            $metodo_2fa = $email_2fa ? 'email' : 'totp';
            $this->session->set_userdata('metodo_2fa', $metodo_2fa);
        }

        $datos = $this->_auth_view_data([
            'contacto_parcial' => $contacto_parcial,
            'metodo_2fa'       => $metodo_2fa,
            'email_2fa'        => $email_2fa,
            'totp_enabled'     => $totp_enabled,
            'error'            => $this->session->flashdata('error'),
            'success'          => $this->session->flashdata('success'),
        ]);
        $this->load->view('auth/dos_factores', $datos);
    }

    /** POST /dos-factores/verificar — Verificar código de 6 dígitos */
    public function verificar_factor()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return redirect('login');
        }

        $usuario_2fa = $this->session->userdata('usuario_2fa');
        if (empty($usuario_2fa)) {
            redirect('login');
        }

        $usuario_db = $this->db->get_where('cab_usuarios', ['usu_id' => $usuario_2fa['usu_id']])->row_array();
        if (empty($usuario_db) || (int)$usuario_db['usu_status'] !== 1) {
            $this->session->unset_userdata('usuario_2fa');
            $this->session->unset_userdata('codigo_2fa');
            $this->session->unset_userdata('codigo_2fa_expira');
            $this->session->set_flashdata('error', 'User is unavailable or inactive.');
            return redirect('login');
        }

        // Verificar si la cuenta ya está bloqueada por intentos previos
        if ($this->_usuario_esta_bloqueado_2fa($usuario_db)) {
            $minutos = $this->_minutos_restantes_bloqueo_2fa($usuario_db);
            $min_texto = $minutos <= 1 ? '1 minute' : "{$minutos} minutes";
            $this->session->unset_userdata('usuario_2fa');
            $this->session->unset_userdata('codigo_2fa');
            $this->session->unset_userdata('codigo_2fa_expira');
            $this->session->set_flashdata('error', "You have exceeded the verification attempt limit. Your account is temporarily locked for {$min_texto}.");
            return redirect('login');
        }

        $metodo_2fa = (string)($this->session->userdata('metodo_2fa') ?: 'email');

        if ($metodo_2fa === 'totp') {
            return $this->_verificar_totp($usuario_db, $usuario_2fa);
        }

        // Concatenar los 6 campos del formulario
        $codigo = '';
        for ($i = 1; $i <= 6; $i++) {
            $codigo .= trim((string)$this->input->post('code_' . $i, TRUE));
        }

        $codigo_guardado = (string)$this->session->userdata('codigo_2fa');
        $expira          = (int)$this->session->userdata('codigo_2fa_expira');

        if ($expira > 0 && time() > $expira) {
            $this->session->set_flashdata('error', 'The verification code has expired. Please request a new one by clicking Resend.');
            return redirect('dos-factores');
        }

        // Si el código no coincide o está vacío
        if ($codigo === '' || $codigo !== $codigo_guardado) {
            $intentos = (int)($usuario_db['usu_2fa_intentos'] ?? 0) + 1;
            $max_intentos = 5;
            $minutos_bloqueo = 15;

            if ($intentos >= $max_intentos) {
                // Bloqueo temporal por fuerza bruta
                $bloqueado_hasta = date('Y-m-d H:i:s', strtotime("+{$minutos_bloqueo} minutes"));
                $this->db->where('usu_id', $usuario_db['usu_id'])->update('cab_usuarios', [
                    'usu_2fa_intentos'        => $intentos,
                    'usu_2fa_bloqueado_hasta' => $bloqueado_hasta,
                ]);

                $ip_actual = $this->input->ip_address() ?: '127.0.0.1';
                $this->_bitacora(
                    $usuario_db['usu_usuario'],
                    'LOGIN_2FA_BLOQUEO_FUERZA_BRUTA',
                    "Bloqueo temporal de {$minutos_bloqueo} minutos tras {$intentos} intentos fallidos de 2FA desde la IP {$ip_actual}"
                );

                // Enviar correo de alerta de seguridad al usuario
                $this->_enviar_alerta_bloqueo_2fa($usuario_db, $minutos_bloqueo, $ip_actual);

                // Destruir sesión temporal de 2FA
                $this->session->unset_userdata('usuario_2fa');
                $this->session->unset_userdata('codigo_2fa');
                $this->session->unset_userdata('codigo_2fa_expira');

                $this->session->set_flashdata('error', "You have exceeded the limit of {$max_intentos} failed attempts. Your account has been temporarily locked for {$minutos_bloqueo} minutes for security reasons.");
                return redirect('login');
            }

            // Aún tiene intentos disponibles
            $this->db->where('usu_id', $usuario_db['usu_id'])->update('cab_usuarios', [
                'usu_2fa_intentos' => $intentos,
            ]);

            $restantes = $max_intentos - $intentos;
            $texto_restantes = $restantes === 1 ? '1 attempt remaining' : "{$restantes} attempts remaining";

            $this->_bitacora(
                $usuario_db['usu_usuario'],
                'LOGIN_2FA_FALLIDO',
                "Código 2FA incorrecto (Intento {$intentos}/{$max_intentos})"
            );

            $this->session->set_flashdata('error', "Incorrect code. You have {$texto_restantes} before a temporary security lock.");
            return redirect('dos-factores');
        }

        // Código correcto → Resetear intentos y limpiar bloqueo en base de datos
        $this->db->where('usu_id', $usuario_db['usu_id'])->update('cab_usuarios', [
            'usu_2fa_intentos'        => 0,
            'usu_2fa_bloqueado_hasta' => NULL,
        ]);

        // Completar sesión
        $this->session->sess_regenerate(FALSE);
        $now = time();
        $this->session->set_userdata('usuario', $usuario_2fa);
        $this->_guardar_estructura_sesion($usuario_2fa['usu_tipoper'] ?? '');
        $this->session->set_userdata('last_activity', $now);
        $this->session->set_userdata('login_time', $now);
        $this->session->unset_userdata('usuario_2fa');
        $this->session->unset_userdata('codigo_2fa');
        $this->session->unset_userdata('codigo_2fa_expira');

        $this->_bitacora($usuario_2fa['usu_usuario'], 'LOGIN_2FA', 'Verificación en dos pasos exitosa');
        $this->session->set_userdata('login_meta', $this->_bitacora_ip($usuario_2fa['usu_usuario']));
        redirect($this->_resolver_redirect_inicio($usuario_2fa));
    }

        /**
     * Verifica un código TOTP o un código de recuperación.
     */
    private function _verificar_totp(array $usuario_db, array $usuario_2fa)
    {
        $codigo = trim((string)$this->input->post('codigo_totp', TRUE));
        $secret = $this->secret_crypto->decrypt((string)($usuario_db['usu_2fa_totp_secret'] ?? ''));
        $last_step = isset($usuario_db['usu_2fa_totp_last_step']) ? (int)$usuario_db['usu_2fa_totp_last_step'] : NULL;
        $step = FALSE;
        $recovery = FALSE;

        if (preg_match('/^\d{6}$/', $codigo) === 1 && $secret !== '') {
            $step = $this->totp_service->verify($secret, $codigo, 1, $last_step);
        } else {
            $normalized = $this->totp_service->normalize_recovery_code($codigo);
            if (strlen($normalized) >= 8) {
                $recovery = $this->User_2fa_model->consume_recovery_code((string)$usuario_db['usu_id'], $normalized);
            }
        }

        if ($step === FALSE && !$recovery) {
            $this->_bitacora($usuario_db['usu_usuario'], 'LOGIN_2FA_FALLIDO', 'Código TOTP o de recuperación incorrecto');
            $intentos = (int)($usuario_db['usu_2fa_intentos'] ?? 0) + 1;
            $max_intentos = 5;
            $minutos_bloqueo = 15;

            if ($intentos >= $max_intentos) {
                $this->db->where('usu_id', $usuario_db['usu_id'])->update('cab_usuarios', [
                    'usu_2fa_intentos'        => $intentos,
                    'usu_2fa_bloqueado_hasta' => date('Y-m-d H:i:s', strtotime("+{$minutos_bloqueo} minutes")),
                ]);
                $this->_bitacora($usuario_db['usu_usuario'], 'LOGIN_2FA_BLOQUEO_FUERZA_BRUTA', "Bloqueo temporal de {$minutos_bloqueo} minutos tras {$intentos} intentos fallidos de 2FA");
                $this->_enviar_alerta_bloqueo_2fa($usuario_db, $minutos_bloqueo, $this->input->ip_address() ?: '127.0.0.1');
                $this->session->unset_userdata(['usuario_2fa', 'metodo_2fa', 'codigo_2fa', 'codigo_2fa_expira']);
                $this->session->set_flashdata('error', "Has superado el límite de {$max_intentos} intentos fallidos. Tu cuenta quedó bloqueada temporalmente por {$minutos_bloqueo} minutos.");
                return redirect('login');
            }

            $this->db->where('usu_id', $usuario_db['usu_id'])->update('cab_usuarios', ['usu_2fa_intentos' => $intentos]);
            $restantes = $max_intentos - $intentos;
            $this->session->set_flashdata('error', 'Código incorrecto. Te quedan ' . $restantes . ($restantes === 1 ? ' intento.' : ' intentos.'));
            return redirect('dos-factores');
        }

        if ($step !== FALSE) {
            $this->db->query(
                'UPDATE cab_usuarios
                    SET usu_2fa_totp_last_step = ?,
                        usu_2fa_intentos = 0,
                        usu_2fa_bloqueado_hasta = NULL
                  WHERE usu_id = ?
                    AND (usu_2fa_totp_last_step IS NULL OR usu_2fa_totp_last_step < ?)',
                [(int)$step, (string)$usuario_db['usu_id'], (int)$step]
            );

            if ($this->db->affected_rows() !== 1) {
                $this->session->set_flashdata('error', 'El código ya fue utilizado. Espera el siguiente código de tu aplicación.');
                return redirect('dos-factores');
            }
        } else {
            $this->db->where('usu_id', $usuario_db['usu_id'])->update('cab_usuarios', ['usu_2fa_intentos' => 0, 'usu_2fa_bloqueado_hasta' => NULL]);
        }

        $this->session->sess_regenerate(FALSE);
        $now = time();
        $this->session->set_userdata('usuario', $usuario_2fa);
        $this->_guardar_estructura_sesion($usuario_2fa['usu_tipoper'] ?? '');
        $this->session->set_userdata('last_activity', $now);
        $this->session->set_userdata('login_time', $now);
        $this->session->unset_userdata(['usuario_2fa', 'metodo_2fa', 'codigo_2fa', 'codigo_2fa_expira']);
        $this->_bitacora($usuario_2fa['usu_usuario'], $recovery ? 'LOGIN_2FA_RECOVERY' : 'LOGIN_2FA_TOTP', $recovery ? 'Verificación 2FA con código de recuperación' : 'Verificación con aplicación autenticadora exitosa');
        $this->session->set_userdata('login_meta', $this->_bitacora_ip($usuario_2fa['usu_usuario']));
        return redirect($this->_resolver_redirect_inicio($usuario_2fa));
    }

    /**
     * GET /dos-factores/usar-totp
     */
    public function usar_totp()
    {
        $usuario_2fa = $this->session->userdata('usuario_2fa');
        if (empty($usuario_2fa)) {
            redirect('login');
        }

        $usuario_db = $this->db->get_where('cab_usuarios', ['usu_id' => $usuario_2fa['usu_id']])->row_array();
        if (empty($usuario_db) || (int)($usuario_db['usu_2fa_totp_enabled'] ?? 0) !== 1) {
            $this->session->set_flashdata('error', 'La aplicación autenticadora no está habilitada para esta cuenta.');
            return redirect('dos-factores');
        }

        $this->session->set_userdata('metodo_2fa', 'totp');
        $this->session->unset_userdata(['codigo_2fa', 'codigo_2fa_expira']);
        redirect('dos-factores');
    }

    /** GET /dos-factores/reenviar - Reenviar código de verificación */
    public function reenviar_factor()
    {
        $usuario_2fa = $this->session->userdata('usuario_2fa');
        if (empty($usuario_2fa)) {
            redirect('login');
        }

        $usuario_db = $this->db->get_where('cab_usuarios', ['usu_id' => $usuario_2fa['usu_id']])->row_array();
        if (empty($usuario_db) || (int)$usuario_db['usu_status'] !== 1) {
            $this->session->unset_userdata('usuario_2fa');
            $this->session->unset_userdata('codigo_2fa');
            $this->session->unset_userdata('codigo_2fa_expira');
            $this->session->set_flashdata('error', 'User is unavailable or inactive.');
            return redirect('login');
        }

        if ((string)$this->session->userdata('metodo_2fa') === 'totp') {
            if ((int)($usuario_db['usu_2fa'] ?? 0) !== 1) {
                $this->session->set_flashdata('error', 'La verificación por correo no está habilitada. Usa la aplicación autenticadora o un código de recuperación.');
                return redirect('dos-factores');
            }
            $this->session->set_userdata('metodo_2fa', 'email');
        }

        if ($this->_usuario_esta_bloqueado_2fa($usuario_db)) {
            $minutos = $this->_minutos_restantes_bloqueo_2fa($usuario_db);
            $min_texto = $minutos <= 1 ? '1 minute' : "{$minutos} minutes";
            $this->session->unset_userdata('usuario_2fa');
            $this->session->unset_userdata('codigo_2fa');
            $this->session->unset_userdata('codigo_2fa_expira');
            $this->session->set_flashdata('error', "Your account is locked for security reasons. Please try again in {$min_texto}.");
            return redirect('login');
        }

        $nuevo_codigo = (string)random_int(100000, 999999);
        $this->session->set_userdata('codigo_2fa', $nuevo_codigo);
        $this->session->set_userdata('codigo_2fa_expira', time() + 600);

        $enviado = $this->_enviar_correo_2fa($usuario_2fa, $nuevo_codigo);

        if ($enviado) {
            $this->session->set_flashdata('success', 'A new verification code has been sent to your email.');
        } else {
            $this->session->set_flashdata('error', 'We could not send the verification email. Check the email configuration with your administrator.');
        }

        redirect('dos-factores');
    }

    /**
     * Envía el correo electrónico con el token 2FA usando la plantilla transaccional LOGIN_2FA
     */
    private function _enviar_correo_2fa(array $usuario, $codigo)
    {
        $correo_destino = trim((string)($usuario['usu_correo'] ?? ''));
        if ($correo_destino === '' || !filter_var($correo_destino, FILTER_VALIDATE_EMAIL)) {
            return FALSE;
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        if (empty($mail_settings['mail_enabled_bool'])) {
            return FALSE;
        }

        $this->load->model('Plantillas_correo_model');
        $plantilla = $this->Plantillas_correo_model->get_por_codigo('LOGIN_2FA');

        $ip_actual = $this->input->ip_address() ?: '127.0.0.1';
        $navegador = $this->agent->is_browser()
            ? $this->agent->browser() . ' ' . $this->agent->version()
            : ($this->agent->is_mobile() ? 'Mobile Device' : 'Web Browser');

        $variables = [
            '{usuario_nombre}'      => trim(($usuario['usu_nombre'] ?? '') . ' ' . ($usuario['usu_apellido'] ?? '')) ?: $usuario['usu_usuario'],
            '{usuario_usuario}'     => $usuario['usu_usuario'] ?? '',
            '{usuario_correo}'      => $correo_destino,
            '{codigo_verificacion}' => $codigo,
            '{ip_direccion}'        => $ip_actual,
            '{navegador}'           => $navegador,
            '{ubicacion}'           => 'Sign-in',
            '{enlace_accion}'       => base_url('dos-factores'),
        ];

        if (!empty($plantilla) && (int)$plantilla['plc_status'] === 1) {
            $asunto = $this->Plantillas_correo_model->procesar_texto($plantilla['plc_asunto'], $variables);
            $mensaje_html = $this->Plantillas_correo_model->generar_html_completo($plantilla, $variables);
        } else {
            // Plantilla de respaldo en caso de que LOGIN_2FA esté inactiva
            $asunto = 'Security verification code: ' . $codigo;
            $mensaje_html = '<div style="font-family:sans-serif;padding:24px;max-width:520px;margin:auto;border:1px solid #e4e6ef;border-radius:8px;">'
                . '<h2 style="color:#181c32;margin-bottom:12px;">Sign-in Verification (2FA)</h2>'
                . '<p style="color:#5e6278;font-size:14px;">Hello <strong>' . htmlspecialchars($usuario['usu_nombre'] ?? '') . '</strong>,</p>'
                . '<p style="color:#5e6278;font-size:14px;">Enter the following numeric code to complete your sign-in:</p>'
                . '<div style="background:#f1f1f4;padding:16px;text-align:center;font-size:32px;font-weight:bold;letter-spacing:6px;color:#181c32;border-radius:6px;margin:24px 0;">'
                . htmlspecialchars($codigo) . '</div>'
                . '<p style="color:#a1a5b7;font-size:12px;">This code expires in 10 minutes and can only be used once. If you did not request this access, we recommend changing your password immediately.</p>'
                . '</div>';
        }

        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($mail_settings['ci_email_config']['config'] ?? []);

        $from_email = $mail_settings['ci_email_config']['from_email'] ?? 'notificaciones@ejemplo.com';
        $from_name  = $mail_settings['ci_email_config']['from_name'] ?? $this->app_name;
        $this->email->from($from_email, $from_name);

        if (!empty($mail_settings['ci_email_config']['reply_to'])) {
            $this->email->reply_to($mail_settings['ci_email_config']['reply_to'], $from_name);
        }

        $this->email->to($correo_destino);
        $this->email->subject($asunto);
        $this->email->message($mensaje_html);

        try {
            return (bool)$this->email->send(FALSE);
        } catch (\Throwable $e) {
            log_message('error', 'Error enviando correo 2FA a ' . $correo_destino . ': ' . $e->getMessage());
            return FALSE;
        }
    }

    /**
     * Determina si el usuario tiene un bloqueo temporal activo por intentos fallidos de 2FA.
     * Si el tiempo de bloqueo ya expiró, limpia automáticamente los campos en cab_usuarios.
     */
    private function _usuario_esta_bloqueado_2fa(array &$usuario)
    {
        if (empty($usuario['usu_2fa_bloqueado_hasta'])) {
            return FALSE;
        }

        $bloqueado_hasta_ts = strtotime($usuario['usu_2fa_bloqueado_hasta']);
        if ($bloqueado_hasta_ts && $bloqueado_hasta_ts > time()) {
            return TRUE;
        }

        // Si ya expiró el bloqueo, autocuración/reseteo inmediato
        if (!empty($usuario['usu_id'])) {
            $this->db->where('usu_id', $usuario['usu_id'])->update('cab_usuarios', [
                'usu_2fa_intentos'        => 0,
                'usu_2fa_bloqueado_hasta' => NULL,
            ]);
        }
        $usuario['usu_2fa_intentos'] = 0;
        $usuario['usu_2fa_bloqueado_hasta'] = NULL;

        return FALSE;
    }

    /**
     * Calcula los minutos enteros restantes del bloqueo 2FA activo.
     */
    private function _minutos_restantes_bloqueo_2fa(array $usuario)
    {
        if (empty($usuario['usu_2fa_bloqueado_hasta'])) {
            return 0;
        }

        $diff = strtotime($usuario['usu_2fa_bloqueado_hasta']) - time();
        return $diff > 0 ? (int)ceil($diff / 60) : 0;
    }

    /**
     * Envía un correo de alerta de seguridad al usuario cuando su cuenta es bloqueada por intentos fallidos de 2FA
     */
    private function _enviar_alerta_bloqueo_2fa(array $usuario, $minutos_bloqueo, $ip)
    {
        $correo_destino = trim((string)($usuario['usu_correo'] ?? ''));
        if ($correo_destino === '' || !filter_var($correo_destino, FILTER_VALIDATE_EMAIL)) {
            return FALSE;
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        if (empty($mail_settings['mail_enabled_bool'])) {
            return FALSE;
        }

        $navegador = $this->agent->is_browser()
            ? $this->agent->browser() . ' ' . $this->agent->version()
            : ($this->agent->is_mobile() ? 'Mobile Device' : 'Web Browser');

        $nombre_usuario = trim(($usuario['usu_nombre'] ?? '') . ' ' . ($usuario['usu_apellido'] ?? '')) ?: $usuario['usu_usuario'];
        $empresa_nombre = $this->app_name;

        $asunto = "⚠️ Security Alert: Temporary lockout due to failed 2FA attempts - {$empresa_nombre}";
        $mensaje_html = '<div style="font-family:sans-serif;padding:24px;max-width:560px;margin:auto;border:1px solid #f1416c;border-radius:8px;background:#fff;">'
            . '<div style="text-align:center;margin-bottom:20px;">'
            . '<span style="display:inline-block;background:#ffe2e5;color:#f1416c;padding:10px 18px;border-radius:50px;font-weight:bold;font-size:14px;">⚠️ SECURITY ALERT</span>'
            . '</div>'
            . '<h2 style="color:#181c32;margin-bottom:12px;text-align:center;">Temporary Sign-in Lockout (2FA)</h2>'
            . '<p style="color:#5e6278;font-size:14px;">Hello <strong>' . htmlspecialchars($nombre_usuario) . '</strong>,</p>'
            . '<p style="color:#5e6278;font-size:14px;">We are notifying you that your account has been temporarily locked for <strong>' . (int)$minutos_bloqueo . ' minutes</strong> after <strong>5 consecutive failed attempts</strong> of two-step verification (2FA).</p>'
            . '<div style="background:#f8f9fa;border-left:4px solid #f1416c;padding:14px 16px;margin:20px 0;border-radius:4px;">'
            . '<p style="margin:0 0 6px 0;font-size:13px;color:#3f4254;"><strong>Date and Time:</strong> ' . date('d/m/Y H:i:s') . '</p>'
            . '<p style="margin:0 0 6px 0;font-size:13px;color:#3f4254;"><strong>IP Address:</strong> ' . htmlspecialchars($ip) . '</p>'
            . '<p style="margin:0;font-size:13px;color:#3f4254;"><strong>Device/Browser:</strong> ' . htmlspecialchars($navegador) . '</p>'
            . '</div>'
            . '<p style="color:#d9214e;font-size:13px;font-weight:600;">Was this not you?</p>'
            . '<p style="color:#5e6278;font-size:13px;">If you did not attempt to sign in, someone else may know your password. We recommend resetting your password immediately using the recovery link once the lockout period has ended.</p>'
            . '<div style="text-align:center;margin-top:24px;">'
            . '<a href="' . base_url('recuperar-clave') . '" style="background:#f1416c;color:#fff;text-decoration:none;padding:10px 22px;border-radius:6px;font-weight:bold;font-size:13px;display:inline-block;">Reset My Password</a>'
            . '</div>'
            . '<hr style="border:none;border-top:1px solid #ebedf3;margin:24px 0;">'
            . '<p style="color:#a1a5b7;font-size:11px;text-align:center;margin:0;">This is an automated security message from ' . htmlspecialchars($empresa_nombre) . '.</p>'
            . '</div>';

        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($mail_settings['ci_email_config']['config'] ?? []);

        $from_email = $mail_settings['ci_email_config']['from_email'] ?? 'notifications@example.com';
        $from_name  = $mail_settings['ci_email_config']['from_name'] ?? $this->app_name;
        $this->email->from($from_email, $from_name);

        if (!empty($mail_settings['ci_email_config']['reply_to'])) {
            $this->email->reply_to($mail_settings['ci_email_config']['reply_to'], $from_name);
        }

        $this->email->to($correo_destino);
        $this->email->subject($asunto);
        $this->email->message($mensaje_html);

        try {
            return (bool)$this->email->send(FALSE);
        } catch (\Throwable $e) {
            log_message('error', 'Error enviando alerta de bloqueo 2FA a ' . $correo_destino . ': ' . $e->getMessage());
            return FALSE;
        }
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    private function _registro_publico_habilitado()
    {
        if (($this->theme_settings['public_registration_enabled'] ?? '0') !== '1'
            || !$this->Usuarios_model->rol_registro_publico_valido($this->theme_settings['public_registration_role_id'] ?? '')
            || !$this->_tabla_tokens_disponible()) {
            return FALSE;
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        return !empty($mail_settings['ci_email_config']['enabled'])
            && !empty($mail_settings['ci_email_config']['from_email']);
    }

    private function _tabla_tokens_disponible()
    {
        return $this->db->table_exists('cab_usuario_verificacion')
            && $this->db->field_exists('uver_tipo', 'cab_usuario_verificacion');
    }

    private function _crear_token_csrf_registro()
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set_userdata('public_registration_csrf', $token);
        return $token;
    }

    private function _crear_token_csrf_login()
    {
        $existing = (string)$this->session->userdata('login_csrf');
        if ($existing !== '' && preg_match('/^[a-f0-9]{64}$/', $existing) === 1) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->set_userdata('login_csrf', $token);
        return $token;
    }

    private function _validar_token_csrf_login()
    {
        $expected = (string)$this->session->userdata('login_csrf');
        $provided = (string)($this->input->post('login_csrf', FALSE) ?: ($_POST['login_csrf'] ?? ''));

        // Si la sesión aún no tenía token pero la petición viene limpia por POST
        if ($expected === '' && $provided !== '') {
            $expected = (string)$this->session->userdata('login_csrf');
        }

        $valid = $expected !== ''
            && preg_match('/^[a-f0-9]{64}$/', $provided) === 1
            && hash_equals($expected, $provided);

        if (!$valid) {
            log_message('error', 'Solicitud de inicio de sesión rechazada por CSRF. Expected: ' . substr($expected, 0, 8) . '... Provided: ' . substr($provided, 0, 8) . '...');
        } else {
            // Renovar token tras validación exitosa
            $this->session->unset_userdata('login_csrf');
        }

        return $valid;
    }

    private function _validar_token_csrf_registro()
    {
        $expected = (string)$this->session->userdata('public_registration_csrf');
        $provided = (string)$this->input->post('registration_csrf', FALSE);
        $this->session->unset_userdata('public_registration_csrf');

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    private function _crear_token_csrf_recuperacion()
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set_userdata('password_recovery_csrf', $token);
        return $token;
    }

    private function _validar_token_csrf_recuperacion()
    {
        $expected = (string)$this->session->userdata('password_recovery_csrf');
        $provided = (string)$this->input->post('recovery_csrf', FALSE);
        $this->session->unset_userdata('password_recovery_csrf');

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    private function _enviar_correo_recuperacion(array $usuario, $token, array $mail_settings)
    {
        $email_config = $mail_settings['ci_email_config']['config'] ?? [];
        $full_name = trim((string)($usuario['usu_nombre'] ?? '') . ' ' . (string)($usuario['usu_apellido'] ?? ''));
        $app_name = (string)($this->theme_settings['project_name'] ?? 'My System');

        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($email_config);
        $this->email->from(
            (string)$mail_settings['ci_email_config']['from_email'],
            (string)($mail_settings['ci_email_config']['from_name'] ?? 'System Notifications')
        );
        if (!empty($mail_settings['ci_email_config']['reply_to'])) {
            $this->email->reply_to(
                (string)$mail_settings['ci_email_config']['reply_to'],
                (string)($mail_settings['ci_email_config']['from_name'] ?? 'System Notifications')
            );
        }

        $this->email->to((string)$usuario['usu_correo']);

        // Consultar plantilla dinámica en base de datos
        $this->load->model('Plantillas_correo_model');
        $plantilla_dinamica = $this->Plantillas_correo_model->render_plantilla('RECUPERAR_CLAVE', [
            '{usuario_nombre}'  => $full_name !== '' ? $full_name : (string)$usuario['usu_correo'],
            '{usuario_usuario}' => (string)($usuario['usu_usuario'] ?? ''),
            '{usuario_correo}'  => (string)$usuario['usu_correo'],
            '{enlace_accion}'   => base_url('nueva-clave/' . rawurlencode((string)$token)),
            '{empresa_nombre}'  => $app_name,
        ]);

        if (!empty($plantilla_dinamica['ok'])) {
            $this->email->subject($plantilla_dinamica['asunto']);
            $this->email->message($plantilla_dinamica['cuerpo_html']);
        } else {
            $this->email->subject($this->Configuracion_correos_model->get_template_subject(
                'metronic_reset_password',
                FALSE,
                'Reset password'
            ));
            $this->email->message($this->Configuracion_correos_model->render_template('metronic_reset_password', [
                'app_name' => $app_name,
                'email_title' => 'Reset password',
                'email_preheader' => 'We received a request to reset your password.',
                'email_heading' => 'Reset password',
                'email_logo_url' => $this->theme_settings['login_logo_url'] ?? base_url('assets/media/logos/default-dark.svg'),
                'email_footer' => $this->theme_settings['footer_text'] ?? 'This message was generated automatically by the system.',
                'email_help_text' => '',
                'recipient_name' => $full_name !== '' ? $full_name : (string)$usuario['usu_correo'],
                'intro_lines' => [
                    'We received a request to reset the password for your account.',
                    'If this was you, use the button below to set a new secure password.',
                ],
                'tips' => [
                    ['title' => 'This link is temporary', 'description' => 'For security, the link expires in one hour and can only be used once.'],
                    ['title' => 'Do not share your new password', 'description' => 'Use a strong password and avoid reusing it on other services.'],
                    ['title' => 'Ignore this email if you did not request it', 'description' => 'Your password will not change unless you open this link.'],
                ],
                'action_url' => base_url('nueva-clave/' . rawurlencode((string)$token)),
                'action_label' => 'Change password',
            ]));
        }

        try {
            if ($this->email->send(FALSE)) {
                return TRUE;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Excepción al enviar correo de recuperación a ' . (string)($usuario['usu_correo'] ?? '') . ': ' . $e->getMessage());
            return FALSE;
        }

        $debug = trim(strip_tags((string)$this->email->print_debugger(['headers', 'subject'])));
        log_message('error', 'No se pudo enviar el correo de recuperación al usuario ' . (string)($usuario['usu_id'] ?? '') . '. ' . $debug);
        return FALSE;
    }

    private function _validar_registro_publico(array $data)
    {
        $errores = [];
        foreach (['usu_nombre' => 'Full name'] as $field => $label) {
            if ($data[$field] === '') {
                $errores[$field] = $label . ' is required.';
            } elseif (strlen($data[$field]) > 100) {
                $errores[$field] = $label . ' cannot exceed 100 characters.';
            }
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $data['usu_usuario'])) {
            $errores['usu_usuario'] = 'The username must be between 3 and 50 characters and may contain only letters, numbers, periods, hyphens, or underscores.';
        } elseif ($this->Usuarios_model->existe_usuario($data['usu_usuario'])) {
            $errores['usu_usuario'] = 'That username is already registered.';
        }

        if ($data['usu_correo'] === '' || strlen($data['usu_correo']) > 150 || filter_var($data['usu_correo'], FILTER_VALIDATE_EMAIL) === FALSE) {
            $errores['usu_correo'] = 'Enter a valid email address of up to 150 characters.';
        } elseif ($this->Usuarios_model->existe_correo($data['usu_correo'])) {
            $errores['usu_correo'] = 'That email address is already registered.';
        }

        if (strlen($data['usu_clave']) < 8 || strlen($data['usu_clave']) > 72) {
            $errores['usu_clave'] = 'The password must be between 8 and 72 characters.';
        } elseif ($data['usu_clave'] !== $data['clave_confirm']) {
            $errores['clave_confirm'] = 'The password confirmation does not match.';
        }

        return $errores;
    }

    private function _enviar_verificacion_correo(array $usuario, $token, array $mail_settings)
    {
        $app_name = (string)($this->theme_settings['project_name'] ?? 'My System');
        $full_name = trim((string)($usuario['usu_nombre'] ?? '') . ' ' . (string)($usuario['usu_apellido'] ?? ''));
        $email_config = $mail_settings['ci_email_config']['config'] ?? [];

        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($email_config);
        $this->email->from(
            (string)$mail_settings['ci_email_config']['from_email'],
            (string)($mail_settings['ci_email_config']['from_name'] ?? 'System Notifications')
        );
        if (!empty($mail_settings['ci_email_config']['reply_to'])) {
            $this->email->reply_to(
                (string)$mail_settings['ci_email_config']['reply_to'],
                (string)($mail_settings['ci_email_config']['from_name'] ?? 'System Notifications')
            );
        }

        $this->email->to((string)$usuario['usu_correo']);

        // Consultar plantilla dinámica en base de datos
        $this->load->model('Plantillas_correo_model');
        $plantilla_dinamica = $this->Plantillas_correo_model->render_plantilla('VERIFICAR_CORREO', [
            '{usuario_nombre}'  => $full_name !== '' ? $full_name : (string)$usuario['usu_correo'],
            '{usuario_usuario}' => (string)($usuario['usu_usuario'] ?? ''),
            '{usuario_correo}'  => (string)$usuario['usu_correo'],
            '{enlace_accion}'   => base_url('verificar-correo/' . rawurlencode((string)$token)),
            '{empresa_nombre}'  => $app_name,
        ]);

        if (!empty($plantilla_dinamica['ok'])) {
            $this->email->subject($plantilla_dinamica['asunto']);
            $this->email->message($plantilla_dinamica['cuerpo_html']);
        } else {
            $this->email->subject($this->Configuracion_correos_model->get_template_subject(
                'metronic_email_verification',
                FALSE,
                'Verify your email to activate your account'
            ));
            $this->email->message($this->Configuracion_correos_model->render_template('metronic_email_verification', [
                'email_title' => 'Verify your email',
                'email_preheader' => 'Confirm your email to activate your account.',
                'email_heading' => 'Activate your account',
                'email_logo_url' => $this->theme_settings['login_logo_url'] ?? base_url('assets/media/logos/default-dark.svg'),
                'email_footer' => $this->theme_settings['footer_text'] ?? 'All rights reserved.',
                'recipient_name' => $full_name !== '' ? $full_name : (string)$usuario['usu_correo'],
                'app_name' => $app_name,
                'action_url' => base_url('verificar-correo/' . rawurlencode((string)$token)),
                'action_label' => 'Verify email',
            ]));
        }

        try {
            if ($this->email->send(FALSE)) {
                return TRUE;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Excepción al enviar verificación de correo a ' . (string)($usuario['usu_correo'] ?? '') . ': ' . $e->getMessage());
            return FALSE;
        }

        $debug = trim(strip_tags((string)$this->email->print_debugger(['headers', 'subject'])));
        log_message('error', 'No se pudo enviar la verificación de correo al usuario ' . (string)($usuario['usu_id'] ?? '') . '. ' . $debug);
        return FALSE;
    }

    /** Registra un evento en cab_bitacora */
    private function _bitacora($usuario, $accion, $detalle = '')
    {
        $ipAddress = $this->_resolver_ip_cliente();

        $this->db->insert('cab_bitacora', [
            'bit_usuario'   => $usuario,
            'bit_date'      => date('Y-m-d H:i:s'),
            'bit_accion'    => $accion,
            'bit_detalles'  => $detalle,
            'bit_navegador' => $this->input->user_agent(),
            'bit_ip'        => $ipAddress,
        ]);
    }

    private function _bitacora_ip($usuario)
    {
        $ip = $this->_resolver_ip_cliente();
        $host = $ip !== '' ? @gethostbyaddr($ip) : '';
        $proxyHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_VIA',
        ];

        $tiene_proxy = 0;
        foreach ($proxyHeaders as $header) {
            $value = (string)$this->input->server($header, TRUE);
            if ($value !== '') {
                $tiene_proxy = 1;
                break;
            }
        }

        $geo = $this->geoip_service->enrich_ip($ip);
        $requestId = function_exists('random_bytes') ? bin2hex(random_bytes(12)) : uniqid('req_', TRUE);

        $payload = [
            'bit_usuario' => (string)$usuario,
            'bit_date' => date('Y-m-d H:i:s'),
            'bit_asn' => $geo['bit_asn'] ?? NULL,
            'bit_isp' => $geo['bit_isp'] ?? NULL,
            'bit_navegador' => $this->input->user_agent(),
            'bit_ip' => $ip !== '' ? $ip : NULL,
            'bit_proxy' => $tiene_proxy,
            'bit_tor' => NULL,
            'bit_vpn' => NULL,
            'bit_mobile' => $this->agent->is_mobile() ? 1 : 0,
            'bit_country_code' => $geo['bit_country_code'] ?? NULL,
            'bit_region' => $geo['bit_region'] ?? NULL,
            'bit_city' => $geo['bit_city'] ?? NULL,
            'bit_organization' => $geo['bit_organization'] ?? NULL,
            'bit_latitude' => $geo['bit_latitude'] ?? NULL,
            'bit_longitude' => $geo['bit_longitude'] ?? NULL,
            'bit_fraud_score' => NULL,
            'bit_request_id' => $requestId,
            'bit_is_crawler' => $this->agent->is_robot() ? 1 : 0,
            'bit_host' => ($host !== '' && $host !== $ip) ? $host : NULL,
        ];

        if ($this->db->table_exists('cab_bitacora_ip')) {
            $this->db->insert('cab_bitacora_ip', $payload);
        }

        return $payload;
    }

    private function _resolver_ip_cliente()
    {
        $realIp = app_resolver_client_ip($this->input);

        if (!$this->_puede_usar_ip_simulada($realIp)) {
            return $realIp;
        }

        $simulatedIp = trim((string)$this->config->item('simulated_client_ip'));
        if ($simulatedIp === '' || !filter_var($simulatedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return $realIp;
        }

        return $simulatedIp;
    }

    private function _puede_usar_ip_simulada($realIp)
    {
        if (ENVIRONMENT !== 'development') {
            return FALSE;
        }

        $host = strtolower((string)$this->input->server('HTTP_HOST', TRUE));
        $serverName = strtolower((string)$this->input->server('SERVER_NAME', TRUE));

        if (strpos($host, 'localhost') === FALSE && strpos($serverName, 'localhost') === FALSE && !$this->_es_ip_privada_o_loopback($realIp)) {
            return FALSE;
        }

        return TRUE;
    }

    private function _es_ip_privada_o_loopback($ip)
    {
        return app_ip_es_privada_o_loopback($ip);
    }

    /** Oculta parcialmente un correo: john@gmail.com → j***@gmail.com */
    private function _ocultar_correo($correo)
    {
        if (empty($correo)) return '******';
        $partes = explode('@', $correo);
        if (count($partes) !== 2) return '******';
        $nombre  = $partes[0];
        $dominio = $partes[1];
        $oculto  = substr($nombre, 0, 1) . str_repeat('*', max(strlen($nombre) - 1, 3));
        return $oculto . '@' . $dominio;
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

    /**
     * Serializa en sesión la estructura de menús del rol autenticado.
     * MY_Controller la usa para resolver el módulo actual por URL y validar
     * acciones sin declarar IDs de menú ni de rol.
     */
    private function _guardar_estructura_sesion($rol_id)
    {
        $rol_id     = (string)$rol_id;
        $estructura = $this->Menu_model->get_estructura_permisos($rol_id);
        $rol        = $this->db
            ->select('per_nombre')
            ->where('per_id', $rol_id)
            ->limit(1)
            ->get('cab_grupopermi')
            ->row_array();

        $this->session->set_userdata([
            'menu_estructura' => json_encode($estructura, JSON_UNESCAPED_UNICODE),
            'menu_version'    => $this->Menu_model->version_permisos(),
            'es_master'       => $this->Menu_model->rol_es_master($rol_id) ? 1 : 0,
            'rol_nombre'      => (string)($rol['per_nombre'] ?? ''),
        ]);
    }

    private function _resolver_redirect_inicio($usuario)
    {
        $rol_id = (string)($usuario['usu_tipoper'] ?? '');
        if ($rol_id === '') {
            return 'dashboard';
        }

        return $this->Menus_model->get_url_inicio_login($rol_id);
    }

    private function _auth_view_data(array $extra = [])
    {
        return array_merge([
            'app_name' => $this->app_name,
            'theme_settings' => $this->theme_settings,
            'theme_css' => $this->theme_settings['theme_css'] ?? '',
        ], $extra);
    }
}
