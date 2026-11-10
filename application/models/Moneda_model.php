<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Moneda_model
 * ─────────────────────────────────────────────────────────────
 * Catálogo M04 de monedas y tasas de cambio.
 *
 * - Base: USD. `tasa_valor` = unidades de la moneda por 1 USD.
 * - `mon_codigo` es VARCHAR(10) para admitir USDT y tickers similares.
 * - Las tasas son históricas por fecha; la tasa actual es la última
 *   con fecha <= hoy y status activo.
 */
class Moneda_model extends CI_Model
{
    protected $table        = 'rif_monedas';
    protected $table_tasas  = 'rif_tasas_cambio';
    protected $primary_key  = 'mon_id';

    protected $columnas_guardado = [
        'mon_codigo',
        'mon_nombre',
        'mon_simbolo',
        'mon_decimales',
        'mon_es_base',
        'mon_orden',
        'mon_status',
        'mon_api_fuente',
        'mon_api_codigo',
        'mon_api_ultima_sync',
    ];

    protected $columnas_ordenables = [
        0 => 'm.mon_codigo',
        1 => 'm.mon_es_base',
        2 => 't.tasa_valor',
        3 => 't.tasa_fecha',
        4 => 'm.mon_status',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return bool
     */
    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists($this->table_tasas);
    }

    // ─────────────────────────────────────────────────────────
    // DATATABLES
    // ─────────────────────────────────────────────────────────

