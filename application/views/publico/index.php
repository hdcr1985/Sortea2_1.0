<?php
$tenant_nombre = trim((string)($tenant['org_nombre_publico'] ?? 'Organizador'));
$rifas = isset($rifas) && is_array($rifas) ? $rifas : [];
$media_base = isset($media_base) && is_string($media_base) && trim($media_base) !== ''
    ? rtrim(trim($media_base), '/') . '/'
    : '';
$limitar = function ($texto, $max) {
    $texto = trim(strip_tags((string)$texto));
    return function_exists('mb_substr') ? mb_substr($texto, 0, (int)$max, 'UTF-8') : substr($texto, 0, (int)$max);
};
?>
<section class="py-10 py-lg-14">
    <div class="container">
        <div class="row justify-content-center text-center mb-10">
            <div class="col-lg-8">
                <span class="badge badge-light-primary fw-bold px-4 py-2 mb-4">Rifas oficiales</span>
                <h1 class="display-5 fw-bold text-gray-900 mb-4">
                    <?php echo htmlspecialchars($tenant_nombre, ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <p class="text-muted fs-4 mb-0">
                    Participa en nuestras rifas activas. Elige tus números, reserva y sigue las instrucciones de pago.
                </p>
            </div>
        </div>

        <?php if (empty($rifas)): ?>
            <div class="card border border-gray-200 rounded-4">
                <div class="card-body text-center py-14">
                    <div class="symbol symbol-70px symbol-circle bg-light-primary mb-5">
                        <i class="ki-duotone ki-gift fs-2x text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <h3 class="fw-bold text-gray-900 mb-2">No hay rifas activas por ahora</h3>
                    <p class="text-muted fs-6 mb-0">Vuelve pronto para conocer las próximas rifas.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-6">
                <?php foreach ($rifas as $rifa): ?>
                    <?php
                    $rifa_id = (int)$rifa['rifa_id'];
                    $imagen = trim((string)($rifa['rifa_imagen'] ?? ''));
                    $imagen_url = ($imagen !== '' && $media_base !== '')
                        ? $media_base . 'rifa/' . $rifa_id
                        : '';
                    $disponibles = (int)($rifa['disponibles'] ?? 0);
                    $total = (int)($rifa['rifa_total_numeros'] ?? 0);
                    $fecha = !empty($rifa['rifa_fecha_sorteo']) ? date('d/m/Y H:i', strtotime($rifa['rifa_fecha_sorteo'])) : 'Por definir';
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card card-flush h-100 border border-gray-200 rounded-4 overflow-hidden">
                            <div class="ratio ratio-16x9 bg-light">
                                <?php if ($imagen_url !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($imagen_url, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars((string)$rifa['rifa_titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-100 h-100 object-fit-cover" loading="lazy" />
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-light-primary">
                                        <i class="ki-duotone ki-gift fs-4x text-primary">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column p-6">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                    <span class="badge <?php echo $disponibles > 0 ? 'badge-light-success' : 'badge-light-danger'; ?> fw-bold px-3 py-2">
                                        <?php echo $disponibles > 0 ? 'Disponible' : 'Agotada'; ?>
                                    </span>
                                    <span class="text-muted fs-8">Sorteo: <?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <h3 class="fw-bold text-gray-900 fs-4 mb-2">
                                    <?php echo htmlspecialchars((string)$rifa['rifa_titulo'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <p class="text-muted fs-7 flex-grow-1 mb-4">
                                    <?php echo htmlspecialchars($limitar((string)($rifa['rifa_descripcion'] ?? ''), 140), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted fs-8">Precio por número</span>
                                        <span class="fw-bold text-gray-900 fs-4">
                                            <?php echo number_format((float)($rifa['rifa_precio'] ?? 0), 2, '.', ','); ?>
                                            <span class="fs-7 text-primary"><?php echo htmlspecialchars((string)$rifa['rifa_moneda'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                    </div>
                                    <div class="d-flex flex-column text-end">
                                        <span class="text-muted fs-8">Disponibles</span>
                                        <span class="fw-bold text-gray-900 fs-5"><?php echo number_format($disponibles, 0, '.', ','); ?> / <?php echo number_format($total, 0, '.', ','); ?></span>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars((string)$rifa['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    class="btn btn-primary fw-bold w-100 <?php echo $disponibles <= 0 ? 'disabled' : ''; ?>">
                                    <i class="ki-duotone ki-gift fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                                    Ver rifa y participar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

