<?php
$dashboard_data = $dashboard ?? [];
$total_usuarios     = (int)($dashboard_data['total_usuarios'] ?? 0);
$usuarios_activos   = (int)($dashboard_data['usuarios_activos'] ?? 0);
$total_roles        = (int)($dashboard_data['total_roles'] ?? 0);
$total_bitacora     = (int)($dashboard_data['total_bitacora'] ?? 0);
$total_sesiones     = (int)($dashboard_data['total_sesiones'] ?? 1);
$actividad_reciente = $dashboard_data['actividad_reciente'] ?? [];
$logins_recientes   = $dashboard_data['logins_recientes'] ?? [];

$nombre_usuario = trim(($usuario['usu_nombre'] ?? '') . ' ' . ($usuario['usu_apellido'] ?? '')) ?: ($usuario['usu_usuario'] ?? 'Usuario');
?>

<!--begin::Hero Banner-->
<div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end mb-5 mb-xl-8" style="background-color: #1B1C22; background-image:url('<?php echo base_url('assets/media/patterns/pattern-1.jpg'); ?>')">
    <div class="card-body pt-9 pb-8">
        <div class="d-flex align-items-center">
            <div class="symbol symbol-65px symbol-circle me-5">
                <span class="symbol-label bg-light-primary text-primary fs-1 fw-bold">
                    <?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?>
                </span>
            </div>
            <div class="d-flex flex-column">
                <h1 class="text-white fw-bold fs-2x mb-1">¡Bienvenido de vuelta, <?php echo htmlspecialchars($nombre_usuario); ?>!</h1>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-gray-500 fs-6">Panel de Control Base del Sistema</span>
                    <span class="badge badge-light-primary fw-semibold"><?php echo htmlspecialchars($app_name ?? 'Proyecto Base'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Hero Banner-->

<!--begin::Stats Cards-->
<div class="row g-5 g-xl-8 mb-5 mb-xl-8">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border-0 shadow-sm">
            <div class="card-header pt-5">
                <div class="card-title d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2"><?php echo number_format($total_usuarios); ?></span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-6">Usuarios Registrados</span>
                </div>
                <span class="badge badge-light-success fs-base my-auto">
                    <i class="ki-duotone ki-user text-success fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $usuarios_activos; ?> Activos
                </span>
            </div>
            <div class="card-body pt-2 pb-5">
                <a href="<?php echo base_url('usuarios'); ?>" class="btn btn-sm btn-light-primary w-100">
                    Gestionar Usuarios
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border-0 shadow-sm">
            <div class="card-header pt-5">
                <div class="card-title d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2"><?php echo number_format($total_roles); ?></span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-6">Roles & Permisos</span>
                </div>
                <div class="symbol symbol-45px bg-light-warning">
                    <i class="ki-duotone ki-security-user fs-2x text-warning p-2"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="card-body pt-2 pb-5">
                <a href="<?php echo base_url('roles-y-permisos'); ?>" class="btn btn-sm btn-light-warning w-100">
                    Configurar Roles
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border-0 shadow-sm">
            <div class="card-header pt-5">
                <div class="card-title d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2"><?php echo number_format($total_sesiones); ?></span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-6">Sesiones Activas</span>
                </div>
                <div class="symbol symbol-45px bg-light-info">
                    <i class="ki-duotone ki-laptop fs-2x text-info p-2"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="card-body pt-2 pb-5">
                <a href="<?php echo base_url('sesiones-activas'); ?>" class="btn btn-sm btn-light-info w-100">
                    Ver Sesiones
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border-0 shadow-sm">
            <div class="card-header pt-5">
                <div class="card-title d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2"><?php echo number_format($total_bitacora); ?></span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-6">Eventos en Bitácora</span>
                </div>
                <div class="symbol symbol-45px bg-light-danger">
                    <i class="ki-duotone ki-shield-tick fs-2x text-danger p-2"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="card-body pt-2 pb-5">
                <a href="<?php echo base_url('reporte-de-bitacora'); ?>" class="btn btn-sm btn-light-danger w-100">
                    Ver Auditoría
                </a>
            </div>
        </div>
    </div>
</div>
<!--end::Stats Cards-->

<!--begin::Row Logins & Bitácora-->
<div class="row g-5 g-xl-8">
    <!--begin::Bitácora Reciente-->
    <div class="col-xl-7">
        <div class="card card-flush h-xl-100 border-0 shadow-sm">
            <div class="card-header pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Actividad Reciente en el Sistema</span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-7">Últimos eventos registrados en bitácora</span>
                </h3>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('reporte-de-bitacora'); ?>" class="btn btn-sm btn-light">Ver todo</a>
                </div>
            </div>
            <div class="card-body pt-4">
                <?php if (empty($actividad_reciente)): ?>
                    <div class="text-center py-8 text-gray-500">No hay actividad reciente registrada en la bitácora.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted fs-7 text-uppercase">
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Detalle</th>
                                    <th>Fecha / Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividad_reciente as $act): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-35px symbol-circle me-3">
                                                    <span class="symbol-label bg-light-primary text-primary fw-semibold fs-7">
                                                        <?php 
                                                            $nom = !empty($act['usu_nombre']) ? $act['usu_nombre'] : ($act['bit_usuario'] ?? 'S');
                                                            echo strtoupper(substr($nom, 0, 1)); 
                                                        ?>
                                                    </span>
                                                </div>
                                                <span class="text-gray-800 fw-bold fs-7"><?php echo htmlspecialchars(!empty($act['usu_nombre']) ? $act['usu_nombre'] : ($act['bit_usuario'] ?? 'Sistema')); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-primary fs-8 fw-semibold"><?php echo htmlspecialchars($act['bit_accion']); ?></span>
                                        </td>
                                        <td class="text-gray-600 fs-7 text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($act['bit_detalles']); ?>
                                        </td>
                                        <td class="text-muted fs-8">
                                            <?php echo htmlspecialchars(!empty($act['bit_date']) ? date('d-m-Y H:i', strtotime($act['bit_date'])) : ''); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::Bitácora Reciente-->

    <!--begin::Accesos Recientes-->
    <div class="col-xl-5">
        <div class="card card-flush h-xl-100 border-0 shadow-sm">
            <div class="card-header pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Últimos Accesos</span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-7">Conexiones recientes por IP y dispositivo</span>
                </h3>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('registro-de-eventos'); ?>" class="btn btn-sm btn-light">Ver todo</a>
                </div>
            </div>
            <div class="card-body pt-4">
                <?php if (empty($logins_recientes)): ?>
                    <div class="text-center py-8 text-gray-500">No hay registros de acceso recientes.</div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($logins_recientes as $login): ?>
                            <div class="timeline-item d-flex mb-4">
                                <div class="timeline-line w-20px"></div>
                                <div class="timeline-icon symbol symbol-circle symbol-25px me-3">
                                    <div class="symbol-label bg-light-success">
                                        <i class="ki-duotone ki-geolocation fs-4 text-success"><span class="path1"></span><span class="path2"></span></i>
                                    </div>
                                </div>
                                <div class="timeline-content d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-gray-800 fw-bold fs-7"><?php echo htmlspecialchars(!empty($login['usu_nombre']) ? $login['usu_nombre'] : ($login['bit_usuario'] ?? 'Desconocido')); ?></span>
                                        <span class="text-muted fs-8"><?php echo htmlspecialchars(!empty($login['bit_date']) ? date('d-m-Y H:i', strtotime($login['bit_date'])) : ''); ?></span>
                                    </div>
                                    <span class="text-muted fs-8">
                                        IP: <strong><?php echo htmlspecialchars($login['bit_ip'] ?? ''); ?></strong> · <?php echo htmlspecialchars(trim(($login['bit_navegador'] ?? '') . ' ' . ($login['bit_city'] ?? ''))); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::Accesos Recientes-->
</div>
<!--end::Row Logins & Bitácora-->