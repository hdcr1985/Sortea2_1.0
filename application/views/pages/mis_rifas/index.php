<?php
/**
 * M03 - Rifas: CRUD, numeración y estados.
 * Vista de autoservicio "Mis Rifas" (organizador).
 *
 * Contrato de datos provisto por el controlador MisRifas:
 * - $url_base      string  Raíz del módulo (por defecto "mis-rifas").
 * - $plan_info     array   Estado del plan y lotes/asignaciones del organizador.
 * - $numeraciones  array   Catálogo de numeraciones activas (M03.1).
 *
 * Endpoints consumidos (todos bajo base_url('mis-rifas/')):
 * - tabla (POST DataTables), datos/{id}, crear, actualizar/{id},
 *   eliminar/{id}, publicar/{id}, pausar/{id}, reactivar/{id},
 *   solicitar-cancelacion/{id}, finalizar/{id}.
 * - M03.2: imagenes/{id}, imagen-subir/{id}, imagen-portada/{id}/{img_id},
 *   imagen-eliminar/{id}/{img_id}.
 *
 * M03.1: el formulario envía rifa_asig_id y rifa_num_id. La numeración libre
 * anterior queda solo como hidden de compatibilidad; el backend deriva el
 * rango desde el lote o desde la numeración activa seleccionada.
 *
 * M03.2: la descripción se edita en un textarea y la galería acepta hasta 3
 * imágenes (1 portada + 2 adicionales). En estados finalizada/cancelada la
 * galería es de solo lectura.
 *
 * Claves DataTables:
 * - rifa, tipo, numeracion, estado, sorteo, acciones.
 *
 * Clases de acción emitidas por el backend (delegadas en JS):
 * - .btn-editar, .btn-publicar, .btn-pausar, .btn-reactivar,
 *   .btn-solicitar-cancelacion, .btn-finalizar, .btn-eliminar.
 */

$url_base = (isset($url_base) && is_string($url_base) && trim($url_base) !== '')
    ? trim($url_base, '/')
    : 'mis-rifas';
?>

<style>
#galeria_preview .ratio img,
#galeria_existentes .ratio img {
    object-fit: cover;
}
</style>

<?php
// ─────────────────────────────────────────────────────────
// Plan del organizador (lectura defensiva).
// Contrato: tiene_plan, usadas, limite, restantes, ilimitado,
// puede_publicar, motivo, fin.
// ─────────────────────────────────────────────────────────
$plan_info = (isset($plan_info) && is_array($plan_info)) ? $plan_info : [];

$plan_tiene = !empty($plan_info['tiene_plan']);
$plan_data = (isset($plan_info['plan']) && is_array($plan_info['plan'])) ? $plan_info['plan'] : [];
$plan_asig = (isset($plan_info['asignacion']) && is_array($plan_info['asignacion'])) ? $plan_info['asignacion'] : [];

$plan_nombre = trim((string)($plan_data['plan_nombre'] ?? $plan_data['nombre'] ?? $plan_info['plan_nombre'] ?? ''));
if ($plan_nombre === '') {
    $plan_nombre = 'Plan de rifas';
}

$plan_tipo = strtolower(trim((string)($plan_data['plan_tipo'] ?? $plan_data['tipo'] ?? $plan_info['tipo'] ?? '')));
$plan_ilimitado = !empty($plan_info['ilimitado']) || $plan_tipo === 'ilimitado';

$plan_limite = null;
if (isset($plan_info['limite']) && is_numeric($plan_info['limite'])) {
    $plan_limite = (int)$plan_info['limite'];
} elseif (isset($plan_data['plan_limite']) && is_numeric($plan_data['plan_limite'])) {
    $plan_limite = (int)$plan_data['plan_limite'];
} elseif (isset($plan_data['limite']) && is_numeric($plan_data['limite'])) {
    $plan_limite = (int)$plan_data['limite'];
}
if ($plan_limite !== null && $plan_limite <= 0) {
    $plan_ilimitado = true;
}

$plan_usadas = (isset($plan_info['usadas']) && is_numeric($plan_info['usadas']))
    ? max(0, (int)$plan_info['usadas'])
    : 0;

$plan_restantes = (isset($plan_info['restantes']) && is_numeric($plan_info['restantes']))
    ? max(0, (int)$plan_info['restantes'])
    : null;
if (!$plan_ilimitado && $plan_restantes === null && $plan_limite !== null) {
    $plan_restantes = max(0, $plan_limite - $plan_usadas);
}

$plan_puede_publicar = array_key_exists('puede_publicar', $plan_info)
    ? (bool)$plan_info['puede_publicar']
    : true;
$plan_motivo = trim((string)($plan_info['motivo'] ?? ''));

$plan_fin_raw = $plan_info['fin'] ?? ($plan_asig['asig_fin'] ?? '');
$plan_fin_texto = 'Sin límite';
if (is_string($plan_fin_raw) && trim($plan_fin_raw) !== '' && strtoupper(trim($plan_fin_raw)) !== 'NULL') {
    $plan_fin_ts = strtotime(trim($plan_fin_raw));
    $plan_fin_texto = $plan_fin_ts ? date('d/m/Y', $plan_fin_ts) : trim($plan_fin_raw);
}

$plan_estado_texto = 'Activo';
$plan_estado_clase = 'success';
if (!$plan_puede_publicar) {
    $plan_motivo_l = function_exists('mb_strtolower') ? mb_strtolower($plan_motivo, 'UTF-8') : strtolower($plan_motivo);

    if (strpos($plan_motivo_l, 'vencid') !== false) {
        $plan_estado_texto = 'Plan vencido';
        $plan_estado_clase = 'danger';
    } elseif (strpos($plan_motivo_l, 'cupo') !== false
        || strpos($plan_motivo_l, 'limite') !== false
        || strpos($plan_motivo_l, 'límite') !== false) {
        $plan_estado_texto = 'Sin cupo';
        $plan_estado_clase = 'warning';
    } else {
        $plan_estado_texto = 'No disponible';
        $plan_estado_clase = 'warning';
    }
} elseif (is_string($plan_fin_raw) && trim($plan_fin_raw) !== '' && strtoupper(trim($plan_fin_raw)) !== 'NULL') {
    $plan_fin_ts = strtotime(trim($plan_fin_raw));
    if ($plan_fin_ts && $plan_fin_ts < time()) {
        $plan_estado_texto = 'Plan vencido';
        $plan_estado_clase = 'danger';
    }
}

$plan_motivo_texto = $plan_motivo;
$plan_motivos = [
    'sin_plan' => 'Sin plan activo',
    'sin_cupo'  => 'Sin cupo disponible',
    'vencida'   => 'Plan vencido',
    'vencido'   => 'Plan vencido',
];
if (isset($plan_motivos[$plan_motivo])) {
    $plan_motivo_texto = $plan_motivos[$plan_motivo];
}

$plan_tipo_badge = $plan_ilimitado
    ? 'Ilimitado'
    : ($plan_limite !== null ? $plan_limite . ' ' . ($plan_limite === 1 ? 'rifa' : 'rifas') : 'Por rifas');

$plan_uso_texto = $plan_ilimitado
    ? ('Ilimitado · ' . $plan_usadas . ' usadas')
    : ($plan_usadas . ' / ' . ($plan_limite !== null ? $plan_limite : 0));

$plan_restantes_texto = $plan_ilimitado
    ? 'Ilimitado'
    : (string)($plan_restantes !== null ? $plan_restantes : 0);

$plan_progreso = 0;
if (!$plan_ilimitado && $plan_limite !== null && $plan_limite > 0) {
    $plan_progreso = (int)min(100, max(0, round(($plan_usadas / $plan_limite) * 100)));
}

// ─────────────────────────────────────────────────────────
// Lotes/asignaciones disponibles del organizador (M02.1).
// Si existen, el banner resume todos los lotes en lugar de
// mostrar un único plan.
// ─────────────────────────────────────────────────────────
$plan_lotes = (isset($plan_info['asignaciones']) && is_array($plan_info['asignaciones']))
    ? array_values($plan_info['asignaciones'])
    : [];

$plan_lotes_render = [];
foreach ($plan_lotes as $lote) {
    if (!is_array($lote)) {
        continue;
    }

    $lote_tipo = strtolower(trim((string)($lote['plan_tipo'] ?? $plan_tipo)));
    $lote_ilimitado = !empty($lote['ilimitado']) || $lote_tipo === 'ilimitado';

    $lote_cantidad = $lote_ilimitado ? null : (int)($lote['asig_cantidad'] ?? 0);
    $lote_usadas = (int)($lote['asig_rifas_usadas'] ?? 0);
    $lote_restantes = $lote_ilimitado
        ? null
        : (int)($lote['restantes'] ?? max(0, $lote_cantidad - $lote_usadas));

    $lote_fin = 'Sin caducidad';
    if (!empty($lote['asig_fin']) && strtoupper(trim((string)$lote['asig_fin'])) !== 'NULL') {
        $lote_fin_ts = strtotime((string)$lote['asig_fin']);
        $lote_fin = $lote_fin_ts ? date('d/m/Y', $lote_fin_ts) : (string)$lote['asig_fin'];
    }

    $lote_estado = strtolower(trim((string)($lote['asig_estado'] ?? 'activa')));
    $lote_estado_clase = 'success';
    $lote_estado_texto = 'Activa';
    if ($lote_estado === 'vencida') {
        $lote_estado_clase = 'warning';
        $lote_estado_texto = 'Vencida';
    } elseif ($lote_estado === 'cancelada') {
        $lote_estado_clase = 'danger';
        $lote_estado_texto = 'Cancelada';
    } elseif ($lote_estado === 'reemplazada') {
        $lote_estado_clase = 'secondary';
        $lote_estado_texto = 'Reemplazada';
    }

    $lote_num_id = (int)($lote['asig_num_id'] ?? $lote['num_id'] ?? 0);
    $lote_num_label = trim((string)($lote['num_label'] ?? ''));
    $lote_num_desde = isset($lote['num_desde']) ? (int)$lote['num_desde'] : 0;
    $lote_num_hasta = isset($lote['num_hasta']) ? (int)$lote['num_hasta'] : 0;
    $lote_num_padding = isset($lote['num_padding']) ? (int)$lote['num_padding'] : 2;

    $plan_lotes_render[] = [
        'id'              => (int)($lote['asig_id'] ?? 0),
        'num_id'          => $lote_num_id,
        'tipo'            => $lote_ilimitado ? 'ilimitado' : 'limitado',
        'plan'            => trim((string)($lote['plan_nombre'] ?? $plan_nombre)),
        'numeracion'      => $lote_num_label,
        'ilimitado'       => $lote_ilimitado,
        'cantidad'        => $lote_cantidad,
        'restantes'       => $lote_restantes,
        'agotado'         => (!$lote_ilimitado && $lote_restantes !== null && $lote_restantes <= 0),
        'num_desde'       => $lote_num_desde,
        'num_hasta'       => $lote_num_hasta,
        'num_padding'     => $lote_num_padding,
        'cantidad_texto'  => $lote_ilimitado ? 'Ilimitado' : (string)$lote_cantidad,
        'uso_texto'       => $lote_ilimitado
            ? ($lote_usadas . ' usadas · Ilimitado')
            : ($lote_usadas . ' / ' . (string)$lote_cantidad),
        'restantes_texto' => $lote_ilimitado ? 'Ilimitado' : (string)$lote_restantes,
        'fin'             => $lote_fin,
        'estado_clase'    => $lote_estado_clase,
        'estado_texto'    => $lote_estado_texto,
    ];
}

// ─────────────────────────────────────────────────────────
// Catálogo de numeraciones activas (M03.1).
// El backend puede no haberlo inyectado todavía; la vista
// degrada a un catálogo vacío sin romper el formulario.
// ─────────────────────────────────────────────────────────
$numeraciones = (isset($numeraciones) && is_array($numeraciones))
    ? $numeraciones
    : ((isset($plan_info['numeraciones']) && is_array($plan_info['numeraciones']))
        ? $plan_info['numeraciones']
        : []);

$numeraciones_render = [];
foreach ($numeraciones as $numeracion) {
    if (!is_array($numeracion)) {
        continue;
    }

    $num_id = (int)($numeracion['num_id'] ?? 0);
    if ($num_id <= 0) {
        continue;
    }

    $num_desde = isset($numeracion['num_desde']) ? (int)$numeracion['num_desde'] : 0;
    $num_hasta = isset($numeracion['num_hasta']) ? (int)$numeracion['num_hasta'] : 0;
    $num_padding = isset($numeracion['num_padding']) ? (int)$numeracion['num_padding'] : 2;
    $num_cantidad = isset($numeracion['num_cantidad']) && is_numeric($numeracion['num_cantidad'])
        ? (int)$numeracion['num_cantidad']
        : (($num_hasta > $num_desde) ? ($num_hasta - $num_desde + 1) : 0);

    $numeraciones_render[] = [
        'id'       => $num_id,
        'label'    => trim((string)($numeracion['num_label'] ?? ('Numeración ' . $num_id))),
        'cantidad' => $num_cantidad,
        'desde'    => $num_desde,
        'hasta'    => $num_hasta,
        'padding'  => $num_padding,
    ];
}

$plan_lotes_form = array_values(array_filter($plan_lotes_render, function ($lote) {
    return isset($lote['id']) && (int)$lote['id'] > 0;
}));
?>

