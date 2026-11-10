<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_asignacion_model
 * ─────────────────────────────────────────────────────────────
 * Modelo de lotes/asignaciones y consumo de cupos de planes (M02.1).
 *
 * - Tabla principal: rif_plan_asignaciones (asig_id).
 * - Planes: rif_planes (plan_id).
 * - Organizadores: rif_organizadores (org_id).
 * - Numeraciones: rif_numeraciones (num_id).
 * - Consumos: rif_plan_consumos (cons_id).
 * - Un organizador puede tener varios lotes activos simultáneamente.
 * - Todas las consultas usan query builder o bindings.
 * - Las operaciones de cupo usan transacción + FOR UPDATE + UNIQUE.
 * - Escrituras con db_debug temporal desactivado para no filtrar SQL.
 * - Incluye guardas table_exists para operar antes de aplicar migraciones.
 */
class Plan_asignacion_model extends CI_Model
{
    protected $table = 'rif_plan_asignaciones';

    protected $table_planes = 'rif_planes';

    protected $table_org = 'rif_organizadores';

    protected $table_consumos = 'rif_plan_consumos';

    protected $table_numeraciones = 'rif_numeraciones';

    protected $primary_key = 'asig_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     * 0 organizador, 1 plan, 2 numeración, 3 cantidad, 4 uso,
     * 5 precio, 6 vigencia, 7 estado, 8 acciones.
     */
    protected $columnas_ordenables = [
        0 => 'o.org_nombre_publico',
        1 => 'p.plan_nombre',
        2 => 'n.num_label',
        3 => 'a.asig_cantidad',
        4 => 'a.asig_rifas_usadas',
        5 => 'a.asig_total',
        6 => 'a.asig_inicio',
        7 => 'a.asig_estado',
        8 => 'a.asig_id',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Indica si las tablas base de asignaciones están disponibles.
     *
     * @return bool
     */
    public function tablas_disponibles()
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists($this->table_planes)
            && $this->db->table_exists($this->table_org);
    }

    /**
     * Indica si el catálogo de numeraciones está disponible.
     *
     * @return bool
     */
    protected function numeraciones_disponibles()
    {
        return $this->db->table_exists($this->table_numeraciones);
    }

    // ─────────────────────────────────────────────────────────
    // CONTRATO DATATABLES SERVER-SIDE
    // ─────────────────────────────────────────────────────────

    /**
     * Total de asignaciones.
     *
     * @return int
     */
    public function count_todos()
    {
        if (!$this->tablas_disponibles()) {
            return 0;
        }

        $this->_datatable_query('');
        return (int)$this->db->count_all_results();
    }

    /**
     * Total de asignaciones tras aplicar la búsqueda.
     *
     * @param  string $search
     * @return int
     */
    public function count_filtrados($search = '')
    {
        if (!$this->tablas_disponibles()) {
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
        if (!$this->tablas_disponibles()) {
            return [];
        }

        $this->_datatable_query($search);
        $this->db->select($this->_select_lote_fields());

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'a.asig_id';

        if (!$this->numeraciones_disponibles() && $columna === 'n.num_label') {
            $columna = 'a.asig_id';
        }

        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, max(0, (int)$start));
        }

        $rows = $this->db->get()->result_array();

