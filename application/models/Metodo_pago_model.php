<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Metodo_pago_model
 * ─────────────────────────────────────────────────────────────
 * M05: catálogo global de métodos de pago y sus campos.
 *
 * Los métodos globales son plantillas administradas por el rol master.
 * Cada organizador crea una instancia en rif_org_metodos y completa sus
 * valores; los métodos propios usan met_scope='organizador'.
 */
class Metodo_pago_model extends CI_Model
{
    protected $table       = 'rif_metodos_pago';
    protected $table_campos = 'rif_metodo_campos';
    protected $table_org   = 'rif_org_metodos';
    protected $table_rm    = 'rif_rifa_metodos';
    protected $table_rifas = 'rif_rifas';

    protected $columnas_guardado = [
        'met_scope',
        'met_org_id',
        'met_nombre',
        'met_descripcion',
        'met_instrucciones',
        'met_icono',
        'met_imagen',
        'met_status',
        'met_orden',
        'met_created_by',
        'met_eliminado',
    ];

    protected $columnas_ordenables = [
        0 => 'm.met_nombre',
        1 => 'campos_count',
        2 => 'orgs_count',
        3 => 'm.met_status',
        4 => 'm.met_orden',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists($this->table_campos);
    }

    // ─────────────────────────────────────────────────────────
    // DATATABLES (globales)
    // ─────────────────────────────────────────────────────────

