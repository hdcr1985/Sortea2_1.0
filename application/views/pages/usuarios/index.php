<!--begin::Card usuarios-->
<div class="card card-flush shadow-sm">

    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text"
                    id="filtro_usuarios"
                    class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar usuarios..." />
            </div>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nuevo_usuario">
                <i class="ki-duotone ki-plus fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Nuevo Usuario
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_usuarios">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-250px">Usuario</th>
                        <th class="min-w-200px">Correo Electrónico</th>
                        <th class="min-w-150px text-center">Rol Asignado</th>
                        <th class="min-w-120px text-center">Estado</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold"></tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card usuarios-->


<!--begin::Modal usuario-->
<div class="modal fade" id="modal_usuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h2 class="fw-bold" id="modal_usuario_titulo">Nuevo Usuario</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-10 py-8">
                <div class="alert alert-danger d-none mb-6" id="alert_errores">
                    <ul class="mb-0" id="lista_errores"></ul>
                </div>

                <form id="form_usuario" novalidate>
                    <input type="hidden" id="usu_id" value="0" />

                    <div class="row g-5">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Foto del usuario</label>
                            <div class="text-muted fs-7 mt-1">Opcional. Solo JPG, PNG o WEBP. Se almacena en la carpeta interna upload y se sirve por URL virtual.</div>
                            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-4 mt-4">
                                <div class="border rounded overflow-hidden bg-light" style="width: 200px; min-width: 200px; height: 200px;">
                                    <img id="preview_foto_img" src="" alt="Preview" class="d-none w-100 h-100" style="display: block; object-fit: cover; object-position: center;" />
                                    <div id="preview_foto_placeholder" class="d-flex align-items-center justify-content-center bg-light-primary text-primary fw-bold fs-2 w-100 h-100">?</div>
                                </div>
                                <div class="flex-grow-1" style="max-width: 360px; width: 100%;">
                                    <input type="file" id="usu_foto_archivo" name="usu_foto_archivo"
                                        class="form-control form-control-solid"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">Nombre</label>
                            <input type="text" id="usu_nombre" name="usu_nombre"
                                class="form-control form-control-solid"
                                placeholder="Ej: Juan" maxlength="100" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">Apellido</label>
                            <input type="text" id="usu_apellido" name="usu_apellido"
                                class="form-control form-control-solid"
                                placeholder="Ej: Pérez" maxlength="100" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" id="usu_usuario" name="usu_usuario"
                                    class="form-control form-control-solid"
                                    placeholder="Ej: jperez" maxlength="50" />
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">Correo</label>
                            <input type="email" id="usu_correo" name="usu_correo"
                                class="form-control form-control-solid"
                                placeholder="Ej: usuario@empresa.com" maxlength="150" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">Rol</label>
                            <select id="usu_tipoper" name="usu_tipoper" class="form-select form-select-solid">
                                <option value="">— Selecciona un rol —</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['per_id']; ?>">
                                    <?php echo htmlspecialchars($rol['per_nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        

                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">Celular</label>
                            <input type="text" id="usu_celular" name="usu_celular"
                                class="form-control form-control-solid"
                                placeholder="Ej: 04141234567" maxlength="11" inputmode="numeric" pattern="[0-9]*" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Género</label>
                            <select id="usu_genero" name="usu_genero" class="form-select form-select-solid">
                                <option value="">— Selecciona —</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                                <option value="Otro">Otro</option>
                                <option value="Prefiero no indicar">Prefiero no indicar</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="text" id="usu_fechan" name="usu_fechan"
                                class="form-control form-control-solid"
                                placeholder="dd-mm-aaaa" autocomplete="off" />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Estado</label>
                            <select id="usu_status" name="usu_status" class="form-select form-select-solid">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" id="label_clave">Contraseña</label>
                            <input type="password" id="usu_clave" name="usu_clave"
                                class="form-control form-control-solid"
                                placeholder="Mínimo 8 caracteres" />
                            <div class="text-muted fs-7 mt-1" id="hint_clave">
                                Obligatoria al crear. En edición, déjala vacía para mantener la actual.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirmar contraseña</label>
                            <input type="password" id="clave_confirm" name="clave_confirm"
                                class="form-control form-control-solid"
                                placeholder="Repite la contraseña" />
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="btn_guardar_usuario">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar
                    </span>
                    <span class="indicator-progress">Guardando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>

        </div>
    </div>
</div>
<!--end::Modal usuario-->


<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function() {
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
const CURRENT_USER_ID = <?php echo json_encode((string)$usuario_sesion_id); ?>;
const modalUsuario = new bootstrap.Modal(document.getElementById('modal_usuario'));
const filtroUsuarios = document.getElementById('filtro_usuarios');
const inputFoto = document.getElementById('usu_foto_archivo');
const previewFotoImg = document.getElementById('preview_foto_img');
const previewFotoPlaceholder = document.getElementById('preview_foto_placeholder');
let tablaUsuarios = null;
let filtroTimer = null;
let fechaNacimientoPicker = null;
let fotoActualUrl = '';

function mostrar_alerta(tipo, msg) {
    const titulos = {
        success: '¡Listo!',
        warning: 'Atención',
        info: 'Información',
        error: 'Error'
    };

    Swal.fire({
        icon: tipo,
        title: titulos[tipo] || 'Mensaje',
        text: msg,
        confirmButtonText: 'Aceptar',
        customClass: { confirmButton: 'btn btn-primary' },
        buttonsStyling: false,
    });
}

function limpiar_modal() {
    document.getElementById('form_usuario').reset();
    document.getElementById('usu_id').value = '0';
    document.getElementById('modal_usuario_titulo').textContent = 'Nuevo Usuario';
    document.getElementById('alert_errores').classList.add('d-none');
    document.getElementById('lista_errores').innerHTML = '';
    document.getElementById('usu_status').value = '1';
        document.getElementById('usu_genero').value = '';
    document.getElementById('label_clave').innerHTML = 'Contraseña <span class="text-danger">*</span>';
    document.getElementById('hint_clave').textContent = 'Obligatoria al crear. En edición, déjala vacía para mantener la actual.';
    fotoActualUrl = '';
    preview_foto('');

    if (fechaNacimientoPicker) fechaNacimientoPicker.clear();
}

function preview_foto(src) {
    if (src) {
        previewFotoImg.src = src;
        previewFotoImg.classList.remove('d-none');
        previewFotoPlaceholder.classList.add('d-none');
        return;
    }

    previewFotoImg.src = '';
    previewFotoImg.classList.add('d-none');
    previewFotoPlaceholder.classList.remove('d-none');
}

function inicializar_datepickers() {
    fechaNacimientoPicker = flatpickr('#usu_fechan', {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd-m-Y',
        allowInput: false,
        disableMobile: true,
        maxDate: 'today'
    });
}

function mostrar_errores(errores) {
    const lista = document.getElementById('lista_errores');
    lista.innerHTML = '';

    Object.values(errores).forEach(error => {
        const li = document.createElement('li');
        li.textContent = error;
        lista.appendChild(li);
    });

    document.getElementById('alert_errores').classList.remove('d-none');
}

document.getElementById('btn_nuevo_usuario').addEventListener('click', function() {
    limpiar_modal();
    modalUsuario.show();
});

function recargar_tabla(mantenerPagina = true) {
    if (tablaUsuarios) {
        tablaUsuarios.ajax.reload(null, !mantenerPagina);
    }
}

function abrir_edicion_usuario(id) {
    limpiar_modal();

    fetch(BASE_URL + 'datos/' + id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) {
            mostrar_alerta('error', res.msg || 'No se pudo cargar el usuario.');
            return;
        }

        const u = res.data;
        document.getElementById('modal_usuario_titulo').textContent = 'Editar Usuario';
        document.getElementById('usu_id').value = u.usu_id;
        document.getElementById('usu_nombre').value = u.usu_nombre || '';
        document.getElementById('usu_apellido').value = u.usu_apellido || '';
        document.getElementById('usu_usuario').value = u.usu_usuario || '';
        document.getElementById('usu_correo').value = u.usu_correo || '';
        document.getElementById('usu_tipoper').value = u.usu_tipoper || '';
                document.getElementById('usu_celular').value = u.usu_celular || '';
        document.getElementById('usu_genero').value = u.usu_genero || '';
        document.getElementById('usu_status').value = u.usu_status || '1';
        document.getElementById('label_clave').textContent = 'Nueva contraseña';
        document.getElementById('hint_clave').textContent = 'Déjala vacía si no deseas cambiar la contraseña actual.';
        fotoActualUrl = u.usu_foto_url || '';
        preview_foto(fotoActualUrl);

        if (fechaNacimientoPicker) {
            if (u.usu_fechan) {
                fechaNacimientoPicker.setDate(u.usu_fechan, true, 'Y-m-d');
            } else {
                fechaNacimientoPicker.clear();
            }
        }

        modalUsuario.show();
    })
    .catch(() => mostrar_alerta('error', 'Error al cargar los datos del usuario.'));
}

function confirmar_eliminacion_usuario(id, nombre, esPropio) {
    if (esPropio) {
        mostrar_alerta('warning', 'No puedes eliminar tu propio usuario.');
        return;
    }

    Swal.fire({
        icon: 'warning',
        title: '¿Eliminar usuario?',
        text: `¿Confirmas eliminar al usuario ${nombre}?`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light ms-2',
        },
        buttonsStyling: false,
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(BASE_URL + 'eliminar/' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                mostrar_alerta('success', res.msg);
                recargar_tabla();
            } else {
                mostrar_alerta('error', res.msg || 'No se pudo eliminar el usuario.');
            }
        })
        .catch(() => mostrar_alerta('error', 'Error de conexión.'));
    });
}

