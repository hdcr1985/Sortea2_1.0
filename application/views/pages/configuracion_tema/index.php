<div class="card mb-7">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="card-label fw-bold text-gray-900">Configuración del Tema</h3>
                <span class="text-muted fw-semibold fs-7">Personaliza identidad, login, logos, colores, tipografía y footer del sistema.</span>
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
                <div>Ejecuta el script <code>database/2026-05-02_create_cab_configuracion_tema.sql</code> para persistir los cambios.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success mb-8"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-8"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo base_url('ConfiguracionTema/guardar'); ?>" enctype="multipart/form-data">
            <div class="row g-6">
                <div class="col-12">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Identidad del Proyecto</h4>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nombre del proyecto</label>
                    <input type="text" name="project_name" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['project_name']); ?>" maxlength="120" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Familia tipográfica</label>
                    <select name="font_family" class="form-select form-select-solid">
                        <?php foreach ($font_options as $font_value => $font_label): ?>
                        <option value="<?php echo htmlspecialchars($font_value); ?>" <?php echo $theme_settings['font_family'] === $font_value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($font_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted fs-7 mt-1">Selecciona una fuente precargada o elige Google Font personalizada.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Google Font personalizada</label>
                    <input type="text" name="google_font_family" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['google_font_family'] ?? ''); ?>" maxlength="80" placeholder="Ej: Quicksand" />
                    <div class="text-muted fs-7 mt-1">Si eliges Google Font personalizada, escribe aquí el nombre exacto de la fuente.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Texto del footer</label>
                    <input type="text" name="footer_text" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['footer_text']); ?>" maxlength="180" />
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Etiqueta soporte</label>
                    <input type="text" name="footer_support_label" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['footer_support_label']); ?>" maxlength="60" />
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">URL soporte</label>
                    <input type="text" name="footer_support_url" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['footer_support_url']); ?>" maxlength="255" />
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Login</h4>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Título lateral del login</label>
                    <input type="text" name="login_welcome_title" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['login_welcome_title']); ?>" maxlength="120" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Texto lateral del login</label>
                    <input type="text" name="login_welcome_subtitle" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['login_welcome_subtitle']); ?>" maxlength="180" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Título del formulario login</label>
                    <input type="text" name="login_form_title" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['login_form_title']); ?>" maxlength="120" />
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subtítulo del formulario login</label>
                    <input type="text" name="login_form_subtitle" class="form-control form-control-solid" value="<?php echo htmlspecialchars($theme_settings['login_form_subtitle']); ?>" maxlength="180" />
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Registro público de usuarios</h4>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="public_registration_enabled" name="public_registration_enabled" <?php echo ($theme_settings['public_registration_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> />
                        <label class="form-check-label fw-semibold text-gray-700 ms-3" for="public_registration_enabled">
                            Permitir registro público
                        </label>
                    </div>
                    <div class="text-muted fs-7 mt-2">Los registros nuevos permanecen inactivos hasta confirmar el correo. El rol no se elige en el formulario público y la cuenta no se asigna a una sucursal.</div>
                    <?php if (empty($verification_table_available)): ?>
                    <div class="text-warning fs-7 mt-2">Prepara la tabla: usa <code>database/2026-10-23_create_cab_usuario_verificacion.sql</code> en instalaciones nuevas o <code>database/2026-10-23_upgrade_cab_usuario_verificacion_token_types.sql</code> si ya existe con el esquema anterior.</div>
                    <?php endif; ?>
                    <?php if (empty($registration_email_ready)): ?>
                    <div class="text-warning fs-7 mt-2">Configura y habilita el correo saliente en Configuración de Correos antes de permitir registros.</div>
                    <?php endif; ?>
                    <?php if (!empty($errores['public_registration_enabled'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['public_registration_enabled']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Rol asignado al registro público</label>
                    <select name="public_registration_role_id" class="form-select form-select-solid">
                        <option value="">Selecciona un rol</option>
                        <?php foreach ($registration_roles as $role): ?>
                        <option value="<?php echo htmlspecialchars((string)$role['per_id']); ?>" <?php echo (string)($theme_settings['public_registration_role_id'] ?? '') === (string)$role['per_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$role['per_nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted fs-7 mt-2">Admin Master no está disponible para registro público.</div>
                    <?php if (!empty($errores['public_registration_role_id'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['public_registration_role_id']); ?></div><?php endif; ?>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-1">Inicio de Sesión con Redes Sociales (OAuth)</h4>
                    <div class="text-muted fs-7 mb-4">Habilita y configura los botones de Google y Apple en la pantalla de inicio de sesión.</div>
                </div>

                <!--begin::Google OAuth-->
                <div class="col-md-6">
                    <div class="card card-bordered p-5 h-100 bg-light-primary bg-opacity-10">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?php echo base_url('assets/media/svg/brand-logos/google.svg'); ?>" class="h-25px" alt="Google" />
                                <span class="fw-bold fs-6">Google Sign-In</span>
                            </div>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input h-20px w-35px" type="checkbox" name="google_auth_enabled" value="1" id="switch_google_auth" <?php echo !empty($theme_settings['google_auth_enabled']) ? 'checked' : ''; ?> />
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fs-7 fw-semibold">Google Client ID</label>
                            <input type="text" name="google_client_id" class="form-control form-control-solid fs-7" placeholder="ej. 123456789-abc.apps.googleusercontent.com" value="<?php echo htmlspecialchars($theme_settings['google_client_id'] ?? ''); ?>" />
                            <?php if (!empty($errores['google_client_id'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['google_client_id']); ?></div><?php endif; ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fs-7 fw-semibold">Google Client Secret</label>
                            <input type="password" name="google_client_secret" class="form-control form-control-solid fs-7" placeholder="••••••••••••••••••••" value="<?php echo htmlspecialchars($theme_settings['google_client_secret'] ?? ''); ?>" autocomplete="new-password" />
                        </div>
                        <div class="bg-body border rounded p-3 mt-auto">
                            <span class="text-muted fs-8 d-block mb-1 fw-bold">URI de redirección autorizada en Google Cloud:</span>
                            <code class="fs-8 text-primary user-select-all"><?php echo base_url('auth/oauth-google-callback'); ?></code>
                        </div>
                    </div>
                </div>
                <!--end::Google OAuth-->

                <!--begin::Apple OAuth-->
                <div class="col-md-6">
                    <div class="card card-bordered p-5 h-100 bg-light-dark bg-opacity-10">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?php echo base_url('assets/media/svg/brand-logos/apple-black.svg'); ?>" class="h-25px" alt="Apple" />
                                <span class="fw-bold fs-6">Sign in with Apple</span>
                            </div>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input h-20px w-35px" type="checkbox" name="apple_auth_enabled" value="1" id="switch_apple_auth" <?php echo !empty($theme_settings['apple_auth_enabled']) ? 'checked' : ''; ?> />
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fs-7 fw-semibold">Apple Services ID (Client ID)</label>
                            <input type="text" name="apple_client_id" class="form-control form-control-solid fs-7" placeholder="ej. com.empresa.sistema.service" value="<?php echo htmlspecialchars($theme_settings['apple_client_id'] ?? ''); ?>" />
                            <?php if (!empty($errores['apple_client_id'])): ?><div class="text-danger fs-7 mt-1"><?php echo htmlspecialchars($errores['apple_client_id']); ?></div><?php endif; ?>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label fs-7 fw-semibold">Team ID</label>
                                <input type="text" name="apple_team_id" class="form-control form-control-solid fs-7" placeholder="ej. ABC123DEFG" value="<?php echo htmlspecialchars($theme_settings['apple_team_id'] ?? ''); ?>" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-7 fw-semibold">Key ID</label>
                                <input type="text" name="apple_key_id" class="form-control form-control-solid fs-7" placeholder="ej. XYZ9876543" value="<?php echo htmlspecialchars($theme_settings['apple_key_id'] ?? ''); ?>" />
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fs-7 fw-semibold">Client Secret / Private Key</label>
                            <input type="password" name="apple_client_secret" class="form-control form-control-solid fs-7" placeholder="••••••••••••••••••••" value="<?php echo htmlspecialchars($theme_settings['apple_client_secret'] ?? ''); ?>" autocomplete="new-password" />
                        </div>
                        <div class="bg-body border rounded p-3 mt-auto">
                            <span class="text-muted fs-8 d-block mb-1 fw-bold">Return URL para configurar en Apple Developer:</span>
                            <code class="fs-8 text-primary user-select-all"><?php echo base_url('auth/oauth-apple-callback'); ?></code>
                        </div>
                    </div>
                </div>
                <!--end::Apple OAuth-->

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold mb-1">Paleta de Colores del Tema</h4>
                            <div class="text-muted fs-7">Personaliza los colores de la interfaz organizados por Modo Claro y Modo Oscuro.</div>
                        </div>
                    </div>

                    <!--begin::Tabs Claro / Oscuro-->
                    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-5 fs-6" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active text-active-primary d-flex align-items-center pb-3" data-bs-toggle="tab" href="#kt_theme_colors_light" role="tab">
                                <i class="ki-duotone ki-sun fs-2 me-2 text-warning">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    <span class="path4"></span><span class="path5"></span><span class="path6"></span>
                                    <span class="path7"></span><span class="path8"></span><span class="path9"></span>
                                </i>
                                <span class="fw-bold">Colores Modo Claro</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link text-active-primary d-flex align-items-center pb-3" data-bs-toggle="tab" href="#kt_theme_colors_dark" role="tab">
                                <i class="ki-duotone ki-moon fs-2 me-2 text-primary">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <span class="fw-bold">Colores Modo Oscuro</span>
                            </a>
                        </li>
                    </ul>
                    <!--end::Tabs Claro / Oscuro-->

                    <!--begin::Tab Content Colores-->
                    <div class="tab-content border rounded p-6 bg-light bg-opacity-30">
                        <!--begin::Tab Modo Claro-->
                        <div class="tab-pane fade show active" id="kt_theme_colors_light" role="tabpanel">
                            <div class="row g-5">
                                <?php
                                $light_color_fields = [
                                    'primary_color'             => 'Color primario',
                                    'button_hover_color'        => 'Hover botones',
                                    'header_bg_color'           => 'Fondo barra superior',
                                    'header_text_color'         => 'Texto barra superior',
                                    'sidebar_bg_color'          => 'Fondo sidebar',
                                    'sidebar_text_color'        => 'Texto sidebar',
                                    'sidebar_active_bg_color'   => 'Fondo menú activo',
                                    'sidebar_active_text_color' => 'Texto menú activo',
                                    'footer_bg_color'           => 'Fondo footer',
                                    'footer_text_color'         => 'Texto footer',
                                    'login_card_bg_color'       => 'Fondo tarjeta login',
                                ];
                                foreach ($light_color_fields as $field => $label): ?>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-7 mb-1"><?php echo $label; ?></label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="<?php echo $field; ?>" id="input_<?php echo $field; ?>" class="form-control form-control-color form-control-solid" value="<?php echo htmlspecialchars($theme_settings[$field] ?? '#FFFFFF'); ?>" oninput="document.getElementById('lbl_<?php echo $field; ?>').innerText = this.value.toUpperCase();" />
                                        <span id="lbl_<?php echo $field; ?>" class="text-muted fs-7 fw-semibold font-monospace"><?php echo htmlspecialchars($theme_settings[$field] ?? '#FFFFFF'); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!--end::Tab Modo Claro-->

                        <!--begin::Tab Modo Oscuro-->
                        <div class="tab-pane fade" id="kt_theme_colors_dark" role="tabpanel">
                            <div class="row g-5">
                                <?php
                                $dark_color_fields = [
                                    'dark_primary_color'            => 'Color primario (Dark)',
                                    'dark_button_hover_color'       => 'Hover botones (Dark)',
                                    'dark_header_bg_color'          => 'Fondo barra superior (Dark)',
                                    'dark_header_text_color'        => 'Texto barra superior (Dark)',
                                    'dark_sidebar_bg_color'         => 'Fondo sidebar (Dark)',
                                    'dark_sidebar_text_color'       => 'Texto sidebar (Dark)',
                                    'dark_sidebar_active_bg_color'  => 'Fondo menú activo (Dark)',
                                    'dark_sidebar_active_text_color'=> 'Texto menú activo (Dark)',
                                    'dark_footer_bg_color'          => 'Fondo footer (Dark)',
                                    'dark_footer_text_color'        => 'Texto footer (Dark)',
                                    'dark_body_bg_color'            => 'Fondo general / Body (Dark)',
                                    'dark_login_card_bg_color'      => 'Fondo tarjeta login (Dark)',
                                ];
                                foreach ($dark_color_fields as $field => $label): ?>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-7 mb-1"><?php echo $label; ?></label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="<?php echo $field; ?>" id="input_<?php echo $field; ?>" class="form-control form-control-color form-control-solid" value="<?php echo htmlspecialchars($theme_settings[$field] ?? '#15171C'); ?>" oninput="document.getElementById('lbl_<?php echo $field; ?>').innerText = this.value.toUpperCase();" />
                                        <span id="lbl_<?php echo $field; ?>" class="text-muted fs-7 fw-semibold font-monospace"><?php echo htmlspecialchars($theme_settings[$field] ?? '#15171C'); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!--end::Tab Modo Oscuro-->
                    </div>
                    <!--end::Tab Content Colores-->
                </div>

                <div class="col-12 mt-6">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Dimensiones y Tipografía del Sidebar</h4>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tamaño base de fuente (px)</label>
                    <input type="number" name="font_size_px" class="form-control form-control-solid" min="5" max="20" value="<?php echo htmlspecialchars($theme_settings['font_size_px']); ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Ancho del sidebar (px)</label>
                    <input type="number" name="sidebar_width_px" class="form-control form-control-solid" min="200" max="360" value="<?php echo htmlspecialchars($theme_settings['sidebar_width_px']); ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Alto logo dashboard (px)</label>
                    <input type="number" name="dashboard_logo_height_px" class="form-control form-control-solid" min="16" max="80" value="<?php echo htmlspecialchars($theme_settings['dashboard_logo_height_px'] ?? '25'); ?>" />
                    <div class="text-muted fs-7 mt-1">Controla el tamaño del logo principal del sidebar.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Peso de texto del sidebar</label>
                    <select name="sidebar_font_weight" class="form-select form-select-solid">
                        <?php foreach ($sidebar_weight_options as $weight_value => $weight_label): ?>
                        <option value="<?php echo htmlspecialchars($weight_value); ?>" <?php echo (string)$theme_settings['sidebar_font_weight'] === (string)$weight_value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($weight_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted fs-7 mt-1">Usa Normal para quitar el efecto de negrita en el menú.</div>
                </div>

                <div class="col-12 mt-8">
                    <div class="separator separator-dashed my-2"></div>
                    <h4 class="fw-bold mb-4">Imágenes</h4>
                </div>

                <?php
                $image_fields = [
                    'dashboard_logo' => ['label' => 'Logo dashboard', 'url' => $theme_settings['dashboard_logo_url']],
                    'dashboard_logo_compact' => ['label' => 'Logo dashboard compacto', 'url' => $theme_settings['dashboard_logo_compact_url']],
                    'login_logo' => ['label' => 'Logo login claro', 'url' => $theme_settings['login_logo_url']],
                    'login_logo_dark' => ['label' => 'Logo login oscuro', 'url' => $theme_settings['login_logo_dark_url']],
                    'login_background' => ['label' => 'Fondo login claro', 'url' => $theme_settings['login_background_url']],
                    'login_background_dark' => ['label' => 'Fondo login oscuro', 'url' => $theme_settings['login_background_dark_url']],
                    'favicon_image' => ['label' => 'Favicon', 'url' => $theme_settings['favicon_url']],
                ];
                foreach ($image_fields as $field => $meta): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><?php echo $meta['label']; ?></label>
                    <div class="border rounded p-4 bg-light d-flex align-items-center gap-4">
                        <img src="<?php echo htmlspecialchars($meta['url']); ?>" alt="<?php echo htmlspecialchars($meta['label']); ?>" style="width: 120px; height: 80px; object-fit: contain; background: #fff; border-radius: 0.475rem;" />
                        <input type="file" name="<?php echo $field; ?>" class="form-control form-control-solid" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-end mt-10">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold px-4 py-2">
                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Guardar configuración
                </button>
            </div>
        </form>
    </div>
</div>