<?php
/**
 * M05 — Métodos de pago globales.
 *
 * Contrato: MetodosPago (tabla, datos, crear, actualizar, eliminar,
 * cambiar-status). Los organizadores configuran sus valores en
 * /mis-metodos-pago.
 */
?>
<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack mb-6 gap-3">
    <div class="d-flex flex-column">
        <h1 class="fw-bold text-gray-900 fs-2 mb-1">Métodos de pago</h1>
        <span class="text-muted fs-7">
            Define las plantillas globales y sus campos. Cada organizador completa sus propios valores.
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($puede_incluir)): ?>
            <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btn_nuevo_metodo">
                <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                Nuevo método
            </button>
        <?php endif; ?>
    </div>
</div>
<!--end::Toolbar-->

<div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-start gap-3 mb-6" role="alert">
    <i class="ki-duotone ki-information-2 fs-2 text-primary mt-1">
        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
    </i>
    <div class="text-gray-700 fs-7">
        Los métodos globales son plantillas. Los organizadores no pueden cambiar sus campos; solo completan los valores.
        Si un método ya está en uso por una rifa activa, no podrá desactivarse.
    </div>
</div>

<div class="card card-flush">
    <div class="card-header align-items-center py-5 gap-3">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="filtro_metodos" class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar método..." autocomplete="off" />
            </div>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fw-bold px-3 py-2" id="badge_total_metodos">—</span>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="alert alert-warning d-none mb-4" id="aviso_datatables_metodos">
            No se pudo inicializar DataTables. Recarga la página.
        </div>
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_metodos">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                        <th class="min-w-250px">Método</th>
                        <th class="min-w-100px">Campos</th>
                        <th class="min-w-130px">Organizaciones</th>
                        <th class="min-w-90px text-center">Estado</th>
                        <th class="min-w-130px text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-700"></tbody>
            </table>
        </div>
    </div>
</div>

