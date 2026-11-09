<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Configuración de Base de Datos
| Ajusta los valores a tu entorno
|--------------------------------------------------------------------------
*/
$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
    'dsn'          => env_value('DB_DSN', ''),
    'hostname'     => env_value('DB_HOSTNAME', '192.168.102.35'),
    'username'     => env_value('DB_USERNAME', 'root'),
    'password'     => env_value('DB_PASSWORD', 'test'),
    'database'     => env_value('DB_DATABASE', 'PROYECTO_NUEVO'),
    'dbdriver'     => env_value('DB_DRIVER', 'mysqli'),
    'dbprefix'     => env_value('DB_PREFIX', ''),
    'pconnect'     => env_value('DB_PCONNECT', FALSE),
    'db_debug'     => (ENVIRONMENT !== 'production'),
    'cache_on'     => env_value('DB_CACHE_ON', FALSE),
    'cachedir'     => env_value('DB_CACHE_DIR', ''),
    'char_set'     => env_value('DB_CHARSET', 'utf8'),
    'dbcollat'     => env_value('DB_COLLATION', 'utf8_unicode_ci'),
    'swap_pre'     => env_value('DB_SWAP_PREFIX', ''),
    'encrypt'      => env_value('DB_ENCRYPT', FALSE),
    'compress'     => env_value('DB_COMPRESS', FALSE),
    'stricton'     => env_value('DB_STRICT_ON', FALSE),
    'port'         => (int) env_value('DB_PORT', 3306),
    'failover'     => array(),
    'save_queries' => env_value('DB_SAVE_QUERIES', TRUE),
);
