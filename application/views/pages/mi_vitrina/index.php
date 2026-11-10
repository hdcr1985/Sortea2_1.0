<?php
/**
 * M01 - Núcleo multi-tenant y subdominios
 * Vista de autoservicio "Mi Vitrina".
 *
 * Variables admitidas (cualquiera de ellas):
 * - $perfil, $organizador, $tenant, $tenant_data: arreglo con datos del organizador.
 * - $base_domain: dominio base para subdominios.
 * - $url_publica: URL pública ya resuelta por el controlador (opcional).
 */

$base_domain = (isset($base_domain) && is_string($base_domain) && trim($base_domain) !== '')
    ? ltrim(trim($base_domain), '.')
    : 'sortea2.net';

$mi_vitrina = null;
foreach (['perfil', 'organizador', 'tenant', 'tenant_data'] as $clave_vitrina) {
    if (isset($$clave_vitrina) && is_array($$clave_vitrina) && (!empty($$clave_vitrina['org_id']) || !empty($$clave_vitrina['org_slug']))) {
        $mi_vitrina = $$clave_vitrina;
        break;
    }
}

$tiene_vitrina = is_array($mi_vitrina);
$suspendida = !empty($suspendida);

$org_nombre = $tiene_vitrina ? trim((string)($mi_vitrina['org_nombre_publico'] ?? '')) : '';
$org_slug = $tiene_vitrina ? trim((string)($mi_vitrina['org_slug'] ?? '')) : '';
$org_email = $tiene_vitrina ? trim((string)($mi_vitrina['org_email'] ?? '')) : '';
$org_telefono = $tiene_vitrina ? trim((string)($mi_vitrina['org_telefono'] ?? '')) : '';
$org_status = $tiene_vitrina ? (int)($mi_vitrina['org_status'] ?? 1) : 1;
$org_verificado = $tiene_vitrina ? (int)($mi_vitrina['org_verificado'] ?? 0) : 0;

$inicial = $org_nombre !== '' ? mb_strtoupper(mb_substr($org_nombre, 0, 1)) : 'T';

$url_publica = (isset($url_publica) && is_string($url_publica) && trim($url_publica) !== '')
    ? trim($url_publica)
    : '';

$url_publica_host = $org_slug !== '' ? 'https://' . $org_slug . '.' . $base_domain : '';
$url_publica_local = $org_slug !== '' ? base_url('sitio/' . rawurlencode($org_slug)) : '';

$host_actual = strtolower((string)parse_url((string)base_url(), PHP_URL_HOST));
$es_entorno_local = isset($path_resolution_enabled)
    ? (bool)$path_resolution_enabled
    : in_array($host_actual, ['localhost', '127.0.0.1', '::1', '[::1]'], TRUE);
$url_ver_sitio = ($es_entorno_local && $url_publica_local !== '')
    ? $url_publica_local
    : ($url_publica !== '' ? $url_publica : $url_publica_local);

if ($url_ver_sitio !== '' && !preg_match('#^(https?://|/(?!/))#i', $url_ver_sitio)) {
    $url_ver_sitio = '#';
}

$reservados = (isset($reservados) && is_array($reservados))
    ? array_values(array_filter($reservados, 'is_string'))
    : ['www', 'app', 'admin', 'api', 'mail', 'smtp', 'cdn', 'static', 'assets', 'media', 'soporte', 'login', 'registro', 'rifas', 'mi-vitrina'];

$reservados_visibles = array_slice($reservados, 0, 12);
$reservados_texto = implode(', ', $reservados_visibles);
if (count($reservados) > count($reservados_visibles)) {
    $reservados_texto .= ', ...';
}

// ─────────────────────────────────────────────────────────
// Mi Plan (M02): lectura defensiva de $plan_info.
// Contrato: tiene_plan, asignacion, plan, usadas, limite,
// restantes, ilimitado, puede_publicar, motivo, fin.
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
        $plan_estado_texto = 'Vencido';
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
        $plan_estado_texto = 'Vencido';
        $plan_estado_clase = 'danger';
    }
}

