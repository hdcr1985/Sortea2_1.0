<!--begin::Card Planes-->
<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text"
                    id="filtro_planes"
                    class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar planes..." />
            </div>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nuevo_plan">
                <i class="ki-duotone ki-plus fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Nuevo Plan
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_planes">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-200px">Plan</th>
                        <th class="min-w-130px">Precio</th>
                        <th class="min-w-140px">Créditos</th>
                        <th class="min-w-160px">Pasarelas de Pago</th>
                        <th class="min-w-100px text-center">Estado</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold"></tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card Planes-->

<!--begin::Modal Plan (Crear / Editar)-->
<div class="modal fade" id="modal_plan" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-element-11 fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_plan_titulo">Nuevo Plan</h4>
                        <span class="text-muted fs-8">Configura el paquete de suscripción y créditos</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_plan">
                    <ul class="mb-0 fs-7" id="lista_errores_plan"></ul>
                </div>
                <!--end::Alerta de errores-->

                <form id="form_plan" novalidate="novalidate">
                    <input type="hidden" name="pla_id" id="pla_id" value="0" />

                    <div class="row g-5">
                        <!--begin::Nombre-->
                        <div class="col-md-12">
                            <label class="form-label required fw-semibold fs-7 text-gray-800">Nombre del Plan</label>
                            <input type="text"
                                name="pla_nombre"
                                id="pla_nombre"
                                class="form-control form-control-solid"
                                placeholder="Ej: Plan Pro Mensual"
                                maxlength="100"
                                required />
                            <div class="invalid-feedback" id="err_pla_nombre"></div>
                        </div>
                        <!--end::Nombre-->

                        <!--begin::Precio-->
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800">Precio</label>
                            <div class="position-relative">
                                <input type="number"
                                    step="0.01"
                                    min="0"
                                    name="pla_precio"
                                    id="pla_precio"
                                    class="form-control form-control-solid pe-8"
                                    placeholder="0.00"
                                    required />
                                <span class="position-absolute end-0 top-50 translate-middle-y me-3 text-muted fs-7">
                                    <i class="ki-duotone ki-dollar fs-5 text-gray-500"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </span>
                            </div>
                            <div class="invalid-feedback" id="err_pla_precio"></div>
                        </div>
                        <!--end::Precio-->

                        <!--begin::Moneda-->
                        <div class="col-md-3">
                            <label class="form-label required fw-semibold fs-7 text-gray-800">Moneda</label>
                            <select name="pla_moneda" id="pla_moneda" class="form-select form-select-solid" required>
                                <option value="USD" selected>USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="USDT">USDT (₮)</option>
                                <option value="MXN">MXN ($)</option>
                                <option value="COP">COP ($)</option>
                                <option value="GBP">GBP (£)</option>
                            </select>
                            <div class="invalid-feedback" id="err_pla_moneda"></div>
                        </div>
                        <!--end::Moneda-->

                        <!--begin::Créditos-->
                        <div class="col-md-5">
                            <label class="form-label required fw-semibold fs-7 text-gray-800">Créditos a Asignar</label>
                            <div class="position-relative">
                                <input type="number"
                                    name="pla_numero"
                                    id="pla_numero"
                                    class="form-control form-control-solid pe-12"
                                    placeholder="100"
                                    min="0"
                                    required />
                                <span class="position-absolute end-0 top-50 translate-middle-y me-4 text-muted fs-7">
                                    <i class="ki-duotone ki-diamond fs-5 text-info"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div class="invalid-feedback" id="err_pla_numero"></div>
                        </div>
                        <!--end::Créditos-->

                        <!--begin::Descripción-->
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800">Descripción del Plan</label>
                            <input type="text"
                                name="pla_descripcion"
                                id="pla_descripcion"
                                class="form-control form-control-solid"
                                placeholder="Ej: Acceso completo con 100 créditos para solicitudes"
                                maxlength="255" />
                            <div class="text-muted fs-8 mt-1">Breve resumen visible para el usuario al adquirir el plan.</div>
                        </div>
                        <!--end::Descripción-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Pasarelas de Pago-->
                        <div class="col-12">
                            <label class="form-label fw-bold fs-7 text-gray-900 mb-3">Métodos de Pago Habilitados</label>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="ki-duotone ki-credit-cart fs-2x text-primary"><span class="path1"></span><span class="path2"></span></i>
                                            <div>
                                                <div class="fw-bold text-gray-900 fs-7">Stripe / Tarjetas</div>
                                                <div class="text-muted fs-8">Permite cobros con tarjeta y Stripe</div>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" name="pla_paypal" id="pla_paypal" value="1" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="ki-duotone ki-bitcoin fs-2x text-warning"><span class="path1"></span><span class="path2"></span></i>
                                            <div>
                                                <div class="fw-bold text-gray-900 fs-7">BTCPay Server</div>
                                                <div class="text-muted fs-8">Permite pagos automáticos en criptomonedas</div>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" name="pla_crypto" id="pla_crypto" value="1" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Pasarelas-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Estado Activo-->
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Estado del Plan</div>
                                    <div class="text-muted fs-8">Los planes inactivos no están disponibles para nuevas compras</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="pla_status" id="pla_status" value="1" checked />
                                </div>
                            </div>
                        </div>
                        <!--end::Estado Activo-->
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_plan">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar Plan
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
<!--end::Modal Plan-->

