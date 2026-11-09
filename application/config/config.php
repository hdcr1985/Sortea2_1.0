<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Base Site URL
|--------------------------------------------------------------------------
| Ajusta esta URL a tu entorno. Sin index.php gracias al .htaccess
*/
$config['base_url'] = env_value('APP_BASE_URL', 'http://localhost/rose/');

/*
|--------------------------------------------------------------------------
| Index File
|--------------------------------------------------------------------------
| Vacío para usar URL amigables con mod_rewrite
*/
$config['index_page'] = '';

/*
|--------------------------------------------------------------------------
| URI PROTOCOL
|--------------------------------------------------------------------------
*/
$config['uri_protocol'] = 'REQUEST_URI';

/*
|--------------------------------------------------------------------------
| Encryption Key — cámbiala por una clave única
|--------------------------------------------------------------------------
*/
$config['encryption_key'] = env_value('APP_ENCRYPTION_KEY', '34d3820154f3319046a9d2b30833f647f134a5eebac819fc990a88272ddb8f05');

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/
$config['sess_driver']         = env_value('SESSION_DRIVER', 'files');
$config['sess_cookie_name']    = env_value('SESSION_COOKIE_NAME', 'ci_session');
$config['sess_expiration']     = (int) env_value('SESSION_EXPIRATION', 7200);
$session_save_path             = env_value('SESSION_SAVE_PATH');
$config['sess_save_path']      = is_string($session_save_path) && trim($session_save_path) !== ''
    ? $session_save_path
    : dirname(FCPATH, 2) . DIRECTORY_SEPARATOR . 'rose_sessions';
$config['sess_match_ip']       = env_value('SESSION_MATCH_IP', FALSE);
$config['sess_time_to_update'] = (int) env_value('SESSION_TIME_TO_UPDATE', 0);
$config['sess_regenerate_destroy'] = env_value('SESSION_REGENERATE_DESTROY', FALSE);
$config['sess_expire_on_close']    = env_value('SESSION_EXPIRE_ON_CLOSE', FALSE);
$config['sess_samesite']           = env_value('SESSION_SAMESITE', 'Lax');

/*
|--------------------------------------------------------------------------
| Cookie
|--------------------------------------------------------------------------
*/
$config['cookie_prefix']   = env_value('COOKIE_PREFIX', '');
$config['cookie_domain']   = env_value('COOKIE_DOMAIN', '');
$config['cookie_path']     = env_value('COOKIE_PATH', '/');
$config['cookie_secure']   = env_value('COOKIE_SECURE', FALSE);
$config['cookie_httponly'] = env_value('COOKIE_HTTPONLY', FALSE);

/*
|--------------------------------------------------------------------------
| Global XSS Filtering
|--------------------------------------------------------------------------
*/
$config['global_xss_filtering'] = FALSE;

/*
|--------------------------------------------------------------------------
| Cross Site Request Forgery (CSRF)
|--------------------------------------------------------------------------
| Protección global para todas las peticiones POST. Los flujos de Auth usan
| su propio token (login_csrf / registration_csrf) y quedan excluidos.
| El token se inyecta en el frontend desde partials/_csrf.php.
*/
$config['csrf_protection']   = TRUE;
$config['csrf_token_name']   = 'csrf_token';
$config['csrf_cookie_name']  = 'csrf_cookie';
$config['csrf_expire']       = 7200;
$config['csrf_regenerate']   = FALSE;
$config['csrf_exclude_uris'] = [
    'login/entrar',
    'registro/.*',
    'recuperar-clave/.*',
    'nueva-clave/.*',
    'dos-factores/.*',
    'verificar-correo/.*',
    'auth/.*',
    'webhook/(btcpay|stripe)$',
    'BuyCredits/btcpay_webhook$',
    'comprar-creditos/webhook-btcpay$',
    'pasarelas-de-pago/webhook-(btcpay|stripe)$',
];

/*
|--------------------------------------------------------------------------
| Output Compression
|--------------------------------------------------------------------------
*/
$config['compress_output'] = FALSE;

$config['language']  = 'english';
$config['charset']   = 'UTF-8';
$config['enable_hooks'] = TRUE;
$config['subclass_prefix'] = 'MY_';
$config['composer_autoload'] = FALSE;
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';
$config['allow_get_array'] = TRUE;
$config['enable_query_strings'] = FALSE;
$config['log_threshold'] = 1;
$config['log_path'] = '';
$config['log_file_extension'] = '';
$config['log_file_permissions'] = 0644;
$config['log_date_format'] = 'Y-m-d H:i:s';
$config['error_views_path'] = '';
$config['cache_path'] = '';
$config['cache_query_string'] = FALSE;
$config['rewrite_short_tags'] = FALSE;
$config['proxy_ips'] = '';

/*
|--------------------------------------------------------------------------
| Simulated Client IP (Development only)
|--------------------------------------------------------------------------
| Permite simular una IP publica cuando trabajas en localhost para probar
| GeoIP y la bitacora de sesiones. Solo debe usarse en desarrollo.
| Ejemplo: 8.8.8.8
*/
$config['simulated_client_ip'] = env_value('SIMULATED_CLIENT_IP', '200.8.235.48');
