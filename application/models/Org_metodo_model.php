<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Org_metodo_model
 * ─────────────────────────────────────────────────────────────
 * M05: configuración de métodos de pago por organizador.
 *
 * - Un método global se instancia en rif_org_metodos.
 * - Un método propio se crea con met_scope='organizador' y su org.
 * - Los valores se guardan por campo en rif_org_metodo_valores.
 * - rif_rifa_metodos vincula una rifa con las instancias elegidas.
 */
class Org_metodo_model extends CI_Model
{
    protected $table_metodos = 'rif_metodos_pago';
    protected $table_campos  = 'rif_metodo_campos';
    protected $table_org     = 'rif_org_metodos';
    protected $table_valores = 'rif_org_metodo_valores';
    protected $table_rm      = 'rif_rifa_metodos';
    protected $table_rifas   = 'rif_rifas';

    public function __construct()
    {
        parent::__construct();
    }

    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table_metodos)
            && $this->db->table_exists($this->table_campos)
            && $this->db->table_exists($this->table_org)
            && $this->db->table_exists($this->table_valores)
            && $this->db->table_exists($this->table_rm);
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Métodos configurados por el organizador.
     *
     * @param  int $org_id
     * @return array
     */
    public function get_por_org($org_id)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $rows = $this->db
            ->select('om.*, m.met_id, m.met_scope, m.met_nombre, m.met_descripcion, m.met_instrucciones, m.met_status, m.met_orden AS metodo_orden, m.met_eliminado', FALSE)
            ->from($this->table_org . ' om')
            ->join($this->table_metodos . ' m', 'm.met_id = om.orgmet_met_id', 'inner')
            ->where('om.orgmet_org_id', (int)$org_id)
            ->where('m.met_eliminado', 0)
            ->where('m.met_status', 1)
            ->order_by('m.met_scope', 'DESC')
            ->order_by('om.orgmet_orden', 'ASC')
            ->order_by('m.met_nombre', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['campos']   = $this->get_campos((int)$row['met_id'], TRUE);
            $row['valores']  = $this->get_valores_map((int)$row['orgmet_id']);
            $row['completo'] = $this->_completo($row['campos'], $row['valores']);
            $row['es_custom'] = (string)$row['met_scope'] === 'organizador';
        }
        unset($row);

        return $rows;
    }

    /**
     * Globales activos que el organizador todavía no configuró.
     *
     * @param  int $org_id
     * @return array
     */
    public function get_globales_disponibles($org_id)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        $sql = "SELECT m.* FROM {$this->table_metodos} m"
            . " WHERE m.met_scope = 'global'"
            . " AND m.met_status = 1"
            . " AND m.met_eliminado = 0"
            . " AND m.met_id NOT IN ("
            . "   SELECT om.orgmet_met_id FROM {$this->table_org} om"
            . "   WHERE om.orgmet_org_id = ?"
            . " )"
            . " ORDER BY m.met_orden ASC, m.met_nombre ASC";

        return $this->db->query($sql, [(int)$org_id])->result_array();
    }

    /**
     * @param  int $orgmet_id
     * @param  int $org_id
     * @return array|null
     */
    public function get_por_id($orgmet_id, $org_id)
    {
        if (!$this->tabla_disponible()) {
            return NULL;
        }

        $row = $this->db
            ->select('om.*, m.*', FALSE)
            ->from($this->table_org . ' om')
            ->join($this->table_metodos . ' m', 'm.met_id = om.orgmet_met_id', 'inner')
            ->where('om.orgmet_id', (int)$orgmet_id)
            ->where('om.orgmet_org_id', (int)$org_id)
            ->where('m.met_eliminado', 0)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($row)) {
            return NULL;
        }

        $row['campos']   = $this->get_campos((int)$row['met_id'], TRUE);
        $row['valores']  = $this->get_valores_map((int)$row['orgmet_id']);
        $row['completo'] = $this->_completo($row['campos'], $row['valores']);
        $row['es_custom'] = (string)$row['met_scope'] === 'organizador';

        return $row;
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

    public function get_valores_map($orgmet_id)
    {
        if (!$this->db->table_exists($this->table_valores)) {
            return [];
        }

        $rows = $this->db
            ->select('val_campo_id, val_valor')
            ->where('val_orgmet_id', (int)$orgmet_id)
            ->get($this->table_valores)
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['val_campo_id']] = (string)($row['val_valor'] ?? '');
        }

        return $map;
    }

    /**
     * Métodos activos y completos para seleccionar en una rifa.
     *
     * @param  int $org_id
     * @return array
     */
    public function get_para_rifa($org_id)
    {
        $metodos = $this->get_por_org($org_id);
        $salida  = [];

        foreach ($metodos as $metodo) {
            if ((int)$metodo['orgmet_activo'] !== 1) {
                continue;
            }
            if (!$metodo['completo']) {
                continue;
            }

            $salida[] = $metodo;
        }

        return $salida;
    }

    public function get_ids_por_rifa($rifa_id)
    {
        if (!$this->db->table_exists($this->table_rm)) {
            return [];
        }

        $rows = $this->db
            ->select('rm_orgmet_id')
            ->where('rm_rifa_id', (int)$rifa_id)
            ->order_by('rm_orden', 'ASC')
            ->get($this->table_rm)
            ->result_array();

        return array_map(function ($row) {
            return (int)$row['rm_orgmet_id'];
        }, $rows);
    }

    public function get_rifa_metodos($rifa_id)
    {
        if (!$this->tabla_disponible()) {
            return [];
        }

        return $this->db
            ->select('rm.rm_orgmet_id, rm.rm_orden, om.orgmet_activo, om.orgmet_org_id,'
                . ' m.met_id, m.met_nombre, m.met_descripcion, m.met_scope, m.met_status, m.met_eliminado', FALSE)
            ->from($this->table_rm . ' rm')
            ->join($this->table_org . ' om', 'om.orgmet_id = rm.rm_orgmet_id', 'inner')
            ->join($this->table_metodos . ' m', 'm.met_id = om.orgmet_met_id', 'inner')
            ->where('rm.rm_rifa_id', (int)$rifa_id)
            ->order_by('rm.rm_orden', 'ASC')
            ->get()
            ->result_array();
    }

    // ─────────────────────────────────────────────────────────
    // CONFIGURACIÓN
    // ─────────────────────────────────────────────────────────

    /**
     * Crea la instancia de un método global para el organizador.
     *
     * @param  int $org_id
     * @param  int $met_id
     * @return int|false
     */
    public function configurar_global($org_id, $met_id)
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $metodo = $this->db
            ->where('met_id', (int)$met_id)
            ->where('met_scope', 'global')
            ->where('met_status', 1)
            ->where('met_eliminado', 0)
            ->limit(1)
            ->get($this->table_metodos)
            ->row_array();

        if (empty($metodo)) {
            return FALSE;
        }

        $existente = $this->db
            ->where('orgmet_org_id', (int)$org_id)
            ->where('orgmet_met_id', (int)$met_id)
            ->limit(1)
            ->get($this->table_org)
            ->row_array();

        if (!empty($existente)) {
            return (int)$existente['orgmet_id'];
        }

        $ahora = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        try {
            $debug_previo = $this->db->db_debug;
            $this->db->db_debug = FALSE;

            try {
                $ok    = $this->db->insert($this->table_org, [
                    'orgmet_org_id'   => (int)$org_id,
                    'orgmet_met_id'   => (int)$met_id,
                    'orgmet_activo'   => 1,
                    'orgmet_orden'    => (int)($metodo['met_orden'] ?? 1),
                    'orgmet_created_at' => $ahora,
                    'orgmet_updated_at' => $ahora,
                ]);
                $error = $this->db->error();
            } catch (Throwable $e) {
                $ok    = FALSE;
                $error = ['code' => -1, 'message' => ''];
            } finally {
                $this->db->db_debug = $debug_previo;
            }

            if (!$ok || !empty($error['code'])) {
                $this->db->trans_rollback();

                // Carrera: otra petición pudo crear la misma instancia.
                $existente = $this->db
                    ->where('orgmet_org_id', (int)$org_id)
                    ->where('orgmet_met_id', (int)$met_id)
                    ->limit(1)
                    ->get($this->table_org)
                    ->row_array();

                return !empty($existente) ? (int)$existente['orgmet_id'] : FALSE;
            }

            $new_id = (int)$this->db->insert_id();

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return $new_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Org_metodo_model::configurar_global no pudo crear la instancia.');
            return FALSE;
        }
    }

    /**
     * @param  int   $org_id
     * @param  array $data
     * @param  array $campos
     * @param  array $valores
     * @return int|false
     */
    public function crear_custom($org_id, array $data, array $campos, array $valores = [])
    {
        if (!$this->tabla_disponible()) {
            return FALSE;
        }

        $ahora = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        try {
            $ok = $this->db->insert($this->table_metodos, [
                'met_scope'         => 'organizador',
                'met_org_id'        => (int)$org_id,
                'met_nombre'        => trim((string)($data['met_nombre'] ?? '')),
                'met_descripcion'   => trim((string)($data['met_descripcion'] ?? '')) ?: NULL,
                'met_instrucciones' => trim((string)($data['met_instrucciones'] ?? '')) ?: NULL,
                'met_status'        => 1,
                'met_orden'         => (int)($data['met_orden'] ?? 1),
                'met_created_by'    => isset($data['met_created_by']) ? (int)$data['met_created_by'] : NULL,
                'met_eliminado'     => 0,
                'met_created_at'    => $ahora,
                'met_updated_at'    => $ahora,
            ]);

            if (!$ok) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $met_id = (int)$this->db->insert_id();

            $this->db->insert($this->table_org, [
                'orgmet_org_id'     => (int)$org_id,
                'orgmet_met_id'     => $met_id,
                'orgmet_activo'     => 1,
                'orgmet_orden'      => (int)($data['met_orden'] ?? 1),
                'orgmet_created_at' => $ahora,
                'orgmet_updated_at' => $ahora,
            ]);
            $orgmet_id = (int)$this->db->insert_id();

            if (!$this->guardar_campos($met_id, $campos)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (!empty($valores)) {
                $validacion = $this->validar_valores($met_id, $valores);
                if (!$validacion['ok']) {
                    $this->db->trans_rollback();
                    return FALSE;
                }

                $this->_upsert_valores($orgmet_id, $validacion['valores']);
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return $orgmet_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Org_metodo_model::crear_custom no pudo crear el método.');
            return FALSE;
        }
    }

    /**
     * @param  int   $orgmet_id
     * @param  int   $org_id
     * @param  array $data
     * @param  array $campos
     * @param  array $valores
     * @return bool
     */
    public function actualizar_custom($orgmet_id, $org_id, array $data, array $campos, array $valores = [])
    {
        $actual = $this->get_por_id($orgmet_id, $org_id);
        if (empty($actual) || (string)$actual['met_scope'] !== 'organizador') {
            return FALSE;
        }

        $met_id = (int)$actual['met_id'];
        $ahora  = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        try {
            $ok = $this->db->where('met_id', $met_id)->update($this->table_metodos, [
                'met_nombre'        => trim((string)($data['met_nombre'] ?? '')),
                'met_descripcion'   => trim((string)($data['met_descripcion'] ?? '')) ?: NULL,
                'met_instrucciones' => trim((string)($data['met_instrucciones'] ?? '')) ?: NULL,
                'met_orden'         => (int)($data['met_orden'] ?? 1),
                'met_updated_at'    => $ahora,
            ]);

            if (!$ok || !$this->guardar_campos($met_id, $campos)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (!empty($valores)) {
                $validacion = $this->validar_valores($met_id, $valores);
                if (!$validacion['ok']) {
                    $this->db->trans_rollback();
                    return FALSE;
                }

                $this->_upsert_valores((int)$orgmet_id, $validacion['valores']);
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Org_metodo_model::actualizar_custom no pudo actualizar.');
            return FALSE;
        }
    }

    /**
     * @param  int   $orgmet_id
     * @param  int   $org_id
     * @param  array $valores
     * @return array
     */
    public function guardar_valores($orgmet_id, $org_id, array $valores)
    {
        $actual = $this->get_por_id($orgmet_id, $org_id);
        if (empty($actual)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        $validacion = $this->validar_valores((int)$actual['met_id'], $valores);
        if (!$validacion['ok']) {
            return ['ok' => FALSE, 'motivo' => 'validacion', 'errores' => $validacion['errores']];
        }

        $this->db->trans_begin();

        try {
            $this->_upsert_valores((int)$orgmet_id, $validacion['valores']);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    public function toggle_activo($orgmet_id, $org_id)
    {
        $actual = $this->get_por_id($orgmet_id, $org_id);
        if (empty($actual)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        $nuevo = ((int)$actual['orgmet_activo'] === 1) ? 0 : 1;

        if ($nuevo === 0 && $this->_rifas_activas($orgmet_id) > 0) {
            return ['ok' => FALSE, 'motivo' => 'en_uso'];
        }

        $ok = $this->db
            ->where('orgmet_id', (int)$orgmet_id)
            ->where('orgmet_org_id', (int)$org_id)
            ->update($this->table_org, [
                'orgmet_activo'     => $nuevo,
                'orgmet_updated_at' => date('Y-m-d H:i:s'),
            ]);

        return ['ok' => (bool)$ok, 'nuevo_status' => $nuevo];
    }

    public function eliminar_custom($orgmet_id, $org_id)
    {
        $actual = $this->get_por_id($orgmet_id, $org_id);
        if (empty($actual) || (string)$actual['met_scope'] !== 'organizador') {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        if ($this->_rifas_vinculadas($orgmet_id) > 0) {
            return ['ok' => FALSE, 'motivo' => 'en_uso'];
        }

        $this->db->trans_begin();

        try {
            $this->db->where('met_id', (int)$actual['met_id'])->update($this->table_metodos, [
                'met_eliminado'  => 1,
                'met_status'     => 0,
                'met_updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->where('campo_met_id', (int)$actual['met_id'])->update($this->table_campos, [
                'campo_status' => 0,
            ]);
            $this->db->where('orgmet_id', (int)$orgmet_id)->delete($this->table_org);

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    // ─────────────────────────────────────────────────────────
    // RIFAS
    // ─────────────────────────────────────────────────────────

    /**
     * Reemplaza los métodos seleccionados de una rifa.
     *
     * @param  int   $rifa_id
     * @param  int   $org_id
     * @param  array $ids
     * @return array
     */
    public function sincronizar_rifa($rifa_id, $org_id, array $ids)
    {
        if (!$this->tabla_disponible()) {
            return ['ok' => FALSE, 'motivo' => 'no_disponible'];
        }

        $rifa = $this->db
            ->select('rifa_id')
            ->where('rifa_id', (int)$rifa_id)
            ->where('rifa_org_id', (int)$org_id)
            ->where('rifa_eliminado', 0)
            ->limit(1)
            ->get($this->table_rifas)
            ->row_array();

        if (empty($rifa)) {
            return ['ok' => FALSE, 'motivo' => 'no_encontrada'];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));

        $invalidos = [];

        foreach ($ids as $id) {
            $metodo = $this->get_por_id($id, $org_id);
            if (empty($metodo)
                || (int)$metodo['orgmet_activo'] !== 1
                || (int)$metodo['met_status'] !== 1
                || (int)$metodo['met_eliminado'] !== 0
                || !$metodo['completo']) {
                $invalidos[] = $id;
            }
        }

        if (!empty($invalidos)) {
            return ['ok' => FALSE, 'motivo' => 'metodos_invalidos', 'invalidos' => $invalidos];
        }

        $this->db->trans_begin();

        try {
            if (empty($ids)) {
                $this->db->where('rm_rifa_id', (int)$rifa_id)->delete($this->table_rm);
            } else {
                $this->db
                    ->where('rm_rifa_id', (int)$rifa_id)
                    ->where_not_in('rm_orgmet_id', $ids)
                    ->delete($this->table_rm);

                $existentes = $this->db
                    ->select('rm_orgmet_id')
                    ->where('rm_rifa_id', (int)$rifa_id)
                    ->get($this->table_rm)
                    ->result_array();
                $set = [];
                foreach ($existentes as $row) {
                    $set[(int)$row['rm_orgmet_id']] = TRUE;
                }

                $orden = 1;
                foreach ($ids as $id) {
                    if (isset($set[$id])) {
                        $orden++;
                        continue;
                    }

                    $this->db->insert($this->table_rm, [
                        'rm_rifa_id'    => (int)$rifa_id,
                        'rm_orgmet_id'  => (int)$id,
                        'rm_orden'      => $orden,
                        'rm_created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $orden++;
                }
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['ok' => FALSE, 'motivo' => 'error'];
            }

            $this->db->trans_commit();
            return ['ok' => TRUE, 'cantidad' => count($ids)];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => FALSE, 'motivo' => 'error'];
        }
    }

    public function contar_rifa_activos_completos($rifa_id)
    {
        $contador = 0;

        foreach ($this->get_rifa_metodos($rifa_id) as $metodo) {
            if ((int)$metodo['orgmet_activo'] === 1
                && (int)$metodo['met_status'] === 1
                && (int)$metodo['met_eliminado'] === 0) {
                $completo = $this->_completo(
                    $this->get_campos((int)$metodo['met_id'], TRUE),
                    $this->get_valores_map((int)$metodo['rm_orgmet_id'])
                );

                if ($completo) {
                    $contador++;
                }
            }
        }

        return $contador;
    }

    // ─────────────────────────────────────────────────────────
    // CAMPOS Y VALORES
    // ─────────────────────────────────────────────────────────

    public function tiene_rifas_activas($orgmet_id)
    {
        return $this->_rifas_activas($orgmet_id) > 0;
    }

    /**
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

            $clave = $this->slug_clave((string)($campo['campo_clave'] ?? ''));
            if ($clave === '') {
                $clave = $this->slug_clave($etiqueta);
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

        $this->db->where('campo_met_id', (int)$met_id);
        if (!empty($ids_presentes)) {
            $this->db->where_not_in('campo_id', $ids_presentes);
        }
        $this->db->update($this->table_campos, ['campo_status' => 0]);

        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Valida y normaliza valores por campo.
     *
     * @param  int   $met_id
     * @param  array $valores
     * @return array
     */
    public function validar_valores($met_id, array $valores)
    {
        $campos = $this->get_campos((int)$met_id, TRUE);
        $salida = [];
        $errores = [];

        foreach ($campos as $campo) {
            $campo_id = (int)$campo['campo_id'];
            $valor = isset($valores[$campo_id]) && is_scalar($valores[$campo_id])
                ? (string)$valores[$campo_id]
                : '';

            $valor = trim($valor);
            $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $valor);

            if ((int)$campo['campo_requerido'] === 1 && $valor === '') {
                $errores['campo_' . $campo_id] = 'El campo "' . $campo['campo_etiqueta'] . '" es obligatorio.';
                continue;
            }

            if ($valor !== '') {
                $tipo = (string)$campo['campo_tipo'];

                if ($tipo === 'email' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    $errores['campo_' . $campo_id] = 'El campo "' . $campo['campo_etiqueta'] . '" debe ser un correo válido.';
                } elseif ($tipo === 'url' && !filter_var($valor, FILTER_VALIDATE_URL)) {
                    $errores['campo_' . $campo_id] = 'El campo "' . $campo['campo_etiqueta'] . '" debe ser una URL válida.';
                } elseif ($tipo === 'number' && !is_numeric($valor)) {
                    $errores['campo_' . $campo_id] = 'El campo "' . $campo['campo_etiqueta'] . '" debe ser numérico.';
                } elseif ($tipo === 'select') {
                    $opciones = array_filter(array_map('trim', preg_split('/\r?\n/', (string)$campo['campo_opciones'])));
                    if (!in_array($valor, $opciones, TRUE)) {
                        $errores['campo_' . $campo_id] = 'Selecciona una opción válida para "' . $campo['campo_etiqueta'] . '".';
                    }
                }

                $max = ($tipo === 'textarea') ? 2000 : (($tipo === 'text') ? 500 : 255);
                if (!isset($errores['campo_' . $campo_id]) && $this->_longitud($valor) > $max) {
                    $errores['campo_' . $campo_id] = 'El campo "' . $campo['campo_etiqueta'] . '" excede la longitud permitida.';
                }
            }

            $salida[$campo_id] = $valor !== '' ? $valor : NULL;
        }

        return [
            'ok'      => empty($errores),
            'valores' => $salida,
            'errores' => $errores,
        ];
    }

    private function _upsert_valores($orgmet_id, array $valores)
    {
        $ahora = date('Y-m-d H:i:s');

        foreach ($valores as $campo_id => $valor) {
            $existente = $this->db
                ->select('val_id')
                ->where('val_orgmet_id', (int)$orgmet_id)
                ->where('val_campo_id', (int)$campo_id)
                ->limit(1)
                ->get($this->table_valores)
                ->row_array();

            if (!empty($existente)) {
                $this->db->where('val_id', (int)$existente['val_id'])->update($this->table_valores, [
                    'val_valor'      => $valor,
                    'val_updated_at' => $ahora,
                ]);
            } else {
                $this->db->insert($this->table_valores, [
                    'val_orgmet_id' => (int)$orgmet_id,
                    'val_campo_id'  => (int)$campo_id,
                    'val_valor'     => $valor,
                    'val_created_at' => $ahora,
                    'val_updated_at' => $ahora,
                ]);
            }
        }
    }

    private function _completo(array $campos, array $valores)
    {
        foreach ($campos as $campo) {
            if ((int)$campo['campo_requerido'] !== 1) {
                continue;
            }

            $campo_id = (int)$campo['campo_id'];
            if (!isset($valores[$campo_id]) || trim((string)$valores[$campo_id]) === '') {
                return FALSE;
            }
        }

        return TRUE;
    }

    private function _rifas_vinculadas($orgmet_id)
    {
        if (!$this->db->table_exists($this->table_rm)) {
            return 0;
        }

        return (int)$this->db
            ->where('rm_orgmet_id', (int)$orgmet_id)
            ->count_all_results($this->table_rm);
    }

    private function _rifas_activas($orgmet_id)
    {
        if (!$this->db->table_exists($this->table_rm)
            || !$this->db->table_exists($this->table_rifas)) {
            return 0;
        }

        return (int)$this->db
            ->from($this->table_rm . ' rm')
            ->join($this->table_rifas . ' r', 'r.rifa_id = rm.rm_rifa_id', 'inner')
            ->where('rm.rm_orgmet_id', (int)$orgmet_id)
            ->where('r.rifa_eliminado', 0)
            ->where_not_in('r.rifa_estado', ['finalizada', 'cancelada', 'rechazada'])
            ->count_all_results();
    }

    public function slug_clave($texto)
    {
        $texto = strtolower(trim((string)$texto));
        $texto = preg_replace('/[^a-z0-9]+/', '_', $texto);
        $texto = trim((string)$texto, '_');

        return substr($texto, 0, 60);
    }

    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
