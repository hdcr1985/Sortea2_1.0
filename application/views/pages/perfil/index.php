<?php
$perfil = $perfil ?? [];
$errores = $errores ?? [];
$foto = !empty($perfil['usu_foto_url']) ? $perfil['usu_foto_url'] : '';
$nombre = trim((string)($perfil['usu_nombre'] ?? 'Usuario'));
$inicial = $nombre !== '' ? mb_strtoupper(mb_substr($nombre, 0, 1)) : 'U';
$blank_avatar = base_url('assets/media/svg/avatars/blank.svg');
$avatar_preview = $foto !== '' ? $foto : $blank_avatar;

$fecha_larga = function ($fecha) {
    $timestamp = strtotime((string)$fecha);
    return $timestamp ? date('d-m-Y H:i:s', $timestamp) : '—';
};
?>

<!--begin::Alertas de estado-->
<?php if (!empty($success)): ?>
<div class="alert alert-dismissible bg-light-success d-flex flex-column flex-sm-row p-5 mb-7 border border-success border-dashed">
    <i class="ki-duotone ki-check-circle fs-2hx text-success me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span></i>
    <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
        <h5 class="mb-1 text-success fw-bold">Operación exitosa</h5>
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
<!--end::Alertas de estado-->

<!--begin::Navbar / Hero de Perfil (Inspirado en Metronic Demo 10 user-profile.html)-->
<div class="card mb-6 mb-xl-9">
    <div class="card-body pt-9 pb-0">
        <!--begin::Detalles de cabecera-->
        <div class="d-flex flex-wrap flex-sm-nowrap mb-6">
            <!--begin::Avatar-->
            <div class="d-flex flex-center position-relative me-7 mb-4">
                <div class="symbol symbol-100px symbol-lg-150px symbol-fixed position-relative">
                    <?php if ($foto !== ''): ?>
                        <img src="<?php echo htmlspecialchars($foto); ?>" alt="<?php echo htmlspecialchars($nombre); ?>" class="rounded-3 object-fit-cover" style="width: 130px; height: 130px;" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($blank_avatar, ENT_QUOTES, 'UTF-8'); ?>';" />
                    <?php else: ?>
                        <div class="symbol-label fs-1 fw-bold text-primary bg-light-primary rounded-3" style="width: 130px; height: 130px; font-size: 3rem !important;">
                            <?php echo htmlspecialchars($inicial); ?>
                        </div>
                    <?php endif; ?>
                    <div class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px" title="Usuario activo"></div>
                </div>
            </div>
            <!--end::Avatar-->

            <!--begin::Info usuario-->
            <div class="flex-grow-1">
                <!--begin::Titulo y badges-->
                <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                    <div class="d-flex flex-column">
                        <div class="d-flex align-items-center mb-1">
                            <span class="text-gray-900 fs-2 fw-bold me-2"><?php echo htmlspecialchars($nombre); ?></span>
                            <span class="badge badge-light-primary fw-bold me-auto px-4 py-2"><?php echo htmlspecialchars((string)($perfil['rol_nombre'] ?? 'Sin rol')); ?></span>
                        </div>
                        <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2">
                            <span class="d-flex align-items-center text-gray-500 text-hover-primary me-5 mb-2">
                                <i class="ki-duotone ki-profile-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                @<?php echo htmlspecialchars((string)($perfil['usu_usuario'] ?? '')); ?>
                            </span>
                            <span class="d-flex align-items-center text-gray-500 text-hover-primary me-5 mb-2">
                                <i class="ki-duotone ki-sms fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                <?php echo htmlspecialchars((string)($perfil['usu_correo'] ?? '—')); ?>
                            </span>
                            <?php if (!empty($perfil['usu_pais'])): ?>
                            <span class="d-flex align-items-center text-gray-500 text-hover-primary mb-2 me-5">
                                <i class="ki-duotone ki-geolocation fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                <?php echo htmlspecialchars((string)$perfil['usu_pais']); ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($perfil['usu_celular'])): ?>
                            <span class="d-flex align-items-center text-gray-500 text-hover-primary mb-2">
                                <i class="ki-duotone ki-phone fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                <?php echo htmlspecialchars((string)$perfil['usu_celular']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!--end::Titulo y badges-->

                <!--begin::Estadisticas rápidas-->
                <div class="d-flex flex-wrap flex-stack">
                    <div class="d-flex flex-column flex-grow-1 pe-8">
                        <div class="d-flex flex-wrap">
                            <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-calendar fs-3 text-primary me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <div class="fs-7 text-gray-800 fw-bold"><?php echo htmlspecialchars(!empty($perfil['usu_created_at']) ? date('d-m-Y', strtotime($perfil['usu_created_at'])) : '—'); ?></div>
                                </div>
                                <div class="fw-semibold fs-8 text-gray-500">Miembro desde</div>
                            </div>

                            <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-time fs-3 text-success me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <div class="fs-7 text-gray-800 fw-bold"><?php echo htmlspecialchars(!empty($perfil['usu_updated_at']) ? date('d-m-Y', strtotime($perfil['usu_updated_at'])) : '—'); ?></div>
                                </div>
                                <div class="fw-semibold fs-8 text-gray-500">Última actualización</div>
                            </div>

                            <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-shield-tick fs-3 text-info me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <div class="fs-7 text-gray-800 fw-bold"><?php echo (string)($perfil['usu_status'] ?? '') === '1' ? 'Activo' : 'Inactivo'; ?></div>
                                </div>
                                <div class="fw-semibold fs-8 text-gray-500">Estado de cuenta</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Estadisticas rápidas-->
            </div>
            <!--end::Info usuario-->
        </div>
        <!--end::Detalles de cabecera-->

        <!--begin::Nav Tabs (Demo 10 navigation)-->
        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold" role="tablist">
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary ms-0 me-10 py-5 active" data-bs-toggle="tab" href="#kt_perfil_tab_info" role="tab">
                    <i class="ki-duotone ki-user fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Información Personal
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 py-5" data-bs-toggle="tab" href="#kt_perfil_tab_seguridad" role="tab">
                    <i class="ki-duotone ki-key fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Seguridad y Clave
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10 py-5" data-bs-toggle="tab" href="#kt_perfil_tab_sesiones" role="tab">
                    <i class="ki-duotone ki-devices fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    Sesiones y Actividad
                </a>
            </li>
        </ul>
        <!--end::Nav Tabs-->
    </div>
