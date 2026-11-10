<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rifa_imagen_model
 * ─────────────────────────────────────────────────────────────
 * Galería de imágenes por rifa (M03.2).
 *
 * - Tabla: rif_rifa_imagenes (img_id).
 * - Máximo 3 imágenes por rifa, una sola principal.
 * - rif_rifas.rifa_imagen se conserva como portada legacy y se sincroniza
 *   con la imagen principal de la galería.
 * - Las mutaciones se ejecutan en transacción con rollback controlado.
 */
class Rifa_imagen_model extends CI_Model
{
    protected $table       = 'rif_rifa_imagenes';
    protected $table_rifa  = 'rif_rifas';
    protected $primary_key = 'img_id';
    protected $max_imagenes = 3;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Indica si la tabla de galería está disponible.
     *
     * @return bool
     */
    public function tabla_disponible()
    {
        return $this->db->table_exists($this->table);
    }

    /**
     * Lista las imágenes de una rifa: principal primero y luego por orden.
     *
     * @param  int $rifa_id
     * @return array
     */
    public function get_por_rifa($rifa_id)
    {
        if (!$this->tabla_disponible() || (int)$rifa_id <= 0) {
            return [];
        }

        return $this->db
            ->where('img_rifa_id', (int)$rifa_id)
            ->order_by('img_principal', 'DESC')
            ->order_by('img_orden', 'ASC')
            ->order_by($this->primary_key, 'ASC')
            ->get($this->table)
            ->result_array();
    }

