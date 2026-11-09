<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Correos_promocionales_model extends CI_Model
{
    private $tabla = 'cab_correos_promocionales';
    private $tabla_destinatarios = 'cab_correos_promocionales_destinatarios';
    private $tabla_grupos = 'cab_correo_grupos';
    private $tabla_grupos_usuarios = 'det_correo_grupos_usuarios';

    public function tablas_existen()
    {
        return $this->db->table_exists($this->tabla) && $this->db->table_exists($this->tabla_destinatarios);
    }

    public function asegurar_tablas_grupos()
    {
        if (!$this->db->table_exists($this->tabla_grupos)) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `cab_correo_grupos` (
              `cgr_id` int(11) NOT NULL AUTO_INCREMENT,
              `cgr_nombre` varchar(100) NOT NULL,
              `cgr_descripcion` varchar(255) DEFAULT NULL,
              `cgr_color` varchar(20) NOT NULL DEFAULT 'primary',
              `cgr_status` tinyint(1) NOT NULL DEFAULT 1,
              `cgr_eliminado` tinyint(1) NOT NULL DEFAULT 0,
              `cgr_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `cgr_updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`cgr_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if (!$this->db->table_exists($this->tabla_grupos_usuarios)) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `det_correo_grupos_usuarios` (
              `dgu_id` int(11) NOT NULL AUTO_INCREMENT,
              `dgu_grupo_id` int(11) NOT NULL,
              `dgu_usuario_id` varchar(50) NOT NULL,
              `dgu_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`dgu_id`),
              UNIQUE KEY `uk_grupo_usuario` (`dgu_grupo_id`, `dgu_usuario_id`),
              KEY `idx_grupo_id` (`dgu_grupo_id`),
              KEY `idx_usuario_id` (`dgu_usuario_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } else {
            // Asegurar que dgu_usuario_id sea VARCHAR(50) para IDs con ceros a la izquierda tipo "000001"
            $campo = $this->db->query("SHOW COLUMNS FROM `det_correo_grupos_usuarios` LIKE 'dgu_usuario_id'")->row_array();
            if ($campo && stripos($campo['Type'], 'varchar') === FALSE) {
                $this->db->query("ALTER TABLE `det_correo_grupos_usuarios` MODIFY COLUMN `dgu_usuario_id` VARCHAR(50) NOT NULL;");
            }
        }
    }

    public function get_grupos()
    {
        $this->asegurar_tablas_grupos();
        if (!$this->db->table_exists($this->tabla_grupos)) {
            return [];
        }

        return $this->db
            ->select('g.cgr_id, g.cgr_nombre, g.cgr_descripcion, g.cgr_color, g.cgr_status, COUNT(d.dgu_usuario_id) AS total_usuarios')
            ->from($this->tabla_grupos . ' g')
            ->join($this->tabla_grupos_usuarios . ' d', 'd.dgu_grupo_id = g.cgr_id', 'left')
            ->where('g.cgr_eliminado', 0)
            ->group_by('g.cgr_id')
            ->order_by('g.cgr_nombre', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_grupo($id)
    {
        $this->asegurar_tablas_grupos();
        $grupo = $this->db
            ->where('cgr_id', (int)$id)
            ->where('cgr_eliminado', 0)
            ->get($this->tabla_grupos)
            ->row_array();

        if (!$grupo) {
            return NULL;
        }

        $rows = $this->db
            ->select('dgu_usuario_id')
            ->where('dgu_grupo_id', (int)$id)
            ->get($this->tabla_grupos_usuarios)
            ->result_array();

        $grupo['usuarios_ids'] = array_values(array_map('strval', array_column($rows, 'dgu_usuario_id')));
        return $grupo;
    }

    public function get_grupo_usuarios_ids($grupo_id)
    {
        $this->asegurar_tablas_grupos();
        $rows = $this->db
            ->select('dgu_usuario_id')
            ->where('dgu_grupo_id', (int)$grupo_id)
            ->get($this->tabla_grupos_usuarios)
            ->result_array();

        return array_values(array_map('strval', array_column($rows, 'dgu_usuario_id')));
    }

    public function guardar_grupo(array $data, array $usuarios_ids = [], $id = 0)
    {
        $this->asegurar_tablas_grupos();
        $id = (int)$id;
        $this->db->trans_start();

        if ($id > 0) {
            $data['cgr_updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('cgr_id', $id)->update($this->tabla_grupos, $data);
            $grupo_id = $id;
            $this->db->where('dgu_grupo_id', $grupo_id)->delete($this->tabla_grupos_usuarios);
        } else {
            $data['cgr_created_at'] = date('Y-m-d H:i:s');
            $data['cgr_updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert($this->tabla_grupos, $data);
            $grupo_id = (int)$this->db->insert_id();
        }

        if ($grupo_id > 0 && !empty($usuarios_ids)) {
            $batch = [];
            $now = date('Y-m-d H:i:s');
            foreach (array_unique($usuarios_ids) as $uid) {
                $uid = trim((string)$uid);
                if ($uid !== '') {
                    $batch[] = [
                        'dgu_grupo_id'   => $grupo_id,
                        'dgu_usuario_id' => $uid,
                        'dgu_created_at' => $now,
                    ];
                }
            }
            if (!empty($batch)) {
                $this->db->insert_batch($this->tabla_grupos_usuarios, $batch);
            }
        }

        $this->db->trans_complete();
        return $this->db->trans_status() ? $grupo_id : 0;
    }

    public function eliminar_grupo($id)
    {
        $this->asegurar_tablas_grupos();
        return $this->db
            ->where('cgr_id', (int)$id)
            ->update($this->tabla_grupos, [
                'cgr_eliminado'  => 1,
                'cgr_updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function crear_campana(array $data)
    {
        $data['cpr_created_at'] = date('Y-m-d H:i:s');
        $data['cpr_updated_at'] = date('Y-m-d H:i:s');

        if ($this->db->insert($this->tabla, $data)) {
            return (int)$this->db->insert_id();
        }

        return 0;
    }

    public function registrar_destinatario(array $data)
    {
        $data['cpd_created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert($this->tabla_destinatarios, $data);
    }

    public function actualizar_resumen($campana_id, $enviados, $fallidos, $estado)
    {
        return $this->db
            ->where('cpr_id', (int)$campana_id)
            ->update($this->tabla, [
                'cpr_total_enviados' => (int)$enviados,
                'cpr_total_fallidos' => (int)$fallidos,
                'cpr_estado' => (string)$estado,
                'cpr_updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function get_historial($limit = 10)
    {
        return $this->db
            ->select('cpr_id, cpr_asunto, cpr_template, cpr_from_email, cpr_from_name, cpr_total_destinatarios, cpr_total_enviados, cpr_total_fallidos, cpr_estado, cpr_usuario, cpr_created_at')
            ->from($this->tabla)
            ->order_by('cpr_id', 'DESC')
            ->limit((int)$limit)
            ->get()
            ->result_array();
    }
}