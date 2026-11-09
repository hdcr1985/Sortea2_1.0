<?php
$page_title  = 'Sign in';
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

<!--begin::Card Login Demo10 Classic-->
<div class="card card-flush shadow-sm rounded-4 border border-gray-200 border-opacity-75 bg-body">
    <div class="card-body p-8 p-lg-10">

        <form class="form w-100" method="POST" action="<?php echo base_url('login/entrar'); ?>" id="kt_sign_in_form">
            <input type="hidden" name="login_csrf" value="<?php echo htmlspecialchars($login_csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

            <!--begin::Heading-->
            <div class="text-center mb-7">
                <h1 class="text-gray-900 fw-bold fs-2 mb-2">
                    <?php echo htmlspecialchars($theme_settings['login_form_title'] ?? 'Sign in'); ?>
                </h1>
                <div class="text-gray-500 fs-7 fw-medium d-flex justify-content-center align-items-center gap-1">
                    <span>Don't have an account?</span>
                    <?php if (!empty($public_registration_available)): ?>
                        <a href="<?php echo base_url('registro'); ?>" class="link-primary fw-semibold">Sign up</a>
                    <?php else: ?>
                        <span class="text-muted">Contact your administrator</span>
                    <?php endif; ?>
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

            <!--begin::Separator (Or)-->
            <div class="d-flex align-items-center mb-6">
                <span class="border-top border-gray-200 border-opacity-75 flex-grow-1"></span>
                <span class="text-gray-400 fs-8 fw-bold text-uppercase px-3">Or sign in with your username</span>
                <span class="border-top border-gray-200 border-opacity-75 flex-grow-1"></span>
            </div>
            <!--end::Separator-->
            <?php endif; ?>

            <!--begin::Alerta de exito-->
            <?php if (!empty($success)): ?>
            <div class="alert alert-success d-flex align-items-center p-4 mb-6 rounded-3">
                <i class="ki-duotone ki-check-circle fs-2 text-success me-3">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <div class="fs-7 fw-medium"><?php echo htmlspecialchars($success); ?></div>
            </div>
            <?php endif; ?>
            <!--end::Alerta de exito-->

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

            <!--begin::Campo usuario-->
            <div class="fv-row mb-5">
                <label class="form-label fs-7 fw-semibold text-gray-800 mb-1">Username</label>
                <input type="text"
                    name="usuario"
                    placeholder="Enter your username"
                    autocomplete="username"
                    class="form-control form-control-solid bg-transparent"
                    value="<?php echo set_value('usuario'); ?>"
                    required />
            </div>
            <!--end::Campo usuario-->

            <!--begin::Campo contraseña-->
            <div class="fv-row mb-5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fs-7 fw-semibold text-gray-800 mb-0">Password</label>
                    <a href="<?php echo base_url('recuperar-clave'); ?>" class="link-primary fs-7 fw-semibold">
                        Forgot your password?
                    </a>
                </div>
                <div class="position-relative">
                    <input type="password"
                        id="login_clave"
                        name="clave"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        class="form-control form-control-solid bg-transparent pe-12"
                        required />
                    <button type="button"
                        class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2 text-gray-500 hover:text-primary"
                        id="btn_toggle_password"
                        tabindex="-1">
                        <i class="ki-duotone ki-eye fs-2" id="icon_eye_show">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <i class="ki-duotone ki-eye-slash fs-2 d-none" id="icon_eye_hide">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </button>
                </div>
            </div>
            <!--end::Campo contraseña-->

            <!--begin::Remember me-->
            <div class="fv-row mb-6">
                <label class="form-check form-check-custom form-check-solid form-check-sm">
                    <input class="form-check-input" type="checkbox" name="recordarme" value="1" />
                    <span class="form-check-label text-gray-700 fs-7 fw-medium">
                        Remember me
                    </span>
                </label>
            </div>
            <!--end::Remember me-->

            <!--begin::Boton ingresar-->
            <div class="d-grid mb-2">
                <button type="submit" id="kt_sign_in_submit" class="btn btn-primary fw-semibold py-3">
                    <span class="indicator-label">Sign in</span>
                    <span class="indicator-progress">
                        Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Boton ingresar-->

        </form>

    </div>
</div>
<!--end::Card Login Demo10 Classic-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('btn_toggle_password');
    var passwordInput = document.getElementById('login_clave');
    var eyeShow = document.getElementById('icon_eye_show');
    var eyeHide = document.getElementById('icon_eye_hide');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeShow.classList.add('d-none');
                eyeHide.classList.remove('d-none');
            } else {
                passwordInput.type = 'password';
                eyeShow.classList.remove('d-none');
                eyeHide.classList.add('d-none');
            }
        });
    }

    // Spinner en submit
    var form = document.getElementById('kt_sign_in_form');
    var submitBtn = document.getElementById('kt_sign_in_submit');
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        });
    }
});
</script>

<?php $this->load->view('auth/_auth_bottom', ['auth_layout' => 'classic']); ?>