$plan_motivo_texto = $plan_motivo;
$plan_motivos = [
    'sin_plan' => 'Sin plan activo',
    'sin_cupo' => 'Sin cupo disponible',
    'vencida'  => 'Plan vencido',
    'vencido'  => 'Plan vencido',
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
// Se conservan las claves plan/usadas/limite/restantes para
// compatibilidad con la tarjeta histórica.
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

    $lote_cantidad = $lote_ilimitado
        ? null
        : (int)($lote['asig_cantidad'] ?? 0);
    $lote_usadas = (int)($lote['asig_rifas_usadas'] ?? 0);
    $lote_restantes = $lote_ilimitado
        ? null
        : (int)($lote['restantes'] ?? max(0, $lote_cantidad - $lote_usadas));

    $lote_inicio = '';
    if (!empty($lote['asig_inicio']) && strtoupper(trim((string)$lote['asig_inicio'])) !== 'NULL') {
        $lote_inicio_ts = strtotime((string)$lote['asig_inicio']);
        $lote_inicio = $lote_inicio_ts ? date('d/m/Y', $lote_inicio_ts) : (string)$lote['asig_inicio'];
    }

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

    $plan_lotes_render[] = [
        'plan'             => trim((string)($lote['plan_nombre'] ?? $plan_nombre)),
        'numeracion'       => trim((string)($lote['num_label'] ?? '')),
        'ilimitado'        => $lote_ilimitado,
        'cantidad'         => $lote_cantidad,
        'usadas'           => $lote_usadas,
        'restantes'        => $lote_restantes,
        'uso_texto'        => $lote_ilimitado
            ? ($lote_usadas . ' usadas · Ilimitado')
            : ($lote_usadas . ' / ' . (string)$lote_cantidad),
        'restantes_texto'  => $lote_ilimitado ? 'Ilimitado' : (string)$lote_restantes,
        'cantidad_texto'   => $lote_ilimitado ? 'Ilimitado' : (string)$lote_cantidad,
        'inicio'           => $lote_inicio,
        'fin'              => $lote_fin,
        'estado_clase'     => $lote_estado_clase,
        'estado_texto'     => $lote_estado_texto,
    ];
}
?>

<div class="d-flex flex-column gap-7 gap-lg-10">

<?php if ($suspendida): ?>
    <!--begin::Vitrina suspendida-->
    <div class="card card-flush shadow-sm border border-warning border-dashed">
        <div class="card-body d-flex align-items-start gap-4 p-7">
            <i class="ki-duotone ki-information-2 fs-2x text-warning">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <div>
                <h3 class="fw-bold text-gray-900 mb-2">Tu vitrina está suspendida</h3>
                <p class="text-muted fs-6 mb-0">
                    Tu tienda y sus rifas no están disponibles públicamente.
                    Contacta al administrador para revisar el estado de tu cuenta.
                </p>
            </div>
        </div>
    </div>
    <!--end::Vitrina suspendida-->
