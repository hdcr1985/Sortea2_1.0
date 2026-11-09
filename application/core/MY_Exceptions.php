<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Exceptions
 *
 * Conserva el comportamiento nativo de CodeIgniter y agrega la ruta
 * solicitada al registro de errores 404 cuando show_404() se invoca sin
 * argumento. No registra query string para evitar filtrar tokens o datos
 * sensibles.
 */
class MY_Exceptions extends CI_Exceptions
{
    /**
     * @param string $page
     * @param bool   $log_error
     * @return void
     */
    public function show_404($page = '', $log_error = TRUE)
    {
        $page = trim((string)$page);

        if ($page === '')
        {
            $page = $this->_ruta_solicitada();
        }

        parent::show_404($this->_limpiar_valor_log($page), $log_error);
    }

    /**
     * Obtiene la ruta relativa solicitada sin query string.
     *
     * @return string
     */
    protected function _ruta_solicitada()
    {
        $ruta = '';

        if (function_exists('get_instance'))
        {
            $CI =& get_instance();

            if (is_object($CI) && isset($CI->uri) && is_object($CI->uri))
            {
                $ruta = (string)$CI->uri->uri_string();
            }
        }

        if ($ruta === '' && !empty($_SERVER['REQUEST_URI']))
        {
            $path = parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH);

            if (is_string($path))
            {
                $ruta = $path;
            }
        }

        if ($ruta === '')
        {
            $ruta = function_exists('is_cli') && is_cli() ? 'CLI' : '/';
        }

        return $ruta;
    }

    /**
     * Evita inyección de saltos de línea en el archivo de log y limita
     * la longitud del valor registrado.
     *
     * @param string $valor
     * @return string
     */
    protected function _limpiar_valor_log($valor)
    {
        $valor = str_replace(array("\r", "\n", "\0"), ' ', (string)$valor);
        $valor = trim($valor);

        if ($valor === '')
        {
            return '/';
        }

        return substr($valor, 0, 2048);
    }
}
