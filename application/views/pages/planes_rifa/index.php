<?php
/**
 * M02 - Planes de rifa y asignaciones.
 * Vista administrativa (frontend-specialist).
 *
 * Contrato de datos provisto por el controlador PlanesRifa:
 * - $url_base string Raíz del módulo (por defecto "planes-rifa").
 *
 * Endpoints consumidos (todos bajo $url_base):
 * - Planes:       tabla (POST DataTables), datos/{id}, crear, actualizar/{id},
 *                 eliminar/{id}, cambiar_status/{id}.
 * - Numeraciones: numeraciones (POST DataTables), numeracion_datos/{id},
 *                 numeracion_crear, numeracion_actualizar/{id},
 *                 numeracion_eliminar/{id}, numeracion_status/{id},
 *                 numeraciones_select.
 * - Asignaciones: asignaciones (POST DataTables), asignacion_datos/{id},
 *                 asignar, cancelar_asignacion/{id}, consumos/{asig_id},
 *                 organizadores_select, planes_select, plan_opciones/{plan_id}.
 *
 * Claves DataTables:
 * - Planes:       plan, opciones, vigencia, precio, asignaciones, estado,
 *                 acciones (tipo se conserva para render de compatibilidad).
 * - Numeraciones: numeracion, rango, cantidad, estado, acciones.
 * - Asignaciones: organizador, plan, numeracion, cantidad, uso, precio,
 *                 vigencia, estado, acciones.
 *
 * Clases de acción emitidas por el backend:
 * - Planes:        .btn-editar, .btn-status, .btn-eliminar.
 * - Numeraciones:  .btn-editar-numeracion, .btn-status-numeracion,
 *                  .btn-eliminar-numeracion.
 * - Asignaciones:  .btn-consumos (detalle + consumos), .btn-cancelar.
 */

$url_base = (isset($url_base) && is_string($url_base) && trim($url_base) !== '')
    ? trim($url_base, '/')
    : 'planes-rifa';
?>

<div class="d-flex flex-column gap-7 gap-lg-10">

    <!--begin::Tabs del módulo-->
    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-1 fs-6" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active text-active-primary d-flex align-items-center pb-3"
                data-bs-toggle="tab"
                href="#kt_tab_planes_rifa"
                id="tab_link_planes_rifa"
                role="tab"
                aria-controls="kt_tab_planes_rifa"
                aria-selected="true">
                <i class="ki-duotone ki-crown fs-4 me-2">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Planes
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link text-active-primary d-flex align-items-center pb-3"
                data-bs-toggle="tab"
                href="#kt_tab_asignaciones_rifa"
                id="tab_link_asignaciones_rifa"
                role="tab"
                aria-controls="kt_tab_asignaciones_rifa"
                aria-selected="false">
                <i class="ki-duotone ki-people fs-4 me-2">
                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                </i>
                Asignaciones
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link text-active-primary d-flex align-items-center pb-3"
                data-bs-toggle="tab"
                href="#tab_numeraciones"
                id="tab_link_numeraciones"
                role="tab"
                aria-controls="tab_numeraciones"
                aria-selected="false">
                <i class="ki-duotone ki-abstract-26 fs-4 me-2">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Numeraciones
            </a>
        </li>
    </ul>
    <!--end::Tabs del módulo-->

    <div class="tab-content">
        <!--begin::Tab Planes-->
        <div class="tab-pane fade show active" id="kt_tab_planes_rifa" role="tabpanel" aria-labelledby="tab_link_planes_rifa">
            <div class="card card-flush shadow-sm">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="d-flex flex-column">
                            <h3 class="fw-bold text-gray-900 mb-1">Planes de rifa</h3>
                            <span class="text-muted fs-7">Define vigencia, opciones de numeración y precio de cada plan.</span>
                        </div>
                    </div>
                    <div class="card-toolbar gap-3 flex-wrap">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                            <input type="text"
                                id="filtro_planes_rifa"
                                class="form-control form-control-solid w-250px ps-12"
                                placeholder="Buscar planes..."
                                aria-label="Buscar planes" />
                        </div>
                        <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nuevo_plan_rifa">
                            <i class="ki-duotone ki-plus fs-4 me-1">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                            Nuevo Plan
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="alert alert-warning d-none mb-4" id="aviso_datatables_planes">
                        No se pudo cargar el componente de tablas. Recarga la página o contacta al administrador.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_planes_rifa">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                    <th scope="col" class="min-w-220px">Plan</th>
                                    <th scope="col" class="min-w-180px">Opciones</th>
                                    <th scope="col" class="min-w-150px">Vigencia</th>
                                    <th scope="col" class="min-w-130px">Precio</th>
                                    <th scope="col" class="min-w-140px text-center">Asignaciones</th>
                                    <th scope="col" class="min-w-110px text-center">Estado</th>
                                    <th scope="col" class="min-w-120px text-end pe-2">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Tab Planes-->

        <!--begin::Tab Asignaciones-->
        <div class="tab-pane fade" id="kt_tab_asignaciones_rifa" role="tabpanel" aria-labelledby="tab_link_asignaciones_rifa">
            <div class="card card-flush shadow-sm">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="d-flex flex-column">
                            <h3 class="fw-bold text-gray-900 mb-1">Asignaciones de planes</h3>
                            <span class="text-muted fs-7">Lotes asignados a cada organizador, su numeración y uso.</span>
                        </div>
                    </div>
                    <div class="card-toolbar gap-3 flex-wrap">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                            <input type="text"
                                id="filtro_asignaciones_rifa"
                                class="form-control form-control-solid w-250px ps-12"
                                placeholder="Buscar asignaciones..."
                                aria-label="Buscar asignaciones" />
                        </div>
                        <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4 py-2" id="btn_asignar_plan">
                            <i class="ki-duotone ki-plus fs-4 me-1">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                            Asignar lote
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="alert alert-warning d-none mb-4" id="aviso_datatables_asignaciones">
                        No se pudo cargar el componente de tablas. Recarga la página o contacta al administrador.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_asignaciones_rifa">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                    <th scope="col" class="min-w-200px">Organizador</th>
                                    <th scope="col" class="min-w-170px">Plan</th>
                                    <th scope="col" class="min-w-140px">Numeración</th>
                                    <th scope="col" class="min-w-100px text-center">Cantidad</th>
                                    <th scope="col" class="min-w-120px">Uso</th>
                                    <th scope="col" class="min-w-140px">Precio</th>
                                    <th scope="col" class="min-w-160px">Vigencia</th>
                                    <th scope="col" class="min-w-110px text-center">Estado</th>
                                    <th scope="col" class="min-w-120px text-end pe-2">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Tab Asignaciones-->

        <!--begin::Tab Numeraciones-->
        <div class="tab-pane fade" id="tab_numeraciones" role="tabpanel" aria-labelledby="tab_link_numeraciones">
            <div class="card card-flush shadow-sm">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="d-flex flex-column">
                            <h3 class="fw-bold text-gray-900 mb-1">Numeraciones</h3>
                            <span class="text-muted fs-7">Catálogo global de rangos numéricos disponibles para los planes.</span>
                        </div>
                    </div>
                    <div class="card-toolbar gap-3 flex-wrap">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                            <input type="text"
                                id="filtro_numeraciones"
                                class="form-control form-control-solid w-250px ps-12"
                                placeholder="Buscar numeraciones..."
                                aria-label="Buscar numeraciones" />
                        </div>
                        <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_nueva_numeracion">
                            <i class="ki-duotone ki-plus fs-4 me-1">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                            Nueva Numeración
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="alert alert-warning d-none mb-4" id="aviso_datatables_numeraciones">
                        No se pudo cargar el componente de tablas. Recarga la página o contacta al administrador.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_numeraciones">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                    <th scope="col" class="min-w-200px">Numeración</th>
                                    <th scope="col" class="min-w-150px">Rango</th>
                                    <th scope="col" class="min-w-130px">Cantidad</th>
                                    <th scope="col" class="min-w-110px text-center">Estado</th>
                                    <th scope="col" class="min-w-120px text-end pe-2">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Tab Numeraciones-->
    </div>
</div>

