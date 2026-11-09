<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Configuracion_general_model extends CI_Model
{
    private $tabla = 'cab_configuracion_general';

    private $defaults = [
        // Empresa
        'empresa_nombre'            => 'Mi Empresa',
        'empresa_documento'         => '',
        'empresa_telefono'          => '',
        'empresa_whatsapp'          => '',
        'empresa_correo'            => '',
        'empresa_direccion'         => '',

        // Monedas y tasas
        'moneda_principal_nombre'   => 'Dólar Estadounidense',
        'moneda_principal_codigo'   => 'USD',
        'moneda_principal_simbolo'  => '$',
        'moneda_secundaria_nombre' => 'Bolívares',
        'moneda_secundaria_codigo' => 'VES',
        'moneda_secundaria_simbolo'=> 'Bs.',
        'tasa_cambio_secundaria'    => '36.50',

        // Mantenimiento y operaciones
        'mantenimiento_modo'        => '0',
        'mantenimiento_titulo'      => 'Mantenimiento en Curso',
        'mantenimiento_mensaje'     => 'Estamos actualizando el sistema para ofrecerte una mejor experiencia. Volveremos en breve.',
        'sesion_inactividad_minutos'=> '120',

        // Banner informativo
        'banner_activo'             => '0',
        'banner_tipo'               => 'info',
        'banner_texto'              => '',

        // Visor de errores (solo lectura de application/logs)
        // logs_reporte_dias: ventana máxima de consulta (1..90).
        // logs_reporte_max_entradas: tope global de entradas por request (50..5000).
        'logs_reporte_activo'        => '1',
        'logs_reporte_dias'          => '7',
        'logs_reporte_max_entradas'  => '500',
    ];

    public function tabla_existe()
    {
        return $this->db->table_exists($this->tabla);
    }

    public function get_defaults()
    {
        return $this->defaults;
    }

    public function get_settings()
    {
        $settings = $this->defaults;

        if ($this->tabla_existe()) {
            $rows = $this->db
                ->select('cfg_clave, cfg_valor')
                ->get($this->tabla)
                ->result_array();

            foreach ($rows as $row) {
                $key = (string)($row['cfg_clave'] ?? '');
                if ($key !== '' && array_key_exists($key, $settings)) {
                    $settings[$key] = (string)($row['cfg_valor'] ?? '');
                }
            }
        }

        return $settings;
    }

    public function get_valor($clave, $default = '')
    {
        if (!$this->tabla_existe()) {
            return $this->defaults[$clave] ?? $default;
        }

        $row = $this->db
            ->select('cfg_valor')
            ->where('cfg_clave', $clave)
            ->limit(1)
            ->get($this->tabla)
            ->row_array();

        return isset($row['cfg_valor']) ? (string)$row['cfg_valor'] : ($this->defaults[$clave] ?? $default);
    }

    public function guardar(array $data)
    {
        if (!$this->tabla_existe()) {
            return FALSE;
        }

        foreach ($this->defaults as $key => $default_val) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $valor = (string)$data[$key];
            // Tipos declarados por la migración que no deben inferirse por valor.
            $tipos_fijos = [
                'logs_reporte_activo' => 'bool',
            ];
            $tipo = $tipos_fijos[$key] ?? (is_numeric($valor) ? 'number' : 'text');

            $exists = $this->db
                ->where('cfg_clave', $key)
                ->count_all_results($this->tabla) > 0;

            $payload = [
                'cfg_clave'      => $key,
                'cfg_valor'      => $valor,
                'cfg_tipo'       => $tipo,
                'cfg_updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($exists) {
                $this->db->where('cfg_clave', $key)->update($this->tabla, $payload);
            } else {
                $payload['cfg_created_at'] = date('Y-m-d H:i:s');
                $this->db->insert($this->tabla, $payload);
            }
        }

        return TRUE;
    }
}

