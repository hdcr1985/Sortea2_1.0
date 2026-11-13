<?php
$estado = (string)($orden['ord_estado'] ?? 'pendiente');
$estados = [
    'pendiente'   => ['badge-light-warning', 'Pendiente de pago'],
    'en_revision' => ['badge-light-info', 'En revisión'],
    'pagada'      => ['badge-light-success', 'Pagada'],
    'rechazada'   => ['badge-light-danger', 'Rechazada'],
    'cancelada'   => ['badge-light-secondary', 'Cancelada'],
    'expirada'    => ['badge-light-secondary', 'Expirada'],
];
list($estado_clase, $estado_label) = $estados[$estado] ?? ['badge-light-secondary', $estado];
$tickets = isset($orden['tickets']) && is_array($orden['tickets']) ? $orden['tickets'] : [];
$snapshot = isset($snapshot) && is_array($snapshot) ? $snapshot : [];
$campos = isset($snapshot['campos']) && is_array($snapshot['campos']) ? $snapshot['campos'] : [];
$rifa_url = base_url('rifa/' . rawurlencode((string)($orden['rifa_slug'] ?? '')));
$captura_url = isset($captura_url) && is_string($captura_url) ? trim($captura_url) : '';

$mask_email = function ($email) {
    $email = (string)$email;
    $pos = strpos($email, '@');
    if ($pos === false) {
        return $email !== '' ? substr($email, 0, 2) . '***' : '';
    }
    return substr($email, 0, 2) . '***' . substr($email, $pos);
};
$mask_phone = function ($valor) {
    $valor = (string)$valor;
    if ($valor === '') {
        return '';
    }
    return strlen($valor) > 4 ? '***' . substr($valor, -4) : '***';
};
$mask_doc = function ($valor) {
    $valor = (string)$valor;
    if ($valor === '') {
        return '';
    }
    return strlen($valor) > 4 ? '***' . substr($valor, -4) : '***';
};
$mask_nombre = function ($nombre) {
    $partes = preg_split('/\s+/', trim((string)$nombre));
    if (empty($partes)) {
        return '';
    }
    if (count($partes) === 1) {
        $inicial = function_exists('mb_substr')
            ? mb_substr($partes[0], 0, 1, 'UTF-8')
            : substr($partes[0], 0, 1);
        return $inicial . '***';
    }

    $salida = $partes[0];
    if (isset($partes[1]) && $partes[1] !== '') {
        $inicial = function_exists('mb_substr')
            ? mb_substr($partes[1], 0, 1, 'UTF-8')
            : substr($partes[1], 0, 1);
        $salida .= ' ' . (function_exists('mb_strtoupper') ? mb_strtoupper($inicial, 'UTF-8') : strtoupper($inicial)) . '.';
    }
    return $salida;
};
?>
<section class="py-8 py-lg-12">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6">
                    <a href="<?php echo htmlspecialchars($rifa_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light fw-semibold">
                        <i class="ki-duotone ki-arrow-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Volver a la rifa
                    </a>
                    <span class="badge <?php echo $estado_clase; ?> fw-bold px-4 py-2"><?php echo htmlspecialchars($estado_label, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="card card-flush border border-gray-200 rounded-4 mb-6">
                    <div class="card-body p-6 p-lg-8">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-4 mb-6">
                            <div class="d-flex flex-column">
                                <span class="text-muted fs-8">Código de orden</span>
                                <span class="fw-bold text-gray-900 fs-2"><?php echo htmlspecialchars((string)$orden['ord_codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-muted fs-8 mt-1"><?php echo htmlspecialchars((string)$orden['rifa_titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="d-flex flex-column text-end">
                                <span class="text-muted fs-8">Total</span>
                                <span class="fw-bold text-gray-900 fs-2">
                                    <?php echo number_format((float)$orden['ord_total'], 2, '.', ','); ?>
                                    <span class="fs-5 text-primary"><?php echo htmlspecialchars((string)$orden['ord_moneda'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>
                        </div>

                        <?php if ($estado === 'pagada'): ?>
                            <div class="alert bg-light-success border border-success border-dashed mb-6">
                                <div class="text-gray-800 fs-7 fw-semibold">Pago confirmado. ¡Tus números están asegurados!</div>
                            </div>
                        <?php elseif ($estado === 'pendiente' || $estado === 'en_revision'): ?>
                            <div class="alert bg-light-warning border border-warning border-dashed mb-6">
                                <div class="text-gray-800 fs-7">
                                    Realiza el pago con los datos de abajo. El organizador confirmará la orden.
                                    <?php if (!empty($orden['ord_reserva_expira_at'])): ?>
                                        <br><strong>Reserva válida hasta:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$orden['ord_reserva_expira_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($estado === 'rechazada'): ?>
                            <div class="alert bg-light-danger border border-danger border-dashed mb-6">
                                <div class="text-gray-800 fs-7">
                                    La orden fue rechazada.
                                    <?php if (!empty($orden['ord_motivo_rechazo'])): ?>
                                        <br><strong>Motivo:</strong> <?php echo htmlspecialchars((string)$orden['ord_motivo_rechazo'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($estado === 'expirada'): ?>
                            <div class="alert bg-light-secondary border border-secondary border-dashed mb-6">
                                <div class="text-gray-800 fs-7">La reserva expiró y los números fueron liberados.</div>
                            </div>
                        <?php endif; ?>

                        <h3 class="fw-bold text-gray-900 fs-5 mb-3">Números reservados</h3>
                        <div class="d-flex flex-wrap gap-2 mb-6">
                            <?php if (empty($tickets)): ?>
                                <span class="text-muted fs-7">La orden no tiene números activos.</span>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <span class="badge badge-light-primary fw-bold fs-6 px-3 py-2"><?php echo htmlspecialchars((string)$ticket['tick_numero'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="separator separator-dashed my-6"></div>

                        <h3 class="fw-bold text-gray-900 fs-5 mb-3">Datos de pago</h3>
                        <?php if (!empty($snapshot['nombre'])): ?>
                            <div class="border border-gray-200 rounded-3 p-4 bg-light mb-4">
                                <?php
                                $snap_imagen = trim((string)($snapshot['imagen'] ?? ''));
                                $snap_icono = (string)($snapshot['icono'] ?? 'ki-bill');
                                if (!preg_match('/^ki-[a-z0-9-]+$/', $snap_icono)) {
                                    $snap_icono = 'ki-bill';
                                }
                                ?>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div class="symbol symbol-45px symbol-circle bg-light-primary flex-shrink-0">
                                        <span class="symbol-label">
                                            <?php if ($snap_imagen !== ''): ?>
                                                <img src="<?php echo htmlspecialchars($snap_imagen, ENT_QUOTES, 'UTF-8'); ?>" alt=""
                                                    loading="lazy" referrerpolicy="no-referrer"
                                                    style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />
                                            <?php else: ?>
                                                <i class="ki-duotone <?php echo htmlspecialchars($snap_icono, ENT_QUOTES, 'UTF-8'); ?> fs-3 text-primary">
                                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                                </i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="fw-bold text-gray-900 fs-6"><?php echo htmlspecialchars((string)$snapshot['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <?php if (!empty($snapshot['descripcion'])): ?>
                                    <div class="text-muted fs-8 mb-2"><?php echo htmlspecialchars((string)$snapshot['descripcion'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($snapshot['instrucciones'])): ?>
                                    <div class="text-gray-700 fs-7"><?php echo nl2br(htmlspecialchars((string)$snapshot['instrucciones'], ENT_QUOTES, 'UTF-8')); ?></div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($campos)): ?>
                                <div class="table-responsive mb-6">
                                    <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3 mb-0">
                                        <tbody>
                                            <?php foreach ($campos as $campo): ?>
                                                <tr>
                                                    <td class="text-muted fs-7 w-50"><?php echo htmlspecialchars((string)($campo['etiqueta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars((string)($campo['valor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted fs-7 mb-6">El organizador no adjuntó datos de pago.</div>
                        <?php endif; ?>

                        <?php if (!empty($orden['ord_pago_soporte']) || $captura_url !== ''): ?>
                            <div class="separator separator-dashed my-6"></div>
                            <h3 class="fw-bold text-gray-900 fs-5 mb-3">Soporte de pago</h3>
                            <div class="row g-4 align-items-start">
                                <div class="col-md-7">
                                    <?php if (!empty($orden['ord_pago_soporte'])): ?>
                                        <div class="text-muted fs-8">Referencia / soporte</div>
                                        <div class="fw-semibold text-gray-900 fs-7 mb-3"><?php echo htmlspecialchars((string)$orden['ord_pago_soporte'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <div class="alert bg-light-info border border-info border-dashed mb-0">
                                        <div class="text-gray-700 fs-8">El organizador revisará este soporte antes de confirmar el pago.</div>
                                    </div>
                                </div>
                                <?php if ($captura_url !== ''): ?>
                                    <div class="col-md-5">
                                        <a href="<?php echo htmlspecialchars($captura_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="d-block border border-gray-200 rounded-3 overflow-hidden bg-light">
                                            <img src="<?php echo htmlspecialchars($captura_url, ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="Captura del pago"
                                                class="w-100 object-fit-contain"
                                                style="max-height:180px;" loading="lazy" />
                                        </a>
                                        <div class="text-muted fs-8 mt-1">Toca la imagen para ampliarla.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="separator separator-dashed my-6"></div>

                        <h3 class="fw-bold text-gray-900 fs-5 mb-3">Comprador</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted fs-8">Nombre</div>
                                <div class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars($mask_nombre($orden['ord_comprador_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted fs-8">Correo</div>
                                <div class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars($mask_email($orden['ord_comprador_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <?php if (!empty($orden['ord_comprador_telefono'])): ?>
                                <div class="col-md-6">
                                    <div class="text-muted fs-8">Teléfono</div>
                                    <div class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars($mask_phone($orden['ord_comprador_telefono']), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($orden['ord_comprador_documento'])): ?>
                                <div class="col-md-6">
                                    <div class="text-muted fs-8">Documento</div>
                                    <div class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars($mask_doc($orden['ord_comprador_documento']), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <div class="text-muted fs-8">Creada</div>
                                <div class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$orden['ord_created_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted fs-8">Cantidad</div>
                                <div class="fw-semibold text-gray-900 fs-7"><?php echo (int)$orden['ord_cantidad']; ?> número(s)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <a href="<?php echo htmlspecialchars($rifa_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light-primary fw-semibold">
                        Volver a la rifa
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
