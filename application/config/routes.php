<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Rutas fijas del sistema
|--------------------------------------------------------------------------
| SOLO van aquí las rutas públicas o del sistema.
| Los módulos del menú se registran automáticamente desde la BD
| a través del hook pre_controller (rutas_dinamicas.php).
|--------------------------------------------------------------------------
*/

$route['default_controller']   = 'Home';
$route['404_override']         = 'Errors/pagina_404';
$route['translate_uri_dashes'] = FALSE;

// ── Autenticación (públicas) ──────────────────────────────────
$route['login']                      = 'Auth/index';
$route['login/entrar']               = 'Auth/autenticar';
$route['logout']                     = 'Auth/logout';
$route['recuperar-clave']            = 'Auth/recuperar';
$route['recuperar-clave/enviar']     = 'Auth/enviar_recuperacion';
$route['nueva-clave/guardar']        = 'Auth/guardar_clave';
$route['nueva-clave/(:any)']         = 'Auth/nueva_clave/$1';
$route['dos-factores']               = 'Auth/dos_factores';
$route['dos-factores/verificar']     = 'Auth/verificar_factor';
$route['dos-factores/reenviar']      = 'Auth/reenviar_factor';
$route['dos-factores/usar-totp']     = 'Auth/usar_totp';
$route['registro']                   = 'Auth/registro';
$route['registro/enviar']            = 'Auth/registrar';
$route['registro/reenviar-verificacion'] = 'Auth/reenviar_verificacion';
$route['registro/resultado']          = 'Auth/resultado_registro';
$route['verificar-correo/confirmar']  = 'Auth/confirmar_correo';
$route['verificar-correo/(:any)']     = 'Auth/verificar_correo/$1';
$route['auth/oauth-google']          = 'Auth/oauth_google';
$route['auth/oauth-google-callback'] = 'Auth/oauth_google_callback';
$route['auth/oauth-apple']           = 'Auth/oauth_apple';
$route['auth/oauth-apple-callback']  = 'Auth/oauth_apple_callback';

// ── Área protegida — rutas fijas ──────────────────────────────
$route['dashboard']                  = 'Home/index';
$route['ping-sesion']                = 'Home/ping_sesion';
$route['sin-permisos']               = 'Errors/sin_permisos';
$route['mantenimiento']              = 'Errors/mantenimiento';
$route['media/usuario/(:any)']       = 'Media/usuario/$1';
$route['media/theme/(:any)']         = 'Media/theme/$1';
$route['notificaciones/feed']        = 'Notificaciones/feed';
$route['notificaciones/marcar-leida/(:num)'] = 'Notificaciones/marcar_leida/$1';
$route['notificaciones/marcar-todas'] = 'Notificaciones/marcar_todas';

// Payment gateway webhooks (public, signature-protected, CSRF-exempt)
$route['webhook/btcpay']                    = 'Webhook/btcpay';
$route['BuyCredits/btcpay_webhook']         = 'Webhook/btcpay';
$route['webhook/stripe']                    = 'Webhook/stripe';
$route['comprar-creditos/webhook-btcpay']   = 'Webhook/btcpay';
$route['pasarelas-de-pago/webhook-btcpay']  = 'Webhook/btcpay';
$route['pasarelas-de-pago/webhook-stripe']  = 'Webhook/stripe';

// ── Multi-tenant M01 — autoservicio y sitio público ──────────
// Nombre actual: "Mi Vitrina".
$route['mi-vitrina']              = 'MiVitrina/index';
$route['mi-vitrina/crear']        = 'MiVitrina/crear';
$route['mi-vitrina/actualizar']   = 'MiVitrina/actualizar';
$route['mi-vitrina/cambiar-slug'] = 'MiVitrina/cambiar_slug';
$route['mi-vitrina/ver']          = 'MiVitrina/ver';

// Alias retrocompatible de la URL anterior "mi-tienda".
$route['mi-tienda']              = 'MiVitrina/index';
$route['mi-tienda/crear']        = 'MiVitrina/crear';
$route['mi-tienda/actualizar']   = 'MiVitrina/actualizar';
$route['mi-tienda/cambiar-slug'] = 'MiVitrina/cambiar_slug';
$route['mi-tienda/ver']          = 'MiVitrina/ver';

$route['sitio/(:any)']           = 'Publico/index/$1';

/*
|--------------------------------------------------------------------------
| Todo lo demás viene de la BD automáticamente
|--------------------------------------------------------------------------
| En cab_menu registra:
|   men_urlamigable = "creacion-de-menus"
|   men_url         = "Menus/index"
|
| El hook pre_controller lo convierte en:
|   $route['creacion-de-menus'] = 'Menus/index';
|--------------------------------------------------------------------------
*/
// ── Rutas dinámicas desde cab_menu ───────────────────────────
$db_config = include(APPPATH . 'config/database.php');

