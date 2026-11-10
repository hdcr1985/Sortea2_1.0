<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rifa_model
 * ─────────────────────────────────────────────────────────────
 * Modelo del módulo M03: rifas, numeración y estados.
 *
 * - Tabla principal: rif_rifas (rifa_id).
 * - Organizadores: rif_organizadores (org_id).
 * - Contrato DataTables server-side canónico.
 * - Borrado lógico mediante rifa_eliminado.
 * - Las transiciones de estado se ejecutan en transacción con FOR UPDATE.
 * - El consumo/devolución de cupo se delega en Plan_asignacion_model (M02).
 * - Todas las consultas usan query builder o bindings.
 */
class Rifa_model extends CI_Model
{
    protected $table       = 'rif_rifas';
    protected $table_org   = 'rif_organizadores';
    protected $table_asig  = 'rif_plan_asignaciones';
    protected $table_plan  = 'rif_planes';
    protected $table_num   = 'rif_numeraciones';
    protected $primary_key = 'rifa_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     */
    protected $columnas_ordenables = [
        0 => 'o.org_nombre_publico',
        1 => 'r.rifa_titulo',
        2 => 'r.rifa_tipo',
        3 => 'r.rifa_total_numeros',
        4 => 'r.rifa_estado',
        5 => 'r.rifa_fecha_sorteo',
        6 => 'r.rifa_publicada_at',
        7 => 'r.rifa_created_at',
    ];

    /**
     * Columnas editables por mass assignment controlado.
     */
    protected $columnas_guardado = [
        'rifa_asig_id',
        'rifa_num_id',
        'rifa_titulo',
        'rifa_slug',
        'rifa_descripcion',
        'rifa_condiciones',
        'rifa_tipo',
        'rifa_precio',
        'rifa_moneda',
        'rifa_numeracion_tipo',
        'rifa_numero_desde',
        'rifa_numero_hasta',
        'rifa_padding',
        'rifa_total_numeros',
        'rifa_max_por_compra',
        'rifa_reserva_ttl_min',
        'rifa_reserva_auto',
        'rifa_modo_asignacion',
        'rifa_mostrar_reservados',
        'rifa_fecha_sorteo',
        'rifa_imagen',
    ];

    /**
     * Estados que permiten borrado lógico.
     */
    protected $estados_eliminables = ['borrador', 'rechazada', 'cancelada', 'finalizada'];

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
     * Total de rifas no eliminadas (sin filtro).
     *
     * @param  int|null $org_id
     * @return int
     */
    public function count_todos($org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_datatable_query('', $org_id);

        return (int)$this->db->count_all_results();
    }

    /**
     * Total de rifas no eliminadas tras aplicar la búsqueda.
     *
     * @param  string   $search
     * @param  int|null $org_id
     * @return int
     */
    public function count_filtrados($search = '', $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_datatable_query($search, $org_id);

        return (int)$this->db->count_all_results();
    }

    /**
     * Registros paginados para DataTables server-side.
     *
     * @param  int      $start
     * @param  int      $length
     * @param  string   $search
     * @param  int      $order_col
     * @param  string   $order_dir
     * @param  int|null $org_id
     * @return array
     */
    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc', $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $this->_datatable_query($search, $org_id);

        $this->db->select('r.*, o.org_nombre_publico, o.org_slug');
        $this->_select_lote_datatable();

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'r.rifa_id';
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
     * @param  string   $search
     * @param  int|null $org_id
     * @return void
     */
    private function _datatable_query($search = '', $org_id = NULL)
    {
        $this->db->from($this->table . ' r');
        $this->db->join($this->table_org . ' o', 'o.org_id = r.rifa_org_id', 'inner');
        $this->db->where('r.rifa_eliminado', 0);

        // Datos del lote y la numeración usados por la rifa. Son LEFT JOIN
        // para no ocultar rifas gratuitas o históricas sin lote.
        $this->_join_lote();

        if ($org_id !== NULL && (int)$org_id > 0) {
            $this->db->where('r.rifa_org_id', (int)$org_id);
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('r.rifa_titulo', $search);
            $this->db->or_like('r.rifa_slug', $search);
            $this->db->or_like('r.rifa_descripcion', $search);
            $this->db->or_like('r.rifa_tipo', $search);
            $this->db->or_like('r.rifa_estado', $search);
            $this->db->or_like('o.org_nombre_publico', $search);
            $this->db->or_like('o.org_slug', $search);
            if ($this->db->table_exists($this->table_asig) && $this->db->table_exists($this->table_num)) {
                $this->db->or_like('n.num_label', $search);
            }
            $this->db->group_end();
        }
    }

