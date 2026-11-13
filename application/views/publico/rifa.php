<?php
$rifa_id      = (int)$rifa['rifa_id'];
$titulo       = (string)$rifa['rifa_titulo'];
$media_base   = isset($media_base) && is_string($media_base) && trim($media_base) !== ''
    ? rtrim(trim($media_base), '/') . '/'
    : '';
$imagen_url   = (!empty($rifa['rifa_imagen']) && $media_base !== '')
    ? $media_base . 'rifa/' . $rifa_id
    : '';
$precio       = (float)($rifa['rifa_precio'] ?? 0);
$moneda       = (string)($rifa['rifa_moneda'] ?? 'USD');
$total        = (int)($rifa['rifa_total_numeros'] ?? 0);
$disponibles  = (int)($resumen['disponibles'] ?? 0);
$ocupados     = max(0, $total - $disponibles);
$porcentaje   = $total > 0 ? min(100, round(($ocupados / $total) * 100, 1)) : 0;
$modo         = (string)($rifa['rifa_modo_asignacion'] ?? 'manual');
$max_compra   = max(1, (int)($rifa['rifa_max_por_compra'] ?? 1));
$mostrar_res  = (int)($rifa['rifa_mostrar_reservados'] ?? 1);
$fecha_sorteo = !empty($rifa['rifa_fecha_sorteo']) ? date('d/m/Y H:i', strtotime($rifa['rifa_fecha_sorteo'])) : 'Por definir';
$metodos      = isset($metodos) && is_array($metodos) ? $metodos : [];
$imagenes     = isset($imagenes) && is_array($imagenes) ? $imagenes : [];
$galeria_js   = [];

foreach ($imagenes as $indice => $img) {
    $galeria_js[] = [
        'url' => (string)$img['url'],
        'alt' => 'Imagen ' . ($indice + 1) . ' de ' . $titulo,
    ];
}

