<?php
/**
 * M03 - Rifas: CRUD, numeración y estados.
 * Vista administrativa con moderación M09 de rifas gratuitas y cancelaciones.
 *
 * Contrato de datos provisto por el controlador Rifas:
 * - $url_base string Raíz del módulo (por defecto "rifas").
 *
 * Endpoints consumidos (todos bajo $url_base):
 * - tabla (POST DataTables), datos/{id}.
 * - aprobar-gratuita/{id}, rechazar-gratuita/{id}.
 * - aprobar-cancelacion/{id}, rechazar-cancelacion/{id}.
 *
 * Claves DataTables:
 * - organizador, rifa, tipo, numeracion, estado, fechas, acciones.
 *
 * Detalle M03.1:
 * - asig_plan_nombre, num_label, asig_cantidad y asig_rifas_usadas.
 *
 * Detalle M03.2:
 * - rifa_descripcion se renderiza como HTML sanitizado por el backend.
 * - imagenes (img_id, url, principal) se muestran como galería de solo
 *   lectura; la portada se destaca visualmente.
 *
 * Acciones:
 * - Ver detalle.
 * - M09: aprobar/rechazar rifas gratuitas y aprobar/rechazar cancelaciones.
 */

$url_base = (isset($url_base) && is_string($url_base) && trim($url_base) !== '')
    ? trim($url_base, '/')
    : 'rifas';

$numeraciones = (isset($numeraciones) && is_array($numeraciones)) ? $numeraciones : [];
?>

<style>
/* M03.2 - Detalle de rifa: HTML enriquecido y galería */
#det_descripcion img {
    max-width: 100%;
    height: auto;
    border-radius: 0.475rem;
}

#det_descripcion table {
    width: 100%;
    margin-bottom: 1rem;
}

#det_descripcion blockquote {
    border-left: 0.25rem solid var(--bs-border-color);
    padding-left: 1rem;
    color: var(--bs-secondary-color);
}

#det_galeria .ratio img {
    object-fit: cover;
}
</style>

<!--begin::Card Rifas-->
<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold text-gray-900 mb-1">Rifas</h3>
                <span class="text-muted fs-7">Consulta las rifas de todos los organizadores, aprueba gratuitas y resuelve cancelaciones.</span>
            </div>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text"
                    id="filtro_rifas"
                    class="form-control form-control-solid w-250px ps-12"
                    placeholder="Buscar rifas..."
                    aria-label="Buscar rifas" />
            </div>
            <button type="button"
                class="btn btn-sm btn-light-primary fw-semibold px-4 py-2"
                id="btn_recargar_rifas">
                <i class="ki-duotone ki-arrows-circle fs-4 me-1">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                Actualizar
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="alert alert-warning d-none mb-4" id="aviso_datatables_rifas">
            No se pudo cargar el componente de tablas. Recarga la página o contacta al administrador.
        </div>

        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_rifas">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th scope="col" class="min-w-200px">Organizador</th>
                        <th scope="col" class="min-w-260px">Rifa</th>
                        <th scope="col" class="min-w-140px">Tipo / Precio</th>
                        <th scope="col" class="min-w-160px">Numeración</th>
                        <th scope="col" class="min-w-130px text-center">Estado</th>
                        <th scope="col" class="min-w-170px">Fechas</th>
                        <th scope="col" class="min-w-110px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fw-semibold"></tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card Rifas-->

