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

        mysqli_close($conn);
    }
} catch (Exception $e) {
    log_message('error', 'routes.php – error cargando rutas dinámicas: ' . $e->getMessage());
}