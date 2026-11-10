<?php
/**
 * M04 — Monedas y tasas de cambio.
 *
 * Contrato del controlador Monedas:
 * - POST tabla, GET datos/{id}, POST crear, actualizar/{id}, eliminar/{id},
 *   cambiar-status/{id}, GET tasas/{id}, POST guardar-tasa/{id}.
 */
?>
<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack mb-6 gap-3">
    <div class="d-flex flex-column">
        <h1 class="fw-bold text-gray-900 fs-2 mb-1">Monedas y tasas de cambio</h1>
        <span class="text-muted fs-7">
            Catálogo de monedas con base USD. Las tasas se expresan como <strong>1 USD = X moneda</strong>.
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($puede_incluir)): ?>
            <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btn_nueva_moneda">
                <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                Nueva moneda
            </button>
        <?php endif; ?>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Aviso base-->
<div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-start gap-3 mb-6" role="alert">
    <i class="ki-duotone ki-information-2 fs-2 text-primary mt-1">
        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
    </i>
    <div class="text-gray-700 fs-7">
        <strong>USD es la moneda base.</strong> No puede eliminarse ni desactivarse. Las demás monedas necesitan una tasa vigente para poder convertirse.
        La estructura de fuente API queda preparada, pero la sincronización automática se implementará en una fase posterior.
    </div>
</div>
<!--end::Aviso base-->

<!--begin::Card-->
<div class="card card-flush">
    <div class="card-header align-items-center py-5 gap-3">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="filtro_monedas" class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar moneda..." autocomplete="off" />
            </div>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fw-bold px-3 py-2" id="badge_total_monedas">—</span>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="alert alert-warning d-none mb-4" id="aviso_datatables_monedas">
            No se pudo inicializar DataTables. Recarga la página.
        </div>
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_monedas">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                        <th class="min-w-200px">Moneda</th>
                        <th class="min-w-100px">Base</th>
                        <th class="min-w-160px">Tasa actual</th>
                        <th class="min-w-100px">Vigencia</th>
                        <th class="min-w-90px text-center">Estado</th>
                        <th class="min-w-150px text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-700"></tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal Moneda-->
