<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Google_analytics_model extends CI_Model
{
    public function disponible()
    {
        return $this->db->table_exists('cab_google_analytics');
    }

    public function configuracion()
    {
        if (!$this->disponible()) return NULL;
        return $this->db->where('ga_id', 1)->get('cab_google_analytics')->row_array();
    }

    public function menu_id()
    {
        $row = $this->db->select('men_id')->where('men_urlamigable', 'google-analytics')
            ->where('men_url', 'GoogleAnalytics/index')->limit(1)->get('cab_menu')->row_array();
        return $row ? (int)$row['men_id'] : 0;
    }

    public function reporte_disponible()
    {
        return $this->disponible() && $this->db->field_exists('ga_reporte_activo', 'cab_google_analytics')
            && $this->db->field_exists('ga_property_id', 'cab_google_analytics');
    }

    public function reporte_menu_id()
    {
        $row = $this->db->select('men_id')->where('men_urlamigable', 'reporte-google-analytics')
            ->where('men_url', 'ReportesGoogleAnalytics/index')->limit(1)->get('cab_menu')->row_array();
        return $row ? (int)$row['men_id'] : 0;
    }

    public function guardar(array $data)
    {
        return $this->db->where('ga_id', 1)->update('cab_google_analytics', $data);
    }
}
