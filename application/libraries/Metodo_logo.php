<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Metodo_logo
 * ─────────────────────────────────────────────────────────────
 * Subida y borrado seguro de logos de métodos de pago.
 *
 * Los archivos se guardan en upload/metodos y se sirven mediante
 * Media::metodo_imagen(). No se aceptan URLs externas.
 */
class Metodo_logo
{
    protected $CI;
    protected $directorio;
    protected $max_bytes = 4194304;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->directorio = rtrim(FCPATH, '\\/')
            . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'metodos';
    }

    /**
     * Procesa el archivo opcional met_logo.
     *
     * @param  string $campo
     * @return array ['ok'=>bool,'filename'=>string|null,'nuevo'=>bool,'error'=>string]
     */
    public function subir($campo = 'met_logo')
    {
        if (!isset($_FILES[$campo])) {
            return ['ok' => TRUE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => ''];
        }

        $archivo = $_FILES[$campo];

        if (!is_array($archivo) || is_array($archivo['name'] ?? NULL)) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'Archivo de logo inválido.'];
        }

        $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => TRUE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => ''];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'No se pudo recibir el logo.'];
        }

        if ((int)($archivo['size'] ?? 0) <= 0 || (int)$archivo['size'] > $this->max_bytes) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'El logo no puede superar 4 MB.'];
        }

        $tmp = (string)($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'Archivo inválido para el logo.'];
        }

        $mime = $this->_detectar_mime($tmp);
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($permitidos[$mime])) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'Solo se permiten logos JPG, PNG o WEBP.'];
        }

        if (!is_dir($this->directorio)
            && !@mkdir($this->directorio, 0755, TRUE)
            && !is_dir($this->directorio)) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'No se pudo preparar el almacenamiento de logos.'];
        }

        $nombre = 'metodo-' . bin2hex(random_bytes(12)) . '.' . $permitidos[$mime];
        $ruta   = $this->directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!@move_uploaded_file($tmp, $ruta)) {
            return ['ok' => FALSE, 'filename' => NULL, 'nuevo' => FALSE, 'error' => 'No se pudo guardar el logo.'];
        }

        return ['ok' => TRUE, 'filename' => $nombre, 'nuevo' => TRUE, 'error' => ''];
    }

    /**
     * @param  string $archivo
     * @return void
     */
    public function borrar($archivo)
    {
        $archivo = basename((string)$archivo);
        if ($archivo === '' || $archivo === '.' || $archivo === '..') {
            return;
        }

        $ruta = $this->directorio . DIRECTORY_SEPARATOR . $archivo;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function _detectar_mime($ruta)
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $ruta) ?: 'application/octet-stream';
                if (PHP_VERSION_ID < 80500 && function_exists('finfo_close')) {
                    @finfo_close($finfo);
                }
                unset($finfo);
                return $mime;
            }
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($ruta);
            if ($info !== FALSE && !empty($info['mime'])) {
                return $info['mime'];
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($ruta);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }
}
