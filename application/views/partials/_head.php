<!DOCTYPE html>
<html lang="es">
<!--begin::Head-->
<head>
    <base href="<?php echo base_url(); ?>" />
    <title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> | <?php echo htmlspecialchars($app_name ?? 'Sistema'); ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php $this->load->view('partials/_csrf'); ?>
    <?php $this->load->view('partials/_google_analytics'); ?>

    <!--begin::Favicon-->
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($theme_settings['favicon_url'] ?? base_url('assets/media/logos/favicon.ico')); ?>" />
    <!--end::Favicon-->

    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <?php if (!empty($theme_settings['google_font_url'])): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($theme_settings['google_font_url']); ?>" />
    <?php endif; ?>
    <!--end::Fonts-->

    <!--begin::Global Stylesheets Bundle (obligatorio en todas las páginas)-->
    <link href="<?php echo base_url('assets/plugins/global/plugins.bundle.css'); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url('assets/css/style.bundle.css'); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url('assets/css/demo1-dark-sidebar.css'); ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->

    <?php if (!empty($page_styles)): ?>
    <!--begin::Estilos específicos de esta página-->
    <?php foreach ((array)$page_styles as $style): ?>
    <link href="<?php echo base_url(htmlspecialchars($style)); ?>" rel="stylesheet" type="text/css" />
    <?php endforeach; ?>
    <!--end::Estilos específicos de esta página-->
    <?php endif; ?>

    <!--begin::Anti click-jacking-->
    <script>
        if (window.top != window.self) {
            window.top.location.replace(window.self.location.href);
        }
    </script>
    <!--end::Anti click-jacking-->

    <?php if (!empty($theme_css)): ?>
    <style><?php echo $theme_css; ?></style>
    <?php endif; ?>
</head>
<!--end::Head-->
