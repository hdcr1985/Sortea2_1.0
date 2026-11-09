<!--begin::Buy Créditos View-->
<div class="d-flex flex-column gap-7 gap-lg-10">

    <!--begin::Info Banner-->
    <!--<div class="card card-flush bg-light-primary border-0 shadow-xs">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between p-6 p-lg-8 gap-4">
            <div class="d-flex align-items-center gap-4">
                <div class="symbol symbol-50px symbol-circle bg-primary text-white d-flex align-items-center justify-content-center">
                    <i class="ki-duotone ki-diamond fs-2x text-white">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                </div>
                <div>
                    <h3 class="fw-bolder text-gray-900 mb-1">Obtén créditos para tu cuenta</h3>
                    <div class="text-gray-700 fs-7">
                        Los créditos comprados <strong>no expiran</strong> y se acreditan automáticamente al confirmar el pago.
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge badge-light-success fw-bold px-3 py-2">
                    <i class="ki-duotone ki-shield-tick fs-5 text-success me-1"><span class="path1"></span><span class="path2"></span></i>
                    Pagos 100% seguros
                </span>
            </div>
        </div>
    </div>-->
    <!--end::Info Banner-->

    <!--begin::Pending Order Alert (if exists)-->
    <?php if (!empty($orden_pendiente)): ?>
    <div class="alert alert-dismissible bg-light-warning border border-warning border-dashed d-flex flex-column flex-sm-row p-5 mb-2 shadow-xs" id="alerta_orden_pendiente">
        <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4 mb-3 mb-sm-0">
            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
        </i>
        <div class="d-flex flex-column pe-0 pe-sm-10 flex-grow-1">
            <h5 class="fw-bold text-gray-900 mb-1">Tienes una orden de compra pendiente (#<?php echo htmlspecialchars($orden_pendiente['mov_id']); ?>)</h5>
            <div class="text-gray-700 fs-7 mb-2">
                Realizaste una orden de <strong><?php echo htmlspecialchars($orden_pendiente['mov_nomplan']); ?></strong> con <strong><?php echo number_format((int)$orden_pendiente['mov_sms'], 0, ',', '.'); ?> créditos</strong> por un total de <strong><?php echo number_format((float)$orden_pendiente['mov_precioplan'], 2, ',', '.'); ?> <?php echo htmlspecialchars($orden_pendiente['mov_moneda']); ?></strong>.
            </div>
            <?php
                $pend_status         = strtolower(trim((string)($orden_pendiente['mov_statuspag'] ?? '')));
                $pend_mov_status     = strtoupper(trim((string)($orden_pendiente['mov_status'] ?? '')));
                $pend_gateway        = strtolower(trim((string)($orden_pendiente['mov_tippago'] ?? '')));
                $pend_session        = trim((string)($orden_pendiente['mov_stripe_session_id'] ?? ''));
                $pend_reference      = trim((string)($orden_pendiente['mov_idpago'] ?? ''));
                $pend_es_stripe      = $pend_session !== '' || $pend_gateway === 'stripe';
                $pend_es_crypto      = in_array($pend_gateway, ['btcpay', 'btc_pay', 'crypto', 'bitcoin'], TRUE);
                $pend_legacy_stripe  = $pend_session === '' && $pend_es_stripe && $pend_reference !== '';
                $pend_ref_desconocida = $pend_session === '' && $pend_reference !== ''
                    && !$pend_es_stripe && !$pend_es_crypto;
                $pend_puede_card     = $pend_mov_status === 'A'
                    && in_array($pend_status, ['nopagado', 'waiting'], TRUE);
                $pend_puede_crypto   = in_array($pend_mov_status, ['A', 'V'], TRUE)
                    && in_array($pend_status, ['nopagado', 'waiting', 'in_process_partial'], TRUE);
                $pend_puede_cancelar = in_array($pend_mov_status, ['A', 'V'], TRUE)
                    && in_array($pend_status, ['nopagado', 'waiting'], TRUE);

                $mostrar_card = $pend_puede_card
                    && !empty($orden_pendiente['pla_paypal'])
                    && (int)$orden_pendiente['pla_paypal'] === 1
                    && !empty($stripe_disponible)
                    && !$pend_es_crypto
                    && !$pend_legacy_stripe
                    && !$pend_ref_desconocida;

                $mostrar_crypto = $pend_puede_crypto
                    && !empty($orden_pendiente['pla_crypto'])
                    && (int)$orden_pendiente['pla_crypto'] === 1
                    && !empty($btcpay_disponible)
                    && !$pend_es_stripe
                    && !$pend_ref_desconocida;

                $mostrar_cancelar = $pend_puede_cancelar && !$pend_legacy_stripe && !$pend_ref_desconocida;
            ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($mostrar_card): ?>
                <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-3 py-1 btn-pagar-stripe" data-id="<?php echo htmlspecialchars($orden_pendiente['mov_id']); ?>">
                    <i class="ki-duotone ki-credit-cart fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $pend_status === 'waiting' ? 'Reanudar pago con tarjeta' : 'Pagar con tarjeta'; ?>
                </button>
                <?php endif; ?>
                <?php if ($mostrar_crypto): ?>
                <button type="button" class="btn btn-sm btn-light-warning fw-semibold px-3 py-1 btn-pagar-crypto" data-id="<?php echo htmlspecialchars($orden_pendiente['mov_id']); ?>">
                    <i class="ki-duotone ki-bitcoin fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $pend_status === 'in_process_partial' ? 'Completar pago cripto' : 'Pagar con criptomonedas'; ?>
                </button>
                <?php endif; ?>
                <?php if ($mostrar_cancelar): ?>
                <button type="button" class="btn btn-sm btn-light-danger fw-semibold px-3 py-1 btn-cancelar-orden" data-id="<?php echo htmlspecialchars($orden_pendiente['mov_id']); ?>">
                    <i class="ki-duotone ki-trash fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    Cancelar esta orden
                </button>
                <?php endif; ?>
                <?php if (!$mostrar_card && !$mostrar_crypto && !$mostrar_cancelar): ?>
                <span class="text-muted fs-8 align-self-center">
                    <?php if ($pend_legacy_stripe): ?>
                        Esta orden está vinculada a un pago con tarjeta heredado. Contacta al administrador.
                    <?php elseif ($pend_ref_desconocida): ?>
                        Esta orden ya tiene una referencia de pago. Contacta al administrador.
                    <?php else: ?>
                        Esta orden se está procesando y no se puede modificar en línea en este momento.
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!--end::Pending Order Alert-->

    <!--begin::Plans Showcase (Pricing Grid)-->
    <div>
        <div class="text-center mb-8">
            <h2 class="fw-bold text-gray-900 fs-2x mb-2">Planes disponibles</h2>
            <p class="text-muted fs-6">Elige el paquete de créditos que mejor se adapte a tus necesidades de verificación</p>
        </div>

        <?php if (empty($planes)): ?>
        <div class="card card-flush shadow-sm">
            <div class="card-body text-center py-12">
                <i class="ki-duotone ki-information-4 fs-4x text-muted mb-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                <h4 class="text-gray-800 fw-bold">No hay planes activos disponibles en este momento</h4>
                <p class="text-muted fs-7">Vuelve a intentarlo más tarde o contacta a soporte.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-6 g-xl-9 justify-content-center">
            <?php foreach ($planes as $p): ?>
                <?php
                    $id_plan     = (int)$p['pla_id'];
                    $nombre      = htmlspecialchars($p['pla_nombre'], ENT_QUOTES, 'UTF-8');
                    $descripcion = htmlspecialchars($p['pla_descripcion'] ?? 'Acceso completo a créditos', ENT_QUOTES, 'UTF-8');
                    $creditos    = (int)($p['pla_numero'] ?? 0);
                    $precio      = number_format((float)($p['pla_precio'] ?? 0), 2, ',', '.');
                    $moneda      = htmlspecialchars($p['pla_moneda'] ?? 'USD', ENT_QUOTES, 'UTF-8');
                    $stripe      = ((int)($p['pla_paypal'] ?? 0) === 1) && !empty($stripe_disponible);
                    $crypto      = ((int)($p['pla_crypto'] ?? 0) === 1) && !empty($btcpay_disponible);
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card card-flush h-100 shadow-sm border border-gray-200 hover-elevate-up transition-300">
                        <div class="card-header pt-6 pb-2 border-0">
                            <div class="d-flex flex-column">
                                <span class="badge badge-light-primary fw-bold fs-7 mb-2 align-self-start">
                                    <i class="ki-duotone ki-diamond fs-6 text-primary me-1"><span class="path1"></span><span class="path2"></span></i>
                                    <?php echo number_format($creditos, 0, ',', '.'); ?> Créditos
                                </span>
                                <h3 class="card-title fw-bolder text-gray-900 fs-3 mb-1"><?php echo $nombre; ?></h3>
                                <p class="text-muted fs-7 mb-0"><?php echo $descripcion; ?></p>
                            </div>
                        </div>

                        <div class="card-body pt-2 pb-6">
                            <!--begin::Price-->
                            <div class="d-flex align-items-baseline my-4 p-4 rounded-3 bg-light">
                                <span class="fs-1 fw-bolder text-gray-900 me-2"><?php echo $precio; ?></span>
                                <span class="fs-6 fw-bold text-primary"><?php echo $moneda; ?></span>
                                <span class="text-muted fs-8 ms-auto">Pago único</span>
                            </div>
                            <!--end::Price-->

                            <!--begin::Supported Payment Icons-->
                            <div class="d-flex align-items-center gap-2 mb-6">
                                <?php if ($stripe): ?>
                                    <span class="badge badge-light-primary p-2" data-bs-toggle="tooltip" title="Tarjeta de crédito / débito">
                                        <i class="ki-duotone ki-credit-cart fs-3 text-primary m-0">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                    </span>
                                <?php endif; ?>
                                <?php if ($crypto): ?>
                                    <span class="badge badge-light-warning p-2" data-bs-toggle="tooltip" title="Criptomonedas">
                                        <i class="ki-duotone ki-bitcoin fs-3 text-warning m-0">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <!--end::Supported Payment Icons-->

                            <!--begin::Features-->
                            <div class="separator separator-dashed mb-5"></div>
                            <div class="d-flex flex-column gap-3 mb-6 fs-7 text-gray-700">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-check-circle fs-5 text-success me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <span>Los créditos no expiran</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-check-circle fs-5 text-success me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <span>Verificaciones SMS instantáneas</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-check-circle fs-5 text-success me-2"><span class="path1"></span><span class="path2"></span></i>
                                    <span>Recarga automática de créditos al pagar</span>
                                </div>
                            </div>
                            <!--end::Features-->

                            <!--begin::Buy Button-->
                            <button type="button"
                                class="btn btn-sm btn-primary w-100 fw-bold py-3 btn-iniciar-compra shadow-xs"
                                data-id="<?php echo $id_plan; ?>"
                                data-nombre="<?php echo $nombre; ?>">
                                <i class="ki-duotone ki-basket fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                Comprar plan
                            </button>
                            <!--end::Buy Button-->
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <!--end::Plans Showcase-->

    <!--begin::Order History Card (DataTables)-->
    <div class="card card-flush shadow-sm mt-6">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex flex-column">
                    <h3 class="fw-bold text-gray-900 mb-1">Historial de compras</h3>
                    <span class="text-muted fs-7">Consulta órdenes, pagos y recargas de créditos.</span>
                </div>
            </div>
            <div class="card-toolbar gap-3 flex-wrap">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text"
                        id="filtro_ordenes"
                        class="form-control form-control-solid w-250px mw-100 ps-12 pe-10"
                        placeholder="Buscar órdenes..."
                        aria-label="Buscar órdenes" />
                    <button type="button"
                        class="btn btn-icon btn-sm position-absolute top-50 translate-middle-y end-0 me-2 d-none"
                        id="btn_limpiar_filtro"
                        title="Limpiar búsqueda"
                        aria-label="Limpiar búsqueda">
                        <i class="ki-duotone ki-cross fs-5"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                </div>
                <select id="filtro_estado"
                    class="form-select form-select-solid w-150px"
                    aria-label="Filtrar órdenes por estado">
                    <option value="">Todos los estados</option>
                    <option value="unpaid">Impagada</option>
                    <option value="waiting">Pendiente de pago</option>
                    <option value="processing">Procesando</option>
                    <option value="paid">Pagada</option>
                    <option value="refunded">Reembolsada</option>
                    <option value="expired">Expirada</option>
                    <option value="cancelled">Cancelada</option>
                    <option value="rejected">Rechazada</option>
                </select>
                <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4 py-2" id="btn_recargar_ordenes">
                    <i class="ki-duotone ki-arrows-circle fs-4 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Actualizar
                </button>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_ordenes">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                            <th scope="col" class="min-w-150px">Orden #</th>
                            <th scope="col" class="min-w-200px">Plan</th>
                            <th scope="col" class="min-w-125px text-end">Créditos</th>
                            <th scope="col" class="min-w-125px text-end">Monto</th>
                            <th scope="col" class="min-w-150px text-center">Estado del pago</th>
                            <th scope="col" class="min-w-175px">Fecha de la orden</th>
                            <th scope="col" class="min-w-120px text-end pe-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold"></tbody>
                </table>
            </div>
        </div>
    </div>
    <!--end::Order History Card-->

</div>
<!--end::Buy Créditos View-->

<!--begin::Confirm Purchase Modal-->
<div class="modal fade" id="modal_confirmar_compra" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">

            <div class="modal-header py-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-basket fs-3 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="conf_titulo">Confirmar compra del plan</h4>
                        <span class="text-muted fs-8">Revisa los detalles de tu orden antes de continuar</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body py-6 px-8">
                <input type="hidden" id="conf_plan_id" value="0" />

                <!--begin::Plan Summary-->
                <div class="border border-gray-200 rounded-3 p-5 bg-light mb-6">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fs-7">Plan seleccionado:</span>
                        <span class="fw-bold text-gray-900 fs-6" id="conf_plan_nombre">-</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fs-7">Créditos a recibir:</span>
                        <span class="badge badge-light-primary fw-bold fs-7" id="conf_plan_creditos">-</span>
                    </div>
                    <div class="separator separator-dashed my-3"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-gray-900 fs-6">Monto total a pagar:</span>
                        <span class="fw-bolder text-primary fs-4" id="conf_plan_precio">-</span>
                    </div>
                </div>
                <!--end::Plan Summary-->

                <div class="text-gray-700 fs-8 mb-2">
                    <i class="ki-duotone ki-information-2 text-info fs-6 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    Al confirmar, se generará tu orden de compra. Luego podrás completar el pago con el método que elijas.
                </div>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-success fw-semibold px-4 py-2" id="btn_confirmar_procesar">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Confirmar y crear orden
                    </span>
                    <span class="indicator-progress">
                        Procesando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>

        </div>
    </div>
</div>
<!--end::Confirm Purchase Modal-->

<!--begin::Module JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const modalConfirmarEl = document.getElementById('modal_confirmar_compra');
    const modalConfirmar = new bootstrap.Modal(modalConfirmarEl);
    const btnConfirmar = document.getElementById('btn_confirmar_procesar');
    const inputFiltro = document.getElementById('filtro_ordenes');
    const btnLimpiarFiltro = document.getElementById('btn_limpiar_filtro');
    const filtroEstado = document.getElementById('filtro_estado');
    const btnRecargar = document.getElementById('btn_recargar_ordenes');

    let dt = null;
    let filtroTimer = null;

    function setActualizarLoading(loading) {
        if (!btnRecargar) return;

        if (loading) {
            if (!btnRecargar.dataset.originalHtml) {
                btnRecargar.dataset.originalHtml = btnRecargar.innerHTML;
            }
            btnRecargar.disabled = true;
            btnRecargar.setAttribute('aria-busy', 'true');
            btnRecargar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Actualizando...';
            return;
        }

        btnRecargar.disabled = false;
        btnRecargar.removeAttribute('aria-busy');
        if (btnRecargar.dataset.originalHtml) {
            btnRecargar.innerHTML = btnRecargar.dataset.originalHtml;
            delete btnRecargar.dataset.originalHtml;
        }
    }

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

    // Initialize Server-Side DataTables
    dt = $('#tabla_ordenes').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[5, 'desc']],
        dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
        ajax: {
            url: BASE_URL + 'tabla',
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: function(d) {
                d.estado = filtroEstado ? filtroEstado.value : '';
            },
            error: function(xhr) {
                setActualizarLoading(false);

                let msg = 'No se pudieron cargar las órdenes. Inténtalo de nuevo.';
                if (xhr.status === 403) {
                    msg = 'No tienes permiso para ver las órdenes.';
                } else if (xhr.status === 401) {
                    msg = 'Tu sesión expiró. Inicia sesión nuevamente.';
                }

                mostrar_alerta('error', msg);
            }
        },
        columns: [
            { data: 'orden', orderable: true, responsivePriority: 1 },
            { data: 'plan', orderable: true, responsivePriority: 3 },
            { data: 'creditos', orderable: true, className: 'text-end', responsivePriority: 4 },
            { data: 'monto', orderable: true, className: 'text-end', responsivePriority: 3 },
            { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
            { data: 'fecha', orderable: true, responsivePriority: 2 },
            { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
        ],
        drawCallback: function() {
            setActualizarLoading(false);
            $('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
        },
        language: {
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span> Cargando órdenes...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ órdenes',
            infoEmpty: 'No hay órdenes para mostrar',
            infoFiltered: '(filtrado de _MAX_ órdenes en total)',
            zeroRecords: '<div class="text-center py-8"><i class="ki-duotone ki-magnifier fs-3x text-muted mb-3"><span class="path1"></span><span class="path2"></span></i><div class="fw-bold text-gray-800">No hay órdenes coincidentes</div><div class="text-muted fs-8">Prueba otro término de búsqueda o filtro de estado.</div></div>',
            emptyTable: '<div class="text-center py-8"><i class="ki-duotone ki-bill fs-3x text-muted mb-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span></i><div class="fw-bold text-gray-800">Aún no hay órdenes de compra</div><div class="text-muted fs-8">Elige un plan arriba para comenzar.</div></div>',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });

    // Stripe redirects back here. Only the signed webhook can settle an
    // order, so this is a non-authoritative UX hint plus a refresh.
    const stripeParams = new URLSearchParams(window.location.search);
    const stripeResult = stripeParams.get('stripe');

    if (stripeResult === 'success') {
        mostrar_alerta('info', 'Pago recibido. Estamos confirmándolo con el procesador de pagos. Tu orden se actualizará en breve.');
        dt.ajax.reload(null, false);
    } else if (stripeResult === 'cancelled') {
        mostrar_alerta('warning', 'El pago con tarjeta fue cancelado. Tu orden sigue pendiente de pago.');
    }

    if (stripeResult !== null) {
        stripeParams.delete('stripe');
        stripeParams.delete('session_id');
        const cleanQuery = stripeParams.toString();
        window.history.replaceState({}, '', window.location.pathname + (cleanQuery !== '' ? '?' + cleanQuery : ''));
    }

    function actualizarBotonLimpiar() {
        if (!btnLimpiarFiltro || !inputFiltro) return;
        btnLimpiarFiltro.classList.toggle('d-none', inputFiltro.value.trim() === '');
    }

    // Search filter with debounce
    if (inputFiltro) {
        inputFiltro.addEventListener('input', function() {
            const valor = this.value;
            actualizarBotonLimpiar();
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function() {
                dt.search(valor).draw();
            }, 250);
        });
        actualizarBotonLimpiar();
    }

    if (btnLimpiarFiltro) {
        btnLimpiarFiltro.addEventListener('click', function() {
            if (!inputFiltro) return;

            inputFiltro.value = '';
            actualizarBotonLimpiar();
            clearTimeout(filtroTimer);
            dt.search('').draw();
            inputFiltro.focus();
        });
    }

    // Status filter
    if (filtroEstado) {
        filtroEstado.addEventListener('change', function() {
            clearTimeout(filtroTimer);
            if (inputFiltro) {
                dt.search(inputFiltro.value);
            }
            dt.ajax.reload(null, false);
        });
    }

    // Manual Actualizar button
    if (btnRecargar) {
        btnRecargar.addEventListener('click', function() {
            if (btnRecargar.disabled) return;

            clearTimeout(filtroTimer);
            if (inputFiltro) {
                dt.search(inputFiltro.value);
            }
            setActualizarLoading(true);
            dt.ajax.reload(null, false);
        });
    }

    function abrir_confirmacion_compra(idPlan, trigger) {
        if (!idPlan) return;

        if (trigger) {
            trigger.disabled = true;
        }

        fetch(BASE_URL + 'confirmar/' + encodeURIComponent(idPlan), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res.ok || !res.plan) {
                mostrar_alerta('error', res.msg || 'No se pudo obtener la información del plan.');
                return;
            }

            const p = res.plan;
            document.getElementById('conf_plan_id').value = p.pla_id;
            document.getElementById('conf_plan_nombre').textContent = p.pla_nombre;
            document.getElementById('conf_plan_creditos').textContent = Number(p.pla_numero).toLocaleString('es-VE') + ' créditos';
            document.getElementById('conf_plan_precio').textContent = p.pla_precio + ' ' + p.pla_moneda;

            modalConfirmar.show();
        })
        .catch(() => {
            mostrar_alerta('error', 'Error de conexión al obtener el plan.');
        })
        .finally(() => {
            if (trigger) {
                trigger.disabled = false;
            }
        });
    }

    // Initiate purchase (Click "Comprar plan" or "Buy again")
    document.querySelectorAll('.btn-iniciar-compra').forEach(function(btn) {
        btn.addEventListener('click', function() {
            abrir_confirmacion_compra(this.dataset.id, this);
        });
    });

    document.addEventListener('click', function(e) {
        const btnRecomprar = e.target.closest('.btn-recomprar');
        if (!btnRecomprar) return;

        abrir_confirmacion_compra(btnRecomprar.dataset.id, btnRecomprar);
    });

    // Confirm and process order
    btnConfirmar.addEventListener('click', function() {
        const idPlan = document.getElementById('conf_plan_id').value;
        if (!idPlan || idPlan === '0') return;

        btnConfirmar.setAttribute('data-kt-indicator', 'on');
        btnConfirmar.disabled = true;

        const fd = new FormData();
        fd.append('id_plan', idPlan);

        fetch(BASE_URL + 'procesar_compra', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            btnConfirmar.removeAttribute('data-kt-indicator');
            btnConfirmar.disabled = false;

            if (res.ok) {
                modalConfirmar.hide();
                Swal.fire({
                    icon: 'success',
                    title: '¡Orden creada!',
                    text: res.msg,
                    confirmButtonText: 'Aceptar',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-sm btn-primary' }
                }).then(() => {
                    window.location.reload();
                });
                return;
            }

            mostrar_alerta('warning', res.msg || 'No se pudo crear la orden.');
        })
        .catch(() => {
            btnConfirmar.removeAttribute('data-kt-indicator');
            btnConfirmar.disabled = false;
            mostrar_alerta('error', 'Error al procesar la compra.');
        });
    });

    // Pagar con criptomonedas
    document.addEventListener('click', function(e) {
        const btnPay = e.target.closest('.btn-pagar-crypto');
        if (!btnPay) return;

        const movId = btnPay.dataset.id;
        if (!movId) return;

        // Button loading state
        const originalHtml = btnPay.innerHTML;
        btnPay.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        btnPay.disabled = true;
        btnPay.setAttribute('aria-busy', 'true');

        const fd = new FormData();
        fd.append('mov_id', movId);

        fetch(BASE_URL + 'pay_crypto', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            btnPay.innerHTML = originalHtml;
            btnPay.disabled = false;
            btnPay.removeAttribute('aria-busy');

            if (res.ok && res.checkout_url) {
                Swal.fire({
                    icon: 'info',
                    title: 'Pago cripto seguro',
                    text: 'Serás redirigido al pago seguro para completar tu pago con criptomonedas.',
                    confirmButtonText: 'Continuar al pago',
                    showCancelButton: true,
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-sm btn-primary',
                        cancelButton: 'btn btn-sm btn-light ms-2'
                    }
                }).then(result => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    let checkoutUrl = null;
                    try {
                        checkoutUrl = new URL(res.checkout_url, window.location.origin);
                    } catch (e) {
                        checkoutUrl = null;
                    }

                    if (!checkoutUrl || checkoutUrl.protocol !== 'https:') {
                        mostrar_alerta('error', 'El enlace de pago no es válido. Inténtalo de nuevo.');
                        return;
                    }

                    window.location.href = checkoutUrl.href;
                });
            } else {
                mostrar_alerta('error', res.msg || 'No se pudo iniciar el pago cripto. Inténtalo de nuevo.');
            }
        })
        .catch(() => {
            btnPay.innerHTML = originalHtml;
            btnPay.disabled = false;
            btnPay.removeAttribute('aria-busy');
            mostrar_alerta('error', 'Error de conexión. Inténtalo de nuevo.');
        });
    });

    // Pagar con tarjeta (hosted Stripe Checkout)
    document.addEventListener('click', function(e) {
        const btnPay = e.target.closest('.btn-pagar-stripe');
        if (!btnPay) return;

        const movId = btnPay.dataset.id;
        if (!movId) return;

        const originalHtml = btnPay.innerHTML;
        btnPay.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        btnPay.disabled = true;
        btnPay.setAttribute('aria-busy', 'true');

        const fd = new FormData();
        fd.append('mov_id', movId);

        fetch(BASE_URL + 'stripe_checkout', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            btnPay.innerHTML = originalHtml;
            btnPay.disabled = false;
            btnPay.removeAttribute('aria-busy');

            if (res.ok && res.checkout_url) {
                Swal.fire({
                    icon: 'info',
                    title: 'Pago seguro con tarjeta',
                    text: 'Serás redirigido al pago seguro con tarjeta para completar tu pago.',
                    confirmButtonText: 'Continuar al pago',
                    showCancelButton: true,
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-sm btn-primary',
                        cancelButton: 'btn btn-sm btn-light ms-2'
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;

                    let checkoutUrl = null;
                    try {
                        checkoutUrl = new URL(res.checkout_url, window.location.origin);
                    } catch (err) {
                        checkoutUrl = null;
                    }

                    if (!checkoutUrl || checkoutUrl.protocol !== 'https:') {
                        mostrar_alerta('error', 'El enlace de pago no es válido. Inténtalo de nuevo.');
                        return;
                    }

                    window.location.href = checkoutUrl.href;
                });
            } else {
                mostrar_alerta('error', res.msg || 'No se pudo iniciar el pago con tarjeta. Inténtalo de nuevo.');
            }
        })
        .catch(() => {
            btnPay.innerHTML = originalHtml;
            btnPay.disabled = false;
            btnPay.removeAttribute('aria-busy');
            mostrar_alerta('error', 'Error de conexión. Inténtalo de nuevo.');
        });
    });

    // Cancelar unpaid order
    document.addEventListener('click', function(e) {
        const btnCancelar = e.target.closest('.btn-cancelar-orden');
        if (!btnCancelar) return;

        const movId = btnCancelar.dataset.id;
        if (!movId) return;

        Swal.fire({
            icon: 'question',
            title: '¿Cancelar esta orden?',
            text: 'Orden #' + movId + ' será cancelada y podrás seleccionar otro plan.',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar orden',
            cancelButtonText: 'No, mantenerla',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            const fd = new FormData();
            fd.append('mov_id', movId);

            fetch(BASE_URL + 'cancelar_orden', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    mostrar_alerta('success', res.msg);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    mostrar_alerta('error', res.msg || 'No se pudo cancelar la orden.');
                }
            })
            .catch(() => {
                mostrar_alerta('error', 'Error de conexión.');
            });
        });
    });
});
</script>
<!--end::Module JavaScript-->