<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
    const modalEl = document.getElementById('modal_plan');
    const modalPlan = new bootstrap.Modal(modalEl);
    const formPlan = document.getElementById('form_plan');
    const btnGuardar = document.getElementById('btn_guardar_plan');
    const btnNuevo = document.getElementById('btn_nuevo_plan');
    const inputFiltro = document.getElementById('filtro_planes');
    const alertErrores = document.getElementById('alert_errores_plan');
    const listaErrores = document.getElementById('lista_errores_plan');

    let dt = null;
    let filtroTimer = null;

    // SweetAlert2 helper estandarizado
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

    // Inicializar DataTables server-side
    dt = $('#tabla_planes').DataTable({
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
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        },
        columns: [
            { data: 'plan', orderable: true },
            { data: 'precio', orderable: true },
            { data: 'creditos', orderable: true },
            { data: 'metodos', orderable: false },
            { data: 'estado', orderable: true, className: 'text-center' },
            { data: 'acciones', orderable: false, className: 'text-end pe-2' }
        ],
        drawCallback: function() {
            // Inicializar tooltips en cada render
            $('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
        },
        language: {
            processing: 'Cargando planes...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ planes',
            infoEmpty: 'No hay planes para mostrar',
            infoFiltered: '(filtrados de _MAX_ registros)',
            zeroRecords: 'No se encontraron planes',
            emptyTable: 'No hay planes registrados',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });

    // Filtro con debounce
    inputFiltro.addEventListener('input', function() {
        const valor = this.value;
        clearTimeout(filtroTimer);
        filtroTimer = setTimeout(function() {
            dt.search(valor).draw();
        }, 250);
    });

    // Limpiar formulario y errores
    function limpiar_modal() {
        formPlan.reset();
        document.getElementById('pla_id').value = '0';
        document.getElementById('pla_precio').value = '0.00';
        document.getElementById('pla_moneda').value = 'USD';
        document.getElementById('pla_status').checked = true;
        document.getElementById('pla_paypal').checked = false;
        document.getElementById('pla_crypto').checked = false;

        alertErrores.classList.add('d-none');
        listaErrores.innerHTML = '';

        formPlan.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        formPlan.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

        document.getElementById('modal_plan_titulo').textContent = 'Nuevo Plan';
    }

    // Mostrar errores de validación 422
    function mostrar_errores(errores) {
        formPlan.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        formPlan.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        listaErrores.innerHTML = '';

        let total = 0;
        Object.keys(errores).forEach(campo => {
            const input = formPlan.querySelector('[name="' + campo + '"]');
            const feedback = document.getElementById('err_' + campo);

            if (input) input.classList.add('is-invalid');
            if (feedback) feedback.textContent = errores[campo];

            const li = document.createElement('li');
            li.textContent = errores[campo];
            listaErrores.appendChild(li);
            total++;
        });

        if (total > 0) {
            alertErrores.classList.remove('d-none');
        }
    }

    // Abrir modal para crear
    btnNuevo.addEventListener('click', function() {
        limpiar_modal();
        modalPlan.show();
    });

    // Guardar (Crear o Actualizar)
    btnGuardar.addEventListener('click', function() {
        const id = document.getElementById('pla_id').value;
        const esNuevo = (id === '0' || id === '');
        const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + id;
        const fd = new FormData(formPlan);

        btnGuardar.setAttribute('data-kt-indicator', 'on');
        btnGuardar.disabled = true;
        alertErrores.classList.add('d-none');

        fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            btnGuardar.removeAttribute('data-kt-indicator');
            btnGuardar.disabled = false;

            if (res.ok) {
                modalPlan.hide();
                mostrar_alerta('success', res.msg || 'Plan guardado exitosamente.');
                dt.ajax.reload(null, false);
                return;
            }

            if (res.errores) {
                mostrar_errores(res.errores);
                return;
            }

            mostrar_alerta('error', res.msg || 'No se pudo guardar el plan.');
        })
        .catch(() => {
            btnGuardar.removeAttribute('data-kt-indicator');
            btnGuardar.disabled = false;
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    });

    // Delegación de eventos en la tabla (Editar, Cambiar Estado, Eliminar)
    document.getElementById('tabla_planes').addEventListener('click', function(event) {
        // 1. Botón Editar
        const btnEditar = event.target.closest('.btn-editar');
        if (btnEditar) {
            const id = btnEditar.dataset.id;
            limpiar_modal();

            fetch(BASE_URL + 'datos/' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                if (!res.ok || !res.data) {
                    mostrar_alerta('error', res.msg || 'No se pudieron cargar los datos del plan.');
                    return;
                }

                const d = res.data;
                document.getElementById('pla_id').value = d.pla_id;
                document.getElementById('pla_nombre').value = d.pla_nombre || '';
                document.getElementById('pla_precio').value = parseFloat(d.pla_precio || 0).toFixed(2);
                document.getElementById('pla_moneda').value = (d.pla_moneda || 'USD').toUpperCase();
                document.getElementById('pla_numero').value = d.pla_numero || 0;
                document.getElementById('pla_descripcion').value = d.pla_descripcion || '';
                document.getElementById('pla_paypal').checked = (parseInt(d.pla_paypal, 10) === 1);
                document.getElementById('pla_crypto').checked = (parseInt(d.pla_crypto, 10) === 1);
                document.getElementById('pla_status').checked = (parseInt(d.pla_status, 10) === 1);

                document.getElementById('modal_plan_titulo').textContent = 'Editar Plan: ' + (d.pla_nombre || '');
                modalPlan.show();
            })
            .catch(() => mostrar_alerta('error', 'Error al consultar el plan.'));
            return;
        }

        // 2. Botón Cambiar Estado
        const btnStatus = event.target.closest('.btn-status');
        if (btnStatus) {
            const id = btnStatus.dataset.id;
            const nombre = btnStatus.dataset.nombre || 'este plan';
            const statusActual = btnStatus.dataset.status === '1';
            const accionTexto = statusActual ? 'desactivar' : 'activar';

            Swal.fire({
                icon: 'question',
                title: '¿Deseas ' + accionTexto + ' el plan?',
                text: 'Se cambiará el estado de "' + nombre + '".',
                showCancelButton: true,
                confirmButtonText: 'Sí, ' + accionTexto,
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-primary',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(result => {
                if (!result.isConfirmed) return;

                fetch(BASE_URL + 'cambiar_status/' + id, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        mostrar_alerta('success', res.msg);
                        dt.ajax.reload(null, false);
                    } else {
                        mostrar_alerta('error', res.msg || 'No se pudo cambiar el estado.');
                    }
                })
                .catch(() => mostrar_alerta('error', 'Error de conexión.'));
            });
            return;
        }

        // 3. Botón Eliminar (Borrado lógico)
        const btnEliminar = event.target.closest('.btn-eliminar');
        if (btnEliminar) {
            const id = btnEliminar.dataset.id;
            const nombre = btnEliminar.dataset.nombre || 'este plan';

            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar plan?',
                text: '¿Estás seguro de que deseas eliminar el plan "' + nombre + '"? Podrás restaurarlo si es necesario.',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-danger',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(result => {
                if (!result.isConfirmed) return;

                fetch(BASE_URL + 'eliminar/' + id, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        mostrar_alerta('success', res.msg);
                        dt.ajax.reload(null, false);
                    } else {
                        mostrar_alerta('error', res.msg || 'No se pudo eliminar el plan.');
                    }
                })
                .catch(() => mostrar_alerta('error', 'Error de conexión.'));
            });
        }
    });
});
</script>
<!--end::JavaScript-->
