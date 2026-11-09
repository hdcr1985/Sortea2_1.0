<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$base_url = function_exists('base_url') ? base_url() : '/';
$CI = function_exists('get_instance') ? get_instance() : NULL;
?><!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <base href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Página no encontrada</title>

    <!--begin::Tema claro/oscuro, igual que el layout principal-->
    <script>
        (function () {
            var theme = 'light';

            try {
                var saved = localStorage.getItem('data-bs-theme');
                if (saved === 'dark' || saved === 'light') {
                    theme = saved;
                } else if (saved === 'system' || saved === null) {
                    theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                        ? 'dark'
                        : 'light';
                }
            } catch (e) {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light';
            }

            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <!--end::Tema-->

    <!--begin::Metronic global styles-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link href="<?php echo htmlspecialchars(base_url('assets/plugins/global/plugins.bundle.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" type="text/css">
    <link href="<?php echo htmlspecialchars(base_url('assets/css/style.bundle.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" type="text/css">
    <link href="<?php echo htmlspecialchars(base_url('assets/css/demo1-dark-sidebar.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" type="text/css">
    <!--end::Metronic global styles-->

    <style>
        html,
        body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
        }

        .error-404-page {
            min-height: 100vh;
        }

        .error-404-card {
            width: 100%;
            max-width: 760px;
        }

        .error-404-illustration-dark {
            display: none;
        }

        [data-bs-theme="dark"] .error-404-illustration-light {
            display: none;
        }

        [data-bs-theme="dark"] .error-404-illustration-dark {
            display: inline-block;
        }
    </style>
</head>
<body class="app-default bg-body">
    <div class="d-flex flex-column flex-root app-root error-404-page">
        <div class="d-flex flex-center flex-column flex-column-fluid p-5 p-lg-10">
            <div class="error-404-card">
                <?php if (is_object($CI) && isset($CI->load)): ?>
                    <?php $CI->load->view('pages/error_404'); ?>
                <?php else: ?>
                    <div class="card card-flush shadow-sm">
                        <div class="card-body text-center py-20">
                            <img src="<?php echo htmlspecialchars(base_url('assets/media/illustrations/19.svg'), ENT_QUOTES, 'UTF-8'); ?>"
                                 alt=""
                                 class="error-404-illustration-light mw-100 mb-8"
                                 style="max-height: 160px;">
                            <img src="<?php echo htmlspecialchars(base_url('assets/media/illustrations/19-dark.svg'), ENT_QUOTES, 'UTF-8'); ?>"
                                 alt=""
                                 class="error-404-illustration-dark mw-100 mb-8"
                                 style="max-height: 160px;">
                            <h1 class="fw-bold fs-2hx text-gray-900 mb-4">404</h1>
                            <div class="fw-semibold fs-6 text-gray-500 mb-8">
                                La página o el recurso que buscas no existe, fue movido o ya no está disponible.
                            </div>
                            <a href="<?php echo htmlspecialchars(base_url('dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
                                Volver al Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