<!--begin::Modal Método-->
<div class="modal fade" id="modal_metodo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-bill fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_metodo_titulo">Nuevo método de pago</h4>
                        <span class="text-muted fs-8">Plantilla global y campos que completará el organizador</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert alert-danger d-none mb-5 rounded-3" id="alert_errores_metodo">
                    <ul class="mb-0 fs-7" id="lista_errores_metodo"></ul>
                </div>

                <form id="form_metodo" enctype="multipart/form-data" novalidate="novalidate">
                    <input type="hidden" name="met_id" id="met_id" value="0" />

                    <div class="row g-5">
                        <div class="col-md-8">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="met_nombre">Nombre</label>
                            <input type="text" name="met_nombre" id="met_nombre" class="form-control form-control-solid"
                                placeholder="Ej: Transferencia bancaria" maxlength="120" autocomplete="off" required />
                            <div class="invalid-feedback" data-error-for="met_nombre"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="met_orden">Orden</label>
                            <input type="number" name="met_orden" id="met_orden" class="form-control form-control-solid"
                                min="0" max="9999" step="1" value="1" />
                            <div class="invalid-feedback" data-error-for="met_orden"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="met_descripcion">Descripción</label>
                            <input type="text" name="met_descripcion" id="met_descripcion" class="form-control form-control-solid"
                                placeholder="Descripción breve" maxlength="255" autocomplete="off" />
                            <div class="invalid-feedback" data-error-for="met_descripcion"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="met_instrucciones">Instrucciones</label>
                            <textarea name="met_instrucciones" id="met_instrucciones" class="form-control form-control-solid"
                                rows="3" maxlength="5000" placeholder="Instrucciones generales para el comprador"></textarea>
                            <div class="invalid-feedback" data-error-for="met_instrucciones"></div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="met_icono">Icono</label>
                            <select name="met_icono" id="met_icono" class="form-select form-select-solid">
                                <option value="ki-bill">Factura / pago</option>
                                <option value="ki-dollar">Dólar</option>
                                <option value="ki-bank">Banco</option>
                                <option value="ki-wallet">Billetera</option>
                                <option value="ki-credit-cart">Tarjeta</option>
                                <option value="ki-phone">Teléfono</option>
                                <option value="ki-abstract-26">Genérico</option>
                                <option value="ki-gift">Regalo</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="met_icono"></div>
                            <div class="text-muted fs-8 mt-1">Se usa si no colocas una imagen.</div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="met_logo">Logo (opcional)</label>
                            <input type="file" name="met_logo" id="met_logo" class="form-control form-control-solid"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
                            <div class="invalid-feedback" data-error-for="met_logo"></div>
                            <div class="text-muted fs-8 mt-1">JPG, PNG o WEBP. Máximo 4 MB. Se usa en lugar del icono.</div>
                            <div class="form-check form-check-custom form-check-solid mt-2">
                                <input class="form-check-input" type="checkbox" name="met_quitar_imagen" id="met_quitar_imagen" value="1" />
                                <label class="form-check-label fs-8 ms-2" for="met_quitar_imagen">Quitar logo actual</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border border-gray-200 rounded-3 p-3 bg-light d-flex align-items-center gap-3">
                                <div class="symbol symbol-50px symbol-circle bg-light-primary flex-shrink-0">
                                    <span class="symbol-label" id="met_media_preview_icon">
                                        <i class="ki-duotone ki-bill fs-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    </span>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-900 fs-7">Vista previa</span>
                                    <span class="text-muted fs-8" id="met_media_preview_text">Icono por defecto</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border border-gray-200 rounded-3 p-3 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Método activo</div>
                                    <div class="text-muted fs-8">Solo los métodos activos se muestran a los organizadores.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input type="hidden" name="met_status" value="0" />
                                    <input class="form-check-input" type="checkbox" name="met_status" id="met_status" value="1" checked />
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="separator separator-dashed my-1"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-6">Campos del método</div>
                                    <div class="text-muted fs-8">El organizador solo completará estos campos.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary fw-semibold" id="btn_agregar_campo">
                                    <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Agregar campo
                                </button>
                            </div>
                            <div class="d-flex flex-column gap-3" id="campos_metodo"></div>
                            <div class="invalid-feedback d-block" data-error-for="campos"></div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_metodo">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar método
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
<!--end::Modal Método-->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MEDIA_METODO_BASE = <?php echo json_encode(base_url('media/metodo/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const jq = window.jQuery || null;
    const tieneDataTables = !!(jq && jq.fn && jq.fn.DataTable);
    const modalEl = document.getElementById('modal_metodo');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('form_metodo');
    const btnGuardar = document.getElementById('btn_guardar_metodo');
    const alertErrores = document.getElementById('alert_errores_metodo');
    const listaErrores = document.getElementById('lista_errores_metodo');
    const camposWrap = document.getElementById('campos_metodo');
    const inputFiltro = document.getElementById('filtro_metodos');

    let dt = null;
    let filtroTimer = null;
    let accionEnCurso = false;
    let campoSeq = 0;
    let metImagenActual = '';
    let metLogoObjectUrl = null;

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

    function set_loading(btn, activo) {
        if (!btn) return;
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
            return response.json().catch(function () { return {}; }).then(function (payload) {
                return { status: response.status, data: payload || {} };
            });
        });
    }

    function redirigir_login() {
        window.location.href = LOGIN_EXPIRED;
    }

    function primer_error(errores) {
        if (!errores || typeof errores !== 'object') return '';
        const claves = Object.keys(errores);
        for (let i = 0; i < claves.length; i++) {
            if (errores[claves[i]]) return String(errores[claves[i]]);
        }
        return '';
    }

    function limpiar_errores() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        alertErrores.classList.add('d-none');
        listaErrores.innerHTML = '';
    }

    function mostrar_errores(errores) {
        limpiar_errores();
        if (!errores || typeof errores !== 'object') return;

        Object.keys(errores).forEach(function (campo) {
            const input = form.querySelector('[name="' + campo + '"]');
            if (input) input.classList.add('is-invalid');
            const feedback = form.querySelector('[data-error-for="' + campo + '"]');
            if (feedback) feedback.textContent = String(errores[campo]);
            const li = document.createElement('li');
            li.textContent = String(errores[campo]);
            listaErrores.appendChild(li);
        });

        alertErrores.classList.remove('d-none');
    }

    function init_tooltips() {
        if (!window.bootstrap || !bootstrap.Tooltip) return;
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
            loadingRecords: 'Cargando...',
            processing: 'Procesando...',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron ' + entidad,
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        };
    }

    function fila_data(dtInstance, elemento) {
        if (!dtInstance) return {};
        const fila = elemento.closest('tr');
        if (!fila) return {};
        try { return dtInstance.row(fila).data() || {}; } catch (e) { return {}; }
    }

    function opciones_select(texto) {
        return String(texto || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function crear_fila_campo(campo) {
        const index = campoSeq++;
        const id = parseInt(campo.campo_id || 0, 10) || 0;
        const tipo = String(campo.campo_tipo || 'text');
        const requerido = parseInt(campo.campo_requerido ?? 1, 10) === 1;

        const div = document.createElement('div');
        div.className = 'border border-gray-200 rounded-3 p-3';
        div.setAttribute('data-campo-index', String(index));
        div.innerHTML =
            '<div class="row g-3 align-items-end">' +
                '<input type="hidden" name="campos[' + index + '][campo_id]" value="' + id + '" />' +
                '<div class="col-md-4">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Etiqueta</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid campo-etiqueta" ' +
                        'name="campos[' + index + '][campo_etiqueta]" value="' + escapeHtml(campo.campo_etiqueta || '') + '" maxlength="120" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Clave (opcional)</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" ' +
                        'name="campos[' + index + '][campo_clave]" value="' + escapeHtml(campo.campo_clave || '') + '" maxlength="60" placeholder="auto" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Tipo</label>' +
                    '<select class="form-select form-select-sm form-select-solid campo-tipo" name="campos[' + index + '][campo_tipo]">' +
                        ['text', 'email', 'tel', 'number', 'url', 'textarea', 'select'].map(function (t) {
                            return '<option value="' + t + '"' + (t === tipo ? ' selected' : '') + '>' + t + '</option>';
                        }).join('') +
                    '</select>' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Orden</label>' +
                    '<input type="number" class="form-control form-control-sm form-control-solid" ' +
                        'name="campos[' + index + '][campo_orden]" value="' + (parseInt(campo.campo_orden || 1, 10) || 1) + '" min="0" max="9999" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Placeholder</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" ' +
                        'name="campos[' + index + '][campo_placeholder]" value="' + escapeHtml(campo.campo_placeholder || '') + '" maxlength="150" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Ayuda</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" ' +
                        'name="campos[' + index + '][campo_ayuda]" value="' + escapeHtml(campo.campo_ayuda || '') + '" maxlength="255" />' +
                '</div>' +
                '<div class="col-md-3 d-flex align-items-center">' +
                    '<div class="form-check form-switch form-check-custom form-check-solid mt-5">' +
                        '<input class="form-check-input" type="checkbox" name="campos[' + index + '][campo_requerido]" value="1"' + (requerido ? ' checked' : '') + ' />' +
                        '<label class="form-check-label fs-8 ms-2">Obligatorio</label>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-3 d-flex align-items-center justify-content-end">' +
                    '<button type="button" class="btn btn-sm btn-light-danger btn-quitar-campo mt-5">Quitar</button>' +
                '</div>' +
                '<div class="col-12 campo-opciones-wrap d-none">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Opciones (una por línea)</label>' +
                    '<textarea class="form-control form-control-sm form-control-solid" rows="2" ' +
                        'name="campos[' + index + '][campo_opciones]">' + escapeHtml(campo.campo_opciones || '') + '</textarea>' +
                '</div>' +
            '</div>';

        const select = div.querySelector('.campo-tipo');
        const wrapOpciones = div.querySelector('.campo-opciones-wrap');
        const actualizarOpciones = function () {
            wrapOpciones.classList.toggle('d-none', select.value !== 'select');
        };
        select.addEventListener('change', actualizarOpciones);
        div.querySelector('.btn-quitar-campo').addEventListener('click', function () {
            div.remove();
        });
        actualizarOpciones();

        return div;
    }

    function render_campos(campos) {
        camposWrap.innerHTML = '';
        campoSeq = 0;
        if (!Array.isArray(campos) || campos.length === 0) {
            agregar_campo();
            return;
        }
        campos.forEach(function (campo) {
            camposWrap.appendChild(crear_fila_campo(campo));
        });
    }

    function agregar_campo() {
        camposWrap.appendChild(crear_fila_campo({ campo_requerido: 1, campo_orden: 1 }));
    }

    function actualizar_media_preview() {
        const icono = document.getElementById('met_icono').value || 'ki-bill';
        const fileInput = document.getElementById('met_logo');
        const quitar = document.getElementById('met_quitar_imagen');
        const cont = document.getElementById('met_media_preview_icon');
        const texto = document.getElementById('met_media_preview_text');
        const metId = parseInt(document.getElementById('met_id').value, 10) || 0;

        if (!cont) return;

        if (metLogoObjectUrl) {
            URL.revokeObjectURL(metLogoObjectUrl);
            metLogoObjectUrl = null;
        }

        if (fileInput && fileInput.files && fileInput.files[0]) {
            metLogoObjectUrl = URL.createObjectURL(fileInput.files[0]);
            cont.innerHTML = '<img src="' + escapeHtml(metLogoObjectUrl) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />';
            if (texto) texto.textContent = 'Nuevo logo seleccionado';
            return;
        }

        if (!quitar || !quitar.checked) {
            if (metId > 0 && metImagenActual !== '') {
                cont.innerHTML = '<img src="' + escapeHtml(MEDIA_METODO_BASE + metId) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />';
                if (texto) texto.textContent = 'Logo actual';
                return;
            }
        }

        cont.innerHTML = '<i class="ki-duotone ' + escapeHtml(icono) + ' fs-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>';
        if (texto) texto.textContent = 'Icono seleccionado';
    }

    function limpiar_modal() {
        form.reset();
        document.getElementById('met_id').value = '0';
        document.getElementById('modal_metodo_titulo').textContent = 'Nuevo método de pago';
        document.getElementById('met_orden').value = '1';
        document.getElementById('met_icono').value = 'ki-bill';
        document.getElementById('met_quitar_imagen').checked = false;
        document.getElementById('met_status').checked = true;
        metImagenActual = '';
        render_campos([]);
        actualizar_media_preview();
        limpiar_errores();
    }

    function abrir_edicion(id) {
        limpiar_modal();
        request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) { redirigir_login(); return; }
            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cargar el método.');
                return;
            }
            const d = res.data.data;
            document.getElementById('met_id').value = String(d.met_id || id);
            document.getElementById('modal_metodo_titulo').textContent = 'Editar: ' + (d.met_nombre || '');
            document.getElementById('met_nombre').value = String(d.met_nombre || '');
            document.getElementById('met_descripcion').value = String(d.met_descripcion || '');
            document.getElementById('met_instrucciones').value = String(d.met_instrucciones || '');
            document.getElementById('met_orden').value = String(parseInt(d.met_orden, 10) || 1);
            document.getElementById('met_icono').value = String(d.met_icono || 'ki-bill');
            document.getElementById('met_quitar_imagen').checked = false;
            metImagenActual = String(d.met_imagen || '');
            document.getElementById('met_status').checked = parseInt(d.met_status, 10) === 1;
            actualizar_media_preview();
            render_campos(d.campos || []);
            modal.show();
        }).catch(function () {
            mostrar_alerta('error', 'Error de conexión al cargar el método.');
        });
    }

    function guardar_metodo() {
        if (accionEnCurso) return;
        const id = document.getElementById('met_id').value;
        const esNuevo = (id === '0' || id === '');
        const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + encodeURIComponent(id);
        const fd = new FormData(form);

        accionEnCurso = true;
        set_loading(btnGuardar, true);
        limpiar_errores();

        request_json(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            accionEnCurso = false;
            set_loading(btnGuardar, false);

            if (res.status === 401) { redirigir_login(); return; }
            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }
            if (res.data && res.data.ok) {
                modal.hide();
                mostrar_alerta('success', res.data.msg || 'Método guardado correctamente.');
                if (dt) dt.ajax.reload(null, false);
                return;
            }
            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(res.data.errores);
                return;
            }
            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo guardar el método.');
        }).catch(function () {
            accionEnCurso = false;
            set_loading(btnGuardar, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    function enviar_accion(ruta, id, mensajeExito) {
        if (accionEnCurso) return;
        accionEnCurso = true;

        request_json(BASE_URL + ruta + '/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            accionEnCurso = false;
            if (res.status === 401) { redirigir_login(); return; }
            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }
            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || mensajeExito);
                if (dt) dt.ajax.reload(null, false);
                return;
            }
            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo completar la acción.');
        }).catch(function () {
            accionEnCurso = false;
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    function confirmar_status(id, status, enRifas) {
        if (String(status) === '1' && enRifas) {
            mostrar_alerta('warning', 'No puedes desactivar un método usado por rifas activas.');
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: String(status) === '1' ? '¿Desactivar el método?' : '¿Activar el método?',
            text: 'El método quedará ' + (String(status) === '1' ? 'oculto' : 'disponible') + ' para los organizadores.',
            showCancelButton: true,
            confirmButtonText: String(status) === '1' ? 'Sí, desactivar' : 'Sí, activar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: String(status) === '1' ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-success',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) enviar_accion('cambiar-status', id, 'Estado actualizado.');
        });
    }

    function confirmar_eliminar(id, enUso) {
        if (enUso) {
            mostrar_alerta('warning', 'No puedes eliminar un método usado por organizadores o rifas activas.');
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar el método?',
            text: 'El método se eliminará lógicamente del catálogo global.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) enviar_accion('eliminar', id, 'Método eliminado.');
        });
    }

    function delegar_acciones(event) {
        const editar = event.target.closest('.btn-editar');
        if (editar) { abrir_edicion(editar.getAttribute('data-id')); return; }

        const status = event.target.closest('.btn-status');
        if (status) {
            confirmar_status(
                status.getAttribute('data-id'),
                status.getAttribute('data-status'),
                status.getAttribute('data-rifas') === '1'
            );
            return;
        }

        const eliminar = event.target.closest('.btn-eliminar');
        if (eliminar) {
            confirmar_eliminar(eliminar.getAttribute('data-id'), eliminar.getAttribute('data-uso') === '1');
        }
    }

    function init_tabla() {
        if (!tieneDataTables) {
            const aviso = document.getElementById('aviso_datatables_metodos');
            if (aviso) aviso.classList.remove('d-none');
            return;
        }

        dt = jq('#tabla_metodos').DataTable({
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
                    if (json && json.error) mostrar_alerta('warning', json.error);
                    const badge = document.getElementById('badge_total_metodos');
                    if (badge) badge.textContent = Number((json && json.recordsTotal) || 0).toLocaleString('es') + ' método(s)';
                    return (json && json.data) ? json.data : [];
                },
                error: function (xhr) {
                    mostrar_alerta('error', (xhr.responseJSON && xhr.responseJSON.msg) || 'No se pudieron cargar los métodos.');
                }
            },
            columns: [
                { data: 'metodo', orderable: true, responsivePriority: 1 },
                { data: 'campos', orderable: false, responsivePriority: 3 },
                { data: 'orgs', orderable: false, responsivePriority: 4 },
                { data: 'estado', orderable: false, className: 'text-center', responsivePriority: 2 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
            ],
            drawCallback: function () { init_tooltips(); },
            language: idioma_es('métodos')
        });
    }

    init_tabla();

    if (inputFiltro && dt) {
        inputFiltro.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function () { dt.search(valor).draw(); }, 250);
        });
    }

    const tabla = document.getElementById('tabla_metodos');
    if (tabla) tabla.addEventListener('click', delegar_acciones);

    const btnNuevo = document.getElementById('btn_nuevo_metodo');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function () {
            limpiar_modal();
            modal.show();
        });
    }

    document.getElementById('btn_agregar_campo').addEventListener('click', agregar_campo);
    document.getElementById('met_icono').addEventListener('change', actualizar_media_preview);
    document.getElementById('met_logo').addEventListener('change', actualizar_media_preview);
    document.getElementById('met_quitar_imagen').addEventListener('change', actualizar_media_preview);
    btnGuardar.addEventListener('click', guardar_metodo);
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        guardar_metodo();
    });
});
</script>
