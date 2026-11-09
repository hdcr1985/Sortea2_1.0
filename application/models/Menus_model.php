<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menus_model
 * CRUD sobre la tabla cab_menu.
 * ─────────────────────────────────────────────────────────────
 * OJO: Este modelo es para el ADMINISTRADOR de menús.
 *      El Menu_model (sidebar) sigue siendo el que lee
 *      la estructura filtrada por permisos.
 */
class Menus_model extends CI_Model
{
    private $tabla = 'cab_menu';

    // ─────────────────────────────────────────────────────────
    /**
     * Todos los menús en lista plana ordenada.
     * Útil para la tabla de administración.
     */
    public function get_todos()
    {
        return $this->db
            ->order_by('men_tipo',  'ASC')
            ->order_by('sub_men_id','ASC')
            ->order_by('men_orden', 'ASC')
            ->get($this->tabla)
            ->result_array();
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Árbol completo (sin filtro de permisos) para mostrar
     * en la tabla con jerarquía visual.
     */
    public function get_arbol()
    {
        $items = $this->get_todos();
        return $this->_construir_arbol($items);
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Solo los menús raíz (men_tipo = 0).
     * Para el <select> de "Menú padre" al crear submenú.
     */
    public function get_raices()
    {
        return $this->db
            ->where('men_tipo', 0)
            ->order_by('men_orden', 'ASC')
            ->get($this->tabla)
            ->result_array();
    }

    // ─────────────────────────────────────────────────────────
    /** Un menú por ID */
    public function get_por_id($id)
    {
        return $this->db
            ->where('men_id', (int)$id)
            ->get($this->tabla)
            ->row_array();
    }

    // ─────────────────────────────────────────────────────────
    /** Crear menú. Devuelve el ID insertado o FALSE. */
    public function crear(array $data)
    {
        $data['men_fecha'] = date('Y-m-d');
        $data['men_hora']  = date('H:i:s');

        // men_tipo: si tiene padre → 1 (submenú), si no → 0 (raíz)
        $data['men_tipo']    = ($data['sub_men_id'] > 0) ? 1 : 0;
        $data['sub_men_id']  = (int)$data['sub_men_id'];

        // Orden automático si no se especificó
        if (empty($data['men_orden'])) {
            $data['men_orden'] = $this->_siguiente_orden($data['sub_men_id']);
        }

        if ($this->_tiene_campo_inicio()) {
            $data['men_inicio'] = ((string)($data['men_inicio'] ?? '0') === '1') ? '1' : '0';

            if ($data['men_inicio'] === '1') {
                $this->_limpiar_menu_inicio();
            }
        } else {
            unset($data['men_inicio']);
        }

        if ($this->db->insert($this->tabla, $data)) {
            return $this->db->insert_id();
        }
        return FALSE;
    }

    // ─────────────────────────────────────────────────────────
    /** Actualizar menú. Devuelve TRUE/FALSE. */
    public function actualizar($id, array $data)
    {
        // Recalcular men_tipo según sub_men_id
        $data['men_tipo']   = ((int)$data['sub_men_id'] > 0) ? 1 : 0;
        $data['sub_men_id'] = (int)$data['sub_men_id'];

        if ($this->_tiene_campo_inicio()) {
            $data['men_inicio'] = ((string)($data['men_inicio'] ?? '0') === '1') ? '1' : '0';

            if ($data['men_inicio'] === '1') {
                $this->_limpiar_menu_inicio((int)$id);
            }
        } else {
            unset($data['men_inicio']);
        }

        return $this->db
            ->where('men_id', (int)$id)
            ->update($this->tabla, $data);
    }

    /** Devuelve la ruta del menú configurado como inicio, filtrado por permisos del rol. */
    public function get_url_inicio_login($rol_id)
    {
        if (!$this->_tiene_campo_inicio()) {
            return 'dashboard';
        }

        $menu = $this->db
            ->select('m.men_url, m.men_urlamigable')
            ->from($this->tabla . ' m')
            ->join('cab_permisologia p', 'p.mis_menu = m.men_id', 'inner')
            ->join('cab_grupopermi g', 'g.per_id = p.mis_permiso', 'inner')
            ->where('m.men_inicio', '1')
            ->where('m.men_externo', '0')
            ->where('p.mis_activo', '1')
            ->where('g.per_activo', '1')
            ->where('g.per_id', $rol_id)
            ->order_by('m.men_id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($menu)) {
            return 'dashboard';
        }

        $amigable = trim((string)($menu['men_urlamigable'] ?? ''), '/');
        if ($amigable !== '') {
            return $amigable;
        }

        $url = trim((string)($menu['men_url'] ?? ''), '/');
        return $url !== '' ? $url : 'dashboard';
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Eliminar menú.
     * Si tiene submenús, primero los elimina (cascada manual).
     */
    public function eliminar($id)
    {
        $id = (int)$id;

        // 1. Eliminar hijos recursivamente primero
        $hijos = $this->db
            ->where('sub_men_id', $id)
            ->get($this->tabla)
            ->result_array();

        foreach ($hijos as $hijo) {
            $this->eliminar($hijo['men_id']);
        }

        // 2. Obtener los IDs de permisología asociados a este menú
        $permisos = $this->db
            ->select('mis_id')
            ->where('mis_menu', $id)
            ->get('cab_permisologia')
            ->result_array();

        foreach ($permisos as $permiso) {
            // 3. Eliminar acciones de cada permiso en cab_permiaccion
            $this->db
                ->where('acc_id', $permiso['mis_id'])
                ->delete('cab_permiaccion');
        }

        // 4. Eliminar registros de cab_permisologia
        $this->db
            ->where('mis_menu', $id)
            ->delete('cab_permisologia');

        // 5. Finalmente eliminar el menú de cab_menu
        return $this->db
            ->where('men_id', $id)
            ->delete($this->tabla);
    }

    // ─────────────────────────────────────────────────────────
    /** Verifica si el menú tiene submenús. */
    public function tiene_hijos($id)
    {
        return $this->db
            ->where('sub_men_id', (int)$id)
            ->count_all_results($this->tabla) > 0;
    }

    // ─────────────────────────────────────────────────────────
    /** Actualizar orden de múltiples ítems (drag & drop). */
    public function actualizar_orden(array $items)
    {
        foreach ($items as $item) {
            $this->db
                ->where('men_id', (int)$item['id'])
                ->update($this->tabla, ['men_orden' => (int)$item['orden']]);
        }
        return TRUE;
    }
    // ─────────────────────────────────────────────────────────
    /** Devuelve el siguiente número de orden dentro de un grupo. */
    private function _siguiente_orden($sub_men_id)
    {
        $this->db->select_max('men_orden', 'max_orden');
        $this->db->where('sub_men_id', (int)$sub_men_id);
        $row = $this->db->get($this->tabla)->row_array();
        return ($row['max_orden'] ?? 0) + 1;
    }

    /** Mantiene un solo menú marcado como inicio. */
    private function _limpiar_menu_inicio($ignorar_id = 0)
    {
        if ((int)$ignorar_id > 0) {
            $this->db->where('men_id !=', (int)$ignorar_id);
        }

        $this->db->update($this->tabla, ['men_inicio' => '0']);
    }

    private function _tiene_campo_inicio()
    {
        return $this->db->field_exists('men_inicio', $this->tabla);
    }

    // ─────────────────────────────────────────────────────────
    /** Construye árbol jerárquico para visualización. */
    private function _construir_arbol(array $items, $parent_id = 0)
    {
        $rama = [];
        foreach ($items as $item) {
            if ((int)$item['sub_men_id'] === (int)$parent_id) {
                $item['hijos'] = $this->_construir_arbol($items, $item['men_id']);
                $rama[] = $item;
            }
        }
        return $rama;
    }


}
