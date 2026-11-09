<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Publico_Controller no usa el prefijo MY_, por lo que CI no lo autocarga.
require_once APPPATH . 'core/Publico_Controller.php';

/**
 * Publico
 * ─────────────────────────────────────────────────────────────
 * Punto de entrada del sitio público de cada organizador (tenant).
 *
 * - Rutas locales: /sitio/<slug>
 * - Hosts de tenant: el Router apunta default_controller aquí.
 *
 * La fase M01 solo publica una pantalla "en construcción". El layout y la
 * vista serán provistos por el módulo de frontend.
 */
class Publico extends Publico_Controller
{
    /**
     * GET /sitio/<slug> o raíz de un host tenant.
     *
     * @param  string|null $slug
     * @return void
     */
    public function index($slug = NULL)
    {
        if (!$this->tenant->resolved() && $slug !== NULL && $slug !== '') {
            $this->tenant->resolve_por_slug($slug);
        }

        if (!$this->tenant->resolved()) {
            show_404();
        }

        $this->tenant_data = $this->tenant->tenant();
        $this->config->set_item('base_url', $this->tenant->base_url());

        $datos = [
            'tenant'        => $this->tenant_data,
            'tenant_data'   => $this->tenant_data,
            'nombre'        => $this->tenant->nombre(),
            'url_principal' => $this->_url_principal(),
            'anio'          => (int)date('Y'),
        ];

        $this->render_publico('publico/en_construccion', $datos);
    }

    /**
     * GET /Publico/no_encontrado
     *
     * @return void
     */
    public function no_encontrado()
    {
        show_404();
    }

    /**
     * URL del dominio principal (panel administrativo).
     *
     * @return string
     */
    private function _url_principal()
    {
        $principal = trim((string)env_value('APP_BASE_URL', ''));

        if ($principal === '') {
            $principal = (string)config_item('base_url');
        }

        return rtrim($principal, '/') . '/';
    }
}
