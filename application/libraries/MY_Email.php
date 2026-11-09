<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Email
 *
 * Extensión de CI_Email para:
 * 1. Soporte moderno de TLS en PHP 7.x, 8.x (incluyendo TLS 1.3 y TLS 1.2).
 * 2. Manejo seguro de timeouts en sockets y streams para evitar cuelgues (Fatal error: Maximum execution time exceeded).
 * 3. Opciones de contexto SSL configurables para servidores SMTP con certificados autofirmados o entornos locales.
 */
class MY_Email extends CI_Email
{
    /**
     * Opciones SSL para stream_context si se requieren en local/desarrollo
     * @var array
     */
    public $smtp_crypto_options = [];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Sobrescribir _smtp_connect para:
     * - Configurar stream context con opciones de SSL/TLS (verify_peer, verify_peer_name, etc.)
     * - Usar stream_socket_client con timeout estricto para evitar bloqueos indefinidos.
     */
    protected function _smtp_connect()
    {
        if (is_resource($this->_smtp_connect))
        {
            return TRUE;
        }

        $ssl = ($this->smtp_crypto === 'ssl') ? 'ssl://' : '';
        $timeout = (int)$this->smtp_timeout > 0 ? (int)$this->smtp_timeout : 10;

        // Verificación TLS estricta por defecto. Para servidores heredados con
        // certificados autofirmados se puede desactivar explícitamente con
        // MAIL_VERIFY_PEER=0 en el entorno, nunca en código.
        $verify_peer = filter_var(env_value('MAIL_VERIFY_PEER', '1'), FILTER_VALIDATE_BOOLEAN);

        $context_options = [
            'ssl' => [
                'verify_peer' => $verify_peer,
                'verify_peer_name' => $verify_peer,
                'allow_self_signed' => !$verify_peer,
            ]
        ];

        if (!empty($this->smtp_crypto_options) && is_array($this->smtp_crypto_options))
        {
            $context_options = array_replace_recursive($context_options, $this->smtp_crypto_options);
        }

        $context = stream_context_create($context_options);

        $errno = 0;
        $errstr = '';

        // Usamos stream_socket_client en lugar de fsockopen para poder adjuntar stream context
        $this->_smtp_connect = @stream_socket_client(
            $ssl . $this->smtp_host . ':' . $this->smtp_port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($this->_smtp_connect))
        {
            $this->_set_error_message('lang:email_smtp_error', $errno . ' ' . $errstr);
            return FALSE;
        }

        stream_set_timeout($this->_smtp_connect, $timeout);
        $this->_set_error_message($this->_get_smtp_data());

        if ($this->smtp_crypto === 'tls')
        {
            $this->_send_command('hello');
            $this->_send_command('starttls');

            // Determinar método crypto según versión de PHP y constantes disponibles
            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') && defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'))
            {
                $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            elseif (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'))
            {
                $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            // Realizar el handshake TLS con control de errores
            $crypto = @stream_socket_enable_crypto($this->_smtp_connect, TRUE, $method);

            if ($crypto !== TRUE)
            {
                $this->_set_error_message('lang:email_smtp_error', 'Fallo o timeout en negociación TLS con el servidor SMTP.');
                return FALSE;
            }
        }

        return $this->_send_command('hello');
    }

    /**
     * Sobrescribir _get_smtp_data con verificación de timeout de stream
     * para evitar que fgets quede en bucle infinito si el servidor no responde o cierra el socket.
     */
    protected function _get_smtp_data()
    {
        $data = '';

        if (!is_resource($this->_smtp_connect))
        {
            return $data;
        }

        while ($str = fgets($this->_smtp_connect, 512))
        {
            $data .= $str;

            $info = stream_get_meta_data($this->_smtp_connect);
            if (!empty($info['timed_out']))
            {
                $this->_set_error_message('lang:email_smtp_error', 'SMTP timeout leyendo datos del servidor.');
                break;
            }

            if (isset($str[3]) && $str[3] === ' ')
            {
                break;
            }
        }

        return $data;
    }
}

