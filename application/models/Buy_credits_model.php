<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Buy_credits_model
 * ─────────────────────────────────────────────────────────────
 * Model for purchasing plans, credits, and managing orders.
 * Uses row transitions and real transactions on cab_movplanes, cab_usuarios
 * and cab_movsms, with defensive compensation for pre-migration MyISAM data.
 *
 * cab_movsms is an order-line detail created with the order; it is NOT a
 * credit ledger. Credits are only applied by liquidar_pago_stripe() or
 * liquidar_pago_aprobado() after the gateway confirms payment.
 */
class Buy_credits_model extends CI_Model
{
    protected $tabla_planes   = 'cab_planes';
    protected $tabla_ordenes  = 'cab_movplanes';
    protected $tabla_movsms   = 'cab_movsms';

    protected $columnas_ordenables = [
        0 => 'm.mov_id',
        1 => 'm.mov_nomplan',
        2 => 'm.mov_sms',
        3 => 'm.mov_precioplan',
        4 => 'm.mov_statuspag',
        5 => 'm.mov_fechcomp',
    ];

    public function __construct()
    {
        parent::__construct();

        // M08: el settlement de una orden de plan de rifa debe crear el lote.
        $this->load->model('Plan_compra_model');
    }

    /**
     * Counts all orders belonging to the user.
     *
     * @param  string $id_usuario
     * @return int
     */
    public function count_todos($id_usuario)
    {
        return $this->db
            ->where('mov_idusuario', (string)$id_usuario)
            ->count_all_results($this->tabla_ordenes);
    }

    /**
     * Counts filtered orders for the user after search filter.
     *
     * @param  string $id_usuario
     * @param  string $search
     * @param  string $estado
     * @return int
     */
    public function count_filtrados($id_usuario, $search = '', $estado = '')
    {
        $this->_datatable_query($id_usuario, $search, $estado);
        return $this->db->count_all_results();
    }

    /**
     * Gets paginated and ordered records for server-side DataTables.
     *
     * @param  string $id_usuario
     * @param  int    $start
     * @param  int    $length
     * @param  string $search
     * @param  int    $order_col
     * @param  string $order_dir
     * @param  string $estado
     * @return array
     */
    public function get_datatable($id_usuario, $start, $length, $search = '', $order_col = 5, $order_dir = 'desc', $estado = '')
    {
        $this->_datatable_query($id_usuario, $search, $estado);

        $columna   = $this->columnas_ordenables[$order_col] ?? 'm.mov_fechcomp';
        $direccion = strtolower((string)$order_dir) === 'asc' ? 'ASC' : 'DESC';

        $this->db->order_by($columna, $direccion);
        if ($columna === 'm.mov_fechcomp') {
            $this->db->order_by('m.mov_hora', $direccion);
        }

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        return $this->db->get()->result_array();
    }

    /**
     * Query base for DataTables filtering and fetching.
     *
     * @param  string $id_usuario
     * @param  string $search
     * @param  string $estado
     * @return void
     */
    private function _datatable_query($id_usuario, $search = '', $estado = '')
    {
        $this->db
            ->select('m.*, p.pla_descripcion, p.pla_paypal, p.pla_crypto, p.pla_status, p.pla_eliminado,'
                . ' p.pla_tipo, p.pla_rif_plan_id, p.pla_rif_num_id, p.pla_rif_periodo')
            ->from($this->tabla_ordenes . ' m')
            ->join($this->tabla_planes . ' p', 'p.pla_id = m.mov_idplan', 'left')
            ->where('m.mov_idusuario', (string)$id_usuario);

        $estados = [
            'unpaid'     => ['NoPagado'],
            'waiting'    => ['waiting'],
            'processing' => ['in_process', 'in_process_partial'],
            'paid'       => ['approved'],
            'refunded'   => ['refunded'],
            'expired'    => ['expired'],
            'cancelled'  => ['cancelled'],
            'rejected'   => ['rejected'],
        ];

        $estado = strtolower(trim((string)$estado));
        if (isset($estados[$estado])) {
            $this->db->where_in('m.mov_statuspag', $estados[$estado]);
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $search_lower = strtolower($search);
            $estados_busqueda = [
                'unpaid'     => 'NoPagado',
                'paid'       => 'approved',
                'awaiting'   => 'waiting',
                'waiting'    => 'waiting',
                'processing' => 'in_process',
                'partial'    => 'in_process_partial',
                'cancelled'  => 'cancelled',
                'canceled'   => 'cancelled',
                'expired'    => 'expired',
                'rejected'   => 'rejected',
            ];

            $this->db->group_start();
            $this->db->like('m.mov_id', $search);
            $this->db->or_like('m.mov_nomplan', $search);
            $this->db->or_like('m.mov_sms', $search);
            $this->db->or_like('m.mov_precioplan', $search);
            $this->db->or_like('m.mov_moneda', $search);
            $this->db->or_like('m.mov_statuspag', $search);
            $this->db->or_like('m.mov_tippago', $search);
            $this->db->or_like('m.mov_coin', $search);
            $this->db->or_like('m.mov_fechcomp', $search);
            $this->db->or_like('m.mov_fechpago', $search);
            $this->db->or_like('p.pla_descripcion', $search);

            if (isset($estados_busqueda[$search_lower])) {
                $this->db->or_like('m.mov_statuspag', $estados_busqueda[$search_lower]);
            }

            $this->db->group_end();
        }
    }

