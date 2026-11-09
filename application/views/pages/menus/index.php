<?php $this->load->view('partials/_icon_picker'); ?>

<!--begin::Card principal-->
<div class="card card-flush shadow-sm">

    <!--begin::Card header-->
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bold text-gray-900 fs-3">
                Árbol de Menús
                <span class="text-muted fw-semibold fs-7 d-block mt-1">
                    Los submenús se muestran anidados bajo su menú padre
                </span>
            </h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nuevo_menu">
                <i class="ki-duotone ki-plus fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Nuevo Menú
            </button>
        </div>
    </div>
    <!--end::Card header-->

    <!--begin::Card body-->
    <div class="card-body pt-0">

        <!--begin::Tabla de menús-->
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_menus">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-300px">Nombre</th>
                        <th class="min-w-200px">URL / Amigable</th>
                        <th class="min-w-120px">Ícono</th>
                        <th class="min-w-80px text-center">Orden</th>
                        <th class="min-w-80px text-center">Tipo</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody_menus">
                    <?php
                    /**
                     * Renderiza el árbol de menús en la tabla con indentación visual.
                     */
                    function render_tabla_menus($items, $nivel = 0) {
                        foreach ($items as $item):
                            $tiene_hijos = !empty($item['hijos']);
                            $padding     = $nivel * 25;
                            $es_raiz     = ((int)$item['men_tipo'] === 0);
                    ?>
                    <tr data-id="<?php echo $item['men_id']; ?>"
                        data-padre="<?php echo $item['sub_men_id']; ?>">

                        <!--begin::Nombre con indentación-->
                        <td>
                            <div class="d-flex align-items-center" style="padding-left: <?php echo $padding; ?>px">
                                <?php if ($nivel > 0): ?>
                                <span class="text-muted me-2">└</span>
                                <?php endif; ?>

                                <?php if (!empty($item['men_imgmenu'])): ?>
                                <span class="symbol symbol-35px me-3">
                                    <span class="symbol-label bg-light-primary">
                                        <i class="<?php echo htmlspecialchars($item['men_imgmenu']); ?> text-primary fs-4"></i>
                                    </span>
                                </span>
                                <?php endif; ?>

                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark fs-6">
                                        <?php echo htmlspecialchars($item['men_nombre']); ?>
                                    </span>
                                    <?php if (!empty($item['men_descripcion'])): ?>
                                    <span class="text-muted fw-semibold fs-7">
                                        <?php echo htmlspecialchars($item['men_descripcion']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <!--end::Nombre-->

                        <!--begin::URLs-->
                        <td>
                            <div class="d-flex flex-column">
                                <span class="text-dark fw-semibold fs-7">
                                    <code><?php echo htmlspecialchars($item['men_url'] ?: '—'); ?></code>
                                </span>
                                <?php if (!empty($item['men_urlamigable'])): ?>
                                <span class="text-muted fs-7">
                                    <i class="ki-duotone ki-arrow-right fs-8 me-1">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    <?php echo htmlspecialchars($item['men_urlamigable']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <!--end::URLs-->

                        <!--begin::Ícono-->
                        <td>
                            <code class="fs-7 text-muted">
                                <?php echo htmlspecialchars($item['men_imgmenu'] ?: '—'); ?>
                            </code>
                        </td>
                        <!--end::Ícono-->

                        <!--begin::Orden-->
                        <td class="text-center">
                            <span class="badge badge-light fw-bold">
                                <?php echo $item['men_orden']; ?>
                            </span>
                        </td>
                        <!--end::Orden-->

                        <!--begin::Tipo-->
                        <td class="text-center">
                            <?php if ($es_raiz): ?>
                            <span class="badge badge-light-primary fw-bold">Raíz</span>
                            <?php else: ?>
                            <span class="badge badge-light-info fw-bold">Submenú</span>
                            <?php endif; ?>
                            <?php if ($item['men_externo'] == '1'): ?>
                            <span class="badge badge-light-warning fw-bold ms-1">Externo</span>
                            <?php endif; ?>
                            <?php if (($item['men_inicio'] ?? '0') == '1'): ?>
                            <span class="badge badge-light-success fw-bold ms-1">Inicio</span>
                            <?php endif; ?>
                        </td>
                        <!--end::Tipo-->

                        <!--begin::Acciones-->
                        <td class="text-end pe-2">
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                <button type="button"
                                    class="btn btn-sm btn-icon btn-light-primary shadow-xs btn_editar"
                                    data-id="<?php echo $item['men_id']; ?>"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Editar"
                                    aria-label="Editar">
                                    <i class="ki-duotone ki-pencil fs-4">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                </button>
                                <button type="button"
                                    class="btn btn-sm btn-icon btn-light-danger shadow-xs btn_eliminar"
                                    data-id="<?php echo $item['men_id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($item['men_nombre']); ?>"
                                    data-hijos="<?php echo $tiene_hijos ? '1' : '0'; ?>"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Eliminar"
                                    aria-label="Eliminar">
                                    <i class="ki-duotone ki-trash fs-4">
                                        <span class="path1"></span><span class="path2"></span>
                                        <span class="path3"></span><span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                            </div>
                        </td>
                        <!--end::Acciones-->
                    </tr>

                    <?php
                    // Renderizar submenús recursivamente
                    if ($tiene_hijos) {
                        render_tabla_menus($item['hijos'], $nivel + 1);
                    }
                    endforeach;
                    }

                    if (!empty($arbol)) {
                        render_tabla_menus($arbol);
                    } else {
                        echo '<tr><td colspan="6" class="text-center text-muted py-10">
                                No hay menús registrados. Crea el primero.
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <!--end::Tabla-->

    </div>
    <!--end::Card body-->
</div>
<!--end::Card principal-->


<!--begin::Modal Crear / Editar Menú-->
<div class="modal fade" id="modal_menu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <!--begin::Modal header-->
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_menu_titulo">Nuevo Menú</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <!--end::Modal header-->

            <!--begin::Modal body-->
            <div class="modal-body px-10 py-8">

                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6" id="alert_errores">
                    <ul class="mb-0" id="lista_errores"></ul>
                </div>
                <!--end::Alerta-->

                <form id="form_menu" novalidate>
                    <input type="hidden" id="men_id" name="men_id" value="0" />

                    <div class="row g-5">

                        <!--begin::Tipo de menú (selector principal)-->
                        <div class="col-12">
                            <label class="form-label required fw-semibold">Tipo de menú</label>
                            <div class="d-flex gap-4">
                                <label class="d-flex align-items-center gap-2 cursor-pointer">
                                    <input type="radio" name="tipo_menu" value="raiz" class="form-check-input" checked />
                                    <span class="fw-semibold">Menú raíz</span>
                                    <span class="text-muted fs-7">(aparece en el sidebar principal)</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 cursor-pointer">
                                    <input type="radio" name="tipo_menu" value="sub" class="form-check-input" />
                                    <span class="fw-semibold">Submenú</span>
                                    <span class="text-muted fs-7">(hijo de un menú raíz)</span>
                                </label>
                            </div>
                        </div>
                        <!--end::Tipo-->

                        <!--begin::Selector de menú padre (solo visible si es submenú)-->
                        <div class="col-12 d-none" id="row_padre">
                            <label class="form-label required fw-semibold">Menú padre</label>
                            <select name="sub_men_id" id="sub_men_id" class="form-select form-select-solid">
                                <option value="0">— Selecciona el menú padre —</option>
                                <?php foreach ($raices as $raiz): ?>
                                <option value="<?php echo $raiz['men_id']; ?>">
                                    <?php echo htmlspecialchars($raiz['men_nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!--end::Selector padre-->

                        <!--begin::Nombre-->
                        <div class="col-md-8">
                            <label class="form-label required fw-semibold">Nombre del menú</label>
                            <input type="text" name="men_nombre" id="men_nombre"
                                class="form-control form-control-solid"
                                placeholder="Ej: Gestión de Usuarios" maxlength="100" />
                        </div>
                        <!--end::Nombre-->

                        <!--begin::Orden-->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Orden</label>
                            <input type="number" name="men_orden" id="men_orden"
                                class="form-control form-control-solid"
                                placeholder="1" min="1" max="999" />
                            <div class="text-muted fs-7 mt-1">Dejar vacío para automático</div>
                        </div>
                        <!--end::Orden-->

                        <!--begin::URL del controlador-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold">URL (controlador/método)</label>
                            <input type="text" name="men_url" id="men_url"
                                class="form-control form-control-solid"
                                placeholder="Ej: Usuarios/index" />
                            <div class="text-muted fs-7 mt-1">
                                Ruta al controlador de CI3
                            </div>
                        </div>
                        <!--end::URL-->

                        <!--begin::URL amigable-->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">URL amigable</label>
                            <div class="input-group">
                                <span class="input-group-text text-muted">/</span>
                                <input type="text" name="men_urlamigable" id="men_urlamigable"
                                    class="form-control form-control-solid"
                                    placeholder="Ej: gestion-de-usuarios" />
                            </div>
                            <div class="text-muted fs-7 mt-1">
                                Sin espacios ni caracteres especiales
                            </div>
                        </div>
                        <!--end::URL amigable-->

                        <!--begin::Ícono — reemplazar el col-md-8 existente-->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Clase del ícono</label>

                            <div class="input-group">
                                <!--begin::Preview-->
                                <span class="input-group-text bg-light px-3" id="preview_icono"
                                    style="min-width:44px; cursor:pointer"
                                    onclick="IconPicker.abrir('men_imgmenu', 'preview_icono')"
                                    title="Clic para elegir ícono">
                                    <i class="fa fa-bars text-muted"></i>
                                </span>
                                <!--end::Preview-->

                                <!--begin::Input-->
                                <input type="text" name="men_imgmenu" id="men_imgmenu"
                                    class="form-control form-control-solid"
                                    placeholder="Ej: fa fa-users  o  ki-duotone ki-people" />
                                <!--end::Input-->

                                <!--begin::Botón abrir picker-->
                                <button type="button"
                                    class="btn btn-light-primary"
                                    onclick="IconPicker.abrir('men_imgmenu', 'preview_icono')"
                                    title="Seleccionar ícono">
                                    <i class="ki-duotone ki-element-11 fs-4">
                                        <span class="path1"></span><span class="path2"></span>
                                        <span class="path3"></span><span class="path4"></span>
                                    </i>
                                    Elegir
                                </button>
                                <!--end::Botón abrir picker-->
                            </div>

                            <div class="text-muted fs-7 mt-1">
                                Escribe la clase directamente o haz clic en
                                <strong>Elegir</strong> para explorar los íconos disponibles.
                            </div>
                        </div>
                        <!--end::Ícono-->

                        <!--begin::Externo-->
                        <div class="col-md-4 d-flex align-items-end pb-2">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox"
                                    name="men_externo" id="men_externo" value="1" />
                                <span class="form-check-label fw-semibold ms-2">
                                    Abrir en nueva pestaña
                                </span>
                            </label>
                        </div>
                        <!--end::Externo-->

                        <div class="col-md-8 d-flex align-items-end pb-2">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox"
                                    name="men_inicio" id="men_inicio" value="1" />
                                <span class="form-check-label fw-semibold ms-2">
                                    Menú predeterminado al iniciar sesión
                                </span>
                            </label>
                        </div>

                        <!--begin::Descripción-->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descripción <span class="text-muted">(opcional)</span></label>
                            <input type="text" name="men_descripcion" id="men_descripcion"
                                class="form-control form-control-solid"
                                placeholder="Descripción corta del módulo" maxlength="150" />
                        </div>
                        <!--end::Descripción-->

                    </div>
                </form>

            </div>
            <!--end::Modal body-->

            <!--begin::Modal footer-->
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="btn_guardar">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                        Guardar
                    </span>
                    <span class="indicator-progress">
                        Guardando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Modal footer-->

        </div>
    </div>
</div>
<!--end::Modal-->


<!--begin::JavaScript del módulo-->
<script>
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";

// ── Utilidades ───────────────────────────────────────────────

function mostrar_cargando(btn, estado) {
    if (estado) {
        btn.setAttribute('data-kt-indicator', 'on');
        btn.disabled = true;
    } else {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
    }
}

function mostrar_alerta(tipo, mensaje) {
    Swal.fire({
        icon: tipo,
        title: tipo === 'success' ? '¡Listo!' : 'Error',
        text: mensaje,
        confirmButtonText: 'Aceptar',
        customClass: { confirmButton: 'btn btn-primary' },
        buttonsStyling: false,
    });
}

function limpiar_modal() {
    document.getElementById('form_menu').reset();
    document.getElementById('men_id').value = '0';
    document.getElementById('modal_menu_titulo').textContent = 'Nuevo Menú';
    document.getElementById('alert_errores').classList.add('d-none');
    document.getElementById('lista_errores').innerHTML = '';
    document.getElementById('row_padre').classList.add('d-none');
    document.getElementById('sub_men_id').value = '0';
    document.querySelector('input[name="tipo_menu"][value="raiz"]').checked = true;
    document.getElementById('men_inicio').checked = false;
    document.getElementById('preview_icono').innerHTML = '<i class="fa fa-bars text-muted"></i>';
}

// ── Preview del ícono en tiempo real ─────────────────────────
document.getElementById('men_imgmenu').addEventListener('input', function() {
    const clase  = this.value.trim();
    const prev   = document.getElementById('preview_icono');
    prev.innerHTML = clase
        ? `<i class="${clase}"></i>`
        : '<i class="fa fa-bars text-muted"></i>';
});

// ── Mostrar/ocultar selector de padre según tipo ─────────────
document.querySelectorAll('input[name="tipo_menu"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const rowPadre = document.getElementById('row_padre');
        if (this.value === 'sub') {
            rowPadre.classList.remove('d-none');
        } else {
            rowPadre.classList.add('d-none');
            document.getElementById('sub_men_id').value = '0';
        }
    });
});

// ── Botón Nuevo Menú ─────────────────────────────────────────
document.getElementById('btn_nuevo_menu').addEventListener('click', function() {
    limpiar_modal();
    new bootstrap.Modal(document.getElementById('modal_menu')).show();
});

// ── Botones Editar ───────────────────────────────────────────
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

            const m = res.data;
            document.getElementById('modal_menu_titulo').textContent = 'Editar Menú';
            document.getElementById('men_id').value          = m.men_id;
            document.getElementById('men_nombre').value      = m.men_nombre;
            document.getElementById('men_descripcion').value = m.men_descripcion || '';
            document.getElementById('men_url').value         = m.men_url;
            document.getElementById('men_urlamigable').value = m.men_urlamigable || '';
            document.getElementById('men_imgmenu').value     = m.men_imgmenu || '';
            document.getElementById('men_orden').value       = m.men_orden;
            document.getElementById('men_externo').checked   = (m.men_externo == '1');
            document.getElementById('men_inicio').checked    = (m.men_inicio == '1');

            // Preview del ícono
            if (m.men_imgmenu) {
                document.getElementById('preview_icono').innerHTML =
                    `<i class="${m.men_imgmenu}"></i>`;
            }

            // Si es submenú, mostrar selector de padre
            if (parseInt(m.men_tipo) === 1 && parseInt(m.sub_men_id) > 0) {
                document.querySelector('input[name="tipo_menu"][value="sub"]').checked = true;
                document.getElementById('row_padre').classList.remove('d-none');
                document.getElementById('sub_men_id').value = m.sub_men_id;
            }

            new bootstrap.Modal(document.getElementById('modal_menu')).show();
        })
        .catch(() => mostrar_alerta('error', 'Error al cargar los datos.'));
    });
});

