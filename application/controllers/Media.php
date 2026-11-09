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

    private function _ruta_archivo_usuario($usuario_id, $archivo)
    {
        return $this->_storage_base()
            . DIRECTORY_SEPARATOR . 'usuarios'
            . DIRECTORY_SEPARATOR . $usuario_id
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