    /**
     * Gets all active plans available for purchase, ordered by pla_id ASC.
     *
     * @return array
     */
    public function get_planes_activos()
    {
        return $this->db
            ->where('pla_status', 1)
            ->where('pla_eliminado', 0)
            ->order_by('pla_id', 'ASC')
            ->get($this->tabla_planes)
            ->result_array();
    }

    /**
     * Gets a single plan by its ID if active and not deleted.
     *
     * @param  int $id
     * @return array|null
     */
    public function get_plan_por_id($id)
    {
        return $this->db
            ->where('pla_id', (int)$id)
            ->where('pla_status', 1)
            ->where('pla_eliminado', 0)
            ->get($this->tabla_planes)
            ->row_array();
    }

    /**
     * Checks if the user has an active unpaid order.
     *
     * @param  string $id_usuario
     * @return array|null
     */
    public function get_orden_pendiente_usuario($id_usuario)
    {
        return $this->db
            ->select('m.*, p.pla_descripcion, p.pla_paypal, p.pla_crypto,'
                . ' p.pla_tipo, p.pla_rif_plan_id, p.pla_rif_num_id, p.pla_rif_periodo')
            ->from($this->tabla_ordenes . ' m')
            ->join($this->tabla_planes . ' p', 'p.pla_id = m.mov_idplan', 'left')
            ->where('m.mov_idusuario', (string)$id_usuario)
            ->where_in('m.mov_status', ['A', 'V'])
            ->where_in('m.mov_statuspag', ['NoPagado', 'waiting', 'in_process', 'in_process_partial'])
            ->order_by('m.mov_fechcomp', 'DESC')
            ->order_by('m.mov_hora', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Gets order/invoice history for the given user.
     *
     * @param  string $id_usuario
     * @param  int    $limit
     * @return array
     */
    public function get_historial_ordenes($id_usuario, $limit = 20)
    {
        return $this->db
            ->select('m.*, p.pla_descripcion, p.pla_paypal, p.pla_crypto,'
                . ' p.pla_tipo, p.pla_rif_plan_id, p.pla_rif_num_id, p.pla_rif_periodo')
            ->from($this->tabla_ordenes . ' m')
            ->join($this->tabla_planes . ' p', 'p.pla_id = m.mov_idplan', 'left')
            ->where('m.mov_idusuario', (string)$id_usuario)
            ->order_by('m.mov_fechcomp', 'DESC')
            ->order_by('m.mov_hora', 'DESC')
            ->limit((int)$limit)
            ->get()
            ->result_array();
    }

    /**
     * Gets single order details verifying user ownership (or Admin).
     *
     * @param  string      $mov_id
     * @param  string|null $id_usuario
     * @return array|null
     */
    public function get_orden_por_id($mov_id, $id_usuario = NULL)
    {
        $this->db
            ->select('m.*, p.pla_descripcion, p.pla_paypal, p.pla_crypto,'
                . ' p.pla_tipo, p.pla_rif_plan_id, p.pla_rif_num_id, p.pla_rif_periodo')
            ->from($this->tabla_ordenes . ' m')
            ->join($this->tabla_planes . ' p', 'p.pla_id = m.mov_idplan', 'left')
            ->where('m.mov_id', (string)$mov_id);

        if ($id_usuario !== NULL) {
            $this->db->where('m.mov_idusuario', (string)$id_usuario);
        }

        return $this->db->get()->row_array();
    }

    /**
     * Processes a purchase creating the order in cab_movplanes.
     *
     * - `plan_rifa`: creates cab_movplanes + rif_plan_compras (no cab_movsms).
     * - `creditos`: keeps the historical behavior (cab_movsms detail).
     *
     * @param  string $id_usuario
     * @param  array  $plan
     * @param  int    $cantidad
     * @param  int    $org_id
     * @return string|false Generated order ID or FALSE on failure
     */
    public function procesar_compra($id_usuario, array $plan, $cantidad = 1, $org_id = 0)
    {
        $tipo    = (string)($plan['pla_tipo'] ?? 'creditos');
        $precio  = round((float)($plan['pla_precio'] ?? 0), 2);
        $moneda  = strtoupper(trim((string)($plan['pla_moneda'] ?? 'USD')));

        if ($tipo === 'plan_rifa') {
            $rif_plan_id = (int)($plan['pla_rif_plan_id'] ?? 0);
            $rif_num_id  = (int)($plan['pla_rif_num_id'] ?? 0);
            $org_id      = (int)$org_id;

            if ($rif_plan_id <= 0 || $org_id <= 0 || $precio <= 0
                || !preg_match('/^[A-Z]{3,10}$/', $moneda)) {
                return FALSE;
            }

            if ($rif_num_id > 0) {
                $cantidad = (int)$cantidad;
                if ($cantidad < 1 || $cantidad > 10000) {
                    return FALSE;
                }
                $total = round($precio * $cantidad, 2);
            } else {
                // Los planes ilimitados compran un único periodo.
                $cantidad = 1;
                $total    = $precio;
            }

            $periodo = $rif_num_id > 0
                ? 'sin_limite'
                : (string)($plan['pla_rif_periodo'] ?? 'sin_limite');

            if (!in_array($periodo, ['sin_limite', 'mensual', 'semestral', 'anual'], TRUE)) {
                $periodo = 'sin_limite';
            }

            // Serialize purchase creation per user with an advisory lock.
            $lock_name = 'vc_buy_' . substr(hash('sha256', (string)$id_usuario), 0, 40);
            $lock_row  = $this->db
                ->query('SELECT GET_LOCK(?, 5) AS l', [$lock_name])
                ->row_array();

            if (empty($lock_row) || (int)($lock_row['l'] ?? 0) !== 1) {
                return FALSE;
            }

            try {
                return $this->_procesar_compra_plan_bloqueada(
                    $id_usuario,
                    $plan,
                    $rif_plan_id,
                    $rif_num_id,
                    $cantidad,
                    $periodo,
                    $precio,
                    $moneda,
                    $total,
                    $org_id
                );
            } finally {
                $this->db->query('SELECT RELEASE_LOCK(?)', [$lock_name]);
            }
        }

        $creditos = (int)($plan['pla_numero'] ?? 0);

        if ($precio <= 0 || $creditos <= 0 || !preg_match('/^[A-Z]{3,10}$/', $moneda)) {
            return FALSE;
        }

        // Serialize purchase creation per user with an advisory lock. This
        // also protects deployments where the settlement tables have not yet
        // been converted to InnoDB.
        $lock_name = 'vc_buy_' . substr(hash('sha256', (string)$id_usuario), 0, 40);
        $lock_row  = $this->db
            ->query('SELECT GET_LOCK(?, 5) AS l', [$lock_name])
            ->row_array();

        if (empty($lock_row) || (int)($lock_row['l'] ?? 0) !== 1) {
            return FALSE;
        }

        try {
            return $this->_procesar_compra_bloqueada($id_usuario, $plan, $precio, $moneda, $creditos);
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock_name]);
        }
    }

