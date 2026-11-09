<?php
$auth_layout = $auth_layout ?? 'branded';
?>
<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
<head>
    <base href="<?php echo base_url(); ?>" />
    <title><?php echo htmlspecialchars($page_title ?? 'Sign in', ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($app_name ?? 'System', ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php $this->load->view('partials/_csrf'); ?>
    <meta name="referrer" content="no-referrer" />
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($theme_settings['favicon_url'] ?? base_url('assets/media/logos/favicon.ico')); ?>" />
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <?php if (!empty($theme_settings['google_font_url'])): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($theme_settings['google_font_url']); ?>" />
    <?php endif; ?>
    <!--end::Fonts-->
    <!--begin::Global Stylesheets Bundle-->
    <link href="<?php echo base_url('assets/plugins/global/plugins.bundle.css'); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url('assets/css/style.bundle.css'); ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    <?php if (!empty($theme_css)): ?>
    <style><?php echo $theme_css; ?></style>
    <?php endif; ?>
    <script>if (window.top != window.self) { window.top.location.replace(window.self.location.href); }</script>
</head>
<!--end::Head-->

<!--begin::Body-->
<body id="kt_body" class="app-blank bgi-size-cover bgi-attachment-fixed bgi-position-center">

<!--begin::Tema-->
<script>
    var defaultThemeMode = "light";
    var themeMode;
    if (document.documentElement) {
        if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
            themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
        } else {
            themeMode = localStorage.getItem("data-bs-theme") !== null
                ? localStorage.getItem("data-bs-theme")
                : defaultThemeMode;
        }
        if (themeMode === "system") {
            themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }
        document.documentElement.setAttribute("data-bs-theme", themeMode);
    }
</script>
<!--end::Tema-->

<!--begin::Fondo-->
<style>
    body { background-image: url('<?php echo htmlspecialchars($theme_settings['login_background_url'] ?? base_url('assets/media/auth/bg-10.png')); ?>'); }
    [data-bs-theme="dark"] body { background-image: url('<?php echo htmlspecialchars($theme_settings['login_background_dark_url'] ?? base_url('assets/media/auth/bg-10-dark.png')); ?>'); }
</style>
<!--end::Fondo-->

<div class="d-flex flex-column flex-root" id="kt_app_root">

<?php if ($auth_layout === 'classic'): ?>
<!--begin::Classic Layout (Centrado Demo10)-->
<div class="d-flex flex-column flex-center flex-column-fluid p-6 p-lg-10 min-vh-100">
    <div class="w-100 mw-425px">
<?php else: ?>
<!--begin::Branded/Split Layout-->
<div class="d-flex flex-column flex-lg-row flex-column-fluid">

<!--begin::Panel izquierdo (compartido en páginas branded)-->
<div class="d-flex flex-lg-row-fluid">
    <div class="d-flex flex-column flex-center pb-0 pb-lg-10 p-10 w-100">
        <img class="theme-light-show mx-auto mw-100 w-150px w-lg-300px mb-10 mb-lg-20"
            src="<?php echo htmlspecialchars($theme_settings['login_logo_url'] ?? base_url('assets/media/auth/agency.png')); ?>" alt="" />
        <img class="theme-dark-show mx-auto mw-100 w-150px w-lg-300px mb-10 mb-lg-20"
            src="<?php echo htmlspecialchars($theme_settings['login_logo_dark_url'] ?? base_url('assets/media/auth/agency-dark.png')); ?>" alt="" />
        <h1 class="text-gray-800 fs-2qx fw-bold text-center mb-7">
            <?php echo htmlspecialchars($theme_settings['login_welcome_title'] ?? ($app_name ?? 'System')); ?>
        </h1>
        <div class="text-gray-600 fs-base text-center fw-semibold">
            <?php echo htmlspecialchars($theme_settings['login_welcome_subtitle'] ?? 'Sign in with your credentials to continue.'); ?>
        </div>
    </div>
</div>
<!--end::Panel izquierdo-->
<?php endif; ?>