<!--begin::Modal Detalle de Rifa-->
<div class="modal fade" id="modal_detalle_rifa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-gift fs-3 text-primary">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Detalle de la rifa</h4>
                        <span class="text-muted fs-8">Información completa en modo solo lectura</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>

            <div class="modal-body px-8 py-6">
                <div class="row g-5">
                    <!--begin::Organizador-->
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Organizador</div>
                            <div class="fw-bold text-gray-900 fs-6" id="det_organizador">—</div>
                            <div class="text-muted fs-8 text-break" id="det_organizador_slug">—</div>
                        </div>
                    </div>
                    <!--end::Organizador-->

                    <!--begin::Rifa-->
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Rifa</div>
                            <div class="fw-bold text-gray-900 fs-6 text-break" id="det_titulo">—</div>
                            <div class="text-muted fs-8 text-break" id="det_slug">—</div>
                        </div>
                    </div>
                    <!--end::Rifa-->

                    <!--begin::Tipo / Precio-->
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Tipo / Precio</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_tipo_precio">—</div>
                        </div>
                    </div>
                    <!--end::Tipo / Precio-->

                    <!--begin::Numeración-->
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Numeración</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_numeracion">—</div>
                        </div>
                    </div>
                    <!--end::Numeración-->

                    <!--begin::Estado-->
                    <div class="col-md-4">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Estado</div>
                            <div id="det_estado">—</div>
                        </div>
                    </div>
                    <!--end::Estado-->

                    <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                    <!--begin::Plan y numeración del lote-->
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Plan / lote</div>
                            <div class="fw-semibold text-gray-900 fs-7 text-break" id="det_plan_lote">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Numeración del lote</div>
                            <div class="fw-semibold text-gray-900 fs-7 text-break" id="det_lote_numeracion">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Capacidad del lote</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_lote_cantidad">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Rifas usadas del lote</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_lote_usadas">—</div>
                        </div>
                    </div>
                    <!--end::Plan y numeración del lote-->

                    <!--begin::Total-->
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Total de números</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_total">—</div>
                        </div>
                    </div>
                    <!--end::Total-->

                    <!--begin::Máximo por compra-->
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Máximo por compra</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_max">—</div>
                        </div>
                    </div>
                    <!--end::Máximo por compra-->

                    <!--begin::TTL-->
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">TTL de reserva</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_ttl">—</div>
                        </div>
                    </div>
                    <!--end::TTL-->

                    <!--begin::Auto-expiración-->
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Auto-expiración</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_auto">—</div>
                        </div>
                    </div>
                    <!--end::Auto-expiración-->

                    <div class="col-12"><div class="separator separator-dashed my-1"></div></div>

                    <!--begin::Fechas-->
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Fecha de sorteo</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_fecha_sorteo">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Publicada</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_publicada">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Finalizada</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_finalizada">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Cancelada</div>
                            <div class="fw-semibold text-gray-900 fs-7" id="det_cancelada">—</div>
                        </div>
                    </div>
                    <!--end::Fechas-->

                    <!--begin::Motivos-->
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Motivo de aprobación / rechazo</div>
                            <div class="text-gray-700 fs-7 text-break" id="det_aprobacion">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Motivo de cancelación</div>
                            <div class="text-gray-700 fs-7 text-break" id="det_cancelacion">—</div>
                        </div>
                    </div>
                    <!--end::Motivos-->

                    <!--begin::Descripción y condiciones-->
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Descripción</div>
                            <div class="text-gray-700 fs-7 text-break rich-text" id="det_descripcion">—</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border border-gray-200 rounded-3 bg-light p-4 h-100">
                            <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Condiciones</div>
                            <div class="text-gray-700 fs-7 text-break" id="det_condiciones">—</div>
                        </div>
                    </div>
                    <!--end::Descripción y condiciones-->

                    <!--begin::Galería de imágenes (M03.2)-->
                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 p-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="text-muted fs-8 text-uppercase fw-bold">Galería de imágenes</div>
                                <span class="badge badge-light-primary fw-bold px-3 py-2" id="det_galeria_contador">0 / 3</span>
                            </div>
                            <div class="row g-3" id="det_galeria">
                                <div class="col-12 text-center text-muted fs-7" id="det_galeria_vacio">Sin imágenes</div>
                            </div>
                        </div>
                    </div>
                    <!--end::Galería de imágenes (M03.2)-->
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
<!--end::Modal Detalle de Rifa-->

<!--begin::Modal Imagen Rifa-->
<div class="modal fade" id="modal_imagen_rifa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-gray-900">Imagen de la rifa</h5>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body text-center p-6">
                <img id="imagen_rifa_ampliada" src="" alt="Imagen ampliada de la rifa"
                    class="img-fluid rounded" style="max-height:80vh;object-fit:contain;" />
            </div>
        </div>
    </div>
</div>
<!--end::Modal Imagen Rifa-->

