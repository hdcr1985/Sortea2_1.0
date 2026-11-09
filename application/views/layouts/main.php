<?php $this->load->view('partials/_head'); ?>

<!--begin::Body-->
<body id="kt_app_body"
    data-kt-app-layout="dark-sidebar"
    data-kt-app-header-fixed="true"
    data-kt-app-sidebar-enabled="true"
    data-kt-app-sidebar-fixed="true"
    data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-header="true"
    data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true"
    data-kt-app-toolbar-enabled="true"
    class="app-default">

<!--begin::Tema claro/oscuro-->
<script>
    var defaultThemeMode = "light";
    var themeMode;
    if (document.documentElement) {
        if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
            themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
        } else {
            themeMode = localStorage.getItem("data-bs-theme") !== null
                ? localStorage.getItem("data-bs-theme")
                : defaultThemeMode;
        }
        if (themeMode === "system") {
            themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }
        document.documentElement.setAttribute("data-bs-theme", themeMode);
    }
</script>
<!--end::Tema-->

<!--begin::App-->
<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
    <div class="app-page flex-column flex-column-fluid" id="kt_app_page">

        <!--begin::Header-->
        <?php $this->load->view('partials/_header'); ?>
        <!--end::Header-->

        <!--begin::Wrapper-->
        <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">

            <!--begin::Sidebar-->
            <?php $this->load->view('partials/_sidebar'); ?>
            <!--end::Sidebar-->

            <!--begin::Main-->
            <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                <div class="d-flex flex-column flex-column-fluid">

                    <!--begin::Toolbar + Breadcrumb-->
                    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
                        <div class="app-container container-fluid d-flex flex-stack">

                            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                                    <?php echo htmlspecialchars($page_title ?? ''); ?>
                                </h1>

                                <?php if (!empty($breadcrumb)): ?>
                                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                                    <?php foreach ($breadcrumb as $idx => $crumb):
                                        $es_ultimo = ($idx === array_key_last($breadcrumb));
                                    ?>
                                    <li class="breadcrumb-item <?php echo $es_ultimo ? 'text-muted' : ''; ?>">
                                        <?php if (!$es_ultimo && !empty($crumb['url'])): ?>
                                            <a href="<?php echo base_url(htmlspecialchars($crumb['url'])); ?>"
                                               class="text-muted text-hover-primary">
                                                <?php echo htmlspecialchars($crumb['titulo']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($crumb['titulo']); ?>
                                        <?php endif; ?>
                                    </li>
                                    <?php if (!$es_ultimo): ?>
                                    <li class="breadcrumb-item">
                                        <span class="bullet bg-gray-400 w-5px h-2px"></span>
                                    </li>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                    <!--end::Toolbar-->

                    <!--begin::Content-->
                    <div id="kt_app_content" class="app-content flex-column-fluid">
                        <div id="kt_app_content_container" class="app-container container-fluid">

                            <!--begin::Global Announcement Banner-->
                            <?php if (!empty($general_settings['banner_activo']) && $general_settings['banner_activo'] === '1' && !empty($general_settings['banner_texto'])): 
                                $b_tipo = in_array($general_settings['banner_tipo'] ?? '', ['primary', 'info', 'warning', 'danger', 'success']) ? $general_settings['banner_tipo'] : 'info';
                                $b_icon = [
                                    'primary' => 'ki-information-5',
                                    'info'    => 'ki-information-5',
                                    'warning' => 'ki-shield-cross',
                                    'danger'  => 'ki-cross-circle',
                                    'success' => 'ki-check-circle',
                                ][$b_tipo] ?? 'ki-information-5';
                            ?>
                            <div class="alert alert-dismissible bg-light-<?php echo $b_tipo; ?> d-flex flex-column flex-sm-row p-4 mb-6 border border-<?php echo $b_tipo; ?> border-dashed">
                                <i class="ki-duotone <?php echo $b_icon; ?> fs-2hx text-<?php echo $b_tipo; ?> me-4 mb-3 mb-sm-0"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
                                    <span class="fs-6 fw-semibold text-gray-800"><?php echo htmlspecialchars($general_settings['banner_texto']); ?></span>
                                </div>
                                <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" data-bs-dismiss="alert">
                                    <i class="ki-duotone ki-cross fs-1 text-<?php echo $b_tipo; ?>"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                            </div>
                            <?php endif; ?>
                            <!--end::Global Announcement Banner-->

                            <!--===== CONTENIDO DE LA PÁGINA =====-->
                            <?php echo $content; ?>
                            <!--===== FIN CONTENIDO =====-->

                        </div>
                    </div>
                    <!--end::Content-->

                </div>

                <!--begin::Footer-->
                <?php $this->load->view('partials/_footer'); ?>
                <!--end::Footer-->

            </div>
            <!--end::Main-->

        </div>
        <!--end::Wrapper-->

    </div>
</div>
<!--end::App-->

<?php $this->load->view('partials/_scripts'); ?>

</body>
</html>
