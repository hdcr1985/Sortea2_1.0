<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ConfiguracionCorreos extends MY_Controller
{
    private $driver_options = [
        'smtp' => 'SMTP',
    ];

    private $encryption_options = [
        'tls' => 'TLS',
        'ssl' => 'SSL',
        'none' => 'Sin cifrado',
    ];

    private $mailtype_options = [
        'html' => 'HTML',
        'text' => 'Texto plano',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Configuracion_correos_model');
        $this->page_title = 'Configuración de Correos';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Configuración de Correos', 'url' => ''],
        ];
    }

    public function index()
    {
        $settings = $this->Configuracion_correos_model->get_settings();
        $templates = $this->Configuracion_correos_model->get_available_templates();
        $mail_template_editors = $this->Configuracion_correos_model->get_template_editors();
        $promo_template_editors = $this->Configuracion_correos_model->get_template_editors(TRUE);
        $this->_aplicar_editores_anteriores($mail_template_editors, $this->session->flashdata('old_mail_template_editors'));
        $this->_aplicar_editores_anteriores($promo_template_editors, $this->session->flashdata('old_promo_template_editors'));

        $datos = [
            'mail_settings' => $settings,
            'mail_templates' => $templates,
            'mail_template_editors' => $mail_template_editors,
            'promo_template_editors' => $promo_template_editors,
            'tabla_disponible' => $this->Configuracion_correos_model->tabla_existe(),
            'success' => $this->session->flashdata('success'),
            'error' => $this->session->flashdata('error'),
            'errores' => $this->session->flashdata('errores') ?: [],
            'driver_options' => $this->driver_options,
            'encryption_options' => $this->encryption_options,
            'mailtype_options' => $this->mailtype_options,
            'test_target_email' => (string)($this->session->flashdata('test_target_email') ?: ($this->usuario['usu_correo'] ?? '')),
        ];

        $this->render('pages/configuracion_correos/index', $datos);
    }

    public function guardar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return redirect($this->module_path());
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para modificar la configuración de correos.');

        if (!$this->Configuracion_correos_model->tabla_existe()) {
            $this->session->set_flashdata('error', 'Debes ejecutar primero el script SQL de configuración de correos.');
            return redirect($this->module_path());
        }

        $data = $this->_recoger_post();
        $errores = $this->_validar($data);
        $errores = array_merge($errores, $this->_validar_template_editors($data));

        if (!empty($errores)) {
            $this->session->set_flashdata('old_mail_template_editors', $data['mail_template_editors'] ?? []);
            $this->session->set_flashdata('old_promo_template_editors', $data['promo_template_editors'] ?? []);
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('error', 'Corrige los campos marcados y vuelve a intentar.');
            return redirect($this->module_path());
        }

        $this->db->trans_begin();
        $settings_saved = $this->Configuracion_correos_model->save_settings($data);
        $mail_templates_saved = $this->Configuracion_correos_model->save_template_editors($data['mail_template_editors'] ?? []);
        $promo_templates_saved = $this->Configuracion_correos_model->save_template_editors($data['promo_template_editors'] ?? [], TRUE);
        if (!$settings_saved || !$mail_templates_saved || !$promo_templates_saved || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('old_mail_template_editors', $data['mail_template_editors'] ?? []);
            $this->session->set_flashdata('old_promo_template_editors', $data['promo_template_editors'] ?? []);
            $this->session->set_flashdata('error', 'No se pudieron guardar todas las plantillas. Verifica que el HTML sea válido y vuelve a intentar.');
            return redirect($this->module_path());
        }
        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('old_mail_template_editors', $data['mail_template_editors'] ?? []);
            $this->session->set_flashdata('old_promo_template_editors', $data['promo_template_editors'] ?? []);
            $this->session->set_flashdata('error', 'No se pudo confirmar la configuración de correos. Vuelve a intentar.');
            return redirect($this->module_path());
        }

        $this->bitacora('CORREO_CONFIGURAR', 'Se actualizó la configuración de correo saliente del sistema.');
        $this->session->set_flashdata('success', 'Configuración de correos guardada correctamente.');
        redirect($this->module_path());
    }

    private function _aplicar_editores_anteriores(array &$templates, $old_templates)
    {
        if (!is_array($old_templates)) {
            return;
        }

        foreach ($templates as $slug => &$template) {
            if (!isset($old_templates[$slug]) || !is_array($old_templates[$slug])) {
                continue;
            }

            if (isset($old_templates[$slug]['subject']) && is_string($old_templates[$slug]['subject'])) {
                $template['subject'] = $old_templates[$slug]['subject'];
            }
            if (isset($old_templates[$slug]['html']) && is_string($old_templates[$slug]['html'])) {
                $template['html'] = $old_templates[$slug]['html'];
            }
        }
        unset($template);
    }

    public function probar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return redirect($this->module_path());
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para probar el correo saliente.');

        if (!$this->Configuracion_correos_model->tabla_existe()) {
            $this->session->set_flashdata('error', 'Debes ejecutar primero el script SQL de configuración de correos.');
            return redirect($this->module_path());
        }

        $target_email = trim((string)$this->input->post('test_target_email', TRUE));
        $this->session->set_flashdata('test_target_email', $target_email);

        if ($target_email === '' || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error', 'Ingresa un correo destino válido para la prueba.');
            return redirect($this->module_path());
        }

        $settings = $this->Configuracion_correos_model->get_settings();
        $errores = $this->_validar_configuracion_prueba($settings);

        if (!empty($errores)) {
            $this->session->set_flashdata('error', implode(' ', $errores));
            return redirect($this->module_path());
        }

        $email_config = $settings['ci_email_config']['config'] ?? [];
        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($email_config);
        $this->email->from($settings['mail_from_email'], $settings['mail_from_name']);

        if ($settings['mail_reply_to'] !== '') {
            $this->email->reply_to($settings['mail_reply_to'], $settings['mail_from_name']);
        }

        $this->email->to($target_email);
        $this->email->subject('Prueba de configuracion SMTP - ' . (string)($this->layout_data['app_name'] ?? 'Sistema'));
        $this->email->message($this->_build_test_email_body($settings));

        $ok = $this->email->send(FALSE);

        if (!$ok) {
            $debug = trim(strip_tags((string)$this->email->print_debugger(['headers', 'subject'])));
            $this->bitacora('CORREO_PRUEBA_FALLIDO', 'Fallo envio de correo de prueba a ' . $target_email . '. ' . $debug);
            $this->session->set_flashdata('error', 'No se pudo enviar el correo de prueba. Revisa host, puerto, usuario, clave y cifrado; el detalle quedó en la bitácora.');
            return redirect($this->module_path());
        }

        $this->bitacora('CORREO_PRUEBA_ENVIADO', 'Correo de prueba enviado a ' . $target_email . '.');
        $this->session->set_flashdata('success', 'Correo de prueba enviado correctamente a ' . $target_email . '.');
        return redirect($this->module_path());
    }

    public function preview($slug = '')
    {
        $settings = $this->Configuracion_correos_model->get_settings();

        $slug = trim((string)$slug);
        if ($slug === '') {
            $slug = trim((string)($this->input->get('template', TRUE) ?: $settings['mail_template_default']));
        }

        if (!$this->Configuracion_correos_model->template_exists($slug)) {
            show_404();
            return;
        }

        $data = $this->_build_preview_data($slug, $settings);

        $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'; img-src data: http: https:; style-src 'unsafe-inline' http: https:; font-src data: http: https:;");
        $this->output->set_content_type('text/html', 'utf-8');
        $this->output->set_output($this->Configuracion_correos_model->render_template($slug, $data));
    }

    private function _recoger_post()
    {
        $data = [
            'mail_enabled' => $this->input->post('mail_enabled') ? '1' : '0',
            'mail_driver' => trim((string)$this->input->post('mail_driver', TRUE)),
            'mail_host' => trim((string)$this->input->post('mail_host', TRUE)),
            'mail_port' => trim((string)$this->input->post('mail_port', TRUE)),
            'mail_encryption' => trim((string)$this->input->post('mail_encryption', TRUE)),
            'mail_user' => trim((string)$this->input->post('mail_user', TRUE)),
            'mail_password' => trim((string)$this->input->post('mail_password', FALSE)),
            'mail_from_email' => trim((string)$this->input->post('mail_from_email', TRUE)),
            'mail_from_name' => trim((string)$this->input->post('mail_from_name', TRUE)),
            'mail_reply_to' => trim((string)$this->input->post('mail_reply_to', TRUE)),
            'mail_timeout' => trim((string)$this->input->post('mail_timeout', TRUE)),
            'mail_charset' => trim((string)$this->input->post('mail_charset', TRUE)),
            'mailtype' => trim((string)$this->input->post('mailtype', TRUE)),
            'mail_template_default' => trim((string)$this->input->post('mail_template_default', TRUE)),
            'mail_newline' => $this->_normalizar_separador_linea($this->input->post('mail_newline', FALSE)),
            'mail_crlf' => $this->_normalizar_separador_linea($this->input->post('mail_crlf', FALSE)),
            'mail_debug' => $this->input->post('mail_debug') ? '1' : '0',
            'mail_template_editors' => $this->_recoger_template_editores('mail_templates'),
            'promo_template_editors' => $this->_recoger_template_editores('promo_templates'),
        ];

        // Si el campo se deja vacío se conserva la clave ya almacenada.
        if ($data['mail_password'] === '') {
            unset($data['mail_password']);
        }

        return $data;
    }

    private function _recoger_template_editores($field)
    {
        $posted = $this->input->post($field, FALSE);
        if (!is_array($posted)) {
            return [];
        }

        $templates = [];
        foreach ($posted as $slug => $values) {
            if (!is_string($slug) || !is_array($values)) {
                continue;
            }

            $templates[$slug] = [
                'subject' => trim((string)($values['subject'] ?? '')),
                'html' => is_string($values['html'] ?? NULL) ? $values['html'] : '',
            ];
        }

        return $templates;
    }

    private function _validar_template_editors(array $data)
    {
        $errores = [];
        foreach ([
            ['mail_template_editors', FALSE],
            ['promo_template_editors', TRUE],
        ] as [$key, $promotional]) {
            $available = $this->Configuracion_correos_model->get_template_editors($promotional);
            foreach ($available as $slug => $template) {
                if (!array_key_exists($slug, $data[$key] ?? [])) {
                    $errores[$key . '_' . $slug . '_html'] = 'Debes enviar el HTML de esta plantilla.';
                }
            }
            foreach (($data[$key] ?? []) as $slug => $values) {
                if (!isset($available[$slug])) {
                    $errores[$key] = 'Se recibió una plantilla no registrada.';
                    continue;
                }

                $subject = trim((string)($values['subject'] ?? ''));
                $html = (string)($values['html'] ?? '');
                if (($subject === '' || strlen($subject) > 180) && !$promotional) {
                    $errores[$key . '_' . $slug . '_subject'] = 'El asunto de la plantilla es obligatorio y no puede superar 180 caracteres.';
                }
                if (trim($html) === '' || strlen($html) > 200000) {
                    $errores[$key . '_' . $slug . '_html'] = 'El HTML es obligatorio y no puede superar 200 KB.';
                } elseif (strpos($html, '<?') !== FALSE
                    || preg_match('/<\s*(script|iframe|object|embed|form)\b|<\s*[^>]+\bon[a-z]+\s*=|javascript\s*:/i', $html)) {
                    $errores[$key . '_' . $slug . '_html'] = 'El HTML no puede incluir PHP, scripts, formularios ni atributos de evento.';
                }
            }
        }

        return $errores;
    }

    private function _normalizar_separador_linea($value)
    {
        $value = is_string($value) ? $value : '';

        if ($value === "\r\n" || $value === "\n" || $value === "\r") {
            return $value;
        }

        return "\r\n";
    }

    private function _validar(array $data)
    {
        $errores = [];

        if (!array_key_exists($data['mail_driver'], $this->driver_options)) {
            $errores['mail_driver'] = 'Selecciona un método de transporte válido.';
        }

        if (!array_key_exists($data['mail_encryption'], $this->encryption_options)) {
            $errores['mail_encryption'] = 'Selecciona un cifrado válido.';
        }

        if (!array_key_exists($data['mailtype'], $this->mailtype_options)) {
            $errores['mailtype'] = 'Selecciona un formato válido para el correo.';
        }

        if (!$this->Configuracion_correos_model->template_exists($data['mail_template_default'])) {
            $errores['mail_template_default'] = 'Selecciona una plantilla registrada válida.';
        }

        if ($data['mail_enabled'] === '1') {
            if ($data['mail_host'] === '') {
                $errores['mail_host'] = 'El servidor SMTP es obligatorio cuando el correo está activo.';
            }

            if ($data['mail_user'] === '') {
                $errores['mail_user'] = 'El usuario SMTP es obligatorio cuando el correo está activo.';
            }

            if ($data['mail_from_email'] === '') {
                $errores['mail_from_email'] = 'El correo remitente es obligatorio cuando el correo está activo.';
            }

            if ($data['mailtype'] === 'html' && $data['mail_template_default'] === '') {
                $errores['mail_template_default'] = 'Selecciona una plantilla HTML predeterminada.';
            }
        }

        if ($data['mail_from_email'] !== '' && !filter_var($data['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
            $errores['mail_from_email'] = 'Ingresa un correo remitente válido.';
        }

        if ($data['mail_reply_to'] !== '' && !filter_var($data['mail_reply_to'], FILTER_VALIDATE_EMAIL)) {
            $errores['mail_reply_to'] = 'Ingresa un correo de respuesta válido.';
        }

        if ($data['mail_from_name'] === '') {
            $errores['mail_from_name'] = 'El nombre remitente es obligatorio.';
        }

        if (!ctype_digit($data['mail_port']) || (int)$data['mail_port'] < 1 || (int)$data['mail_port'] > 65535) {
            $errores['mail_port'] = 'El puerto SMTP debe estar entre 1 y 65535.';
        }

        if (!ctype_digit($data['mail_timeout']) || (int)$data['mail_timeout'] < 5 || (int)$data['mail_timeout'] > 120) {
            $errores['mail_timeout'] = 'El timeout debe estar entre 5 y 120 segundos.';
        }

        if ($data['mail_charset'] === '' || preg_match('/^[A-Za-z0-9_\-]{3,20}$/', $data['mail_charset']) !== 1) {
            $errores['mail_charset'] = 'Ingresa un charset válido. Ejemplo: utf-8.';
        }

        foreach (['mail_newline', 'mail_crlf'] as $field) {
            if (!in_array($data[$field], ["\r\n", "\n", "\r"], TRUE)) {
                $errores[$field] = 'Selecciona un separador válido.';
            }
        }

        return $errores;
    }

    private function _build_preview_data($slug, array $settings)
    {
        $app_name = (string)($this->layout_data['app_name'] ?? 'Mi Sistema');
        $from_name = (string)($settings['mail_from_name'] ?? 'Notificaciones del Sistema');
        $from_email = (string)($settings['mail_from_email'] ?? 'correo@dominio.com');

        $base = [
            'app_name' => $app_name,
            'email_title' => 'Vista previa de plantilla',
            'email_preheader' => 'Así se verá el correo predeterminado del sistema.',
            'email_heading' => 'Correo de ejemplo',
            'email_footer' => 'Vista previa generada desde Configuración de Correos.',
            'email_logo_url' => !empty($this->layout_data['theme_settings']['login_logo_url'])
                ? $this->layout_data['theme_settings']['login_logo_url']
                : base_url('assets/media/logos/default-dark.svg'),
            'email_help_text' => 'Soporte: ' . $from_name . ' <' . $from_email . '>',
            'action_label' => 'Ver detalle',
            'action_url' => base_url('dashboard'),
        ];

        if ($slug === 'metronic_email_verification') {
            $base['email_title'] = 'Verifica tu correo';
            $base['email_heading'] = 'Activa tu cuenta';
            $base['email_preheader'] = 'Confirma tu correo para activar tu cuenta.';
            $base['recipient_name'] = 'María Rodríguez';
            $base['action_label'] = 'Verificar correo';
            $base['action_url'] = base_url('verificar-correo/token-de-ejemplo');
            return $base;
        }

        if ($slug === 'metronic_welcome') {
            $base['email_title'] = 'Vista previa de bienvenida';
            $base['email_heading'] = 'Bienvenido al sistema';
            $base['email_preheader'] = 'Correo de alta inicial para nuevos usuarios.';
            $base['recipient_name'] = 'María Rodríguez';
            $base['intro_lines'] = [
                'Tu cuenta ya fue creada correctamente y está lista para usarse.',
                'Desde este momento podrás acceder al panel y completar tu perfil inicial.',
            ];
            $base['steps'] = [
                ['title' => 'Activa tu acceso', 'description' => 'Ingresa por primera vez y valida que tus datos de contacto estén correctos.'],
                ['title' => 'Completa tu perfil', 'description' => 'Agrega la información básica que el sistema necesita para identificarte.'],
                ['title' => 'Explora tus opciones', 'description' => 'Revisa los módulos y permisos que ya tienes habilitados dentro de la plataforma.'],
            ];
            $base['action_label'] = 'Activar cuenta';
            return $base;
        }

        if ($slug === 'metronic_reset_password') {
            $base['email_title'] = 'Vista previa de recuperación';
            $base['email_heading'] = 'Restablecer contraseña';
            $base['email_preheader'] = 'Correo de recuperación de clave.';
            $base['recipient_name'] = 'Carlos Pérez';
            $base['intro_lines'] = [
                'Recibimos una solicitud para restablecer la contraseña de tu cuenta.',
                'Si fuiste tú, usa el botón inferior para registrar una nueva clave segura.',
            ];
            $base['tips'] = [
                ['title' => 'El enlace es temporal', 'description' => 'Por seguridad, el acceso de recuperación debe usarse antes de que expire.'],
                ['title' => 'No compartas tu nueva clave', 'description' => 'Usa una contraseña robusta y evita reutilizarla en otros servicios.'],
                ['title' => 'Ignora este correo si no lo solicitaste', 'description' => 'Si no reconoces esta acción, puedes descartarlo sin cambios en tu cuenta.'],
            ];
            $base['action_label'] = 'Cambiar contraseña';
            return $base;
        }

        if ($slug === 'metronic_subscription_confirmed') {
            $base['email_title'] = 'Vista previa de confirmación';
            $base['email_heading'] = 'Suscripción confirmada';
            $base['email_preheader'] = 'Correo de confirmación o alta de servicio.';
            $base['recipient_name'] = 'Ana Gómez';
            $base['intro_lines'] = [
                'Tu suscripción o plan ha sido confirmado correctamente.',
                'A continuación se muestra un resumen del alta aplicada dentro del sistema.',
            ];
            $base['summary_title'] = 'Resumen de activación';
            $base['summary_items'] = [
                ['label' => 'Plan', 'value' => 'Business mensual'],
                ['label' => 'Usuarios incluidos', 'value' => '25'],
                ['label' => 'Monto pagado', 'value' => '$150,00'],
            ];
            $base['action_label'] = 'Ver comprobante';
            return $base;
        }

        $base['email_body_html'] = '
            <p style="margin-top:0; margin-bottom:16px;">Este es un ejemplo de cómo se verá la plantilla seleccionada cuando el sistema envíe mensajes automáticos.</p>
            <p style="margin-top:0; margin-bottom:16px;">Puedes usar esta misma estructura para bienvenida, recuperación de clave y notificaciones operativas.</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0; background-color:#f9f9f9; border:1px solid #eff2f5; border-radius:12px;">
                <tr>
                    <td style="padding:20px;">
                        <div style="font-size:13px; color:#7e8299; margin-bottom:8px;">Aplicación</div>
                        <div style="font-size:16px; font-weight:700; color:#181c32; margin-bottom:16px;">' . $app_name . '</div>
                        <div style="font-size:13px; color:#7e8299; margin-bottom:8px;">Remitente configurado</div>
                        <div style="font-size:15px; color:#3f4254;">' . $from_name . ' &lt;' . $from_email . '&gt;</div>
                    </td>
                </tr>
            </table>
            <p style="margin-top:0; margin-bottom:0;">Cuando me compartas el HTML final de Metronic, este preview mostrará exactamente esa plantilla adaptada al sistema.</p>';

        return $base;
    }

    private function _validar_configuracion_prueba(array $settings)
    {
        $errores = [];

        if (!array_key_exists($settings['mail_driver'], $this->driver_options)) {
            $errores[] = 'El driver configurado no es valido.';
        }

        if ($settings['mail_host'] === '') {
            $errores[] = 'Debes configurar el servidor SMTP antes de probar.';
        }

        if (!ctype_digit((string)$settings['mail_port']) || (int)$settings['mail_port'] < 1 || (int)$settings['mail_port'] > 65535) {
            $errores[] = 'El puerto SMTP configurado no es valido.';
        }

        if ($settings['mail_user'] === '') {
            $errores[] = 'Debes configurar el usuario SMTP antes de probar.';
        }

        if ($settings['mail_from_email'] === '' || !filter_var($settings['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo remitente configurado no es valido.';
        }

        if ($settings['mail_from_name'] === '') {
            $errores[] = 'Debes configurar el nombre remitente antes de probar.';
        }

        if (!array_key_exists($settings['mail_encryption'], $this->encryption_options)) {
            $errores[] = 'El cifrado configurado no es valido.';
        }

        if (!array_key_exists($settings['mailtype'], $this->mailtype_options)) {
            $errores[] = 'El tipo de correo configurado no es valido.';
        }

        return $errores;
    }

    private function _build_test_email_body(array $settings)
    {
        $template_slug = trim((string)($settings['mail_template_default'] ?? ''));

        if (($settings['mailtype'] ?? 'html') === 'html' && $this->Configuracion_correos_model->template_exists($template_slug)) {
            $data = $this->_build_preview_data($template_slug, $settings);
            $data['email_title'] = 'Prueba de configuracion SMTP';
            $data['email_preheader'] = 'Este correo confirma que la configuracion actual puede enviar mensajes.';
            $data['email_footer'] = 'Correo de prueba generado desde Configuracion de Correos.';
            $data['email_heading'] = 'Prueba de configuracion SMTP';
            return $this->Configuracion_correos_model->render_template($template_slug, $data);
        }

        return "Prueba de configuracion SMTP\n\n" .
            'Servidor: ' . (string)$settings['mail_host'] . ':' . (string)$settings['mail_port'] . "\n" .
            'Remitente: ' . (string)$settings['mail_from_name'] . ' <' . (string)$settings['mail_from_email'] . ">\n" .
            'Fecha: ' . date('Y-m-d H:i:s');
    }
}