<!--begin::Modal Plan de rifa (Crear / Editar)-->
<div class="modal fade" id="modal_plan_rifa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-crown fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_plan_rifa_titulo">Nuevo Plan</h4>
                        <span class="text-muted fs-8">Configura la vigencia y las opciones de numeración del plan</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_plan_rifa">
                    <ul class="mb-0 fs-7" id="lista_errores_plan_rifa"></ul>
                </div>
                <!--end::Alerta de errores-->

                <form id="form_plan_rifa" novalidate="novalidate">
                    <input type="hidden" name="plan_id" id="plan_id" value="0" />
                    <input type="hidden" name="plan_limite" id="plan_limite" value="0" />
                    <input type="hidden" name="numeraciones" id="plan_numeraciones_json" value="" />

                    <div class="row g-5">
                        <!--begin::Nombre-->
                        <div class="col-md-12">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_nombre">Nombre del plan</label>
                            <input type="text"
                                name="plan_nombre"
                                id="plan_nombre"
                                class="form-control form-control-solid"
                                placeholder="Ej: Plan Emprendedor"
                                minlength="3"
                                maxlength="100"
                                autocomplete="off"
                                required />
                            <div class="invalid-feedback" data-error-for="plan_nombre"></div>
                        </div>
                        <!--end::Nombre-->

                        <!--begin::Descripción-->
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="plan_descripcion">Descripción</label>
                            <input type="text"
                                name="plan_descripcion"
                                id="plan_descripcion"
                                class="form-control form-control-solid"
                                placeholder="Ej: Ideal para organizadores que inician"
                                maxlength="255" />
                            <div class="invalid-feedback" data-error-for="plan_descripcion"></div>
                            <div class="text-muted fs-8 mt-1">Breve resumen visible al asignar el plan.</div>
                        </div>
                        <!--end::Descripción-->

                        <!--begin::Tipo-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_tipo">Tipo de plan</label>
                            <select name="plan_tipo" id="plan_tipo" class="form-select form-select-solid" required>
                                <option value="rifas" selected>Por rifas (opciones de numeración)</option>
                                <option value="ilimitado">Ilimitado</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="plan_tipo"></div>
                        </div>
                        <!--end::Tipo-->

                        <!--begin::Vigencia-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_periodo">Vigencia</label>
                            <select name="plan_periodo" id="plan_periodo" class="form-select form-select-solid" required>
                                <option value="sin_limite" selected>Sin límite</option>
                                <option value="mensual">Mensual</option>
                                <option value="anual">Anual</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="plan_periodo"></div>
                            <div class="text-muted fs-8 mt-1" id="hint_plan_periodo">Los planes por rifas no caducan.</div>
                        </div>
                        <!--end::Vigencia-->

                        <!--begin::Precio base-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_precio">Precio base</label>
                            <div class="position-relative">
                                <input type="number"
                                    name="plan_precio"
                                    id="plan_precio"
                                    class="form-control form-control-solid pe-8"
                                    placeholder="0.00"
                                    min="0"
                                    step="0.01"
                                    value="0.00"
                                    required />
                                <span class="position-absolute end-0 top-50 translate-middle-y me-3 text-muted fs-7">
                                    <i class="ki-duotone ki-dollar fs-5 text-gray-500"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </span>
                            </div>
                            <div class="invalid-feedback" data-error-for="plan_precio"></div>
                            <div class="text-muted fs-8 mt-1" id="hint_plan_precio">Precio base de referencia. El precio real se define por numeración.</div>
                        </div>
                        <!--end::Precio base-->

                        <!--begin::Moneda-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_moneda">Moneda</label>
                            <select name="plan_moneda" id="plan_moneda" class="form-select form-select-solid" required>
                                <?php foreach ($monedas as $moneda_item): ?>
                                    <?php
                                    $moneda_codigo  = (string)($moneda_item['mon_codigo'] ?? 'USD');
                                    $moneda_simbolo = (string)($moneda_item['mon_simbolo'] ?? $moneda_codigo);
                                    $moneda_nombre  = (string)($moneda_item['mon_nombre'] ?? $moneda_codigo);
                                    ?>
                                    <option value="<?php echo htmlspecialchars($moneda_codigo, ENT_QUOTES, 'UTF-8'); ?>"
                                        title="<?php echo htmlspecialchars($moneda_nombre, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $moneda_codigo === 'USD' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($moneda_codigo . ' (' . $moneda_simbolo . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-error-for="plan_moneda"></div>
                        </div>
                        <!--end::Moneda-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Opciones de numeración-->
                        <div class="col-12" id="wrap_plan_opciones">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-6">Opciones de numeración</div>
                                    <div class="text-muted fs-8">Cada opción define una numeración y su precio por rifa para este plan.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4 py-2" id="btn_agregar_opcion_numeracion">
                                    <i class="ki-duotone ki-plus fs-4 me-1">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    Agregar numeración
                                </button>
                            </div>

                            <div class="border border-gray-200 rounded-3 bg-light p-4">
                                <div id="plan_opciones_rows" class="d-flex flex-column gap-3"></div>
                                <div id="plan_opciones_empty" class="text-center text-muted fs-7 py-4">
                                    Aún no hay numeraciones configuradas. Agrega al menos una activa.
                                </div>
                            </div>
                            <div class="invalid-feedback d-block mt-2" data-error-for="numeraciones"></div>
                        </div>
                        <!--end::Opciones de numeración-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Estado-->
                        <div class="col-12">
                            <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Plan activo</div>
                                    <div class="text-muted fs-8">Los planes inactivos no se pueden asignar a nuevos organizadores.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="plan_status" id="plan_status" value="1" checked />
                                </div>
                            </div>
                            <div class="invalid-feedback" data-error-for="plan_status"></div>
                        </div>
                        <!--end::Estado-->
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_plan_rifa">
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
<!--end::Modal Plan de rifa-->

<!--begin::Modal Asignar lote de plan-->
<div class="modal fade" id="modal_asignar_plan" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-people fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Asignar lote de plan</h4>
                        <span class="text-muted fs-8">Registra un lote nuevo para el organizador sin reemplazar los anteriores</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_asignar_plan">
                    <ul class="mb-0 fs-7" id="lista_errores_asignar_plan"></ul>
                </div>
                <!--end::Alerta de errores-->

                <form id="form_asignar_plan" novalidate="novalidate">
                    <div class="row g-5">
                        <!--begin::Organizador-->
                        <div class="col-12">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="asig_org_id">Organizador</label>
                            <select name="org_id" id="asig_org_id" class="form-select form-select-solid" required>
                                <option value="">Busca un organizador...</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="org_id"></div>
                        </div>
                        <!--end::Organizador-->

                        <!--begin::Plan-->
                        <div class="col-12">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="asig_plan_id">Plan de rifas</label>
                            <select name="plan_id" id="asig_plan_id" class="form-select form-select-solid" required>
                                <option value="">Selecciona un plan...</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="plan_id"></div>
                        </div>
                        <!--end::Plan-->

                        <!--begin::Datos del lote por rifas-->
                        <div class="col-12 d-none" id="wrap_asig_rifas">
                            <div class="border border-gray-200 rounded-3 p-4">
                                <div class="row g-5">
                                    <div class="col-md-6">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="asig_num_id">Numeración</label>
                                        <select name="num_id" id="asig_num_id" class="form-select form-select-solid">
                                            <option value="">Selecciona una numeración...</option>
                                        </select>
                                        <div class="invalid-feedback" data-error-for="num_id"></div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="asig_cantidad">Cantidad de rifas</label>
                                        <input type="number"
                                            name="cantidad"
                                            id="asig_cantidad"
                                            class="form-control form-control-solid"
                                            min="1"
                                            max="1000000"
                                            step="1"
                                            value="1" />
                                        <div class="invalid-feedback" data-error-for="cantidad"></div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="asig_precio_unitario">Precio unitario</label>
                                        <input type="text"
                                            id="asig_precio_unitario"
                                            class="form-control form-control-solid"
                                            value="—"
                                            readonly
                                            tabindex="-1"
                                            aria-readonly="true" />
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="asig_total">Total del lote</label>
                                        <input type="text"
                                            id="asig_total"
                                            class="form-control form-control-solid"
                                            value="—"
                                            readonly
                                            tabindex="-1"
                                            aria-readonly="true" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Datos del lote por rifas-->

                        <!--begin::Datos del lote ilimitado-->
                        <div class="col-12 d-none" id="wrap_asig_ilimitado">
                            <div class="border border-gray-200 rounded-3 bg-light p-4">
                                <div class="row g-5">
                                    <div class="col-md-6">
                                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Precio del plan</div>
                                        <div class="fw-bold text-gray-900 fs-5" id="asig_ilimitado_precio">—</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Periodo de vigencia</div>
                                        <div class="fw-bold text-gray-900 fs-5" id="asig_ilimitado_periodo">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Datos del lote ilimitado-->

                        <!--begin::Notas-->
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="asig_notas">Notas</label>
                            <input type="text"
                                name="notas"
                                id="asig_notas"
                                class="form-control form-control-solid"
                                placeholder="Ej: Asignación de cortesía"
                                maxlength="255" />
                            <div class="invalid-feedback" data-error-for="notas"></div>
                            <div class="text-muted fs-8 mt-1">Máximo 255 caracteres.</div>
                        </div>
                        <!--end::Notas-->
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_asignacion">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Asignar lote
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
<!--end::Modal Asignar lote de plan-->

<!--begin::Modal Detalle de asignación-->
<div class="modal fade" id="modal_detalle_asignacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-info">
                        <i class="ki-duotone ki-list fs-3 text-info">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Detalle del lote asignado</h4>
                        <span class="text-muted fs-8">Información del plan, numeración y consumos registrados</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="row g-5">
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Organizador</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_org">—</div>
                            <div class="text-muted fs-8" id="detalle_org_slug">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Plan</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_plan">—</div>
                            <div class="text-muted fs-8" id="detalle_plan_tipo">—</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Numeración</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_numeracion">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Cantidad del lote</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_cantidad">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Usadas</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_usadas">—</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Restantes</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_restantes">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Precio unitario</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_precio_unitario">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Total del lote</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_total">—</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Vigencia</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="detalle_vigencia">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Estado</div>
                            <div id="detalle_estado">—</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 bg-light p-4">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Notas</div>
                            <div class="text-gray-700 fs-7" id="detalle_notas">—</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="separator separator-dashed my-1"></div>
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <h4 class="fw-bold text-gray-900 mb-0">Consumos</h4>
                            <span class="text-muted fs-8">Historial de rifas consumidas y devueltas</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                        <th scope="col" class="min-w-140px">Rifa / Referencia</th>
                                        <th scope="col" class="min-w-120px">Estado</th>
                                        <th scope="col" class="min-w-200px">Motivo</th>
                                        <th scope="col" class="min-w-150px">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 fw-semibold" id="tbody_consumos_asignacion">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-6">Sin consumos registrados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
<!--end::Modal Detalle de asignación-->

<!--begin::Modal Numeración (Crear / Editar)-->
<div class="modal fade" id="modal_numeracion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-abstract-26 fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_numeracion_titulo">Nueva Numeración</h4>
                        <span class="text-muted fs-8">Define el rango, el padding y la cantidad de números</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <!--begin::Alerta de errores-->
                <div class="alert alert-danger d-none mb-6 rounded-3" id="alert_errores_numeracion">
                    <ul class="mb-0 fs-7" id="lista_errores_numeracion"></ul>
                </div>
                <!--end::Alerta de errores-->

                <form id="form_numeracion" novalidate="novalidate">
                    <input type="hidden" name="num_id" id="num_id" value="0" />
                    <input type="hidden" name="num_orden" id="num_orden" value="0" />
                    <input type="hidden" name="num_cantidad" id="num_cantidad" value="100" />

                    <div class="row g-5">
                        <!--begin::Etiqueta-->
                        <div class="col-12">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="num_label">Etiqueta</label>
                            <input type="text"
                                name="num_label"
                                id="num_label"
                                class="form-control form-control-solid"
                                placeholder="Ej: 100 números"
                                minlength="3"
                                maxlength="60"
                                autocomplete="off"
                                required />
                            <div class="invalid-feedback" data-error-for="num_label"></div>
                            <div class="text-muted fs-8 mt-1">Entre 3 y 60 caracteres.</div>
                        </div>
                        <!--end::Etiqueta-->

                        <!--begin::Desde-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="num_desde">Desde</label>
                            <input type="number"
                                name="num_desde"
                                id="num_desde"
                                class="form-control form-control-solid"
                                min="0"
                                max="999999"
                                step="1"
                                value="0" />
                            <div class="invalid-feedback" data-error-for="num_desde"></div>
                        </div>
                        <!--end::Desde-->

                        <!--begin::Hasta-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="num_hasta">Hasta</label>
                            <input type="number"
                                name="num_hasta"
                                id="num_hasta"
                                class="form-control form-control-solid"
                                min="1"
                                max="999999"
                                step="1"
                                value="99" />
                            <div class="invalid-feedback" data-error-for="num_hasta"></div>
                        </div>
                        <!--end::Hasta-->

                        <!--begin::Padding-->
                        <div class="col-md-4">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="num_padding">Padding</label>
                            <input type="number"
                                name="num_padding"
                                id="num_padding"
                                class="form-control form-control-solid"
                                min="1"
                                max="6"
                                step="1"
                                value="2" />
                            <div class="invalid-feedback" data-error-for="num_padding"></div>
                            <div class="text-muted fs-8 mt-1">Dígitos a mostrar (1–6).</div>
                        </div>
                        <!--end::Padding-->

                        <!--begin::Cantidad calculada-->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800" for="num_cantidad_display">Cantidad</label>
                            <input type="number"
                                id="num_cantidad_display"
                                class="form-control form-control-solid"
                                value="100"
                                readonly
                                tabindex="-1"
                                aria-readonly="true" />
                            <div class="text-muted fs-8 mt-1">Se calcula desde el rango.</div>
                        </div>
                        <!--end::Cantidad calculada-->

                        <!--begin::Preview-->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-7 text-gray-800">Preview del rango</label>
                            <div class="border border-gray-200 rounded-3 bg-light p-3 h-100 d-flex align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="ki-duotone ki-abstract-26 fs-3 text-primary">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-900 fs-7" id="num_preview">00 – 99</span>
                                </div>
                            </div>
                        </div>
                        <!--end::Preview-->

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Estado-->
                        <div class="col-12">
                            <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Numeración activa</div>
                                    <div class="text-muted fs-8">Las numeraciones inactivas no se pueden asignar a planes nuevos.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="num_status" id="num_status" value="1" checked />
                                </div>
                            </div>
                            <div class="invalid-feedback" data-error-for="num_status"></div>
                        </div>
                        <!--end::Estado-->
                    </div>
                </form>
            </div>

            <div class="modal-footer d-flex justify-content-end gap-2 py-4">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4 py-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4 py-2" id="btn_guardar_numeracion">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Guardar Numeración
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
<!--end::Modal Numeración-->

