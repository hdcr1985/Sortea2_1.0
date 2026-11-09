<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Order_history_model
 *
 * Read-only transaction history over cab_movplanes. A NULL user id means the
 * caller is an administrator and can see every order.
 */
class Order_history_model extends CI_Model
{
    protected $tabla_ordenes = 'cab_movplanes';
    protected $tabla_planes  = 'cab_planes';

    protected $columnas_ordenables = [
        0 => 'm.mov_id',
        1 => 'm.mov_fechcomp',
        2 => 'm.mov_nomplan',
        3 => 'm.mov_sms',
        4 => 'm.mov_precioplan',
        5 => 'm.mov_statuspag',
        6 => 'm.mov_tippago',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Total orders without search/status filters.
     *
     * @param  string|null $id_usuario
     * @return int
     */
    public function count_todos($id_usuario = NULL)
    {
        $this->_query($id_usuario);
        return $this->db->count_all_results();
    }

    /**
     * Filtered total for DataTables.
     *
     * @param  string|null $id_usuario
     * @param  string      $search
     * @param  string      $estado
     * @return int
     */
    public function count_filtrados($id_usuario = NULL, $search = '', $estado = '')
    {
        $this->_query($id_usuario, $search, $estado);
        return $this->db->count_all_results();
    }

    /**
     * Paginated rows for DataTables.
     *
     * @param  string|null $id_usuario
     * @param  int         $start
     * @param  int         $length
     * @param  string      $search
     * @param  int         $order_col
     * @param  string      $order_dir
     * @param  string      $estado
     * @return array
     */
    public function get_datatable($id_usuario = NULL, $start = 0, $length = 10, $search = '', $order_col = 1, $order_dir = 'desc', $estado = '')
    {
        $this->_query($id_usuario, $search, $estado);

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
     * Single order, optionally restricted to its owner.
     *
     * @param  string      $mov_id
     * @param  string|null $id_usuario
     * @return array|null
     */
    public function get_por_id($mov_id, $id_usuario = NULL)
    {
        $this->db
            ->select('m.*, p.pla_descripcion, p.pla_paypal, p.pla_crypto, p.pla_status, p.pla_eliminado')
            ->from($this->tabla_ordenes . ' m')
            ->join($this->tabla_planes . ' p', 'p.pla_id = m.mov_idplan', 'left')
            ->where('m.mov_id', (string)$mov_id);

        if ($id_usuario !== NULL) {
            $this->db->where('m.mov_idusuario', (string)$id_usuario);
        }

        return $this->db->limit(1)->get()->row_array();
    }

    /**
     * Minimal buyer data for the administrator detail view.
     *
     * @param  string $usu_id
     * @return array|null
     */
    public function get_usuario($usu_id)
    {
        return $this->db
            ->select('usu_id, usu_nombre, usu_apellido, usu_correo, usu_usuario, usu_credit')
            ->where('usu_id', (string)$usu_id)
            ->limit(1)
            ->get('cab_usuarios')
            ->row_array();
    }

    /**
     * Latest processor record for an order, without exposing raw references.
     *
     * @param  array $order
     * @return array|null
     */
    public function get_detalle_pasarela(array $order)
    {
        $order_id = (string)($order['mov_id'] ?? '');
        $gateway  = strtolower(trim((string)($order['mov_tippago'] ?? '')));
        $session  = trim((string)($order['mov_stripe_session_id'] ?? ''));

        if (($gateway === 'stripe' || $session !== '') && $this->db->table_exists('cab_mov_stripe')) {
            return $this->db
                ->select('payment_status, status, amount_total, currency, created_at, updated_at')
                ->where('order_id', $order_id)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get('cab_mov_stripe')
                ->row_array();
        }

        if ($this->db->table_exists('cab_mov_btcpay')) {
            return $this->db
                ->select('status, amountPaid, paymentMethods, price, currency, date, time')
                ->where('orderId', $order_id)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get('cab_mov_btcpay')
                ->row_array();
        }

        return NULL;
    }

    /**
     * Base query shared by count and data methods.
     *
     * @param  string|null $id_usuario
     * @param  string      $search
     * @param  string      $estado
     * @return void
     */
    private function _query($id_usuario = NULL, $search = '', $estado = '')
    {
        $this->db
            ->select('m.*, p.pla_descripcion, p.pla_paypal, p.pla_crypto, p.pla_status, p.pla_eliminado')
            ->from($this->tabla_ordenes . ' m')
            ->join($this->tabla_planes . ' p', 'p.pla_id = m.mov_idplan', 'left');

        if ($id_usuario !== NULL) {
            $this->db->where('m.mov_idusuario', (string)$id_usuario);
        }

        $estados = [
            'unpaid'     => ['NoPagado', 'nopagado'],
            'waiting'    => ['waiting'],
            'processing' => ['in_process', 'in_process_partial'],
            'paid'       => ['approved'],
            'refunded'   => ['refunded'],
            'expired'    => ['expired', 'Expired'],
            'cancelled'  => ['cancelled'],
            'rejected'   => ['rejected'],
        ];

        $estado = strtolower(trim((string)$estado));
        if (isset($estados[$estado])) {
            $this->db->where_in('m.mov_statuspag', $estados[$estado]);
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start()
                ->like('m.mov_id', $search)
                ->or_like('m.mov_nomplan', $search)
                ->or_like('m.mov_statuspag', $search)
                ->or_like('m.mov_tippago', $search)
                ->or_like('m.mov_coin', $search)
                ->or_like('m.mov_fechcomp', $search)
                ->or_like('m.mov_fechpago', $search)
                ->or_like('p.pla_descripcion', $search)
                ->group_end();
        }
    }
}
