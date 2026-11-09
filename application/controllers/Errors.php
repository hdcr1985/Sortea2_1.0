<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Errors
 * Maneja páginas de error dentro del layout de Metronic.
 */
class Errors extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // ─────────────────────────────────────────────────────────
    /** 404 — Página no encontrada */
    public function pagina_404()
    {
        $this->page_title = 'Página no encontrada';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => '404',    'url' => ''],
        ];
        $this->output->set_status_header(404);
        $this->render('pages/error_404');
    }

    // ─────────────────────────────────────────────────────────
    /** 403 — Sin permisos */
    public function sin_permisos()
    {
        $this->page_title = 'Acceso denegado';
        $this->breadcrumb = [
            ['titulo' => 'Inicio',          'url' => base_url('dashboard')],
            ['titulo' => 'Acceso denegado', 'url' => ''],
        ];
        $this->output->set_status_header(403);
        $this->render('pages/sin_permisos');
    }

    // ─────────────────────────────────────────────────────────
    /** 503 — Sistema en Mantenimiento */
    public function mantenimiento()
    {
        $this->load->model('Configuracion_general_model');
        $settings = $this->Configuracion_general_model->get_settings();

        $this->page_title = !empty($settings['mantenimiento_titulo'])
            ? $settings['mantenimiento_titulo']
            : 'Sistema en Mantenimiento';

        $this->breadcrumb = [
            ['titulo' => 'Inicio',        'url' => base_url('dashboard')],
            ['titulo' => 'Mantenimiento', 'url' => ''],
        ];

        $this->output->set_status_header(503);
        $this->render('pages/mantenimiento', [
            'mantenimiento_titulo'  => $settings['mantenimiento_titulo'] ?? 'Sistema en Mantenimiento',
            'mantenimiento_mensaje' => $settings['mantenimiento_mensaje'] ?? 'Estamos actualizando el sistema para ofrecerte una mejor experiencia. Volveremos en breve.',
        ]);
    }
}