try {
    $conn = @mysqli_connect(
        $db['default']['hostname'],
        $db['default']['username'],
        $db['default']['password'],
        $db['default']['database']
    );

    if ($conn) {
        mysqli_set_charset($conn, $db['default']['char_set']);

        $result = mysqli_query(
            $conn,"SELECT men_url, men_urlamigable FROM cab_menu WHERE men_urlamigable != '' AND men_url != ''"
        );

        while ($menu = mysqli_fetch_assoc($result)) {
            $amigable    = trim($menu['men_urlamigable'], '/');
            $controlador = trim($menu['men_url'], '/');

            if (!empty($amigable) && !empty($controlador)) {
                $ctrl = explode('/', $controlador)[0];

                // De más específico a menos específico
                $route[$amigable . '/(:any)/(:num)'] = $ctrl . '/$1/$2';
                $route[$amigable . '/(:any)/(:any)'] = $ctrl . '/$1/$2';
                $route[$amigable . '/(:any)']        = $ctrl . '/$1';
                $route[$amigable]                    = $controlador;
            }
        }

        // ── Resolución de host multi-tenant (M01) ─────────────
        // Se aísla en su propio try/catch: si rif_dominios/rif_organizadores
        // todavía no existen, el admin y las rutas dinámicas siguen intactos.
        $tenant_host_evaluado = '';
        $tenant_encontrado    = FALSE;

        try {
            if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
                $tenant_host = strtolower(trim((string)$_SERVER['HTTP_HOST']));

                if (substr($tenant_host, 0, 1) === '[') {
                    if (preg_match('/^\[([a-f0-9:]+)\](?::\d+)?$/', $tenant_host, $matches)) {
                        $tenant_host = $matches[1];
                    } else {
                        $tenant_host = '';
                    }
                } else {
                    $tenant_host = preg_replace('/:\d+$/', '', $tenant_host);
                }

                $tenant_host = rtrim((string)$tenant_host, '.');

                if (strpos($tenant_host, 'www.') === 0) {
                    $tenant_host = substr($tenant_host, 4);
                }

                $tenant_host_evaluado = $tenant_host;

                if ($tenant_host !== '' && preg_match('/^[a-z0-9.-]{1,191}$/', $tenant_host)) {
                    $stmt = mysqli_prepare(
                        $conn,
                        'SELECT 1 FROM rif_dominios d '
                        . 'INNER JOIN rif_organizadores o ON o.org_id = d.dom_org_id '
                        . 'WHERE d.dom_host = ? AND d.dom_status = 1 '
                        . 'AND (d.dom_tipo = \'subdominio\' OR d.dom_verificado = 1) '
                        . 'AND o.org_status = 1 AND o.org_eliminado = 0 LIMIT 1'
                    );

                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 's', $tenant_host);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_store_result($stmt);

                        if (mysqli_stmt_num_rows($stmt) > 0) {
                            $tenant_encontrado = TRUE;
                            $route['default_controller'] = 'Publico/index';

                            if (!defined('TENANT_HOST_ACTIVO')) {
                                define('TENANT_HOST_ACTIVO', TRUE);
                            }
                        }

                        mysqli_stmt_close($stmt);
                    }
                }
            }
        } catch (Throwable $e) {
            // La tabla de dominios puede no existir todavía; se ignora.
        }

        // Deny-by-default: solo el host principal, los hosts locales de
        // desarrollo y los tenants registrados pueden servir la aplicación.
        // Cualquier otro Host se corta con 404 (incluye dominios personales
        // no verificados, que se habilitarán en una fase posterior).
        if (!$tenant_encontrado && $tenant_host_evaluado !== '' && PHP_SAPI !== 'cli') {
            $main_host = strtolower((string)parse_url((string)env_value('APP_BASE_URL', ''), PHP_URL_HOST));

            if (strpos($main_host, 'www.') === 0) {
                $main_host = substr($main_host, 4);
            }

            $es_local = in_array(
                $tenant_host_evaluado,
                ['localhost', '127.0.0.1', '::1', '[::1]'],
                TRUE
            );

            if (!$es_local && $tenant_host_evaluado !== $main_host && !defined('TENANT_HOST_INVALIDO')) {
                define('TENANT_HOST_INVALIDO', TRUE);
            }
        }

        mysqli_close($conn);
    }
} catch (Exception $e) {
    log_message('error', 'routes.php – error cargando rutas dinámicas: ' . $e->getMessage());
}