</div>
<!--end::Navbar / Hero de Perfil-->

<!--begin::Tab Content-->
<div class="tab-content" id="kt_perfil_tabs_content">

    <!-- ========================================== -->
    <!-- TAB 1: INFORMACIÓN PERSONAL                -->
    <!-- ========================================== -->
    <div class="tab-pane fade show active" id="kt_perfil_tab_info" role="tabpanel">
        <div class="card card-flush shadow-sm">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-3">Datos del Perfil</h3>
                        <span class="text-muted fw-semibold fs-7">Actualiza tu foto, nombre visible y canales de contacto.</span>
                    </div>
                </div>
            </div>

            <div class="card-body pt-0">
                <form id="kt_perfil_info_form" method="post" action="<?php echo base_url('perfil/guardar'); ?>" enctype="multipart/form-data">
                    
                    <!--begin::Foto de perfil con KTImageInput Metronic-->
                    <div class="row mb-8">
                        <label class="col-lg-3 col-form-label fw-semibold fs-6">Foto de Perfil</label>
                        <div class="col-lg-9">
                            <div class="image-input image-input-outline <?php echo $foto === '' ? 'image-input-empty' : ''; ?>" data-kt-image-input="true" style="background-image: url('<?php echo $blank_avatar; ?>')">
                                <div class="image-input-wrapper w-125px h-125px" style="background-image: url('<?php echo htmlspecialchars($avatar_preview); ?>')"></div>

                                <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="change"
                                    data-bs-toggle="tooltip"
                                    data-bs-dismiss="click"
                                    title="Cambiar avatar">
                                    <i class="ki-duotone ki-pencil fs-6"><span class="path1"></span><span class="path2"></span></i>
                                    <input type="file" name="usu_foto_archivo" accept=".png, .jpg, .jpeg, .webp" />
                                    <input type="hidden" name="avatar_remove" />
                                </label>

                                <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="cancel"
                                    data-bs-toggle="tooltip"
                                    data-bs-dismiss="click"
                                    title="Cancelar avatar">
                                    <i class="ki-duotone ki-cross fs-3"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div class="form-text text-muted fs-7">Formatos permitidos: JPG, PNG o WEBP. Tamaño máximo: 3 MB.</div>
                            <?php if (!empty($errores['usu_foto'])): ?>
                            <div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['usu_foto']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!--end::Foto de perfil-->

                    <div class="separator separator-dashed my-6"></div>

                    <!--begin::Campos del formulario-->
                    <div class="row g-6">
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-6">Nombre Completo</label>
                            <input type="text" name="usu_nombre" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)($perfil['usu_nombre'] ?? '')); ?>" required />
                            <?php if (!empty($errores['usu_nombre'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['usu_nombre']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-6">Nombre de Usuario</label>
                            <div class="input-group input-group-solid">
                                <span class="input-group-text"><i class="ki-duotone ki-profile-circle fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></span>
                                <input type="text" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($perfil['usu_usuario'] ?? '')); ?>" disabled />
                            </div>
                            <div class="form-text text-muted fs-8">El nombre de usuario es único y no puede ser alterado.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-6">Correo Electrónico</label>
                            <div class="input-group input-group-solid">
                                <span class="input-group-text"><i class="ki-duotone ki-sms fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                                <input type="email" name="usu_correo" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)($perfil['usu_correo'] ?? '')); ?>" required />
                            </div>
                            <?php if (!empty($errores['usu_correo'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['usu_correo']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-6">Rol de Usuario</label>
                            <input type="text" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($perfil['rol_nombre'] ?? 'Sin rol')); ?>" disabled />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-6">Teléfono Celular</label>
                            <div class="input-group input-group-solid">
                                <span class="input-group-text"><i class="ki-duotone ki-phone fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                                <input type="text" name="usu_celular" class="form-control form-control-solid" maxlength="11" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 04141234567" value="<?php echo htmlspecialchars((string)($perfil['usu_celular'] ?? '')); ?>" required />
                            </div>
                            <?php if (!empty($errores['usu_celular'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['usu_celular']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-6">Fecha de Nacimiento</label>
                            <div class="input-group input-group-solid">
                                <span class="input-group-text"><i class="ki-duotone ki-calendar fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                                <input type="text" name="usu_fechan" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($perfil['usu_fechan'] ?? '')); ?>" autocomplete="off" placeholder="YYYY-MM-DD" data-app-date-picker="true" data-max-date="<?php echo date('Y-m-d'); ?>" />
                            </div>
                            <?php if (!empty($errores['usu_fechan'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['usu_fechan']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-6">Género</label>
                            <select name="usu_genero" class="form-select form-select-solid" data-control="select2" data-hide-search="true">
                                <option value="">— Selecciona —</option>
                                <?php foreach (['Masculino', 'Femenino', 'Otro', 'Prefiero no indicar'] as $genero): ?>
                                <option value="<?php echo htmlspecialchars($genero); ?>" <?php echo ((string)($perfil['usu_genero'] ?? '') === $genero) ? 'selected' : ''; ?>><?php echo htmlspecialchars($genero); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errores['usu_genero'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['usu_genero']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-6">País de Conexión</label>
                            <input type="text" class="form-control form-control-solid" value="<?php echo htmlspecialchars((string)($perfil['usu_pais'] ?? '—')); ?>" readonly />
                        </div>
                    </div>
                    <!--end::Campos del formulario-->

                    <div class="d-flex justify-content-end mt-8">
                        <button type="submit" id="kt_perfil_info_submit" class="btn btn-sm btn-primary fw-semibold px-4 py-2">
                            <span class="indicator-label">
                                <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Guardar Cambios
                            </span>
                            <span class="indicator-progress">Guardando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: SEGURIDAD Y CONTRASEÑA              -->
    <!-- ========================================== -->
    <div class="tab-pane fade" id="kt_perfil_tab_seguridad" role="tabpanel">
        
        <!--begin::Card Estado de Autenticacion (Inspirado en Metronic security/overview.html)-->
        <div class="card card-flush shadow-sm mb-6 mb-xl-9">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-3">Métodos de Acceso</h3>
                        <span class="text-muted fw-semibold fs-7">Control de seguridad para ingresar al sistema.</span>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex flex-stack py-4 border-bottom border-gray-200">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-45px symbol-circle bg-light-primary me-4">
                            <i class="ki-duotone ki-key fs-2 text-primary"><span class="path1"></span><span class="path2"></span></i>
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fs-6 fw-bold text-gray-800">Contraseña de acceso</span>
                            <span class="fs-7 text-gray-500">Última actualización: <?php echo htmlspecialchars($fecha_larga($perfil['usu_updated_at'] ?? '')); ?></span>
                        </div>
                    </div>
                    <span class="badge badge-light-success fw-bold px-4 py-2">Habilitada</span>
                </div>

                <div class="d-flex flex-stack py-4">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-45px symbol-circle <?php echo ((int)($perfil['usu_2fa'] ?? 0) === 1) ? 'bg-light-success' : 'bg-light-warning'; ?> me-4">
                            <i class="ki-duotone ki-shield-tick fs-2 <?php echo ((int)($perfil['usu_2fa'] ?? 0) === 1) ? 'text-success' : 'text-warning'; ?>"><span class="path1"></span><span class="path2"></span></i>
                        </div>
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-6 fw-bold text-gray-800">Autenticación de dos pasos (2FA)</span>
                                <?php if ((int)($perfil['usu_2fa'] ?? 0) === 1): ?>
                                    <span class="badge badge-light-success fw-bold px-3 py-1 fs-8">Habilitada</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary fw-semibold px-3 py-1 fs-8">Deshabilitada</span>
                                <?php endif; ?>
                            </div>
                            <span class="fs-7 text-gray-500">
                                <?php if ((int)($perfil['usu_2fa'] ?? 0) === 1): ?>
                                    Protección activa. Se enviará un código de verificación de 6 dígitos a <strong class="text-gray-700"><?php echo htmlspecialchars($perfil['usu_correo'] ?? ''); ?></strong> en cada inicio de sesión.
                                <?php else: ?>
                                    Añade una capa extra de seguridad solicitando un código temporal de 6 dígitos en tu correo al iniciar sesión.
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <?php if ((int)($perfil['usu_2fa'] ?? 0) === 1): ?>
                            <button type="button" class="btn btn-sm btn-light-danger fw-semibold d-flex align-items-center btn-accion-2fa" data-nuevo-estado="0">
                                <i class="ki-duotone ki-cross-circle fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Desactivar 2FA
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-light-primary fw-semibold d-flex align-items-center btn-accion-2fa" data-nuevo-estado="1">
                                <i class="ki-duotone ki-shield-tick fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Activar 2FA
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-stack py-4 border-top border-gray-200">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-45px symbol-circle <?php echo !empty($totp_enabled) ? 'bg-light-success' : 'bg-light-primary'; ?> me-4">
                            <i class="ki-duotone ki-technology-2 fs-2 <?php echo !empty($totp_enabled) ? 'text-success' : 'text-primary'; ?>"><span class="path1"></span><span class="path2"></span></i>
                        </div>
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-6 fw-bold text-gray-800">Aplicación autenticadora</span>
                                <?php if (!empty($totp_enabled)): ?>
                                    <span class="badge badge-light-success fw-bold px-3 py-1 fs-8">Habilitada</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary fw-semibold px-3 py-1 fs-8">Deshabilitada</span>
                                <?php endif; ?>
                            </div>
                            <span class="fs-7 text-gray-500">
                                <?php if (!empty($totp_enabled)): ?>
                                    Usa Google Authenticator, Microsoft Authenticator, Authy, 1Password u otra aplicación TOTP. Códigos de recuperación disponibles: <strong class="text-gray-700"><?php echo (int)($totp_recovery_count ?? 0); ?></strong>.
                                <?php else: ?>
                                    Agrega un segundo factor con códigos temporales de 6 dígitos desde tu aplicación autenticadora.
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <?php if (!empty($totp_enabled)): ?>
                            <button type="button" class="btn btn-sm btn-light-danger fw-semibold d-flex align-items-center" id="btn_disable_totp">
                                <i class="ki-duotone ki-cross-circle fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Deshabilitar app
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-light-primary fw-semibold d-flex align-items-center" id="btn_setup_totp">
                                <i class="ki-duotone ki-shield-tick fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Configurar app
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card Estado de Autenticacion-->

        <!--begin::Card Cambio de Clave-->
        <div class="card card-flush shadow-sm">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-3">Actualizar Contraseña</h3>
                        <span class="text-muted fw-semibold fs-7">Usa una contraseña de al menos 8 caracteres combinando números y letras.</span>
                    </div>
                </div>
            </div>

            <div class="card-body pt-0">
                <form id="kt_perfil_clave_form" method="post" action="<?php echo base_url('perfil/cambiar_clave'); ?>">
                    <div class="row g-6">
                        
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-6">Contraseña Actual</label>
                            <div class="position-relative">
                                <input type="password" name="clave_actual" id="input_clave_actual" class="form-control form-control-solid" placeholder="Ingresa tu clave actual" required />
                                <button type="button" class="btn btn-sm btn-icon position-absolute end-0 top-50 translate-middle-y me-2 text-gray-500" id="btn_toggle_actual">
                                    <i class="ki-duotone ki-eye fs-4" id="icon_actual_show"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <i class="ki-duotone ki-eye-slash fs-4 d-none" id="icon_actual_hide"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </button>
                            </div>
                            <?php if (!empty($errores['clave_actual'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['clave_actual']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-6">Nueva Contraseña</label>
                            <div class="position-relative">
                                <input type="password" name="nueva_clave" id="input_nueva_clave" class="form-control form-control-solid" placeholder="Mínimo 8 caracteres" minlength="8" required />
                                <button type="button" class="btn btn-sm btn-icon position-absolute end-0 top-50 translate-middle-y me-2 text-gray-500" id="btn_toggle_nueva">
                                    <i class="ki-duotone ki-eye fs-4" id="icon_nueva_show"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <i class="ki-duotone ki-eye-slash fs-4 d-none" id="icon_nueva_hide"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </button>
                            </div>
                            <?php if (!empty($errores['nueva_clave'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['nueva_clave']); ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-6">Confirmar Nueva Contraseña</label>
                            <div class="position-relative">
                                <input type="password" name="nueva_clave_confirm" id="input_nueva_confirm" class="form-control form-control-solid" placeholder="Repite la contraseña" minlength="8" required />
                                <button type="button" class="btn btn-sm btn-icon position-absolute end-0 top-50 translate-middle-y me-2 text-gray-500" id="btn_toggle_confirm">
                                    <i class="ki-duotone ki-eye fs-4" id="icon_confirm_show"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <i class="ki-duotone ki-eye-slash fs-4 d-none" id="icon_confirm_hide"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </button>
                            </div>
                            <?php if (!empty($errores['nueva_clave_confirm'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['nueva_clave_confirm']); ?></div><?php endif; ?>
                        </div>

                    </div>

                    <div class="d-flex justify-content-end mt-8">
                        <button type="submit" id="kt_perfil_clave_submit" class="btn btn-sm btn-primary fw-semibold px-4 py-2">
                            <span class="indicator-label">
                                <i class="ki-duotone ki-key fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Actualizar Contraseña
                            </span>
                            <span class="indicator-progress">Actualizando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card Cambio de Clave-->

    </div>

    <!-- ========================================== -->
    <!-- TAB 3: SESIONES Y ACTIVIDAD                -->
    <!-- ========================================== -->
    <div class="tab-pane fade" id="kt_perfil_tab_sesiones" role="tabpanel">

        <!--begin::Tabla de Sesiones Recientes (Estilo Metronic current-sessions.html)-->
        <div class="card card-flush shadow-sm mb-6 mb-xl-9">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-3">Inicios de Sesión Recientes</h3>
                        <span class="text-muted fw-semibold fs-7">Dispositivos y ubicaciones IP desde los cuales has accedido al sistema.</span>
                    </div>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary fw-bold px-3 py-2"><?php echo count($sesiones_login ?? []); ?> registros</span>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                <th class="min-w-225px">Ubicación</th>
                                <th class="min-w-100px text-center">Estado</th>
                                <th class="min-w-180px">Dispositivo y Navegador</th>
                                <th class="min-w-140px">Dirección IP</th>
                                <th class="min-w-140px text-end pe-2">Fecha y Hora</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 fw-semibold">
                            <?php if (!empty($sesiones_login)): ?>
                                <?php foreach ($sesiones_login as $sesion): ?>
                                <?php
                                    $browser_lower = strtolower((string)$sesion['device']);
                                    $browser_icon = 'ki-chrome';
                                    $browser_color = 'primary';
                                    if (strpos($browser_lower, 'firefox') !== FALSE) {
                                        $browser_icon = 'ki-firefox';
                                        $browser_color = 'warning';
                                    } elseif (strpos($browser_lower, 'safari') !== FALSE || strpos($browser_lower, 'apple') !== FALSE) {
                                        $browser_icon = 'ki-apple';
                                        $browser_color = 'dark';
                                    } elseif (strpos($browser_lower, 'edge') !== FALSE) {
                                        $browser_icon = 'ki-edge';
                                        $browser_color = 'info';
                                    } elseif (strpos($browser_lower, 'android') !== FALSE) {
                                        $browser_icon = 'ki-android';
                                        $browser_color = 'success';
                                    }
                                ?>
                                <tr class="hover-elevate-up">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40px symbol-circle bg-light me-3">
                                                <span class="symbol-label bg-light-primary">
                                                    <i class="ki-duotone ki-geolocation fs-3 text-primary"><span class="path1"></span><span class="path2"></span></i>
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-900 fw-bold fs-6"><?php echo htmlspecialchars((string)$sesion['location']); ?></span>
                                                <?php if (!empty($sesion['network'])): ?>
                                                    <span class="text-muted fs-8"><?php echo htmlspecialchars((string)$sesion['network']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-<?php echo htmlspecialchars((string)$sesion['status']['class']); ?> fw-bold px-3 py-2">
                                            <span class="bullet bullet-dot bg-<?php echo htmlspecialchars((string)$sesion['status']['class']); ?> me-1"></span>
                                            <?php echo htmlspecialchars((string)$sesion['status']['label']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone <?php echo $browser_icon; ?> fs-2 text-<?php echo $browser_color; ?> me-2"><span class="path1"></span><span class="path2"></span></i>
                                            <span class="text-gray-800 fw-medium fs-7"><?php echo htmlspecialchars((string)$sesion['device']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <code class="text-gray-800 bg-light-secondary px-2 py-1 rounded fw-bold fs-7"><?php echo htmlspecialchars((string)($sesion['bit_ip'] ?: '—')); ?></code>
                                        </div>
                                    </td>
                                    <td class="text-end pe-2" title="<?php echo htmlspecialchars($fecha_larga((string)($sesion['bit_date'] ?: '')), ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="text-gray-900 fw-semibold fs-7"><?php echo htmlspecialchars((string)$sesion['time_human']); ?></div>
                                        <div class="text-muted fs-8"><?php echo htmlspecialchars(!empty($sesion['bit_date']) ? date('d-m-Y H:i', strtotime($sesion['bit_date'])) : ''); ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10 text-muted">
                                        <i class="ki-duotone ki-information-2 fs-2x text-muted mb-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        <div class="fs-6">No se registran sesiones recientes.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Tabla de Sesiones Recientes-->

        <!--begin::Tabla de Bitacora / Security Log (Estilo Metronic security-log.html)-->
        <?php if (!empty($puede_ver_bitacora)): ?>
        <div class="card card-flush shadow-sm">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex flex-column">
                        <h3 class="card-label fw-bold text-gray-900 fs-3">Registro de Seguridad y Auditoría</h3>
                        <span class="text-muted fw-semibold fs-7">Trazabilidad completa de eventos ejecutados por tu cuenta.</span>
                    </div>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary fw-semibold px-3 py-2">Solo Administrador</span>
                </div>
            </div>

            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                <th class="min-w-160px">Evento</th>
                                <th class="min-w-260px">Acción y Detalle</th>
                                <th class="min-w-140px">IP Origen</th>
                                <th class="min-w-180px">Navegador</th>
                                <th class="min-w-140px text-end pe-2">Fecha y Hora</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 fw-semibold">
                            <?php if (!empty($actividad)): ?>
                                <?php foreach ($actividad as $evento): ?>
                                    <?php
                                        $accion = strtoupper(trim((string)($evento['bit_accion'] ?? '')));
                                        $badgeClass = 'primary';
                                        $icon = 'ki-information-2';

                                        if (strpos($accion, 'LOGIN_FALLIDO') !== FALSE || strpos($accion, 'ELIMINAR') !== FALSE || strpos($accion, 'ERROR') !== FALSE) {
                                            $badgeClass = 'danger';
                                            $icon = 'ki-shield-cross';
                                        } elseif (strpos($accion, 'LOGIN') !== FALSE || strpos($accion, 'CREAR') !== FALSE || strpos($accion, 'ACTUALIZAR') !== FALSE || strpos($accion, 'PERFIL') !== FALSE) {
                                            $badgeClass = 'success';
                                            $icon = 'ki-check-circle';
                                        } elseif (strpos($accion, 'LOGOUT') !== FALSE || strpos($accion, 'RECUPERAR') !== FALSE) {
                                            $badgeClass = 'warning';
                                            $icon = 'ki-time';
                                        }
                                    ?>
                                    <tr class="hover-elevate-up">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-35px symbol-circle bg-light-<?php echo $badgeClass; ?> me-3">
                                                    <span class="symbol-label bg-light-<?php echo $badgeClass; ?>">
                                                        <i class="ki-duotone <?php echo $icon; ?> fs-4 text-<?php echo $badgeClass; ?>"><span class="path1"></span><span class="path2"></span></i>
                                                    </span>
                                                </div>
                                                <span class="badge badge-light-<?php echo $badgeClass; ?> fw-bold fs-7 px-3 py-2">
                                                    <?php echo htmlspecialchars((string)$evento['bit_accion']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-gray-900 fw-bold fs-7 mb-0"><?php echo htmlspecialchars((string)($evento['bit_detalles'] ?: 'Sin detalle registrado.')); ?></div>
                                        </td>
                                        <td>
                                            <code class="text-gray-800 bg-light-secondary px-2 py-1 rounded fw-bold fs-8"><?php echo htmlspecialchars((string)($evento['bit_ip'] ?: '—')); ?></code>
                                        </td>
                                        <td>
                                            <span class="text-muted fs-7 text-truncate d-inline-block mw-200px" title="<?php echo htmlspecialchars((string)($evento['bit_navegador'] ?: '—')); ?>">
                                                <i class="ki-duotone ki-chrome fs-6 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                <?php echo htmlspecialchars((string)($evento['bit_navegador'] ?: '—')); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-2">
                                            <div class="text-gray-900 fw-semibold fs-7"><?php echo htmlspecialchars(!empty($evento['bit_date']) ? date('d-m-Y H:i:s', strtotime($evento['bit_date'])) : '—'); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10 text-muted">
                                        <i class="ki-duotone ki-time text-muted fs-2x mb-2"><span class="path1"></span><span class="path2"></span></i>
                                        <div class="fs-6">No se encontraron eventos en la bitácora.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Tabla de Bitacora / Security Log-->

    </div>

</div>
<!--end::Tab Content-->

<!--begin::TOTP Setup Modal-->
<div class="modal fade" id="totp_setup_modal" tabindex="-1" aria-labelledby="totp_setup_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex flex-column">
                    <h2 class="modal-title fw-bold text-gray-900" id="totp_setup_modal_title">Configurar aplicación autenticadora</h2>
                    <span class="text-muted fs-8">Agrega la clave manualmente o abre el enlace en tu aplicación.</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-light-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-2x"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-5">
                    <div id="totp_qr" class="d-inline-flex align-items-center justify-content-center bg-white border border-gray-200 rounded p-3"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-semibold">Cuenta</label>
                    <div class="form-control form-control-solid bg-light" id="totp_account">-</div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-semibold">Clave secreta</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-solid font-monospace" id="totp_secret" readonly />
                        <button type="button" class="btn btn-light-primary" id="btn_copy_totp_secret">Copiar</button>
                    </div>
                    <div class="form-text">Ingresa esta clave en tu aplicación si no puedes abrir el enlace.</div>
                </div>
                <div class="mb-6">
                    <a href="#" class="btn btn-sm btn-light-primary fw-semibold d-inline-flex align-items-center" id="totp_open_link">
                        <i class="ki-duotone ki-arrow-up-right fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Abrir en aplicación autenticadora
                    </a>
                </div>
                <div class="separator separator-dashed my-6"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold required" for="totp_code">Código de 6 dígitos</label>
                    <input type="text" class="form-control form-control-solid text-center font-monospace fs-4" id="totp_code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" />
                    <div class="invalid-feedback" id="totp_code_error"></div>
                </div>
                <div id="totp_recovery_wrap" class="d-none">
                    <div class="notice bg-light-success border border-success border-dashed rounded p-4 mb-4">
                        <div class="fw-bold text-gray-900 mb-2">Guarda estos códigos de recuperación</div>
                        <div class="text-muted fs-8 mb-3">Cada código se puede usar una vez si pierdes acceso a tu aplicación.</div>
                        <div class="row row-cols-2 g-2" id="totp_recovery_codes"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light fw-semibold" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btn_totp_confirm">
                    <span class="indicator-label">Confirmar y habilitar</span>
                    <span class="indicator-progress">Habilitando... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::TOTP Setup Modal-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper de visibilidad de contraseñas
    function setupToggle(btnId, inputId, showIconId, hideIconId) {
        var btn = document.getElementById(btnId);
        var input = document.getElementById(inputId);
        var iconShow = document.getElementById(showIconId);
        var iconHide = document.getElementById(hideIconId);

        if (btn && input && iconShow && iconHide) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (input.type === 'password') {
                    input.type = 'text';
                    iconShow.classList.add('d-none');
                    iconHide.classList.remove('d-none');
                } else {
                    input.type = 'password';
                    iconShow.classList.remove('d-none');
                    iconHide.classList.add('d-none');
                }
            });
        }
    }

    setupToggle('btn_toggle_actual', 'input_clave_actual', 'icon_actual_show', 'icon_actual_hide');
    setupToggle('btn_toggle_nueva', 'input_nueva_clave', 'icon_nueva_show', 'icon_nueva_hide');
    setupToggle('btn_toggle_confirm', 'input_nueva_confirm', 'icon_confirm_show', 'icon_confirm_hide');

    // Submit spinner en formulario de perfil
    var formInfo = document.getElementById('kt_perfil_info_form');
    var btnInfo = document.getElementById('kt_perfil_info_submit');
    if (formInfo && btnInfo) {
        formInfo.addEventListener('submit', function() {
            btnInfo.setAttribute('data-kt-indicator', 'on');
            btnInfo.disabled = true;
        });
    }

    // Submit spinner en formulario de contraseña
    var formClave = document.getElementById('kt_perfil_clave_form');
    var btnClave = document.getElementById('kt_perfil_clave_submit');
    if (formClave && btnClave) {
        formClave.addEventListener('submit', function() {
            btnClave.setAttribute('data-kt-indicator', 'on');
            btnClave.disabled = true;
        });
    }

    // Manejo interactivo de activación / desactivación de 2FA
    var btn2fa = document.querySelector('.btn-accion-2fa');
    if (btn2fa) {
        btn2fa.addEventListener('click', function(e) {
            e.preventDefault();
            var nuevoEstado = parseInt(this.getAttribute('data-nuevo-estado'), 10);
            var esActivar = (nuevoEstado === 1);

            Swal.fire({
                title: esActivar ? '¿Activar autenticación 2FA?' : '¿Desactivar autenticación 2FA?',
                text: esActivar
                    ? 'Al activar 2FA, cada vez que inicies sesión se te enviará un código numérico a tu correo electrónico registrado.'
                    : 'Tu cuenta ya no solicitará un código de verificación por correo al iniciar sesión.',
                icon: esActivar ? 'info' : 'warning',
                showCancelButton: true,
                confirmButtonText: esActivar ? 'Sí, activar 2FA' : 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: esActivar ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-danger',
                    cancelButton: 'btn btn-sm btn-light'
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    btn2fa.disabled = true;
                    var formData = new FormData();
                    formData.append('estado', nuevoEstado);

                    fetch('<?php echo base_url("perfil/toggle_2fa"); ?>', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        btn2fa.disabled = false;
                        if (res.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: esActivar ? '¡2FA Activado!' : '2FA Desactivado',
                                text: res.msg,
                                confirmButtonText: 'Aceptar',
                                customClass: {
                                    confirmButton: 'btn btn-sm btn-primary'
                                }
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: res.msg || 'No se pudo actualizar el estado de 2FA.',
                                confirmButtonText: 'Entendido',
                                customClass: {
                                    confirmButton: 'btn btn-sm btn-primary'
                                }
                            });
                        }
                    })
                    .catch(function() {
                        btn2fa.disabled = false;
                        Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                    });
                }
            });
        });
    }
});
</script>

<script src="<?php echo base_url('assets/plugins/custom/qrcode/qrcode.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalElement = document.getElementById('totp_setup_modal');
    var modal = modalElement && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    var setupButton = document.getElementById('btn_setup_totp');
    var disableButton = document.getElementById('btn_disable_totp');
    var confirmButton = document.getElementById('btn_totp_confirm');
    var secretInput = document.getElementById('totp_secret');
    var accountField = document.getElementById('totp_account');
    var codeInput = document.getElementById('totp_code');
    var codeError = document.getElementById('totp_code_error');
    var link = document.getElementById('totp_open_link');
    var recoveryWrap = document.getElementById('totp_recovery_wrap');
    var recoveryCodes = document.getElementById('totp_recovery_codes');
    var qrContainer = document.getElementById('totp_qr');
    var currentSecret = '';
    var completed = false;

    function jsonRequest(url, fields) {
        var fd = new FormData();
        Object.keys(fields || {}).forEach(function(key) { fd.append(key, fields[key]); });
        return fetch(url, {
            method: 'POST',
            body: fd,
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
        }).then(function(response) {
            return response.json().then(function(data) {
                if (!response.ok || !data || data.ok !== true) {
                    throw new Error((data && data.msg) || 'No se pudo completar la solicitud.');
                }
                return data;
            });
        });
    }

    function setBusy(button, busy) {
        if (!button) return;
        button.disabled = busy;
        var label = button.querySelector('.indicator-label');
        var progress = button.querySelector('.indicator-progress');
        if (label) label.classList.toggle('d-none', busy);
        if (progress) progress.classList.toggle('d-none', !busy);
    }

    function resetModal() {
        completed = false;
        currentSecret = '';
        if (secretInput) secretInput.value = '';
        if (accountField) accountField.textContent = '-';
        if (codeInput) { codeInput.value = ''; codeInput.classList.remove('is-invalid'); codeInput.disabled = false; }
        if (codeError) codeError.textContent = '';
        if (recoveryWrap) recoveryWrap.classList.add('d-none');
        if (recoveryCodes) recoveryCodes.innerHTML = '';
        if (qrContainer) qrContainer.innerHTML = '';
        if (confirmButton) {
            confirmButton.querySelector('.indicator-label').textContent = 'Confirmar y habilitar';
            confirmButton.classList.remove('d-none');
        }
        if (link) link.href = '#';
    }

    if (setupButton) {
        setupButton.addEventListener('click', function() {
            resetModal();
            setBusy(setupButton, true);
            jsonRequest('<?php echo base_url("perfil/totp_iniciar"); ?>', {}).then(function(data) {
                currentSecret = data.secret || '';
                if (secretInput) secretInput.value = currentSecret;
                if (accountField) accountField.textContent = data.account || '';
                if (link) link.href = data.otpauth_uri || '#';
                if (qrContainer && window.QRCode && data.otpauth_uri) {
                    qrContainer.innerHTML = '';
                    new window.QRCode(qrContainer, {
                        text: data.otpauth_uri,
                        width: 200,
                        height: 200,
                        correctLevel: window.QRCode.CorrectLevel.M
                    });
                }
                if (modal) modal.show();
            }).catch(function(error) {
                Swal.fire({icon: 'error', title: 'No se pudo iniciar la configuración', text: error.message, confirmButtonText: 'Aceptar'});
            }).finally(function() { setBusy(setupButton, false); });
        });
    }

    if (confirmButton) {
        confirmButton.addEventListener('click', function() {
            if (completed) { window.location.reload(); return; }
            var code = codeInput ? codeInput.value.replace(/\D/g, '') : '';
            if (code.length !== 6) {
                if (codeError) codeError.textContent = 'Ingresa el código de 6 dígitos de tu aplicación.';
                if (codeInput) codeInput.classList.add('is-invalid');
                return;
            }
            setBusy(confirmButton, true);
            jsonRequest('<?php echo base_url("perfil/totp_confirmar"); ?>', {codigo: code}).then(function(data) {
                completed = true;
                if (codeInput) codeInput.disabled = true;
                if (recoveryCodes) {
                    recoveryCodes.innerHTML = '';
                    (data.recovery_codes || []).forEach(function(item) {
                        var col = document.createElement('div');
                        col.className = 'col';
                        var codeEl = document.createElement('div');
                        codeEl.className = 'bg-body border border-gray-200 rounded p-2 text-center font-monospace fw-bold';
                        codeEl.textContent = item;
                        col.appendChild(codeEl);
                        recoveryCodes.appendChild(col);
                    });
                }
                if (recoveryWrap) recoveryWrap.classList.remove('d-none');
                confirmButton.querySelector('.indicator-label').textContent = 'Listo';
            }).catch(function(error) {
                if (codeError) codeError.textContent = error.message;
                if (codeInput) codeInput.classList.add('is-invalid');
            }).finally(function() { setBusy(confirmButton, false); });
        });
    }

    var copyButton = document.getElementById('btn_copy_totp_secret');
    if (copyButton) {
        copyButton.addEventListener('click', function() {
            if (!currentSecret) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(currentSecret);
            } else {
                var temp = document.createElement('textarea');
                temp.value = currentSecret;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
            copyButton.textContent = 'Copiado';
            window.setTimeout(function() { copyButton.textContent = 'Copiar'; }, 1500);
        });
    }

    if (disableButton) {
        disableButton.addEventListener('click', function() {
            Swal.fire({
                title: '¿Deshabilitar aplicación autenticadora?',
                text: 'Se requiere tu contraseña actual para deshabilitar este método.',
                input: 'password',
                inputLabel: 'Contraseña actual',
                inputAttributes: {autocomplete: 'current-password'},
                showCancelButton: true,
                confirmButtonText: 'Deshabilitar',
                cancelButtonText: 'Cancelar',
                customClass: {confirmButton: 'btn btn-sm btn-danger', cancelButton: 'btn btn-sm btn-light ms-2'}
            }).then(function(result) {
                if (!result.isConfirmed) return;
                setBusy(disableButton, true);
                jsonRequest('<?php echo base_url("perfil/totp_desactivar"); ?>', {clave_actual: result.value || ''}).then(function() {
                    window.location.reload();
                }).catch(function(error) {
                    Swal.fire({icon: 'error', title: 'No se pudo deshabilitar', text: error.message, confirmButtonText: 'Aceptar'});
                }).finally(function() { setBusy(disableButton, false); });
            });
        });
    }
});
</script>
