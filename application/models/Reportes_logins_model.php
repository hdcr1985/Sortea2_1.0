<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reportes_logins_model extends CI_Model
{
    private $tabla = 'cab_bitacora_ip';
    private $tiene_apellido = NULL;
    private $columnas_ordenables = [
        'l.bit_usuario',
        'l.bit_city',
        'l.bit_proxy',
        'l.bit_navegador',
        'l.bit_ip',
        'l.bit_organization',
        'l.bit_date',
    ];

    public function count_todos()
    {
        if (!$this->db->table_exists($this->tabla)) {
            return 0;
        }

        return $this->db->count_all($this->tabla);
    }

    public function count_filtrados($search = '', $usuario = '')
    {
        if (!$this->db->table_exists($this->tabla)) {
            return 0;
        }

        $this->_datatable_query($search, $usuario);
        return $this->db->count_all_results();
    }

    public function get_datatable($start, $length, $search = '', $usuario = '', $order_column = 0, $order_dir = 'desc')
    {
        if (!$this->db->table_exists($this->tabla)) {
            return [];
        }

        $this->_datatable_query($search, $usuario);

        $order_column = isset($this->columnas_ordenables[$order_column])
            ? $this->columnas_ordenables[$order_column]
            : 'l.bit_date';

        $order_dir = strtolower($order_dir) === 'asc' ? 'ASC' : 'DESC';

        $this->db->order_by($order_column, $order_dir);
        if ($order_column !== 'l.bit_date') {
            $this->db->order_by('l.bit_date', 'DESC');
        }

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        return $this->db->get()->result_array();
    }

    public function get_usuarios_filtro()
    {
        if (!$this->db->table_exists($this->tabla)) {
            return [];
        }

        return $this->db
            ->distinct()
            ->select('bit_usuario')
            ->where('bit_usuario !=', '')
            ->order_by('bit_usuario', 'ASC')
            ->get($this->tabla)
            ->result_array();
    }

    private function _datatable_query($search = '', $usuario = '')
    {
        $select = 'l.bit_usuario, l.bit_date, l.bit_asn, l.bit_isp, l.bit_navegador, l.bit_ip, l.bit_proxy, l.bit_tor, l.bit_vpn, l.bit_mobile, l.bit_country_code, l.bit_region, l.bit_city, l.bit_organization, l.bit_latitude, l.bit_longitude, l.bit_is_crawler, l.bit_host, u.usu_id, u.usu_nombre, u.usu_foto';
        if ($this->_tiene_apellido()) {
            $select .= ', u.usu_apellido';
        }

        $this->db
            ->select($select)
            ->from($this->tabla . ' l')
            ->join('cab_usuarios u', 'CONVERT(u.usu_usuario USING utf8) COLLATE utf8_unicode_ci = CONVERT(l.bit_usuario USING utf8) COLLATE utf8_unicode_ci', 'left', FALSE);

        $usuario = trim((string)$usuario);
        if ($usuario !== '') {
            $this->db->where('l.bit_usuario', $usuario);
        }

        $search = trim((string)$search);
        if ($search === '') {
            return;
        }

        $this->db->group_start();
        $this->db->like('l.bit_usuario', $search);
        $this->db->or_like('u.usu_nombre', $search);
        if ($this->_tiene_apellido()) {
            $this->db->or_like('u.usu_apellido', $search);
        }
        $this->db->or_like('l.bit_ip', $search);
        $this->db->or_like('l.bit_navegador', $search);
        $this->db->or_like('l.bit_city', $search);
        $this->db->or_like('l.bit_region', $search);
        $this->db->or_like('l.bit_country_code', $search);
        $this->db->or_like('l.bit_organization', $search);
        $this->db->or_like('l.bit_isp', $search);
        $this->db->or_like('l.bit_asn', $search);
        $this->db->group_end();
    }

    private function _tiene_apellido()
    {
        if ($this->tiene_apellido === NULL) {
            $this->tiene_apellido = $this->db->field_exists('usu_apellido', 'cab_usuarios');
        }

        return $this->tiene_apellido;
    }
}