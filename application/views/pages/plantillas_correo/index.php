<?php
$url_base = $url_base ?? 'plantillas-correo';
?>

<style>
/* Z-Index elevado para que menús y paneles flotantes de CKEditor se superpongan correctamente sobre el modal de Bootstrap */
:root {
    --ck-z-default: 2000 !important;
    --ck-z-panel: 3000 !important;
    --ck-z-dialog: 10000 !important;
}
.ck-body-wrapper {
    z-index: 10055 !important;
    position: relative;
}

/* Ajustes de Altura y Diseño para CKEditor en Modal */
.ck.ck-editor {
    width: 100% !important;
}
.ck-editor__editable_inline {
    min-height: 250px !important;
    max-height: 400px !important;
    overflow-y: auto !important;
    font-size: 14px !important;
    line-height: 1.6 !important;
}

/* Compatibilidad Bi-Tema (Modo Oscuro Metronic) */
[data-bs-theme="dark"] .ck.ck-toolbar {
    background: #1e1e2d !important;
    border-color: #323248 !important;
}
[data-bs-theme="dark"] .ck.ck-toolbar .ck-button {
    color: #9a9cae !important;
}
[data-bs-theme="dark"] .ck.ck-toolbar .ck-button:hover,
[data-bs-theme="dark"] .ck.ck-toolbar .ck-button.ck-on {
    background: #2b2b40 !important;
    color: #ffffff !important;
}
[data-bs-theme="dark"] .ck.ck-editor__editable:not(.ck-editor__nested-editable) {
    background: #151521 !important;
    color: #cdcdde !important;
    border-color: #323248 !important;
}
[data-bs-theme="dark"] .ck.ck-dropdown__panel {
    background: #1e1e2d !important;
    border-color: #323248 !important;
}
[data-bs-theme="dark"] .ck.ck-list {
    background: #1e1e2d !important;
}
[data-bs-theme="dark"] .ck.ck-list__item .ck-button {
    color: #cdcdde !important;
    background: transparent !important;
}
[data-bs-theme="dark"] .ck.ck-list__item .ck-button:hover {
    background: #2b2b40 !important;
}
[data-bs-theme="dark"] .ck.ck-input {
    background: #151521 !important;
    color: #cdcdde !important;
    border-color: #323248 !important;
}
[data-bs-theme="dark"] .ck.ck-labeled-field-view__status {
    color: #9a9cae !important;
}
</style>

<!--begin::Card Principal-->
<div class="card card-flush shadow-sm">
    <!--begin::Card Header-->
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4 text-gray-500">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="filtro_datatable" class="form-control form-control-solid w-250px ps-12 fs-7" placeholder="Search templates..." />
            </div>
        </div>

        <div class="card-toolbar">
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light d-flex align-items-center" id="btn_recargar_tabla" title="Reload table">
                    <i class="ki-duotone ki-arrows-circle fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Reload
                </button>
                <button type="button" class="btn btn-sm btn-primary d-flex align-items-center" id="btn_nueva_plantilla">
                    <i class="ki-duotone ki-plus fs-4 me-1"></i> New Template
                </button>
            </div>
        </div>
    </div>
    <!--end::Card Header-->

    <!--begin::Card Body-->
    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_plantillas">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="w-50px">#</th>
                        <th class="min-w-220px">Identifier / Name</th>
                        <th class="min-w-250px">Subject & Preheader</th>
                        <th class="min-w-100px">Status</th>
                        <th class="min-w-120px text-end pe-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 fs-7">
                    <!-- DataTables Server-Side -->
                </tbody>
            </table>
        </div>
    </div>
    <!--end::Card Body-->
</div>
<!--end::Card Principal-->

