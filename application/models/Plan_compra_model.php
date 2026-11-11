<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_compra_model
 * ─────────────────────────────────────────────────────────────
 * M08: ofertas de planes de rifa para el checkout de Comprar Planes.
 *
 * - Sincroniza cab_planes (ofertas) desde rif_planes/rif_plan_numeraciones.
 * - Crea el lote en rif_plan_asignaciones cuando el pago es confirmado.
 * - Es idempotente por compra_mov_id y compra_asig_id.
 */
class Plan_compra_model extends CI_Model
{
    protected $table_planes   = 'rif_planes';
    protected $table_opciones = 'rif_plan_numeraciones';
    protected $table_num      = 'rif_numeraciones';
    protected $table_ofertas  = 'cab_planes';
    protected $table_compras  = 'rif_plan_compras';
    protected $table_lotes    = 'rif_plan_asignaciones';

    public function __construct()
    {
        parent::__construct();
    }

    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table_planes)
            && $this->db->table_exists($this->table_opciones)
            && $this->db->table_exists($this->table_num)
            && $this->db->table_exists($this->table_ofertas)
            && $this->db->table_exists($this->table_compras)
            && $this->db->table_exists($this->table_lotes);
    }

    /**
     * Sincroniza las ofertas vendibles de cab_planes con los planes activos.
     *
     * @return bool
     */
    public function sincronizar_todas()
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $planes = $this->db
            ->where('plan_eliminado', 0)
            ->where('plan_status', 1)
            ->order_by('plan_id', 'ASC')
            ->get($this->table_planes)
            ->result_array();

        $ids = [];
        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            foreach ($planes as $plan) {
                if ((string)$plan['plan_tipo'] === 'ilimitado') {
                    $id = $this->_upsert_ilimitado($plan);
                    if ($id) {
                        $ids[] = $id;
                    }
                    continue;
                }

                foreach ($this->_opciones_activas((int)$plan['plan_id']) as $opcion) {
                    $id = $this->_upsert_finito($plan, $opcion);
                    if ($id) {
                        $ids[] = $id;
                    }
                }
            }

            $this->db->where('pla_tipo', 'plan_rifa');
            if (!empty($ids)) {
                $this->db->where_not_in('pla_id', $ids);
            }
            $this->db->update($this->table_ofertas, ['pla_status' => 0]);

            return TRUE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * @return array
     */
    public function get_ofertas_activas()
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        return $this->db
            ->select(
                'p.pla_id, p.pla_nombre, p.pla_descripcion, p.pla_numero, p.pla_tipo,'
                . ' p.pla_rif_plan_id, p.pla_rif_num_id, p.pla_paypal, p.pla_crypto,'
                . ' r.plan_nombre, r.plan_tipo, r.plan_periodo,'
                . ' r.plan_descripcion AS plan_descripcion, n.num_label,'
                . " CASE WHEN p.pla_rif_num_id > 0 THEN r.plan_precio ELSE NULL END AS pla_precio_regular,"
                . " CASE WHEN p.pla_rif_num_id > 0 THEN pn.pnum_precio ELSE r.plan_precio END AS pla_precio,"
                . " r.plan_moneda AS pla_moneda,"
                . " CASE WHEN p.pla_rif_num_id > 0 THEN 'sin_limite' ELSE r.plan_periodo END AS pla_rif_periodo",
                FALSE
            )
            ->from($this->table_ofertas . ' p')
            ->join($this->table_planes . ' r', 'r.plan_id = p.pla_rif_plan_id', 'inner')
            ->join($this->table_num . ' n', 'n.num_id = p.pla_rif_num_id', 'left')
            ->join(
                $this->table_opciones . ' pn',
                'pn.pnum_plan_id = p.pla_rif_plan_id AND pn.pnum_num_id = p.pla_rif_num_id',
                'left'
            )
            ->where('p.pla_tipo', 'plan_rifa')
            ->where('p.pla_status', 1)
            ->where('p.pla_eliminado', 0)
            ->where('r.plan_eliminado', 0)
            ->where('r.plan_status', 1)
            ->group_start()
                ->group_start()
                    ->where('p.pla_rif_num_id', 0)
                    ->where('r.plan_tipo', 'ilimitado')
                ->group_end()
                ->or_group_start()
                    ->where('p.pla_rif_num_id >', 0)
                    ->where('r.plan_tipo', 'rifas')
                    ->where('pn.pnum_status', 1)
                    ->where('n.num_status', 1)
                ->group_end()
            ->group_end()
            ->order_by("CASE WHEN r.plan_tipo = 'ilimitado' THEN 0 ELSE 1 END", 'ASC', FALSE)
            ->order_by("CASE r.plan_periodo WHEN 'mensual' THEN 1 WHEN 'semestral' THEN 2 WHEN 'anual' THEN 3 ELSE 4 END", 'ASC', FALSE)
            ->order_by('r.plan_nombre', 'ASC')
            ->order_by('p.pla_rif_num_id', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * @param  int $pla_id
     * @return array|null
     */
    public function get_oferta($pla_id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $row = $this->db
            ->select(
                'p.pla_id, p.pla_nombre, p.pla_descripcion, p.pla_tipo,'
                . ' p.pla_rif_plan_id, p.pla_rif_num_id, p.pla_rif_periodo,'
                . ' p.pla_numero, p.pla_paypal, p.pla_crypto, p.pla_status, p.pla_eliminado,'
                . ' r.plan_nombre, r.plan_tipo, r.plan_periodo, r.plan_precio, r.plan_moneda,'
                . ' r.plan_descripcion AS plan_descripcion, r.plan_status, r.plan_eliminado,'
                . ' n.num_label, n.num_status, pn.pnum_precio, pn.pnum_status',
                FALSE
            )
            ->from($this->table_ofertas . ' p')
            ->join($this->table_planes . ' r', 'r.plan_id = p.pla_rif_plan_id', 'inner')
            ->join($this->table_num . ' n', 'n.num_id = p.pla_rif_num_id', 'left')
            ->join(
                $this->table_opciones . ' pn',
                'pn.pnum_plan_id = p.pla_rif_plan_id AND pn.pnum_num_id = p.pla_rif_num_id',
                'left'
            )
            ->where('p.pla_id', (int)$pla_id)
            ->where('p.pla_tipo', 'plan_rifa')
            ->where('p.pla_status', 1)
            ->where('p.pla_eliminado', 0)
            ->where('r.plan_eliminado', 0)
            ->where('r.plan_status', 1)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($row)) {
            return NULL;
        }

        $rif_num_id = (int)$row['pla_rif_num_id'];

        if ($rif_num_id > 0) {
            if ((string)$row['plan_tipo'] !== 'rifas'
                || (int)($row['pnum_status'] ?? 0) !== 1
                || (int)($row['num_status'] ?? 0) !== 1) {
                return NULL;
            }

            $row['pla_precio']          = $row['pnum_precio'];
            $row['pla_precio_regular']  = $row['plan_precio'];
            $row['pla_moneda']          = $row['plan_moneda'];
            $row['pla_rif_periodo']     = 'sin_limite';
        } else {
            if ((string)$row['plan_tipo'] !== 'ilimitado') {
                return NULL;
            }

            $row['pla_precio']          = $row['plan_precio'];
            $row['pla_precio_regular']  = NULL;
            $row['pla_moneda']          = $row['plan_moneda'];
            $row['pla_rif_periodo']     = $row['plan_periodo'];
        }

        return $row;
    }

    /**
     * Crea el lote de una compra pagada. Se llama DENTRO de la transacción
     * del settlement; no inicia ni confirma su propia transacción.
     *
     * @param  string $mov_id
     * @return array ['ok'=>bool,'asig_id'=>int|null,'no_plan'=>bool]
     */
    public function crear_lote_para_orden($mov_id)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'asig_id' => NULL, 'no_plan' => FALSE];
        }

        $compra = $this->db->query(
            "SELECT * FROM {$this->table_compras} WHERE compra_mov_id = ? LIMIT 1 FOR UPDATE",
            [(string)$mov_id]
        )->row_array();

        if (empty($compra)) {
            return ['ok' => TRUE, 'asig_id' => NULL, 'no_plan' => TRUE];
        }

        if ((int)($compra['compra_asig_id'] ?? 0) > 0) {
            return ['ok' => TRUE, 'asig_id' => (int)$compra['compra_asig_id'], 'no_plan' => FALSE];
        }

        $cantidad = (int)($compra['compra_cantidad'] ?? 0);
        $num_id   = (int)($compra['compra_num_id'] ?? 0);
        $periodo  = (string)($compra['compra_periodo'] ?? 'sin_limite');
        $ahora    = date('Y-m-d H:i:s');
        $fin      = NULL;

        if ($cantidad <= 0) {
            if ($periodo === 'mensual') {
                $fin = date('Y-m-d H:i:s', strtotime('+1 month'));
            } elseif ($periodo === 'semestral') {
                $fin = date('Y-m-d H:i:s', strtotime('+6 months'));
            } elseif ($periodo === 'anual') {
                $fin = date('Y-m-d H:i:s', strtotime('+1 year'));
            }
        }

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $ok = $this->db->insert($this->table_lotes, [
                'asig_org_id'          => (int)$compra['compra_org_id'],
                'asig_plan_id'         => (int)$compra['compra_plan_id'],
                'asig_num_id'          => $cantidad > 0 && $num_id > 0 ? $num_id : NULL,
                'asig_cantidad'        => $cantidad > 0 ? $cantidad : NULL,
                'asig_precio_unitario' => round((float)$compra['compra_precio_unitario'], 2),
                'asig_total'           => round((float)$compra['compra_total'], 2),
                'asig_origen'          => 'compra',
                'asig_inicio'          => $ahora,
                'asig_fin'             => $fin,
                'asig_rifas_usadas'    => 0,
                'asig_estado'          => 'activa',
                'asig_notas'           => 'Compra #' . (string)$mov_id,
                'asig_created_at'      => $ahora,
                'asig_updated_at'      => $ahora,
            ]);

            if (!$ok) {
                return ['ok' => FALSE, 'asig_id' => NULL, 'no_plan' => FALSE];
            }

            $asig_id = (int)$this->db->insert_id();

            if ($asig_id <= 0) {
                return ['ok' => FALSE, 'asig_id' => NULL, 'no_plan' => FALSE];
            }

            $actualizada = $this->db
                ->where('compra_id', (int)$compra['compra_id'])
                ->where('compra_asig_id', NULL)
                ->update($this->table_compras, [
                    'compra_asig_id'    => $asig_id,
                    'compra_estado'     => 'pagada',
                    'compra_updated_at' => $ahora,
                ]);

            if (!$actualizada || $this->db->affected_rows() !== 1) {
                return ['ok' => FALSE, 'asig_id' => NULL, 'no_plan' => FALSE];
            }

            return ['ok' => TRUE, 'asig_id' => $asig_id, 'no_plan' => FALSE];
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * @param  string $mov_id
     * @return bool
     */
    public function marcar_cancelada($mov_id)
    {
        if (!$this->db->table_exists($this->table_compras)) {
            return FALSE;
        }

        $compra = $this->db
            ->select('compra_id, compra_estado, compra_asig_id')
            ->where('compra_mov_id', (string)$mov_id)
            ->limit(1)
            ->get($this->table_compras)
            ->row_array();

        if (empty($compra)) {
            return FALSE;
        }

        if ((int)($compra['compra_asig_id'] ?? 0) > 0
            || in_array((string)$compra['compra_estado'], ['pagada'], TRUE)) {
            return FALSE;
        }

        if ((string)$compra['compra_estado'] === 'cancelada') {
            return TRUE;
        }

        $ok = $this->db
            ->where('compra_id', (int)$compra['compra_id'])
            ->where('compra_asig_id', NULL)
            ->where_in('compra_estado', ['pendiente', 'expirada', 'error'])
            ->update($this->table_compras, [
                'compra_estado'     => 'cancelada',
                'compra_updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $ok && $this->db->affected_rows() === 1;
    }

    // ─────────────────────────────────────────────────────────

    private function _opciones_activas($plan_id)
    {
        return $this->db
            ->select('pn.pnum_num_id, pn.pnum_precio, n.num_label, n.num_orden', FALSE)
            ->from($this->table_opciones . ' pn')
            ->join($this->table_num . ' n', 'n.num_id = pn.pnum_num_id', 'inner')
            ->where('pn.pnum_plan_id', (int)$plan_id)
            ->where('pn.pnum_status', 1)
            ->where('n.num_status', 1)
            ->order_by('pn.pnum_orden', 'ASC')
            ->order_by('pn.pnum_id', 'ASC')
            ->get()
            ->result_array();
    }

    private function _upsert_finito(array $plan, array $opcion)
    {
        return $this->_upsert([
            'pla_nombre'       => $this->_nombre_oferta($plan['plan_nombre'], $opcion['num_label']),
            'pla_descripcion'  => (string)($plan['plan_descripcion'] ?? ''),
            'pla_tipo'         => 'plan_rifa',
            'pla_rif_plan_id'  => (int)$plan['plan_id'],
            'pla_rif_num_id'   => (int)$opcion['pnum_num_id'],
            'pla_rif_periodo'  => 'sin_limite',
            'pla_numero'       => 0,
            'pla_precio'       => round((float)$opcion['pnum_precio'], 2),
            'pla_moneda'       => (string)$plan['plan_moneda'],
            'pla_paypal'       => 1,
            'pla_crypto'       => 1,
            'pla_status'       => 1,
            'pla_eliminado'    => 0,
        ]);
    }

    private function _upsert_ilimitado(array $plan)
    {
        return $this->_upsert([
            'pla_nombre'       => (string)$plan['plan_nombre'],
            'pla_descripcion'  => (string)($plan['plan_descripcion'] ?? ''),
            'pla_tipo'         => 'plan_rifa',
            'pla_rif_plan_id'  => (int)$plan['plan_id'],
            'pla_rif_num_id'   => 0,
            'pla_rif_periodo'  => (string)$plan['plan_periodo'],
            'pla_numero'       => 0,
            'pla_precio'       => round((float)$plan['plan_precio'], 2),
            'pla_moneda'       => (string)$plan['plan_moneda'],
            'pla_paypal'       => 1,
            'pla_crypto'       => 1,
            'pla_status'       => 1,
            'pla_eliminado'    => 0,
        ]);
    }

    private function _upsert(array $data)
    {
        $existente = $this->db
            ->where('pla_rif_plan_id', (int)$data['pla_rif_plan_id'])
            ->where('pla_rif_num_id', (int)$data['pla_rif_num_id'])
            ->limit(1)
            ->get($this->table_ofertas)
            ->row_array();

        $data['pla_nombre'] = $this->_nombre_unico(
            (string)$data['pla_nombre'],
            $existente ? (int)$existente['pla_id'] : 0
        );

        $ahora = date('Y-m-d H:i:s');

        if (!empty($existente)) {
            $data['pla_updated_at'] = $ahora;
            return $this->db->where('pla_id', (int)$existente['pla_id'])->update($this->table_ofertas, $data)
                ? (int)$existente['pla_id']
                : NULL;
        }

        $data['pla_created_at'] = $ahora;
        $data['pla_updated_at'] = $ahora;

        if ($this->db->insert($this->table_ofertas, $data)) {
            return (int)$this->db->insert_id();
        }

        // Otra petición concurrente pudo insertar la misma oferta entre el
        // SELECT y el INSERT. La oferta ya existe y se reutiliza.
        $creada = $this->db
            ->where('pla_rif_plan_id', (int)$data['pla_rif_plan_id'])
            ->where('pla_rif_num_id', (int)$data['pla_rif_num_id'])
            ->limit(1)
            ->get($this->table_ofertas)
            ->row_array();

        return empty($creada) ? NULL : (int)$creada['pla_id'];
    }

    private function _nombre_oferta($plan_nombre, $num_label)
    {
        $nombre = trim((string)$plan_nombre) . ' — ' . trim((string)$num_label);
        return $this->_limitar($nombre, 100);
    }

    private function _nombre_unico($nombre, $ignorar_id = 0)
    {
        $nombre = $this->_limitar(trim((string)$nombre), 100);
        $base   = $nombre;
        $i      = 2;

        while (TRUE) {
            $this->db->where('pla_nombre', $nombre);
            if ((int)$ignorar_id > 0) {
                $this->db->where('pla_id !=', (int)$ignorar_id);
            }

            if ($this->db->count_all_results($this->table_ofertas) === 0) {
                return $nombre;
            }

            $sufijo = ' (' . $i . ')';
            $nombre = $this->_limitar($base, 100 - strlen($sufijo)) . $sufijo;
            $i++;

            if ($i > 50) {
                return $this->_limitar($base . ' (' . $ignorar_id . ')', 100);
            }
        }
    }

    private function _limitar($texto, $max)
    {
        return function_exists('mb_substr')
            ? mb_substr((string)$texto, 0, (int)$max, 'UTF-8')
            : substr((string)$texto, 0, (int)$max);
    }
}