$imagen_principal = $imagen_url;
if ($imagen_principal === '' && !empty($galeria_js[0]['url'])) {
    $imagen_principal = (string)$galeria_js[0]['url'];
}
?>
<style>
    .rifa-page {
        background:
            radial-gradient(circle at 8% 0%, rgba(var(--bs-primary-rgb), .11), transparent 34rem),
            radial-gradient(circle at 94% 8%, rgba(var(--bs-info-rgb), .07), transparent 30rem);
    }

    .rifa-reveal {
        animation: rifaFadeUp .65s cubic-bezier(.2, .8, .2, 1) both;
    }

    .rifa-reveal-delay-1 { animation-delay: .08s; }
    .rifa-reveal-delay-2 { animation-delay: .16s; }
    .rifa-reveal-delay-3 { animation-delay: .24s; }

    @keyframes rifaFadeUp {
        from { opacity: 0; transform: translateY(18px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .rifa-hero {
        position: relative;
        border-radius: 1.5rem;
        overflow: hidden;
        min-height: 380px;
        background: var(--bs-secondary-bg);
        box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, .14);
        isolation: isolate;
    }

    .rifa-hero-media,
    .rifa-hero-media img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }

    .rifa-hero-media img {
        object-fit: cover;
        transform: scale(1.01);
    }

    .rifa-hero-media::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(8, 15, 30, .04) 0%, rgba(8, 15, 30, .2) 42%, rgba(8, 15, 30, .88) 100%);
    }

    .rifa-hero-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .3), rgba(var(--bs-info-rgb), .18));
    }

    .rifa-hero-content {
        position: relative;
        z-index: 2;
        min-height: 380px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 2rem;
        color: #fff;
    }

    .rifa-hero-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 999px;
        background: rgba(255, 255, 255, .12);
        backdrop-filter: blur(10px);
        padding: .42rem .8rem;
        font-size: .78rem;
        font-weight: 700;
        color: #fff;
    }

    .rifa-hero-title {
        max-width: 48rem;
        color: #fff;
        font-size: clamp(2rem, 4vw, 4rem);
        line-height: 1.02;
        letter-spacing: -.04em;
        text-shadow: 0 3px 22px rgba(0, 0, 0, .35);
    }

    .rifa-hero-gallery {
        position: absolute;
        z-index: 3;
        top: 1.1rem;
        right: 1.1rem;
        border: 1px solid rgba(255, 255, 255, .25);
        background: rgba(8, 15, 30, .42);
        color: #fff;
        backdrop-filter: blur(12px);
        transition: transform .25s ease, background .25s ease;
    }

    .rifa-hero-gallery:hover {
        color: #fff;
        background: rgba(8, 15, 30, .68);
        transform: translateY(-2px);
    }

    .rifa-panel {
        border: 1px solid var(--bs-border-color);
        border-radius: 1.25rem;
        background: var(--bs-body-bg);
        box-shadow: 0 .85rem 2.8rem rgba(15, 23, 42, .07);
    }

    .rifa-panel-title {
        letter-spacing: -.025em;
    }

    .rifa-descripcion {
        line-height: 1.75;
    }

    .rifa-descripcion img {
        max-width: 100%;
        height: auto;
        border-radius: .85rem;
    }

    .rifa-descripcion table {
        width: 100%;
        border-collapse: collapse;
    }

    .rifa-descripcion th,
    .rifa-descripcion td {
        border: 1px solid var(--bs-border-color);
        padding: .55rem;
    }

    .rifa-descripcion p:last-child {
        margin-bottom: 0;
    }

    .rifa-progress-wrap {
        border: 1px solid var(--bs-border-color);
        border-radius: 1rem;
        background: rgba(var(--bs-primary-rgb), .045);
        padding: 1rem;
    }

    .rifa-progress {
        height: .8rem;
        border-radius: 999px;
        background: rgba(var(--bs-primary-rgb), .12);
        overflow: hidden;
    }

    .rifa-progress .progress-bar {
        border-radius: 999px;
        background: linear-gradient(90deg, var(--bs-primary), rgba(var(--bs-primary-rgb), .62));
        transition: width .65s cubic-bezier(.2, .8, .2, 1);
    }

    .rifa-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: end;
    }

    .rifa-search {
        position: relative;
    }

    .rifa-search .form-control {
        padding-left: 2.6rem;
        border-radius: .85rem;
    }

    .rifa-search-icon {
        position: absolute;
        left: .9rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--bs-secondary-color);
        pointer-events: none;
    }

    .rifa-search-clear {
        position: absolute;
        right: .45rem;
        top: 50%;
        transform: translateY(-50%);
    }

    .rifa-random {
        display: flex;
        align-items: end;
        gap: .45rem;
        padding: .45rem;
        border: 1px solid var(--bs-border-color);
        border-radius: .95rem;
        background: var(--bs-tertiary-bg);
    }

    .rifa-random .form-control {
        width: 4.25rem;
        border-radius: .7rem;
    }

    .rifa-number-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(58px, 1fr));
        gap: .55rem;
        max-height: 390px;
        overflow: auto;
        padding: .2rem;
        scrollbar-width: thin;
    }

    .rifa-number {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 52px;
        border: 1px solid var(--bs-border-color);
        border-radius: .8rem;
        background: var(--bs-body-bg);
        color: var(--bs-emphasis-color);
        font-size: .86rem;
        font-weight: 800;
        letter-spacing: -.02em;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease, color .18s ease;
        user-select: none;
    }

    .rifa-number:hover:not(:disabled) {
        border-color: var(--bs-primary);
        box-shadow: 0 .45rem 1.1rem rgba(var(--bs-primary-rgb), .18);
        transform: translateY(-2px);
    }

    .rifa-number:focus-visible {
        outline: 3px solid rgba(var(--bs-primary-rgb), .3);
        outline-offset: 2px;
    }

    .rifa-number.is-selected {
        border-color: var(--bs-primary);
        background: var(--bs-primary);
        color: var(--tenant-primary-contrast);
        box-shadow: 0 .6rem 1.3rem rgba(var(--bs-primary-rgb), .28);
        transform: translateY(-2px) scale(1.025);
    }

    .rifa-number.is-reserved {
        border-color: rgba(var(--bs-warning-rgb), .32);
        background: rgba(var(--bs-warning-rgb), .09);
        color: var(--bs-warning);
        cursor: not-allowed;
    }

    .rifa-number.is-paid {
        border-color: rgba(var(--bs-primary-rgb), .18);
        background: rgba(var(--bs-primary-rgb), .08);
        color: var(--bs-primary);
        cursor: not-allowed;
    }

    .rifa-number.is-blocked {
        border-color: rgba(var(--bs-danger-rgb), .22);
        background: rgba(var(--bs-danger-rgb), .07);
        color: var(--bs-danger);
        cursor: not-allowed;
    }

    .rifa-selected-panel {
        border: 1px dashed var(--bs-border-color);
        border-radius: 1rem;
        background: var(--bs-tertiary-bg);
        padding: .9rem 1rem;
    }

    .rifa-selected-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border: 1px solid rgba(var(--bs-primary-rgb), .22);
        border-radius: 999px;
        background: rgba(var(--bs-primary-rgb), .09);
        color: var(--bs-primary);
        padding: .3rem .55rem .3rem .7rem;
        font-size: .78rem;
        font-weight: 800;
        animation: rifaChipIn .2s ease both;
    }

    .rifa-selected-chip button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        border: 0;
        border-radius: 50%;
        background: rgba(var(--bs-primary-rgb), .13);
        color: inherit;
        font-size: .8rem;
        line-height: 1;
    }

    @keyframes rifaChipIn {
        from { opacity: 0; transform: scale(.9); }
        to { opacity: 1; transform: scale(1); }
    }

    .rifa-empty {
        border: 1px dashed var(--bs-border-color);
        border-radius: 1rem;
        padding: 2rem 1rem;
        text-align: center;
        color: var(--bs-secondary-color);
    }

    .rifa-checkout {
        border: 1px solid var(--bs-border-color);
        border-radius: 1.35rem;
        box-shadow: 0 1.3rem 3.5rem rgba(15, 23, 42, .12);
        overflow: hidden;
    }

    .rifa-checkout::before {
        content: "";
        display: block;
        height: 6px;
        background: linear-gradient(90deg, var(--bs-primary), rgba(var(--bs-primary-rgb), .45));
    }

    .rifa-section-label {
        display: flex;
        align-items: center;
        gap: .55rem;
        margin: 1.5rem 0 .75rem;
        color: var(--bs-emphasis-color);
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .rifa-section-label:first-of-type {
        margin-top: 0;
    }

    .rifa-section-label span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.45rem;
        height: 1.45rem;
        border-radius: 50%;
        background: rgba(var(--bs-primary-rgb), .12);
        color: var(--bs-primary);
        font-size: .72rem;
    }

    .rifa-metodos {
        display: grid;
        gap: .55rem;
    }

    .rifa-metodo {
        position: relative;
        display: block;
        cursor: pointer;
    }

    .rifa-metodo-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .rifa-metodo-card {
        display: flex;
        align-items: center;
        gap: .75rem;
        border: 1px solid var(--bs-border-color);
        border-radius: .95rem;
        background: var(--bs-body-bg);
        padding: .7rem .75rem;
        transition: border-color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .rifa-metodo-card:hover {
        border-color: rgba(var(--bs-primary-rgb), .55);
        transform: translateY(-1px);
    }

    .rifa-metodo-input:checked + .rifa-metodo-card {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), .075);
        box-shadow: 0 .5rem 1.2rem rgba(var(--bs-primary-rgb), .13);
    }

    .rifa-metodo-input:focus-visible + .rifa-metodo-card {
        outline: 3px solid rgba(var(--bs-primary-rgb), .25);
        outline-offset: 2px;
    }

    .rifa-metodo-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.55rem;
        height: 2.55rem;
        border-radius: .8rem;
        background: var(--bs-tertiary-bg);
        color: var(--bs-primary);
        overflow: hidden;
        flex-shrink: 0;
    }

    .rifa-metodo-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .rifa-metodo-info {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .rifa-metodo-name {
        color: var(--bs-emphasis-color);
        font-size: .86rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .rifa-metodo-desc {
        color: var(--bs-secondary-color);
        font-size: .72rem;
        line-height: 1.35;
        margin-top: .15rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rifa-metodo-radio {
        width: 1.1rem;
        height: 1.1rem;
        border: 2px solid var(--bs-border-color);
        border-radius: 50%;
        flex-shrink: 0;
        transition: box-shadow .2s ease, border-color .2s ease;
    }

    .rifa-metodo-input:checked + .rifa-metodo-card .rifa-metodo-radio {
        border-color: var(--bs-primary);
        box-shadow: inset 0 0 0 4px var(--bs-primary);
    }

    .rifa-metodo-detalle {
        border: 1px solid rgba(var(--bs-info-rgb), .28);
        border-radius: .9rem;
        background: rgba(var(--bs-info-rgb), .06);
        padding: .8rem .9rem;
        margin-top: .65rem;
    }

    .rifa-upload {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .25rem;
        border: 1.5px dashed var(--bs-border-color);
        border-radius: 1rem;
        background: var(--bs-tertiary-bg);
        padding: 1.15rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s ease, background .2s ease, transform .2s ease;
    }

    .rifa-upload:hover {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), .055);
        transform: translateY(-1px);
    }

    .rifa-upload-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.55rem;
        height: 2.55rem;
        border-radius: 50%;
        background: rgba(var(--bs-primary-rgb), .12);
        color: var(--bs-primary);
    }

    .rifa-upload-preview {
        position: relative;
        display: inline-flex;
        border-radius: .9rem;
        overflow: hidden;
        border: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
    }

    .rifa-upload-preview img {
        width: 100%;
        max-height: 180px;
        object-fit: contain;
    }

    .rifa-upload-preview button {
        position: absolute;
        top: .35rem;
        right: .35rem;
    }

    .rifa-gallery-thumb {
        display: block;
        width: 100%;
        border: 0;
        padding: 0;
        border-radius: .9rem;
        overflow: hidden;
        background: var(--bs-tertiary-bg);
        cursor: zoom-in;
        transition: transform .25s ease, box-shadow .25s ease;
    }

    .rifa-gallery-thumb:hover {
        transform: translateY(-4px) scale(1.015);
        box-shadow: 0 .8rem 1.8rem rgba(15, 23, 42, .16);
    }

    .rifa-gallery-thumb img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        transition: transform .35s ease;
    }

    .rifa-gallery-thumb:hover img {
        transform: scale(1.06);
    }

    .rifa-gallery-modal .modal-content {
        border: 0;
        border-radius: 1.25rem;
        overflow: hidden;
        background: #0b1220;
        color: #fff;
    }

    .rifa-gallery-stage {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: min(72vh, 640px);
        background: radial-gradient(circle at center, rgba(255, 255, 255, .08), transparent 65%);
    }

    .rifa-gallery-stage img {
        max-width: 100%;
        max-height: min(70vh, 620px);
        object-fit: contain;
    }

    .rifa-gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        border: 1px solid rgba(255, 255, 255, .18);
        background: rgba(255, 255, 255, .1);
        color: #fff;
        backdrop-filter: blur(10px);
    }

    .rifa-gallery-nav:hover {
        color: #fff;
        background: rgba(255, 255, 255, .2);
    }

    .rifa-gallery-nav.prev { left: 1rem; }
    .rifa-gallery-nav.next { right: 1rem; }

    .rifa-gallery-thumbs {
        display: flex;
        gap: .5rem;
        overflow-x: auto;
        padding: .75rem 1rem 1rem;
        background: rgba(255, 255, 255, .04);
    }

    .rifa-gallery-thumbs button {
        width: 4.5rem;
        height: 3.4rem;
        border: 2px solid transparent;
        border-radius: .6rem;
        overflow: hidden;
        padding: 0;
        background: transparent;
        opacity: .6;
        transition: opacity .2s ease, border-color .2s ease;
        flex-shrink: 0;
    }

    .rifa-gallery-thumbs button.active,
    .rifa-gallery-thumbs button:hover {
        border-color: #fff;
        opacity: 1;
    }

    .rifa-gallery-thumbs img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .rifa-checkout-sticky {
        position: sticky;
        top: 1rem;
    }

    @media (max-width: 991.98px) {
        .rifa-hero,
        .rifa-hero-content {
            min-height: 320px;
        }

        .rifa-checkout-sticky {
            position: static;
        }
    }

    @media (max-width: 575.98px) {
        .rifa-hero-content {
            padding: 1.25rem;
        }

        .rifa-toolbar {
            grid-template-columns: 1fr;
        }

        .rifa-random {
            justify-content: space-between;
        }

        .rifa-random .form-control {
            width: 4.75rem;
        }

        .rifa-number-grid {
            grid-template-columns: repeat(auto-fill, minmax(52px, 1fr));
            max-height: 340px;
        }

        .rifa-number {
            min-height: 48px;
            font-size: .8rem;
        }

        .rifa-gallery-stage {
            min-height: 52vh;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rifa-reveal,
        .rifa-selected-chip,
        .rifa-number,
        .rifa-gallery-thumb,
        .rifa-gallery-thumb img,
        .rifa-progress .progress-bar,
        .rifa-metodo-card,
        .rifa-upload {
            animation: none !important;
            transition: none !important;
        }
    }
</style>

<section class="rifa-page py-6 py-lg-10">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5 rifa-reveal">
            <a href="<?php echo htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light fw-semibold">
                <i class="ki-duotone ki-arrow-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                Volver a las rifas
            </a>
            <span class="badge <?php echo $disponibles > 0 ? 'badge-light-success' : 'badge-light-danger'; ?> fw-bold px-4 py-2">
                <?php echo $disponibles > 0 ? 'Rifa activa' : 'Agotada'; ?>
            </span>
        </div>

        <div class="rifa-hero rifa-reveal mb-6">
            <div class="rifa-hero-media">
                <?php if ($imagen_principal !== ''): ?>
                    <img src="<?php echo htmlspecialchars($imagen_principal, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php else: ?>
                    <div class="rifa-hero-placeholder">
                        <i class="ki-duotone ki-gift fs-5x text-white"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($galeria_js)): ?>
                <button type="button" class="btn btn-sm rifa-hero-gallery fw-semibold" id="abrir_galeria_rifa">
                    <i class="ki-duotone ki-picture fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Ver galería (<?php echo count($galeria_js); ?>)
                </button>
            <?php endif; ?>

            <div class="rifa-hero-content">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="rifa-hero-chip">
                        <i class="ki-duotone ki-calendar fs-5"><span class="path1"></span><span class="path2"></span></i>
                        <?php echo htmlspecialchars($fecha_sorteo, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="rifa-hero-chip">
                        <i class="ki-duotone ki-tag fs-5"><span class="path1"></span><span class="path2"></span></i>
                        <?php echo $modo === 'automatica' ? 'Asignación aleatoria' : 'Elige tus números'; ?>
                    </span>
                    <span class="rifa-hero-chip">
                        <i class="ki-duotone ki-people fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <?php echo number_format($disponibles, 0, '.', ','); ?> disponibles
                    </span>
                </div>
                <h1 class="rifa-hero-title fw-bold mb-3"><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="d-flex flex-wrap align-items-end gap-4">
                    <div>
                        <div class="text-white-50 fs-8 text-uppercase fw-bold letter-spacing-1">Precio por número</div>
                        <div class="fw-bold fs-1 lh-1">
                            <?php echo number_format($precio, 2, '.', ','); ?>
                            <span class="fs-4 opacity-75"><?php echo htmlspecialchars($moneda, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="ms-sm-auto">
                        <div class="text-white-50 fs-8 text-uppercase fw-bold letter-spacing-1">Progreso</div>
                        <div class="fw-bold fs-4"><?php echo number_format($ocupados, 0, '.', ','); ?> / <?php echo number_format($total, 0, '.', ','); ?> vendidos</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-6">
            <div class="col-lg-7">
                <div class="rifa-panel mb-6 rifa-reveal rifa-reveal-delay-1">
                    <div class="card-body p-6 p-lg-8">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                            <div>
                                <span class="badge badge-light-primary fw-bold px-3 py-2 mb-2">Sobre la rifa</span>
                                <h2 class="rifa-panel-title fw-bold text-gray-900 fs-2 mb-0">Una oportunidad real de ganar</h2>
                            </div>
                            <div class="symbol symbol-50px symbol-circle bg-light-primary">
                                <i class="ki-duotone ki-gift fs-2x text-primary"><span class="path1"></span><span class="path2"></span></i>
                            </div>
                        </div>

                        <div class="rifa-descripcion text-gray-700 fs-6">
                            <?php echo (string)($rifa['rifa_descripcion'] ?? ''); ?>
                        </div>

                        <?php if (!empty($rifa['rifa_condiciones'])): ?>
                            <div class="separator separator-dashed my-6"></div>
                            <h3 class="fw-bold text-gray-900 fs-5 mb-2">Condiciones</h3>
                            <div class="text-muted fs-7" style="white-space:pre-line;"><?php echo htmlspecialchars((string)$rifa['rifa_condiciones'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rifa-panel mb-6 rifa-reveal rifa-reveal-delay-2">
                    <div class="card-body p-6 p-lg-8">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                            <div>
                                <span class="badge badge-light-success fw-bold px-3 py-2 mb-2">Participa</span>
                                <h2 class="rifa-panel-title fw-bold text-gray-900 fs-2 mb-1"><?php echo $modo === 'automatica' ? 'Elige cuántos números quieres' : 'Elige tus números de la suerte'; ?></h2>
                                <p class="text-muted fs-7 mb-0"><?php echo $modo === 'automatica' ? 'El sistema seleccionará números disponibles al azar.' : 'Toca los números disponibles. Los seleccionados se guardan en tu orden.'; ?></p>
                            </div>
                            <?php if ($modo === 'manual'): ?>
                                <span class="badge badge-light-primary fw-bold px-3 py-2" id="checkout_seleccionados">0 seleccionados</span>
                            <?php endif; ?>
                        </div>

                        <div class="rifa-progress-wrap mb-5">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <span class="text-muted fs-8 fw-semibold">Progreso de la rifa</span>
                                <span class="fw-bold text-gray-900 fs-8" id="rifa_progreso_texto"><?php echo number_format($porcentaje, 0, '.', ','); ?>%</span>
                            </div>
                            <div class="progress rifa-progress" role="progressbar"
                                aria-valuenow="<?php echo (int)$porcentaje; ?>" aria-valuemin="0" aria-valuemax="100"
                                aria-label="Números vendidos">
                                <div class="progress-bar" id="rifa_progreso_bar" style="width: <?php echo (float)$porcentaje; ?>%"></div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                <span class="text-muted fs-8"><strong class="text-gray-900" id="rifa_progreso_vendidos"><?php echo number_format($ocupados, 0, '.', ','); ?></strong> vendidos</span>
                                <span class="text-muted fs-8"><strong class="text-gray-900" id="rifa_progreso_disponibles"><?php echo number_format($disponibles, 0, '.', ','); ?></strong> disponibles</span>
                            </div>
                        </div>

                        <?php if ($modo === 'automatica'): ?>
                            <div class="alert bg-light-info border border-info border-dashed mb-0">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="ki-duotone ki-information-5 fs-2 text-info mt-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <div>
                                        <div class="fw-bold text-gray-900 fs-7 mb-1">Asignación automática</div>
                                        <div class="text-gray-700 fs-7">Indica la cantidad en el resumen de compra. El sistema elegirá números disponibles al azar por ti.</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="rifa-toolbar mb-4">
                                <form id="form_buscar_numero" class="rifa-search" autocomplete="off">
                                    <label class="form-label fw-semibold fs-7 text-gray-800" for="buscar_numero">Buscar número</label>
                                    <div class="position-relative">
                                        <i class="ki-duotone ki-magnifier rifa-search-icon fs-5"><span class="path1"></span><span class="path2"></span></i>
                                        <input type="search" class="form-control form-control-solid" id="buscar_numero"
                                            placeholder="Ej: 05 o 128" inputmode="numeric" maxlength="12" />
                                        <button type="button" class="btn btn-sm btn-icon btn-light rifa-search-clear d-none" id="limpiar_busqueda" aria-label="Limpiar búsqueda">
                                            <i class="ki-duotone ki-cross fs-5"><span class="path1"></span><span class="path2"></span></i>
                                        </button>
                                    </div>
                                </form>

                                <div class="rifa-random">
                                    <div>
                                        <label class="form-label fw-semibold fs-8 text-gray-800 mb-1" for="azar_cantidad">Al azar</label>
                                        <input type="number" class="form-control form-control-sm form-control-solid" id="azar_cantidad"
                                            min="1" max="<?php echo (int)$max_compra; ?>" value="1" />
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary fw-bold" id="btn_azar">
                                        <i class="ki-duotone ki-switch fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Elegir
                                    </button>
                                </div>
                            </div>

                            <div class="rifa-selected-panel mb-4">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <span class="text-muted fs-8" id="checkout_seleccionados_resumen">Sin números seleccionados</span>
                                    <span class="fw-bold text-gray-900 fs-7" id="checkout_total_estimado">Total: <?php echo number_format(0, 2, '.', ','); ?> <?php echo htmlspecialchars($moneda, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3" id="checkout_seleccionados_chips"></div>
                            </div>

                            <div id="checkout_numeros" class="rifa-number-grid" aria-live="polite">
                                <div class="rifa-empty" style="grid-column:1/-1;">
                                    <span class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></span>
                                    Cargando números…
                                </div>
                            </div>
                            <div id="rifa_numeros_vacio" class="rifa-empty d-none mt-3">No se encontraron números para la búsqueda.</div>

                            <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                                <button type="button" class="btn btn-sm btn-light fw-semibold" id="checkout_prev">
                                    <i class="ki-duotone ki-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Anterior
                                </button>
                                <span class="text-muted fs-8" id="checkout_pagina">—</span>
                                <button type="button" class="btn btn-sm btn-light fw-semibold" id="checkout_next">
                                    Siguiente
                                    <i class="ki-duotone ki-right fs-4 ms-1"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                            </div>
                            <div class="text-muted fs-8 mt-2">Máximo <?php echo (int)$max_compra; ?> números por orden.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($galeria_js)): ?>
                    <div class="rifa-panel rifa-reveal rifa-reveal-delay-3">
                        <div class="card-body p-6">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-4">
                                <div>
                                    <h2 class="rifa-panel-title fw-bold text-gray-900 fs-4 mb-1">Galería</h2>
                                    <div class="text-muted fs-8">Conoce más de esta rifa.</div>
                                </div>
                                <span class="badge badge-light-primary fw-bold px-3 py-2"><?php echo count($galeria_js); ?> imágenes</span>
                            </div>
                            <div class="row g-3">
                                <?php foreach ($galeria_js as $indice => $img): ?>
                                    <div class="col-4 col-sm-3">
                                        <button type="button" class="rifa-gallery-thumb" data-gallery-index="<?php echo (int)$indice; ?>" aria-label="Abrir imagen <?php echo (int)$indice + 1; ?>">
                                            <img src="<?php echo htmlspecialchars((string)$img['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="<?php echo htmlspecialchars((string)$img['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                                                loading="lazy" />
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <div class="rifa-checkout rifa-checkout-sticky rifa-reveal rifa-reveal-delay-1">
                    <div class="card-body p-6 p-lg-8">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-5">
                            <div>
                                <span class="text-muted fs-8">Precio por número</span>
                                <div class="fw-bold text-gray-900 fs-1 lh-1">
                                    <?php echo number_format($precio, 2, '.', ','); ?>
                                    <span class="fs-5 text-primary"><?php echo htmlspecialchars($moneda, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="text-muted fs-8">Por número</span>
                                <div class="fw-bold text-gray-900 fs-4"><?php echo number_format($disponibles, 0, '.', ','); ?> <span class="fs-8 text-muted">disponibles</span></div>
                            </div>
                        </div>

                        <?php if ($precio <= 0): ?>
                            <div class="alert bg-light-info border border-info border-dashed mb-0">
                                <div class="text-gray-700 fs-7">Esta rifa no tiene un precio de ticket disponible.</div>
                            </div>
                        <?php elseif (empty($metodos)): ?>
                            <div class="alert bg-light-warning border border-warning border-dashed mb-0">
                                <div class="text-gray-700 fs-7">El organizador aún no tiene métodos de pago disponibles. Intenta más tarde.</div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger d-none mb-5 rounded-3" id="checkout_error" role="alert"></div>

                            <form id="checkout_form" enctype="multipart/form-data" novalidate="novalidate">
                                <input type="hidden" name="<?php echo htmlspecialchars($csrf_name, ENT_QUOTES, 'UTF-8'); ?>"
                                    value="<?php echo htmlspecialchars($csrf_hash, ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="hidden" name="checkout_token"
                                    value="<?php echo htmlspecialchars($checkout_token ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="hidden" name="numeros" id="checkout_numeros_input" value="" />

                                <div class="rifa-section-label"><span>1</span> Método de pago</div>
                                <div class="rifa-metodos" role="radiogroup" aria-label="Método de pago">
                                    <?php foreach ($metodos as $indice => $metodo): ?>
                                        <?php
                                        $metodo_id    = (int)$metodo['orgmet_id'];
                                        $metodo_icono = (string)($metodo['icono'] ?? 'ki-bill');
                                        if (!preg_match('/^ki-[a-z0-9-]+$/', $metodo_icono)) {
                                            $metodo_icono = 'ki-bill';
                                        }
                                        $metodo_imagen = trim((string)($metodo['imagen'] ?? ''));
                                        ?>
                                        <label class="rifa-metodo" for="checkout_metodo_<?php echo $metodo_id; ?>">
                                            <input class="rifa-metodo-input" type="radio" name="orgmet_id"
                                                id="checkout_metodo_<?php echo $metodo_id; ?>"
                                                value="<?php echo $metodo_id; ?>"
                                                data-nombre="<?php echo htmlspecialchars((string)$metodo['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-descripcion="<?php echo htmlspecialchars((string)($metodo['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-instrucciones="<?php echo htmlspecialchars((string)($metodo['instrucciones'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-icono="<?php echo htmlspecialchars($metodo_icono, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-imagen="<?php echo htmlspecialchars($metodo_imagen, ENT_QUOTES, 'UTF-8'); ?>"
                                                required />
                                            <span class="rifa-metodo-card">
                                                <span class="rifa-metodo-icon">
                                                    <?php if ($metodo_imagen !== ''): ?>
                                                        <img src="<?php echo htmlspecialchars($metodo_imagen, ENT_QUOTES, 'UTF-8'); ?>"
                                                            alt="" loading="lazy" referrerpolicy="no-referrer" />
                                                    <?php else: ?>
                                                        <i class="ki-duotone <?php echo htmlspecialchars($metodo_icono, ENT_QUOTES, 'UTF-8'); ?> fs-3">
                                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                                        </i>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="rifa-metodo-info">
                                                    <span class="rifa-metodo-name"><?php echo htmlspecialchars((string)$metodo['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="rifa-metodo-desc"><?php echo htmlspecialchars((string)($metodo['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </span>
                                                <span class="rifa-metodo-radio" aria-hidden="true"></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="invalid-feedback d-block" data-error-for="orgmet_id"></div>
                                <div class="rifa-metodo-detalle d-none" id="checkout_metodo_detalle"></div>

                                <?php if ($modo === 'automatica'): ?>
                                    <div class="rifa-section-label"><span>2</span> Cantidad</div>
                                    <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_cantidad">Cantidad de números</label>
                                    <input type="number" class="form-control form-control-solid" name="cantidad" id="checkout_cantidad"
                                        min="1" max="<?php echo (int)$max_compra; ?>" value="1" required />
                                    <div class="text-muted fs-8 mt-1">El sistema asignará números aleatorios disponibles. Máximo <?php echo (int)$max_compra; ?> por orden.</div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                        <span class="text-muted fs-8">Total estimado</span>
                                        <span class="fw-bold text-gray-900 fs-7" id="checkout_total_estimado_checkout">Total: <?php echo number_format(0, 2, '.', ','); ?> <?php echo htmlspecialchars($moneda, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="cantidad"></div>
                                <?php else: ?>
                                    <div class="rifa-section-label"><span>2</span> Tus números</div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <span class="text-muted fs-8" id="checkout_seleccionados_resumen_checkout">Sin números seleccionados</span>
                                        <span class="fw-bold text-gray-900 fs-7" id="checkout_total_estimado_checkout">Total: <?php echo number_format(0, 2, '.', ','); ?> <?php echo htmlspecialchars($moneda, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-2" id="checkout_seleccionados_chips_checkout"></div>
                                    <div class="invalid-feedback d-block" data-error-for="numeros"></div>
                                <?php endif; ?>

                                <div class="rifa-section-label"><span>3</span> Tus datos</div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_nombre">Nombre completo</label>
                                        <input type="text" class="form-control form-control-solid" name="nombre" id="checkout_nombre"
                                            maxlength="150" autocomplete="name" required />
                                        <div class="invalid-feedback" data-error-for="nombre"></div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_email">Correo electrónico</label>
                                        <input type="email" class="form-control form-control-solid" name="email" id="checkout_email"
                                            maxlength="150" autocomplete="email" required />
                                        <div class="invalid-feedback" data-error-for="email"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="checkout_telefono">Teléfono</label>
                                        <input type="tel" class="form-control form-control-solid" name="telefono" id="checkout_telefono"
                                            maxlength="50" autocomplete="tel" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="checkout_documento">Documento</label>
                                        <input type="text" class="form-control form-control-solid" name="documento" id="checkout_documento"
                                            maxlength="50" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="checkout_notas">Notas (opcional)</label>
                                        <textarea class="form-control form-control-solid" name="notas" id="checkout_notas"
                                            rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>

                                <div class="rifa-section-label"><span>4</span> Soporte de pago</div>
                                <div class="mb-4">
                                    <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_pago_soporte">Referencia o soporte</label>
                                    <input type="text" class="form-control form-control-solid" name="pago_soporte" id="checkout_pago_soporte"
                                        maxlength="120" placeholder="Ej: referencia, últimos 4 dígitos, Nro. de operación" required />
                                    <div class="invalid-feedback" data-error-for="pago_soporte"></div>
                                    <div class="text-muted fs-8 mt-1">Indica el dato que permita identificar tu pago.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_pago_captura">Captura del pago</label>
                                    <label class="rifa-upload" for="checkout_pago_captura" id="checkout_captura_drop">
                                        <span class="rifa-upload-icon">
                                            <i class="ki-duotone ki-upload fs-2"><span class="path1"></span><span class="path2"></span></i>
                                        </span>
                                        <span class="fw-bold text-gray-900 fs-7" id="checkout_captura_texto">Sube una foto o captura de pantalla</span>
                                        <span class="text-muted fs-8">JPG, PNG o WEBP · máximo 5 MB</span>
                                        <input type="file" name="pago_captura" id="checkout_pago_captura"
                                            class="d-none" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required />
                                    </label>
                                    <div class="rifa-upload-preview d-none mt-3" id="checkout_captura_preview_wrap">
                                        <img src="" alt="Vista previa de la captura de pago" id="checkout_captura_preview" />
                                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" id="checkout_captura_quitar" aria-label="Quitar captura">
                                            <i class="ki-duotone ki-cross fs-5"><span class="path1"></span><span class="path2"></span></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="pago_captura"></div>
                                </div>

                                <button type="submit" class="btn btn-primary fw-bold w-100 mt-5 py-3" id="checkout_submit">
                                    <span class="indicator-label">
                                        <i class="ki-duotone ki-lock-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Reservar y enviar pago
                                    </span>
                                    <span class="indicator-progress">
                                        Procesando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                                <div class="text-muted fs-8 text-center mt-3">
                                    Tu orden queda pendiente hasta que el organizador confirme el pago.
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($galeria_js)): ?>
    <div class="modal fade rifa-gallery-modal" id="modal_galeria_rifa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex flex-column">
                        <h3 class="modal-title text-white fw-bold fs-5" id="galeria_titulo"><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="text-white-50 fs-8" id="galeria_contador">1 / <?php echo count($galeria_js); ?></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                        <i class="ki-duotone ki-cross fs-1 text-white"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="rifa-gallery-stage">
                        <button type="button" class="btn btn-sm btn-icon rifa-gallery-nav prev" id="galeria_prev" aria-label="Imagen anterior">
                            <i class="ki-duotone ki-left fs-2"><span class="path1"></span><span class="path2"></span></i>
                        </button>
                        <img src="" alt="" id="galeria_imagen" />
                        <button type="button" class="btn btn-sm btn-icon rifa-gallery-nav next" id="galeria_next" aria-label="Imagen siguiente">
                            <i class="ki-duotone ki-right fs-2"><span class="path1"></span><span class="path2"></span></i>
                        </button>
                    </div>
                    <div class="rifa-gallery-thumbs" id="galeria_thumbnails"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const DISPONIBILIDAD_URL = <?php echo json_encode($disponibilidad_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const COMPRAR_URL = <?php echo json_encode($comprar_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const ALEATORIOS_URL = <?php echo json_encode($aleatorios_url ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CSRF_NAME = <?php echo json_encode($csrf_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CSRF_HASH = <?php echo json_encode($csrf_hash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MODO = <?php echo json_encode($modo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MAX_COMPRA = <?php echo (int)$max_compra; ?>;
    const PRECIO = <?php echo json_encode((float)$precio, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MONEDA = <?php echo json_encode($moneda, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const GALERIA = <?php echo json_encode($galeria_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;

    const form = document.getElementById('checkout_form');
    const errorBox = document.getElementById('checkout_error');
    const submitBtn = document.getElementById('checkout_submit');
    const numerosWrap = document.getElementById('checkout_numeros');
    const numerosVacio = document.getElementById('rifa_numeros_vacio');
    const seleccionadosBadge = document.getElementById('checkout_seleccionados');
    const seleccionadosResumen = document.getElementById('checkout_seleccionados_resumen');
    const seleccionadosResumenCheckout = document.getElementById('checkout_seleccionados_resumen_checkout');
    const seleccionadosChips = document.getElementById('checkout_seleccionados_chips');
    const seleccionadosChipsCheckout = document.getElementById('checkout_seleccionados_chips_checkout');
    const totalEstimado = document.getElementById('checkout_total_estimado');
    const totalEstimadoCheckout = document.getElementById('checkout_total_estimado_checkout');
    const numerosInput = document.getElementById('checkout_numeros_input');
    const paginaLabel = document.getElementById('checkout_pagina');
    const btnPrev = document.getElementById('checkout_prev');
    const btnNext = document.getElementById('checkout_next');
    const inputBuscar = document.getElementById('buscar_numero');
    const formBuscar = document.getElementById('form_buscar_numero');
    const btnLimpiarBusqueda = document.getElementById('limpiar_busqueda');
    const inputAzar = document.getElementById('azar_cantidad');
    const btnAzar = document.getElementById('btn_azar');
    const metodoDetalle = document.getElementById('checkout_metodo_detalle');
    const inputCaptura = document.getElementById('checkout_pago_captura');
    const capturaPreviewWrap = document.getElementById('checkout_captura_preview_wrap');
    const capturaPreview = document.getElementById('checkout_captura_preview');
    const capturaTexto = document.getElementById('checkout_captura_texto');
    const btnQuitarCaptura = document.getElementById('checkout_captura_quitar');

    let offset = 0;
    const limit = 200;
    let total = <?php echo (int)$total; ?>;
    let disponibles = <?php echo (int)$disponibles; ?>;
    let seleccionados = new Set();
    let cargando = false;
    let busqueda = '';
    let capturaUrl = '';
    let galeriaIndex = 0;

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function formatearNumero(valor) {
        return Number(valor || 0).toLocaleString('es', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function formatearDinero(valor) {
        return Number(valor || 0).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function mostrar_error(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function limpiar_error() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    function set_loading(activo) {
        if (!submitBtn) return;
        if (activo) {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        } else {
            submitBtn.removeAttribute('data-kt-indicator');
            submitBtn.disabled = false;
        }
    }

    function actualizar_progreso(totalNumeros, disponiblesNumeros) {
        total = parseInt(totalNumeros, 10) || 0;
        disponibles = parseInt(disponiblesNumeros, 10) || 0;

        const ocupados = Math.max(0, total - disponibles);
        const porcentaje = total > 0 ? Math.min(100, (ocupados / total) * 100) : 0;
        const barra = document.getElementById('rifa_progreso_bar');
        const texto = document.getElementById('rifa_progreso_texto');
        const vendidos = document.getElementById('rifa_progreso_vendidos');
        const disponiblesEl = document.getElementById('rifa_progreso_disponibles');

        if (barra) barra.style.width = porcentaje.toFixed(2) + '%';
        if (texto) texto.textContent = Math.round(porcentaje) + '%';
        if (vendidos) vendidos.textContent = formatearNumero(ocupados);
        if (disponiblesEl) disponiblesEl.textContent = formatearNumero(disponibles);

        const progress = barra ? barra.closest('.progress') : null;
        if (progress) progress.setAttribute('aria-valuenow', String(Math.round(porcentaje)));
    }

    function total_seleccionado() {
        if (MODO === 'manual') {
            return seleccionados.size;
        }

        const input = document.getElementById('checkout_cantidad');
        return input ? Math.max(0, parseInt(input.value, 10) || 0) : 0;
    }

    function actualizar_resumen() {
        const cantidad = total_seleccionado();
        const totalPagar = cantidad * PRECIO;
        const label = cantidad === 1 ? '1 número seleccionado' : cantidad + ' números seleccionados';

        if (seleccionadosBadge) {
            seleccionadosBadge.textContent = MODO === 'manual'
                ? (seleccionados.size + (seleccionados.size === 1 ? ' seleccionado' : ' seleccionados'))
                : label;
        }

        if (seleccionadosResumen) {
            seleccionadosResumen.textContent = cantidad > 0 ? label : 'Sin números seleccionados';
        }

        if (seleccionadosResumenCheckout) {
            seleccionadosResumenCheckout.textContent = cantidad > 0 ? label : 'Sin números seleccionados';
        }

        const totalTexto = 'Total: ' + formatearDinero(totalPagar) + ' ' + MONEDA;
        if (totalEstimado) totalEstimado.textContent = totalTexto;
        if (totalEstimadoCheckout) totalEstimadoCheckout.textContent = totalTexto;

        if (numerosInput) {
            numerosInput.value = MODO === 'manual' ? Array.from(seleccionados).join(',') : '';
        }

        render_chips();
    }

    function render_chips() {
        const chips = MODO === 'manual' ? Array.from(seleccionados).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }) : [];

        [seleccionadosChips, seleccionadosChipsCheckout].forEach(function (contenedor) {
            if (!contenedor) return;
            contenedor.innerHTML = '';

            if (!chips.length) {
                contenedor.innerHTML = '<span class="text-muted fs-8">Toca un número disponible para agregarlo.</span>';
                return;
            }

            chips.forEach(function (numero) {
                const chip = document.createElement('span');
                chip.className = 'rifa-selected-chip';
                chip.innerHTML = '<span>' + escapeHtml(numero) + '</span>' +
                    '<button type="button" data-quitar-numero="' + escapeHtml(numero) + '" aria-label="Quitar ' + escapeHtml(numero) + '">&times;</button>';
                contenedor.appendChild(chip);
            });
        });
    }

    function estado_clase(estado) {
        if (estado === 'reservado') return 'is-reserved';
        if (estado === 'pagado') return 'is-paid';
        if (estado === 'bloqueado') return 'is-blocked';
        return 'is-available';
    }

    function estado_label(estado) {
        if (estado === 'reservado') return 'Reservado';
        if (estado === 'pagado') return 'Vendido';
        if (estado === 'bloqueado') return 'Bloqueado';
        return 'Disponible';
    }

    function render_numeros(data, esBusqueda) {
        if (!numerosWrap) return;

        numerosWrap.innerHTML = '';

        const totalRecibido = parseInt(data.total_numeros, 10);
        const disponiblesRecibido = parseInt(data.disponibles, 10);

        if (!isNaN(totalRecibido)) total = totalRecibido;
        if (!isNaN(disponiblesRecibido)) disponibles = disponiblesRecibido;

        actualizar_progreso(total, disponibles);

        const items = Array.isArray(data.items) ? data.items : [];

        if (!items.length) {
            if (numerosVacio) {
                numerosVacio.classList.remove('d-none');
                numerosVacio.textContent = esBusqueda
                    ? 'No encontramos ese número en la rifa.'
                    : 'No hay números disponibles.';
            }
        } else {
            if (numerosVacio) numerosVacio.classList.add('d-none');

            items.forEach(function (item) {
                const numero = String(item.numero);
                const estado = String(item.estado || 'disponible');
                const disponible = estado === 'disponible';
                const seleccionado = seleccionados.has(numero);

                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'rifa-number ' + estado_clase(estado) + (seleccionado ? ' is-selected' : '');
                boton.textContent = numero;
                boton.dataset.numero = numero;
                boton.dataset.estado = estado;
                boton.disabled = !disponible;
                boton.setAttribute('aria-pressed', seleccionado ? 'true' : 'false');
                boton.setAttribute('aria-label', 'Número ' + numero + ', ' + estado_label(estado));
                boton.title = estado_label(estado);

                numerosWrap.appendChild(boton);
            });
        }

        if (paginaLabel) {
            if (esBusqueda) {
                paginaLabel.textContent = 'Resultado de búsqueda';
            } else {
                const totalPaginas = total > 0 ? Math.ceil(total / limit) : 0;
                const pagina = Math.floor(offset / limit) + 1;
                paginaLabel.textContent = totalPaginas > 0 ? 'Página ' + pagina + ' de ' + totalPaginas : 'Sin números';
            }
        }

        if (btnPrev) btnPrev.disabled = esBusqueda || offset <= 0;
        if (btnNext) btnNext.disabled = esBusqueda || total <= 0 || (offset + limit) >= total;

        actualizar_resumen();
    }

    function cargar_numeros() {
        if (cargando || !numerosWrap || MODO !== 'manual') return;

        cargando = true;
        numerosWrap.innerHTML = '<div class="rifa-empty" style="grid-column:1/-1;"><span class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></span>Cargando números…</div>';

        let url = DISPONIBILIDAD_URL + '?offset=' + offset + '&limit=' + limit;
        if (busqueda !== '') {
            url += '&buscar=' + encodeURIComponent(busqueda);
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                cargando = false;

                if (!payload || payload.ok !== true || !payload.data) {
                    numerosWrap.innerHTML = '<div class="rifa-empty" style="grid-column:1/-1;">No se pudieron cargar los números.</div>';
                    return;
                }

                render_numeros(payload.data, busqueda !== '');
            })
            .catch(function () {
                cargando = false;
                numerosWrap.innerHTML = '<div class="rifa-empty" style="grid-column:1/-1;">Error de conexión al cargar números.</div>';
            });
    }

    function alternar_numero(numero, boton) {
        if (!boton || boton.disabled || boton.dataset.estado !== 'disponible') {
            return;
        }

        if (seleccionados.has(numero)) {
            seleccionados.delete(numero);
            boton.classList.remove('is-selected');
            boton.setAttribute('aria-pressed', 'false');
        } else {
            if (seleccionados.size >= MAX_COMPRA) {
                mostrar_error('Puedes seleccionar hasta ' + MAX_COMPRA + ' números por orden.');
                return;
            }

            seleccionados.add(numero);
            boton.classList.add('is-selected');
            boton.setAttribute('aria-pressed', 'true');
        }

        actualizar_resumen();
    }

    if (numerosWrap) {
        numerosWrap.addEventListener('click', function (event) {
            const boton = event.target.closest('.rifa-number');
            if (!boton) return;
            alternar_numero(String(boton.dataset.numero || ''), boton);
        });
    }

    [seleccionadosChips, seleccionadosChipsCheckout].forEach(function (contenedor) {
        if (!contenedor) return;
        contenedor.addEventListener('click', function (event) {
            const boton = event.target.closest('[data-quitar-numero]');
            if (!boton) return;

            const numero = String(boton.getAttribute('data-quitar-numero') || '');
            seleccionados.delete(numero);
            const chipNumero = numerosWrap ? numerosWrap.querySelector('.rifa-number[data-numero="' + CSS.escape(numero) + '"]') : null;
            if (chipNumero) {
                chipNumero.classList.remove('is-selected');
                chipNumero.setAttribute('aria-pressed', 'false');
            }
            actualizar_resumen();
        });
    });

    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            if (busqueda === '' && offset > 0) {
                offset = Math.max(0, offset - limit);
                cargar_numeros();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (busqueda === '' && (offset + limit) < total) {
                offset += limit;
                cargar_numeros();
            }
        });
    }

    if (formBuscar) {
        formBuscar.addEventListener('submit', function (event) {
            event.preventDefault();
            const valor = inputBuscar ? inputBuscar.value.trim() : '';

            if (valor === '') {
                busqueda = '';
                offset = 0;
                if (btnLimpiarBusqueda) btnLimpiarBusqueda.classList.add('d-none');
                cargar_numeros();
                return;
            }

            if (!/^\d+$/.test(valor)) {
                mostrar_error('Escribe solo el número que deseas buscar.');
                return;
            }

            busqueda = valor;
            offset = 0;
            if (btnLimpiarBusqueda) btnLimpiarBusqueda.classList.remove('d-none');
            limpiar_error();
            cargar_numeros();
        });
    }

    if (btnLimpiarBusqueda) {
        btnLimpiarBusqueda.addEventListener('click', function () {
            busqueda = '';
            offset = 0;
            if (inputBuscar) inputBuscar.value = '';
            btnLimpiarBusqueda.classList.add('d-none');
            cargar_numeros();
        });
    }

    if (btnAzar) {
        btnAzar.addEventListener('click', function () {
            if (MODO !== 'manual') return;

            const cantidad = Math.max(1, Math.min(MAX_COMPRA, parseInt(inputAzar ? inputAzar.value : '1', 10) || 1));

            limpiar_error();
            btnAzar.disabled = true;
            const textoOriginal = btnAzar.innerHTML;
            btnAzar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Eligiendo';

            fetch(ALEATORIOS_URL + '?cantidad=' + encodeURIComponent(cantidad), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return { status: response.status, data: payload || {} };
                    });
                })
                .then(function (res) {
                    btnAzar.disabled = false;
                    btnAzar.innerHTML = textoOriginal;

                    if (!res.data || res.data.ok !== true || !res.data.data || !Array.isArray(res.data.data.items)) {
                        mostrar_error((res.data && res.data.msg) || 'No se pudo hacer la selección al azar.');
                        return;
                    }

                    seleccionados = new Set(res.data.data.items.map(function (item) {
                        return String(item.numero);
                    }));
                    busqueda = '';
                    offset = 0;
                    if (inputBuscar) inputBuscar.value = '';
                    if (btnLimpiarBusqueda) btnLimpiarBusqueda.classList.add('d-none');
                    actualizar_resumen();
                    cargar_numeros();
                })
                .catch(function () {
                    btnAzar.disabled = false;
                    btnAzar.innerHTML = textoOriginal;
                    mostrar_error('Error de conexión al elegir números al azar.');
                });
        });
    }

    const inputCantidad = document.getElementById('checkout_cantidad');
    if (inputCantidad) {
        inputCantidad.addEventListener('input', actualizar_resumen);
    }

    const metodosRadios = document.querySelectorAll('input[name="orgmet_id"]');
    function actualizar_metodo_detalle() {
        if (!metodoDetalle) return;

        const seleccionado = document.querySelector('input[name="orgmet_id"]:checked');
        if (!seleccionado) {
            metodoDetalle.classList.add('d-none');
            metodoDetalle.innerHTML = '';
            return;
        }

        const instrucciones = seleccionado.getAttribute('data-instrucciones') || '';
        const descripcion = seleccionado.getAttribute('data-descripcion') || '';

        if (instrucciones === '' && descripcion === '') {
            metodoDetalle.classList.add('d-none');
            metodoDetalle.innerHTML = '';
            return;
        }

        let html = '<div class="fw-bold text-gray-900 fs-7 mb-1">' + escapeHtml(seleccionado.getAttribute('data-nombre') || 'Método de pago') + '</div>';
        if (descripcion !== '') {
            html += '<div class="text-muted fs-8">' + escapeHtml(descripcion) + '</div>';
        }
        if (instrucciones !== '') {
            html += '<div class="text-gray-700 fs-8 mt-1" style="white-space:pre-line;">' + escapeHtml(instrucciones) + '</div>';
        }
        html += '<div class="text-muted fs-8 mt-2">Al confirmar, la orden quedará en revisión.</div>';

        metodoDetalle.innerHTML = html;
        metodoDetalle.classList.remove('d-none');
    }

    metodosRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            actualizar_metodo_detalle();
            const feedback = document.querySelector('[data-error-for="orgmet_id"]');
            if (feedback) feedback.textContent = '';
        });
    });

    if (inputCaptura) {
        inputCaptura.addEventListener('change', function () {
            const archivo = inputCaptura.files && inputCaptura.files[0];

            if (!archivo) {
                limpiar_preview_captura();
                return;
            }

            if (!/^image\/(jpeg|png|webp)$/i.test(archivo.type)) {
                mostrar_error('La captura debe ser una imagen JPG, PNG o WEBP.');
                inputCaptura.value = '';
                limpiar_preview_captura();
                return;
            }

            if (archivo.size > 5 * 1024 * 1024) {
                mostrar_error('La captura no puede superar 5 MB.');
                inputCaptura.value = '';
                limpiar_preview_captura();
                return;
            }

            if (capturaUrl) URL.revokeObjectURL(capturaUrl);
            capturaUrl = URL.createObjectURL(archivo);

            if (capturaPreview) capturaPreview.src = capturaUrl;
            if (capturaPreviewWrap) capturaPreviewWrap.classList.remove('d-none');
            if (capturaTexto) capturaTexto.textContent = archivo.name;

            const feedback = document.querySelector('[data-error-for="pago_captura"]');
            if (feedback) feedback.textContent = '';
        });
    }

    function limpiar_preview_captura() {
        if (capturaUrl) {
            URL.revokeObjectURL(capturaUrl);
            capturaUrl = '';
        }
        if (capturaPreview) capturaPreview.removeAttribute('src');
        if (capturaPreviewWrap) capturaPreviewWrap.classList.add('d-none');
        if (capturaTexto) capturaTexto.textContent = 'Sube una foto o captura de pantalla';
    }

    if (btnQuitarCaptura) {
        btnQuitarCaptura.addEventListener('click', function () {
            if (inputCaptura) inputCaptura.value = '';
            limpiar_preview_captura();
        });
    }

    function mostrar_errores_campos(errores) {
        if (!errores || typeof errores !== 'object') return;

        Object.keys(errores).forEach(function (campo) {
            const mensaje = String(errores[campo] || '');
            const feedback = document.querySelector('[data-error-for="' + CSS.escape(campo) + '"]');
            if (feedback) feedback.textContent = mensaje;

            const input = document.querySelector('[name="' + CSS.escape(campo) + '"]');
            if (input) {
                input.classList.toggle('is-invalid', mensaje !== '');
            }
        });
    }

    function limpiar_errores_campos() {
        document.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
        });
        document.querySelectorAll('#checkout_form .is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            limpiar_error();
            limpiar_errores_campos();

            const metodo = document.querySelector('input[name="orgmet_id"]:checked');
            if (!metodo) {
                mostrar_error('Selecciona un método de pago.');
                const feedback = document.querySelector('[data-error-for="orgmet_id"]');
                if (feedback) feedback.textContent = 'Selecciona un método de pago.';
                return;
            }

            if (MODO === 'manual' && seleccionados.size === 0) {
                mostrar_error('Selecciona al menos un número.');
                const feedback = document.querySelector('[data-error-for="numeros"]');
                if (feedback) feedback.textContent = 'Selecciona al menos un número.';
                return;
            }

            if (MODO === 'automatica') {
                const cantidad = parseInt(document.getElementById('checkout_cantidad').value, 10) || 0;
                if (cantidad < 1 || cantidad > MAX_COMPRA) {
                    mostrar_error('Indica una cantidad entre 1 y ' + MAX_COMPRA + '.');
                    return;
                }
            }

            const soporte = document.getElementById('checkout_pago_soporte');
            if (!soporte || soporte.value.trim() === '') {
                mostrar_error('Indica el soporte o referencia del pago.');
                if (soporte) soporte.classList.add('is-invalid');
                const feedback = document.querySelector('[data-error-for="pago_soporte"]');
                if (feedback) feedback.textContent = 'El soporte de pago es obligatorio.';
                return;
            }

            if (!inputCaptura || !inputCaptura.files || inputCaptura.files.length === 0) {
                mostrar_error('Adjunta la captura del pago.');
                const feedback = document.querySelector('[data-error-for="pago_captura"]');
                if (feedback) feedback.textContent = 'Adjunta la captura del pago.';
                return;
            }

            const fd = new FormData(form);
            if (!fd.has(CSRF_NAME)) {
                fd.append(CSRF_NAME, CSRF_HASH);
            }
            fd.set('orgmet_id', metodo.value);
            fd.set('pago_soporte', soporte.value.trim());

            if (MODO === 'manual') {
                fd.set('numeros', Array.from(seleccionados).join(','));
                fd.delete('cantidad');
            } else {
                fd.set('cantidad', String(parseInt(document.getElementById('checkout_cantidad').value, 10) || 0));
                fd.delete('numeros');
            }

            set_loading(true);

            fetch(COMPRAR_URL, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function (response) {
                return response.json().then(function (payload) {
                    return { status: response.status, data: payload || {} };
                });
            }).then(function (res) {
                set_loading(false);

                if (res.data && res.data.ok && res.data.orden && res.data.orden.url) {
                    window.location.href = res.data.orden.url;
                    return;
                }

                if (res.data && res.data.errores) {
                    mostrar_errores_campos(res.data.errores);
                }

                mostrar_error((res.data && res.data.msg) || 'No se pudo crear la reserva.');

                if (res.status === 409 && MODO === 'manual') {
                    seleccionados = new Set();
                    actualizar_resumen();
                    cargar_numeros();
                }
            }).catch(function () {
                set_loading(false);
                mostrar_error('Error de conexión con el servidor.');
            });
        });
    }

    if (MODO === 'manual') {
        cargar_numeros();
    } else {
        actualizar_resumen();
    }

    actualizar_metodo_detalle();

    const galeriaModalEl = document.getElementById('modal_galeria_rifa');
    const galeriaModal = galeriaModalEl && window.bootstrap ? new bootstrap.Modal(galeriaModalEl) : null;
    const galeriaImagen = document.getElementById('galeria_imagen');
    const galeriaContador = document.getElementById('galeria_contador');
    const galeriaThumbs = document.getElementById('galeria_thumbnails');

    function mostrar_galeria(indice) {
        if (!Array.isArray(GALERIA) || GALERIA.length === 0 || !galeriaImagen) return;

        galeriaIndex = (indice + GALERIA.length) % GALERIA.length;
        const item = GALERIA[galeriaIndex];

        galeriaImagen.src = item.url;
        galeriaImagen.alt = item.alt || 'Imagen de la rifa';
        if (galeriaContador) galeriaContador.textContent = (galeriaIndex + 1) + ' / ' + GALERIA.length;

        if (galeriaThumbs) {
            galeriaThumbs.querySelectorAll('button').forEach(function (thumb, i) {
                thumb.classList.toggle('active', i === galeriaIndex);
            });
        }
    }

    function abrir_galeria(indice) {
        mostrar_galeria(indice);
        if (galeriaModal) galeriaModal.show();
    }

    if (galeriaThumbs) {
        GALERIA.forEach(function (item, indice) {
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = indice === 0 ? 'active' : '';
            thumb.setAttribute('aria-label', 'Ver imagen ' + (indice + 1));
            thumb.innerHTML = '<img src="' + escapeHtml(item.url) + '" alt="" loading="lazy" />';
            thumb.addEventListener('click', function () { mostrar_galeria(indice); });
            galeriaThumbs.appendChild(thumb);
        });
    }

    document.querySelectorAll('[data-gallery-index]').forEach(function (el) {
        el.addEventListener('click', function () {
            abrir_galeria(parseInt(el.getAttribute('data-gallery-index'), 10) || 0);
        });
    });

    const abrirGaleria = document.getElementById('abrir_galeria_rifa');
    if (abrirGaleria) {
        abrirGaleria.addEventListener('click', function () { abrir_galeria(0); });
    }

    const galeriaPrev = document.getElementById('galeria_prev');
    const galeriaNext = document.getElementById('galeria_next');

    if (galeriaPrev) galeriaPrev.addEventListener('click', function () { mostrar_galeria(galeriaIndex - 1); });
    if (galeriaNext) galeriaNext.addEventListener('click', function () { mostrar_galeria(galeriaIndex + 1); });

    if (galeriaModalEl) {
        galeriaModalEl.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') mostrar_galeria(galeriaIndex - 1);
            if (event.key === 'ArrowRight') mostrar_galeria(galeriaIndex + 1);
        });
    }
});
</script>
