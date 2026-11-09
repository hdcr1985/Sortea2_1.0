<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class GoogleAnalytics extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Google_analytics_model');
        $this->page_title = 'Google Analytics';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Google Analytics', 'url' => ''],
        ];
    }

    private function autorizado($accion)
    {
        $id = $this->Google_analytics_model->menu_id();
        return $id > 0 && $this->tiene_permiso($id, $accion);
    }

    public function index()
    {
        if (!$this->autorizado('consultar')) return show_error('No tienes permiso para consultar esta configuración.', 403);
        $token = bin2hex(random_bytes(32));
        $this->session->set_userdata('ga_config_csrf', $token);
        $this->render('pages/google_analytics/index', [
            'configuracion' => $this->Google_analytics_model->configuracion(),
            'puede_editar' => $this->autorizado('modificar'),
            'reporte_disponible' => $this->Google_analytics_model->reporte_disponible(),
            'credenciales_disponibles' => $this->_credenciales_disponibles(),
            'csrf_ga' => $token,
        ]);
    }

    public function guardar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        if (!$this->autorizado('modificar')) return $this->json(['ok' => FALSE, 'msg' => 'No tienes permiso para modificar esta configuración.'], 403);
        $token = $this->input->post('csrf_ga');
        $esperado = (string)$this->session->userdata('ga_config_csrf');
        if (!is_string($token) || $esperado === '' || !hash_equals($esperado, $token)) return $this->json(['ok' => FALSE, 'msg' => 'La sesión del formulario expiró. Recarga la página.'], 403);
        if (!$this->Google_analytics_model->configuracion()) return $this->json(['ok' => FALSE, 'msg' => 'Configuración no disponible. Ejecuta la migración del módulo.'], 500);

        $id_post = $this->input->post('measurement_id');
        if (!is_string($id_post)) return $this->json(['ok' => FALSE, 'errores' => ['measurement_id' => 'Introduce un ID GA4 válido.']], 422);
        $id = strtoupper(trim($id_post));
        $activo = $this->input->post('activo') === '1' ? 1 : 0;
        if (($id !== '' && !preg_match('/\AG-[A-Z0-9]{4,29}\z/', $id)) || ($activo && $id === '')) {
            return $this->json(['ok' => FALSE, 'errores' => ['measurement_id' => 'Introduce un ID GA4 válido, por ejemplo G-ABC1234567.']], 422);
        }
        $data = [
            'ga_measurement_id' => $id,
            'ga_activo' => $activo,
            'ga_page_view' => $this->input->post('page_view') === '1' ? 1 : 0,
            'ga_outbound_click' => $this->input->post('outbound_click') === '1' ? 1 : 0,
        ];
        if ($this->Google_analytics_model->reporte_disponible()) {
            $property = $this->input->post('property_id');
            $property = is_string($property) ? trim($property) : '';
            $reporte_activo = $this->input->post('reporte_activo') === '1' ? 1 : 0;
            if (($property !== '' && !preg_match('/\A[0-9]{1,20}\z/', $property)) || ($reporte_activo && $property === '')) {
                return $this->json(['ok' => FALSE, 'errores' => ['property_id' => 'Introduce el ID numérico de la propiedad GA4.']], 422);
            }
            $data['ga_property_id'] = $property;
            $data['ga_reporte_activo'] = $reporte_activo;
        }
        if (!$this->Google_analytics_model->guardar($data)) return $this->json(['ok' => FALSE, 'msg' => 'No se pudo guardar la configuración.'], 500);
        $this->bitacora('GOOGLE_ANALYTICS_ACTUALIZAR', 'Configuración GA4 actualizada; seguimiento ' . ($activo ? 'activado' : 'desactivado') . '.');
        return $this->json(['ok' => TRUE, 'msg' => 'Configuración de Google Analytics guardada.']);
    }

    private function _credenciales_disponibles()
    {
        $path = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        $path = is_string($path) ? realpath($path) : FALSE;
        $webroot = realpath(FCPATH);
        return $path && $webroot && is_file($path) && is_readable($path)
            && stripos(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $webroot), '/') . '/') !== 0;
    }
}