<div class="d-flex flex-column gap-7 gap-lg-10">

    <!--begin::Banner del plan-->
    <div class="card card-flush shadow-sm">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center gap-3">
                    <div class="symbol symbol-45px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-crown fs-2 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="fw-bold text-gray-900 mb-1">Mi plan de rifas</h3>
                        <span class="text-muted fs-7">Cupo disponible para crear y publicar tus rifas.</span>
                    </div>
                </div>
            </div>
            <?php if ($plan_tiene): ?>
                <div class="card-toolbar">
                    <span class="badge badge-light-<?php echo htmlspecialchars($plan_estado_clase, ENT_QUOTES, 'UTF-8'); ?> fw-bold px-3 py-2">
                        <?php echo htmlspecialchars($plan_estado_texto, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body pt-2">
            <?php if ($plan_tiene): ?>
                <?php if (!empty($plan_lotes_render)): ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-bold text-gray-900 fs-5">Lotes disponibles</span>
                            <span class="badge badge-light-primary fw-bold px-3 py-2">
                                <?php echo count($plan_lotes_render); ?>
                                <?php echo count($plan_lotes_render) === 1 ? 'lote' : 'lotes'; ?>
                            </span>
                        </div>
                        <span class="text-muted fs-8">Cada lote mantiene su numeración y cupo por separado.</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                    <th scope="col" class="min-w-180px">Plan</th>
                                    <th scope="col" class="min-w-140px">Numeración</th>
                                    <th scope="col" class="min-w-130px">Uso</th>
                                    <th scope="col" class="min-w-110px">Restantes</th>
                                    <th scope="col" class="min-w-140px">Vigencia</th>
                                    <th scope="col" class="min-w-110px text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold">
                                <?php foreach ($plan_lotes_render as $lote_render): ?>
                                    <tr>
                                        <td class="fw-bold text-gray-900">
                                            <?php echo htmlspecialchars($lote_render['plan'] !== '' ? $lote_render['plan'] : $plan_nombre, ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <?php if ($lote_render['numeracion'] !== ''): ?>
                                                <span class="badge badge-light-primary fw-semibold px-3 py-2">
                                                    <?php echo htmlspecialchars($lote_render['numeracion'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-light-info fw-semibold px-3 py-2">Libre por rifa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lote_render['uso_texto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-bold text-gray-900"><?php echo htmlspecialchars($lote_render['restantes_texto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fs-8 text-muted"><?php echo htmlspecialchars($lote_render['fin'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-light-<?php echo htmlspecialchars($lote_render['estado_clase'], ENT_QUOTES, 'UTF-8'); ?> fw-bold px-3 py-2">
                                                <?php echo htmlspecialchars($lote_render['estado_texto'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-5">
                        <span class="fw-bold text-gray-900 fs-5"><?php echo htmlspecialchars($plan_nombre, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="badge badge-light-primary fw-bold px-3 py-2"><?php echo htmlspecialchars($plan_tipo_badge, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="row g-5">
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                                <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Uso</div>
                                <div class="fw-bold text-gray-900 fs-5"><?php echo htmlspecialchars($plan_uso_texto, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!$plan_ilimitado && $plan_limite !== null && $plan_limite > 0): ?>
                                    <div class="progress h-6px w-100 bg-light-primary mt-3">
                                        <div class="progress-bar bg-primary"
                                            role="progressbar"
                                            style="width: <?php echo (int)$plan_progreso; ?>%"
                                            aria-valuenow="<?php echo (int)$plan_progreso; ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                                <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Restantes</div>
                                <div class="fw-bold text-gray-900 fs-5"><?php echo htmlspecialchars($plan_restantes_texto, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                                <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Vigencia</div>
                                <div class="fw-bold text-gray-900 fs-6"><?php echo htmlspecialchars($plan_fin_texto, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                                <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Estado</div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="bullet bullet-dot bg-<?php echo htmlspecialchars($plan_estado_clase, ENT_QUOTES, 'UTF-8'); ?> w-10px h-10px"></span>
                                    <span class="fw-bold text-gray-900 fs-6"><?php echo htmlspecialchars($plan_estado_texto, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$plan_puede_publicar): ?>
                    <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-start gap-3 mt-5 mb-0" role="alert">
                        <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <div class="text-gray-700 fs-7">
                            No puedes publicar nuevas rifas en este momento.
                            <?php if ($plan_motivo_texto !== ''): ?>
                                Motivo: <strong><?php echo htmlspecialchars($plan_motivo_texto, ENT_QUOTES, 'UTF-8'); ?></strong>.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-start gap-3 mb-0" role="alert">
                    <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                    </i>
                    <div class="text-gray-700 fs-7">
                        <strong>No tienes plan activo.</strong>
                        Puedes crear borradores y rifas gratuitas, pero no publicar rifas pagas.
                        <?php if ($plan_motivo_texto !== ''): ?>
                            <span class="d-block mt-1">Detalle: <?php echo htmlspecialchars($plan_motivo_texto, ENT_QUOTES, 'UTF-8'); ?>.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!--end::Banner del plan-->

    <!--begin::Card Mis Rifas-->
    <div class="card card-flush shadow-sm">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex flex-column">
                    <h3 class="fw-bold text-gray-900 mb-1">Mis rifas</h3>
                    <span class="text-muted fs-7">Crea, edita y administra el ciclo de vida de tus rifas.</span>
                </div>
            </div>
            <div class="card-toolbar gap-3 flex-wrap">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text"
                        id="filtro_mis_rifas"
                        class="form-control form-control-solid w-250px ps-12"
                        placeholder="Buscar mis rifas..."
                        aria-label="Buscar mis rifas" />
                </div>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nueva_rifa">
                    <i class="ki-duotone ki-plus fs-4 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Nueva Rifa
                </button>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="alert alert-warning d-none mb-4" id="aviso_datatables_mis_rifas">
                No se pudo cargar el componente de tablas. Recarga la página o contacta al administrador.
            </div>

            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_mis_rifas">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                            <th scope="col" class="min-w-260px">Rifa</th>
                            <th scope="col" class="min-w-140px">Tipo / Precio</th>
                            <th scope="col" class="min-w-160px">Numeración</th>
                            <th scope="col" class="min-w-130px text-center">Estado</th>
                            <th scope="col" class="min-w-150px">Sorteo</th>
                            <th scope="col" class="min-w-140px text-end pe-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold"></tbody>
                </table>
            </div>
        </div>
    </div>
    <!--end::Card Mis Rifas-->
</div>

<!--begin::Modal Crear / Editar Rifa-->
<div class="modal fade" id="modal_rifa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-gift fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_rifa_titulo">Nueva Rifa</h4>
                        <span class="text-muted fs-8">Configura la información, numeración y publicación de tu rifa</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_rifa">
                    <ul class="mb-0 fs-7" id="lista_errores_rifa"></ul>
                </div>
                <!--end::Alerta de errores-->

                <!--begin::Aviso de campos bloqueados-->
                <div class="alert bg-light-info border border-info border-dashed d-none align-items-start gap-3 mb-6" id="aviso_rifa_bloqueada" role="alert">
                    <i class="ki-duotone ki-lock-2 fs-2 text-info mt-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <div class="text-gray-700 fs-7">
                        En este estado solo puedes editar el título, la descripción, las condiciones, la fecha de sorteo, la imagen, el modo de asignación y la visibilidad de los números. Los demás campos están bloqueados para proteger la rifa publicada.
                    </div>
                </div>
                <!--end::Aviso de campos bloqueados-->

                <form id="form_rifa" enctype="multipart/form-data" novalidate="novalidate">
                    <input type="hidden" name="rifa_id" id="rifa_id" value="0" />
                    <!--
                        Campos legacy de numeración: se conservan ocultos para
                        compatibilidad con el contrato anterior. Ya no son
                        editables; el lote/numeración se deriva del catálogo.
                    -->
                    <input type="hidden" name="rifa_total_numeros" id="rifa_total_numeros" value="100" />
                    <input type="hidden" name="rifa_numeracion_tipo" id="rifa_numeracion_tipo" value="2" />
                    <input type="hidden" name="rifa_numero_desde" id="rifa_numero_desde" value="0" />
                    <input type="hidden" name="rifa_numero_hasta" id="rifa_numero_hasta" value="99" />
                    <input type="hidden" name="rifa_padding" id="rifa_padding" value="2" />

                    <div class="row g-5">
                        <!--begin::Título-->
                        <div class="col-md-7">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_titulo">Título</label>
                            <input type="text"
                                name="rifa_titulo"
                                id="rifa_titulo"
                                class="form-control form-control-solid"
                                placeholder="Ej: Gran Rifa de Aniversario"
                                minlength="3"
                                maxlength="150"
                                autocomplete="off"
                                required />
                            <div class="invalid-feedback" data-error-for="rifa_titulo"></div>
                            <div class="text-muted fs-8 mt-1">Entre 3 y 150 caracteres.</div>
                        </div>
                        <!--end::Título-->

                        <!--begin::Slug-->
                        <div class="col-md-5">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_slug">Slug</label>
                            <input type="text"
                                name="rifa_slug"
                                id="rifa_slug"
                                class="form-control form-control-solid"
                                placeholder="gran-rifa-de-aniversario"
                                minlength="3"
                                maxlength="120"
                                pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                autocomplete="off"
                                spellcheck="false"
                                required />
                            <div class="invalid-feedback" data-error-for="rifa_slug"></div>
                            <div class="text-muted fs-8 mt-1">Minúsculas, números y guiones simples. Se autogenera desde el título.</div>
                        </div>
                        <!--end::Slug-->

                        <!--begin::Descripción-->
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="rifa_descripcion">Descripción</label>
                            <textarea name="rifa_descripcion"
                                id="rifa_descripcion"
                                class="form-control form-control-solid"
                                rows="5"
                                maxlength="20000"
                                placeholder="Describe el premio o la dinámica de la rifa"></textarea>
                            <div class="invalid-feedback" data-error-for="rifa_descripcion"></div>
                            <div class="text-muted fs-8 mt-1">Máximo 20000 caracteres.</div>
                        </div>
                        <!--end::Descripción-->

                        <!--begin::Condiciones-->
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="rifa_condiciones">Condiciones</label>
                            <textarea name="rifa_condiciones"
                                id="rifa_condiciones"
                                class="form-control form-control-solid"
                                rows="3"
                                maxlength="5000"
                                placeholder="Bases, restricciones y condiciones de participación"></textarea>
                            <div class="invalid-feedback" data-error-for="rifa_condiciones"></div>
                            <div class="text-muted fs-8 mt-1">Máximo 5000 caracteres.</div>
                        </div>
                        <!--end::Condiciones-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Tipo-->
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_tipo">Tipo de rifa</label>
                            <select name="rifa_tipo" id="rifa_tipo" class="form-select form-select-solid" required>
                                <option value="paga" selected>Paga</option>
                                <option value="gratuita">Gratuita (publicación gratis)</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="rifa_tipo"></div>
                            <div class="text-muted fs-8 mt-1">Gratuita: no consume plan y queda sujeta a aprobación. El precio del ticket se define aparte.</div>
                        </div>
                        <!--end::Tipo-->

                        <!--begin::Precio-->
                        <div class="col-md-4" id="wrap_rifa_precio">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_precio">Precio</label>
                            <div class="position-relative">
                                <input type="number"
                                    name="rifa_precio"
                                    id="rifa_precio"
                                    class="form-control form-control-solid pe-8"
                                    placeholder="0.00"
                                    min="0.01"
                                    max="9999999999.99"
                                    step="0.01"
                                    value="0.00" />
                                <span class="position-absolute end-0 top-50 translate-middle-y me-3 text-muted fs-7">
                                    <i class="ki-duotone ki-dollar fs-5 text-gray-500">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="invalid-feedback" data-error-for="rifa_precio"></div>
                        </div>
                        <!--end::Precio-->

                        <!--begin::Moneda-->
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_moneda">Moneda</label>
                            <select name="rifa_moneda" id="rifa_moneda" class="form-select form-select-solid" required>
                                <?php foreach ($monedas as $moneda_item): ?>
                                    <?php
                                    $moneda_codigo  = (string)($moneda_item['mon_codigo'] ?? 'USD');
                                    $moneda_simbolo = (string)($moneda_item['mon_simbolo'] ?? $moneda_codigo);
                                    $moneda_nombre  = (string)($moneda_item['mon_nombre'] ?? $moneda_codigo);
                                    ?>
                                    <option value="<?php echo htmlspecialchars($moneda_codigo, ENT_QUOTES, 'UTF-8'); ?>"
                                        title="<?php echo htmlspecialchars($moneda_nombre, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $moneda_codigo === 'USD' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($moneda_codigo . ' (' . $moneda_simbolo . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-error-for="rifa_moneda"></div>
                        </div>
                        <!--end::Moneda-->

                        <!--begin::Plan y numeración-->
                        <div class="col-12" id="wrap_rifa_plan_num">
                            <div class="border border-gray-200 rounded-3 p-4">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ki-duotone ki-layers fs-3 text-primary">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-gray-900 fs-6">Plan y numeración</span>
                                            <span class="text-muted fs-8">Selecciona el lote que financiará esta rifa.</span>
                                        </div>
                                    </div>
                                    <span class="badge badge-light-primary fw-bold px-3 py-2" id="rifa_plan_badge">—</span>
                                </div>

                                <div class="alert bg-light-warning border border-warning border-dashed d-none align-items-start gap-3 mb-4" id="aviso_rifa_sin_lotes" role="alert">
                                    <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                    <div class="text-gray-700 fs-7">
                                        No tienes lotes disponibles. El admin debe asignarte un plan.
                                    </div>
                                </div>

                                <div class="row g-5" id="wrap_rifa_lote_num">
                                    <div class="col-md-6">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_asig_id">Lote</label>
                                        <select name="rifa_asig_id" id="rifa_asig_id" class="form-select form-select-solid">
                                            <?php if (empty($plan_lotes_form)): ?>
                                                <option value="0" selected>Sin lotes disponibles</option>
                                            <?php else: ?>
                                                <option value="0" selected>Selecciona un lote</option>
                                                <?php foreach ($plan_lotes_form as $lote_form): ?>
                                                    <?php
                                                    $lote_form_num_label = $lote_form['numeracion'] !== ''
                                                        ? $lote_form['numeracion']
                                                        : 'Sin numeración';
                                                    $lote_form_label = $lote_form['ilimitado']
                                                        ? ($lote_form['plan'] . ' — Ilimitado')
                                                        : ($lote_form['plan'] . ' — ' . $lote_form_num_label . ' — quedan ' . (int)$lote_form['restantes']);
                                                    ?>
                                                    <option value="<?php echo (int)$lote_form['id']; ?>"
                                                        data-tipo="<?php echo htmlspecialchars($lote_form['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-num-id="<?php echo (int)$lote_form['num_id']; ?>"
                                                        data-num-label="<?php echo htmlspecialchars($lote_form_num_label, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-cantidad="<?php echo $lote_form['ilimitado'] ? '' : (int)$lote_form['cantidad']; ?>"
                                                        data-restantes="<?php echo $lote_form['ilimitado'] ? '' : (int)$lote_form['restantes']; ?>"
                                                        data-num-desde="<?php echo (int)$lote_form['num_desde']; ?>"
                                                        data-num-hasta="<?php echo (int)$lote_form['num_hasta']; ?>"
                                                        data-num-padding="<?php echo (int)$lote_form['num_padding']; ?>"
                                                        data-agotado="<?php echo $lote_form['agotado'] ? '1' : '0'; ?>"
                                                        <?php echo $lote_form['agotado'] ? 'disabled' : ''; ?>>
                                                        <?php echo htmlspecialchars($lote_form_label, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback" data-error-for="rifa_asig_id"></div>
                                        <div class="text-muted fs-8 mt-1">Solo se listan tus lotes activos y vigentes.</div>
                                    </div>

                                    <div class="col-md-6" id="wrap_rifa_num_select">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_num_id">Numeración</label>
                                        <select name="rifa_num_id" id="rifa_num_id" class="form-select form-select-solid">
                                            <?php if (empty($numeraciones_render)): ?>
                                                <option value="0" selected>Sin numeraciones activas</option>
                                            <?php else: ?>
                                                <option value="0" selected>Selecciona una numeración</option>
                                                <?php foreach ($numeraciones_render as $numeracion_render): ?>
                                                    <?php
                                                    $numeracion_texto = $numeracion_render['label'];
                                                    if ($numeracion_render['hasta'] >= $numeracion_render['desde']) {
                                                        $numeracion_texto .= ' · '
                                                            . str_pad((string)$numeracion_render['desde'], max(1, (int)$numeracion_render['padding']), '0', STR_PAD_LEFT)
                                                            . '–'
                                                            . str_pad((string)$numeracion_render['hasta'], max(1, (int)$numeracion_render['padding']), '0', STR_PAD_LEFT);
                                                    }
                                                    ?>
                                                    <option value="<?php echo (int)$numeracion_render['id']; ?>"
                                                        data-cantidad="<?php echo (int)$numeracion_render['cantidad']; ?>"
                                                        data-desde="<?php echo (int)$numeracion_render['desde']; ?>"
                                                        data-hasta="<?php echo (int)$numeracion_render['hasta']; ?>"
                                                        data-padding="<?php echo (int)$numeracion_render['padding']; ?>">
                                                        <?php echo htmlspecialchars($numeracion_texto, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback" data-error-for="rifa_num_id"></div>
                                        <div class="text-muted fs-8 mt-1">Disponible cuando el lote es ilimitado.</div>
                                    </div>

                                    <div class="col-md-6 d-none" id="wrap_rifa_num_readonly">
                                        <label class="form-label fw-semibold fs-7 text-gray-800">Numeración del lote</label>
                                        <div class="border border-gray-200 rounded-3 bg-light p-3">
                                            <div class="fw-bold text-gray-900 fs-6" id="rifa_num_readonly_label">—</div>
                                            <div class="text-muted fs-8" id="rifa_num_readonly_rango">—</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800">Vista previa</label>
                                        <div class="border border-gray-200 rounded-3 bg-light p-3 h-100 d-flex align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ki-duotone ki-abstract-26 fs-3 text-primary">
                                                    <span class="path1"></span><span class="path2"></span>
                                                </i>
                                                <span class="fw-semibold text-gray-900 fs-7" id="rifa_numeracion_resumen">—</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Plan y numeración-->

                        <!--begin::Métodos de pago (M05)-->
                        <div class="col-12" id="wrap_rifa_metodos">
                            <input type="hidden" name="rifa_metodos_present" value="1" />
                            <div class="border border-gray-200 rounded-3 p-4">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ki-duotone ki-bill fs-3 text-primary">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-gray-900 fs-6">Métodos de pago</span>
                                            <span class="text-muted fs-8">Selecciona los métodos que verá el comprador en esta rifa.</span>
                                        </div>
                                    </div>
                                    <a href="<?php echo base_url('mis-metodos-pago'); ?>" target="_blank" rel="noopener"
                                        class="btn btn-sm btn-light-primary fw-semibold">
                                        <i class="ki-duotone ki-setting-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Administrar métodos
                                    </a>
                                </div>

                                <?php if (empty($metodos_pago)): ?>
                                    <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-start gap-3 mb-0" role="alert">
                                        <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                        <div class="text-gray-700 fs-7">
                                            No tienes métodos de pago activos y completos.
                                            <a href="<?php echo base_url('mis-metodos-pago'); ?>" class="fw-bold text-primary">Configura tus métodos</a>
                                            antes de publicar una rifa paga.
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3" id="rifa_metodos_lista">
                                        <?php foreach ($metodos_pago as $metodo_item): ?>
                                            <?php
                                            $orgmet_id = (int)$metodo_item['orgmet_id'];
                                            $es_custom = (string)$metodo_item['met_scope'] === 'organizador';
                                            $met_imagen = trim((string)($metodo_item['met_imagen'] ?? ''));
                                            $met_imagen_url = $met_imagen !== ''
                                                ? base_url('media/metodo/' . (int)$metodo_item['met_id'])
                                                : '';
                                            $met_icono = (string)($metodo_item['met_icono'] ?? 'ki-bill');
                                            if (!preg_match('/^ki-[a-z0-9-]+$/', $met_icono)) {
                                                $met_icono = 'ki-bill';
                                            }
                                            ?>
                                            <div class="col-md-6">
                                                <label class="border border-gray-200 rounded-3 p-3 bg-light d-flex align-items-start gap-3 h-100"
                                                    for="rifa_metodo_<?php echo $orgmet_id; ?>">
                                                    <div class="form-check form-check-custom form-check-solid mt-1">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="rifa_metodos[]"
                                                            id="rifa_metodo_<?php echo $orgmet_id; ?>"
                                                            value="<?php echo $orgmet_id; ?>" />
                                                    </div>
                                                    <div class="symbol symbol-35px symbol-circle bg-light-primary flex-shrink-0">
                                                        <span class="symbol-label">
                                                            <?php if ($met_imagen_url !== ''): ?>
                                                                <img src="<?php echo htmlspecialchars($met_imagen_url, ENT_QUOTES, 'UTF-8'); ?>" alt=""
                                                                    loading="lazy" referrerpolicy="no-referrer"
                                                                    style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />
                                                            <?php else: ?>
                                                                <i class="ki-duotone <?php echo htmlspecialchars($met_icono, ENT_QUOTES, 'UTF-8'); ?> fs-4 text-primary">
                                                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                                                </i>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-gray-900 fs-7">
                                                            <?php echo htmlspecialchars((string)$metodo_item['met_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                        <span class="badge <?php echo $es_custom ? 'badge-light-info' : 'badge-light-primary'; ?> fw-bold align-self-start mt-1">
                                                            <?php echo $es_custom ? 'Propio' : 'Global'; ?>
                                                        </span>
                                                        <span class="text-muted fs-8 mt-1">
                                                            <?php echo htmlspecialchars((string)($metodo_item['met_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!--end::Métodos de pago (M05)-->

                        <!--begin::Aviso rifa gratuita-->
                        <div class="col-12 d-none" id="aviso_rifa_gratuita">
                            <div class="alert bg-light-info border border-info border-dashed d-flex align-items-start gap-3 mb-0" role="alert">
                                <i class="ki-duotone ki-information-2 fs-2 text-info mt-1">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                </i>
                                <div class="text-gray-700 fs-7">
                                    Las rifas gratuitas no consumen un lote: la publicación es gratuita y queda pendiente de aprobación del administrador.
                                    Puedes definir el precio del ticket para recaudar fondos; el admin asignará la numeración al aprobar.
                                </div>
                            </div>
                        </div>
                        <!--end::Aviso rifa gratuita-->

                        <!--begin::Máximo por compra-->
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_max_por_compra">Máximo por compra</label>
                            <input type="number"
                                name="rifa_max_por_compra"
                                id="rifa_max_por_compra"
                                class="form-control form-control-solid"
                                min="1"
                                step="1"
                                value="10" />
                            <div class="invalid-feedback" data-error-for="rifa_max_por_compra"></div>
                            <div class="text-muted fs-8 mt-1">Entre 1 y el total de números.</div>
                        </div>
                        <!--end::Máximo por compra-->

                        <!--begin::TTL reserva-->
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="rifa_reserva_ttl_min">TTL de reserva (min)</label>
                            <input type="number"
                                name="rifa_reserva_ttl_min"
                                id="rifa_reserva_ttl_min"
                                class="form-control form-control-solid"
                                min="0"
                                max="10080"
                                step="1"
                                value="60" />
                            <div class="invalid-feedback" data-error-for="rifa_reserva_ttl_min"></div>
                            <div class="text-muted fs-8 mt-1">0 = sin expiración. Máximo 10080 minutos.</div>
                        </div>
                        <!--end::TTL reserva-->

                        <!--begin::Auto-expiración-->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800">Auto-expiración</label>
                            <div class="border border-gray-200 rounded-3 p-3 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Liberar reservas vencidas</div>
                                    <div class="text-muted fs-8">Según el TTL configurado.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="rifa_reserva_auto" id="rifa_reserva_auto" value="1" checked />
                                </div>
                            </div>
                            <div class="invalid-feedback" data-error-for="rifa_reserva_auto"></div>
                        </div>
                        <!--end::Auto-expiración-->

                        <!--begin::Modo de asignación (M03.3)-->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="rifa_modo_asignacion">Asignación de números</label>
                            <select name="rifa_modo_asignacion" id="rifa_modo_asignacion" class="form-select form-select-solid">
                                <option value="manual" selected>Manual: el comprador elige los números</option>
                                <option value="automatica">Automática: el sistema asigna números aleatorios</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="rifa_modo_asignacion"></div>
                            <div class="text-muted fs-8 mt-1">En automática se excluyen reservados, pagados y bloqueados.</div>
                        </div>
                        <!--end::Modo de asignación (M03.3)-->

                        <!--begin::Visibilidad de reservados (M03.3)-->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800">Números reservados en el sitio público</label>
                            <input type="hidden" name="rifa_mostrar_reservados" value="0" />
                            <div class="border border-gray-200 rounded-3 p-3 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Mostrar reservados y bloqueados</div>
                                    <div class="text-muted fs-8">Si se desactiva, el sitio público solo muestra los números disponibles.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="rifa_mostrar_reservados" id="rifa_mostrar_reservados" value="1" checked />
                                </div>
                            </div>
                            <div class="invalid-feedback" data-error-for="rifa_mostrar_reservados"></div>
                        </div>
                        <!--end::Visibilidad de reservados (M03.3)-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Fecha de sorteo-->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="rifa_fecha_sorteo">Fecha de sorteo</label>
                            <input type="datetime-local"
                                name="rifa_fecha_sorteo"
                                id="rifa_fecha_sorteo"
                                class="form-control form-control-solid" />
                            <div class="invalid-feedback" data-error-for="rifa_fecha_sorteo"></div>
                            <div class="text-muted fs-8 mt-1">Opcional en borrador. Debe ser futura para publicar.</div>
                        </div>
                        <!--end::Fecha de sorteo-->

                        <!--begin::Galería de imágenes (M03.2)-->
                        <div class="col-12" id="wrap_galeria_rifa">
                            <div class="border border-gray-200 rounded-3 p-4">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ki-duotone ki-picture fs-3 text-primary">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-gray-900 fs-6">Imágenes (máx. 3)</span>
                                            <span class="text-muted fs-8">1 portada + 2 adicionales. JPG, PNG o WEBP, máximo 4 MB por imagen.</span>
                                        </div>
                                    </div>
                                    <span class="badge badge-light-primary fw-bold px-3 py-2" id="galeria_contador">0 / 3</span>
                                </div>

                                <div class="alert bg-light-info border border-info border-dashed d-none align-items-start gap-3 mb-4" id="aviso_galeria_bloqueada" role="alert">
                                    <i class="ki-duotone ki-lock-2 fs-2 text-info mt-1">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    <div class="text-gray-700 fs-7">
                                        La galería no puede modificarse porque la rifa está finalizada o cancelada.
                                    </div>
                                </div>

                                <div class="alert bg-light-warning border border-warning border-dashed d-none align-items-start gap-3 mb-4" id="aviso_galeria_portada" role="alert">
                                    <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                    <div class="text-gray-700 fs-7">
                                        La portada es obligatoria para publicar la rifa.
                                    </div>
                                </div>

                                <div id="galeria_modo_crear">
                                    <label class="form-label fw-semibold fs-7 text-gray-800" for="rifa_imagenes">Seleccionar imágenes</label>
                                    <input type="file"
                                        name="rifa_imagenes[]"
                                        id="rifa_imagenes"
                                        class="form-control form-control-solid"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        multiple />
                                    <div class="invalid-feedback d-block" data-error-for="rifa_imagenes"></div>
                                    <div class="text-muted fs-8 mt-1">Puedes seleccionar hasta 3 imágenes. La primera será la portada por defecto.</div>
                                    <div class="row g-3 mt-1" id="galeria_preview">
                                        <div class="col-12 text-muted fs-8" id="galeria_preview_vacio">No has seleccionado imágenes.</div>
                                    </div>
                                    <input type="hidden" name="rifa_portada_index" id="rifa_portada_index" value="0" />
                                </div>

                                <div id="galeria_modo_editar" class="d-none">
                                    <div class="row g-3" id="galeria_existentes"></div>

                                    <div class="mt-4" id="galeria_agregar_wrap">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="galeria_agregar_input">Agregar imágenes</label>
                                        <input type="file"
                                            id="galeria_agregar_input"
                                            class="form-control form-control-solid"
                                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                            multiple />
                                        <div class="invalid-feedback d-block" data-error-for="galeria_agregar_input"></div>
                                        <div class="text-muted fs-8 mt-1" id="galeria_agregar_ayuda">Puedes agregar hasta 3 imágenes.</div>
                                    </div>

                                    <div class="text-muted fs-7 mt-3 d-none" id="galeria_limite_msg">
                                        <i class="ki-duotone ki-information-2 fs-5 me-1">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                        Límite de 3 imágenes alcanzado. Elimina una para agregar otra.
                                    </div>

                                    <input type="hidden" name="rifa_portada_id" id="rifa_portada_id" value="0" disabled />
                                </div>

                                <div class="text-muted fs-8 mt-2" id="galeria_estado" role="status" aria-live="polite"></div>
                            </div>
                        </div>
                        <!--end::Galería de imágenes (M03.2)-->
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_rifa">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar Rifa
                    </span>
                    <span class="indicator-progress">
                        Por favor espere...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>

        </div>
    </div>
</div>
<!--end::Modal Crear / Editar Rifa-->

<!--begin::Modal Imagen Rifa-->
<div class="modal fade" id="modal_imagen_rifa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-gray-900">Imagen de la rifa</h5>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body text-center p-6">
                <img id="imagen_rifa_ampliada" src="" alt="Imagen ampliada de la rifa"
                    class="img-fluid rounded" style="max-height:80vh;object-fit:contain;" />
            </div>
        </div>
    </div>
</div>
<!--end::Modal Imagen Rifa-->

<!--begin::Modal Números / Bloqueos / Venta presencial (M03.3)-->
<div class="modal fade" id="modal_numeros_rifa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-info">
                        <i class="ki-duotone ki-abstract-26 fs-3 text-info">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Números de la rifa</h4>
                        <span class="text-muted fs-8" id="numeros_rifa_subtitulo">Bloquea, libera o registra ventas presenciales</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert alert-danger d-none mb-5 rounded-3" id="alert_numeros_rifa" role="alert"></div>

                <div class="row g-3 mb-6">
                    <div class="col-6 col-md">
                        <div class="border border-gray-200 rounded-3 p-3 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Total</div>
                            <div class="fw-bold text-gray-900 fs-4" id="num_total">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border border-gray-200 rounded-3 p-3 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Disponibles</div>
                            <div class="fw-bold text-success fs-4" id="num_disponibles">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border border-gray-200 rounded-3 p-3 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Reservados</div>
                            <div class="fw-bold text-warning fs-4" id="num_reservados">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border border-gray-200 rounded-3 p-3 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Pagados</div>
                            <div class="fw-bold text-primary fs-4" id="num_pagados">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border border-gray-200 rounded-3 p-3 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Bloqueados</div>
                            <div class="fw-bold text-danger fs-4" id="num_bloqueados">0</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mb-6">
                    <span class="badge badge-light-primary fw-bold px-3 py-2" id="num_modo_badge">Asignación: —</span>
                    <span class="badge badge-light-info fw-bold px-3 py-2" id="num_visibilidad_badge">Sitio público: —</span>
                    <span class="badge badge-light-secondary fw-bold px-3 py-2" id="num_rango_badge">Rango: —</span>
                </div>

                <div class="border border-gray-200 rounded-3 p-4 mb-6">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                        <div>
                            <div class="fw-bold text-gray-900 fs-6">Operar números</div>
                            <div class="text-muted fs-8">Acepta rangos y números específicos: <code>10-20, 25, 86</code>. La operación es todo o nada.</div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="numeros_input">Rango o números</label>
                            <input type="text" class="form-control form-control-solid" id="numeros_input"
                                placeholder="10-20, 25, 86" maxlength="10000" autocomplete="off" />
                            <div class="text-muted fs-8 mt-1">Si el rango incluye un número ocupado, no se bloquea ninguno.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="numeros_motivo">Motivo (opcional)</label>
                            <input type="text" class="form-control form-control-solid" id="numeros_motivo"
                                placeholder="Ej: reserva para evento" maxlength="255" autocomplete="off" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="numeros_comprador">Comprador presencial</label>
                            <input type="text" class="form-control form-control-solid" id="numeros_comprador"
                                placeholder="Nombre del comprador" maxlength="150" autocomplete="off" />
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="button" class="btn btn-sm btn-warning fw-semibold" id="btn_bloquear_numeros">
                            <i class="ki-duotone ki-lock-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                            Bloquear
                        </button>
                        <button type="button" class="btn btn-sm btn-info fw-semibold" id="btn_liberar_numeros">
                            <i class="ki-duotone ki-arrows-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Liberar bloqueo
                        </button>
                        <button type="button" class="btn btn-sm btn-success fw-semibold" id="btn_venta_numeros">
                            <i class="ki-duotone ki-check-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Venta presencial
                        </button>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-3 p-4 mb-6">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-bold text-gray-900 fs-6">Bloqueos activos</div>
                            <div class="text-muted fs-8">Puedes liberar un rango completo o números específicos.</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3 mb-0">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-8 text-uppercase">
                                    <th>Rango</th>
                                    <th class="text-center">Cantidad</th>
                                    <th>Motivo</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tabla_bloqueados_numeros">
                                <tr>
                                    <td colspan="4" class="text-center text-muted fs-7 py-4">Sin bloqueos activos.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-3 p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-bold text-gray-900 fs-6">Mapa de números</div>
                            <div class="text-muted fs-8">Verde disponible · amarillo reservado · azul pagado · rojo bloqueado.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-icon btn-light" id="btn_mapa_prev" aria-label="Página anterior">
                                <i class="ki-duotone ki-left fs-4"><span class="path1"></span><span class="path2"></span></i>
                            </button>
                            <span class="text-muted fs-8" id="mapa_pagina_label">—</span>
                            <button type="button" class="btn btn-sm btn-icon btn-light" id="btn_mapa_next" aria-label="Página siguiente">
                                <i class="ki-duotone ki-right fs-4"><span class="path1"></span><span class="path2"></span></i>
                            </button>
                        </div>
                    </div>
                    <div id="mapa_numeros" class="d-flex flex-wrap gap-2">
                        <span class="text-muted fs-7">Cargando números…</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Números / Bloqueos / Venta presencial (M03.3)-->

<!--begin::JavaScript-->
<script>
    // Ampliar miniatura de rifa en un modal
    document.addEventListener('click', function (event) {
        const thumb = event.target.closest('[data-rifa-thumb]');
        if (!thumb) {
            return;
        }

        const src = thumb.getAttribute('data-src') || thumb.getAttribute('src');
        const modalEl = document.getElementById('modal_imagen_rifa');
        const img = document.getElementById('imagen_rifa_ampliada');
        if (!src || !modalEl || !img) {
            return;
        }

        event.preventDefault();
        img.src = src;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const jq = window.jQuery || null;
    const tieneDataTables = !!(jq && jq.fn && jq.fn.DataTable);

    const modalRifaEl = document.getElementById('modal_rifa');
    const modalRifa = new bootstrap.Modal(modalRifaEl);

    // M03.3: números, bloqueos y venta presencial
    const modalNumerosEl = document.getElementById('modal_numeros_rifa');
    const modalNumeros = modalNumerosEl ? new bootstrap.Modal(modalNumerosEl) : null;
    const numerosInput = document.getElementById('numeros_input');
    const numerosMotivo = document.getElementById('numeros_motivo');
    const numerosComprador = document.getElementById('numeros_comprador');
    const alertNumeros = document.getElementById('alert_numeros_rifa');
    const tablaBloqueadosNumeros = document.getElementById('tabla_bloqueados_numeros');
    const mapaNumerosEl = document.getElementById('mapa_numeros');
    const mapaPaginaLabel = document.getElementById('mapa_pagina_label');
    const btnMapaPrev = document.getElementById('btn_mapa_prev');
    const btnMapaNext = document.getElementById('btn_mapa_next');
    const NUMEROS_PAGINA = 200;
    let numerosRifaId = 0;
    let numerosPagina = 0;
    let numerosTotal = 0;
    let numerosEnCurso = false;

    const formRifa = document.getElementById('form_rifa');
    const btnNuevaRifa = document.getElementById('btn_nueva_rifa');
    const btnGuardarRifa = document.getElementById('btn_guardar_rifa');
    const inputFiltro = document.getElementById('filtro_mis_rifas');
    const alertErroresRifa = document.getElementById('alert_errores_rifa');
    const listaErroresRifa = document.getElementById('lista_errores_rifa');
    const avisoBloqueada = document.getElementById('aviso_rifa_bloqueada');
    const maxPorCompraInput = document.getElementById('rifa_max_por_compra');

    const descripcionTextarea = document.getElementById('rifa_descripcion');

    const galeriaContadorEl = document.getElementById('galeria_contador');
    const galeriaModoCrearEl = document.getElementById('galeria_modo_crear');
    const galeriaModoEditarEl = document.getElementById('galeria_modo_editar');
    const galeriaPreviewEl = document.getElementById('galeria_preview');
    const galeriaPreviewVacioEl = document.getElementById('galeria_preview_vacio');
    const galeriaExistentesEl = document.getElementById('galeria_existentes');
    const galeriaAgregarWrapEl = document.getElementById('galeria_agregar_wrap');
    const galeriaAgregarAyudaEl = document.getElementById('galeria_agregar_ayuda');
    const galeriaLimiteMsgEl = document.getElementById('galeria_limite_msg');
    const galeriaEstadoEl = document.getElementById('galeria_estado');
    const avisoGaleriaBloqueadaEl = document.getElementById('aviso_galeria_bloqueada');
    const avisoGaleriaPortadaEl = document.getElementById('aviso_galeria_portada');
    const inputImagenesRifa = document.getElementById('rifa_imagenes');
    const inputPortadaIndex = document.getElementById('rifa_portada_index');
    const inputPortadaId = document.getElementById('rifa_portada_id');
    const inputAgregarImagenes = document.getElementById('galeria_agregar_input');

    const NUMERACION_FALLBACK = { tipo: '2', desde: 0, hasta: 99, padding: 2, total: 100 };

    const ESTADOS_BLOQUEADOS = {
        activa: true,
        pausada: true,
        pendiente_aprobacion: true,
        cancelacion_solicitada: true
    };

    const ESTADOS_BADGE = {
        borrador: ['secondary', 'Borrador'],
        pendiente_aprobacion: ['warning', 'Pendiente de aprobación'],
        activa: ['success', 'Activa'],
        pausada: ['warning', 'Pausada'],
        cancelacion_solicitada: ['danger', 'Cancelación solicitada'],
        finalizada: ['primary', 'Finalizada'],
        cancelada: ['danger', 'Cancelada'],
        rechazada: ['danger', 'Rechazada']
    };

    let dtMisRifas = null;
    let filtroTimer = null;
    let slugManual = false;
    let camposBloqueados = false;
    let accionEnCurso = false;

    const GALERIA_MAX = 3;
    const GALERIA_TIPOS = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const GALERIA_MAX_BYTES = 4 * 1024 * 1024;
    let galeriaRifaId = 0;
    let galeriaEditable = true;
    let galeriaImagenes = [];
    let galeriaPreviewUrls = [];
    let galeriaCargando = false;

    // ─────────────────────────────────────────────────────
    // Utilidades generales
    // ─────────────────────────────────────────────────────
    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function mostrar_alerta(tipo, msg) {
        const titulos = {
            success: '¡Éxito!',
            error: 'Error',
            warning: 'Atención',
            info: 'Información'
        };

        Swal.fire({
            icon: tipo,
            title: titulos[tipo] || 'Mensaje',
            text: msg,
            confirmButtonText: 'Aceptar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-primary'
            }
        });
    }

    function set_loading(btn, activo) {
        if (!btn) {
            return;
        }

        if (activo) {
            btn.setAttribute('data-kt-indicator', 'on');
            btn.disabled = true;
        } else {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            if (btn.id === 'btn_guardar_rifa') {
                actualizar_boton_guardar();
            }
        }
    }

    function request_json(url, options) {
        return fetch(url, options).then(function (response) {
            return response.json()
                .catch(function () { return {}; })
                .then(function (payload) {
                    return { status: response.status, data: payload || {} };
                });
        });
    }

    function redirigir_login() {
        window.location.href = LOGIN_EXPIRED;
    }

    function primer_error(errores) {
        if (!errores || typeof errores !== 'object') {
            return '';
        }

        const claves = Object.keys(errores);
        for (let i = 0; i < claves.length; i++) {
            const valor = errores[claves[i]];
            if (Array.isArray(valor) && valor.length) {
                return String(valor[0]);
            }
            if (valor) {
                return String(valor);
            }
        }

        return '';
    }

    function normalizar_slug(valor) {
        let texto = String(valor || '').toLowerCase();

        try {
            texto = texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        } catch (e) {
            texto = texto.replace(/[áàäâã]/g, 'a')
                .replace(/[éèëê]/g, 'e')
                .replace(/[íìïî]/g, 'i')
                .replace(/[óòöôõ]/g, 'o')
                .replace(/[úùüû]/g, 'u')
                .replace(/[ñ]/g, 'n');
        }

        return texto
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-+/g, '-')
            .slice(0, 120)
            .replace(/^-+|-+$/g, '');
    }

    function slug_valido(valor) {
        return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(String(valor || '')) && String(valor || '').length >= 3 && String(valor || '').length <= 120;
    }

    function pad_numero(valor, longitud) {
        const numero = parseInt(valor, 10);
        const largo = Math.max(1, Math.min(6, parseInt(longitud, 10) || 1));
        if (!isFinite(numero)) {
            return '';
        }
        return String(numero).padStart(largo, '0');
    }

    function formatear_fecha(valor, incluirHora) {
        if (!valor) {
            return '';
        }

        const texto = String(valor).trim();
        if (texto === '' || texto.toUpperCase() === 'NULL') {
            return '';
        }

        const normalizado = texto.indexOf('T') === -1 ? texto.replace(' ', 'T') : texto;
        const fecha = new Date(normalizado);

        if (isNaN(fecha.getTime())) {
            return texto;
        }

        return fecha.toLocaleString('es-CO', incluirHora
            ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
            : { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function fecha_para_input(valor) {
        const texto = String(valor || '').trim();
        if (texto === '' || texto.toUpperCase() === 'NULL') {
            return '';
        }
        return texto.replace(' ', 'T').slice(0, 16);
    }

    function fecha_para_backend(valor) {
        const texto = String(valor || '').trim();
        if (texto === '') {
            return '';
        }
        return texto.replace('T', ' ') + (texto.length === 16 ? ':00' : '');
    }

    function init_tooltips() {
        if (jq && jq.fn && jq.fn.tooltip) {
            jq('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
        }
    }

    function idioma_es(entidad) {
        return {
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span> Cargando ' + entidad + '...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ ' + entidad,
            infoEmpty: 'No hay ' + entidad + ' para mostrar',
            infoFiltered: '(filtrados de _MAX_ registros)',
            zeroRecords: 'No se encontraron ' + entidad,
            emptyTable: 'No hay ' + entidad + ' registradas',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        };
    }

    function mensaje_error_xhr(xhr, porDefecto) {
        if (xhr && xhr.status === 401) {
            return 'Tu sesión expiró. Inicia sesión nuevamente.';
        }
        if (xhr && xhr.status === 403) {
            return 'No tienes permiso para consultar este módulo.';
        }
        if (xhr && xhr.responseJSON) {
            if (xhr.responseJSON.msg) {
                return xhr.responseJSON.msg;
            }
            const detalle = primer_error(xhr.responseJSON.errores);
            if (detalle) {
                return detalle;
            }
        }
        return porDefecto;
    }

    function fila_data(dt, elemento) {
        if (!dt || !jq) {
            return {};
        }

        try {
            return dt.row(jq(elemento).closest('tr')).data() || {};
        } catch (e) {
            return {};
        }
    }

    function campo(d, claves, porDefecto) {
        if (!d || typeof d !== 'object') {
            return porDefecto;
        }

        for (let i = 0; i < claves.length; i++) {
            const clave = claves[i];
            if (d[clave] !== undefined && d[clave] !== null && d[clave] !== '') {
                return d[clave];
            }
        }

        return porDefecto;
    }

    // ─────────────────────────────────────────────────────
    // Descripción (textarea)
    // ─────────────────────────────────────────────────────
    function get_descripcion_html() {
        return descripcionTextarea ? descripcionTextarea.value : '';
    }

    function set_descripcion_html(html) {
        if (descripcionTextarea) {
            descripcionTextarea.value = String(html || '');
        }
    }

    // ─────────────────────────────────────────────────────
    // Galería de imágenes de la rifa (M03.2)
    // ─────────────────────────────────────────────────────
    function galeria_estado(msg) {
        if (galeriaEstadoEl) {
            galeriaEstadoEl.textContent = msg || '';
        }
    }

    function mostrar_aviso_portada_galeria(mostrar) {
        if (!avisoGaleriaPortadaEl) {
            return;
        }
        avisoGaleriaPortadaEl.classList.toggle('d-none', !mostrar);
        avisoGaleriaPortadaEl.classList.toggle('d-flex', !!mostrar);
    }

    function galeria_error_crear(msg) {
        const feedback = document.querySelector('[data-error-for="rifa_imagenes"]');
        if (feedback) {
            feedback.textContent = msg || '';
        }
        if (inputImagenesRifa) {
            inputImagenesRifa.classList.toggle('is-invalid', !!msg);
        }
    }

    function galeria_error_subir(msg) {
        const feedback = document.querySelector('[data-error-for="galeria_agregar_input"]');
        if (feedback) {
            feedback.textContent = msg || '';
        }
        if (inputAgregarImagenes) {
            inputAgregarImagenes.classList.toggle('is-invalid', !!msg);
        }
    }

    function actualizar_contador_galeria(total) {
        if (!galeriaContadorEl) {
            return;
        }

        galeriaContadorEl.textContent = total + ' / ' + GALERIA_MAX;
        galeriaContadorEl.classList.toggle('badge-light-warning', total >= GALERIA_MAX);
        galeriaContadorEl.classList.toggle('badge-light-primary', total < GALERIA_MAX);
    }

    function revocar_previews_galeria() {
        galeriaPreviewUrls.forEach(function (url) {
            try {
                URL.revokeObjectURL(url);
            } catch (e) {}
        });
        galeriaPreviewUrls = [];
    }

    function validar_archivos_galeria(files, disponibles) {
        if (!files.length) {
            return 'Selecciona al menos una imagen.';
        }

        if (files.length > disponibles) {
            return 'Solo puedes agregar ' + disponibles + ' imagen(es) más.';
        }

        for (let i = 0; i < files.length; i++) {
            const archivo = files[i];
            if (GALERIA_TIPOS.indexOf(String(archivo.type || '').toLowerCase()) === -1) {
                return 'Formato no permitido en "' + archivo.name + '". Usa JPG, PNG o WEBP.';
            }
            if (archivo.size > GALERIA_MAX_BYTES) {
                return 'La imagen "' + archivo.name + '" supera el máximo de 4 MB.';
            }
        }

        return '';
    }

    function normalizar_imagenes(lista) {
        if (!Array.isArray(lista)) {
            return [];
        }

        return lista.map(function (item) {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const id = parseInt(campo(item, ['img_id', 'id'], 0), 10) || 0;
            const url = String(campo(item, ['url', 'img_url', 'imagen_url'], ''));
            const principalRaw = campo(item, ['principal', 'img_principal', 'es_principal'], 0);
            const principal = principalRaw === true
                || principalRaw === 1
                || String(principalRaw) === '1';

            if (!id && !url) {
                return null;
            }

            return {
                id: id,
                url: url,
                principal: principal
            };
        }).filter(function (item) {
            return item !== null;
        });
    }

    function ordenar_imagenes(lista) {
        return lista.slice().sort(function (a, b) {
            if (a.principal !== b.principal) {
                return a.principal ? -1 : 1;
            }
            return a.id - b.id;
        });
    }

    function extraer_imagenes_payload(payload) {
        if (!payload) {
            return [];
        }
        if (Array.isArray(payload)) {
            return payload;
        }
        if (Array.isArray(payload.imagenes)) {
            return payload.imagenes;
        }
        if (payload.data) {
            if (Array.isArray(payload.data)) {
                return payload.data;
            }
            if (Array.isArray(payload.data.imagenes)) {
                return payload.data.imagenes;
            }
        }
        return [];
    }

    function render_galeria_crear() {
        revocar_previews_galeria();

        if (!galeriaPreviewEl) {
            return;
        }

        galeriaPreviewEl.innerHTML = '';
        const files = (inputImagenesRifa && inputImagenesRifa.files)
            ? Array.prototype.slice.call(inputImagenesRifa.files)
            : [];

        if (!files.length) {
            if (galeriaPreviewVacioEl) {
                galeriaPreviewEl.appendChild(galeriaPreviewVacioEl);
                galeriaPreviewVacioEl.classList.remove('d-none');
            }
            if (inputPortadaIndex) {
                inputPortadaIndex.value = '0';
            }
            actualizar_contador_galeria(0);
            mostrar_aviso_portada_galeria(galeriaEditable);
            return;
        }

        mostrar_aviso_portada_galeria(false);

        let portada = parseInt(inputPortadaIndex && inputPortadaIndex.value, 10);
        if (!isFinite(portada) || portada < 0 || portada >= files.length) {
            portada = 0;
        }

        files.forEach(function (archivo, index) {
            const objectUrl = URL.createObjectURL(archivo);
            galeriaPreviewUrls.push(objectUrl);

            const card = document.createElement('div');
            card.className = 'col-6 col-md-4';
            card.innerHTML =
                '<div class="border border-gray-200 rounded-3 p-2 h-100 d-flex flex-column">' +
                    '<div class="ratio ratio-4x3">' +
                        '<img src="' + escapeHtml(objectUrl) + '" alt="' + escapeHtml(archivo.name) + '" ' +
                            'class="w-100 h-100 object-fit-cover rounded" data-rifa-thumb="1" ' +
                            'data-src="' + escapeHtml(objectUrl) + '" title="Ampliar imagen" />' +
                    '</div>' +
                    '<div class="d-flex align-items-center justify-content-between gap-2 mt-2">' +
                        '<label class="form-check form-check-custom form-check-solid mb-0">' +
                            '<input class="form-check-input" type="radio" name="galeria_portada_radio" value="' + index + '"' +
                                (index === portada ? ' checked' : '') + ' />' +
                            '<span class="form-check-label fs-8 text-gray-800">Portada</span>' +
                        '</label>' +
                        '<span class="badge badge-light-secondary fw-bold">' + (index + 1) + '</span>' +
                    '</div>' +
                '</div>';

            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                radio.addEventListener('change', function () {
                    if (this.checked && inputPortadaIndex) {
                        inputPortadaIndex.value = this.value;
                    }
                });
            }

            galeriaPreviewEl.appendChild(card);
        });

        if (inputPortadaIndex) {
            inputPortadaIndex.value = String(portada);
        }
        actualizar_contador_galeria(files.length);
    }

    function render_galeria_editar() {
        if (!galeriaExistentesEl) {
            return;
        }

        galeriaExistentesEl.innerHTML = '';
        const lista = ordenar_imagenes(galeriaImagenes);
        const restantes = Math.max(0, GALERIA_MAX - lista.length);

        if (!lista.length) {
            galeriaExistentesEl.innerHTML =
                '<div class="col-12">' +
                    '<div class="border border-gray-200 border-dashed rounded-3 p-6 text-center text-muted fs-7">' +
                        'Aún no hay imágenes en la galería.' +
                    '</div>' +
                '</div>';
        } else {
            lista.forEach(function (imagen) {
                const principal = !!imagen.principal;
                const urlSeguro = escapeHtml(imagen.url || '');
                const vista = urlSeguro
                    ? '<div class="ratio ratio-4x3">' +
                        '<img src="' + urlSeguro + '" alt="Imagen de la rifa" ' +
                            'class="w-100 h-100 object-fit-cover rounded" data-rifa-thumb="1" ' +
                            'data-src="' + urlSeguro + '" role="button" tabindex="0" title="Ampliar imagen" />' +
                      '</div>'
                    : '<div class="ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted fs-8">' +
                        'Sin vista previa' +
                      '</div>';

                const acciones = (galeriaEditable && imagen.id)
                    ? '<div class="d-flex flex-wrap justify-content-end gap-2 mt-2">' +
                        '<button type="button" class="btn btn-sm btn-light-primary fw-semibold"' +
                            ' data-galeria-portada="' + imagen.id + '"' +
                            (principal ? ' disabled' : '') +
                            ' title="Usar como portada" aria-label="Usar como portada">' +
                            '<i class="ki-duotone ki-star fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>' +
                            'Portada' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-light-danger fw-semibold"' +
                            ' data-galeria-eliminar="' + imagen.id + '"' +
                            ' title="Eliminar imagen" aria-label="Eliminar imagen">' +
                            '<i class="ki-duotone ki-trash fs-4 me-1">' +
                                '<span class="path1"></span><span class="path2"></span><span class="path3"></span>' +
                                '<span class="path4"></span><span class="path5"></span>' +
                            '</i>' +
                            'Eliminar' +
                        '</button>' +
                      '</div>'
                    : '';

                const card = document.createElement('div');
                card.className = 'col-6 col-md-4';
                card.innerHTML =
                    '<div class="border ' + (principal ? 'border-2 border-primary' : 'border-gray-200') + ' rounded-3 p-2 h-100 d-flex flex-column">' +
                        vista +
                        '<div class="d-flex align-items-center justify-content-between gap-2 mt-2">' +
                            '<span class="badge ' + (principal ? 'badge-light-primary' : 'badge-light-secondary') + ' fw-bold">' +
                                (principal ? 'Portada' : 'Adicional') +
                            '</span>' +
                            (principal ? '<span class="text-muted fs-8">Principal</span>' : '') +
                        '</div>' +
                        acciones +
                    '</div>';
                galeriaExistentesEl.appendChild(card);
            });
        }

        actualizar_contador_galeria(lista.length);

        const principalImg = lista.filter(function (item) { return item.principal; })[0] || null;
        if (inputPortadaId) {
            inputPortadaId.value = principalImg ? String(principalImg.id) : '0';
        }
        mostrar_aviso_portada_galeria(galeriaEditable && !principalImg);

        if (galeriaAgregarWrapEl) {
            galeriaAgregarWrapEl.classList.toggle('d-none', !galeriaEditable || restantes <= 0);
        }
        if (galeriaLimiteMsgEl) {
            galeriaLimiteMsgEl.classList.toggle('d-none', !galeriaEditable || restantes > 0);
        }
        if (galeriaAgregarAyudaEl) {
            galeriaAgregarAyudaEl.textContent = restantes > 0
                ? ('Puedes agregar hasta ' + restantes + ' imagen(es) más. Formatos JPG, PNG o WEBP, máximo 4 MB c/u.')
                : 'Límite alcanzado.';
        }
        if (inputAgregarImagenes) {
            inputAgregarImagenes.disabled = !galeriaEditable || restantes <= 0;
            if (inputAgregarImagenes.disabled) {
                inputAgregarImagenes.value = '';
            }
        }
    }

    function aplicar_estado_galeria(editable) {
        galeriaEditable = !!editable;

        if (avisoGaleriaBloqueadaEl) {
            avisoGaleriaBloqueadaEl.classList.toggle('d-none', galeriaEditable);
            avisoGaleriaBloqueadaEl.classList.toggle('d-flex', !galeriaEditable);
        }
        if (inputImagenesRifa) {
            inputImagenesRifa.disabled = !galeriaEditable;
        }

        render_galeria_editar();
    }

    function configurar_modo_galeria(modo) {
        const esCrear = (modo === 'crear');

        if (galeriaModoCrearEl) {
            galeriaModoCrearEl.classList.toggle('d-none', !esCrear);
        }
        if (galeriaModoEditarEl) {
            galeriaModoEditarEl.classList.toggle('d-none', esCrear);
        }
        if (inputPortadaIndex) {
            inputPortadaIndex.disabled = !esCrear;
            inputPortadaIndex.value = '0';
        }
        if (inputPortadaId) {
            inputPortadaId.disabled = esCrear;
            inputPortadaId.value = '0';
        }
        if (inputImagenesRifa) {
            inputImagenesRifa.value = '';
        }
        if (inputAgregarImagenes) {
            inputAgregarImagenes.value = '';
        }

        galeria_error_crear('');
        galeria_error_subir('');
    }

    function limpiar_galeria() {
        galeriaRifaId = 0;
        galeriaImagenes = [];
        galeriaCargando = false;

        revocar_previews_galeria();
        if (galeriaPreviewEl) {
            galeriaPreviewEl.innerHTML = '';
        }
        if (galeriaExistentesEl) {
            galeriaExistentesEl.innerHTML = '';
        }
        if (inputImagenesRifa) {
            inputImagenesRifa.value = '';
        }
        if (inputAgregarImagenes) {
            inputAgregarImagenes.value = '';
        }

        configurar_modo_galeria('crear');
        aplicar_estado_galeria(true);
        render_galeria_crear();
        galeria_estado('');
    }

    function cargar_galeria(rifaId, fallback) {
        galeriaRifaId = parseInt(rifaId, 10) || 0;

        if (!galeriaRifaId) {
            galeriaImagenes = normalizar_imagenes(fallback || []);
            render_galeria_editar();
            return Promise.resolve();
        }

        return request_json(BASE_URL + 'imagenes/' + encodeURIComponent(galeriaRifaId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para consultar la galería.');
                return;
            }

            let lista = extraer_imagenes_payload(res.data);
            if (!lista.length && Array.isArray(fallback)) {
                lista = fallback;
            }

            galeriaImagenes = normalizar_imagenes(lista);
            render_galeria_editar();
        }).catch(function () {
            galeriaImagenes = normalizar_imagenes(fallback || []);
            render_galeria_editar();
        });
    }

    function ejecutar_accion_galeria(ruta, imgId, mensajeExito) {
        if (!galeriaRifaId || galeriaCargando) {
            return;
        }

        galeriaCargando = true;
        galeria_estado('Guardando cambios en la galería...');

        request_json(BASE_URL + ruta + '/' + encodeURIComponent(galeriaRifaId) + '/' + encodeURIComponent(imgId), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            galeriaCargando = false;
            galeria_estado('');

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para modificar la galería.');
                return;
            }

            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || mensajeExito);
                if (dtMisRifas) {
                    dtMisRifas.ajax.reload(null, false);
                }
                cargar_galeria(galeriaRifaId, galeriaImagenes);
                return;
            }

            const detalle = primer_error(res.data && res.data.errores);
            mostrar_alerta('error', detalle || (res.data && res.data.msg) || 'No se pudo completar la acción.');
        }).catch(function () {
            galeriaCargando = false;
            galeria_estado('');
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    function confirmar_eliminar_imagen(imgId) {
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la imagen?',
            text: 'La imagen se quitará de la galería de la rifa.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                ejecutar_accion_galeria('imagen-eliminar', imgId, 'Imagen eliminada correctamente.');
            }
        });
    }

    function subir_imagenes_galeria(files) {
        if (!galeriaRifaId || galeriaCargando || !galeriaEditable) {
            return;
        }

        const restantes = Math.max(0, GALERIA_MAX - galeriaImagenes.length);
        const error = validar_archivos_galeria(files, restantes);
        if (error) {
            galeria_error_subir(error);
            if (inputAgregarImagenes) {
                inputAgregarImagenes.value = '';
            }
            return;
        }

        galeria_error_subir('');
        galeriaCargando = true;
        galeria_estado('Subiendo imágenes...');

        const fd = new FormData();
        files.forEach(function (archivo) {
            fd.append('rifa_imagenes[]', archivo);
        });

        if (inputAgregarImagenes) {
            inputAgregarImagenes.disabled = true;
        }

        request_json(BASE_URL + 'imagen-subir/' + encodeURIComponent(galeriaRifaId), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            galeriaCargando = false;
            galeria_estado('');

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para subir imágenes.');
                render_galeria_editar();
                return;
            }

            if (res.data && res.data.ok) {
                if (inputAgregarImagenes) {
                    inputAgregarImagenes.value = '';
                }
                mostrar_alerta('success', res.data.msg || 'Imágenes agregadas correctamente.');
                if (dtMisRifas) {
                    dtMisRifas.ajax.reload(null, false);
                }
                cargar_galeria(galeriaRifaId, galeriaImagenes);
                return;
            }

            const detalle = primer_error(res.data && res.data.errores);
            mostrar_alerta('error', detalle || (res.data && res.data.msg) || 'No se pudieron subir las imágenes.');
            render_galeria_editar();
        }).catch(function () {
            galeriaCargando = false;
            galeria_estado('');
            mostrar_alerta('error', 'Error de conexión con el servidor.');
            render_galeria_editar();
        });
    }

    if (inputImagenesRifa) {
        inputImagenesRifa.addEventListener('change', function () {
            galeria_error_crear('');
            const files = Array.prototype.slice.call(this.files || []);

            if (!files.length) {
                render_galeria_crear();
                return;
            }

            const error = validar_archivos_galeria(files, GALERIA_MAX);
            if (error) {
                galeria_error_crear(error);
                this.value = '';
                render_galeria_crear();
                return;
            }

            render_galeria_crear();
        });
    }

    if (inputAgregarImagenes) {
        inputAgregarImagenes.addEventListener('change', function () {
            galeria_error_subir('');
            const files = Array.prototype.slice.call(this.files || []);

            if (!files.length) {
                return;
            }

            if (files.length > Math.max(0, GALERIA_MAX - galeriaImagenes.length)) {
                galeria_error_subir('Solo puedes agregar ' + Math.max(0, GALERIA_MAX - galeriaImagenes.length) + ' imagen(es) más.');
                this.value = '';
                return;
            }

            subir_imagenes_galeria(files);
        });
    }

    if (galeriaExistentesEl) {
        galeriaExistentesEl.addEventListener('click', function (event) {
            const btnPortada = event.target.closest('[data-galeria-portada]');
            if (btnPortada) {
                if (btnPortada.disabled) {
                    return;
                }
                ejecutar_accion_galeria('imagen-portada', btnPortada.getAttribute('data-galeria-portada'), 'Portada actualizada correctamente.');
                return;
            }

            const btnEliminar = event.target.closest('[data-galeria-eliminar]');
            if (btnEliminar) {
                confirmar_eliminar_imagen(btnEliminar.getAttribute('data-galeria-eliminar'));
            }
        });
    }

    // ─────────────────────────────────────────────────────
    // Errores del formulario
    // ─────────────────────────────────────────────────────
    function limpiar_errores(form, alertEl, listaEl) {
        if (!form) {
            return;
        }

        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
        });
        if (listaEl) {
            listaEl.innerHTML = '';
        }
        if (alertEl) {
            alertEl.classList.add('d-none');
        }
    }

    function mostrar_errores(form, alertEl, listaEl, errores) {
        limpiar_errores(form, alertEl, listaEl);

        if (!errores || typeof errores !== 'object') {
            return;
        }

        const alias = {
            titulo: 'rifa_titulo',
            slug: 'rifa_slug',
            descripcion: 'rifa_descripcion',
            condiciones: 'rifa_condiciones',
            tipo: 'rifa_tipo',
            precio: 'rifa_precio',
            moneda: 'rifa_moneda',
            asignacion: 'rifa_asig_id',
            asig_id: 'rifa_asig_id',
            lote: 'rifa_asig_id',
            num_id: 'rifa_num_id',
            numeracion_id: 'rifa_num_id',
            numeracion: 'rifa_numeracion_tipo',
            numeracion_tipo: 'rifa_numeracion_tipo',
            numero_desde: 'rifa_numero_desde',
            numero_hasta: 'rifa_numero_hasta',
            padding: 'rifa_padding',
            total_numeros: 'rifa_total_numeros',
            max_por_compra: 'rifa_max_por_compra',
            reserva_ttl_min: 'rifa_reserva_ttl_min',
            ttl: 'rifa_reserva_ttl_min',
            reserva_auto: 'rifa_reserva_auto',
            fecha_sorteo: 'rifa_fecha_sorteo',
            sorteo: 'rifa_fecha_sorteo',
            imagenes: 'rifa_imagenes[]',
            rifa_imagenes: 'rifa_imagenes[]',
            portada: 'rifa_imagenes[]',
            portada_index: 'rifa_imagenes[]',
            portada_id: 'rifa_imagenes[]'
        };

        let total = 0;
        Object.keys(errores).forEach(function (clave) {
            let mensaje = errores[clave];
            if (Array.isArray(mensaje)) {
                mensaje = mensaje.length ? mensaje[0] : '';
            }
            if (!mensaje) {
                return;
            }

            const destino = alias[clave] || clave;
            const selector = String(destino).replace(/"/g, '\\"');
            let input = form.querySelector('[name="' + selector + '"]');
            if (!input && selector.indexOf('[]') === -1) {
                input = form.querySelector('[name="' + selector + '[]"]');
            }
            const feedback = form.querySelector('[data-error-for="' + selector.replace(/\[\]/g, '') + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = String(mensaje);
            }

            if (listaEl) {
                const li = document.createElement('li');
                li.textContent = String(mensaje);
                listaEl.appendChild(li);
            }
            total++;
        });

        if (total > 0 && alertEl) {
            alertEl.classList.remove('d-none');
        }
    }

    // ─────────────────────────────────────────────────────
    // Tipo, lote y numeración
    // ─────────────────────────────────────────────────────
    function es_rifa_gratuita_form() {
        const selectTipo = document.getElementById('rifa_tipo');
        return !!selectTipo && selectTipo.value === 'gratuita';
    }

    function lote_option() {
        const selectLote = document.getElementById('rifa_asig_id');
        if (!selectLote || selectLote.selectedIndex < 0) {
            return null;
        }

        const option = selectLote.options[selectLote.selectedIndex];
        if (!option || String(option.value) === '0') {
            return null;
        }

        return option;
    }

    function lote_datos(option) {
        if (!option) {
            return null;
        }

        const ds = option.dataset || {};
        const tipo = String(ds.tipo || 'limitado').toLowerCase();

        return {
            id: parseInt(option.value, 10) || 0,
            tipo: tipo,
            ilimitado: tipo === 'ilimitado',
            numId: parseInt(ds.numId, 10) || 0,
            numLabel: String(ds.numLabel || ''),
            cantidad: parseInt(ds.cantidad, 10),
            restantes: parseInt(ds.restantes, 10),
            desde: parseInt(ds.numDesde, 10),
            hasta: parseInt(ds.numHasta, 10),
            padding: parseInt(ds.numPadding, 10)
        };
    }

    function num_option() {
        const selectNum = document.getElementById('rifa_num_id');
        if (!selectNum || selectNum.selectedIndex < 0) {
            return null;
        }

        const option = selectNum.options[selectNum.selectedIndex];
        if (!option || parseInt(option.value, 10) <= 0) {
            return null;
        }

        return option;
    }

    function num_datos(option) {
        if (!option) {
            return null;
        }

        const ds = option.dataset || {};
        const desde = parseInt(ds.desde, 10);
        const hasta = parseInt(ds.hasta, 10);
        const padding = parseInt(ds.padding, 10);
        const cantidad = parseInt(ds.cantidad, 10);

        return {
            id: parseInt(option.value, 10) || 0,
            label: String(option.textContent || '').trim(),
            cantidad: isFinite(cantidad) && cantidad > 0
                ? cantidad
                : ((isFinite(desde) && isFinite(hasta) && hasta >= desde) ? (hasta - desde + 1) : 0),
            desde: isFinite(desde) ? desde : 0,
            hasta: isFinite(hasta) ? hasta : 0,
            padding: isFinite(padding) ? Math.max(1, Math.min(6, padding)) : 2
        };
    }

    function preset_numeracion(desde, hasta, padding) {
        if (desde === 0 && hasta === 99 && padding === 2) {
            return '2';
        }
        if (desde === 0 && hasta === 999 && padding === 3) {
            return '3';
        }
        if (desde === 0 && hasta === 9999 && padding === 4) {
            return '4';
        }
        return 'custom';
    }

    function set_hidden_numeracion(desde, hasta, padding, total) {
        const tipo = preset_numeracion(desde, hasta, padding);
        const segPadding = Math.max(1, Math.min(6, parseInt(padding, 10) || 2));
        const segTotal = (parseInt(total, 10) > 0)
            ? parseInt(total, 10)
            : ((isFinite(desde) && isFinite(hasta) && hasta >= desde) ? (hasta - desde + 1) : 0);

        document.getElementById('rifa_numeracion_tipo').value = tipo;
        document.getElementById('rifa_numero_desde').value = String(isFinite(desde) ? desde : 0);
        document.getElementById('rifa_numero_hasta').value = String(isFinite(hasta) ? hasta : 0);
        document.getElementById('rifa_padding').value = String(segPadding);
        document.getElementById('rifa_total_numeros').value = String(segTotal);

        if (maxPorCompraInput) {
            if (segTotal > 0) {
                maxPorCompraInput.max = String(segTotal);

                const actual = parseInt(maxPorCompraInput.value, 10);
                if (!isFinite(actual) || actual < 1) {
                    maxPorCompraInput.value = String(Math.min(10, segTotal));
                } else if (actual > segTotal) {
                    maxPorCompraInput.value = String(segTotal);
                }
            } else {
                maxPorCompraInput.removeAttribute('max');
            }
        }
    }

    function actualizar_readonly(label, desde, hasta, padding, total) {
        const labelEl = document.getElementById('rifa_num_readonly_label');
        const rangoEl = document.getElementById('rifa_num_readonly_rango');

        if (labelEl) {
            labelEl.textContent = label || 'Sin numeración';
        }

        if (!rangoEl) {
            return;
        }

        if (isFinite(desde) && isFinite(hasta) && hasta >= desde) {
            const partes = [pad_numero(desde, padding) + '–' + pad_numero(hasta, padding)];
            if (total > 0) {
                partes.push(total + ' números');
            }
            rangoEl.textContent = partes.join(' · ');
        } else {
            rangoEl.textContent = '—';
        }
    }

    function actualizar_preview(label, desde, hasta, padding, total) {
        const resumen = document.getElementById('rifa_numeracion_resumen');
        if (!resumen) {
            return;
        }

        const partes = [];
        if (label) {
            partes.push(label);
        }
        if (isFinite(desde) && isFinite(hasta) && hasta >= desde) {
            partes.push(pad_numero(desde, padding) + '–' + pad_numero(hasta, padding));
        }
        if (total > 0) {
            partes.push(total + ' números');
        }

        resumen.textContent = partes.length ? partes.join(' · ') : '—';
    }

    function seleccionar_lote_inicial() {
        const selectLote = document.getElementById('rifa_asig_id');
        if (!selectLote) {
            return false;
        }

        for (let i = 0; i < selectLote.options.length; i++) {
            const option = selectLote.options[i];
            if (!option || String(option.value) === '0') {
                continue;
            }

            const datos = lote_datos(option);
            if (!datos) {
                continue;
            }
            if (datos.ilimitado || !isFinite(datos.restantes) || datos.restantes > 0) {
                option.selected = true;
                return true;
            }
        }

        return false;
    }

    function seleccionar_lote(id, permitir_agotado) {
        const selectLote = document.getElementById('rifa_asig_id');
        const objetivo = parseInt(id, 10) || 0;
        if (!selectLote || objetivo <= 0) {
            return false;
        }

        for (let i = 0; i < selectLote.options.length; i++) {
            const option = selectLote.options[i];
            if (!option || parseInt(option.value, 10) !== objetivo) {
                continue;
            }

            if (option.disabled && permitir_agotado) {
                option.disabled = false;
            }
            if (option.disabled) {
                return false;
            }

            option.selected = true;
            return true;
        }

        return false;
    }

    function seleccionar_numeracion(id) {
        const selectNum = document.getElementById('rifa_num_id');
        const objetivo = parseInt(id, 10) || 0;
        if (!selectNum || objetivo <= 0) {
            return false;
        }

        for (let i = 0; i < selectNum.options.length; i++) {
            if (parseInt(selectNum.options[i].value, 10) === objetivo) {
                selectNum.selectedIndex = i;
                return true;
            }
        }

        return false;
    }

    /*
     * Fallback de edición: si el lote de la rifa ya no aparece entre los lotes
     * disponibles (por ejemplo, venció después de publicarse), se agrega una
     * opción de solo lectura con los datos que devolvió el backend para no
     * perder la preselección ni bloquear la edición de los campos permitidos.
     */
    function asegurar_lote_actual(d, asigId, numId) {
        const selectLote = document.getElementById('rifa_asig_id');
        const objetivo = parseInt(asigId, 10) || 0;
        if (!selectLote || objetivo <= 0) {
            return false;
        }

        for (let i = 0; i < selectLote.options.length; i++) {
            if (parseInt(selectLote.options[i].value, 10) === objetivo) {
                return true;
            }
        }

        const planNombre = String(campo(d, ['asig_plan_nombre', 'plan_nombre'], 'Lote actual'));
        const numLabel = String(campo(d, ['num_label', 'asig_num_label'], 'Sin numeración'));
        const cantidadRaw = campo(d, ['asig_cantidad'], '');
        const desde = parseInt(campo(d, ['rifa_numero_desde', 'numero_desde'], 0), 10) || 0;
        const hasta = parseInt(campo(d, ['rifa_numero_hasta', 'numero_hasta'], 0), 10) || 0;
        const padding = parseInt(campo(d, ['rifa_padding', 'padding'], 2), 10) || 2;

        const option = document.createElement('option');
        option.value = String(objetivo);
        option.dataset.tipo = 'limitado';
        option.dataset.numId = String(numId || 0);
        option.dataset.numLabel = numLabel;
        option.dataset.cantidad = (cantidadRaw === '' || cantidadRaw === null)
            ? ''
            : String(parseInt(cantidadRaw, 10) || 0);
        option.dataset.restantes = '1';
        option.dataset.numDesde = String(desde);
        option.dataset.numHasta = String(hasta);
        option.dataset.numPadding = String(padding);
        option.textContent = planNombre + ' — ' + numLabel + ' — lote actual';
        selectLote.appendChild(option);
        option.selected = true;

        return true;
    }

    function actualizar_boton_guardar() {
        if (!btnGuardarRifa) {
            return;
        }

        const cargando = btnGuardarRifa.getAttribute('data-kt-indicator') === 'on';
        let bloquear = false;

        if (!es_rifa_gratuita_form()) {
            const lote = lote_datos(lote_option());
            if (!lote) {
                bloquear = true;
            } else if (lote.ilimitado && !num_datos(num_option())) {
                bloquear = true;
            }
        }

        btnGuardarRifa.disabled = cargando || bloquear;

        if (bloquear) {
            btnGuardarRifa.setAttribute('title', 'Selecciona un lote válido para guardar la rifa paga.');
        } else {
            btnGuardarRifa.removeAttribute('title');
        }
    }

    function actualizar_estado_lote() {
        const wrapPlan = document.getElementById('wrap_rifa_plan_num');
        const avisoGratis = document.getElementById('aviso_rifa_gratuita');
        const avisoSinLotes = document.getElementById('aviso_rifa_sin_lotes');
        const wrapNumSelect = document.getElementById('wrap_rifa_num_select');
        const wrapNumReadonly = document.getElementById('wrap_rifa_num_readonly');
        const badge = document.getElementById('rifa_plan_badge');
        const selectLote = document.getElementById('rifa_asig_id');
        const selectNum = document.getElementById('rifa_num_id');
        const esGratuita = es_rifa_gratuita_form();

        if (wrapPlan) {
            wrapPlan.classList.toggle('d-none', esGratuita);
        }
        if (avisoGratis) {
            avisoGratis.classList.toggle('d-none', !esGratuita);
        }

        if (esGratuita) {
            if (selectLote) {
                selectLote.value = '0';
            }
            if (selectNum) {
                selectNum.value = '0';
            }
            if (badge) {
                badge.textContent = 'Gratuita';
            }
            actualizar_preview('', 0, 0, 2, 0);
            actualizar_boton_guardar();
            return;
        }

        const lote = lote_datos(lote_option());
        let hayDisponibles = false;

        if (selectLote) {
            for (let i = 0; i < selectLote.options.length; i++) {
                const option = selectLote.options[i];
                if (!option || String(option.value) === '0') {
                    continue;
                }

                const datos = lote_datos(option);
                if (datos && (datos.ilimitado || !isFinite(datos.restantes) || datos.restantes > 0)) {
                    hayDisponibles = true;
                    break;
                }
            }
        }

        if (avisoSinLotes) {
            const mostrarAviso = (lote === null && !hayDisponibles);
            avisoSinLotes.classList.toggle('d-none', !mostrarAviso);
            avisoSinLotes.classList.toggle('d-flex', mostrarAviso);
        }

        if (!lote) {
            if (wrapNumSelect) {
                wrapNumSelect.classList.add('d-none');
            }
            if (wrapNumReadonly) {
                wrapNumReadonly.classList.add('d-none');
            }
            if (badge) {
                badge.textContent = 'Sin lote';
            }
            actualizar_preview('', 0, 0, 2, 0);
            actualizar_boton_guardar();
            return;
        }

        if (badge) {
            badge.textContent = lote.ilimitado ? 'Lote ilimitado' : 'Lote limitado';
        }

        if (lote.ilimitado) {
            if (wrapNumSelect) {
                wrapNumSelect.classList.remove('d-none');
            }
            if (wrapNumReadonly) {
                wrapNumReadonly.classList.add('d-none');
            }

            if (selectNum) {
                selectNum.disabled = camposBloqueados;
                if (parseInt(selectNum.value, 10) <= 0) {
                    for (let i = 0; i < selectNum.options.length; i++) {
                        if (parseInt(selectNum.options[i].value, 10) > 0) {
                            selectNum.selectedIndex = i;
                            break;
                        }
                    }
                }
            }

            const num = num_datos(num_option());
            if (num) {
                set_hidden_numeracion(num.desde, num.hasta, num.padding, num.cantidad);
                actualizar_preview(num.label, num.desde, num.hasta, num.padding, num.cantidad);
            } else {
                actualizar_preview('', 0, 0, 2, 0);
            }
        } else {
            if (wrapNumSelect) {
                wrapNumSelect.classList.add('d-none');
            }
            if (wrapNumReadonly) {
                wrapNumReadonly.classList.remove('d-none');
            }
            if (selectNum) {
                selectNum.value = String(lote.numId || 0);
            }

            const desde = isFinite(lote.desde) ? lote.desde : 0;
            const hasta = isFinite(lote.hasta) ? lote.hasta : 0;
            const padding = isFinite(lote.padding) ? lote.padding : 2;
            // En un lote limitado, asig_cantidad es el número de rifas del
            // lote; el total de números de esta rifa lo define su numeración.
            const total = (isFinite(desde) && isFinite(hasta) && hasta >= desde)
                ? (hasta - desde + 1)
                : 0;

            actualizar_readonly(lote.numLabel, desde, hasta, padding, total);
            set_hidden_numeracion(desde, hasta, padding, total);
            actualizar_preview(lote.numLabel, desde, hasta, padding, total);
        }

        actualizar_boton_guardar();
    }

    function aplicar_tipo_rifa() {
        const selectTipo = document.getElementById('rifa_tipo');
        const wrapPrecio = document.getElementById('wrap_rifa_precio');
        const inputPrecio = document.getElementById('rifa_precio');
        const selectMoneda = document.getElementById('rifa_moneda');
        const esGratuita = selectTipo && selectTipo.value === 'gratuita';
        const wrapMetodosPago = document.getElementById('wrap_rifa_metodos');

        // El precio del ticket es independiente del tipo de publicación:
        // una rifa gratuita puede recaudar fondos y debe poder cobrar.
        if (wrapMetodosPago) {
            wrapMetodosPago.classList.remove('d-none');
        }

        if (wrapPrecio && inputPrecio && selectMoneda) {
            wrapPrecio.classList.remove('d-none');
            inputPrecio.required = !camposBloqueados;
            inputPrecio.disabled = camposBloqueados;
            selectMoneda.disabled = camposBloqueados;
            if (!inputPrecio.value || parseFloat(inputPrecio.value) < 0) {
                inputPrecio.value = '0.00';
            }
        }

        if (!esGratuita) {
            const selectLote = document.getElementById('rifa_asig_id');
            if (selectLote && (parseInt(selectLote.value, 10) || 0) <= 0) {
                seleccionar_lote_inicial();
            }
        }

        actualizar_estado_lote();
    }

    const selectTipoRifa = document.getElementById('rifa_tipo');
    if (selectTipoRifa) {
        selectTipoRifa.addEventListener('change', aplicar_tipo_rifa);
    }

    const selectLoteRifa = document.getElementById('rifa_asig_id');
    if (selectLoteRifa) {
        selectLoteRifa.addEventListener('change', actualizar_estado_lote);
    }

    const selectNumRifa = document.getElementById('rifa_num_id');
    if (selectNumRifa) {
        selectNumRifa.addEventListener('change', actualizar_estado_lote);
    }

    // ─────────────────────────────────────────────────────
    // Bloqueo de campos según estado
    // ─────────────────────────────────────────────────────
    function habilitar_campos_editables(habilitado) {
        camposBloqueados = !habilitado;

        const ids = [
            'rifa_slug',
            'rifa_tipo',
            'rifa_asig_id',
            'rifa_num_id',
            'rifa_numeracion_tipo',
            'rifa_numero_desde',
            'rifa_numero_hasta',
            'rifa_padding',
            'rifa_total_numeros',
            'rifa_max_por_compra',
            'rifa_reserva_ttl_min',
            'rifa_reserva_auto'
        ];

        ids.forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.disabled = !habilitado;
            }
        });

        if (avisoBloqueada) {
            avisoBloqueada.classList.toggle('d-none', habilitado);
            avisoBloqueada.classList.toggle('d-flex', !habilitado);
        }

        aplicar_tipo_rifa();
    }

    // ─────────────────────────────────────────────────────
    // Título -> slug automático
    // ─────────────────────────────────────────────────────
    const inputTitulo = document.getElementById('rifa_titulo');
    const inputSlug = document.getElementById('rifa_slug');

    if (inputTitulo) {
        inputTitulo.addEventListener('input', function () {
            if (slugManual || !inputSlug || inputSlug.disabled) {
                return;
            }
            inputSlug.value = normalizar_slug(this.value);
        });
    }

    if (inputSlug) {
        inputSlug.addEventListener('input', function () {
            const normalizado = normalizar_slug(this.value);
            if (this.value !== normalizado) {
                const posicion = this.selectionStart;
                this.value = normalizado;
                try {
                    this.setSelectionRange(Math.min(posicion, normalizado.length), Math.min(posicion, normalizado.length));
                } catch (e) {}
            }

            slugManual = this.value !== '';
        });
    }

    // ─────────────────────────────────────────────────────
    // Modal: limpiar y cargar
    // ─────────────────────────────────────────────────────
    function limpiar_modal_rifa() {
        formRifa.reset();

        document.getElementById('rifa_id').value = '0';
        document.getElementById('modal_rifa_titulo').textContent = 'Nueva Rifa';
        document.getElementById('rifa_tipo').value = 'paga';
        document.getElementById('rifa_moneda').value = 'USD';
        document.getElementById('rifa_numeracion_tipo').value = NUMERACION_FALLBACK.tipo;
        document.getElementById('rifa_numero_desde').value = String(NUMERACION_FALLBACK.desde);
        document.getElementById('rifa_numero_hasta').value = String(NUMERACION_FALLBACK.hasta);
        document.getElementById('rifa_padding').value = String(NUMERACION_FALLBACK.padding);
        document.getElementById('rifa_total_numeros').value = String(NUMERACION_FALLBACK.total);
        document.getElementById('rifa_asig_id').value = '0';
        document.getElementById('rifa_num_id').value = '0';
        document.getElementById('rifa_max_por_compra').value = '10';
        document.getElementById('rifa_reserva_ttl_min').value = '60';
        document.getElementById('rifa_reserva_auto').checked = true;
        document.getElementById('rifa_modo_asignacion').value = 'manual';
        document.getElementById('rifa_mostrar_reservados').checked = true;
        document.getElementById('rifa_fecha_sorteo').value = '';
        document.querySelectorAll('input[name="rifa_metodos[]"]').forEach(function (cb) {
            cb.checked = false;
        });

        slugManual = false;
        set_descripcion_html('');
        limpiar_galeria();
        habilitar_campos_editables(true);
        aplicar_tipo_rifa();
        limpiar_errores(formRifa, alertErroresRifa, listaErroresRifa);
    }

    function cargar_datos(id) {
        return request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return null;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para consultar esta rifa.');
                return null;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron cargar los datos de la rifa.');
                return null;
            }

            return res.data.data;
        });
    }

    function abrir_edicion(id) {
        limpiar_modal_rifa();

        cargar_datos(id).then(function (d) {
            if (!d) {
                return;
            }

            const rifaId = campo(d, ['rifa_id', 'id'], id);
            const estado = String(campo(d, ['rifa_estado', 'estado'], 'borrador')).toLowerCase();
            const tipo = String(campo(d, ['rifa_tipo', 'tipo'], 'paga')).toLowerCase();
            const numeracion = String(campo(d, ['rifa_numeracion_tipo', 'numeracion_tipo'], '2'));
            const asigId = parseInt(campo(d, ['rifa_asig_id', 'asig_id'], 0), 10) || 0;
            const numId = parseInt(campo(d, ['rifa_num_id', 'num_id', 'asig_num_id'], 0), 10) || 0;

            document.getElementById('rifa_id').value = String(rifaId);
            document.getElementById('rifa_titulo').value = campo(d, ['rifa_titulo', 'titulo'], '');
            document.getElementById('rifa_slug').value = campo(d, ['rifa_slug', 'slug'], '');
            set_descripcion_html(campo(d, ['rifa_descripcion', 'descripcion'], ''));
            document.getElementById('rifa_condiciones').value = campo(d, ['rifa_condiciones', 'condiciones'], '');
            document.getElementById('rifa_tipo').value = (tipo === 'gratuita') ? 'gratuita' : 'paga';
            const selectMonedaRifa = document.getElementById('rifa_moneda');
            const monedasValidas = selectMonedaRifa
                ? Array.from(selectMonedaRifa.options).map(function (opcion) { return opcion.value; })
                : ['USD'];
            const moneda = String(campo(d, ['rifa_moneda', 'moneda'], 'USD')).toUpperCase();
            const numeracionesValidas = ['2', '3', '4', 'custom'];

            document.getElementById('rifa_precio').value = String(campo(d, ['rifa_precio', 'precio'], 0));
            document.getElementById('rifa_moneda').value = monedasValidas.indexOf(moneda) !== -1 ? moneda : 'USD';
            document.getElementById('rifa_numeracion_tipo').value = numeracionesValidas.indexOf(numeracion) !== -1 ? numeracion : '2';
            document.getElementById('rifa_numero_desde').value = String(campo(d, ['rifa_numero_desde', 'numero_desde'], 0));
            document.getElementById('rifa_numero_hasta').value = String(campo(d, ['rifa_numero_hasta', 'numero_hasta'], 99));
            document.getElementById('rifa_padding').value = String(campo(d, ['rifa_padding', 'padding'], 2));
            document.getElementById('rifa_max_por_compra').value = String(campo(d, ['rifa_max_por_compra', 'max_por_compra'], 10));
            document.getElementById('rifa_reserva_ttl_min').value = String(campo(d, ['rifa_reserva_ttl_min', 'reserva_ttl_min', 'ttl'], 60));
            document.getElementById('rifa_reserva_auto').checked = parseInt(campo(d, ['rifa_reserva_auto', 'reserva_auto'], 1), 10) === 1;
            const modoAsignacion = String(campo(d, ['rifa_modo_asignacion', 'modo_asignacion'], 'manual')).toLowerCase();
            document.getElementById('rifa_modo_asignacion').value = modoAsignacion === 'automatica' ? 'automatica' : 'manual';
            document.getElementById('rifa_mostrar_reservados').checked = parseInt(campo(d, ['rifa_mostrar_reservados', 'mostrar_reservados'], 1), 10) === 1;
            document.getElementById('rifa_fecha_sorteo').value = fecha_para_input(campo(d, ['rifa_fecha_sorteo', 'fecha_sorteo'], ''));

            const metodosSeleccionados = Array.isArray(d.metodos_pago)
                ? d.metodos_pago.map(function (id) { return parseInt(id, 10) || 0; })
                : [];
            document.querySelectorAll('input[name="rifa_metodos[]"]').forEach(function (cb) {
                cb.checked = metodosSeleccionados.indexOf(parseInt(cb.value, 10)) !== -1;
            });

            slugManual = true;
            document.getElementById('modal_rifa_titulo').textContent = 'Editar: ' + (campo(d, ['rifa_titulo', 'titulo'], 'rifa'));

            const bloqueado = !!ESTADOS_BLOQUEADOS[estado];
            habilitar_campos_editables(!bloqueado);

            if (tipo !== 'gratuita') {
                asegurar_lote_actual(d, asigId, numId);
                if (!seleccionar_lote(asigId, true)) {
                    seleccionar_lote_inicial();
                }
                if (numId > 0) {
                    seleccionar_numeracion(numId);
                }
            }

            actualizar_estado_lote();

            // Galería M03.2: editable salvo en estados cerrados.
            const galeriaPermitida = (estado !== 'finalizada' && estado !== 'cancelada');
            configurar_modo_galeria('editar');
            aplicar_estado_galeria(galeriaPermitida);
            cargar_galeria(rifaId, campo(d, ['imagenes'], []));

            limpiar_errores(formRifa, alertErroresRifa, listaErroresRifa);
            modalRifa.show();
        });
    }

    // ─────────────────────────────────────────────────────
    // Validación previa al guardado
    // ─────────────────────────────────────────────────────
    function validar_formulario() {
        const errores = {};
        const titulo = document.getElementById('rifa_titulo').value.trim();
        const slug = normalizar_slug(document.getElementById('rifa_slug').value);
        document.getElementById('rifa_slug').value = slug;

        if (titulo.length < 3 || titulo.length > 150) {
            errores.rifa_titulo = 'El título debe tener entre 3 y 150 caracteres.';
        }

        if (document.getElementById('rifa_slug').disabled === false && !slug_valido(slug)) {
            errores.rifa_slug = 'El slug debe tener entre 3 y 120 caracteres y solo usar minúsculas, números y guiones simples.';
        }

        const tipo = document.getElementById('rifa_tipo').value;
        const inputPrecioRifa = document.getElementById('rifa_precio');
        if (inputPrecioRifa && !inputPrecioRifa.disabled) {
            const precio = parseFloat(inputPrecioRifa.value);
            if (!isFinite(precio) || precio <= 0) {
                errores.rifa_precio = 'El precio del ticket debe ser mayor que cero.';
            }
        }

        if (tipo === 'paga') {
            const lote = lote_datos(lote_option());
            if (!lote) {
                errores.rifa_asig_id = 'Selecciona un lote para esta rifa paga.';
            } else if (lote.ilimitado) {
                if (!num_datos(num_option())) {
                    errores.rifa_num_id = 'Selecciona una numeración activa para el lote ilimitado.';
                }
            } else if (isFinite(lote.restantes) && lote.restantes <= 0) {
                const idActual = parseInt(document.getElementById('rifa_id').value, 10) || 0;
                if (idActual <= 0) {
                    errores.rifa_asig_id = 'El lote seleccionado no tiene cupo disponible.';
                }
            }
        }

        const total = parseInt(document.getElementById('rifa_total_numeros').value, 10) || 0;
        if (!document.getElementById('rifa_max_por_compra').disabled) {
            const maximo = parseInt(document.getElementById('rifa_max_por_compra').value, 10);
            if (!isFinite(maximo) || maximo < 1 || (total > 0 && maximo > total)) {
                errores.rifa_max_por_compra = total > 0
                    ? ('El máximo por compra debe estar entre 1 y ' + total + '.')
                    : 'El máximo por compra debe ser mayor o igual a 1.';
            }
        }

        if (!document.getElementById('rifa_reserva_ttl_min').disabled) {
            const ttl = parseInt(document.getElementById('rifa_reserva_ttl_min').value, 10);
            if (!isFinite(ttl) || ttl < 0 || ttl > 10080) {
                errores.rifa_reserva_ttl_min = 'El TTL debe estar entre 0 y 10080 minutos.';
            }
        }

        const modoCrearGaleria = !!galeriaModoCrearEl && !galeriaModoCrearEl.classList.contains('d-none');
        if (modoCrearGaleria && inputImagenesRifa && inputImagenesRifa.files && inputImagenesRifa.files.length > GALERIA_MAX) {
            errores.rifa_imagenes = 'Puedes seleccionar un máximo de ' + GALERIA_MAX + ' imágenes.';
        }

        mostrar_errores(formRifa, alertErroresRifa, listaErroresRifa, errores);

        return Object.keys(errores).length === 0;
    }

    // ─────────────────────────────────────────────────────
    // Guardar (crear / actualizar)
    // ─────────────────────────────────────────────────────
    function guardar_rifa() {
        if (!validar_formulario()) {
            return;
        }

        const id = document.getElementById('rifa_id').value;
        const esNuevo = (id === '0' || id === '');
        const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + encodeURIComponent(id);
        const tipo = document.getElementById('rifa_tipo').value;
        const lote = tipo === 'paga' ? lote_datos(lote_option()) : null;
        const num = (tipo === 'paga' && lote && lote.ilimitado) ? num_datos(num_option()) : null;

        const descripcionHtml = get_descripcion_html();
        if (descripcionTextarea) {
            descripcionTextarea.value = descripcionHtml;
        }

        const fd = new FormData(formRifa);
        fd.set('rifa_titulo', document.getElementById('rifa_titulo').value.trim());
        fd.set('rifa_descripcion', descripcionHtml);
        if (!camposBloqueados) {
            fd.set('rifa_slug', normalizar_slug(document.getElementById('rifa_slug').value));
        }
        fd.set('rifa_fecha_sorteo', fecha_para_backend(document.getElementById('rifa_fecha_sorteo').value));

        // Lote y numeración: el backend M03.1 deriva la numeración del lote.
        fd.set('rifa_asig_id', String(lote ? lote.id : 0));
        if (lote && lote.ilimitado) {
            fd.set('rifa_num_id', String(num ? num.id : 0));
        } else if (lote) {
            fd.set('rifa_num_id', String(lote.numId || 0));
        } else {
            fd.set('rifa_num_id', '0');
        }

        if (!camposBloqueados) {
            fd.set('rifa_reserva_auto', document.getElementById('rifa_reserva_auto').checked ? '1' : '0');
            fd.set('rifa_modo_asignacion', document.getElementById('rifa_modo_asignacion').value || 'manual');
            fd.set('rifa_mostrar_reservados', document.getElementById('rifa_mostrar_reservados').checked ? '1' : '0');
            fd.set('rifa_total_numeros', document.getElementById('rifa_total_numeros').value || '0');
            fd.set('rifa_numeracion_tipo', document.getElementById('rifa_numeracion_tipo').value || NUMERACION_FALLBACK.tipo);
            fd.set('rifa_numero_desde', document.getElementById('rifa_numero_desde').value || String(NUMERACION_FALLBACK.desde));
            fd.set('rifa_numero_hasta', document.getElementById('rifa_numero_hasta').value || String(NUMERACION_FALLBACK.hasta));
            fd.set('rifa_padding', document.getElementById('rifa_padding').value || String(NUMERACION_FALLBACK.padding));
        }

        set_loading(btnGuardarRifa, true);
        limpiar_errores(formRifa, alertErroresRifa, listaErroresRifa);

        request_json(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) {
            set_loading(btnGuardarRifa, false);

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.data && res.data.ok) {
                modalRifa.hide();
                mostrar_alerta('success', res.data.msg || (esNuevo ? 'Rifa creada correctamente.' : 'Rifa actualizada correctamente.'));
                if (dtMisRifas) {
                    dtMisRifas.ajax.reload(null, false);
                }
                return;
            }

            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(formRifa, alertErroresRifa, listaErroresRifa, res.data.errores);
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para realizar esta acción.');
                return;
            }

            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo guardar la rifa.');
        })
        .catch(function () {
            set_loading(btnGuardarRifa, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    if (btnNuevaRifa) {
        btnNuevaRifa.addEventListener('click', function () {
            limpiar_modal_rifa();
            modalRifa.show();
        });
    }

    if (btnGuardarRifa) {
        btnGuardarRifa.addEventListener('click', guardar_rifa);
    }

    if (formRifa) {
        formRifa.addEventListener('submit', function (event) {
            event.preventDefault();
            guardar_rifa();
        });
    }

    if (modalRifaEl) {
        modalRifaEl.addEventListener('hidden.bs.modal', function () {
            revocar_previews_galeria();
        });
    }

    // ─────────────────────────────────────────────────────
    // M03.3: números, bloqueos y venta presencial
    // ─────────────────────────────────────────────────────
    function abrir_numeros(id) {
        numerosRifaId = parseInt(id, 10) || 0;
        numerosPagina = 0;
        numerosTotal = 0;

        if (numerosInput) { numerosInput.value = ''; }
        if (numerosMotivo) { numerosMotivo.value = ''; }
        if (numerosComprador) { numerosComprador.value = ''; }

        if (alertNumeros) {
            alertNumeros.classList.add('d-none');
            alertNumeros.innerHTML = '';
        }

        if (modalNumeros) {
            modalNumeros.show();
        }

        cargar_resumen_numeros();
    }

    function cargar_resumen_numeros() {
        if (!numerosRifaId) {
            return;
        }

        if (alertNumeros) {
            alertNumeros.classList.add('d-none');
            alertNumeros.innerHTML = '';
        }

        request_json(BASE_URL + 'tickets/' + encodeURIComponent(numerosRifaId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_error_numeros((res.data && res.data.msg) || 'No se pudieron cargar los números de la rifa.');
                return;
            }

            const payload = res.data.data;
            const rifa = payload.rifa || {};
            const resumen = payload.resumen || {};

            document.getElementById('numeros_rifa_subtitulo').textContent =
                (rifa.titulo || 'Rifa') + ' · ' + (rifa.estado || '');

            document.getElementById('num_total').textContent = Number(resumen.total || 0).toLocaleString('es');
            document.getElementById('num_disponibles').textContent = Number(resumen.disponibles || 0).toLocaleString('es');
            document.getElementById('num_reservados').textContent = Number(resumen.reservados || 0).toLocaleString('es');
            document.getElementById('num_pagados').textContent = Number(resumen.pagados || 0).toLocaleString('es');
            document.getElementById('num_bloqueados').textContent = Number(resumen.bloqueados || 0).toLocaleString('es');

            document.getElementById('num_modo_badge').textContent =
                'Asignación: ' + (rifa.modo === 'automatica' ? 'Automática (aleatoria)' : 'Manual');

            document.getElementById('num_visibilidad_badge').textContent =
                Number(rifa.mostrar_reservados || 0) === 1
                    ? 'Sitio público: muestra reservados'
                    : 'Sitio público: solo disponibles';

            const padding = Math.max(1, parseInt(rifa.padding, 10) || 1);
            const desde = String(rifa.desde || 0).padStart(padding, '0');
            const hasta = String(rifa.hasta || 0).padStart(padding, '0');
            document.getElementById('num_rango_badge').textContent = 'Rango: ' + desde + '–' + hasta;

            numerosTotal = parseInt(resumen.total, 10) || 0;
            render_bloqueados_numeros(payload.bloqueados || [], parseInt(resumen.bloqueados, 10) || 0);
            cargar_mapa_numeros();
        }).catch(function () {
            mostrar_error_numeros('Error de conexión al cargar los números.');
        });
    }

    function mostrar_error_numeros(msg) {
        if (!alertNumeros) {
            mostrar_alerta('error', msg);
            return;
        }

        alertNumeros.textContent = msg;
        alertNumeros.classList.remove('d-none');
    }

    function render_bloqueados_numeros(lista, totalBloqueados) {
        if (!tablaBloqueadosNumeros) {
            return;
        }

        tablaBloqueadosNumeros.innerHTML = '';

        if (!Array.isArray(lista) || lista.length === 0) {
            tablaBloqueadosNumeros.innerHTML =
                '<tr><td colspan="4" class="text-center text-muted fs-7 py-4">Sin bloqueos activos.</td></tr>';
            return;
        }

        let mostrados = 0;

        lista.forEach(function (rango) {
            const cantidad = Number(rango.cantidad || 0);
            mostrados += cantidad;

            const fila = document.createElement('tr');
            fila.innerHTML =
                '<td><span class="fw-bold text-gray-900 fs-7">' + escapeHtml(rango.label) + '</span></td>' +
                '<td class="text-center"><span class="badge badge-light-danger fw-bold">' + cantidad + '</span></td>' +
                '<td class="text-muted fs-7">' + escapeHtml(rango.motivo || 'Sin motivo') + '</td>' +
                '<td class="text-end">' +
                    '<button type="button" class="btn btn-sm btn-light-info fw-semibold btn-liberar-rango" ' +
                        'data-desde="' + Number(rango.desde || 0) + '" data-hasta="' + Number(rango.hasta || 0) + '">' +
                        '<i class="ki-duotone ki-arrows-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>' +
                        'Liberar' +
                    '</button>' +
                '</td>';
            tablaBloqueadosNumeros.appendChild(fila);
        });

        if (Number(totalBloqueados || 0) > mostrados) {
            const aviso = document.createElement('tr');
            aviso.innerHTML =
                '<td colspan="4" class="text-warning fs-8 fw-semibold py-3">' +
                    'Mostrando ' + mostrados.toLocaleString('es') + ' de ' +
                    Number(totalBloqueados).toLocaleString('es') +
                    ' números bloqueados. Usa un rango específico para liberar el resto.' +
                '</td>';
            tablaBloqueadosNumeros.appendChild(aviso);
        }
    }

    function cargar_mapa_numeros() {
        if (!numerosRifaId || !mapaNumerosEl) {
            return;
        }

        mapaNumerosEl.innerHTML = '<span class="text-muted fs-7">Cargando números…</span>';

        const offset = numerosPagina * NUMEROS_PAGINA;

        request_json(BASE_URL + 'tickets-mapa/' + encodeURIComponent(numerosRifaId)
            + '?offset=' + offset + '&limit=' + NUMEROS_PAGINA, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                mapaNumerosEl.innerHTML = '<span class="text-danger fs-7">No se pudo cargar el mapa de números.</span>';
                return;
            }

            const data = res.data.data;
            const items = Array.isArray(data.items) ? data.items : [];
            const total = parseInt(data.total, 10) || 0;
            numerosTotal = total;
            numerosPagina = Math.floor((parseInt(data.offset, 10) || 0) / NUMEROS_PAGINA);

            mapaNumerosEl.innerHTML = '';

            if (items.length === 0) {
                mapaNumerosEl.innerHTML = '<span class="text-muted fs-7">No hay números para mostrar.</span>';
            }

            items.forEach(function (item) {
                const estado = String(item.estado || 'disponible');
                const clase = estado === 'pagado'
                    ? 'badge-light-primary'
                    : (estado === 'reservado'
                        ? 'badge-light-warning'
                        : (estado === 'bloqueado' ? 'badge-light-danger' : 'badge-light-success'));

                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'badge ' + clase + ' fw-bold border border-gray-200 num-chip';
                chip.textContent = item.numero;
                chip.setAttribute('data-numero', item.numero);
                chip.setAttribute('title', estado + (item.comprador_nombre ? ' · ' + item.comprador_nombre : ''));
                mapaNumerosEl.appendChild(chip);
            });

            const totalPaginas = total > 0 ? Math.ceil(total / NUMEROS_PAGINA) : 0;
            if (mapaPaginaLabel) {
                mapaPaginaLabel.textContent = totalPaginas > 0
                    ? 'Página ' + (numerosPagina + 1) + ' de ' + totalPaginas
                    : 'Sin números';
            }

            if (btnMapaPrev) {
                btnMapaPrev.disabled = numerosPagina <= 0;
            }
            if (btnMapaNext) {
                btnMapaNext.disabled = totalPaginas === 0 || (numerosPagina + 1) >= totalPaginas;
            }
        }).catch(function () {
            mapaNumerosEl.innerHTML = '<span class="text-danger fs-7">Error de conexión al cargar el mapa.</span>';
        });
    }

    function confirmar_y_ejecutar_numeros(accion) {
        if (!numerosInput || !numerosInput.value.trim()) {
            mostrar_error_numeros('Indica un número o rango. Ejemplo: 10-20, 25, 86.');
            numerosInput.focus();
            return;
        }

        const textos = {
            bloquear: {
                title: '¿Bloquear los números?',
                text: 'Los números indicados quedarán fuera de la venta.',
                boton: 'Sí, bloquear',
                clase: 'btn btn-sm btn-warning'
            },
            liberar: {
                title: '¿Liberar los números?',
                text: 'Los números bloqueados volverán a estar disponibles.',
                boton: 'Sí, liberar',
                clase: 'btn btn-sm btn-info'
            },
            venta: {
                title: '¿Registrar venta presencial?',
                text: 'Los números se marcarán como pagados a nombre del comprador indicado.',
                boton: 'Sí, registrar venta',
                clase: 'btn btn-sm btn-success'
            }
        };

        const cfg = textos[accion] || textos.bloquear;

        Swal.fire({
            icon: accion === 'liberar' ? 'question' : 'warning',
            title: cfg.title,
            text: cfg.text,
            showCancelButton: true,
            confirmButtonText: cfg.boton,
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: cfg.clase,
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                ejecutar_operacion_numeros(accion);
            }
        });
    }

    function ejecutar_operacion_numeros(accion) {
        if (numerosEnCurso || !numerosRifaId) {
            return;
        }

        const rutas = {
            bloquear: 'bloquear-numeros',
            liberar: 'liberar-numeros',
            venta: 'venta-presencial'
        };

        const ruta = rutas[accion];
        if (!ruta) {
            return;
        }

        const fd = new FormData();
        fd.set('numeros', numerosInput ? numerosInput.value.trim() : '');
        fd.set('motivo', numerosMotivo ? numerosMotivo.value.trim() : '');
        fd.set('comprador', numerosComprador ? numerosComprador.value.trim() : '');

        numerosEnCurso = true;

        request_json(BASE_URL + ruta + '/' + encodeURIComponent(numerosRifaId), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            numerosEnCurso = false;

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_error_numeros((res.data && res.data.msg) || 'No tienes permiso para realizar esta acción.');
                return;
            }

            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || 'Operación completada.');
                if (numerosInput) { numerosInput.value = ''; }
                cargar_resumen_numeros();
                if (dtMisRifas) {
                    dtMisRifas.ajax.reload(null, false);
                }
                return;
            }

            if (res.data && res.data.conflictos && res.data.conflictos.length) {
                mostrar_error_numeros(
                    (res.data.msg || 'Hay conflictos.') + ' ' +
                    conflictos_texto(res.data.conflictos, res.data.conflictos_total)
                );
                return;
            }

            const detalle = primer_error(res.data && res.data.errores);
            mostrar_error_numeros(detalle || (res.data && res.data.msg) || 'No se pudo completar la operación.');
        }).catch(function () {
            numerosEnCurso = false;
            mostrar_error_numeros('Error de conexión con el servidor.');
        });
    }

    function conflictos_texto(conflictos, total) {
        const etiquetas = {
            reservado: 'reservado',
            pagado: 'pagado',
            bloqueado: 'bloqueado',
            disponible: 'disponible'
        };

        const totalReal = Number(total || 0) || conflictos.length;
        const visibles = conflictos.slice(0, 30).map(function (item) {
            return item.numero + ' (' + (etiquetas[item.estado] || item.estado) + ')';
        }).join(', ');

        return visibles + (totalReal > 30 ? '… y ' + (totalReal - 30).toLocaleString('es') + ' más' : '');
    }

    if (document.getElementById('btn_bloquear_numeros')) {
        document.getElementById('btn_bloquear_numeros').addEventListener('click', function () {
            confirmar_y_ejecutar_numeros('bloquear');
        });
    }

    if (document.getElementById('btn_liberar_numeros')) {
        document.getElementById('btn_liberar_numeros').addEventListener('click', function () {
            confirmar_y_ejecutar_numeros('liberar');
        });
    }

    if (document.getElementById('btn_venta_numeros')) {
        document.getElementById('btn_venta_numeros').addEventListener('click', function () {
            confirmar_y_ejecutar_numeros('venta');
        });
    }

    if (btnMapaPrev) {
        btnMapaPrev.addEventListener('click', function () {
            if (numerosPagina > 0) {
                numerosPagina--;
                cargar_mapa_numeros();
            }
        });
    }

    if (btnMapaNext) {
        btnMapaNext.addEventListener('click', function () {
            if ((numerosPagina + 1) * NUMEROS_PAGINA < numerosTotal) {
                numerosPagina++;
                cargar_mapa_numeros();
            }
        });
    }

    if (mapaNumerosEl) {
        mapaNumerosEl.addEventListener('click', function (event) {
            const chip = event.target.closest('[data-numero]');
            if (!chip || !numerosInput) {
                return;
            }

            const numero = chip.getAttribute('data-numero');
            const actual = numerosInput.value.trim();

            numerosInput.value = actual === '' ? numero : (actual.replace(/,\s*$/, '') + ', ' + numero);
            numerosInput.focus();
        });
    }

    if (tablaBloqueadosNumeros) {
        tablaBloqueadosNumeros.addEventListener('click', function (event) {
            const btn = event.target.closest('.btn-liberar-rango');
            if (!btn || !numerosInput) {
                return;
            }

            const desde = parseInt(btn.getAttribute('data-desde'), 10);
            const hasta = parseInt(btn.getAttribute('data-hasta'), 10);

            if (!isFinite(desde) || !isFinite(hasta)) {
                return;
            }

            numerosInput.value = desde === hasta ? String(desde) : (desde + '-' + hasta);
            confirmar_y_ejecutar_numeros('liberar');
        });
    }

    // ─────────────────────────────────────────────────────
    // DataTable server-side
    // ─────────────────────────────────────────────────────
    function init_tabla() {
        if (!tieneDataTables) {
            const aviso = document.getElementById('aviso_datatables_mis_rifas');
            if (aviso) {
                aviso.classList.remove('d-none');
            }
            return;
        }

        dtMisRifas = jq('#tabla_mis_rifas').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
            ajax: {
                url: BASE_URL + 'tabla',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: function (d) {
                    /*
                     * El mapa server-side de Rifa_model es compartido con el
                     * módulo administrativo: 0 organizador, 1 título, 2 tipo,
                     * 3 total, 4 estado, 5 fecha de sorteo.
                     * La vista "Mis Rifas" no muestra organizador, por lo que
                     * se remapean los índices para ordenar por la columna real.
                     */
                    if (d.order && d.order.length) {
                        const mapa = { 0: 1, 1: 2, 2: 3, 3: 4, 4: 5 };
                        d.order.forEach(function (orden) {
                            if (orden.column !== undefined && mapa[orden.column] !== undefined) {
                                orden.column = String(mapa[orden.column]);
                            }
                        });
                    }
                },
                dataSrc: function (json) {
                    if (json && json.error) {
                        mostrar_alerta('warning', json.error);
                    }
                    return (json && json.data) ? json.data : [];
                },
                error: function (xhr) {
                    mostrar_alerta('error', mensaje_error_xhr(xhr, 'No se pudieron cargar tus rifas.'));
                }
            },
            columns: [
                { data: 'rifa', orderable: true, responsivePriority: 1 },
                { data: 'tipo', orderable: false, responsivePriority: 2 },
                { data: 'numeracion', orderable: false, responsivePriority: 3 },
                { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
                { data: 'sorteo', orderable: true, responsivePriority: 4 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
            ],
            drawCallback: function () {
                init_tooltips();
            },
            language: idioma_es('rifas')
        });
    }

    init_tabla();

    if (inputFiltro && dtMisRifas) {
        inputFiltro.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function () {
                dtMisRifas.search(valor).draw();
            }, 250);
        });
    }

    // ─────────────────────────────────────────────────────
    // Acciones de fila
    // ─────────────────────────────────────────────────────
    function obtener_contexto(btn) {
        const fila = fila_data(dtMisRifas, btn);

        return {
            id: btn.dataset.id || fila.rifa_id || fila.id || '',
            titulo: btn.dataset.titulo || fila.rifa_titulo || fila.titulo || 'esta rifa',
            tipo: String(btn.dataset.tipo || fila.rifa_tipo || '').toLowerCase(),
            estado: String(btn.dataset.estado || fila.rifa_estado || '').toLowerCase(),
            tipoHtml: String(fila.tipo || '')
        };
    }

    function es_rifa_gratuita(ctx) {
        if (ctx.tipo === 'gratuita' || ctx.tipo === 'gratis') {
            return true;
        }
        if (ctx.tipo === 'paga') {
            return false;
        }
        return /gratuit/i.test(ctx.tipoHtml);
    }

    function enviar_accion(ruta, id, datos, mensajeExito) {
        if (accionEnCurso) {
            return;
        }

        accionEnCurso = true;
        const fd = datos || new FormData();

        request_json(BASE_URL + ruta + '/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) {
            accionEnCurso = false;

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || mensajeExito);
                if (dtMisRifas) {
                    dtMisRifas.ajax.reload(null, false);
                }
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para realizar esta acción.');
                return;
            }

            const detalle = primer_error(res.data && res.data.errores);
            mostrar_alerta('error', detalle || (res.data && res.data.msg) || 'No se pudo completar la acción.');
        })
        .catch(function () {
            accionEnCurso = false;
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    function confirmar_pausar(id, titulo) {
        Swal.fire({
            icon: 'warning',
            title: '¿Pausar la rifa?',
            text: 'La rifa "' + titulo + '" dejará de aceptar compras mientras esté pausada.',
            showCancelButton: true,
            confirmButtonText: 'Sí, pausar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-warning',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                enviar_accion('pausar', id, null, 'Rifa pausada correctamente.');
            }
        });
    }

    function confirmar_reactivar(id, titulo) {
        Swal.fire({
            icon: 'question',
            title: '¿Reactivar la rifa?',
            text: 'La rifa "' + titulo + '" volverá a estar disponible para compras.',
            showCancelButton: true,
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-success',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                enviar_accion('reactivar', id, null, 'Rifa reactivada correctamente.');
            }
        });
    }

    function confirmar_finalizar(id, titulo) {
        Swal.fire({
            icon: 'warning',
            title: '¿Finalizar la rifa?',
            text: 'La rifa "' + titulo + '" se marcará como finalizada y no podrá volver a publicarse.',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-primary',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                enviar_accion('finalizar', id, null, 'Rifa finalizada correctamente.');
            }
        });
    }

    function confirmar_eliminar(id, titulo) {
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la rifa?',
            text: '¿Estás seguro de que deseas eliminar "' + titulo + '"? Esta acción es un borrado lógico.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                enviar_accion('eliminar', id, null, 'Rifa eliminada correctamente.');
            }
        });
    }

    function solicitar_cancelacion(id, titulo) {
        Swal.fire({
            icon: 'warning',
            title: 'Solicitar cancelación',
            html: 'Indica el motivo para solicitar la cancelación de <strong>' + escapeHtml(titulo) + '</strong>.',
            input: 'textarea',
            inputLabel: 'Motivo de la cancelación',
            inputPlaceholder: 'Describe brevemente el motivo (obligatorio)',
            inputAttributes: {
                maxlength: 255,
                'aria-label': 'Motivo de la cancelación'
            },
            inputValidator: function (value) {
                if (!value || !String(value).trim()) {
                    return 'El motivo es obligatorio.';
                }
                if (String(value).trim().length > 255) {
                    return 'El motivo no puede superar los 255 caracteres.';
                }
                return null;
            },
            showCancelButton: true,
            confirmButtonText: 'Solicitar cancelación',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            const fd = new FormData();
            fd.set('motivo', String(result.value || '').trim());
            enviar_accion('solicitar-cancelacion', id, fd, 'Solicitud de cancelación enviada.');
        });
    }

    function publicar(id, ctx) {
        if (es_rifa_gratuita(ctx)) {
            Swal.fire({
                icon: 'question',
                title: 'Publicar rifa gratuita',
                html: 'Puedes indicar el motivo de recaudación de <strong>' + escapeHtml(ctx.titulo) + '</strong>.',
                input: 'textarea',
                inputLabel: 'Motivo de recaudación (opcional)',
                inputPlaceholder: 'Ej: Recaudar fondos para una causa benéfica',
                inputAttributes: {
                    maxlength: 255,
                    'aria-label': 'Motivo de recaudación'
                },
                showCancelButton: true,
                confirmButtonText: 'Publicar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-success',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                const fd = new FormData();
                fd.set('motivo', String(result.value || '').trim());
                enviar_accion('publicar', id, fd, 'Rifa publicada correctamente.');
            });
            return;
        }

        Swal.fire({
            icon: 'question',
            title: '¿Publicar la rifa?',
            text: 'La rifa "' + ctx.titulo + '" quedará visible para tus clientes.',
            showCancelButton: true,
            confirmButtonText: 'Sí, publicar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-success',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                enviar_accion('publicar', id, null, 'Rifa publicada correctamente.');
            }
        });
    }

    function delegar_acciones(event) {
        const btnNumeros = event.target.closest('.btn-numeros, [data-action="numeros"]');
        if (btnNumeros) {
            const ctx = obtener_contexto(btnNumeros);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            abrir_numeros(ctx.id);
            return;
        }

        const btnEditar = event.target.closest('.btn-editar, .btn-ver, .btn-detalle, [data-action="editar"], [data-action="ver"], [data-action="detalle"]');
        if (btnEditar) {
            if (btnEditar.disabled) {
                return;
            }

            const ctx = obtener_contexto(btnEditar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            abrir_edicion(ctx.id);
            return;
        }

        const btnPublicar = event.target.closest('.btn-publicar, [data-action="publicar"]');
        if (btnPublicar) {
            const ctx = obtener_contexto(btnPublicar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            publicar(ctx.id, ctx);
            return;
        }

        const btnPausar = event.target.closest('.btn-pausar, [data-action="pausar"]');
        if (btnPausar) {
            const ctx = obtener_contexto(btnPausar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            confirmar_pausar(ctx.id, ctx.titulo);
            return;
        }

        const btnReactivar = event.target.closest('.btn-reactivar, [data-action="reactivar"]');
        if (btnReactivar) {
            const ctx = obtener_contexto(btnReactivar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            confirmar_reactivar(ctx.id, ctx.titulo);
            return;
        }

        const btnCancelacion = event.target.closest('.btn-solicitar-cancelacion, .btn-cancelar, [data-action="solicitar-cancelacion"], [data-action="cancelar"]');
        if (btnCancelacion) {
            const ctx = obtener_contexto(btnCancelacion);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            solicitar_cancelacion(ctx.id, ctx.titulo);
            return;
        }

        const btnFinalizar = event.target.closest('.btn-finalizar, [data-action="finalizar"]');
        if (btnFinalizar) {
            const ctx = obtener_contexto(btnFinalizar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            confirmar_finalizar(ctx.id, ctx.titulo);
            return;
        }

        const btnEliminar = event.target.closest('.btn-eliminar, [data-action="eliminar"]');
        if (btnEliminar) {
            const ctx = obtener_contexto(btnEliminar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                return;
            }
            confirmar_eliminar(ctx.id, ctx.titulo);
        }
    }

    const tablaMisRifas = document.getElementById('tabla_mis_rifas');
    if (tablaMisRifas) {
        tablaMisRifas.addEventListener('click', delegar_acciones);
    }
});
</script>
<!--end::JavaScript-->
