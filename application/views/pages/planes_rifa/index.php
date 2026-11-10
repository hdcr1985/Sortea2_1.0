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
 * - Asignaciones: asignaciones (POST DataTables), asignacion_datos/{id},
 *                 asignar, cancelar_asignacion/{id}, consumos/{asig_id},
 *                 organizadores_select, planes_select.
 *
 * Claves DataTables:
 * - Planes:       plan, tipo, vigencia, precio, asignaciones, estado, acciones.
 * - Asignaciones: organizador, plan, uso, vigencia, estado, acciones.
 *
 * Clases de acción emitidas por el backend:
 * - Planes:       .btn-editar, .btn-status, .btn-eliminar.
 * - Asignaciones: .btn-consumos (detalle + consumos), .btn-cancelar.
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
                            <span class="text-muted fs-7">Define cupos, vigencia y precio de cada plan.</span>
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
                                    <th scope="col" class="min-w-140px">Tipo / Cupo</th>
                                    <th scope="col" class="min-w-150px">Vigencia</th>
                                    <th scope="col" class="min-w-120px">Precio</th>
                                    <th scope="col" class="min-w-140px text-center">Asignaciones activas</th>
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
                            <span class="text-muted fs-7">Cupos asignados a cada organizador y su historial de uso.</span>
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
                            Asignar plan
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
                                    <th scope="col" class="min-w-180px">Plan</th>
                                    <th scope="col" class="min-w-140px">Uso</th>
                                    <th scope="col" class="min-w-170px">Vigencia</th>
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
                        <span class="text-muted fs-8">Configura el cupo y la vigencia del plan de rifas</span>
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
                                <option value="rifas" selected>Por rifas (cupo limitado)</option>
                                <option value="ilimitado">Ilimitado</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="plan_tipo"></div>
                        </div>
                        <!--end::Tipo-->

                        <!--begin::Cupo-->
                        <div class="col-md-6" id="wrap_plan_limite">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_limite">Cupo de rifas</label>
                            <div class="position-relative">
                                <input type="number"
                                    name="plan_limite"
                                    id="plan_limite"
                                    class="form-control form-control-solid pe-12"
                                    placeholder="10"
                                    min="1"
                                    max="10000"
                                    step="1"
                                    value="1"
                                    required />
                                <span class="position-absolute end-0 top-50 translate-middle-y me-4 text-muted fs-7">
                                    <i class="ki-duotone ki-diamond fs-5 text-primary"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div class="invalid-feedback" data-error-for="plan_limite"></div>
                            <div class="text-muted fs-8 mt-1">Entre 1 y 10.000 rifas.</div>
                        </div>
                        <!--end::Cupo-->

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

                        <!--begin::Precio-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_precio">Precio</label>
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
                        </div>
                        <!--end::Precio-->

                        <!--begin::Moneda-->
                        <div class="col-md-6">
                            <label class="form-label required fw-semibold fs-7 text-gray-800" for="plan_moneda">Moneda</label>
                            <select name="plan_moneda" id="plan_moneda" class="form-select form-select-solid" required>
                                <option value="USD" selected>USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="VES">VES (Bs.)</option>
                                <option value="COP">COP ($)</option>
                                <option value="MXN">MXN ($)</option>
                                <option value="USDT">USDT (₮)</option>
                                <option value="GBP">GBP (£)</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="plan_moneda"></div>
                        </div>
                        <!--end::Moneda-->

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

