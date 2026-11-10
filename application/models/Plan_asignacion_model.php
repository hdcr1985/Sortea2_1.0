<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_asignacion_model
 * ─────────────────────────────────────────────────────────────
 * Modelo de asignaciones y consumo de cupos de planes de rifa (M02).
 *
 * - Tabla principal: rif_plan_asignaciones (asig_id).
 * - Planes: rif_planes (plan_id).
 * - Organizadores: rif_organizadores (org_id).
 * - Consumos: rif_plan_consumos (cons_id).
 * - Todas las consultas usan query builder o bindings.
 * - Las operaciones de cupo usan transacción + FOR UPDATE + UNIQUE.
 * - Incluye guardas table_exists para operar antes de aplicar migraciones.
 */
class Plan_asignacion_model extends CI_Model
{
    protected $table = 'rif_plan_asignaciones';

    protected $table_planes = 'rif_planes';

    protected $table_org = 'rif_organizadores';

    protected $table_consumos = 'rif_plan_consumos';

    protected $primary_key = 'asig_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     */
    protected $columnas_ordenables = [
        0 => 'o.org_nombre_publico',
        1 => 'p.plan_nombre',
        2 => 'a.asig_rifas_usadas',
        3 => 'a.asig_inicio', // columna "vigencia"
        4 => 'a.asig_estado', // columna "estado" en la vista (índice 4)
        5 => 'a.asig_id',     // columna "acciones": no ordenable
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

        $this->db->select(
            'a.*, o.org_nombre_publico, o.org_slug, o.org_status,'
            . ' p.plan_nombre, p.plan_tipo, p.plan_limite, p.plan_periodo,'
            . ' p.plan_precio, p.plan_moneda, p.plan_eliminado'
        );

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'a.asig_id';
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
        $this->db->from($this->table . ' a');
        $this->db->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner');
        $this->db->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner');

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('o.org_nombre_publico', $search);
            $this->db->or_like('o.org_slug', $search);
            $this->db->or_like('p.plan_nombre', $search);
            $this->db->or_like('a.asig_estado', $search);
            $this->db->or_like('a.asig_origen', $search);
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

        return $this->db
            ->select(
                'a.*, o.org_nombre_publico, o.org_slug, o.org_status,'
                . ' p.plan_nombre, p.plan_tipo, p.plan_limite, p.plan_periodo,'
                . ' p.plan_precio, p.plan_moneda, p.plan_eliminado'
            )
            ->from($this->table . ' a')
            ->join($this->table_org . ' o', 'o.org_id = a.asig_org_id', 'inner')
            ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner')
            ->where('a.asig_id', (int)$id)
            ->limit(1)
            ->get()
            ->row_array();
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

        $this->db
            ->where('asig_estado', 'activa')
            ->where('asig_fin IS NOT NULL', NULL, FALSE)
            ->where('asig_fin <', 'NOW()', FALSE);

        if ($org_id !== NULL && (int)$org_id > 0) {
            $this->db->where('asig_org_id', (int)$org_id);
        }

        $this->db->update($this->table, [
            'asig_estado'    => 'vencida',
            'asig_updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->db->affected_rows();
    }

    /**
     * Obtiene la asignación activa vigente de un organizador.
     *
     * @param  int $org_id
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

        return $this->db
            ->select(
                'a.*, p.plan_nombre, p.plan_tipo, p.plan_limite, p.plan_periodo,'
                . ' p.plan_precio, p.plan_moneda, p.plan_eliminado'
            )
            ->from($this->table . ' a')
            ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner')
            ->where('a.asig_org_id', (int)$org_id)
            ->where('a.asig_estado', 'activa')
            ->group_start()
                ->where('a.asig_fin IS NULL', NULL, FALSE)
                ->or_where('a.asig_fin >=', 'NOW()', FALSE)
            ->group_end()
            ->order_by('a.asig_id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Estado seguro de publicación de un organizador.
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
        ];

        if (!$this->tablas_disponibles()) {
            return $neutro;
        }

        // Lectura sin efectos secundarios: no normaliza vencidas en un GET.
        $asig = $this->get_activa_por_org((int)$org_id, FALSE);
        if (empty($asig)) {
            // Si la última asignación venció, mostrarla como "vencida" en
            // lugar de un estado neutral que oculte el motivo.
            $vencida = $this->db
                ->select(
                    'a.*, p.plan_nombre, p.plan_tipo, p.plan_limite, p.plan_periodo,'
                    . ' p.plan_precio, p.plan_moneda'
                )
                ->from($this->table . ' a')
                ->join($this->table_planes . ' p', 'p.plan_id = a.asig_plan_id', 'inner')
                ->where('a.asig_org_id', (int)$org_id)
                ->where_in('a.asig_estado', ['activa', 'vencida'])
                ->where('a.asig_fin IS NOT NULL', NULL, FALSE)
                ->where('a.asig_fin <', date('Y-m-d H:i:s'))
                ->order_by('a.asig_id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();

            if (!empty($vencida)) {
                $tipo      = (string)($vencida['plan_tipo'] ?? 'rifas');
                $ilimitado = ($tipo === 'ilimitado');
                $limite    = $ilimitado ? NULL : (int)($vencida['plan_limite'] ?? 0);
                $usadas    = (int)($vencida['asig_rifas_usadas'] ?? 0);

                return [
                    'tiene_plan'     => TRUE,
                    'asignacion'     => $vencida,
                    'plan'           => [
                        'plan_id'      => (int)($vencida['asig_plan_id'] ?? 0),
                        'plan_nombre'  => (string)($vencida['plan_nombre'] ?? ''),
                        'plan_tipo'    => $tipo,
                        'plan_limite'  => (int)($vencida['plan_limite'] ?? 0),
                        'plan_periodo' => (string)($vencida['plan_periodo'] ?? ''),
                        'plan_precio'  => $vencida['plan_precio'] ?? 0,
                        'plan_moneda'  => (string)($vencida['plan_moneda'] ?? ''),
                    ],
                    'usadas'         => $usadas,
                    'limite'         => $limite,
                    'restantes'      => 0,
                    'ilimitado'      => $ilimitado,
                    'puede_publicar' => FALSE,
                    'motivo'         => 'vencida',
                    'fin'            => $vencida['asig_fin'] ?? NULL,
                ];
            }

            return $neutro;
        }

        $tipo      = (string)($asig['plan_tipo'] ?? 'rifas');
        $ilimitado = ($tipo === 'ilimitado');
        $limite    = $ilimitado ? NULL : (int)($asig['plan_limite'] ?? 0);
        $usadas    = (int)($asig['asig_rifas_usadas'] ?? 0);
        $restantes = $ilimitado ? NULL : max(0, $limite - $usadas);

        $puede_publicar = $ilimitado
            ? TRUE
            : ($limite > 0 && $usadas < $limite);

        return [
            'tiene_plan'     => TRUE,
            'asignacion'     => $asig,
            'plan'           => [
                'plan_id'     => (int)($asig['asig_plan_id'] ?? 0),
                'plan_nombre' => (string)($asig['plan_nombre'] ?? ''),
                'plan_tipo'   => $tipo,
                'plan_limite' => (int)($asig['plan_limite'] ?? 0),
                'plan_periodo' => (string)($asig['plan_periodo'] ?? ''),
                'plan_precio' => $asig['plan_precio'] ?? 0,
                'plan_moneda' => (string)($asig['plan_moneda'] ?? ''),
            ],
            'usadas'         => $usadas,
            'limite'         => $limite,
            'restantes'      => $restantes,
            'ilimitado'      => $ilimitado,
            'puede_publicar' => $puede_publicar,
            'motivo'         => $puede_publicar ? '' : 'sin_cupo',
            'fin'            => $asig['asig_fin'] ?? NULL,
        ];
    }

    /**
     * Indica si el organizador puede publicar una nueva rifa.
     *
     * @param  int $org_id
     * @return array ['ok' => bool, 'motivo' => string, 'estado' => array]
     */
    public function puede_publicar($org_id)
    {
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
     * Asigna un plan a un organizador.
     *
     * @param  int    $org_id
     * @param  int    $plan_id
     * @param  string $origen
     * @param  string $notas
     * @param  bool   $reemplazar
     * @return array ['ok' => bool, 'asig_id' => int|null, 'motivo' => string]
     */
    public function asignar($org_id, $plan_id, $origen = 'manual', $notas = '', $reemplazar = FALSE)
    {
        if (!$this->tablas_disponibles()) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $origenes = ['manual', 'compra', 'cortesia', 'admin'];
        $origen   = in_array($origen, $origenes, TRUE) ? $origen : 'manual';
        $notas    = $this->_limitar_texto(trim((string)$notas), 255);

        $inicio = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        try {
            // Bloqueo por organizador: serializa asignaciones concurrentes y
            // evita dos asignaciones activas simultáneas aunque aún no exista
            // una fila previa que bloquear.
            $org_lock = $this->db->query(
                "SELECT org_id FROM {$this->table_org}"
                . " WHERE org_id = ? AND org_status = 1 AND org_eliminado = 0 FOR UPDATE",
                [(int)$org_id]
            )->row_array();

            if (empty($org_lock)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'org_invalido'];
            }

            // Leer y bloquear el plan dentro de la misma transacción para que
            // una desactivación/edición concurrente no pase inadvertida.
            $plan = $this->db->query(
                'SELECT plan_id, plan_periodo, plan_eliminado, plan_status'
                . ' FROM ' . $this->table_planes
                . ' WHERE plan_id = ? AND plan_eliminado = 0 AND plan_status = 1'
                . ' LIMIT 1 FOR UPDATE',
                [(int)$plan_id]
            )->row_array();

            if (empty($plan)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'plan_invalido'];
            }

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

            $this->normalizar_vencidas((int)$org_id);

            // Bloqueo pesimista de la asignación activa actual.
            $sql = "SELECT asig_id, asig_notas FROM {$this->table}"
                . " WHERE asig_org_id = ? AND asig_estado = 'activa'"
                . " AND (asig_fin IS NULL OR asig_fin >= NOW())"
                . " ORDER BY asig_id DESC LIMIT 1 FOR UPDATE";

            $activa = $this->db->query($sql, [(int)$org_id])->row_array();

            if (!empty($activa) && !$reemplazar) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'ya_tiene_plan'];
            }

            if (!empty($activa)) {
                $nota_previa = 'Reemplazada' . ($notas !== '' ? ': ' . $notas : '');
                $notas_previas = trim((string)($activa['asig_notas'] ?? ''));
                $notas_finales = trim($notas_previas !== '' ? $notas_previas . ' | ' . $nota_previa : $nota_previa);

                $this->db
                    ->where('asig_id', (int)$activa['asig_id'])
                    ->update($this->table, [
                        'asig_estado'     => 'reemplazada',
                        'asig_notas'      => $this->_limitar_texto($notas_finales, 255),
                        'asig_updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $insertado = $this->db->insert($this->table, [
                'asig_org_id'      => (int)$org_id,
                'asig_plan_id'     => (int)$plan_id,
                'asig_origen'      => $origen,
                'asig_inicio'      => $inicio,
                'asig_fin'         => $fin,
                'asig_rifas_usadas' => 0,
                'asig_estado'      => 'activa',
                'asig_notas'       => $notas !== '' ? $notas : NULL,
                'asig_created_at'  => $inicio,
                'asig_updated_at'  => $inicio,
            ]);

            if (!$insertado || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $asig_id = (int)$this->db->insert_id();
            $this->db->trans_commit();

            return ['ok' => TRUE, 'asig_id' => $asig_id, 'motivo' => ''];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::asignar no pudo completar la asignación.');
            return ['ok' => FALSE, 'motivo' => 'error'];
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

        $this->db->trans_begin();

        try {
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
        }
    }

    /**
     * Consume un cupo del plan activo de un organizador para una rifa.
     *
     * M03 DEBE verificar que `rifa_id` pertenece a `org_id` antes de llamar
     * a este método. El modelo solo controla el cupo y la idempotencia.
     *
     * @param  int    $org_id
     * @param  int    $rifa_id
     * @param  string $motivo
     * @return array ['ok' => bool, 'cons_id' => int, 'asig_id' => int, 'usadas' => int, 'motivo' => string]
     */
    public function consumir_para_rifa($org_id, $rifa_id, $motivo = '')
    {
        if (!$this->tablas_disponibles() || !$this->db->table_exists($this->table_consumos)) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $rifa_id = (int)$rifa_id;
        if ($rifa_id <= 0) {
            return ['ok' => FALSE, 'motivo' => 'rifa_invalida'];
        }

        $this->db->trans_begin();

        try {
            $this->normalizar_vencidas((int)$org_id);

            $sql = "SELECT a.asig_id, a.asig_org_id, a.asig_plan_id, a.asig_rifas_usadas,"
                . " p.plan_tipo, p.plan_limite, p.plan_periodo"
                . " FROM {$this->table} a"
                . " INNER JOIN {$this->table_planes} p ON p.plan_id = a.asig_plan_id"
                . " WHERE a.asig_org_id = ? AND a.asig_estado = 'activa'"
                . " AND (a.asig_fin IS NULL OR a.asig_fin >= NOW())"
                . " ORDER BY a.asig_id DESC LIMIT 1 FOR UPDATE";

            $asig = $this->db->query($sql, [(int)$org_id])->row_array();

            if (empty($asig)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'sin_plan'];
            }

            $tipo   = (string)$asig['plan_tipo'];
            $usadas = (int)$asig['asig_rifas_usadas'];

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

            if ($tipo === 'rifas') {
                $limite = (int)$asig['plan_limite'];
                if ($usadas >= $limite) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'sin_cupo'];
                }
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
                'ok'      => TRUE,
                'cons_id' => $cons_id,
                'asig_id' => (int)$asig['asig_id'],
                'usadas'  => $usadas + 1,
                'motivo'  => '',
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Plan_asignacion_model::consumir_para_rifa no pudo consumir el cupo.');
            return ['ok' => FALSE, 'motivo' => 'error'];
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

        $this->db->trans_begin();

        try {
            $sql = "SELECT cons_id, cons_asig_id FROM {$this->table_consumos}"
                . " WHERE cons_org_id = ? AND cons_rifa_id = ? AND cons_estado = 'consumido'"
                . " ORDER BY cons_id DESC LIMIT 1 FOR UPDATE";

            $cons = $this->db->query($sql, [(int)$org_id, (int)$rifa_id])->row_array();

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
        }
    }

    /**
     * Devuelve un consumo por su ID.
     *
     * @param  int    $cons_id
     * @param  string $motivo
     * @return array
     */
    public function devolver($cons_id, $motivo = '')
    {
        if (!$this->db->table_exists($this->table) || !$this->db->table_exists($this->table_consumos)) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $cons_id = (int)$cons_id;
        if ($cons_id <= 0) {
            return ['ok' => FALSE, 'motivo' => 'cons_invalido'];
        }

        $this->db->trans_begin();

        try {
            $sql = "SELECT cons_id, cons_asig_id FROM {$this->table_consumos}"
                . " WHERE cons_id = ? AND cons_estado = 'consumido'"
                . " LIMIT 1 FOR UPDATE";

            $cons = $this->db->query($sql, [(int)$cons_id])->row_array();

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

        return $this->db
            ->where('asig_id', (int)$asig_id)
            ->update($this->table, [
                'asig_rifas_usadas' => $usadas,
                'asig_updated_at'   => date('Y-m-d H:i:s'),
            ]);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

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
