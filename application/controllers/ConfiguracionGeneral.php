<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ConfiguracionGeneral extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Configuracion_general_model');
        $this->page_title = 'Configuración General';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Configuración General', 'url' => ''],
        ];
    }

    public function index()
    {
        $settings = $this->Configuracion_general_model->get_settings();

        $datos = [
            'settings'         => $settings,
            'tabla_disponible' => $this->Configuracion_general_model->tabla_existe(),
            'success'          => $this->session->flashdata('success'),
            'error'            => $this->session->flashdata('error'),
            'errores'          => $this->session->flashdata('errores') ?: [],
        ];

        $this->render('pages/configuracion_general/index', $datos);
    }

    public function guardar()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return redirect($this->module_path());
        }
        $this->autorizar_accion('modificar', 'No tienes permiso para modificar la configuración general.');

        if (!$this->Configuracion_general_model->tabla_existe()) {
            $this->session->set_flashdata('error', 'Debes ejecutar primero el script SQL de configuración general.');
            return redirect($this->module_path());
        }

        // Visor de errores. Si el campo no viene en el POST se omite para no
        // sobrescribir el valor existente (el formulario puede no incluirlo
        // todavía). Si llega un arreglo se rechaza.
        $logs_activo_raw = $this->input->post('logs_reporte_activo');
        $logs_dias_raw   = $this->input->post('logs_reporte_dias');
        $logs_max_raw    = $this->input->post('logs_reporte_max_entradas');

        $logs_activo = ($logs_activo_raw === '1') ? '1' : '0';
        $logs_dias   = is_scalar($logs_dias_raw) ? trim((string)$logs_dias_raw) : '';
        $logs_max    = is_scalar($logs_max_raw) ? trim((string)$logs_max_raw) : '';

        $data = [
            // Empresa
            'empresa_nombre'            => trim((string)$this->input->post('empresa_nombre', TRUE)),
            'empresa_documento'         => trim((string)$this->input->post('empresa_documento', TRUE)),
            'empresa_telefono'          => trim((string)$this->input->post('empresa_telefono', TRUE)),
            'empresa_whatsapp'          => trim((string)$this->input->post('empresa_whatsapp', TRUE)),
            'empresa_correo'            => trim((string)$this->input->post('empresa_correo', TRUE)),
            'empresa_direccion'         => trim((string)$this->input->post('empresa_direccion', TRUE)),

            // Monedas y tasas
            'moneda_principal_nombre'   => trim((string)$this->input->post('moneda_principal_nombre', TRUE)),
            'moneda_principal_codigo'   => strtoupper(trim((string)$this->input->post('moneda_principal_codigo', TRUE))),
            'moneda_principal_simbolo'  => trim((string)$this->input->post('moneda_principal_simbolo', TRUE)),
            'moneda_secundaria_nombre' => trim((string)$this->input->post('moneda_secundaria_nombre', TRUE)),
            'moneda_secundaria_codigo' => strtoupper(trim((string)$this->input->post('moneda_secundaria_codigo', TRUE))),
            'moneda_secundaria_simbolo'=> trim((string)$this->input->post('moneda_secundaria_simbolo', TRUE)),
            'tasa_cambio_secundaria'    => str_replace(',', '.', trim((string)$this->input->post('tasa_cambio_secundaria', TRUE))),

            // Mantenimiento y operaciones
            'mantenimiento_modo'        => $this->input->post('mantenimiento_modo') === '1' ? '1' : '0',
            'mantenimiento_titulo'      => trim((string)$this->input->post('mantenimiento_titulo', TRUE)),
            'mantenimiento_mensaje'     => trim((string)$this->input->post('mantenimiento_mensaje', TRUE)),
            'sesion_inactividad_minutos'=> (string)max(5, (int)($this->input->post('sesion_inactividad_minutos') ?: 120)),

            // Banner informativo
            'banner_activo'             => $this->input->post('banner_activo') === '1' ? '1' : '0',
            'banner_tipo'               => trim((string)$this->input->post('banner_tipo', TRUE)),
            'banner_texto'              => trim((string)$this->input->post('banner_texto', TRUE)),
        ];

        if ($logs_activo_raw !== NULL) {
            $data['logs_reporte_activo'] = $logs_activo;
        }
        if ($logs_dias_raw !== NULL) {
            $data['logs_reporte_dias'] = $logs_dias;
        }
        if ($logs_max_raw !== NULL) {
            $data['logs_reporte_max_entradas'] = $logs_max;
        }

        $errores = [];

        if ($data['empresa_nombre'] === '') {
            $errores['empresa_nombre'] = 'El nombre de la empresa es obligatorio.';
        }

        if ($data['moneda_principal_codigo'] === '') {
            $errores['moneda_principal_codigo'] = 'El código de moneda principal es requerido (ej: USD).';
        }

        if ($data['moneda_principal_simbolo'] === '') {
            $errores['moneda_principal_simbolo'] = 'El símbolo de moneda principal es requerido.';
        }

        if ($data['tasa_cambio_secundaria'] === '' || !is_numeric($data['tasa_cambio_secundaria']) || (float)$data['tasa_cambio_secundaria'] <= 0) {
            $errores['tasa_cambio_secundaria'] = 'Ingresa una tasa de cambio numérica mayor a 0.';
        }

        // Visor de errores: el estado se normaliza a '1'/'0'; se rechazan
        // arreglos, objetos y valores distintos de 0/1.
        if ($logs_activo_raw !== NULL
            && (!is_scalar($logs_activo_raw) || !in_array((string)$logs_activo_raw, ['0', '1'], TRUE))) {
            $errores['logs_reporte_activo'] = 'El estado del visor de errores no es válido.';
        }

        if ($logs_dias_raw !== NULL
            && (!is_scalar($logs_dias_raw) || !preg_match('/^\d+$/', $logs_dias) || (int)$logs_dias < 1 || (int)$logs_dias > 90)) {
            $errores['logs_reporte_dias'] = 'Los días del reporte de errores deben ser un entero entre 1 y 90.';
        }

        if ($logs_max_raw !== NULL
            && (!is_scalar($logs_max_raw) || !preg_match('/^\d+$/', $logs_max) || (int)$logs_max < 50 || (int)$logs_max > 5000)) {
            $errores['logs_reporte_max_entradas'] = 'El máximo de entradas debe ser un entero entre 50 y 5000.';
        }

        if (!empty($errores)) {
            $this->session->set_flashdata('errores', $errores);
            $this->session->set_flashdata('error', 'Por favor corrige los campos indicados.');
            return redirect($this->module_path());
        }

        if (!$this->Configuracion_general_model->guardar($data)) {
            $this->session->set_flashdata('error', 'No se pudieron guardar los ajustes generales.');
            return redirect($this->module_path());
        }

        $this->bitacora('CONFIGURACION_GENERAL_ACTUALIZAR', 'Se actualizaron los parámetros generales y financieros del sistema.');
        $this->session->set_flashdata('success', 'Configuración general guardada exitosamente.');
        redirect($this->module_path());
    }
}