<!--begin::JavaScript-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const jq = window.jQuery || null;
    const tieneDataTables = !!(jq && jq.fn && jq.fn.DataTable);
    const tieneSelect2 = !!(jq && jq.fn && jq.fn.select2);

    const modalPlanEl = document.getElementById('modal_plan_rifa');
    const modalAsignarEl = document.getElementById('modal_asignar_plan');
    const modalDetalleEl = document.getElementById('modal_detalle_asignacion');
    const modalNumeracionEl = document.getElementById('modal_numeracion');

    const modalPlan = new bootstrap.Modal(modalPlanEl);
    const modalAsignar = new bootstrap.Modal(modalAsignarEl);
    const modalDetalle = new bootstrap.Modal(modalDetalleEl);
    const modalNumeracion = new bootstrap.Modal(modalNumeracionEl);

    const formPlan = document.getElementById('form_plan_rifa');
    const formAsignar = document.getElementById('form_asignar_plan');
    const formNumeracion = document.getElementById('form_numeracion');

    const btnGuardarPlan = document.getElementById('btn_guardar_plan_rifa');
    const btnGuardarAsignacion = document.getElementById('btn_guardar_asignacion');
    const btnGuardarNumeracion = document.getElementById('btn_guardar_numeracion');
    const btnNuevoPlan = document.getElementById('btn_nuevo_plan_rifa');
    const btnAsignar = document.getElementById('btn_asignar_plan');
    const btnNuevaNumeracion = document.getElementById('btn_nueva_numeracion');

    const inputFiltroPlanes = document.getElementById('filtro_planes_rifa');
    const inputFiltroAsignaciones = document.getElementById('filtro_asignaciones_rifa');
    const inputFiltroNumeraciones = document.getElementById('filtro_numeraciones');

    const alertPlan = document.getElementById('alert_errores_plan_rifa');
    const listaPlan = document.getElementById('lista_errores_plan_rifa');
    const alertAsignar = document.getElementById('alert_errores_asignar_plan');
    const listaAsignar = document.getElementById('lista_errores_asignar_plan');
    const alertNumeracion = document.getElementById('alert_errores_numeracion');
    const listaNumeracion = document.getElementById('lista_errores_numeracion');

    const contenedorOpciones = document.getElementById('plan_opciones_rows');
    const estadoVacioOpciones = document.getElementById('plan_opciones_empty');
    const inputNumeracionesJson = document.getElementById('plan_numeraciones_json');

    let dtPlanes = null;
    let dtAsignaciones = null;
    let dtNumeraciones = null;

    let filtroPlanesTimer = null;
    let filtroAsignacionesTimer = null;
    let filtroNumeracionesTimer = null;

    let catalogoNumeraciones = null;
    let catalogoNumeracionesPromise = null;
    let asignacionPlanActual = null;
    let asignacionPlanToken = 0;

    // ─────────────────────────────────────────────────────
    // Utilidades generales
    // ─────────────────────────────────────────────────────
    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

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

    function limpiar_errores(form, alertEl, listaEl) {
        if (!form) {
            return;
        }

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

        let total = 0;
        Object.keys(errores).forEach(function (campo) {
            let mensaje = errores[campo];
            if (Array.isArray(mensaje)) {
                mensaje = mensaje.length ? mensaje[0] : '';
            }
            if (!mensaje) {
                return;
            }

            const selector = String(campo).replace(/"/g, '\\"');
            const input = form.querySelector('[name="' + selector + '"]');
            const feedback = form.querySelector('[data-error-for="' + selector + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = String(mensaje);
            }

            if (listaEl) {
                const li = document.createElement('li');
                li.textContent = String(mensaje);
                listaEl.appendChild(li);
            }
            total++;
        });

        if (total > 0 && alertEl) {
            alertEl.classList.remove('d-none');
        }
    }

    function init_tooltips() {
        if (jq && jq.fn && jq.fn.tooltip) {
            jq('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
        }
    }

    function mensaje_error_xhr(xhr, porDefecto) {
        if (xhr && xhr.status === 401) {
            return 'Tu sesión expiró. Inicia sesión nuevamente.';
        }
        if (xhr && xhr.status === 403) {
            return 'No tienes permiso para consultar este módulo.';
        }
        if (xhr && xhr.responseJSON) {
            if (xhr.responseJSON.msg) {
                return xhr.responseJSON.msg;
            }
            if (xhr.responseJSON.error) {
                return xhr.responseJSON.error;
            }
        }
        return porDefecto;
    }

    function idioma_es(entidad) {
        return {
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span> Cargando ' + entidad + '...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ ' + entidad,
            infoEmpty: 'No hay ' + entidad + ' para mostrar',
            infoFiltered: '(filtrados de _MAX_ registros)',
            zeroRecords: 'No se encontraron ' + entidad,
            emptyTable: 'No hay ' + entidad + ' registradas',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        };
    }

    function formatear_fecha(valor, incluirHora) {
        if (!valor) {
            return '';
        }

        const texto = String(valor).trim();
        if (texto === '' || texto.toUpperCase() === 'NULL') {
            return '';
        }

        const normalizado = texto.indexOf('T') === -1 ? texto.replace(' ', 'T') : texto;
        const fecha = new Date(normalizado);

        if (isNaN(fecha.getTime())) {
            return texto;
        }

        return fecha.toLocaleString('es-CO', incluirHora
            ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
            : { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function fila_data(dt, elemento) {
        if (!dt || !jq) {
            return {};
        }

        try {
            return dt.row(jq(elemento).closest('tr')).data() || {};
        } catch (e) {
            return {};
        }
    }

    function formato_numero(valor, decimales) {
        const numero = parseFloat(valor);
        const decimalesFinales = (decimales === undefined || decimales === null) ? 0 : decimales;

        if (!isFinite(numero)) {
            return decimalesFinales > 0 ? '0.00' : '0';
        }

        const partes = Math.abs(numero).toFixed(decimalesFinales).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        return (numero < 0 ? '-' : '') + partes.join('.');
    }

    function formato_moneda(valor) {
        return formato_numero(valor, 2);
    }

    function pad_numero(valor, padding) {
        const numero = parseInt(valor, 10);
        const largo = Math.max(1, Math.min(6, parseInt(padding, 10) || 1));

        if (!isFinite(numero)) {
            return '';
        }

        return String(numero).padStart(largo, '0');
    }

    function normalizar_opciones(payload) {
        let items = [];

        if (Array.isArray(payload)) {
            items = payload;
        } else if (payload && Array.isArray(payload.results)) {
            items = payload.results;
        } else if (payload && Array.isArray(payload.data)) {
            items = payload.data;
        } else if (payload && Array.isArray(payload.items)) {
            items = payload.items;
        }

        return items.map(function (item) {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const id = (item.id !== undefined && item.id !== null)
                ? item.id
                : (item.value !== undefined ? item.value : '');

            const text = (item.text !== undefined && item.text !== null && item.text !== '')
                ? item.text
                : (item.label !== undefined ? item.label : '');

            if (id === '' || id === null || id === undefined) {
                return null;
            }

            return {
                id: String(id),
                text: String(text !== '' ? text : ('#' + id))
            };
        }).filter(function (item) {
            return item !== null;
        });
    }

    function normalizar_catalogo(payload) {
        let items = [];

        if (Array.isArray(payload)) {
            items = payload;
        } else if (payload && Array.isArray(payload.results)) {
            items = payload.results;
        } else if (payload && Array.isArray(payload.data)) {
            items = payload.data;
        }

        return items.map(function (item) {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const id = (item.id !== undefined && item.id !== null) ? item.id : item.value;
            if (id === '' || id === null || id === undefined) {
                return null;
            }

            const text = (item.text !== undefined && item.text !== null && item.text !== '')
                ? item.text
                : (item.label !== undefined ? item.label : ('#' + id));

            return {
                id: String(id),
                text: String(text),
                cantidad: parseInt(item.cantidad, 10) || 0,
                desde: parseInt(item.desde, 10) || 0,
                hasta: parseInt(item.hasta, 10) || 0,
                padding: parseInt(item.padding, 10) || 1
            };
        }).filter(function (item) {
            return item !== null;
        });
    }

    function init_select2_ajax(selectId, url, placeholder, parentEl) {
        if (!tieneSelect2) {
            return;
        }

        const $select = jq('#' + selectId);
        if (!$select.length || $select.data('select2')) {
            return;
        }

        const opciones = {
            placeholder: placeholder,
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: url,
                type: 'GET',
                dataType: 'json',
                delay: 250,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: function (params) {
                    return {
                        q: params.term || '',
                        term: params.term || ''
                    };
                },
                processResults: function (data) {
                    return { results: normalizar_opciones(data) };
                },
                cache: true
            },
            language: {
                inputTooShort: function () { return 'Escribe para buscar...'; },
                noResults: function () { return 'Sin resultados'; },
                searching: function () { return 'Buscando...'; }
            }
        };

        if (parentEl) {
            opciones.dropdownParent = jq(parentEl);
        }

        $select.select2(opciones);
    }

    function init_select2_local(selectEl, placeholder, parentEl) {
        if (!tieneSelect2 || !selectEl) {
            return;
        }

        const $select = jq(selectEl);
        if (!$select.length || $select.data('select2')) {
            return;
        }

        const opciones = {
            placeholder: placeholder,
            allowClear: true,
            width: '100%',
            language: {
                noResults: function () { return 'Sin resultados'; }
            }
        };

        if (parentEl) {
            opciones.dropdownParent = jq(parentEl);
        }

        $select.select2(opciones);
    }

    function destruir_select2(selectEl) {
        if (!tieneSelect2 || !selectEl) {
            return;
        }

        const $select = jq(selectEl);
        if ($select.length && $select.data('select2')) {
            $select.select2('destroy');
        }
    }

    function cargar_select_nativo(selectId, url, placeholder) {
        const select = document.getElementById(selectId);
        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>';

        request_json(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (!res.data || res.data.ok === false) {
                return;
            }

            const opciones = normalizar_opciones(res.data);
            opciones.forEach(function (opcion) {
                const option = document.createElement('option');
                option.value = opcion.id;
                option.textContent = opcion.text;
                select.appendChild(option);
            });
        })
        .catch(function () {});
    }

    // ─────────────────────────────────────────────────────
    // Catálogo de numeraciones (para opciones de plan)
    // ─────────────────────────────────────────────────────
    function cargar_catalogo_numeraciones(force) {
        if (catalogoNumeraciones && !force) {
            return Promise.resolve(catalogoNumeraciones);
        }

        if (catalogoNumeracionesPromise && !force) {
            return catalogoNumeracionesPromise;
        }

        catalogoNumeracionesPromise = request_json(BASE_URL + 'numeraciones_select', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return [];
            }

            if (!res.data || res.data.ok === false) {
                catalogoNumeraciones = [];
                return catalogoNumeraciones;
            }

            catalogoNumeraciones = normalizar_catalogo(res.data);
            return catalogoNumeraciones;
        })
        .catch(function () {
            catalogoNumeraciones = [];
            return catalogoNumeraciones;
        });

        return catalogoNumeracionesPromise;
    }

    function invalidar_catalogo_numeraciones() {
        catalogoNumeraciones = null;
        catalogoNumeracionesPromise = null;
    }

    // ─────────────────────────────────────────────────────
    // DataTables server-side
    // ─────────────────────────────────────────────────────
    function render_precio_plan(data, type, row) {
        if (type !== 'display' || typeof data !== 'string') {
            return data;
        }

        const tipo = (row && typeof row.tipo === 'string') ? row.tipo.toLowerCase() : '';
        if (tipo.indexOf('ilimitado') !== -1) {
            return data;
        }

        const coincidencia = data.match(/>([\d.,]+)<\/span>/);
        if (!coincidencia) {
            return data;
        }

        const valor = parseFloat(coincidencia[1].replace(/,/g, ''));
        if (!isFinite(valor) || valor <= 0) {
            return '<span class="text-muted fs-7">Según numeración</span>';
        }

        return data.replace(coincidencia[0], '>Desde ' + coincidencia[1] + '</span>');
    }

    function init_tablas() {
        if (!tieneDataTables) {
            ['aviso_datatables_planes', 'aviso_datatables_asignaciones', 'aviso_datatables_numeraciones'].forEach(function (id) {
                const aviso = document.getElementById(id);
                if (aviso) {
                    aviso.classList.remove('d-none');
                }
            });
            return;
        }

        dtPlanes = jq('#tabla_planes_rifa').DataTable({
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
                error: function (xhr) {
                    mostrar_alerta('error', mensaje_error_xhr(xhr, 'No se pudieron cargar los planes.'));
                }
            },
            columns: [
                { data: 'plan', orderable: true, responsivePriority: 1 },
                { data: 'opciones', orderable: false, responsivePriority: 3 },
                { data: 'vigencia', orderable: false, responsivePriority: 4 },
                { data: 'precio', orderable: true, responsivePriority: 3, render: render_precio_plan },
                { data: 'asignaciones', orderable: false, className: 'text-center', responsivePriority: 5 },
                { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
            ],
            drawCallback: function () {
                init_tooltips();
            },
            language: idioma_es('planes')
        });

        dtAsignaciones = jq('#tabla_asignaciones_rifa').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
            ajax: {
                url: BASE_URL + 'asignaciones',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                error: function (xhr) {
                    mostrar_alerta('error', mensaje_error_xhr(xhr, 'No se pudieron cargar las asignaciones.'));
                }
            },
            columns: [
                { data: 'organizador', orderable: true, responsivePriority: 1 },
                { data: 'plan', orderable: true, responsivePriority: 2 },
                { data: 'numeracion', orderable: true, responsivePriority: 3 },
                { data: 'cantidad', orderable: true, className: 'text-center', responsivePriority: 4 },
                { data: 'uso', orderable: false, responsivePriority: 3 },
                { data: 'precio', orderable: true, responsivePriority: 4 },
                { data: 'vigencia', orderable: false, responsivePriority: 5 },
                { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
            ],
            drawCallback: function () {
                init_tooltips();
            },
            language: idioma_es('asignaciones')
        });

        dtNumeraciones = jq('#tabla_numeraciones').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
            ajax: {
                url: BASE_URL + 'numeraciones',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                error: function (xhr) {
                    mostrar_alerta('error', mensaje_error_xhr(xhr, 'No se pudieron cargar las numeraciones.'));
                }
            },
            columns: [
                { data: 'numeracion', orderable: true, responsivePriority: 1 },
                { data: 'rango', orderable: true, responsivePriority: 2 },
                { data: 'cantidad', orderable: true, responsivePriority: 3 },
                { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1 }
            ],
            drawCallback: function () {
                init_tooltips();
            },
            language: idioma_es('numeraciones')
        });
    }

    init_tablas();

    if (inputFiltroPlanes && dtPlanes) {
        inputFiltroPlanes.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroPlanesTimer);
            filtroPlanesTimer = setTimeout(function () {
                dtPlanes.search(valor).draw();
            }, 250);
        });
    }

    if (inputFiltroAsignaciones && dtAsignaciones) {
        inputFiltroAsignaciones.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroAsignacionesTimer);
            filtroAsignacionesTimer = setTimeout(function () {
                dtAsignaciones.search(valor).draw();
            }, 250);
        });
    }

    if (inputFiltroNumeraciones && dtNumeraciones) {
        inputFiltroNumeraciones.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroNumeracionesTimer);
            filtroNumeracionesTimer = setTimeout(function () {
                dtNumeraciones.search(valor).draw();
            }, 250);
        });
    }

    // Ajustar columnas al mostrar una pestaña (DataTables en contenedor oculto)
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            [dtPlanes, dtAsignaciones, dtNumeraciones].forEach(function (dt) {
                if (!dt) {
                    return;
                }
                dt.columns.adjust();
                if (dt.responsive) {
                    dt.responsive.recalc();
                }
            });
        });
    });

    // ─────────────────────────────────────────────────────
    // Modal de plan: tipo y opciones de numeración
    // ─────────────────────────────────────────────────────
    function aplicar_tipo_plan(tipo) {
        const esRifas = (tipo === 'rifas');
        const wrapOpciones = document.getElementById('wrap_plan_opciones');
        const selectPeriodo = document.getElementById('plan_periodo');
        const hintPeriodo = document.getElementById('hint_plan_periodo');
        const hintPrecio = document.getElementById('hint_plan_precio');
        const inputLimite = document.getElementById('plan_limite');

        if (inputLimite) {
            inputLimite.value = '0';
        }

        if (wrapOpciones) {
            wrapOpciones.classList.toggle('d-none', !esRifas);
        }

        if (!selectPeriodo) {
            return;
        }

        if (esRifas) {
            selectPeriodo.value = 'sin_limite';
            selectPeriodo.disabled = true;
            if (hintPeriodo) {
                hintPeriodo.textContent = 'Los planes por rifas no caducan.';
            }
            if (hintPrecio) {
                hintPrecio.textContent = 'Precio base de referencia. El precio real se define por numeración.';
            }
        } else {
            selectPeriodo.disabled = false;
            if (selectPeriodo.value === 'sin_limite') {
                selectPeriodo.value = 'mensual';
            }
            if (hintPeriodo) {
                hintPeriodo.textContent = 'Los planes ilimitados tienen vigencia mensual o anual.';
            }
            if (hintPrecio) {
                hintPrecio.textContent = 'Precio del plan ilimitado.';
            }
        }
    }

    function actualizar_estado_vacio_opciones() {
        if (!contenedorOpciones || !estadoVacioOpciones) {
            return;
        }

        const filas = contenedorOpciones.querySelectorAll('.plan-opcion-row');
        estadoVacioOpciones.classList.toggle('d-none', filas.length > 0);
    }

    function poblar_select_numeracion(selectEl, numId, opcionData) {
        if (!selectEl) {
            return;
        }

        selectEl.innerHTML = '<option value="">Selecciona numeración...</option>';

        const items = catalogoNumeraciones || [];
        const idsAgregados = {};

        items.forEach(function (item) {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.text;
            selectEl.appendChild(option);
            idsAgregados[String(item.id)] = true;
        });

        const idBuscado = String(numId || '');
        if (idBuscado !== '' && !idsAgregados[idBuscado]) {
            const option = document.createElement('option');
            option.value = idBuscado;
            const etiqueta = (opcionData && opcionData.num_label)
                ? opcionData.num_label
                : ('Numeración #' + idBuscado);
            option.textContent = etiqueta + ' (inactiva)';
            selectEl.appendChild(option);
        }

        selectEl.value = idBuscado;
    }

    function crear_fila_opcion(opcion) {
        if (!contenedorOpciones) {
            return;
        }

        const data = (opcion && typeof opcion === 'object') ? opcion : {};
        const numId = (data.num_id !== undefined && data.num_id !== null) ? String(data.num_id) : '';
        const precio = (data.pnum_precio !== undefined && data.pnum_precio !== null)
            ? parseFloat(data.pnum_precio)
            : 0;
        const activo = (data.pnum_status === undefined || data.pnum_status === null)
            ? true
            : (parseInt(data.pnum_status, 10) === 1);

        const fila = document.createElement('div');
        fila.className = 'row g-3 align-items-end plan-opcion-row border-bottom border-gray-200 pb-3';

        fila.innerHTML = ''
            .concat('<div class="col-md-5">')
            .concat('    <label class="form-label fw-semibold fs-8 text-gray-700">Numeración</label>')
            .concat('    <select class="form-select form-select-solid plan-opcion-num" aria-label="Numeración"></select>')
            .concat('</div>')
            .concat('<div class="col-md-3">')
            .concat('    <label class="form-label fw-semibold fs-8 text-gray-700">Precio por rifa</label>')
            .concat('    <input type="number" class="form-control form-control-solid plan-opcion-precio" min="0" step="0.01" placeholder="0.00" aria-label="Precio por rifa" />')
            .concat('</div>')
            .concat('<div class="col-md-2">')
            .concat('    <label class="form-label fw-semibold fs-8 text-gray-700 d-block">Estado</label>')
            .concat('    <div class="form-check form-switch form-check-custom form-check-solid mt-2">')
            .concat('        <input class="form-check-input plan-opcion-status" type="checkbox" value="1" aria-label="Estado de la numeración" />')
            .concat('    </div>')
            .concat('</div>')
            .concat('<div class="col-md-2 text-end">')
            .concat('    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-eliminar-opcion shadow-xs" data-bs-toggle="tooltip" data-bs-placement="top" title="Quitar numeración" aria-label="Quitar numeración">')
            .concat('        <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>')
            .concat('    </button>')
            .concat('</div>');

        const select = fila.querySelector('.plan-opcion-num');
        const inputPrecio = fila.querySelector('.plan-opcion-precio');
        const inputStatus = fila.querySelector('.plan-opcion-status');

        poblar_select_numeracion(select, numId, data);

        if (inputPrecio) {
            inputPrecio.value = isFinite(precio) ? precio.toFixed(2) : '0.00';
        }

        if (inputStatus) {
            inputStatus.checked = activo;
        }

        contenedorOpciones.appendChild(fila);
        init_select2_local(select, 'Selecciona numeración...', modalPlanEl);
        init_tooltips();
        actualizar_estado_vacio_opciones();
    }

    function limpiar_opciones_plan() {
        if (!contenedorOpciones) {
            return;
        }

        contenedorOpciones.querySelectorAll('.plan-opcion-num').forEach(function (select) {
            destruir_select2(select);
        });
        contenedorOpciones.innerHTML = '';
        actualizar_estado_vacio_opciones();
    }

    function render_opciones_plan(opciones) {
        limpiar_opciones_plan();

        const items = Array.isArray(opciones) ? opciones : [];
        items.forEach(function (opcion) {
            crear_fila_opcion(opcion);
        });

        actualizar_estado_vacio_opciones();
    }

    function recoger_opciones_plan() {
        const resultado = {
            opciones: [],
            errores: [],
            activas: 0
        };

        if (!contenedorOpciones) {
            resultado.errores.push('No se pudo inicializar la sección de numeraciones.');
            return resultado;
        }

        const filas = contenedorOpciones.querySelectorAll('.plan-opcion-row');
        const vistos = {};

        filas.forEach(function (fila, indice) {
            const select = fila.querySelector('.plan-opcion-num');
            const inputPrecio = fila.querySelector('.plan-opcion-precio');
            const inputStatus = fila.querySelector('.plan-opcion-status');

            if (!select) {
                return;
            }

            const numId = parseInt(select.value, 10) || 0;
            if (numId <= 0) {
                resultado.errores.push('Selecciona una numeración en todas las filas.');
                return;
            }

            if (vistos[numId]) {
                resultado.errores.push('No puedes repetir la misma numeración en el plan.');
                return;
            }
            vistos[numId] = true;

            const precio = parseFloat(inputPrecio ? inputPrecio.value : '');
            if (!isFinite(precio) || precio < 0) {
                resultado.errores.push('El precio por rifa debe ser un número mayor o igual a 0.');
                return;
            }

            const status = (inputStatus && inputStatus.checked) ? 1 : 0;
            if (status === 1) {
                resultado.activas++;
            }

            resultado.opciones.push({
                num_id: numId,
                pnum_precio: precio,
                pnum_status: status,
                pnum_orden: indice
            });
        });

        if (filas.length === 0) {
            resultado.errores.push('Agrega al menos una numeración al plan.');
        } else if (resultado.activas < 1) {
            resultado.errores.push('El plan debe tener al menos una numeración activa.');
        }

        return resultado;
    }

    function limpiar_modal_plan() {
        formPlan.reset();
        document.getElementById('plan_id').value = '0';
        document.getElementById('plan_tipo').value = 'rifas';
        document.getElementById('plan_limite').value = '0';
        document.getElementById('plan_periodo').value = 'sin_limite';
        document.getElementById('plan_precio').value = '0.00';
        document.getElementById('plan_moneda').value = 'USD';
        document.getElementById('plan_status').checked = true;
        document.getElementById('modal_plan_rifa_titulo').textContent = 'Nuevo Plan';
        limpiar_opciones_plan();
        aplicar_tipo_plan('rifas');
        limpiar_errores(formPlan, alertPlan, listaPlan);
    }

    const selectTipoPlan = document.getElementById('plan_tipo');
    if (selectTipoPlan) {
        selectTipoPlan.addEventListener('change', function () {
            aplicar_tipo_plan(this.value);
        });
    }

    if (contenedorOpciones) {
        contenedorOpciones.addEventListener('click', function (event) {
            const btnEliminar = event.target.closest('.btn-eliminar-opcion');
            if (!btnEliminar) {
                return;
            }

            const fila = btnEliminar.closest('.plan-opcion-row');
            const select = fila ? fila.querySelector('.plan-opcion-num') : null;
            destruir_select2(select);
            if (fila && fila.parentNode) {
                fila.parentNode.removeChild(fila);
            }
            actualizar_estado_vacio_opciones();
        });
    }

    const btnAgregarOpcion = document.getElementById('btn_agregar_opcion_numeracion');
    if (btnAgregarOpcion) {
        btnAgregarOpcion.addEventListener('click', function () {
            cargar_catalogo_numeraciones().then(function () {
                crear_fila_opcion(null);
            });
        });
    }

    if (btnNuevoPlan) {
        btnNuevoPlan.addEventListener('click', function () {
            limpiar_modal_plan();
            cargar_catalogo_numeraciones().then(function () {
                if (contenedorOpciones && contenedorOpciones.querySelectorAll('.plan-opcion-row').length === 0) {
                    crear_fila_opcion(null);
                }
                modalPlan.show();
            });
        });
    }

    if (btnGuardarPlan) {
        btnGuardarPlan.addEventListener('click', function () {
            const id = document.getElementById('plan_id').value;
            const esNuevo = (id === '0' || id === '');
            const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + encodeURIComponent(id);
            const tipo = document.getElementById('plan_tipo').value;
            const nombre = document.getElementById('plan_nombre').value.trim();

            limpiar_errores(formPlan, alertPlan, listaPlan);

            if (nombre.length < 3) {
                mostrar_errores(formPlan, alertPlan, listaPlan, {
                    plan_nombre: 'El nombre del plan debe tener al menos 3 caracteres.'
                });
                return;
            }

            let opcionesPayload = [];
            if (tipo === 'rifas') {
                const revision = recoger_opciones_plan();
                if (revision.errores.length > 0) {
                    mostrar_errores(formPlan, alertPlan, listaPlan, {
                        numeraciones: revision.errores[0]
                    });
                    return;
                }
                opcionesPayload = revision.opciones;
            }

            if (inputNumeracionesJson) {
                inputNumeracionesJson.value = (tipo === 'rifas') ? JSON.stringify(opcionesPayload) : '';
            }

            const fd = new FormData(formPlan);
            fd.set('plan_nombre', nombre);
            fd.set('plan_status', document.getElementById('plan_status').checked ? '1' : '0');
            fd.set('plan_limite', '0');

            const rawPrecio = document.getElementById('plan_precio').value;
            const precio = parseFloat(rawPrecio);

            if (!isFinite(precio) || precio < 0) {
                mostrar_errores(formPlan, alertPlan, listaPlan, {
                    plan_precio: 'El precio debe ser un número válido mayor o igual a 0.'
                });
                return;
            }

            fd.set('plan_precio', precio.toFixed(2));

            if (tipo === 'rifas') {
                fd.set('plan_periodo', 'sin_limite');
                fd.set('numeraciones', JSON.stringify(opcionesPayload));
            } else {
                fd.set('plan_periodo', document.getElementById('plan_periodo').value || 'mensual');
                fd.set('numeraciones', '');
            }

            set_loading(btnGuardarPlan, true);

            request_json(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (res) {
                set_loading(btnGuardarPlan, false);

                if (res.status === 401) {
                    redirigir_login();
                    return;
                }

                if (res.data && res.data.ok) {
                    modalPlan.hide();
                    mostrar_alerta('success', res.data.msg || 'Plan guardado correctamente.');
                    if (dtPlanes) {
                        dtPlanes.ajax.reload(null, false);
                    }
                    return;
                }

                if (res.status === 422 && res.data && res.data.errores) {
                    mostrar_errores(formPlan, alertPlan, listaPlan, res.data.errores);
                    return;
                }

                mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo guardar el plan.');
            })
            .catch(function () {
                set_loading(btnGuardarPlan, false);
                mostrar_alerta('error', 'Error de conexión con el servidor.');
            });
        });
    }

    // ─────────────────────────────────────────────────────
    // Modal de asignación por lote
    // ─────────────────────────────────────────────────────
    function preparar_selects_asignacion() {
        const urlOrganizadores = BASE_URL + 'organizadores_select';
        const urlPlanes = BASE_URL + 'planes_select';

        if (tieneSelect2) {
            init_select2_ajax('asig_org_id', urlOrganizadores, 'Busca un organizador...', modalAsignarEl);
            init_select2_ajax('asig_plan_id', urlPlanes, 'Selecciona un plan...', modalAsignarEl);
            return;
        }

        cargar_select_nativo('asig_org_id', urlOrganizadores, 'Busca un organizador...');
        cargar_select_nativo('asig_plan_id', urlPlanes, 'Selecciona un plan...');
    }

    function valor_select(selectId) {
        if (jq) {
            const valor = jq('#' + selectId).val();
            return (valor === null || valor === undefined) ? '' : String(valor);
        }

        const select = document.getElementById(selectId);
        return select ? String(select.value || '') : '';
    }

    function reset_plan_asignacion_ui() {
        asignacionPlanActual = null;

        const wrapRifas = document.getElementById('wrap_asig_rifas');
        const wrapIlimitado = document.getElementById('wrap_asig_ilimitado');
        const selectNum = document.getElementById('asig_num_id');
        const inputCantidad = document.getElementById('asig_cantidad');
        const inputPrecio = document.getElementById('asig_precio_unitario');
        const inputTotal = document.getElementById('asig_total');
        const precioIlimitado = document.getElementById('asig_ilimitado_precio');
        const periodoIlimitado = document.getElementById('asig_ilimitado_periodo');

        if (wrapRifas) {
            wrapRifas.classList.add('d-none');
        }
        if (wrapIlimitado) {
            wrapIlimitado.classList.add('d-none');
        }

        destruir_select2(selectNum);
        if (selectNum) {
            selectNum.innerHTML = '<option value="">Selecciona una numeración...</option>';
        }

        if (inputCantidad) {
            inputCantidad.value = '1';
        }
        if (inputPrecio) {
            inputPrecio.value = '—';
        }
        if (inputTotal) {
            inputTotal.value = '—';
        }
        if (precioIlimitado) {
            precioIlimitado.textContent = '—';
        }
        if (periodoIlimitado) {
            periodoIlimitado.textContent = '—';
        }
    }

    function etiqueta_periodo(periodo) {
        switch (String(periodo || '')) {
            case 'mensual':
                return 'Mensual';
            case 'anual':
                return 'Anual';
            default:
                return 'Sin límite';
        }
    }

    function poblar_numeraciones_asignacion(opciones) {
        const selectNum = document.getElementById('asig_num_id');
        if (!selectNum) {
            return;
        }

        destruir_select2(selectNum);
        selectNum.innerHTML = '<option value="">Selecciona una numeración...</option>';

        const items = Array.isArray(opciones) ? opciones : [];

        items.forEach(function (item) {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.text;
            option.dataset.precio = String(item.precio !== undefined ? item.precio : 0);
            option.dataset.cantidad = String(item.cantidad !== undefined ? item.cantidad : 0);
            selectNum.appendChild(option);
        });

        if (items.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Este plan no tiene numeraciones activas';
            option.disabled = true;
            selectNum.appendChild(option);
        }

        selectNum.value = '';
        init_select2_local(selectNum, 'Selecciona una numeración...', modalAsignarEl);
    }

    function actualizar_precio_asignacion() {
        const inputPrecio = document.getElementById('asig_precio_unitario');
        const inputTotal = document.getElementById('asig_total');
        const inputCantidad = document.getElementById('asig_cantidad');
        const selectNum = document.getElementById('asig_num_id');

        if (!asignacionPlanActual || asignacionPlanActual.tipo !== 'rifas') {
            if (inputPrecio) {
                inputPrecio.value = '—';
            }
            if (inputTotal) {
                inputTotal.value = '—';
            }
            return;
        }

        let precio = 0;
        let moneda = asignacionPlanActual.moneda || 'USD';

        if (selectNum) {
            const opcion = selectNum.options[selectNum.selectedIndex];
            precio = opcion ? parseFloat(opcion.dataset.precio || '0') : 0;
        }

        if (!isFinite(precio) || precio < 0) {
            precio = 0;
        }

        const cantidad = inputCantidad ? (parseInt(inputCantidad.value, 10) || 0) : 0;
        const total = precio * Math.max(0, cantidad);

        if (inputPrecio) {
            inputPrecio.value = formato_moneda(precio) + ' ' + moneda;
        }

        if (inputTotal) {
            inputTotal.value = formato_moneda(total) + ' ' + moneda;
        }
    }

    function manejar_cambio_plan_asignacion() {
        const planId = valor_select('asig_plan_id');
        const token = ++asignacionPlanToken;

        reset_plan_asignacion_ui();

        if (planId === '') {
            return;
        }

        Promise.all([
            request_json(BASE_URL + 'datos/' + encodeURIComponent(planId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }),
            request_json(BASE_URL + 'plan_opciones/' + encodeURIComponent(planId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
        ])
        .then(function (resultados) {
            if (token !== asignacionPlanToken) {
                return;
            }

            const resPlan = resultados[0];
            const resOpciones = resultados[1];

            if (resPlan.status === 401) {
                redirigir_login();
                return;
            }

            if (!resPlan.data || resPlan.data.ok !== true || !resPlan.data.data) {
                mostrar_alerta('error', (resPlan.data && resPlan.data.msg) || 'No se pudieron cargar los datos del plan.');
                return;
            }

            const plan = resPlan.data.data;
            const tipo = String(plan.plan_tipo || 'rifas');
            const moneda = String(plan.plan_moneda || 'USD');

            asignacionPlanActual = {
                tipo: tipo,
                plan: plan,
                moneda: moneda,
                opciones: []
            };

            const wrapRifas = document.getElementById('wrap_asig_rifas');
            const wrapIlimitado = document.getElementById('wrap_asig_ilimitado');
            const precioIlimitado = document.getElementById('asig_ilimitado_precio');
            const periodoIlimitado = document.getElementById('asig_ilimitado_periodo');

            if (tipo === 'ilimitado') {
                if (wrapRifas) {
                    wrapRifas.classList.add('d-none');
                }
                if (wrapIlimitado) {
                    wrapIlimitado.classList.remove('d-none');
                }
                if (precioIlimitado) {
                    precioIlimitado.textContent = formato_moneda(plan.plan_precio) + ' ' + moneda;
                }
                if (periodoIlimitado) {
                    periodoIlimitado.textContent = etiqueta_periodo(plan.plan_periodo);
                }
                return;
            }

            if (wrapRifas) {
                wrapRifas.classList.remove('d-none');
            }
            if (wrapIlimitado) {
                wrapIlimitado.classList.add('d-none');
            }

            let opciones = [];
            if (resOpciones.data && Array.isArray(resOpciones.data.results)) {
                opciones = resOpciones.data.results;
            } else if (resOpciones.data && Array.isArray(resOpciones.data.data)) {
                opciones = resOpciones.data.data.map(function (item) {
                    return {
                        id: item.num_id,
                        text: (item.num_label || '') + ' (' + formato_moneda(item.pnum_precio) + ' por rifa)',
                        precio: parseFloat(item.pnum_precio) || 0,
                        cantidad: parseInt(item.num_cantidad, 10) || 0
                    };
                });
            }

            if (resOpciones.status === 401) {
                redirigir_login();
                return;
            }

            asignacionPlanActual.opciones = opciones;
            poblar_numeraciones_asignacion(opciones);
            actualizar_precio_asignacion();
        })
        .catch(function () {
            mostrar_alerta('error', 'Error de conexión al consultar el plan.');
        });
    }

    const selectPlanAsignacion = document.getElementById('asig_plan_id');
    if (selectPlanAsignacion) {
        selectPlanAsignacion.addEventListener('change', manejar_cambio_plan_asignacion);
    }

    const selectNumAsignacion = document.getElementById('asig_num_id');
    if (selectNumAsignacion) {
        selectNumAsignacion.addEventListener('change', actualizar_precio_asignacion);
    }

    const inputCantidadAsignacion = document.getElementById('asig_cantidad');
    if (inputCantidadAsignacion) {
        inputCantidadAsignacion.addEventListener('input', actualizar_precio_asignacion);
    }

    function limpiar_modal_asignar() {
        formAsignar.reset();

        if (tieneSelect2) {
            jq('#asig_org_id').val(null).trigger('change');
            jq('#asig_plan_id').val(null).trigger('change');
        } else {
            document.getElementById('asig_org_id').value = '';
            document.getElementById('asig_plan_id').value = '';
        }

        reset_plan_asignacion_ui();
        limpiar_errores(formAsignar, alertAsignar, listaAsignar);
    }

    if (btnAsignar) {
        btnAsignar.addEventListener('click', function () {
            limpiar_modal_asignar();
            modalAsignar.show();
        });
    }

    // Inicializar Select2 con el modal ya visible para calcular el ancho correcto.
    modalAsignarEl.addEventListener('shown.bs.modal', function () {
        preparar_selects_asignacion();

        if (tieneSelect2) {
            jq('#asig_org_id, #asig_plan_id').each(function () {
                const $contenedor = jq(this).next('.select2-container');
                if ($contenedor.length) {
                    $contenedor.css('width', '100%');
                }
            });
        }
    });

    function enviar_asignacion() {
        const orgId = valor_select('asig_org_id');
        const planId = valor_select('asig_plan_id');
        const notas = document.getElementById('asig_notas').value.trim();

        limpiar_errores(formAsignar, alertAsignar, listaAsignar);

        if (orgId === '') {
            mostrar_errores(formAsignar, alertAsignar, listaAsignar, {
                org_id: 'Debes seleccionar un organizador.'
            });
            return;
        }

        if (planId === '') {
            mostrar_errores(formAsignar, alertAsignar, listaAsignar, {
                plan_id: 'Debes seleccionar un plan.'
            });
            return;
        }

        if (notas.length > 255) {
            mostrar_errores(formAsignar, alertAsignar, listaAsignar, {
                notas: 'Las notas no pueden exceder los 255 caracteres.'
            });
            return;
        }

        const fd = new FormData();
        fd.set('org_id', orgId);
        fd.set('plan_id', planId);
        fd.set('notas', notas);

        if (asignacionPlanActual && asignacionPlanActual.tipo === 'rifas') {
            const numId = valor_select('asig_num_id');
            const cantidad = parseInt(document.getElementById('asig_cantidad').value, 10) || 0;

            if (numId === '') {
                mostrar_errores(formAsignar, alertAsignar, listaAsignar, {
                    num_id: 'Debes seleccionar una numeración para el lote.'
                });
                return;
            }

            if (cantidad < 1) {
                mostrar_errores(formAsignar, alertAsignar, listaAsignar, {
                    cantidad: 'La cantidad de rifas debe ser mayor que cero.'
                });
                return;
            }

            fd.set('num_id', numId);
            fd.set('cantidad', String(cantidad));
        }

        set_loading(btnGuardarAsignacion, true);

        request_json(BASE_URL + 'asignar', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) {
            set_loading(btnGuardarAsignacion, false);

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.data && res.data.ok) {
                modalAsignar.hide();
                mostrar_alerta('success', res.data.msg || 'Lote de plan asignado correctamente.');
                if (dtAsignaciones) {
                    dtAsignaciones.ajax.reload(null, false);
                }
                if (dtPlanes) {
                    dtPlanes.ajax.reload(null, false);
                }
                return;
            }

            if (res.status === 422 && res.data && res.data.errores) {
                mostrar_errores(formAsignar, alertAsignar, listaAsignar, res.data.errores);
                return;
            }

            mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo asignar el lote.');
        })
        .catch(function () {
            set_loading(btnGuardarAsignacion, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    if (btnGuardarAsignacion) {
        btnGuardarAsignacion.addEventListener('click', function () {
            enviar_asignacion();
        });
    }

    // ─────────────────────────────────────────────────────
    // Modal de numeraciones
    // ─────────────────────────────────────────────────────
    function aplicar_preview_numeracion() {
        const desdeInput = document.getElementById('num_desde');
        const hastaInput = document.getElementById('num_hasta');
        const paddingInput = document.getElementById('num_padding');
        const cantidadInput = document.getElementById('num_cantidad');
        const cantidadDisplay = document.getElementById('num_cantidad_display');
        const preview = document.getElementById('num_preview');

        if (!desdeInput || !hastaInput || !paddingInput) {
            return;
        }

        const desde = parseInt(desdeInput.value, 10);
        const hasta = parseInt(hastaInput.value, 10);
        const padding = Math.max(1, Math.min(6, parseInt(paddingInput.value, 10) || 1));

        let cantidad = 0;
        if (isFinite(desde) && isFinite(hasta) && hasta > desde) {
            cantidad = hasta - desde + 1;
        }

        if (cantidadInput) {
            cantidadInput.value = cantidad > 0 ? String(cantidad) : '';
        }

        if (cantidadDisplay) {
            cantidadDisplay.value = cantidad > 0 ? String(cantidad) : '';
        }

        if (preview) {
            if (cantidad > 0) {
                preview.textContent = pad_numero(desde, padding) + ' – ' + pad_numero(hasta, padding);
            } else {
                preview.textContent = 'Rango inválido';
            }
        }
    }

    ['num_desde', 'num_hasta', 'num_padding'].forEach(function (id) {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', aplicar_preview_numeracion);
        }
    });

    function limpiar_modal_numeracion() {
        formNumeracion.reset();
        document.getElementById('num_id').value = '0';
        document.getElementById('num_orden').value = '0';
        document.getElementById('num_desde').value = '0';
        document.getElementById('num_hasta').value = '99';
        document.getElementById('num_padding').value = '2';
        document.getElementById('num_status').checked = true;
        document.getElementById('modal_numeracion_titulo').textContent = 'Nueva Numeración';
        limpiar_errores(formNumeracion, alertNumeracion, listaNumeracion);
        aplicar_preview_numeracion();
    }

    if (btnNuevaNumeracion) {
        btnNuevaNumeracion.addEventListener('click', function () {
            limpiar_modal_numeracion();
            modalNumeracion.show();
        });
    }

    if (btnGuardarNumeracion) {
        btnGuardarNumeracion.addEventListener('click', function () {
            const id = document.getElementById('num_id').value;
            const esNuevo = (id === '0' || id === '');
            const url = esNuevo ? BASE_URL + 'numeracion_crear' : BASE_URL + 'numeracion_actualizar/' + encodeURIComponent(id);
            const label = document.getElementById('num_label').value.trim();

            limpiar_errores(formNumeracion, alertNumeracion, listaNumeracion);

            if (label.length < 3) {
                mostrar_errores(formNumeracion, alertNumeracion, listaNumeracion, {
                    num_label: 'La etiqueta debe tener al menos 3 caracteres.'
                });
                return;
            }

            const desde = parseInt(document.getElementById('num_desde').value, 10);
            const hasta = parseInt(document.getElementById('num_hasta').value, 10);
            const padding = parseInt(document.getElementById('num_padding').value, 10);

            const errores = {};
            if (!isFinite(desde) || desde < 0) {
                errores.num_desde = 'El número inicial no puede ser negativo.';
            }
            if (!isFinite(hasta) || hasta <= desde) {
                errores.num_hasta = 'El número final debe ser mayor que el inicial.';
            }
            if (!isFinite(padding) || padding < 1 || padding > 6) {
                errores.num_padding = 'El padding debe estar entre 1 y 6.';
            }

            if (Object.keys(errores).length > 0) {
                mostrar_errores(formNumeracion, alertNumeracion, listaNumeracion, errores);
                return;
            }

            const cantidad = hasta - desde + 1;
            document.getElementById('num_cantidad').value = String(cantidad);

            const fd = new FormData(formNumeracion);
            fd.set('num_label', label);
            fd.set('num_cantidad', String(cantidad));
            fd.set('num_desde', String(desde));
            fd.set('num_hasta', String(hasta));
            fd.set('num_padding', String(padding));
            fd.set('num_status', document.getElementById('num_status').checked ? '1' : '0');
            fd.set('num_orden', document.getElementById('num_orden').value || '0');

            set_loading(btnGuardarNumeracion, true);

            request_json(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (res) {
                set_loading(btnGuardarNumeracion, false);

                if (res.status === 401) {
                    redirigir_login();
                    return;
                }

                if (res.data && res.data.ok) {
                    modalNumeracion.hide();
                    invalidar_catalogo_numeraciones();
                    mostrar_alerta('success', res.data.msg || 'Numeración guardada correctamente.');
                    if (dtNumeraciones) {
                        dtNumeraciones.ajax.reload(null, false);
                    }
                    return;
                }

                if (res.status === 422 && res.data && res.data.errores) {
                    mostrar_errores(formNumeracion, alertNumeracion, listaNumeracion, res.data.errores);
                    return;
                }

                mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo guardar la numeración.');
            })
            .catch(function () {
                set_loading(btnGuardarNumeracion, false);
                mostrar_alerta('error', 'Error de conexión con el servidor.');
            });
        });
    }

    // ─────────────────────────────────────────────────────
    // Detalle de asignación + consumos
    // ─────────────────────────────────────────────────────
    function badge_estado_asignacion(estado) {
        const mapa = {
            activa: ['success', 'Activa'],
            vencida: ['warning', 'Vencida'],
            reemplazada: ['secondary', 'Reemplazada'],
            cancelada: ['danger', 'Cancelada']
        };
        const item = mapa[estado] || ['secondary', (estado || 'Desconocido')];

        return '<span class="badge badge-light-' + item[0] + ' fw-bold px-3 py-2">' + escapeHtml(item[1]) + '</span>';
    }

    function limpiar_detalle() {
        const ids = [
            'detalle_org', 'detalle_org_slug', 'detalle_plan', 'detalle_plan_tipo',
            'detalle_numeracion', 'detalle_cantidad', 'detalle_usadas', 'detalle_restantes',
            'detalle_precio_unitario', 'detalle_total', 'detalle_vigencia', 'detalle_notas'
        ];

        ids.forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = '—';
            }
        });

        const estado = document.getElementById('detalle_estado');
        if (estado) {
            estado.innerHTML = '—';
        }

        const tbody = document.getElementById('tbody_consumos_asignacion');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-6">Cargando consumos...</td></tr>';
        }
    }

    function cargar_consumos(asigId) {
        const tbody = document.getElementById('tbody_consumos_asignacion');
        if (!tbody) {
            return;
        }

        request_json(BASE_URL + 'consumos/' + encodeURIComponent(asigId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (!res.data || res.data.ok !== true) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-6">No se pudieron cargar los consumos.</td></tr>';
                return;
            }

            const items = Array.isArray(res.data.data) ? res.data.data : [];

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-6">Sin consumos registrados.</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            items.forEach(function (item) {
                const referencia = (item.cons_rifa_id !== null && item.cons_rifa_id !== undefined && item.cons_rifa_id !== '')
                    ? ('#' + item.cons_rifa_id)
                    : '—';
                const estadoHtml = item.estado_html || escapeHtml(item.cons_estado || '');
                const motivo = (item.cons_motivo !== undefined && item.cons_motivo !== null && item.cons_motivo !== '')
                    ? String(item.cons_motivo)
                    : '—';
                const fecha = (item.fecha !== undefined && item.fecha !== null && item.fecha !== '')
                    ? String(item.fecha)
                    : '—';

                const tr = document.createElement('tr');
                tr.innerHTML = ''
                    .concat('<td class="fw-semibold text-gray-800">' + escapeHtml(referencia) + '</td>')
                    .concat('<td>' + estadoHtml + '</td>')
                    .concat('<td class="text-gray-700">' + motivo + '</td>')
                    .concat('<td class="text-muted">' + fecha + '</td>');
                tbody.appendChild(tr);
            });
        })
        .catch(function () {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-6">No se pudieron cargar los consumos.</td></tr>';
        });
    }

    function abrir_detalle_asignacion(asigId) {
        limpiar_detalle();
        modalDetalle.show();

        request_json(BASE_URL + 'asignacion_datos/' + encodeURIComponent(asigId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                modalDetalle.hide();
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cargar el detalle de la asignación.');
                return;
            }

            const d = res.data.data;
            const tipo = String(d.plan_tipo || 'rifas');
            const ilimitado = !!d.ilimitado || tipo === 'ilimitado';
            const moneda = String(d.plan_moneda || 'USD');
            const usadas = parseInt(d.asig_rifas_usadas, 10) || 0;
            const cantidad = ilimitado ? null : (parseInt(d.asig_cantidad, 10) || 0);
            const restantes = ilimitado ? null : (parseInt(d.restantes, 10) || 0);

            document.getElementById('detalle_org').textContent = d.org_nombre_publico || '—';
            document.getElementById('detalle_org_slug').textContent = d.org_slug || '—';
            document.getElementById('detalle_plan').textContent = d.plan_nombre || '—';
            document.getElementById('detalle_plan_tipo').textContent = ilimitado
                ? ('Ilimitado · ' + etiqueta_periodo(d.plan_periodo))
                : 'Por rifas · lote por cantidad';
            document.getElementById('detalle_numeracion').textContent = d.num_label || 'Libre por rifa';
            document.getElementById('detalle_cantidad').textContent = ilimitado ? 'Ilimitado' : String(cantidad);
            document.getElementById('detalle_usadas').textContent = String(usadas);
            document.getElementById('detalle_restantes').textContent = ilimitado ? 'Ilimitado' : String(restantes);
            document.getElementById('detalle_precio_unitario').textContent = formato_moneda(d.asig_precio_unitario) + ' ' + moneda;
            document.getElementById('detalle_total').textContent = formato_moneda(d.asig_total) + ' ' + moneda;

            const inicio = formatear_fecha(d.asig_inicio, false) || '—';
            const fin = formatear_fecha(d.asig_fin, false) || 'Sin caducidad';
            document.getElementById('detalle_vigencia').textContent = 'Inicio: ' + inicio + ' · Fin: ' + fin;

            document.getElementById('detalle_estado').innerHTML = badge_estado_asignacion(String(d.asig_estado || '').toLowerCase());
            document.getElementById('detalle_notas').textContent = d.asig_notas || 'Sin notas';
        })
        .catch(function () {
            modalDetalle.hide();
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });

        cargar_consumos(asigId);
    }

    // ─────────────────────────────────────────────────────
    // Delegación de acciones - Planes
    // ─────────────────────────────────────────────────────
    const tablaPlanes = document.getElementById('tabla_planes_rifa');
    if (tablaPlanes) {
        tablaPlanes.addEventListener('click', function (event) {
            // Editar
            const btnEditar = event.target.closest('.btn-editar, [data-action="editar"]');
            if (btnEditar) {
                const fila = fila_data(dtPlanes, btnEditar);
                const id = btnEditar.dataset.id || fila.plan_id || fila.id || '';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar el plan seleccionado.');
                    return;
                }

                limpiar_modal_plan();

                request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) {
                    if (res.status === 401) {
                        redirigir_login();
                        return;
                    }

                    if (!res.data || res.data.ok !== true || !res.data.data) {
                        mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron cargar los datos del plan.');
                        return;
                    }

                    const d = res.data.data;

                    cargar_catalogo_numeraciones().then(function () {
                        document.getElementById('plan_id').value = d.plan_id || id;
                        document.getElementById('plan_nombre').value = d.plan_nombre || '';
                        document.getElementById('plan_descripcion').value = d.plan_descripcion || '';
                        document.getElementById('plan_tipo').value = d.plan_tipo || 'rifas';
                        document.getElementById('plan_limite').value = (d.plan_limite !== undefined && d.plan_limite !== null) ? d.plan_limite : 0;
                        document.getElementById('plan_periodo').value = d.plan_periodo || 'sin_limite';
                        document.getElementById('plan_precio').value = parseFloat(d.plan_precio || 0).toFixed(2);
                        document.getElementById('plan_moneda').value = String(d.plan_moneda || 'USD').toUpperCase();
                        document.getElementById('plan_status').checked = parseInt(d.plan_status, 10) === 1;
                        document.getElementById('modal_plan_rifa_titulo').textContent = 'Editar Plan: ' + (d.plan_nombre || '');

                        aplicar_tipo_plan(d.plan_tipo || 'rifas');
                        render_opciones_plan(d.numeraciones);
                        modalPlan.show();
                    });
                })
                .catch(function () {
                    mostrar_alerta('error', 'Error al consultar el plan.');
                });
                return;
            }

            // Activar / desactivar
            const btnStatus = event.target.closest('.btn-status, [data-action="status"]');
            if (btnStatus) {
                const fila = fila_data(dtPlanes, btnStatus);
                const id = btnStatus.dataset.id || fila.plan_id || fila.id || '';
                const nombre = btnStatus.dataset.nombre || fila.plan_nombre || 'este plan';
                const statusActual = String(btnStatus.dataset.status !== undefined ? btnStatus.dataset.status : fila.plan_status) === '1';
                const accionTexto = statusActual ? 'desactivar' : 'activar';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar el plan seleccionado.');
                    return;
                }

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
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    request_json(BASE_URL + 'cambiar_status/' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (res) {
                        if (res.status === 401) {
                            redirigir_login();
                            return;
                        }

                        if (res.data && res.data.ok) {
                            mostrar_alerta('success', res.data.msg || 'Estado actualizado correctamente.');
                            if (dtPlanes) {
                                dtPlanes.ajax.reload(null, false);
                            }
                        } else {
                            mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo cambiar el estado.');
                        }
                    })
                    .catch(function () {
                        mostrar_alerta('error', 'Error de conexión con el servidor.');
                    });
                });
                return;
            }

            // Eliminar (borrado lógico)
            const btnEliminar = event.target.closest('.btn-eliminar, [data-action="eliminar"]');
            if (btnEliminar) {
                const fila = fila_data(dtPlanes, btnEliminar);
                const id = btnEliminar.dataset.id || fila.plan_id || fila.id || '';
                const nombre = btnEliminar.dataset.nombre || fila.plan_nombre || 'este plan';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar el plan seleccionado.');
                    return;
                }

                Swal.fire({
                    icon: 'warning',
                    title: '¿Eliminar plan?',
                    text: '¿Estás seguro de que deseas eliminar el plan "' + nombre + '"? Esta acción es un borrado lógico.',
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

                    request_json(BASE_URL + 'eliminar/' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (res) {
                        if (res.status === 401) {
                            redirigir_login();
                            return;
                        }

                        if (res.data && res.data.ok) {
                            mostrar_alerta('success', res.data.msg || 'Plan eliminado correctamente.');
                            if (dtPlanes) {
                                dtPlanes.ajax.reload(null, false);
                            }
                        } else {
                            mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo eliminar el plan.');
                        }
                    })
                    .catch(function () {
                        mostrar_alerta('error', 'Error de conexión con el servidor.');
                    });
                });
            }
        });
    }

    // ─────────────────────────────────────────────────────
    // Delegación de acciones - Asignaciones
    // ─────────────────────────────────────────────────────
    const tablaAsignaciones = document.getElementById('tabla_asignaciones_rifa');
    if (tablaAsignaciones) {
        tablaAsignaciones.addEventListener('click', function (event) {
            // Ver detalle / consumos (el backend emite .btn-consumos)
            const btnDetalle = event.target.closest('.btn-consumos, .btn-detalle, [data-action="consumos"], [data-action="detalle"]');
            if (btnDetalle) {
                const fila = fila_data(dtAsignaciones, btnDetalle);
                const id = btnDetalle.dataset.id || fila.asig_id || fila.id || '';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar la asignación seleccionada.');
                    return;
                }

                abrir_detalle_asignacion(id);
                return;
            }

            // Cancelar asignación (solo si el backend emitió el botón, es decir, activa)
            const btnCancelar = event.target.closest('.btn-cancelar, [data-action="cancelar"]');
            if (btnCancelar) {
                const fila = fila_data(dtAsignaciones, btnCancelar);
                const id = btnCancelar.dataset.id || fila.asig_id || fila.id || '';
                const nombre = btnCancelar.dataset.nombre || fila.org_nombre_publico || 'este organizador';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar la asignación seleccionada.');
                    return;
                }

                Swal.fire({
                    icon: 'warning',
                    title: '¿Cancelar lote?',
                    text: 'El lote del organizador "' + nombre + '" quedará cancelado. Esta acción no se puede deshacer.',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cancelar',
                    cancelButtonText: 'Volver',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-sm btn-danger',
                        cancelButton: 'btn btn-sm btn-light ms-2'
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    request_json(BASE_URL + 'cancelar_asignacion/' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (res) {
                        if (res.status === 401) {
                            redirigir_login();
                            return;
                        }

                        if (res.data && res.data.ok) {
                            mostrar_alerta('success', res.data.msg || 'Lote cancelado correctamente.');
                            if (dtAsignaciones) {
                                dtAsignaciones.ajax.reload(null, false);
                            }
                            if (dtPlanes) {
                                dtPlanes.ajax.reload(null, false);
                            }
                        } else {
                            mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo cancelar el lote.');
                        }
                    })
                    .catch(function () {
                        mostrar_alerta('error', 'Error de conexión con el servidor.');
                    });
                });
            }
        });
    }

    // ─────────────────────────────────────────────────────
    // Delegación de acciones - Numeraciones
    // ─────────────────────────────────────────────────────
    const tablaNumeraciones = document.getElementById('tabla_numeraciones');
    if (tablaNumeraciones) {
        tablaNumeraciones.addEventListener('click', function (event) {
            // Editar numeración
            const btnEditar = event.target.closest('.btn-editar-numeracion');
            if (btnEditar) {
                const fila = fila_data(dtNumeraciones, btnEditar);
                const id = btnEditar.dataset.id || fila.num_id || fila.id || '';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar la numeración seleccionada.');
                    return;
                }

                limpiar_modal_numeracion();

                request_json(BASE_URL + 'numeracion_datos/' + encodeURIComponent(id), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) {
                    if (res.status === 401) {
                        redirigir_login();
                        return;
                    }

                    if (!res.data || res.data.ok !== true || !res.data.data) {
                        mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron cargar los datos de la numeración.');
                        return;
                    }

                    const d = res.data.data;
                    document.getElementById('num_id').value = d.num_id || id;
                    document.getElementById('num_label').value = d.num_label || '';
                    document.getElementById('num_desde').value = (d.num_desde !== undefined && d.num_desde !== null) ? d.num_desde : 0;
                    document.getElementById('num_hasta').value = (d.num_hasta !== undefined && d.num_hasta !== null) ? d.num_hasta : 99;
                    document.getElementById('num_padding').value = (d.num_padding !== undefined && d.num_padding !== null) ? d.num_padding : 2;
                    document.getElementById('num_orden').value = (d.num_orden !== undefined && d.num_orden !== null) ? d.num_orden : 0;
                    document.getElementById('num_status').checked = parseInt(d.num_status, 10) === 1;
                    document.getElementById('modal_numeracion_titulo').textContent = 'Editar Numeración: ' + (d.num_label || '');

                    aplicar_preview_numeracion();
                    modalNumeracion.show();
                })
                .catch(function () {
                    mostrar_alerta('error', 'Error al consultar la numeración.');
                });
                return;
            }

            // Activar / desactivar numeración
            const btnStatus = event.target.closest('.btn-status-numeracion');
            if (btnStatus) {
                const fila = fila_data(dtNumeraciones, btnStatus);
                const id = btnStatus.dataset.id || fila.num_id || fila.id || '';
                const nombre = btnStatus.dataset.nombre || fila.num_label || 'esta numeración';
                const statusActual = String(btnStatus.dataset.status !== undefined ? btnStatus.dataset.status : fila.num_status) === '1';
                const accionTexto = statusActual ? 'desactivar' : 'activar';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar la numeración seleccionada.');
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: '¿Deseas ' + accionTexto + ' la numeración?',
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

                    request_json(BASE_URL + 'numeracion_status/' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (res) {
                        if (res.status === 401) {
                            redirigir_login();
                            return;
                        }

                        if (res.data && res.data.ok) {
                            invalidar_catalogo_numeraciones();
                            mostrar_alerta('success', res.data.msg || 'Estado actualizado correctamente.');
                            if (dtNumeraciones) {
                                dtNumeraciones.ajax.reload(null, false);
                            }
                        } else {
                            mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo cambiar el estado.');
                        }
                    })
                    .catch(function () {
                        mostrar_alerta('error', 'Error de conexión con el servidor.');
                    });
                });
                return;
            }

            // Desactivar / eliminar numeración
            const btnEliminar = event.target.closest('.btn-eliminar-numeracion');
            if (btnEliminar) {
                const fila = fila_data(dtNumeraciones, btnEliminar);
                const id = btnEliminar.dataset.id || fila.num_id || fila.id || '';
                const nombre = btnEliminar.dataset.nombre || fila.num_label || 'esta numeración';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar la numeración seleccionada.');
                    return;
                }

                Swal.fire({
                    icon: 'warning',
                    title: '¿Desactivar numeración?',
                    text: 'La numeración "' + nombre + '" dejará de estar disponible para nuevas opciones de plan. Si está en uso, el sistema lo impedirá.',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, desactivar',
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

                    request_json(BASE_URL + 'numeracion_eliminar/' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (res) {
                        if (res.status === 401) {
                            redirigir_login();
                            return;
                        }

                        if (res.data && res.data.ok) {
                            invalidar_catalogo_numeraciones();
                            mostrar_alerta('success', res.data.msg || 'Numeración desactivada correctamente.');
                            if (dtNumeraciones) {
                                dtNumeraciones.ajax.reload(null, false);
                            }
                        } else {
                            mostrar_alerta(res.status === 422 ? 'warning' : 'error', (res.data && res.data.msg) || 'No se pudo desactivar la numeración.');
                        }
                    })
                    .catch(function () {
                        mostrar_alerta('error', 'Error de conexión con el servidor.');
                    });
                });
            }
        });
    }
});
</script>
<!--end::JavaScript-->
