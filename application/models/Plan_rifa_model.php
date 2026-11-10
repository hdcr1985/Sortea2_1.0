<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_rifa_model
 * ─────────────────────────────────────────────────────────────
 * Modelo de planes de rifa (M02 / M02.1).
 *
 * - Tabla principal: rif_planes (plan_id).
 * - Opciones por plan: rif_plan_numeraciones (pnum_id) + rif_numeraciones.
 * - Contrato DataTables server-side canónico.
 * - Borrado lógico mediante plan_eliminado.
 * - Todas las consultas usan query builder o bindings.
 * - Escrituras con db_debug temporal desactivado para no filtrar SQL.
 * - Incluye guardas table_exists para operar antes de aplicar migraciones.
 */
class Plan_rifa_model extends CI_Model
{
    protected $table = 'rif_planes';

    protected $table_asignaciones = 'rif_plan_asignaciones';

    protected $table_opciones = 'rif_plan_numeraciones';

    protected $table_numeraciones = 'rif_numeraciones';

    protected $primary_key = 'plan_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     */
    protected $columnas_ordenables = [
        0 => 'p.plan_nombre',  // plan
        1 => 'p.plan_id',      // opciones: no ordenable
        2 => 'p.plan_periodo', // vigencia
        3 => 'p.plan_precio',  // precio
        4 => 'p.plan_id',      // asignaciones: no ordenable
        5 => 'p.plan_status',  // estado
        6 => 'p.plan_id',      // acciones: no ordenable
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Indica si la tabla principal está disponible.
     *
     * @return bool
     */
    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table);
    }

    /**
     * Indica si el esquema de opciones por plan está disponible.
     *
     * @return bool
     */
    public function opciones_disponibles()
    {
        return $this->db->table_exists($this->table_opciones)
            && $this->db->table_exists($this->table_numeraciones);
    }

    // ─────────────────────────────────────────────────────────
    // CONTRATO DATATABLES SERVER-SIDE
    // ─────────────────────────────────────────────────────────

    /**
     * Total de planes no eliminados.
     *
     * @return int
     */
    public function count_todos()
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_datatable_query('');
        return (int)$this->db->count_all_results();
    }

    /**
     * Total de planes no eliminados tras aplicar la búsqueda.
     *
     * @param  string $search
     * @return int
     */
    public function count_filtrados($search = '')
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_datatable_query($search);
        return (int)$this->db->count_all_results();
    }

    /**
     * Registros paginados para DataTables server-side.
     *
     * Incluye el número de asignaciones activas y el resumen de opciones.
     *
     * @param  int    $start
     * @param  int    $length
     * @param  string $search
     * @param  int    $order_col
     * @param  string $order_dir
     * @return array
     */
    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $this->_datatable_query($search);

        $subquery_asignaciones = $this->db->table_exists($this->table_asignaciones)
            ? "(SELECT COUNT(*) FROM {$this->table_asignaciones} a"
                . " WHERE a.asig_plan_id = p.plan_id AND a.asig_estado = 'activa') AS asignaciones_activas"
            : '0 AS asignaciones_activas';

        if ($this->opciones_disponibles()) {
            $subquery_opciones_count = "(SELECT COUNT(*) FROM {$this->table_opciones} pn"
                . " INNER JOIN {$this->table_numeraciones} n ON n.num_id = pn.pnum_num_id"
                . " WHERE pn.pnum_plan_id = p.plan_id AND pn.pnum_status = 1 AND n.num_status = 1"
                . " ) AS opciones_count";

            $subquery_opciones_resumen = "(SELECT GROUP_CONCAT(n.num_label ORDER BY pn.pnum_orden ASC, pn.pnum_id ASC SEPARATOR ', ')"
                . " FROM {$this->table_opciones} pn"
                . " INNER JOIN {$this->table_numeraciones} n ON n.num_id = pn.pnum_num_id"
                . " WHERE pn.pnum_plan_id = p.plan_id AND pn.pnum_status = 1 AND n.num_status = 1"
                . " ) AS opciones_resumen";
        } else {
            $subquery_opciones_count   = '0 AS opciones_count';
            $subquery_opciones_resumen = "'' AS opciones_resumen";
        }

        $this->db->select(
            'p.*, ' . $subquery_asignaciones . ', ' . $subquery_opciones_count . ', ' . $subquery_opciones_resumen,
            FALSE
        );

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'p.plan_id';
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, max(0, (int)$start));
        }

        return $this->db->get()->result_array();
    }

    /**
     * Query base para conteos y listado DataTables.
     * Siempre filtra plan_eliminado = 0.
     *
     * @param  string $search
     * @return void
     */
    private function _datatable_query($search = '')
    {
        $this->db->from($this->table . ' p');
        $this->db->where('p.plan_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('p.plan_nombre', $search);
            $this->db->or_like('p.plan_descripcion', $search);
            $this->db->or_like('p.plan_tipo', $search);
            $this->db->or_like('p.plan_periodo', $search);
            $this->db->or_like('p.plan_precio', $search);
            $this->db->or_like('p.plan_moneda', $search);
            $this->db->group_end();
        }
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene un plan no eliminado por su ID junto con sus opciones.
     *
     * @param  int $id
     * @return array|null
     */
    public function get_por_id($id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $plan = $this->db
            ->where($this->primary_key, (int)$id)
            ->where('plan_eliminado', 0)
            ->limit(1)
            ->get($this->table)
            ->row_array();

        if (empty($plan)) {
            return NULL;
        }

        $plan['numeraciones'] = $this->_get_numeraciones_plan((int)$id);

        return $plan;
    }

    /**
     * Opciones (numeraciones) activas de un plan.
     *
     * @param  int $plan_id
     * @return array
     */
    public function get_opciones_activas($plan_id)
    {
        if (!$this->opciones_disponibles()) {
            return [];
        }

        return $this->db
            ->select(
                'pn.pnum_num_id AS num_id, n.num_label, pn.pnum_precio,'
                . ' n.num_cantidad, n.num_desde, n.num_hasta, n.num_padding, pn.pnum_orden'
            )
            ->from($this->table_opciones . ' pn')
            ->join($this->table_numeraciones . ' n', 'n.num_id = pn.pnum_num_id', 'inner')
            ->where('pn.pnum_plan_id', (int)$plan_id)
            ->where('pn.pnum_status', 1)
            ->where('n.num_status', 1)
            ->order_by('pn.pnum_orden', 'ASC')
            ->order_by('pn.pnum_id', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Verifica si existe otro plan con el mismo nombre (case-insensitive).
     *
     * Se incluyen los registros eliminados porque la restricción UNIQUE
     * sigue vigente sobre ellos.
     *
     * @param  string $nombre
     * @param  int    $ignorar_id
     * @return bool
     */
    public function existe_nombre($nombre, $ignorar_id = 0)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $nombre = strtolower(trim((string)$nombre));
        if ($nombre === '') {
            return FALSE;
        }

        $this->db->where('LOWER(plan_nombre)', $nombre);

        if ((int)$ignorar_id > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$ignorar_id);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Planes activos para selects (Select2).
     *
     * @return array
     */
    public function get_activos_para_select()
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $select = 'plan_id, plan_nombre, plan_tipo, plan_limite, plan_periodo, plan_precio, plan_moneda';

        if ($this->opciones_disponibles()) {
            $select .= ', (SELECT COUNT(*) FROM ' . $this->table_opciones . ' pn'
                . ' INNER JOIN ' . $this->table_numeraciones . ' n ON n.num_id = pn.pnum_num_id'
                . ' WHERE pn.pnum_plan_id = plan_id AND pn.pnum_status = 1 AND n.num_status = 1) AS opciones_count';
        }

        return $this->db
            ->select($select, FALSE)
            ->where('plan_eliminado', 0)
            ->where('plan_status', 1)
            ->order_by('plan_nombre', 'ASC')
            ->get($this->table)
            ->result_array();
    }

    /**
     * Cuenta las asignaciones activas de un plan.
     *
     * @param  int $plan_id
     * @return int
     */
    public function tiene_asignaciones_activas($plan_id)
    {
        if (!$this->tabla_disponible() || !$this->db->table_exists($this->table_asignaciones)) {
            return 0;
        }

        return (int)$this->db
            ->where('asig_plan_id', (int)$plan_id)
            ->where('asig_estado', 'activa')
            ->count_all_results($this->table_asignaciones);
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * Inserta un plan de rifa y sus opciones de numeración.
     *
     * @param  array $data
     * @return int|false ID insertado o FALSE
     */
    public function crear(array $data)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $opciones      = $this->_extraer_opciones($data);
        $sincronizar   = ($opciones !== NULL);
        $data          = $this->_mapear_guardado($data);

        if (empty($data)) {
            return FALSE;
        }

        $ahora = date('Y-m-d H:i:s');
        $data['plan_created_at'] = $ahora;
        $data['plan_updated_at'] = $ahora;
        $data['plan_eliminado']  = 0;

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            if (!$this->db->insert($this->table, $data)) {
                $this->db->trans_rollback();
                log_message('error', 'Plan_rifa_model::crear no pudo insertar el plan.');
                return FALSE;
            }

            $plan_id = (int)$this->db->insert_id();

            if ($sincronizar && !$this->_reemplazar_numeraciones($plan_id, $opciones)) {
                $this->db->trans_rollback();
                log_message('error', 'Plan_rifa_model::crear no pudo guardar las opciones del plan.');
                return FALSE;
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return $plan_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_rifa_model::crear no pudo insertar el plan.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Actualiza un plan existente y, si se envían, sus opciones.
     *
     * Si el payload no incluye la clave "numeraciones" las opciones actuales
     * se conservan intactas (compatibilidad con formularios anteriores).
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function actualizar($id, array $data)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $id          = (int)$id;
        $opciones    = $this->_extraer_opciones($data);
        $sincronizar = ($opciones !== NULL);
        $data        = $this->_mapear_guardado($data);

        if ($id <= 0 || (empty($data) && !$sincronizar)) {
            return FALSE;
        }

        if (!empty($data)) {
            $data['plan_updated_at'] = date('Y-m-d H:i:s');
        }

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            // Bloquea el plan para que una asignación concurrente no cambie
            // las reglas de cupo/vigencia mientras se actualiza.
            $actual = $this->db->query(
                "SELECT * FROM {$this->table} WHERE plan_id = ? AND plan_eliminado = 0 LIMIT 1 FOR UPDATE",
                [$id]
            )->row_array();

            if (empty($actual)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($this->tiene_asignaciones_activas($id) > 0) {
                foreach (['plan_tipo', 'plan_limite', 'plan_periodo'] as $campo) {
                    if (array_key_exists($campo, $data)
                        && (string)$data[$campo] !== (string)($actual[$campo] ?? '')) {
                        $this->db->trans_rollback();
                        return FALSE;
                    }
                }
            }

            if (!empty($data)) {
                $ok = $this->db
                    ->where($this->primary_key, $id)
                    ->where('plan_eliminado', 0)
                    ->update($this->table, $data);

                if (!$ok || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            }

            if ($sincronizar && !$this->_reemplazar_numeraciones($id, $opciones)) {
                $this->db->trans_rollback();
                log_message('error', 'Plan_rifa_model::actualizar no pudo guardar las opciones del plan.');
                return FALSE;
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_rifa_model::actualizar no pudo actualizar el plan.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Borrado lógico de un plan.
     *
     * @param  int $id
     * @return bool
     */
    public function eliminar($id)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $id = (int)$id;
        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            $actual = $this->db->query(
                "SELECT plan_id FROM {$this->table} WHERE plan_id = ? AND plan_eliminado = 0 LIMIT 1 FOR UPDATE",
                [$id]
            )->row_array();

            if (empty($actual)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            // Defensa en profundidad: no borrar planes con asignaciones activas.
            if ($this->tiene_asignaciones_activas($id) > 0) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $ok = $this->db
                ->where($this->primary_key, $id)
                ->where('plan_eliminado', 0)
                ->update($this->table, [
                    'plan_eliminado'  => 1,
                    'plan_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_rifa_model::eliminar no pudo eliminar el plan.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Alterna el estado activo/inactivo (1/0).
     *
     * @param  int $id
     * @return array ['ok' => bool, 'nuevo_status' => int, 'plan' => array]
     */
    public function toggle_status($id)
    {
        $plan = $this->get_por_id($id);
        if (empty($plan)) {
            return ['ok' => FALSE, 'nuevo_status' => 0, 'plan' => []];
        }

        $nuevo_status = ((int)$plan['plan_status'] === 1) ? 0 : 1;

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            $ok = $this->db
                ->where($this->primary_key, (int)$id)
                ->where('plan_eliminado', 0)
                ->update($this->table, [
                    'plan_status'     => $nuevo_status,
                    'plan_updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable $e) {
            $ok = FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }

        if (!$ok) {
            log_message('error', 'Plan_rifa_model::toggle_status no pudo cambiar el estado.');
        }

        $plan['plan_status'] = $nuevo_status;

        return [
            'ok'           => (bool)$ok,
            'nuevo_status' => $nuevo_status,
            'plan'         => $plan,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Normaliza y filtra las columnas persistibles de un plan.
     *
     * @param  array $data
     * @return array
     */
    private function _mapear_guardado(array $data)
    {
        $permitidos = [
            'plan_nombre',
            'plan_descripcion',
            'plan_tipo',
            'plan_limite',
            'plan_periodo',
            'plan_precio',
            'plan_moneda',
            'plan_status',
        ];

        $data = array_intersect_key($data, array_flip($permitidos));

        if (array_key_exists('plan_descripcion', $data)) {
            $data['plan_descripcion'] = trim((string)$data['plan_descripcion']);
            if ($data['plan_descripcion'] === '') {
                $data['plan_descripcion'] = NULL;
            }
        }

        return $data;
    }

    /**
     * Extrae las opciones del payload. Devuelve NULL si la clave no existe.
     *
     * @param  array $data
     * @return array|null
     */
    private function _extraer_opciones(array $data)
    {
        if (!array_key_exists('numeraciones', $data)) {
            return NULL;
        }

        $raw = $data['numeraciones'];
        if (!is_array($raw)) {
            return [];
        }

        $opciones = [];
        $vistos   = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $num_id = isset($item['num_id']) ? (int)$item['num_id'] : 0;
            if ($num_id <= 0 || isset($vistos[$num_id])) {
                continue;
            }

            $precio = isset($item['pnum_precio'])
                ? round((float)str_replace(',', '.', (string)$item['pnum_precio']), 2)
                : 0.00;

            if (!is_finite($precio) || $precio < 0) {
                $precio = 0.00;
            }

            $opciones[] = [
                'num_id'      => $num_id,
                'pnum_precio' => $precio,
                'pnum_status' => (isset($item['pnum_status']) && (int)$item['pnum_status'] === 1) ? 1 : 0,
                'pnum_orden'  => isset($item['pnum_orden']) ? abs((int)$item['pnum_orden']) : 0,
            ];

            $vistos[$num_id] = TRUE;
        }

        return $opciones;
    }

    /**
     * Reemplaza las opciones de un plan dentro de una transacción abierta.
     *
     * @param  int   $plan_id
     * @param  array $opciones
     * @return bool
     */
    private function _reemplazar_numeraciones($plan_id, array $opciones)
    {
        if (!$this->opciones_disponibles()) {
            // Sin esquema de opciones no hay nada que sincronizar.
            return TRUE;
        }

        $plan_id = (int)$plan_id;
        if ($plan_id <= 0) {
            return FALSE;
        }

        if (!$this->db->where('pnum_plan_id', $plan_id)->delete($this->table_opciones)) {
            return FALSE;
        }

        if (empty($opciones)) {
            return TRUE;
        }

        $ids = [];
        foreach ($opciones as $opcion) {
            $ids[] = (int)$opcion['num_id'];
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return TRUE;
        }

        $validos = $this->db
            ->select('num_id')
            ->where_in('num_id', $ids)
            ->get($this->table_numeraciones)
            ->result_array();

        $permitidos = [];
        foreach ($validos as $row) {
            $permitidos[(int)$row['num_id']] = TRUE;
        }

        $insertar = [];
        $ahora    = date('Y-m-d H:i:s');

        foreach ($opciones as $opcion) {
            $num_id = (int)$opcion['num_id'];
            if (!isset($permitidos[$num_id])) {
                continue;
            }

            $insertar[] = [
                'pnum_plan_id'    => $plan_id,
                'pnum_num_id'     => $num_id,
                'pnum_precio'     => (float)$opcion['pnum_precio'],
                'pnum_status'     => (int)$opcion['pnum_status'] === 1 ? 1 : 0,
                'pnum_orden'      => (int)$opcion['pnum_orden'],
                'pnum_created_at' => $ahora,
                'pnum_updated_at' => $ahora,
            ];
        }

        if (empty($insertar)) {
            return TRUE;
        }

        return (bool)$this->db->insert_batch($this->table_opciones, $insertar);
    }

    /**
     * Opciones completas de un plan para el modal de edición.
     *
     * @param  int $plan_id
     * @return array
     */
    private function _get_numeraciones_plan($plan_id)
    {
        if (!$this->opciones_disponibles()) {
            return [];
        }

        return $this->db
            ->select(
                'pn.pnum_id, pn.pnum_plan_id, pn.pnum_num_id AS num_id, pn.pnum_precio,'
                . ' pn.pnum_status, pn.pnum_orden, n.num_label, n.num_cantidad,'
                . ' n.num_desde, n.num_hasta, n.num_padding, n.num_status'
            )
            ->from($this->table_opciones . ' pn')
            ->join($this->table_numeraciones . ' n', 'n.num_id = pn.pnum_num_id', 'inner')
            ->where('pn.pnum_plan_id', (int)$plan_id)
            ->order_by('pn.pnum_orden', 'ASC')
            ->order_by('pn.pnum_id', 'ASC')
            ->get()
            ->result_array();
    }
}