<!--============================================================-->
<!-- MODAL: CREAR / EDITAR PLANTILLA                            -->
<!--============================================================-->
<div class="modal fade" id="modal_plantilla" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-primary">
                        <i class="ki-duotone ki-sms fs-3 text-primary"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h4 class="modal-title fw-bold text-gray-900" id="modal_plantilla_titulo">New Email Template</h4>
                        <span class="text-muted fs-8">Customize transactional content and dynamic variables</span>
                    </div>
                </div>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>

            <form id="form_plantilla" method="POST" novalidate="novalidate">
                <input type="hidden" name="plc_id" id="plc_id" value="0" />
                <input type="hidden" name="plc_variables_disponibles" id="plc_variables_disponibles" value="" />

                <div class="modal-body py-5 px-lg-8" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                    
                    <div class="row g-4 mb-4">
                        <!-- Código Identificador -->
                        <div class="col-md-5">
                            <label class="required form-label fw-semibold fs-7">Identifier Code</label>
                            <input type="text" name="plc_codigo" id="plc_codigo" class="form-control form-control-solid font-monospace fs-7 text-uppercase" placeholder="E.G. BIENVENIDA, RECUPERAR_CLAVE" required />
                            <div class="text-muted fs-8 mt-1">Unique uppercase identifier (letters, numbers, and underscores).</div>
                            <div class="invalid-feedback" id="err_plc_codigo"></div>
                        </div>

                        <!-- Nombre Descriptivo -->
                        <div class="col-md-7">
                            <label class="required form-label fw-semibold fs-7">Template Name</label>
                            <input type="text" name="plc_nombre" id="plc_nombre" class="form-control form-control-solid fs-7" placeholder="E.g. New User Welcome" required />
                            <div class="invalid-feedback" id="err_plc_nombre"></div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Asunto -->
                        <div class="col-md-7">
                            <label class="required form-label fw-semibold fs-7">Email Subject</label>
                            <input type="text" name="plc_asunto" id="plc_asunto" class="form-control form-control-solid fs-7" placeholder="E.g. Welcome to {empresa_nombre} - Activate your account" required />
                            <div class="invalid-feedback" id="err_plc_asunto"></div>
                        </div>

                        <!-- Preheader -->
                        <div class="col-md-5">
                            <label class="form-label fw-semibold fs-7">Preheader (Preview Text)</label>
                            <input type="text" name="plc_preheader" id="plc_preheader" class="form-control form-control-solid fs-7" placeholder="Short text visible in the inbox..." />
                            <div class="invalid-feedback" id="err_plc_preheader"></div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Título en Encabezado -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7">Email Heading</label>
                            <input type="text" name="plc_titulo" id="plc_titulo" class="form-control form-control-solid fs-7" placeholder="E.g. Welcome to {empresa_nombre}!" />
                        </div>

                        <!-- Subtítulo -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7">Subtitle / Intro Text</label>
                            <input type="text" name="plc_subtitulo" id="plc_subtitulo" class="form-control form-control-solid fs-7" placeholder="E.g. We are glad to have you on our platform." />
                        </div>
                    </div>

                    <!-- Panel de Variables Dinámicas Interactivas -->
                    <div class="card card-flush bg-light bg-opacity-50 border border-gray-300 rounded p-4 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-7 fw-bold text-gray-800 d-flex align-items-center">
                                <i class="ki-duotone ki-tag fs-5 text-primary me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                Available Dynamic Variables (click to insert):
                            </span>
                            <span class="text-muted fs-8">They will be replaced with real data when sent</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2" id="contenedor_pills_variables">
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-primary py-1 px-2 fs-8 btn-insertar-var" data-var="{usuario_nombre}" title="Full user name">
                                <span class="bullet bullet-dot bg-primary me-1"></span>{usuario_nombre}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-primary py-1 px-2 fs-8 btn-insertar-var" data-var="{usuario_usuario}" title="Username">
                                <span class="bullet bullet-dot bg-primary me-1"></span>{usuario_usuario}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-primary py-1 px-2 fs-8 btn-insertar-var" data-var="{usuario_correo}" title="Recipient email">
                                <span class="bullet bullet-dot bg-primary me-1"></span>{usuario_correo}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-success py-1 px-2 fs-8 btn-insertar-var" data-var="{enlace_accion}" title="Button or link URL">
                                <span class="bullet bullet-dot bg-success me-1"></span>{enlace_accion}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-info py-1 px-2 fs-8 btn-insertar-var" data-var="{codigo_verificacion}" title="Numeric code">
                                <span class="bullet bullet-dot bg-info me-1"></span>{codigo_verificacion}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-secondary py-1 px-2 fs-8 btn-insertar-var" data-var="{empresa_nombre}" title="Your company name">
                                <span class="bullet bullet-dot bg-secondary me-1"></span>{empresa_nombre}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-secondary py-1 px-2 fs-8 btn-insertar-var" data-var="{empresa_correo}" title="Support email">
                                <span class="bullet bullet-dot bg-secondary me-1"></span>{empresa_correo}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-secondary py-1 px-2 fs-8 btn-insertar-var" data-var="{fecha_actual}" title="Current date">
                                <span class="bullet bullet-dot bg-secondary me-1"></span>{fecha_actual}
                            </button>
                        </div>
                    </div>

                    <!-- Cuerpo / Mensaje HTML (CKEditor) -->
                    <div class="mb-4">
                        <label class="required form-label fw-semibold fs-7">Message Body or Content</label>
                        <textarea name="plc_cuerpo" id="plc_cuerpo" class="form-control form-control-solid" rows="8"></textarea>
                        <div class="invalid-feedback" id="err_plc_cuerpo"></div>
                    </div>

                    <!-- Botón de Llamada a la Acción (CTA) -->
                    <div class="row g-4 mb-4 p-3 border rounded bg-body">
                        <div class="col-12">
                            <span class="fs-7 fw-bold text-gray-800 d-block mb-1">
                                <i class="ki-duotone ki-cursor fs-5 text-success me-1"><span class="path1"></span><span class="path2"></span></i>
                                Main Call-to-Action Button (Optional)
                            </span>
                            <span class="text-muted fs-8">If set, the email will display a centered, styled call-to-action button.</span>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold fs-7">Button Text</label>
                            <input type="text" name="plc_boton_texto" id="plc_boton_texto" class="form-control form-control-solid fs-7" placeholder="E.g. Reset Password, Activate Account" />
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold fs-7">Destination Link (URL or Variable)</label>
                            <input type="text" name="plc_boton_url" id="plc_boton_url" class="form-control form-control-solid fs-7" placeholder="E.g. {enlace_accion} or https://..." />
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Footer del Correo -->
                        <div class="col-md-7">
                            <label class="form-label fw-semibold fs-7">Security Text / Email Footer</label>
                            <input type="text" name="plc_footer_texto" id="plc_footer_texto" class="form-control form-control-solid fs-7" placeholder="E.g. If you did not request this change, you can ignore this message." />
                        </div>

                        <!-- Estado -->
                        <div class="col-md-5 d-flex align-items-center">
                            <div class="d-flex align-items-center gap-3 mt-4">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="plc_status" id="plc_status" value="1" checked />
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-900 fs-7">Active Template</span>
                                    <span class="text-muted fs-8">Enable automatic sending in the system</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descripción interna -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold fs-7">Template Description or Notes</label>
                        <input type="text" name="plc_descripcion" id="plc_descripcion" class="form-control form-control-solid fs-7" placeholder="Brief note explaining the purpose of this template..." />
                    </div>

                </div>

                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-sm btn-light-info d-flex align-items-center" id="btn_modal_preview_vivo">
                            <i class="ki-duotone ki-eye fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i> Preview
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light d-flex align-items-center" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Cancel
                        </button>
                        <button type="button" class="btn btn-sm btn-primary d-flex align-items-center" id="btn_guardar_plantilla">
                            <i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Save Template
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>
<!--end::Modal Crear / Editar-->