tablaUsuarios = $('#tabla_usuarios').DataTable({
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
        { data: 'usuario', orderable: true, searchable: false },
        { data: 'correo', orderable: true },
        { data: 'rol', orderable: true, searchable: false, className: 'text-center' },
        { data: 'estado', orderable: true, searchable: false, className: 'text-center' },
        { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2' }
    ],
    language: {
        processing: 'Cargando usuarios...',
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
        infoEmpty: 'No hay usuarios para mostrar',
        infoFiltered: '(filtrados de _MAX_ usuarios)',
        zeroRecords: 'No se encontraron usuarios',
        emptyTable: 'No hay usuarios registrados',
        paginate: {
            first: 'Primero',
            last: 'Último',
            next: 'Siguiente',
            previous: 'Anterior'
        }
    }
});

inicializar_datepickers();

inputFoto.addEventListener('change', function() {
    const archivo = this.files && this.files[0] ? this.files[0] : null;
    if (!archivo) {
        preview_foto(fotoActualUrl);
        return;
    }

    preview_foto(URL.createObjectURL(archivo));
});

const inputCelularUsuario = document.getElementById('usu_celular');
if (inputCelularUsuario) inputCelularUsuario.addEventListener('input', function() { this.value = this.value.replace(/\D+/g, '').slice(0, 11); });

