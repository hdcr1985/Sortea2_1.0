<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_model
 * ─────────────────────────────────────────────────────────────
 * Modelo para la gestión de planes de suscripción/créditos (cab_planes).
 * Sigue el patrón canónico server-side y borrado lógico.
 */
class Plan_model extends CI_Model
{
    protected $table = 'cab_planes';
    protected $primary_key = 'pla_id';

    protected $columnas_ordenables = [
        0 => 'pla_nombre',
        1 => 'pla_precio',
        2 => 'pla_numero',
        3 => 'pla_paypal',
        4 => 'pla_status',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Cuenta todos los registros no eliminados (ADR-002: no filtra por status).
     *
     * @return int
     */
    public function count_todos()
    {
        return $this->db
            ->where('pla_eliminado', 0)
            ->count_all_results($this->table);
    }

    /**
     * Cuenta los registros tras aplicar el filtro de búsqueda.
     *
     * @param  string $search
     * @return int
     */
    public function count_filtrados($search = '')
    {
        $this->_datatable_query($search);
        return $this->db->count_all_results($this->table);
    }

    /**
     * Obtiene registros paginados para DataTables server-side.
     *
     * @param  int    $start
     * @param  int    $length
     * @param  string $search
     * @param  int    $order_col
     * @param  string $order_dir
     * @return array
     */
    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        $this->_datatable_query($search);

        $columna = $this->columnas_ordenables[$order_col] ?? 'pla_id';
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        return $this->db->get($this->table)->result_array();
    }

    /**
     * Query base para el conteo filtrado y obtención de DataTables.
     *
     * @param  string $search
     * @return void
     */
    private function _datatable_query($search = '')
    {
        $this->db->where('pla_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('pla_nombre', $search);
            $this->db->or_like('pla_descripcion', $search);
            $this->db->or_like('pla_numero', $search);
            $this->db->or_like('pla_precio', $search);
            $this->db->or_like('pla_moneda', $search);
            $this->db->group_end();
        }
    }

    /**
     * Obtiene un plan por su ID (si no está eliminado).
     *
     * @param  int $id
     * @return array|null
     */
    public function get_por_id($id)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('pla_eliminado', 0)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Verifica si existe otro plan con el mismo nombre.
     *
     * @param  string $nombre
     * @param  int    $ignorar_id
     * @return bool
     */
    public function existe_nombre($nombre, $ignorar_id = 0)
    {
        $this->db
            ->where('LOWER(pla_nombre)', strtolower(trim($nombre)))
            ->where('pla_eliminado', 0);

        if ((int)$ignorar_id > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$ignorar_id);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Inserta un nuevo plan.
     *
     * @param  array $data
     * @return int|false ID insertado o FALSE
     */
    public function crear(array $data)
    {
        $data['pla_created_at'] = date('Y-m-d H:i:s');
        $data['pla_updated_at'] = date('Y-m-d H:i:s');
        $data['pla_eliminado']  = 0;

        if ($this->db->insert($this->table, $data)) {
            return (int)$this->db->insert_id();
        }
        return FALSE;
    }

    /**
     * Actualiza un plan existente.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function actualizar($id, array $data)
    {
        $data['pla_updated_at'] = date('Y-m-d H:i:s');

        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('pla_eliminado', 0)
            ->update($this->table, $data);
    }

    /**
     * Borrado lógico de un plan.
     *
     * @param  int $id
     * @return bool
     */
    public function eliminar($id)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->update($this->table, [
                'pla_eliminado'  => 1,
                'pla_updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Cambia el estado activo/inactivo (1/0).
     *
     * @param  int $id
     * @return array ['ok' => bool, 'nuevo_status' => int]
     */
    public function toggle_status($id)
    {
        $plan = $this->get_por_id($id);
        if (!$plan) {
            return ['ok' => FALSE, 'nuevo_status' => 0];
        }

        $nuevo_status = ((int)$plan['pla_status'] === 1) ? 0 : 1;

        $ok = $this->db
            ->where($this->primary_key, (int)$id)
            ->update($this->table, [
                'pla_status'     => $nuevo_status,
                'pla_updated_at' => date('Y-m-d H:i:s'),
            ]);

        return ['ok' => $ok, 'nuevo_status' => $nuevo_status, 'plan' => $plan];
    }
}