<!--============================================================-->
<!-- MODAL: PREVIEW RESPONSIVO                                  -->
<!--============================================================-->
<div class="modal fade" id="modal_preview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            
            <div class="modal-header py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light-primary fw-bold fs-7 font-monospace" id="preview_badge_codigo">PREVIEW</span>
                    <h5 class="modal-title fw-bold text-gray-900 mb-0" id="preview_modal_titulo">Email Preview</h5>
                </div>
                
                <!-- Conmutador Desktop / Móvil -->
                <div class="d-flex align-items-center gap-2 ms-auto me-4">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light-primary active py-1 px-3 d-flex align-items-center" id="btn_view_desktop" title="Desktop View (600px)">
                            <i class="ki-duotone ki-screen fs-6 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i> Desktop
                        </button>
                        <button type="button" class="btn btn-light py-1 px-3 d-flex align-items-center" id="btn_view_mobile" title="Mobile View (375px)">
                            <i class="ki-duotone ki-phone fs-6 me-1"><span class="path1"></span><span class="path2"></span></i> Mobile
                        </button>
                    </div>
                </div>

                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>

            <div class="modal-body p-0 bg-light d-flex justify-content-center" style="min-height: 550px;">
                <div id="preview_wrapper" style="width: 100%; transition: width 0.3s ease; display: flex; justify-content: center; padding: 20px 0;">
                    <iframe id="iframe_preview" style="width: 100%; max-width: 650px; height: 600px; border: 1px solid #E4E6EF; border-radius: 8px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05);"></iframe>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
