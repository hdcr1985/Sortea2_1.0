<?php
$page_title  = 'Reset password';
$auth_layout = 'classic';
$this->load->view('auth/_auth_top', ['auth_layout' => 'classic', 'page_title' => $page_title]);
?>

<!--begin::Logo del proyecto-->
<div class="text-center mb-8">
    <a href="<?php echo base_url(); ?>" class="d-inline-block">
        <img alt="<?php echo htmlspecialchars($app_name ?? 'Logo'); ?>"
            src="<?php echo htmlspecialchars($theme_settings['login_logo_url'] ?? base_url('assets/media/logos/default.svg')); ?>"
            class="h-45px h-lg-50px theme-light-show" />
        <img alt="<?php echo htmlspecialchars($app_name ?? 'Logo'); ?>"
            src="<?php echo htmlspecialchars($theme_settings['login_logo_dark_url'] ?? base_url('assets/media/logos/default-dark.svg')); ?>"
            class="h-45px h-lg-50px theme-dark-show" />
    </a>
</div>
<!--end::Logo del proyecto-->

<!--begin::Card Demo10 Classic-->
<div class="card card-flush shadow-sm rounded-4 border border-gray-200 border-opacity-75 bg-body">
    <div class="card-body p-8 p-lg-10">

        <?php if (!empty($enviado)): ?>
        <!--begin::Confirmación de envío (Check Email Demo10)-->
        <div class="text-center py-4">
            <div class="d-flex justify-content-center mb-6">
                <div class="symbol symbol-70px symbol-circle bg-light-primary">
                    <i class="ki-duotone ki-sms fs-2tx text-primary">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <h2 class="text-gray-900 fw-bold fs-3 mb-3">Check your email</h2>
            <div class="text-gray-600 fs-7 fw-medium mb-7">
                We've sent password reset instructions to:
                <div class="text-gray-900 fw-bold mt-1"><?php echo htmlspecialchars($correo_enviado ?? ''); ?></div>
            </div>

            <div class="d-grid mb-6">
                <a href="<?php echo base_url('login'); ?>" class="btn btn-primary fw-semibold py-3">
                    Back to sign in
                </a>
            </div>

            <div class="text-center">
                <span class="text-gray-500 fs-8">Didn't receive the email?</span>
                <a href="<?php echo base_url('recuperar-clave'); ?>" class="link-primary fs-8 fw-semibold ms-1">
                    Try again
                </a>
            </div>
        </div>
        <!--end::Confirmación-->

        <?php else: ?>

        <!--begin::Formulario Enter Email Demo10-->
        <form class="form w-100" method="POST" action="<?php echo base_url('recuperar-clave/enviar'); ?>" id="kt_password_reset_form">
            <input type="hidden" name="recovery_csrf" value="<?php echo htmlspecialchars($reset_csrf ?? ''); ?>" />

            <!--begin::Heading-->
            <div class="text-center mb-7">
                <h1 class="text-gray-900 fw-bold fs-2 mb-2">Forgot your password?</h1>
                <div class="text-gray-500 fs-7 fw-medium">
                    Enter your registered email address and we'll send you password reset instructions.
                </div>
            </div>
            <!--end::Heading-->

            <!--begin::Alerta de error-->
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center p-4 mb-6 rounded-3">
                <i class="ki-duotone ki-shield-cross fs-2 text-danger me-3">
                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                </i>
                <div class="fs-7 fw-medium"><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>
            <!--end::Alerta de error-->

            <!--begin::Campo correo-->
            <div class="fv-row mb-6">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Email address</label>
                <input type="email"
                    name="correo"
                    placeholder="name@example.com"
                    autocomplete="email"
                    class="form-control form-control-solid bg-transparent"
                    value="<?php echo set_value('correo'); ?>"
                    required />
            </div>
            <!--end::Campo correo-->

            <!--begin::Boton Enviar-->
            <div class="d-grid mb-4">
                <button type="submit" id="kt_password_reset_submit" class="btn btn-primary fw-semibold py-3">
                    <span class="indicator-label">Send reset instructions</span>
                    <span class="indicator-progress">
                        Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Boton Enviar-->

            <!--begin::Cancelar-->
            <div class="text-center">
                <a href="<?php echo base_url('login'); ?>" class="btn btn-light-secondary text-gray-700 w-100 fw-semibold py-3">
                    Cancel
                </a>
            </div>
            <!--end::Cancelar-->

        </form>
        <!--end::Formulario-->
        <?php endif; ?>

    </div>
</div>
<!--end::Card Demo10 Classic-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('kt_password_reset_form');
    var btn = document.getElementById('kt_password_reset_submit');
    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.setAttribute('data-kt-indicator', 'on');
            btn.disabled = true;
        });
    }
});
</script>

<?php $this->load->view('auth/_auth_bottom', ['auth_layout' => 'classic']); ?>
