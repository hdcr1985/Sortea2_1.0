<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Publico_Controller
 * ─────────────────────────────────────────────────────────────
 * Controlador base para el área pública multi-tenant (M01).
 *
 * NO extiende MY_Controller: es público, no exige sesión ni permisos
 * administrativos. Resuelve el tenant de la petición y prepara su URL base.
 *
 * Nota: al no seguir el prefijo MY_, este archivo debe incluirse
 * explícitamente desde el controlador que lo extiende.
 */
class Publico_Controller extends CI_Controller
{
    /** @var array Datos del organizador resuelto */
    protected $tenant_data = [];

    public function __construct()
    {
        parent::__construct();

        $this->load->database();
        $this->load->helper(['url', 'network']);

        $this->load->library('tenant');
        $this->tenant->resolve();

        if (!$this->tenant->resolved()) {
            show_404();
        }

        // Las URLs generadas dentro del sitio público viven en el tenant.
        $this->config->set_item('base_url', $this->tenant->base_url());
        $this->tenant_data = $this->tenant->tenant();
    }

    /**
     * Renderiza una vista dentro del layout público (nunca el layout admin).
     *
     * @param  string $vista
     * @param  array  $datos
     * @return void
     */
    protected function render_publico($vista, $datos = [])
    {
        $datos['tenant']      = $this->tenant_data;
        $datos['tenant_data'] = $this->tenant_data;

        if (!isset($datos['page_title']) || $datos['page_title'] === '') {
            $datos['page_title'] = $this->tenant->nombre();
        }

        $datos['content'] = $this->load->view($vista, $datos, TRUE);
        $this->load->view('publico/layout', $datos);
    }

    /**
     * Respuesta JSON para endpoints públicos.
     *
     * @param  mixed $data
     * @param  int   $status
     * @return void
     */
    protected function json($data, $status = 200)
    {
        $this->output
            ->set_status_header((int)$status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