<!--begin::Modal Asignar plan-->
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
                        <h4 class="modal-title fw-bold text-gray-900">Asignar plan</h4>
                        <span class="text-muted fs-8">Vincula un plan de rifas a un organizador</span>
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

                        <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                        <!--begin::Reemplazar-->
                        <div class="col-12">
                            <div class="border border-gray-200 rounded-3 p-4 bg-light d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7">Reemplazar plan activo si existe</div>
                                    <div class="text-muted fs-8">Si el organizador ya tiene un plan, se marcará como reemplazado.</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="reemplazar" id="asig_reemplazar" value="1" />
                                </div>
                            </div>
                        </div>
                        <!--end::Reemplazar-->
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
                        Asignar plan
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
<!--end::Modal Asignar plan-->

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
                        <h4 class="modal-title fw-bold text-gray-900">Detalle de la asignación</h4>
                        <span class="text-muted fs-8">Información del plan y consumos registrados</span>
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
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Uso</div>
                            <div class="fw-bold text-gray-900 fs-6" id="detalle_uso">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Vigencia</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="detalle_vigencia">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
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
    const modalPlan = new bootstrap.Modal(modalPlanEl);
    const modalAsignar = new bootstrap.Modal(modalAsignarEl);
    const modalDetalle = new bootstrap.Modal(modalDetalleEl);

    const formPlan = document.getElementById('form_plan_rifa');
    const formAsignar = document.getElementById('form_asignar_plan');
    const btnGuardarPlan = document.getElementById('btn_guardar_plan_rifa');
    const btnGuardarAsignacion = document.getElementById('btn_guardar_asignacion');
    const btnNuevoPlan = document.getElementById('btn_nuevo_plan_rifa');
    const btnAsignar = document.getElementById('btn_asignar_plan');
    const inputFiltroPlanes = document.getElementById('filtro_planes_rifa');
    const inputFiltroAsignaciones = document.getElementById('filtro_asignaciones_rifa');
    const alertPlan = document.getElementById('alert_errores_plan_rifa');
    const listaPlan = document.getElementById('lista_errores_plan_rifa');
    const alertAsignar = document.getElementById('alert_errores_asignar_plan');
    const listaAsignar = document.getElementById('lista_errores_asignar_plan');

    let dtPlanes = null;
    let dtAsignaciones = null;
    let filtroPlanesTimer = null;
    let filtroAsignacionesTimer = null;

    // ─────────────────────────────────────────────────────
    // Utilidades
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
            const mensaje = errores[campo];
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
            emptyTable: 'No hay ' + entidad + ' registrados',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        };
    }

    /*
     * El backend emite la acción "Ver consumos" (.btn-consumos) que abre el
     * modal combinado de detalle + consumos. Para exponer también una acción
     * explícita de detalle, se antepone un botón .btn-detalle reutilizando el
     * mismo data-id. Ambas acciones abren el mismo modal informativo.
     */
    function render_acciones_asignacion(data, type) {
        if (type !== 'display' || typeof data !== 'string') {
            return data;
        }

        if (data.indexOf('btn-detalle') !== -1) {
            return data;
        }

        const coincidencia = data.match(/data-id="(\d+)"/);
        if (!coincidencia) {
            return data;
        }

        const botonDetalle = ''
            .concat('<button type="button" class="btn btn-sm btn-icon btn-light-primary btn-detalle shadow-xs" ')
            .concat('data-id="' + coincidencia[1] + '" data-bs-toggle="tooltip" data-bs-placement="top" ')
            .concat('title="Ver detalle" aria-label="Ver detalle">')
            .concat('<i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>')
            .concat('</button>');

        return data.replace(
            '<div class="d-flex justify-content-end align-items-center gap-2">',
            '<div class="d-flex justify-content-end align-items-center gap-2">' + botonDetalle
        );
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

    // ─────────────────────────────────────────────────────
    // DataTables server-side
    // ─────────────────────────────────────────────────────
    function init_tablas() {
        if (!tieneDataTables) {
            const avisoPlanes = document.getElementById('aviso_datatables_planes');
            const avisoAsignaciones = document.getElementById('aviso_datatables_asignaciones');
            if (avisoPlanes) {
                avisoPlanes.classList.remove('d-none');
            }
            if (avisoAsignaciones) {
                avisoAsignaciones.classList.remove('d-none');
            }
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
                { data: 'tipo', orderable: false, responsivePriority: 2 },
                { data: 'vigencia', orderable: false, responsivePriority: 4 },
                { data: 'precio', orderable: true, responsivePriority: 3 },
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
                { data: 'uso', orderable: false, responsivePriority: 3 },
                { data: 'vigencia', orderable: false, responsivePriority: 4 },
                { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1, render: render_acciones_asignacion }
            ],
            drawCallback: function () {
                init_tooltips();
            },
            language: idioma_es('asignaciones')
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

    // Ajustar columnas al mostrar una pestaña (DataTables en contenedor oculto)
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            [dtPlanes, dtAsignaciones].forEach(function (dt) {
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
    // Modal de plan (crear / editar)
    // ─────────────────────────────────────────────────────
    function aplicar_tipo_plan(tipo) {
        const esRifas = (tipo === 'rifas');
        const wrapLimite = document.getElementById('wrap_plan_limite');
        const inputLimite = document.getElementById('plan_limite');
        const selectPeriodo = document.getElementById('plan_periodo');
        const hintPeriodo = document.getElementById('hint_plan_periodo');
        const opcionSinLimite = selectPeriodo ? selectPeriodo.querySelector('option[value="sin_limite"]') : null;

        if (!wrapLimite || !inputLimite || !selectPeriodo) {
            return;
        }

        if (esRifas) {
            wrapLimite.classList.remove('d-none');
            inputLimite.required = true;
            if (!inputLimite.value || parseInt(inputLimite.value, 10) < 1) {
                inputLimite.value = '1';
            }
            if (opcionSinLimite) {
                opcionSinLimite.disabled = false;
            }
            selectPeriodo.value = 'sin_limite';
            selectPeriodo.disabled = true;
            if (hintPeriodo) {
                hintPeriodo.textContent = 'Los planes por rifas no caducan.';
            }
        } else {
            wrapLimite.classList.add('d-none');
            inputLimite.required = false;
            inputLimite.value = '0';
            selectPeriodo.disabled = false;
            if (opcionSinLimite) {
                opcionSinLimite.disabled = true;
            }
            if (selectPeriodo.value === 'sin_limite') {
                selectPeriodo.value = 'mensual';
            }
            if (hintPeriodo) {
                hintPeriodo.textContent = 'Los planes ilimitados tienen vigencia mensual o anual.';
            }
        }
    }

    function limpiar_modal_plan() {
        formPlan.reset();
        document.getElementById('plan_id').value = '0';
        document.getElementById('plan_tipo').value = 'rifas';
        document.getElementById('plan_limite').value = '1';
        document.getElementById('plan_periodo').value = 'sin_limite';
        document.getElementById('plan_precio').value = '0.00';
        document.getElementById('plan_moneda').value = 'USD';
        document.getElementById('plan_status').checked = true;
        document.getElementById('modal_plan_rifa_titulo').textContent = 'Nuevo Plan';
        aplicar_tipo_plan('rifas');
        limpiar_errores(formPlan, alertPlan, listaPlan);
    }

    const selectTipoPlan = document.getElementById('plan_tipo');
    if (selectTipoPlan) {
        selectTipoPlan.addEventListener('change', function () {
            aplicar_tipo_plan(this.value);
        });
    }

    if (btnNuevoPlan) {
        btnNuevoPlan.addEventListener('click', function () {
            limpiar_modal_plan();
            modalPlan.show();
        });
    }

    if (btnGuardarPlan) {
        btnGuardarPlan.addEventListener('click', function () {
            const id = document.getElementById('plan_id').value;
            const esNuevo = (id === '0' || id === '');
            const url = esNuevo ? BASE_URL + 'crear' : BASE_URL + 'actualizar/' + encodeURIComponent(id);
            const tipo = document.getElementById('plan_tipo').value;
            const nombre = document.getElementById('plan_nombre').value.trim();

            if (nombre.length < 3) {
                mostrar_errores(formPlan, alertPlan, listaPlan, {
                    plan_nombre: 'El nombre del plan debe tener al menos 3 caracteres.'
                });
                return;
            }

            const fd = new FormData(formPlan);
            fd.set('plan_nombre', nombre);
            fd.set('plan_status', document.getElementById('plan_status').checked ? '1' : '0');

            if (tipo === 'rifas') {
                fd.set('plan_limite', document.getElementById('plan_limite').value || '1');
                fd.set('plan_periodo', 'sin_limite');
            } else {
                fd.set('plan_limite', '0');
                fd.set('plan_periodo', document.getElementById('plan_periodo').value || 'mensual');
            }

            set_loading(btnGuardarPlan, true);
            limpiar_errores(formPlan, alertPlan, listaPlan);

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

                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo guardar el plan.');
            })
            .catch(function () {
                set_loading(btnGuardarPlan, false);
                mostrar_alerta('error', 'Error de conexión con el servidor.');
            });
        });
    }

    // ─────────────────────────────────────────────────────
    // Modal de asignación
    // ─────────────────────────────────────────────────────
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

    function init_select2(selectId, url, placeholder) {
        if (!tieneSelect2) {
            return;
        }

        const $select = jq('#' + selectId);
        if (!$select.length || $select.data('select2')) {
            return;
        }

        $select.select2({
            dropdownParent: jq('#modal_asignar_plan'),
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
        });
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

    function preparar_selects_asignacion() {
        const urlOrganizadores = BASE_URL + 'organizadores_select';
        const urlPlanes = BASE_URL + 'planes_select';

        if (tieneSelect2) {
            init_select2('asig_org_id', urlOrganizadores, 'Busca un organizador...');
            init_select2('asig_plan_id', urlPlanes, 'Selecciona un plan...');
            return;
        }

        cargar_select_nativo('asig_org_id', urlOrganizadores, 'Busca un organizador...');
        cargar_select_nativo('asig_plan_id', urlPlanes, 'Selecciona un plan...');
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

        document.getElementById('asig_reemplazar').checked = false;
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

    function valor_select(selectId) {
        if (jq) {
            const valor = jq('#' + selectId).val();
            return (valor === null || valor === undefined) ? '' : String(valor);
        }

        const select = document.getElementById(selectId);
        return select ? String(select.value || '') : '';
    }

    function enviar_asignacion(reemplazar) {
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

        const fd = new FormData();
        fd.set('org_id', orgId);
        fd.set('plan_id', planId);
        fd.set('notas', notas);
        fd.set('reemplazar', reemplazar ? '1' : '0');

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
                mostrar_alerta('success', res.data.msg || 'Plan asignado correctamente.');
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

            const codigo = (res.data && (res.data.code || res.data.motivo)) || '';
            if (res.status === 409 || codigo === 'ya_tiene_plan') {
                Swal.fire({
                    icon: 'warning',
                    title: 'El organizador ya tiene un plan activo',
                    text: (res.data && res.data.msg) || '¿Deseas reemplazar el plan activo por el seleccionado?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reemplazar',
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-sm btn-warning',
                        cancelButton: 'btn btn-sm btn-light ms-2'
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        enviar_asignacion(true);
                    }
                });
                return;
            }

            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo asignar el plan.');
        })
        .catch(function () {
            set_loading(btnGuardarAsignacion, false);
            mostrar_alerta('error', 'Error de conexión con el servidor.');
        });
    }

    if (btnGuardarAsignacion) {
        btnGuardarAsignacion.addEventListener('click', function () {
            enviar_asignacion(document.getElementById('asig_reemplazar').checked);
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
            'detalle_uso', 'detalle_vigencia', 'detalle_notas'
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
                // cons_motivo y fecha llegan ya escapados por el controlador.
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
            const ilimitado = (tipo === 'ilimitado');
            const limite = (d.plan_limite !== undefined && d.plan_limite !== null) ? parseInt(d.plan_limite, 10) : 0;
            const usadas = (d.asig_rifas_usadas !== undefined && d.asig_rifas_usadas !== null) ? parseInt(d.asig_rifas_usadas, 10) : 0;

            document.getElementById('detalle_org').textContent = d.org_nombre_publico || '—';
            document.getElementById('detalle_org_slug').textContent = d.org_slug || '—';
            document.getElementById('detalle_plan').textContent = d.plan_nombre || '—';
            document.getElementById('detalle_plan_tipo').textContent = ilimitado
                ? 'Ilimitado'
                : ('Por rifas · ' + limite + (limite === 1 ? ' rifa' : ' rifas'));
            document.getElementById('detalle_uso').textContent = ilimitado
                ? (usadas + ' usadas · Ilimitado')
                : (usadas + ' / ' + limite);

            const inicio = formatear_fecha(d.asig_inicio, false) || '—';
            const fin = formatear_fecha(d.asig_fin, false) || 'Sin límite';
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
                    modalPlan.show();
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
                            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cambiar el estado.');
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
                            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo eliminar el plan.');
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
                    title: '¿Cancelar asignación?',
                    text: 'El organizador "' + nombre + '" quedará sin este plan activo. Esta acción no se puede deshacer.',
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
                            mostrar_alerta('success', res.data.msg || 'Asignación cancelada correctamente.');
                            if (dtAsignaciones) {
                                dtAsignaciones.ajax.reload(null, false);
                            }
                            if (dtPlanes) {
                                dtPlanes.ajax.reload(null, false);
                            }
                        } else {
                            mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudo cancelar la asignación.');
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
