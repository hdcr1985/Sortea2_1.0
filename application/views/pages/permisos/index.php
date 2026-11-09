<!--begin::Card roles-->
<div class="card card-flush shadow-sm">

    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bold text-gray-900 fs-3">
                Roles del Sistema
                <span class="text-muted fw-semibold fs-7 d-block mt-1">
                    Define los roles y asigna qué módulos puede acceder cada uno
                </span>
            </h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nuevo_rol">
                <i class="ki-duotone ki-plus fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Nuevo Rol
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-200px">Rol</th>
                        <th class="min-w-200px">Descripción</th>
                        <th class="min-w-80px text-center">Tipo</th>
                        <th class="min-w-80px text-center">Usuarios</th>
                        <th class="min-w-80px text-center">Estado</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($roles)): ?>
                <?php foreach ($roles as $rol): ?>
                <tr>
                    <!--begin::Rol-->
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-3">
                                <span class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-shield-tick fs-2x text-primary">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark fs-6">
                                    <?php echo htmlspecialchars($rol['per_nombre']); ?>
                                </span>
                                <span class="text-muted fw-semibold fs-7">
                                    ID: <?php echo $rol['per_id']; ?>
                                </span>
                            </div>
                        </div>
                    </td>
                    <!--end::Rol-->

                    <td class="text-muted fw-semibold">
                        <?php echo htmlspecialchars($rol['per_descripcion'] ?? '—'); ?>
                    </td>

                    <td class="text-center">
                        <?php if ($rol['per_tipo'] == '0'): ?>
                            <span class="badge badge-light-primary fw-bold">Grupal</span>
                        <?php else: ?>
                            <span class="badge badge-light-info fw-bold">Usuario</span>
                        <?php endif; ?>
                    </td>

                    <td class="text-center">
                        <span class="badge badge-light fw-bold fs-7">
                            <?php echo $rol['total_usuarios']; ?>
                        </span>
                    </td>

                    <td class="text-center">
                        <?php if ($rol['per_activo'] == '1'): ?>
                            <span class="badge badge-light-success fw-bold">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-light-danger fw-bold">Inactivo</span>
                        <?php endif; ?>
                    </td>

                    <td class="text-end pe-2">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                        <!--begin::Botón permisos-->
                        <a href="<?php echo base_url($url_base . '/asignar/' . $rol['per_id']); ?>"
                            class="btn btn-sm btn-icon btn-light-success shadow-xs"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Asignar permisos de menú"
                            aria-label="Asignar permisos de menú">
                            <i class="ki-duotone ki-key-square fs-4">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </a>
                        <!--end::Botón permisos-->

                        <!--begin::Botón editar-->
                        <button type="button"
                            class="btn btn-sm btn-icon btn-light-primary shadow-xs btn_editar"
                            data-id="<?php echo $rol['per_id']; ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Editar rol"
                            aria-label="Editar rol">
                            <i class="ki-duotone ki-pencil fs-4">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </button>
                        <!--end::Botón editar-->

                        <!--begin::Botón eliminar-->
                        <button type="button"
                            class="btn btn-sm btn-icon btn-light-danger shadow-xs btn_eliminar"
                            data-id="<?php echo $rol['per_id']; ?>"
                            data-nombre="<?php echo htmlspecialchars($rol['per_nombre']); ?>"
                            data-usuarios="<?php echo $rol['total_usuarios']; ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Eliminar rol"
                            aria-label="Eliminar rol">
                            <i class="ki-duotone ki-trash fs-4">
                                <span class="path1"></span><span class="path2"></span>
                                <span class="path3"></span><span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </button>
                        </div>
                        <!--end::Botón eliminar-->
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-10">
                        No hay roles registrados. Crea el primero.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card roles-->


