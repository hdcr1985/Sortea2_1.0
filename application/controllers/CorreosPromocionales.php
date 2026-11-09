<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CorreosPromocionales extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Correos_promocionales_model');
        $this->load->model('Configuracion_correos_model');
        $this->load->model('Usuarios_model');
        $this->page_styles = ['assets/plugins/custom/ckeditor/ckeditor-classic.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/ckeditor/ckeditor-classic.bundle.js'];

        $this->page_title = 'Correos Promocionales';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Correos Promocionales', 'url' => ''],
        ];
    }

    public function index()
    {
        $mail_settings = $this->Configuracion_correos_model->get_settings();
        $promo_templates = $this->Configuracion_correos_model->get_promo_templates();

        $datos = [
            'mail_settings' => $mail_settings,
            'promo_templates' => $promo_templates,
            'destinatarios' => $this->Usuarios_model->get_destinatarios_promocionales(),
            'grupos' => $this->Correos_promocionales_model->get_grupos(),
            'historial' => $this->Correos_promocionales_model->get_historial(10),
            'tablas_disponibles' => $this->Correos_promocionales_model->tablas_existen(),
            'correo_configurado' => $mail_settings['ci_email_config']['enabled'] ?? FALSE,
            'success' => $this->session->flashdata('success'),
            'error' => $this->session->flashdata('error'),
            'errores' => $this->session->flashdata('errores') ?: [],
            'old' => $this->session->flashdata('old') ?: [],
        ];

        $this->render('pages/correos_promocionales/index', $datos);
    }

    public function preview()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            $this->_preview_error(405, 'La vista previa solo está disponible mediante POST.');
            return;
        }

        if (!$this->tiene_permiso('consultar')) {
            $this->_preview_error(403, 'No tienes permiso para previsualizar correos promocionales.');
            return;
        }

        $data = $this->_recoger_post();
        $template_view = $this->Configuracion_correos_model->resolve_promo_template_view($data['promo_template']);

        if ($template_view === '') {
            $this->_preview_error(422, 'Selecciona una plantilla promocional válida para generar la vista previa.');
            return;
        }

        $preview_data = $this->_build_email_view_data($data, [
            'usu_nombre' => 'Usuario de ejemplo',
            'usu_correo' => 'usuario@empresa.com',
        ]);

        $this->output->set_content_type('text/html', 'utf-8');
        $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'; img-src data: http: https:; style-src 'unsafe-inline' http: https:; font-src data: http: https:;");
        $this->output->set_output($this->Configuracion_correos_model->render_template($data['promo_template'], $preview_data, TRUE));
    }

    private function _preview_error($status, $message)
    {
        $this->output
            ->set_status_header((int)$status)
            ->set_content_type('text/html', 'utf-8')
            ->set_header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline';")
            ->set_output(
                '<!doctype html><html lang="es"><head><meta charset="utf-8">'
                . '<style>body{margin:0;padding:24px;font-family:Inter,Arial,sans-serif;line-height:1.5;}</style>'
                . '</head><body><p>' . htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '</p></body></html>'
            );
    }

    public function enviar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            redirect($this->module_path());
        }
        $this->autorizar_accion('incluir', 'No tienes permiso para enviar correos promocionales.');

        if (!$this->Correos_promocionales_model->tablas_existen()) {
            $this->session->set_flashdata('error', 'Debes ejecutar primero el script SQL del módulo de correos promocionales.');
            redirect($this->module_path());
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        if (empty($mail_settings['ci_email_config']['enabled'])) {
            $this->session->set_flashdata('error', 'El correo saliente del sistema está deshabilitado. Actívalo en Configuración de Correos.');
            redirect($this->module_path());
        }

        $data = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('old', $data);
            $this->session->set_flashdata('error', 'Corrige los campos marcados y vuelve a intentar.');
            redirect($this->module_path());
        }

        $destinatarios = $this->Usuarios_model->get_destinatarios_promocionales_por_ids($data['promo_destinatarios']);
        if (empty($destinatarios)) {
            $this->session->set_flashdata('error', 'No se encontraron destinatarios válidos para el envío.');
            $this->session->set_flashdata('old', $data);
            redirect($this->module_path());
        }

        $campana_id = $this->Correos_promocionales_model->crear_campana([
            'cpr_asunto' => $data['promo_subject'],
            'cpr_template' => $data['promo_template'],
            'cpr_from_email' => $data['promo_from_email'],
            'cpr_from_name' => $data['promo_from_name'],
            'cpr_reply_to' => $data['promo_reply_to'],
            'cpr_body_html' => $data['promo_body_html'],
            'cpr_total_destinatarios' => count($destinatarios),
            'cpr_estado' => 'procesando',
            'cpr_usuario_id' => (string)($this->usuario['usu_id'] ?? ''),
            'cpr_usuario' => (string)($this->usuario['usu_usuario'] ?? ''),
        ]);

        if ($campana_id <= 0) {
            $this->session->set_flashdata('error', 'No se pudo registrar la campaña promocional.');
            $this->session->set_flashdata('old', $data);
            redirect($this->module_path());
        }

        $this->load->library('email');
        $email_config = $mail_settings['ci_email_config']['config'] ?? [];
        $enviados = 0;
        $fallidos = 0;

        foreach ($destinatarios as $destinatario) {
            $this->email->clear(TRUE);
            $this->email->initialize($email_config);
            $this->email->from($data['promo_from_email'], $data['promo_from_name']);

            if ($data['promo_reply_to'] !== '') {
                $this->email->reply_to($data['promo_reply_to'], $data['promo_from_name']);
            }

            $this->email->to((string)$destinatario['usu_correo']);
            $this->email->subject($data['promo_subject']);
            $this->email->message($this->Configuracion_correos_model->render_template(
                $data['promo_template'],
                $this->_build_email_view_data($data, $destinatario),
                TRUE
            ));

            $ok = $this->email->send(FALSE);
            $error = '';

            if ($ok) {
                $enviados++;
            } else {
                $fallidos++;
                $error = trim(strip_tags((string)$this->email->print_debugger(['headers'])));
            }

            $this->Correos_promocionales_model->registrar_destinatario([
                'cpd_promocion_id' => $campana_id,
                'cpd_usuario_id' => (string)($destinatario['usu_id'] ?? ''),
                'cpd_usuario' => (string)($destinatario['usu_usuario'] ?? ''),
                'cpd_nombre' => (string)($destinatario['usu_nombre'] ?? ''),
                'cpd_correo' => (string)($destinatario['usu_correo'] ?? ''),
                'cpd_estado' => $ok ? 'enviado' : 'fallido',
                'cpd_error' => $error,
                'cpd_sent_at' => $ok ? date('Y-m-d H:i:s') : NULL,
            ]);
        }

        $estado = $fallidos > 0 ? ($enviados > 0 ? 'parcial' : 'fallido') : 'enviado';
        $this->Correos_promocionales_model->actualizar_resumen($campana_id, $enviados, $fallidos, $estado);
        $this->bitacora('CORREO_PROMOCIONAL_ENVIAR', 'Campaña ' . $campana_id . ' enviada a ' . count($destinatarios) . ' destinatarios.');

        $this->session->set_flashdata('success', 'Campaña enviada. Enviados: ' . $enviados . '. Fallidos: ' . $fallidos . '.');
        redirect($this->module_path());
    }

    /** GET /CorreosPromocionales/grupo_datos/:id - Obtener datos del grupo para edición */
    public function grupo_datos($id = 0)
    {
        $id = (int)$id;
        $grupo = $this->Correos_promocionales_model->get_grupo($id);
        if (!$grupo) {
            return $this->json(['ok' => FALSE, 'msg' => 'Grupo no encontrado.'], 404);
        }

        return $this->json(['ok' => TRUE, 'data' => $grupo]);
    }

    /** GET /CorreosPromocionales/grupo_usuarios/:id - Obtener IDs de usuarios del grupo */
    public function grupo_usuarios($id = 0)
    {
        $id = (int)$id;
        $usuarios_ids = $this->Correos_promocionales_model->get_grupo_usuarios_ids($id);
        return $this->json(['ok' => TRUE, 'usuarios_ids' => $usuarios_ids]);
    }

    /** POST /CorreosPromocionales/grupo_guardar - Crear o actualizar un grupo de destinatarios */
    public function grupo_guardar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            redirect($this->module_path());
        }
        $this->autorizar_accion('incluir', 'No tienes permiso para guardar grupos de correo.');

        $id = (int)$this->input->post('cgr_id');
        $nombre = trim((string)$this->input->post('cgr_nombre', TRUE));
        $descripcion = trim((string)$this->input->post('cgr_descripcion', TRUE));
        $color = trim((string)$this->input->post('cgr_color', TRUE));
        $usuarios = $this->input->post('usuarios_ids');

        if (!is_array($usuarios)) {
            $usuarios = [];
        }
        $usuarios = array_values(array_filter(array_map('trim', array_map('strval', $usuarios)), function ($uid) {
            return $uid !== '';
        }));

        $colores_validos = ['primary', 'success', 'info', 'warning', 'danger', 'dark'];
        if (!in_array($color, $colores_validos, TRUE)) {
            $color = 'primary';
        }

        if ($nombre === '') {
            return $this->json(['ok' => FALSE, 'errores' => ['cgr_nombre' => 'El nombre del grupo es obligatorio.']], 422);
        }

        if (mb_strlen($nombre) > 100) {
            return $this->json(['ok' => FALSE, 'errores' => ['cgr_nombre' => 'El nombre no puede superar los 100 caracteres.']], 422);
        }

        $data = [
            'cgr_nombre'      => $nombre,
            'cgr_descripcion' => $descripcion,
            'cgr_color'       => $color,
            'cgr_status'      => 1,
            'cgr_eliminado'   => 0,
        ];

        $grupo_id = $this->Correos_promocionales_model->guardar_grupo($data, $usuarios, $id);
        if ($grupo_id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo guardar el grupo.'], 500);
        }

        $accion = $id > 0 ? 'CORREO_GRUPO_ACTUALIZAR' : 'CORREO_GRUPO_CREAR';
        $detalle = "Grupo ID: $grupo_id | Nombre: $nombre | Destinatarios asignados: " . count($usuarios);
        $this->bitacora($accion, $detalle);

        return $this->json([
            'ok' => TRUE,
            'msg' => $id > 0 ? 'Grupo actualizado correctamente.' : 'Grupo creado correctamente.',
            'grupo_id' => $grupo_id,
        ]);
    }

    /** POST /CorreosPromocionales/grupo_eliminar/:id - Eliminar grupo */
    public function grupo_eliminar($id = 0)
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar grupos de correo.');

        $id = (int)$id;
        $grupo = $this->Correos_promocionales_model->get_grupo($id);
        if (!$grupo) {
            return $this->json(['ok' => FALSE, 'msg' => 'Grupo no encontrado.'], 404);
        }

        $this->Correos_promocionales_model->eliminar_grupo($id);
        $this->bitacora('CORREO_GRUPO_ELIMINAR', "Grupo ID: $id | Nombre: " . ($grupo['cgr_nombre'] ?? ''));

        return $this->json(['ok' => TRUE, 'msg' => 'Grupo eliminado correctamente.']);
    }

    private function _recoger_post()
    {
        $destinatarios = $this->input->post('promo_destinatarios');
        if (!is_array($destinatarios)) {
            $destinatarios = [];
        }

        return [
            'promo_template' => trim((string)$this->input->post('promo_template', TRUE)),
            'promo_subject' => trim((string)$this->input->post('promo_subject', TRUE)),
            'promo_from_email' => trim((string)$this->input->post('promo_from_email', TRUE)),
            'promo_from_name' => trim((string)$this->input->post('promo_from_name', TRUE)),
            'promo_reply_to' => trim((string)$this->input->post('promo_reply_to', TRUE)),
            'promo_body_html' => trim((string)$this->input->post('promo_body_html', FALSE)),
            'promo_destinatarios' => array_values(array_filter(array_map('strval', $destinatarios), function ($id) {
                return $id !== '';
            })),
        ];
    }

    private function _validar(array $data)
    {
        $errores = [];

        if (!$this->Configuracion_correos_model->promo_template_exists($data['promo_template'])) {
            $errores['promo_template'] = 'Selecciona una plantilla promocional válida.';
        }

        if ($data['promo_subject'] === '') {
            $errores['promo_subject'] = 'El asunto es obligatorio.';
        } elseif (strlen($data['promo_subject']) > 180) {
            $errores['promo_subject'] = 'El asunto no puede superar 180 caracteres.';
        }

        if ($data['promo_from_email'] === '' || !filter_var($data['promo_from_email'], FILTER_VALIDATE_EMAIL)) {
            $errores['promo_from_email'] = 'Ingresa un correo remitente válido.';
        }

        if ($data['promo_from_name'] === '') {
            $errores['promo_from_name'] = 'El nombre remitente es obligatorio.';
        }

        if ($data['promo_reply_to'] !== '' && !filter_var($data['promo_reply_to'], FILTER_VALIDATE_EMAIL)) {
            $errores['promo_reply_to'] = 'Ingresa un correo reply-to válido.';
        }

        if ($data['promo_body_html'] === '') {
            $errores['promo_body_html'] = 'El cuerpo HTML es obligatorio.';
        }

        if (empty($data['promo_destinatarios'])) {
            $errores['promo_destinatarios'] = 'Selecciona al menos un destinatario.';
        }

        return $errores;
    }

    private function _build_email_view_data(array $data, array $destinatario)
    {
        $logo_url = !empty($this->layout_data['theme_settings']['login_logo_url'])
            ? $this->layout_data['theme_settings']['login_logo_url']
            : base_url('assets/media/logos/default-dark.svg');

        return [
            'email_subject' => $data['promo_subject'],
            'email_title' => $data['promo_subject'],
            'email_preheader' => $data['promo_subject'],
            'email_heading' => $data['promo_subject'],
            'email_logo_url' => $logo_url,
            'email_body_html' => $data['promo_body_html'],
            'email_footer' => (string)($this->layout_data['theme_settings']['footer_text'] ?? 'Todos los derechos reservados.'),
            'promo_from_name' => $data['promo_from_name'],
            'promo_from_email' => $data['promo_from_email'],
            'promo_reply_to' => $data['promo_reply_to'],
            'recipient_name' => (string)($destinatario['usu_nombre'] ?? 'Usuario'),
            'recipient_email' => (string)($destinatario['usu_correo'] ?? ''),
            'nombre' => (string)($destinatario['usu_nombre'] ?? 'Usuario'),
            'correo' => (string)($destinatario['usu_correo'] ?? ''),
            'app_name' => (string)($this->layout_data['app_name'] ?? 'Mi Sistema'),
        ];
    }
}