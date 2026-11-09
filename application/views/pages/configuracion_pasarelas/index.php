        <!--begin::Alerts-->
        <?php if (!$tabla_disponible): ?>
        <div class="alert alert-dismissible bg-light-warning d-flex flex-column flex-sm-row p-5 mb-7 border border-warning border-dashed">
            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4 mb-5 mb-sm-0">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <div class="d-flex flex-column pe-0 pe-sm-10">
                <h5 class="mb-1 text-gray-900">Migración de base de datos pendiente</h5>
                <span class="text-gray-700">La tabla <code>cab_configuracion_pasarelas</code> aún no ha sido creada. La pasarela permanecerá deshabilitada hasta migrar el esquema y guardar las credenciales desde esta pantalla. Ejecuta <code>database/2026-11-03_create_buy_credits_module.sql</code>.</span>
            </div>
        </div>
        <?php endif; ?>

        <!--begin::Row-->
        <div class="row g-5 g-xl-10">
            <!--begin::BTCPay Server Column-->
            <div class="col-xl-6">
                <div class="card card-flush h-md-100">
                    <!--begin::Card header-->
                    <div class="card-header border-0 pt-6">
                        <div class="card-title d-flex align-items-center">
                            <div class="symbol symbol-45px me-4">
                                <span class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-bitcoin fs-1 text-warning">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="d-flex flex-column">
                                <h2 class="fs-4 fw-bold text-gray-900 mb-0">BTCPay Server</h2>
                                <span class="text-muted fs-7">Pago con criptomonedas (Bitcoin, Lightning, Litecoin)</span>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <span class="badge <?php echo (!empty($btcpay['pas_activo']) ? 'badge-light-success' : 'badge-light-danger'); ?> fw-semibold" id="btcpay_badge_status">
                                <?php echo (!empty($btcpay['pas_activo']) ? 'Habilitada' : 'Deshabilitada'); ?>
                            </span>
                        </div>
                    </div>
                    <!--end::Card header-->

                    <!--begin::Form-->
                    <form id="form_btcpay" class="form">
                        <div class="card-body pt-0">
                            <!--begin::Status Switch-->
                            <div class="d-flex flex-stack mb-6 p-4 rounded bg-light">
                                <div class="d-flex flex-column">
                                    <span class="fs-6 fw-bold text-gray-800">Habilitar pasarela BTCPay</span>
                                    <span class="fs-7 text-muted">Permite a los usuarios pagar órdenes con criptomonedas</span>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="pas_activo" value="1" id="btcpay_switch" <?php echo (!empty($btcpay['pas_activo']) ? 'checked' : ''); ?> />
                                </div>
                            </div>
                            <!--end::Status Switch-->

                            <!--begin::Entorno-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700">Entorno</label>
                                <select name="pas_ambiente" class="form-select form-select-sm form-select-solid">
                                    <option value="production" <?php echo (($btcpay['pas_ambiente'] ?? '') === 'production' ? 'selected' : ''); ?>>Producción (Red principal)</option>
                                    <option value="sandbox" <?php echo (($btcpay['pas_ambiente'] ?? '') === 'sandbox' ? 'selected' : ''); ?>>Red de pruebas / Demo</option>
                                </select>
                            </div>

                            <!--begin::URL del servidor-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700 required">URL del servidor</label>
                                <input type="url" name="pas_url" class="form-control form-control-sm form-control-solid" value="<?php echo htmlspecialchars($btcpay['pas_url'] ?? ''); ?>" placeholder="https://tu-servidor-btcpay.example.com" required />
                                <div class="invalid-feedback"></div>
                            </div>

                            <!--begin::ID de tienda-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700 required">ID de tienda</label>
                                <input type="text" name="pas_store_id" class="form-control form-control-sm form-control-solid" value="<?php echo htmlspecialchars($btcpay['pas_store_id'] ?? ''); ?>" placeholder="ID de tienda de BTCPay Server" required />
                                <div class="invalid-feedback"></div>
                            </div>

                            <!--begin::API Key-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700 required">Clave API / Clave Basic</label>
                                <input type="password" name="pas_api_key" class="form-control form-control-sm form-control-solid" value="" placeholder="<?php echo !empty($btcpay_has_api_key) ? 'Configurada: deja en blanco para conservar la clave actual' : 'Token API o clave Basic en Base64'; ?>" autocomplete="new-password" />
                                <span class="form-text fs-8 text-muted">Los secretos nunca se muestran en esta página. Deja en blanco para conservar la clave guardada.</span>
                                <div class="invalid-feedback"></div>
                                <span class="form-text fs-8 text-muted">Admite clave Basic o clave API con permisos para gestionar facturas.</span>
                            </div>

                            <!--begin::Secreto del webhook-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700 required">Secreto del webhook</label>
                                <input type="password" name="pas_webhook_secret" class="form-control form-control-sm form-control-solid" value="" placeholder="<?php echo !empty($btcpay_has_webhook_secret) ? 'Configurado: deja en blanco para conservar el secreto actual' : 'Clave secreta del webhook'; ?>" autocomplete="new-password" />
                                <span class="form-text fs-8 text-muted">Los secretos nunca se muestran en esta página. Deja en blanco para conservar el secreto guardado.</span>
                                <div class="invalid-feedback"></div>
                                <span class="form-text fs-8 text-muted">Se usa para verificar de forma segura las firmas HMAC-SHA256 del encabezado <code>BTCPay-Sig</code>.</span>
                            </div>

                            <!--begin::Política de velocidad & Expiration-->
                            <div class="row g-4 mb-5">
                                <div class="col-sm-6">
                                    <label class="form-label fs-7 fw-bold text-gray-700">Política de velocidad</label>
                                    <select name="pas_speed_policy" class="form-select form-select-sm form-select-solid">
                                        <option value="HighSpeed" <?php echo (($btcpay['pas_speed_policy'] ?? '') === 'HighSpeed' ? 'selected' : ''); ?>>Alta velocidad (0-conf)</option>
                                        <option value="MediumSpeed" <?php echo (($btcpay['pas_speed_policy'] ?? '') === 'MediumSpeed' ? 'selected' : ''); ?>>Velocidad media (1-conf)</option>
                                        <option value="LowSpeed" <?php echo (($btcpay['pas_speed_policy'] ?? '') === 'LowSpeed' ? 'selected' : ''); ?>>Baja velocidad (2-conf)</option>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fs-7 fw-bold text-gray-700">Expiración (minutos)</label>
                                    <input type="number" name="pas_expiration_minutes" class="form-control form-control-sm form-control-solid" value="<?php echo (int)($btcpay['pas_expiration_minutes'] ?? 60); ?>" min="5" max="1440" />
                                </div>
                            </div>

                            <!--begin::Ayuda de URL del webhook-->
                            <div class="p-4 rounded border border-gray-200 bg-light-primary mb-6">
                                <label class="fs-7 fw-bold text-gray-800 d-block mb-1">URL del webhook</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="webhook_url_input" class="form-control form-control-sm bg-body" value="<?php echo htmlspecialchars($webhook_url); ?>" readonly />
                                    <button class="btn btn-sm btn-primary" type="button" id="btn_copy_webhook">
                                        <i class="ki-duotone ki-copy fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Copiar
                                    </button>
                                </div>
                                <span class="fs-8 text-muted mt-2 d-block">Agrega esta URL en BTCPay Server en <strong>Configuración de tienda &gt; Webhooks</strong>.</span>
                            </div>
                        </div>

                        <!--begin::Card footer-->
                        <div class="card-footer d-flex justify-content-between pt-0 pb-6 border-0">
                            <button type="button" class="btn btn-sm btn-light-info" id="btn_test_btcpay">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-shield-tick fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Probar conexión
                                </span>
                                <span class="indicator-progress">Probando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary" id="btn_save_btcpay">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Guardar configuración de BTCPay
                                </span>
                                <span class="indicator-progress">Guardando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                        <!--end::Card footer-->
                    </form>
                    <!--end::Form-->
                </div>
            </div>
            <!--end::BTCPay Server Column-->

            <!--begin::Stripe Column-->
            <div class="col-xl-6">
                <div class="card card-flush h-md-100">
                    <!--begin::Card header-->
                    <div class="card-header border-0 pt-6">
                        <div class="card-title d-flex align-items-center">
                            <div class="symbol symbol-45px me-4">
                                <span class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-credit-cart fs-1 text-primary">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="d-flex flex-column">
                                <h2 class="fs-4 fw-bold text-gray-900 mb-0">Pago con tarjeta (Stripe)</h2>
                                <span class="text-muted fs-7">Pagos con tarjeta alojados (Visa, Mastercard, Amex, billeteras)</span>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <span class="badge <?php echo (!empty($stripe['pas_activo']) ? 'badge-light-success' : 'badge-light-danger'); ?> fw-semibold" id="stripe_badge_status">
                                <?php echo (!empty($stripe['pas_activo']) ? 'Habilitada' : 'Deshabilitada'); ?>
                            </span>
                        </div>
                    </div>
                    <!--end::Card header-->

                    <!--begin::Form-->
                    <form id="form_stripe" class="form">
                        <div class="card-body pt-0">
                            <!--begin::Status Switch-->
                            <div class="d-flex flex-stack mb-6 p-4 rounded bg-light">
                                <div class="d-flex flex-column">
                                    <span class="fs-6 fw-bold text-gray-800">Habilitar pasarela Stripe</span>
                                    <span class="fs-7 text-muted">Permite a los usuarios pagar órdenes con tarjetas de crédito o débito</span>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="pas_activo" value="1" id="stripe_switch" <?php echo (!empty($stripe['pas_activo']) ? 'checked' : ''); ?> />
                                </div>
                            </div>
                            <!--end::Status Switch-->

                            <!--begin::Entorno-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700">Entorno</label>
                                <select name="pas_ambiente" class="form-select form-select-sm form-select-solid">
                                    <option value="sandbox" <?php echo (($stripe['pas_ambiente'] ?? '') === 'sandbox' ? 'selected' : ''); ?>>Pruebas / Modo de prueba</option>
                                    <option value="production" <?php echo (($stripe['pas_ambiente'] ?? '') === 'production' ? 'selected' : ''); ?>>Producción / Modo real</option>
                                </select>
                                <span class="form-text fs-8 text-muted">El modo de la clave debe coincidir con este entorno: sk_test_ para sandbox y sk_live_ para producción.</span>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!--begin::Clave secreta-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700 required">Clave secreta</label>
                                <input type="password" name="pas_api_key" class="form-control form-control-sm form-control-solid" value="" placeholder="<?php echo !empty($stripe_has_api_key) ? 'Configurada: deja en blanco para conservar la clave actual' : 'sk_test_... o sk_live_...'; ?>" autocomplete="new-password" />
                                <span class="form-text fs-8 text-muted">Los secretos nunca se muestran en esta página. Deja en blanco para conservar la clave guardada.</span>
                                <div class="invalid-feedback"></div>
                            </div>

                            <!--begin::Secreto del webhook-->
                            <div class="mb-5">
                                <label class="form-label fs-7 fw-bold text-gray-700 required">Secreto de firma del webhook</label>
                                <input type="password" name="pas_webhook_secret" class="form-control form-control-sm form-control-solid" value="" placeholder="<?php echo !empty($stripe_has_webhook_secret) ? 'Configurado: deja en blanco para conservar el secreto actual' : 'whsec_...'; ?>" autocomplete="new-password" />
                                <span class="form-text fs-8 text-muted">Los secretos nunca se muestran en esta página. Deja en blanco para conservar el secreto guardado.</span>
                                <div class="invalid-feedback"></div>
                                <span class="form-text fs-8 text-muted">Se usa para verificar el encabezado <code>Stripe-Signature</code> con HMAC-SHA256.</span>
                            </div>

                            <!--begin::Ayuda de URL del webhook-->
                            <div class="p-4 rounded border border-gray-200 bg-light-primary mb-6">
                                <label class="fs-7 fw-bold text-gray-800 d-block mb-1">URL del webhook</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="stripe_webhook_url_input" class="form-control form-control-sm bg-body" value="<?php echo htmlspecialchars($stripe_webhook_url); ?>" readonly />
                                    <button class="btn btn-sm btn-primary" type="button" id="btn_copy_stripe_webhook">
                                        <i class="ki-duotone ki-copy fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Copiar
                                    </button>
                                </div>
                                <span class="fs-8 text-muted mt-2 d-block">Agrega esta URL en Stripe en <strong>Desarrolladores &gt; Webhooks</strong>. Suscríbete a <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code> y <code>checkout.session.expired</code>.</span>
                            </div>
                        </div>

                        <!--begin::Card footer-->
                        <div class="card-footer d-flex justify-content-between pt-0 pb-6 border-0">
                            <button type="button" class="btn btn-sm btn-light-info" id="btn_test_stripe">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-shield-tick fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Probar conexión
                                </span>
                                <span class="indicator-progress">Probando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary" id="btn_save_stripe">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Guardar configuración de Stripe
                                </span>
                                <span class="indicator-progress">Guardando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                        <!--end::Card footer-->
                    </form>
                    <!--end::Form-->
                </div>
            </div>
            <!--end::Stripe Column-->
        </div>
        <!--end::Row-->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formBtcpay = document.getElementById('form_btcpay');
    const btnSaveBtcpay = document.getElementById('btn_save_btcpay');
    const btnTestBtcpay = document.getElementById('btn_test_btcpay');
    const btnCopiarWebhook = document.getElementById('btn_copy_webhook');
    const inputWebhookUrl = document.getElementById('webhook_url_input');
    const badgeStatus = document.getElementById('btcpay_badge_status');
    const switchStatus = document.getElementById('btcpay_switch');

    // Switch label update
    if (switchStatus && badgeStatus) {
        switchStatus.addEventListener('change', function () {
            if (this.checked) {
                badgeStatus.className = 'badge badge-light-success fw-semibold';
                badgeStatus.textContent = 'Habilitada';
            } else {
                badgeStatus.className = 'badge badge-light-danger fw-semibold';
                badgeStatus.textContent = 'Deshabilitada';
            }
        });
    }

    // Copiar Webhook URL
    if (btnCopiarWebhook && inputWebhookUrl) {
        btnCopiarWebhook.addEventListener('click', function () {
            inputWebhookUrl.select();
            inputWebhookUrl.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(inputWebhookUrl.value).then(function () {
                Swal.fire({
                    text: '¡URL del webhook copiada al portapapeles!',
                    icon: 'success',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-primary' }
                });
            });
        });
    }

    // Guardar configuración de BTCPay
    if (formBtcpay) {
        formBtcpay.addEventListener('submit', function (e) {
            e.preventDefault();
            btnSaveBtcpay.setAttribute('data-kt-indicator', 'on');
            btnSaveBtcpay.disabled = true;

            // Clear previous errors
            formBtcpay.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            formBtcpay.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

            const formData = new FormData(formBtcpay);

            fetch('<?php echo base_url($url_base . "/guardar_btcpay"); ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                btnSaveBtcpay.removeAttribute('data-kt-indicator');
                btnSaveBtcpay.disabled = false;

                if (data.ok) {
                    Swal.fire({
                        text: data.msg || 'Configuración de BTCPay Server guardada correctamente.',
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: '¡Listo!',
                        customClass: { confirmButton: 'btn btn-sm btn-primary' }
                    });
                } else if (data.errores) {
                    for (const [campo, err] of Object.entries(data.errores)) {
                        const input = formBtcpay.querySelector(`[name="${campo}"]`);
                        if (input) {
                            input.classList.add('is-invalid');
                            const feedback = input.parentElement.querySelector('.invalid-feedback');
                            if (feedback) feedback.textContent = err;
                        }
                    }
                    Swal.fire({
                        text: 'Revisa los campos resaltados.',
                        icon: 'warning',
                        buttonsStyling: false,
                        confirmButtonText: 'Entendido',
                        customClass: { confirmButton: 'btn btn-sm btn-warning' }
                    });
                } else {
                    Swal.fire({
                        text: data.msg || 'No se pudo guardar la configuración.',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Aceptar',
                        customClass: { confirmButton: 'btn btn-sm btn-danger' }
                    });
                }
            })
            .catch(err => {
                btnSaveBtcpay.removeAttribute('data-kt-indicator');
                btnSaveBtcpay.disabled = false;
                Swal.fire({
                    text: 'Ocurrió un error de conexión al guardar.',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-danger' }
                });
            });
        });
    }

    // Probar conexión
    if (btnTestBtcpay) {
        btnTestBtcpay.addEventListener('click', function () {
            btnTestBtcpay.setAttribute('data-kt-indicator', 'on');
            btnTestBtcpay.disabled = true;

            fetch('<?php echo base_url($url_base . "/test_btcpay"); ?>', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                btnTestBtcpay.removeAttribute('data-kt-indicator');
                btnTestBtcpay.disabled = false;

                Swal.fire({
                    text: data.msg || (data.ok ? '¡Conexión exitosa!' : 'Conexión fallida.'),
                    icon: data.ok ? 'success' : 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-primary' }
                });
            })
            .catch(err => {
                btnTestBtcpay.removeAttribute('data-kt-indicator');
                btnTestBtcpay.disabled = false;
                Swal.fire({
                    text: 'Error de red al intentar conectar con BTCPay Server.',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-danger' }
                });
            });
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formStripe = document.getElementById('form_stripe');
    const btnSaveStripe = document.getElementById('btn_save_stripe');
    const btnTestStripe = document.getElementById('btn_test_stripe');
    const btnCopiarStripeWebhook = document.getElementById('btn_copy_stripe_webhook');
    const inputStripeWebhook = document.getElementById('stripe_webhook_url_input');
    const stripeBadge = document.getElementById('stripe_badge_status');
    const stripeSwitch = document.getElementById('stripe_switch');

    if (stripeSwitch && stripeBadge) {
        stripeSwitch.addEventListener('change', function () {
            if (this.checked) {
                stripeBadge.className = 'badge badge-light-success fw-semibold';
                stripeBadge.textContent = 'Habilitada';
            } else {
                stripeBadge.className = 'badge badge-light-danger fw-semibold';
                stripeBadge.textContent = 'Deshabilitada';
            }
        });
    }

    if (btnCopiarStripeWebhook && inputStripeWebhook) {
        btnCopiarStripeWebhook.addEventListener('click', function () {
            inputStripeWebhook.select();
            inputStripeWebhook.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(inputStripeWebhook.value).then(function () {
                Swal.fire({
                    text: '¡URL del webhook de Stripe copiada al portapapeles!',
                    icon: 'success',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-primary' }
                });
            });
        });
    }

    if (formStripe) {
        formStripe.addEventListener('submit', function (e) {
            e.preventDefault();
            btnSaveStripe.setAttribute('data-kt-indicator', 'on');
            btnSaveStripe.disabled = true;

            formStripe.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            formStripe.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

            const formData = new FormData(formStripe);

            fetch('<?php echo base_url($url_base . "/guardar_stripe"); ?>', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btnSaveStripe.removeAttribute('data-kt-indicator');
                btnSaveStripe.disabled = false;

                if (data.ok) {
                    Swal.fire({
                        text: data.msg || 'Configuración de Stripe guardada correctamente.',
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: '¡Listo!',
                        customClass: { confirmButton: 'btn btn-sm btn-primary' }
                    });
                } else if (data.errores) {
                    for (const [campo, err] of Object.entries(data.errores)) {
                        const input = formStripe.querySelector(`[name="${campo}"]`);
                        if (input) {
                            input.classList.add('is-invalid');
                            const feedback = input.parentElement.querySelector('.invalid-feedback');
                            if (feedback) feedback.textContent = err;
                        }
                    }
                    Swal.fire({
                        text: 'Revisa los campos resaltados.',
                        icon: 'warning',
                        buttonsStyling: false,
                        confirmButtonText: 'Entendido',
                        customClass: { confirmButton: 'btn btn-sm btn-warning' }
                    });
                } else {
                    Swal.fire({
                        text: data.msg || 'No se pudo guardar la configuración de Stripe.',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Aceptar',
                        customClass: { confirmButton: 'btn btn-sm btn-danger' }
                    });
                }
            })
            .catch(() => {
                btnSaveStripe.removeAttribute('data-kt-indicator');
                btnSaveStripe.disabled = false;
                Swal.fire({
                    text: 'Ocurrió un error de conexión al guardar la configuración de Stripe.',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-danger' }
                });
            });
        });
    }

    if (btnTestStripe) {
        btnTestStripe.addEventListener('click', function () {
            btnTestStripe.setAttribute('data-kt-indicator', 'on');
            btnTestStripe.disabled = true;

            fetch('<?php echo base_url($url_base . "/test_stripe"); ?>', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btnTestStripe.removeAttribute('data-kt-indicator');
                btnTestStripe.disabled = false;

                Swal.fire({
                    text: data.msg || (data.ok ? '¡Conexión exitosa!' : 'Conexión fallida.'),
                    icon: data.ok ? 'success' : 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-primary' }
                });
            })
            .catch(() => {
                btnTestStripe.removeAttribute('data-kt-indicator');
                btnTestStripe.disabled = false;
                Swal.fire({
                    text: 'Error de red al intentar conectar con Stripe.',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Aceptar',
                    customClass: { confirmButton: 'btn btn-sm btn-danger' }
                });
            });
        });
    }
});
</script>
