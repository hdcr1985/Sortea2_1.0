<?php
/**
 * M05 — Métodos de pago del organizador.
 *
 * Los métodos globales se configuran con valores propios. Los métodos
 * propios permiten nombre, instrucciones y campos personalizados.
 */
?>
<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack mb-6 gap-3">
    <div class="d-flex flex-column">
        <h1 class="fw-bold text-gray-900 fs-2 mb-1">Mis métodos de pago</h1>
        <span class="text-muted fs-7">
            Configura los datos que verá el comprador y selecciona tus métodos en cada rifa.
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?php echo base_url('mis-rifas'); ?>" class="btn btn-sm btn-light fw-semibold">
            <i class="ki-duotone ki-gift fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Mis rifas
        </a>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btn_nuevo_custom">
            <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Nuevo método propio
        </button>
    </div>
</div>
<!--end::Toolbar-->

<div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-start gap-3 mb-6" role="alert">
    <i class="ki-duotone ki-information-2 fs-2 text-primary mt-1">
        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
    </i>
    <div class="text-gray-700 fs-7">
        Los métodos globales ya tienen sus campos definidos por el administrador: solo completa tus datos.
        Los métodos propios te permiten definir tus propios campos. Una rifa paga necesita al menos un método activo y completo para publicarse.
    </div>
</div>

