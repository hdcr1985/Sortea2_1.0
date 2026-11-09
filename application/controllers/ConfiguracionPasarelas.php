<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ConfiguracionPasarelas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Configuracion_pasarelas_model');
        $this->load->library('btcpay_client');
        $this->load->library('stripe_client');
        $this->page_title = 'Configuración de Pasarelas de Pago';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Pasarelas de Pago', 'url' => ''],
        ];
    }

    public function index()
    {
        $this->autorizar_accion('consultar', 'No tienes permiso para ver la configuración de pasarelas de pago.');

        $btcpay = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');
        $stripe = $this->Configuracion_pasarelas_model->get_pasarela('stripe');

        // Never expose stored secrets to the view layer.
        $btcpay_has_api_key         = is_array($btcpay) && !empty($btcpay['pas_api_key']);
        $btcpay_has_webhook_secret  = is_array($btcpay) && !empty($btcpay['pas_webhook_secret']);
        $stripe_has_api_key         = is_array($stripe) && !empty($stripe['pas_api_key']);
        $stripe_has_webhook_secret  = is_array($stripe) && !empty($stripe['pas_webhook_secret']);

        if (is_array($btcpay)) {
            unset($btcpay['pas_api_key'], $btcpay['pas_webhook_secret']);
        }
        if (is_array($stripe)) {
            unset($stripe['pas_api_key'], $stripe['pas_webhook_secret']);
        }

        $datos = [
            'url_base'                   => $this->url_base,
            'btcpay'                     => $btcpay,
            'stripe'                     => $stripe,
            'btcpay_has_api_key'         => $btcpay_has_api_key,
            'btcpay_has_webhook_secret'  => $btcpay_has_webhook_secret,
            'stripe_has_api_key'         => $stripe_has_api_key,
            'stripe_has_webhook_secret'  => $stripe_has_webhook_secret,
            'webhook_url'                => site_url('pasarelas-de-pago/webhook-btcpay'),
            'stripe_webhook_url'         => site_url('pasarelas-de-pago/webhook-stripe'),
            'tabla_disponible'           => $this->Configuracion_pasarelas_model->tabla_existe(),
            'success'                    => $this->session->flashdata('success'),
            'error'                      => $this->session->flashdata('error'),
        ];

        $this->render('pages/configuracion_pasarelas/index', $datos);
    }

    public function guardar_btcpay()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar la configuración de pasarelas de pago.');

        $actual = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');

        $url                   = $this->_post_escalar('pas_url');
        $store_id              = $this->_post_escalar('pas_store_id');
        $api_key_posted        = $this->_post_escalar('pas_api_key');
        $api_key               = $api_key_posted;
        $webhook_secret_posted = $this->_post_escalar('pas_webhook_secret');
        $webhook_secret        = $webhook_secret_posted;
        $speed_policy          = $this->_post_escalar('pas_speed_policy');
        $url_normalized  = rtrim($url, '/');
        $actual_url      = is_array($actual) ? rtrim((string)($actual['pas_url'] ?? ''), '/') : '';

        // Secrets are never rendered back into the HTML. An empty submitted
        // value means "keep the currently stored secret".
        if ($api_key === '' && is_array($actual)) {
            $api_key = trim((string)($actual['pas_api_key'] ?? ''));
        }
        if ($webhook_secret === '' && is_array($actual)) {
            $webhook_secret = trim((string)($actual['pas_webhook_secret'] ?? ''));
        }
        $expiration     = (int)$this->_post_escalar('pas_expiration_minutes');
        $activo         = $this->input->post('pas_activo') === '1' ? 1 : 0;
        $ambiente       = $this->_post_escalar('pas_ambiente');

        $errores = [];

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL) || !$this->btcpay_client->es_url_https($url)) {
            $errores['pas_url'] = 'Ingresa una URL HTTPS válida de BTCPay Server sin credenciales embebidas.';
        }
        if (empty($store_id)) {
            $errores['pas_store_id'] = 'El ID de tienda es obligatorio.';
        }
        if (empty($api_key)) {
            $errores['pas_api_key'] = 'La clave API o clave Basic es obligatoria.';
        } elseif ($api_key_posted === '' && $url_normalized !== $actual_url) {
            // Never send a stored credential to a newly configured host.
            $errores['pas_api_key'] = 'Vuelve a ingresar la clave API al cambiar la URL de BTCPay.';
        }
        if (empty($webhook_secret)) {
            $errores['pas_webhook_secret'] = 'El secreto del webhook es obligatorio.';
        } elseif ($webhook_secret_posted === '' && $url_normalized !== $actual_url) {
            // The stored secret belongs to the previous BTCPay host.
            $errores['pas_webhook_secret'] = 'Vuelve a ingresar el secreto del webhook al cambiar la URL de BTCPay.';
        }

        if (empty($errores) && !$this->btcpay_client->configuracion_api_valida([
            'pas_url'      => $url,
            'pas_store_id' => $store_id,
            'pas_api_key'  => $api_key,
        ])) {
            $errores['pas_url'] = 'Ingresa una URL base HTTPS válida de BTCPay Server sin cadena de consulta ni fragmento.';
        }

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $datos_guardar = [
            'pas_nombre'             => 'BTCPay Server (Criptomonedas)',
            'pas_url'                => $url_normalized,
            'pas_store_id'           => $store_id,
            'pas_api_key'            => $api_key,
            'pas_webhook_secret'     => $webhook_secret,
            'pas_speed_policy'       => in_array($speed_policy, ['HighSpeed', 'MediumSpeed', 'LowSpeed']) ? $speed_policy : 'MediumSpeed',
            'pas_expiration_minutes' => $expiration > 0 ? $expiration : 60,
            'pas_activo'             => $activo,
            'pas_ambiente'           => in_array($ambiente, ['sandbox', 'production']) ? $ambiente : 'production',
        ];

        $ok = $this->Configuracion_pasarelas_model->actualizar('btcpay', $datos_guardar);

        if ($ok) {
            $this->bitacora('PASARELA_BTCPAY_ACTUALIZAR', 'Configuración de BTCPay Server actualizada. Store ID: ' . $store_id);
            return $this->json(['ok' => TRUE, 'msg' => 'Configuración de BTCPay Server actualizada correctamente.']);
        }

        return $this->json(['ok' => FALSE, 'msg' => 'No se pudo guardar la configuración. Verifica que la tabla de base de datos exista.'], 500);
    }

    public function guardar_stripe()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion('modificar', 'No tienes permiso para modificar la configuración de pasarelas de pago.');

        $actual = $this->Configuracion_pasarelas_model->get_pasarela('stripe');

        $api_key        = $this->_post_escalar('pas_api_key');
        $webhook_secret = $this->_post_escalar('pas_webhook_secret');
        $ambiente       = $this->_post_escalar('pas_ambiente');
        $activo         = $this->input->post('pas_activo') === '1' ? 1 : 0;

        // Secrets are never rendered back into the HTML. An empty submitted
        // value means "keep the currently stored secret".
        if ($api_key === '' && is_array($actual)) {
            $api_key = trim((string)($actual['pas_api_key'] ?? ''));
        }
        if ($webhook_secret === '' && is_array($actual)) {
            $webhook_secret = trim((string)($actual['pas_webhook_secret'] ?? ''));
        }

        $errores = [];

        if (!$this->stripe_client->clave_api_valida($api_key)) {
            $errores['pas_api_key'] = 'Ingresa una clave secreta válida de Stripe (sk_... o rk_...).';
        }

        if (!$this->stripe_client->clave_webhook_valida($webhook_secret)) {
            $errores['pas_webhook_secret'] = 'Ingresa un secreto de firma de webhook válido de Stripe (whsec_...).';
        }

        if (!in_array($ambiente, ['sandbox', 'production'], TRUE)) {
            $errores['pas_ambiente'] = 'Selecciona un entorno válido de Stripe.';
        } elseif ($this->stripe_client->clave_api_valida($api_key)
            && !$this->stripe_client->ambiente_coincide($api_key, $ambiente)) {
            $errores['pas_api_key'] = 'El modo de la clave no coincide con el entorno seleccionado. Usa una clave de prueba para sandbox y una clave real para producción.';
        }

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $datos_guardar = [
            'pas_nombre'             => 'Stripe (Tarjetas de crédito / débito)',
            'pas_activo'             => $activo,
            'pas_ambiente'           => $ambiente,
            'pas_url'                => 'https://api.stripe.com',
            'pas_store_id'           => NULL,
            'pas_api_key'            => $api_key,
            'pas_webhook_secret'     => $webhook_secret,
            'pas_speed_policy'       => 'MediumSpeed',
            'pas_expiration_minutes' => 30,
        ];

        $ok = $this->Configuracion_pasarelas_model->actualizar('stripe', $datos_guardar);

        if ($ok) {
            $this->bitacora(
                'PASARELA_STRIPE_ACTUALIZAR',
                'Configuración de Stripe actualizada. Entorno: ' . $ambiente . ' | Activa: ' . $activo
            );
            return $this->json(['ok' => TRUE, 'msg' => 'Configuración de Stripe actualizada correctamente.']);
        }

        return $this->json(['ok' => FALSE, 'msg' => 'No se pudo guardar la configuración. Verifica que la tabla de base de datos exista.'], 500);
    }

    public function test_stripe()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para probar esta pasarela de pago.');

        $stripe = $this->Configuracion_pasarelas_model->get_pasarela('stripe');
        if (!is_array($stripe) || !$this->stripe_client->configuracion_api_valida($stripe)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La configuración de Stripe falta o es insegura. La clave secreta y el entorno deben coincidir.',
            ], 503);
        }

        $balance = $this->stripe_client->request('GET', '/v1/balance', $stripe);

        if (!is_array($balance)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La prueba de conexión con Stripe falló (HTTP ' . $this->stripe_client->get_last_status() . '): '
                    . $this->stripe_client->get_last_error(),
            ], 400);
        }

        if (!$this->stripe_client->modo_coincide($balance, $stripe)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'El modo de la cuenta Stripe no coincide con el entorno seleccionado. Revisa la clave y el entorno.',
            ], 400);
        }

        return $this->json([
            'ok'  => TRUE,
            'msg' => 'Conexión exitosa. La cuenta de Stripe es accesible en el modo esperado.',
        ]);
    }

    public function test_btcpay()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $this->autorizar_accion('consultar', 'No tienes permiso para probar esta pasarela de pago.');

        $btcpay = $this->Configuracion_pasarelas_model->get_pasarela('btcpay');
        if (!is_array($btcpay) || !$this->btcpay_client->configuracion_api_valida($btcpay)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La configuración de BTCPay falta o es insegura. La URL debe usar HTTPS e incluir ID de tienda y clave API.',
            ], 503);
        }

        $json = $this->btcpay_client->request(
            'GET',
            '/api/v1/stores/' . rawurlencode((string)$btcpay['pas_store_id']),
            $btcpay
        );

        if (!is_array($json) || empty($json['id'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La prueba de BTCPay Server falló (HTTP ' . $this->btcpay_client->get_last_status() . '): '
                    . $this->btcpay_client->get_last_error(),
            ], 400);
        }

        return $this->json([
            'ok'  => TRUE,
            'msg' => '¡Conexión exitosa! Nombre de la tienda: ' . (string)($json['name'] ?? $json['id']),
        ]);
    }

    /**
     * Reads a scalar POST value without triggering array-to-string warnings.
     *
     * @param  string $key
     * @return string
     */
    private function _post_escalar($key)
    {
        $value = $this->input->post($key, TRUE);

        return is_scalar($value) ? trim((string)$value) : '';
    }
}

