<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReportesGoogleAnalytics extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Google_analytics_model');
        $this->page_title = 'Reporte Google Analytics';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Reporte Google Analytics', 'url' => ''],
        ];
    }

    private function autorizado()
    {
        $id = $this->Google_analytics_model->reporte_menu_id();
        return $id > 0 && $this->tiene_permiso($id, 'consultar');
    }

    public function index()
    {
        if (!$this->autorizado()) return show_error('No tienes permiso para consultar este reporte.', 403);
        $this->render('pages/reportes_google_analytics/index');
    }

    public function datos()
    {
        if (!$this->autorizado()) return $this->json(['ok' => FALSE, 'msg' => 'No tienes permiso para consultar este reporte.'], 403);
        if (strtoupper($this->input->method(TRUE)) !== 'GET') return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        if (!$this->Google_analytics_model->reporte_disponible()) return $this->json(['ok' => FALSE, 'msg' => 'Ejecuta la migración del reporte GA4.'], 503);
        $settings = $this->Google_analytics_model->configuracion();
        if (empty($settings['ga_reporte_activo']) || empty($settings['ga_property_id'])) return $this->json(['ok' => FALSE, 'msg' => 'Activa el reporte y configura el ID de propiedad en Google Analytics.'], 422);
        $days = $this->input->get('dias');
        if (!is_string($days) || !in_array($days, ['7', '30', '90'], TRUE)) return $this->json(['ok' => FALSE, 'msg' => 'Selecciona un período válido.'], 422);
        $key = hash('sha256', $settings['ga_property_id'] . ':' . $days . ':' . ($settings['ga_updated_at'] ?? ''));
        $cache = $this->session->userdata('ga_reporte_cache');
        if (is_array($cache) && ($cache['key'] ?? '') === $key && (int)($cache['expires'] ?? 0) > time()) return $this->json(['ok' => TRUE, 'data' => $cache['data'], 'cache' => TRUE]);
        try {
            $this->load->library('Ga4_reporting');
            $data = $this->ga4_reporting->consultar($settings['ga_property_id'], (int)$days);
            $this->session->set_userdata('ga_reporte_cache', ['key' => $key, 'expires' => time() + 300, 'data' => $data]);
            return $this->json(['ok' => TRUE, 'data' => $data, 'cache' => FALSE]);
        } catch (Throwable $e) {
            log_message('error', 'Reporte GA4: consulta fallida.');
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo consultar Google Analytics. Verifica la propiedad, las credenciales y los permisos de la cuenta de servicio.'], 502);
        }
    }
}
