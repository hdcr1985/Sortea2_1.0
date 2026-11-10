<?php
/**
 * M01 - Núcleo multi-tenant y subdominios
 * Layout público autónomo para sitios de organizadores.
 *
 * Contrato:
 * - $content (string, requerido): HTML del contenido de la página.
 * - $page_title (string, opcional): título del documento.
 * - $tenant / $tenant_data (array, opcional): datos del organizador.
 * - $url_principal (string, opcional): enlace al sitio principal.
 *
 * El tema visual del tenant llegará en M04. Por ahora se usa el color
 * primario por defecto como variable CSS.
 */

if (isset($tenant) && is_array($tenant)) {
    $tenant_data = $tenant;
} elseif (!isset($tenant_data) || !is_array($tenant_data)) {
    $tenant_data = [];
}

$tenant_nombre = trim((string)($tenant_data['org_nombre_publico'] ?? ''));
if ($tenant_nombre === '') {
    $tenant_nombre = 'Organizador';
}

$tenant_inicial = mb_strtoupper(mb_substr($tenant_nombre, 0, 1));
$page_title = isset($page_title) && is_string($page_title) && trim($page_title) !== ''
    ? trim($page_title)
    : $tenant_nombre . ' | Sitio en construcción';

$url_principal = isset($url_principal) && is_string($url_principal) ? trim($url_principal) : '';
if ($url_principal === '' || $url_principal === '/') {
    $url_principal = base_url('/');
}
if ($url_principal === '') {
    $url_principal = '#';
}
if ($url_principal !== '#' && !preg_match('#^(https?://|/(?!/))#i', $url_principal)) {
    $url_principal = '#';
}

/*
 * En entorno local el controlador público reescribe base_url a /sitio/<slug>/.
 * Los assets viven en la raíz de la aplicación, así que se elimina ese
 * segmento para conservar rutas estables. En un host tenant real no hay
 * segmento /sitio/ y base_url ya apunta al dominio correcto.
 */
$asset_base = (string)base_url();
$asset_base = preg_replace('#/sitio/[^/]+/?$#', '/', $asset_base);
$asset_base = rtrim((string)$asset_base, '/') . '/';

$logo_url = '';
foreach (['org_logo', 'org_logo_url', 'logo_url', 'tenant_logo'] as $logo_key) {
    if (!empty($tenant_data[$logo_key]) && is_string($tenant_data[$logo_key])) {
        $logo_url = trim($tenant_data[$logo_key]);
        break;
    }
}
if ($logo_url !== '' && !preg_match('#^(https?://|/)#i', $logo_url)) {
    $logo_url = $asset_base . ltrim($logo_url, '/');
}
if ($logo_url !== '' && !preg_match('#^(https?://|/)#i', $logo_url)) {
    $logo_url = '';
}

$primary_color = isset($tenant_data['org_color_primario']) && is_string($tenant_data['org_color_primario'])
    ? trim($tenant_data['org_color_primario'])
    : '#009EF7';
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
    $primary_color = '#009EF7';
}

// Contraste legible para elementos que usan el color primario como fondo.
$r = hexdec(substr($primary_color, 1, 2));
$g = hexdec(substr($primary_color, 3, 2));
$b = hexdec(substr($primary_color, 5, 2));
$luminancia = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
$primary_contrast = $luminancia > 0.6 ? '#111827' : '#FFFFFF';
$primary_rgb = $r . ', ' . $g . ', ' . $b;

