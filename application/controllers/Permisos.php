<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Permisos
 * Administración de roles y asignación de permisos por menú.
 * URL base: /roles-y-permisos
 */
class Permisos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Permisos_model');
        $this->page_title = 'Roles y Permisos';
        $this->breadcrumb = [
            ['titulo' => 'Inicio',        'url' => base_url('dashboard')],
            ['titulo' => 'Roles y Permisos', 'url' => ''],
        ];
    }

    // ─────────────────────────────────────────────────────────
    /** GET — Lista de roles */
    public function index()
    {
        $roles = $this->Permisos_model->get_todos();

        // Agregar conteo de usuarios por rol
        foreach ($roles as &$rol) {
            $rol['total_usuarios'] = $this->Permisos_model->count_usuarios($rol['per_id']);
        }

        $this->render('pages/permisos/index', ['roles' => $roles]);
    }

    // ─────────────────────────────────────────────────────────
    /** GET AJAX — Datos de un rol para el modal de edición */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());

        $rol = $this->Permisos_model->get_por_id($id);
        if (empty($rol)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rol no encontrado.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $rol]);
    }

    // ─────────────────────────────────────────────────────────
    /** POST AJAX — Crear rol */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('incluir', 'No tienes permiso para crear roles.');

        $data    = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $id = $this->Permisos_model->crear($data);

        if ($id) {
            $this->Permisos_model->bump_permisos_version();
            $this->bitacora('ROL_CREAR', 'Rol creado: ' . $data['per_nombre']);
            $this->json(['ok' => TRUE, 'msg' => 'Rol creado correctamente.', 'id' => $id]);
        } else {
            $this->json(['ok' => FALSE, 'msg' => 'Error al guardar.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    /** POST AJAX — Actualizar rol */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para modificar roles.');
        if ($this->Menu_model->rol_es_master($id) && !$this->es_master) {
            return $this->json(['ok' => FALSE, 'msg' => 'Solo un rol con cobertura total puede modificar el rol maestro.'], 403);
        }

        $rol = $this->Permisos_model->get_por_id($id);
        if (empty($rol)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rol no encontrado.'], 404);
        }

        $data    = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if ($this->Permisos_model->actualizar($id, $data)) {
            $this->Permisos_model->bump_permisos_version();
            $this->bitacora('ROL_EDITAR', 'Rol editado: ' . $data['per_nombre']);
            $this->json(['ok' => TRUE, 'msg' => 'Rol actualizado correctamente.']);
        } else {
            $this->json(['ok' => FALSE, 'msg' => 'Error al actualizar.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    /** POST AJAX — Eliminar rol */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar roles.');
        if ($this->Menu_model->rol_es_master($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'El rol con cobertura total no puede eliminarse.'], 403);
        }

        $rol = $this->Permisos_model->get_por_id($id);
        if (empty($rol)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rol no encontrado.'], 404);
        }

        $total_usuarios = $this->Permisos_model->count_usuarios($id);
        if ($total_usuarios > 0) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => "No se puede eliminar. Hay {$total_usuarios} usuario(s) asignado(s) a este rol.",
            ], 422);
        }

        if ($this->Permisos_model->eliminar($id)) {
            $this->Permisos_model->bump_permisos_version();
            $this->bitacora('ROL_ELIMINAR', 'Rol eliminado: ' . $rol['per_nombre']);
            $this->json(['ok' => TRUE, 'msg' => 'Rol eliminado correctamente.']);
        } else {
            $this->json(['ok' => FALSE, 'msg' => 'Error al eliminar.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    /**
     * GET — Página de asignación de permisos de un rol.
     * Muestra árbol de menús con checkboxes CRUD.
     */
    public function asignar($id = 0)
    {
        $rol = $this->Permisos_model->get_por_id($id);
        if (empty($rol)) redirect($this->module_path());

        $this->page_title = 'Permisos: ' . $rol['per_nombre'];
        $this->breadcrumb = [
            ['titulo' => 'Inicio',           'url' => base_url('dashboard')],
            ['titulo' => 'Roles y Permisos', 'url' => base_url($this->module_path())],
            ['titulo' => $rol['per_nombre'], 'url' => ''],
        ];

        $datos = [
            'rol'      => $rol,
            'menus'    => $this->Permisos_model->get_menus_arbol(),
            'permisos' => $this->Permisos_model->get_permisos_rol($id),
        ];

        $this->render('pages/permisos/asignar', $datos);
    }

    // ─────────────────────────────────────────────────────────
    /** POST AJAX — Guardar permisos de un rol */
    public function guardar_permisos($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para asignar permisos.');

        // Comprobación de seguridad 1: Jerarquía de roles
        if (!$this->_puede_gestionar_rol($id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No tienes permisos suficientes para gestionar los privilegios de este rol.'], 403);
        }

        $rol = $this->Permisos_model->get_por_id($id);
        if (empty($rol)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rol no encontrado.'], 404);
        }

        // Recibir permisos desde POST (FormData) o raw JSON
        $raw_permisos = $this->input->post('permisos');
        if ($raw_permisos !== NULL && $raw_permisos !== '') {
            $permisos = is_array($raw_permisos) ? $raw_permisos : json_decode($raw_permisos, TRUE);
        } else {
            $input    = json_decode($this->input->raw_input_stream, TRUE);
            $permisos = $input['permisos'] ?? [];
        }
        $permisos = is_array($permisos) ? $permisos : [];

        // Comprobación de seguridad 2: Delegación segura de privilegios
        // Si el usuario no tiene cobertura total, solo puede otorgar permisos
        // sobre menús a los que su propio rol tiene acceso.
        if (!$this->es_master) {
            $mis_menus_propios = $this->Permisos_model->get_permisos_rol((int)$this->usuario['usu_tipoper']);
            foreach ($permisos as $m_id => $flags) {
                if (!isset($mis_menus_propios[$m_id]) || empty($mis_menus_propios[$m_id]['mis_activo'])) {
                    unset($permisos[$m_id]); // Descarta cualquier intento de inyección de menús no poseídos
                }
            }
        }

        $guardado = $this->Permisos_model->guardar_permisos($id, $permisos, $rol['per_tipo']);
        if (!$guardado) {
            return $this->json(['ok' => FALSE, 'msg' => 'Error al guardar los permisos en la base de datos.'], 500);
        }

        $total_modulos = count($permisos);
        $this->Permisos_model->bump_permisos_version();
        $this->bitacora('ROL_PERMISOS', "Permisos actualizados para rol: {$rol['per_nombre']} (ID: {$id}) | Módulos autorizados: {$total_modulos}");
        $this->json(['ok' => TRUE, 'msg' => 'Permisos guardados correctamente.']);
    }

    // ─────────────────────────────────────────────────────────
    private function _puede_gestionar_rol($rol_id)
    {
        if ($this->es_master) {
            return TRUE;
        }

        if ($this->Menu_model->rol_es_master($rol_id)) {
            return FALSE;
        }

        $cobertura_objetivo = $this->Menu_model->cobertura_rol($rol_id);
        $cobertura_actor    = $this->Menu_model->cobertura_rol($this->usuario['usu_tipoper'] ?? 0);

        return $cobertura_objetivo < $cobertura_actor;
    }

    // ─────────────────────────────────────────────────────────
    private function _recoger_post()
    {
        return [
            'per_nombre'      => $this->input->post('per_nombre',      TRUE),
            'per_descripcion' => $this->input->post('per_descripcion', TRUE),
            'per_tipo'        => $this->input->post('per_tipo',        TRUE) ?? '0',
            'per_activo'      => $this->input->post('per_activo',      TRUE) ?? '1',
        ];
    }

    private function _validar(array $data)
    {
        $errores = [];
        if (empty($data['per_nombre'])) {
            $errores['per_nombre'] = 'El nombre del rol es requerido.';
        } elseif (strlen($data['per_nombre']) > 100) {
            $errores['per_nombre'] = 'El nombre no puede superar 100 caracteres.';
        }
        return $errores;
    }
}
