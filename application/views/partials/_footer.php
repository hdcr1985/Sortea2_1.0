<!--begin::Footer-->
<div id="kt_app_footer" class="app-footer">
    <div class="app-container container-fluid d-flex flex-column flex-md-row flex-center flex-md-stack py-3">
        <div class="text-dark order-2 order-md-1">
            <span class="text-muted fw-semibold me-1"><?php echo date('Y'); ?> &copy;</span>
            <a href="<?php echo base_url(); ?>" class="text-gray-800 text-hover-primary">
                <?php echo htmlspecialchars($app_name ?? 'Sistema'); ?>
            </a>
            <span class="ms-2"><?php echo htmlspecialchars($theme_settings['footer_text'] ?? 'Todos los derechos reservados.'); ?></span>
        </div>
        <ul class="menu menu-gray-600 menu-hover-primary fw-semibold order-1">
            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($theme_settings['footer_support_url'] ?? '#'); ?>" class="menu-link px-2" target="_blank">
                    <?php echo htmlspecialchars($theme_settings['footer_support_label'] ?? 'Soporte'); ?>
                </a>
            </li>
        </ul>
    </div>
</div>
<!--end::Footer-->
