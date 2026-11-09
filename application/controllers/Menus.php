<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menus
 * Administración de menús del sistema (cab_menu).
 * URL: /creacion-de-menus
 */
class Menus extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menus_model');
        $this->page_title = 'Creación de Menús';
        $this->breadcrumb = [
            ['titulo' => 'Inicio',            'url' => base_url('dashboard')],
            ['titulo' => 'Creación de Menús', 'url' => ''],
        ];
    }

    // ─────────────────────────────────────────────────────────
    /**
     * GET /creacion-de-menus
     * Lista principal con árbol de menús.
     */
    public function index()
    {
        $datos = [
            'arbol'  => $this->Menus_model->get_arbol(),
            'raices' => $this->Menus_model->get_raices(),
        ];
        $this->render('pages/menus/index', $datos);
    }

    // ─────────────────────────────────────────────────────────
    /**
     * GET /creacion-de-menus/datos/:id  (AJAX)
     * Devuelve los datos de un menú para editar en el modal.
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());

        $menu = $this->Menus_model->get_por_id($id);

        if (empty($menu)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Menú no encontrado.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $menu]);
    }

    // ─────────────────────────────────────────────────────────
    /**
     * POST /creacion-de-menus/crear  (AJAX)
     * Inserta un nuevo menú o submenú.
     */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('incluir', 'No tienes permiso para crear menús.');

        $data = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $id = $this->Menus_model->crear($data);

        if ($id) {
            $this->bitacora('MENU_CREAR', 'Menú creado: ' . $data['men_nombre'] . ' (ID: ' . $id . ')');
            $this->json([
                'ok'  => TRUE,
                'msg' => 'Menú creado correctamente.',
                'id'  => $id,
            ]);
        } else {
            $this->json(['ok' => FALSE, 'msg' => 'Error al guardar en la base de datos.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    /**
     * POST /creacion-de-menus/actualizar/:id  (AJAX)
     * Modifica un menú existente.
     */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para modificar menús.');

        $menu = $this->Menus_model->get_por_id($id);
        if (empty($menu)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Menú no encontrado.'], 404);
        }

        $data    = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if ($this->Menus_model->actualizar($id, $data)) {
            $this->bitacora('MENU_EDITAR', 'Menú editado: ' . $data['men_nombre'] . ' (ID: ' . $id . ')');
            $this->json(['ok' => TRUE, 'msg' => 'Menú actualizado correctamente.']);
        } else {
            $this->json(['ok' => FALSE, 'msg' => 'Error al actualizar.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    /**
     * POST /creacion-de-menus/eliminar/:id  (AJAX)
     * Elimina un menú y sus submenús en cascada.
     */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('eliminar', 'No tienes permiso para eliminar menús.');

        $menu = $this->Menus_model->get_por_id($id);
        if (empty($menu)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Menú no encontrado.'], 404);
        }

        $tiene_hijos = $this->Menus_model->tiene_hijos($id);

        if ($this->Menus_model->eliminar($id)) {
            $msg = $tiene_hijos
                ? 'Menú y sus submenús eliminados correctamente.'
                : 'Menú eliminado correctamente.';

            $this->bitacora('MENU_ELIMINAR', 'Menú eliminado: ' . $menu['men_nombre'] . ' (ID: ' . $id . ')');
            $this->json(['ok' => TRUE, 'msg' => $msg]);
        } else {
            $this->json(['ok' => FALSE, 'msg' => 'Error al eliminar.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    /**
     * POST /creacion-de-menus/reordenar  (AJAX)
     * Actualiza el orden de los ítems (drag & drop).
     * Espera JSON: [{"id":1,"orden":1}, {"id":2,"orden":2}, ...]
     */
    public function reordenar()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para reordenar menús.');

        $items = json_decode($this->input->raw_input_stream, TRUE);

        if (empty($items) || !is_array($items)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Datos inválidos.'], 422);
        }

        $this->Menus_model->actualizar_orden($items);
        $this->json(['ok' => TRUE, 'msg' => 'Orden actualizado.']);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /** Recoge y limpia los campos del formulario */
    private function _recoger_post()
    {
        return [
            'men_nombre'      => $this->input->post('men_nombre',      TRUE),
            'men_descripcion' => $this->input->post('men_descripcion', TRUE),
            'men_url'         => $this->input->post('men_url',         TRUE),
            'men_urlamigable' => $this->input->post('men_urlamigable', TRUE),
            'men_imgmenu'     => $this->input->post('men_imgmenu',     TRUE),
            'men_orden'       => (int)$this->input->post('men_orden'),
            'sub_men_id'      => (int)$this->input->post('sub_men_id'),
            'men_externo'     => $this->input->post('men_externo', TRUE) ?? '0',
            'men_inicio'      => $this->input->post('men_inicio', TRUE) ?? '0',
        ];
    }

    /** Valida los campos requeridos */
    private function _validar(array $data)
    {
        $errores = [];

        if (empty($data['men_nombre'])) {
            $errores['men_nombre'] = 'El nombre del menú es requerido.';
        } elseif (strlen($data['men_nombre']) > 100) {
            $errores['men_nombre'] = 'El nombre no puede superar 100 caracteres.';
        }

        if (empty($data['men_url'])) {
            $errores['men_url'] = 'La URL es requerida.';
        }

        if (!in_array((string)$data['men_inicio'], ['0', '1'], TRUE)) {
            $errores['men_inicio'] = 'El indicador de inicio no es válido.';
        }

        if ((string)$data['men_inicio'] === '1' && (string)$data['men_externo'] === '1') {
            $errores['men_inicio'] = 'El menú predeterminado de inicio no puede ser externo.';
        }

        return $errores;
    }
}