<?php elseif (!$tiene_vitrina): ?>
    <!--begin::Onboarding: crear vitrina-->
    <div class="card card-flush shadow-sm">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center gap-3">
                    <div class="symbol symbol-45px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-shop fs-2x text-primary"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="fw-bold text-gray-900 mb-1">Crea tu vitrina</h3>
                        <span class="text-muted fs-7">Publica tus rifas en tu propio subdominio de <?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?>.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-2">
            <!--begin::Alerta de errores-->
            <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_onboarding">
                <ul class="mb-0 fs-7" id="lista_errores_onboarding"></ul>
            </div>
            <!--end::Alerta de errores-->

            <div class="row g-6 g-xl-10">
                <div class="col-lg-7">
                    <form id="form_crear_vitrina" novalidate="novalidate">
                        <div class="row g-5">
                            <div class="col-12">
                                <label class="form-label required fw-semibold fs-7 text-gray-800" for="crear_org_nombre_publico">Nombre público</label>
                                <input type="text"
                                    name="org_nombre_publico"
                                    id="crear_org_nombre_publico"
                                    class="form-control form-control-solid"
                                    placeholder="Ej: Sorteos La Suerte"
                                    minlength="3"
                                    maxlength="150"
                                    autocomplete="organization"
                                    required />
                                <div class="invalid-feedback" data-error-for="org_nombre_publico"></div>
                                <div class="text-muted fs-8 mt-1">Será el nombre visible en la cabecera de tu sitio.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label required fw-semibold fs-7 text-gray-800" for="crear_org_slug">Subdominio</label>
                                <div class="input-group input-group-solid">
                                    <input type="text"
                                        name="org_slug"
                                        id="crear_org_slug"
                                        class="form-control form-control-solid"
                                        placeholder="mi-sorteo"
                                        minlength="3"
                                        maxlength="63"
                                        pattern="[a-z0-9][a-z0-9-]{1,61}[a-z0-9]"
                                        autocomplete="off"
                                        spellcheck="false"
                                        required />
                                    <span class="input-group-text text-gray-700">.<?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="invalid-feedback d-block" data-error-for="org_slug"></div>
                                <div class="d-flex align-items-center justify-content-between gap-3 mt-2">
                                    <div class="text-muted fs-8">Solo minúsculas, números y guiones. No puede iniciar ni terminar con guion.</div>
                                    <div class="text-muted fs-8 flex-shrink-0"><span id="crear_slug_counter">0</span>/63</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold fs-7 text-gray-800" for="crear_org_email">Email de contacto</label>
                                <input type="email"
                                    name="org_email"
                                    id="crear_org_email"
                                    class="form-control form-control-solid"
                                    placeholder="contacto@ejemplo.com"
                                    maxlength="150"
                                    autocomplete="email" />
                                <div class="invalid-feedback" data-error-for="org_email"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold fs-7 text-gray-800" for="crear_org_telefono">Teléfono de contacto</label>
                                <input type="tel"
                                    name="org_telefono"
                                    id="crear_org_telefono"
                                    class="form-control form-control-solid"
                                    placeholder="+58 412 000 0000"
                                    maxlength="30"
                                    autocomplete="tel" />
                                <div class="invalid-feedback" data-error-for="org_telefono"></div>
                            </div>

                            <div class="col-12">
                                <div class="separator separator-dashed my-1"></div>
                            </div>

                            <div class="col-12">
                                <div class="alert bg-light-info border border-info border-dashed d-flex align-items-start gap-3 mb-0" role="alert">
                                    <i class="ki-duotone ki-information-2 fs-2 text-info mt-1">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                    <div class="text-gray-700 fs-7">
                                        <strong>Nombres reservados:</strong> no se pueden usar subdominios como <?php echo htmlspecialchars($reservados_texto, ENT_QUOTES, 'UTF-8'); ?>. Si ya tienes una vitrina con ese subdominio, deberás elegir otro.
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_crear_vitrina">
                                    <span class="indicator-label">
                                        <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Crear mi vitrina
                                    </span>
                                    <span class="indicator-progress">
                                        Creando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-lg-5">
                    <div class="border border-gray-200 rounded-4 bg-light p-6 h-100 d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <i class="ki-duotone ki-geolocation fs-2 text-primary"><span class="path1"></span><span class="path2"></span></i>
                            <h4 class="fw-bold text-gray-900 mb-0">Vista previa de tu URL</h4>
                        </div>

                        <div class="bg-body border border-gray-200 rounded-3 p-3 mb-4">
                            <div class="d-flex align-items-center gap-2 text-muted fs-8 mb-2">
                                <span class="bullet bullet-dot bg-success w-6px h-6px"></span>
                                <span class="bullet bullet-dot bg-warning w-6px h-6px"></span>
                                <span class="bullet bullet-dot bg-danger w-6px h-6px"></span>
                                <span class="ms-1">URL pública</span>
                            </div>
                            <div class="text-gray-900 fw-semibold fs-7 text-break" id="crear_preview_host">
                                https://tu-subdominio.<?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="text-muted fs-8 mt-2 text-break" id="crear_preview_local">
                                <?php echo htmlspecialchars(base_url('sitio/tu-subdominio'), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="separator separator-dashed mb-4"></div>

                        <div class="d-flex flex-column gap-3 text-gray-700 fs-7">
                            <div class="d-flex align-items-start gap-2">
                                <i class="ki-duotone ki-check-circle fs-5 text-success mt-1"><span class="path1"></span><span class="path2"></span></i>
                                <span>Tu vitrina tendrá una URL propia y fácil de compartir.</span>
                            </div>
                            <div class="d-flex align-items-start gap-2">
                                <i class="ki-duotone ki-check-circle fs-5 text-success mt-1"><span class="path1"></span><span class="path2"></span></i>
                                <span>Podrás editar tu nombre, contacto y subdominio cuando quieras.</span>
                            </div>
                            <div class="d-flex align-items-start gap-2">
                                <i class="ki-duotone ki-shield-tick fs-5 text-primary mt-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <span>La disponibilidad del subdominio se valida en el servidor.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Onboarding: crear vitrina-->

<?php else: ?>
    <!--begin::Resumen de mi vitrina-->
    <div class="card card-flush shadow-sm">
        <div class="card-body p-6 p-lg-8">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-6">
                <div class="d-flex align-items-center gap-4 min-w-0">
                    <div class="symbol symbol-60px symbol-circle bg-light-primary flex-shrink-0">
                        <span class="symbol-label bg-light-primary text-primary fw-bolder fs-2x"><?php echo htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="d-flex flex-column min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="fw-bolder text-gray-900 mb-0 text-truncate" id="resumen_nombre"><?php echo htmlspecialchars($org_nombre !== '' ? $org_nombre : 'Mi vitrina', ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if ($org_status === 1): ?>
                                <span class="badge badge-light-success fw-bold">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-light-danger fw-bold">Inactivo</span>
                            <?php endif; ?>
                            <?php if ($org_verificado === 1): ?>
                                <span class="badge badge-light-primary fw-bold">
                                    <i class="ki-duotone ki-shield-tick fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Verificado
                                </span>
                            <?php else: ?>
                                <span class="badge badge-light-warning fw-bold">Pendiente de verificación</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 text-muted fs-7">
                            <span class="badge badge-light fs-8 fw-semibold"><?php echo htmlspecialchars($org_slug !== '' ? $org_slug : 'sin-subdominio', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="text-break"><?php echo htmlspecialchars($url_publica_host !== '' ? $url_publica_host : $url_publica_local, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                    <a href="<?php echo htmlspecialchars($url_ver_sitio, ENT_QUOTES, 'UTF-8'); ?>"
                        class="btn btn-sm btn-light-primary fw-semibold px-4 py-2"
                        id="btn_ver_sitio"
                        target="_blank"
                        rel="noopener noreferrer">
                        <i class="ki-duotone ki-arrow-up-right fs-4 me-1">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        Ver mi sitio
                    </a>
                </div>
            </div>

            <div class="separator separator-dashed my-6"></div>

            <div class="row g-5 align-items-center">
                <div class="col-lg-8">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">URL pública</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-gray-900 fw-semibold fs-7 text-break" id="resumen_url"><?php echo htmlspecialchars($url_publica_host !== '' ? $url_publica_host : $url_publica_local, ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs" id="btn_copiar_url" title="Copiar URL" aria-label="Copiar URL">
                            <i class="ki-duotone ki-copy fs-4"><span class="path1"></span><span class="path2"></span></i>
                        </button>
                    </div>
                    <?php if ($url_publica_local !== ''): ?>
                        <div class="text-muted fs-8 mt-1 text-break">
                            Acceso local:
                            <a href="<?php echo htmlspecialchars($url_publica_local, ENT_QUOTES, 'UTF-8'); ?>"
                                class="text-muted text-hover-primary fw-semibold"
                                target="_blank"
                                rel="noopener noreferrer"><?php echo htmlspecialchars($url_publica_local, ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <div class="border border-gray-200 rounded-3 bg-light p-4">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <div class="fw-bold text-gray-900 fs-7">Contacto público</div>
                                <div class="text-muted fs-8 text-break"><?php echo htmlspecialchars($org_email !== '' ? $org_email : 'Sin email configurado', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="text-muted fs-8"><?php echo htmlspecialchars($org_telefono !== '' ? $org_telefono : 'Sin teléfono configurado', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <i class="ki-duotone ki-sms fs-2x text-primary"><span class="path1"></span><span class="path2"></span></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Resumen de mi vitrina-->

    <!--begin::Mi Plan-->
    <div class="card card-flush shadow-sm">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center gap-3">
                    <div class="symbol symbol-40px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-crown fs-2 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h3 class="fw-bold text-gray-900 mb-1">Mi Plan</h3>
                        <span class="text-muted fs-7">Estado de tu plan de rifas y cupo disponible.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-2">
            <?php if ($plan_tiene): ?>
                <?php if (!empty($plan_lotes_render)): ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-5">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-bold text-gray-900 fs-5">Lotes disponibles</span>
                            <span class="badge badge-light-primary fw-bold px-3 py-2">
                                <?php echo count($plan_lotes_render); ?>
                                <?php echo count($plan_lotes_render) === 1 ? 'lote activo' : 'lotes activos'; ?>
                            </span>
                        </div>
                        <span class="badge badge-light-<?php echo htmlspecialchars($plan_estado_clase, ENT_QUOTES, 'UTF-8'); ?> fw-bold px-3 py-2">
                            <?php echo htmlspecialchars($plan_estado_texto, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                    <th scope="col" class="min-w-180px">Plan</th>
                                    <th scope="col" class="min-w-140px">Numeración</th>
                                    <th scope="col" class="min-w-110px">Cantidad</th>
                                    <th scope="col" class="min-w-110px">Usadas</th>
                                    <th scope="col" class="min-w-110px">Restantes</th>
                                    <th scope="col" class="min-w-170px">Vigencia</th>
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
                                        <td class="fw-semibold text-gray-900">
                                            <?php echo htmlspecialchars($lote_render['cantidad_texto'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lote_render['uso_texto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold text-gray-900">
                                            <?php echo htmlspecialchars($lote_render['restantes_texto'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="fs-8">
                                            <div class="text-gray-900 fw-semibold">Inicio: <?php echo htmlspecialchars($lote_render['inicio'] !== '' ? $lote_render['inicio'] : '—', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-muted">Fin: <?php echo htmlspecialchars($lote_render['fin'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
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
                        <span class="badge badge-light-<?php echo htmlspecialchars($plan_estado_clase, ENT_QUOTES, 'UTF-8'); ?> fw-bold px-3 py-2"><?php echo htmlspecialchars($plan_estado_texto, ENT_QUOTES, 'UTF-8'); ?></span>
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
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-45px symbol-circle bg-light-warning flex-shrink-0">
                            <i class="ki-duotone ki-information-2 fs-2x text-warning">
                                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                            </i>
                        </div>
                        <div class="d-flex flex-column">
                            <div class="fw-bold text-gray-900 fs-6">Sin plan activo</div>
                            <div class="text-muted fs-7">Contacta al administrador para asignarte un plan.</div>
                        </div>
                    </div>
                    <span class="badge badge-light-danger fw-bold px-3 py-2 align-self-start align-self-md-center">Sin plan activo</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!--end::Mi Plan-->

    <div class="row g-6 g-xl-9">
        <!--begin::Editar datos-->
        <div class="col-lg-6">
            <div class="card card-flush shadow-sm h-100">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="d-flex align-items-center gap-3">
                            <div class="symbol symbol-40px symbol-circle bg-light-primary">
                                <i class="ki-duotone ki-pencil fs-2 text-primary"><span class="path1"></span><span class="path2"></span></i>
                            </div>
                            <div class="d-flex flex-column">
                                <h3 class="fw-bold text-gray-900 mb-1">Datos de la vitrina</h3>
                                <span class="text-muted fs-7">Actualiza el nombre y los datos de contacto.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body pt-2">
                    <!--begin::Alerta de errores-->
                    <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_editar">
                        <ul class="mb-0 fs-7" id="lista_errores_editar"></ul>
                    </div>
                    <!--end::Alerta de errores-->

                    <form id="form_editar_vitrina" novalidate="novalidate">
                        <div class="row g-5">
                            <div class="col-12">
                                <label class="form-label required fw-semibold fs-7 text-gray-800" for="editar_org_nombre_publico">Nombre público</label>
                                <input type="text"
                                    name="org_nombre_publico"
                                    id="editar_org_nombre_publico"
                                    class="form-control form-control-solid"
                                    value="<?php echo htmlspecialchars($org_nombre, ENT_QUOTES, 'UTF-8'); ?>"
                                    minlength="3"
                                    maxlength="150"
                                    required />
                                <div class="invalid-feedback" data-error-for="org_nombre_publico"></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold fs-7 text-gray-800" for="editar_org_email">Email de contacto</label>
                                <input type="email"
                                    name="org_email"
                                    id="editar_org_email"
                                    class="form-control form-control-solid"
                                    value="<?php echo htmlspecialchars($org_email, ENT_QUOTES, 'UTF-8'); ?>"
                                    maxlength="150" />
                                <div class="invalid-feedback" data-error-for="org_email"></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold fs-7 text-gray-800" for="editar_org_telefono">Teléfono de contacto</label>
                                <input type="tel"
                                    name="org_telefono"
                                    id="editar_org_telefono"
                                    class="form-control form-control-solid"
                                    value="<?php echo htmlspecialchars($org_telefono, ENT_QUOTES, 'UTF-8'); ?>"
                                    maxlength="30" />
                                <div class="invalid-feedback" data-error-for="org_telefono"></div>
                            </div>

                            <div class="col-12 d-flex justify-content-end pt-2">
                                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_vitrina">
                                    <span class="indicator-label">
                                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Guardar cambios
                                    </span>
                                    <span class="indicator-progress">
                                        Guardando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!--end::Editar datos-->

        <!--begin::Cambiar subdominio-->
        <div class="col-lg-6">
            <div class="card card-flush shadow-sm h-100">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="d-flex align-items-center gap-3">
                            <div class="symbol symbol-40px symbol-circle bg-light-warning">
                                <i class="ki-duotone ki-route fs-2 text-warning">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                </i>
                            </div>
                            <div class="d-flex flex-column">
                                <h3 class="fw-bold text-gray-900 mb-1">Cambiar subdominio</h3>
                                <span class="text-muted fs-7">Actual: <span class="fw-semibold text-gray-800"><?php echo htmlspecialchars($org_slug !== '' ? $org_slug : 'sin-subdominio', ENT_QUOTES, 'UTF-8'); ?></span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body pt-2">
                    <!--begin::Alerta de errores-->
                    <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_slug_vitrina">
                        <ul class="mb-0 fs-7" id="lista_errores_slug_vitrina"></ul>
                    </div>
                    <!--end::Alerta de errores-->

                    <form id="form_cambiar_slug_vitrina" novalidate="novalidate">
                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="cambiar_org_slug">Nuevo subdominio</label>
                        <div class="input-group input-group-solid">
                            <input type="text"
                                name="org_slug"
                                id="cambiar_org_slug"
                                class="form-control form-control-solid"
                                value="<?php echo htmlspecialchars($org_slug, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="mi-nuevo-sorteo"
                                minlength="3"
                                maxlength="63"
                                pattern="[a-z0-9][a-z0-9-]{1,61}[a-z0-9]"
                                autocomplete="off"
                                spellcheck="false"
                                required />
                            <span class="input-group-text text-gray-700">.<?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="invalid-feedback d-block" data-error-for="org_slug"></div>
                        <div class="d-flex align-items-center justify-content-between gap-3 mt-2">
                            <div class="text-muted fs-8">Solo minúsculas, números y guiones. No puede iniciar ni terminar con guion.</div>
                            <div class="text-muted fs-8 flex-shrink-0"><span id="cambiar_slug_counter">0</span>/63</div>
                        </div>

                        <div class="border border-gray-200 rounded-3 bg-light p-4 mt-5">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Nueva URL pública</div>
                            <div class="text-gray-900 fw-semibold fs-7 text-break" id="cambiar_slug_preview">
                                https://<?php echo htmlspecialchars($org_slug !== '' ? $org_slug : 'tu-subdominio', ENT_QUOTES, 'UTF-8'); ?>.<?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="text-muted fs-8 mt-1 text-break" id="cambiar_slug_preview_local">
                                <?php echo htmlspecialchars($org_slug !== '' ? base_url('sitio/' . rawurlencode($org_slug)) : base_url('sitio/tu-subdominio'), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-start gap-3 mt-5 mb-0" role="alert">
                            <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                            </i>
                            <div class="text-gray-700 fs-7">
                                Al confirmar, la URL anterior dejará de estar disponible. Esta acción se valida en el servidor.
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-5">
                            <button type="button" class="btn btn-sm btn-warning fw-semibold px-4 py-2" id="btn_cambiar_slug_vitrina">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-route fs-4 me-1">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                    </i>
                                    Cambiar subdominio
                                </span>
                                <span class="indicator-progress">
                                    Cambiando...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!--end::Cambiar subdominio-->
    </div>
<?php endif; ?>

</div>

<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_DOMAIN = <?php echo json_encode($base_domain, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const URL_CREAR = <?php echo json_encode(base_url('mi-vitrina/crear'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const URL_ACTUALIZAR = <?php echo json_encode(base_url('mi-vitrina/actualizar'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const URL_CAMBIAR_SLUG = <?php echo json_encode(base_url('mi-vitrina/cambiar-slug'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const SITIO_BASE = <?php echo json_encode(base_url('sitio/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function mostrar_alerta(tipo, msg) {
        const titulos = {
            success: '¡Listo!',
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
            .slice(0, 63)
            .replace(/^-+|-+$/g, '');
    }

    function slug_valido(valor) {
        return /^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/.test(String(valor || ''));
    }

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

        const mapa = {};
        Object.keys(errores).forEach(function (clave) {
            const destino = clave === 'slug' ? 'org_slug' : clave;
            mapa[destino] = errores[clave];
        });

        let total = 0;
        Object.keys(mapa).forEach(function (campo) {
            const mensaje = mapa[campo];
            if (!mensaje) {
                return;
            }

            const input = form.querySelector('[name="' + String(campo).replace(/"/g, '\\"') + '"]');
            const feedback = form.querySelector('[data-error-for="' + String(campo).replace(/"/g, '\\"') + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = mensaje;
            }

            if (listaEl) {
                const li = document.createElement('li');
                li.textContent = mensaje;
                listaEl.appendChild(li);
            }
            total++;
        });

        if (total > 0 && alertEl) {
            alertEl.classList.remove('d-none');
        }
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

    function manejar_respuesta_error(res, form, alertEl, listaEl) {
        if (res.status === 401) {
            window.location.href = LOGIN_EXPIRED;
            return true;
        }

        if (res.status === 422 && res.data.errores) {
            mostrar_errores(form, alertEl, listaEl, res.data.errores);
            return true;
        }

        if (res.status === 409) {
            mostrar_alerta('warning', res.data.msg || 'Ese subdominio ya está en uso. Elige otro.');
            return true;
        }

        if (res.status === 403) {
            mostrar_alerta('error', res.data.msg || 'No tienes permiso para realizar esta acción.');
            return true;
        }

        if (res.data && res.data.errores) {
            mostrar_errores(form, alertEl, listaEl, res.data.errores);
            return true;
        }

        if (res.data && res.data.msg) {
            mostrar_alerta(res.status >= 500 ? 'error' : 'warning', res.data.msg);
            return true;
        }

        return false;
    }

    function slug_counter(input, contador) {
        if (!input || !contador) {
            return;
        }
        contador.textContent = String(input.value.length);
    }

    function actualizar_preview(input, hostEl, localEl, placeholder) {
        const slug = normalizar_slug(input ? input.value : '');
        const host = slug !== '' ? 'https://' + slug + '.' + BASE_DOMAIN : 'https://' + placeholder + '.' + BASE_DOMAIN;
        const local = slug !== '' ? SITIO_BASE + encodeURIComponent(slug) : SITIO_BASE + encodeURIComponent(placeholder);

        if (hostEl) {
            hostEl.textContent = host;
        }
        if (localEl) {
            localEl.textContent = local;
        }
    }

    function conectar_slug(input, contador, hostEl, localEl, placeholder) {
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            const normalizado = normalizar_slug(this.value);
            if (this.value !== normalizado) {
                this.value = normalizado;
            }
            slug_counter(this, contador);
            actualizar_preview(this, hostEl, localEl, placeholder);
        });

        slug_counter(input, contador);
        actualizar_preview(input, hostEl, localEl, placeholder);
    }

    // =====================================================
    // Estado sin vitrina: crear
    // =====================================================
    const formCrear = document.getElementById('form_crear_vitrina');
    if (formCrear) {
        const btnCrear = document.getElementById('btn_crear_vitrina');
        const alertCrear = document.getElementById('alert_errores_onboarding');
        const listaCrear = document.getElementById('lista_errores_onboarding');
        const inputSlug = document.getElementById('crear_org_slug');

        conectar_slug(
            inputSlug,
            document.getElementById('crear_slug_counter'),
            document.getElementById('crear_preview_host'),
            document.getElementById('crear_preview_local'),
            'tu-subdominio'
        );

        btnCrear.addEventListener('click', function () {
            const nombre = document.getElementById('crear_org_nombre_publico').value.trim();
            const slug = normalizar_slug(inputSlug.value);
            inputSlug.value = slug;

            if (nombre.length < 3) {
                mostrar_errores(formCrear, alertCrear, listaCrear, {
                    org_nombre_publico: 'El nombre público debe tener al menos 3 caracteres.'
                });
                return;
            }

            if (!slug_valido(slug)) {
                mostrar_errores(formCrear, alertCrear, listaCrear, {
                    org_slug: 'El subdominio debe tener entre 3 y 63 caracteres y solo puede usar minúsculas, números y guiones (sin guion al inicio ni al final).'
                });
                return;
            }

            const fd = new FormData(formCrear);
            fd.set('org_nombre_publico', nombre);
            fd.set('org_slug', slug);

            set_loading(btnCrear, true);
            limpiar_errores(formCrear, alertCrear, listaCrear);

            request_json(URL_CREAR, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (res) {
                set_loading(btnCrear, false);

                if (res.data && res.data.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Vitrina creada!',
                        text: res.data.msg || 'Tu vitrina está lista. Recargaremos la página para mostrarte el resumen.',
                        confirmButtonText: 'Continuar',
                        buttonsStyling: false,
                        customClass: { confirmButton: 'btn btn-sm btn-primary' }
                    }).then(function () {
                        window.location.reload();
                    });
                    return;
                }

                if (manejar_respuesta_error(res, formCrear, alertCrear, listaCrear)) {
                    return;
                }

                mostrar_alerta('error', 'No se pudo crear la vitrina. Inténtalo de nuevo.');
            })
            .catch(function () {
                set_loading(btnCrear, false);
                mostrar_alerta('error', 'Error de conexión con el servidor.');
            });
        });
    }

    // =====================================================
    // Estado con vitrina: editar
    // =====================================================
    const formEditar = document.getElementById('form_editar_vitrina');
    if (formEditar) {
        const btnGuardar = document.getElementById('btn_guardar_vitrina');
        const alertEditar = document.getElementById('alert_errores_editar');
        const listaEditar = document.getElementById('lista_errores_editar');

        btnGuardar.addEventListener('click', function () {
            const nombre = document.getElementById('editar_org_nombre_publico').value.trim();
            if (nombre.length < 3) {
                mostrar_errores(formEditar, alertEditar, listaEditar, {
                    org_nombre_publico: 'El nombre público debe tener al menos 3 caracteres.'
                });
                return;
            }

            const fd = new FormData(formEditar);
            fd.set('org_nombre_publico', nombre);

            set_loading(btnGuardar, true);
            limpiar_errores(formEditar, alertEditar, listaEditar);

            request_json(URL_ACTUALIZAR, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (res) {
                set_loading(btnGuardar, false);

                if (res.data && res.data.ok) {
                    const resumen = document.getElementById('resumen_nombre');
                    if (resumen) {
                        resumen.textContent = nombre;
                    }

                    const avatar = document.querySelector('.symbol-label');
                    if (avatar) {
                        avatar.textContent = nombre.charAt(0).toUpperCase();
                    }

                    mostrar_alerta('success', res.data.msg || 'Datos actualizados correctamente.');
                    return;
                }

                if (manejar_respuesta_error(res, formEditar, alertEditar, listaEditar)) {
                    return;
                }

                mostrar_alerta('error', 'No se pudieron actualizar los datos.');
            })
            .catch(function () {
                set_loading(btnGuardar, false);
                mostrar_alerta('error', 'Error de conexión con el servidor.');
            });
        });
    }

    // =====================================================
    // Estado con vitrina: cambiar subdominio
    // =====================================================
    const formSlug = document.getElementById('form_cambiar_slug_vitrina');
    if (formSlug) {
        const btnSlug = document.getElementById('btn_cambiar_slug_vitrina');
        const alertSlug = document.getElementById('alert_errores_slug_vitrina');
        const listaSlug = document.getElementById('lista_errores_slug_vitrina');
        const inputSlug = document.getElementById('cambiar_org_slug');

        conectar_slug(
            inputSlug,
            document.getElementById('cambiar_slug_counter'),
            document.getElementById('cambiar_slug_preview'),
            document.getElementById('cambiar_slug_preview_local'),
            'tu-subdominio'
        );

        btnSlug.addEventListener('click', function () {
            const slug = normalizar_slug(inputSlug.value);
            inputSlug.value = slug;

            if (!slug_valido(slug)) {
                mostrar_errores(formSlug, alertSlug, listaSlug, {
                    org_slug: 'El subdominio debe tener entre 3 y 63 caracteres y solo puede usar minúsculas, números y guiones (sin guion al inicio ni al final).'
                });
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: '¿Cambiar tu subdominio?',
                text: 'Tu URL pública pasará a ser ' + slug + '.' + BASE_DOMAIN + '. La URL anterior dejará de funcionar.',
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-warning',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                const fd = new FormData();
                fd.append('org_slug', slug);

                set_loading(btnSlug, true);
                limpiar_errores(formSlug, alertSlug, listaSlug);

                request_json(URL_CAMBIAR_SLUG, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(function (res) {
                    set_loading(btnSlug, false);

                    if (res.data && res.data.ok) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Subdominio actualizado',
                            text: res.data.msg || 'Recargaremos la página para reflejar la nueva URL.',
                            confirmButtonText: 'Continuar',
                            buttonsStyling: false,
                            customClass: { confirmButton: 'btn btn-sm btn-primary' }
                        }).then(function () {
                            window.location.reload();
                        });
                        return;
                    }

                    if (manejar_respuesta_error(res, formSlug, alertSlug, listaSlug)) {
                        return;
                    }

                    mostrar_alerta('error', 'No se pudo cambiar el subdominio.');
                })
                .catch(function () {
                    set_loading(btnSlug, false);
                    mostrar_alerta('error', 'Error de conexión con el servidor.');
                });
            });
        });
    }

    // =====================================================
    // Copiar URL pública
    // =====================================================
    const btnCopiar = document.getElementById('btn_copiar_url');
    const resumenUrl = document.getElementById('resumen_url');
    if (btnCopiar && resumenUrl) {
        btnCopiar.addEventListener('click', function () {
            const url = resumenUrl.textContent.trim();
            if (url === '') {
                return;
            }

            function mostrar_copiado() {
                mostrar_alerta('success', 'URL pública copiada al portapapeles.');
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(mostrar_copiado).catch(function () {
                    mostrar_alerta('error', 'No se pudo copiar la URL. Cópiala manualmente.');
                });
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                mostrar_copiado();
            } catch (e) {
                mostrar_alerta('error', 'No se pudo copiar la URL. Cópiala manualmente.');
            } finally {
                document.body.removeChild(textarea);
            }
        });
    }
});
</script>
<!--end::JavaScript-->