    /**
     * Creates the order while the per-user advisory lock is held.
     *
     * @param  string $id_usuario
     * @param  array  $plan
     * @param  float  $precio
     * @param  string $moneda
     * @param  int    $creditos
     * @return string|false
     */
    private function _procesar_compra_bloqueada($id_usuario, array $plan, $precio, $moneda, $creditos)
    {
        $this->db->trans_start();

        if ($this->_tiene_orden_pendiente($id_usuario)) {
            $this->db->trans_complete();
            return FALSE;
        }

        $now_date = date('Y-m-d');
        $now_time = date('H:i:s');

        $mov_id = $this->_generar_mov_id();
        if ($mov_id === FALSE) {
            $this->db->trans_complete();
            return FALSE;
        }

        // 2. Insert into cab_movplanes
        $data_orden = [
            'mov_id'         => $mov_id,
            'mov_idusuario'  => (string)$id_usuario,
            'mov_idplan'     => (string)$plan['pla_id'],
            'mov_nomplan'    => (string)$plan['pla_nombre'],
            'mov_precioplan' => number_format($precio, 2, '.', ''),
            'mov_moneda'     => $moneda,
            'mov_sms'        => $creditos,
            'mov_status'     => 'A',
            'mov_statuspag'  => 'NoPagado',
            'mov_fechcomp'   => $now_date,
            'mov_hora'       => $now_time,
            'mov_pais'       => 'US',
        ];
        $insert_orden = $this->db->insert($this->tabla_ordenes, $data_orden);

        // 3. Insert into cab_movsms (credits detail)
        $data_sms = [
            'movs_id'        => $mov_id,
            'movs_idusuario' => (string)$id_usuario,
            'movs_sms'       => $creditos,
        ];
        $insert_sms = $this->db->insert($this->tabla_movsms, $data_sms);

        $completed = $this->db->trans_complete();

        if (!$insert_orden || !$insert_sms || $completed === FALSE
            || $this->db->trans_status() === FALSE) {
            log_message('error', 'BuyCredits: unable to commit order ' . (string)$mov_id . '.');
            return FALSE;
        }

        return $mov_id;
    }

