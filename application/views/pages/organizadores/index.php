<?php
/**
 * M01 - Núcleo multi-tenant y subdominios
 * Vista administrativa de organizadores.
 *
 * Contrato de datos provisto por el controlador:
 * - $url_base   string  Raíz del módulo (por defecto "organizadores").
 * - $base_domain string Dominio base para subdominios (por defecto "sortea2.net").
 *
 * Contrato de tabla server-side (endpoint `tabla`):
 * - columnas: organizador, contacto, subdominio, estado, acciones
 * - Los botones de acciones deben incluir, como mínimo:
 *   data-id, data-nombre, data-status y data-slug.
 */

$url_base = (isset($url_base) && is_string($url_base) && trim($url_base) !== '')
    ? trim($url_base, '/')
    : 'organizadores';

$base_domain = (isset($base_domain) && is_string($base_domain) && trim($base_domain) !== '')
    ? ltrim(trim($base_domain), '.')
    : 'sortea2.net';
?>

<!--begin::Card Organizadores-->
<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold text-gray-900 mb-1">Organizadores</h3>
                <span class="text-muted fs-7">Gestiona las tiendas, subdominios y estados de cada organizador.</span>
            </div>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text"
                    id="filtro_organizadores"
                    class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar organizadores..."
                    aria-label="Buscar organizadores" />
            </div>
            <button type="button"
                class="btn btn-sm btn-light-primary fw-semibold px-4 py-2"
                id="btn_recargar_organizadores">
                <i class="ki-duotone ki-arrows-circle fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Actualizar
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_organizadores">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th scope="col" class="min-w-220px">Organizador</th>
                        <th scope="col" class="min-w-200px">Contacto</th>
                        <th scope="col" class="min-w-200px">Subdominio</th>
                        <th scope="col" class="min-w-110px text-center">Estado</th>
                        <th scope="col" class="min-w-140px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold"></tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card Organizadores-->

<!--begin::Modal Editar Organizador-->
<div class="modal fade" id="modal_organizador" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-shop fs-3 text-primary"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_organizador_titulo">Editar organizador</h4>
                        <span class="text-muted fs-8">Actualiza los datos públicos y el estado de la tienda</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_organizador">
                    <ul class="mb-0 fs-7" id="lista_errores_organizador"></ul>
                </div>
                <!--end::Alerta de errores-->

                <form id="form_organizador" novalidate="novalidate">
                    <input type="hidden" name="org_id" id="org_id" value="0" />

                    <div class="row g-5">
                        <div class="col-md-12">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="org_nombre_publico">Nombre público</label>
                            <input type="text"
                                name="org_nombre_publico"
                                id="org_nombre_publico"
                                class="form-control form-control-solid"
                                placeholder="Ej: Sorteos La Suerte"
                                maxlength="150"
                                required />
                            <div class="invalid-feedback" data-error-for="org_nombre_publico"></div>
                            <div class="text-muted fs-8 mt-1">Es el nombre que verán los visitantes en el sitio público.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="org_email">Email de contacto</label>
                            <input type="email"
                                name="org_email"
                                id="org_email"
                                class="form-control form-control-solid"
                                placeholder="contacto@ejemplo.com"
                                maxlength="150" />
                            <div class="invalid-feedback" data-error-for="org_email"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="org_telefono">Teléfono de contacto</label>
                            <input type="tel"
                                name="org_telefono"
                                id="org_telefono"
                                class="form-control form-control-solid"
                                placeholder="+58 412 000 0000"
                                maxlength="30" />
                            <div class="invalid-feedback" data-error-for="org_telefono"></div>
                        </div>

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <div class="col-md-6">
                            <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Organizador verificado</div>
                                    <div class="text-muted fs-8">Marca la cuenta como verificada.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="org_verificado" id="org_verificado" value="1" />
                                </div>
                            </div>
                            <div class="invalid-feedback" data-error-for="org_verificado"></div>
                        </div>

                        <div class="col-md-6">
                            <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Tienda activa</div>
                                    <div class="text-muted fs-8">Las tiendas inactivas no publican su sitio.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="org_status" id="org_status" value="1" />
                                </div>
                            </div>
                            <div class="invalid-feedback" data-error-for="org_status"></div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_organizador">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar cambios
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
<!--end::Modal Editar Organizador-->