<!--end::Modal Preview-->

<!--============================================================-->
<!-- MODAL: ENVIAR CORREO DE PRUEBA                             -->
<!--============================================================-->
<div class="modal fade" id="modal_prueba" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="symbol symbol-35px symbol-circle bg-light-success">
                        <i class="ki-duotone ki-paper-plane fs-3 text-success"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <div class="d-flex flex-column">
                        <h5 class="modal-title fw-bold text-gray-900 mb-0">Send Test Email</h5>
                        <span class="text-muted fs-8">Check how the email is received in your real inbox</span>
                    </div>
                </div>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>

            <div class="modal-body py-5">
                <input type="hidden" id="prueba_plantilla_id" value="0" />
                <div class="mb-4">
                    <span class="text-muted fs-7 d-block mb-1">Selected template:</span>
                    <span class="fw-bold text-gray-900 fs-6 d-block" id="prueba_plantilla_nombre">-</span>
                </div>
                <div class="mb-3">
                    <label class="required form-label fw-semibold fs-7">Recipient Email Address</label>
                    <input type="email" id="prueba_email_destino" class="form-control form-control-solid fs-7" placeholder="your-email@example.com" value="<?php echo htmlspecialchars((string)($usuario['usu_correo'] ?? '')); ?>" required />
                    <div class="text-muted fs-8 mt-1">The message will be sent through the configured SMTP server with sample data.</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light d-flex align-items-center" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Cancel
                </button>
                <button type="button" class="btn btn-sm btn-success d-flex align-items-center" id="btn_confirmar_enviar_prueba">
                    <i class="ki-duotone ki-paper-plane fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Send Test
                </button>
            </div>

        </div>
    </div>
</div>
<!--end::Modal Enviar Correo de Prueba-->

