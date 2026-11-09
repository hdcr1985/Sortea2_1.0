<!--begin::Header del rol al estilo Demo 10-->
<div class="card card-flush shadow-sm mb-6">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-4 py-5">
        <div class="d-flex align-items-center">
            <div class="symbol symbol-55px symbol-circle me-4">
                <span class="symbol-label bg-light-primary text-primary">
                    <i class="ki-duotone ki-security-user fs-1 text-primary">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </span>
            </div>
            <div class="d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="fw-bold text-gray-900 fs-2 mb-0">
                        <?php echo htmlspecialchars($rol['per_nombre']); ?>
                    </h2>
                    <span class="badge badge-light-dark fw-bold fs-8">
                        ID: #<?php echo (int)$rol['per_id']; ?>
                    </span>
                    <span class="badge <?php echo $rol['per_activo'] == '1' ? 'badge-light-success' : 'badge-light-danger'; ?> fw-semibold fs-7">
                        <span class="bullet bullet-dot <?php echo $rol['per_activo'] == '1' ? 'bg-success' : 'bg-danger'; ?> me-1"></span>
                        <?php echo $rol['per_activo'] == '1' ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 text-muted fw-semibold fs-7">
                    <span>
                        <i class="ki-duotone ki-profile-user fs-7 text-muted me-1">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                        </i>
                        <?php echo $rol['per_tipo'] == '0' ? 'Rol Grupal (Múltiples usuarios)' : 'Rol Individual (Usuario único)'; ?>
                    </span>
                    <span class="bullet bullet-dot bg-gray-400"></span>
                    <span>
                        <?php echo htmlspecialchars($rol['per_descripcion'] ?? 'Sin descripción asignada'); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center bg-light-primary bg-opacity-40 rounded px-4 py-2 border border-dashed border-primary border-opacity-30">
                <i class="ki-duotone ki-category fs-2 text-primary me-2">
                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                </i>
                <div class="fs-7 text-gray-700">
                    <span id="contador_modulos_activos" class="fw-bold text-primary fs-5">0</span> módulos asignados
                </div>
            </div>

            <a href="<?php echo base_url($url_base); ?>" class="btn btn-sm btn-light fw-semibold">
                <i class="ki-duotone ki-arrow-left fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Volver
            </a>

            <button type="button" class="btn btn-sm btn-primary fw-semibold btn-guardar-trigger">
                <i class="ki-duotone ki-check fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Guardar
            </button>
        </div>
    </div>
</div>
<!--end::Header del rol-->