<!--begin::Modal Crear/Editar Rol-->
<div class="modal fade" id="modal_rol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h2 class="fw-bold" id="modal_rol_titulo">Nuevo Rol</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-10 py-8">

                <div class="alert alert-danger d-none mb-6" id="alert_errores">
                    <ul class="mb-0" id="lista_errores"></ul>
                </div>

                <form id="form_rol" novalidate>
                    <input type="hidden" id="per_id" value="0" />

                    <div class="row g-5">

                        <!--begin::Nombre-->
                        <div class="col-12">
                            <label class="form-label required fw-semibold">Nombre del rol</label>
                            <input type="text" id="per_nombre" name="per_nombre"
                                class="form-control form-control-solid"
                                placeholder="Ej: Administrador, Supervisor..." maxlength="100" />
                        </div>
                        <!--end::Nombre-->

                        <!--begin::Descripción-->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descripción</label>
                            <input type="text" id="per_descripcion" name="per_descripcion"
                                class="form-control form-control-solid"
                                placeholder="Descripción corta del rol" maxlength="200" />
                        </div>
                        <!--end::Descripción-->

                        <!--begin::Tipo y Estado-->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo</label>
                            <select id="per_tipo" name="per_tipo" class="form-select form-select-solid">
                                <option value="0">Grupal</option>
                                <option value="1">Usuario específico</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estado</label>
                            <select id="per_activo" name="per_activo" class="form-select form-select-solid">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <!--end::Tipo y Estado-->

                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="btn_guardar_rol">
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
<!--end::Modal-->


<!--begin::JavaScript-->
<script>
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";

function mostrar_alerta(tipo, msg) {
    Swal.fire({
        icon: tipo,
        title: tipo === 'success' ? '¡Listo!' : 'Error',
        text: msg,
        confirmButtonText: 'Aceptar',
        customClass: { confirmButton: 'btn btn-primary' },
        buttonsStyling: false,
    });
}

function limpiar_modal() {
    document.getElementById('form_rol').reset();
    document.getElementById('per_id').value = '0';
    document.getElementById('modal_rol_titulo').textContent = 'Nuevo Rol';
    document.getElementById('alert_errores').classList.add('d-none');
    document.getElementById('lista_errores').innerHTML = '';
}

// ── Nuevo rol ────────────────────────────────────────────────
document.getElementById('btn_nuevo_rol').addEventListener('click', function() {
    limpiar_modal();
    new bootstrap.Modal(document.getElementById('modal_rol')).show();
});

// ── Editar rol ───────────────────────────────────────────────
document.querySelectorAll('.btn_editar').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        limpiar_modal();

        fetch(BASE_URL + 'datos/' + id, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { mostrar_alerta('error', res.msg); return; }
            const r = res.data;
            document.getElementById('modal_rol_titulo').textContent = 'Editar Rol';
            document.getElementById('per_id').value          = r.per_id;
            document.getElementById('per_nombre').value      = r.per_nombre;
            document.getElementById('per_descripcion').value = r.per_descripcion || '';
            document.getElementById('per_tipo').value        = r.per_tipo;
            document.getElementById('per_activo').value      = r.per_activo;
            new bootstrap.Modal(document.getElementById('modal_rol')).show();
        })
        .catch(() => mostrar_alerta('error', 'Error al cargar los datos.'));
    });
});

// ── Guardar (crear / actualizar) ─────────────────────────────
document.getElementById('btn_guardar_rol').addEventListener('click', function() {
    const btn   = this;
    const id    = document.getElementById('per_id').value;
    const nuevo = (id === '0' || id === '');
    const url   = nuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + id;
    const fd    = new FormData(document.getElementById('form_rol'));

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
            bootstrap.Modal.getInstance(document.getElementById('modal_rol')).hide();
            mostrar_alerta('success', res.msg);
            setTimeout(() => location.reload(), 1200);
        } else if (res.errores) {
            const lista = document.getElementById('lista_errores');
            lista.innerHTML = '';
            Object.values(res.errores).forEach(e => {
                const li = document.createElement('li');
                li.textContent = e;
                lista.appendChild(li);
            });
            document.getElementById('alert_errores').classList.remove('d-none');
        } else {
            mostrar_alerta('error', res.msg);
        }
    })
    .catch(() => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        mostrar_alerta('error', 'Error de conexión.');
    });
});

// ── Eliminar rol ─────────────────────────────────────────────
document.querySelectorAll('.btn_eliminar').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id       = this.dataset.id;
        const nombre   = this.dataset.nombre;
        const usuarios = parseInt(this.dataset.usuarios);

        if (usuarios > 0) {
            mostrar_alerta('warning',
                `No puedes eliminar "${nombre}" porque tiene ${usuarios} usuario(s) asignado(s).`
            );
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar rol?',
            html: `¿Confirmas eliminar el rol <b>${nombre}</b>?<br>
                   <span class="text-muted fs-7">También se eliminarán todos sus permisos asignados.</span>`,
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
                    setTimeout(() => location.reload(), 1200);
                } else {
                    mostrar_alerta('error', res.msg);
                }
            })
            .catch(() => mostrar_alerta('error', 'Error de conexión.'));
        });
    });
});
</script>
<!--end::JavaScript-->
