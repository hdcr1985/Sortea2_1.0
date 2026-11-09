<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PlantillasCorreo extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Plantillas_correo_model');
        $this->load->model('Configuracion_correos_model');

        $this->page_title = 'Transactional Email Templates';
        $this->breadcrumb = [
            ['titulo' => 'Home', 'url' => base_url('dashboard')],
            ['titulo' => 'Settings', 'url' => '#'],
            ['titulo' => 'Email Templates', 'url' => base_url('plantillas-correo')],
        ];

        // Assets necesarios para tablas, modales y editor enriquecido
        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
            'assets/plugins/custom/ckeditor/ckeditor-classic.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
            'assets/plugins/custom/ckeditor/ckeditor-classic.bundle.js',
        ];
    }

    /**
     * GET /plantillas-correo o /PlantillasCorreo/index
     */
    public function index()
    {
        $datos = [
            'total_plantillas' => $this->Plantillas_correo_model->count_todos(),
            'url_base'         => $this->url_base,
        ];

        $this->render('pages/plantillas_correo/index', $datos);
    }

    /**
     * POST /PlantillasCorreo/tabla (DataTables Server-Side)
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Access denied.'], 403);
        }

        $draw   = (int)$this->input->post('draw');
        $start  = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $search = trim((string)$this->input->post('search')['value'] ?? '');

        $order_col = (int)($this->input->post('order')[0]['column'] ?? 0);
        $order_dir = (string)($this->input->post('order')[0]['dir'] ?? 'asc');

        $total_records = $this->Plantillas_correo_model->count_todos();
        $filtered_records = $this->Plantillas_correo_model->count_filtrados($search);
        $rows = $this->Plantillas_correo_model->get_datatable($start, $length, $search, $order_col, $order_dir);

        $data = $this->_formatear_filas_datatable($rows);

        return $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $data,
        ]);
    }

    /**
     * GET /PlantillasCorreo/datos/:id (Datos JSON para modal de edición)
     */
    public function datos($id = 0)
    {
        $id = (int)$id;
        $row = $this->Plantillas_correo_model->get_por_id($id);

        if (!$row) {
            return $this->json(['ok' => FALSE, 'msg' => 'Template not found.'], 404);
        }

        // Decodificar JSON de variables
        $variables = [];
        if (!empty($row['plc_variables_disponibles'])) {
            $variables = json_decode($row['plc_variables_disponibles'], TRUE) ?: [];
        }
        $row['variables_array'] = $variables;

        return $this->json([
            'ok'   => TRUE,
            'data' => $row,
        ]);
    }

    /**
     * POST /PlantillasCorreo/crear
     */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Access denied.'], 403);
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Method not allowed.'], 405);
        }
        $this->autorizar_accion('incluir', 'You do not have permission to create templates.');

        $data = $this->_recoger_post();
        $errores = $this->_validar($data, 0);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $nuevo_id = $this->Plantillas_correo_model->insertar($data);
        if ($nuevo_id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'The template could not be saved to the database.'], 500);
        }

        $this->bitacora('PLANTILLA_CORREO_CREAR', "ID: {$nuevo_id} | Código: {$data['plc_codigo']} | Nombre: {$data['plc_nombre']}");

        return $this->json([
            'ok'  => TRUE,
            'msg' => 'Email template created successfully.',
            'id'  => $nuevo_id,
        ]);
    }

    /**
     * POST /PlantillasCorreo/actualizar/:id
     */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Access denied.'], 403);
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Method not allowed.'], 405);
        }
        $this->autorizar_accion('modificar', 'You do not have permission to modify templates.');

        $id = (int)$id;
        $actual = $this->Plantillas_correo_model->get_por_id($id);
        if (!$actual) {
            return $this->json(['ok' => FALSE, 'msg' => 'Template not found.'], 404);
        }

        $data = $this->_recoger_post();
        $errores = $this->_validar($data, $id);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        // Si es plantilla del sistema, el código no se debe modificar
        $codigos_sistema = ['BIENVENIDA', 'RECUPERAR_CLAVE', 'VERIFICAR_CORREO', 'LOGIN_2FA', 'AVISO_SEGURIDAD_IP'];
        if (in_array($actual['plc_codigo'], $codigos_sistema, TRUE)) {
            $data['plc_codigo'] = $actual['plc_codigo'];
        }

        $ok = $this->Plantillas_correo_model->actualizar($id, $data);
        if (!$ok) {
            return $this->json(['ok' => FALSE, 'msg' => 'The template changes could not be saved.'], 500);
        }

        $this->bitacora('PLANTILLA_CORREO_ACTUALIZAR', "ID: {$id} | Código: {$data['plc_codigo']} | Nombre: {$data['plc_nombre']}");

        return $this->json([
            'ok'  => TRUE,
            'msg' => 'Email template updated successfully.',
        ]);
    }

    /**
     * POST /PlantillasCorreo/eliminar/:id
     */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Access denied.'], 403);
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Method not allowed.'], 405);
        }
        $this->autorizar_accion('eliminar', 'You do not have permission to delete templates.');

        $id = (int)$id;
        $actual = $this->Plantillas_correo_model->get_por_id($id);
        if (!$actual) {
            return $this->json(['ok' => FALSE, 'msg' => 'Template not found.'], 404);
        }

        $codigos_sistema = ['BIENVENIDA', 'RECUPERAR_CLAVE', 'VERIFICAR_CORREO', 'LOGIN_2FA', 'AVISO_SEGURIDAD_IP'];
        if (in_array($actual['plc_codigo'], $codigos_sistema, TRUE)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'This is a core system template and cannot be deleted. If you do not want to use it, you can deactivate it by changing its status.',
            ], 422);
        }

        $this->Plantillas_correo_model->eliminar($id);
        $this->bitacora('PLANTILLA_CORREO_ELIMINAR', "ID: {$id} | Código: {$actual['plc_codigo']} | Nombre: {$actual['plc_nombre']}");

        return $this->json([
            'ok'  => TRUE,
            'msg' => 'Template deleted successfully.',
        ]);
    }

    /**
     * POST /PlantillasCorreo/cambiar_status/:id
     */
    public function cambiar_status($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Access denied.'], 403);
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Method not allowed.'], 405);
        }
        $this->autorizar_accion('modificar', 'You do not have permission to change template status.');

        $id = (int)$id;
        $actual = $this->Plantillas_correo_model->get_por_id($id);
        if (!$actual) {
            return $this->json(['ok' => FALSE, 'msg' => 'Template not found.'], 404);
        }

        $nuevo_status = ((int)$actual['plc_status'] === 1) ? 0 : 1;
        $this->Plantillas_correo_model->cambiar_status($id, $nuevo_status);

        $accion = $nuevo_status === 1 ? 'activated' : 'deactivated';
        $this->bitacora('PLANTILLA_CORREO_STATUS', "ID: {$id} | Template {$accion} (Status: {$nuevo_status})");

        return $this->json([
            'ok'     => TRUE,
            'status' => $nuevo_status,
            'msg'    => "Template {$accion} successfully.",
        ]);
    }

    /**
     * POST /PlantillasCorreo/preview o GET /PlantillasCorreo/preview/:id
     * Genera la previsualización responsiva renderizada en un iframe.
     */
    public function preview($id = 0)
    {
        $id = (int)$id;
        $plantilla = null;

        // Si se reciben datos por POST (previsualización en vivo desde el modal)
        if ($this->input->server('REQUEST_METHOD') === 'POST' && $this->input->post('plc_asunto')) {
            $plantilla = $this->_recoger_post();
        } elseif ($id > 0) {
            $plantilla = $this->Plantillas_correo_model->get_por_id($id);
        }

        if (!$plantilla) {
            echo '<div style="padding: 20px; font-family: sans-serif; color: #777;">The preview could not be loaded.</div>';
            return;
        }

        // Datos de simulación para preview
        $simulacion = [
            '{usuario_nombre}'      => $this->usuario['usu_nombre'] ?? 'John Doe',
            '{usuario_usuario}'     => $this->usuario['usu_usuario'] ?? 'jdoe',
            '{usuario_correo}'      => $this->usuario['usu_correo'] ?? 'john.doe@example.com',
            '{enlace_accion}'       => base_url('nueva-clave/token_de_prueba_simulado'),
            '{codigo_verificacion}' => '784291',
            '{ip_direccion}'        => $this->input->ip_address() ?: '200.8.235.48',
            '{navegador}'           => 'Chrome 122 on Windows 11',
            '{ubicacion}'           => 'New York, USA',
        ];

        $html = $this->Plantillas_correo_model->generar_html_completo($plantilla, $simulacion);

        $this->output
            ->set_header("Content-Security-Policy: sandbox; default-src 'none'; img-src data: http: https:; style-src 'unsafe-inline' http: https:; font-src data: http: https:;")
            ->set_content_type('text/html', 'utf-8')
            ->set_output($html);
    }

    /**
     * POST /PlantillasCorreo/enviar_prueba
     * Envía un correo real de prueba a la dirección indicada por el usuario usando SMTP.
     */
    public function enviar_prueba()
    {
        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'Access denied.'], 403);
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Method not allowed.'], 405);
        }
        $this->autorizar_accion('modificar', 'You do not have permission to send template tests.');

        $email_destino = trim((string)$this->input->post('email_destino', TRUE));
        $plantilla_id  = (int)$this->input->post('plc_id');

        if ($email_destino === '' || !filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Enter a valid email address for the test.'], 422);
        }

        $plantilla = null;
        if ($this->input->post('plc_asunto')) {
            $plantilla = $this->_recoger_post();
        } elseif ($plantilla_id > 0) {
            $plantilla = $this->Plantillas_correo_model->get_por_id($plantilla_id);
        }

        if (!$plantilla) {
            return $this->json(['ok' => FALSE, 'msg' => 'The template for the test could not be found.'], 404);
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        if (empty($mail_settings['mail_enabled_bool'])) {
            return $this->json([
                'ok' => FALSE,
                'msg' => 'The email service (SMTP) is inactive. Enable it in Email Settings before sending tests.',
            ], 422);
        }

        // Datos de simulación
        $simulacion = [
            '{usuario_nombre}'      => $this->usuario['usu_nombre'] ?? 'Test User',
            '{usuario_usuario}'     => $this->usuario['usu_usuario'] ?? 'test_user',
            '{usuario_correo}'      => $email_destino,
            '{enlace_accion}'       => base_url('dashboard'),
            '{codigo_verificacion}' => '592814',
            '{ip_direccion}'        => $this->input->ip_address() ?: '127.0.0.1',
            '{navegador}'           => 'Test Web Browser',
            '{ubicacion}'           => 'Test City',
        ];

        $asunto = '[TEST] ' . $this->Plantillas_correo_model->procesar_texto($plantilla['plc_asunto'], $simulacion);
        $cuerpo_html = $this->Plantillas_correo_model->generar_html_completo($plantilla, $simulacion);

        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($mail_settings['ci_email_config']['config'] ?? []);

        $from_email = $mail_settings['ci_email_config']['from_email'] ?? 'notifications@example.com';
        $from_name  = $mail_settings['ci_email_config']['from_name'] ?? 'Notifications';
        $this->email->from($from_email, $from_name);

        if (!empty($mail_settings['ci_email_config']['reply_to'])) {
            $this->email->reply_to($mail_settings['ci_email_config']['reply_to'], $from_name);
        }

        $this->email->to($email_destino);
        $this->email->subject($asunto);
        $this->email->message($cuerpo_html);

        try {
            if ($this->email->send(FALSE)) {
                $this->bitacora('PLANTILLA_CORREO_TEST', "Envío de prueba de '{$plantilla['plc_codigo']}' hacia {$email_destino}");
                return $this->json([
                    'ok'  => TRUE,
                    'msg' => "Test email sent successfully to {$email_destino}!",
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', "Excepción al enviar correo de prueba: " . $e->getMessage());
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'The test email could not be sent. Check the SMTP configuration and system logs.',
            ], 500);
        }

        $debug = trim(strip_tags((string)$this->email->print_debugger(['headers', 'subject'])));
        log_message('error', 'Fallo al enviar correo de prueba: ' . $debug);
        return $this->json([
            'ok'  => FALSE,
            'msg' => 'No se pudo enviar el correo de prueba. Revisa la configuración SMTP y los registros del sistema.',
        ], 500);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    private function _recoger_post()
    {
        return [
            'plc_codigo'                => strtoupper(trim((string)$this->input->post('plc_codigo', TRUE))),
            'plc_nombre'                => trim((string)$this->input->post('plc_nombre', TRUE)),
            'plc_descripcion'           => trim((string)$this->input->post('plc_descripcion', TRUE)),
            'plc_asunto'                => trim((string)$this->input->post('plc_asunto', TRUE)),
            'plc_preheader'             => trim((string)$this->input->post('plc_preheader', TRUE)),
            'plc_titulo'                => trim((string)$this->input->post('plc_titulo', TRUE)),
            'plc_subtitulo'             => trim((string)$this->input->post('plc_subtitulo', TRUE)),
            'plc_cuerpo'                => $this->_sanitizar_html_correo(trim((string)$this->input->post('plc_cuerpo'))), // HTML controlado
            'plc_boton_texto'           => trim((string)$this->input->post('plc_boton_texto', TRUE)),
            'plc_boton_url'             => trim((string)$this->input->post('plc_boton_url', TRUE)),
            'plc_footer_texto'          => trim((string)$this->input->post('plc_footer_texto', TRUE)),
            'plc_variables_disponibles' => trim((string)$this->input->post('plc_variables_disponibles')),
            'plc_status'                => $this->input->post('plc_status') === '0' ? 0 : 1,
        ];
    }

    /**
     * Sanitiza el HTML del cuerpo de la plantilla: elimina etiquetas y
     * atributos capaces de ejecutar scripts. El preview además usa CSP sandbox.
     */
    private function _sanitizar_html_correo($html)
    {
        $html = (string)$html;

        // Etiquetas peligrosas con contenido
        $html = preg_replace(
            '#<\s*(script|iframe|object|embed|form|meta|link|base|svg)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        );

        // Etiquetas peligrosas autocerradas
        $html = preg_replace(
            '#<\s*(script|iframe|object|embed|form|meta|link|base)\b[^>]*/?>#is',
            '',
            $html
        );

        // Manejadores de eventos inline (onclick, onerror, etc.)
        $html = preg_replace('#\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)#is', '', $html);

        // Esquemas peligrosos en href/src
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):#is', '$1=$2#', $html);

        return $html;
    }

    private function _validar(array $data, $id = 0)
    {
        $errores = [];

        if ($data['plc_codigo'] === '') {
            $errores['plc_codigo'] = 'The identifier code is required.';
        } elseif (!preg_match('/^[A-Z0-9_]{3,50}$/', $data['plc_codigo'])) {
            $errores['plc_codigo'] = 'The code must contain between 3 and 50 characters (uppercase letters, numbers, and underscores only).';
        } elseif ($this->Plantillas_correo_model->existe_codigo($data['plc_codigo'], $id)) {
            $errores['plc_codigo'] = 'That code is already in use by another template.';
        }

        if ($data['plc_nombre'] === '') {
            $errores['plc_nombre'] = 'The template name is required.';
        } elseif (mb_strlen($data['plc_nombre']) > 100) {
            $errores['plc_nombre'] = 'The name cannot exceed 100 characters.';
        }

        if ($data['plc_asunto'] === '') {
            $errores['plc_asunto'] = 'The email subject is required.';
        } elseif (mb_strlen($data['plc_asunto']) > 255) {
            $errores['plc_asunto'] = 'The subject cannot exceed 255 characters.';
        }

        if ($data['plc_cuerpo'] === '') {
            $errores['plc_cuerpo'] = 'The message body or content is required.';
        }

        return $errores;
    }

    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];
        $codigos_sistema = ['BIENVENIDA', 'RECUPERAR_CLAVE', 'VERIFICAR_CORREO', 'LOGIN_2FA', 'AVISO_SEGURIDAD_IP'];

        foreach ($rows as $r) {
            $id = (int)$r['plc_id'];
            $codigo = htmlspecialchars((string)$r['plc_codigo']);
            $nombre = htmlspecialchars((string)$r['plc_nombre']);
            $asunto = htmlspecialchars((string)$r['plc_asunto']);
            $descripcion = htmlspecialchars((string)($r['plc_descripcion'] ?? ''));
            $is_sistema = in_array($r['plc_codigo'], $codigos_sistema, TRUE);

            // Badge de código
            $badge_tipo = $is_sistema ? 'primary' : 'info';
            $badge_label = $is_sistema ? 'System' : 'Custom';
            $col_identificador = '<div class="d-flex flex-column">';
            $col_identificador .= '<div class="d-flex align-items-center gap-2">';
            $col_identificador .= '<span class="badge badge-light-' . $badge_tipo . ' fs-7 fw-bold font-monospace">' . $codigo . '</span>';
            $col_identificador .= '<span class="badge badge-light fs-9 text-muted">' . $badge_label . '</span>';
            $col_identificador .= '</div>';
            $col_identificador .= '<span class="fw-bold text-gray-900 fs-6 mt-1">' . $nombre . '</span>';
            if ($descripcion !== '') {
                $col_identificador .= '<span class="text-muted fs-7 text-truncate w-250px d-inline-block" title="' . $descripcion . '">' . $descripcion . '</span>';
            }
            $col_identificador .= '</div>';

            // Columna de Asunto
            $col_asunto = '<div class="text-gray-800 fw-semibold fs-7">' . $asunto . '</div>';
            if (!empty($r['plc_preheader'])) {
                $col_asunto .= '<div class="text-muted fs-8 fst-italic">' . htmlspecialchars((string)$r['plc_preheader']) . '</div>';
            }

            // Columna Estado
            $checked = ((int)$r['plc_status'] === 1) ? 'checked' : '';
            $status_class = ((int)$r['plc_status'] === 1) ? 'badge-light-success' : 'badge-light-danger';
            $status_text  = ((int)$r['plc_status'] === 1) ? 'Active' : 'Inactive';
            $col_estado = '
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check form-switch form-check-custom form-check-solid form-check-sm">
                        <input class="form-check-input cursor-pointer btn-cambiar-status" type="checkbox" ' . $checked . ' data-id="' . $id . '" title="Activate / Deactivate template" />
                    </div>
                    <span class="badge ' . $status_class . ' fs-8">' . $status_text . '</span>
                </div>';

            // Columna Acciones con botones compactos y KeenIcons obligatorios
            $col_acciones = '
                <div class="d-flex justify-content-end align-items-center gap-2 text-nowrap">
                    <button type="button" class="btn btn-sm btn-icon btn-light-info shadow-xs btn-preview-plantilla" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Preview email" aria-label="Preview email">
                        <i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-icon btn-light-success shadow-xs btn-probar-plantilla" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Send test email" aria-label="Send test email">
                        <i class="ki-duotone ki-paper-plane fs-4"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-editar-plantilla" data-id="' . $id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit template" aria-label="Edit template">
                        <i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>
                    </button>';

            if (!$is_sistema) {
                $col_acciones .= '
                    <button type="button" class="btn btn-sm btn-icon btn-light-danger shadow-xs btn-eliminar-plantilla" data-id="' . $id . '" data-nombre="' . $nombre . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete template" aria-label="Delete template">
                        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    </button>';
            }

            $col_acciones .= '</div>';

            $data[] = [
                $id,
                $col_identificador,
                $col_asunto,
                $col_estado,
                $col_acciones,
            ];
        }

        return $data;
    }
}