<!--begin::Card permisos principal estilo Demo 10-->
<div class="card card-flush shadow-sm">

    <!--begin::Card header-->
    <div class="card-header border-0 pt-6 pb-4">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="card-label fw-bold text-gray-900 fs-4 mb-1">
                    Matriz de Permisos y Acciones
                </h3>
                <span class="text-muted fw-semibold fs-7">
                    Configura el acceso al menú y autorizaciones de operación (Consultar, Incluir, Modificar, Eliminar, Imprimir) para cada módulo.
                </span>
            </div>
        </div>
        <div class="card-toolbar flex-wrap gap-3">
            <!--begin::Buscador rápido-->
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-3 text-gray-500">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="buscador_modulos" class="form-control form-control-solid form-control-sm w-225px ps-10" placeholder="Buscar módulo o sección..." />
            </div>
            <!--end::Buscador rápido-->

            <!--begin::Botones de selección masiva-->
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light-primary btn-sm fw-semibold" id="btn_marcar_todo" title="Habilita acceso y todas las acciones">
                    <i class="ki-duotone ki-check-square fs-5 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Marcar todo
                </button>
                <button type="button" class="btn btn-light-info btn-sm fw-semibold" id="btn_solo_consulta" title="Habilita acceso únicamente con permiso de consulta">
                    <i class="ki-duotone ki-eye fs-5 me-1">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                    </i>
                    Solo lectura
                </button>
                <button type="button" class="btn btn-light-danger btn-sm fw-semibold" id="btn_desmarcar_todo" title="Revoca todo acceso">
                    <i class="ki-duotone ki-cross-square fs-5 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Desmarcar todo
                </button>
            </div>
            <!--end::Botones de selección masiva-->
        </div>
    </div>
    <!--end::Card header-->

    <!--begin::Card body-->
    <div class="card-body pt-0 pb-6">

        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3 my-0" id="tabla_permisos">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200 bg-light-secondary py-3">
                        <th class="min-w-280px ps-4 rounded-start">Módulo / Sección</th>
                        <th class="min-w-110px text-center">Acceso General</th>
                        <th class="min-w-90px text-center">Consultar</th>
                        <th class="min-w-90px text-center">Incluir</th>
                        <th class="min-w-90px text-center">Modificar</th>
                        <th class="min-w-90px text-center">Eliminar</th>
                        <th class="min-w-90px text-center pe-4 rounded-end">Imprimir</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold fs-7">
                <?php
                /**
                 * Renderiza recursivamente el árbol de menús con controles Metronic Demo 10.
                 *
                 * @param array $menus
                 * @param array $permisos_actuales
                 * @param int   $nivel
                 */
                function render_permisos_demo10($menus, $permisos_actuales, $nivel = 0) {
                    foreach ($menus as $menu):
                        $id          = $menu['men_id'];
                        $tiene_hijos = !empty($menu['hijos']);
                        $es_raiz     = ((int)$menu['men_tipo'] === 0);
                        $padding     = $nivel * 24;

                        // Permisos actuales en base de datos
                        $p = $permisos_actuales[$id] ?? [];
                        $tiene_acceso = !empty($p['mis_activo']) && $p['mis_activo'] === '1';

                        // Clase de ícono
                        $icono = !empty($menu['men_imgmenu']) ? $menu['men_imgmenu'] : '';
                        $es_ki = strpos($icono, 'ki-duotone') !== false;
                ?>
                <tr class="menu-row <?php echo $es_raiz ? 'bg-light bg-opacity-70 fw-bold' : ''; ?>"
                    data-id="<?php echo $id; ?>"
                    data-padre="<?php echo (int)$menu['sub_men_id']; ?>"
                    data-raiz="<?php echo $es_raiz ? '1' : '0'; ?>"
                    data-nombre="<?php echo htmlspecialchars(strtolower($menu['men_nombre'])); ?>">

                    <!--begin::Nombre del módulo-->
                    <td class="ps-4 py-3">
                        <div class="d-flex align-items-center" style="padding-left: <?php echo $padding; ?>px">
                            <?php if ($es_raiz): ?>
                                <div class="symbol symbol-30px me-3">
                                    <span class="symbol-label bg-light-primary text-primary">
                                        <?php if ($icono): ?>
                                            <i class="<?php echo htmlspecialchars($icono); ?> fs-5">
                                                <?php if ($es_ki): ?>
                                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                                                <?php endif; ?>
                                            </i>
                                        <?php else: ?>
                                            <i class="ki-duotone ki-category fs-5">
                                                <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                            </i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <span class="fw-bold text-gray-900 fs-6">
                                    <?php echo htmlspecialchars($menu['men_nombre']); ?>
                                </span>
                                <?php if ($tiene_hijos): ?>
                                    <span class="badge badge-light-primary fs-8 ms-2 px-2 py-1">
                                        <?php echo count($menu['hijos']); ?> submódulos
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bullet bullet-dot bg-gray-400 me-3"></span>
                                <?php if ($icono): ?>
                                    <i class="<?php echo htmlspecialchars($icono); ?> text-muted fs-6 me-2">
                                        <?php if ($es_ki): ?>
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                                        <?php endif; ?>
                                    </i>
                                <?php endif; ?>
                                <span class="fw-semibold text-gray-800 fs-7">
                                    <?php echo htmlspecialchars($menu['men_nombre']); ?>
                                </span>
                                <?php if (!empty($menu['men_urlamigable'])): ?>
                                    <span class="text-muted fs-8 ms-2">/<?php echo htmlspecialchars($menu['men_urlamigable']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <!--end::Nombre del módulo-->

                    <!--begin::Switch de Acceso General-->
                    <td class="text-center py-3">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input type="checkbox"
                                    class="form-check-input h-20px w-35px chk-acceso cursor-pointer"
                                    data-id="<?php echo $id; ?>"
                                    <?php echo $tiene_acceso ? 'checked' : ''; ?>
                                    title="Activar/Desactivar acceso general a este módulo" />
                            </div>
                        </div>
                    </td>
                    <!--end::Switch de Acceso General-->

                    <!--begin::Acción: Consultar-->
                    <td class="text-center py-3">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-check-custom form-check-solid form-check-sm">
                                <input type="checkbox"
                                    class="form-check-input chk-accion"
                                    data-id="<?php echo $id; ?>"
                                    data-accion="consultar"
                                    <?php echo !empty($p['acc_consultar']) ? 'checked' : ''; ?>
                                    <?php echo !$tiene_acceso ? 'disabled' : ''; ?>
                                    title="Permitir consultar registros" />
                            </div>
                        </div>
                    </td>
                    <!--end::Acción: Consultar-->

                    <!--begin::Acción: Incluir-->
                    <td class="text-center py-3">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-check-custom form-check-solid form-check-sm">
                                <input type="checkbox"
                                    class="form-check-input chk-accion"
                                    data-id="<?php echo $id; ?>"
                                    data-accion="incluir"
                                    <?php echo !empty($p['acc_incluir']) ? 'checked' : ''; ?>
                                    <?php echo !$tiene_acceso ? 'disabled' : ''; ?>
                                    title="Permitir crear nuevos registros" />
                            </div>
                        </div>
                    </td>
                    <!--end::Acción: Incluir-->

                    <!--begin::Acción: Modificar-->
                    <td class="text-center py-3">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-check-custom form-check-solid form-check-sm">
                                <input type="checkbox"
                                    class="form-check-input chk-accion"
                                    data-id="<?php echo $id; ?>"
                                    data-accion="modificar"
                                    <?php echo !empty($p['acc_modificar']) ? 'checked' : ''; ?>
                                    <?php echo !$tiene_acceso ? 'disabled' : ''; ?>
                                    title="Permitir editar registros existentes" />
                            </div>
                        </div>
                    </td>
                    <!--end::Acción: Modificar-->

                    <!--begin::Acción: Eliminar-->
                    <td class="text-center py-3">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-check-custom form-check-solid form-check-sm">
                                <input type="checkbox"
                                    class="form-check-input chk-accion"
                                    data-id="<?php echo $id; ?>"
                                    data-accion="eliminar"
                                    <?php echo !empty($p['acc_eliminar']) ? 'checked' : ''; ?>
                                    <?php echo !$tiene_acceso ? 'disabled' : ''; ?>
                                    title="Permitir eliminar registros" />
                            </div>
                        </div>
                    </td>
                    <!--end::Acción: Eliminar-->

                    <!--begin::Acción: Imprimir-->
                    <td class="text-center pe-4 py-3">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-check-custom form-check-solid form-check-sm">
                                <input type="checkbox"
                                    class="form-check-input chk-accion"
                                    data-id="<?php echo $id; ?>"
                                    data-accion="imprimir"
                                    <?php echo !empty($p['acc_imprimir']) ? 'checked' : ''; ?>
                                    <?php echo !$tiene_acceso ? 'disabled' : ''; ?>
                                    title="Permitir exportar e imprimir reportes" />
                            </div>
                        </div>
                    </td>
                    <!--end::Acción: Imprimir-->

                </tr>

                <?php
                        if ($tiene_hijos) {
                            render_permisos_demo10($menu['hijos'], $permisos_actuales, $nivel + 1);
                        }
                    endforeach;
                }

                render_permisos_demo10($menus, $permisos);
                ?>
                </tbody>
            </table>
        </div>

    </div>
    <!--end::Card body-->

    <!--begin::Card footer estilo Demo 10-->
    <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-4 py-5 border-top border-gray-200">
        <div class="d-flex align-items-center text-muted fs-7">
            <i class="ki-duotone ki-information-2 fs-4 text-primary me-2">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            Los permisos asignados tendrán vigencia en tiempo real para todos los usuarios vinculados a este rol.
        </div>

        <div class="d-flex align-items-center gap-3">
            <a href="<?php echo base_url($url_base); ?>" class="btn btn-sm btn-light fw-semibold px-4">
                <i class="ki-duotone ki-cross fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Cancelar
            </a>

            <button type="button" class="btn btn-sm btn-primary fw-semibold px-4" id="btn_guardar_permisos">
                <span class="indicator-label">
                    <i class="ki-duotone ki-check fs-4 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Guardar Permisos
                </span>
                <span class="indicator-progress">
                    Guardando...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </div>
    <!--end::Card footer-->

</div>
<!--end::Card permisos principal-->

<!--begin::JavaScript Reactivo-->
<script>
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
const ROL_ID   = <?php echo (int)$rol['per_id']; ?>;

// ── Actualiza el contador superior de módulos con acceso ─────────────
function actualizar_contador_modulos() {
    const total = document.querySelectorAll('.chk-acceso:checked').length;
    const el = document.getElementById('contador_modulos_activos');
    if (el) el.textContent = total;
}

// ── Al marcar/desmarcar "Acceso General" ──────────────────────────────
document.querySelectorAll('.chk-acceso').forEach(function(chk) {
    chk.addEventListener('change', function() {
        const id       = this.dataset.id;
        const activo   = this.checked;
        const acciones = document.querySelectorAll(`.chk-accion[data-id="${id}"]`);

        acciones.forEach(function(a) {
            a.disabled = !activo;
            if (!activo) {
                a.checked = false;
            } else if (a.dataset.accion === 'consultar' && !a.checked) {
                // Al encender acceso, auto-activar 'consultar' por conveniencia
                a.checked = true;
            }
        });

        // Cascada sobre submódulos si es menú raíz
        const fila = this.closest('tr');
        if (fila && fila.dataset.raiz === '1' && !activo) {
            let siguiente = fila.nextElementSibling;
            while (siguiente && siguiente.dataset.raiz !== '1') {
                const chkHijo = siguiente.querySelector('.chk-acceso');
                if (chkHijo && chkHijo.checked) {
                    chkHijo.checked = false;
                    chkHijo.dispatchEvent(new Event('change'));
                }
                siguiente = siguiente.nextElementSibling;
            }
        }

        actualizar_contador_modulos();
    });
});

// ── Buscador en vivo de módulos ──────────────────────────────────────
const buscador = document.getElementById('buscador_modulos');
if (buscador) {
    buscador.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('#tabla_permisos tbody tr.menu-row').forEach(function(tr) {
            const nombre = tr.getAttribute('data-nombre') || '';
            if (term === '' || nombre.includes(term)) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
        });
    });
}