    /**
     * Creates a plan/lote order while the per-user advisory lock is held.
     *
     * @param  string $id_usuario
     * @param  array  $plan
     * @param  int    $rif_plan_id
     * @param  int    $rif_num_id
     * @param  int    $cantidad
     * @param  string $periodo
     * @param  float  $precio        Precio unitario
     * @param  string $moneda
     * @param  float  $total
     * @param  int    $org_id
     * @return string|false
     */
    private function _procesar_compra_plan_bloqueada(
        $id_usuario,
        array $plan,
        $rif_plan_id,
        $rif_num_id,
        $cantidad,
        $periodo,
        $precio,
        $moneda,
        $total,
        $org_id
    ) {
        if (!$this->db->table_exists('rif_plan_compras')) {
            log_message('error', 'BuyCredits: rif_plan_compras is missing; cannot create a plan order.');
            return FALSE;
        }

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->trans_start();

            if ($this->_tiene_orden_pendiente($id_usuario)) {
                $this->db->trans_complete();
                return FALSE;
            }

            $mov_id = $this->_generar_mov_id();
            if ($mov_id === FALSE) {
                $this->db->trans_complete();
                return FALSE;
            }

            $now_date = date('Y-m-d');
            $now_time = date('H:i:s');
            $now      = date('Y-m-d H:i:s');

            $data_orden = [
                'mov_id'         => $mov_id,
                'mov_idusuario'  => (string)$id_usuario,
                'mov_idplan'     => (string)$plan['pla_id'],
                'mov_nomplan'    => (string)$plan['pla_nombre'],
                'mov_precioplan' => number_format($total, 2, '.', ''),
                'mov_moneda'     => $moneda,
                'mov_sms'        => (int)$cantidad,
                'mov_status'     => 'A',
                'mov_statuspag'  => 'NoPagado',
                'mov_fechcomp'   => $now_date,
                'mov_hora'       => $now_time,
                'mov_pais'       => 'US',
            ];
            $insert_orden = $this->db->insert($this->tabla_ordenes, $data_orden);

            $insert_compra = $this->db->insert('rif_plan_compras', [
                'compra_mov_id'         => (string)$mov_id,
                'compra_org_id'         => (int)$org_id,
                'compra_plan_id'        => (int)$rif_plan_id,
                'compra_num_id'         => (int)$rif_num_id,
                // 0 = ilimitado: el lote no tiene cupo finito y su vigencia
                // se calcula desde compra_periodo (mensual/anual).
                'compra_cantidad'       => $rif_num_id > 0 ? (int)$cantidad : 0,
                'compra_periodo'        => (string)$periodo,
                'compra_precio_unitario' => number_format($precio, 2, '.', ''),
                'compra_total'          => number_format($total, 2, '.', ''),
                'compra_moneda'         => $moneda,
                'compra_estado'         => 'pendiente',
                'compra_created_at'     => $now,
                'compra_updated_at'     => $now,
            ]);

            $completed = $this->db->trans_complete();

            if (!$insert_orden || !$insert_compra || $completed === FALSE
                || $this->db->trans_status() === FALSE) {
                log_message('error', 'BuyCredits: unable to commit plan order ' . (string)$mov_id . '.');
                return FALSE;
            }

            return $mov_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'BuyCredits: plan order creation failed.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Indica si el usuario tiene una orden pendiente de pago.
     *
     * @param  string $id_usuario
     * @return bool
     */
    private function _tiene_orden_pendiente($id_usuario)
    {
        return (int)$this->db
            ->where('mov_idusuario', (string)$id_usuario)
            ->where_in('mov_status', ['A', 'V'])
            ->where_in('mov_statuspag', ['NoPagado', 'waiting', 'in_process', 'in_process_partial'])
            ->count_all_results($this->tabla_ordenes) > 0;
    }

    /**
     * Genera un `mov_id` de 10 caracteres no predecible.
     *
     * @return string|false
     */
    private function _generar_mov_id()
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $conteo     = $this->db->count_all($this->tabla_ordenes);
            $secuencial = str_pad((string)($conteo + 1), 7, '0', STR_PAD_LEFT);
            $random     = str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $candidato  = $secuencial . $random;

            if ($this->db->where('mov_id', $candidato)->count_all_results($this->tabla_ordenes) === 0) {
                return $candidato;
            }
        }