<!--begin::Globales disponibles-->
<div class="card card-flush mb-6">
    <div class="card-header py-5">
        <div class="card-title">
            <div class="d-flex flex-column">
                <span class="fw-bold text-gray-900 fs-4">Métodos globales disponibles</span>
                <span class="text-muted fs-8">Agrega un método para completar tus datos.</span>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($disponibles)): ?>
            <div class="text-muted fs-7 py-3">Ya configuraste todos los métodos globales disponibles.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($disponibles as $disponible): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100 d-flex flex-column">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="symbol symbol-35px symbol-circle bg-light-primary">
                                    <i class="ki-duotone ki-bill fs-4 text-primary">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-900 fs-7">
                                        <?php echo htmlspecialchars((string)$disponible['met_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="text-muted fs-8">
                                        <?php echo (int)($disponible['campos_count'] ?? 0); ?> campo(s)
                                    </span>
                                </div>
                            </div>
                            <div class="text-muted fs-8 flex-grow-1 mb-3">
                                <?php echo htmlspecialchars((string)($disponible['met_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-light-primary fw-semibold btn-configurar"
                                data-met-id="<?php echo (int)$disponible['met_id']; ?>">
                                <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Usar este método
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Globales disponibles-->

<!--begin::Mis métodos-->
<div class="card card-flush">
    <div class="card-header py-5">
        <div class="card-title">
            <div class="d-flex flex-column">
                <span class="fw-bold text-gray-900 fs-4">Mis métodos configurados</span>
                <span class="text-muted fs-8">Estos métodos puedes seleccionarlos en tus rifas.</span>
            </div>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fw-bold px-3 py-2">
                <?php echo count($metodos); ?> método(s)
            </span>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($metodos)): ?>
            <div class="alert bg-light-warning border border-warning border-dashed mb-0">
                <div class="text-gray-700 fs-7">
                    Aún no tienes métodos configurados. Usa un método global o crea uno propio.
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4 mb-0">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-8 text-uppercase">
                            <th class="min-w-250px">Método</th>
                            <th class="min-w-100px">Tipo</th>
                            <th class="min-w-120px">Datos</th>
                            <th class="min-w-90px text-center">Estado</th>
                            <th class="min-w-220px text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metodos as $metodo): ?>
                            <?php
                            $orgmet_id  = (int)$metodo['orgmet_id'];
                            $es_custom  = (string)$metodo['met_scope'] === 'organizador';
                            $activo     = (int)$metodo['orgmet_activo'] === 1;
                            $completo   = !empty($metodo['completo']);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-gray-900 fs-6">
                                            <?php echo htmlspecialchars((string)$metodo['met_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <span class="text-muted fs-8">
                                            <?php echo htmlspecialchars((string)($metodo['met_descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $es_custom ? 'badge-light-info' : 'badge-light-primary'; ?> fw-bold px-3 py-2">
                                        <?php echo $es_custom ? 'Propio' : 'Global'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($completo): ?>
                                        <span class="badge badge-light-success fw-bold px-3 py-2">Completo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-warning fw-bold px-3 py-2">Faltan datos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $activo ? 'badge-light-success' : 'badge-light-danger'; ?> fw-bold px-3 py-2">
                                        <?php echo $activo ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary fw-semibold btn-valores"
                                            data-id="<?php echo $orgmet_id; ?>">
                                            <i class="ki-duotone ki-pencil fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                            Datos
                                        </button>

                                        <?php if ($es_custom): ?>
                                            <button type="button" class="btn btn-sm btn-icon btn-light-info btn-editar-custom"
                                                data-id="<?php echo $orgmet_id; ?>" data-bs-toggle="tooltip" title="Editar método propio">
                                                <i class="ki-duotone ki-setting-2 fs-4"><span class="path1"></span><span class="path2"></span></i>
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-icon <?php echo $activo ? 'btn-light-warning' : 'btn-light-success'; ?> btn-estado"
                                            data-id="<?php echo $orgmet_id; ?>" data-status="<?php echo $activo ? '1' : '0'; ?>"
                                            data-bs-toggle="tooltip" title="<?php echo $activo ? 'Desactivar' : 'Activar'; ?>">
                                            <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        </button>

                                        <?php if ($es_custom): ?>
                                            <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar-custom"
                                                data-id="<?php echo $orgmet_id; ?>" data-bs-toggle="tooltip" title="Eliminar método propio">
                                                <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
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
<!--end::Mis métodos-->

<!--begin::Modal Valores-->
<div class="modal fade" id="modal_valores" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex flex-column">
                    <h4 class="modal-title fw-bold text-gray-900" id="modal_valores_titulo">Datos del método</h4>
                    <span class="text-muted fs-8" id="modal_valores_subtitulo">Completa los datos que verá el comprador</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert alert-danger d-none mb-5 rounded-3" id="alert_errores_valores">
                    <ul class="mb-0 fs-7" id="lista_errores_valores"></ul>
                </div>
                <form id="form_valores" novalidate="novalidate">
                    <input type="hidden" id="valores_orgmet_id" value="0" />
                    <div class="d-flex flex-column gap-4" id="valores_campos"></div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_valores">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar datos
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
<!--end::Modal Valores-->

<!--begin::Modal Método Propio-->
<div class="modal fade" id="modal_custom" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex flex-column">
                    <h4 class="modal-title fw-bold text-gray-900" id="modal_custom_titulo">Nuevo método propio</h4>
                    <span class="text-muted fs-8">Define el nombre y los campos que completarás</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert alert-danger d-none mb-5 rounded-3" id="alert_errores_custom">
                    <ul class="mb-0 fs-7" id="lista_errores_custom"></ul>
                </div>

                <form id="form_custom" novalidate="novalidate">
                    <input type="hidden" name="orgmet_id" id="custom_orgmet_id" value="0" />

                    <div class="row g-5">
                        <div class="col-md-8">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="custom_nombre">Nombre</label>
                            <input type="text" name="met_nombre" id="custom_nombre" class="form-control form-control-solid"
                                placeholder="Ej: Transferencia en efectivo" maxlength="120" required />
                            <div class="invalid-feedback" data-error-for="met_nombre"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="custom_orden">Orden</label>
                            <input type="number" name="met_orden" id="custom_orden" class="form-control form-control-solid"
                                min="0" max="9999" value="1" />
                            <div class="invalid-feedback" data-error-for="met_orden"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="custom_descripcion">Descripción</label>
                            <input type="text" name="met_descripcion" id="custom_descripcion" class="form-control form-control-solid"
                                maxlength="255" placeholder="Descripción breve" />
                            <div class="invalid-feedback" data-error-for="met_descripcion"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="custom_instrucciones">Instrucciones</label>
                            <textarea name="met_instrucciones" id="custom_instrucciones" class="form-control form-control-solid"
                                rows="3" maxlength="5000" placeholder="Instrucciones para el comprador"></textarea>
                            <div class="invalid-feedback" data-error-for="met_instrucciones"></div>
                        </div>

                        <div class="col-12">
                            <div class="separator separator-dashed my-1"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-6">Campos del método</div>
                                    <div class="text-muted fs-8">Estos campos se completan en “Datos”.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary fw-semibold" id="btn_agregar_campo_custom">
                                    <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Agregar campo
                                </button>
                            </div>
                            <div class="d-flex flex-column gap-3" id="campos_custom"></div>
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
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_custom">
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
<!--end::Modal Método Propio-->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const modalValores = new bootstrap.Modal(document.getElementById('modal_valores'));
    const modalCustom = new bootstrap.Modal(document.getElementById('modal_custom'));
    const formValores = document.getElementById('form_valores');
    const formCustom = document.getElementById('form_custom');
    const btnGuardarValores = document.getElementById('btn_guardar_valores');
    const btnGuardarCustom = document.getElementById('btn_guardar_custom');
    const valoresCampos = document.getElementById('valores_campos');
    const camposCustom = document.getElementById('campos_custom');
    const alertErroresValores = document.getElementById('alert_errores_valores');
    const listaErroresValores = document.getElementById('lista_errores_valores');
    const alertErroresCustom = document.getElementById('alert_errores_custom');
    const listaErroresCustom = document.getElementById('lista_errores_custom');

    let accionEnCurso = false;
    let campoSeq = 0;

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

    function limpiar_errores(form, alert, lista) {
        if (form) form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        if (alert) alert.classList.add('d-none');
        if (lista) lista.innerHTML = '';
    }

    function mostrar_errores(form, alert, lista, errores) {
        limpiar_errores(form, alert, lista);
        if (!errores || typeof errores !== 'object') return;

        Object.keys(errores).forEach(function (campo) {
            const input = form ? form.querySelector('[name="' + campo + '"]') : null;
            if (input) input.classList.add('is-invalid');
            const feedback = form ? form.querySelector('[data-error-for="' + campo + '"]') : null;
            if (feedback) feedback.textContent = String(errores[campo]);
            if (lista) {
                const li = document.createElement('li');
                li.textContent = String(errores[campo]);
                lista.appendChild(li);
            }
        });

        if (alert) alert.classList.remove('d-none');
    }

    function opciones(texto) {
        return String(texto || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
    }

    // ─────────────────────────────────────────────────────
    // Valores
    // ─────────────────────────────────────────────────────
    function render_valores(data) {
        valoresCampos.innerHTML = '';
        const campos = Array.isArray(data.campos) ? data.campos : [];
        const valores = data.valores || {};

        document.getElementById('valores_orgmet_id').value = String(data.orgmet_id || 0);
        document.getElementById('modal_valores_titulo').textContent = 'Datos de ' + (data.met_nombre || '');
        document.getElementById('modal_valores_subtitulo').textContent = data.met_descripcion || 'Completa los datos que verá el comprador';

        if (campos.length === 0) {
            valoresCampos.innerHTML = '<div class="text-muted fs-7">Este método no tiene campos configurados.</div>';
            return;
        }

        campos.forEach(function (campo) {
            const campoId = String(campo.campo_id);
            const valor = valores[campoId] !== undefined ? String(valores[campoId]) : '';
            const tipo = String(campo.campo_tipo || 'text');
            const requerido = parseInt(campo.campo_requerido, 10) === 1;
            const div = document.createElement('div');
            div.className = 'border border-gray-200 rounded-3 p-3';

            let inputHtml = '';
            if (tipo === 'textarea') {
                inputHtml = '<textarea class="form-control form-control-solid" rows="3" name="valores[' + campoId + ']" ' +
                    (requerido ? 'required ' : '') + 'maxlength="2000">' + escapeHtml(valor) + '</textarea>';
            } else if (tipo === 'select') {
                const opcionesCampo = opciones(campo.campo_opciones);
                inputHtml = '<select class="form-select form-select-solid" name="valores[' + campoId + ']" ' + (requerido ? 'required' : '') + '>' +
                    '<option value="">Selecciona…</option>' +
                    opcionesCampo.map(function (op) {
                        return '<option value="' + escapeHtml(op) + '"' + (op === valor ? ' selected' : '') + '>' + escapeHtml(op) + '</option>';
                    }).join('') +
                    '</select>';
            } else {
                const inputType = ['email', 'tel', 'number', 'url'].indexOf(tipo) !== -1 ? tipo : 'text';
                inputHtml = '<input type="' + inputType + '" class="form-control form-control-solid" name="valores[' + campoId + ']" ' +
                    'value="' + escapeHtml(valor) + '" ' + (requerido ? 'required ' : '') + 'maxlength="500" />';
            }

            div.innerHTML =
                '<label class="form-label fw-semibold fs-7 text-gray-800">' +
                    escapeHtml(campo.campo_etiqueta || '') + (requerido ? ' <span class="text-danger">*</span>' : '') +
                '</label>' +
                inputHtml +
                (campo.campo_ayuda ? '<div class="text-muted fs-8 mt-1">' + escapeHtml(campo.campo_ayuda) + '</div>' : '') +
                (campo.campo_placeholder ? '' : '');

            valoresCampos.appendChild(div);
        });
    }

    function abrir_valores(id) {
        limpiar_errores(formValores, alertErroresValores, listaErroresValores);
        valoresCampos.innerHTML = '<div class="text-muted fs-7">Cargando campos…</div>';
        modalValores.show();

        request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) { redirigir_login(); return; }
            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cargar el método.');
                return;
            }
            render_valores(res.data.data);
        }).catch(function () {
            mostrar_alerta('error', 'Error de conexión al cargar el método.');
        });
    }

    function guardar_valores() {
        if (accionEnCurso) return;
        const id = document.getElementById('valores_orgmet_id').value;
        if (!id || id === '0') return;

        accionEnCurso = true;
        set_loading(btnGuardarValores, true);
        limpiar_errores(formValores, alertErroresValores, listaErroresValores);

        const fd = new FormData(formValores);

        request_json(BASE_URL + 'guardar-valores/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            accionEnCurso = false;
            set_loading(btnGuardarValores, false);

            if (res.status === 401) { redirigir_login(); return; }
            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }
            if (res.data && res.data.ok) {
                modalValores.hide();
                mostrar_alerta('success', res.data.msg || 'Datos guardados.');
                window.location.reload();
                return;
            }
            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(formValores, alertErroresValores, listaErroresValores, res.data.errores);
                return;
            }
            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron guardar los datos.');
        }).catch(function () {
            accionEnCurso = false;
            set_loading(btnGuardarValores, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    // ─────────────────────────────────────────────────────
    // Método propio
    // ─────────────────────────────────────────────────────
    function crear_fila_campo(campo) {
        const index = campoSeq++;
        const id = parseInt(campo.campo_id || 0, 10) || 0;
        const tipo = String(campo.campo_tipo || 'text');
        const requerido = parseInt(campo.campo_requerido ?? 1, 10) === 1;

        const div = document.createElement('div');
        div.className = 'border border-gray-200 rounded-3 p-3';
        div.innerHTML =
            '<div class="row g-3 align-items-end">' +
                '<input type="hidden" name="campos[' + index + '][campo_id]" value="' + id + '" />' +
                '<div class="col-md-4">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Etiqueta</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" name="campos[' + index + '][campo_etiqueta]" value="' + escapeHtml(campo.campo_etiqueta || '') + '" maxlength="120" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Clave (opcional)</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" name="campos[' + index + '][campo_clave]" value="' + escapeHtml(campo.campo_clave || '') + '" maxlength="60" placeholder="auto" />' +
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
                    '<input type="number" class="form-control form-control-sm form-control-solid" name="campos[' + index + '][campo_orden]" value="' + (parseInt(campo.campo_orden || 1, 10) || 1) + '" min="0" max="9999" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Placeholder</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" name="campos[' + index + '][campo_placeholder]" value="' + escapeHtml(campo.campo_placeholder || '') + '" maxlength="150" />' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label fw-semibold fs-8 text-gray-800">Ayuda</label>' +
                    '<input type="text" class="form-control form-control-sm form-control-solid" name="campos[' + index + '][campo_ayuda]" value="' + escapeHtml(campo.campo_ayuda || '') + '" maxlength="255" />' +
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
                    '<textarea class="form-control form-control-sm form-control-solid" rows="2" name="campos[' + index + '][campo_opciones]">' + escapeHtml(campo.campo_opciones || '') + '</textarea>' +
                '</div>' +
            '</div>';

        const select = div.querySelector('.campo-tipo');
        const wrap = div.querySelector('.campo-opciones-wrap');
        const actualizar = function () { wrap.classList.toggle('d-none', select.value !== 'select'); };
        select.addEventListener('change', actualizar);
        div.querySelector('.btn-quitar-campo').addEventListener('click', function () { div.remove(); });
        actualizar();

        return div;
    }

    function render_campos(campos) {
        camposCustom.innerHTML = '';
        campoSeq = 0;
        if (!Array.isArray(campos) || campos.length === 0) {
            camposCustom.appendChild(crear_fila_campo({ campo_requerido: 1, campo_orden: 1 }));
            return;
        }
        campos.forEach(function (campo) { camposCustom.appendChild(crear_fila_campo(campo)); });
    }

    function limpiar_custom() {
        formCustom.reset();
        document.getElementById('custom_orgmet_id').value = '0';
        document.getElementById('modal_custom_titulo').textContent = 'Nuevo método propio';
        document.getElementById('custom_orden').value = '1';
        render_campos([]);
        limpiar_errores(formCustom, alertErroresCustom, listaErroresCustom);
    }

    function abrir_custom(id) {
        limpiar_custom();
        request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) { redirigir_login(); return; }
            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cargar el método.');
                return;
            }
            const d = res.data.data;
            if (d.met_scope !== 'organizador') {
                mostrar_alerta('warning', 'Los métodos globales no se editan: solo completa sus datos.');
                return;
            }
            document.getElementById('custom_orgmet_id').value = String(d.orgmet_id || id);
            document.getElementById('modal_custom_titulo').textContent = 'Editar: ' + (d.met_nombre || '');
            document.getElementById('custom_nombre').value = String(d.met_nombre || '');
            document.getElementById('custom_descripcion').value = String(d.met_descripcion || '');
            document.getElementById('custom_instrucciones').value = String(d.met_instrucciones || '');
            document.getElementById('custom_orden').value = String(parseInt(d.met_orden, 10) || 1);
            render_campos(d.campos || []);
            modalCustom.show();
        }).catch(function () {
            mostrar_alerta('error', 'Error de conexión al cargar el método.');
        });
    }

    function guardar_custom() {
        if (accionEnCurso) return;
        const id = document.getElementById('custom_orgmet_id').value;
        const esNuevo = (id === '0' || id === '');
        const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + encodeURIComponent(id);
        const fd = new FormData(formCustom);

        accionEnCurso = true;
        set_loading(btnGuardarCustom, true);
        limpiar_errores(formCustom, alertErroresCustom, listaErroresCustom);

        request_json(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            accionEnCurso = false;
            set_loading(btnGuardarCustom, false);

            if (res.status === 401) { redirigir_login(); return; }
            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para esta acción.');
                return;
            }
            if (res.data && res.data.ok) {
                modalCustom.hide();
                mostrar_alerta('success', res.data.msg || 'Método guardado.');
                window.location.reload();
                return;
            }
            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(formCustom, alertErroresCustom, listaErroresCustom, res.data.errores);
                return;
            }
            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo guardar el método.');
        }).catch(function () {
            accionEnCurso = false;
            set_loading(btnGuardarCustom, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    // ─────────────────────────────────────────────────────
    // Acciones
    // ─────────────────────────────────────────────────────
    function configurar_global(metId) {
        if (accionEnCurso) return;
        accionEnCurso = true;

        const fd = new FormData();
        fd.set('met_id', String(metId));

        request_json(BASE_URL + 'configurar', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            accionEnCurso = false;
            if (res.status === 401) { redirigir_login(); return; }
            if (!res.data || res.data.ok !== true) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo configurar el método.');
                return;
            }
            render_valores(res.data.data || {});
            modalValores.show();
        }).catch(function () {
            accionEnCurso = false;
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
            if (res.data && res.data.ok) {
                mostrar_alerta('success', res.data.msg || mensajeExito);
                window.location.reload();
                return;
            }
            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo completar la acción.');
        }).catch(function () {
            accionEnCurso = false;
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    function confirmar_estado(id, status) {
        const desactivar = String(status) === '1';
        Swal.fire({
            icon: 'warning',
            title: desactivar ? '¿Desactivar el método?' : '¿Activar el método?',
            text: 'El método quedará ' + (desactivar ? 'oculto' : 'disponible') + ' para tus rifas.',
            showCancelButton: true,
            confirmButtonText: desactivar ? 'Sí, desactivar' : 'Sí, activar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: desactivar ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-success',
                cancelButton: 'btn btn-sm btn-light ms-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) enviar_accion('estado', id, 'Estado actualizado.');
        });
    }

    function confirmar_eliminar(id) {
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar el método propio?',
            text: 'Se eliminará el método y sus valores. No podrás usarlo en nuevas rifas.',
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

    document.querySelectorAll('.btn-configurar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            configurar_global(btn.getAttribute('data-met-id'));
        });
    });

    document.querySelectorAll('.btn-valores').forEach(function (btn) {
        btn.addEventListener('click', function () {
            abrir_valores(btn.getAttribute('data-id'));
        });
    });

    document.querySelectorAll('.btn-editar-custom').forEach(function (btn) {
        btn.addEventListener('click', function () {
            abrir_custom(btn.getAttribute('data-id'));
        });
    });

    document.querySelectorAll('.btn-estado').forEach(function (btn) {
        btn.addEventListener('click', function () {
            confirmar_estado(btn.getAttribute('data-id'), btn.getAttribute('data-status'));
        });
    });

    document.querySelectorAll('.btn-eliminar-custom').forEach(function (btn) {
        btn.addEventListener('click', function () {
            confirmar_eliminar(btn.getAttribute('data-id'));
        });
    });

    const btnNuevoCustom = document.getElementById('btn_nuevo_custom');
    if (btnNuevoCustom) {
        btnNuevoCustom.addEventListener('click', function () {
            limpiar_custom();
            modalCustom.show();
        });
    }

    document.getElementById('btn_agregar_campo_custom').addEventListener('click', function () {
        camposCustom.appendChild(crear_fila_campo({ campo_requerido: 1, campo_orden: 1 }));
    });

    btnGuardarValores.addEventListener('click', guardar_valores);
    formValores.addEventListener('submit', function (event) {
        event.preventDefault();
        guardar_valores();
    });

    btnGuardarCustom.addEventListener('click', guardar_custom);
    formCustom.addEventListener('submit', function (event) {
        event.preventDefault();
        guardar_custom();
    });
});
</script>
