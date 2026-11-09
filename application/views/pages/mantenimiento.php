<!--begin::Card Mantenimiento-->
<div class="card card-flush shadow-sm">
    <div class="card-body text-center py-20">

        <i class="ki-duotone ki-setting-3 fs-5tx text-warning mb-8">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
            <span class="path4"></span>
            <span class="path5"></span>
        </i>

        <h1 class="fw-bolder fs-2hx text-gray-900 mb-4"><?php echo htmlspecialchars($mantenimiento_titulo ?? 'Sistema en Mantenimiento'); ?></h1>

        <div class="fw-semibold fs-5 text-gray-600 mb-10 mx-auto" style="max-width: 600px; line-height: 1.6;">
            <?php echo nl2br(htmlspecialchars($mantenimiento_mensaje ?? 'Estamos actualizando el sistema para ofrecerte una mejor experiencia. Volveremos en breve.')); ?>
        </div>

        <a href="<?php echo base_url('logout'); ?>" class="btn btn-sm btn-light-danger fw-semibold px-4 py-2">
            <i class="ki-duotone ki-entrance-left fs-4 me-1">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Cerrar Sesión
        </a>

    </div>
</div>
<!--end::Card Mantenimiento-->

