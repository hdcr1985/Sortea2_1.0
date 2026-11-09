<div class="card mb-7">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="card-label fw-bold text-dark">Configuración de Correos</h3>
                <span class="text-muted fw-semibold fs-7">Define el remitente y la conexión SMTP que usará el sistema para notificaciones, bienvenida y recuperación de clave.</span>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <?php if (!$tabla_disponible): ?>
        <div class="alert alert-warning d-flex align-items-start p-5 mb-8">
            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
            </i>
            <div>
                <div class="fw-bold mb-1">Falta la tabla de configuración</div>
                <div>Ejecuta el script <code>database/2026-05-05_create_cab_configuracion_correo.sql</code> para persistir los cambios.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success mb-8"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-8"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo base_url($url_base . '/guardar'); ?>">
            <div class="row g-6">
                <div class="col-12">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Estado General</h4>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="mail_enabled" name="mail_enabled" <?php echo !empty($mail_settings['mail_enabled_bool']) ? 'checked' : ''; ?> />
                        <label class="form-check-label fw-semibold text-gray-700 ms-3" for="mail_enabled">
                            Habilitar correo saliente del sistema
                        </label>
                    </div>
                    <div class="text-muted fs-7 mt-2">Si está apagado, futuras integraciones deberían omitir envíos automáticos.</div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="mail_debug" name="mail_debug" <?php echo !empty($mail_settings['mail_debug_bool']) ? 'checked' : ''; ?> />
                        <label class="form-check-label fw-semibold text-gray-700 ms-3" for="mail_debug">
                            Activar modo debug SMTP
                        </label>
                    </div>
                    <div class="text-muted fs-7 mt-2">Útil para diagnósticos cuando se integre el envío real de mensajes.</div>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Remitente</h4>
                </div>

                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Correo remitente</label>
                    <input type="email" name="mail_from_email" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)$mail_settings['mail_from_email']); ?>" />
                    <?php if (!empty($errores['mail_from_email'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_from_email']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Nombre remitente</label>
                    <input type="text" name="mail_from_name" class="form-control form-control-solid" maxlength="120" value="<?php echo htmlspecialchars((string)$mail_settings['mail_from_name']); ?>" />
                    <?php if (!empty($errores['mail_from_name'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_from_name']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reply-To</label>
                    <input type="email" name="mail_reply_to" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)$mail_settings['mail_reply_to']); ?>" />
                    <?php if (!empty($errores['mail_reply_to'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_reply_to']); ?></div><?php endif; ?>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Servidor SMTP</h4>
                </div>

                <div class="col-md-3">
                    <label class="form-label required fw-semibold">Driver</label>
                    <select name="mail_driver" class="form-select form-select-solid">
                        <?php foreach ($driver_options as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $mail_settings['mail_driver'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['mail_driver'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_driver']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label required fw-semibold">Servidor</label>
                    <input type="text" name="mail_host" class="form-control form-control-solid" maxlength="190" placeholder="smtp.tudominio.com" value="<?php echo htmlspecialchars((string)$mail_settings['mail_host']); ?>" />
                    <?php if (!empty($errores['mail_host'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_host']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label required fw-semibold">Puerto</label>
                    <input type="number" name="mail_port" class="form-control form-control-solid" min="1" max="65535" value="<?php echo htmlspecialchars((string)$mail_settings['mail_port']); ?>" />
                    <?php if (!empty($errores['mail_port'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_port']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label required fw-semibold">Cifrado</label>
                    <select name="mail_encryption" class="form-select form-select-solid">
                        <?php foreach ($encryption_options as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $mail_settings['mail_encryption'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['mail_encryption'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_encryption']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Usuario SMTP</label>
                    <input type="text" name="mail_user" class="form-control form-control-solid" maxlength="150" value="<?php echo htmlspecialchars((string)$mail_settings['mail_user']); ?>" />
                    <?php if (!empty($errores['mail_user'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_user']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Clave SMTP</label>
                    <input type="password" name="mail_password" class="form-control form-control-solid" maxlength="190" value="" placeholder="<?php echo !empty($mail_settings['mail_password']) ? '•••••••••• (dejar vacío para conservar)' : 'Sin clave configurada'; ?>" autocomplete="new-password" />
                    <div class="text-muted fs-7 mt-1">Por seguridad la clave no se muestra. Déjala vacía para conservar la actual.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label required fw-semibold">Timeout (segundos)</label>
                    <input type="number" name="mail_timeout" class="form-control form-control-solid" min="5" max="120" value="<?php echo htmlspecialchars((string)$mail_settings['mail_timeout']); ?>" />
                    <?php if (!empty($errores['mail_timeout'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_timeout']); ?></div><?php endif; ?>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Formato</h4>
                </div>

                <div class="col-md-3">
                    <label class="form-label required fw-semibold">Tipo de correo</label>
                    <select name="mailtype" class="form-select form-select-solid">
                        <?php foreach ($mailtype_options as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $mail_settings['mailtype'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['mailtype'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mailtype']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required fw-semibold">Charset</label>
                    <input type="text" name="mail_charset" class="form-control form-control-solid" maxlength="20" value="<?php echo htmlspecialchars((string)$mail_settings['mail_charset']); ?>" />
                    <?php if (!empty($errores['mail_charset'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_charset']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Plantilla HTML predeterminada</label>
                    <select name="mail_template_default" class="form-select form-select-solid">
                        <option value="">Selecciona una plantilla</option>
                        <?php foreach ($mail_templates as $template): ?>
                        <option value="<?php echo htmlspecialchars($template['slug']); ?>" <?php echo $mail_settings['mail_template_default'] === $template['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($template['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['mail_template_default'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_template_default']); ?></div><?php endif; ?>
                    <?php if (!empty($mail_settings['mail_template_view'])): ?>
                    <div class="text-muted fs-7 mt-1">Vista activa: <?php echo htmlspecialchars((string)$mail_settings['mail_template_view']); ?></div>
                    <?php else: ?>
                    <div class="text-muted fs-7 mt-1">Selecciona una plantilla HTML para previsualizarla. Puedes editar su contenido en el editor de plantillas de abajo.</div>
                    <?php endif; ?>
                    <?php if (!empty($mail_templates)): ?>
                    <div class="d-flex gap-3 mt-4">
                        <a href="<?php echo base_url($url_base . '/preview/' . rawurlencode((string)$mail_settings['mail_template_default'])); ?>"
                           target="_blank"
                           id="mail_template_preview_link"
                           class="btn btn-light-primary btn-sm">
                            Abrir preview
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <div class="rounded border border-dashed border-gray-300 bg-light p-5 h-100 d-flex flex-column justify-content-center">
                        <div class="fw-semibold text-gray-800 mb-1">Plantillas detectadas</div>
                        <div class="fs-2hx fw-bold text-primary lh-1"><?php echo count($mail_templates); ?></div>
                        <div class="text-muted fs-7 mt-1">Al agregar nuevas vistas al directorio, aparecerán automáticamente en el selector.</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label required fw-semibold">Newline</label>
                    <select name="mail_newline" class="form-select form-select-solid">
                        <?php foreach (["\r\n" => '\\r\\n', "\n" => '\\n', "\r" => '\\r'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $mail_settings['mail_newline'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['mail_newline'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_newline']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required fw-semibold">CRLF</label>
                    <select name="mail_crlf" class="form-select form-select-solid">
                        <?php foreach (["\r\n" => '\\r\\n', "\n" => '\\n', "\r" => '\\r'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $mail_settings['mail_crlf'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['mail_crlf'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['mail_crlf']); ?></div><?php endif; ?>
                </div>

                <div class="col-12">
                    <div class="alert alert-light-primary border border-primary border-dashed mt-6 mb-0">
                        <div class="fw-semibold mb-1">Siguiente integración</div>
                        <div class="text-muted fs-7">Este módulo ya deja la configuración lista para consumirse desde la librería Email de CodeIgniter en recuperación de clave, bienvenida y notificaciones, usando una plantilla única predeterminada.</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border border-dashed border-gray-300 mt-6 shadow-sm">
                        <div class="card-header border-0 py-4">
                            <div class="card-title">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-900">Correo de prueba</span>
                                    <span class="text-muted fs-7">Guarda primero la configuración y luego envía un correo de validación con esos valores.</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row g-4 align-items-end">
                                <div class="col-lg-8">
                                    <label class="form-label fw-semibold">Correo destino</label>
                                    <input type="email" name="test_target_email" form="mail_test_form" class="form-control form-control-solid" maxlength="150" placeholder="destino@dominio.com" value="<?php echo htmlspecialchars((string)$test_target_email); ?>" />
                                    <div class="text-muted fs-7 mt-1">El envío usa la configuración guardada actualmente en este módulo.</div>
                                </div>
                                <div class="col-lg-4">
                                    <button type="submit" form="mail_test_form" class="btn btn-light-success w-100">
                                        Enviar correo de prueba
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (empty($mail_templates)): ?>
                <div class="col-12">
                    <div class="alert alert-warning mt-2 mb-0">
                        Aún no hay plantillas cargadas. Cuando compartas el HTML de Metronic, se puede convertir en vistas reutilizables y quedarán disponibles aquí.
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($mail_templates)): ?>
                <div class="col-12 mt-4">
                    <div class="card border border-dashed border-gray-300 shadow-sm">
                        <div class="card-header border-0 py-4">
                            <div class="card-title">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-900">Vista previa</span>
                                    <span class="text-muted fs-7">La previsualización usa datos de ejemplo y se actualiza según la plantilla seleccionada.</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <iframe
                                id="mail_template_preview_frame"
                                title="Vista previa de plantilla de correo"
                                src="<?php echo base_url($url_base . '/preview/' . rawurlencode((string)$mail_settings['mail_template_default'])); ?>"
                                style="width:100%; min-height:720px; border:1px solid #eff2f5; border-radius:0.625rem; background:#f5f8fa;">
                            </iframe>
                        </div>
                    </div>
                </div>
                <div class="col-12 mt-4">
                    <div class="card border border-dashed border-gray-300 shadow-sm">
                        <div class="card-header border-0 py-4">
                            <div class="card-title">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-900">Editor HTML de plantillas</span>
                                    <span class="text-muted fs-7">Edita asunto y diseño de cada plantilla. Los cambios se guardan en la base de datos y se usan en los envíos.</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="alert alert-light-info">
                                Variables disponibles: <code>{{email_title}}</code>, <code>{{email_preheader}}</code>, <code>{{email_heading}}</code>,
                                <code>{{recipient_name}}</code>, <code>{{app_name}}</code>, <code>{{action_label}}</code>, <code>{{action_url}}</code>,
                                <code>{{email_logo_url}}</code>, <code>{{email_footer}}</code>, <code>{{email_help_text}}</code>.
                                Para promociones también puedes usar <code>{{recipient_email}}</code>, <code>{{nombre}}</code> y <code>{{correo}}</code>.
                                Contenido HTML dinámico: <code>{{{email_body_html}}}</code>, <code>{{{intro_lines_html}}}</code>,
                                <code>{{{steps_html}}}</code>, <code>{{{tips_html}}}</code>, <code>{{{summary_items_html}}}</code>.
                                No se ejecuta PHP. No incluyas scripts, formularios ni atributos JavaScript.
                            </div>
                            <?php foreach ($mail_template_editors as $slug => $template): ?>
                            <details class="border rounded p-4 mb-3">
                                <summary class="fw-bold cursor-pointer"><?php echo htmlspecialchars($template['name']); ?></summary>
                                <div class="mt-4">
                                    <label class="form-label fw-semibold">Asunto predeterminado</label>
                                    <input type="text" name="mail_templates[<?php echo htmlspecialchars($slug); ?>][subject]" class="form-control form-control-solid mb-4" maxlength="180" value="<?php echo htmlspecialchars((string)$template['subject']); ?>" />
                                    <?php if (!empty($errores['mail_template_editors_' . $slug . '_subject'])): ?><div class="text-danger fs-7 mb-3"><?php echo htmlspecialchars($errores['mail_template_editors_' . $slug . '_subject']); ?></div><?php endif; ?>
                                    <label class="form-label fw-semibold">HTML completo</label>
                                    <textarea name="mail_templates[<?php echo htmlspecialchars($slug); ?>][html]" class="form-control form-control-solid font-monospace" rows="20" spellcheck="false"><?php echo htmlspecialchars((string)$template['html']); ?></textarea>
                                    <?php if (!empty($errores['mail_template_editors_' . $slug . '_html'])): ?><div class="text-danger fs-7 mt-2"><?php echo htmlspecialchars($errores['mail_template_editors_' . $slug . '_html']); ?></div><?php endif; ?>
                                </div>
                            </details>
                            <?php endforeach; ?>

                            <h5 class="fw-bold mt-8 mb-4">Diseños de campañas promocionales</h5>
                            <div class="text-muted fs-7 mb-4">El asunto de cada campaña se define en Correos Promocionales; aquí puedes editar el HTML y el diseño base.</div>
                            <?php foreach ($promo_template_editors as $slug => $template): ?>
                            <details class="border rounded p-4 mb-3">
                                <summary class="fw-bold cursor-pointer"><?php echo htmlspecialchars($template['name']); ?></summary>
                                <div class="mt-4">
                                    <textarea name="promo_templates[<?php echo htmlspecialchars($slug); ?>][html]" class="form-control form-control-solid font-monospace" rows="20" spellcheck="false"><?php echo htmlspecialchars((string)$template['html']); ?></textarea>
                                    <?php if (!empty($errores['promo_template_editors_' . $slug . '_html'])): ?><div class="text-danger fs-7 mt-2"><?php echo htmlspecialchars($errores['promo_template_editors_' . $slug . '_html']); ?></div><?php endif; ?>
                                </div>
                            </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    var select = document.querySelector('select[name="mail_template_default"]');
                    var frame = document.getElementById('mail_template_preview_frame');
                    var link = document.getElementById('mail_template_preview_link');

                    if (!select || !frame || !link) {
                        return;
                    }

                    function updatePreview() {
                        var slug = select.value || '';
                        if (!slug) {
                            frame.removeAttribute('src');
                            link.setAttribute('href', '#');
                            link.classList.add('disabled');
                            return;
                        }

                        var url = '<?php echo base_url($url_base . '/preview/'); ?>' + encodeURIComponent(slug);
                        frame.setAttribute('src', url);
                        link.setAttribute('href', url);
                        link.classList.remove('disabled');
                    }

                    select.addEventListener('change', updatePreview);
                    updatePreview();
                })();
                </script>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end mt-10">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold px-4 py-2">
                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Guardar configuración
                </button>
            </div>
        </form>
        <form id="mail_test_form" method="post" action="<?php echo base_url($url_base . '/probar'); ?>"></form>
    </div>
</div>