<!--begin::Modal Aprobar Rifa Gratuita-->
<div class="modal fade" id="modal_aprobar_gratuita" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-success">
                        <i class="ki-duotone ki-check-circle fs-3 text-success"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Aprobar rifa gratuita</h4>
                        <span class="text-muted fs-8" id="aprobar_gratuita_titulo">-</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aprobar_gratuita_id" value="0" />
                <div class="mb-5">
                    <label class="form-label required fw-semibold fs-7 text-gray-800" for="aprobar_gratuita_num_id">Numeración</label>
                    <select id="aprobar_gratuita_num_id" class="form-select form-select-solid" required>
                        <option value="">Selecciona una numeración...</option>
                        <?php foreach ($numeraciones as $numeracion): ?>
                            <?php
                            $num_id    = (int)($numeracion['num_id'] ?? 0);
                            $num_label = (string)($numeracion['num_label'] ?? '');
                            ?>
                            <?php if ($num_id > 0 && (int)($numeracion['num_status'] ?? 0) === 1): ?>
                                <option value="<?php echo $num_id; ?>">
                                    <?php echo htmlspecialchars($num_label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback d-block" id="aprobar_gratuita_error"></div>
                    <div class="text-muted fs-8 mt-2">La numeración se asigna al aprobar y no puede cambiarse después de publicar.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-success fw-semibold" id="btn_confirmar_aprobar_gratuita">
                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Aprobar y publicar
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Aprobar Rifa Gratuita-->

<!--begin::Modal Rechazar Rifa Gratuita-->
<div class="modal fade" id="modal_rechazar_gratuita" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-danger">
                        <i class="ki-duotone ki-cross-circle fs-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Rechazar rifa gratuita</h4>
                        <span class="text-muted fs-8" id="rechazar_gratuita_titulo">-</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rechazar_gratuita_id" value="0" />
                <label class="form-label required fw-semibold fs-7 text-gray-800" for="rechazar_gratuita_motivo">Motivo del rechazo</label>
                <textarea id="rechazar_gratuita_motivo" class="form-control form-control-solid" rows="4" maxlength="255" placeholder="Explica al organizador por qué se rechaza la rifa."></textarea>
                <div class="invalid-feedback d-block" id="rechazar_gratuita_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-danger fw-semibold" id="btn_confirmar_rechazar_gratuita">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Rechazar rifa
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Rechazar Rifa Gratuita-->

<!--begin::Modal Aprobar Cancelación-->
<div class="modal fade" id="modal_aprobar_cancelacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-danger">
                        <i class="ki-duotone ki-cross-circle fs-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Aprobar cancelación</h4>
                        <span class="text-muted fs-8" id="aprobar_cancelacion_titulo">-</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aprobar_cancelacion_id" value="0" />
                <div class="alert alert-warning d-flex gap-2 mb-5">
                    <i class="ki-duotone ki-information-5 fs-2 text-warning"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div class="text-gray-700 fs-7">
                        La rifa pasará a <strong>cancelada</strong> y se devolverá el cupo consumido al plan del organizador.
                        Si tiene números vendidos u órdenes pagadas, el sistema bloqueará la cancelación hasta gestionar los reembolsos.
                    </div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Motivo indicado por el organizador</div>
                    <div class="fw-semibold text-gray-800 fs-7 text-break" id="aprobar_cancelacion_motivo_org">-</div>
                </div>
                <label class="form-label fw-semibold fs-7 text-gray-800" for="aprobar_cancelacion_motivo">Nota administrativa (opcional)</label>
                <textarea id="aprobar_cancelacion_motivo" class="form-control form-control-solid" rows="3" maxlength="255" placeholder="Nota interna para la bitácora."></textarea>
                <div class="invalid-feedback d-block" id="aprobar_cancelacion_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-danger fw-semibold" id="btn_confirmar_aprobar_cancelacion">
                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Aprobar cancelación
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Aprobar Cancelación-->

<!--begin::Modal Rechazar Cancelación-->
<div class="modal fade" id="modal_rechazar_cancelacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-warning">
                        <i class="ki-duotone ki-arrow-circle-left fs-3 text-warning"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900">Rechazar cancelación</h4>
                        <span class="text-muted fs-8" id="rechazar_cancelacion_titulo">-</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rechazar_cancelacion_id" value="0" />
                <div class="mb-4">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Motivo indicado por el organizador</div>
                    <div class="fw-semibold text-gray-800 fs-7 text-break" id="rechazar_cancelacion_motivo_org">-</div>
                </div>
                <label class="form-label required fw-semibold fs-7 text-gray-800" for="rechazar_cancelacion_motivo">Motivo del rechazo</label>
                <textarea id="rechazar_cancelacion_motivo" class="form-control form-control-solid" rows="4" maxlength="255" placeholder="Explica por qué la rifa continúa activa."></textarea>
                <div class="invalid-feedback d-block" id="rechazar_cancelacion_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-warning fw-semibold" id="btn_confirmar_rechazar_cancelacion">
                    <i class="ki-duotone ki-arrow-circle-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Mantener rifa activa
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Rechazar Cancelación-->

<!--begin::JavaScript-->
<script>
    // Ampliar miniatura de rifa en un modal
    document.addEventListener('click', function (event) {
        const thumb = event.target.closest('[data-rifa-thumb]');
        if (!thumb) {
            return;
        }

        const src = thumb.getAttribute('data-src') || thumb.getAttribute('src');
        const modalEl = document.getElementById('modal_imagen_rifa');
        const img = document.getElementById('imagen_rifa_ampliada');
        if (!src || !modalEl || !img) {
            return;
        }

        event.preventDefault();
        img.src = src;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = <?php echo json_encode(base_url($url_base . '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_EXPIRED = <?php echo json_encode(base_url('login?expired=1'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MEDIA_RIFA_BASE = <?php echo json_encode(base_url('media/rifa/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const jq = window.jQuery || null;
    const tieneDataTables = !!(jq && jq.fn && jq.fn.DataTable);

    const modalDetalleEl = document.getElementById('modal_detalle_rifa');
    const modalDetalle = new bootstrap.Modal(modalDetalleEl);
    const modalAprobarGratuita = new bootstrap.Modal(document.getElementById('modal_aprobar_gratuita'));
    const modalRechazarGratuita = new bootstrap.Modal(document.getElementById('modal_rechazar_gratuita'));
    const modalAprobarCancelacion = new bootstrap.Modal(document.getElementById('modal_aprobar_cancelacion'));
    const modalRechazarCancelacion = new bootstrap.Modal(document.getElementById('modal_rechazar_cancelacion'));

    const btnRecargar = document.getElementById('btn_recargar_rifas');
    const inputFiltro = document.getElementById('filtro_rifas');
    const detGaleriaEl = document.getElementById('det_galeria');
    const detGaleriaVacioEl = document.getElementById('det_galeria_vacio');
    const detGaleriaContadorEl = document.getElementById('det_galeria_contador');

    const ESTADOS_BADGE = {
        borrador: ['secondary', 'Borrador'],
        pendiente_aprobacion: ['warning', 'Pendiente de aprobación'],
        activa: ['success', 'Activa'],
        pausada: ['warning', 'Pausada'],
        cancelacion_solicitada: ['danger', 'Cancelación solicitada'],
        finalizada: ['primary', 'Finalizada'],
        cancelada: ['danger', 'Cancelada'],
        rechazada: ['danger', 'Rechazada']
    };

    let dtRifas = null;
    let filtroTimer = null;

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

    function primer_error(errores) {
        if (!errores || typeof errores !== 'object') {
            return '';
        }

        const claves = Object.keys(errores);
        for (let i = 0; i < claves.length; i++) {
            const valor = errores[claves[i]];
            if (Array.isArray(valor) && valor.length) {
                return String(valor[0]);
            }
            if (valor) {
                return String(valor);
            }
        }

        return '';
    }

    function campo(d, claves, porDefecto) {
        if (!d || typeof d !== 'object') {
            return porDefecto;
        }

        for (let i = 0; i < claves.length; i++) {
            const clave = claves[i];
            if (d[clave] !== undefined && d[clave] !== null && d[clave] !== '') {
                return d[clave];
            }
        }

        return porDefecto;
    }

    // ─────────────────────────────────────────────────────
    // Galería de imágenes (M03.2)
    // ─────────────────────────────────────────────────────
    function normalizar_imagenes(lista) {
        if (!Array.isArray(lista)) {
            return [];
        }

        return lista.map(function (item) {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const id = parseInt(campo(item, ['img_id', 'id'], 0), 10) || 0;
            const url = String(campo(item, ['url', 'img_url', 'imagen_url'], ''));
            const principalRaw = campo(item, ['principal', 'img_principal', 'es_principal'], 0);
            const principal = principalRaw === true
                || principalRaw === 1
                || String(principalRaw) === '1';

            if (!url) {
                return null;
            }

            return {
                id: id,
                url: url,
                principal: principal
            };
        }).filter(function (item) {
            return item !== null;
        });
    }

    function ordenar_imagenes(lista) {
        return lista.slice().sort(function (a, b) {
            if (a.principal !== b.principal) {
                return a.principal ? -1 : 1;
            }
            return a.id - b.id;
        });
    }

    function render_detalle_galeria(imagenes) {
        if (!detGaleriaEl) {
            return;
        }

        detGaleriaEl.innerHTML = '';
        const lista = ordenar_imagenes(imagenes);

        if (detGaleriaContadorEl) {
            detGaleriaContadorEl.textContent = lista.length + ' / 3';
        }

        if (!lista.length) {
            if (detGaleriaVacioEl) {
                detGaleriaEl.appendChild(detGaleriaVacioEl);
                detGaleriaVacioEl.classList.remove('d-none');
                detGaleriaVacioEl.textContent = 'Sin imágenes';
            }
            return;
        }

        lista.forEach(function (imagen) {
            const principal = !!imagen.principal;
            const urlSeguro = escapeHtml(imagen.url);

            const card = document.createElement('div');
            card.className = 'col-6 col-md-4 col-lg-3';
            card.innerHTML =
                '<div class="border ' + (principal ? 'border-2 border-primary' : 'border-gray-200') + ' rounded-3 p-2 h-100 d-flex flex-column">' +
                    '<div class="ratio ratio-4x3">' +
                        '<img src="' + urlSeguro + '" alt="Imagen de la rifa" ' +
                            'class="w-100 h-100 object-fit-cover rounded" data-rifa-thumb="1" ' +
                            'data-src="' + urlSeguro + '" role="button" tabindex="0" title="Ampliar imagen" />' +
                    '</div>' +
                    '<div class="d-flex align-items-center justify-content-between gap-2 mt-2">' +
                        '<span class="badge ' + (principal ? 'badge-light-primary' : 'badge-light-secondary') + ' fw-bold">' +
                            (principal ? 'Portada' : 'Adicional') +
                        '</span>' +
                        (principal ? '<span class="text-muted fs-8">Principal</span>' : '') +
                    '</div>' +
                '</div>';
            detGaleriaEl.appendChild(card);
        });
    }

    function pad_numero(valor, longitud) {
        const numero = parseInt(valor, 10);
        const largo = Math.max(1, Math.min(6, parseInt(longitud, 10) || 1));
        if (!isFinite(numero)) {
            return '';
        }
        return String(numero).padStart(largo, '0');
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

    function formatear_moneda(precio, moneda) {
        const simbolos = {
            USD: '$',
            EUR: '€',
            VES: 'Bs.',
            COP: '$',
            MXN: '$',
            USDT: '₮',
            GBP: '£'
        };
        const codigo = String(moneda || '').toUpperCase();
        const simbolo = simbolos[codigo] || codigo;
        const numero = parseFloat(precio);

        if (!isFinite(numero)) {
            return simbolo;
        }

        const monto = numero.toLocaleString('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        return (simbolo !== '' ? simbolo + ' ' : '') + monto + (codigo !== '' ? ' ' + codigo : '');
    }

    function texto_multilinea(valor) {
        return escapeHtml(valor).replace(/\r\n|\r|\n/g, '<br>');
    }

    function badge_estado(estado) {
        const clave = String(estado || '').toLowerCase();
        const item = ESTADOS_BADGE[clave] || ['secondary', (clave || 'Desconocido')];
        return '<span class="badge badge-light-' + item[0] + ' fw-bold px-3 py-2">' + escapeHtml(item[1]) + '</span>';
    }

    function resumen_numeracion(d) {
        const tipo = String(campo(d, ['rifa_numeracion_tipo', 'numeracion_tipo'], '2'));
        let desde = parseInt(campo(d, ['rifa_numero_desde', 'numero_desde'], 0), 10);
        let hasta = parseInt(campo(d, ['rifa_numero_hasta', 'numero_hasta'], 99), 10);
        let padding = parseInt(campo(d, ['rifa_padding', 'padding'], 2), 10);

        if (tipo === '2') {
            desde = 0;
            hasta = 99;
            padding = 2;
        } else if (tipo === '3') {
            desde = 0;
            hasta = 999;
            padding = 3;
        } else if (tipo === '4') {
            desde = 0;
            hasta = 9999;
            padding = 4;
        }

        if (!isFinite(desde) || !isFinite(hasta) || !isFinite(padding) || hasta <= desde) {
            return '—';
        }

        const total = hasta - desde + 1;
        return pad_numero(desde, padding) + '–' + pad_numero(hasta, padding) + ' · ' + total + ' números';
    }

    function init_tooltips() {
        if (jq && jq.fn && jq.fn.tooltip) {
            jq('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
        }
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
            const detalle = primer_error(xhr.responseJSON.errores);
            if (detalle) {
                return detalle;
            }
        }
        return porDefecto;
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

    /*
     * M09: la columna de acciones puede incluir aprobar/rechazar además de
     * ver detalle. Se conserva el HTML del backend cuando ya trae botones;
     * el fallback mantiene el botón de detalle para respuestas antiguas.
     */
    function render_acciones_rifa(data, type, row) {
        if (type !== 'display') {
            return data;
        }

        if (typeof data === 'string' && data.indexOf('btn-') !== -1) {
            return data;
        }

        const texto = (data === null || data === undefined) ? '' : String(data);
        const fila = row || {};
        const fuenteIds = texto + ' ' + String(fila.rifa || '') + ' ' + String(fila.organizador || '');
        const coincidencia = fuenteIds.match(/data-id=["'](\d+)["']/);
        const id = fila.rifa_id || fila.id || (coincidencia ? coincidencia[1] : '');

        if (!id) {
            return data;
        }

        const boton = ''
            .concat('<button type="button" class="btn btn-sm btn-icon btn-light-primary btn-ver shadow-xs" ')
            .concat('data-id="' + escapeHtml(id) + '" data-bs-toggle="tooltip" data-bs-placement="top" ')
            .concat('title="Ver detalle" aria-label="Ver detalle">')
            .concat('<i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>')
            .concat('</button>');

        return '<div class="d-flex justify-content-end align-items-center gap-2">' + boton + '</div>';
    }

    // ─────────────────────────────────────────────────────
    // DataTable server-side
    // ─────────────────────────────────────────────────────
    function init_tabla() {
        if (!tieneDataTables) {
            const aviso = document.getElementById('aviso_datatables_rifas');
            if (aviso) {
                aviso.classList.remove('d-none');
            }
            return;
        }

        dtRifas = jq('#tabla_rifas').DataTable({
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
                    if (json && json.error) {
                        mostrar_alerta('warning', json.error);
                    }
                    return (json && json.data) ? json.data : [];
                },
                error: function (xhr) {
                    set_loading(btnRecargar, false);
                    mostrar_alerta('error', mensaje_error_xhr(xhr, 'No se pudieron cargar las rifas.'));
                }
            },
            columns: [
                { data: 'organizador', orderable: true, responsivePriority: 2 },
                { data: 'rifa', orderable: true, responsivePriority: 1 },
                { data: 'tipo', orderable: false, responsivePriority: 3 },
                { data: 'numeracion', orderable: false, responsivePriority: 4 },
                { data: 'estado', orderable: true, className: 'text-center', responsivePriority: 2 },
                { data: 'fechas', orderable: false, responsivePriority: 5 },
                { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2', responsivePriority: 1, render: render_acciones_rifa }
            ],
            drawCallback: function () {
                set_loading(btnRecargar, false);
                init_tooltips();
            },
            language: idioma_es('rifas')
        });
    }

    init_tabla();

    if (inputFiltro && dtRifas) {
        inputFiltro.addEventListener('input', function () {
            const valor = this.value;
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function () {
                dtRifas.search(valor).draw();
            }, 250);
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', function () {
            if (btnRecargar.disabled) {
                return;
            }
            set_loading(btnRecargar, true);
            if (dtRifas) {
                dtRifas.ajax.reload(null, false);
            } else {
                set_loading(btnRecargar, false);
            }
        });
    }

    // ─────────────────────────────────────────────────────
    // Detalle (solo lectura)
    // ─────────────────────────────────────────────────────
    function limpiar_detalle() {
        const ids = [
            'det_organizador', 'det_organizador_slug', 'det_titulo', 'det_slug',
            'det_tipo_precio', 'det_numeracion', 'det_plan_lote', 'det_lote_numeracion',
            'det_lote_cantidad', 'det_lote_usadas', 'det_total', 'det_max', 'det_ttl',
            'det_auto', 'det_fecha_sorteo', 'det_publicada', 'det_finalizada',
            'det_cancelada', 'det_aprobacion', 'det_cancelacion', 'det_descripcion',
            'det_condiciones'
        ];

        ids.forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = '—';
            }
        });

        const estado = document.getElementById('det_estado');
        if (estado) {
            estado.innerHTML = '—';
        }

        if (detGaleriaEl) {
            detGaleriaEl.innerHTML = '';
            if (detGaleriaVacioEl) {
                detGaleriaEl.appendChild(detGaleriaVacioEl);
                detGaleriaVacioEl.classList.remove('d-none');
                detGaleriaVacioEl.textContent = 'Sin imágenes';
            }
        }
        if (detGaleriaContadorEl) {
            detGaleriaContadorEl.textContent = '0 / 3';
        }
    }

    function cargar_datos(id) {
        return request_json(BASE_URL + 'datos/' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 401) {
                redirigir_login();
                return null;
            }

            if (res.status === 403) {
                mostrar_alerta('error', (res.data && res.data.msg) || 'No tienes permiso para consultar esta rifa.');
                return null;
            }

            if (!res.data || res.data.ok !== true || !res.data.data) {
                modalDetalle.hide();
                mostrar_alerta('error', (res.data && res.data.msg) || 'No se pudieron cargar los datos de la rifa.');
                return null;
            }

            return res.data.data;
        });
    }

    function abrir_detalle(id) {
        limpiar_detalle();
        modalDetalle.show();

        cargar_datos(id).then(function (d) {
            if (!d) {
                return;
            }

            const rifaId = campo(d, ['rifa_id', 'id'], id);
            const estado = String(campo(d, ['rifa_estado', 'estado'], 'borrador')).toLowerCase();
            const tipo = String(campo(d, ['rifa_tipo', 'tipo'], 'paga')).toLowerCase();
            const precio = campo(d, ['rifa_precio', 'precio'], 0);
            const moneda = campo(d, ['rifa_moneda', 'moneda'], 'USD');
            const total = parseInt(campo(d, ['rifa_total_numeros', 'total_numeros'], 0), 10);
            const maximo = parseInt(campo(d, ['rifa_max_por_compra', 'max_por_compra'], 0), 10);
            const ttl = parseInt(campo(d, ['rifa_reserva_ttl_min', 'reserva_ttl_min', 'ttl'], 0), 10);
            const auto = parseInt(campo(d, ['rifa_reserva_auto', 'reserva_auto'], 0), 10) === 1;

            document.getElementById('det_organizador').textContent = campo(d, ['org_nombre_publico', 'organizador', 'org_nombre'], '—');
            document.getElementById('det_organizador_slug').textContent = campo(d, ['org_slug', 'organizador_slug'], '—');
            document.getElementById('det_titulo').textContent = campo(d, ['rifa_titulo', 'titulo'], '—');
            document.getElementById('det_slug').textContent = campo(d, ['rifa_slug', 'slug'], '—');

            document.getElementById('det_tipo_precio').textContent = ((tipo === 'gratuita') ? 'Gratuita' : 'Paga')
                + ' · ' + formatear_moneda(precio, moneda);

            document.getElementById('det_numeracion').textContent = resumen_numeracion(d);
            document.getElementById('det_estado').innerHTML = badge_estado(estado);

            document.getElementById('det_plan_lote').textContent = campo(d, ['asig_plan_nombre', 'plan_nombre'], '—');
            document.getElementById('det_lote_numeracion').textContent = campo(d, ['num_label', 'asig_num_label'], '—');

            const loteCantidadRaw = campo(d, ['asig_cantidad'], '');
            const loteCantidad = parseInt(loteCantidadRaw, 10);
            document.getElementById('det_lote_cantidad').textContent = (loteCantidadRaw !== '' && isFinite(loteCantidad))
                ? (loteCantidad.toLocaleString('es-CO') + ' rifas')
                : '—';

            const loteUsadasRaw = campo(d, ['asig_rifas_usadas'], '');
            const loteUsadas = parseInt(loteUsadasRaw, 10);
            document.getElementById('det_lote_usadas').textContent = (loteUsadasRaw !== '' && isFinite(loteUsadas))
                ? (loteUsadas.toLocaleString('es-CO') + ' usadas')
                : '—';

            document.getElementById('det_total').textContent = isFinite(total) && total > 0 ? (total + ' números') : '—';
            document.getElementById('det_max').textContent = isFinite(maximo) && maximo > 0 ? String(maximo) : '—';
            document.getElementById('det_ttl').textContent = (isFinite(ttl) && ttl > 0) ? (ttl + ' minutos') : 'Sin expiración';
            document.getElementById('det_auto').textContent = auto ? 'Activada' : 'Desactivada';

            document.getElementById('det_fecha_sorteo').textContent = formatear_fecha(campo(d, ['rifa_fecha_sorteo', 'fecha_sorteo'], ''), true) || '—';
            document.getElementById('det_publicada').textContent = formatear_fecha(campo(d, ['rifa_publicada_at', 'publicada_at'], ''), true) || '—';
            document.getElementById('det_finalizada').textContent = formatear_fecha(campo(d, ['rifa_finalizada_at', 'finalizada_at'], ''), true) || '—';
            document.getElementById('det_cancelada').textContent = formatear_fecha(campo(d, ['rifa_cancelada_at', 'cancelada_at'], ''), true) || '—';

            document.getElementById('det_aprobacion').textContent = campo(d, ['rifa_aprobacion_motivo', 'aprobacion_motivo'], '—');
            document.getElementById('det_cancelacion').textContent = campo(d, ['rifa_cancelacion_motivo', 'cancelacion_motivo'], '—');

            // La descripción llega sanitizada desde el backend y admite HTML.
            const descripcionHtml = campo(d, ['rifa_descripcion', 'descripcion'], '');
            document.getElementById('det_descripcion').innerHTML = descripcionHtml ? String(descripcionHtml) : '—';
            document.getElementById('det_condiciones').innerHTML = texto_multilinea(campo(d, ['rifa_condiciones', 'condiciones'], '—'));

            const imagenUrl = campo(d, ['imagen_url', 'rifa_imagen_url'], '');
            const imagenArchivo = campo(d, ['rifa_imagen', 'imagen'], '');
            const preview = imagenUrl
                ? String(imagenUrl)
                : (imagenArchivo ? MEDIA_RIFA_BASE + encodeURIComponent(String(rifaId)) : '');

            const galeria = normalizar_imagenes(campo(d, ['imagenes'], []));
            if (!galeria.length && preview) {
                galeria.push({ id: 0, url: preview, principal: true });
            }
            if (galeria.length && !galeria.some(function (item) { return item.principal; })) {
                galeria[0].principal = true;
            }
            render_detalle_galeria(galeria);
        });
    }

    function limpiar_error_moderacion(id) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = '';
        }
    }

    function post_moderacion(url, datos, btn, errorId, onSuccess) {
        limpiar_error_moderacion(errorId);
        set_loading(btn, true);

        const fd = new FormData();
        Object.keys(datos).forEach(function (key) {
            fd.append(key, datos[key]);
        });

        request_json(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            set_loading(btn, false);

            if (res.status === 401) {
                redirigir_login();
                return;
            }

            if (res.data && res.data.ok === true) {
                if (typeof onSuccess === 'function') {
                    onSuccess(res.data);
                }
                return;
            }

            const msg = (res.data && res.data.msg)
                || primer_error(res.data && res.data.errores)
                || 'No se pudo completar la acción.';
            const errorEl = document.getElementById(errorId);
            if (errorEl) {
                errorEl.textContent = msg;
            }
            mostrar_alerta('error', msg);
        }).catch(function () {
            set_loading(btn, false);
            const errorEl = document.getElementById(errorId);
            if (errorEl) {
                errorEl.textContent = 'Error de conexión. Inténtalo de nuevo.';
            }
            mostrar_alerta('error', 'Error de conexión. Inténtalo de nuevo.');
        });
    }

    function abrir_aprobar_gratuita(fila, btn) {
        const id = (fila && fila.rifa_id) || (btn && btn.dataset.id) || '';
        const titulo = (fila && fila.rifa_titulo) || (btn && btn.dataset.titulo) || '';

        document.getElementById('aprobar_gratuita_id').value = id;
        document.getElementById('aprobar_gratuita_titulo').textContent = titulo || 'Rifa gratuita';
        document.getElementById('aprobar_gratuita_num_id').value = '';
        limpiar_error_moderacion('aprobar_gratuita_error');

        const select = document.getElementById('aprobar_gratuita_num_id');
        if (!select || select.options.length <= 1) {
            document.getElementById('aprobar_gratuita_error').textContent = 'No hay numeraciones activas. Configura una en Planes de Rifa.';
        }

        modalAprobarGratuita.show();
    }

    function abrir_rechazar_gratuita(fila, btn) {
        const id = (fila && fila.rifa_id) || (btn && btn.dataset.id) || '';
        const titulo = (fila && fila.rifa_titulo) || (btn && btn.dataset.titulo) || '';

        document.getElementById('rechazar_gratuita_id').value = id;
        document.getElementById('rechazar_gratuita_titulo').textContent = titulo || 'Rifa gratuita';
        document.getElementById('rechazar_gratuita_motivo').value = '';
        limpiar_error_moderacion('rechazar_gratuita_error');
        modalRechazarGratuita.show();
    }

    function abrir_aprobar_cancelacion(fila, btn) {
        const id = (fila && fila.rifa_id) || (btn && btn.dataset.id) || '';
        const titulo = (fila && fila.rifa_titulo) || (btn && btn.dataset.titulo) || '';
        const motivo = (fila && fila.rifa_cancelacion_motivo) || '';

        document.getElementById('aprobar_cancelacion_id').value = id;
        document.getElementById('aprobar_cancelacion_titulo').textContent = titulo || 'Rifa';
        document.getElementById('aprobar_cancelacion_motivo_org').textContent = motivo || 'Sin motivo indicado';
        document.getElementById('aprobar_cancelacion_motivo').value = '';
        limpiar_error_moderacion('aprobar_cancelacion_error');
        modalAprobarCancelacion.show();
    }

    function abrir_rechazar_cancelacion(fila, btn) {
        const id = (fila && fila.rifa_id) || (btn && btn.dataset.id) || '';
        const titulo = (fila && fila.rifa_titulo) || (btn && btn.dataset.titulo) || '';
        const motivo = (fila && fila.rifa_cancelacion_motivo) || '';

        document.getElementById('rechazar_cancelacion_id').value = id;
        document.getElementById('rechazar_cancelacion_titulo').textContent = titulo || 'Rifa';
        document.getElementById('rechazar_cancelacion_motivo_org').textContent = motivo || 'Sin motivo indicado';
        document.getElementById('rechazar_cancelacion_motivo').value = '';
        limpiar_error_moderacion('rechazar_cancelacion_error');
        modalRechazarCancelacion.show();
    }

    const tablaRifas = document.getElementById('tabla_rifas');
    if (tablaRifas) {
        tablaRifas.addEventListener('click', function (event) {
            const btnDetalle = event.target.closest('.btn-ver, .btn-detalle, [data-action="detalle"], [data-action="ver"]');
            if (btnDetalle) {
                const fila = fila_data(dtRifas, btnDetalle);
                const id = btnDetalle.dataset.id || fila.rifa_id || fila.id || '';

                if (!id) {
                    mostrar_alerta('error', 'No se pudo identificar la rifa seleccionada.');
                    return;
                }

                abrir_detalle(id);
                return;
            }

            const btnAprobarGratuita = event.target.closest('.btn-aprobar-gratuita');
            if (btnAprobarGratuita) {
                abrir_aprobar_gratuita(fila_data(dtRifas, btnAprobarGratuita), btnAprobarGratuita);
                return;
            }

            const btnRechazarGratuita = event.target.closest('.btn-rechazar-gratuita');
            if (btnRechazarGratuita) {
                abrir_rechazar_gratuita(fila_data(dtRifas, btnRechazarGratuita), btnRechazarGratuita);
                return;
            }

            const btnAprobarCancelacion = event.target.closest('.btn-aprobar-cancelacion');
            if (btnAprobarCancelacion) {
                abrir_aprobar_cancelacion(fila_data(dtRifas, btnAprobarCancelacion), btnAprobarCancelacion);
                return;
            }

            const btnRechazarCancelacion = event.target.closest('.btn-rechazar-cancelacion');
            if (btnRechazarCancelacion) {
                abrir_rechazar_cancelacion(fila_data(dtRifas, btnRechazarCancelacion), btnRechazarCancelacion);
            }
        });
    }

    const btnConfirmarAprobarGratuita = document.getElementById('btn_confirmar_aprobar_gratuita');
    if (btnConfirmarAprobarGratuita) {
        btnConfirmarAprobarGratuita.addEventListener('click', function () {
            const id = document.getElementById('aprobar_gratuita_id').value;
            const numId = document.getElementById('aprobar_gratuita_num_id').value;

            if (!id) {
                return;
            }
            if (!numId) {
                document.getElementById('aprobar_gratuita_error').textContent = 'Selecciona una numeración activa.';
                return;
            }

            post_moderacion(BASE_URL + 'aprobar-gratuita/' + encodeURIComponent(id), { num_id: numId }, this, 'aprobar_gratuita_error', function (res) {
                modalAprobarGratuita.hide();
                mostrar_alerta('success', res.msg || 'Rifa aprobada correctamente.');
                if (dtRifas) {
                    dtRifas.ajax.reload(null, false);
                }
            });
        });
    }

    const btnConfirmarRechazarGratuita = document.getElementById('btn_confirmar_rechazar_gratuita');
    if (btnConfirmarRechazarGratuita) {
        btnConfirmarRechazarGratuita.addEventListener('click', function () {
            const id = document.getElementById('rechazar_gratuita_id').value;
            const motivo = document.getElementById('rechazar_gratuita_motivo').value.trim();

            if (!id) {
                return;
            }
            if (motivo.length < 5) {
                document.getElementById('rechazar_gratuita_error').textContent = 'Indica un motivo de al menos 5 caracteres.';
                return;
            }

            post_moderacion(BASE_URL + 'rechazar-gratuita/' + encodeURIComponent(id), { motivo: motivo }, this, 'rechazar_gratuita_error', function (res) {
                modalRechazarGratuita.hide();
                mostrar_alerta('success', res.msg || 'Rifa rechazada correctamente.');
                if (dtRifas) {
                    dtRifas.ajax.reload(null, false);
                }
            });
        });
    }

    const btnConfirmarAprobarCancelacion = document.getElementById('btn_confirmar_aprobar_cancelacion');
    if (btnConfirmarAprobarCancelacion) {
        btnConfirmarAprobarCancelacion.addEventListener('click', function () {
            const id = document.getElementById('aprobar_cancelacion_id').value;
            const motivo = document.getElementById('aprobar_cancelacion_motivo').value.trim();

            if (!id) {
                return;
            }

            post_moderacion(BASE_URL + 'aprobar-cancelacion/' + encodeURIComponent(id), { motivo: motivo }, this, 'aprobar_cancelacion_error', function (res) {
                modalAprobarCancelacion.hide();
                mostrar_alerta('success', res.msg || 'Cancelación aprobada.');
                if (dtRifas) {
                    dtRifas.ajax.reload(null, false);
                }
            });
        });
    }

    const btnConfirmarRechazarCancelacion = document.getElementById('btn_confirmar_rechazar_cancelacion');
    if (btnConfirmarRechazarCancelacion) {
        btnConfirmarRechazarCancelacion.addEventListener('click', function () {
            const id = document.getElementById('rechazar_cancelacion_id').value;
            const motivo = document.getElementById('rechazar_cancelacion_motivo').value.trim();

            if (!id) {
                return;
            }
            if (motivo.length < 5) {
                document.getElementById('rechazar_cancelacion_error').textContent = 'Indica un motivo de al menos 5 caracteres.';
                return;
            }

            post_moderacion(BASE_URL + 'rechazar-cancelacion/' + encodeURIComponent(id), { motivo: motivo }, this, 'rechazar_cancelacion_error', function (res) {
                modalRechazarCancelacion.hide();
                mostrar_alerta('success', res.msg || 'Solicitud rechazada.');
                if (dtRifas) {
                    dtRifas.ajax.reload(null, false);
                }
            });
        });
    }
});
</script>
<!--end::JavaScript-->