    /**
     * Agrega los JOIN del lote/numeración sobre el alias r.
     *
     * @return void
     */
    private function _join_lote()
    {
        if (!$this->db->table_exists($this->table_asig)) {
            return;
        }

        $this->db->join($this->table_asig . ' a', 'a.asig_id = r.rifa_asig_id', 'left');

        if ($this->db->table_exists($this->table_plan)) {
            $this->db->join($this->table_plan . ' p', 'p.plan_id = a.asig_plan_id', 'left');
        }

        if ($this->db->table_exists($this->table_num)) {
            $this->db->join($this->table_num . ' n', 'n.num_id = r.rifa_num_id', 'left');
        }
    }

    /**
     * Agrega los campos del lote/numeración al SELECT de DataTables.
     *
     * @return void
     */
    private function _select_lote_datatable()
    {
        if (!$this->db->table_exists($this->table_asig)) {
            return;
        }

        $this->db->select(
            'a.asig_id AS asig_id, a.asig_num_id AS asig_num_id,'
            . ' a.asig_cantidad AS asig_cantidad, a.asig_rifas_usadas AS asig_rifas_usadas,'
            . ' a.asig_plan_id AS asig_plan_id',
            FALSE
        );

        if ($this->db->table_exists($this->table_plan)) {
            $this->db->select('p.plan_nombre AS asig_plan_nombre', FALSE);
        }

        if ($this->db->table_exists($this->table_num)) {
            $this->db->select('n.num_label AS num_label', FALSE);
        }
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene una rifa no eliminada por ID, opcionalmente filtrada por org.
     *
     * @param  int      $id
     * @param  int|null $org_id
     * @return array|null
     */
    public function get_por_id($id, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $this->db
            ->select('r.*, o.org_nombre_publico, o.org_slug')
            ->from($this->table . ' r')
            ->join($this->table_org . ' o', 'o.org_id = r.rifa_org_id', 'inner');

        $this->_select_lote_datatable();
        $this->_join_lote();

        $this->db
            ->where('r.' . $this->primary_key, (int)$id)
            ->where('r.rifa_eliminado', 0);

        if ($org_id !== NULL && (int)$org_id > 0) {
            $this->db->where('r.rifa_org_id', (int)$org_id);
        }

        return $this->db->limit(1)->get()->row_array();
    }

    /**
     * Obtiene una rifa no eliminada por slug dentro de un organizador.
     *
     * @param  string $slug
     * @param  int    $org_id
     * @return array|null
     */
    public function get_por_slug($slug, $org_id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $slug = strtolower(trim((string)$slug));
        if ($slug === '' || (int)$org_id <= 0) {
            return NULL;
        }

        return $this->db
            ->select('r.*, o.org_nombre_publico, o.org_slug')
            ->from($this->table . ' r')
            ->join($this->table_org . ' o', 'o.org_id = r.rifa_org_id', 'inner')
            ->where('LOWER(r.rifa_slug)', $slug)
            ->where('r.rifa_org_id', (int)$org_id)
            ->where('r.rifa_eliminado', 0)
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Verifica si un slug existe dentro de un organizador.
     * Incluye filas con borrado lógico porque la UNIQUE sigue vigente.
     *
     * @param  string $slug
     * @param  int    $org_id
     * @param  int    $ignorar_id
     * @return bool
     */
    public function existe_slug($slug, $org_id, $ignorar_id = 0)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $slug = strtolower(trim((string)$slug));
        if ($slug === '' || (int)$org_id <= 0) {
            return FALSE;
        }

        $this->db
            ->where('rifa_org_id', (int)$org_id)
            ->where('LOWER(rifa_slug)', $slug);

        if ((int)$ignorar_id > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$ignorar_id);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES CRUD
    // ─────────────────────────────────────────────────────────

    /**
     * Inserta una rifa en estado borrador.
     *
     * @param  array $data
     * @return int|false
     */
    public function crear(array $data)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $org_id = (int)($data['rifa_org_id'] ?? 0);
        if ($org_id <= 0) {
            return FALSE;
        }

        $guardado = $this->_filtrar_guardado($data);
        if (empty($guardado)) {
            return FALSE;
        }

        $ahora = date('Y-m-d H:i:s');

        $guardado['rifa_org_id']   = $org_id;
        $guardado['rifa_estado']   = 'borrador';
        $guardado['rifa_created_at'] = $ahora;
        $guardado['rifa_updated_at'] = $ahora;
        $guardado['rifa_eliminado']  = 0;

        // CI3 llama a display_error() y termina el proceso con exit(8) cuando
        // db_debug está activo. Se desactiva solo durante la escritura para
        // poder devolver un 422 controlado ante una colisión de slug.
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

        if ($ok && empty($error['code'])) {
            return (int)$this->db->insert_id();
        }

        if (!empty($error['code'])) {
            log_message('error', 'Rifa_model::crear no pudo insertar la rifa (DB code ' . (int)$error['code'] . ').');
        }

        return FALSE;
    }

    /**
     * Actualiza una rifa no eliminada, opcionalmente filtrada por org.
     *
     * @param  int      $id
     * @param  array    $data
     * @param  int|null $org_id
     * @return bool
     */
    public function actualizar($id, array $data, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $guardado = $this->_filtrar_guardado($data);
        if (empty($guardado)) {
            return FALSE;
        }

        $guardado['rifa_updated_at'] = date('Y-m-d H:i:s');

        // Verificar existencia con el mismo filtro de organización antes del
        // UPDATE: db->update() devuelve TRUE aunque no afecte filas, lo que
        // ocultaría un intento de acceso cruzado (IDOR).
        $this->db
            ->where($this->primary_key, (int)$id)
            ->where('rifa_eliminado', 0);

        if ($org_id !== NULL && (int)$org_id > 0) {
            $this->db->where('rifa_org_id', (int)$org_id);
        }

        if ((int)$this->db->count_all_results($this->table) <= 0) {
            return FALSE;
        }

        $this->db
            ->where($this->primary_key, (int)$id)
            ->where('rifa_eliminado', 0);

        if ($org_id !== NULL && (int)$org_id > 0) {
            $this->db->where('rifa_org_id', (int)$org_id);
        }

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $ok    = $this->db->update($this->table, $guardado);
            $error = $this->db->error();
        } catch (Throwable $e) {
            $ok    = FALSE;
            $error = ['code' => -1, 'message' => ''];
        } finally {
            $this->db->db_debug = $debug_previo;
        }

        if (!empty($error['code'])) {
            log_message('error', 'Rifa_model::actualizar no pudo actualizar la rifa (DB code ' . (int)$error['code'] . ').');
            return FALSE;
        }

        return (bool)$ok;
    }