    /**
     * Obtiene una imagen por su PK.
     *
     * @param  int $img_id
     * @return array|null
     */
    public function get_por_id($img_id)
    {
        if (!$this->tabla_disponible() || (int)$img_id <= 0) {
            return NULL;
        }

        return $this->db
            ->where($this->primary_key, (int)$img_id)
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Cuenta las imágenes de una rifa.
     *
     * @param  int $rifa_id
     * @return int
     */
    public function contar_por_rifa($rifa_id)
    {
        if (!$this->tabla_disponible() || (int)$rifa_id <= 0) {
            return 0;
        }

        return (int)$this->db
            ->where('img_rifa_id', (int)$rifa_id)
            ->count_all_results($this->table);
    }

    /**
     * Devuelve la imagen principal de una rifa.
     *
     * @param  int $rifa_id
     * @return array|null
     */
    public function get_principal($rifa_id)
    {
        if (!$this->tabla_disponible() || (int)$rifa_id <= 0) {
            return NULL;
        }

        return $this->db
            ->where('img_rifa_id', (int)$rifa_id)
            ->where('img_principal', 1)
            ->order_by('img_orden', 'ASC')
            ->order_by($this->primary_key, 'ASC')
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Siguiente valor de orden disponible para una rifa.
     *
     * @param  int $rifa_id
     * @return int
     */
    public function siguiente_orden($rifa_id)
    {
        if (!$this->tabla_disponible() || (int)$rifa_id <= 0) {
            return 0;
        }

        $this->db->select_max('img_orden', 'max_orden');
        $fila = $this->db
            ->where('img_rifa_id', (int)$rifa_id)
            ->get($this->table)
            ->row_array();

        return isset($fila['max_orden']) ? ((int)$fila['max_orden'] + 1) : 0;
    }

    /**
     * Inserta una imagen en la galería.
     *
     * Si la rifa aún no tiene imágenes, la nueva se marca como principal
     * aunque el llamador no lo solicite, para mantener la invariante.
     *
     * @param  int    $rifa_id
     * @param  string $archivo
     * @param  int    $principal
     * @param  int    $orden
     * @return int|false ID insertado o FALSE.
     */
    public function crear($rifa_id, $archivo, $principal = 0, $orden = 0)
    {
        if (!$this->_tablas_disponibles()) {
            return FALSE;
        }

        $rifa_id = (int)$rifa_id;
        $archivo = $this->_normalizar_archivo($archivo);

        if ($rifa_id <= 0 || $archivo === '') {
            return FALSE;
        }

        if (!$this->_rifa_existe($rifa_id)) {
            return FALSE;
        }

        if ($this->contar_por_rifa($rifa_id) >= (int)$this->max_imagenes) {
            return FALSE;
        }

        $principal = ((int)$principal === 1) ? 1 : 0;
        $orden     = max(0, (int)$orden);

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();

        try {
            // Serializa altas concurrentes por rifa y vuelve a contar bajo lock.
            if (!$this->_lock_rifa($rifa_id)
                || $this->contar_por_rifa($rifa_id) >= (int)$this->max_imagenes) {
                $this->db->trans_rollback();
                return FALSE;
            }

            // La primera imagen siempre es portada.
            if ($this->contar_por_rifa($rifa_id) === 0) {
                $principal = 1;
            }

            if ($principal === 1) {
                if (!$this->_desmarcar_principales($rifa_id)) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            }

            $ok = $this->db->insert($this->table, [
                'img_rifa_id'   => $rifa_id,
                'img_archivo'   => $archivo,
                'img_principal' => $principal,
                'img_orden'     => $orden,
            ]);

            $error = $this->db->error();

            if (!$ok || !empty($error['code'])) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $img_id = (int)$this->db->insert_id();

            if ($principal === 1 && !$this->_sincronizar_portada($rifa_id, $archivo)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return $img_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_imagen_model::crear no pudo insertar la imagen.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Marca una imagen como principal de su rifa.
     *
     * @param  int $rifa_id
     * @param  int $img_id
     * @return bool
     */
    public function marcar_principal($rifa_id, $img_id)
    {
        if (!$this->_tablas_disponibles()) {
            return FALSE;
        }

        $rifa_id = (int)$rifa_id;
        $img_id  = (int)$img_id;

        if ($rifa_id <= 0 || $img_id <= 0) {
            return FALSE;
        }

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();

        try {
            if (!$this->_lock_rifa($rifa_id)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $imagen = $this->db
                ->where($this->primary_key, $img_id)
                ->where('img_rifa_id', $rifa_id)
                ->limit(1)
                ->get($this->table)
                ->row_array();

            if (empty($imagen)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (!$this->_desmarcar_principales($rifa_id)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $ok = $this->db
                ->where($this->primary_key, $img_id)
                ->where('img_rifa_id', $rifa_id)
                ->update($this->table, ['img_principal' => 1]);

            $error = $this->db->error();

            if (!$ok || !empty($error['code'])) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if (!$this->_sincronizar_portada($rifa_id, (string)$imagen['img_archivo'])) {
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
            log_message('error', 'Rifa_imagen_model::marcar_principal no pudo actualizar la portada.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Elimina una imagen de la galería.
     *
     * Si era la principal, promueve la imagen de menor orden o deja
     * rifa_imagen en NULL.
     *
     * @param  int $img_id
     * @return bool
     */
    public function eliminar($img_id)
    {
        if (!$this->_tablas_disponibles() || (int)$img_id <= 0) {
            return FALSE;
        }

        $img_id = (int)$img_id;

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();

        try {
            $imagen = $this->db
                ->where($this->primary_key, $img_id)
                ->limit(1)
                ->get($this->table)
                ->row_array();

            if (empty($imagen)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $rifa_id       = (int)$imagen['img_rifa_id'];
            $era_principal = ((int)$imagen['img_principal'] === 1);

            if (!$this->_lock_rifa($rifa_id)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            // Releer bajo lock para evitar borrar una fila ya modificada.
            $imagen = $this->db
                ->where($this->primary_key, $img_id)
                ->where('img_rifa_id', $rifa_id)
                ->limit(1)
                ->get($this->table)
                ->row_array();

            if (empty($imagen)) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $era_principal = ((int)$imagen['img_principal'] === 1);

            $ok = $this->db
                ->where($this->primary_key, $img_id)
                ->delete($this->table);

            $error = $this->db->error();

            if (!$ok || !empty($error['code'])) {
                $this->db->trans_rollback();
                return FALSE;
            }

            if ($era_principal) {
                $siguiente = $this->db
                    ->where('img_rifa_id', $rifa_id)
                    ->order_by('img_orden', 'ASC')
                    ->order_by($this->primary_key, 'ASC')
                    ->limit(1)
                    ->get($this->table)
                    ->row_array();

                if (!empty($siguiente)) {
                    $ok_promover = $this->db
                        ->where($this->primary_key, (int)$siguiente[$this->primary_key])
                        ->where('img_rifa_id', $rifa_id)
                        ->update($this->table, ['img_principal' => 1]);

                    if (!$ok_promover
                        || !$this->_sincronizar_portada($rifa_id, (string)$siguiente['img_archivo'])) {
                        $this->db->trans_rollback();
                        return FALSE;
                    }
                } elseif (!$this->_sincronizar_portada($rifa_id, NULL)) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Rifa_imagen_model::eliminar no pudo borrar la imagen.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    /**
     * Reemplaza el archivo de la imagen principal (compatibilidad con la
     * portada legacy de un solo archivo).
     *
     * @param  int    $rifa_id
     * @param  string $archivo
     * @return bool
     */
    public function reemplazar_principal($rifa_id, $archivo)
    {
        if (!$this->_tablas_disponibles()) {
            return FALSE;
        }

        $rifa_id = (int)$rifa_id;
        $archivo = $this->_normalizar_archivo($archivo);

        if ($rifa_id <= 0 || $archivo === '' || !$this->_rifa_existe($rifa_id)) {
            return FALSE;
        }

        $debug_previo = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();

        try {
            $principal = $this->get_principal($rifa_id);

            if (!empty($principal)) {
                $ok = $this->db
                    ->where($this->primary_key, (int)$principal[$this->primary_key])
                    ->update($this->table, ['img_archivo' => $archivo]);

                $error = $this->db->error();

                if (!$ok || !empty($error['code'])) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            } else {
                $orden = $this->siguiente_orden($rifa_id);

                $ok = $this->db->insert($this->table, [
                    'img_rifa_id'   => $rifa_id,
                    'img_archivo'   => $archivo,
                    'img_principal' => 1,
                    'img_orden'     => $orden,
                ]);

                $error = $this->db->error();

                if (!$ok || !empty($error['code'])) {
                    $this->db->trans_rollback();
                    return FALSE;
                }
            }

            if (!$this->_sincronizar_portada($rifa_id, $archivo)) {
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
            log_message('error', 'Rifa_imagen_model::reemplazar_principal no pudo reemplazar la portada.');
            return FALSE;
        } finally {
            $this->db->db_debug = $debug_previo;
        }
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * @return bool
     */
    private function _tablas_disponibles()
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists($this->table_rifa);
    }

    /**
     * Bloquea la rifa padre para serializar operaciones de galería.
     *
     * @param  int $rifa_id
     * @return bool
     */
    private function _lock_rifa($rifa_id)
    {
        $row = $this->db->query(
            'SELECT rifa_id FROM rif_rifas WHERE rifa_id = ? FOR UPDATE',
            [(int)$rifa_id]
        )->row_array();

        return !empty($row);
    }

    /**
     * @param  int $rifa_id
     * @return bool
     */
    private function _rifa_existe($rifa_id)
    {
        return $this->db
            ->where('rifa_id', (int)$rifa_id)
            ->where('rifa_eliminado', 0)
            ->count_all_results($this->table_rifa) > 0;
    }

    /**
     * Desmarca todas las portadas de una rifa.
     *
     * @param  int $rifa_id
     * @return bool
     */
    private function _desmarcar_principales($rifa_id)
    {
        $ok = $this->db
            ->where('img_rifa_id', (int)$rifa_id)
            ->where('img_principal', 1)
            ->update($this->table, ['img_principal' => 0]);

        $error = $this->db->error();

        return (bool)$ok && empty($error['code']);
    }

    /**
     * Sincroniza rif_rifas.rifa_imagen con la portada.
     *
     * @param  int         $rifa_id
     * @param  string|null $archivo
     * @return bool
     */
    private function _sincronizar_portada($rifa_id, $archivo)
    {
        $archivo = ($archivo === NULL) ? NULL : $this->_normalizar_archivo($archivo);

        if ($archivo === '') {
            $archivo = NULL;
        }

        $ok = $this->db
            ->where('rifa_id', (int)$rifa_id)
            ->update($this->table_rifa, [
                'rifa_imagen'     => $archivo,
                'rifa_updated_at' => date('Y-m-d H:i:s'),
            ]);

        $error = $this->db->error();

        return (bool)$ok && empty($error['code']);
    }

    /**
     * Normaliza el nombre del archivo para evitar rutas.
     *
     * @param  string $archivo
     * @return string
     */
    private function _normalizar_archivo($archivo)
    {
        $archivo = basename(trim((string)$archivo));

        if ($archivo === '' || $archivo === '.' || $archivo === '..') {
            return '';
        }

        return $archivo;
    }
}
