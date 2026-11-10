<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Orden_model
 * ─────────────────────────────────────────────────────────────
 * M07: órdenes del checkout público.
 *
 * - Crea la orden y reserva los tickets en una sola transacción.
 * - Manual: el comprador elige números; Automática: el sistema asigna
 *   números aleatorios disponibles.
 * - Aprobar convierte reservas en pagadas; rechazar/expirar las libera.
 * - Guarda un snapshot del método de pago y sus valores.
 */
class Orden_model extends CI_Model
{
    protected $table        = 'rif_ordenes';
    protected $table_rifas  = 'rif_rifas';
    protected $table_tickets = 'rif_tickets';
    protected $table_hist   = 'rif_ticket_historial';
    protected $table_orgmet = 'rif_org_metodos';
    protected $table_met    = 'rif_metodos_pago';
    protected $table_campos = 'rif_metodo_campos';
    protected $table_val    = 'rif_org_metodo_valores';
    protected $table_rm     = 'rif_rifa_metodos';
    protected $max_auto     = 100000;

    public function __construct()
    {
        parent::__construct();
    }

    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists($this->table_tickets)
            && $this->db->table_exists($this->table_hist);
    }

    /**
     * Crea una orden y reserva sus tickets de forma atómica.
     *
     * @param  int    $rifa_id
     * @param  int    $org_id
     * @param  array  $comprador
     * @param  int    $orgmet_id
     * @param  string $numeros_input
     * @param  int    $cantidad
     * @param  string $ip
     * @param  string $ua
     * @return array
     */
    public function crear($rifa_id, $org_id, array $comprador, $orgmet_id, $numeros_input = '', $cantidad = 0, $ip = '', $ua = '')
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible'];
        }

        $comprador = $this->_normalizar_comprador($comprador);
        if (!$this->_comprador_valido($comprador)) {
            return ['ok' => FALSE, 'motivo' => 'comprador_invalido'];
        }

        // Validación y rate-limit ANTES de retener el lock de la rifa.
        $lectura = $this->_leer_rifa((int)$rifa_id, (int)$org_id);
        if (empty($lectura)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        if ((string)$lectura['rifa_estado'] !== 'activa') {
            return ['ok' => FALSE, 'motivo' => 'no_activa'];
        }

        if ((string)$lectura['rifa_tipo'] !== 'paga') {
            return ['ok' => FALSE, 'motivo' => 'solo_pagas'];
        }

        $fecha = trim((string)($lectura['rifa_fecha_sorteo'] ?? ''));
        if ($fecha !== '' && strtotime($fecha) !== FALSE && strtotime($fecha) <= time()) {
            return ['ok' => FALSE, 'motivo' => 'rifa_cerrada'];
        }

        $max = max(1, (int)$lectura['rifa_max_por_compra']);
        $modo = (string)$lectura['rifa_modo_asignacion'];
        $ints = [];
        $padded = [];

        if ($modo === 'manual') {
            $parsed = $this->_parse_numeros($lectura, $numeros_input);
            if (!$parsed['ok']) {
                return $parsed;
            }

            $ints = $parsed['ints'];
            $padded = $parsed['padded'];
            $cantidad = count($ints);
        } else {
            $cantidad = (int)$cantidad;
            if ($cantidad <= 0) {
                return ['ok' => FALSE, 'motivo' => 'cantidad_invalida'];
            }
        }

        if ($cantidad > $max) {
            return ['ok' => FALSE, 'motivo' => 'max_compra', 'max' => $max];
        }

        $metodo = $this->_metodo_rifa((int)$org_id, (int)$orgmet_id, (int)$rifa_id);
        if (empty($metodo)) {
            return ['ok' => FALSE, 'motivo' => 'metodo_invalido'];
        }

        $limite = $this->_rate_limit((int)$rifa_id, (string)$ip);
        if (!$limite['ok']) {
            return $limite;
        }

        $limite_inventario = $this->_limite_pendientes($lectura, (string)$ip, $cantidad);
        if (!$limite_inventario['ok']) {
            return $limite_inventario;
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa((int)$rifa_id, (int)$org_id);
            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
            }

            if ((string)$rifa['rifa_estado'] !== 'activa') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_activa'];
            }

            if ((string)$rifa['rifa_tipo'] !== 'paga') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'solo_pagas'];
            }

            $fecha = trim((string)($rifa['rifa_fecha_sorteo'] ?? ''));
            if ($fecha !== '' && strtotime($fecha) !== FALSE && strtotime($fecha) <= time()) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'rifa_cerrada'];
            }

            if (!$this->_misma_numeracion($lectura, $rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
            }

            $max = max(1, (int)$rifa['rifa_max_por_compra']);
            if ($cantidad > $max) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'max_compra', 'max' => $max];
            }

            $metodo = $this->_metodo_rifa((int)$org_id, (int)$orgmet_id, (int)$rifa_id);
            if (empty($metodo)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'metodo_invalido'];
            }

            // Revalidar topes bajo lock (global 80% y 10 tickets por IP).
            $limite_inventario = $this->_limite_pendientes($rifa, (string)$ip, $cantidad);
            if (!$limite_inventario['ok']) {
                $this->db->trans_rollback();
                return $limite_inventario;
            }

            $precio = round((float)$rifa['rifa_precio'], 2);
            $moneda = (string)$rifa['rifa_moneda'];
            $total  = round($precio * $cantidad, 2);
            $snapshot = $this->_snapshot_metodo($metodo);
            $ahora  = date('Y-m-d H:i:s');

            $auto_reserva = (int)$rifa['rifa_reserva_auto'] === 1;
            $ttl = (int)$rifa['rifa_reserva_ttl_min'];

            // Si la auto-expiración está activa, el checkout público exige
            // una ventana finita. Con TTL 0 se usa 60 minutos de seguridad.
            if ($auto_reserva) {
                if ($ttl <= 0) {
                    $ttl = 60;
                }
                $expira = date('Y-m-d H:i:s', strtotime('+' . $ttl . ' minutes'));
            } else {
                $expira = NULL;
            }

            $codigo = $this->_codigo_unico();
            if ($codigo === NULL) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $orden = [
                'ord_rifa_id'          => (int)$rifa_id,
                'ord_org_id'           => (int)$org_id,
                'ord_codigo'           => $codigo,
                'ord_comprador_nombre' => $comprador['nombre'],
                'ord_comprador_email'  => $comprador['email'],
                'ord_comprador_telefono' => $comprador['telefono'] ?: NULL,
                'ord_comprador_documento' => $comprador['documento'] ?: NULL,
                'ord_cantidad'         => (int)$cantidad,
                'ord_precio_unitario'  => $precio,
                'ord_total'            => $total,
                'ord_moneda'           => $moneda,
                'ord_orgmet_id'        => (int)$orgmet_id,
                'ord_metodo_snapshot'  => $snapshot,
                'ord_estado'           => 'pendiente',
                'ord_notas_comprador'  => $comprador['notas'] ?: NULL,
                'ord_reserva_expira_at' => $expira,
                'ord_ip'               => $ip !== '' ? $this->_limitar($ip, 45) : NULL,
                'ord_user_agent'       => $ua !== '' ? $this->_limitar($ua, 255) : NULL,
                'ord_created_at'       => $ahora,
                'ord_updated_at'       => $ahora,
            ];

            $this->db->insert($this->table, $orden);
            $orden_id = (int)$this->db->insert_id();

            if ($orden_id <= 0) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            if ($modo === 'manual') {
                $reserva = $this->_reservar_manual($rifa, $orden_id, $ints, $padded, $comprador['nombre'], $precio, $moneda, $ahora);
            } else {
                $reserva = $this->_reservar_auto($rifa, $orden_id, $cantidad, $comprador['nombre'], $precio, $moneda, $ahora);
            }

            if (!$reserva['ok']) {
                $this->db->trans_rollback();
                return $reserva;
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return [
                'ok'      => TRUE,
                'orden'   => $this->get_por_id($orden_id, (int)$org_id),
                'tickets' => $reserva['numeros'],
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Orden_model::crear no pudo crear la orden.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    public function get_por_codigo($codigo, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $this->db
            ->select('o.*, r.rifa_titulo, r.rifa_slug, r.rifa_fecha_sorteo, r.rifa_imagen, g.org_nombre_publico, g.org_slug')
            ->from($this->table . ' o')
            ->join($this->table_rifas . ' r', 'r.rifa_id = o.ord_rifa_id', 'inner')
            ->join('rif_organizadores g', 'g.org_id = o.ord_org_id', 'inner')
            ->where('o.ord_codigo', (string)$codigo);

        if ($org_id !== NULL) {
            $this->db->where('o.ord_org_id', (int)$org_id);
        }

        $orden = $this->db->limit(1)->get()->row_array();
        if (!empty($orden)) {
            $orden['tickets'] = $this->get_tickets((int)$orden['ord_id']);
        }

        return $orden;
    }

    public function get_por_id($id, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $this->db
            ->select('o.*, r.rifa_titulo, r.rifa_slug, r.rifa_fecha_sorteo, r.rifa_imagen, g.org_nombre_publico, g.org_slug')
            ->from($this->table . ' o')
            ->join($this->table_rifas . ' r', 'r.rifa_id = o.ord_rifa_id', 'inner')
            ->join('rif_organizadores g', 'g.org_id = o.ord_org_id', 'inner')
            ->where('o.ord_id', (int)$id);

        if ($org_id !== NULL) {
            $this->db->where('o.ord_org_id', (int)$org_id);
        }

        $orden = $this->db->limit(1)->get()->row_array();
        if (!empty($orden)) {
            $orden['tickets'] = $this->get_tickets((int)$orden['ord_id']);
        }

        return $orden;
    }

    public function get_por_org($org_id, $limit = 200)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $limit = max(1, min(500, (int)$limit));

        $rows = $this->db
            ->select('o.*, r.rifa_titulo, r.rifa_slug')
            ->from($this->table . ' o')
            ->join($this->table_rifas . ' r', 'r.rifa_id = o.ord_rifa_id', 'inner')
            ->where('o.ord_org_id', (int)$org_id)
            ->order_by('o.ord_created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['tickets'] = $this->get_tickets((int)$row['ord_id']);
        }
        unset($row);

        return $rows;
    }

    public function get_tickets($orden_id)
    {
        if (!$this->db->table_exists($this->table_tickets)) {
            return [];
        }

        return $this->db
            ->select('tick_id, tick_numero, tick_numero_int, tick_estado, tick_precio, tick_moneda, tick_comprador_nombre')
            ->where('tick_orden_id', (int)$orden_id)
            ->order_by('tick_numero_int', 'ASC')
            ->get($this->table_tickets)
            ->result_array();
    }

    public function aprobar($orden_id, $org_id)
    {
        $this->db->trans_begin();

        try {
            $orden = $this->_lock_orden((int)$orden_id, (int)$org_id);
            if (empty($orden)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
            }

            if (!in_array((string)$orden['ord_estado'], ['pendiente', 'en_revision'], TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido'];
            }

            $ahora = date('Y-m-d H:i:s');
            $tickets = $this->get_tickets((int)$orden_id);

            if (empty($tickets)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'sin_tickets'];
            }

            $this->db
                ->where('tick_orden_id', (int)$orden_id)
                ->where('tick_estado', 'reservado')
                ->update($this->table_tickets, [
                    'tick_estado'     => 'pagado',
                    'tick_pagado_at'  => $ahora,
                    'tick_updated_at' => $ahora,
                ]);

            $this->db->where('ord_id', (int)$orden_id)->update($this->table, [
                'ord_estado'     => 'pagada',
                'ord_pagada_at'  => $ahora,
                'ord_updated_at' => $ahora,
            ]);

            $this->_historial_numeros($orden_id, $tickets, 'pagado', (string)$orden['ord_comprador_nombre'], 'Pago aprobado');

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'orden' => $this->get_por_id((int)$orden_id, (int)$org_id)];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Orden_model::aprobar no pudo aprobar la orden.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    public function rechazar($orden_id, $org_id, $motivo = '')
    {
        $this->db->trans_begin();

        try {
            $orden = $this->_lock_orden((int)$orden_id, (int)$org_id);
            if (empty($orden)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
            }

            if (!in_array((string)$orden['ord_estado'], ['pendiente', 'en_revision'], TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido'];
            }

            $ahora = date('Y-m-d H:i:s');
            $tickets = $this->get_tickets((int)$orden_id);

            foreach ($tickets as $ticket) {
                if ((string)$ticket['tick_estado'] === 'pagado') {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'estado_invalido'];
                }
            }

            $this->_historial_numeros((int)$orden_id, $tickets, 'rechazado', (string)$orden['ord_comprador_nombre'], $motivo);

            $this->db->where('tick_orden_id', (int)$orden_id)->delete($this->table_tickets);

            $this->db->where('ord_id', (int)$orden_id)->update($this->table, [
                'ord_estado'         => 'rechazada',
                'ord_motivo_rechazo' => $motivo !== '' ? $this->_limitar($motivo, 255) : NULL,
                'ord_rechazada_at'   => $ahora,
                'ord_updated_at'     => $ahora,
            ]);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'orden' => $this->get_por_id((int)$orden_id, (int)$org_id)];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Orden_model::rechazar no pudo rechazar la orden.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    public function expirar_vencidas($rifa_id = NULL, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->db->select('o.ord_id')
            ->from($this->table . ' o')
            ->join($this->table_rifas . ' r', 'r.rifa_id = o.ord_rifa_id', 'inner')
            ->where_in('o.ord_estado', ['pendiente', 'en_revision'])
            ->where('o.ord_reserva_expira_at <=', date('Y-m-d H:i:s'))
            ->where('r.rifa_reserva_auto', 1);

        if ($rifa_id !== NULL) {
            $this->db->where('o.ord_rifa_id', (int)$rifa_id);
        }

        if ($org_id !== NULL) {
            $this->db->where('o.ord_org_id', (int)$org_id);
        }

        $ordenes = $this->db->get()->result_array();
        $total = 0;

        foreach ($ordenes as $orden) {
            if ($this->_expirar_una((int)$orden['ord_id'])) {
                $total++;
            }
        }

        return $total;
    }

    private function _expirar_una($orden_id)
    {
        $this->db->trans_begin();

        try {
            $orden = $this->_lock_orden($orden_id, NULL);
            if (empty($orden) || !in_array((string)$orden['ord_estado'], ['pendiente', 'en_revision'], TRUE)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $ahora = date('Y-m-d H:i:s');
            $tickets = $this->get_tickets($orden_id);

            foreach ($tickets as $ticket) {
                if ((string)$ticket['tick_estado'] === 'pagado') {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            }

            $this->_historial_numeros($orden_id, $tickets, 'expirado', (string)$orden['ord_comprador_nombre'], 'Reserva expirada');
            $this->db->where('tick_orden_id', (int)$orden_id)->delete($this->table_tickets);
            $this->db->where('ord_id', (int)$orden_id)->update($this->table, [
                'ord_estado'      => 'expirada',
                'ord_expirada_at' => $ahora,
                'ord_updated_at'  => $ahora,
            ]);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return FALSE;
        }
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────

    /**
     * Límites anti-abuso del checkout público.
     *
     * @param  int    $rifa_id
     * @param  string $email
     * @param  string $ip
     * @return array
     */
    private function _rate_limit($rifa_id, $ip)
    {
        $hace15 = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        if ((string)$ip === '') {
            return ['ok' => TRUE];
        }

        $intentos_ip = (int)$this->db
            ->from($this->table)
            ->where('ord_rifa_id', (int)$rifa_id)
            ->where('ord_ip', (string)$ip)
            ->where('ord_created_at >=', $hace15)
            ->count_all_results();

        if ($intentos_ip >= 20) {
            return [
                'ok'    => FALSE,
                'motivo' => 'rate_limit',
                'retry' => 300,
                'msg'   => 'Demasiados intentos de compra. Espera unos minutos.',
            ];
        }

        return ['ok' => TRUE];
    }

    /**
     * Tope de tickets pendientes: por IP y global como red de seguridad.
     *
     * @param  array  $rifa
     * @param  string $ip
     * @param  int    $cantidad
     * @return array
     */
    private function _limite_pendientes(array $rifa, $ip, $cantidad)
    {
        $total = max(1, (int)$rifa['rifa_total_numeros']);
        $tope_global = max(1, (int)floor($total * 0.8));

        $reservados = (int)$this->db
            ->where('tick_rifa_id', (int)$rifa['rifa_id'])
            ->where('tick_estado', 'reservado')
            ->count_all_results($this->table_tickets);

        if (($reservados + (int)$cantidad) > $tope_global) {
            return [
                'ok'    => FALSE,
                'motivo' => 'limite_inventario',
                'retry' => 600,
                'msg'   => 'La rifa alcanzó su límite temporal de reservas pendientes. Intenta más tarde.',
            ];
        }

        if ((string)$ip !== '') {
            $reservados_ip = (int)$this->db
                ->select('t.tick_id')
                ->from($this->table_tickets . ' t')
                ->join($this->table . ' o', 'o.ord_id = t.tick_orden_id', 'inner')
                ->where('t.tick_rifa_id', (int)$rifa['rifa_id'])
                ->where('t.tick_estado', 'reservado')
                ->where_in('o.ord_estado', ['pendiente', 'en_revision'])
                ->where('o.ord_ip', (string)$ip)
                ->count_all_results();

            if (($reservados_ip + (int)$cantidad) > 10) {
                return [
                    'ok'    => FALSE,
                    'motivo' => 'limite_inventario',
                    'retry' => 600,
                    'msg'   => 'Ya tienes demasiadas reservas pendientes para esta rifa. Espera a que el organizador las revise.',
                ];
            }
        }

        return ['ok' => TRUE];
    }

    private function _leer_rifa($rifa_id, $org_id)
    {
        return $this->db
            ->where('rifa_id', (int)$rifa_id)
            ->where('rifa_org_id', (int)$org_id)
            ->where('rifa_eliminado', 0)
            ->limit(1)
            ->get($this->table_rifas)
            ->row_array();
    }

    private function _misma_numeracion(array $a, array $b)
    {
        return (int)$a['rifa_total_numeros'] === (int)$b['rifa_total_numeros']
            && (int)$a['rifa_numero_desde'] === (int)$b['rifa_numero_desde']
            && (int)$a['rifa_numero_hasta'] === (int)$b['rifa_numero_hasta']
            && (int)$a['rifa_padding'] === (int)$b['rifa_padding']
            && (string)$a['rifa_modo_asignacion'] === (string)$b['rifa_modo_asignacion'];
    }

    private function _lock_rifa($rifa_id, $org_id)
    {
        return $this->db->query(
            "SELECT * FROM {$this->table_rifas} WHERE rifa_id = ? AND rifa_org_id = ?"
            . " AND rifa_eliminado = 0 LIMIT 1 FOR UPDATE",
            [(int)$rifa_id, (int)$org_id]
        )->row_array();
    }

    private function _lock_orden($orden_id, $org_id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE ord_id = ?";
        $binds = [(int)$orden_id];

        if ($org_id !== NULL) {
            $sql .= ' AND ord_org_id = ?';
            $binds[] = (int)$org_id;
        }

        $sql .= ' LIMIT 1 FOR UPDATE';

        return $this->db->query($sql, $binds)->row_array();
    }

    private function _metodo_rifa($org_id, $orgmet_id, $rifa_id)
    {
        $metodo = $this->db->query(
            "SELECT om.*, m.met_id, m.met_nombre, m.met_descripcion, m.met_instrucciones, m.met_icono, m.met_imagen"
            . " FROM {$this->table_orgmet} om"
            . " INNER JOIN {$this->table_met} m ON m.met_id = om.orgmet_met_id"
            . " INNER JOIN {$this->table_rm} rm ON rm.rm_orgmet_id = om.orgmet_id AND rm.rm_rifa_id = ?"
            . " WHERE om.orgmet_id = ? AND om.orgmet_org_id = ?"
            . " AND om.orgmet_activo = 1 AND m.met_status = 1 AND m.met_eliminado = 0"
            . " LIMIT 1",
            [(int)$rifa_id, (int)$orgmet_id, (int)$org_id]
        )->row_array();

        if (empty($metodo)) {
            return NULL;
        }

        $campos = $this->db->query(
            "SELECT c.campo_id, c.campo_etiqueta, c.campo_tipo, c.campo_requerido, v.val_valor"
            . " FROM {$this->table_campos} c"
            . " LEFT JOIN {$this->table_val} v ON v.val_campo_id = c.campo_id AND v.val_orgmet_id = ?"
            . " WHERE c.campo_met_id = ? AND c.campo_status = 1"
            . " ORDER BY c.campo_orden ASC, c.campo_id ASC",
            [(int)$orgmet_id, (int)$metodo['met_id']]
        )->result_array();

        foreach ($campos as $campo) {
            if ((int)$campo['campo_requerido'] === 1
                && trim((string)($campo['val_valor'] ?? '')) === '') {
                return NULL;
            }
        }

        $metodo['campos'] = $campos;

        return $metodo;
    }

    private function _snapshot_metodo(array $metodo)
    {
        $campos = [];
        foreach ($metodo['campos'] as $campo) {
            $campos[] = [
                'etiqueta' => (string)$campo['campo_etiqueta'],
                'tipo'     => (string)$campo['campo_tipo'],
                'valor'    => (string)($campo['val_valor'] ?? ''),
            ];
        }

        return json_encode([
            'orgmet_id'    => (int)$metodo['orgmet_id'],
            'nombre'       => (string)$metodo['met_nombre'],
            'descripcion'  => (string)($metodo['met_descripcion'] ?? ''),
            'instrucciones' => (string)($metodo['met_instrucciones'] ?? ''),
            'icono'        => (string)($metodo['met_icono'] ?? 'ki-bill'),
            'imagen'       => !empty($metodo['met_imagen'])
                ? base_url('media/metodo/' . (int)$metodo['met_id'])
                : '',
            'campos'       => $campos,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function _reservar_manual(array $rifa, $orden_id, array $ints, array $padded, $comprador, $precio, $moneda, $ahora)
    {
        if (empty($ints)) {
            return ['ok' => FALSE, 'motivo' => 'cantidad_invalida'];
        }

        $filas = $this->db
            ->select('tick_numero_int, tick_estado')
            ->where('tick_rifa_id', (int)$rifa['rifa_id'])
            ->where_in('tick_numero_int', $ints)
            ->get($this->table_tickets)
            ->result_array();

        if (!empty($filas)) {
            return [
                'ok'          => FALSE,
                'motivo'      => 'numeros_ocupados',
                'conflictos'  => array_map(function ($fila) {
                    return [
                        'numero' => (string)$fila['tick_numero_int'],
                        'estado' => (string)$fila['tick_estado'],
                    ];
                }, $filas),
            ];
        }

        $lote = [];
        foreach ($ints as $indice => $int) {
            $lote[] = [
                'tick_rifa_id'      => (int)$rifa['rifa_id'],
                'tick_numero'       => $padded[$indice],
                'tick_numero_int'   => (int)$int,
                'tick_estado'       => 'reservado',
                'tick_orden_id'     => (int)$orden_id,
                'tick_comprador_nombre' => $comprador,
                'tick_precio'       => $precio,
                'tick_moneda'       => $moneda,
                'tick_reservado_at' => $ahora,
                'tick_created_at'   => $ahora,
                'tick_updated_at'   => $ahora,
            ];
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $ok = $this->db->insert_batch($this->table_tickets, $lote);
            $error = $this->db->error();
        } catch (Throwable $e) {
            $ok = FALSE;
            $error = ['code' => -1];
        } finally {
            $this->db->db_debug = $debug;
        }

        if (!$ok || !empty($error['code']) || $this->db->affected_rows() !== count($lote)) {
            return ['ok' => FALSE, 'motivo' => 'error'];
        }

        $this->_historial_numeros((int)$orden_id, $lote, 'reservado', $comprador, 'Reserva pública');

        return ['ok' => TRUE, 'numeros' => $padded];
    }

    private function _reservar_auto(array $rifa, $orden_id, $cantidad, $comprador, $precio, $moneda, $ahora)
    {
        $total = (int)$rifa['rifa_total_numeros'];
        $desde = (int)$rifa['rifa_numero_desde'];
        $hasta = (int)$rifa['rifa_numero_hasta'];
        $padding = max(1, (int)$rifa['rifa_padding']);

        if ($total <= 0
            || $total > $this->max_auto
            || $hasta < $desde
            || $total !== ($hasta - $desde + 1)) {
            return ['ok' => FALSE, 'motivo' => 'sin_disponibilidad'];
        }

        $ocupados = $this->db
            ->select('tick_numero_int')
            ->where('tick_rifa_id', (int)$rifa['rifa_id'])
            ->get($this->table_tickets)
            ->result_array();

        $set = [];
        foreach ($ocupados as $fila) {
            $set[(int)$fila['tick_numero_int']] = TRUE;
        }

        $disponibles = [];
        for ($n = $desde; $n <= $hasta; $n++) {
            if (!isset($set[$n])) {
                $disponibles[] = $n;
            }
        }

        if (count($disponibles) < $cantidad) {
            return ['ok' => FALSE, 'motivo' => 'sin_disponibilidad'];
        }

        $claves = [];
        $total_disponibles = count($disponibles);
        while (count($claves) < $cantidad) {
            $claves[random_int(0, $total_disponibles - 1)] = TRUE;
        }

        $elegidos = [];
        foreach (array_keys($claves) as $indice) {
            $elegidos[] = $disponibles[$indice];
        }
        sort($elegidos);

        $ints = [];
        $padded = [];
        foreach ($elegidos as $n) {
            $ints[] = $n;
            $padded[] = str_pad((string)$n, $padding, '0', STR_PAD_LEFT);
        }

        return $this->_reservar_manual($rifa, $orden_id, $ints, $padded, $comprador, $precio, $moneda, $ahora);
    }

    private function _parse_numeros(array $rifa, $entrada)
    {
        $total = (int)$rifa['rifa_total_numeros'];
        $desde = (int)$rifa['rifa_numero_desde'];
        $hasta = (int)$rifa['rifa_numero_hasta'];
        $padding = (int)$rifa['rifa_padding'];

        if ($total <= 0 || $hasta < $desde || $padding < 1 || $total !== ($hasta - $desde + 1)) {
            return ['ok' => FALSE, 'motivo' => 'sin_numeracion'];
        }

        if (!is_scalar($entrada)) {
            return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
        }

        $entrada = trim((string)$entrada);
        if ($entrada === '' || strlen($entrada) > 10000) {
            return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
        }

        $tokens = preg_split('/[,;]+/', $entrada);
        if (!is_array($tokens) || count($tokens) > 1000) {
            return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
        }

        $numeros = [];
        $span = 0;

        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d{1,9})\s*-\s*(\d{1,9})$/', $token, $m)) {
                $ini = (int)$m[1];
                $fin = (int)$m[2];

                if ($ini > $fin || $ini < $desde || $fin > $hasta) {
                    return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
                }

                $span += ($fin - $ini + 1);
                if ($span > 20000) {
                    return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
                }

                for ($n = $ini; $n <= $fin; $n++) {
                    $numeros[$n] = TRUE;
                }

                continue;
            }

            if (preg_match('/^\d{1,9}$/', $token)) {
                $n = (int)$token;
                if ($n < $desde || $n > $hasta) {
                    return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
                }
                $numeros[$n] = TRUE;
                continue;
            }

            return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
        }

        if (empty($numeros) || count($numeros) > 10000) {
            return ['ok' => FALSE, 'motivo' => 'numeros_invalidos'];
        }

        ksort($numeros);
        $ints = array_keys($numeros);
        $padded = [];
        foreach ($ints as $n) {
            $padded[] = str_pad((string)$n, $padding, '0', STR_PAD_LEFT);
        }

        return ['ok' => TRUE, 'ints' => $ints, 'padded' => $padded];
    }

    private function _normalizar_comprador(array $comprador)
    {
        return [
            'nombre'    => $this->_limitar(trim((string)($comprador['nombre'] ?? '')), 150),
            'email'     => strtolower($this->_limitar(trim((string)($comprador['email'] ?? '')), 150)),
            'telefono'  => $this->_limitar(trim((string)($comprador['telefono'] ?? '')), 50),
            'documento' => $this->_limitar(trim((string)($comprador['documento'] ?? '')), 50),
            'notas'     => $this->_limitar(trim((string)($comprador['notas'] ?? '')), 500),
        ];
    }

    private function _comprador_valido(array $comprador)
    {
        return $comprador['nombre'] !== ''
            && filter_var($comprador['email'], FILTER_VALIDATE_EMAIL) !== FALSE;
    }

    private function _historial_numeros($orden_id, array $tickets, $accion, $actor = '', $motivo = '')
    {
        if (empty($tickets)) {
            return;
        }

        $filas = [];
        $ahora = date('Y-m-d H:i:s');

        foreach ($tickets as $ticket) {
            $numero = (string)($ticket['tick_numero'] ?? '');
            if ($numero === '') {
                continue;
            }

            $filas[] = [
                'hist_rifa_id' => 0,
                'hist_numero'  => $numero,
                'hist_orden_id' => (int)$orden_id,
                'hist_accion'  => (string)$accion,
                'hist_actor'   => $actor !== '' ? $this->_limitar($actor, 100) : NULL,
                'hist_motivo'  => $motivo !== '' ? $this->_limitar($motivo, 255) : NULL,
                'hist_at'      => $ahora,
            ];
        }

        if (!empty($filas)) {
            // hist_rifa_id se completa desde la orden para no depender del caller.
            $orden = $this->db->select('ord_rifa_id')->where('ord_id', (int)$orden_id)->get($this->table)->row_array();
            $rifa_id = (int)($orden['ord_rifa_id'] ?? 0);
            foreach ($filas as &$fila) {
                $fila['hist_rifa_id'] = $rifa_id;
            }
            unset($fila);
            $this->db->insert_batch($this->table_hist, $filas);
        }
    }

    private function _codigo_unico()
    {
        for ($i = 0; $i < 6; $i++) {
            $codigo = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
            $existe = $this->db->where('ord_codigo', $codigo)->count_all_results($this->table) > 0;
            if (!$existe) {
                return $codigo;
            }
        }

        return NULL;
    }

    private function _limitar($texto, $max)
    {
        return function_exists('mb_substr')
            ? mb_substr((string)$texto, 0, (int)$max, 'UTF-8')
            : substr((string)$texto, 0, (int)$max);
    }
}