<div class="modal fade" id="modal_moneda" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-dollar fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_moneda_titulo">Nueva moneda</h4>
                        <span class="text-muted fs-8">Código, símbolo y tasa inicial contra USD</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert alert-danger d-none mb-5 rounded-3" id="alert_errores_moneda">
                    <ul class="mb-0 fs-7" id="lista_errores_moneda"></ul>
                </div>

                <form id="form_moneda" novalidate="novalidate">
                    <input type="hidden" name="mon_id" id="mon_id" value="0" />

                    <div class="row g-5">
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="mon_codigo">Código</label>
                            <input type="text" name="mon_codigo" id="mon_codigo" class="form-control form-control-solid"
                                placeholder="USD" maxlength="10" autocomplete="off" spellcheck="false" required />
                            <div class="invalid-feedback" data-error-for="mon_codigo"></div>
                            <div class="text-muted fs-8 mt-1">3 a 10 caracteres, mayúsculas. Ej: USD, USDT.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="mon_nombre">Nombre</label>
                            <input type="text" name="mon_nombre" id="mon_nombre" class="form-control form-control-solid"
                                placeholder="Dólar estadounidense" maxlength="80" autocomplete="off" required />
                            <div class="invalid-feedback" data-error-for="mon_nombre"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="mon_simbolo">Símbolo</label>
                            <input type="text" name="mon_simbolo" id="mon_simbolo" class="form-control form-control-solid"
                                placeholder="$" maxlength="10" autocomplete="off" required />
                            <div class="invalid-feedback" data-error-for="mon_simbolo"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="mon_decimales">Decimales</label>
                            <input type="number" name="mon_decimales" id="mon_decimales" class="form-control form-control-solid"
                                min="0" max="6" step="1" value="2" required />
                            <div class="invalid-feedback" data-error-for="mon_decimales"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="mon_orden">Orden</label>
                            <input type="number" name="mon_orden" id="mon_orden" class="form-control form-control-solid"
                                min="0" max="9999" step="1" value="1" />
                            <div class="invalid-feedback" data-error-for="mon_orden"></div>
                        </div>

                        <div class="col-12" id="wrap_mon_tasa_inicial">
                            <div class="border border-gray-200 rounded-3 p-4 bg-light">
                                <label class="form-label required fw-semibold fs-7 text-gray-800" for="mon_tasa_inicial">
                                    Tasa inicial (1 USD = X moneda)
                                </label>
                                <input type="number" name="mon_tasa_inicial" id="mon_tasa_inicial"
                                    class="form-control form-control-solid" min="0.0000000001" step="0.0000000001"
                                    value="1" required />
                                <div class="invalid-feedback" data-error-for="mon_tasa_inicial"></div>
                                <div class="text-muted fs-8 mt-1" id="mon_tasa_inicial_ayuda">
                                    Para USD la tasa siempre es 1.0000.
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="wrap_mon_status">
                            <div class="border border-gray-200 rounded-3 p-3 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Moneda activa</div>
                                    <div class="text-muted fs-8">Solo las monedas activas aparecen en los formularios.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="mon_status" id="mon_status" value="1" checked />
                                </div>
                            </div>
                        </div>

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="mon_api_fuente">Fuente API (futura)</label>
                            <select name="mon_api_fuente" id="mon_api_fuente" class="form-select form-select-solid">
                                <option value="manual" selected>Manual</option>
                                <option value="exchangerate.host">exchangerate.host</option>
                                <option value="frankfurter">Frankfurter</option>
                                <option value="open.er-api.com">Open ER API</option>
                                <option value="custom">Personalizada</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="mon_api_fuente"></div>
                            <div class="text-muted fs-8 mt-1">La automatización se implementará en una fase posterior.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="mon_api_codigo">Código en la API</label>
                            <input type="text" name="mon_api_codigo" id="mon_api_codigo" class="form-control form-control-solid"
                                placeholder="Ej: USD" maxlength="30" autocomplete="off" />
                            <div class="invalid-feedback" data-error-for="mon_api_codigo"></div>
                        </div>

                        <div class="col-12 d-none" id="aviso_moneda_base">
                            <div class="alert bg-light-info border border-info border-dashed d-flex align-items-start gap-3 mb-0" role="alert">
                                <i class="ki-duotone ki-lock-2 fs-2 text-info mt-1">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <div class="text-gray-700 fs-7">
                                    USD es la moneda base: su código no puede cambiarse y no puede eliminarse ni desactivarse.
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_moneda">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar
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
<!--end::Modal Moneda-->

<!--begin::Modal Tasas-->
<div class="modal fade" id="modal_tasas" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-info">
                        <i class="ki-duotone ki-chart-simple fs-3 text-info">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_tasas_titulo">Tasas de cambio</h4>
                        <span class="text-muted fs-8">Historial y tasa vigente (unidades por 1 USD)</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert alert-danger d-none mb-5 rounded-3" id="alert_errores_tasa">
                    <ul class="mb-0 fs-7" id="lista_errores_tasa"></ul>
                </div>

                <div class="row g-3 mb-6">
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Tasa vigente</div>
                            <div class="fw-bold text-gray-900 fs-3" id="tasa_actual_valor">—</div>
                            <div class="text-muted fs-8" id="tasa_actual_fecha">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 bg-light h-100">
                            <div class="text-muted fs-8 fw-semibold">Regla</div>
                            <div class="fw-semibold text-gray-900 fs-7">1 USD = X moneda</div>
                            <div class="text-muted fs-8 mt-1">La tasa más reciente con fecha menor o igual a hoy es la vigente.</div>
                        </div>
                    </div>
                </div>

                <form id="form_tasa" class="border border-gray-200 rounded-3 p-4 mb-6" novalidate="novalidate">
                    <div class="row g-4 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="tasa_fecha">Fecha</label>
                            <input type="date" name="tasa_fecha" id="tasa_fecha" class="form-control form-control-solid" required />
                            <div class="invalid-feedback" data-error-for="tasa_fecha"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="tasa_valor">Valor (1 USD = X)</label>
                            <input type="number" name="tasa_valor" id="tasa_valor" class="form-control form-control-solid"
                                min="0.0000000001" step="0.0000000001" required />
                            <div class="invalid-feedback" data-error-for="tasa_valor"></div>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-sm btn-primary fw-semibold w-100" id="btn_guardar_tasa">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Guardar tasa
                                </span>
                                <span class="indicator-progress">
                                    Por favor espere...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="fw-bold text-gray-900 fs-6">Historial reciente</div>
                        <div class="text-muted fs-8">Se muestran hasta 100 tasas.</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3 mb-0">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-8 text-uppercase">
                                <th>Fecha</th>
                                <th>Valor</th>
                                <th>Origen</th>
                                <th>Registrada</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_historial_tasas">
                            <tr><td colspan="5" class="text-center text-muted fs-7 py-4">Sin tasas registradas.</td></tr>
                        </tbody>
                    </table>
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
<!--end::Modal Tasas-->

