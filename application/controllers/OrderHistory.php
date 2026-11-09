<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * OrderHistory
 *
 * Read-only transaction history. Administrators see every order; other users
 * only see their own orders.
 */
class OrderHistory extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Order_history_model');

        $this->page_title = 'Historial de Transacciones';
        $this->breadcrumb = [
            ['titulo' => 'Inicio',         'url' => base_url('dashboard')],
            ['titulo' => 'Historial de Transacciones', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
        ];
    }

    /**
     * GET /order-history or /OrderHistory/index
     */
    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para ver el historial de transacciones.');

        $this->render('pages/order_history/index', [
            'url_base'   => $this->url_base,
            'es_master'  => $this->_es_administrador_1000(),
        ]);
    }

    /**
     * POST /OrderHistory/tabla (AJAX DataTables server-side)
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para ver el historial de transacciones.');

        $id_usuario = $this->_es_administrador_1000() ? NULL : (string)($this->usuario['usu_id'] ?? '');

        $draw       = max(0, $this->_post_entero('draw'));
        $start      = min(1000000, max(0, $this->_post_entero('start')));
        $length     = $this->_post_entero('length');
        $length     = ($length > 0 && $length <= 100) ? $length : 10;

        $search_data = $this->input->post('search');
        $search      = is_array($search_data)
            ? mb_substr(trim((string)($search_data['value'] ?? '')), 0, 100)
            : '';

        $order_data = $this->input->post('order');
        $order_col  = is_array($order_data) ? (int)($order_data[0]['column'] ?? 1) : 1;
        $order_dir  = is_array($order_data) ? (string)($order_data[0]['dir'] ?? 'desc') : 'desc';

        $estado = strtolower($this->_post_escalar('estado'));
        if (!in_array($estado, ['unpaid', 'waiting', 'processing', 'paid', 'refunded', 'expired', 'cancelled', 'rejected'], TRUE)) {
            $estado = '';
        }

        $records_total    = $this->Order_history_model->count_todos($id_usuario);
        $records_filtered = $this->Order_history_model->count_filtrados($id_usuario, $search, $estado);
        $rows             = $this->Order_history_model->get_datatable(
            $id_usuario,
            $start,
            $length,
            $search,
            $order_col,
            $order_dir,
            $estado
        );

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $records_total,
            'recordsFiltered' => $records_filtered,
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    /**
     * GET /OrderHistory/datos/:id (AJAX detail modal)
     */
    public function datos($id = '')
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para ver esta transacción.');

        $id = trim((string)$id);
        if ($id === '' || !preg_match('/^[A-Za-z0-9_-]{1,20}$/', $id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Transacción no encontrada.'], 404);
        }

        $id_usuario = $this->_es_administrador_1000() ? NULL : (string)($this->usuario['usu_id'] ?? '');
        $orden      = $this->Order_history_model->get_por_id($id, $id_usuario);

        if (empty($orden)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La transacción no existe o no pertenece a tu cuenta.'], 404);
        }

        $processor = $this->Order_history_model->get_detalle_pasarela($orden);
        $estado    = $this->_mapear_estado((string)($orden['mov_statuspag'] ?? ''));
        $metodo    = $this->_mapear_metodo($orden);

        $usuario = NULL;
        if ($this->_es_administrador_1000()) {
            $usuario = $this->Order_history_model->get_usuario((string)($orden['mov_idusuario'] ?? ''));
        }

        $processor_status = $this->_mapear_estado_procesador($processor, $orden);
        $processor_amount = $this->_importe_procesador($processor, $orden);

        $this->json([
            'ok'   => TRUE,
            'data' => [
                'mov_id'               => (string)$orden['mov_id'],
                'plan'                 => (string)($orden['mov_nomplan'] ?? ''),
                'descripcion'          => (string)($orden['pla_descripcion'] ?? ''),
                'creditos'             => number_format((int)($orden['mov_sms'] ?? 0), 0, ',', '.'),
                'monto'                => number_format((float)($orden['mov_precioplan'] ?? 0), 2, ',', '.'),
                'moneda'               => strtoupper(trim((string)($orden['mov_moneda'] ?? 'USD'))),
                'estado_label'         => $estado['label'],
                'estado_badge'         => $estado['badge'],
                'estado_icon'          => $estado['icon'],
                'metodo'               => $metodo,
                'fecha_compra'         => $this->_fecha_hora($orden['mov_fechcomp'] ?? '', $orden['mov_hora'] ?? ''),
                'fecha_pago'           => $this->_fecha_hora($orden['mov_fechpago'] ?? '', $orden['mov_horapag'] ?? ''),
                'fecha_venc'           => $this->_fecha($orden['mov_fechvenc'] ?? ''),
                'processor_status'     => $processor_status,
                'processor_amount'     => $processor_amount,
                'processor_method'     => is_array($processor) ? $metodo : '',
                'usuario_nombre'       => $usuario
                    ? trim((string)($usuario['usu_nombre'] ?? '') . ' ' . (string)($usuario['usu_apellido'] ?? ''))
                    : '',
                'usuario_correo'       => (string)($usuario['usu_correo'] ?? ''),
            ],
        ]);
    }

    /**
     * Formats rows for the DataTable.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_datatable(array $rows)
    {
        $salida = [];

        foreach ($rows as $row) {
            $mov_id   = htmlspecialchars((string)($row['mov_id'] ?? ''), ENT_QUOTES, 'UTF-8');
            $plan     = htmlspecialchars((string)($row['mov_nomplan'] ?? ''), ENT_QUOTES, 'UTF-8');
            $desc     = htmlspecialchars(trim((string)($row['pla_descripcion'] ?? '')), ENT_QUOTES, 'UTF-8');
            $fecha    = $this->_fecha_hora($row['mov_fechcomp'] ?? '', $row['mov_hora'] ?? '');
            $pago     = $this->_fecha_hora($row['mov_fechpago'] ?? '', $row['mov_horapag'] ?? '');
            $creditos = number_format((int)($row['mov_sms'] ?? 0), 0, ',', '.');
            $monto    = number_format((float)($row['mov_precioplan'] ?? 0), 2, ',', '.');
            $moneda   = strtoupper(trim((string)($row['mov_moneda'] ?? 'USD')));
            $estado   = $this->_mapear_estado((string)($row['mov_statuspag'] ?? ''));
            $metodo   = $this->_mapear_metodo($row);

            $badge = '<span class="badge ' . $estado['badge'] . ' fw-bold px-2 py-1 text-nowrap">'
                . '<i class="ki-duotone ' . $estado['icon'] . ' fs-7 ' . $estado['color'] . ' me-1" aria-hidden="true">'
                . '<span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                . htmlspecialchars($estado['label'], ENT_QUOTES, 'UTF-8')
                . '</span>';

            $plan_html = '<div class="d-flex flex-column">'
                . '<span class="fw-bolder text-gray-800 fs-7">' . ($plan !== '' ? $plan : '-') . '</span>'
                . ($desc !== '' ? '<span class="text-muted fs-8 d-block text-truncate mw-200px" title="' . $desc . '">' . $desc . '</span>' : '')
                . '</div>';

            $fecha_html = '<div class="d-flex flex-column">'
                . '<span class="text-gray-800 fw-semibold fs-7 text-nowrap">' . ($fecha !== '' ? htmlspecialchars($fecha) : '-') . '</span>'
                . ($pago !== '' ? '<span class="text-muted fs-8 text-nowrap">Pagada ' . htmlspecialchars($pago) . '</span>' : '')
                . '</div>';

            $salida[] = [
                'orden'    => '<span class="fw-bold text-gray-900 fs-7 text-nowrap">#' . $mov_id . '</span>',
                'fecha'    => $fecha_html,
                'plan'     => $plan_html,
                'creditos' => '<span class="badge badge-light-primary fw-bold px-2 py-1 text-nowrap">'
                    . '<i class="ki-duotone ki-diamond fs-6 text-primary me-1" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>'
                    . $creditos . ' Créditos</span>',
                'monto'    => '<div class="d-flex align-items-baseline justify-content-end gap-1 text-nowrap">'
                    . '<span class="fw-bolder text-gray-900 fs-6">' . $monto . '</span>'
                    . '<span class="badge badge-light-primary fw-bold fs-8">' . htmlspecialchars($moneda) . '</span>'
                    . '</div>',
                'estado'   => '<div class="d-flex flex-column align-items-center gap-1">' . $badge . '</div>',
                'metodo'   => '<span class="text-muted fs-8 text-nowrap">' . htmlspecialchars($metodo) . '</span>',
                'acciones' => '<button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-ver-transaccion" data-id="' . $mov_id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Ver detalles" aria-label="Ver detalles">'
                    . '<i class="ki-duotone ki-eye fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                    . '</button>',
            ];
        }

        return $salida;
    }

    /**
     * @param  string $status
     * @return array
     */
    private function _mapear_estado($status)
    {
        $status = strtolower(trim((string)$status));

        $estados = [
            'nopagado'           => ['label' => 'Impagada',          'badge' => 'badge-light-danger',    'icon' => 'ki-cross-circle', 'color' => 'text-danger'],
            'waiting'            => ['label' => 'Pendiente de Pago', 'badge' => 'badge-light-primary',   'icon' => 'ki-time',         'color' => 'text-primary'],
            'in_process'         => ['label' => 'Procesando',       'badge' => 'badge-light-info',      'icon' => 'ki-loading',      'color' => 'text-info'],
            'in_process_partial' => ['label' => 'Pago Parcial',  'badge' => 'badge-light-info',      'icon' => 'ki-loading',      'color' => 'text-info'],
            'approved'           => ['label' => 'Pagada',             'badge' => 'badge-light-success',   'icon' => 'ki-check-circle', 'color' => 'text-success'],
            'refunded'           => ['label' => 'Reembolsada',         'badge' => 'badge-light-dark',      'icon' => 'ki-information',  'color' => 'text-muted'],
            'expired'            => ['label' => 'Expirada',          'badge' => 'badge-light-warning',   'icon' => 'ki-information',  'color' => 'text-warning'],
            'cancelled'          => ['label' => 'Cancelada',        'badge' => 'badge-light-secondary', 'icon' => 'ki-minus-circle', 'color' => 'text-muted'],
            'rejected'           => ['label' => 'Rechazada',         'badge' => 'badge-light-danger',    'icon' => 'ki-cross-circle', 'color' => 'text-danger'],
        ];

        return $estados[$status] ?? [
            'label' => 'Desconocida',
            'badge' => 'badge-light-dark',
            'icon'  => 'ki-information',
            'color' => 'text-muted',
        ];
    }

    /**
     * @param  array $row
     * @return string
     */
    private function _mapear_metodo(array $row)
    {
        $gateway = strtolower(trim((string)($row['mov_tippago'] ?? '')));
        $coin    = trim((string)($row['mov_coin'] ?? ''));
        $session = trim((string)($row['mov_stripe_session_id'] ?? ''));
        $coin_l  = strtolower($coin);

        if ($gateway === 'paypal-account' || strpos($gateway, 'paypal') !== FALSE || strpos($coin_l, 'paypal') !== FALSE) {
            return 'PayPal';
        }

        if ($gateway === 'stripe' || $session !== '') {
            return 'Tarjeta';
        }

        if (in_array($gateway, ['btcpay', 'btc_pay', 'crypto', 'bitcoin'], TRUE)) {
            return $coin !== '' ? 'Cripto · ' . $coin : 'Cripto';
        }

        if ($gateway !== '' || $coin !== '') {
            return 'Pago en Línea';
        }

        return '—';
    }

    /**
     * @param  array|null $processor
     * @param  array      $order
     * @return string
     */
    private function _mapear_estado_procesador($processor, array $order)
    {
        if (!is_array($processor)) {
            return '—';
        }

        $gateway = strtolower(trim((string)($order['mov_tippago'] ?? '')));
        $stripe  = $gateway === 'stripe' || trim((string)($order['mov_stripe_session_id'] ?? '')) !== '';

        if ($stripe) {
            $payment = strtolower(trim((string)($processor['payment_status'] ?? '')));
            $status  = strtolower(trim((string)($processor['status'] ?? '')));

            if ($payment === 'paid' || $status === 'complete') {
                return 'Completado';
            }
            if ($payment === 'unpaid' || $status === 'open') {
                return 'Pendiente';
            }
            if ($status === 'expired') {
                return 'Expirada';
            }
            if ($payment === 'failed') {
                return 'Fallido';
            }
            return 'Procesando';
        }

        $status = strtolower(trim((string)($processor['status'] ?? '')));
        $map = [
            'settled'    => 'Completado',
            'processing' => 'Procesando',
            'new'        => 'Pendiente',
            'expired'    => 'Expirada',
            'invalid'    => 'Inválido',
        ];

        return $map[$status] ?? '—';
    }

    /**
     * @param  array|null $processor
     * @param  array      $order
     * @return string
     */
    private function _importe_procesador($processor, array $order)
    {
        if (!is_array($processor)) {
            return '—';
        }

        $gateway = strtolower(trim((string)($order['mov_tippago'] ?? '')));
        $stripe  = $gateway === 'stripe' || trim((string)($order['mov_stripe_session_id'] ?? '')) !== '';

        if ($stripe) {
            $minor = (int)($processor['amount_total'] ?? 0);
            if ($minor <= 0) {
                return '—';
            }
            $currency = strtoupper(trim((string)($processor['currency'] ?? $order['mov_moneda'] ?? 'USD')));
            return number_format($minor / 100, 2, ',', '.') . ' ' . $currency;
        }

        $amount = trim((string)($processor['amountPaid'] ?? ''));
        $method = trim((string)($processor['paymentMethods'] ?? ''));

        if ($amount !== '' && $method !== '') {
            return $amount . ' ' . $method;
        }

        $price    = trim((string)($processor['price'] ?? ''));
        $currency = strtoupper(trim((string)($processor['currency'] ?? $order['mov_moneda'] ?? 'USD')));

        return $price !== '' ? $price . ' ' . $currency : '—';
    }

    /**
     * @param  string $date
     * @param  string $time
     * @return string
     */
    private function _fecha_hora($date, $time)
    {
        $date = trim((string)$date);
        $time = trim((string)$time);

        if ($date === '' || $date === '0000-00-00') {
            return '';
        }

        return $date . ($time !== '' && $time !== '00:00:00' ? ' ' . $time : '');
    }

    /**
     * Explicit business rule: only Admin Master role 1000 sees every order.
     * Other roles see only their own purchase history.
     *
     * @return bool
     */
    private function _es_administrador_1000()
    {
        return trim((string)($this->usuario['usu_tipoper'] ?? '')) === '1000';
    }

    /**
     * @param  string $key
     * @param  int    $default
     * @return int
     */
    private function _post_entero($key, $default = 0)
    {
        $value = $this->input->post($key, TRUE);
        return is_scalar($value) ? (int)$value : (int)$default;
    }

    /**
     * @param  string $key
     * @return string
     */
    private function _post_escalar($key)
    {
        $value = $this->input->post($key, TRUE);
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * @param  string $date
     * @return string
     */
    private function _fecha($date)
    {
        $date = trim((string)$date);
        return ($date === '' || $date === '0000-00-00') ? '' : $date;
    }
}
