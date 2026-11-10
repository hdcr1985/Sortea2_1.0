<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_rifa_model
 * ─────────────────────────────────────────────────────────────
 * Modelo de planes de rifa (M02).
 *
 * - Tabla principal: rif_planes (plan_id).
 * - Contrato DataTables server-side canónico.
 * - Borrado lógico mediante plan_eliminado.
 * - Todas las consultas usan query builder o bindings.
 * - Incluye guardas table_exists para operar antes de aplicar migraciones.
 */
class Plan_rifa_model extends CI_Model
{
    protected $table = 'rif_planes';

    protected $table_asignaciones = 'rif_plan_asignaciones';

    protected $primary_key = 'plan_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     * 0 => nombre, 1 => tipo, 2 => periodo, 3 => precio, 4 => estado.
     */
    protected $columnas_ordenables = [
        0 => 'p.plan_nombre',
        1 => 'p.plan_tipo',
        2 => 'p.plan_periodo',
        3 => 'p.plan_precio',
        4 => 'p.plan_id',      // columna "asignaciones": no ordenable en la vista
        5 => 'p.plan_status',  // columna "estado" en la vista (índice 5)
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
     * Incluye el número de asignaciones activas por plan.
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

        $subquery = $this->db->table_exists($this->table_asignaciones)
            ? "(SELECT COUNT(*) FROM {$this->table_asignaciones} a"
                . " WHERE a.asig_plan_id = p.plan_id AND a.asig_estado = 'activa') AS asignaciones_activas"
            : '0 AS asignaciones_activas';

        $this->db->select('p.*, ' . $subquery, FALSE);

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
     * Obtiene un plan no eliminado por su ID.
     *
     * @param  int $id
     * @return array|null
     */
    public function get_por_id($id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('plan_eliminado', 0)
            ->limit(1)
            ->get($this->table)
            ->row_array();
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

        return $this->db
            ->select('plan_id, plan_nombre, plan_tipo, plan_limite, plan_periodo, plan_precio, plan_moneda')
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
     * Inserta un plan de rifa.
     *
     * @param  array $data
     * @return int|false ID insertado o FALSE
     */
    public function crear(array $data)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $data = $this->_mapear_guardado($data);
        if (empty($data)) {
            return FALSE;
        }

        $data['plan_created_at'] = date('Y-m-d H:i:s');
        $data['plan_updated_at'] = date('Y-m-d H:i:s');
        $data['plan_eliminado']  = 0;

        if ($this->db->insert($this->table, $data)) {
            return (int)$this->db->insert_id();
        }

        return FALSE;
    }

    /**
     * Actualiza un plan existente con lista blanca de columnas.
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

        $data = $this->_mapear_guardado($data);
        if (empty($data)) {
            return FALSE;
        }

        $data['plan_updated_at'] = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        try {
            // Bloquea el plan para que una asignación concurrente no cambie
            // las reglas de cupo/vigencia mientras se actualiza.
            $actual = $this->db->query(
                "SELECT * FROM {$this->table} WHERE plan_id = ? AND plan_eliminado = 0 LIMIT 1 FOR UPDATE",
                [(int)$id]
            )->row_array();

            if (empty($actual)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($this->tiene_asignaciones_activas((int)$id) > 0) {
                foreach (['plan_tipo', 'plan_limite', 'plan_periodo'] as $campo) {
                    if (array_key_exists($campo, $data)
                        && (string)$data[$campo] !== (string)($actual[$campo] ?? '')) {
                        $this->db->trans_rollback();
                        return FALSE;
                    }
                }
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$id)
                ->where('plan_eliminado', 0)
                ->update($this->table, $data);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_rifa_model::actualizar no pudo actualizar el plan.');
            return FALSE;
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

        $this->db->trans_begin();

        try {
            $actual = $this->db->query(
                "SELECT plan_id FROM {$this->table} WHERE plan_id = ? AND plan_eliminado = 0 LIMIT 1 FOR UPDATE",
                [(int)$id]
            )->row_array();

            if (empty($actual)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            // Defensa en profundidad: no borrar planes con asignaciones activas.
            if ($this->tiene_asignaciones_activas((int)$id) > 0) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$id)
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

        $ok = $this->db
            ->where($this->primary_key, (int)$id)
            ->where('plan_eliminado', 0)
            ->update($this->table, [
                'plan_status'     => $nuevo_status,
                'plan_updated_at' => date('Y-m-d H:i:s'),
            ]);

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
}