<!--begin::Modal Cambiar Subdominio-->
<div class="modal fade" id="modal_slug" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-warning">
                        <i class="ki-duotone ki-route fs-3 text-warning">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Cambiar subdominio</h4>
                        <span class="text-muted fs-8">La URL pública del organizador cambiará</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-start gap-3 mb-6" role="alert">
                    <i class="ki-duotone ki-information-2 fs-2 text-warning mt-1">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                    </i>
                    <div class="text-gray-700 fs-7">
                        Al cambiar el subdominio, la URL pública anterior dejará de funcionar. Comparte la nueva URL con tus visitantes.
                    </div>
                </div>

                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_slug">
                    <ul class="mb-0 fs-7" id="lista_errores_slug"></ul>
                </div>
                <!--end::Alerta de errores-->

                <form id="form_slug" novalidate="novalidate">
                    <input type="hidden" name="org_id" id="slug_org_id" value="0" />

                    <label class="form-label required fw-semibold fs-7 text-gray-800" for="org_slug">Nuevo subdominio</label>
                    <div class="input-group input-group-solid">
                        <input type="text"
                            name="org_slug"
                            id="org_slug"
                            class="form-control form-control-solid"
                            placeholder="mi-sorteo"
                            maxlength="63"
                            minlength="3"
                            pattern="[a-z0-9][a-z0-9-]{1,61}[a-z0-9]"
                            autocomplete="off"
                            spellcheck="false"
                            required />
                        <span class="input-group-text text-gray-700">.<?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="org_slug"></div>
                    <div class="text-muted fs-8 mt-2">
                        Usa entre 3 y 63 caracteres: minúsculas, números y guiones. No puede iniciar ni terminar con guion.
                    </div>

                    <div class="border border-gray-200 rounded-3 bg-light p-4 mt-5">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Vista previa de la URL pública</div>
                        <div class="d-flex align-items-center gap-2 text-gray-900 fw-semibold fs-7 text-break">
                            <i class="ki-duotone ki-geolocation fs-4 text-primary"><span class="path1"></span><span class="path2"></span></i>
                            <span id="slug_preview">https://<?php echo htmlspecialchars($base_domain, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-warning fw-semibold px-4 py-2" id="btn_guardar_slug">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Cambiar subdominio
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
<!--end::Modal Cambiar Subdominio-->

