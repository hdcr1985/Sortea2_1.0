<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ConfiguracionTema extends MY_Controller
{
    private $image_fields = [
        'dashboard_logo' => 'logo-dashboard',
        'dashboard_logo_compact' => 'logo-dashboard-mini',
        'login_logo' => 'logo-login',
        'login_logo_dark' => 'logo-login-dark',
        'login_background' => 'login-bg',
        'login_background_dark' => 'login-bg-dark',
        'favicon_image' => 'favicon',
    ];

    private $font_options = [
        'Inter, sans-serif' => 'Inter',
        'Segoe UI, sans-serif' => 'Segoe UI',
        'Roboto, sans-serif' => 'Roboto',
        'Poppins, sans-serif' => 'Poppins',
        'Montserrat, sans-serif' => 'Montserrat',
        'Nunito, sans-serif' => 'Nunito',
        'Lato, sans-serif' => 'Lato',
        'Open Sans, sans-serif' => 'Open Sans',
        'Source Sans 3, sans-serif' => 'Source Sans 3',
        'Work Sans, sans-serif' => 'Work Sans',
        'DM Sans, sans-serif' => 'DM Sans',
        'Mulish, sans-serif' => 'Mulish',
        'Ubuntu, sans-serif' => 'Ubuntu',
        'Rubik, sans-serif' => 'Rubik',
        'Manrope, sans-serif' => 'Manrope',
        'Libre Franklin, sans-serif' => 'Libre Franklin',
        'Barlow, sans-serif' => 'Barlow',
        'Oswald, sans-serif' => 'Oswald',
        'PT Sans, sans-serif' => 'PT Sans',
        'Merriweather, serif' => 'Merriweather',
        'Playfair Display, serif' => 'Playfair Display',
        'Lora, serif' => 'Lora',
        'Cormorant Garamond, serif' => 'Cormorant Garamond',
        'Georgia, serif' => 'Georgia',
        'JetBrains Mono, monospace' => 'JetBrains Mono',
        'Fira Code, monospace' => 'Fira Code',
        'IBM Plex Mono, monospace' => 'IBM Plex Mono',
        '__custom_google__' => 'Google Font personalizada',
    ];

    private $sidebar_weight_options = [
        '400' => 'Normal',
        '500' => 'Medium',
        '600' => 'Semi bold',
        '700' => 'Bold',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Configuracion_tema_model');
        $this->load->model('Usuarios_model');
        $this->load->model('Configuracion_correos_model');
        $this->page_title = 'Configuración del Tema';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Configuración del Tema', 'url' => ''],
        ];
    }

    public function index()
    {
        $theme_settings = $this->Configuracion_tema_model->get_settings();
        $font_options = $this->font_options;

        if (!isset($font_options[$theme_settings['font_family']])) {
            $font_options[$theme_settings['font_family']] = 'Actual (' . $theme_settings['font_family'] . ')';
        }

        $datos = [
            'theme_settings' => $theme_settings,
            'tabla_disponible' => $this->Configuracion_tema_model->tabla_existe(),
            'success' => $this->session->flashdata('success'),
            'error' => $this->session->flashdata('error'),
            'errores' => $this->session->flashdata('errores') ?: [],
            'font_options' => $font_options,
            'sidebar_weight_options' => $this->sidebar_weight_options,
            'registration_roles' => $this->Usuarios_model->get_roles_activos(FALSE),
            'verification_table_available' => $this->_tabla_tokens_disponible(),
            'registration_email_ready' => $this->_correo_registro_disponible(),
        ];

        $this->render('pages/configuracion_tema/index', $datos);
    }

    public function guardar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return redirect($this->module_path());
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para modificar la configuración del tema.');

        if (!$this->Configuracion_tema_model->tabla_existe()) {
            $this->session->set_flashdata('error', 'Debes ejecutar primero el script SQL de configuración del tema.');
            return redirect('ConfiguracionTema');
        }

        $data = $this->_recoger_post();
        $errores = $this->_validar($data);
        if ($data['public_registration_enabled'] === '1') {
            if (!$this->_tabla_tokens_disponible()) {
                $errores['public_registration_enabled'] = 'Prepara la tabla de tokens con el script SQL correspondiente antes de activar el registro.';
            }
            if (!$this->_correo_registro_disponible()) {
                $errores['public_registration_enabled'] = 'Configura y habilita el correo saliente antes de activar el registro público.';
            }
            if (!$this->Usuarios_model->rol_registro_publico_valido($data['public_registration_role_id'])) {
                $errores['public_registration_role_id'] = 'Selecciona un rol activo sin acceso a módulos administrativos (por ejemplo, el rol de registro público).';
            }
        }

        if (!empty($errores)) {
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('error', 'Corrige los campos marcados y vuelve a intentar.');
            return redirect('ConfiguracionTema');
        }

        $actual = $this->Configuracion_tema_model->get_settings();
        foreach ($this->image_fields as $field => $prefix) {
            $subida = $this->_procesar_imagen($field, $prefix, $actual[$field] ?? '');
            if (!$subida['ok']) {
                $this->session->set_flashdata('error', $subida['error']);
                return redirect('ConfiguracionTema');
            }
            $data[$field] = $subida['archivo'];
        }

        $this->db->trans_begin();
        $settings_saved = $this->Configuracion_tema_model->save_settings($data);
        if (!$settings_saved || !$this->db->trans_status() || !$this->db->trans_commit()) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('error', 'No se pudo guardar la configuración. Vuelve a intentarlo.');
            return redirect('ConfiguracionTema');
        }

        $this->bitacora('TEMA_CONFIGURAR', 'Se actualizó la configuración visual del sistema.');
        $this->session->set_flashdata('success', 'Configuración guardada correctamente.');
        redirect('ConfiguracionTema');
    }

    private function _recoger_post()
    {
        return [
            'project_name' => trim((string)$this->input->post('project_name', TRUE)),
            'footer_text' => trim((string)$this->input->post('footer_text', TRUE)),
            'footer_support_label' => trim((string)$this->input->post('footer_support_label', TRUE)),
            'footer_support_url' => trim((string)$this->input->post('footer_support_url', TRUE)),
            'font_family' => trim((string)$this->input->post('font_family', TRUE)),
            'google_font_family' => trim((string)$this->input->post('google_font_family', TRUE)),
            'font_size_px' => trim((string)$this->input->post('font_size_px', TRUE)),
            'sidebar_width_px' => trim((string)$this->input->post('sidebar_width_px', TRUE)),
            'dashboard_logo_height_px' => trim((string)$this->input->post('dashboard_logo_height_px', TRUE)),
            'sidebar_font_weight' => trim((string)$this->input->post('sidebar_font_weight', TRUE)),
            'primary_color' => trim((string)$this->input->post('primary_color', TRUE)),
            'button_hover_color' => trim((string)$this->input->post('button_hover_color', TRUE)),
            'header_bg_color' => trim((string)$this->input->post('header_bg_color', TRUE)),
            'header_text_color' => trim((string)$this->input->post('header_text_color', TRUE)),
            'sidebar_bg_color' => trim((string)$this->input->post('sidebar_bg_color', TRUE)),
            'sidebar_text_color' => trim((string)$this->input->post('sidebar_text_color', TRUE)),
            'sidebar_active_bg_color' => trim((string)$this->input->post('sidebar_active_bg_color', TRUE)),
            'sidebar_active_text_color' => trim((string)$this->input->post('sidebar_active_text_color', TRUE)),
            'footer_bg_color' => trim((string)$this->input->post('footer_bg_color', TRUE)),
            'footer_text_color' => trim((string)$this->input->post('footer_text_color', TRUE)),
            'login_card_bg_color' => trim((string)$this->input->post('login_card_bg_color', TRUE)),
            // Colores Modo Oscuro
            'dark_primary_color' => trim((string)$this->input->post('dark_primary_color', TRUE)),
            'dark_button_hover_color' => trim((string)$this->input->post('dark_button_hover_color', TRUE)),
            'dark_header_bg_color' => trim((string)$this->input->post('dark_header_bg_color', TRUE)),
            'dark_header_text_color' => trim((string)$this->input->post('dark_header_text_color', TRUE)),
            'dark_sidebar_bg_color' => trim((string)$this->input->post('dark_sidebar_bg_color', TRUE)),
            'dark_sidebar_text_color' => trim((string)$this->input->post('dark_sidebar_text_color', TRUE)),
            'dark_sidebar_active_bg_color' => trim((string)$this->input->post('dark_sidebar_active_bg_color', TRUE)),
            'dark_sidebar_active_text_color' => trim((string)$this->input->post('dark_sidebar_active_text_color', TRUE)),
            'dark_footer_bg_color' => trim((string)$this->input->post('dark_footer_bg_color', TRUE)),
            'dark_footer_text_color' => trim((string)$this->input->post('dark_footer_text_color', TRUE)),
            'dark_body_bg_color' => trim((string)$this->input->post('dark_body_bg_color', TRUE)),
            'dark_login_card_bg_color' => trim((string)$this->input->post('dark_login_card_bg_color', TRUE)),
            'login_welcome_title' => trim((string)$this->input->post('login_welcome_title', TRUE)),
            'login_welcome_subtitle' => trim((string)$this->input->post('login_welcome_subtitle', TRUE)),
            'login_form_title' => trim((string)$this->input->post('login_form_title', TRUE)),
            'login_form_subtitle' => trim((string)$this->input->post('login_form_subtitle', TRUE)),
            'public_registration_enabled' => $this->input->post('public_registration_enabled') ? '1' : '0',
            'public_registration_role_id' => trim((string)$this->input->post('public_registration_role_id', TRUE)),
            'google_auth_enabled' => $this->input->post('google_auth_enabled') ? '1' : '0',
            'google_client_id' => trim((string)$this->input->post('google_client_id', TRUE)),
            'google_client_secret' => trim((string)$this->input->post('google_client_secret', TRUE)),
            'apple_auth_enabled' => $this->input->post('apple_auth_enabled') ? '1' : '0',
            'apple_client_id' => trim((string)$this->input->post('apple_client_id', TRUE)),
            'apple_team_id' => trim((string)$this->input->post('apple_team_id', TRUE)),
            'apple_key_id' => trim((string)$this->input->post('apple_key_id', TRUE)),
            'apple_client_secret' => trim((string)$this->input->post('apple_client_secret', TRUE)),
        ];
    }

    private function _validar(array $data)
    {
        $errores = [];
        $required = ['project_name', 'footer_text', 'login_welcome_title', 'login_form_title'];

        foreach ($required as $field) {
            if ($data[$field] === '') {
                $errores[$field] = 'Este campo es obligatorio.';
            }
        }

        if (!array_key_exists($data['font_family'], $this->font_options)) {
            $errores['font_family'] = 'Selecciona una tipografía válida de la lista.';
        }

        if ($data['font_family'] === '__custom_google__') {
            if ($data['google_font_family'] === '') {
                $errores['google_font_family'] = 'Ingresa el nombre de la fuente de Google.';
            } elseif (preg_match('/^[A-Za-z0-9\s\-]{2,80}$/', $data['google_font_family']) !== 1) {
                $errores['google_font_family'] = 'El nombre de la fuente contiene caracteres no permitidos.';
            }
        }

        if (!array_key_exists($data['sidebar_font_weight'], $this->sidebar_weight_options)) {
            $errores['sidebar_font_weight'] = 'Selecciona un peso válido para el sidebar.';
        }

        $color_keys = [
            'primary_color','button_hover_color','header_bg_color','header_text_color',
            'sidebar_bg_color','sidebar_text_color','sidebar_active_bg_color','sidebar_active_text_color',
            'footer_bg_color','footer_text_color','login_card_bg_color',
            'dark_primary_color','dark_button_hover_color','dark_header_bg_color','dark_header_text_color',
            'dark_sidebar_bg_color','dark_sidebar_text_color','dark_sidebar_active_bg_color','dark_sidebar_active_text_color',
            'dark_footer_bg_color','dark_footer_text_color','dark_body_bg_color','dark_login_card_bg_color'
        ];
        foreach ($color_keys as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !$this->_es_color_hex($data[$field])) {
                $errores[$field] = 'Selecciona un color válido.';
            }
        }

        if (!ctype_digit($data['font_size_px']) || (int)$data['font_size_px'] < 5 || (int)$data['font_size_px'] > 20) {
            $errores['font_size_px'] = 'El tamaño de fuente debe estar entre 5 y 20 px.';
        }

        if (!ctype_digit($data['sidebar_width_px']) || (int)$data['sidebar_width_px'] < 200 || (int)$data['sidebar_width_px'] > 360) {
            $errores['sidebar_width_px'] = 'El ancho del sidebar debe estar entre 200 y 360 px.';
        }

        if (!ctype_digit($data['dashboard_logo_height_px']) || (int)$data['dashboard_logo_height_px'] < 16 || (int)$data['dashboard_logo_height_px'] > 80) {
            $errores['dashboard_logo_height_px'] = 'El tamaño del logo del dashboard debe estar entre 16 y 80 px.';
        }

        if ($data['footer_support_url'] !== '' && $data['footer_support_url'] !== '#' && filter_var($data['footer_support_url'], FILTER_VALIDATE_URL) === FALSE) {
            $errores['footer_support_url'] = 'Ingresa una URL válida o deja #.';
        }

        if ($data['google_auth_enabled'] === '1' && empty($data['google_client_id'])) {
            $errores['google_client_id'] = 'Ingresa el Client ID de Google para activar el inicio de sesión.';
        }

        if ($data['apple_auth_enabled'] === '1' && empty($data['apple_client_id'])) {
            $errores['apple_client_id'] = 'Ingresa el Service ID / Client ID de Apple para activar el inicio de sesión.';
        }

        return $errores;
    }

    private function _correo_registro_disponible()
    {
        $mail_settings = $this->Configuracion_correos_model->get_settings();
        return !empty($mail_settings['ci_email_config']['enabled'])
            && !empty($mail_settings['ci_email_config']['from_email']);
    }

    private function _tabla_tokens_disponible()
    {
        return $this->db->table_exists('cab_usuario_verificacion')
            && $this->db->field_exists('uver_tipo', 'cab_usuario_verificacion');
    }

    private function _procesar_imagen($field, $prefix, $actual = '')
    {
        if (empty($_FILES[$field]) || !isset($_FILES[$field]['error'])) {
            return ['ok' => TRUE, 'archivo' => $actual];
        }

        $archivo = $_FILES[$field];
        if ((int)$archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => TRUE, 'archivo' => $actual];
        }

        if ((int)$archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => FALSE, 'error' => 'No se pudo subir el archivo de ' . $field . '.'];
        }

        if ((int)$archivo['size'] <= 0 || (int)$archivo['size'] > 5 * 1024 * 1024) {
            return ['ok' => FALSE, 'error' => 'Cada imagen debe ser menor a 5 MB.'];
        }

        $tmp = $archivo['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return ['ok' => FALSE, 'error' => 'Archivo inválido para ' . $field . '.'];
        }

        $info = @getimagesize($tmp);
        if ($info === FALSE || empty($info['mime'])) {
            return ['ok' => FALSE, 'error' => 'El archivo ' . $field . ' debe ser una imagen válida.'];
        }

        $mime = $info['mime'];
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ];

        if (!isset($permitidos[$mime])) {
            return ['ok' => FALSE, 'error' => 'Solo se permiten imágenes JPG, PNG, WEBP o ICO para ' . $field . '.'];
        }

        $directorio = $this->_directorio_theme();
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, TRUE) && !is_dir($directorio)) {
            return ['ok' => FALSE, 'error' => 'No se pudo preparar el directorio de imágenes del tema.'];
        }

        $nombre = $prefix . '-' . bin2hex(random_bytes(10)) . '.' . $permitidos[$mime];
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;

        $guardado = FALSE;

        // Si la librería GD está disponible, re-codificar para sanitizar
        if (function_exists('imagecreatefromstring') && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], TRUE)) {
            $contenido = @file_get_contents($tmp);
            $imagen = $contenido !== FALSE ? @imagecreatefromstring($contenido) : FALSE;
            if ($imagen !== FALSE) {
                switch ($mime) {
                    case 'image/jpeg':
                        $guardado = @imagejpeg($imagen, $ruta, 88);
                        break;
                    case 'image/png':
                        @imagealphablending($imagen, FALSE);
                        @imagesavealpha($imagen, TRUE);
                        $guardado = @imagepng($imagen, $ruta, 6);
                        break;
                    case 'image/webp':
                        $guardado = function_exists('imagewebp') ? @imagewebp($imagen, $ruta, 86) : FALSE;
                        break;
                }
                @imagedestroy($imagen);
            }
        }

        // Si GD no está instalada o falló el guardado por GD, mover el archivo validado de forma nativa
        if (!$guardado) {
            $guardado = @move_uploaded_file($tmp, $ruta) || @copy($tmp, $ruta);
        }

        if (!$guardado) {
            return ['ok' => FALSE, 'error' => 'No se pudo guardar la imagen ' . $field . '.'];
        }

        if ($actual !== '') {
            $anterior = $directorio . DIRECTORY_SEPARATOR . basename($actual);
            if (is_file($anterior)) {
                @unlink($anterior);
            }
        }

        return ['ok' => TRUE, 'archivo' => $nombre];
    }

    private function _es_color_hex($value)
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$value) === 1;
    }

    private function _directorio_theme()
    {
        return rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'theme';
    }
}