<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notificaciones_model extends CI_Model
{
    private $tabla = 'cab_notifications_system';
    private $tabla_usuario = 'cab_notifications_system_user';

    public function tablas_existen()
    {
        return $this->db->table_exists($this->tabla) && $this->db->table_exists($this->tabla_usuario);
    }

    public function get_usuarios_activos()
    {
        return $this->db
            ->select('usu_id, usu_nombre, usu_usuario, usu_correo')
            ->where('usu_status', '1')
            ->order_by('usu_nombre', 'ASC')
            ->get('cab_usuarios')
            ->result_array();
    }

    public function get_todos()
    {
        if (!$this->tablas_existen()) {
            return [];
        }

        return $this->db
            ->select('n.noti_id, n.noti_titulo, n.noti_mensaje, n.noti_tipo, n.noti_enlace, n.noti_es_global, n.noti_created_at, n.noti_usuario_creador, u.usu_nombre AS creador_nombre, (SELECT COUNT(1) FROM ' . $this->tabla_usuario . ' nu WHERE nu.nfu_notificacion = n.noti_id) AS total_destinatarios', FALSE)
            ->from($this->tabla . ' n')
            ->join('cab_usuarios u', 'u.usu_id = n.noti_usuario_creador', 'left')
            ->order_by('n.noti_created_at', 'DESC')
            ->get()
            ->result_array();
    }

    public function count_todos()
    {
        if (!$this->tablas_existen()) {
            return 0;
        }

        return $this->db->count_all_results($this->tabla);
    }

    public function count_filtrados($search = '')
    {
        if (!$this->tablas_existen()) {
            return 0;
        }

        $this->_datatable_query($search);
        return $this->db->count_all_results();
    }

    public function get_datatable($start, $length, $search = '', $order_col = 5, $order_dir = 'desc')
    {
        if (!$this->tablas_existen()) {
            return [];
        }

        $columnas = [
            0 => 'n.noti_titulo',
            1 => 'n.noti_es_global',
            2 => 'n.noti_tipo',
            3 => 'total_destinatarios',
            4 => 'creador_nombre',
            5 => 'n.noti_created_at',
        ];

        $col = $columnas[$order_col] ?? 'n.noti_created_at';
        $dir = strtolower($order_dir) === 'asc' ? 'ASC' : 'DESC';

        $this->db->select('n.noti_id, n.noti_titulo, n.noti_mensaje, n.noti_tipo, n.noti_enlace, n.noti_es_global, n.noti_created_at, n.noti_usuario_creador, u.usu_nombre AS creador_nombre, u.usu_usuario AS creador_usuario, (SELECT COUNT(1) FROM ' . $this->tabla_usuario . ' nu WHERE nu.nfu_notificacion = n.noti_id) AS total_destinatarios', FALSE);
        $this->_datatable_query($search);
        $this->db->order_by($col, $dir);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        return $this->db->get()->result_array();
    }

    private function _datatable_query($search = '')
    {
        $this->db->from($this->tabla . ' n');
        $this->db->join('cab_usuarios u', 'u.usu_id = n.noti_usuario_creador', 'left');

        if (!empty($search)) {
            $search = trim($search);
            $this->db->group_start();
            $this->db->like('n.noti_titulo', $search);
            $this->db->or_like('n.noti_mensaje', $search);
            $this->db->or_like('u.usu_nombre', $search);
            $this->db->or_like('u.usu_usuario', $search);
            $this->db->group_end();
        }
    }

    public function crear(array $data, array $destinatarios)
    {
        if (!$this->tablas_existen()) {
            return FALSE;
        }

        $this->db->trans_start();

        $this->db->insert($this->tabla, $data);
        $notificacion_id = (int)$this->db->insert_id();

        foreach ($destinatarios as $usuario_id) {
            $this->db->insert($this->tabla_usuario, [
                'nfu_notificacion' => $notificacion_id,
                'nfu_usuario' => (string)$usuario_id,
                'nfu_leida' => '0',
                'nfu_fecha_leida' => NULL,
                'nfu_created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->trans_complete();

        return $this->db->trans_status() ? $notificacion_id : FALSE;
    }

    public function eliminar($id)
    {
        if (!$this->tablas_existen()) {
            return FALSE;
        }

        $this->db->trans_start();
        $this->db->where('nfu_notificacion', (int)$id)->delete($this->tabla_usuario);
        $this->db->where('noti_id', (int)$id)->delete($this->tabla);
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function get_por_id($id)
    {
        if (!$this->tablas_existen()) {
            return [];
        }

        return $this->db
            ->where('noti_id', (int)$id)
            ->get($this->tabla)
            ->row_array();
    }

    public function get_feed_usuario($usuario_id, $limit = 10)
    {
        if (!$this->tablas_existen()) {
            return [];
        }

        return $this->db
            ->select('nu.nfu_id, nu.nfu_leida, nu.nfu_fecha_leida, n.noti_id, n.noti_titulo, n.noti_mensaje, n.noti_tipo, n.noti_enlace, n.noti_created_at')
            ->from($this->tabla_usuario . ' nu')
            ->join($this->tabla . ' n', 'n.noti_id = nu.nfu_notificacion', 'inner')
            ->where('nu.nfu_usuario', (string)$usuario_id)
            ->order_by('nu.nfu_leida', 'ASC')
            ->order_by('n.noti_created_at', 'DESC')
            ->limit((int)$limit)
            ->get()
            ->result_array();
    }

    public function count_no_leidas($usuario_id)
    {
        if (!$this->tablas_existen()) {
            return 0;
        }

        return (int)$this->db
            ->where('nfu_usuario', (string)$usuario_id)
            ->where('nfu_leida', '0')
            ->count_all_results($this->tabla_usuario);
    }

    public function marcar_leida($nfu_id, $usuario_id)
    {
        if (!$this->tablas_existen()) {
            return FALSE;
        }

        return $this->db
            ->where('nfu_id', (int)$nfu_id)
            ->where('nfu_usuario', (string)$usuario_id)
            ->update($this->tabla_usuario, [
                'nfu_leida' => '1',
                'nfu_fecha_leida' => date('Y-m-d H:i:s'),
            ]);
    }

    public function marcar_todas_leidas($usuario_id)
    {
        if (!$this->tablas_existen()) {
            return FALSE;
        }

        return $this->db
            ->where('nfu_usuario', (string)$usuario_id)
            ->where('nfu_leida', '0')
            ->update($this->tabla_usuario, [
                'nfu_leida' => '1',
                'nfu_fecha_leida' => date('Y-m-d H:i:s'),
            ]);
    }
}
