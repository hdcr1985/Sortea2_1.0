<?php
$page_title  = 'New password';
$auth_layout = 'classic';
$auth_script = 'custom/authentication/reset-password/new-password.js';
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

        <?php if (($token_status ?? 'invalid') !== 'valid'): ?>
        <!--begin::Token Inválido-->
        <div class="text-center py-4">
            <div class="d-flex justify-content-center mb-6">
                <div class="symbol symbol-70px symbol-circle bg-light-danger">
                    <i class="ki-duotone ki-shield-cross fs-2tx text-danger">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </div>
            </div>

            <h2 class="text-gray-900 fw-bold fs-3 mb-3">Invalid link</h2>
            <p class="text-gray-600 fs-7 fw-medium mb-7">
                The link has expired, has already been used, or is invalid. Request a new one to continue.
            </p>

            <div class="d-grid mb-3">
                <a href="<?php echo base_url('recuperar-clave'); ?>" class="btn btn-primary fw-semibold py-3">
                    Request another link
                </a>
            </div>
            <div class="text-center">
                <a href="<?php echo base_url('login'); ?>" class="link-primary fs-8 fw-semibold">
                    Back to sign in
                </a>
            </div>
        </div>
        <!--end::Token Inválido-->

        <?php else: ?>

        <!--begin::Formulario Change Password Demo10-->
        <form class="form w-100" method="POST" action="<?php echo base_url('nueva-clave/guardar'); ?>" id="kt_new_password_form">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>" />
            <input type="hidden" name="recovery_csrf" value="<?php echo htmlspecialchars($reset_csrf ?? ''); ?>" />

            <!--begin::Heading-->
            <div class="text-center mb-7">
                <h1 class="text-gray-900 fw-bold fs-2 mb-2">Set a new password</h1>
                <div class="text-gray-500 fs-7 fw-medium">
                    Remembered it?
                    <a href="<?php echo base_url('login'); ?>" class="link-primary fw-semibold ms-1">Sign in</a>
                </div>
            </div>
            <!--end::Heading-->

            <!--begin::Alerta de error-->
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center p-4 mb-6 rounded-3">
                <i class="ki-duotone ki-shield-cross fs-2 text-danger me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="fs-7 fw-medium"><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>
            <!--end::Alerta de error-->

            <!--begin::Nueva contraseña-->
            <div class="fv-row mb-5" data-kt-password-meter="true">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">New password</label>
                <div class="position-relative mb-2">
                    <input class="form-control form-control-solid bg-transparent pe-12"
                        type="password"
                        id="new_password_input"
                        name="clave"
                        placeholder="At least 8 characters"
                        autocomplete="new-password"
                        required />
                    <button type="button"
                        class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2 text-gray-500 hover:text-primary"
                        id="btn_toggle_new_pass"
                        tabindex="-1">
                        <i class="ki-duotone ki-eye fs-2" id="icon_new_show">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <i class="ki-duotone ki-eye-slash fs-2 d-none" id="icon_new_hide">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </button>
                </div>

                <!--begin::Medidor de fuerza-->
                <div class="d-flex align-items-center mb-2" data-kt-password-meter-control="highlight">
                    <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2"></div>
                    <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2"></div>
                    <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2"></div>
                    <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px"></div>
                </div>
                <div class="text-muted fs-8">Use 8 or more characters with letters, numbers, and symbols.</div>
                <!--end::Medidor de fuerza-->
            </div>
            <!--end::Nueva contraseña-->

            <!--begin::Confirmar contraseña-->
            <div class="fv-row mb-6">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Confirm new password</label>
                <div class="position-relative">
                    <input class="form-control form-control-solid bg-transparent pe-12"
                        type="password"
                        id="confirm_password_input"
                        name="clave_confirm"
                        placeholder="Repeat the new password"
                        autocomplete="new-password"
                        required />
                    <button type="button"
                        class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2 text-gray-500 hover:text-primary"
                        id="btn_toggle_confirm_pass"
                        tabindex="-1">
                        <i class="ki-duotone ki-eye fs-2" id="icon_confirm_show">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <i class="ki-duotone ki-eye-slash fs-2 d-none" id="icon_confirm_hide">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </button>
                </div>
            </div>
            <!--end::Confirmar contraseña-->

            <!--begin::Boton Enviar-->
            <div class="d-grid mb-4">
                <button type="submit" id="kt_new_password_submit" class="btn btn-primary fw-semibold py-3">
                    <span class="indicator-label">Save password</span>
                    <span class="indicator-progress">
                        Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Boton Enviar-->

        </form>
        <!--end::Formulario-->
        <?php endif; ?>

    </div>
</div>
<!--end::Card Demo10 Classic-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupToggle(btnId, inputId, showId, hideId) {
        var btn = document.getElementById(btnId);
        var input = document.getElementById(inputId);
        var show = document.getElementById(showId);
        var hide = document.getElementById(hideId);
        if (btn && input) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (input.type === 'password') {
                    input.type = 'text';
                    show.classList.add('d-none');
                    hide.classList.remove('d-none');
                } else {
                    input.type = 'password';
                    show.classList.remove('d-none');
                    hide.classList.add('d-none');
                }
            });
        }
    }

    setupToggle('btn_toggle_new_pass', 'new_password_input', 'icon_new_show', 'icon_new_hide');
    setupToggle('btn_toggle_confirm_pass', 'confirm_password_input', 'icon_confirm_show', 'icon_confirm_hide');

    var form = document.getElementById('kt_new_password_form');
    var submitBtn = document.getElementById('kt_new_password_submit');
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        });
    }
});
</script>

<?php $this->load->view('auth/_auth_bottom', ['auth_layout' => 'classic']); ?>
