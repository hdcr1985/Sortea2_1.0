<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Permisos_model
 * ─────────────────────────────────────────────────────────────
 * Maneja:
 *   cab_grupopermi   → Roles/grupos
 *   cab_permisologia → Qué menús tiene asignados cada rol
 *   cab_permiaccion  → Qué acciones CRUD tiene sobre cada menú
 */
class Permisos_model extends CI_Model
{
    // ═══════════════════════════════════════════════════════════
    // CRUD DE ROLES (cab_grupopermi)
    // ═══════════════════════════════════════════════════════════

    /** Lista todos los roles */
    public function get_todos()
    {
        return $this->db
            ->order_by('per_nombre', 'ASC')
            ->get('cab_grupopermi')
            ->result_array();
    }

    /** Un rol por ID */
    public function get_por_id($id)
    {
        return $this->db
            ->where('per_id', (int)$id)
            ->get('cab_grupopermi')
            ->row_array();
    }

    /** Crear rol */
    public function crear(array $data)
    {
        $data['per_fecha'] = date('Y-m-d');
        $data['per_hora']  = date('H:i:s');

        if ($this->db->insert('cab_grupopermi', $data)) {
            return $this->db->insert_id();
        }
        return FALSE;
    }

    /** Actualizar rol */
    public function actualizar($id, array $data)
    {
        return $this->db
            ->where('per_id', (int)$id)
            ->update('cab_grupopermi', $data);
    }

    /**
     * Eliminar rol y todos sus permisos asociados.
     * Orden: cab_permiaccion → cab_permisologia → cab_grupopermi
     */
    public function eliminar($id)
    {
        $id = (int)$id;

        // 1. Obtener IDs de permisología del rol
        $permisos = $this->db
            ->select('mis_id')
            ->where('mis_permiso', $id)
            ->get('cab_permisologia')
            ->result_array();

        // 2. Eliminar acciones CRUD
        foreach ($permisos as $p) {
            $this->db->where('acc_id', $p['mis_id'])->delete('cab_permiaccion');
        }

        // 3. Eliminar asignaciones de menús
        $this->db->where('mis_permiso', $id)->delete('cab_permisologia');

        // 4. Eliminar el rol
        return $this->db->where('per_id', $id)->delete('cab_grupopermi');
    }

    /** Contar usuarios que usan este rol */
    public function count_usuarios($rol_id)
    {
        return $this->db
            ->where('usu_tipoper', $rol_id)
            ->count_all_results('cab_usuarios');
    }

    // ═══════════════════════════════════════════════════════════
    // ASIGNACIÓN DE PERMISOS (cab_permisologia + cab_permiaccion)
    // ═══════════════════════════════════════════════════════════