// ── Marcar todo ──────────────────────────────────────────────────────
document.getElementById('btn_marcar_todo').addEventListener('click', function() {
    document.querySelectorAll('.chk-acceso').forEach(chk => {
        chk.checked = true;
        const id = chk.dataset.id;
        document.querySelectorAll(`.chk-accion[data-id="${id}"]`).forEach(a => {
            a.disabled = false;
            a.checked = true;
        });
    });
    actualizar_contador_modulos();
});

// ── Solo lectura / consulta ──────────────────────────────────────────
document.getElementById('btn_solo_consulta').addEventListener('click', function() {
    document.querySelectorAll('.chk-acceso').forEach(chk => {
        chk.checked = true;
        const id = chk.dataset.id;
        document.querySelectorAll(`.chk-accion[data-id="${id}"]`).forEach(a => {
            a.disabled = false;
            a.checked = (a.dataset.accion === 'consultar');
        });
    });
    actualizar_contador_modulos();
});

// ── Desmarcar todo ───────────────────────────────────────────────────
document.getElementById('btn_desmarcar_todo').addEventListener('click', function() {
    document.querySelectorAll('.chk-acceso').forEach(chk => {
        chk.checked = false;
        const id = chk.dataset.id;
        document.querySelectorAll(`.chk-accion[data-id="${id}"]`).forEach(a => {
            a.disabled = true;
            a.checked = false;
        });
    });
    actualizar_contador_modulos();
});