$content = isset($content) ? $content : '';
$anio = (isset($anio) && is_numeric($anio)) ? (int)$anio : (int)date('Y');
$robots = isset($robots) && is_string($robots) && trim($robots) !== ''
    ? trim($robots)
    : 'index, follow';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($tenant_nombre . ' - Sitio oficial', ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="robots" content="<?php echo htmlspecialchars($robots, ENT_QUOTES, 'UTF-8'); ?>" />


    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="<?php echo htmlspecialchars($asset_base . 'assets/plugins/global/plugins.bundle.css', ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo htmlspecialchars($asset_base . 'assets/css/style.bundle.css', ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" type="text/css" />

    <style>
        :root {
            --bs-primary: <?php echo htmlspecialchars($primary_color, ENT_QUOTES, 'UTF-8'); ?>;
            --bs-primary-rgb: <?php echo htmlspecialchars($primary_rgb, ENT_QUOTES, 'UTF-8'); ?>;
            --tenant-primary: <?php echo htmlspecialchars($primary_color, ENT_QUOTES, 'UTF-8'); ?>;
            --tenant-primary-contrast: <?php echo htmlspecialchars($primary_contrast, ENT_QUOTES, 'UTF-8'); ?>;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .public-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .public-main {
            flex: 1 0 auto;
        }

        .public-header {
            background-color: var(--bs-body-bg);
            border-bottom: 1px solid var(--bs-border-color);
        }

        .public-accent {
            height: 4px;
            background-color: var(--tenant-primary);
        }

        .public-brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background-color: var(--tenant-primary);
            color: var(--tenant-primary-contrast);
            font-weight: 700;
            font-size: 1.25rem;
            line-height: 1;
            overflow: hidden;
            flex-shrink: 0;
        }

        .public-brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .public-footer {
            background-color: var(--bs-body-bg);
            border-top: 1px solid var(--bs-border-color);
        }

        .public-skip-link {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 1080;
            padding: 0.75rem 1rem;
            border-radius: 0 0 0.5rem 0;
            background-color: var(--tenant-primary);
            color: var(--tenant-primary-contrast);
        }

        .public-skip-link:focus {
            left: 0;
        }

        .public-hero-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 78px;
            height: 78px;
            border-radius: 22px;
            background-color: var(--tenant-primary);
            color: var(--tenant-primary-contrast);
        }

        .public-contact-link {
            color: var(--tenant-primary);
            text-decoration: none;
            font-weight: 600;
            word-break: break-word;
        }

        .public-contact-link:hover,
        .public-contact-link:focus {
            text-decoration: underline;
        }

        .public-tenant-link {
            color: var(--bs-secondary-color);
            text-decoration: none;
        }

        .public-tenant-link:hover,
        .public-tenant-link:focus {
            color: var(--tenant-primary);
            text-decoration: underline;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
<a class="public-skip-link" href="#contenido-principal">Saltar al contenido principal</a>

<div class="public-shell">
    <div class="public-accent" aria-hidden="true"></div>

    <header class="public-header">
        <div class="container py-4 py-lg-5">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <span class="public-brand-mark" aria-hidden="true">
                        <?php if ($logo_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="" />
                        <?php else: ?>
                            <?php echo htmlspecialchars($tenant_inicial, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </span>
                    <div class="d-flex flex-column min-w-0">
                        <span class="fw-bolder text-gray-900 fs-3 text-truncate"><?php echo htmlspecialchars($tenant_nombre, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="text-muted fs-8">Sitio oficial del organizador</span>
                    </div>
                </div>

                <?php if ($url_principal !== '#'): ?>
                <a href="<?php echo htmlspecialchars($url_principal, ENT_QUOTES, 'UTF-8'); ?>"
                    class="public-tenant-link fs-7 fw-semibold d-inline-flex align-items-center gap-2">
                    <i class="ki-duotone ki-arrow-left fs-5" aria-hidden="true">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Ir al sitio principal
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main id="contenido-principal" class="public-main" tabindex="-1">
        <?php echo $content; ?>
    </main>

    <footer class="public-footer">
        <div class="container py-5">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 text-muted fs-8">
                <span>&copy; <?php echo htmlspecialchars($anio, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($tenant_nombre, ENT_QUOTES, 'UTF-8'); ?>. Todos los derechos reservados.</span>
                <span>Impulsado por Sortea2</span>
            </div>
        </div>
    </footer>
</div>

<script>
(function () {
    try {
        var tema = localStorage.getItem('data-bs-theme');
        if (tema === 'dark' || tema === 'light') {
            document.documentElement.setAttribute('data-bs-theme', tema);
        }
    } catch (e) {}
})();
</script>
<script src="<?php echo htmlspecialchars($asset_base . 'assets/plugins/global/plugins.bundle.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($asset_base . 'assets/js/scripts.bundle.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
