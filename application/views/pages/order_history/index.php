<!--begin::Historial de Transacciones View-->
<style>
#tabla_transacciones_wrapper .dt-length,
#tabla_transacciones_wrapper .dataTables_length {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex: 0 0 auto;
    white-space: nowrap;
}
#tabla_transacciones_wrapper .dt-length label,
#tabla_transacciones_wrapper .dataTables_length label {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin: 0;
    white-space: nowrap;
}
#tabla_transacciones_wrapper .dt-length select,
#tabla_transacciones_wrapper .dataTables_length select {
    width: auto !important;
    min-width: 4.5rem;
    max-width: 6rem;
    display: inline-block;
}
</style>

<div class="d-flex flex-column gap-7 gap-lg-10">

    <div class="card card-flush shadow-sm">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex flex-column">
                    <h3 class="fw-bold text-gray-900 mb-1">Historial de Transacciones</h3>
                    <span class="text-muted fs-7">Registro detallado de compras de créditos y transacciones de pago.</span>
                </div>
            </div>
            <div class="card-toolbar gap-3 flex-wrap">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text"
                        id="filtro_transacciones"
                        class="form-control form-control-solid w-250px mw-100 ps-12"
                        placeholder="Buscar transacciones..."
                        aria-label="Buscar transacciones" />
                </div>
                <select id="filtro_estado_transaccion"
                    class="form-select form-select-solid w-175px"
                    aria-label="Filtrar transacciones por estado">
                    <option value="">Todos los estados</option>
                    <option value="unpaid">Impagada</option>
                    <option value="waiting">Pendiente de Pago</option>
                    <option value="processing">Procesando</option>
                    <option value="paid">Pagada</option>
                    <option value="refunded">Reembolsada</option>
                    <option value="expired">Expirada</option>
                    <option value="cancelled">Cancelada</option>
                    <option value="rejected">Rechazada</option>
                </select>
                <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4 py-2" id="btn_recargar_transacciones">
                    <i class="ki-duotone ki-arrows-circle fs-4 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Actualizar
                </button>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_transacciones">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                            <th scope="col" class="min-w-125px">Orden</th>
                            <th scope="col" class="min-w-160px">Fecha</th>
                            <th scope="col" class="min-w-200px">Plan</th>
                            <th scope="col" class="min-w-110px text-end">Créditos</th>
                            <th scope="col" class="min-w-125px text-end">Monto</th>
                            <th scope="col" class="min-w-140px text-center">Estado</th>
                            <th scope="col" class="min-w-130px">Método</th>
                            <th scope="col" class="min-w-120px text-end pe-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<!--end::Historial de Transacciones View-->

