<!--begin::Sidebar-->
<div id="kt_app_sidebar" class="app-sidebar flex-column kt-sidebar dark"
    data-kt-drawer="true"
    data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}"
    data-kt-drawer-overlay="true"
    data-kt-drawer-width="280px"
    data-kt-drawer-direction="start"
    data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <!--begin::Logo-->
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="<?php echo base_url('dashboard'); ?>">
            <img alt="Logo"
                src="<?php echo htmlspecialchars($theme_settings['dashboard_logo_url'] ?? base_url('assets/media/logos/default-dark.svg')); ?>"
                class="app-sidebar-logo-default default-logo" />
            <img alt="Logo"
                src="<?php echo htmlspecialchars($theme_settings['dashboard_logo_compact_url'] ?? base_url('assets/media/logos/default-small.svg')); ?>"
                class="h-20px app-sidebar-logo-minimize small-logo" />
        </a>

        <!--begin::Botón colapsar sidebar-->
        <div id="kt_app_sidebar_toggle"
            class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm body-bg h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
            data-kt-toggle="true"
            data-kt-toggle-state="active"
            data-kt-toggle-target="body"
            data-kt-toggle-name="app-sidebar-minimize"
            title="Colapsar / Expandir menú">
            <i class="ki-duotone ki-double-left fs-4 rotate-180">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </div>
        <!--end::Botón colapsar sidebar-->
    </div>
    <!--end::Logo-->

    <!--begin::Menú-->
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
            <div id="kt_app_sidebar_menu_scroll"
                class="scroll-y my-5 mx-3"
                data-kt-scroll="true"
                data-kt-scroll-activate="true"
                data-kt-scroll-height="auto"
                data-kt-scroll-dependencies="#kt_app_sidebar_logo"
                data-kt-scroll-wrappers="#kt_app_sidebar_menu_wrapper"
                data-kt-scroll-offset="5px"
                data-kt-scroll-save-state="true">

                <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6"
                    id="kt_app_sidebar_menu"
                    data-kt-menu="true"
                    data-kt-menu-expand="false">

                    <?php
                    /*
                     * ─────────────────────────────────────────────────────
                     * Renderiza el árbol de menús procedente de:
                     *   Menu_model::get_arbol_por_usuario()
                     *
                     * Estructura de cada ítem:
                     *   men_id        → ID del menú
                     *   sub_men_id    → ID del padre (0 = raíz)
                     *   men_nombre    → Texto visible
                     *   men_tipo      → 0 = raíz, 1 = submenú
                     *   men_url       → URL del controlador (ej: Admin/clientes)
                     *   men_urlamigable → URL amigable (ej: clientes)
                     *   men_imgmenu   → Clase CSS del ícono (fa fa-users)
                     *   men_externo   → Si abre en _blank
                     *   hijos[]       → Submenús anidados
                     * ─────────────────────────────────────────────────────
                     */

                    /**
                     * Verifica recursivamente si algún hijo del ítem
                     * coincide con la URL actual (para abrir el acordeón padre).
                     */
                    function tiene_hijo_activo($hijos, $url_actual) {
                        foreach ($hijos as $hijo) {
                            $url_hijo = !empty($hijo['men_urlamigable'])
                                ? trim($hijo['men_urlamigable'])
                                : $hijo['men_url'];
                            if (trim($url_actual, '/') === trim($url_hijo, '/')) {
                                return true;
                            }
                            if (!empty($hijo['hijos']) && tiene_hijo_activo($hijo['hijos'], $url_actual)) {
                                return true;
                            }
                        }
                        return false;
                    }

                    /**
                     * Renderiza recursivamente los ítems del menú.
                     *
                     * @param array  $items      Árbol de menús
                     * @param string $url_actual URL actual para marcar activo
                     * @param int    $nivel      Nivel de profundidad (0=raíz)
                     */
                    function render_menu($items, $url_actual, $nivel = 0) {
                        foreach ($items as $item):

                            // URL a usar: preferir amigable, caer en men_url
                            $url = !empty($item['men_urlamigable'])
                                ? trim($item['men_urlamigable'])
                                : $item['men_url'];

                            $tiene_hijos = !empty($item['hijos']);
                            $es_activo   = (trim($url_actual, '/') === trim($url, '/'));
                            $hijo_activo = $tiene_hijos
                                ? tiene_hijo_activo($item['hijos'], $url_actual)
                                : false;

                            $target = ($item['men_externo'] === '1' || $item['men_externo'] === '_blank')
                                ? '_blank'
                                : '_self';

                            // Ícono: men_imgmenu puede ser "fa fa-users" o "ki-duotone ki-people"
                            $icono = $item['men_imgmenu'] ?? '';
                            $es_ki = strpos($icono, 'ki-duotone') !== false;

                            if ($tiene_hijos):
                    ?>
                        <!--begin::Menú con submenús (acordeón)-->
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion <?php echo $hijo_activo ? 'here show' : ''; ?>">

                            <span class="menu-link" title="<?php echo htmlspecialchars($item['men_nombre']); ?>">
                                <?php if ($icono): ?>
                                <span class="menu-icon">
                                    <i class="<?php echo htmlspecialchars($icono); ?> fs-2">
                                        <?php if ($es_ki): ?>
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        <?php endif; ?>
                                    </i>
                                </span>
                                <?php elseif ($nivel > 0): ?>
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <?php endif; ?>
                                <span class="menu-title"><?php echo htmlspecialchars($item['men_nombre']); ?></span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion <?php echo $hijo_activo ? 'show' : ''; ?>">
                                <?php render_menu($item['hijos'], $url_actual, $nivel + 1); ?>
                            </div>
                        </div>
                        <!--end::Menú con submenús-->

                    <?php else: ?>

                        <!--begin::Ítem hoja (sin hijos)-->
                        <div class="menu-item">
                            <a class="menu-link <?php echo $es_activo ? 'active' : ''; ?>"
                               href="<?php echo base_url(htmlspecialchars($url)); ?>"
                               target="<?php echo $target; ?>"
                               title="<?php echo htmlspecialchars($item['men_nombre']); ?>">

                                <?php if ($icono): ?>
                                <span class="menu-icon">
                                    <i class="<?php echo htmlspecialchars($icono); ?> fs-2">
                                        <?php if ($es_ki): ?>
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        <?php endif; ?>
                                    </i>
                                </span>
                                <?php elseif ($nivel > 0): ?>
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <?php endif; ?>

                                <span class="menu-title"><?php echo htmlspecialchars($item['men_nombre']); ?></span>
                            </a>
                        </div>
                        <!--end::Ítem hoja-->

                    <?php
                            endif; // tiene_hijos
                        endforeach;
                    }

                    // ── Renderizar el árbol desde la variable $menu ──────
                    if (!empty($menu)) {
                        render_menu($menu, $url_actual ?? '');
                    } else {
                        echo '<div class="menu-item"><span class="menu-link text-muted">Sin menús asignados</span></div>';
                    }
                    ?>

                </div>
            </div>
        </div>
    </div>
    <!--end::Menú-->

</div>
<!--end::Sidebar-->