// ── Botón Guardar (Crear / Actualizar) ───────────────────────
document.getElementById('btn_guardar').addEventListener('click', function() {
    const btn    = this;
    const id     = document.getElementById('men_id').value;
    const esNuevo = (id === '0' || id === '');

    // Recoger datos del form
    const form = document.getElementById('form_menu');
    const fd   = new FormData(form);

    // sub_men_id: si es raíz forzar 0
    const tipoMenu = document.querySelector('input[name="tipo_menu"]:checked').value;
    if (tipoMenu === 'raiz') fd.set('sub_men_id', '0');

    // men_externo como string
    fd.set('men_externo', document.getElementById('men_externo').checked ? '1' : '0');
    fd.set('men_inicio', document.getElementById('men_inicio').checked ? '1' : '0');

    const url = esNuevo
        ? BASE_URL + 'crear'
        : BASE_URL + 'actualizar/' + id;

    mostrar_cargando(btn, true);
    document.getElementById('alert_errores').classList.add('d-none');
    document.getElementById('lista_errores').innerHTML = '';

    fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    })
    .then(r => r.json())
    .then(res => {
        mostrar_cargando(btn, false);

        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modal_menu')).hide();
            mostrar_alerta('success', res.msg);
            setTimeout(() => location.reload(), 1200);
        } else if (res.errores) {
            // Mostrar errores de validación
            const lista = document.getElementById('lista_errores');
            lista.innerHTML = '';
            Object.values(res.errores).forEach(e => {
                const li = document.createElement('li');
                li.textContent = e;
                lista.appendChild(li);
            });
            document.getElementById('alert_errores').classList.remove('d-none');
        } else {
            mostrar_alerta('error', res.msg || 'Error inesperado.');
        }
    })
    .catch(() => {
        mostrar_cargando(btn, false);
        mostrar_alerta('error', 'Error de conexión.');
    });
});

// ── Botones Eliminar ─────────────────────────────────────────
document.querySelectorAll('.btn_eliminar').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id      = this.dataset.id;
        const nombre  = this.dataset.nombre;
        const hijos   = this.dataset.hijos === '1';

        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar menú?',
            html: hijos
                ? `<b>${nombre}</b> tiene submenús. <br>Se eliminarán <b>todos</b> junto con él.`
                : `¿Confirmas eliminar <b>${nombre}</b>?`,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton:  'btn btn-light ms-2',
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