    public function count_todos()
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_query_datatable('');
        return (int)$this->db->count_all_results();
    }

    public function count_filtrados($search = '')
    {
        if (!$this->tabla_disponible()) {
            return 0;
        }

        $this->_query_datatable($search);
        return (int)$this->db->count_all_results();
    }

    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $this->_query_datatable($search);
        $this->db->select('m.*, ' . $this->_sub_campos() . ' AS campos_count, ' . $this->_sub_orgs() . ' AS orgs_count', FALSE);

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'm.met_orden';
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, max(0, (int)$start));
        }

        return $this->db->get()->result_array();
    }

    private function _query_datatable($search = '')
    {
        $this->db->from($this->table . ' m');
        $this->db->where('m.met_scope', 'global');
        $this->db->where('m.met_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('m.met_nombre', $search);
            $this->db->or_like('m.met_descripcion', $search);
            $this->db->or_like('m.met_instrucciones', $search);
            $this->db->group_end();
        }
    }

    private function _sub_campos()
    {
        return "(SELECT COUNT(*) FROM {$this->table_campos} c"
            . " WHERE c.campo_met_id = m.met_id AND c.campo_status = 1)";
    }

    private function _sub_orgs()
    {
        return "(SELECT COUNT(*) FROM {$this->table_org} o"
            . " WHERE o.orgmet_met_id = m.met_id)";
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    public function get_global_por_id($id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        return $this->db
            ->select('m.*, ' . $this->_sub_campos() . ' AS campos_count, ' . $this->_sub_orgs() . ' AS orgs_count', FALSE)
            ->where('m.met_id', (int)$id)
            ->where('m.met_scope', 'global')
            ->where('m.met_eliminado', 0)
            ->limit(1)
            ->get($this->table . ' m')
            ->row_array();
    }

    public function get_globales_activos()
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        return $this->db
            ->select('m.*, ' . $this->_sub_campos() . ' AS campos_count', FALSE)
            ->where('m.met_scope', 'global')
            ->where('m.met_status', 1)
            ->where('m.met_eliminado', 0)
            ->order_by('m.met_orden', 'ASC')
            ->order_by('m.met_nombre', 'ASC')
            ->get($this->table . ' m')
            ->result_array();
    }

    public function get_campos($met_id, $solo_activos = TRUE)
    {
        if (!$this->db->table_exists($this->table_campos)) {
            return [];
        }

        $this->db->where('campo_met_id', (int)$met_id);
        if ($solo_activos) {
            $this->db->where('campo_status', 1);
        }

        return $this->db
            ->order_by('campo_orden', 'ASC')
            ->order_by('campo_id', 'ASC')
            ->get($this->table_campos)
            ->result_array();
    }

    public function contar_org_metodos($met_id)
    {
        if (!$this->db->table_exists($this->table_org)) {
            return 0;
        }

        return (int)$this->db
            ->where('orgmet_met_id', (int)$met_id)
            ->count_all_results($this->table_org);
    }

    public function tiene_rifas_activas($met_id)
    {
        if (!$this->db->table_exists($this->table_rm)
            || !$this->db->table_exists($this->table_org)
            || !$this->db->table_exists($this->table_rifas)) {
            return FALSE;
        }

        return $this->db
            ->from($this->table_rm . ' rm')
            ->join($this->table_org . ' om', 'om.orgmet_id = rm.rm_orgmet_id', 'inner')
            ->join($this->table_rifas . ' r', 'r.rifa_id = rm.rm_rifa_id', 'inner')
            ->where('om.orgmet_met_id', (int)$met_id)
            ->where('r.rifa_eliminado', 0)
            ->where_not_in('r.rifa_estado', ['finalizada', 'cancelada', 'rechazada'])
            ->count_all_results() > 0;
    }

    public function esta_en_uso($met_id)
    {
        return $this->contar_org_metodos($met_id) > 0
            || $this->tiene_rifas_activas($met_id);
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES GLOBALES
    // ─────────────────────────────────────────────────────────

    public function crear_global(array $data, array $campos, $actor_id = NULL)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $data['met_scope'] = 'global';
        $data['met_org_id'] = NULL;
        $data['met_created_by'] = $actor_id !== NULL ? (int)$actor_id : NULL;
        $data['met_eliminado'] = 0;

        $guardado = $this->_filtrar_guardado($data);
        if (empty($guardado['met_nombre'])) {
            return FALSE;
        }

        $ahora = date('Y-m-d H:i:s');
        $guardado['met_created_at'] = $ahora;
        $guardado['met_updated_at'] = $ahora;

        $this->db->trans_begin();

        try {
            if (!$this->db->insert($this->table, $guardado)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $met_id = (int)$this->db->insert_id();
            if (!$this->guardar_campos($met_id, $campos)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return $met_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Metodo_pago_model::crear_global no pudo crear el método.');
            return FALSE;
        }
    }

    public function actualizar_global($id, array $data, array $campos)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $actual = $this->get_global_por_id($id);
        if (empty($actual)) {
            return FALSE;
        }

        $guardado = $this->_filtrar_guardado($data);
        $guardado['met_scope'] = 'global';
        $guardado['met_org_id'] = NULL;
        $guardado['met_updated_at'] = date('Y-m-d H:i:s');
        unset($guardado['met_eliminado'], $guardado['met_status']);

        $this->db->trans_begin();

        try {
            if (!$this->db->where('met_id', (int)$id)->update($this->table, $guardado)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (!$this->guardar_campos((int)$id, $campos)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Metodo_pago_model::actualizar_global no pudo actualizar el método.');
            return FALSE;
        }
    }

    public function eliminar_global($id)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $actual = $this->get_global_por_id($id);
        if (empty($actual) || $this->esta_en_uso($id)) {
            return FALSE;
        }

        $this->db->trans_begin();

        try {
            $ok = $this->db
                ->where('met_id', (int)$id)
                ->where('met_scope', 'global')
                ->update($this->table, [
                    'met_eliminado'  => 1,
                    'met_status'     => 0,
                    'met_updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->db->where('campo_met_id', (int)$id)->update($this->table_campos, ['campo_status' => 0]);

            if (!$ok || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return FALSE;
        }
    }

    public function toggle_global($id)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'metodo' => NULL, 'nuevo_status' => NULL];
        }

        $actual = $this->get_global_por_id($id);
        if (empty($actual)) {
            return ['ok' => FALSE, 'metodo' => NULL, 'nuevo_status' => NULL];
        }

        $nuevo = ((int)$actual['met_status'] === 1) ? 0 : 1;

        if ($nuevo === 0
            && ($this->tiene_rifas_activas($id) || $this->contar_org_metodos($id) > 0)) {
            return ['ok' => FALSE, 'metodo' => $actual, 'nuevo_status' => $nuevo];
        }

        $ok = $this->db
            ->where('met_id', (int)$id)
            ->where('met_scope', 'global')
            ->update($this->table, [
                'met_status'     => $nuevo,
                'met_updated_at' => date('Y-m-d H:i:s'),
            ]);

        return ['ok' => (bool)$ok, 'metodo' => $actual, 'nuevo_status' => $nuevo];
    }

    // ─────────────────────────────────────────────────────────
    // CAMPOS
    // ─────────────────────────────────────────────────────────

    /**
     * Guarda los campos enviados. Los campos no enviados se desactivan
     * para conservar valores históricos.
     *
     * @param  int   $met_id
     * @param  array $campos
     * @return bool
     */
    public function guardar_campos($met_id, array $campos)
    {
        if (!$this->db->table_exists($this->table_campos)) {
            return FALSE;
        }

        $existentes = [];
        foreach ($this->get_campos($met_id, FALSE) as $campo) {
            $existentes[(int)$campo['campo_id']] = $campo;
        }

        $claves = [];
        $ids_presentes = [];
        $ahora = date('Y-m-d H:i:s');

        foreach ($campos as $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $campo_id = (int)($campo['campo_id'] ?? 0);
            $etiqueta = trim((string)($campo['campo_etiqueta'] ?? ''));
            $tipo     = (string)($campo['campo_tipo'] ?? 'text');

            if ($etiqueta === '') {
                continue;
            }

            if (!in_array($tipo, ['text', 'email', 'tel', 'number', 'url', 'textarea', 'select'], TRUE)) {
                $tipo = 'text';
            }

            $clave = $this->_slug_clave((string)($campo['campo_clave'] ?? ''));
            if ($clave === '') {
                $clave = $this->_slug_clave($etiqueta);
            }
            if ($clave === '') {
                $clave = 'campo';
            }

            $base = $clave;
            $i = 2;
            while (isset($claves[$clave])) {
                $clave = $base . '_' . $i;
                $i++;
            }
            $claves[$clave] = TRUE;

            $data = [
                'campo_clave'       => $clave,
                'campo_etiqueta'    => $etiqueta,
                'campo_tipo'        => $tipo,
                'campo_requerido'   => (int)($campo['campo_requerido'] ?? 0) === 1 ? 1 : 0,
                'campo_placeholder' => trim((string)($campo['campo_placeholder'] ?? '')) ?: NULL,
                'campo_ayuda'       => trim((string)($campo['campo_ayuda'] ?? '')) ?: NULL,
                'campo_opciones'    => $tipo === 'select' ? trim((string)($campo['campo_opciones'] ?? '')) : NULL,
                'campo_orden'       => (int)($campo['campo_orden'] ?? 1),
                'campo_status'      => 1,
                'campo_updated_at'  => $ahora,
            ];

            if ($campo_id > 0 && isset($existentes[$campo_id])) {
                $this->db->where('campo_id', $campo_id)->update($this->table_campos, $data);
                $ids_presentes[] = $campo_id;
            } else {
                $data['campo_met_id'] = (int)$met_id;
                $data['campo_created_at'] = $ahora;
                $this->db->insert($this->table_campos, $data);
                $ids_presentes[] = (int)$this->db->insert_id();
            }
        }

        // Desactivar campos que ya no vienen en el formulario.
        $this->db->where('campo_met_id', (int)$met_id);
        if (!empty($ids_presentes)) {
            $this->db->where_not_in('campo_id', $ids_presentes);
        }
        $this->db->update($this->table_campos, ['campo_status' => 0]);

        return $this->db->trans_status() !== FALSE;
    }

    public function contar_campos($met_id)
    {
        return count($this->get_campos($met_id, TRUE));
    }

    /**
     * Detecta si la definición de campos activos cambió.
     *
     * @param  int   $met_id
     * @param  array $campos
     * @return bool
     */
    public function campos_cambiaron($met_id, array $campos)
    {
        $actuales = $this->get_campos($met_id, TRUE);
        if (count($actuales) !== count($campos)) {
            return TRUE;
        }

        $mapa = [];
        foreach ($actuales as $campo) {
            $mapa[(int)$campo['campo_id']] = $campo;
        }

        foreach ($campos as $campo) {
            $id = (int)($campo['campo_id'] ?? 0);
            if ($id <= 0 || !isset($mapa[$id])) {
                return TRUE;
            }

            $actual = $mapa[$id];
            $pares = [
                'campo_etiqueta'    => 'campo_etiqueta',
                'campo_tipo'        => 'campo_tipo',
                'campo_requerido'   => 'campo_requerido',
                'campo_placeholder' => 'campo_placeholder',
                'campo_ayuda'       => 'campo_ayuda',
                'campo_opciones'    => 'campo_opciones',
                'campo_orden'       => 'campo_orden',
            ];

            foreach ($pares as $entrada => $columna) {
                $nuevo = $campo[$entrada] ?? '';
                $viejo = $actual[$columna] ?? '';

                if (in_array($entrada, ['campo_requerido', 'campo_orden'], TRUE)) {
                    if ((int)$nuevo !== (int)$viejo) {
                        return TRUE;
                    }
                } elseif ((string)$nuevo !== (string)$viejo) {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────

    public function _slug_clave($texto)
    {
        $texto = strtolower(trim((string)$texto));
        $texto = preg_replace('/[^a-z0-9]+/', '_', $texto);
        $texto = trim((string)$texto, '_');

        return substr($texto, 0, 60);
    }

    private function _filtrar_guardado(array $data)
    {
        return array_intersect_key($data, array_flip($this->columnas_guardado));
    }
}
