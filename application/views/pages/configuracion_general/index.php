<?php
$settings = $settings ?? [];
$errores = $errores ?? [];
?>

<?php if (empty($tabla_disponible)): ?>
<div class="alert alert-dismissible bg-light-warning d-flex flex-column flex-sm-row p-5 mb-7 border border-warning border-dashed">
    <i class="ki-duotone ki-information-4 fs-2hx text-warning me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
    <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
        <h5 class="mb-1 text-warning fw-bold">Tabla de configuración no encontrada</h5>
        <span class="fs-7 text-gray-700">Debes ejecutar la migración <code>database/2026-10-25_create_cab_configuracion_general.sql</code> en tu base de datos para habilitar este módulo.</span>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="alert alert-dismissible bg-light-success d-flex flex-column flex-sm-row p-5 mb-7 border border-success border-dashed">
    <i class="ki-duotone ki-check-circle fs-2hx text-success me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span></i>
    <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
        <h5 class="mb-1 text-success fw-bold">Ajustes actualizados</h5>
        <span class="fs-7 text-gray-700"><?php echo htmlspecialchars($success); ?></span>
    </div>
    <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
        <i class="ki-duotone ki-cross fs-1 text-success"><span class="path1"></span><span class="path2"></span></i>
    </button>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-dismissible bg-light-danger d-flex flex-column flex-sm-row p-5 mb-7 border border-danger border-dashed">
    <i class="ki-duotone ki-cross-circle fs-2hx text-danger me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span></i>
    <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
        <h5 class="mb-1 text-danger fw-bold">Atención</h5>
        <span class="fs-7 text-gray-700"><?php echo htmlspecialchars($error); ?></span>
    </div>
    <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
        <i class="ki-duotone ki-cross fs-1 text-danger"><span class="path1"></span><span class="path2"></span></i>
    </button>
</div>
<?php endif; ?>