    /**
     * Obtiene todos los menús con sus permisos para un rol dado.
     * Devuelve array indexado por men_id con los flags CRUD.
     *
     * @param  int   $rol_id
     * @return array [ men_id => [ mis_activo, acc_consultar, acc_incluir, ... ] ]
     */
    public function get_permisos_rol($rol_id)
    {
        $this->db->select('p.mis_id, p.mis_menu, p.mis_activo,
                           a.acc_consultar, a.acc_incluir, a.acc_modificar,
                           a.acc_eliminar, a.acc_imprimir, a.acc_extra1, a.acc_extra2');
        $this->db->from('cab_permisologia p');
        $this->db->join('cab_permiaccion a', 'a.acc_id = p.mis_id', 'left');
        $this->db->where('p.mis_permiso', (int)$rol_id);

        $rows = $this->db->get()->result_array();

        // Indexar por men_id para fácil acceso en la vista
        $permisos = [];
        foreach ($rows as $row) {
            $permisos[$row['mis_menu']] = $row;
        }
        return $permisos;
    }

    /**
     * Guarda TODOS los permisos de un rol de una vez de forma transaccional y segura.
     * Recibe array de menús con sus flags CRUD.
     * Valida existencia real en cab_menu para evitar inyecciones de menús ficticios.
     *
     * @param  int   $rol_id
     * @param  array $permisos  [ men_id => [ acceso, consultar, incluir, ... ] ]
     * @param  int   $per_tipo  tipo del rol (per_tipo de cab_grupopermi)
     * @return bool
     */
    public function guardar_permisos($rol_id, array $permisos, $per_tipo)
    {
        $rol_id = (int)$rol_id;

        // Iniciar transacción atómica
        $this->db->trans_start();

        // 1. Limpiar permisos anteriores del rol
        $anteriores = $this->db
            ->select('mis_id')
            ->where('mis_permiso', $rol_id)
            ->get('cab_permisologia')
            ->result_array();

        if (!empty($anteriores)) {
            $mis_ids = array_column($anteriores, 'mis_id');
            // Eliminar acciones asociadas
            $this->db->where_in('acc_id', $mis_ids)->delete('cab_permiaccion');
            // Eliminar asignación de permisología
            $this->db->where('mis_permiso', $rol_id)->delete('cab_permisologia');
        }

        // 2. Obtener lista blanca de menús existentes en la base de datos
        $menus_bd = $this->db
            ->select('men_id, men_tipo')
            ->get('cab_menu')
            ->result_array();

        $mapa_menus = [];
        foreach ($menus_bd as $m) {
            $mapa_menus[(int)$m['men_id']] = (int)$m['men_tipo'];
        }

        // 3. Insertar los nuevos permisos verificando la lista blanca
        foreach ($permisos as $men_id => $flags) {
            $men_id = (int)$men_id;

            // Validación de seguridad 1: El menú debe existir en la base de datos
            if (!isset($mapa_menus[$men_id])) {
                continue;
            }

            // Validación de seguridad 2: Solo procesar si el acceso está expresamente habilitado
            if (empty($flags['acceso'])) {
                continue;
            }

            $mis_id   = $rol_id . '_' . $men_id; // PK compuesta estricta (ADR-025)
            $men_tipo = $mapa_menus[$men_id];

            // Insertar en cab_permisologia
            $this->db->insert('cab_permisologia', [
                'mis_id'       => $mis_id,
                'mis_permiso'  => $rol_id,
                'mis_menu'     => $men_id,
                'mis_tipomenu' => $men_tipo,
                'mis_tipoper'  => $per_tipo,
                'mis_activo'   => '1',
            ]);

            // Validación de seguridad 3: Casteo estricto booleano contra lista blanca de columnas CRUD
            $this->db->insert('cab_permiaccion', [
                'acc_id'        => $mis_id,
                'acc_permiso'   => $rol_id,
                'acc_consultar' => (!empty($flags['consultar']) && ((int)$flags['consultar'] === 1 || $flags['consultar'] === true)) ? 1 : 0,
                'acc_incluir'   => (!empty($flags['incluir'])   && ((int)$flags['incluir'] === 1   || $flags['incluir'] === true))   ? 1 : 0,
                'acc_modificar' => (!empty($flags['modificar']) && ((int)$flags['modificar'] === 1 || $flags['modificar'] === true)) ? 1 : 0,
                'acc_eliminar'  => (!empty($flags['eliminar'])  && ((int)$flags['eliminar'] === 1  || $flags['eliminar'] === true))  ? 1 : 0,
                'acc_imprimir'  => (!empty($flags['imprimir'])  && ((int)$flags['imprimir'] === 1  || $flags['imprimir'] === true))  ? 1 : 0,
                'acc_extra1'    => (!empty($flags['extra1'])    && ((int)$flags['extra1'] === 1    || $flags['extra1'] === true))    ? 1 : 0,
                'acc_extra2'    => (!empty($flags['extra2'])    && ((int)$flags['extra2'] === 1    || $flags['extra2'] === true))    ? 1 : 0,
            ]);
        }

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    // ═══════════════════════════════════════════════════════════
    // MENÚS (para la vista de asignación)
    // ═══════════════════════════════════════════════════════════

    /**
     * Invalida el snapshot de menús de todas las sesiones. MY_Controller
     * compara esta versión con la guardada al iniciar sesión y reconstruye
     * la estructura cuando cambia.
     */
    public function bump_permisos_version()
    {
        if (!$this->db->table_exists('cab_configuracion_general')) {
            return;
        }

        $version = (string)microtime(TRUE);
        $payload = [
            'cfg_valor'      => $version,
            'cfg_tipo'       => 'text',
            'cfg_updated_at' => date('Y-m-d H:i:s'),
        ];

        $existe = $this->db
            ->where('cfg_clave', 'permisos_version')
            ->count_all_results('cab_configuracion_general') > 0;

        if ($existe) {
            $this->db->where('cfg_clave', 'permisos_version')
                ->update('cab_configuracion_general', $payload);
            return;
        }

        $payload['cfg_clave']      = 'permisos_version';
        $payload['cfg_created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('cab_configuracion_general', $payload);
    }

    /** Todos los menús en árbol para mostrar en la vista de permisos */
    public function get_menus_arbol()
    {
        $items = $this->db
            ->order_by('men_tipo',  'ASC')
            ->order_by('men_orden', 'ASC')
            ->get('cab_menu')
            ->result_array();

        return $this->_arbol($items);
    }

    private function _arbol(array $items, $parent_id = 0)
    {
        $rama = [];
        foreach ($items as $item) {
            if ((int)$item['sub_men_id'] === (int)$parent_id) {
                $item['hijos'] = $this->_arbol($items, $item['men_id']);
                $rama[] = $item;
            }
        }
        return $rama;
    }
}