<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const jq = window.jQuery || null;
    const tieneDataTables = !!(jq && jq.fn && jq.fn.DataTable);

    const modalMonedaEl = document.getElementById('modal_moneda');
    const modalMoneda = new bootstrap.Modal(modalMonedaEl);
    const modalTasasEl = document.getElementById('modal_tasas');
    const modalTasas = new bootstrap.Modal(modalTasasEl);
    const formMoneda = document.getElementById('form_moneda');
    const formTasa = document.getElementById('form_tasa');
    const btnGuardarMoneda = document.getElementById('btn_guardar_moneda');
    const btnGuardarTasa = document.getElementById('btn_guardar_tasa');
    const alertErroresMoneda = document.getElementById('alert_errores_moneda');
    const listaErroresMoneda = document.getElementById('lista_errores_moneda');
    const alertErroresTasa = document.getElementById('alert_errores_tasa');
    const listaErroresTasa = document.getElementById('lista_errores_tasa');
    const inputFiltro = document.getElementById('filtro_monedas');

    let dtMonedas = null;
    let filtroTimer = null;
    let monedaTasasId = 0;
    let monedaTasasCodigo = '';
    let monedaTasasBase = false;
    let accionEnCurso = false;

    const TASA_FMT = new Intl.NumberFormat('es', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 10
    });

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

    function limpiar_errores(form, alert, lista) {
        if (form) {
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
        }
        if (alert) {
            alert.classList.add('d-none');
        }
        if (lista) {
            lista.innerHTML = '';
        }
    }

    function mostrar_errores(form, alert, lista, errores) {
        limpiar_errores(form, alert, lista);

        if (!errores || typeof errores !== 'object') {
            return;
        }

        const claves = Object.keys(errores);
        if (!claves.length) {
            return;
        }

        claves.forEach(function (campo) {
            const input = form ? form.querySelector('[name="' + campo + '"]') : null;
            if (input) {
                input.classList.add('is-invalid');
            }

            const feedback = form ? form.querySelector('[data-error-for="' + campo + '"]') : null;
            if (feedback) {
                feedback.textContent = String(errores[campo]);
            }

            if (lista) {
                const li = document.createElement('li');
                li.textContent = String(errores[campo]);
                lista.appendChild(li);
            }
        });

        if (alert) {
            alert.classList.remove('d-none');
        }
    }

    function fecha_hoy() {
        const hoy = new Date();
        const y = hoy.getFullYear();
        const m = String(hoy.getMonth() + 1).padStart(2, '0');
        const d = String(hoy.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function formato_fecha(valor) {
        if (!valor) {
            return '—';
        }

        const partes = String(valor).split('-');
        if (partes.length !== 3) {
            return String(valor);
        }

        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function formato_tasa(valor) {
        const numero = parseFloat(valor);
        return isFinite(numero) ? TASA_FMT.format(numero) : '—';
    }

    function init_tooltips() {
        if (!window.bootstrap || !bootstrap.Tooltip) {
            return;
        }

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function idioma_es(entidad) {
        return {
            emptyTable: 'No hay ' + entidad + ' para mostrar.',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ ' + entidad,
            infoEmpty: 'Mostrando 0 a 0 de 0 ' + entidad,
            infoFiltered: '(filtrado de _MAX_ registros)',
            lengthMenu: 'Mostrar _MENU_ ' + entidad,
            loadingRecords: 'Cargando...',
            processing: 'Procesando...',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron ' + entidad,
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        };
    }

    function fila_data(dt, elemento) {
        if (!dt) {
            return {};
        }

        const fila = elemento.closest('tr');
        if (!fila) {
            return {};
        }

        try {
            return dt.row(fila).data() || {};
        } catch (e) {
            return {};
        }
    }

    function actualizar_badge_total(total) {
        const badge = document.getElementById('badge_total_monedas');
        if (badge) {
            badge.textContent = Number(total || 0).toLocaleString('es') + ' moneda(s)';
        }
    }

    // ─────────────────────────────────────────────────────
    // Modal moneda
    // ─────────────────────────────────────────────────────
    function actualizar_tasa_inicial_ui() {
        const codigo = document.getElementById('mon_codigo').value.trim().toUpperCase();
        const input = document.getElementById('mon_tasa_inicial');
        const ayuda = document.getElementById('mon_tasa_inicial_ayuda');

        if (!input) {
            return;
        }

        if (codigo === 'USD') {
            input.value = '1';
            input.readOnly = true;
            input.required = false;
            if (ayuda) {
                ayuda.textContent = 'USD es la base: la tasa siempre es 1.0000.';
            }
        } else {
            input.readOnly = false;
            input.required = true;
            if (ayuda) {
                ayuda.textContent = 'Indica cuántas unidades de esta moneda equivalen a 1 USD.';
            }
        }
    }

    function limpiar_modal_moneda() {
        formMoneda.reset();
        document.getElementById('mon_id').value = '0';
        document.getElementById('modal_moneda_titulo').textContent = 'Nueva moneda';
        document.getElementById('mon_codigo').disabled = false;
        document.getElementById('mon_codigo').value = 'USD';
        document.getElementById('mon_nombre').value = '';
        document.getElementById('mon_simbolo').value = '$';
        document.getElementById('mon_decimales').value = '2';
        document.getElementById('mon_orden').value = '1';
        document.getElementById('mon_status').checked = true;
        document.getElementById('mon_api_fuente').value = 'manual';
        document.getElementById('mon_api_codigo').value = '';
        document.getElementById('wrap_mon_tasa_inicial').classList.remove('d-none');
        document.getElementById('wrap_mon_status').classList.remove('d-none');
        document.getElementById('aviso_moneda_base').classList.add('d-none');
        actualizar_tasa_inicial_ui();
        limpiar_errores(formMoneda, alertErroresMoneda, listaErroresMoneda);
    }

    function abrir_edicion_moneda(id) {
        limpiar_modal_moneda();

        request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cargar la moneda.');
                return;
            }

            const d = res.data.data;
            const base = !!d.es_base;

            document.getElementById('mon_id').value = String(d.mon_id || id);
            document.getElementById('modal_moneda_titulo').textContent = 'Editar: ' + (d.mon_codigo || '');
            document.getElementById('mon_codigo').value = String(d.mon_codigo || '');
            document.getElementById('mon_codigo').disabled = base;
            document.getElementById('mon_nombre').value = String(d.mon_nombre || '');
            document.getElementById('mon_simbolo').value = String(d.mon_simbolo || '');
            document.getElementById('mon_decimales').value = String(parseInt(d.mon_decimales, 10) || 0);
            document.getElementById('mon_orden').value = String(parseInt(d.mon_orden, 10) || 0);
            document.getElementById('mon_api_fuente').value = String(d.mon_api_fuente || 'manual');
            document.getElementById('mon_api_codigo').value = String(d.mon_api_codigo || '');
            document.getElementById('wrap_mon_tasa_inicial').classList.add('d-none');
            document.getElementById('wrap_mon_status').classList.add('d-none');
            document.getElementById('aviso_moneda_base').classList.toggle('d-none', !base);
            actualizar_tasa_inicial_ui();

            modalMoneda.show();
        }).catch(function () {
            mostrar_alerta('error', 'Error de conexión al cargar la moneda.');
        });
    }

    function guardar_moneda() {
        if (accionEnCurso) {
            return;
        }

        const id = document.getElementById('mon_id').value;
        const esNuevo = (id === '0' || id === '');

        if (esNuevo) {
            const codigo = document.getElementById('mon_codigo').value.trim().toUpperCase();
            if (codigo !== 'USD') {
                const tasa = parseFloat(document.getElementById('mon_tasa_inicial').value);
                if (!isFinite(tasa) || tasa <= 0) {
                    mostrar_errores(formMoneda, alertErroresMoneda, listaErroresMoneda, {
                        mon_tasa_inicial: 'Indica una tasa inicial mayor que cero.'
                    });
                    return;
                }
            }
        }

        const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + encodeURIComponent(id);
        const fd = new FormData(formMoneda);
        fd.set('mon_codigo', document.getElementById('mon_codigo').value.trim().toUpperCase());

        if (!esNuevo) {
            fd.delete('mon_status');
            fd.delete('mon_tasa_inicial');
        }

        accionEnCurso = true;
        set_loading(btnGuardarMoneda, true);
        limpiar_errores(formMoneda, alertErroresMoneda, listaErroresMoneda);

        request_json(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            accionEnCurso = false;
            set_loading(btnGuardarMoneda, false);

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }

            if (res.data && res.data.ok) {
                modalMoneda.hide();
                mostrar_alerta('success', res.data.msg || 'Moneda guardada correctamente.');
                if (dtMonedas) {
                    dtMonedas.ajax.reload(null, false);
                }
                return;
            }

            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(formMoneda, alertErroresMoneda, listaErroresMoneda, res.data.errores);
                return;
            }

            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo guardar la moneda.');
        }).catch(function () {
            accionEnCurso = false;
            set_loading(btnGuardarMoneda, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    // ─────────────────────────────────────────────────────
    // Modal tasas
    // ─────────────────────────────────────────────────────
    function render_historial_tasas(historial) {
        const tbody = document.getElementById('tabla_historial_tasas');
        if (!tbody) {
            return;
        }

        tbody.innerHTML = '';

        if (!Array.isArray(historial) || historial.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted fs-7 py-4">Sin tasas registradas.</td></tr>';
            return;
        }

        historial.forEach(function (tasa) {
            const fila = document.createElement('tr');
            const fecha = String(tasa.tasa_fecha || '');
            const valor = formato_tasa(tasa.tasa_valor);
            const origen = String(tasa.tasa_origen || 'manual');
            const registrada = String(tasa.tasa_created_at || '').replace('T', ' ').slice(0, 16);

            fila.innerHTML =
                '<td><span class="fw-bold text-gray-900 fs-7">' + escapeHtml(formato_fecha(fecha)) + '</span></td>' +
                '<td><span class="fw-semibold text-gray-900 fs-7">1 USD = ' + escapeHtml(valor) + ' ' + escapeHtml(monedaTasasCodigo) + '</span></td>' +
                '<td><span class="badge ' + (origen === 'api' ? 'badge-light-info' : 'badge-light-secondary') + ' fw-bold px-3 py-2">' + escapeHtml(origen) + '</span></td>' +
                '<td><span class="text-muted fs-8">' + escapeHtml(registrada || '—') + '</span></td>' +
                '<td class="text-end">' +
                    '<button type="button" class="btn btn-sm btn-light-primary fw-semibold btn-usar-tasa" ' +
                        'data-fecha="' + escapeHtml(fecha) + '" data-valor="' + escapeHtml(tasa.tasa_valor) + '">' +
                        '<i class="ki-duotone ki-pencil fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Usar' +
                    '</button>' +
                '</td>';

            tbody.appendChild(fila);
        });
    }

    function actualizar_tasa_actual(actual) {
        const valorEl = document.getElementById('tasa_actual_valor');
        const fechaEl = document.getElementById('tasa_actual_fecha');

        if (!actual) {
            valorEl.textContent = 'Sin tasa vigente';
            fechaEl.textContent = '—';
            return;
        }

        valorEl.textContent = '1 USD = ' + formato_tasa(actual.tasa_valor) + ' ' + monedaTasasCodigo;
        fechaEl.textContent = 'Vigente desde ' + formato_fecha(actual.tasa_fecha);
    }

    function abrir_tasas(id, codigo, esBase) {
        monedaTasasId = parseInt(id, 10) || 0;
        monedaTasasCodigo = String(codigo || '');
        monedaTasasBase = !!esBase;

        document.getElementById('modal_tasas_titulo').textContent = 'Tasas de ' + monedaTasasCodigo;
        document.getElementById('tasa_fecha').value = fecha_hoy();
        document.getElementById('tasa_valor').value = monedaTasasBase ? '1' : '';
        document.getElementById('tasa_valor').readOnly = monedaTasasBase;
        document.getElementById('tasa_actual_valor').textContent = '—';
        document.getElementById('tasa_actual_fecha').textContent = '—';
        document.getElementById('tabla_historial_tasas').innerHTML =
            '<tr><td colspan="5" class="text-center text-muted fs-7 py-4">Cargando tasas…</td></tr>';
        limpiar_errores(formTasa, alertErroresTasa, listaErroresTasa);
        modalTasas.show();

        request_json(BASE_URL + 'tasas/' + encodeURIComponent(monedaTasasId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron cargar las tasas.');
                return;
            }

            const data = res.data.data;
            monedaTasasCodigo = String((data.moneda && data.moneda.mon_codigo) || monedaTasasCodigo);
            monedaTasasBase = !!(data.moneda && data.moneda.es_base);
            document.getElementById('tasa_valor').readOnly = monedaTasasBase;

            actualizar_tasa_actual(data.actual);
            render_historial_tasas(data.historial || []);

            if (data.actual && data.actual.tasa_valor) {
                document.getElementById('tasa_valor').value = String(data.actual.tasa_valor);
                document.getElementById('tasa_fecha').value = String(data.actual.tasa_fecha || fecha_hoy());
            }
        }).catch(function () {
            mostrar_alerta('error', 'Error de conexión al cargar las tasas.');
        });
    }

    function guardar_tasa() {
        if (accionEnCurso || !monedaTasasId) {
            return;
        }

        const valor = parseFloat(document.getElementById('tasa_valor').value);
        if (!isFinite(valor) || valor <= 0) {
            mostrar_errores(formTasa, alertErroresTasa, listaErroresTasa, {
                tasa_valor: 'La tasa debe ser un número mayor que cero.'
            });
            return;
        }

        const fd = new FormData(formTasa);
        if (monedaTasasBase) {
            fd.set('tasa_valor', '1');
        }

        accionEnCurso = true;
        set_loading(btnGuardarTasa, true);
        limpiar_errores(formTasa, alertErroresTasa, listaErroresTasa);

        request_json(BASE_URL + 'guardar-tasa/' + encodeURIComponent(monedaTasasId), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            accionEnCurso = false;
            set_loading(btnGuardarTasa, false);

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }

            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || 'Tasa guardada correctamente.');
                actualizar_tasa_actual(res.data.actual);
                render_historial_tasas(res.data.historial || []);
                if (dtMonedas) {
                    dtMonedas.ajax.reload(null, false);
                }
                return;
            }

            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(formTasa, alertErroresTasa, listaErroresTasa, res.data.errores);
                return;
            }

            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo guardar la tasa.');
        }).catch(function () {
            accionEnCurso = false;
            set_loading(btnGuardarTasa, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    // ─────────────────────────────────────────────────────
    // Acciones
    // ─────────────────────────────────────────────────────
    function enviar_accion(ruta, id, mensajeExito) {
        if (accionEnCurso) {
            return;
        }

        accionEnCurso = true;

        request_json(BASE_URL + ruta + '/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            accionEnCurso = false;

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }

            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || mensajeExito);
                if (dtMonedas) {
                    dtMonedas.ajax.reload(null, false);
                }
                return;
            }

            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo completar la acción.');
        }).catch(function () {
            accionEnCurso = false;
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    function confirmar_status(id, codigo, status, esBase) {
        if (esBase) {
            mostrar_alerta('warning', 'La moneda base USD no puede desactivarse.');
            return;
        }

        const desactivar = String(status) === '1';

        Swal.fire({
            icon: 'warning',
            title: desactivar ? '¿Desactivar la moneda?' : '¿Activar la moneda?',
            text: 'La moneda "' + codigo + '" quedará ' + (desactivar ? 'oculta' : 'disponible') + ' en los formularios.',
            showCancelButton: true,
            confirmButtonText: desactivar ? 'Sí, desactivar' : 'Sí, activar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: desactivar ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-success',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                enviar_accion('cambiar-status', id, 'Estado actualizado correctamente.');
            }
        });
    }

    function confirmar_eliminar(id, codigo, esBase) {
        if (esBase) {
            mostrar_alerta('warning', 'La moneda base USD no puede eliminarse.');
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la moneda?',
            text: 'La moneda "' + codigo + '" se eliminará lógicamente. No podrás usarla si está referenciada.',
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
                enviar_accion('eliminar', id, 'Moneda eliminada correctamente.');
            }
        });
    }

    function delegar_acciones(event) {
        const btnEditar = event.target.closest('.btn-editar');
        if (btnEditar) {
            abrir_edicion_moneda(btnEditar.getAttribute('data-id'));
            return;
        }

        const btnTasas = event.target.closest('.btn-tasas');
        if (btnTasas) {
            abrir_tasas(
                btnTasas.getAttribute('data-id'),
                btnTasas.getAttribute('data-codigo'),
                btnTasas.getAttribute('data-es-base') === '1'
            );
            return;
        }

        const btnStatus = event.target.closest('.btn-status');
        if (btnStatus) {
            confirmar_status(
                btnStatus.getAttribute('data-id'),
                btnStatus.getAttribute('data-codigo'),
                btnStatus.getAttribute('data-status'),
                btnStatus.getAttribute('data-es-base') === '1'
            );
            return;
        }

        const btnEliminar = event.target.closest('.btn-eliminar');
        if (btnEliminar) {
            confirmar_eliminar(
                btnEliminar.getAttribute('data-id'),
                btnEliminar.getAttribute('data-codigo'),
                btnEliminar.getAttribute('data-es-base') === '1'
            );
        }
    }

    // ─────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────
    function init_tabla() {
        if (!tieneDataTables) {
            const aviso = document.getElementById('aviso_datatables_monedas');
            if (aviso) {
                aviso.classList.remove('d-none');
            }
            return;
        }

        dtMonedas = jq('#tabla_monedas').DataTable({
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
                dataSrc: function (json) {
                    if (json && json.error) {
                        mostrar_alerta('warning', json.error);
                    }
                    actualizar_badge_total(json ? json.recordsTotal : 0);
                    return (json && json.data) ? json.data : [];
                },
                error: function (xhr) {
                    actualizar_badge_total(0);
                    mostrar_alerta('error', (xhr.responseJSON && xhr.responseJSON.msg) || 'No se pudieron cargar las monedas.');
                }
            },
            columns: [
                { data: 'moneda', orderable: true, responsivePriority: 1 },
                { data: 'base', orderable: false, responsivePriority: 3 },
                { data: 'tasa', orderable: false, responsivePriority: 2 },
                { data: 'vigencia', orderable: false, responsivePriority: 4 },
                { data: 'estado', orderable: false, className: 'text-center', responsivePriority: 2 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
            ],
            drawCallback: function () {
                init_tooltips();
            },
            language: idioma_es('monedas')
        });
    }

    init_tabla();

    if (inputFiltro && dtMonedas) {
        inputFiltro.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function () {
                dtMonedas.search(valor).draw();
            }, 250);
        });
    }

    const tablaMonedas = document.getElementById('tabla_monedas');
    if (tablaMonedas) {
        tablaMonedas.addEventListener('click', delegar_acciones);
    }

    const btnNuevaMoneda = document.getElementById('btn_nueva_moneda');
    if (btnNuevaMoneda) {
        btnNuevaMoneda.addEventListener('click', function () {
            limpiar_modal_moneda();
            modalMoneda.show();
        });
    }

    document.getElementById('mon_codigo').addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
        actualizar_tasa_inicial_ui();
    });

    btnGuardarMoneda.addEventListener('click', guardar_moneda);
    formMoneda.addEventListener('submit', function (event) {
        event.preventDefault();
        guardar_moneda();
    });

    btnGuardarTasa.addEventListener('click', guardar_tasa);
    formTasa.addEventListener('submit', function (event) {
        event.preventDefault();
        guardar_tasa();
    });

    const tablaHistorial = document.getElementById('tabla_historial_tasas');
    if (tablaHistorial) {
        tablaHistorial.addEventListener('click', function (event) {
            const btn = event.target.closest('.btn-usar-tasa');
            if (!btn) {
                return;
            }

            document.getElementById('tasa_fecha').value = btn.getAttribute('data-fecha') || fecha_hoy();
            document.getElementById('tasa_valor').value = btn.getAttribute('data-valor') || '';
            document.getElementById('tasa_valor').focus();
        });
    }
});
</script>
<!--end::JavaScript-->
