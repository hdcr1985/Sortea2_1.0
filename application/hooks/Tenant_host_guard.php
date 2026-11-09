<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant_host_guard
 * ─────────────────────────────────────────────────────────────
 * Aislamiento por host para el núcleo multi-tenant (M01).
 *
 * - En un host de tenant (TENANT_HOST_ACTIVO) solo se permite el sitio
 *   público y los recursos servidos por Media/Errors. Cualquier controlador
 *   administrativo se corta con 404 antes de instanciarse.
 * - En un host bajo el dominio base que no corresponde a un tenant
 *   (TENANT_HOST_INVALIDO) se corta toda petición con 404.
 *
 * Las constantes las define application/config/routes.php después de
 * resolver el host contra rif_dominios.
 */
class Tenant_host_guard
{
    /**
     * @return void
     */
    public function apply()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $tenant_activo  = defined('TENANT_HOST_ACTIVO') && TENANT_HOST_ACTIVO;
        $tenant_invalido = defined('TENANT_HOST_INVALIDO') && TENANT_HOST_INVALIDO;

        if (!$tenant_activo && !$tenant_invalido) {
            return;
        }

        $router =& load_class('Router', 'core');
        $class  = strtolower((string)$router->fetch_class());

        // En un tenant solo viven el sitio público y los recursos internos.
        if ($tenant_activo && in_array($class, ['publico', 'media', 'errors'], TRUE)) {
            return;
        }

        $this->_not_found();
    }

    /**
     * Emite un 404 mínimo sin depender del ciclo completo de CI.
     *
     * @return void
     */
    private function _not_found()
    {
        $es_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        http_response_code(404);

        if ($es_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => FALSE, 'msg' => 'Recurso no encontrado.'], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>404</title></head><body>'
                . '<h1>404</h1><p>Recurso no encontrado.</p>'
                . '</body></html>';
        }

        exit;
    }
}