    public function count_todos()
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_query_datatable('');
        return (int)$this->db->count_all_results();
    }

    public function count_filtrados($search = '')
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_query_datatable($search);
        return (int)$this->db->count_all_results();
    }

    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $this->_query_datatable($search);

        $this->db->select('m.*, t.tasa_valor, t.tasa_fecha', FALSE);
        $this->_join_tasa_actual();

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'm.mon_orden';
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, max(0, (int)$start));
        }

        return $this->db->get()->result_array();
    }

    private function _query_datatable($search = '')
    {
        $this->db->from($this->table . ' m');
        $this->db->where('m.mon_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('m.mon_codigo', $search);
            $this->db->or_like('m.mon_nombre', $search);
            $this->db->or_like('m.mon_simbolo', $search);
            $this->db->or_like('m.mon_api_fuente', $search);
            $this->db->or_like('m.mon_api_codigo', $search);
            $this->db->group_end();
        }
    }

    /**
     * Join a la última tasa vigente (fecha <= hoy).
     */
    private function _join_tasa_actual()
    {
        $sub = "(SELECT t2.tasa_id FROM {$this->table_tasas} t2"
            . " WHERE t2.tasa_mon_id = m.mon_id"
            . " AND t2.tasa_status = 1"
            . " AND t2.tasa_fecha <= CURDATE()"
            . " ORDER BY t2.tasa_fecha DESC, t2.tasa_id DESC LIMIT 1)";

        $this->db->join($this->table_tasas . ' t', 't.tasa_id = ' . $sub, 'left', FALSE);
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    public function get_por_id($id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $this->db->select('m.*', FALSE)
            ->from($this->table . ' m')
            ->where('m.mon_id', (int)$id)
            ->where('m.mon_eliminado', 0);

        return $this->db->limit(1)->get()->row_array();
    }

    public function get_por_codigo($codigo)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $codigo = strtoupper(trim((string)$codigo));
        if ($codigo === '') {
            return NULL;
        }

        return $this->db
            ->where('mon_codigo', $codigo)
            ->where('mon_eliminado', 0)
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    public function existe_codigo($codigo, $ignorar_id = 0)
    {
        if (!$this->db->table_exists($this->table)) {
            return FALSE;
        }

        $codigo = strtoupper(trim((string)$codigo));
        if ($codigo === '') {
            return FALSE;
        }

        $this->db->where('mon_codigo', $codigo);
        if ((int)$ignorar_id > 0) {
            $this->db->where('mon_id !=', (int)$ignorar_id);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Monedas activas con su tasa actual.
     *
     * @return array
     */
    public function get_activas()
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $this->db
            ->select('m.mon_id, m.mon_codigo, m.mon_nombre, m.mon_simbolo, m.mon_decimales, m.mon_es_base, m.mon_orden, m.mon_status, t.tasa_valor AS tasa_actual, t.tasa_fecha AS tasa_fecha_actual', FALSE)
            ->from($this->table . ' m')
            ->where('m.mon_eliminado', 0)
            ->where('m.mon_status', 1);

        $this->_join_tasa_actual();

        return $this->db
            ->order_by('m.mon_es_base', 'DESC')
            ->order_by('m.mon_orden', 'ASC')
            ->order_by('m.mon_codigo', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * @return array
     */
    public function get_codigos_activos()
    {
        $codigos = [];

        foreach ($this->get_activas() as $moneda) {
            $codigos[] = (string)$moneda['mon_codigo'];
        }

        return $codigos;
    }

    public function get_base()
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        return $this->db
            ->where('mon_es_base', 1)
            ->where('mon_eliminado', 0)
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * Crea una moneda y su tasa inicial.
     *
     * @param  array    $data
     * @param  float|null $tasa_inicial
     * @return int|false
     */
    public function crear(array $data, $tasa_inicial = NULL)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $guardado = $this->_filtrar_guardado($data);
        if (empty($guardado)) {
            return FALSE;
        }

        $guardado['mon_codigo'] = strtoupper(trim((string)$guardado['mon_codigo']));
        $guardado['mon_es_base'] = ($guardado['mon_codigo'] === 'USD') ? 1 : 0;
        $guardado['mon_created_at'] = date('Y-m-d H:i:s');
        $guardado['mon_updated_at'] = $guardado['mon_created_at'];
        $guardado['mon_eliminado'] = 0;

        $this->db->trans_begin();

        try {
            if ((int)$guardado['mon_es_base'] === 1) {
                $this->db->where('mon_es_base', 1)->update($this->table, ['mon_es_base' => 0]);
            }

            $debug_previo = $this->db->db_debug;
            $this->db->db_debug = FALSE;

            try {
                $ok    = $this->db->insert($this->table, $guardado);
                $error = $this->db->error();
            } catch (Throwable $e) {
                $ok    = FALSE;
                $error = ['code' => -1, 'message' => ''];
            } finally {
                $this->db->db_debug = $debug_previo;
            }

            if (!$ok || !empty($error['code'])) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $id = (int)$this->db->insert_id();
            $valor = ($guardado['mon_codigo'] === 'USD')
                ? 1.0
                : round((float)$tasa_inicial, 10);

            if ($guardado['mon_codigo'] !== 'USD' && $valor <= 0) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($valor > 0) {
                $this->_insertar_tasa($id, $valor, date('Y-m-d'), 'manual');
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Moneda_model::crear no pudo crear la moneda.');
            return FALSE;
        }
    }

    /**
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function actualizar($id, array $data)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $actual = $this->get_por_id($id);
        if (empty($actual)) {
            return FALSE;
        }

        $guardado = $this->_filtrar_guardado($data);
        if (empty($guardado)) {
            return FALSE;
        }

        $guardado['mon_codigo'] = strtoupper(trim((string)$guardado['mon_codigo']));

        if ((int)$actual['mon_es_base'] === 1) {
            $guardado['mon_codigo'] = 'USD';
            $guardado['mon_es_base'] = 1;
        } else {
            $guardado['mon_es_base'] = ($guardado['mon_codigo'] === 'USD') ? 1 : 0;
        }

        $guardado['mon_updated_at'] = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        try {
            if ((int)$guardado['mon_es_base'] === 1) {
                $this->db->where('mon_es_base', 1)->where('mon_id !=', (int)$id)
                    ->update($this->table, ['mon_es_base' => 0]);
            }

            $debug_previo = $this->db->db_debug;
            $this->db->db_debug = FALSE;

            try {
                $ok    = $this->db->where('mon_id', (int)$id)->update($this->table, $guardado);
                $error = $this->db->error();
            } catch (Throwable $e) {
                $ok    = FALSE;
                $error = ['code' => -1, 'message' => ''];
            } finally {
                $this->db->db_debug = $debug_previo;
            }

            if (!$ok || !empty($error['code']) || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Moneda_model::actualizar no pudo actualizar la moneda.');
            return FALSE;
        }
    }

    /**
     * Borrado lógico.
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
                "SELECT * FROM {$this->table} WHERE mon_id = ? AND mon_eliminado = 0 LIMIT 1 FOR UPDATE",
                [(int)$id]
            )->row_array();

            if (empty($actual)
                || (int)$actual['mon_es_base'] === 1
                || $this->esta_en_uso((string)$actual['mon_codigo'])) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $ok = $this->db
                ->where('mon_id', (int)$id)
                ->where('mon_eliminado', 0)
                ->update($this->table, [
                    'mon_eliminado'  => 1,
                    'mon_status'     => 0,
                    'mon_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->affected_rows() !== 1 || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Moneda_model::eliminar no pudo eliminar la moneda.');
            return FALSE;
        }
    }

    /**
     * @param  int $id
     * @return array
     */
    public function toggle_status($id)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'moneda' => NULL, 'nuevo_status' => NULL];
        }

        $this->db->trans_begin();

        try {
            $actual = $this->db->query(
                "SELECT * FROM {$this->table} WHERE mon_id = ? AND mon_eliminado = 0 LIMIT 1 FOR UPDATE",
                [(int)$id]
            )->row_array();

            if (empty($actual)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'moneda' => NULL, 'nuevo_status' => NULL];
            }

            $nuevo = ((int)$actual['mon_status'] === 1) ? 0 : 1;

            if ($nuevo === 0
                && ((int)$actual['mon_es_base'] === 1
                    || $this->esta_en_uso((string)$actual['mon_codigo']))) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'moneda' => $actual, 'nuevo_status' => $nuevo];
            }

            $ok = $this->db
                ->where('mon_id', (int)$id)
                ->where('mon_eliminado', 0)
                ->update($this->table, [
                    'mon_status'     => $nuevo,
                    'mon_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->affected_rows() !== 1 || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'moneda' => $actual, 'nuevo_status' => $nuevo];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'moneda' => $actual, 'nuevo_status' => $nuevo];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Moneda_model::toggle_status no pudo cambiar el estado.');
            return ['ok' => FALSE, 'moneda' => NULL, 'nuevo_status' => NULL];
        }
    }

    /**
     * Indica si una moneda está referenciada por rifas, planes de rifa o
     * planes de crédito. No bloquea el histórico de órdenes.
     *
     * @param  string $codigo
     * @return bool
     */
    public function esta_en_uso($codigo)
    {
        $codigo = strtoupper(trim((string)$codigo));
        if ($codigo === '') {
            return FALSE;
        }

        if ($this->db->table_exists('rif_rifas')) {
            $en_rifas = $this->db
                ->where('rifa_moneda', $codigo)
                ->where('rifa_eliminado', 0)
                ->count_all_results('rif_rifas');
            if ($en_rifas > 0) {
                return TRUE;
            }
        }

        if ($this->db->table_exists('rif_planes')) {
            $en_planes = $this->db
                ->where('plan_moneda', $codigo)
                ->where('plan_eliminado', 0)
                ->count_all_results('rif_planes');
            if ($en_planes > 0) {
                return TRUE;
            }
        }

        if ($this->db->table_exists('rif_tickets')) {
            $en_tickets = $this->db
                ->where('tick_moneda', $codigo)
                ->count_all_results('rif_tickets');
            if ($en_tickets > 0) {
                return TRUE;
            }
        }

        if ($this->db->table_exists('cab_movplanes')) {
            $en_mov = $this->db
                ->where('mov_moneda', $codigo)
                ->count_all_results('cab_movplanes');
            if ($en_mov > 0) {
                return TRUE;
            }
        }

        if ($this->db->table_exists('cab_planes')) {
            $en_cab = $this->db
                ->where('pla_moneda', $codigo)
                ->where('pla_eliminado', 0)
                ->count_all_results('cab_planes');
            if ($en_cab > 0) {
                return TRUE;
            }
        }

        return FALSE;
    }

    // ─────────────────────────────────────────────────────────
    // TASAS
    // ─────────────────────────────────────────────────────────

    public function get_tasa_actual($mon_id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        return $this->db
            ->select('tasa_id, tasa_mon_id, tasa_valor, tasa_fecha, tasa_origen')
            ->where('tasa_mon_id', (int)$mon_id)
            ->where('tasa_status', 1)
            ->where('tasa_fecha <=', date('Y-m-d'))
            ->order_by('tasa_fecha', 'DESC')
            ->order_by('tasa_id', 'DESC')
            ->limit(1)
            ->get($this->table_tasas)
            ->row_array();
    }

    public function get_tasas($mon_id, $limit = 100)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $limit = max(1, min(500, (int)$limit));

        return $this->db
            ->select('tasa_id, tasa_mon_id, tasa_valor, tasa_fecha, tasa_origen, tasa_status, tasa_created_at, tasa_updated_at')
            ->where('tasa_mon_id', (int)$mon_id)
            ->where('tasa_status', 1)
            ->order_by('tasa_fecha', 'DESC')
            ->order_by('tasa_id', 'DESC')
            ->limit($limit)
            ->get($this->table_tasas)
            ->result_array();
    }

    /**
     * Inserta o actualiza la tasa de una moneda para una fecha.
     *
     * @param  int    $mon_id
     * @param  float  $valor
     * @param  string $fecha Y-m-d
     * @param  string $origen manual|api
     * @return array
     */
    public function guardar_tasa($mon_id, $valor, $fecha, $origen = 'manual')
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible'];
        }

        $moneda = $this->get_por_id($mon_id);
        if (empty($moneda)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        $valor = round((float)$valor, 10);
        if ((string)$moneda['mon_codigo'] === 'USD') {
            $valor = 1.0;
        }

        if ($valor <= 0) {
            return ['ok' => FALSE, 'motivo' => 'valor_invalido'];
        }

        $origen = ($origen === 'api') ? 'api' : 'manual';
        $ahora  = date('Y-m-d H:i:s');

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();

        try {
            $existente = $this->db->query(
                "SELECT tasa_id FROM {$this->table_tasas}"
                . " WHERE tasa_mon_id = ? AND tasa_fecha = ? LIMIT 1 FOR UPDATE",
                [(int)$mon_id, (string)$fecha]
            )->row_array();

            if (!empty($existente)) {
                $this->db->where('tasa_id', (int)$existente['tasa_id'])->update($this->table_tasas, [
                    'tasa_valor'     => $valor,
                    'tasa_origen'    => $origen,
                    'tasa_status'    => 1,
                    'tasa_updated_at' => $ahora,
                ]);
                $accion = 'actualizada';
            } else {
                $this->db->insert($this->table_tasas, [
                    'tasa_mon_id'    => (int)$mon_id,
                    'tasa_valor'     => $valor,
                    'tasa_fecha'     => (string)$fecha,
                    'tasa_origen'    => $origen,
                    'tasa_status'    => 1,
                    'tasa_created_at' => $ahora,
                    'tasa_updated_at' => $ahora,
                ]);
                $accion = 'creada';
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return [
                'ok'     => TRUE,
                'accion' => $accion,
                'actual' => $this->get_tasa_actual($mon_id),
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Moneda_model::guardar_tasa no pudo guardar la tasa.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Conversión simple usando tasas actuales contra USD.
     *
     * @param  float  $monto
     * @param  string $desde
     * @param  string $hasta
     * @return float|null
     */
    public function convertir($monto, $desde, $hasta)
    {
        $desde = strtoupper(trim((string)$desde));
        $hasta = strtoupper(trim((string)$hasta));

        if (!is_numeric($monto) || !is_finite((float)$monto) || (float)$monto < 0) {
            return NULL;
        }

        if ($desde === $hasta) {
            return (float)$monto;
        }

        $origen  = $this->get_por_codigo($desde);
        $destino = $this->get_por_codigo($hasta);

        if (empty($origen) || empty($destino)
            || (int)$origen['mon_status'] !== 1
            || (int)$destino['mon_status'] !== 1) {
            return NULL;
        }

        $tasa_origen  = $this->get_tasa_actual((int)$origen['mon_id']);
        $tasa_destino = $this->get_tasa_actual((int)$destino['mon_id']);

        if (empty($tasa_origen) || empty($tasa_destino)
            || (float)$tasa_origen['tasa_valor'] <= 0
            || (float)$tasa_destino['tasa_valor'] <= 0) {
            return NULL;
        }

        $usd = (float)$monto / (float)$tasa_origen['tasa_valor'];

        return round($usd * (float)$tasa_destino['tasa_valor'], 10);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────

    private function _insertar_tasa($mon_id, $valor, $fecha, $origen = 'manual')
    {
        $ahora = date('Y-m-d H:i:s');

        return $this->db->insert($this->table_tasas, [
            'tasa_mon_id'     => (int)$mon_id,
            'tasa_valor'      => round((float)$valor, 10),
            'tasa_fecha'      => (string)$fecha,
            'tasa_origen'     => ($origen === 'api') ? 'api' : 'manual',
            'tasa_status'     => 1,
            'tasa_created_at' => $ahora,
            'tasa_updated_at' => $ahora,
        ]);
    }

    private function _filtrar_guardado(array $data)
    {
        return array_intersect_key($data, array_flip($this->columnas_guardado));
    }
}