    /**
     * Borrado lógico de una rifa en un estado permitido.
     *
     * @param  int      $id
     * @param  int|null $org_id
     * @return bool
     */
    public function eliminar($id, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, $org_id);

            if (empty($rifa) || !in_array((string)$rifa['rifa_estado'], $this->estados_eliminables, TRUE)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$id)
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_eliminado'  => 1,
                    'rifa_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::eliminar no pudo completar el borrado lógico.');
            return FALSE;
        }
    }

    // ─────────────────────────────────────────────────────────
    // TRANSICIONES DE ESTADO
    // ─────────────────────────────────────────────────────────

    /**
     * Publica una rifa.
     *
     * - Gratuita: pasa a pendiente_aprobacion sin consumir cupo.
     * - Paga: consume un cupo del plan activo y pasa a activa.
     *
     * @param  int    $id
     * @param  int    $org_id
     * @param  string $motivo
     * @return array ['ok' => bool, 'motivo' => string, 'estado' => string]
     */
    public function publicar($id, $org_id, $motivo = '')
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $motivo = $this->_limitar_texto(trim((string)$motivo), 255);

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, $org_id);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];

            if (!in_array($estado_actual, ['borrador', 'rechazada'], TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $validacion = $this->_validar_publicacion($rifa);
            if (!$validacion['ok']) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => $validacion['motivo'], 'estado' => $estado_actual];
            }

            $ahora = date('Y-m-d H:i:s');

            if ((string)$rifa['rifa_tipo'] === 'gratuita') {
                $datos = [
                    'rifa_estado'            => 'pendiente_aprobacion',
                    'rifa_aprobacion_motivo' => $motivo !== '' ? $motivo : NULL,
                    'rifa_updated_at'        => $ahora,
                ];

                $ok = $this->db
                    ->where($this->primary_key, (int)$rifa['rifa_id'])
                    ->where('rifa_eliminado', 0)
                    ->update($this->table, $datos);

                if (!$ok || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
                }

                $this->db->trans_commit();
                return [
                    'ok'      => TRUE,
                    'motivo'  => '',
                    'estado'  => 'pendiente_aprobacion',
                    'asig_id' => NULL,
                    'cons_id' => NULL,
                    'num_id'  => NULL,
                ];
            }

            $this->load->model('Plan_asignacion_model');

            $asig_id = (int)($rifa['rifa_asig_id'] ?? 0);
            $num_id  = (int)($rifa['rifa_num_id'] ?? 0);

            if ($asig_id <= 0 || $num_id <= 0) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'sin_lote', 'estado' => $estado_actual];
            }

            $lote = $this->Plan_asignacion_model->get_lote_por_org((int)$org_id, $asig_id);
            if (empty($lote)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'asignacion_invalida', 'estado' => $estado_actual];
            }

            $puede = $this->Plan_asignacion_model->puede_publicar((int)$org_id, $asig_id);
            if (!$puede['ok']) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => (string)$puede['motivo'], 'estado' => $estado_actual];
            }

            // El lote y la numeración deben coincidir: en lotes limitados la
            // numeración la fija la compra; en ilimitados debe ser del catálogo.
            $ilimitado   = !empty($lote['ilimitado']);
            $asig_num_id = isset($lote['asig_num_id']) && $lote['asig_num_id'] !== NULL
                ? (int)$lote['asig_num_id']
                : NULL;

            if ($ilimitado) {
                if ($asig_num_id !== NULL) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'numeracion_invalida', 'estado' => $estado_actual];
                }

                $this->load->model('Numeracion_model');
                $numeracion = $this->Numeracion_model->get_por_id($num_id);
                if (empty($numeracion) || (int)$numeracion['num_status'] !== 1) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'numeracion_invalida', 'estado' => $estado_actual];
                }
            } elseif ($asig_num_id !== $num_id) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'numeracion_invalida', 'estado' => $estado_actual];
            }

            $consumo = $this->Plan_asignacion_model->consumir_para_rifa(
                (int)$org_id,
                (int)$rifa['rifa_id'],
                'Publicación de rifa',
                $asig_id,
                $num_id
            );

            if (!$consumo['ok']) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => (string)$consumo['motivo'], 'estado' => $estado_actual];
            }

            // No se sobrescriben rifa_asig_id/rifa_num_id: se fijaron al
            // crear/editar la rifa y son la fuente de verdad del lote.
            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'            => 'activa',
                    'rifa_publicada_at'      => $ahora,
                    'rifa_aprobacion_motivo' => NULL,
                    'rifa_updated_at'        => $ahora,
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return [
                'ok'      => TRUE,
                'motivo'  => '',
                'estado'  => 'activa',
                'asig_id' => (int)$consumo['asig_id'],
                'cons_id' => (int)$consumo['cons_id'],
                'num_id'  => $consumo['num_id'] !== NULL ? (int)$consumo['num_id'] : NULL,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::publicar no pudo completar la publicación.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Pausa una rifa activa.
     *
     * @param  int $id
     * @param  int $org_id
     * @return array
     */
    public function pausar($id, $org_id)
    {
        return $this->_cambiar_estado_simple($id, $org_id, ['activa'], 'pausada');
    }

    /**
     * Reactiva una rifa pausada si el organizador conserva un plan.
     *
     * @param  int $id
     * @param  int $org_id
     * @return array
     */
    public function reactivar($id, $org_id)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, $org_id);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];
            if ($estado_actual !== 'pausada') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $this->load->model('Plan_asignacion_model');

            // Una rifa paga conserva el lote consumido al publicarse. Para
            // reactivar solo se exige que ese lote siga vigente; no se exige
            // cupo libre porque el cupo de esta rifa ya fue consumido.
            if ((string)$rifa['rifa_tipo'] === 'paga') {
                $asig_id = (int)($rifa['rifa_asig_id'] ?? 0);

                if ($asig_id <= 0) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'sin_plan', 'estado' => $estado_actual];
                }

                if (!$this->Plan_asignacion_model->lote_vigente((int)$org_id, $asig_id)) {
                    $estado_plan = $this->Plan_asignacion_model->get_estado_por_org((int)$org_id);
                    $this->db->trans_rollback();
                    return [
                        'ok'     => FALSE,
                        'motivo' => !empty($estado_plan['tiene_plan']) ? 'vencida' : 'sin_plan',
                        'estado' => $estado_actual,
                    ];
                }

                if ((int)($rifa['rifa_num_id'] ?? 0) <= 0) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'numeracion_invalida', 'estado' => $estado_actual];
                }
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'     => 'activa',
                    'rifa_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'motivo' => '', 'estado' => 'activa'];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::reactivar no pudo reactivar la rifa.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Solicita la cancelación de una rifa activa o pausada.
     * No devuelve cupo hasta la aprobación (M09).
     *
     * @param  int    $id
     * @param  int    $org_id
     * @param  string $motivo
     * @return array
     */
    public function solicitar_cancelacion($id, $org_id, $motivo)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $motivo = $this->_limitar_texto(trim((string)$motivo), 255);

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, $org_id);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];
            if (!in_array($estado_actual, ['activa', 'pausada'], TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $ahora = date('Y-m-d H:i:s');

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'                    => 'cancelacion_solicitada',
                    'rifa_estado_previo'             => $estado_actual,
                    'rifa_cancelacion_motivo'        => $motivo,
                    'rifa_cancelacion_solicitada_at' => $ahora,
                    'rifa_updated_at'                => $ahora,
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'motivo' => '', 'estado' => 'cancelacion_solicitada'];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::solicitar_cancelacion no pudo registrar la solicitud.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Finaliza una rifa activa o pausada y devuelve el cupo consumido.
     *
     * @param  int $id
     * @param  int $org_id
     * @return array
     */
    public function finalizar($id, $org_id)
    {
        return $this->_cerrar_con_devolucion($id, $org_id, 'finalizada');
    }

    /**
     * Cancela definitivamente una solicitud (M09) y devuelve el cupo.
     *
     * @param  int    $id
     * @param  string $motivo
     * @return array
     */
    public function cancelar_aprobada($id, $motivo = '')
    {
        return $this->_cerrar_con_devolucion($id, NULL, 'cancelada', $motivo, TRUE);
    }

    /**
     * Rechaza una solicitud de cancelación (M09) y restaura el estado previo.
     *
     * @param  int    $id
     * @param  string $motivo
     * @return array
     */
    public function rechazar_cancelacion($id, $motivo = '')
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, NULL);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];
            if ($estado_actual !== 'cancelacion_solicitada') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $previo = (string)($rifa['rifa_estado_previo'] ?? '');
            if (!in_array($previo, ['activa', 'pausada'], TRUE)) {
                $previo = 'activa';
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'                    => $previo,
                    'rifa_estado_previo'             => NULL,
                    'rifa_cancelacion_motivo'        => NULL,
                    'rifa_cancelacion_solicitada_at' => NULL,
                    'rifa_updated_at'                => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'motivo' => '', 'estado' => $previo];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::rechazar_cancelacion no pudo restaurar la rifa.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Aprueba una rifa gratuita pendiente de aprobación (M09).
     *
     * @param  int $id
     * @return array
     */
    public function aprobar_gratuita($id)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, NULL);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];
            if ($estado_actual !== 'pendiente_aprobacion'
                || (string)$rifa['rifa_tipo'] !== 'gratuita') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $ahora = date('Y-m-d H:i:s');

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'            => 'activa',
                    'rifa_publicada_at'      => $ahora,
                    'rifa_aprobacion_motivo' => NULL,
                    'rifa_updated_at'        => $ahora,
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'motivo' => '', 'estado' => 'activa'];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::aprobar_gratuita no pudo aprobar la rifa.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Rechaza una rifa gratuita pendiente de aprobación (M09).
     *
     * @param  int    $id
     * @param  string $motivo
     * @return array
     */
    public function rechazar_gratuita($id, $motivo = '')
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $motivo = $this->_limitar_texto(trim((string)$motivo), 255);

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, NULL);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];
            if ($estado_actual !== 'pendiente_aprobacion'
                || (string)$rifa['rifa_tipo'] !== 'gratuita') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'            => 'rechazada',
                    'rifa_aprobacion_motivo' => $motivo !== '' ? $motivo : NULL,
                    'rifa_updated_at'        => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'motivo' => '', 'estado' => 'rechazada'];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model::rechazar_gratuita no pudo rechazar la rifa.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Cambio de estado directo entre un conjunto de orígenes y un destino.
     *
     * @param  int    $id
     * @param  int    $org_id
     * @param  array  $desde
     * @param  string $hacia
     * @return array
     */
    private function _cambiar_estado_simple($id, $org_id, array $desde, $hacia)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, $org_id);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];
            if (!in_array($estado_actual, $desde, TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, [
                    'rifa_estado'     => $hacia,
                    'rifa_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'motivo' => '', 'estado' => $hacia];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model no pudo cambiar el estado de la rifa.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Finaliza/cancela una rifa y devuelve el cupo consumido.
     *
     * @param  int         $id
     * @param  int|null    $org_id
     * @param  string      $hacia
     * @param  string      $motivo
     * @param  bool        $solo_cancelacion
     * @return array
     */
    private function _cerrar_con_devolucion($id, $org_id, $hacia, $motivo = '', $solo_cancelacion = FALSE)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible', 'estado' => ''];
        }

        $motivo = $this->_limitar_texto(trim((string)$motivo), 255);

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($id, $org_id);

            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada', 'estado' => ''];
            }

            $estado_actual = (string)$rifa['rifa_estado'];

            if ($solo_cancelacion) {
                $permitidos = ['cancelacion_solicitada'];
            } else {
                $permitidos = ['activa', 'pausada'];
            }

            if (!in_array($estado_actual, $permitidos, TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => $estado_actual];
            }

            $this->load->model('Plan_asignacion_model');

            $devolucion = $this->Plan_asignacion_model->devolver_por_rifa(
                (int)$rifa['rifa_org_id'],
                (int)$rifa['rifa_id'],
                $hacia === 'cancelada' ? 'Rifa cancelada' : 'Rifa finalizada'
            );

            if (!$devolucion['ok'] && (string)$devolucion['motivo'] !== 'no_consumo') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => (string)$devolucion['motivo'], 'estado' => $estado_actual];
            }

            $ahora = date('Y-m-d H:i:s');

            $datos = [
                'rifa_estado'     => $hacia,
                'rifa_updated_at' => $ahora,
            ];

            if ($hacia === 'finalizada') {
                $datos['rifa_finalizada_at'] = $ahora;
            } else {
                $datos['rifa_cancelada_at']       = $ahora;
                $datos['rifa_estado_previo']      = NULL;
                $datos['rifa_cancelacion_motivo'] = $motivo !== '' ? $motivo : (string)($rifa['rifa_cancelacion_motivo'] ?? '');
                if ($datos['rifa_cancelacion_motivo'] === '') {
                    $datos['rifa_cancelacion_motivo'] = NULL;
                }
                $datos['rifa_cancelacion_solicitada_at'] = NULL;
            }

            $ok = $this->db
                ->where($this->primary_key, (int)$rifa['rifa_id'])
                ->where('rifa_eliminado', 0)
                ->update($this->table, $datos);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error', 'estado' => $estado_actual];
            }

            $this->db->trans_commit();

            $resultado = ['ok' => TRUE, 'motivo' => '', 'estado' => $hacia];

            if (!empty($devolucion['asig_id'])) {
                $resultado['asig_id'] = (int)$devolucion['asig_id'];
                $resultado['cons_id'] = (int)($devolucion['cons_id'] ?? 0);
            }

            return $resultado;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_model no pudo cerrar la rifa con devolución de cupo.');
            return ['ok' => FALSE, 'motivo' => 'error', 'estado' => ''];
        }
    }

    /**
     * Bloquea una rifa no eliminada dentro de la transacción actual.
     *
     * @param  int      $id
     * @param  int|null $org_id
     * @return array|null
     */
    private function _lock_rifa($id, $org_id = NULL)
    {
        $sql = "SELECT * FROM {$this->table}"
            . " WHERE {$this->primary_key} = ? AND rifa_eliminado = 0";

        $binds = [(int)$id];

        if ($org_id !== NULL && (int)$org_id > 0) {
            $sql .= ' AND rifa_org_id = ?';
            $binds[] = (int)$org_id;
        }

        $sql .= ' LIMIT 1 FOR UPDATE';

        return $this->db->query($sql, $binds)->row_array();
    }

    /**
     * Valida los datos mínimos para publicar una rifa bloqueada.
     *
     * @param  array $rifa
     * @return array ['ok' => bool, 'motivo' => string]
     */
    private function _validar_publicacion(array $rifa)
    {
        $titulo = trim((string)($rifa['rifa_titulo'] ?? ''));
        if ($this->_longitud($titulo) < 3) {
            return ['ok' => FALSE, 'motivo' => 'titulo_invalido'];
        }

        // La portada es obligatoria para publicar. Se usa la columna legacy
        // rifa_imagen, que la galería mantiene sincronizada con la principal.
        if (trim((string)($rifa['rifa_imagen'] ?? '')) === '') {
            return ['ok' => FALSE, 'motivo' => 'sin_portada'];
        }

        $fecha = trim((string)($rifa['rifa_fecha_sorteo'] ?? ''));
        $timestamp = $fecha !== '' ? strtotime($fecha) : FALSE;
        if ($timestamp === FALSE || $timestamp <= time()) {
            return ['ok' => FALSE, 'motivo' => 'fecha_sorteo'];
        }

        $tipo = (string)($rifa['rifa_tipo'] ?? 'paga');

        if ($tipo === 'paga') {
            if ((int)($rifa['rifa_asig_id'] ?? 0) <= 0 || (int)($rifa['rifa_num_id'] ?? 0) <= 0) {
                return ['ok' => FALSE, 'motivo' => 'sin_lote'];
            }

            $desde    = (int)($rifa['rifa_numero_desde'] ?? -1);
            $hasta    = (int)($rifa['rifa_numero_hasta'] ?? -1);
            $padding  = (int)($rifa['rifa_padding'] ?? 0);
            $total    = (int)($rifa['rifa_total_numeros'] ?? 0);

            if ($desde < 0
                || $hasta <= $desde
                || $hasta > 999999
                || $padding < 1
                || $padding > 6
                || $total <= 0
                || $total > 1000000
                || $total !== ($hasta - $desde + 1)) {
                return ['ok' => FALSE, 'motivo' => 'numeracion_invalida'];
            }

            if ((float)($rifa['rifa_precio'] ?? 0) <= 0) {
                return ['ok' => FALSE, 'motivo' => 'precio_invalido'];
            }
        }

        // Las rifas gratuitas no consumen plan y su numeración se define al
        // aprobarse (M09); no se exige numeración en este punto.

        return ['ok' => TRUE, 'motivo' => ''];
    }

    /**
     * Filtra los datos de entrada según la whitelist de guardado.
     *
     * @param  array $data
     * @return array
     */
    private function _filtrar_guardado(array $data)
    {
        return array_intersect_key($data, array_flip($this->columnas_guardado));
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

    /**
     * Longitud segura en caracteres.
     *
     * @param  string $valor
     * @return int
     */
    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
