<!--begin::Card 404-->
<div class="card card-flush shadow-sm">
    <div class="card-body d-flex flex-column flex-center text-center py-15 py-lg-20">

        <div class="mb-10">
            <img src="<?php echo base_url('assets/media/illustrations/19.svg'); ?>"
                 alt=""
                 class="error-404-illustration-light mw-100"
                 style="max-height: 160px;">
            <img src="<?php echo base_url('assets/media/illustrations/19-dark.svg'); ?>"
                 alt=""
                 class="error-404-illustration-dark mw-100"
                 style="max-height: 160px;">
        </div>

        <span class="badge badge-primary badge-outline fs-7 fw-semibold mb-3">404 Error</span>

        <h1 class="fw-bold fs-2hx text-gray-900 mb-2">No encontramos esta página</h1>

        <div class="fs-6 fw-semibold text-gray-500 mb-10 mx-auto" style="max-width: 520px; line-height: 1.6;">
            La página o el recurso que buscas no existe, fue movido o ya no está disponible.
        </div>

        <a href="<?php echo base_url('dashboard'); ?>" class="btn btn-primary">
            <i class="ki-duotone ki-arrow-left fs-4 me-1">
                <span class="path1"></span><span class="path2"></span>
            </i>
            Volver al Dashboard
        </a>

    </div>
</div>
<!--end::Card 404-->

<style>
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
