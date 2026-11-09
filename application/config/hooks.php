<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Hooks
|--------------------------------------------------------------------------
| pre_controller: aísla el panel administrativo de los hosts de tenant y
| corta con 404 los subdominios no registrados (M01).
| post_controller_constructor: aplica cabeceras de seguridad a todas las
| respuestas (incluye Auth, Welcome y Media).
|--------------------------------------------------------------------------
*/
$hook['pre_controller'][] = [
    'class'    => 'Tenant_host_guard',
    'function' => 'apply',
    'filename' => 'Tenant_host_guard.php',
    'filepath' => 'hooks',
    'params'   => [],
];

$hook['post_controller_constructor'][] = [
    'class'    => 'Security_headers',
    'function' => 'apply',
    'filename' => 'Security_headers.php',
    'filepath' => 'hooks',
    'params'   => [],
];
