<?php if (empty($tabla_disponible)): ?>
<div class="alert alert-dismissible bg-light-warning d-flex flex-column flex-sm-row p-5 mb-7 border border-warning border-dashed">
    <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
    <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
        <h5 class="mb-1 text-warning fw-bold">Faltan crear las tablas de notificaciones</h5>
        <span class="fs-7 text-gray-700">Ejecuta el script SQL <code>database/2026-05-02_create_notifications_tables.sql</code> en tu base de datos para habilitar este módulo.</span>
    </div>
</div>
<?php endif; ?>

<!--begin::Card notificaciones-->
<div class="card card-flush shadow-sm">

    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text"
                    id="filtro_notificaciones"
                    class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar notificaciones..." />
            </div>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nueva_notificacion" <?php echo empty($tabla_disponible) ? 'disabled' : ''; ?>>
                <i class="ki-duotone ki-plus fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Nueva Notificación
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_notificaciones">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-250px">Notificación</th>
                        <th class="min-w-120px text-center">Alcance</th>
                        <th class="min-w-120px text-center">Tipo</th>
                        <th class="min-w-120px text-center">Usuarios</th>
                        <th class="min-w-180px">Creada por</th>
                        <th class="min-w-140px">Fecha</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold"></tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card notificaciones-->

