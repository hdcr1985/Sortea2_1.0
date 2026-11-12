<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Security_headers
 * Aplica cabeceras de seguridad a todas las respuestas HTTP.
 * Se ejecuta en post_controller_constructor (application/config/hooks.php).
 */
class Security_headers
{
    public function apply()
    {
        if (headers_sent()) {
            return;
        }

        header_remove('X-Powered-By');

        // X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
        // Permissions-Policy y X-Permitted-Cross-Domain-Policies se envían
        // desde .htaccess (Apache) y web.config (IIS) para cubrir también
        // recursos estáticos. Aquí solo se agregan las cabeceras dinámicas.
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // No aplicar CSP a la transmisión binaria de Media.
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($uri, '/media/') === false) {
            header(
                "Content-Security-Policy: default-src 'self'; base-uri 'self'; "
                . "form-action 'self'; frame-ancestors 'self'; object-src 'none'; "
                . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; "
                . "style-src 'self' 'unsafe-inline' https:; "
                . "img-src 'self' data: blob: https:; "
                . "font-src 'self' data: https:; "
                . "connect-src 'self' https:; "
                . "frame-src 'self' https:"
            );
        }
    }
}
