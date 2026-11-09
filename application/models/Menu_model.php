<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menu_model
 * ─────────────────────────────────────────────────────────────
 * Versión actualizada — agrega el método usuario_tiene_acceso()
 * que usa MY_Controller para verificar permisos en cada request.
 */
class Menu_model extends CI_Model
{
    // ─────────────────────────────────────────────────────────
    /**
     * Árbol de menús visible para el usuario según su rol.
     */
    public function get_arbol_por_usuario($usu_id, $usu_tipoper)
    {
        $this->db->select('m.*');
        $this->db->from('cab_menu m');
        $this->db->join('cab_permisologia p', 'p.mis_menu = m.men_id',     'inner');
        $this->db->join('cab_grupopermi g',   'g.per_id  = p.mis_permiso', 'inner');
        $this->db->where('g.per_id',    $usu_tipoper);
        $this->db->where('p.mis_activo', '1');
        $this->db->where('g.per_activo', '1');
        $this->db->order_by('m.men_tipo',  'ASC');
        $this->db->order_by('m.men_orden', 'ASC');

        $items = $this->db->get()->result_array();
        return $this->_construir_arbol($items);
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Verifica si el rol del usuario tiene acceso a la URL actual.
     *
     * Compara la URL actual contra men_url Y men_urlamigable
     * de los menús que tiene asignados su rol en cab_permisologia.
     *
     * URLs fijas (login, logout, dashboard) se excluyen aquí —
     * esas las maneja directamente MY_Controller.
     *
     * @param  string $usu_tipoper  Rol del usuario
     * @param  string $url_actual   URI actual (ej: "creacion-de-menus")
     * @return bool
     */
    public function usuario_tiene_acceso($usu_tipoper, $url_actual)
    {
        $url = $this->db->escape_str(trim($url_actual, '/'));

        $this->db->select('m.men_id');
        $this->db->from('cab_menu m');
        $this->db->join('cab_permisologia p', 'p.mis_menu = m.men_id',     'inner');
        $this->db->join('cab_grupopermi g',   'g.per_id  = p.mis_permiso', 'inner');
        $this->db->where('g.per_id',     $usu_tipoper);
        $this->db->where('p.mis_activo', '1');
        $this->db->where('g.per_activo', '1');
        // Buscar por URL de controlador o URL amigable
        $this->db->where(
            "(TRIM(BOTH '/' FROM m.men_url) = '{$url}'
              OR TRIM(BOTH '/' FROM m.men_urlamigable) = '{$url}')"
        );
        $this->db->limit(1);

        return $this->db->get()->num_rows() > 0;
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Verifica permiso CRUD sobre un menú específico.
     */
    public function verificar_permiso($usu_tipoper, $men_id, $accion = 'consultar')
    {
        $columnas_validas = [
            'acc_incluir', 'acc_consultar', 'acc_modificar',
            'acc_eliminar', 'acc_imprimir', 'acc_extra1', 'acc_extra2',
        ];
        $col = 'acc_' . $accion;
        if (!in_array($col, $columnas_validas)) return FALSE;

        $this->db->select("a.$col");
        $this->db->from('cab_permiaccion a');
        $this->db->join('cab_permisologia p', 'p.mis_id  = a.acc_id',    'inner');
        $this->db->join('cab_grupopermi g',   'g.per_id  = p.mis_permiso', 'inner');
        $this->db->where('p.mis_menu',   $men_id);
        $this->db->where('g.per_id',     $usu_tipoper);
        $this->db->where('p.mis_activo', '1');
        $this->db->where('g.per_activo', '1');
        $this->db->limit(1);

        $row = $this->db->get()->row_array();
        return !empty($row) && $row[$col] == 1;
    }

    // ─────────────────────────────────────────────────────────
    /**
     * Estructura plana de los menús autorizados para un rol, con sus flags
     * CRUD. Se serializa en sesión al iniciar sesión y es la única fuente
     * para resolver el menú actual por URL y autorizar acciones.
     *
     * @return array [ men_id => [ men_*, acc_* ] ]
     */
    public function get_estructura_permisos($rol_id)
    {
        $this->db->select('m.*, p.mis_id, p.mis_activo,
                           a.acc_consultar, a.acc_incluir, a.acc_modificar,
                           a.acc_eliminar, a.acc_imprimir, a.acc_extra1, a.acc_extra2');
        $this->db->from('cab_menu m');
        $this->db->join('cab_permisologia p', 'p.mis_menu = m.men_id',     'inner');
        $this->db->join('cab_grupopermi g',   'g.per_id  = p.mis_permiso', 'inner');
        $this->db->join('cab_permiaccion a',  'a.acc_id  = p.mis_id',      'left');
        $this->db->where('p.mis_permiso', (int)$rol_id);
        $this->db->where('p.mis_activo', '1');
        $this->db->where('g.per_activo', '1');
        $this->db->order_by('m.men_tipo',  'ASC');
        $this->db->order_by('m.men_orden', 'ASC');

        $estructura = [];
        foreach ($this->db->get()->result_array() as $row) {
            foreach (['acc_consultar', 'acc_incluir', 'acc_modificar', 'acc_eliminar',
                      'acc_imprimir', 'acc_extra1', 'acc_extra2'] as $col) {
                $row[$col] = (int)($row[$col] ?? 0);
            }
            $estructura[(int)$row['men_id']] = $row;
        }

        return $estructura;
    }

    /**
     * Construye el árbol jerárquico a partir de la estructura plana.
     */
    public function construir_arbol(array $estructura, $parent_id = 0)
    {
        return $this->_construir_arbol($estructura, $parent_id);
    }

    /**
     * Resuelve el menú de la estructura cuya URL corresponde al módulo actual.
     * Prioriza coincidencia exacta de men_url, luego el controlador y por
     * último la URL amigable. No consulta la base de datos.
     *
     * @return array|null
     */
    public function resolver_menu_por_url(array $estructura, $url_actual)
    {
        $url = strtolower(trim((string)$url_actual, '/'));
        if ($url === '' || empty($estructura)) {
            return NULL;
        }

        $partes      = explode('/', $url);
        $controlador = $partes[0] ?? '';

        // 1. Coincidencia exacta Controlador/metodo
        foreach ($estructura as $menu) {
            $men_url = strtolower(trim((string)($menu['men_url'] ?? ''), '/'));
            if ($men_url !== '' && $men_url === $url) {
                return $menu;
            }
        }

        // 2. Coincidencia por controlador (primer segmento de men_url)
        foreach ($estructura as $menu) {
            $men_url = strtolower(trim((string)($menu['men_url'] ?? ''), '/'));
            if ($men_url === '') {
                continue;
            }
            $men_controlador = explode('/', $men_url)[0] ?? '';
            if ($men_controlador !== '' && $men_controlador === $controlador) {
                return $menu;
            }
        }

        // 3. Coincidencia por URL amigable
        foreach ($estructura as $menu) {
            $amigable = strtolower(trim((string)($menu['men_urlamigable'] ?? ''), '/'));
            if ($amigable !== '' && $amigable === $controlador) {
                return $menu;
            }
        }

        return NULL;
    }

    /**
     * Cantidad de menús activos asignados a un rol. Se usa para comparar
     * jerarquías sin depender de IDs de rol.
     */
    public function cobertura_rol($rol_id)
    {
        return (int)$this->db
            ->from('cab_permisologia p')
            ->join('cab_grupopermi g', 'g.per_id = p.mis_permiso', 'inner')
            ->where('p.mis_permiso', (int)$rol_id)
            ->where('p.mis_activo', '1')
            ->where('g.per_activo', '1')
            ->count_all_results();
    }

    /**
     * Un rol es de nivel maestro cuando su cobertura alcanza todos los
     * menús activos del sistema. No se codifica ningún ID de rol.
     */
    public function rol_es_master($rol_id)
    {
        $total = (int)$this->db->count_all_results('cab_menu');
        if ($total <= 0) {
            return FALSE;
        }

        return $this->cobertura_rol($rol_id) >= $total;
    }

    /**
     * Versión global de permisos. Cambia cuando se guardan permisos o roles
     * y permite invalidar el snapshot de menús de todas las sesiones.
     */
    public function version_permisos()
    {
        if (!$this->db->table_exists('cab_configuracion_general')) {
            return '0';
        }

        $row = $this->db
            ->select('cfg_valor')
            ->where('cfg_clave', 'permisos_version')
            ->limit(1)
            ->get('cab_configuracion_general')
            ->row_array();

        return isset($row['cfg_valor']) ? (string)$row['cfg_valor'] : '0';
    }

    /**
     * Traduce una lista de URLs amigables a IDs de menú. Evita declarar IDs
     * en el código para listas como los módulos sensibles.
     */
    public function ids_menus_por_slugs(array $slugs)
    {
        $slugs = array_values(array_filter(array_map('strval', $slugs)));
        if (empty($slugs)) {
            return [];
        }

        $rows = $this->db
            ->select('men_id')
            ->where_in('men_urlamigable', $slugs)
            ->get('cab_menu')
            ->result_array();

        return array_map('intval', array_column($rows, 'men_id'));
    }

    // ─────────────────────────────────────────────────────────
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

    /**
     * Verifica si una URL base existe en cab_menu (sin filtro de permisos).
     * Se conserva por compatibilidad; el control de acceso usa
     * controlador_registrado() y usuario_tiene_acceso_controlador().
     */
    public function url_existe_en_menu($url_base)
    {
        $url = $this->db->escape_str(trim($url_base, '/'));

        return $this->db
            ->where("(TRIM(BOTH '/' FROM men_url) = '{$url}'
                OR TRIM(BOTH '/' FROM men_urlamigable) = '{$url}')")
            ->count_all_results('cab_menu') > 0;
    }

    /**
     * Indica si un controlador está registrado en cab_menu.
     * men_url guarda "Controlador/accion"; se compara el primer segmento.
     * Deny-by-default: un controlador administrativo no registrado no debe
     * quedar accesible solo porque su URL no coincida exactamente.
     */
    public function controlador_registrado($controlador)
    {
        $controlador = $this->db->escape_str(trim((string)$controlador, '/'));
        if ($controlador === '') {
            return FALSE;
        }

        return $this->db
            ->where(
                "(TRIM(BOTH '/' FROM men_url) = '{$controlador}'
                  OR TRIM(BOTH '/' FROM men_url) LIKE '{$controlador}/%')",
                NULL,
                FALSE
            )
            ->count_all_results('cab_menu') > 0;
    }

    /**
     * Verifica que el rol tenga asignado el menú del controlador actual,
     * comparando por el prefijo de men_url (Controlador/accion) o por la
     * URL amigable (men_urlamigable).
     */
    public function usuario_tiene_acceso_controlador($usu_tipoper, $controlador, $uri_amigable = '')
    {
        $controlador = $this->db->escape_str(trim((string)$controlador, '/'));
        $amigable    = $this->db->escape_str(trim((string)$uri_amigable, '/'));

        if ($controlador === '') {
            return FALSE;
        }

        $condicion = "TRIM(BOTH '/' FROM m.men_url) = '{$controlador}'
                      OR TRIM(BOTH '/' FROM m.men_url) LIKE '{$controlador}/%'";

        if ($amigable !== '') {
            $condicion .= " OR TRIM(BOTH '/' FROM m.men_urlamigable) = '{$amigable}'";
        }

        $this->db->select('m.men_id');
        $this->db->from('cab_menu m');
        $this->db->join('cab_permisologia p', 'p.mis_menu = m.men_id',     'inner');
        $this->db->join('cab_grupopermi g',   'g.per_id  = p.mis_permiso', 'inner');
        $this->db->where('g.per_id',     $usu_tipoper);
        $this->db->where('p.mis_activo', '1');
        $this->db->where('g.per_activo', '1');
        $this->db->where("({$condicion})", NULL, FALSE);
        $this->db->limit(1);

        return $this->db->get()->num_rows() > 0;
    }

}