<form method="POST" action="<?php echo base_url($url_base . '/guardar'); ?>" id="kt_config_general_form">

    <!--begin::Card 1: Datos de la Empresa (Inspirado en company-profile.html)-->
    <div class="card card-flush shadow-sm mb-6 mb-xl-9">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-primary me-3">
                        <i class="ki-duotone ki-briefcase fs-2 text-primary"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-4 mb-0">Identidad Corporativa y Contacto</h3>
                        <span class="text-muted fw-semibold fs-7">Razón social, documentos fiscales y canales de atención</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="row g-6">
                <div class="col-md-6">
                    <label class="form-label required fw-semibold fs-6">Nombre Comercial / Empresa</label>
                    <input type="text" name="empresa_nombre" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)($settings['empresa_nombre'] ?? '')); ?>" required />
                    <?php if (!empty($errores['empresa_nombre'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['empresa_nombre']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-6">Identificación Fiscal / RIF / Tax ID</label>
                    <input type="text" name="empresa_documento" class="form-control form-control-solid" maxlength="50" placeholder="Ej: J-12345678-9" value="<?php echo htmlspecialchars((string)($settings['empresa_documento'] ?? '')); ?>" />
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-6">Teléfono de Oficina</label>
                    <div class="input-group input-group-solid">
                        <span class="input-group-text"><i class="ki-duotone ki-phone fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                        <input type="text" name="empresa_telefono" class="form-control form-control-solid" maxlength="50" placeholder="Ej: +58 212 1234567" value="<?php echo htmlspecialchars((string)($settings['empresa_telefono'] ?? '')); ?>" />
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-6">WhatsApp Oficial de Soporte</label>
                    <div class="input-group input-group-solid">
                        <span class="input-group-text"><i class="ki-duotone ki-whatsapp fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                        <input type="text" name="empresa_whatsapp" class="form-control form-control-solid" maxlength="50" placeholder="Ej: +58 414 1234567" value="<?php echo htmlspecialchars((string)($settings['empresa_whatsapp'] ?? '')); ?>" />
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-6">Correo de Contacto</label>
                    <div class="input-group input-group-solid">
                        <span class="input-group-text"><i class="ki-duotone ki-sms fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                        <input type="email" name="empresa_correo" class="form-control form-control-solid" maxlength="150" placeholder="contacto@empresa.com" value="<?php echo htmlspecialchars((string)($settings['empresa_correo'] ?? '')); ?>" />
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold fs-6">Dirección Física</label>
                    <textarea name="empresa_direccion" class="form-control form-control-solid" rows="2" placeholder="Ciudad, Estado, País"><?php echo htmlspecialchars((string)($settings['empresa_direccion'] ?? '')); ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <!--end::Card 1: Datos de la Empresa-->

    <!--begin::Card 2: Monedas y Tasas (Inspirado en billing/plans.html)-->
    <div class="card card-flush shadow-sm mb-6 mb-xl-9">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-success me-3">
                        <i class="ki-duotone ki-dollar fs-2 text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-4 mb-0">Monedas y Tasa de Cambio</h3>
                        <span class="text-muted fw-semibold fs-7">Define la moneda de facturación y la tasa de conversión para pagos</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="row g-6">
                <!-- Moneda Principal -->
                <div class="col-md-6">
                    <div class="border border-gray-200 rounded-3 p-5 bg-light-primary bg-opacity-25 h-100">
                        <h4 class="fw-bold text-gray-900 mb-4 d-flex align-items-center">
                            <span class="badge badge-primary me-2">1</span> Moneda Principal (Base)
                        </h4>
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label required fw-semibold fs-7">Nombre de la Moneda</label>
                                <input type="text" name="moneda_principal_nombre" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($settings['moneda_principal_nombre'] ?? 'Dólar Estadounidense')); ?>" required />
                            </div>
                            <div class="col-6">
                                <label class="form-label required fw-semibold fs-7">Código ISO</label>
                                <input type="text" name="moneda_principal_codigo" class="form-control form-control-solid text-uppercase" maxlength="5" value="<?php echo htmlspecialchars((string)($settings['moneda_principal_codigo'] ?? 'USD')); ?>" required />
                            </div>
                            <div class="col-6">
                                <label class="form-label required fw-semibold fs-7">Símbolo</label>
                                <input type="text" name="moneda_principal_simbolo" class="form-control form-control-solid" maxlength="5" value="<?php echo htmlspecialchars((string)($settings['moneda_principal_simbolo'] ?? '$')); ?>" required />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Moneda Secundaria y Tasa -->
                <div class="col-md-6">
                    <div class="border border-gray-200 rounded-3 p-5 bg-light-success bg-opacity-25 h-100">
                        <h4 class="fw-bold text-gray-900 mb-4 d-flex align-items-center">
                            <span class="badge badge-success me-2">2</span> Moneda Secundaria y Tasa de Conversión
                        </h4>
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label required fw-semibold fs-7">Nombre de la Moneda</label>
                                <input type="text" name="moneda_secundaria_nombre" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($settings['moneda_secundaria_nombre'] ?? 'Bolívares')); ?>" required />
                            </div>
                            <div class="col-6">
                                <label class="form-label required fw-semibold fs-7">Código ISO</label>
                                <input type="text" name="moneda_secundaria_codigo" class="form-control form-control-solid text-uppercase" maxlength="5" value="<?php echo htmlspecialchars((string)($settings['moneda_secundaria_codigo'] ?? 'VES')); ?>" required />
                            </div>
                            <div class="col-6">
                                <label class="form-label required fw-semibold fs-7">Símbolo</label>
                                <input type="text" name="moneda_secundaria_simbolo" class="form-control form-control-solid" maxlength="10" value="<?php echo htmlspecialchars((string)($settings['moneda_secundaria_simbolo'] ?? 'Bs.')); ?>" required />
                            </div>
                            <div class="col-12">
                                <label class="form-label required fw-semibold fs-7">Tasa de Cambio (1 <?php echo htmlspecialchars((string)($settings['moneda_principal_codigo'] ?? 'USD')); ?> = ? <?php echo htmlspecialchars((string)($settings['moneda_secundaria_codigo'] ?? 'VES')); ?>)</label>
                                <div class="input-group input-group-solid">
                                    <span class="input-group-text fw-bold"><?php echo htmlspecialchars((string)($settings['moneda_secundaria_simbolo'] ?? 'Bs.')); ?></span>
                                    <input type="text" name="tasa_cambio_secundaria" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($settings['tasa_cambio_secundaria'] ?? '36.50')); ?>" required />
                                </div>
                                <?php if (!empty($errores['tasa_cambio_secundaria'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['tasa_cambio_secundaria']); ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Card 2: Monedas y Tasas-->

    <!--begin::Card 3: Modo Mantenimiento y Banner Global (Inspirado en security/overview.html)-->
    <div class="card card-flush shadow-sm mb-6 mb-xl-9">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-danger me-3">
                        <i class="ki-duotone ki-shield-tick fs-2 text-danger"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-4 mb-0">Control Operativo y Avisos Globales</h3>
                        <span class="text-muted fw-semibold fs-7">Activa suspensiones de servicio para actualizaciones o publica avisos en todo el portal</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <!-- Switch Modo Mantenimiento -->
            <div class="d-flex flex-stack py-4 border-bottom border-gray-200">
                <div class="d-flex flex-column pe-4">
                    <span class="fs-6 fw-bold text-gray-900">Activar Modo Mantenimiento</span>
                    <span class="fs-7 text-muted">Si está encendido, solo los administradores podrán acceder. Los usuarios verán la pantalla de mantenimiento.</span>
                </div>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="mantenimiento_modo" value="1" id="mantenimiento_modo_switch" <?php echo !empty($settings['mantenimiento_modo']) ? 'checked' : ''; ?> />
                </div>
            </div>

            <div class="row g-4 my-3 <?php echo empty($settings['mantenimiento_modo']) ? 'd-none' : ''; ?>" id="contenedor_mantenimiento_detalles">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Título del Mantenimiento</label>
                    <input type="text" name="mantenimiento_titulo" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($settings['mantenimiento_titulo'] ?? 'Mantenimiento en Curso')); ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7">Mensaje para los Usuarios</label>
                    <input type="text" name="mantenimiento_mensaje" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($settings['mantenimiento_mensaje'] ?? 'Estamos actualizando el sistema. Volveremos en breve.')); ?>" />
                </div>
            </div>

            <!-- Switch Banner Informativo -->
            <div class="d-flex flex-stack py-4 mt-2">
                <div class="d-flex flex-column pe-4">
                    <span class="fs-6 fw-bold text-gray-900">Banner Informativo Global</span>
                    <span class="fs-7 text-muted">Muestra un cintillo de aviso en la parte superior de todas las pantallas del sistema.</span>
                </div>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="banner_activo" value="1" id="banner_activo_switch" <?php echo !empty($settings['banner_activo']) ? 'checked' : ''; ?> />
                </div>
            </div>

            <div class="row g-4 my-3 <?php echo empty($settings['banner_activo']) ? 'd-none' : ''; ?>" id="contenedor_banner_detalles">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-7">Tipo de Alerta</label>
                    <select name="banner_tipo" class="form-select form-select-solid">
                        <option value="info" <?php echo ($settings['banner_tipo'] ?? '') === 'info' ? 'selected' : ''; ?>>Información (Azul)</option>
                        <option value="warning" <?php echo ($settings['banner_tipo'] ?? '') === 'warning' ? 'selected' : ''; ?>>Advertencia (Amarillo)</option>
                        <option value="success" <?php echo ($settings['banner_tipo'] ?? '') === 'success' ? 'selected' : ''; ?>>Promoción / Éxito (Verde)</option>
                        <option value="danger" <?php echo ($settings['banner_tipo'] ?? '') === 'danger' ? 'selected' : ''; ?>>Alerta Crítica (Rojo)</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold fs-7">Texto del Banner</label>
                    <input type="text" name="banner_texto" class="form-control form-control-solid" placeholder="Ej: Recuerda que hoy a las 11:00 PM realizaremos mantenimiento preventivo..." value="<?php echo htmlspecialchars((string)($settings['banner_texto'] ?? '')); ?>" />
                </div>
            </div>

            <!-- Separador -->
            <div class="separator separator-dashed my-5"></div>

            <!-- Inactividad de Sesión -->
            <div class="d-flex flex-stack py-2">
                <div class="d-flex flex-column pe-4">
                    <span class="fs-6 fw-bold text-gray-900">Tiempo de Inactividad de Sesión</span>
                    <span class="fs-7 text-muted">La sesión solo expirará cuando el usuario no realice ninguna acción durante este lapso. Mientras interactúe con el sistema, la sesión permanecerá activa continuamente.</span>
                </div>
                <div class="w-150px">
                    <div class="input-group input-group-solid">
                        <input type="number" min="5" max="1440" step="5" name="sesion_inactividad_minutos" class="form-control form-control-solid text-end" value="<?php echo htmlspecialchars((string)($settings['sesion_inactividad_minutos'] ?? '120')); ?>" required />
                        <span class="input-group-text fs-7">min</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Card 3: Modo Mantenimiento y Banner Global-->

    <!--begin::Card 4: Visor de Errores (Logs)-->
    <div class="card card-flush shadow-sm mb-6 mb-xl-9">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-warning me-3">
                        <i class="ki-duotone ki-shield-cross fs-2 text-warning"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-4 mb-0">Visor de Errores (Logs)</h3>
                        <span class="text-muted fw-semibold fs-7">Controla el acceso al reporte de errores y su ventana de consulta</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <!-- Switch Habilitar Visor -->
            <div class="d-flex flex-stack py-4 border-bottom border-gray-200">
                <div class="d-flex flex-column pe-4">
                    <span class="fs-6 fw-bold text-gray-900">Habilitar visor de errores</span>
                    <span class="fs-7 text-muted">Permite consultar <code>application/logs</code> desde el módulo Reporte de Errores.</span>
                    <?php if (!empty($errores['logs_reporte_activo'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['logs_reporte_activo']); ?></div><?php endif; ?>
                </div>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input type="hidden" name="logs_reporte_activo" value="0" />
                    <input class="form-check-input" type="checkbox" name="logs_reporte_activo" value="1" id="logs_reporte_activo_switch" <?php echo (string)($settings['logs_reporte_activo'] ?? '1') === '1' ? 'checked' : ''; ?> />
                </div>
            </div>

            <div class="row g-4 my-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7" for="logs_reporte_dias">Ventana de consulta</label>
                    <div class="input-group input-group-solid">
                        <input type="number" min="1" max="90" step="1" name="logs_reporte_dias" id="logs_reporte_dias" class="form-control form-control-solid text-end" value="<?php echo htmlspecialchars((string)($settings['logs_reporte_dias'] ?? '7')); ?>" required />
                        <span class="input-group-text fs-7">días</span>
                    </div>
                    <?php if (!empty($errores['logs_reporte_dias'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['logs_reporte_dias']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-7" for="logs_reporte_max_entradas">Máximo de entradas</label>
                    <div class="input-group input-group-solid">
                        <input type="number" min="50" max="5000" step="50" name="logs_reporte_max_entradas" id="logs_reporte_max_entradas" class="form-control form-control-solid text-end" value="<?php echo htmlspecialchars((string)($settings['logs_reporte_max_entradas'] ?? '500')); ?>" required />
                        <span class="input-group-text fs-7">entradas</span>
                    </div>
                    <?php if (!empty($errores['logs_reporte_max_entradas'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['logs_reporte_max_entradas']); ?></div><?php endif; ?>
                </div>
            </div>

            <div class="separator separator-dashed my-5"></div>

            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-3 rounded border border-gray-200 bg-light p-4">
                <div class="d-flex align-items-start">
                    <i class="ki-duotone ki-information-4 fs-2 text-primary me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <span class="fs-7 text-gray-700">El nivel de detalle lo define <code>log_threshold</code> en <code>application/config/config.php</code> (actualmente 1 = solo ERROR). Este visor no modifica ese archivo.</span>
                </div>
                <a href="<?php echo base_url('reporte-errores'); ?>" class="btn btn-sm btn-light-primary fw-semibold px-4 text-nowrap">
                    <i class="ki-duotone ki-eye fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i> Abrir reporte
                </a>
            </div>
        </div>
    </div>
    <!--end::Card 4: Visor de Errores (Logs)-->

    <!--begin::Botones de acción flotantes / footer-->
    <div class="d-flex justify-content-end mb-10">
        <button type="submit" id="kt_config_general_submit" class="btn btn-sm btn-primary fw-semibold px-4 py-2" <?php echo empty($tabla_disponible) ? 'disabled' : ''; ?>>
            <span class="indicator-label">
                <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                Guardar Configuración
            </span>
            <span class="indicator-progress">Guardando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Mantenimiento
    var switchMant = document.getElementById('mantenimiento_modo_switch');
    var contMant = document.getElementById('contenedor_mantenimiento_detalles');
    if (switchMant && contMant) {
        switchMant.addEventListener('change', function() {
            if (this.checked) {
                contMant.classList.remove('d-none');
            } else {
                contMant.classList.add('d-none');
            }
        });
    }

    // Toggle Banner
    var switchBanner = document.getElementById('banner_activo_switch');
    var contBanner = document.getElementById('contenedor_banner_detalles');
    if (switchBanner && contBanner) {
        switchBanner.addEventListener('change', function() {
            if (this.checked) {
                contBanner.classList.remove('d-none');
            } else {
                contBanner.classList.add('d-none');
            }
        });
    }

    // Spinner Submit
    var form = document.getElementById('kt_config_general_form');
    var btn = document.getElementById('kt_config_general_submit');
    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.setAttribute('data-kt-indicator', 'on');
            btn.disabled = true;
        });
    }
});
</script>

