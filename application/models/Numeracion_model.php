<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Numeracion_model
 * ─────────────────────────────────────────────────────────────
 * Catálogo global de numeraciones (M02.1).
 *
 * - Tabla principal: rif_numeraciones (num_id).
 * - Una numeración define un rango de números y su padding.
 * - No existe borrado físico: eliminar() desactiva (num_status = 0).
 * - Contrato DataTables server-side canónico.
 * - Escrituras con db_debug temporal desactivado para no filtrar SQL.
 * - Guardas table_exists para operar antes de aplicar migraciones.
 */
class Numeracion_model extends CI_Model
{
    protected $table = 'rif_numeraciones';

    protected $table_planes = 'rif_plan_numeraciones';

    protected $table_asignaciones = 'rif_plan_asignaciones';

    protected $table_rifas = 'rif_rifas';

    protected $primary_key = 'num_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     * 0 => numeración, 1 => rango, 2 => cantidad, 3 => estado, 4 => acciones.
     *
     * @var array
     */
    protected $columnas_ordenables = [
        0 => 'n.num_label',
        1 => 'n.num_desde',
        2 => 'n.num_cantidad',
        3 => 'n.num_status',
        4 => 'n.num_id',
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
     * Total de numeraciones registradas.
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
     * Total de numeraciones tras aplicar la búsqueda.
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
        $this->db->select('n.*');

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'n.num_orden';
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, max(0, (int)$start));
        }

        return $this->db->get()->result_array();
    }

    /**
     * Query base para conteos y listado DataTables.
     *
     * @param  string $search
     * @return void
     */
    private function _datatable_query($search = '')
    {
        $this->db->from($this->table . ' n');

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('n.num_label', $search);
            $this->db->or_like('n.num_desde', $search);
            $this->db->or_like('n.num_hasta', $search);
            $this->db->or_like('n.num_cantidad', $search);
            $this->db->or_like('n.num_padding', $search);
            $this->db->group_end();
        }
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene una numeración por su ID.
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
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Numeraciones activas ordenadas para selects.
     *
     * @return array
     */
    public function get_activas()
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        return $this->db
            ->where('num_status', 1)
            ->order_by('num_orden', 'ASC')
            ->order_by('num_cantidad', 'ASC')
            ->order_by('num_id', 'ASC')
            ->get($this->table)
            ->result_array();
    }

    /**
     * Comprueba si ya existe una numeración con el mismo rango y padding.
     *
     * La restricción UNIQUE de la tabla también incluye num_padding, por eso
     * el chequeo replica exactamente esos tres campos.
     *
     * @param  int $desde
     * @param  int $hasta
     * @param  int $padding
     * @param  int $ignorar_id
     * @return bool
     */
    public function existe_rango($desde, $hasta, $padding, $ignorar_id = 0)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $this->db
            ->where('num_desde', (int)$desde)
            ->where('num_hasta', (int)$hasta)
            ->where('num_padding', (int)$padding);

        if ((int)$ignorar_id > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$ignorar_id);
        }

        return (int)$this->db->count_all_results($this->table) > 0;
    }

    /**
     * Indica si una numeración está referenciada por planes, lotes o rifas.
     *
     * Es conservador: cualquier referencia bloquea la desactivación para no
     * romper datos históricos ni la FK de rif_plan_numeraciones.
     *
     * @param  int $num_id
     * @return bool
     */
    public function esta_en_uso($num_id)
    {
        $num_id = (int)$num_id;
        if ($num_id <= 0) {
            return FALSE;
        }

        if ($this->db->table_exists($this->table_planes)) {
            $planes = (int)$this->db
                ->where('pnum_num_id', $num_id)
                ->count_all_results($this->table_planes);

            if ($planes > 0) {
                return TRUE;
            }
        }

        if ($this->db->table_exists($this->table_asignaciones)) {
            $lotes = (int)$this->db
                ->where('asig_num_id', $num_id)
                ->count_all_results($this->table_asignaciones);

            if ($lotes > 0) {
                return TRUE;
            }
        }

        if ($this->db->table_exists($this->table_rifas)) {
            $rifas = (int)$this->db
                ->where('rifa_num_id', $num_id)
                ->count_all_results($this->table_rifas);

            if ($rifas > 0) {
                return TRUE;
            }
        }

        return FALSE;
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * Inserta una numeración.
     *
     * @param  array $data
     * @return int|false
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

        $ahora = date('Y-m-d H:i:s');
        $data['num_created_at'] = $ahora;
        $data['num_updated_at'] = $ahora;

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            if (!$this->db->insert($this->table, $data)) {
                $this->_log_db_error('crear');
                return FALSE;
            }

            return (int)$this->db->insert_id();
        } catch (Throwable $e) {
            $this->_log_db_error('crear');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Actualiza una numeración existente.
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

        $id   = (int)$id;
        $data = $this->_mapear_guardado($data);
        if ($id <= 0 || empty($data)) {
            return FALSE;
        }

        $data['num_updated_at'] = date('Y-m-d H:i:s');

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            $ok = $this->db
                ->where($this->primary_key, $id)
                ->update($this->table, $data);

            if (!$ok) {
                $this->_log_db_error('actualizar');
                return FALSE;
            }

            return TRUE;
        } catch (Throwable $e) {
            $this->_log_db_error('actualizar');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Desactiva una numeración. No se elimina físicamente.
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
        if ($id <= 0) {
            return FALSE;
        }

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            $ok = $this->db
                ->where($this->primary_key, $id)
                ->update($this->table, [
                    'num_status'     => 0,
                    'num_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok) {
                $this->_log_db_error('eliminar');
                return FALSE;
            }

            return TRUE;
        } catch (Throwable $e) {
            $this->_log_db_error('eliminar');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Alterna el estado activo/inactivo (1/0).
     *
     * @param  int $id
     * @return array ['ok' => bool, 'nuevo_status' => int, 'numeracion' => array]
     */
    public function toggle_status($id)
    {
        $numeracion = $this->get_por_id($id);
        if (empty($numeracion)) {
            return ['ok' => FALSE, 'nuevo_status' => 0, 'numeracion' => []];
        }

        $nuevo_status = ((int)$numeracion['num_status'] === 1) ? 0 : 1;

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            $ok = $this->db
                ->where($this->primary_key, (int)$id)
                ->update($this->table, [
                    'num_status'     => $nuevo_status,
                    'num_updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable $e) {
            $ok = FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }

        if (!$ok) {
            $this->_log_db_error('toggle_status');
        }

        $numeracion['num_status'] = $nuevo_status;

        return [
            'ok'           => (bool)$ok,
            'nuevo_status' => $nuevo_status,
            'numeracion'   => $numeracion,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Registra un error de base de datos sin exponer SQL ni detalles sensibles.
     *
     * @param  string $metodo
     * @return void
     */
    private function _log_db_error($metodo)
    {
        $error = $this->db->error();
        $code  = isset($error['code']) ? (int)$error['code'] : 0;

        log_message(
            'error',
            'Numeracion_model::' . (string)$metodo . ' no pudo completar la operación. Código DB: ' . $code
        );
    }

    /**
     * Normaliza y filtra las columnas persistibles.
     *
     * @param  array $data
     * @return array
     */
    private function _mapear_guardado(array $data)
    {
        $permitidos = [
            'num_label',
            'num_cantidad',
            'num_desde',
            'num_hasta',
            'num_padding',
            'num_status',
            'num_orden',
        ];

        $data = array_intersect_key($data, array_flip($permitidos));

        if (array_key_exists('num_label', $data)) {
            $data['num_label'] = trim((string)$data['num_label']);
        }
        if (array_key_exists('num_cantidad', $data)) {
            $data['num_cantidad'] = abs((int)$data['num_cantidad']);
        }
        if (array_key_exists('num_desde', $data)) {
            $data['num_desde'] = abs((int)$data['num_desde']);
        }
        if (array_key_exists('num_hasta', $data)) {
            $data['num_hasta'] = abs((int)$data['num_hasta']);
        }
        if (array_key_exists('num_padding', $data)) {
            $data['num_padding'] = abs((int)$data['num_padding']);
        }
        if (array_key_exists('num_status', $data)) {
            $data['num_status'] = ((int)$data['num_status'] === 1) ? 1 : 0;
        }
        if (array_key_exists('num_orden', $data)) {
            $data['num_orden'] = abs((int)$data['num_orden']);
        }

        return $data;
    }
}