<!-- Script del Módulo PlantillasCorreo -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var urlBase = '<?php echo base_url($url_base); ?>';
    var urlTabla = urlBase + '/tabla';
    var urlDatos = urlBase + '/datos/';
    var urlCrear = urlBase + '/crear';
    var urlActualizar = urlBase + '/actualizar/';
    var urlEliminar = urlBase + '/eliminar/';
    var urlStatus = urlBase + '/cambiar_status/';
    var urlPreview = urlBase + '/preview';
    var urlEnviarPrueba = urlBase + '/enviar_prueba';

    var dt = null;
    var ckEditorPlantilla = null;
    var pendingHtml = null;
    var ultimoCampoEnfocado = null;
    var ultimoCursorPos = 0;
    var textareaCuerpo = document.getElementById('plc_cuerpo');
    
    var modalEl = document.getElementById('modal_plantilla');
    var modalPlantilla = modalEl ? new bootstrap.Modal(modalEl) : null;

    var previewEl = document.getElementById('modal_preview');
    var modalPreview = previewEl ? new bootstrap.Modal(previewEl) : null;

    var pruebaEl = document.getElementById('modal_prueba');
    var modalPrueba = pruebaEl ? new bootstrap.Modal(pruebaEl) : null;

    var form = document.getElementById('form_plantilla');
    var btnGuardar = document.getElementById('btn_guardar_plantilla');
    var inputFiltro = document.getElementById('filtro_datatable');

    // Rastrear campo activo del formulario para inserción precisa de variables
    if (form) {
        var camposModal = form.querySelectorAll('input[type="text"], textarea');
        camposModal.forEach(function (campo) {
            if (campo.id === 'plc_cuerpo') return;
            campo.addEventListener('focus', function () {
                ultimoCampoEnfocado = this;
                ultimoCursorPos = (typeof this.selectionStart === 'number') ? this.selectionStart : this.value.length;
            });
            campo.addEventListener('keyup', function () {
                ultimoCursorPos = (typeof this.selectionStart === 'number') ? this.selectionStart : this.value.length;
            });
            campo.addEventListener('click', function () {
                ultimoCursorPos = (typeof this.selectionStart === 'number') ? this.selectionStart : this.value.length;
            });
        });
    }

    // Inicializar CKEditor 5 con los plugins disponibles en el bundle de Metronic
    if (typeof ClassicEditor !== 'undefined' && textareaCuerpo) {
        ClassicEditor.create(textareaCuerpo, {
            toolbar: {
                items: [
                    'undo', 'redo', '|',
                    'heading', '|',
                    'bold', 'italic', '|',
                    'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                    'alignment', '|',
                    'link', 'bulletedList', 'numberedList', '|',
                    'blockQuote', 'insertTable', '|',
                    'removeFormat'
                ],
                shouldNotGroupWhenFull: true
            },
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            }
        }).then(function (editor) {
            ckEditorPlantilla = editor;

            // Escuchar foco de CKEditor
            if (editor.ui && editor.ui.focusTracker) {
                editor.ui.focusTracker.on('change:isFocused', function (evt, name, isFocused) {
                    if (isFocused) {
                        ultimoCampoEnfocado = 'ckeditor';
                    }
                });
            }

            // Aplicar HTML pendiente si se cargó mientras iniciaba
            if (pendingHtml !== null) {
                editor.setData(pendingHtml);
                pendingHtml = null;
            }
        }).catch(function (error) {
            console.error('Error initializing CKEditor in Email Templates:', error);
        });
    }

    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function () {
            if (ckEditorPlantilla && ckEditorPlantilla.editing && ckEditorPlantilla.editing.view) {
                ckEditorPlantilla.editing.view.focus();
            }
        });
    }

    function getCuerpoHtml() {
        if (ckEditorPlantilla) {
            return ckEditorPlantilla.getData();
        }
        return textareaCuerpo ? textareaCuerpo.value : '';
    }

    function setCuerpoHtml(html) {
        var contenido = html || '';
        if (ckEditorPlantilla) {
            ckEditorPlantilla.setData(contenido);
            pendingHtml = null;
        } else {
            pendingHtml = contenido;
            if (textareaCuerpo) {
                textareaCuerpo.value = contenido;
            }
        }
    }

    // Inicializar DataTables Server-Side
    function initDataTable() {
        dt = $('#tabla_plantillas').DataTable({
            processing: true,
            serverSide: true,
            order: [[0, 'asc']],
            ajax: {
                url: urlTabla,
                type: 'POST'
            },
            columns: [
                { data: 0, orderable: true },
                { data: 1, orderable: true },
                { data: 2, orderable: true },
                { data: 3, orderable: true },
                { data: 4, orderable: false, className: 'text-end pe-2' }
            ],
            language: {
                zeroRecords: 'No email templates found.',
                info: 'Showing _START_ to _END_ of _TOTAL_ templates',
                infoEmpty: 'Showing 0 to 0 of 0 templates',
                infoFiltered: '(filtered from _MAX_ total entries)',
                processing: '<span class="spinner-border spinner-border-sm me-2"></span> Loading templates...'
            },
            dom: "<'table-responsive'tr><'row'<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'i><'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>>"
        });

        if (inputFiltro) {
            inputFiltro.addEventListener('keyup', function () {
                dt.search(this.value).draw();
            });
        }
    }

    initDataTable();

    document.getElementById('btn_recargar_tabla').addEventListener('click', function () {
        if (dt) dt.ajax.reload(null, false);
    });

    // Limpiar formulario y errores
    function limpiarModal() {
        form.reset();
        document.getElementById('plc_id').value = '0';
        document.getElementById('plc_codigo').readOnly = false;
        document.getElementById('plc_codigo').classList.remove('bg-secondary', 'bg-opacity-25');
        setCuerpoHtml('');
        ultimoCampoEnfocado = null;
        ultimoCursorPos = 0;
        
        // Limpiar errores visuales
        var invalidos = form.querySelectorAll('.is-invalid');
        invalidos.forEach(function (el) { el.classList.remove('is-invalid'); });
        var feedbacks = form.querySelectorAll('.invalid-feedback');
        feedbacks.forEach(function (el) { el.textContent = ''; });

        document.getElementById('modal_plantilla_titulo').textContent = 'New Email Template';
        document.getElementById('plc_status').checked = true;
    }

    function mostrarErrores(errores) {
        // Limpiar primero
        var invalidos = form.querySelectorAll('.is-invalid');
        invalidos.forEach(function (el) { el.classList.remove('is-invalid'); });

        Object.keys(errores).forEach(function (key) {
            var input = form.querySelector('[name="' + key + '"]');
            var feedback = document.getElementById('err_' + key);
            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = errores[key];
            }
        });
    }

    // Abrir Modal Crear
    document.getElementById('btn_nueva_plantilla').addEventListener('click', function () {
        limpiarModal();
        modalPlantilla.show();
    });

    // Clic en píldoras de variables dinámicas: inserta la etiqueta en el campo activo o en el editor
    document.getElementById('contenedor_pills_variables').addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-insertar-var');
        if (!btn) return;

        var variable = btn.getAttribute('data-var');
        if (!variable) return;

        // Si el usuario estaba enfocado en un input (Asunto, Preheader, Título, Enlace, etc.)
        if (ultimoCampoEnfocado && ultimoCampoEnfocado !== 'ckeditor' && typeof ultimoCampoEnfocado.focus === 'function') {
            var input = ultimoCampoEnfocado;
            var start = (typeof input.selectionStart === 'number') ? input.selectionStart : input.value.length;
            var end = (typeof input.selectionEnd === 'number') ? input.selectionEnd : input.value.length;
            var texto = input.value;
            input.value = texto.substring(0, start) + variable + texto.substring(end);
            input.focus();
            var nuevaPos = start + variable.length;
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(nuevaPos, nuevaPos);
            }
            ultimoCursorPos = nuevaPos;
            return;
        }

        // Si está en CKEditor o por defecto en el cuerpo del mensaje
        if (ckEditorPlantilla) {
            ckEditorPlantilla.model.change(function (writer) {
                var insertPosition = ckEditorPlantilla.model.document.selection.getFirstPosition();
                if (insertPosition) {
                    writer.insertText(variable, insertPosition);
                } else {
                    var root = ckEditorPlantilla.model.document.getRoot();
                    var endPosition = writer.createPositionAt(root, 'end');
                    writer.insertText(variable, endPosition);
                }
            });
            ckEditorPlantilla.editing.view.focus();
        } else if (textareaCuerpo) {
            var start = (typeof textareaCuerpo.selectionStart === 'number') ? textareaCuerpo.selectionStart : textareaCuerpo.value.length;
            var end = (typeof textareaCuerpo.selectionEnd === 'number') ? textareaCuerpo.selectionEnd : textareaCuerpo.value.length;
            var texto = textareaCuerpo.value;
            textareaCuerpo.value = texto.substring(0, start) + variable + texto.substring(end);
            textareaCuerpo.focus();
        }
    });

    // Guardar Plantilla (Crear o Actualizar)
    btnGuardar.addEventListener('click', function () {
        var id = parseInt(document.getElementById('plc_id').value, 10);
        var urlAccion = id > 0 ? (urlActualizar + id) : urlCrear;

        // Sincronizar CKEditor
        if (textareaCuerpo) {
            textareaCuerpo.value = getCuerpoHtml();
        }

        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        var formData = new FormData(form);

        fetch(urlAccion, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Save Template';

            if (res.ok) {
                modalPlantilla.hide();
                if (dt) dt.ajax.reload(null, false);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.msg || 'Saved successfully',
                    showConfirmButton: false,
                    timer: 2500
                });
            } else {
                if (res.errores) {
                    mostrarErrores(res.errores);
                } else {
                    Swal.fire('Error', res.msg || 'The template could not be saved.', 'error');
                }
            }
        })
        .catch(function () {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Save Template';
            Swal.fire('Error', 'Server connection error.', 'error');
        });
    });

    // Abrir Modal Editar
    $('#tabla_plantillas').on('click', '.btn-editar-plantilla', function () {
        var id = $(this).data('id');
        limpiarModal();

        fetch(urlDatos + id)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.data) {
                    Swal.fire('Error', res.msg || 'The template could not be loaded.', 'error');
                    return;
                }

                var d = res.data;
                document.getElementById('plc_id').value = d.plc_id;
                document.getElementById('plc_codigo').value = d.plc_codigo || '';
                document.getElementById('plc_nombre').value = d.plc_nombre || '';
                document.getElementById('plc_descripcion').value = d.plc_descripcion || '';
                document.getElementById('plc_asunto').value = d.plc_asunto || '';
                document.getElementById('plc_preheader').value = d.plc_preheader || '';
                document.getElementById('plc_titulo').value = d.plc_titulo || '';
                document.getElementById('plc_subtitulo').value = d.plc_subtitulo || '';
                document.getElementById('plc_boton_texto').value = d.plc_boton_texto || '';
                document.getElementById('plc_boton_url').value = d.plc_boton_url || '';
                document.getElementById('plc_footer_texto').value = d.plc_footer_texto || '';
                document.getElementById('plc_status').checked = (parseInt(d.plc_status, 10) === 1);

                setCuerpoHtml(d.plc_cuerpo || '');

                var codigosSistema = ['BIENVENIDA', 'RECUPERAR_CLAVE', 'VERIFICAR_CORREO', 'LOGIN_2FA', 'AVISO_SEGURIDAD_IP'];
                if (codigosSistema.indexOf(d.plc_codigo) !== -1) {
                    document.getElementById('plc_codigo').readOnly = true;
                    document.getElementById('plc_codigo').classList.add('bg-secondary', 'bg-opacity-25');
                }

                document.getElementById('modal_plantilla_titulo').textContent = 'Edit Template: ' + (d.plc_nombre || '');
                modalPlantilla.show();
            })
            .catch(function () {
                Swal.fire('Error', 'Network error while loading template data.', 'error');
            });
    });

    // Switch de cambio de estado rápido
    $('#tabla_plantillas').on('change', '.btn-cambiar-status', function () {
        var id = $(this).data('id');
        var switchEl = this;

        fetch(urlStatus + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.ok) {
                if (dt) dt.ajax.reload(null, false);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.msg || 'Status updated',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                switchEl.checked = !switchEl.checked;
                Swal.fire('Error', res.msg || 'The status could not be changed.', 'error');
            }
        })
        .catch(function () {
            switchEl.checked = !switchEl.checked;
            Swal.fire('Error', 'Network error.', 'error');
        });
    });

    // Eliminar plantilla
    $('#tabla_plantillas').on('click', '.btn-eliminar-plantilla', function () {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre') || 'the template';

        Swal.fire({
            title: 'Delete template?',
            text: 'The template "' + nombre + '" will be deleted. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                fetch(urlEliminar + id, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) {
                        if (dt) dt.ajax.reload(null, false);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: res.msg || 'Template deleted',
                            showConfirmButton: false,
                            timer: 2500
                        });
                    } else {
                        Swal.fire('Notice', res.msg || 'The template could not be deleted.', 'warning');
                    }
                })
                .catch(function () {
                    Swal.fire('Error', 'Error de red al eliminar.', 'error');
                });
            }
        });
    });

    // ==========================================
    // PREVIEW DE PLANTILLA
    // ==========================================
    var iframePreview = document.getElementById('iframe_preview');
    var btnViewDesktop = document.getElementById('btn_view_desktop');
    var btnViewMobile = document.getElementById('btn_view_mobile');

    function cargarPreview(id, nombre, codigo) {
        document.getElementById('preview_badge_codigo').textContent = codigo || 'PREVIEW';
        document.getElementById('preview_modal_titulo').textContent = 'Preview: ' + (nombre || 'Template');
        iframePreview.src = urlPreview + '/' + id;
        modalPreview.show();
    }

    $('#tabla_plantillas').on('click', '.btn-preview-plantilla', function () {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        cargarPreview(id, nombre, '');
    });

    // Preview en vivo desde el modal de edición
    document.getElementById('btn_modal_preview_vivo').addEventListener('click', function () {
        if (textareaCuerpo) {
            textareaCuerpo.value = getCuerpoHtml();
        }

        var tempForm = document.createElement('form');
        tempForm.method = 'POST';
        tempForm.action = urlPreview;
        tempForm.target = 'iframe_preview';
        tempForm.style.display = 'none';

        var campos = ['plc_codigo', 'plc_nombre', 'plc_asunto', 'plc_preheader', 'plc_titulo', 'plc_subtitulo', 'plc_cuerpo', 'plc_boton_texto', 'plc_boton_url', 'plc_footer_texto'];
        campos.forEach(function (name) {
            var input = form.querySelector('[name="' + name + '"]');
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = input ? input.value : '';
            tempForm.appendChild(hidden);
        });

        document.body.appendChild(tempForm);
        document.getElementById('preview_badge_codigo').textContent = document.getElementById('plc_codigo').value || 'PREVIEW';
        document.getElementById('preview_modal_titulo').textContent = 'Live Preview: ' + (document.getElementById('plc_nombre').value || 'Template');
        
        modalPreview.show();
        tempForm.submit();
        document.body.removeChild(tempForm);
    });

    // Switch Desktop / Mobile en Preview
    btnViewDesktop.addEventListener('click', function () {
        btnViewDesktop.classList.add('active', 'btn-light-primary');
        btnViewDesktop.classList.remove('btn-light');
        btnViewMobile.classList.remove('active', 'btn-light-primary');
        btnViewMobile.classList.add('btn-light');
        iframePreview.style.maxWidth = '650px';
    });

    btnViewMobile.addEventListener('click', function () {
        btnViewMobile.classList.add('active', 'btn-light-primary');
        btnViewMobile.classList.remove('btn-light');
        btnViewDesktop.classList.remove('active', 'btn-light-primary');
        btnViewDesktop.classList.add('btn-light');
        iframePreview.style.maxWidth = '375px';
    });

    // ==========================================
    // ENVÍO DE CORREO DE PRUEBA REAL
    // ==========================================
    var btnConfirmarPrueba = document.getElementById('btn_confirmar_enviar_prueba');

    $('#tabla_plantillas').on('click', '.btn-probar-plantilla', function () {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        document.getElementById('prueba_plantilla_id').value = id;
        document.getElementById('prueba_plantilla_nombre').textContent = nombre || '-';
        modalPrueba.show();
    });

    btnConfirmarPrueba.addEventListener('click', function () {
        var email = document.getElementById('prueba_email_destino').value.trim();
        var id = parseInt(document.getElementById('prueba_plantilla_id').value, 10);

        if (email === '') {
            Swal.fire('Notice', 'Please enter an email address for the test.', 'warning');
            return;
        }

        btnConfirmarPrueba.disabled = true;
        btnConfirmarPrueba.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

        var payload = new FormData();
        payload.append('plc_id', id);
        payload.append('email_destino', email);

        fetch(urlEnviarPrueba, {
            method: 'POST',
            body: payload,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btnConfirmarPrueba.disabled = false;
            btnConfirmarPrueba.innerHTML = '<i class="ki-duotone ki-paper-plane fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Enviar Prueba';

            if (res.ok) {
                modalPrueba.hide();
                Swal.fire('¡Enviado!', res.msg || 'Correo de prueba enviado correctamente.', 'success');
            } else {
                Swal.fire('No se pudo enviar', res.msg || 'Error al enviar correo de prueba.', 'error');
            }
        })
        .catch(function () {
            btnConfirmarPrueba.disabled = false;
            btnConfirmarPrueba.innerHTML = '<i class="ki-duotone ki-paper-plane fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Enviar Prueba';
            Swal.fire('Error', 'Error de conexión con el servidor al enviar prueba.', 'error');
        });
    });
});
</script>

