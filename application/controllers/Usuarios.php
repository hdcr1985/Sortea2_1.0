<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Usuarios
 * Administración de usuarios del sistema.
 */
class Usuarios extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Usuarios_model');
                $this->load->model('Configuracion_correos_model');
        $this->page_styles = ['assets/plugins/custom/datatables/datatables.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/datatables/datatables.bundle.js'];

        $this->page_title = 'Usuarios';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Usuarios', 'url' => ''],
        ];
    }

    /** GET - Lista principal. */
    public function index()
    {
        $puede_asignar_admin_master = $this->_puede_asignar_admin_master();

        $datos = [
            'roles'             => $this->Usuarios_model->get_roles_activos($puede_asignar_admin_master),
                        'usuario_sesion_id' => (string)($this->usuario['usu_id'] ?? '0'),
            'puede_asignar_admin_master' => $puede_asignar_admin_master,
        ];

        $this->render('pages/usuarios/index', $datos);
    }

    /** POST AJAX - Datos paginados para DataTables. */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());

        $draw = (int)$this->input->post('draw');
        $start = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $searchData = $this->input->post('search');
        $search = is_array($searchData) ? (string)($searchData['value'] ?? '') : '';
        $order = $this->input->post('order');

        $order_column = 0;
        $order_dir = 'asc';

        if (!empty($order[0])) {
            $order_column = (int)($order[0]['column'] ?? 0);
            $order_dir = (string)($order[0]['dir'] ?? 'asc');
        }

        $rows = $this->Usuarios_model->get_datatable($start, $length, $search, $order_column, $order_dir);

        $this->json([
            'draw' => $draw,
            'recordsTotal' => $this->Usuarios_model->count_todos(),
            'recordsFiltered' => $this->Usuarios_model->count_filtrados($search),
            'data' => $this->_formatear_filas_datatable($rows),
        ]);
    }

    /** GET AJAX - Datos de un usuario. */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());

        $usuario = $this->Usuarios_model->get_por_id($id);
        if (empty($usuario)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Usuario no encontrado.'], 404);
        }

        if (!$this->_puede_gestionar_usuario($usuario, 'editar')) {
            return $this->json(['ok' => FALSE, 'msg' => 'Solo un Administrador Master puede editar a otro Administrador Master.'], 403);
        }

        $usuario['usu_foto_url'] = $this->_resolver_foto_usuario_url($usuario['usu_id'], $usuario['usu_foto'] ?? '');
        unset($usuario['usu_clave'], $usuario['usu_2fa_totp_secret'], $usuario['usu_2fa_totp_last_step']);
        $this->json(['ok' => TRUE, 'data' => $usuario]);
    }

    /** POST AJAX - Crear usuario. */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('incluir', 'No tienes permiso para crear usuarios.');

        $data = $this->_recoger_post();
        $data['usu_id'] = $this->Usuarios_model->generar_nuevo_id();
        $data['usu_foto'] = '';
        $errores = $this->_validar($data, 0, TRUE);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $data['usu_clave'] = password_hash($data['usu_clave'], PASSWORD_DEFAULT);

        $foto = $this->_procesar_foto_subida($data['usu_id']);
        if (!$foto['ok']) {
            return $this->json(['ok' => FALSE, 'errores' => ['usu_foto' => $foto['error']]], 422);
        }

        $data['usu_foto'] = $foto['archivo'];

        $id = $this->Usuarios_model->crear($this->_mapear_data_guardado($data, TRUE));

        if ($id) {
            $this->bitacora('USUARIO_CREAR', 'Usuario creado: ' . $data['usu_usuario']);
            $envio_bienvenida = $this->_enviar_correo_bienvenida($data);
            $mensaje = 'Usuario creado correctamente.';

            if (!empty($envio_bienvenida['sent'])) {
                $mensaje .= ' Se envió el correo de bienvenida.';
                $this->bitacora('USUARIO_BIENVENIDA_ENVIADA', 'Correo de bienvenida enviado a: ' . $data['usu_correo']);
            } elseif (!empty($envio_bienvenida['attempted'])) {
                $mensaje .= ' El usuario fue creado, pero no se pudo enviar el correo de bienvenida.';
                $this->bitacora('USUARIO_BIENVENIDA_FALLIDA', 'No se pudo enviar correo de bienvenida a: ' . $data['usu_correo'] . '. ' . (string)($envio_bienvenida['error'] ?? 'Sin detalle.'));
            }

            return $this->json(['ok' => TRUE, 'msg' => $mensaje, 'id' => $id]);
        }

        $this->json(['ok' => FALSE, 'msg' => 'Error al guardar el usuario.'], 500);
    }

    /** POST AJAX - Actualizar usuario. */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para modificar usuarios.');

        $usuario = $this->Usuarios_model->get_por_id($id);
        if (empty($usuario)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Usuario no encontrado.'], 404);
        }

        if (!$this->_puede_gestionar_usuario($usuario, 'editar')) {
            return $this->json(['ok' => FALSE, 'msg' => 'No tienes permiso para editar a este usuario.'], 403);
        }

        $data = $this->_recoger_post();

        // Un usuario no puede cambiarse su propio rol (evita autoelevación).
        if ((string)$id === (string)$this->usuario['usu_id']
            && (string)$data['usu_tipoper'] !== (string)$usuario['usu_tipoper']
            && !$this->_puede_asignar_admin_master()) {
            return $this->json(['ok' => FALSE, 'errores' => ['usu_tipoper' => 'No puedes cambiar tu propio rol.']], 422);
        }

        // Solo el Admin Master puede restablecer la clave de otro usuario.
        if ((string)$id !== (string)$this->usuario['usu_id']
            && !empty($data['usu_clave'])
            && !$this->_puede_asignar_admin_master()) {
            return $this->json(['ok' => FALSE, 'errores' => ['usu_clave' => 'Solo el Administrador Master puede cambiar la clave de otro usuario.']], 422);
        }

        $data['usu_foto'] = $usuario['usu_foto'] ?? '';
        $errores = $this->_validar($data, (string)$id, FALSE);

        if ((string)$id === (string)$this->usuario['usu_id'] && ($data['usu_status'] ?? '1') !== '1') {
            $errores['usu_status'] = 'No puedes desactivar tu propio usuario mientras estás autenticado.';
        }

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if (!empty($data['usu_clave'])) {
            $data['usu_clave'] = password_hash($data['usu_clave'], PASSWORD_DEFAULT);
        } else {
            unset($data['usu_clave']);
        }

        $foto = $this->_procesar_foto_subida((string)$id, $usuario['usu_foto'] ?? '');
        if (!$foto['ok']) {
            return $this->json(['ok' => FALSE, 'errores' => ['usu_foto' => $foto['error']]], 422);
        }
        $data['usu_foto'] = $foto['archivo'];

        $guardado = $this->_mapear_data_guardado($data, FALSE);

        if ($this->Usuarios_model->actualizar($id, $guardado)) {
            $this->_refrescar_sesion_si_aplica((string)$id);
            $this->bitacora('USUARIO_EDITAR', 'Usuario actualizado: ' . $data['usu_usuario']);
            return $this->json(['ok' => TRUE, 'msg' => 'Usuario actualizado correctamente.']);
        }

        $this->json(['ok' => FALSE, 'msg' => 'Error al actualizar el usuario.'], 500);
    }

    /** POST AJAX - Eliminar usuario. */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar usuarios.');

        $usuario = $this->Usuarios_model->get_por_id($id);
        if (empty($usuario)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Usuario no encontrado.'], 404);
        }

        if (!$this->_puede_gestionar_usuario($usuario, 'eliminar')) {
            return $this->json(['ok' => FALSE, 'msg' => 'No tienes permiso para eliminar a este usuario.'], 403);
        }

        if ((string)$id === (string)$this->usuario['usu_id']) {
            return $this->json(['ok' => FALSE, 'msg' => 'No puedes eliminar tu propio usuario.'], 422);
        }

        if ($this->Usuarios_model->eliminar($id)) {
            $this->bitacora('USUARIO_ELIMINAR', 'Usuario eliminado: ' . $usuario['usu_usuario']);
            return $this->json(['ok' => TRUE, 'msg' => 'Usuario eliminado correctamente.']);
        }

        $this->json(['ok' => FALSE, 'msg' => 'Error al eliminar el usuario.'], 500);
    }

    private function _recoger_post()
    {
        return [
            'usu_nombre'   => $this->_limpiar_texto_simple((string)$this->input->post('usu_nombre', TRUE)),
            'usu_apellido' => $this->_limpiar_texto_simple((string)$this->input->post('usu_apellido', TRUE)),
            'usu_usuario'  => trim((string)$this->input->post('usu_usuario', TRUE)),
            'usu_correo'   => trim((string)$this->input->post('usu_correo', TRUE)),
            'usu_tipoper'  => trim((string)$this->input->post('usu_tipoper', TRUE)),
                        'usu_celular'  => $this->normalizar_celular((string)$this->input->post('usu_celular', TRUE)),
            'usu_fechan'   => trim((string)$this->input->post('usu_fechan', TRUE)),
            'usu_genero'   => trim((string)$this->input->post('usu_genero', TRUE)),
            'usu_status'   => trim((string)$this->input->post('usu_status', TRUE)),
            'usu_clave'    => (string)$this->input->post('usu_clave'),
            'clave_confirm'=> (string)$this->input->post('clave_confirm'),
        ];
    }

    private function _validar(array $data, $id = 0, $requerir_clave = TRUE)
    {
        $errores = [];

        if ($data['usu_nombre'] === '') {
            $errores['usu_nombre'] = 'El nombre es requerido.';
        } elseif (strlen($data['usu_nombre']) > 100) {
            $errores['usu_nombre'] = 'El nombre no puede superar 100 caracteres.';
        }

        if ($data['usu_apellido'] === '') {
            $errores['usu_apellido'] = 'El apellido es requerido.';
        } elseif (strlen($data['usu_apellido']) > 100) {
            $errores['usu_apellido'] = 'El apellido no puede superar 100 caracteres.';
        }

        if ($data['usu_usuario'] === '') {
            $errores['usu_usuario'] = 'El nombre de usuario es requerido.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $data['usu_usuario'])) {
            $errores['usu_usuario'] = 'El usuario solo puede contener letras, números, punto, guion y guion bajo.';
        } elseif ($this->Usuarios_model->existe_usuario($data['usu_usuario'], $id)) {
            $errores['usu_usuario'] = 'Ese nombre de usuario ya está registrado.';
        }

        if ($data['usu_correo'] === '') {
            $errores['usu_correo'] = 'El correo es requerido.';
        } elseif (!filter_var($data['usu_correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['usu_correo'] = 'Ingresa un correo válido.';
        } elseif ($this->Usuarios_model->existe_correo($data['usu_correo'], $id)) {
            $errores['usu_correo'] = 'Ese correo ya está registrado.';
        }

        if ($data['usu_tipoper'] === '' || !$this->Usuarios_model->rol_valido($data['usu_tipoper'])) {
            $errores['usu_tipoper'] = 'Selecciona un rol válido.';
        } elseif (!$this->_puede_asignar_rol($data['usu_tipoper'])) {
            $errores['usu_tipoper'] = 'No tienes permiso para asignar el rol Admin Master.';
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

        if (!in_array($data['usu_status'], ['0', '1'], TRUE)) {
            $errores['usu_status'] = 'Selecciona un estado válido.';
        }

        if ($requerir_clave && $data['usu_clave'] === '') {
            $errores['usu_clave'] = 'La contraseña es requerida.';
        }

        if ($data['usu_clave'] !== '') {
            if (strlen($data['usu_clave']) < 8) {
                $errores['usu_clave'] = 'La contraseña debe tener al menos 8 caracteres.';
            }

            if ($data['usu_clave'] !== $data['clave_confirm']) {
                $errores['clave_confirm'] = 'La confirmación de contraseña no coincide.';
            }
        }

        return $errores;
    }

    private function _puede_asignar_admin_master()
    {
        return $this->es_master;
    }

    /**
     * Cobertura de menús de un rol. Sustituye la jerarquía por IDs: un rol
     * con más módulos asignados tiene mayor nivel operativo.
     */
    private function _cobertura_rol($rol_id)
    {
        return $this->Menu_model->cobertura_rol($rol_id);
    }

    /**
     * Un usuario solo puede asignar roles de cobertura inferior a la propia.
     * El rol con cobertura total puede asignar cualquier rol.
     */
    private function _puede_asignar_rol($rol_id)
    {
        if ($this->_puede_asignar_admin_master()) {
            return TRUE;
        }

        return $this->_cobertura_rol($rol_id) < $this->_cobertura_rol($this->usuario['usu_tipoper'] ?? 0);
    }

    /**
     * Un usuario no puede gestionar cuentas de nivel igual o superior al suyo,
     * salvo su propia cuenta (para editar sus datos básicos).
     */
    private function _puede_gestionar_usuario(array $usuario, $accion = 'editar')
    {
        if ($this->_puede_asignar_admin_master()) {
            return TRUE;
        }

        if ((string)($usuario['usu_id'] ?? '') === (string)($this->usuario['usu_id'] ?? '')) {
            return TRUE;
        }

        return $this->_cobertura_rol($usuario['usu_tipoper'] ?? 0) < $this->_cobertura_rol($this->usuario['usu_tipoper'] ?? 0);
    }

    private function _mapear_data_guardado(array $data, $incluye_clave)
    {
        $guardado = [
            'usu_nombre'  => $data['usu_nombre'],
            'usu_apellido' => $this->db->field_exists('usu_apellido', 'cab_usuarios')
                ? ($data['usu_apellido'] !== '' ? $data['usu_apellido'] : NULL)
                : NULL,
            'usu_usuario' => $data['usu_usuario'],
            'usu_correo'  => $data['usu_correo'],
            'usu_tipoper' => $data['usu_tipoper'],
                        'usu_celular' => $data['usu_celular'],
            'usu_fechan'  => $data['usu_fechan'] !== '' ? $data['usu_fechan'] : NULL,
            'usu_genero'  => $data['usu_genero'],
            'usu_foto'    => $data['usu_foto'],
            'usu_status'  => $data['usu_status'],
        ];

        

        if (!$this->db->field_exists('usu_apellido', 'cab_usuarios')) {
            unset($guardado['usu_apellido']);
        }

        if ($incluye_clave || isset($data['usu_clave'])) {
            $guardado['usu_clave'] = $data['usu_clave'];
        }

        return $guardado;
    }

    private function _refrescar_sesion_si_aplica($usuario_id)
    {
        if ((string)$usuario_id !== (string)($this->usuario['usu_id'] ?? '0')) {
            return;
        }

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

    private function _enviar_correo_bienvenida(array $usuario)
    {
        $correo = trim((string)($usuario['usu_correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['attempted' => FALSE, 'sent' => FALSE, 'error' => 'Correo del usuario inválido.'];
        }

        $mail_settings = $this->Configuracion_correos_model->get_settings();
        $email_config = $mail_settings['ci_email_config'] ?? [];

        if (empty($email_config['enabled']) || empty($email_config['from_email'])) {
            return ['attempted' => FALSE, 'sent' => FALSE, 'error' => 'Correo saliente deshabilitado o sin remitente configurado.'];
        }

        $template_view = $this->Configuracion_correos_model->resolve_template_view('metronic_welcome');
        if ($template_view === '') {
            return ['attempted' => FALSE, 'sent' => FALSE, 'error' => 'Plantilla de bienvenida no disponible.'];
        }

        $this->load->library('email');
        $this->email->clear(TRUE);
        $this->email->initialize($email_config['config'] ?? []);
        $this->email->from((string)$email_config['from_email'], (string)($email_config['from_name'] ?? 'Notificaciones del Sistema'));

        if (!empty($email_config['reply_to'])) {
            $this->email->reply_to((string)$email_config['reply_to'], (string)($email_config['from_name'] ?? 'Notificaciones del Sistema'));
        }

        $this->email->to($correo);
        $this->email->subject($this->Configuracion_correos_model->get_template_subject(
            'metronic_welcome',
            FALSE,
            'Bienvenido a ' . (string)($this->layout_data['app_name'] ?? 'Mi Sistema')
        ));
        $this->email->message($this->Configuracion_correos_model->render_template(
            'metronic_welcome',
            $this->_build_welcome_email_view_data($usuario, $email_config)
        ));

        $ok = $this->email->send(FALSE);
        if ($ok) {
            return ['attempted' => TRUE, 'sent' => TRUE, 'error' => ''];
        }

        return [
            'attempted' => TRUE,
            'sent' => FALSE,
            'error' => trim(strip_tags((string)$this->email->print_debugger(['headers']))),
        ];
    }

    private function _build_welcome_email_view_data(array $usuario, array $email_config)
    {
        $app_name = (string)($this->layout_data['app_name'] ?? 'Mi Sistema');
        $nombre_completo = $this->_nombre_completo_usuario($usuario);
        $from_name = (string)($email_config['from_name'] ?? 'Notificaciones del Sistema');
        $from_email = (string)($email_config['from_email'] ?? '');
        $logo_url = !empty($this->layout_data['theme_settings']['login_logo_url'])
            ? $this->layout_data['theme_settings']['login_logo_url']
            : base_url('assets/media/logos/default-dark.svg');

        return [
            'email_title' => 'Bienvenido a ' . $app_name,
            'email_heading' => 'Bienvenido a ' . $app_name,
            'app_name' => $app_name,
            'email_preheader' => 'Tu cuenta ya fue creada y está lista para usar.',
            'email_logo_url' => $logo_url,
            'recipient_name' => $nombre_completo,
            'intro_lines' => [
                'Tu cuenta fue creada correctamente dentro de ' . $app_name . '.',
                'Usuario asignado: @' . (string)($usuario['usu_usuario'] ?? ''),
            ],
            'steps' => [
                ['title' => 'Inicia sesión', 'description' => 'Accede con tu usuario y la contraseña asignada por el administrador.'],
                ['title' => 'Verifica tus datos', 'description' => 'Confirma que tu correo y celular estén correctos dentro del sistema.'],
                ['title' => 'Explora tus módulos', 'description' => 'Revisa las opciones habilitadas según tu rol actual.'],
            ],
            'action_label' => 'Ir al sistema',
            'action_url' => base_url('login'),
            'email_help_text' => $from_email !== '' ? ('Soporte: ' . $from_name . ' <' . $from_email . '>') : ('Soporte: ' . $from_name),
            'email_footer' => (string)($this->layout_data['theme_settings']['footer_text'] ?? 'Este mensaje fue generado automáticamente por el sistema.'),
        ];
    }

    private function _es_fecha_valida($fecha)
    {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt && $dt->format('Y-m-d') === $fecha;
    }

    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $nombre = $this->_nombre_completo_usuario($item);
            $iniciales = strtoupper(substr($nombre, 0, 1));
            $es_actual = ((string)$item['usu_id'] === (string)($this->usuario['usu_id'] ?? '0'));
            $puede_gestionar = $this->_puede_gestionar_usuario($item);
            $foto = $this->_resolver_foto_usuario_url($item['usu_id'], $item['usu_foto'] ?? '');

            $avatar = $foto !== ''
                ? '<img src="' . htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" class="object-fit-cover" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">'
                    . '<span class="symbol-label bg-light-primary text-primary fw-bold" style="display:none;">' . htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="symbol-label bg-light-primary text-primary fw-bold">' . htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') . '</span>';

            $usuarioHtml = ''
                . '<div class="d-flex align-items-center">'
                . '    <div class="symbol symbol-45px me-3">' . $avatar . '</div>'
                . '    <div class="d-flex flex-column">'
                . '        <span class="fw-bold text-dark fs-6">'
                .              htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')
                .              ($es_actual ? '<span class="badge badge-light-info fw-bold ms-2">Tú</span>' : '')
                . '        </span>'
                . '        <span class="text-muted fw-semibold fs-7">@' . htmlspecialchars($item['usu_usuario'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '    </div>'
                . '</div>';

            $rolHtml = !empty($item['rol_nombre'])
                ? '<span class="badge ' . (($item['rol_activo'] ?? '0') === '1' ? 'badge-light-primary' : 'badge-light-warning') . ' fw-bold">' . htmlspecialchars($item['rol_nombre'], ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="badge badge-light-danger fw-bold">Sin rol</span>';

            $estadoHtml = ($item['usu_status'] ?? '0') === '1'
                ? '<span class="badge badge-light-success fw-bold px-3 py-2"><span class="bullet bullet-dot bg-success me-1"></span>Activo</span>'
                : '<span class="badge badge-light-danger fw-bold px-3 py-2"><span class="bullet bullet-dot bg-danger me-1"></span>Inactivo</span>';

            if ($puede_gestionar) {
                $accionesHtml = ''
                    . '<div class="d-flex justify-content-end align-items-center gap-2">'
                    . '    <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-editar shadow-xs" data-id="' . htmlspecialchars((string)$item['usu_id'], ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar" aria-label="Editar">'
                    . '        <i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '    </button>'
                    . '    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar shadow-xs" data-id="' . htmlspecialchars((string)$item['usu_id'], ENT_QUOTES, 'UTF-8') . '" data-nombre="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" data-self="' . ($es_actual ? '1' : '0') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar" aria-label="Eliminar">'
                    . '        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>'
                    . '    </button>'
                    . '</div>';
            } else {
                $accionesHtml = '<div class="d-flex justify-content-end"><span class="badge badge-light-dark fw-bold px-3 py-2"><i class="ki-duotone ki-lock fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>Protegido</span></div>';
            }

            $data[] = [
                'usuario' => $usuarioHtml,
                'correo' => htmlspecialchars((string)$item['usu_correo'], ENT_QUOTES, 'UTF-8'),
                'rol' => $rolHtml,
                'estado' => $estadoHtml,
                'acciones' => $accionesHtml,
            ];
        }

        return $data;
    }

    private function _nombre_completo_usuario(array $usuario)
    {
        $nombre = $this->_limpiar_texto_simple((string)($usuario['usu_nombre'] ?? ''));
        $apellido = $this->_limpiar_texto_simple((string)($usuario['usu_apellido'] ?? ''));

        if ($nombre !== '' && $apellido !== '') {
            return trim($nombre . ' ' . $apellido);
        }

        return $nombre !== '' ? $nombre : 'Usuario';
    }

    private function _limpiar_texto_simple($texto)
    {
        return trim(preg_replace('/\s+/', ' ', (string)$texto));
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
            return ['ok' => FALSE, 'error' => 'Archivo inválido para la foto.'];
        }

        $info = @getimagesize($tmp);
        if ($info === FALSE || empty($info['mime'])) {
            return ['ok' => FALSE, 'error' => 'El archivo debe ser una imagen válida.'];
        }

        $mime = $info['mime'];
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
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

        // Si GD está disponible, re-escalar y optimizar la imagen
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

        // Si GD no está disponible o falló el guardado GD, mover archivo validado nativamente
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