        return FALSE;
    }

    /**
     * Updates an order with a generated BTCPay invoice.
     *
     * @param  string      $mov_id
     * @param  string      $invoice_id
     * @param  string|null $expected_invoice_id Current binding expected by the
     *                                           caller ('' for an unbound order).
     * @return bool
     */
    public function actualizar_invoice_btcpay($mov_id, $invoice_id, $expected_invoice_id = NULL)
    {
        $this->db
            ->where('mov_id', (string)$mov_id)
            // Legacy BTCPay orders may still carry the historical 'V' status.
            ->where_in('mov_status', ['A', 'V'])
            ->where_in('mov_statuspag', ['NoPagado', 'waiting', 'in_process_partial']);

        if ($expected_invoice_id !== NULL) {
            if ((string)$expected_invoice_id === '') {
                $this->db->group_start()
                    ->where('mov_idpago', '')
                    ->or_where('mov_idpago IS NULL', NULL, FALSE)
                    ->group_end();
            } else {
                $this->db->where('mov_idpago', (string)$expected_invoice_id);
            }
        }

        $ok = $this->db->update($this->tabla_ordenes, [
                'mov_idpago'    => (string)$invoice_id,
                'mov_refid'     => (string)$invoice_id,
                'mov_tippago'   => 'BTCPay',
                'mov_statuspag' => 'waiting',
            ]);

        // A zero affected-row count means the order stopped being pending
        // between validation and the atomic update.
        return $ok && $this->db->affected_rows() > 0;
    }

    /**
     * Binds a Stripe Checkout Session to a pending order.
     *
     * Uses an optimistic lock on the previous session reference so two
     * concurrent checkout requests cannot overwrite each other. It also
     * refuses to overwrite an order that is already owned by another gateway.
     *
     * @param  string $mov_id
     * @param  string $session_id
     * @param  string $payment_intent
     * @param  string $expected_session
     * @param  string $currency
     * @return bool
     */
    public function actualizar_sesion_stripe($mov_id, $session_id, $payment_intent, $expected_session, $currency)
    {
        if (!$this->db->field_exists('mov_stripe_session_id', $this->tabla_ordenes)) {
            log_message('error', 'Stripe: cab_movplanes.mov_stripe_session_id is missing. Apply the Stripe migration.');
            return FALSE;
        }

        $this->db
            ->where('mov_id', (string)$mov_id)
            ->where('mov_status', 'A')
            ->where_in('mov_statuspag', ['NoPagado', 'waiting']);

        $expected_session = trim((string)$expected_session);
        if ($expected_session === '') {
            $this->db->group_start()
                ->where('mov_stripe_session_id', '')
                ->or_where('mov_stripe_session_id IS NULL', NULL, FALSE)
                ->group_end();

            // The order must not already belong to another payment gateway.
            $this->db->group_start()
                ->where('mov_idpago', '')
                ->or_where('mov_idpago IS NULL', NULL, FALSE)
                ->group_end();
        } else {
            $this->db->where('mov_stripe_session_id', $expected_session);

            // Never let a stale/inconsistent gateway reference be overwritten.
            $this->db->group_start()
                ->where('mov_idpago', $expected_session)
                ->or_where('mov_idpago', '')
                ->or_where('mov_idpago IS NULL', NULL, FALSE)
                ->group_end();
        }

        $payment_intent = trim((string)$payment_intent);

        $ok = $this->db->update($this->tabla_ordenes, [
            'mov_stripe_session_id'     => (string)$session_id,
            'mov_stripe_payment_intent' => $payment_intent !== '' ? $payment_intent : NULL,
            'mov_idpago'                => (string)$session_id,
            'mov_refid'                 => (string)$session_id,
            'mov_tippago'               => 'Stripe',
            'mov_coin'                  => strtoupper(trim((string)$currency)),
            'mov_statuspag'             => 'waiting',
        ]);

        return $ok && $this->db->affected_rows() > 0;
    }

    /**
     * Late-binds a paid Stripe session when the order has no gateway binding.
     *
     * This covers the narrow race where Stripe created a session but the
     * response was lost before the session id could be persisted. It is only
     * allowed when the order is pending and no other gateway owns it.
     *
     * @param  string $mov_id
     * @param  string $session_id
     * @param  string $payment_intent
     * @param  string $currency
     * @return bool
     */
    public function vincular_sesion_stripe_pagada($mov_id, $session_id, $payment_intent, $currency)
    {
        if (!$this->db->field_exists('mov_stripe_session_id', $this->tabla_ordenes)) {
            return FALSE;
        }

        $payment_intent = trim((string)$payment_intent);
        $reference      = $payment_intent !== '' ? $payment_intent : (string)$session_id;

        $ok = $this->db
            ->where('mov_id', (string)$mov_id)
            ->where('mov_status', 'A')
            ->where_in('mov_statuspag', ['NoPagado', 'waiting'])
            ->group_start()
                ->where('mov_stripe_session_id', '')
                ->or_where('mov_stripe_session_id IS NULL', NULL, FALSE)
            ->group_end()
            ->group_start()
                ->where('mov_idpago', '')
                ->or_where('mov_idpago IS NULL', NULL, FALSE)
            ->group_end()
            ->update($this->tabla_ordenes, [
                'mov_stripe_session_id'     => (string)$session_id,
                'mov_stripe_payment_intent' => $payment_intent !== '' ? $payment_intent : NULL,
                'mov_idpago'                => (string)$reference,
                'mov_refid'                 => (string)$session_id,
                'mov_tippago'               => 'Stripe',
                'mov_coin'                  => strtoupper(trim((string)$currency)),
                'mov_statuspag'             => 'waiting',
            ]);

        return $ok && $this->db->affected_rows() === 1;
    }

    /**
     * Idempotently marks a Stripe order as approved.
     *
     * - Orden de créditos: acredita `usu_credit`.
     * - Orden de plan de rifa: crea el lote (`rif_plan_asignaciones`) en la
     *   misma transacción. No exige créditos ni toca `usu_credit`.
     *
     * @param  array  $order
     * @param  string $session_id
     * @param  string $payment_intent
     * @param  string $currency
     * @return int|false 1 = settled, 0 = already processed, FALSE = DB error
     */
    public function liquidar_pago_stripe(array $order, $session_id, $payment_intent, $currency)
    {
        $sms_credits = (int)($order['mov_sms'] ?? 0);
        $id_usuario  = (string)($order['mov_idusuario'] ?? '');
        $es_plan     = $this->_es_compra_plan((string)($order['mov_id'] ?? ''));

        if ($id_usuario === '') {
            log_message('error', 'Stripe: order ' . (string)($order['mov_id'] ?? '') . ' has no user.');
            return FALSE;
        }

        if (!$es_plan && $this->_orden_es_plan($order)) {
            log_message('error', 'Stripe: plan order ' . (string)$order['mov_id'] . ' has no rif_plan_compras row; settlement aborted.');
            return FALSE;
        }

        // Never approve a paid credit order when the credit balance cannot be
        // increased. The webhook will retry so the payment can be reconciled.
        if (!$es_plan
            && ($sms_credits <= 0 || !$this->db->field_exists('usu_credit', 'cab_usuarios'))) {
            log_message('error', 'Stripe: order ' . (string)$order['mov_id'] . ' cannot be credited because the user or usu_credit field is invalid.');
            return FALSE;
        }

        $usuario = $this->db
            ->select('usu_id')
            ->where('usu_id', $id_usuario)
            ->get('cab_usuarios')
            ->row_array();
        if (empty($usuario)) {
            log_message('error', 'Stripe: user ' . $id_usuario . ' does not exist for order ' . (string)$order['mov_id'] . '.');
            return FALSE;
        }

        $this->db->trans_start();

        $payment_intent = trim((string)$payment_intent);
        $reference      = $payment_intent !== '' ? $payment_intent : (string)$session_id;

        $this->db
            ->where('mov_id', (string)$order['mov_id'])
            ->where('mov_status', 'A')
            ->where_in('mov_statuspag', ['NoPagado', 'waiting', 'in_process'])
            ->where('mov_stripe_session_id', (string)$session_id)
            ->update($this->tabla_ordenes, [
                'mov_statuspag'             => 'approved',
                'mov_status'                => 'A',
                'mov_fechpago'              => date('Y-m-d'),
                'mov_horapag'               => date('H:i:s'),
                'mov_fechvenc'              => date('Y-m-d'),
                'mov_idpago'                => (string)$reference,
                'mov_refid'                 => (string)$session_id,
                'mov_tippago'               => 'Stripe',
                'mov_coin'                  => strtoupper(trim((string)$currency)),
                'mov_stripe_payment_intent' => $payment_intent !== '' ? $payment_intent : NULL,
            ]);

        $filas_actualizadas = (int)$this->db->affected_rows();

        if ($filas_actualizadas === 1) {
            if ($es_plan) {
                if (!$this->_crear_lote_plan((string)$order['mov_id'])) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            } else {
                $this->db
                    ->set('usu_credit', 'COALESCE(usu_credit, 0) + ' . $sms_credits, FALSE)
                    ->where('usu_id', $id_usuario)
                    ->update('cab_usuarios');

                if ($this->db->affected_rows() !== 1) {
                    log_message('error', 'Stripe: unable to credit user ' . $id_usuario . ' for order ' . (string)$order['mov_id'] . '.');

                    // Defensive compensation only for deployments where the InnoDB
                    // migration has not run. On InnoDB, trans_rollback() reverts
                    // both the approval and any manual compensation.
                    if (!$this->_liquidacion_es_innodb()) {
                        $this->db
                            ->where('mov_id', (string)$order['mov_id'])
                            ->where('mov_stripe_session_id', (string)$session_id)
                            ->where('mov_statuspag', 'approved')
                            ->update($this->tabla_ordenes, [
                                'mov_statuspag' => 'in_process',
                                'mov_fechpago'  => NULL,
                                'mov_horapag'   => NULL,
                            ]);

                        if ($this->db->affected_rows() !== 1) {
                            log_message('error', 'Stripe: CRITICAL unable to revert order ' . (string)$order['mov_id'] . ' after a credit failure.');
                        }
                    }

                    $this->db->trans_rollback();
                    return FALSE;
                }
            }
        }

        $completed = $this->db->trans_complete();

        if ($completed === FALSE || $this->db->trans_status() === FALSE) {
            log_message('error', 'Stripe: unable to commit settlement for order ' . (string)$order['mov_id'] . '.');
            return FALSE;
        }

        return $filas_actualizadas;
    }

    /**
     * Updates a Stripe order to an intermediate/terminal status.
     *
     * @param  string $mov_id
     * @param  string $session_id
     * @param  string $status
     * @return bool
     */
    public function actualizar_estado_stripe($mov_id, $session_id, $status)
    {
        if (!$this->db->field_exists('mov_stripe_session_id', $this->tabla_ordenes)) {
            return FALSE;
        }

        $ok = $this->db
            ->where('mov_id', (string)$mov_id)
            ->where('mov_status', 'A')
            ->where_in('mov_statuspag', ['NoPagado', 'waiting', 'in_process'])
            ->where('mov_stripe_session_id', (string)$session_id)
            ->update($this->tabla_ordenes, [
                'mov_statuspag' => (string)$status,
                'mov_tippago'   => 'Stripe',
            ]);

        // A concurrent delivery may already have moved the order to the same
        // status. Returning false lets Stripe retry, and the retry will read
        // the fresh order state and acknowledge the already-applied status.
        return $ok && $this->db->affected_rows() > 0;
    }

    /**
     * Idempotently marks a BTCPay order as approved.
     *
     * - Orden de créditos: acredita `usu_credit`.
     * - Orden de plan de rifa: crea el lote (`rif_plan_asignaciones`) en la
     *   misma transacción. No exige créditos ni toca `usu_credit`.
     *
     * The order transition is the idempotency guard. If the subsequent write
     * fails, the transaction rolls back. A defensive explicit revert is kept
     * for pre-migration MyISAM deployments.
     *
     * @param  array  $order
     * @param  string $invoice_id
     * @param  string $coin
     * @return int|false 1 = settled, 0 = already processed, FALSE = DB error
     */
    public function liquidar_pago_aprobado(array $order, $invoice_id, $coin = 'BTC')
    {
        $sms_credits = (int)($order['mov_sms'] ?? 0);
        $id_usuario  = (string)($order['mov_idusuario'] ?? '');
        $es_plan     = $this->_es_compra_plan((string)($order['mov_id'] ?? ''));

        if ($id_usuario === '') {
            log_message('error', 'BTCPay: order ' . (string)($order['mov_id'] ?? '') . ' has no user.');
            return FALSE;
        }

        if (!$es_plan && $this->_orden_es_plan($order)) {
            log_message('error', 'BTCPay: plan order ' . (string)$order['mov_id'] . ' has no rif_plan_compras row; settlement aborted.');
            return FALSE;
        }

        if (!$es_plan
            && ($sms_credits <= 0 || !$this->db->field_exists('usu_credit', 'cab_usuarios'))) {
            log_message('error', 'BTCPay: order ' . (string)($order['mov_id'] ?? '') . ' cannot be credited because the user or usu_credit field is invalid.');
            return FALSE;
        }

        $usuario = $this->db
            ->select('usu_id')
            ->where('usu_id', $id_usuario)
            ->get('cab_usuarios')
            ->row_array();
        if (empty($usuario)) {
            log_message('error', 'BTCPay: user ' . $id_usuario . ' does not exist for order ' . (string)$order['mov_id'] . '.');
            return FALSE;
        }

        $this->db->trans_start();

        // 1. Update cab_movplanes. Only pending orders can be settled; already
        //    approved, cancelled, expired or rejected orders are not overwritten.
        $this->db
            ->where('mov_id', (string)$order['mov_id'])
            // Legacy BTCPay orders may still carry the historical 'V' status.
            ->where_in('mov_status', ['A', 'V'])
            ->where_in('mov_statuspag', ['NoPagado', 'waiting', 'in_process', 'in_process_partial'])
            ->update($this->tabla_ordenes, [
                'mov_statuspag' => 'approved',
                'mov_status'    => 'A',
                'mov_fechpago'  => date('Y-m-d'),
                'mov_horapag'   => date('H:i:s'),
                'mov_fechvenc'  => date('Y-m-d'),
                'mov_idpago'    => (string)$invoice_id,
                'mov_refid'     => (string)$invoice_id,
                'mov_tippago'   => 'BTC_Pay',
                'mov_coin'      => (string)$coin,
            ]);

        $filas_actualizadas = (int)$this->db->affected_rows();

        // 2. Apply the order's effect ONLY if this request won the status
        //    transition. This makes webhook retries and concurrent deliveries
        //    idempotent.
        if ($filas_actualizadas === 1) {
            if ($es_plan) {
                if (!$this->_crear_lote_plan((string)$order['mov_id'])) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            } else {
                $this->db
                    ->set('usu_credit', 'COALESCE(usu_credit, 0) + ' . $sms_credits, FALSE)
                    ->where('usu_id', $id_usuario)
                    ->update('cab_usuarios');

                if ($this->db->affected_rows() !== 1) {
                    log_message('error', 'BTCPay: unable to credit user ' . $id_usuario . ' for order ' . (string)$order['mov_id'] . '.');

                    // Defensive compensation only for pre-migration MyISAM data;
                    // on InnoDB the transaction rollback below reverts the approval.
                    if (!$this->_liquidacion_es_innodb()) {
                        $this->db
                            ->where('mov_id', (string)$order['mov_id'])
                            ->where('mov_idpago', (string)$invoice_id)
                            ->where('mov_statuspag', 'approved')
                            ->update($this->tabla_ordenes, [
                                'mov_statuspag' => 'in_process',
                                'mov_fechpago'  => NULL,
                                'mov_horapag'   => NULL,
                            ]);

                        if ($this->db->affected_rows() !== 1) {
                            log_message('error', 'BTCPay: CRITICAL unable to revert order ' . (string)$order['mov_id'] . ' after a credit failure.');
                        }
                    }

                    $this->db->trans_rollback();
                    return FALSE;
                }
            }
        }

        $completed = $this->db->trans_complete();

        if ($completed === FALSE || $this->db->trans_status() === FALSE) {
            log_message('error', 'BTCPay: unable to commit settlement for order ' . (string)$order['mov_id'] . '.');
            return FALSE;
        }

        return $filas_actualizadas;
    }

    /**
     * Updates order status to processing or partial payment.
     *
     * @param  string $mov_id
     * @param  string $invoice_id
     * @param  string $status
     * @param  string $coin
     * @return bool
     */
    public function actualizar_estado_pago($mov_id, $invoice_id, $status = 'in_process', $coin = '')
    {
        $data = [
            'mov_statuspag' => $status,
            'mov_idpago'    => (string)$invoice_id,
            'mov_refid'     => (string)$invoice_id,
            'mov_tippago'   => 'BTCPay',
        ];

        if (!empty($coin)) {
            $data['mov_coin'] = $coin;
        }

        $ok = $this->db
            ->where('mov_id', (string)$mov_id)
            ->where_in('mov_status', ['A', 'V'])
            ->where_in('mov_statuspag', ['NoPagado', 'waiting', 'in_process', 'in_process_partial'])
            ->group_start()
                ->where('mov_idpago', (string)$invoice_id)
                ->or_where('mov_idpago', '')
                ->or_where('mov_idpago IS NULL', NULL, FALSE)
            ->group_end()
            ->update($this->tabla_ordenes, $data);

        if (!$ok) {
            return FALSE;
        }

        if ($this->db->affected_rows() > 0) {
            return TRUE;
        }

        // Duplicate BTCPay events often target a state that is already stored.
        // Treat an exact no-op as success; a different state/invoice remains a
        // conflict.
        $fresh = $this->db
            ->select('mov_status, mov_statuspag, mov_idpago, mov_tippago')
            ->where('mov_id', (string)$mov_id)
            ->limit(1)
            ->get($this->tabla_ordenes)
            ->row_array();

        return !empty($fresh)
            && in_array((string)$fresh['mov_status'], ['A', 'V'], TRUE)
            && (string)$fresh['mov_statuspag'] === (string)$status
            && (string)$fresh['mov_idpago'] === (string)$invoice_id
            && strcasecmp((string)$fresh['mov_tippago'], 'BTCPay') === 0;
    }

    /**
     * Checks whether a table uses InnoDB so compensation is only applied to
     * deployments that have not run the engine migration.
     *
     * @param  string $tabla
     * @return bool
     */
    private function _tabla_es_innodb($tabla)
    {
        $query = $this->db->query(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [(string)$tabla]
        );

        if (!is_object($query)) {
            return FALSE;
        }

        $row = $query->row_array();

        return !empty($row['ENGINE']) && strcasecmp((string)$row['ENGINE'], 'InnoDB') === 0;
    }

    /**
     * A settlement is atomic only when both the order and the user balance
     * table use InnoDB. A partial migration must keep the defensive revert.
     *
     * @return bool
     */
    private function _liquidacion_es_innodb()
    {
        return $this->_tabla_es_innodb($this->tabla_ordenes)
            && $this->_tabla_es_innodb('cab_usuarios');
    }

    /**
     * Cancels an unpaid order.
     *
     * Si la orden corresponde a un plan de rifa, marca también la compra como
     * cancelada cuando todavía no generó lote.
     *
     * @param  string $mov_id
     * @param  string $id_usuario
     * @return bool
     */
    public function cancelar_orden($mov_id, $id_usuario)
    {
        $this->db->trans_start();

        $ok = $this->db
            ->where('mov_id', (string)$mov_id)
            ->where('mov_idusuario', (string)$id_usuario)
            ->where_in('mov_status', ['A', 'V'])
            ->where_in('mov_statuspag', ['NoPagado', 'waiting'])
            ->update($this->tabla_ordenes, [
                'mov_statuspag' => 'cancelled',
            ]);

        if (!$ok || $this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return FALSE;
        }

        if ($this->_es_compra_plan((string)$mov_id)) {
            if (!$this->Plan_compra_model->marcar_cancelada((string)$mov_id)) {
                log_message('error', 'BuyCredits: order ' . (string)$mov_id . ' cancelled but rif_plan_compras could not be marked.');
                $this->db->trans_rollback();
                return FALSE;
            }
        }

        $completed = $this->db->trans_complete();

        return $completed !== FALSE && $this->db->trans_status() !== FALSE;
    }

    /**
     * Indica si una orden tiene una compra de plan de rifa asociada.
     *
     * @param  string $mov_id
     * @return bool
     */
    private function _es_compra_plan($mov_id)
    {
        if (!$this->db->table_exists('rif_plan_compras')) {
            return FALSE;
        }

        return (int)$this->db
            ->where('compra_mov_id', (string)$mov_id)
            ->count_all_results('rif_plan_compras') > 0;
    }

    /**
     * Respaldo de tipo para no degradar una orden de plan a créditos si falta
     * su fila en rif_plan_compras. La oferta se conserva con borrado lógico.
     *
     * @param  array $order
     * @return bool
     */
    private function _orden_es_plan(array $order)
    {
        $plan_id = trim((string)($order['mov_idplan'] ?? ''));
        if ($plan_id === '') {
            return FALSE;
        }

        $plan = $this->db
            ->select('pla_tipo')
            ->where('pla_id', $plan_id)
            ->limit(1)
            ->get($this->tabla_planes)
            ->row_array();

        return !empty($plan) && (string)($plan['pla_tipo'] ?? '') === 'plan_rifa';
    }

    /**
     * Crea el lote de plan dentro de la transacción de settlement.
     *
     * @param  string $mov_id
     * @return bool
     */
    private function _crear_lote_plan($mov_id)
    {
        $resultado = $this->Plan_compra_model->crear_lote_para_orden((string)$mov_id);

        if (!is_array($resultado) || empty($resultado['ok']) || !empty($resultado['no_plan'])) {
            log_message('error', 'BuyCredits: unable to create the plan assignment for order ' . (string)$mov_id . '.');
            return FALSE;
        }

        return TRUE;
    }
}

