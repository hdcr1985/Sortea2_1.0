<?php
/**
 * M01 - Núcleo multi-tenant y subdominios
 * Página pública placeholder del organizador.
 *
 * Por defecto esta vista se renderiza como contenido de `publico/layout`
 * (así la usa `Publico_Controller::render_publico()`). Para renderizarla de
 * forma autónoma, el controlador debe enviar `$render_layout_autonomo = TRUE`.
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

$tenant_email = trim((string)($tenant_data['org_email'] ?? ''));
if ($tenant_email !== '' && !filter_var($tenant_email, FILTER_VALIDATE_EMAIL)) {
    $tenant_email = '';
}

$tenant_telefono = trim((string)($tenant_data['org_telefono'] ?? ''));
$tenant_tel_href = preg_replace('/[^0-9+]/', '', $tenant_telefono);

$url_principal = isset($url_principal) && is_string($url_principal) ? trim($url_principal) : '';
if ($url_principal === '') {
    $url_principal = base_url('/');
}
if ($url_principal === '') {
    $url_principal = '#';
}
if ($url_principal !== '#' && !preg_match('#^(https?://|/(?!/))#i', $url_principal)) {
    $url_principal = '#';
}

$page_title_publico = isset($page_title) && is_string($page_title) && trim($page_title) !== ''
    ? trim($page_title)
    : $tenant_nombre . ' | Sitio en construcción';

ob_start();
?>
<section class="py-10 py-lg-15">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8 text-center">
                <span class="public-hero-icon mb-6" aria-hidden="true">
                    <i class="ki-duotone ki-shop fs-2hx"></i>
                </span>

                <div class="mb-4">
                    <span class="badge badge-light-primary fw-bold fs-7 px-3 py-2">
                        <i class="ki-duotone ki-information-2 fs-6 text-primary me-1">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        Sitio en construcción
                    </span>
                </div>

                <h1 class="fw-bolder text-gray-900 fs-2x mb-3"><?php echo htmlspecialchars($tenant_nombre, ENT_QUOTES, 'UTF-8'); ?></h1>

                <p class="text-muted fs-5 fw-semibold mb-2">Muy pronto encontrarás aquí las rifas activas.</p>
                <p class="text-muted fs-7 mb-8">
                    Estamos preparando este espacio para que puedas participar en las próximas rifas.
                </p>

                <?php if ($tenant_email !== '' || $tenant_telefono !== ''): ?>
                <div class="card card-flush shadow-sm border border-gray-200 mb-8">
                    <div class="card-body p-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-3">Contacto del organizador</div>
                        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-3 gap-sm-5">
                            <?php if ($tenant_email !== ''): ?>
                            <a class="public-contact-link d-inline-flex align-items-center gap-2 fs-7"
                                href="mailto:<?php echo htmlspecialchars($tenant_email, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="ki-duotone ki-sms fs-5" aria-hidden="true">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <?php echo htmlspecialchars($tenant_email, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php endif; ?>

                            <?php if ($tenant_telefono !== ''): ?>
                            <a class="public-contact-link d-inline-flex align-items-center gap-2 fs-7"
                                href="tel:<?php echo htmlspecialchars($tenant_tel_href, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="ki-duotone ki-phone fs-5" aria-hidden="true">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <?php echo htmlspecialchars($tenant_telefono, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($url_principal !== '#'): ?>
                <a href="<?php echo htmlspecialchars($url_principal, ENT_QUOTES, 'UTF-8'); ?>"
                    class="public-tenant-link d-inline-flex align-items-center gap-2 fs-8 fw-semibold">
                    <i class="ki-duotone ki-arrow-left fs-5" aria-hidden="true">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Volver al sitio principal
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php
$contenido_publico = ob_get_clean();

if (empty($render_layout_autonomo) || !empty($solo_contenido)) {
    echo $contenido_publico;
    return;
}

$this->load->view('publico/layout', [
    'content'       => $contenido_publico,
    'page_title'    => $page_title_publico,
    'tenant'        => $tenant_data,
    'tenant_data'   => $tenant_data,
    'url_principal' => $url_principal,
]);
?>
