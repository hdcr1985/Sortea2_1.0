<?php
$page_title  = 'Create account';
$auth_layout = 'classic';
$old         = is_array($old ?? NULL) ? $old : [];
$errores     = is_array($errores ?? NULL) ? $errores : [];
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

<!--begin::Card Registro Demo10 Classic-->
<div class="card card-flush shadow-sm rounded-4 border border-gray-200 border-opacity-75 bg-body">
    <div class="card-body p-8 p-lg-10">

        <!--begin::Heading-->
        <div class="text-center mb-7">
            <h1 class="text-gray-900 fw-bold fs-2 mb-2">Create an account</h1>
            <div class="text-gray-500 fs-7 fw-medium d-flex justify-content-center align-items-center gap-1">
                <span>Already have an account?</span>
                <a href="<?php echo base_url('login'); ?>" class="link-primary fw-semibold">Sign in</a>
            </div>
        </div>
        <!--end::Heading-->

        <?php
        $google_activo = !empty($theme_settings['google_auth_enabled']);
        $apple_activo  = !empty($theme_settings['apple_auth_enabled']);
        $social_activo = $google_activo || $apple_activo;
        $social_cols   = ($google_activo && $apple_activo) ? 'col-6' : 'col-12';
        ?>

        <?php if ($social_activo): ?>
        <!--begin::Social Buttons (Google & Apple)-->
        <div class="row g-3 mb-6">
            <?php if ($google_activo): ?>
            <div class="<?php echo $social_cols; ?>">
                <a href="<?php echo base_url('auth/oauth-google'); ?>" class="btn btn-outline btn-outline-default btn-active-light-primary w-100 d-flex justify-content-center align-items-center py-3 fs-7 fw-semibold text-gray-700">
                    <img alt="Google" class="h-18px me-2" src="<?php echo base_url('assets/media/svg/brand-logos/google.svg'); ?>" />
                    Google
                </a>
            </div>
            <?php endif; ?>

            <?php if ($apple_activo): ?>
            <div class="<?php echo $social_cols; ?>">
                <a href="<?php echo base_url('auth/oauth-apple'); ?>" class="btn btn-outline btn-outline-default btn-active-light-primary w-100 d-flex justify-content-center align-items-center py-3 fs-7 fw-semibold text-gray-700">
                    <img alt="Apple" class="h-18px me-2 theme-light-show" src="<?php echo base_url('assets/media/svg/brand-logos/apple-black.svg'); ?>" />
                    <img alt="Apple" class="h-18px me-2 theme-dark-show" src="<?php echo base_url('assets/media/svg/brand-logos/apple-white.svg'); ?>" />
                    Apple
                </a>
            </div>
            <?php endif; ?>
        </div>
        <!--end::Social Buttons-->

        <!--begin::Separator-->
        <div class="d-flex align-items-center mb-6">
            <span class="border-top border-gray-200 border-opacity-75 flex-grow-1"></span>
            <span class="text-gray-400 fs-8 fw-bold text-uppercase px-3">Or use the form</span>
            <span class="border-top border-gray-200 border-opacity-75 flex-grow-1"></span>
        </div>
        <!--end::Separator-->
        <?php endif; ?>

        <!--begin::Alertas-->
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center p-4 mb-6 rounded-3">
            <i class="ki-duotone ki-shield-cross fs-2 text-danger me-3">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <div class="fs-7 fw-medium"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center p-4 mb-6 rounded-3">
            <i class="ki-duotone ki-check-circle fs-2 text-success me-3">
                <span class="path1"></span><span class="path2"></span>
            </i>
            <div class="fs-7 fw-medium"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php endif; ?>
        <!--end::Alertas-->

        <!--begin::Formulario-->
        <form class="form w-100" method="POST" action="<?php echo base_url('registro/enviar'); ?>" id="kt_sign_up_form">
            <input type="hidden" name="registration_csrf" value="<?php echo htmlspecialchars($registration_csrf, ENT_QUOTES, 'UTF-8'); ?>" />

            <!--begin::Campo Nombre-->
            <div class="fv-row mb-5">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Full name</label>
                <input type="text"
                    name="usu_nombre"
                    maxlength="100"
                    placeholder="Your full name"
                    class="form-control form-control-solid bg-transparent"
                    value="<?php echo htmlspecialchars((string)($old['usu_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    required />
                <?php if (!empty($errores['usu_nombre'])): ?><div class="text-danger fs-8 mt-1"><?php echo htmlspecialchars($errores['usu_nombre'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </div>
            <!--end::Campo Nombre-->

            <!--begin::Campo Usuario-->
            <div class="fv-row mb-5">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Username</label>
                <input type="text"
                    name="usu_usuario"
                    maxlength="50"
                    placeholder="username123"
                    autocomplete="username"
                    class="form-control form-control-solid bg-transparent"
                    value="<?php echo htmlspecialchars((string)($old['usu_usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    required />
                <?php if (!empty($errores['usu_usuario'])): ?><div class="text-danger fs-8 mt-1"><?php echo htmlspecialchars($errores['usu_usuario'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </div>
            <!--end::Campo Usuario-->

            <!--begin::Campo Correo-->
            <div class="fv-row mb-5">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Email address</label>
                <input type="email"
                    name="usu_correo"
                    maxlength="150"
                    placeholder="name@example.com"
                    autocomplete="email"
                    class="form-control form-control-solid bg-transparent"
                    value="<?php echo htmlspecialchars((string)($old['usu_correo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    required />
                <?php if (!empty($errores['usu_correo'])): ?><div class="text-danger fs-8 mt-1"><?php echo htmlspecialchars($errores['usu_correo'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </div>
            <!--end::Campo Correo-->

            <!--begin::Campo Contraseña-->
            <div class="fv-row mb-5">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Password</label>
                <div class="position-relative">
                    <input type="password"
                        id="reg_clave"
                        name="usu_clave"
                        minlength="8"
                        maxlength="72"
                        placeholder="At least 8 characters"
                        autocomplete="new-password"
                        class="form-control form-control-solid bg-transparent pe-12"
                        required />
                    <button type="button"
                        class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2 text-gray-500 hover:text-primary"
                        id="btn_toggle_reg_pass"
                        tabindex="-1">
                        <i class="ki-duotone ki-eye fs-2" id="icon_reg_show">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <i class="ki-duotone ki-eye-slash fs-2 d-none" id="icon_reg_hide">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </button>
                </div>
                <?php if (!empty($errores['usu_clave'])): ?><div class="text-danger fs-8 mt-1"><?php echo htmlspecialchars($errores['usu_clave'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </div>
            <!--end::Campo Contraseña-->

            <!--begin::Confirmar Contraseña-->
            <div class="fv-row mb-6">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Confirm password</label>
                <div class="position-relative">
                    <input type="password"
                        id="reg_confirm"
                        name="clave_confirm"
                        minlength="8"
                        maxlength="72"
                        placeholder="Repeat your password"
                        autocomplete="new-password"
                        class="form-control form-control-solid bg-transparent pe-12"
                        required />
                    <button type="button"
                        class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2 text-gray-500 hover:text-primary"
                        id="btn_toggle_reg_confirm"
                        tabindex="-1">
                        <i class="ki-duotone ki-eye fs-2" id="icon_reg_confirm_show">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <i class="ki-duotone ki-eye-slash fs-2 d-none" id="icon_reg_confirm_hide">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </button>
                </div>
                <?php if (!empty($errores['clave_confirm'])): ?><div class="text-danger fs-8 mt-1"><?php echo htmlspecialchars($errores['clave_confirm'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </div>
            <!--end::Confirmar Contraseña-->

            <!--begin::Boton Crear Cuenta-->
            <div class="d-grid mb-4">
                <button type="submit" id="kt_sign_up_submit" class="btn btn-primary fw-semibold py-3">
                    <span class="indicator-label">Create account</span>
                    <span class="indicator-progress">
                        Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Boton Crear Cuenta-->

            <div class="text-center text-gray-500 fs-8">
                By signing up, you agree to the system's policies and terms.
            </div>

        </form>
        <!--end::Formulario-->

    </div>
</div>
<!--end::Card Registro Demo10 Classic-->

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

    setupToggle('btn_toggle_reg_pass', 'reg_clave', 'icon_reg_show', 'icon_reg_hide');
    setupToggle('btn_toggle_reg_confirm', 'reg_confirm', 'icon_reg_confirm_show', 'icon_reg_confirm_hide');

    var form = document.getElementById('kt_sign_up_form');
    var submitBtn = document.getElementById('kt_sign_up_submit');
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        });
    }
});
</script>

<?php $this->load->view('auth/_auth_bottom', ['auth_layout' => 'classic']); ?>

