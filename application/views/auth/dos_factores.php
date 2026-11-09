<?php
$page_title  = 'Two-step verification';
$auth_layout = 'classic';
$auth_script = ''; // Manejo inline garantizado sin problemas de caché de navegador
$this->load->view('auth/_auth_top', ['auth_layout' => $auth_layout, 'page_title' => $page_title]);
?>

<style>
/* Estilos compactos para inputs de código PIN en una sola línea */
.pin-input-container {
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
}
.pin-input-box {
    width: 42px !important;
    height: 48px !important;
    max-width: 44px !important;
    font-size: 1.45rem !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.pin-input-box:focus {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.2) !important;
    background-color: var(--bs-body-bg) !important;
}
@media (max-width: 380px) {
    .pin-input-box {
        width: 36px !important;
        height: 42px !important;
        font-size: 1.25rem !important;
    }
}
</style>

<!--begin::Card 2FA Demo10 Classic-->
<div class="card card-flush shadow-sm rounded-4 w-100 p-6 p-sm-9 bg-body border border-gray-200 border-opacity-75" id="2fa_form">
    
    <?php if (($metodo_2fa ?? 'email') === 'email'): ?>
    <form class="form w-100" method="POST" action="<?php echo base_url('dos-factores/verificar'); ?>" id="kt_sing_in_two_factor_form" autocomplete="off">
        
        <!--begin::Ilustración Metronic Demo 10-->
        <div class="text-center mb-5">
            <img alt="2FA" class="theme-light-show h-75px mb-2" src="<?php echo base_url('assets/media/illustrations/34.svg'); ?>" />
            <img alt="2FA" class="theme-dark-show h-75px mb-2" src="<?php echo base_url('assets/media/illustrations/34-dark.svg'); ?>" />
        </div>
        <!--end::Ilustración-->

        <!--begin::Heading-->
        <div class="text-center mb-6">
            <h2 class="text-gray-900 fw-bolder fs-2 mb-2">Two-step verification</h2>
            <div class="text-muted fw-semibold fs-7 mb-3">
                Enter the 6-digit code we sent to your email:
            </div>
            <div class="d-inline-flex align-items-center gap-2 bg-light-primary border border-primary border-opacity-25 py-1 px-3 rounded-pill">
                <i class="ki-duotone ki-sms fs-5 text-primary"><span class="path1"></span><span class="path2"></span></i>
                <span class="fw-bold text-gray-900 fs-7 font-monospace">
                    <?php echo htmlspecialchars($contacto_parcial ?? '******'); ?>
                </span>
            </div>
        </div>
        <!--end::Heading-->

        <!--begin::Alerta de éxito-->
        <?php if (!empty($success)): ?>
        <div class="alert alert-dismissible bg-light-success d-flex align-items-center p-3 mb-5 border border-success border-dashed">
            <i class="ki-duotone ki-check-circle fs-2 text-success me-3">
                <span class="path1"></span><span class="path2"></span>
            </i>
            <div class="d-flex flex-column pe-0 pe-sm-10">
                <span class="fs-7 fw-semibold text-gray-800"><?php echo htmlspecialchars($success); ?></span>
            </div>
            <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
                <i class="ki-duotone ki-cross fs-3 text-success"><span class="path1"></span><span class="path2"></span></i>
            </button>
        </div>
        <?php endif; ?>
        <!--end::Alerta de éxito-->

        <!--begin::Alerta de error-->
        <?php if (!empty($error)): ?>
        <div class="alert alert-dismissible bg-light-danger d-flex align-items-center p-3 mb-5 border border-danger border-dashed">
            <i class="ki-duotone ki-shield-cross fs-2 text-danger me-3">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <div class="d-flex flex-column pe-0 pe-sm-10">
                <span class="fs-7 fw-semibold text-gray-800"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
                <i class="ki-duotone ki-cross fs-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
            </button>
        </div>
        <?php endif; ?>
        <!--end::Alerta de error-->

        <!--begin::Inputs PIN de 6 dígitos en una sola línea-->
        <div class="mb-6">
            <div class="text-center text-muted fs-8 fw-semibold mb-3">
                Enter or paste the 6 digits below:
            </div>
            <div class="pin-input-container">
                <div class="d-flex flex-nowrap justify-content-between gap-2" data-kt-pin-input="true">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text"
                        name="code_<?php echo $i; ?>"
                        id="pin_code_<?php echo $i; ?>"
                        maxlength="1"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="<?php echo ($i === 1) ? 'one-time-code' : 'off'; ?>"
                        class="form-control form-control-solid text-center p-0 border border-gray-300 rounded-3 text-gray-900 pin-input-box"
                        value=""
                        required />
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <!--end::Inputs PIN-->

        <!--begin::Reenviar código con contador-->
        <div class="d-flex align-items-center justify-content-center gap-1 mb-6 fs-7">
            <span class="text-muted">Didn't receive the code?</span>
            <span id="countdown_timer" class="text-muted fw-semibold d-none">(<span id="countdown_seconds">45</span>s)</span>
            <a href="<?php echo base_url('dos-factores/reenviar'); ?>" class="link-primary fw-bold" id="link_reenviar_codigo">
                Resend code
            </a>
        </div>
        <!--end::Reenviar código-->

        <!--begin::Botón Continuar-->
        <div class="d-grid mb-5">
            <button type="submit" id="kt_sing_in_two_factor_submit" class="btn btn-primary fw-bold py-3 fs-6">
                <span class="indicator-label">
                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Continue
                </span>
                <span class="indicator-progress">
                    Verifying...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
        <!--end::Botón Continuar-->

        <?php if (!empty($totp_enabled)): ?>
        <div class="text-center mb-4">
            <a href="<?php echo base_url('dos-factores/usar-totp'); ?>" class="link-primary fw-bold fs-7">Usar aplicación autenticadora</a>
        </div>
        <?php endif; ?>

        <!--begin::Volver al login-->
        <div class="text-center">
            <a href="<?php echo base_url('login'); ?>" class="text-muted text-hover-primary fs-7 d-inline-flex align-items-center">
                <i class="ki-duotone ki-arrow-left fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                Back to sign in
            </a>
        </div>
        <!--end::Volver-->

    </form>
    <?php else: ?>
    <form class="form w-100" method="POST" action="<?php echo base_url('dos-factores/verificar'); ?>" id="kt_totp_verification_form" autocomplete="off">
        <div class="text-center mb-5">
            <img alt="Aplicación autenticadora" class="theme-light-show h-75px mb-2" src="<?php echo base_url('assets/media/illustrations/34.svg'); ?>" />
            <img alt="Aplicación autenticadora" class="theme-dark-show h-75px mb-2" src="<?php echo base_url('assets/media/illustrations/34-dark.svg'); ?>" />
        </div>

        <div class="text-center mb-6">
            <h2 class="text-gray-900 fw-bolder fs-2 mb-2">Aplicación autenticadora</h2>
            <div class="text-muted fw-semibold fs-7 mb-3">
                Ingresa el código de 6 dígitos de tu aplicación o un código de recuperación.
            </div>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-dismissible bg-light-success d-flex align-items-center p-3 mb-5 border border-success border-dashed">
            <i class="ki-duotone ki-check-circle fs-2 text-success me-3"><span class="path1"></span><span class="path2"></span></i>
            <div class="d-flex flex-column pe-0 pe-sm-10"><span class="fs-7 fw-semibold text-gray-800"><?php echo htmlspecialchars($success); ?></span></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-dismissible bg-light-danger d-flex align-items-center p-3 mb-5 border border-danger border-dashed">
            <i class="ki-duotone ki-shield-cross fs-2 text-danger me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <div class="d-flex flex-column pe-0 pe-sm-10"><span class="fs-7 fw-semibold text-gray-800"><?php echo htmlspecialchars($error); ?></span></div>
        </div>
        <?php endif; ?>

        <div class="mb-5">
            <label class="form-label fw-semibold" for="codigo_totp">Código de verificación</label>
            <input type="text" name="codigo_totp" id="codigo_totp"
                class="form-control form-control-solid text-center font-monospace fs-3"
                maxlength="32" inputmode="text" autocomplete="one-time-code"
                placeholder="123456 o XXXX-XXXX" required />
            <div class="form-text">Puedes usar un código de recuperación si perdiste acceso a la aplicación.</div>
        </div>

        <div class="d-grid mb-5">
            <button type="submit" class="btn btn-primary fw-bold py-3 fs-6">
                <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                Continuar
            </button>
        </div>

        <div class="d-flex flex-column align-items-center gap-3 text-center">
            <?php if (!empty($email_2fa)): ?>
            <a href="<?php echo base_url('dos-factores/reenviar'); ?>" class="link-primary fw-bold fs-7">Usar código por correo</a>
            <?php endif; ?>
            <a href="<?php echo base_url('login'); ?>" class="text-muted text-hover-primary fs-7 d-inline-flex align-items-center">
                <i class="ki-duotone ki-arrow-left fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                Volver al inicio de sesión
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>
<!--end::Card 2FA Demo10 Classic-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('kt_sing_in_two_factor_form');

    if (!form) {
        return;
    }

    var btnSubmit = document.getElementById('kt_sing_in_two_factor_submit');
    var inputs = [];
    
    for (var i = 1; i <= 6; i++) {
        var el = document.getElementById('pin_code_' + i);
        if (el) inputs.push(el);
    }

    // Enfocar automáticamente el primer input al cargar
    if (inputs.length > 0) {
        setTimeout(function() {
            inputs[0].focus();
        }, 150);
    }

    // Control de escritura y salto automático a la siguiente casilla
    inputs.forEach(function(input, index) {
        
        // Manejar entrada de dígitos
        input.addEventListener('input', function(e) {
            var rawVal = this.value;
            var digit = rawVal.replace(/\D/g, ''); // Solo números

            if (digit.length > 0) {
                // Tomar el último dígito ingresado
                this.value = digit.charAt(digit.length - 1);
                
                // Saltar automáticamente al siguiente input
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                    inputs[index + 1].select();
                } else if (index === inputs.length - 1) {
                    // Si completó el 6to dígito, enfocar el botón o enviar si todos están llenos
                    var allFilled = inputs.every(function(inp) { return inp.value !== ''; });
                    if (allFilled) {
                        btnSubmit.focus();
                    }
                }
            } else {
                this.value = '';
            }
        });

        // Manejar teclas especiales: Backspace, flechas izquierda/derecha y Enter
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (this.value === '' && index > 0) {
                    e.preventDefault();
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                }
            } else if (e.key === 'ArrowLeft' && index > 0) {
                e.preventDefault();
                inputs[index - 1].focus();
                inputs[index - 1].select();
            } else if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                e.preventDefault();
                inputs[index + 1].focus();
                inputs[index + 1].select();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                form.requestSubmit ? form.requestSubmit() : btnSubmit.click();
            }
        });

        // Soporte completo para pegar código completo (Ctrl + V)
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            var clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;
            var pastedText = clipboardData.getData('text').trim();
            var digits = pastedText.replace(/\D/g, '').substring(0, 6);

            if (digits.length > 0) {
                for (var j = 0; j < digits.length; j++) {
                    if (inputs[j]) {
                        inputs[j].value = digits.charAt(j);
                    }
                }

                if (digits.length === 6) {
                    inputs[5].focus();
                    btnSubmit.focus();
                } else if (digits.length < 6 && inputs[digits.length]) {
                    inputs[digits.length].focus();
                }
            }
        });

        // Seleccionar contenido al hacer clic para facilitar reescritura
        input.addEventListener('click', function() {
            this.select();
        });
    });

    // Validación y envío del formulario
    form.addEventListener('submit', function(e) {
        var isValid = true;
        inputs.forEach(function(inp) {
            if (!inp.value || inp.value.trim() === '') {
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                text: "Please enter all 6 digits of the verification code.",
                icon: "warning",
                buttonsStyling: false,
                confirmButtonText: "Got it",
                customClass: {
                    confirmButton: "btn btn-sm btn-primary"
                }
            });
            return false;
        }

        // Indicador de carga
        btnSubmit.setAttribute('data-kt-indicator', 'on');
        btnSubmit.disabled = true;
    });

    // Temporizador de reenvío
    var btnReenviar = document.getElementById('link_reenviar_codigo');
    var timerSpan = document.getElementById('countdown_timer');
    var secondsSpan = document.getElementById('countdown_seconds');
    
    if (document.querySelector('.alert-success')) {
        var timeLeft = 45;
        if (timerSpan && secondsSpan && btnReenviar) {
            btnReenviar.classList.add('text-muted', 'pe-none');
            timerSpan.classList.remove('d-none');
            secondsSpan.textContent = timeLeft;

            var countdownInterval = setInterval(function() {
                timeLeft--;
                secondsSpan.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    timerSpan.classList.add('d-none');
                    btnReenviar.classList.remove('text-muted', 'pe-none');
                }
            }, 1000);
        }
    }
});
</script>

<?php $this->load->view('auth/_auth_bottom', ['auth_layout' => $auth_layout]); ?>
