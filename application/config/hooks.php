<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Hooks
|--------------------------------------------------------------------------
| post_controller_constructor: aplica cabeceras de seguridad a todas las
| respuestas (incluye Auth, Welcome y Media).
|--------------------------------------------------------------------------
*/
$hook['post_controller_constructor'][] = [
    'class'    => 'Security_headers',
    'function' => 'apply',
    'filename' => 'Security_headers.php',
    'filepath' => 'hooks',
    'params'   => [],
];
