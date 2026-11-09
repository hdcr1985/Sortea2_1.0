<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reportes_bitacora_model extends CI_Model
{
    private $tabla = 'cab_bitacora';
    private $tabla_usuarios = 'cab_usuarios';
    private $tiene_apellido = NULL;
    private $columnas_ordenables = [
        'b.bit_usuario',
        'b.bit_accion',
        'b.bit_detalles',
        'b.bit_navegador',
        'b.bit_ip',
        'b.bit_date',
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

        $this->db->reset_query();
        $usuarios_coincidentes = $this->_usuarios_coincidentes($search);

        $this->db->from($this->tabla . ' b');
        $this->_aplicar_filtros('b', $search, $usuario, $usuarios_coincidentes);

        return $this->db->count_all_results();
    }

    public function get_datatable($start, $length, $search = '', $usuario = '', $order_column = 0, $order_dir = 'desc')
    {
        if (!$this->db->table_exists($this->tabla)) {
            return [];
        }

        $this->db->reset_query();
        $usuarios_coincidentes = $this->_usuarios_coincidentes($search);

        $this->db
            ->select('b.bit_usuario, b.bit_date, b.bit_accion, b.bit_detalles, b.bit_navegador, b.bit_ip')
            ->from($this->tabla . ' b');

        $this->_aplicar_filtros('b', $search, $usuario, $usuarios_coincidentes);

        $order_column = isset($this->columnas_ordenables[$order_column])
            ? $this->columnas_ordenables[$order_column]
            : 'b.bit_date';

        $order_dir = strtolower($order_dir) === 'asc' ? 'ASC' : 'DESC';

        $this->db->order_by($order_column, $order_dir);
        if ($order_column !== 'b.bit_date') {
            $this->db->order_by('b.bit_date', 'DESC');
        }

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        $rows = $this->db->get()->result_array();
        $this->_adjuntar_datos_usuario($rows);

        return $rows;
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

    private function _aplicar_filtros($alias, $search, $usuario, array $usuarios_coincidentes = [])
    {
        $usuario = trim((string)$usuario);
        if ($usuario !== '') {
            $this->db->where($alias . '.bit_usuario', $usuario);
        }

        $search = trim((string)$search);
        if ($search === '') {
            return;
        }

        $this->db->group_start();
        $this->db->like($alias . '.bit_usuario', $search);
        $this->db->or_like($alias . '.bit_accion', $search);
        $this->db->or_like($alias . '.bit_detalles', $search);
        $this->db->or_like($alias . '.bit_navegador', $search);
        $this->db->or_like($alias . '.bit_ip', $search);
        if (!empty($usuarios_coincidentes)) {
            $this->db->or_where_in($alias . '.bit_usuario', $usuarios_coincidentes);
        }
        $this->db->group_end();
    }

    /**
     * Resuelve los usuarios que coinciden por nombre/apellido sin unir toda
     * cab_bitacora contra cab_usuarios. Devuelve sus nombres de usuario para
     * que el filtro principal use el indice de cab_bitacora.bit_usuario.
     */
    private function _usuarios_coincidentes($search)
    {
        $search = trim((string)$search);
        if ($search === '' || !$this->db->table_exists($this->tabla_usuarios)) {
            return [];
        }

        $this->db->reset_query();
        $this->db->select('usu_usuario')->distinct()->from($this->tabla_usuarios);
        $this->db->group_start();
        $this->db->like('usu_nombre', $search);
        if ($this->_tiene_apellido()) {
            $this->db->or_like('usu_apellido', $search);
        }
        $this->db->group_end();

        $rows = $this->db->get()->result_array();
        $usuarios = [];
        foreach ($rows as $row) {
            $usuario = trim((string)($row['usu_usuario'] ?? ''));
            if ($usuario !== '') {
                $usuarios[$usuario] = $usuario;
            }
        }

        return array_values($usuarios);
    }

    /**
     * Adjunta los datos del usuario solo para las filas ya paginadas, evitando
     * el join costoso sobre las 92k filas de cab_bitacora.
     */
    private function _adjuntar_datos_usuario(array &$rows)
    {
        if (empty($rows) || !$this->db->table_exists($this->tabla_usuarios)) {
            return;
        }

        $usuarios = [];
        foreach ($rows as $row) {
            $usuario = trim((string)($row['bit_usuario'] ?? ''));
            if ($usuario !== '') {
                $usuarios[mb_strtolower($usuario, 'UTF-8')] = $usuario;
            }
        }

        if (empty($usuarios)) {
            return;
        }

        $select = 'usu_id, usu_usuario, usu_nombre, usu_foto';
        if ($this->_tiene_apellido()) {
            $select .= ', usu_apellido';
        }

        $this->db->reset_query();
        $registros = $this->db
            ->select($select)
            ->where_in('usu_usuario', array_values($usuarios))
            ->get($this->tabla_usuarios)
            ->result_array();

        $mapa = [];
        foreach ($registros as $registro) {
            $usuario = mb_strtolower(trim((string)($registro['usu_usuario'] ?? '')), 'UTF-8');
            if ($usuario !== '') {
                $mapa[$usuario] = $registro;
            }
        }

        foreach ($rows as &$row) {
            $usuario = mb_strtolower(trim((string)($row['bit_usuario'] ?? '')), 'UTF-8');
            $registro = $mapa[$usuario] ?? [];

            $row['usu_id'] = $registro['usu_id'] ?? NULL;
            $row['usu_nombre'] = $registro['usu_nombre'] ?? NULL;
            $row['usu_foto'] = $registro['usu_foto'] ?? NULL;
            if ($this->_tiene_apellido()) {
                $row['usu_apellido'] = $registro['usu_apellido'] ?? NULL;
            }
        }
        unset($row);
    }

    private function _tiene_apellido()
    {
        if ($this->tiene_apellido === NULL) {
            $this->tiene_apellido = $this->db->field_exists('usu_apellido', $this->tabla_usuarios);
        }

        return $this->tiene_apellido;
    }
}
