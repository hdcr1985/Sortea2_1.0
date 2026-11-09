<!--begin::Header-->
<div id="kt_app_header" class="app-header"
    data-kt-sticky="true"
    data-kt-sticky-activate="{default: true, lg: true}"
    data-kt-sticky-name="app-header-minimize"
    data-kt-sticky-offset="{default: '200px', lg: '0'}"
    data-kt-sticky-animation="false">

    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">

        <!--begin::Toggle sidebar móvil-->
        <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Mostrar menú">
            <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                <i class="ki-duotone ki-abstract-14 fs-2 fs-md-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </div>
        </div>
        <!--end::Toggle sidebar móvil-->

        <!--begin::Logo móvil-->
        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <a href="<?php echo base_url('dashboard'); ?>" class="d-lg-none">
                <img alt="Logo" src="<?php echo htmlspecialchars($theme_settings['dashboard_logo_compact_url'] ?? base_url('assets/media/logos/default-small.svg')); ?>" class="h-30px" />
            </a>
        </div>
        <!--end::Logo móvil-->

        <!--begin::Header wrapper-->
        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">

            <!--begin::Título de la página-->
            <div class="d-flex align-items-center ms-3">
                <h3 class="text-gray-700 fw-bold my-0 fs-4">
                    <?php //echo htmlspecialchars($page_title ?? ''); ?>
                </h3>
            </div>
            <!--end::Título de la página-->

            <!--begin::Navbar derecha-->
            <div class="d-flex align-items-center flex-shrink-0 gap-2">

                <!--begin::Notificaciones del sistema-->
                <div class="d-flex align-items-center" id="kt_header_notifications_menu">
                    <!--begin::Disparador notificaciones estilo Demo 1-->
                    <div class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px rounded-circle position-relative shrink-0"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                        data-kt-menu-attach="parent"
                        data-kt-menu-placement="bottom-end">
                        <i class="ki-duotone ki-notification-status fs-2">
                            <span class="path1"></span><span class="path2"></span>
                            <span class="path3"></span><span class="path4"></span>
                        </i>
                        <!--begin::Badge contador (se actualiza vía AJAX)-->
                        <span id="header_notifications_badge" class="bullet bullet-dot bg-success h-6px w-6px position-absolute translate-middle top-0 start-50 animation-blink d-none"></span>
                        <!--end::Badge contador-->
                    </div>

                    <!--begin::Dropdown notificaciones estilo Demo 1-->
                    <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px"
                        data-kt-menu="true">
                        <div class="d-flex align-items-center justify-content-between px-6 py-4 border-bottom">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <span class="fs-6 fw-bold text-gray-900 text-truncate">Notificaciones</span>
                                <span id="header_notifications_count" class="badge badge-light-primary fs-8 fw-semibold px-2 py-1 d-none">0</span>
                            </div>
                            <button type="button" id="header_notifications_mark_all" class="btn btn-color-gray-600 btn-active-color-primary btn-sm py-1 px-2 d-none d-flex align-items-center">
                                <i class="ki-duotone ki-check-circle fs-6 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Marcar todas
                            </button>
                        </div>
                        <div class="px-6 pt-3 pb-1 border-bottom">
                            <ul class="nav nav-line-tabs nav-line-tabs-2x border-transparent fs-7 fw-semibold" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link text-active-primary pb-2 active" data-bs-toggle="tab" href="#kt_header_notifications_all" role="tab">
                                        Todas
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link text-active-primary pb-2" data-bs-toggle="tab" href="#kt_header_notifications_unread" role="tab">
                                        No leídas
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="kt_header_notifications_all" role="tabpanel">
                                <div id="header_notifications_list" class="scroll-y mh-300px my-2 px-6">
                                    <div id="header_notifications_empty" class="text-center text-muted py-8">
                                        <i class="ki-duotone ki-notification-status fs-3x mb-3 text-muted">
                                            <span class="path1"></span><span class="path2"></span>
                                            <span class="path3"></span><span class="path4"></span>
                                        </i>
                                        <div class="fs-7 fw-medium">Sin notificaciones nuevas</div>
                                    </div>
                                    <div id="header_notifications_items"></div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="kt_header_notifications_unread" role="tabpanel">
                                <div id="header_notifications_unread_list" class="scroll-y mh-300px my-2 px-6">
                                    <div class="text-center text-muted py-8">
                                        <div class="fs-7 fw-medium">No tienes notificaciones pendientes</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="py-3 px-6 text-center border-top">
                            <a href="<?php echo base_url('notificaciones'); ?>" class="btn btn-color-gray-600 btn-active-color-primary btn-sm d-flex align-items-center justify-content-center">
                                <i class="ki-duotone ki-eye fs-5 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                Ver todas las notificaciones
                            </a>
                        </div>
                    </div>
                    <!--end::Dropdown notificaciones-->
                </div>
                <!--end::Notificaciones del sistema-->

                <!--begin::Menú de usuario estilo Demo 1-->
                <div class="d-flex align-items-center" id="kt_header_user_menu_toggle">

                    <?php
                    // Foto del usuario o iniciales.
                    // La sesión puede contener una URL de Media o un nombre de archivo legado.
                    $foto_raw = trim((string)($usuario['usu_foto'] ?? ''));
                    $foto     = '';
                    if ($foto_raw !== '') {
                        if (preg_match('#^https?://#i', $foto_raw) || strpos($foto_raw, '/') === 0) {
                            $foto = $foto_raw;
                        } elseif (!empty($usuario['usu_id'])) {
                            $foto = base_url('media/usuario/' . rawurlencode((string)$usuario['usu_id']));
                        }
                    }

                    $nombre   = trim((string)($usuario['usu_nombre'] ?? ''));
                    $apellido = trim((string)($usuario['usu_apellido'] ?? ''));
                    $nombre   = trim($nombre . ' ' . $apellido);
                    if ($nombre === '') {
                        $nombre = 'Usuario';
                    }
                    $correo   = $usuario['usu_correo'] ?? '';
                    $inicial  = mb_strtoupper(mb_substr($nombre, 0, 1));
                    $perfil_id = (int)($usuario['usu_perfil'] ?? 0);
                    if (!empty($es_master)) {
                        $perfil_texto = 'Admin Master';
                    } elseif (!empty($rol_nombre)) {
                        $perfil_texto = $rol_nombre;
                    } elseif (!empty($usuario['per_nombre'])) {
                        $perfil_texto = $usuario['per_nombre'];
                    } elseif ($perfil_id > 0) {
                        $perfil_texto = 'Rol #' . $perfil_id;
                    } else {
                        $perfil_texto = 'Pro';
                    }

                    // Si la imagen no carga (archivo eliminado o ruta inválida), se
                    // reemplaza por el avatar de iniciales para no dejar el img roto.
                    $render_avatar = function () use ($foto, $nombre, $inicial) {
                        if ($foto === '') {
                            echo '<span class="topbar-avatar-user-initial shrink-0">'
                                . htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8')
                                . '</span>';
                            return;
                        }

                        echo '<img src="' . htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') . '"'
                            . ' alt="' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '"'
                            . ' class="topbar-avatar-user shrink-0"'
                            . ' data-initial="' . htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') . '"'
                            . ' onerror="var s=document.createElement(\'span\');'
                            . 's.className=\'topbar-avatar-user-initial shrink-0\';'
                            . 's.textContent=this.dataset.initial;this.replaceWith(s);" />';
                    };
                    ?>

                    <!--begin::Disparador shrink-0 con foto y aro verde estilo Demo 1-->
                    <div class="cursor-pointer shrink-0"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                        data-kt-menu-attach="parent"
                        data-kt-menu-placement="bottom-end">
                        <?php $render_avatar(); ?>
                    </div>
                    <!--end::Disparador shrink-0-->

                    <!--begin::User dropdown menú estilo Demo 1-->
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-3 fs-6 w-275px shadow-lg"
                        data-kt-menu="true">

                        <!--begin::Cabecera de usuario estilo Demo 1-->
                        <div class="d-flex align-items-center justify-content-between px-4 py-2 gap-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <?php $render_avatar(); ?>
                                <div class="d-flex flex-column min-w-0">
                                    <span class="text-gray-900 fw-bold fs-7 lh-1 mb-1 text-truncate">
                                        <?php echo htmlspecialchars($nombre); ?>
                                    </span>
                                    <a href="<?php echo base_url('perfil'); ?>" class="text-muted text-hover-primary fs-8 fw-semibold lh-1 text-truncate">
                                        <?php echo htmlspecialchars($correo); ?>
                                    </a>
                                </div>
                            </div>
                            <span class="badge badge-light-primary badge-outline fs-8 fw-bold px-2 py-1 shrink-0">
                                <?php echo htmlspecialchars($perfil_texto); ?>
                            </span>
                        </div>
                        <!--end::Cabecera de usuario-->

                        <div class="separator my-2"></div>

                        <!--begin::Enlaces de navegación-->
                        <div class="menu-item px-3">
                            <a href="<?php echo base_url('perfil'); ?>" class="menu-link px-3 d-flex align-items-center">
                                <i class="ki-duotone ki-profile-circle fs-3 text-gray-600 me-2">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                </i>
                                <span class="menu-title fs-7">Mi Perfil</span>
                            </a>
                        </div>

                        <div class="menu-item px-3">
                            <a href="<?php echo base_url('configuracion'); ?>" class="menu-link px-3 d-flex align-items-center">
                                <i class="ki-duotone ki-setting-2 fs-3 text-gray-600 me-2">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <span class="menu-title fs-7">Configuración</span>
                            </a>
                        </div>
                        <!--end::Enlaces de navegación-->

                        <div class="separator my-2"></div>

                        <!--begin::Modo Oscuro & Salir estilo Demo 1-->
                        <div class="px-4 py-2 d-flex flex-column gap-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="d-flex align-items-center text-gray-700 fs-7 fw-semibold">
                                    <i class="ki-duotone ki-moon fs-4 text-muted me-2">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    Modo Oscuro
                                </span>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input h-20px w-35px cursor-pointer" type="checkbox" id="kt_user_menu_dark_mode_toggle" />
                                </div>
                            </div>
                            <a href="<?php echo base_url('logout'); ?>" class="btn btn-sm btn-outline btn-outline-default d-flex align-items-center justify-content-center w-100 text-hover-danger">
                                <i class="ki-duotone ki-exit-right fs-4 me-2">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                Cerrar Sesión
                            </a>
                        </div>
                        <!--end::Modo Oscuro & Salir-->

                    </div>
                    <!--end::User dropdown menú-->
                </div>
                <!--end::Menú de usuario-->

            </div>
            <!--end::Navbar derecha-->

            <script>
            document.addEventListener("DOMContentLoaded", function () {
                var toggle = document.getElementById("kt_user_menu_dark_mode_toggle");
                if (!toggle) return;
                var currentTheme = document.documentElement.getAttribute("data-bs-theme") || localStorage.getItem("data-bs-theme") || "light";
                toggle.checked = (currentTheme === "dark");
                toggle.addEventListener("change", function () {
                    var newTheme = this.checked ? "dark" : "light";
                    document.documentElement.setAttribute("data-bs-theme", newTheme);
                    localStorage.setItem("data-bs-theme", newTheme);
                });
            });
            </script>
        </div>
        <!--end::Header wrapper-->
    </div>
</div>
<!--end::Header-->