filtroUsuarios.addEventListener('input', function() {
    const valor = this.value;

    clearTimeout(filtroTimer);
    filtroTimer = setTimeout(function() {
        tablaUsuarios.search(valor).draw();
    }, 250);
});

document.getElementById('tabla_usuarios').addEventListener('click', function(event) {
    const btnEditar = event.target.closest('.btn-editar');
    if (btnEditar) {
        abrir_edicion_usuario(btnEditar.dataset.id);
        return;
    }

    const btnEliminar = event.target.closest('.btn-eliminar');
    if (btnEliminar) {
        confirmar_eliminacion_usuario(
            btnEliminar.dataset.id,
            btnEliminar.dataset.nombre,
            btnEliminar.dataset.self === '1' || btnEliminar.dataset.id === CURRENT_USER_ID
        );
    }
});

document.getElementById('btn_guardar_usuario').addEventListener('click', function() {
    const btn = this;
    const id = document.getElementById('usu_id').value;
    const esNuevo = (id === '0' || id === '');
    const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + id;
    const fd = new FormData(document.getElementById('form_usuario'));

    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    document.getElementById('alert_errores').classList.add('d-none');

    fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    })
    .then(r => r.json())
    .then(res => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;

        if (res.ok) {
            modalUsuario.hide();
            mostrar_alerta('success', res.msg);
            recargar_tabla();
            return;
        }

        if (res.errores) {
            mostrar_errores(res.errores);
            return;
        }

        mostrar_alerta('error', res.msg || 'No se pudo guardar el usuario.');
    })
    .catch(() => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        mostrar_alerta('error', 'Error de conexión.');
    });
});
});
</script>
<!--end::JavaScript-->