        return array_map([$this, '_normalizar_lote'], $rows);
    }

    /**
     * Query base para conteos y listado DataTables.
     *
     * @param  string $search
     * @return void
     */
    private function _datatable_query($search = '')
    {
        $this->db->from($this->table . ' a');
        $this->db->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner');
        $this->db->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner');

        if ($this->numeraciones_disponibles()) {
            $this->db->join($this->table_numeraciones . ' n', 'n.num_id = a.asig_num_id', 'left');
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('o.org_nombre_publico', $search);
            $this->db->or_like('o.org_slug', $search);
            $this->db->or_like('p.plan_nombre', $search);
            $this->db->or_like('a.asig_estado', $search);
            $this->db->or_like('a.asig_origen', $search);
            if ($this->numeraciones_disponibles()) {
                $this->db->or_like('n.num_label', $search);
            }
            $this->db->group_end();
        }
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene una asignación por ID (sin filtrar estado).
     *
     * @param  int $id
     * @return array|null
     */
    public function get_por_id($id)
    {
        if (!$this->tablas_disponibles()) {
            return NULL;
        }

        $this->db
            ->select($this->_select_lote_fields())
            ->from($this->table . ' a')
            ->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner')
            ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner');

        if ($this->numeraciones_disponibles()) {
            $this->db->join($this->table_numeraciones . ' n', 'n.num_id = a.asig_num_id', 'left');
        }

        $row = $this->db
            ->where('a.asig_id', (int)$id)
            ->limit(1)
            ->get()
            ->row_array();

        return empty($row) ? NULL : $this->_normalizar_lote($row);
    }

    /**
     * Lotes activos y vigentes de un organizador.
     *
     * Devuelve todos los lotes disponibles para operar, incluidos los que ya
     * agotaron su cupo (restantes = 0), para que la UI pueda explicar el estado.
     *
     * @param  int $org_id
     * @return array
     */
    public function get_disponibles_por_org($org_id)
    {
        if (!$this->tablas_disponibles()) {
            return [];
        }

        $this->db
            ->select($this->_select_lote_fields())
            ->from($this->table . ' a')
            ->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner')
            ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner');

        if ($this->numeraciones_disponibles()) {
            $this->db->join($this->table_numeraciones . ' n', 'n.num_id = a.asig_num_id', 'left');
        }

        $rows = $this->db
            ->where('a.asig_org_id', (int)$org_id)
            ->where('a.asig_estado', 'activa')
            ->group_start()
                ->where('a.asig_fin IS NULL', NULL, FALSE)
                ->or_where('a.asig_fin >=', 'NOW()', FALSE)
            ->group_end()
            ->order_by('a.asig_id', 'ASC')
            ->get()
            ->result_array();

        return array_map([$this, '_normalizar_lote'], $rows);
    }

    /**
     * Obtiene el primer lote utilizable de un organizador.
     *
     * Se conserva por compatibilidad con M03: devuelve un lote limitado con
     * cupo o un lote ilimitado vigente.
     *
     * @param  int  $org_id
     * @param  bool $normalizar
     * @return array|null
     */
    public function get_activa_por_org($org_id, $normalizar = TRUE)
    {
        if (!$this->tablas_disponibles()) {
            return NULL;
        }

        if ($normalizar) {
            $this->normalizar_vencidas((int)$org_id);
        }

        foreach ($this->get_disponibles_por_org($org_id) as $lote) {
            if (!empty($lote['ilimitado']) || (int)$lote['restantes'] > 0) {
                return $lote;
            }
        }

        return NULL;
    }

    /**
     * Obtiene un lote concreto del organizador si está activo y vigente.
     *
     * @param  int $org_id
     * @param  int $asig_id
     * @return array|null
     */
    public function get_lote_disponible_por_id($org_id, $asig_id)
    {
        return $this->get_lote_por_org($org_id, $asig_id);
    }

    /**
     * Obtiene un lote concreto del organizador con plan y numeración.
     *
     * No filtra por cupo restante: un lote agotado debe poder consultarse
     * para detalle, reactivación y validaciones de consistencia. Solo exige
     * pertenencia al organizador, estado activo y vigencia.
     *
     * @param  int $org_id
     * @param  int $asig_id
     * @return array|null
     */
    public function get_lote_por_org($org_id, $asig_id)
    {
        if (!$this->tablas_disponibles() || (int)$org_id <= 0 || (int)$asig_id <= 0) {
            return NULL;
        }

        $this->db
            ->select($this->_select_lote_fields())
            ->from($this->table . ' a')
            ->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner')
            ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner');

        if ($this->numeraciones_disponibles()) {
            $this->db->join($this->table_numeraciones . ' n', 'n.num_id = a.asig_num_id', 'left');
        }

        $row = $this->db
            ->where('a.asig_id', (int)$asig_id)
            ->where('a.asig_org_id', (int)$org_id)
            ->where('a.asig_estado', 'activa')
            ->group_start()
                ->where('a.asig_fin IS NULL', NULL, FALSE)
                ->or_where('a.asig_fin >=', 'NOW()', FALSE)
            ->group_end()
            ->limit(1)
            ->get()
            ->row_array();

        return empty($row) ? NULL : $this->_normalizar_lote($row);
    }

    /**
     * Indica si un lote pertenece al organizador, está activo y vigente.
     *
     * @param  int $org_id
     * @param  int $asig_id
     * @return bool
     */
    public function lote_vigente($org_id, $asig_id)
    {
        return $this->get_lote_por_org($org_id, $asig_id) !== NULL;
    }

    /**
     * Marca como vencidas las asignaciones activas cuya vigencia terminó.
     *
     * @param  int|null $org_id
     * @return int Filas afectadas
     */
    public function normalizar_vencidas($org_id = NULL)
    {
        if (!$this->db->table_exists($this->table)) {
            return 0;
        }

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            $this->db
                ->where('asig_estado', 'activa')
                ->where('asig_fin IS NOT NULL', NULL, FALSE)
                ->where('asig_fin <', 'NOW()', FALSE);

            if ($org_id !== NULL && (int)$org_id > 0) {
                $this->db->where('asig_org_id', (int)$org_id);
            }

            $this->db->update($this->table, [
                'asig_estado'     => 'vencida',
                'asig_updated_at' => date('Y-m-d H:i:s'),
            ]);

            return (int)$this->db->affected_rows();
        } catch (Throwable $e) {
            log_message('error', 'Plan_asignacion_model::normalizar_vencidas no pudo actualizar vencidas.');
            return 0;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Estado seguro de publicación de un organizador.
     *
     * No escribe en la BD: la normalización de vencidas se reserva para las
     * operaciones de escritura.
     *
     * @param  int $org_id
     * @return array
     */
    public function get_estado_por_org($org_id)
    {
        $neutro = [
            'tiene_plan'      => FALSE,
            'asignacion'      => NULL,
            'plan'            => NULL,
            'usadas'          => 0,
            'limite'          => NULL,
            'restantes'       => NULL,
            'ilimitado'       => FALSE,
            'puede_publicar'  => FALSE,
            'motivo'          => 'sin_plan',
            'fin'             => NULL,
            'asignaciones'    => [],
        ];

        if (!$this->tablas_disponibles()) {
            return $neutro;
        }

        $lotes = $this->get_disponibles_por_org((int)$org_id);

        if (empty($lotes)) {
            // Si la última asignación venció, mostrarla como "vencida" en
            // lugar de un estado neutral que oculte el motivo.
            $vencida = $this->_get_vencida_por_org((int)$org_id);

            if (!empty($vencida)) {
                $vencida = $this->_normalizar_lote($vencida);
                $tipo      = (string)($vencida['plan_tipo'] ?? 'rifas');
                $ilimitado = ($tipo === 'ilimitado');
                $limite    = $ilimitado ? NULL : (int)($vencida['asig_cantidad'] ?? 0);
                $usadas    = (int)($vencida['asig_rifas_usadas'] ?? 0);

                return [
                    'tiene_plan'      => TRUE,
                    'asignacion'      => $vencida,
                    'plan'            => $this->_plan_desde_lote($vencida),
                    'usadas'          => $usadas,
                    'limite'          => $limite,
                    'restantes'       => 0,
                    'ilimitado'       => $ilimitado,
                    'puede_publicar'  => FALSE,
                    'motivo'          => 'vencida',
                    'fin'             => $vencida['asig_fin'] ?? NULL,
                    'asignaciones'    => [],
                ];
            }

            return $neutro;
        }

        $elegida = NULL;
        foreach ($lotes as $lote) {
            if (!empty($lote['ilimitado']) || (int)$lote['restantes'] > 0) {
                $elegida = $lote;
                break;
            }
        }

        $puede_publicar = ($elegida !== NULL);
        $compat         = $elegida !== NULL ? $elegida : $lotes[0];

        $tipo      = (string)($compat['plan_tipo'] ?? 'rifas');
        $ilimitado = ($tipo === 'ilimitado');
        $limite    = $ilimitado ? NULL : (int)($compat['asig_cantidad'] ?? 0);
        $usadas    = (int)($compat['asig_rifas_usadas'] ?? 0);
        $restantes = $ilimitado ? NULL : max(0, (int)($compat['restantes'] ?? 0));

        return [
            'tiene_plan'      => TRUE,
            'asignacion'      => $compat,
            'plan'            => $this->_plan_desde_lote($compat),
            'usadas'          => $usadas,
            'limite'          => $limite,
            'restantes'       => $restantes,
            'ilimitado'       => $ilimitado,
            'puede_publicar'  => $puede_publicar,
            'motivo'          => $puede_publicar ? '' : 'sin_cupo',
            'fin'             => $compat['asig_fin'] ?? NULL,
            'asignaciones'    => $lotes,
        ];
    }

    /**
     * Indica si el organizador puede publicar una nueva rifa.
     *
     * @param  int      $org_id
     * @param  int|null $asig_id
     * @return array ['ok' => bool, 'motivo' => string, 'estado' => array|null]
     */
    public function puede_publicar($org_id, $asig_id = NULL)
    {
        if ($asig_id !== NULL && (int)$asig_id > 0) {
            $lote = $this->get_lote_disponible_por_id((int)$org_id, (int)$asig_id);

            if (empty($lote)) {
                return [
                    'ok'     => FALSE,
                    'motivo' => 'asignacion_invalida',
                    'estado' => NULL,
                ];
            }

            $ok = !empty($lote['ilimitado']) || (int)$lote['restantes'] > 0;

            return [
                'ok'     => $ok,
                'motivo' => $ok ? '' : 'sin_cupo',
                'estado' => [
                    'tiene_plan'     => TRUE,
                    'asignacion'     => $lote,
                    'plan'           => $this->_plan_desde_lote($lote),
                    'usadas'         => (int)$lote['asig_rifas_usadas'],
                    'limite'         => !empty($lote['ilimitado']) ? NULL : (int)$lote['asig_cantidad'],
                    'restantes'      => $lote['restantes'],
                    'ilimitado'      => !empty($lote['ilimitado']),
                    'puede_publicar' => $ok,
                    'motivo'         => $ok ? '' : 'sin_cupo',
                    'fin'            => $lote['asig_fin'],
                    'asignaciones'   => [$lote],
                ],
            ];
        }

        $estado = $this->get_estado_por_org($org_id);

        return [
            'ok'     => (bool)$estado['puede_publicar'],
            'motivo' => (string)$estado['motivo'],
            'estado' => $estado,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * Asigna un lote de plan a un organizador.
     *
     * Para planes por rifas exige una numeración activa del plan y la cantidad
     * comprada. Para planes ilimitados registra la vigencia según el periodo.
     * No reemplaza lotes anteriores: cada compra/asignación es un lote nuevo.
     *
     * @param  int         $org_id
     * @param  int         $plan_id
     * @param  int|null    $num_id
     * @param  int|null    $cantidad
     * @param  string      $origen
     * @param  string      $notas
     * @return array ['ok' => bool, 'asig_id' => int|null, 'motivo' => string]
     */
    public function asignar($org_id, $plan_id, $num_id = NULL, $cantidad = NULL, $origen = 'manual', $notas = '')
    {
        if (!$this->tablas_disponibles()) {
            return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'error'];
        }

        $org_id  = (int)$org_id;
        $plan_id = (int)$plan_id;
        $num_id  = ($num_id !== NULL) ? (int)$num_id : NULL;
        $cantidad = ($cantidad !== NULL) ? (int)$cantidad : NULL;

        $origenes = ['manual', 'compra', 'cortesia', 'admin'];
        $origen   = in_array($origen, $origenes, TRUE) ? $origen : 'manual';
        $notas    = $this->_limitar_texto(trim((string)$notas), 255);

        $inicio = date('Y-m-d H:i:s');
        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            // Bloqueo por organizador: serializa asignaciones concurrentes.
            $org_lock = $this->db->query(
                "SELECT org_id FROM {$this->table_org}"
                . " WHERE org_id = ? AND org_status = 1 AND org_eliminado = 0 FOR UPDATE",
                [$org_id]
            )->row_array();

            if (empty($org_lock)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'org_invalido'];
            }

            // Leer y bloquear el plan dentro de la misma transacción.
            $plan = $this->db->query(
                'SELECT plan_id, plan_tipo, plan_limite, plan_periodo, plan_precio,'
                . ' plan_eliminado, plan_status'
                . ' FROM ' . $this->table_planes
                . ' WHERE plan_id = ? AND plan_eliminado = 0 AND plan_status = 1'
                . ' LIMIT 1 FOR UPDATE',
                [$plan_id]
            )->row_array();

            if (empty($plan)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'plan_invalido'];
            }

            $tipo = (string)$plan['plan_tipo'];

            if ($tipo === 'ilimitado') {
                $asig_num_id      = NULL;
                $asig_cantidad    = NULL;
                $precio_unitario  = round((float)$plan['plan_precio'], 2);
                $total            = $precio_unitario;

                switch ((string)$plan['plan_periodo']) {
                    case 'mensual':
                        $fin = date('Y-m-d H:i:s', strtotime('+1 month'));
                        break;

                    case 'anual':
                        $fin = date('Y-m-d H:i:s', strtotime('+1 year'));
                        break;

                    default:
                        $fin = NULL;
                        break;
                }
            } else {
                $asig_num_id = $num_id !== NULL ? (int)$num_id : 0;
                $asig_cantidad = $cantidad !== NULL ? (int)$cantidad : 0;

                if ($asig_num_id <= 0 || $asig_cantidad <= 0) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'datos_invalidos'];
                }

                // La numeración debe ser una opción activa del plan.
                if (!$this->db->table_exists($this->table_opciones_sql())) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'numeracion_invalida'];
                }

                $opcion = $this->db->query(
                    "SELECT pn.pnum_precio FROM {$this->table_opciones_sql()} pn"
                    . " INNER JOIN {$this->table_numeraciones} n ON n.num_id = pn.pnum_num_id"
                    . " WHERE pn.pnum_plan_id = ? AND pn.pnum_num_id = ?"
                    . " AND pn.pnum_status = 1 AND n.num_status = 1"
                    . " LIMIT 1 FOR UPDATE",
                    [$plan_id, $asig_num_id]
                )->row_array();

                if (empty($opcion)) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'numeracion_invalida'];
                }

                $precio_unitario = round((float)$opcion['pnum_precio'], 2);
                $total           = round($precio_unitario * $asig_cantidad, 2);
                $fin             = NULL;
            }

            $insertado = $this->db->insert($this->table, [
                'asig_org_id'         => $org_id,
                'asig_plan_id'        => $plan_id,
                'asig_num_id'         => $asig_num_id,
                'asig_cantidad'       => $asig_cantidad,
                'asig_precio_unitario' => $precio_unitario,
                'asig_total'          => $total,
                'asig_origen'         => $origen,
                'asig_inicio'         => $inicio,
                'asig_fin'            => $fin,
                'asig_rifas_usadas'   => 0,
                'asig_estado'         => 'activa',
                'asig_notas'          => $notas !== '' ? $notas : NULL,
                'asig_created_at'     => $inicio,
                'asig_updated_at'     => $inicio,
            ]);

            if (!$insertado || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'error'];
            }

            $asig_id = (int)$this->db->insert_id();
            $this->db->trans_commit();

            return ['ok' => TRUE, 'asig_id' => $asig_id, 'motivo' => ''];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::asignar no pudo completar la asignación.');
            return ['ok' => FALSE, 'asig_id' => NULL, 'motivo' => 'error'];
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Cancela una asignación activa.
     *
     * @param  int    $id
     * @param  string $notas
     * @return bool
     */
    public function cancelar($id, $notas = '')
    {
        if (!$this->db->table_exists($this->table)) {
            return FALSE;
        }

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            $asig = $this->db->query(
                "SELECT asig_id, asig_estado, asig_notas FROM {$this->table}"
                . " WHERE asig_id = ? LIMIT 1 FOR UPDATE",
                [(int)$id]
            )->row_array();

            if (empty($asig) || (string)$asig['asig_estado'] !== 'activa') {
                $this->db->trans_rollback();
                return FALSE;
            }

            $datos = [
                'asig_estado'     => 'cancelada',
                'asig_updated_at' => date('Y-m-d H:i:s'),
            ];

            $notas = $this->_limitar_texto(trim((string)$notas), 255);
            if ($notas !== '') {
                $notas_previas = trim((string)($asig['asig_notas'] ?? ''));
                $notas_finales = trim($notas_previas !== '' ? $notas_previas . ' | ' . $notas : $notas);
                $datos['asig_notas'] = $this->_limitar_texto($notas_finales, 255);
            }

            $ok = $this->db
                ->where('asig_id', (int)$id)
                ->where('asig_estado', 'activa')
                ->update($this->table, $datos);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::cancelar no pudo cancelar la asignación.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Consume un cupo de un lote para una rifa.
     *
     * Si se indica $asig_id se usa ese lote (debe pertenecer al organizador).
     * Si no, se elige el primer lote disponible con cupo o ilimitado.
     *
     * M03 DEBE verificar que `rifa_id` pertenece a `org_id` antes de llamar
     * a este método. El modelo solo controla el cupo y la idempotencia.
     *
     * @param  int      $org_id
     * @param  int      $rifa_id
     * @param  string   $motivo
     * @param  int|null $asig_id
     * @param  int|null $num_id
     * @return array
     */
    public function consumir_para_rifa($org_id, $rifa_id, $motivo = '', $asig_id = NULL, $num_id = NULL)
    {
        if (!$this->tablas_disponibles() || !$this->db->table_exists($this->table_consumos)) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $rifa_id = (int)$rifa_id;
        if ($rifa_id <= 0) {
            return ['ok' => FALSE, 'motivo' => 'rifa_invalida'];
        }

        $asig_id = ($asig_id !== NULL && (int)$asig_id > 0) ? (int)$asig_id : NULL;
        $num_id  = ($num_id !== NULL && (int)$num_id > 0) ? (int)$num_id : NULL;
        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            $this->normalizar_vencidas((int)$org_id);

            // Una misma rifa no puede consumir dos lotes distintos. La
            // verificación es por rifa y organización, no solo por lote, para
            // bloquear reintentos con un lote diferente.
            $ya_consumido = (int)$this->db->query(
                "SELECT COUNT(*) AS total FROM {$this->table_consumos}"
                . " WHERE cons_org_id = ? AND cons_rifa_id = ? AND cons_estado = 'consumido'",
                [(int)$org_id, $rifa_id]
            )->row()->total;

            if ($ya_consumido > 0) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'ya_consumido'];
            }

            $select = "SELECT a.asig_id, a.asig_org_id, a.asig_plan_id, a.asig_num_id,"
                . " a.asig_cantidad, a.asig_rifas_usadas, a.asig_estado, a.asig_fin,"
                . " p.plan_tipo, p.plan_limite, p.plan_periodo"
                . " FROM {$this->table} a"
                . " INNER JOIN {$this->table_planes} p ON p.plan_id = a.asig_plan_id";

            if ($asig_id !== NULL) {
                $sql = $select
                    . " WHERE a.asig_id = ? AND a.asig_org_id = ? AND a.asig_estado = 'activa'"
                    . " AND (a.asig_fin IS NULL OR a.asig_fin >= NOW())"
                    . " LIMIT 1 FOR UPDATE";

                $asig = $this->db->query($sql, [$asig_id, (int)$org_id])->row_array();
            } else {
                $sql = $select
                    . " WHERE a.asig_org_id = ? AND a.asig_estado = 'activa'"
                    . " AND (a.asig_fin IS NULL OR a.asig_fin >= NOW())"
                    . " AND (p.plan_tipo = 'ilimitado'"
                    . "      OR COALESCE(a.asig_cantidad, p.plan_limite) > a.asig_rifas_usadas)"
                    . " ORDER BY a.asig_id ASC LIMIT 1 FOR UPDATE";

                $asig = $this->db->query($sql, [(int)$org_id])->row_array();
            }

            if (empty($asig)) {
                $this->db->trans_rollback();
                return [
                    'ok'     => FALSE,
                    'motivo' => $asig_id !== NULL ? 'asignacion_invalida' : 'sin_plan',
                ];
            }

            $tipo       = (string)$asig['plan_tipo'];
            $ilimitado  = ($tipo === 'ilimitado');
            $usadas     = (int)$asig['asig_rifas_usadas'];
            $cantidad   = $ilimitado
                ? NULL
                : (int)($asig['asig_cantidad'] !== NULL ? $asig['asig_cantidad'] : $asig['plan_limite']);
            $restantes  = $ilimitado ? NULL : max(0, $cantidad - $usadas);

            // La numeración debe ser coherente con el lote: en lotes
            // limitados la numeración está fijada por la compra; en lotes
            // ilimitados debe ser una numeración activa del catálogo.
            $asig_num_id = ($asig['asig_num_id'] !== NULL && (int)$asig['asig_num_id'] > 0)
                ? (int)$asig['asig_num_id']
                : NULL;

            if ($num_id !== NULL) {
                if ($ilimitado) {
                    if ($asig_num_id !== NULL || !$this->numeraciones_disponibles()) {
                        $this->db->trans_rollback();
                        return ['ok' => FALSE, 'motivo' => 'numeracion_invalida'];
                    }

                    $num_activa = (int)$this->db
                        ->where('num_id', $num_id)
                        ->where('num_status', 1)
                        ->count_all_results($this->table_numeraciones);

                    if ($num_activa <= 0) {
                        $this->db->trans_rollback();
                        return ['ok' => FALSE, 'motivo' => 'numeracion_invalida'];
                    }
                } elseif ($asig_num_id !== $num_id) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'numeracion_invalida'];
                }
            }

            $num_efectivo = $ilimitado ? $num_id : $asig_num_id;

            $motivo = $this->_limitar_texto(trim((string)$motivo), 255);

            // La idempotencia se evalúa antes del cupo: un reintento de la
            // misma rifa debe responder ya_consumido aunque el cupo esté lleno.
            // Un consumo devuelto sí puede reutilizarse.
            $existente = $this->db->query(
                "SELECT cons_id, cons_estado FROM {$this->table_consumos}"
                . " WHERE cons_asig_id = ? AND cons_rifa_id = ? LIMIT 1 FOR UPDATE",
                [(int)$asig['asig_id'], $rifa_id]
            )->row_array();

            if (!empty($existente) && (string)$existente['cons_estado'] === 'consumido') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'ya_consumido'];
            }

            if (!$ilimitado && $restantes <= 0) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'sin_cupo'];
            }

            if (!empty($existente)) {
                $actualizado = $this->db
                    ->where('cons_id', (int)$existente['cons_id'])
                    ->update($this->table_consumos, [
                        'cons_estado'     => 'consumido',
                        'cons_motivo'     => $motivo !== '' ? $motivo : NULL,
                        'cons_updated_at' => date('Y-m-d H:i:s'),
                    ]);

                if (!$actualizado) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'error'];
                }

                $cons_id = (int)$existente['cons_id'];
            } else {
                $insertado = $this->db->insert($this->table_consumos, [
                    'cons_asig_id'    => (int)$asig['asig_id'],
                    'cons_org_id'     => (int)$org_id,
                    'cons_rifa_id'    => $rifa_id,
                    'cons_estado'     => 'consumido',
                    'cons_motivo'     => $motivo !== '' ? $motivo : NULL,
                    'cons_created_at' => date('Y-m-d H:i:s'),
                    'cons_updated_at' => date('Y-m-d H:i:s'),
                ]);

                if (!$insertado) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'error'];
                }

                $cons_id = (int)$this->db->insert_id();
            }

            $this->db
                ->set('asig_rifas_usadas', 'asig_rifas_usadas + 1', FALSE)
                ->set('asig_updated_at', date('Y-m-d H:i:s'))
                ->where('asig_id', (int)$asig['asig_id'])
                ->update($this->table);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return [
                'ok'        => TRUE,
                'cons_id'   => $cons_id,
                'asig_id'   => (int)$asig['asig_id'],
                'num_id'    => $num_efectivo,
                'usadas'    => $usadas + 1,
                'restantes' => $ilimitado ? NULL : max(0, $cantidad - ($usadas + 1)),
                'motivo'    => '',
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::consumir_para_rifa no pudo consumir el cupo.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Devuelve el consumo de una rifa concreta.
     *
     * @param  int    $org_id
     * @param  int    $rifa_id
     * @param  string $motivo
     * @return array
     */
    public function devolver_por_rifa($org_id, $rifa_id, $motivo = '')
    {
        if (!$this->db->table_exists($this->table) || !$this->db->table_exists($this->table_consumos)) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $rifa_id = (int)$rifa_id;
        if ($rifa_id <= 0) {
            return ['ok' => FALSE, 'motivo' => 'rifa_invalida'];
        }

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            $sql = "SELECT cons_id, cons_asig_id FROM {$this->table_consumos}"
                . " WHERE cons_org_id = ? AND cons_rifa_id = ? AND cons_estado = 'consumido'"
                . " ORDER BY cons_id DESC LIMIT 1 FOR UPDATE";

            $cons = $this->db->query($sql, [(int)$org_id, $rifa_id])->row_array();

            if (empty($cons)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_consumo'];
            }

            if (!$this->_devolver_consumo($cons, $motivo)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return [
                'ok'      => TRUE,
                'cons_id' => (int)$cons['cons_id'],
                'asig_id' => (int)$cons['cons_asig_id'],
                'motivo'  => '',
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::devolver_por_rifa no pudo devolver el consumo.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Devuelve un consumo por su ID.
     *
     * @param  int      $cons_id
     * @param  string   $motivo
     * @param  int|null $org_id
     * @return array
     */
    public function devolver($cons_id, $motivo = '', $org_id = NULL)
    {
        if (!$this->db->table_exists($this->table) || !$this->db->table_exists($this->table_consumos)) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $cons_id = (int)$cons_id;
        if ($cons_id <= 0) {
            return ['ok' => FALSE, 'motivo' => 'cons_invalido'];
        }

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();

            $sql    = "SELECT cons_id, cons_asig_id FROM {$this->table_consumos}"
                . " WHERE cons_id = ? AND cons_estado = 'consumido'";
            $binds  = [$cons_id];

            if ($org_id !== NULL && (int)$org_id > 0) {
                $sql .= ' AND cons_org_id = ?';
                $binds[] = (int)$org_id;
            }

            $sql .= ' LIMIT 1 FOR UPDATE';

            $cons = $this->db->query($sql, $binds)->row_array();

            if (empty($cons)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_consumo'];
            }

            if (!$this->_devolver_consumo($cons, $motivo)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return [
                'ok'      => TRUE,
                'cons_id' => (int)$cons['cons_id'],
                'asig_id' => (int)$cons['cons_asig_id'],
                'motivo'  => '',
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::devolver no pudo devolver el consumo.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Consumos registrados para una asignación (para modal).
     *
     * @param  int $asig_id
     * @return array
     */
    public function get_consumos_por_asignacion($asig_id)
    {
        if (!$this->db->table_exists($this->table_consumos)) {
            return [];
        }

        return $this->db
            ->select('cons_id, cons_asig_id, cons_org_id, cons_rifa_id, cons_estado,'
                . ' cons_motivo, cons_created_at, cons_created_at AS created_at')
            ->where('cons_asig_id', (int)$asig_id)
            ->order_by('cons_id', 'DESC')
            ->get($this->table_consumos)
            ->result_array();
    }

    /**
     * Recalcula el contador de rifas usadas de una asignación.
     *
     * @param  int $asig_id
     * @return bool
     */
    public function recalcular_usadas($asig_id)
    {
        if (!$this->db->table_exists($this->table) || !$this->db->table_exists($this->table_consumos)) {
            return FALSE;
        }

        $usadas = (int)$this->db
            ->where('cons_asig_id', (int)$asig_id)
            ->where('cons_estado', 'consumido')
            ->count_all_results($this->table_consumos);

        $debug_previo = $this->db->db_debug;

        try {
            $this->db->db_debug = FALSE;

            return (bool)$this->db
                ->where('asig_id', (int)$asig_id)
                ->update($this->table, [
                    'asig_rifas_usadas' => $usadas,
                    'asig_updated_at'   => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable $e) {
            log_message('error', 'Plan_asignacion_model::recalcular_usadas no pudo recalcular el uso.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Nombre de la tabla de opciones por plan.
     *
     * @return string
     */
    private function table_opciones_sql()
    {
        return 'rif_plan_numeraciones';
    }

    /**
     * Campos seleccionados para un lote con datos de plan y numeración.
     *
     * @return string
     */
    private function _select_lote_fields()
    {
        $campos = 'a.*, o.org_nombre_publico, o.org_slug, o.org_status,'
            . ' p.plan_nombre, p.plan_tipo, p.plan_limite, p.plan_periodo,'
            . ' p.plan_precio, p.plan_moneda, p.plan_eliminado';

        if ($this->numeraciones_disponibles()) {
            $campos .= ', n.num_label, n.num_cantidad, n.num_desde, n.num_hasta, n.num_padding';
        }

        return $campos;
    }

    /**
     * Normaliza un lote y calcula cupo restante / ilimitado.
     *
     * @param  array $row
     * @return array
     */
    private function _normalizar_lote(array $row)
    {
        $tipo      = (string)($row['plan_tipo'] ?? 'rifas');
        $ilimitado = ($tipo === 'ilimitado');

        if ($ilimitado) {
            $row['asig_cantidad'] = NULL;
            $row['restantes']     = NULL;
        } else {
            $cantidad = ($row['asig_cantidad'] !== NULL && $row['asig_cantidad'] !== '')
                ? (int)$row['asig_cantidad']
                : (int)($row['plan_limite'] ?? 0);

            $row['asig_cantidad'] = $cantidad;
            $row['restantes']     = max(0, $cantidad - (int)($row['asig_rifas_usadas'] ?? 0));
        }

        $row['ilimitado'] = $ilimitado;

        // Alias útiles para la capa de presentación.
        $row['num_id'] = isset($row['asig_num_id']) && $row['asig_num_id'] !== NULL
            ? (int)$row['asig_num_id']
            : NULL;

        return $row;
    }

    /**
     * Construye el arreglo "plan" de compatibilidad desde un lote.
     *
     * @param  array $lote
     * @return array
     */
    private function _plan_desde_lote(array $lote)
    {
        return [
            'plan_id'      => (int)($lote['asig_plan_id'] ?? 0),
            'plan_nombre'  => (string)($lote['plan_nombre'] ?? ''),
            'plan_tipo'    => (string)($lote['plan_tipo'] ?? 'rifas'),
            'plan_limite'  => (int)($lote['plan_limite'] ?? 0),
            'plan_periodo' => (string)($lote['plan_periodo'] ?? ''),
            'plan_precio'  => $lote['plan_precio'] ?? 0,
            'plan_moneda'  => (string)($lote['plan_moneda'] ?? ''),
        ];
    }

    /**
     * Obtiene la última asignación vencida de un organizador.
     *
     * @param  int $org_id
     * @return array|null
     */
    private function _get_vencida_por_org($org_id)
    {
        $this->db
            ->select($this->_select_lote_fields())
            ->from($this->table . ' a')
            ->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner')
            ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner');

        if ($this->numeraciones_disponibles()) {
            $this->db->join($this->table_numeraciones . ' n', 'n.num_id = a.asig_num_id', 'left');
        }

        return $this->db
            ->where('a.asig_org_id', (int)$org_id)
            ->where_in('a.asig_estado', ['activa', 'vencida'])
            ->where('a.asig_fin IS NOT NULL', NULL, FALSE)
            ->where('a.asig_fin <', date('Y-m-d H:i:s'))
            ->order_by('a.asig_id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Marca un consumo como devuelto y decrementa el contador de la asignación.
     * Debe invocarse dentro de una transacción abierta.
     *
     * @param  array  $cons
     * @param  string $motivo
     * @return bool
     */
    private function _devolver_consumo(array $cons, $motivo = '')
    {
        $datos = [
            'cons_estado'     => 'devuelto',
            'cons_updated_at' => date('Y-m-d H:i:s'),
        ];

        $motivo = $this->_limitar_texto(trim((string)$motivo), 255);
        if ($motivo !== '') {
            $datos['cons_motivo'] = $motivo;
        }

        $this->db
            ->where('cons_id', (int)$cons['cons_id'])
            ->where('cons_estado', 'consumido')
            ->update($this->table_consumos, $datos);

        if ($this->db->affected_rows() <= 0) {
            return FALSE;
        }

        $this->db
            ->set('asig_rifas_usadas', 'IF(asig_rifas_usadas > 0, asig_rifas_usadas - 1, 0)', FALSE)
            ->set('asig_updated_at', date('Y-m-d H:i:s'))
            ->where('asig_id', (int)$cons['cons_asig_id'])
            ->update($this->table);

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Limita un texto a una longitud segura en caracteres.
     *
     * @param  string $texto
     * @param  int    $max
     * @return string
     */
    private function _limitar_texto($texto, $max)
    {
        $texto = (string)$texto;

        return function_exists('mb_substr')
            ? mb_substr($texto, 0, (int)$max, 'UTF-8')
            : substr($texto, 0, (int)$max);
    }
}
