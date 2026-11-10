<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rifa_ticket_model
 * ─────────────────────────────────────────────────────────────
 * Inventario de números de una rifa (M03.3).
 *
 * Modelo sparse: solo se guardan los números ocupados. Los números sin fila
 * están disponibles y se calculan a partir de la numeración de la rifa.
 *
 * Estados:
 * - reservado: comprador lo eligió y el pago está pendiente/en revisión.
 * - pagado:    el organizador confirmó el pago.
 * - bloqueado: el organizador lo retiró de la venta.
 *
 * Todas las mutaciones bloquean la rifa con FOR UPDATE y usan la UNIQUE
 * (tick_rifa_id, tick_numero) como última barrera contra dobles ventas.
 */
class Rifa_ticket_model extends CI_Model
{
    protected $table         = 'rif_tickets';
    protected $table_hist    = 'rif_ticket_historial';
    protected $table_rifas   = 'rif_rifas';
    protected $max_operacion = 10000;
    protected $max_auto      = 100000;

    protected $estados_operables = ['borrador', 'activa', 'pausada'];
    protected $estados_venta     = ['activa', 'pausada'];

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
            && $this->db->table_exists($this->table_hist)
            && $this->db->table_exists($this->table_rifas);
    }

    /**
     * Cantidad de números ocupados de una rifa.
     *
     * @param  int $rifa_id
     * @return int
     */
    public function contar_por_rifa($rifa_id)
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        return (int)$this->db
            ->where('tick_rifa_id', (int)$rifa_id)
            ->count_all_results($this->table);
    }

    /**
     * Resumen de ocupación de una rifa.
     *
     * @param  int $rifa_id
     * @return array
     */
    public function get_resumen($rifa_id)
    {
        $vacio = [
            'total'        => 0,
            'disponibles'  => 0,
            'ocupados'     => 0,
            'reservados'   => 0,
            'pagados'      => 0,
            'bloqueados'   => 0,
            'modo'         => 'manual',
            'mostrar_reservados' => 1,
        ];

        if (!$this->tabla_disponible()) {
            return $vacio;
        }

        $rifa = $this->db
            ->select('rifa_id, rifa_total_numeros, rifa_numero_desde, rifa_numero_hasta, rifa_padding, rifa_modo_asignacion, rifa_mostrar_reservados')
            ->where('rifa_id', (int)$rifa_id)
            ->where('rifa_eliminado', 0)
            ->limit(1)
            ->get($this->table_rifas)
            ->row_array();

        if (empty($rifa)) {
            return $vacio;
        }

        $filas = $this->db
            ->select('tick_estado, COUNT(*) AS total', FALSE)
            ->where('tick_rifa_id', (int)$rifa_id)
            ->group_by('tick_estado')
            ->get($this->table)
            ->result_array();

        $conteo = ['reservado' => 0, 'pagado' => 0, 'bloqueado' => 0];

        foreach ($filas as $fila) {
            $estado = (string)$fila['tick_estado'];
            if (array_key_exists($estado, $conteo)) {
                $conteo[$estado] = (int)$fila['total'];
            }
        }

        $total   = max(0, (int)$rifa['rifa_total_numeros']);
        $ocupados = array_sum($conteo);

        return [
            'total'              => $total,
            'disponibles'        => max(0, $total - $ocupados),
            'ocupados'           => $ocupados,
            'reservados'         => $conteo['reservado'],
            'pagados'            => $conteo['pagado'],
            'bloqueados'         => $conteo['bloqueado'],
            'modo'               => (string)($rifa['rifa_modo_asignacion'] ?? 'manual'),
            'mostrar_reservados' => (int)($rifa['rifa_mostrar_reservados'] ?? 1),
            'desde'              => (int)($rifa['rifa_numero_desde'] ?? 0),
            'hasta'              => (int)($rifa['rifa_numero_hasta'] ?? 0),
            'padding'            => (int)($rifa['rifa_padding'] ?? 0),
        ];
    }

    /**
     * Mapa paginado de números y estados para el organizador.
     *
     * @param  int $rifa_id
     * @param  int $offset
     * @param  int $limit
     * @return array
     */
    public function get_mapa($rifa_id, $offset = 0, $limit = 200)
    {
        $offset = max(0, (int)$offset);
        $limit  = max(1, min(500, (int)$limit));

        if (!$this->tabla_disponible()) {
            return ['total' => 0, 'offset' => 0, 'limit' => $limit, 'items' => []];
        }

        $rifa = $this->_get_rifa($rifa_id);
        if (empty($rifa)) {
            return ['total' => 0, 'offset' => 0, 'limit' => $limit, 'items' => []];
        }

        $total   = max(0, (int)$rifa['rifa_total_numeros']);
        $desde   = (int)$rifa['rifa_numero_desde'];
        $padding = max(1, (int)$rifa['rifa_padding']);

        if ($total <= 0 || $offset >= $total) {
            return ['total' => $total, 'offset' => $offset, 'limit' => $limit, 'items' => []];
        }

        $fin      = min($total - 1, $offset + $limit - 1);
        $num_ini  = $desde + $offset;
        $num_fin  = $desde + $fin;

        $filas = $this->db
            ->select('tick_numero_int, tick_estado, tick_comprador_nombre, tick_motivo')
            ->where('tick_rifa_id', (int)$rifa_id)
            ->where('tick_numero_int >=', $num_ini)
            ->where('tick_numero_int <=', $num_fin)
            ->get($this->table)
            ->result_array();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int)$fila['tick_numero_int']] = $fila;
        }

        $items = [];
        for ($n = $num_ini; $n <= $num_fin; $n++) {
            $fila = $mapa[$n] ?? NULL;

            $items[] = [
                'numero'           => str_pad((string)$n, $padding, '0', STR_PAD_LEFT),
                'numero_int'       => $n,
                'estado'           => $fila ? (string)$fila['tick_estado'] : 'disponible',
                'comprador_nombre' => $fila ? (string)($fila['tick_comprador_nombre'] ?? '') : '',
                'motivo'           => $fila ? (string)($fila['tick_motivo'] ?? '') : '',
            ];
        }

        return [
            'total'  => $total,
            'offset' => $offset,
            'limit'  => $limit,
            'items'  => $items,
        ];
    }

    /**
     * Rangos bloqueados agrupados por motivo y números consecutivos.
     *
     * @param  int $rifa_id
     * @return array
     */
    public function get_bloqueados_agrupados($rifa_id)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $rifa = $this->_get_rifa($rifa_id);
        if (empty($rifa)) {
            return [];
        }

        $padding = max(1, (int)$rifa['rifa_padding']);

        // Tope de filas para evitar respuestas gigantes. La UI compara este
        // listado con el total del resumen y avisa si quedó truncado.
        $limite = 2000;

        $filas = $this->db
            ->select('tick_numero_int, tick_motivo, tick_created_at')
            ->where('tick_rifa_id', (int)$rifa_id)
            ->where('tick_estado', 'bloqueado')
            ->order_by('tick_numero_int', 'ASC')
            ->limit($limite + 1)
            ->get($this->table)
            ->result_array();

        if (count($filas) > $limite) {
            $filas = array_slice($filas, 0, $limite);
        }

        $rangos = [];
        $actual = NULL;

        foreach ($filas as $fila) {
            $num    = (int)$fila['tick_numero_int'];
            $motivo = (string)($fila['tick_motivo'] ?? '');

            if ($actual !== NULL && $num === $actual['hasta'] + 1 && $motivo === $actual['motivo']) {
                $actual['hasta']    = $num;
                $actual['cantidad']++;
                continue;
            }

            if ($actual !== NULL) {
                $rangos[] = $this->_formatear_rango($actual, $padding);
            }

            $actual = [
                'desde'    => $num,
                'hasta'    => $num,
                'cantidad' => 1,
                'motivo'   => $motivo,
                'creado'   => (string)($fila['tick_created_at'] ?? ''),
            ];
        }

        if ($actual !== NULL) {
            $rangos[] = $this->_formatear_rango($actual, $padding);
        }

        return $rangos;
    }

    /**
     * Bloquea un rango/lista de números. Todo o nada.
     *
     * @param  int    $rifa_id
     * @param  int    $org_id
     * @param  string $entrada
     * @param  string $motivo
     * @param  string $actor
     * @return array
     */
    public function bloquear($rifa_id, $org_id, $entrada, $motivo = '', $actor = '')
    {
        return $this->_operar_numeros($rifa_id, $org_id, $entrada, $motivo, $actor, 'bloquear');
    }

    /**
     * Libera números bloqueados. Todo o nada.
     *
     * @param  int    $rifa_id
     * @param  int    $org_id
     * @param  string $entrada
     * @param  string $motivo
     * @param  string $actor
     * @return array
     */
    public function liberar($rifa_id, $org_id, $entrada, $motivo = '', $actor = '')
    {
        return $this->_operar_numeros($rifa_id, $org_id, $entrada, $motivo, $actor, 'liberar');
    }

    /**
     * Venta presencial: marca números como pagados con comprador manual.
     *
     * @param  int    $rifa_id
     * @param  int    $org_id
     * @param  string $entrada
     * @param  string $comprador
     * @param  string $motivo
     * @param  string $actor
     * @return array
     */
    public function venta_presencial($rifa_id, $org_id, $entrada, $comprador = '', $motivo = '', $actor = '')
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible'];
        }

        $comprador = $this->_limitar_texto(trim((string)$comprador), 150);
        $motivo    = $this->_limitar_texto(trim((string)$motivo), 255);
        $actor     = $this->_limitar_texto(trim((string)$actor), 100);

        $lectura = $this->_get_rifa($rifa_id, $org_id);
        if (empty($lectura)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        $parsed = $this->parse_numeros($lectura, $entrada);
        if (!$parsed['ok']) {
            return ['ok' => FALSE, 'motivo' => $parsed['motivo'], 'error' => $parsed['error']];
        }

        $ints   = $parsed['ints'];
        $padded = $parsed['padded'];

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($rifa_id, $org_id);
            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
            }

            if (!$this->_misma_numeracion($lectura, $rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => (string)$rifa['rifa_estado']];
            }

            if (!in_array((string)$rifa['rifa_estado'], $this->estados_venta, TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => (string)$rifa['rifa_estado']];
            }

            $filas      = $this->_filas_existentes($rifa_id, $padded, $ints);
            $porInt     = [];
            $conflictos = [];

            foreach ($filas as $fila) {
                $porInt[(int)$fila['tick_numero_int']] = $fila;
                if (in_array((string)$fila['tick_estado'], ['reservado', 'pagado'], TRUE)) {
                    $conflictos[] = [
                        'numero' => (string)$fila['tick_numero'],
                        'estado' => (string)$fila['tick_estado'],
                    ];
                }
            }

            if (!empty($conflictos)) {
                $this->db->trans_rollback();
                $cap = $this->_limitar_conflictos($conflictos);
                return [
                    'ok'              => FALSE,
                    'motivo'          => 'conflictos',
                    'conflictos'      => $cap['items'],
                    'conflictos_total' => $cap['total'],
                ];
            }

            $ahora = date('Y-m-d H:i:s');
            $motivo_final = $motivo !== '' ? $motivo : 'Venta presencial';
            $precio = round((float)($rifa['rifa_precio'] ?? 0), 2);
            $moneda = (string)($rifa['rifa_moneda'] ?? 'USD');

            $bloqueados_ints = [];
            $nuevos          = [];
            $hist_numeros    = [];

            foreach ($ints as $indice => $n) {
                $fila = $porInt[$n] ?? NULL;

                if (!empty($fila)) {
                    $hist_numeros[] = (string)$fila['tick_numero'];

                    if ((string)$fila['tick_estado'] === 'bloqueado') {
                        $bloqueados_ints[] = $n;
                    }
                } else {
                    $nuevos[] = [
                        'int'    => $n,
                        'numero' => $padded[$indice],
                    ];
                    $hist_numeros[] = $padded[$indice];
                }
            }

            if (!empty($bloqueados_ints)) {
                $this->db
                    ->where('tick_rifa_id', (int)$rifa_id)
                    ->where('tick_estado', 'bloqueado')
                    ->where_in('tick_numero_int', $bloqueados_ints)
                    ->update($this->table, [
                        'tick_estado'           => 'pagado',
                        'tick_comprador_nombre' => $comprador !== '' ? $comprador : NULL,
                        'tick_precio'           => $precio,
                        'tick_moneda'           => $moneda,
                        'tick_motivo'           => $motivo_final,
                        'tick_pagado_at'        => $ahora,
                        'tick_updated_at'       => $ahora,
                    ]);

                if ($this->db->affected_rows() !== count($bloqueados_ints)) {
                    $this->db->trans_rollback();
                    log_message('error', 'Rifa_ticket_model::venta_presencial no actualizó todos los tickets bloqueados.');
                    return ['ok' => FALSE, 'motivo' => 'error'];
                }
            }

            if (!empty($nuevos)) {
                $lote = [];
                foreach ($nuevos as $nuevo) {
                    $lote[] = [
                        'tick_rifa_id'          => (int)$rifa_id,
                        'tick_numero'           => $nuevo['numero'],
                        'tick_numero_int'       => (int)$nuevo['int'],
                        'tick_estado'           => 'pagado',
                        'tick_comprador_nombre' => $comprador !== '' ? $comprador : NULL,
                        'tick_precio'           => $precio,
                        'tick_moneda'           => $moneda,
                        'tick_motivo'           => $motivo_final,
                        'tick_pagado_at'        => $ahora,
                    ];
                }

                $debug_previo = $this->db->db_debug;
                $this->db->db_debug = FALSE;

                try {
                    $ok    = $this->db->insert_batch($this->table, $lote);
                    $error = $this->db->error();
                } catch (Throwable $e) {
                    $ok    = FALSE;
                    $error = ['code' => -1, 'message' => ''];
                } finally {
                    $this->db->db_debug = $debug_previo;
                }

                if (!$ok || !empty($error['code'])
                    || $this->db->affected_rows() !== count($lote)) {
                    $this->db->trans_rollback();
                    log_message('error', 'Rifa_ticket_model::venta_presencial no pudo insertar todos los tickets (DB code ' . (int)($error['code'] ?? 0) . ').');
                    return ['ok' => FALSE, 'motivo' => 'error'];
                }
            }

            $this->_historial_batch($rifa_id, $hist_numeros, 'venta_presencial', $actor, $motivo_final);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return ['ok' => TRUE, 'cantidad' => count($ints), 'comprador' => $comprador];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_ticket_model::venta_presencial no pudo completar la venta.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    /**
     * Reserva aleatoria de N números disponibles (M07).
     *
     * No expone endpoint en M03.3; queda lista para el checkout.
     *
     * @param  int      $rifa_id
     * @param  int      $cantidad
     * @param  int|null $orden_id
     * @param  int|null $comprador_id
     * @return array
     */
    public function reservar_automatico($rifa_id, $cantidad, $orden_id = NULL, $comprador_id = NULL, $org_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible'];
        }

        $cantidad = (int)$cantidad;
        if ($cantidad <= 0 || $cantidad > $this->max_operacion) {
            return ['ok' => FALSE, 'motivo' => 'cantidad_invalida'];
        }

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($rifa_id, $org_id);
            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
            }

            if ((string)$rifa['rifa_estado'] !== 'activa') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido'];
            }

            if ((string)($rifa['rifa_modo_asignacion'] ?? 'manual') !== 'automatica') {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'modo_invalido'];
            }

            $total = (int)$rifa['rifa_total_numeros'];
            $desde = (int)$rifa['rifa_numero_desde'];
            $hasta = (int)$rifa['rifa_numero_hasta'];
            $padding = max(1, (int)$rifa['rifa_padding']);

            if ($total <= 0 || $hasta < $desde || $total !== ($hasta - $desde + 1)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'sin_numeracion'];
            }

            if ($total > $this->max_auto) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'rango_demasiado_grande'];
            }

            $ocupados = $this->db
                ->select('tick_numero_int')
                ->where('tick_rifa_id', (int)$rifa_id)
                ->get($this->table)
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
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'sin_disponibilidad'];
            }

            // Selección aleatoria sin reemplazo con random_int. Se usan
            // índices únicos para evitar array_splice O(n²) con el lock.
            $total_disponibles = count($disponibles);
            $claves = [];
            while (count($claves) < $cantidad) {
                $claves[random_int(0, $total_disponibles - 1)] = TRUE;
            }

            $elegidos = [];
            foreach (array_keys($claves) as $indice) {
                $elegidos[] = $disponibles[$indice];
            }

            sort($elegidos);

            $ahora  = date('Y-m-d H:i:s');
            $precio = round((float)($rifa['rifa_precio'] ?? 0), 2);
            $moneda = (string)($rifa['rifa_moneda'] ?? 'USD');
            $filas  = [];
            $padded = [];

            foreach ($elegidos as $numero) {
                $texto = str_pad((string)$numero, $padding, '0', STR_PAD_LEFT);
                $padded[] = $texto;
                $filas[] = [
                    'tick_rifa_id'    => (int)$rifa_id,
                    'tick_numero'     => $texto,
                    'tick_numero_int' => $numero,
                    'tick_estado'     => 'reservado',
                    'tick_orden_id'   => $orden_id !== NULL ? (int)$orden_id : NULL,
                    'tick_comprador_id' => $comprador_id !== NULL ? (int)$comprador_id : NULL,
                    'tick_precio'     => $precio,
                    'tick_moneda'     => $moneda,
                    'tick_reservado_at' => $ahora,
                ];
            }

            $debug_previo = $this->db->db_debug;
            $this->db->db_debug = FALSE;

            try {
                $ok    = $this->db->insert_batch($this->table, $filas);
                $error = $this->db->error();
            } catch (Throwable $e) {
                $ok    = FALSE;
                $error = ['code' => -1, 'message' => ''];
            } finally {
                $this->db->db_debug = $debug_previo;
            }

            if (!$ok || !empty($error['code'])
                || $this->db->affected_rows() !== count($filas)) {
                $this->db->trans_rollback();
                log_message('error', 'Rifa_ticket_model::reservar_automatico no pudo insertar todos los tickets (DB code ' . (int)($error['code'] ?? 0) . ').');
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->_historial_batch($rifa_id, $padded, 'reservado', '', 'Asignación aleatoria');

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();

            return ['ok' => TRUE, 'numeros' => $padded];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_ticket_model::reservar_automatico no pudo completar la reserva.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    /**
     * Parsea "10-20, 25, 86" a números validados y con padding.
     *
     * @param  array  $rifa
     * @param  string $entrada
     * @return array
     */
    public function parse_numeros(array $rifa, $entrada)
    {
        $total = (int)($rifa['rifa_total_numeros'] ?? 0);
        $desde = (int)($rifa['rifa_numero_desde'] ?? 0);
        $hasta = (int)($rifa['rifa_numero_hasta'] ?? 0);
        $padding = (int)($rifa['rifa_padding'] ?? 0);

        if ($total <= 0 || $hasta < $desde || $padding < 1 || $total !== ($hasta - $desde + 1)) {
            return ['ok' => FALSE, 'motivo' => 'sin_numeracion', 'error' => 'La rifa todavía no tiene una numeración definida.'];
        }

        if (!is_scalar($entrada)) {
            return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'El rango de números no es válido.'];
        }

        $entrada = trim((string)$entrada);
        if ($entrada === '') {
            return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'Indica al menos un número o rango.'];
        }

        if (strlen($entrada) > 10000) {
            return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'La lista de números es demasiado larga.'];
        }

        $tokens = preg_split('/[,;]+/', $entrada);

        if (!is_array($tokens) || count($tokens) > 1000) {
            return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'La lista contiene demasiados elementos.'];
        }

        $numeros = [];
        $span_total = 0;

        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d{1,9})\s*-\s*(\d{1,9})$/', $token, $m)) {
                $ini = (int)$m[1];
                $fin = (int)$m[2];

                if ($ini > $fin) {
                    return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => "El rango {$token} está invertido."];
                }

                if ($ini < $desde || $fin > $hasta) {
                    return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => "El rango {$token} está fuera de la numeración de la rifa ({$desde}-{$hasta})."];
                }

                $span_total += ($fin - $ini + 1);
                if ($span_total > ($this->max_operacion * 2)) {
                    return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'La lista de números es demasiado grande.'];
                }

                for ($n = $ini; $n <= $fin; $n++) {
                    $numeros[$n] = TRUE;
                    if (count($numeros) > $this->max_operacion) {
                        return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'No puedes operar más de ' . number_format($this->max_operacion, 0, '.', ',') . ' números a la vez.'];
                    }
                }

                continue;
            }

            if (preg_match('/^\d{1,9}$/', $token)) {
                $n = (int)$token;

                if ($n < $desde || $n > $hasta) {
                    return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => "El número {$n} está fuera de la numeración de la rifa ({$desde}-{$hasta})."];
                }

                $span_total++;
                if ($span_total > ($this->max_operacion * 2)) {
                    return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'La lista de números es demasiado grande.'];
                }

                $numeros[$n] = TRUE;
                if (count($numeros) > $this->max_operacion) {
                    return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'No puedes operar más de ' . number_format($this->max_operacion, 0, '.', ',') . ' números a la vez.'];
                }

                continue;
            }

            return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => "El valor \"{$token}\" no es un número ni un rango válido."];
        }

        if (empty($numeros)) {
            return ['ok' => FALSE, 'motivo' => 'invalido', 'error' => 'Indica al menos un número o rango.'];
        }

        ksort($numeros);

        $ints   = array_keys($numeros);
        $padded = [];
        foreach ($ints as $n) {
            $padded[] = str_pad((string)$n, $padding, '0', STR_PAD_LEFT);
        }

        return [
            'ok'      => TRUE,
            'motivo'  => '',
            'error'   => '',
            'ints'    => $ints,
            'padded'  => $padded,
            'cantidad' => count($padded),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Implementación común de bloquear/liberar.
     *
     * @param  int    $rifa_id
     * @param  int    $org_id
     * @param  string $entrada
     * @param  string $motivo
     * @param  string $actor
     * @param  string $accion
     * @return array
     */
    private function _operar_numeros($rifa_id, $org_id, $entrada, $motivo, $actor, $accion)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible'];
        }

        $motivo = $this->_limitar_texto(trim((string)$motivo), 255);
        $actor  = $this->_limitar_texto(trim((string)$actor), 100);

        // Parsear antes de retener el lock de la rifa. Así una entrada
        // maliciosa no bloquea la fila mientras se expanden rangos.
        $lectura = $this->_get_rifa($rifa_id, $org_id);
        if (empty($lectura)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        $parsed = $this->parse_numeros($lectura, $entrada);
        if (!$parsed['ok']) {
            return ['ok' => FALSE, 'motivo' => $parsed['motivo'], 'error' => $parsed['error']];
        }

        $ints   = $parsed['ints'];
        $padded = $parsed['padded'];

        $this->db->trans_begin();

        try {
            $rifa = $this->_lock_rifa($rifa_id, $org_id);
            if (empty($rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
            }

            if (!$this->_misma_numeracion($lectura, $rifa)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => (string)$rifa['rifa_estado']];
            }

            if (!in_array((string)$rifa['rifa_estado'], $this->estados_operables, TRUE)) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'estado_invalido', 'estado' => (string)$rifa['rifa_estado']];
            }

            $filas  = $this->_filas_existentes($rifa_id, $padded, $ints);
            $porInt = [];
            foreach ($filas as $fila) {
                $porInt[(int)$fila['tick_numero_int']] = $fila;
            }

            if ($accion === 'bloquear') {
                $conflictos = [];
                foreach ($filas as $fila) {
                    $conflictos[] = [
                        'numero' => (string)$fila['tick_numero'],
                        'estado' => (string)$fila['tick_estado'],
                    ];
                }

                if (!empty($conflictos)) {
                    $this->db->trans_rollback();
                    $cap = $this->_limitar_conflictos($conflictos);
                    return [
                        'ok'              => FALSE,
                        'motivo'          => 'conflictos',
                        'conflictos'      => $cap['items'],
                        'conflictos_total' => $cap['total'],
                    ];
                }

                $ahora = date('Y-m-d H:i:s');
                $lote  = [];
                foreach ($padded as $indice => $numero) {
                    $lote[] = [
                        'tick_rifa_id'    => (int)$rifa_id,
                        'tick_numero'     => $numero,
                        'tick_numero_int' => (int)$ints[$indice],
                        'tick_estado'     => 'bloqueado',
                        'tick_motivo'     => $motivo !== '' ? $motivo : NULL,
                        'tick_created_at' => $ahora,
                        'tick_updated_at' => $ahora,
                    ];
                }

                $debug_previo = $this->db->db_debug;
                $this->db->db_debug = FALSE;

                try {
                    $ok    = $this->db->insert_batch($this->table, $lote);
                    $error = $this->db->error();
                } catch (Throwable $e) {
                    $ok    = FALSE;
                    $error = ['code' => -1, 'message' => ''];
                } finally {
                    $this->db->db_debug = $debug_previo;
                }

                if (!$ok || !empty($error['code'])
                    || $this->db->affected_rows() !== count($lote)) {
                    $this->db->trans_rollback();
                    log_message('error', 'Rifa_ticket_model::bloquear no pudo insertar todos los tickets (DB code ' . (int)($error['code'] ?? 0) . ').');
                    return ['ok' => FALSE, 'motivo' => 'error'];
                }

                $this->_historial_batch($rifa_id, $padded, 'bloqueado', $actor, $motivo);

                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return ['ok' => FALSE, 'motivo' => 'error'];
                }

                $this->db->trans_commit();
                return ['ok' => TRUE, 'cantidad' => count($padded)];
            }

            // Liberar: todos los números deben existir y estar bloqueados.
            $conflictos  = [];
            $hist_numeros = [];
            foreach ($ints as $indice => $n) {
                $fila = $porInt[$n] ?? NULL;

                if (empty($fila) || (string)$fila['tick_estado'] !== 'bloqueado') {
                    $conflictos[] = [
                        'numero' => $fila ? (string)$fila['tick_numero'] : $padded[$indice],
                        'estado' => $fila ? (string)$fila['tick_estado'] : 'disponible',
                    ];
                } else {
                    $hist_numeros[] = (string)$fila['tick_numero'];
                }
            }

            if (!empty($conflictos)) {
                $this->db->trans_rollback();
                $cap = $this->_limitar_conflictos($conflictos);
                return [
                    'ok'              => FALSE,
                    'motivo'          => 'conflictos',
                    'conflictos'      => $cap['items'],
                    'conflictos_total' => $cap['total'],
                ];
            }

            $this->db
                ->where('tick_rifa_id', (int)$rifa_id)
                ->where('tick_estado', 'bloqueado')
                ->where_in('tick_numero_int', $ints)
                ->delete($this->table);

            if ($this->db->affected_rows() !== count($ints)) {
                $this->db->trans_rollback();
                log_message('error', 'Rifa_ticket_model::liberar no liberó todas las filas esperadas.');
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->_historial_batch($rifa_id, $hist_numeros, 'liberado', $actor, $motivo);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'cantidad' => count($ints)];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_ticket_model no pudo operar los números de la rifa.');
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    /**
     * @param  array $conflictos
     * @return array ['items' => array, 'total' => int]
     */
    private function _limitar_conflictos(array $conflictos)
    {
        $total = count($conflictos);

        return [
            'items' => array_slice($conflictos, 0, 200),
            'total' => $total,
        ];
    }

    /**
     * @param  int   $rifa_id
     * @param  array $padded
     * @return array
     */
    private function _filas_existentes($rifa_id, array $padded, array $ints = [])
    {
        if (empty($padded) && empty($ints)) {
            return [];
        }

        $this->db
            ->select('tick_numero, tick_numero_int, tick_estado')
            ->where('tick_rifa_id', (int)$rifa_id);

        if (!empty($ints)) {
            $this->db->where_in('tick_numero_int', $ints);
        } else {
            $this->db->where_in('tick_numero', $padded);
        }

        return $this->db->get($this->table)->result_array();
    }

    /**
     * @param  int    $rifa_id
     * @param  array  $padded
     * @param  string $accion
     * @param  string $actor
     * @param  string $motivo
     * @return void
     */
    private function _historial_batch($rifa_id, array $padded, $accion, $actor = '', $motivo = '')
    {
        if (empty($padded)) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');
        $filas = [];

        foreach ($padded as $numero) {
            $filas[] = [
                'hist_rifa_id' => (int)$rifa_id,
                'hist_numero'  => (string)$numero,
                'hist_accion'  => (string)$accion,
                'hist_actor'   => $actor !== '' ? $actor : NULL,
                'hist_motivo'  => $motivo !== '' ? $motivo : NULL,
                'hist_at'      => $ahora,
            ];
        }

        $this->db->insert_batch($this->table_hist, $filas);
    }

    /**
     * @param  int      $rifa_id
     * @param  int|null $org_id
     * @return array|null
     */
    private function _lock_rifa($rifa_id, $org_id = NULL)
    {
        if ($org_id !== NULL && (int)$org_id <= 0) {
            return NULL;
        }

        $sql = "SELECT * FROM {$this->table_rifas}"
            . " WHERE rifa_id = ? AND rifa_eliminado = 0";
        $binds = [(int)$rifa_id];

        if ($org_id !== NULL) {
            $sql .= ' AND rifa_org_id = ?';
            $binds[] = (int)$org_id;
        }

        $sql .= ' LIMIT 1 FOR UPDATE';

        return $this->db->query($sql, $binds)->row_array();
    }

    /**
     * @param  int $rifa_id
     * @return array|null
     */
    private function _get_rifa($rifa_id, $org_id = NULL)
    {
        if ($org_id !== NULL && (int)$org_id <= 0) {
            return NULL;
        }

        $this->db
            ->select('rifa_id, rifa_org_id, rifa_total_numeros, rifa_numero_desde, rifa_numero_hasta, rifa_padding, rifa_modo_asignacion, rifa_mostrar_reservados')
            ->where('rifa_id', (int)$rifa_id)
            ->where('rifa_eliminado', 0);

        if ($org_id !== NULL) {
            $this->db->where('rifa_org_id', (int)$org_id);
        }

        return $this->db->limit(1)->get($this->table_rifas)->row_array();
    }

    /**
     * @param  array $a
     * @param  array $b
     * @return bool
     */
    private function _misma_numeracion(array $a, array $b)
    {
        return (int)$a['rifa_total_numeros'] === (int)$b['rifa_total_numeros']
            && (int)$a['rifa_numero_desde'] === (int)$b['rifa_numero_desde']
            && (int)$a['rifa_numero_hasta'] === (int)$b['rifa_numero_hasta']
            && (int)$a['rifa_padding'] === (int)$b['rifa_padding'];
    }

    /**
     * @param  array $rango
     * @param  int   $padding
     * @return array
     */
    private function _formatear_rango(array $rango, $padding)
    {
        $desde = str_pad((string)$rango['desde'], $padding, '0', STR_PAD_LEFT);
        $hasta = str_pad((string)$rango['hasta'], $padding, '0', STR_PAD_LEFT);

        return [
            'desde'    => $rango['desde'],
            'hasta'    => $rango['hasta'],
            'cantidad' => (int)$rango['cantidad'],
            'motivo'   => (string)$rango['motivo'],
            'creado'   => (string)$rango['creado'],
            'label'    => $rango['desde'] === $rango['hasta'] ? $desde : $desde . '–' . $hasta,
        ];
    }

    /**
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