<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const BASE_DOMAIN = <?php echo json_encode($base_domain, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const modalOrganizadorEl = document.getElementById('modal_organizador');
    const modalSlugEl = document.getElementById('modal_slug');
    const modalOrganizador = new bootstrap.Modal(modalOrganizadorEl);
    const modalSlug = new bootstrap.Modal(modalSlugEl);
    const formOrganizador = document.getElementById('form_organizador');
    const formSlug = document.getElementById('form_slug');
    const btnGuardarOrganizador = document.getElementById('btn_guardar_organizador');
    const btnGuardarSlug = document.getElementById('btn_guardar_slug');
    const btnRecargar = document.getElementById('btn_recargar_organizadores');
    const inputFiltro = document.getElementById('filtro_organizadores');
    const alertErroresOrganizador = document.getElementById('alert_errores_organizador');
    const listaErroresOrganizador = document.getElementById('lista_errores_organizador');
    const alertErroresSlug = document.getElementById('alert_errores_slug');
    const listaErroresSlug = document.getElementById('lista_errores_slug');
    const slugInput = document.getElementById('org_slug');
    const slugPreview = document.getElementById('slug_preview');

    let dt = null;
    let filtroTimer = null;
    let status_original_organizador = '';

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

    function normalizar_slug(valor) {
        let texto = String(valor || '').toLowerCase();

        try {
            texto = texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        } catch (e) {
            texto = texto.replace(/[áàäâã]/g, 'a')
                .replace(/[éèëê]/g, 'e')
                .replace(/[íìïî]/g, 'i')
                .replace(/[óòöôõ]/g, 'o')
                .replace(/[úùüû]/g, 'u')
                .replace(/[ñ]/g, 'n');
        }

        return texto
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-+/g, '-')
            .slice(0, 63)
            .replace(/^-+|-+$/g, '');
    }

    function slug_valido(valor) {
        return /^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/.test(String(valor || ''));
    }

    function actualizar_preview_slug() {
        if (!slugPreview) {
            return;
        }

        const slug = normalizar_slug(slugInput ? slugInput.value : '');
        slugPreview.textContent = slug !== ''
            ? 'https://' + slug + '.' + BASE_DOMAIN
            : 'https://' + BASE_DOMAIN;
    }

    function limpiar_errores(form, alertEl, listaEl) {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
        });
        if (listaEl) {
            listaEl.innerHTML = '';
        }
        if (alertEl) {
            alertEl.classList.add('d-none');
        }
    }

    function mostrar_errores(form, alertEl, listaEl, errores) {
        limpiar_errores(form, alertEl, listaEl);

        if (!errores || typeof errores !== 'object') {
            return;
        }

        const mapa = {};
        Object.keys(errores).forEach(function (clave) {
            const destino = clave === 'slug' ? 'org_slug' : clave;
            mapa[destino] = errores[clave];
        });

        let total = 0;
        Object.keys(mapa).forEach(function (campo) {
            const mensaje = mapa[campo];
            if (!mensaje) {
                return;
            }

            const input = form.querySelector('[name="' + String(campo).replace(/"/g, '\\"') + '"]');
            const feedback = form.querySelector('[data-error-for="' + String(campo).replace(/"/g, '\\"') + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = mensaje;
            }

            if (listaEl) {
                const li = document.createElement('li');
                li.textContent = mensaje;
                listaEl.appendChild(li);
            }
            total++;
        });

        if (total > 0 && alertEl) {
            alertEl.classList.remove('d-none');
        }
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

    function obtener_contexto(btn) {
        const fila = $(btn).closest('tr');
        const datosFila = (dt && fila.length) ? (dt.row(fila).data() || {}) : {};

        return {
            id: btn.dataset.id || datosFila.org_id || datosFila.id || '',
            nombre: btn.dataset.nombre || datosFila.org_nombre_publico || datosFila.nombre || '',
            slug: btn.dataset.slug || datosFila.org_slug || datosFila.slug || '',
            status: btn.dataset.status !== undefined ? btn.dataset.status : (datosFila.org_status || '')
        };
    }

    function limpiar_modal_organizador() {
        formOrganizador.reset();
        document.getElementById('org_id').value = '0';
        document.getElementById('org_nombre_publico').value = '';
        document.getElementById('org_email').value = '';
        document.getElementById('org_telefono').value = '';
        document.getElementById('org_verificado').checked = false;
        document.getElementById('org_status').checked = true;
        status_original_organizador = '';
        document.getElementById('modal_organizador_titulo').textContent = 'Editar organizador';
        limpiar_errores(formOrganizador, alertErroresOrganizador, listaErroresOrganizador);
    }

    function limpiar_modal_slug() {
        formSlug.reset();
        document.getElementById('slug_org_id').value = '0';
        slugInput.value = '';
        limpiar_errores(formSlug, alertErroresSlug, listaErroresSlug);
        actualizar_preview_slug();
    }

    function cargar_datos(id) {
        return request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                window.location.href = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                return null;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron cargar los datos del organizador.');
                return null;
            }

            return res.data.data;
        });
    }

    // DataTable server-side
    dt = $('#tabla_organizadores').DataTable({
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
            data: function (d) {
                /*
                 * El backend ordena por: 0 nombre, 1 slug, 2 email, 3 estado.
                 * La vista muestra Contacto (email) antes que Subdominio (slug),
                 * por lo que se remapean los índices de orden para no ordenar
                 * por una columna distinta a la solicitada.
                 */
                if (d.order && d.order.length) {
                    const mapa = { 0: 0, 1: 2, 2: 1, 3: 3, 4: 4 };
                    d.order.forEach(function (orden) {
                        if (orden.column !== undefined && mapa[orden.column] !== undefined) {
                            orden.column = String(mapa[orden.column]);
                        }
                    });
                }
            },
            error: function (xhr) {
                set_loading(btnRecargar, false);

                let msg = 'No se pudieron cargar los organizadores. Inténtalo de nuevo.';
                if (xhr.status === 403) {
                    msg = 'No tienes permiso para consultar organizadores.';
                } else if (xhr.status === 401) {
                    msg = 'Tu sesión expiró. Inicia sesión nuevamente.';
                }
                mostrar_alerta('error', msg);
            }
        },
        columns: [
            { data: 'organizador', orderable: true, responsivePriority: 1 },
            { data: 'contacto', orderable: true, responsivePriority: 3 },
            { data: 'dominio', orderable: true, responsivePriority: 2 },
            { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
            { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
        ],
        drawCallback: function () {
            set_loading(btnRecargar, false);
            $('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
        },
        language: {
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span> Cargando organizadores...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ organizadores',
            infoEmpty: 'No hay organizadores para mostrar',
            infoFiltered: '(filtrados de _MAX_ registros)',
            zeroRecords: 'No se encontraron organizadores',
            emptyTable: 'No hay organizadores registrados',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });

    if (inputFiltro) {
        inputFiltro.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function () {
                dt.search(valor).draw();
            }, 250);
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', function () {
            if (btnRecargar.disabled) {
                return;
            }
            set_loading(btnRecargar, true);
            dt.ajax.reload(null, false);
        });
    }

    // Normalización y vista previa del subdominio
    if (slugInput) {
        slugInput.addEventListener('input', function () {
            const posicion = this.selectionStart;
            const normalizado = normalizar_slug(this.value);
            if (this.value !== normalizado) {
                this.value = normalizado;
                try {
                    this.setSelectionRange(Math.min(posicion, normalizado.length), Math.min(posicion, normalizado.length));
                } catch (e) {}
            }
            actualizar_preview_slug();
        });
    }

    // Guardar datos generales
    btnGuardarOrganizador.addEventListener('click', function () {
        const id = document.getElementById('org_id').value;
        if (!id || id === '0') {
            mostrar_alerta('error', 'No se identificó el organizador a actualizar.');
            return;
        }

        const nombre = document.getElementById('org_nombre_publico').value.trim();
        if (nombre === '') {
            mostrar_errores(formOrganizador, alertErroresOrganizador, listaErroresOrganizador, {
                org_nombre_publico: 'El nombre público es obligatorio.'
            });
            return;
        }

        const fd = new FormData(formOrganizador);
        fd.set('org_nombre_publico', nombre);
        fd.set('org_verificado', document.getElementById('org_verificado').checked ? '1' : '0');
        fd.set('org_status', document.getElementById('org_status').checked ? '1' : '0');

        set_loading(btnGuardarOrganizador, true);
        limpiar_errores(formOrganizador, alertErroresOrganizador, listaErroresOrganizador);

        request_json(BASE_URL + 'actualizar/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) {
            set_loading(btnGuardarOrganizador, false);

            if (res.status === 401) {
                window.location.href = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                return;
            }

            if (res.data.ok) {
                const status_deseado = document.getElementById('org_status').checked ? '1' : '0';

                // El endpoint principal de edición no modifica el estado.
                // Si el switch cambió, se sincroniza con el endpoint dedicado.
                if (status_original_organizador !== '' && status_deseado !== status_original_organizador) {
                    set_loading(btnGuardarOrganizador, true);

                    request_json(BASE_URL + 'cambiar_status/' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (resStatus) {
                        set_loading(btnGuardarOrganizador, false);
                        modalOrganizador.hide();

                        if (resStatus.data && resStatus.data.ok) {
                            mostrar_alerta('success', res.data.msg || 'Organizador actualizado correctamente.');
                        } else {
                            mostrar_alerta('warning', 'Los datos se guardaron, pero no se pudo cambiar el estado de la tienda.');
                        }

                        dt.ajax.reload(null, false);
                    })
                    .catch(function () {
                        set_loading(btnGuardarOrganizador, false);
                        modalOrganizador.hide();
                        mostrar_alerta('warning', 'Los datos se guardaron, pero no se pudo cambiar el estado de la tienda.');
                        dt.ajax.reload(null, false);
                    });

                    return;
                }

                modalOrganizador.hide();
                mostrar_alerta('success', res.data.msg || 'Organizador actualizado correctamente.');
                dt.ajax.reload(null, false);
                return;
            }

            if (res.data.errores) {
                mostrar_errores(formOrganizador, alertErroresOrganizador, listaErroresOrganizador, res.data.errores);
                return;
            }

            mostrar_alerta('error', res.data.msg || 'No se pudo actualizar el organizador.');
        })
        .catch(function () {
            set_loading(btnGuardarOrganizador, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    });

    // Guardar nuevo subdominio
    btnGuardarSlug.addEventListener('click', function () {
        const id = document.getElementById('slug_org_id').value;
        if (!id || id === '0') {
            mostrar_alerta('error', 'No se identificó el organizador.');
            return;
        }

        const slug = normalizar_slug(slugInput.value);
        slugInput.value = slug;

        if (!slug_valido(slug)) {
            mostrar_errores(formSlug, alertErroresSlug, listaErroresSlug, {
                org_slug: 'El subdominio debe tener entre 3 y 63 caracteres y solo puede usar minúsculas, números y guiones (sin guion al inicio ni al final).'
            });
            return;
        }

        const fd = new FormData();
        fd.append('org_slug', slug);

        set_loading(btnGuardarSlug, true);
        limpiar_errores(formSlug, alertErroresSlug, listaErroresSlug);

        request_json(BASE_URL + 'cambiar_slug/' + encodeURIComponent(id), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) {
            set_loading(btnGuardarSlug, false);

            if (res.status === 401) {
                window.location.href = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                return;
            }

            if (res.data.ok) {
                modalSlug.hide();
                mostrar_alerta('success', res.data.msg || 'Subdominio actualizado correctamente.');
                dt.ajax.reload(null, false);
                return;
            }

            if (res.data.errores) {
                mostrar_errores(formSlug, alertErroresSlug, listaErroresSlug, res.data.errores);
                return;
            }

            mostrar_alerta(res.status === 409 ? 'warning' : 'error', res.data.msg || 'No se pudo cambiar el subdominio.');
        })
        .catch(function () {
            set_loading(btnGuardarSlug, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    });

    // Delegación de eventos de la tabla
    document.getElementById('tabla_organizadores').addEventListener('click', function (event) {
        // Editar
        const btnEditar = event.target.closest('.btn-editar');
        if (btnEditar) {
            const ctx = obtener_contexto(btnEditar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar el organizador seleccionado.');
                return;
            }

            limpiar_modal_organizador();

            cargar_datos(ctx.id).then(function (d) {
                if (!d) {
                    return;
                }

                document.getElementById('org_id').value = d.org_id || ctx.id;
                document.getElementById('org_nombre_publico').value = d.org_nombre_publico || '';
                document.getElementById('org_email').value = d.org_email || '';
                document.getElementById('org_telefono').value = d.org_telefono || '';
                document.getElementById('org_verificado').checked = parseInt(d.org_verificado, 10) === 1;
                document.getElementById('org_status').checked = parseInt(d.org_status, 10) === 1;
                status_original_organizador = (parseInt(d.org_status, 10) === 1) ? '1' : '0';
                document.getElementById('modal_organizador_titulo').textContent = 'Editar: ' + (d.org_nombre_publico || 'organizador');

                modalOrganizador.show();
            });
            return;
        }

        // Cambiar subdominio
        const btnSlug = event.target.closest('.btn-slug');
        if (btnSlug) {
            const ctx = obtener_contexto(btnSlug);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar el organizador seleccionado.');
                return;
            }

            limpiar_modal_slug();
            document.getElementById('slug_org_id').value = ctx.id;
            slugInput.value = normalizar_slug(ctx.slug);
            actualizar_preview_slug();

            cargar_datos(ctx.id).then(function (d) {
                if (!d) {
                    return;
                }

                document.getElementById('slug_org_id').value = d.org_id || ctx.id;
                slugInput.value = normalizar_slug(d.org_slug || ctx.slug);
                actualizar_preview_slug();
                modalSlug.show();
                slugInput.focus();
            });
            return;
        }

        // Cambiar estado
        const btnStatus = event.target.closest('.btn-status');
        if (btnStatus) {
            const ctx = obtener_contexto(btnStatus);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar el organizador seleccionado.');
                return;
            }

            const statusActual = String(ctx.status) === '1';
            const accionTexto = statusActual ? 'desactivar' : 'activar';
            const nombre = ctx.nombre || 'este organizador';

            Swal.fire({
                icon: 'question',
                title: '¿Deseas ' + accionTexto + ' la tienda?',
                text: 'Se cambiará el estado de "' + nombre + '".',
                showCancelButton: true,
                confirmButtonText: 'Sí, ' + accionTexto,
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-primary',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                request_json(BASE_URL + 'cambiar_status/' + encodeURIComponent(ctx.id), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) {
                    if (res.status === 401) {
                        window.location.href = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                        return;
                    }

                    if (res.data.ok) {
                        mostrar_alerta('success', res.data.msg || 'Estado actualizado correctamente.');
                        dt.ajax.reload(null, false);
                    } else {
                        mostrar_alerta('error', res.data.msg || 'No se pudo cambiar el estado.');
                    }
                })
                .catch(function () {
                    mostrar_alerta('error', 'Error de conexión con el servidor.');
                });
            });
            return;
        }

        // Eliminar (borrado lógico)
        const btnEliminar = event.target.closest('.btn-eliminar');
        if (btnEliminar) {
            const ctx = obtener_contexto(btnEliminar);
            if (!ctx.id) {
                mostrar_alerta('error', 'No se pudo identificar el organizador seleccionado.');
                return;
            }

            const nombre = ctx.nombre || 'este organizador';

            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar organizador?',
                text: '¿Estás seguro de que deseas eliminar "' + nombre + '"? Su sitio público dejará de estar disponible.',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-sm btn-danger',
                    cancelButton: 'btn btn-sm btn-light ms-2'
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                request_json(BASE_URL + 'eliminar/' + encodeURIComponent(ctx.id), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) {
                    if (res.status === 401) {
                        window.location.href = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                        return;
                    }

                    if (res.data.ok) {
                        mostrar_alerta('success', res.data.msg || 'Organizador eliminado correctamente.');
                        dt.ajax.reload(null, false);
                    } else {
                        mostrar_alerta('error', res.data.msg || 'No se pudo eliminar el organizador.');
                    }
                })
                .catch(function () {
                    mostrar_alerta('error', 'Error de conexión con el servidor.');
                });
            });
        }
    });
});
</script>
<!--end::JavaScript-->