<!--begin::Transaction Detail Modal-->
<div class="modal fade" id="modal_detalle_transaccion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-4">
                <div class="d-flex flex-column">
                    <h4 class="modal-title fw-bold text-gray-900">Detalle de la transacción</h4>
                    <span class="text-muted fs-8" id="det_orden_subtitulo">Detalles de la orden</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body py-6">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100 bg-light">
                            <span class="text-muted fs-8 d-block mb-1">Orden</span>
                            <span class="fw-bold text-gray-900 fs-5" id="det_orden">-</span>
                            <div class="separator separator-dashed my-3"></div>
                            <span class="text-muted fs-8 d-block mb-1">Estado</span>
                            <span class="badge badge-light-info fw-bold px-2 py-1" id="det_estado">-</span>
                            <div class="separator separator-dashed my-3"></div>
                            <span class="text-muted fs-8 d-block mb-1">Método de pago</span>
                            <span class="fw-semibold text-gray-800" id="det_metodo">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100 bg-light">
                            <span class="text-muted fs-8 d-block mb-1">Plan</span>
                            <span class="fw-bold text-gray-900 fs-6" id="det_plan">-</span>
                            <span class="text-muted fs-8 d-block mt-1" id="det_descripcion"></span>
                            <div class="separator separator-dashed my-3"></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted fs-8">Créditos</span>
                                <span class="fw-bold text-gray-900" id="det_creditos">-</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="text-muted fs-8">Monto</span>
                                <span class="fw-bold text-primary" id="det_monto">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 p-4">
                            <h6 class="fw-bold text-gray-900 mb-3">Cronología</h6>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <span class="text-muted fs-8 d-block">Creada</span>
                                    <span class="text-gray-800 fs-7" id="det_creado">-</span>
                                </div>
                                <div class="col-sm-4">
                                    <span class="text-muted fs-8 d-block">Pagada</span>
                                    <span class="text-gray-800 fs-7" id="det_pagado">-</span>
                                </div>
                                <div class="col-sm-4">
                                    <span class="text-muted fs-8 d-block">Vence</span>
                                    <span class="text-gray-800 fs-7" id="det_vence">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 p-4">
                            <h6 class="fw-bold text-gray-900 mb-3">Procesador de pago</h6>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <span class="text-muted fs-8 d-block">Estado del procesador</span>
                                    <span class="text-gray-800 fs-7" id="det_procesador_estado">-</span>
                                </div>
                                <div class="col-sm-4">
                                    <span class="text-muted fs-8 d-block">Monto del procesador</span>
                                    <span class="text-gray-800 fs-7" id="det_procesador_monto">-</span>
                                </div>
                                <div class="col-sm-4">
                                    <span class="text-muted fs-8 d-block">Método del procesador</span>
                                    <span class="text-gray-800 fs-7" id="det_procesador_metodo">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($es_master)): ?>
                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 p-4">
                            <h6 class="fw-bold text-gray-900 mb-3">Cliente</h6>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <span class="text-muted fs-8 d-block">Nombre</span>
                                    <span class="text-gray-800 fs-7" id="det_usuario">-</span>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted fs-8 d-block">Correo</span>
                                    <span class="text-gray-800 fs-7" id="det_correo">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal-footer py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Transaction Detail Modal-->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const filtroEstado = document.getElementById('filtro_estado_transaccion');
    const inputFiltro = document.getElementById('filtro_transacciones');
    const btnRecargar = document.getElementById('btn_recargar_transacciones');
    const modalEl = document.getElementById('modal_detalle_transaccion');
    const modal = new bootstrap.Modal(modalEl);
    let filtroTimer = null;

    function alerta(tipo, mensaje) {
        Swal.fire({
            icon: tipo,
            title: tipo === 'error' ? 'Error' : 'Información',
            text: mensaje,
            confirmButtonText: 'Aceptar',
            buttonsStyling: false,
            customClass: { confirmButton: 'btn btn-sm btn-primary' }
        });
    }

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

    const dt = $('#tabla_transacciones').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        dom: "rt<'row align-items-center mt-5 gy-3'<'col-sm-12 col-md-4'l><'col-sm-12 col-md-4 text-center'i><'col-sm-12 col-md-4 d-flex justify-content-md-end'p>>",
        ajax: {
            url: BASE_URL + 'tabla',
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: function (d) {
                d.estado = filtroEstado ? filtroEstado.value : '';
            },
            error: function (xhr) {
                setActualizarLoading(false);
                let msg = 'No se pudieron cargar las transacciones. Inténtalo de nuevo.';
                if (xhr.status === 403) {
                    msg = 'No tienes permiso para ver las transacciones.';
                } else if (xhr.status === 401) {
                    msg = 'Tu sesión expiró. Inicia sesión nuevamente.';
                }
                alerta('error', msg);
            }
        },
        columns: [
            { data: 'orden', orderable: true, responsivePriority: 1 },
            { data: 'fecha', orderable: true, responsivePriority: 3 },
            { data: 'plan', orderable: true, responsivePriority: 2 },
            { data: 'creditos', orderable: true, className: 'text-end', responsivePriority: 4 },
            { data: 'monto', orderable: true, className: 'text-end', responsivePriority: 3 },
            { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
            { data: 'metodo', orderable: true, responsivePriority: 4 },
            { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
        ],
        drawCallback: function () {
            setActualizarLoading(false);
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { if (window.bootstrap && bootstrap.Tooltip) { bootstrap.Tooltip.getOrCreateInstance(el); } });
        },
        language: {
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span> Cargando transacciones...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ transacciones',
            infoEmpty: 'No hay transacciones para mostrar',
            infoFiltered: '(filtrado de _MAX_ transacciones en total)',
            zeroRecords: '<div class="text-center py-8"><div class="fw-bold text-gray-800">No hay transacciones coincidentes</div><div class="text-muted fs-8">Prueba otro término de búsqueda o filtro de estado.</div></div>',
            emptyTable: '<div class="text-center py-8"><div class="fw-bold text-gray-800">Aún no hay transacciones</div><div class="text-muted fs-8">Las compras de créditos aparecerán aquí.</div></div>',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    });

    if (inputFiltro) {
        inputFiltro.addEventListener('input', function () {
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function () {
                dt.search(inputFiltro.value).draw();
            }, 250);
        });
    }

    if (filtroEstado) {
        filtroEstado.addEventListener('change', function () {
            clearTimeout(filtroTimer);
            if (inputFiltro) dt.search(inputFiltro.value);
            dt.ajax.reload(null, false);
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', function () {
            if (btnRecargar.disabled) return;
            clearTimeout(filtroTimer);
            if (inputFiltro) dt.search(inputFiltro.value);
            setActualizarLoading(true);
            dt.ajax.reload(null, false);
        });
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = (value === undefined || value === null || value === '') ? '—' : value;
    }

    function abrirDetalle(id) {
        fetch(BASE_URL + 'datos/' + encodeURIComponent(id), {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res.ok || !res.data) {
                alerta('error', res.msg || 'No se pudieron cargar los detalles de la transacción.');
                return;
            }

            const d = res.data;
            setText('det_orden', '#' + d.mov_id);
            setText('det_orden_subtitulo', 'Orden #' + d.mov_id);
            const estado = document.getElementById('det_estado');
            if (estado) {
                estado.className = 'badge fw-bold px-2 py-1 ' + (d.estado_badge || 'badge-light-info');
                estado.textContent = d.estado_label || '—';
            }
            setText('det_metodo', d.metodo);
            setText('det_plan', d.plan);
            setText('det_descripcion', d.descripcion);
            setText('det_creditos', d.creditos);
            setText('det_monto', (d.monto && d.moneda) ? d.monto + ' ' + d.moneda : '');
            setText('det_creado', d.fecha_compra);
            setText('det_pagado', d.fecha_pago);
            setText('det_vence', d.fecha_venc);
            setText('det_procesador_estado', d.processor_status);
            setText('det_procesador_monto', d.processor_amount);
            setText('det_procesador_metodo', d.processor_method);
            setText('det_usuario', d.usuario_nombre);
            setText('det_correo', d.usuario_correo);
            modal.show();
        })
        .catch(() => {
            alerta('error', 'Error de conexión al cargar los detalles de la transacción.');
        });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-ver-transaccion');
        if (!btn) return;
        abrirDetalle(btn.dataset.id);
    });
});
</script>
<!--end::Historial de Transacciones JavaScript-->
