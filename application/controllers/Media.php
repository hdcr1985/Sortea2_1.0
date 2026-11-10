<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Media
 * Sirve archivos internos mediante URLs virtuales.
 */
class Media extends CI_Controller
{
    private $mimes_theme = ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];

    public function __construct()
    {
        parent::__construct();
    }

    public function usuario($usuario_id = '')
    {
        $this->load->library('session');
        $this->load->database();

        if (!$this->session->userdata('usuario')) {
            return $this->_no_encontrado();
        }

        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return $this->_no_encontrado();
        }

        $usuario = $this->db
            ->select('usu_id, usu_foto')
            ->where('usu_id', $usuario_id)
            ->limit(1)
            ->get('cab_usuarios')
            ->row_array();

        if (empty($usuario) || empty($usuario['usu_foto'])) {
            return $this->_no_encontrado();
        }

        $ruta = $this->_ruta_archivo_usuario($usuario['usu_id'], $usuario['usu_foto']);
        if (!is_file($ruta)) {
            return $this->_no_encontrado();
        }

        $mime = $this->_detectar_mime($ruta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], TRUE)) {
            return $this->_no_encontrado();
        }

        // Limpiar cualquier buffer previo para evitar contaminación de cabeceras o binarios
        if (ob_get_level() > 0) {
            ob_clean();
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Length: ' . filesize($ruta))
            ->set_header('Cache-Control: private, max-age=86400')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(file_get_contents($ruta));
    }

    /**
     * Sirve la imagen de una rifa (M03).
     *
     * Acceso:
     * - Sesión master.
     * - Organizador propietario de la rifa.
     * - Público cuando la rifa está activa.
     *
     * @param  int $id
     * @return void
     */
    public function rifa($id = 0)
    {
        $this->load->library('session');
        $this->load->database();

        $id = (int)$id;
        if ($id <= 0 || !$this->db->table_exists('rif_rifas')) {
            return $this->_no_encontrado();
        }

        $rifa = $this->db
            ->select('rifa_id, rifa_org_id, rifa_imagen, rifa_estado')
            ->where('rifa_id', $id)
            ->where('rifa_eliminado', 0)
            ->limit(1)
            ->get('rif_rifas')
            ->row_array();

        if (empty($rifa)) {
            return $this->_no_encontrado();
        }

        // Fallback: si la portada legacy está vacía, usar la principal de la
        // galería (M03.2). La autorización se evalúa después, igual que antes.
        if (empty($rifa['rifa_imagen']) && $this->db->table_exists('rif_rifa_imagenes')) {
            $principal = $this->db
                ->select('img_archivo')
                ->where('img_rifa_id', $id)
                ->where('img_principal', 1)
                ->order_by('img_orden', 'ASC')
                ->order_by('img_id', 'ASC')
                ->limit(1)
                ->get('rif_rifa_imagenes')
                ->row_array();

            if (!empty($principal['img_archivo'])) {
                $rifa['rifa_imagen'] = (string)$principal['img_archivo'];
            }
        }

        if (empty($rifa['rifa_imagen'])) {
            return $this->_no_encontrado();
        }

        if (!$this->_autorizado_rifa($rifa)) {
            return $this->_no_encontrado();
        }

        return $this->_servir_imagen_rifa((int)$rifa['rifa_org_id'], (string)$rifa['rifa_imagen']);
    }

    /**
     * Sirve una imagen de la galería de una rifa (M03.2).
     *
     * La autorización replica la de Media::rifa: rifa activa y organización
     * activa para visitantes; propietario/master con usuario activo; si no,
     * 404.
     *
     * @param  int $img_id
     * @return void
     */
    public function rifa_imagen($img_id = 0)
    {
        $this->load->library('session');
        $this->load->database();

        $img_id = (int)$img_id;

        if ($img_id <= 0
            || !$this->db->table_exists('rif_rifa_imagenes')
            || !$this->db->table_exists('rif_rifas')) {
            return $this->_no_encontrado();
        }

        $imagen = $this->db
            ->select('i.img_id, i.img_archivo, i.img_rifa_id, r.rifa_id, r.rifa_org_id, r.rifa_estado')
            ->from('rif_rifa_imagenes i')
            ->join('rif_rifas r', 'r.rifa_id = i.img_rifa_id', 'inner')
            ->where('i.img_id', $img_id)
            ->where('r.rifa_eliminado', 0)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($imagen) || empty($imagen['img_archivo'])) {
            return $this->_no_encontrado();
        }

        $rifa = [
            'rifa_id'     => (int)$imagen['rifa_id'],
            'rifa_org_id' => (int)$imagen['rifa_org_id'],
            'rifa_estado' => (string)$imagen['rifa_estado'],
        ];

        if (!$this->_autorizado_rifa($rifa)) {
            return $this->_no_encontrado();
        }

        return $this->_servir_imagen_rifa((int)$imagen['rifa_org_id'], (string)$imagen['img_archivo']);
    }

    /**
     * Sirve el logo de un método de pago (M05).
     *
     * @param  int $met_id
     * @return void
     */
    public function metodo_imagen($met_id = 0)
    {
        $this->load->library('session');
        $this->load->database();

        $met_id = (int)$met_id;
        if ($met_id <= 0 || !$this->db->table_exists('rif_metodos_pago')) {
            return $this->_no_encontrado();
        }

        $metodo = $this->db
            ->select('met_id, met_imagen, met_status, met_scope, met_org_id, met_eliminado')
            ->where('met_id', $met_id)
            ->where('met_eliminado', 0)
            ->limit(1)
            ->get('rif_metodos_pago')
            ->row_array();

        if (empty($metodo) || empty($metodo['met_imagen'])) {
            return $this->_no_encontrado();
        }

        if (!$this->_autorizado_metodo($metodo)) {
            return $this->_no_encontrado();
        }

        return $this->_servir_imagen_metodo((string)$metodo['met_imagen']);
    }

    public function theme($archivo = '')
    {
        $archivo = basename(rawurldecode(trim((string)$archivo)));
        if ($archivo === '') {
            return $this->_no_encontrado();
        }

        $ruta = $this->_storage_base()
            . DIRECTORY_SEPARATOR . 'theme'
            . DIRECTORY_SEPARATOR . $archivo;

        if (!is_file($ruta)) {
            return $this->_no_encontrado();
        }

        $mime = $this->_detectar_mime($ruta);
        if (!in_array($mime, $this->mimes_theme, TRUE)) {
            return $this->_no_encontrado();
        }

        // Limpiar cualquier buffer previo para evitar contaminación de cabeceras o binarios
        if (ob_get_level() > 0) {
            ob_clean();
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Length: ' . filesize($ruta))
            ->set_header('Cache-Control: private, max-age=86400')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(file_get_contents($ruta));
    }

    /**
     * Autoriza el acceso a una imagen de rifa.
     *
     * @param  array $rifa
     * @return bool
     */
    private function _autorizado_rifa(array $rifa)
    {
        $org_activa = $this->_org_activa((int)($rifa['rifa_org_id'] ?? 0));

        $usuario        = $this->session->userdata('usuario');
        $usuario_activo = FALSE;
        $usuario_db     = [];

        if (!empty($usuario) && !empty($usuario['usu_id'])) {
            $usuario_db = $this->db
                ->select('usu_id, usu_tipoper')
                ->where('usu_id', (string)$usuario['usu_id'])
                ->where('usu_status', '1')
                ->limit(1)
                ->get('cab_usuarios')
                ->row_array();

            $usuario_activo = !empty($usuario_db);
        }

        if ($usuario_activo) {
            // Revalidar el rol REAL en BD: no confiar en es_master ni en
            // usu_tipoper cacheados en la sesión.
            $tipoper   = (string)($usuario_db['usu_tipoper'] ?? '');
            $es_master = FALSE;

            if ($tipoper !== '') {
                $this->load->model('Menu_model');
                $es_master = $this->Menu_model->rol_es_master($tipoper);
            }

            if ($es_master) {
                return TRUE;
            }

            if ($org_activa && $this->db->table_exists('rif_organizadores')) {
                $organizador = $this->db
                    ->select('org_id')
                    ->where('org_usuario_id', (string)$usuario['usu_id'])
                    ->where('org_eliminado', 0)
                    ->where('org_status', 1)
                    ->limit(1)
                    ->get('rif_organizadores')
                    ->row_array();

                if (!empty($organizador)
                    && (int)$organizador['org_id'] === (int)($rifa['rifa_org_id'] ?? 0)) {
                    return TRUE;
                }
            }
        }

        return $org_activa && (string)($rifa['rifa_estado'] ?? '') === 'activa';
    }

    /**
     * Indica si una organización está activa y no eliminada.
     *
     * @param  int $org_id
     * @return bool
     */
    private function _org_activa($org_id)
    {
        if ((int)$org_id <= 0 || !$this->db->table_exists('rif_organizadores')) {
            return FALSE;
        }

        $organizacion = $this->db
            ->select('org_status, org_eliminado')
            ->where('org_id', (int)$org_id)
            ->limit(1)
            ->get('rif_organizadores')
            ->row_array();

        return !empty($organizacion)
            && (int)$organizacion['org_status'] === 1
            && (int)$organizacion['org_eliminado'] === 0;
    }

    /**
     * Sirve un archivo de imagen de rifa con MIME validado.
     *
     * @param  int    $org_id
     * @param  string $archivo
     * @return void
     */
    private function _servir_imagen_rifa($org_id, $archivo)
    {
        $ruta = $this->_ruta_archivo_rifa((int)$org_id, $archivo);

        if (!is_file($ruta)) {
            return $this->_no_encontrado();
        }

        $mime = $this->_detectar_mime($ruta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], TRUE)) {
            return $this->_no_encontrado();
        }

        if (ob_get_level() > 0) {
            ob_clean();
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Length: ' . filesize($ruta))
            ->set_header('Cache-Control: private, max-age=86400')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(file_get_contents($ruta));
    }

    /**
     * Autoriza el logo de un método de pago.
     *
     * @param  array $metodo
     * @return bool
     */
    private function _autorizado_metodo(array $metodo)
    {
        if ((int)($metodo['met_status'] ?? 0) === 1) {
            return TRUE;
        }

        $usuario = $this->session->userdata('usuario');
        if (empty($usuario) || empty($usuario['usu_id'])) {
            return FALSE;
        }

        $usuario_db = $this->db
            ->select('usu_id, usu_tipoper')
            ->where('usu_id', (string)$usuario['usu_id'])
            ->where('usu_status', '1')
            ->limit(1)
            ->get('cab_usuarios')
            ->row_array();

        if (empty($usuario_db)) {
            return FALSE;
        }

        $tipoper   = (string)($usuario_db['usu_tipoper'] ?? '');
        $es_master = FALSE;

        if ($tipoper !== '') {
            $this->load->model('Menu_model');
            $es_master = $this->Menu_model->rol_es_master($tipoper);
        }

        if ($es_master) {
            return TRUE;
        }

        if ((string)($metodo['met_scope'] ?? '') === 'organizador'
            && !empty($metodo['met_org_id'])) {
            $organizador = $this->db
                ->select('org_id')
                ->where('org_usuario_id', (string)$usuario['usu_id'])
                ->where('org_eliminado', 0)
                ->where('org_status', 1)
                ->limit(1)
                ->get('rif_organizadores')
                ->row_array();

            return !empty($organizador)
                && (int)$organizador['org_id'] === (int)$metodo['met_org_id'];
        }

        return FALSE;
    }

    private function _ruta_archivo_metodo($archivo)
    {
        return $this->_storage_base()
            . DIRECTORY_SEPARATOR . 'metodos'
            . DIRECTORY_SEPARATOR . basename((string)$archivo);
    }

    private function _servir_imagen_metodo($archivo)
    {
        $ruta = $this->_ruta_archivo_metodo($archivo);

        if (!is_file($ruta)) {
            return $this->_no_encontrado();
        }

        $mime = $this->_detectar_mime($ruta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], TRUE)) {
            return $this->_no_encontrado();
        }

        if (ob_get_level() > 0) {
            ob_clean();
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Length: ' . filesize($ruta))
            ->set_header('Cache-Control: private, max-age=86400')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(file_get_contents($ruta));
    }

    private function _ruta_archivo_usuario($usuario_id, $archivo)
    {
        return $this->_storage_base()
            . DIRECTORY_SEPARATOR . 'usuarios'
            . DIRECTORY_SEPARATOR . $usuario_id
            . DIRECTORY_SEPARATOR . basename($archivo);
    }

    private function _ruta_archivo_rifa($org_id, $archivo)
    {
        return $this->_storage_base()
            . DIRECTORY_SEPARATOR . 'rifas'
            . DIRECTORY_SEPARATOR . (int)$org_id
            . DIRECTORY_SEPARATOR . basename($archivo);
    }

    private function _storage_base()
    {
        return rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'upload';
    }

    private function _detectar_mime($ruta)
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $ruta) ?: 'application/octet-stream';
                // En PHP 8.5+ finfo_close() está deprecado ya que los recursos se liberan solos
                if (PHP_VERSION_ID < 80500 && function_exists('finfo_close')) {
                    @finfo_close($finfo);
                }
                unset($finfo);
                return $mime;
            }
        }

        if (function_exists('getimagesize')) {
            $image_info = @getimagesize($ruta);
            if ($image_info !== FALSE && !empty($image_info['mime'])) {
                return $image_info['mime'];
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($ruta);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mime_map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'svg'  => 'image/svg+xml',
        ];

        return isset($mime_map[$extension]) ? $mime_map[$extension] : 'application/octet-stream';
    }

    private function _no_encontrado()
    {
        show_404();
    }
}