// ── Disparador superior para guardar ─────────────────────────────────
document.querySelectorAll('.btn-guardar-trigger').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('btn_guardar_permisos').click();
    });
});

// ── Guardar permisos vía AJAX ────────────────────────────────────────
document.getElementById('btn_guardar_permisos').addEventListener('click', function() {
    const btn = this;
    const permisos = {};

    document.querySelectorAll('.chk-acceso').forEach(function(chk) {
        const id = chk.dataset.id;
        if (chk.checked) {
            permisos[id] = { acceso: 1 };

            document.querySelectorAll(`.chk-accion[data-id="${id}"]`).forEach(function(a) {
                if (a.checked) {
                    permisos[id][a.dataset.accion] = 1;
                }
            });
        }
    });

    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    const topBtn = document.querySelector('.btn-guardar-trigger');
    if (topBtn) topBtn.disabled = true;

    const fd = new FormData();
    fd.append('permisos', JSON.stringify(permisos));

    fetch(BASE_URL + 'guardar_permisos/' + ROL_ID, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        if (topBtn) topBtn.disabled = false;

        Swal.fire({
            icon: res.ok ? 'success' : 'error',
            title: res.ok ? '¡Permisos Actualizados!' : 'Error',
            text: res.msg,
            confirmButtonText: 'Aceptar',
            customClass: { confirmButton: 'btn btn-sm btn-primary' },
            buttonsStyling: false,
        });

        actualizar_contador_modulos();
    })
    .catch(() => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        if (topBtn) topBtn.disabled = false;

        Swal.fire({
            icon: 'error',
            title: 'Error de Conexión',
            text: 'No se pudo comunicar con el servidor para guardar los permisos.',
            confirmButtonText: 'Aceptar',
            customClass: { confirmButton: 'btn btn-sm btn-primary' },
            buttonsStyling: false,
        });
    });
});

// Inicializar conteo al cargar la pantalla
document.addEventListener('DOMContentLoaded', actualizar_contador_modulos);
actualizar_contador_modulos();
</script>
<!--end::JavaScript-->