<!--begin::Modal notificacion-->
<div class="modal fade" id="modal_notificacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h2 class="fw-bold" id="modal_notificacion_titulo">Nueva Notificación</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-10 py-8">
                <div class="alert alert-danger d-none mb-6" id="alert_errores_notificacion">
                    <ul class="mb-0" id="lista_errores_notificacion"></ul>
                </div>

                <form id="form_notificacion" novalidate>
                    <div class="row g-5">
                        <div class="col-md-8">
                            <label class="form-label required fw-semibold">Título</label>
                            <input type="text" id="noti_titulo" name="noti_titulo" class="form-control form-control-solid" maxlength="150" placeholder="Ej: Mantenimiento programado" />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required fw-semibold">Tipo de Alerta</label>
                            <select id="noti_tipo" name="noti_tipo" class="form-select form-select-solid">
                                <option value="info">Información</option>
                                <option value="success">Éxito</option>
                                <option value="warning">Advertencia</option>
                                <option value="danger">Crítica</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label required fw-semibold">Mensaje</label>
                            <textarea id="noti_mensaje" name="noti_mensaje" rows="4" class="form-control form-control-solid" maxlength="1000" placeholder="Escribe aquí el contenido que verá el usuario"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Enlace Opcional</label>
                            <input type="text" id="noti_enlace" name="noti_enlace" class="form-control form-control-solid" maxlength="255" placeholder="https://... o /ruta/interna" />
                            <div class="text-muted fs-7 mt-1">Si lo configuras, el usuario podrá saltar directamente a la pantalla asociada al hacer clic en el aviso.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" value="1" id="noti_es_global" name="noti_es_global" checked />
                                <label class="form-check-label fw-semibold text-gray-800" for="noti_es_global">
                                    Enviar a todos los usuarios activos del sistema
                                </label>
                            </div>
                        </div>

                        <div class="col-12" id="contenedor_destinatarios" style="display:none;">
                            <label class="form-label required fw-semibold">Usuarios Destinatarios</label>
                            <select id="destinatarios" name="destinatarios[]" class="form-select form-select-solid" multiple size="7">
                                <?php if (!empty($usuarios)): ?>
                                    <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo htmlspecialchars($usuario['usu_id']); ?>">
                                        <?php echo htmlspecialchars($usuario['usu_nombre'] . ' (@' . $usuario['usu_usuario'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="text-muted fs-7 mt-1">Mantén presionada la tecla Ctrl (o Cmd en Mac) para seleccionar varios usuarios específicos.</div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="btn_guardar_notificacion">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-paper-plane fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Enviar Notificación
                    </span>
                    <span class="indicator-progress">
                        Enviando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal notificacion-->

<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/')); ?>;
    const modalEl = document.getElementById('modal_notificacion');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('form_notificacion');
    const globalSwitch = document.getElementById('noti_es_global');
    const recipientsContainer = document.getElementById('contenedor_destinatarios');
    const saveButton = document.getElementById('btn_guardar_notificacion');
    const filtroInput = document.getElementById('filtro_notificaciones');
    let filtroTimer = null;
    let tablaNotificaciones = null;

    function mostrarAlerta(tipo, msg) {
        const titulos = { success: 'Listo', error: 'Error', warning: 'Atención', info: 'Información' };
        Swal.fire({
            icon: tipo,
            title: titulos[tipo] || 'Mensaje',
            text: msg,
            confirmButtonText: 'Aceptar',
            customClass: { confirmButton: 'btn btn-primary' },
            buttonsStyling: false,
        });
    }

    function toggleRecipients() {
        recipientsContainer.style.display = globalSwitch.checked ? 'none' : '';
    }

    function limpiarErrores() {
        document.getElementById('alert_errores_notificacion').classList.add('d-none');
        document.getElementById('lista_errores_notificacion').innerHTML = '';
    }

    function mostrarErrores(errores) {
        const lista = document.getElementById('lista_errores_notificacion');
        lista.innerHTML = '';
        Object.keys(errores || {}).forEach(function(key) {
            const li = document.createElement('li');
            li.textContent = errores[key];
            lista.appendChild(li);
        });
        document.getElementById('alert_errores_notificacion').classList.remove('d-none');
    }

    function resetForm() {
        form.reset();
        globalSwitch.checked = true;
        toggleRecipients();
        limpiarErrores();
    }

    document.getElementById('btn_nueva_notificacion')?.addEventListener('click', function() {
        resetForm();
        modal.show();
    });

    globalSwitch.addEventListener('change', toggleRecipients);

    saveButton.addEventListener('click', function() {
        limpiarErrores();
        saveButton.setAttribute('data-kt-indicator', 'on');
        saveButton.disabled = true;

        const formData = new FormData(form);
        formData.set('noti_es_global', globalSwitch.checked ? '1' : '0');

        fetch(BASE_URL + 'crear', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, data: data };
            });
        })
        .then(function(result) {
            if (result.status === 422) {
                mostrarErrores(result.data.errores || { general: result.data.msg || 'Valida la información ingresada.' });
                return;
            }

            if (!result.data || result.data.ok !== true) {
                throw new Error(result.data && result.data.msg ? result.data.msg : 'No se pudo enviar la notificación.');
            }

            modal.hide();
            mostrarAlerta('success', result.data.msg || 'Notificación enviada correctamente.');
            if (tablaNotificaciones) {
                tablaNotificaciones.ajax.reload(null, false);
            }
        })
        .catch(function(error) {
            mostrarAlerta('error', error.message || 'No se pudo enviar la notificación.');
        })
        .finally(function() {
            saveButton.removeAttribute('data-kt-indicator');
            saveButton.disabled = false;
        });
    });

    // Inicializar DataTables server-side
    tablaNotificaciones = $('#tabla_notificaciones').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        responsive: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[5, 'desc']],
        dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
        ajax: {
            url: BASE_URL + 'tabla',
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        },
        columns: [
            { data: 'notificacion', orderable: true },
            { data: 'alcance', orderable: true, className: 'text-center' },
            { data: 'tipo', orderable: true, className: 'text-center' },
            { data: 'destinatarios', orderable: false, className: 'text-center' },
            { data: 'creador', orderable: true },
            { data: 'fecha', orderable: true },
            { data: 'acciones', orderable: false, className: 'text-end pe-2' }
        ],
        language: {
            processing: 'Cargando notificaciones...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ notificaciones',
            infoEmpty: 'No hay notificaciones para mostrar',
            infoFiltered: '(filtradas de _MAX_ notificaciones)',
            zeroRecords: 'No se encontraron notificaciones',
            emptyTable: 'No hay notificaciones enviadas todavía',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        },
        drawCallback: function() {
            // Inicializar tooltips nativos de Bootstrap en los botones
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });

    // Búsqueda en vivo con debounce
    filtroInput?.addEventListener('input', function() {
        const valor = this.value;
        clearTimeout(filtroTimer);
        filtroTimer = setTimeout(function() {
            tablaNotificaciones.search(valor).draw();
        }, 250);
    });

    // Delegación de eventos para eliminar
    document.getElementById('tabla_notificaciones').addEventListener('click', function(event) {
        const btnEliminar = event.target.closest('.btn-eliminar');
        if (!btnEliminar) return;

        const id = btnEliminar.dataset.id;
        const titulo = btnEliminar.dataset.titulo || 'esta notificación';

        Swal.fire({
            title: '¿Eliminar notificación?',
            text: 'Esta acción quitará "' + titulo + '" para todos sus destinatarios.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light'
            },
            buttonsStyling: false,
        }).then(function(result) {
            if (!result.isConfirmed) return;

            fetch(BASE_URL + 'eliminar/' + id, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                return response.json().then(function(data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function(resultDelete) {
                if (!resultDelete.data || resultDelete.data.ok !== true) {
                    throw new Error(resultDelete.data && resultDelete.data.msg ? resultDelete.data.msg : 'No se pudo eliminar la notificación.');
                }

                mostrarAlerta('success', resultDelete.data.msg || 'Notificación eliminada correctamente.');
                tablaNotificaciones.ajax.reload(null, false);
            })
            .catch(function(error) {
                mostrarAlerta('error', error.message || 'No se pudo eliminar la notificación.');
            });
        });
    });

    toggleRecipients();
});
</script>
<!--end::JavaScript-->
