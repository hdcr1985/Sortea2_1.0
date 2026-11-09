<div class="card mb-7">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="card-label fw-bold text-gray-900">Correos Promocionales</h3>
                <span class="text-muted fw-semibold fs-7">Envía campañas manuales a usuarios seleccionados, editando solo remitente y cuerpo HTML dentro de una plantilla fija.</span>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!$tablas_disponibles): ?>
        <div class="alert alert-warning d-flex align-items-start p-5 mb-8">
            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <div>
                <div class="fw-bold mb-1">Faltan tablas del módulo</div>
                <div>Ejecuta el script <code>database/2026-05-05_create_cab_correos_promocionales.sql</code> antes de usar esta pantalla.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$correo_configurado): ?>
        <div class="alert alert-danger mb-8">El correo saliente está deshabilitado o sin configurar. Revisa Configuración de Correos antes de enviar campañas.</div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success mb-8"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-8"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php
        $form_data = array_merge([
            'promo_template' => array_key_first($promo_templates ?: []),
            'promo_subject' => '',
            'promo_from_email' => (string)($mail_settings['mail_from_email'] ?? ''),
            'promo_from_name' => (string)($mail_settings['mail_from_name'] ?? ''),
            'promo_reply_to' => (string)($mail_settings['mail_reply_to'] ?? ''),
            'promo_body_html' => '<p>Hola {{nombre}},</p><p>Aquí puedes redactar el contenido promocional manteniendo la plantilla corporativa.</p><p><strong>Incluye aquí tu oferta, anuncio o llamada a la acción.</strong></p>',
            'promo_destinatarios' => [],
        ], $old);

        if (empty($form_data['promo_template'])) {
            $form_data['promo_template'] = array_key_first($promo_templates ?: []);
        }
        ?>

        <form method="post" action="<?php echo base_url($url_base . '/enviar'); ?>" id="form_promocional">
            <div class="row g-6">
                <div class="col-12">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Remitente y Plantilla</h4>
                </div>

                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Plantilla promocional</label>
                    <select name="promo_template" id="promo_template" class="form-select form-select-solid">
                        <option value="">Selecciona una plantilla</option>
                        <?php foreach ($promo_templates as $template): ?>
                        <option value="<?php echo htmlspecialchars($template['slug']); ?>" <?php echo $form_data['promo_template'] === $template['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($template['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['promo_template'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['promo_template']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Correo remitente</label>
                    <input type="email" name="promo_from_email" id="promo_from_email" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)$form_data['promo_from_email']); ?>" />
                    <?php if (!empty($errores['promo_from_email'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['promo_from_email']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Nombre remitente</label>
                    <input type="text" name="promo_from_name" id="promo_from_name" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)$form_data['promo_from_name']); ?>" />
                    <?php if (!empty($errores['promo_from_name'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['promo_from_name']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-8">
                    <label class="form-label required fw-semibold">Asunto</label>
                    <input type="text" name="promo_subject" id="promo_subject" class="form-control form-control-solid" maxlength="180" value="<?php echo htmlspecialchars((string)$form_data['promo_subject']); ?>" />
                    <?php if (!empty($errores['promo_subject'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['promo_subject']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reply-To</label>
                    <input type="email" name="promo_reply_to" id="promo_reply_to" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)$form_data['promo_reply_to']); ?>" />
                    <?php if (!empty($errores['promo_reply_to'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['promo_reply_to']); ?></div><?php endif; ?>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Cuerpo HTML</h4>
                </div>

                <div class="col-md-6">
                    <label class="form-label required fw-semibold">Contenido</label>
                    <textarea name="promo_body_html" id="promo_body_html" class="form-control form-control-solid font-monospace" rows="16"><?php echo htmlspecialchars((string)$form_data['promo_body_html']); ?></textarea>
                    <?php if (!empty($errores['promo_body_html'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['promo_body_html']); ?></div><?php endif; ?>
                    <div class="text-muted fs-7 mt-2">Puedes editar el contenido interno con el editor HTML. La plantilla mantiene el encabezado, pie y estilo corporativo. Marcadores sugeridos: <code>{{nombre}}</code> y <code>{{correo}}</code>.</div>
                    <div class="d-flex gap-3 mt-4">
                        <button type="button" class="btn btn-light-primary btn-sm" id="btn_preview_promocion">Actualizar preview</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Vista previa</label>
                    <iframe name="promo_preview_frame" id="promo_preview_frame" title="Vista previa correo promocional" style="width:100%; min-height:560px; border:1px solid #eff2f5; border-radius:0.625rem; background:#f5f8fa;"></iframe>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold mb-1">Destinatarios</h4>
                            <div class="text-muted fs-7">Selecciona usuarios individuales o aplica grupos segmentados (ej: Usuarios más activos).</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light-primary d-flex align-items-center" id="btn_abrir_modal_grupos">
                            <i class="ki-duotone ki-people fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                            Crear / Gestionar Grupos
                        </button>
                    </div>
                </div>

                <!--begin::Barra de Grupos de Destinatarios-->
                <div class="col-12">
                    <div class="d-flex align-items-center gap-2 flex-wrap p-3 border rounded bg-body mb-3">
                        <span class="fs-7 fw-bold text-gray-700 me-2 d-flex align-items-center">
                            <i class="ki-duotone ki-filter fs-5 text-primary me-1"><span class="path1"></span><span class="path2"></span></i>
                            Grupos:
                        </span>
                        <div class="d-flex align-items-center gap-2 flex-wrap" id="lista_pills_grupos">
                            <?php if (empty($grupos)): ?>
                                <span class="text-muted fs-8 fst-italic" id="txt_sin_grupos">No hay grupos creados aún. Haz clic en "Crear / Gestionar Grupos".</span>
                            <?php else: ?>
                                <?php foreach ($grupos as $g): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline btn-outline-dashed btn-outline-<?php echo htmlspecialchars($g['cgr_color'] ?: 'primary'); ?> py-1 px-3 d-flex align-items-center btn-aplicar-grupo"
                                            data-grupo-id="<?php echo (int)$g['cgr_id']; ?>"
                                            data-grupo-nombre="<?php echo htmlspecialchars($g['cgr_nombre']); ?>"
                                            title="Haz clic para seleccionar los <?php echo (int)$g['total_usuarios']; ?> usuarios de este grupo">
                                        <span class="bullet bullet-dot bg-<?php echo htmlspecialchars($g['cgr_color'] ?: 'primary'); ?> me-1"></span>
                                        <?php echo htmlspecialchars($g['cgr_nombre']); ?>
                                        <span class="badge badge-light-<?php echo htmlspecialchars($g['cgr_color'] ?: 'primary'); ?> fs-9 ms-1 px-1 py-0">
                                            <?php echo (int)$g['total_usuarios']; ?>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!--end::Barra de Grupos de Destinatarios-->

                <div class="col-12">
                    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between mb-4">
                        <input type="text" id="filtro_destinatarios" class="form-control form-control-solid w-md-350px" placeholder="Buscar por nombre, usuario, correo o rol" />
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-light-primary btn-sm d-flex align-items-center" id="btn_seleccionar_visibles">
                                <i class="ki-duotone ki-check-square fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Seleccionar visibles
                            </button>
                            <button type="button" class="btn btn-light btn-sm d-flex align-items-center" id="btn_limpiar_destinatarios">
                                <i class="ki-duotone ki-cross-circle fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Limpiar selección
                            </button>
                            <span class="badge badge-light-primary fs-7" id="contador_destinatarios">0 seleccionados</span>
                        </div>
                    </div>
                    <?php if (!empty($errores['promo_destinatarios'])): ?><div class="text-danger fs-7 mb-3"><?php echo htmlspecialchars($errores['promo_destinatarios']); ?></div><?php endif; ?>

                    <div class="border border-dashed border-gray-300 rounded p-4 bg-light bg-opacity-40" style="max-height: 340px; overflow:auto;">
                        <div class="row g-3" id="lista_destinatarios">
                            <?php foreach ($destinatarios as $destinatario): ?>
                            <?php $checked = in_array((string)$destinatario['usu_id'], $form_data['promo_destinatarios'], TRUE); ?>
                            <div class="col-md-6 destinatario-item"
                                 data-search="<?php echo htmlspecialchars(strtolower(($destinatario['usu_nombre'] ?? '') . ' ' . ($destinatario['usu_usuario'] ?? '') . ' ' . ($destinatario['usu_correo'] ?? '') . ' ' . ($destinatario['rol_nombre'] ?? ''))); ?>">
                                <label class="d-flex align-items-start gap-3 rounded border border-gray-300 bg-body p-4 h-100 cursor-pointer">
                                    <input class="form-check-input mt-1 promo-destinatario-checkbox"
                                           type="checkbox"
                                           name="promo_destinatarios[]"
                                           value="<?php echo htmlspecialchars((string)$destinatario['usu_id']); ?>"
                                           <?php echo $checked ? 'checked' : ''; ?> />
                                    <span class="flex-grow-1">
                                        <span class="fw-bold text-gray-900 d-block"><?php echo htmlspecialchars((string)$destinatario['usu_nombre']); ?></span>
                                        <span class="text-muted d-block fs-7">@<?php echo htmlspecialchars((string)$destinatario['usu_usuario']); ?></span>
                                        <span class="text-gray-700 d-block fs-7 mt-1"><?php echo htmlspecialchars((string)$destinatario['usu_correo']); ?></span>
                                        <span class="badge badge-light mt-2"><?php echo htmlspecialchars((string)($destinatario['rol_nombre'] ?? 'Sin rol')); ?></span>
                                    </span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-10 gap-3">
                <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4" id="btn_preview_footer"><i class="ki-duotone ki-eye fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i> Actualizar preview</button>
                <button type="submit" class="btn btn-sm btn-primary fw-semibold px-4" <?php echo (!$tablas_disponibles || !$correo_configurado) ? 'disabled' : ''; ?>>
                    <i class="ki-duotone ki-paper-plane fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Enviar campaña
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="card-label fw-bold text-gray-900">Historial reciente</h3>
                <span class="text-muted fw-semibold fs-7">Últimas campañas registradas desde este módulo.</span>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th>Asunto</th>
                        <th>Plantilla</th>
                        <th>Remitente</th>
                        <th class="text-center">Destinatarios</th>
                        <th class="text-center">Enviados</th>
                        <th class="text-center">Fallidos</th>
                        <th class="text-center">Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-8">Aún no hay campañas registradas.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($historial as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-gray-900"><?php echo htmlspecialchars((string)$item['cpr_asunto']); ?></div>
                            <div class="text-muted fs-7">Por <?php echo htmlspecialchars((string)($item['cpr_usuario'] ?? 'sistema')); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars((string)$item['cpr_template']); ?></td>
                        <td><?php echo htmlspecialchars((string)$item['cpr_from_name'] . ' <' . (string)$item['cpr_from_email'] . '>'); ?></td>
                        <td class="text-center"><?php echo (int)$item['cpr_total_destinatarios']; ?></td>
                        <td class="text-center text-success fw-bold"><?php echo (int)$item['cpr_total_enviados']; ?></td>
                        <td class="text-center text-danger fw-bold"><?php echo (int)$item['cpr_total_fallidos']; ?></td>
                        <td class="text-center"><span class="badge badge-light-<?php echo $item['cpr_estado'] === 'enviado' ? 'success' : ($item['cpr_estado'] === 'parcial' ? 'warning' : 'danger'); ?>"><?php echo htmlspecialchars((string)$item['cpr_estado']); ?></span></td>
                        <td><?php echo htmlspecialchars((string)$item['cpr_created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!--begin::Modal Grupos de Destinatarios-->
<div class="modal fade" id="modal_grupos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold text-gray-900 d-flex align-items-center">
                    <i class="ki-duotone ki-people fs-2 text-primary me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    Grupos de Destinatarios
                </h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-2"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body p-6">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-5 fs-6" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active text-active-primary d-flex align-items-center pb-3" data-bs-toggle="tab" href="#kt_tab_grupos_lista" id="tab_link_grupos_lista" role="tab">
                            <i class="ki-duotone ki-element-11 fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                            Mis Grupos
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link text-active-primary d-flex align-items-center pb-3" data-bs-toggle="tab" href="#kt_tab_grupos_form" id="tab_link_grupos_form" role="tab">
                            <i class="ki-duotone ki-plus-circle fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                            <span id="titulo_tab_form">Nuevo Grupo</span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Listado de Grupos -->
                    <div class="tab-pane fade show active" id="kt_tab_grupos_lista" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <span class="text-muted fs-7">Crea y administra grupos para segmentar tus envíos con un solo clic.</span>
                            <button type="button" class="btn btn-sm btn-primary d-flex align-items-center" id="btn_modal_nuevo_grupo">
                                <i class="ki-duotone ki-plus fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Nuevo Grupo
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                        <th>Grupo</th>
                                        <th>Descripción</th>
                                        <th class="text-center">Integrantes</th>
                                        <th class="min-w-120px text-end pe-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody_modal_grupos">
                                    <!-- Rendered dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Formulario Grupo -->
                    <div class="tab-pane fade" id="kt_tab_grupos_form" role="tabpanel">
                        <form id="form_grupo" onsubmit="return false;">
                            <input type="hidden" name="cgr_id" id="grupo_cgr_id" value="0" />
                            <div class="row g-4">
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold required fs-7">Nombre del Grupo</label>
                                    <input type="text" name="cgr_nombre" id="grupo_cgr_nombre" class="form-control form-control-solid" placeholder="Ej: Usuarios Más Activos, Clientes VIP" maxlength="100" />
                                    <div class="text-danger fs-8 mt-1 d-none" id="err_cgr_nombre"></div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold fs-7">Color / Distintivo</label>
                                    <select name="cgr_color" id="grupo_cgr_color" class="form-select form-select-solid">
                                        <option value="primary">Azul (Primary)</option>
                                        <option value="success">Verde (Success)</option>
                                        <option value="info">Celeste (Info)</option>
                                        <option value="warning">Amarillo (Warning)</option>
                                        <option value="danger">Rojo (Danger)</option>
                                        <option value="dark">Oscuro (Dark)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold fs-7">Descripción</label>
                                    <input type="text" name="cgr_descripcion" id="grupo_cgr_descripcion" class="form-control form-control-solid" placeholder="Breve nota sobre los criterios del grupo" maxlength="255" />
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                        <label class="form-label fw-semibold fs-7 mb-0">Seleccionar Integrantes del Grupo</label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <button type="button" class="btn btn-sm btn-light-primary py-1 px-2 fs-8 d-flex align-items-center" id="btn_grupo_sel_todos">
                                                <i class="ki-duotone ki-check fs-6 me-1"><span class="path1"></span><span class="path2"></span></i> Todos
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light py-1 px-2 fs-8 d-flex align-items-center" id="btn_grupo_sel_ninguno">
                                                <i class="ki-duotone ki-cross fs-6 me-1"><span class="path1"></span><span class="path2"></span></i> Ninguno
                                            </button>
                                            <span class="badge badge-light-primary fs-8 ms-1" id="contador_modal_grupo">0 seleccionados</span>
                                        </div>
                                    </div>
                                    <input type="text" id="filtro_usuarios_modal_grupo" class="form-control form-control-solid mb-3 fs-7" placeholder="Buscar usuario por nombre o correo para agregar al grupo..." />
                                    <div class="border rounded p-3 bg-body" style="max-height: 220px; overflow-y: auto;">
                                        <div class="row g-2" id="lista_usuarios_modal_grupo">
                                            <?php foreach ($destinatarios as $dest): ?>
                                            <div class="col-md-6 modal-user-item" data-search="<?php echo htmlspecialchars(strtolower(($dest['usu_nombre'] ?? '') . ' ' . ($dest['usu_usuario'] ?? '') . ' ' . ($dest['usu_correo'] ?? '') . ' ' . ($dest['rol_nombre'] ?? ''))); ?>">
                                                <label class="d-flex align-items-center gap-2 p-2 border rounded bg-light bg-opacity-30 cursor-pointer">
                                                    <input type="checkbox" name="usuarios_ids[]" value="<?php echo htmlspecialchars((string)$dest['usu_id']); ?>" class="form-check-input grupo-usuario-chk" />
                                                    <span class="fs-7 text-truncate">
                                                        <span class="fw-bold text-gray-900 d-block"><?php echo htmlspecialchars((string)$dest['usu_nombre']); ?></span>
                                                        <span class="text-muted fs-8"><?php echo htmlspecialchars((string)$dest['usu_correo']); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-6">
                                <button type="button" class="btn btn-sm btn-light d-flex align-items-center" id="btn_cancelar_form_grupo">
                                    <i class="ki-duotone ki-cross fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-sm btn-primary d-flex align-items-center" id="btn_guardar_grupo">
                                    <i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Guardar Grupo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Grupos de Destinatarios-->

<script>
document.addEventListener('DOMContentLoaded', function () {
    var promoEditor = null;
    var filtro = document.getElementById('filtro_destinatarios');
    var items = Array.prototype.slice.call(document.querySelectorAll('.destinatario-item'));
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.promo-destinatario-checkbox'));
    var contador = document.getElementById('contador_destinatarios');
    var btnSeleccionar = document.getElementById('btn_seleccionar_visibles');
    var btnLimpiar = document.getElementById('btn_limpiar_destinatarios');
    var btnPreview = document.getElementById('btn_preview_promocion');
    var btnPreviewFooter = document.getElementById('btn_preview_footer');
    var previewFrame = document.getElementById('promo_preview_frame');
    var form = document.getElementById('form_promocional');
    var basePreviewUrl = '<?php echo base_url($url_base . '/preview'); ?>';
    var textareaBody = document.getElementById('promo_body_html');
    var csrfNameMeta = document.querySelector('meta[name="csrf-name"]');
    var csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');

    function getBodyHtml() {
        if (promoEditor) {
            return promoEditor.getData();
        }

        return textareaBody ? textareaBody.value : '';
    }

    function syncBodyHtml() {
        if (textareaBody) {
            textareaBody.value = getBodyHtml();
        }
    }

    function actualizarContador() {
        var total = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        contador.textContent = total + ' seleccionados';
    }

    function aplicarFiltro() {
        var query = (filtro.value || '').toLowerCase().trim();
        items.forEach(function (item) {
            var match = query === '' || item.getAttribute('data-search').indexOf(query) !== -1;
            item.style.display = match ? '' : 'none';
        });
    }

    function actualizarPreview() {
        syncBodyHtml();

        var templateField = form.querySelector('[name="promo_template"]');
        if (!templateField || !templateField.value) {
            if (previewFrame) {
                previewFrame.srcdoc = '<!doctype html><html lang="es"><body style="margin:0;padding:24px;background:#f5f8fa;font-family:Inter,Arial,sans-serif;color:#5e6278;"><p style="margin:0;">Selecciona una plantilla promocional para generar la vista previa.</p></body></html>';
            }
            return;
        }

        var tempForm = document.createElement('form');
        tempForm.method = 'post';
        tempForm.action = basePreviewUrl;
        tempForm.target = 'promo_preview_frame';
        tempForm.style.display = 'none';

        ['promo_template', 'promo_subject', 'promo_from_email', 'promo_from_name', 'promo_reply_to', 'promo_body_html'].forEach(function (name) {
            var source = form.querySelector('[name="' + name + '"]');
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = source ? source.value : '';
            tempForm.appendChild(input);
        });

        if (csrfNameMeta && csrfTokenMeta && csrfNameMeta.content && csrfTokenMeta.content) {
            var csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = csrfNameMeta.content;
            csrfInput.value = csrfTokenMeta.content;
            tempForm.appendChild(csrfInput);
        }

        document.body.appendChild(tempForm);
        tempForm.submit();
        document.body.removeChild(tempForm);
    }

    filtro.addEventListener('input', aplicarFiltro);
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', actualizarContador);
    });

    btnSeleccionar.addEventListener('click', function () {
        items.forEach(function (item) {
            if (item.style.display === 'none') {
                return;
            }
            var checkbox = item.querySelector('.promo-destinatario-checkbox');
            if (checkbox) {
                checkbox.checked = true;
            }
        });
        actualizarContador();
    });

    btnLimpiar.addEventListener('click', function () {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = false;
        });
        actualizarContador();
    });

    btnPreview.addEventListener('click', actualizarPreview);
    btnPreviewFooter.addEventListener('click', actualizarPreview);
    form.addEventListener('submit', function () {
        syncBodyHtml();
    });

    if (typeof ClassicEditor !== 'undefined' && textareaBody) {
        ClassicEditor.create(textareaBody, {
            toolbar: [
                'undo', 'redo', '|',
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'link', 'bulletedList', 'numberedList', '|',
                'blockQuote', 'insertTable', '|',
                'outdent', 'indent'
            ],
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            }
        }).then(function (editor) {
            promoEditor = editor;
            editor.model.document.on('change:data', function () {
                syncBodyHtml();
            });

            actualizarPreview();
        }).catch(function (error) {
            console.error('No se pudo inicializar CKEditor en Correos Promocionales:', error);
        });
    }

    actualizarContador();
    if (previewFrame && !promoEditor) {
        actualizarPreview();
    }

    // ==========================================
    // GESTIÓN DE GRUPOS DE DESTINATARIOS
    // ==========================================
    var gruposData = <?php echo json_encode($grupos ?? []); ?>;
    var modalGruposEl = document.getElementById('modal_grupos');
    var modalGrupos = modalGruposEl ? new bootstrap.Modal(modalGruposEl) : null;
    var btnAbrirModalGrupos = document.getElementById('btn_abrir_modal_grupos');
    var tabLinkLista = document.getElementById('tab_link_grupos_lista');
    var tabLinkForm = document.getElementById('tab_link_grupos_form');
    var btnModalNuevoGrupo = document.getElementById('btn_modal_nuevo_grupo');
    var btnCancelarFormGrupo = document.getElementById('btn_cancelar_form_grupo');
    var btnGuardarGrupo = document.getElementById('btn_guardar_grupo');
    var tbodyGrupos = document.getElementById('tbody_modal_grupos');
    var listaPillsGrupos = document.getElementById('lista_pills_grupos');
    var formGrupo = document.getElementById('form_grupo');
    var tituloTabForm = document.getElementById('titulo_tab_form');
    var chkModalGrupo = Array.prototype.slice.call(document.querySelectorAll('.grupo-usuario-chk'));
    var filtroModalGrupo = document.getElementById('filtro_usuarios_modal_grupo');
    var itemsModalGrupo = Array.prototype.slice.call(document.querySelectorAll('.modal-user-item'));
    var contadorModalGrupo = document.getElementById('contador_modal_grupo');
    var btnGrupoSelTodos = document.getElementById('btn_grupo_sel_todos');
    var btnGrupoSelNinguno = document.getElementById('btn_grupo_sel_ninguno');

    var urlGrupoDatos = '<?php echo base_url($url_base . '/grupo_datos/'); ?>';
    var urlGrupoUsuarios = '<?php echo base_url($url_base . '/grupo_usuarios/'); ?>';
    var urlGrupoGuardar = '<?php echo base_url($url_base . '/grupo_guardar'); ?>';
    var urlGrupoEliminar = '<?php echo base_url($url_base . '/grupo_eliminar/'); ?>';

    function actualizarContadorModalGrupo() {
        if (!contadorModalGrupo) return;
        var total = chkModalGrupo.filter(function (chk) { return chk.checked; }).length;
        contadorModalGrupo.textContent = total + ' seleccionados';
    }

    function renderGruposPills() {
        if (!listaPillsGrupos) return;
        if (!gruposData || gruposData.length === 0) {
            listaPillsGrupos.innerHTML = '<span class="text-muted fs-8 fst-italic" id="txt_sin_grupos">No hay grupos creados aún. Haz clic en "Crear / Gestionar Grupos".</span>';
            return;
        }

        var html = '';
        gruposData.forEach(function (g) {
            var color = g.cgr_color || 'primary';
            var total = parseInt(g.total_usuarios || 0, 10);
            html += '<button type="button" class="btn btn-sm btn-outline btn-outline-dashed btn-outline-' + color + ' py-1 px-3 d-flex align-items-center btn-aplicar-grupo" data-grupo-id="' + g.cgr_id + '" data-grupo-nombre="' + escapeHtml(g.cgr_nombre) + '" title="Haz clic para seleccionar los ' + total + ' usuarios de este grupo">';
            html += '<span class="bullet bullet-dot bg-' + color + ' me-1"></span>';
            html += escapeHtml(g.cgr_nombre);
            html += '<span class="badge badge-light-' + color + ' fs-9 ms-1 px-1 py-0">' + total + '</span>';
            html += '</button>';
        });
        listaPillsGrupos.innerHTML = html;
    }

    function renderGruposTable() {
        if (!tbodyGrupos) return;
        if (!gruposData || gruposData.length === 0) {
            tbodyGrupos.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-6">No has creado grupos aún. Haz clic en "Nuevo Grupo" para comenzar.</td></tr>';
            return;
        }

        var html = '';
        gruposData.forEach(function (g) {
            var color = g.cgr_color || 'primary';
            var total = parseInt(g.total_usuarios || 0, 10);
            html += '<tr>';
            html += '<td><span class="badge badge-light-' + color + ' fs-7 fw-bold"><span class="bullet bullet-dot bg-' + color + ' me-1"></span>' + escapeHtml(g.cgr_nombre) + '</span></td>';
            html += '<td class="text-muted fs-7">' + escapeHtml(g.cgr_descripcion || 'Sin descripción') + '</td>';
            html += '<td class="text-center"><span class="badge badge-light fw-bold fs-7">' + total + ' usuarios</span></td>';
            html += '<td class="text-end pe-2 text-nowrap">';
            html += '<div class="d-flex justify-content-end align-items-center gap-2">';
            html += '<button type="button" class="btn btn-sm btn-icon btn-light-success shadow-xs btn-usar-grupo" data-grupo-id="' + g.cgr_id + '" data-grupo-nombre="' + escapeHtml(g.cgr_nombre) + '" data-bs-toggle="tooltip" data-bs-placement="top" title="Seleccionar en campaña" aria-label="Seleccionar en campaña">';
            html += '<i class="ki-duotone ki-check-square fs-4"><span class="path1"></span><span class="path2"></span></i>';
            html += '</button>';
            html += '<button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-editar-grupo" data-grupo-id="' + g.cgr_id + '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar grupo" aria-label="Editar grupo">';
            html += '<i class="ki-duotone ki-pencil fs-4"><span class="path1"></span><span class="path2"></span></i>';
            html += '</button>';
            html += '<button type="button" class="btn btn-sm btn-icon btn-light-danger shadow-xs btn-eliminar-grupo" data-grupo-id="' + g.cgr_id + '" data-grupo-nombre="' + escapeHtml(g.cgr_nombre) + '" data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar grupo" aria-label="Eliminar grupo">';
            html += '<i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>';
            html += '</button>';
            html += '</div>';
            html += '</td>';
            html += '</tr>';
        });
        tbodyGrupos.innerHTML = html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function aplicarGrupo(grupoId, grupoNombre) {
        fetch(urlGrupoUsuarios + grupoId)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) {
                    Swal.fire('Error', res.msg || 'No se pudieron obtener los usuarios del grupo.', 'error');
                    return;
                }
                var ids = (res.usuarios_ids || []).map(function(id) { return String(id).trim(); });
                var seleccionados = 0;
                checkboxes.forEach(function (chk) {
                    if (ids.indexOf(String(chk.value).trim()) !== -1) {
                        chk.checked = true;
                        seleccionados++;
                    }
                });
                actualizarContador();
                if (modalGrupos) {
                    modalGrupos.hide();
                }
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Grupo "' + grupoNombre + '" aplicado (' + seleccionados + ' usuarios seleccionados)',
                    showConfirmButton: false,
                    timer: 3000
                });
            })
            .catch(function () {
                Swal.fire('Error', 'Error de conexión al cargar usuarios del grupo.', 'error');
            });
    }

    function limpiarFormGrupo() {
        document.getElementById('grupo_cgr_id').value = '0';
        document.getElementById('grupo_cgr_nombre').value = '';
        document.getElementById('grupo_cgr_descripcion').value = '';
        document.getElementById('grupo_cgr_color').value = 'primary';
        document.getElementById('err_cgr_nombre').classList.add('d-none');
        document.getElementById('filtro_usuarios_modal_grupo').value = '';
        chkModalGrupo.forEach(function (chk) { chk.checked = false; });
        itemsModalGrupo.forEach(function (item) { item.style.display = ''; });
        tituloTabForm.textContent = 'Nuevo Grupo';
        actualizarContadorModalGrupo();
    }

    function editarGrupo(grupoId) {
        limpiarFormGrupo();
        fetch(urlGrupoDatos + grupoId)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.data) {
                    Swal.fire('Error', res.msg || 'No se pudieron obtener los datos del grupo.', 'error');
                    return;
                }
                var d = res.data;
                document.getElementById('grupo_cgr_id').value = d.cgr_id;
                document.getElementById('grupo_cgr_nombre').value = d.cgr_nombre || '';
                document.getElementById('grupo_cgr_descripcion').value = d.cgr_descripcion || '';
                document.getElementById('grupo_cgr_color').value = d.cgr_color || 'primary';
                tituloTabForm.textContent = 'Editar Grupo: ' + (d.cgr_nombre || '');

                var uids = (d.usuarios_ids || []).map(function(id) { return String(id).trim(); });
                chkModalGrupo.forEach(function (chk) {
                    chk.checked = (uids.indexOf(String(chk.value).trim()) !== -1);
                });
                actualizarContadorModalGrupo();

                var tab = new bootstrap.Tab(tabLinkForm);
                tab.show();
            })
            .catch(function () {
                Swal.fire('Error', 'Error de red al cargar el grupo.', 'error');
            });
    }

    function eliminarGrupo(grupoId, grupoNombre) {
        Swal.fire({
            title: '¿Eliminar grupo?',
            text: 'Se eliminará el grupo "' + grupoNombre + '". Los usuarios no se verán afectados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-sm btn-danger',
                cancelButton: 'btn btn-sm btn-light'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                fetch(urlGrupoEliminar + grupoId, { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.ok) {
                            gruposData = gruposData.filter(function (g) { return parseInt(g.cgr_id, 10) !== parseInt(grupoId, 10); });
                            renderGruposPills();
                            renderGruposTable();
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Grupo eliminado correctamente',
                                showConfirmButton: false,
                                timer: 2500
                            });
                        } else {
                            Swal.fire('Error', res.msg || 'No se pudo eliminar el grupo.', 'error');
                        }
                    })
                    .catch(function () {
                        Swal.fire('Error', 'Error de conexión al eliminar el grupo.', 'error');
                    });
            }
        });
    }

    function guardarGrupo() {
        var nombre = document.getElementById('grupo_cgr_nombre').value.trim();
        var errEl = document.getElementById('err_cgr_nombre');
        if (nombre === '') {
            errEl.textContent = 'El nombre del grupo es obligatorio.';
            errEl.classList.remove('d-none');
            return;
        }
        errEl.classList.add('d-none');

        btnGuardarGrupo.disabled = true;
        btnGuardarGrupo.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        var formData = new FormData(formGrupo);
        fetch(urlGrupoGuardar, {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btnGuardarGrupo.disabled = false;
            btnGuardarGrupo.innerHTML = '<i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Guardar Grupo';

            if (!res.ok) {
                if (res.errores && res.errores.cgr_nombre) {
                    errEl.textContent = res.errores.cgr_nombre;
                    errEl.classList.remove('d-none');
                } else {
                    Swal.fire('Error', res.msg || 'No se pudo guardar el grupo.', 'error');
                }
                return;
            }

            var idGuardado = parseInt(res.grupo_id, 10);
            var color = document.getElementById('grupo_cgr_color').value;
            var desc = document.getElementById('grupo_cgr_descripcion').value.trim();
            var totalUsuarios = chkModalGrupo.filter(function (chk) { return chk.checked; }).length;

            var idx = gruposData.findIndex(function (g) { return parseInt(g.cgr_id, 10) === idGuardado; });
            if (idx !== -1) {
                gruposData[idx].cgr_nombre = nombre;
                gruposData[idx].cgr_descripcion = desc;
                gruposData[idx].cgr_color = color;
                gruposData[idx].total_usuarios = totalUsuarios;
            } else {
                gruposData.push({
                    cgr_id: idGuardado,
                    cgr_nombre: nombre,
                    cgr_descripcion: desc,
                    cgr_color: color,
                    cgr_status: 1,
                    total_usuarios: totalUsuarios
                });
            }

            renderGruposPills();
            renderGruposTable();
            limpiarFormGrupo();

            var tab = new bootstrap.Tab(tabLinkLista);
            tab.show();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: res.msg || 'Grupo guardado exitosamente',
                showConfirmButton: false,
                timer: 2500
            });
        })
        .catch(function () {
            btnGuardarGrupo.disabled = false;
            btnGuardarGrupo.innerHTML = '<i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Guardar Grupo';
            Swal.fire('Error', 'Error de red al guardar el grupo.', 'error');
        });
    }

    if (listaPillsGrupos) {
        listaPillsGrupos.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-aplicar-grupo');
            if (!btn) return;
            var id = btn.getAttribute('data-grupo-id');
            var nombre = btn.getAttribute('data-grupo-nombre');
            aplicarGrupo(id, nombre);
        });
    }

    if (tbodyGrupos) {
        tbodyGrupos.addEventListener('click', function (e) {
            var btnUsar = e.target.closest('.btn-usar-grupo');
            if (btnUsar) {
                aplicarGrupo(btnUsar.getAttribute('data-grupo-id'), btnUsar.getAttribute('data-grupo-nombre'));
                return;
            }
            var btnEdit = e.target.closest('.btn-editar-grupo');
            if (btnEdit) {
                editarGrupo(btnEdit.getAttribute('data-grupo-id'));
                return;
            }
            var btnDel = e.target.closest('.btn-eliminar-grupo');
            if (btnDel) {
                eliminarGrupo(btnDel.getAttribute('data-grupo-id'), btnDel.getAttribute('data-grupo-nombre'));
                return;
            }
        });
    }

    if (btnAbrirModalGrupos) {
        btnAbrirModalGrupos.addEventListener('click', function () {
            renderGruposTable();
            var tab = new bootstrap.Tab(tabLinkLista);
            tab.show();
            if (modalGrupos) modalGrupos.show();
        });
    }

    if (btnModalNuevoGrupo) {
        btnModalNuevoGrupo.addEventListener('click', function () {
            limpiarFormGrupo();
            var tab = new bootstrap.Tab(tabLinkForm);
            tab.show();
        });
    }

    if (btnCancelarFormGrupo) {
        btnCancelarFormGrupo.addEventListener('click', function () {
            limpiarFormGrupo();
            var tab = new bootstrap.Tab(tabLinkLista);
            tab.show();
        });
    }

    if (btnGuardarGrupo) {
        btnGuardarGrupo.addEventListener('click', guardarGrupo);
    }

    if (filtroModalGrupo) {
        filtroModalGrupo.addEventListener('input', function () {
            var q = (this.value || '').toLowerCase().trim();
            itemsModalGrupo.forEach(function (item) {
                var match = q === '' || item.getAttribute('data-search').indexOf(q) !== -1;
                item.style.display = match ? '' : 'none';
            });
        });
    }

    if (btnGrupoSelTodos) {
        btnGrupoSelTodos.addEventListener('click', function () {
            itemsModalGrupo.forEach(function (item) {
                if (item.style.display !== 'none') {
                    var chk = item.querySelector('.grupo-usuario-chk');
                    if (chk) chk.checked = true;
                }
            });
            actualizarContadorModalGrupo();
        });
    }

    if (btnGrupoSelNinguno) {
        btnGrupoSelNinguno.addEventListener('click', function () {
            chkModalGrupo.forEach(function (chk) { chk.checked = false; });
            actualizarContadorModalGrupo();
        });
    }

    chkModalGrupo.forEach(function (chk) {
        chk.addEventListener('change', actualizarContadorModalGrupo);
    });

    renderGruposTable();
    actualizarContadorModalGrupo();

});
</script>