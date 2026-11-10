<?php
$estados = [
    'pendiente'   => ['badge-light-warning', 'Pendiente'],
    'en_revision' => ['badge-light-info', 'En revisión'],
    'pagada'      => ['badge-light-success', 'Pagada'],
    'rechazada'   => ['badge-light-danger', 'Rechazada'],
    'cancelada'   => ['badge-light-secondary', 'Cancelada'],
    'expirada'    => ['badge-light-secondary', 'Expirada'],
];
?>
<div class="d-flex flex-wrap flex-stack mb-6 gap-3">
    <div class="d-flex flex-column">
        <h1 class="fw-bold text-gray-900 fs-2 mb-1">Órdenes</h1>
        <span class="text-muted fs-7">Reservas y pagos de tus rifas. Aprueba o rechaza los pagos pendientes.</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?php echo base_url('mis-rifas'); ?>" class="btn btn-sm btn-light fw-semibold">
            <i class="ki-duotone ki-gift fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Mis rifas
        </a>
    </div>
</div>

<div class="card card-flush">
    <div class="card-header py-5">
        <div class="card-title">
            <span class="fw-bold text-gray-900 fs-4">Últimas órdenes</span>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fw-bold px-3 py-2"><?php echo count($ordenes); ?> orden(es)</span>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($ordenes)): ?>
            <div class="text-muted fs-7 py-6">Aún no hay órdenes para tus rifas.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4 mb-0">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-8 text-uppercase">
                            <th class="min-w-150px">Orden</th>
                            <th class="min-w-180px">Rifa</th>
                            <th class="min-w-180px">Comprador</th>
                            <th class="min-w-110px">Total</th>
                            <th class="min-w-100px text-center">Estado</th>
                            <th class="min-w-130px">Fecha</th>
                            <th class="min-w-180px text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $orden): ?>
                            <?php
                            $estado = (string)$orden['ord_estado'];
                            list($clase, $label) = $estados[$estado] ?? ['badge-light-secondary', $estado];
                            $pendiente = in_array($estado, ['pendiente', 'en_revision'], true);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-gray-900 fs-7"><?php echo htmlspecialchars((string)$orden['ord_codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-muted fs-8"><?php echo (int)$orden['ord_cantidad']; ?> número(s)</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars((string)$orden['rifa_titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-gray-900 fs-7"><?php echo htmlspecialchars((string)$orden['ord_comprador_nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-muted fs-8"><?php echo htmlspecialchars((string)$orden['ord_comprador_email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-gray-900 fs-7">
                                        <?php echo number_format((float)$orden['ord_total'], 2, '.', ','); ?>
                                        <span class="text-primary"><?php echo htmlspecialchars((string)$orden['ord_moneda'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $clase; ?> fw-bold px-3 py-2"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <span class="text-muted fs-8"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$orden['ord_created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-icon btn-light-info btn-ver-orden"
                                            data-id="<?php echo (int)$orden['ord_id']; ?>" data-bs-toggle="tooltip" title="Ver detalle">
                                            <i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        </button>
                                        <?php if ($pendiente): ?>
                                            <button type="button" class="btn btn-sm btn-icon btn-light-success btn-aprobar-orden"
                                                data-id="<?php echo (int)$orden['ord_id']; ?>" data-codigo="<?php echo htmlspecialchars((string)$orden['ord_codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-bs-toggle="tooltip" title="Aprobar pago">
                                                <i class="ki-duotone ki-check-circle fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-rechazar-orden"
                                                data-id="<?php echo (int)$orden['ord_id']; ?>" data-codigo="<?php echo htmlspecialchars((string)$orden['ord_codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-bs-toggle="tooltip" title="Rechazar pago">
                                                <i class="ki-duotone ki-cross-circle fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modal_orden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex flex-column">
                    <h4 class="modal-title fw-bold text-gray-900" id="modal_orden_titulo">Detalle de orden</h4>
                    <span class="text-muted fs-8" id="modal_orden_subtitulo">—</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body px-8 py-6" id="modal_orden_body">
                <div class="text-muted fs-7">Cargando orden…</div>
            </div>
            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const modalEl = document.getElementById('modal_orden');
    const modal = new bootstrap.Modal(modalEl);
    let accionEnCurso = false;

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function mostrar_alerta(tipo, msg) {
        Swal.fire({
            icon: tipo,
            title: tipo === 'success' ? '¡Éxito!' : (tipo === 'error' ? 'Error' : 'Atención'),
            text: msg,
            confirmButtonText: 'Aceptar',
            buttonsStyling: false,
            customClass: { confirmButton: 'btn btn-sm btn-primary' }
        });
    }

    function request_json(url, options) {
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (payload) {
                return { status: response.status, data: payload || {} };
            });
        });
    }

    function abrir_orden(id) {
        document.getElementById('modal_orden_body').innerHTML = '<div class="text-muted fs-7">Cargando orden…</div>';
        modal.show();

        request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) { window.location.href = LOGIN_EXPIRED; return; }
            if (!res.data || res.data.ok !== true || !res.data.data) {
                document.getElementById('modal_orden_body').innerHTML = '<div class="text-danger fs-7">No se pudo cargar la orden.</div>';
                return;
            }

            const data = res.data.data;
            const orden = data.orden || {};
            const tickets = Array.isArray(data.tickets) ? data.tickets : [];
            const snapshot = data.snapshot || {};
            const campos = Array.isArray(snapshot.campos) ? snapshot.campos : [];

            document.getElementById('modal_orden_titulo').textContent = 'Orden ' + (orden.ord_codigo || '');
            document.getElementById('modal_orden_subtitulo').textContent = (orden.rifa_titulo || '') + ' · ' + (orden.ord_estado || '');

            let html = '';
            html += '<div class="row g-4 mb-5">';
            html += '<div class="col-md-6"><div class="text-muted fs-8">Total</div><div class="fw-bold text-gray-900 fs-4">' + Number(orden.ord_total || 0).toLocaleString('es', {minimumFractionDigits:2}) + ' ' + escapeHtml(orden.ord_moneda) + '</div></div>';
            html += '<div class="col-md-6"><div class="text-muted fs-8">Comprador</div><div class="fw-semibold text-gray-900 fs-7">' + escapeHtml(orden.ord_comprador_nombre) + '</div><div class="text-muted fs-8">' + escapeHtml(orden.ord_comprador_email) + '</div></div>';
            html += '</div>';

            html += '<div class="fw-bold text-gray-900 fs-6 mb-2">Números</div><div class="d-flex flex-wrap gap-2 mb-5">';
            tickets.forEach(function (t) {
                html += '<span class="badge badge-light-primary fw-bold px-3 py-2">' + escapeHtml(t.tick_numero) + '</span>';
            });
            if (!tickets.length) html += '<span class="text-muted fs-8">Sin tickets activos.</span>';
            html += '</div>';

            html += '<div class="fw-bold text-gray-900 fs-6 mb-2">Método de pago</div>';
            html += '<div class="border border-gray-200 rounded-3 p-4 bg-light mb-5">';
            html += '<div class="fw-bold text-gray-900 fs-7">' + escapeHtml(snapshot.nombre || '—') + '</div>';
            if (snapshot.instrucciones) html += '<div class="text-muted fs-8 mt-1" style="white-space:pre-line;">' + escapeHtml(snapshot.instrucciones) + '</div>';
            if (campos.length) {
                html += '<div class="separator separator-dashed my-3"></div>';
                campos.forEach(function (c) {
                    html += '<div class="d-flex justify-content-between gap-3 fs-8 mb-1"><span class="text-muted">' + escapeHtml(c.etiqueta) + '</span><span class="fw-semibold text-gray-900">' + escapeHtml(c.valor) + '</span></div>';
                });
            }
            html += '</div>';

            if (orden.ord_notas_comprador) {
                html += '<div class="fw-bold text-gray-900 fs-6 mb-2">Notas del comprador</div><div class="text-muted fs-7 mb-4">' + escapeHtml(orden.ord_notas_comprador) + '</div>';
            }

            document.getElementById('modal_orden_body').innerHTML = html;
        }).catch(function () {
            document.getElementById('modal_orden_body').innerHTML = '<div class="text-danger fs-7">Error de conexión.</div>';
        });
    }

    function enviar_accion(ruta, id, datos, mensaje) {
        if (accionEnCurso) return;
        accionEnCurso = true;

        request_json(BASE_URL + ruta + '/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: datos || new FormData()
        }).then(function (res) {
            accionEnCurso = false;
            if (res.status === 401) { window.location.href = LOGIN_EXPIRED; return; }
            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || mensaje);
                window.location.reload();
                return;
            }
            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo completar la acción.');
        }).catch(function () {
            accionEnCurso = false;
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    document.querySelectorAll('.btn-ver-orden').forEach(function (btn) {
        btn.addEventListener('click', function () { abrir_orden(btn.getAttribute('data-id')); });
    });

    document.querySelectorAll('.btn-aprobar-orden').forEach(function (btn) {
        btn.addEventListener('click', function () {
            Swal.fire({
                icon: 'question',
                title: '¿Aprobar el pago?',
                text: 'La orden ' + btn.getAttribute('data-codigo') + ' quedará pagada y sus números asegurados.',
                showCancelButton: true,
                confirmButtonText: 'Sí, aprobar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-success',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(function (result) {
                if (result.isConfirmed) enviar_accion('aprobar', btn.getAttribute('data-id'), null, 'Orden aprobada.');
            });
        });
    });

    document.querySelectorAll('.btn-rechazar-orden').forEach(function (btn) {
        btn.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Rechazar orden',
                html: 'Indica el motivo para rechazar <strong>' + escapeHtml(btn.getAttribute('data-codigo')) + '</strong>.',
                input: 'textarea',
                inputLabel: 'Motivo (opcional)',
                inputAttributes: { maxlength: 255, 'aria-label': 'Motivo' },
                showCancelButton: true,
                confirmButtonText: 'Rechazar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-danger',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(function (result) {
                if (!result.isConfirmed) return;
                const fd = new FormData();
                fd.set('motivo', String(result.value || '').trim());
                enviar_accion('rechazar', btn.getAttribute('data-id'), fd, 'Orden rechazada.');
            });
        });
    });
});
</script>
