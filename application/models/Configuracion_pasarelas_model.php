<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Configuracion_pasarelas_model extends CI_Model
{
    private $tabla = 'cab_configuracion_pasarelas';
    private $tabla_webhooks = 'cab_mov_btcpay_webhooks';
    private $tabla_btcpay = 'cab_mov_btcpay';
    private $tabla_stripe_webhooks = 'cab_mov_stripe_webhooks';
    private $tabla_stripe = 'cab_mov_stripe';

    public function __construct()
    {
        parent::__construct();
    }

    public function tabla_existe()
    {
        return $this->db->table_exists($this->tabla);
    }

    /**
     * Obtiene la configuración de una pasarela por su código
     *
     * @param string $codigo 'btcpay' | 'stripe'
     * @return array|null
     */
    public function get_pasarela($codigo)
    {
        if (!$this->tabla_existe()) {
            return $this->_get_fallback_config($codigo);
        }

        $row = $this->db
            ->where('pas_codigo', $codigo)
            ->limit(1)
            ->get($this->tabla)
            ->row_array();

        if (empty($row)) {
            return $this->_get_fallback_config($codigo);
        }

        // Defense in depth: refuse to use credentials that were previously
        // committed to source control in production. The known test values
        // are allowed only in development/testing so the BTCPay sandbox can
        // be exercised without weakening production.
        if ($this->_contiene_credenciales_filtradas($row)) {
            if (!$this->_permite_credenciales_de_prueba()) {
                log_message('error', 'Payment gateway: leaked credentials detected. Gateway disabled until they are rotated.');
                return $this->_get_fallback_config($codigo);
            }

            static $test_credentials_logged = FALSE;
            if (!$test_credentials_logged) {
                log_message('info', 'Payment gateway: using known TEST credentials in ' . ENVIRONMENT . '; never enable these in production.');
                $test_credentials_logged = TRUE;
            }
        }

        return $row;
    }

    /**
     * Allows the documented test credentials only outside production.
     *
     * @return bool
     */
    private function _permite_credenciales_de_prueba()
    {
        return defined('ENVIRONMENT') && in_array(ENVIRONMENT, ['development', 'testing'], TRUE);
    }

    /**
     * Detecta las credenciales BTCPay que fueron filtradas en el repositorio.
     *
     * @param  array $row
     * @return bool
     */
    private function _contiene_credenciales_filtradas(array $row)
    {
        $api_key        = (string)($row['pas_api_key'] ?? '');
        $webhook_secret = (string)($row['pas_webhook_secret'] ?? '');

        $hashes = [
            'c340aab9dbc7d511f756332dfb84f680726b3ee13c06df960e45ffe864a556b5',
            '8b64801f6fb6c4a1424d4530846bf725e0a1d7ef46761ab5bbae1d89b882529b',
        ];

        if ($api_key !== '' && in_array(hash('sha256', $api_key), $hashes, TRUE)) {
            return TRUE;
        }

        return $webhook_secret !== '' && in_array(hash('sha256', $webhook_secret), $hashes, TRUE);
    }

    /**
     * Obtiene todas las pasarelas
     *
     * @return array
     */
    public function get_todas()
    {
        if (!$this->tabla_existe()) {
            return [
                $this->_get_fallback_config('btcpay'),
                $this->_get_fallback_config('stripe'),
            ];
        }

        $rows = $this->db
            ->order_by('pas_id', 'ASC')
            ->get($this->tabla)
            ->result_array();

        foreach ($rows as &$row) {
            if ($this->_contiene_credenciales_filtradas($row)
                && !$this->_permite_credenciales_de_prueba()) {
                $row['pas_activo']         = 0;
                $row['pas_api_key']        = '';
                $row['pas_webhook_secret'] = '';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Actualiza la configuración de una pasarela
     *
     * @param string $codigo
     * @param array $data
     * @return bool
     */
    public function actualizar($codigo, array $data)
    {
        if (!$this->tabla_existe()) {
            return FALSE;
        }

        $pas = $this->db
            ->where('pas_codigo', $codigo)
            ->get($this->tabla)
            ->row_array();

        $data['pas_fecha_reg'] = date('Y-m-d');
        $data['pas_hora_reg']  = date('H:i:s');

        if ($pas) {
            return $this->db
                ->where('pas_codigo', $codigo)
                ->update($this->tabla, $data);
        } else {
            $data['pas_codigo'] = $codigo;
            return $this->db->insert($this->tabla, $data);
        }
    }

    /**
     * Registra un webhook recibido en cab_mov_btcpay_webhooks
     *
     * @param array $data
     * @return int|bool
     */
    public function registrar_webhook(array $data)
    {
        if (!$this->db->table_exists($this->tabla_webhooks)) {
            return FALSE;
        }

        $data['date'] = date('Y-m-d');
        $data['time'] = date('H:i:s');

        if ($this->db->insert($this->tabla_webhooks, $data)) {
            return $this->db->insert_id();
        }
        return FALSE;
    }

    /**
     * Registra o actualiza la factura BTCPay en cab_mov_btcpay
     *
     * @param array $data
     * @return bool
     */
    public function registrar_factura_btcpay(array $data)
    {
        if (!$this->db->table_exists($this->tabla_btcpay)) {
            return FALSE;
        }

        $data['date'] = date('Y-m-d');
        $data['time'] = date('H:i:s');

        return $this->db->insert($this->tabla_btcpay, $data);
    }

    /**
     * Returns a Stripe webhook event by its Stripe event id.
     *
     * @param  string $event_id
     * @return array|null
     */
    public function get_evento_stripe($event_id)
    {
        if (!$this->db->table_exists($this->tabla_stripe_webhooks)) {
            return NULL;
        }

        return $this->db
            ->where('event_id', (string)$event_id)
            ->limit(1)
            ->get($this->tabla_stripe_webhooks)
            ->row_array();
    }

    /**
     * Stores a Stripe webhook event once. Returns the row id, or FALSE.
     *
     * The event_id column is unique, so concurrent deliveries race safely:
     * the loser re-reads the row created by the winner.
     *
     * @param  array $data
     * @return int|false
     */
    public function crear_evento_stripe(array $data)
    {
        if (!$this->db->table_exists($this->tabla_stripe_webhooks)) {
            return FALSE;
        }

        $event_id = trim((string)($data['event_id'] ?? ''));
        if ($event_id === '') {
            return FALSE;
        }

        $existente = $this->get_evento_stripe($event_id);
        if (!empty($existente)) {
            return (int)$existente['id'];
        }

        $data['event_id']  = $event_id;
        $data['processed'] = 0;
        $data['date']      = date('Y-m-d');
        $data['time']      = date('H:i:s');

        if ($this->db->insert($this->tabla_stripe_webhooks, $data)) {
            return (int)$this->db->insert_id();
        }

        $existente = $this->get_evento_stripe($event_id);

        return !empty($existente) ? (int)$existente['id'] : FALSE;
    }

    /**
     * Marks a Stripe webhook event as fully processed.
     *
     * @param  string $event_id
     * @return bool
     */
    public function marcar_evento_stripe_procesado($event_id)
    {
        if (!$this->db->table_exists($this->tabla_stripe_webhooks)) {
            return FALSE;
        }

        return (bool)$this->db
            ->where('event_id', (string)$event_id)
            ->where('processed', 0)
            ->update($this->tabla_stripe_webhooks, [
                'processed'  => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Inserts or updates the Stripe transaction/audit row for a session.
     *
     * @param  array $data
     * @return bool
     */
    public function registrar_transaccion_stripe(array $data)
    {
        if (!$this->db->table_exists($this->tabla_stripe)) {
            return FALSE;
        }

        $session_id = trim((string)($data['session_id'] ?? ''));
        if ($session_id === '') {
            return FALSE;
        }

        $data['date'] = date('Y-m-d');
        $data['time'] = date('H:i:s');

        $existente = $this->db
            ->where('session_id', $session_id)
            ->limit(1)
            ->get($this->tabla_stripe)
            ->row_array();

        if (!empty($existente)) {
            return (bool)$this->db
                ->where('session_id', $session_id)
                ->update($this->tabla_stripe, $data);
        }

        $data['session_id'] = $session_id;

        if ($this->db->insert($this->tabla_stripe, $data)) {
            return TRUE;
        }

        // A concurrent webhook delivery may have inserted the same session
        // between the read and the insert. Update that row instead.
        return (bool)$this->db
            ->where('session_id', $session_id)
            ->update($this->tabla_stripe, $data);
    }

    /**
     * Valores fallback seguros en caso de que la tabla a�n no haya sido migrada
     */
    private function _get_fallback_config($codigo)
    {
        // Never return credentials from source code. If the configuration
        // table has not been migrated or the gateway was not configured, the
        // gateway stays disabled until an administrator stores validated
        // credentials through the Payment Gateways screen.
        if ($codigo === 'btcpay') {
            return [
                'pas_id'                 => 1,
                'pas_codigo'             => 'btcpay',
                'pas_nombre'             => 'BTCPay Server (Cryptocurrency)',
                'pas_activo'             => 0,
                'pas_ambiente'           => 'production',
                'pas_url'                => '',
                'pas_store_id'           => '',
                'pas_api_key'            => '',
                'pas_webhook_secret'     => '',
                'pas_speed_policy'       => 'MediumSpeed',
                'pas_expiration_minutes' => 60,
                'pas_fecha_reg'          => date('Y-m-d'),
                'pas_hora_reg'           => date('H:i:s'),
            ];
        }

        return [
            'pas_id'                 => 2,
            'pas_codigo'             => 'stripe',
            'pas_nombre'             => 'Stripe (Credit / Debit Cards)',
            'pas_activo'             => 0,
            'pas_ambiente'           => 'sandbox',
            'pas_url'                => 'https://api.stripe.com',
            'pas_store_id'           => '',
            'pas_api_key'            => '',
            'pas_webhook_secret'     => '',
            'pas_speed_policy'       => 'MediumSpeed',
            'pas_expiration_minutes' => 30,
            'pas_fecha_reg'          => date('Y-m-d'),
            'pas_hora_reg'           => date('H:i:s'),
        ];
    }
}

