<style>
    #modal_icon_picker {
        z-index: 1070 !important;
    }
</style>

<!--begin::Modal Icon Picker-->
<div class="modal fade" id="modal_icon_picker" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">

            <!--begin::Header-->
            <div class="modal-header py-4">
                <h2 class="fw-bold fs-4 mb-0">Seleccionar Ícono</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <!--end::Header-->

            <!--begin::Toolbar-->
            <div class="px-7 py-4 border-bottom d-flex align-items-center gap-4 flex-wrap">

                <!--begin::Buscador-->
                <div class="position-relative flex-grow-1" style="max-width:320px">
                    <i class="ki-duotone ki-magnifier fs-4 position-absolute top-50 translate-middle-y ms-3 text-muted">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="icon_search"
                        class="form-control form-control-solid ps-10"
                        placeholder="Buscar ícono... (ej: home, user, chart)" />
                </div>
                <!--end::Buscador-->

                <!--begin::Filtro por librería-->
                <div class="d-flex gap-2" id="icon_tabs">
                    <button type="button" class="btn btn-primary btn-sm" data-lib="todos">
                        Todos <span class="badge bg-white text-primary ms-1" id="count_todos">0</span>
                    </button>
                    <button type="button" class="btn btn-light btn-sm" data-lib="ki">
                        <img src="<?php echo base_url('assets/media/logos/default-small.svg'); ?>"
                            style="height:14px" class="me-1" />
                        Ki-duotone <span class="badge bg-secondary ms-1" id="count_ki">0</span>
                    </button>
                    <button type="button" class="btn btn-light btn-sm" data-lib="fa">
                        <i class="fab fa-font-awesome-flag me-1"></i>
                        Font Awesome <span class="badge bg-secondary ms-1" id="count_fa">0</span>
                    </button>
                </div>
                <!--end::Filtro-->

                <!--begin::Seleccionado actual-->
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="text-muted fw-semibold fs-7">Seleccionado:</span>
                    <div id="icon_selected_preview"
                        class="d-flex align-items-center justify-content-center
                               bg-light rounded w-35px h-35px">
                        <span class="text-muted fs-8">—</span>
                    </div>
                    <code id="icon_selected_name" class="fs-8 text-muted">ninguno</code>
                </div>
                <!--end::Seleccionado-->

            </div>
            <!--end::Toolbar-->

            <!--begin::Grid de íconos-->
            <div class="modal-body p-5" style="max-height:55vh; overflow-y:auto;">
                <div id="icon_grid" class="row g-2"></div>
                <div id="icon_no_results" class="text-center text-muted py-15 d-none">
                    <i class="ki-duotone ki-search-list fs-3x mb-3 text-muted">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                    </i>
                    <div class="fw-semibold">No se encontraron íconos</div>
                    <div class="fs-7">Intenta con otra palabra clave</div>
                </div>
            </div>
            <!--end::Grid-->

            <!--begin::Footer-->
            <div class="modal-footer py-3">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btn_icon_confirmar">
                    <i class="ki-duotone ki-check fs-4 me-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Usar este ícono
                </button>
            </div>
            <!--end::Footer-->

        </div>
    </div>
</div>
<!--end::Modal Icon Picker-->


<!--begin::JavaScript del Icon Picker-->
<script>
const IconPicker = (function() {

    // ── Ícono seleccionado actualmente ─────────────────────
    let _inputId   = null;
    let _previewId = null;
    let _libActiva = 'todos';
    let _claseSeleccionada = null;
    let _modalInstance = null;

    // ══════════════════════════════════════════════════════
    // CATÁLOGO DE ÍCONOS
    // Formato: { c: 'clase css', n: 'nombre para búsqueda', l: 'ki|fa' }
    // ══════════════════════════════════════════════════════
    const ICONOS = [
        // ─── KI-DUOTONE (Metronic v8.3.3) ───────────────
        // Navegación
        {c:'ki-duotone ki-home-2',           n:'home inicio',             l:'ki'},
        {c:'ki-duotone ki-home-3',           n:'home inicio',             l:'ki'},
        {c:'ki-duotone ki-arrow-left',       n:'flecha izquierda back',   l:'ki'},
        {c:'ki-duotone ki-arrow-right',      n:'flecha derecha next',     l:'ki'},
        {c:'ki-duotone ki-arrow-up',         n:'flecha arriba up',        l:'ki'},
        {c:'ki-duotone ki-arrow-down',       n:'flecha abajo down',       l:'ki'},
        {c:'ki-duotone ki-double-left',      n:'doble flecha izquierda',  l:'ki'},
        {c:'ki-duotone ki-double-right',     n:'doble flecha derecha',    l:'ki'},
        {c:'ki-duotone ki-menu',             n:'menu hamburguesa',        l:'ki'},
        {c:'ki-duotone ki-category',         n:'categoria grid',          l:'ki'},
        {c:'ki-duotone ki-element-11',       n:'dashboard grid',          l:'ki'},
        {c:'ki-duotone ki-element-plus',     n:'elemento mas grid',       l:'ki'},
        {c:'ki-duotone ki-grid',             n:'grid cuadricula',         l:'ki'},
        {c:'ki-duotone ki-row-horizontal',   n:'filas lista',             l:'ki'},
        // Usuarios
        {c:'ki-duotone ki-profile-circle',   n:'usuario perfil',          l:'ki'},
        {c:'ki-duotone ki-user-square',      n:'usuario perfil',          l:'ki'},
        {c:'ki-duotone ki-people',           n:'usuarios personas grupo', l:'ki'},
        {c:'ki-duotone ki-user-edit',        n:'editar usuario',          l:'ki'},
        {c:'ki-duotone ki-badge',            n:'insignia badge usuario',  l:'ki'},
        // Seguridad
        {c:'ki-duotone ki-shield-tick',      n:'seguridad escudo permiso',l:'ki'},
        {c:'ki-duotone ki-shield-cross',     n:'escudo error bloqueo',    l:'ki'},
        {c:'ki-duotone ki-shield-search',    n:'escudo buscar',           l:'ki'},
        {c:'ki-duotone ki-lock',             n:'candado bloqueo',         l:'ki'},
        {c:'ki-duotone ki-lock-2',           n:'candado bloqueo',         l:'ki'},
        {c:'ki-duotone ki-key',              n:'llave clave permiso',     l:'ki'},
        {c:'ki-duotone ki-key-square',       n:'llave cuadrada permiso',  l:'ki'},
        {c:'ki-duotone ki-finger-scan',      n:'huella biometrico',       l:'ki'},
        {c:'ki-duotone ki-password-check',   n:'contraseña verificar',    l:'ki'},
        // Datos y archivos
        {c:'ki-duotone ki-document',         n:'documento archivo',       l:'ki'},
        {c:'ki-duotone ki-file',             n:'archivo file',            l:'ki'},
        {c:'ki-duotone ki-folder',           n:'carpeta folder',          l:'ki'},
        {c:'ki-duotone ki-folder-added',     n:'carpeta nueva',           l:'ki'},
        {c:'ki-duotone ki-some-files',       n:'archivos documentos',     l:'ki'},
        {c:'ki-duotone ki-notepad',          n:'bloc notas apuntes',      l:'ki'},
        {c:'ki-duotone ki-note',             n:'nota apunte',             l:'ki'},
        {c:'ki-duotone ki-diary',            n:'diario agenda',           l:'ki'},
        {c:'ki-duotone ki-book',             n:'libro libro',             l:'ki'},
        {c:'ki-duotone ki-book-open',        n:'libro abierto leer',      l:'ki'},
        {c:'ki-duotone ki-clipboard',        n:'portapapeles clipboard',  l:'ki'},
        {c:'ki-duotone ki-paper-clip',       n:'adjunto clip',            l:'ki'},
        // Gráficas y reportes
        {c:'ki-duotone ki-chart-simple',     n:'grafica reporte chart',   l:'ki'},
        {c:'ki-duotone ki-chart-line-up',    n:'grafica linea tendencia', l:'ki'},
        {c:'ki-duotone ki-chart-line-down',  n:'grafica linea tendencia', l:'ki'},
        {c:'ki-duotone ki-chart-bar',        n:'grafica barras bar chart',l:'ki'},
        {c:'ki-duotone ki-graph',            n:'grafico reporte',         l:'ki'},
        {c:'ki-duotone ki-graph-up',         n:'tendencia alza',          l:'ki'},
        {c:'ki-duotone ki-graph-down',       n:'tendencia baja',          l:'ki'},
        {c:'ki-duotone ki-finance-calculater',n:'calculadora finanzas',   l:'ki'},
        // Comercio
        {c:'ki-duotone ki-basket',           n:'carrito compras tienda',  l:'ki'},
        {c:'ki-duotone ki-bag',              n:'bolsa compra',            l:'ki'},
        {c:'ki-duotone ki-shop',             n:'tienda local comercio',   l:'ki'},
        {c:'ki-duotone ki-lots-shopping',    n:'compras carrito tienda',  l:'ki'},
        {c:'ki-duotone ki-truck',            n:'camion entrega delivery', l:'ki'},
        {c:'ki-duotone ki-delivery',         n:'entrega delivery envio',  l:'ki'},
        {c:'ki-duotone ki-parcel',           n:'paquete envio box',       l:'ki'},
        {c:'ki-duotone ki-package',          n:'paquete caja producto',   l:'ki'},
        {c:'ki-duotone ki-tag',              n:'etiqueta precio tag',     l:'ki'},
        {c:'ki-duotone ki-discount',         n:'descuento oferta',        l:'ki'},
        {c:'ki-duotone ki-barcode',          n:'codigo barras producto',  l:'ki'},
        // Finanzas
        {c:'ki-duotone ki-dollar',           n:'dinero dolar pago',       l:'ki'},
        {c:'ki-duotone ki-wallet',           n:'billetera cartera pago',  l:'ki'},
        {c:'ki-duotone ki-bank',             n:'banco financiero',        l:'ki'},
        {c:'ki-duotone ki-bill',             n:'factura recibo pago',     l:'ki'},
        {c:'ki-duotone ki-receipt-square',   n:'recibo factura',          l:'ki'},
        {c:'ki-duotone ki-two-credit-cart',  n:'tarjeta credito pago',    l:'ki'},
        {c:'ki-duotone ki-percentage',       n:'porcentaje descuento',    l:'ki'},
        // Comunicación
        {c:'ki-duotone ki-sms',              n:'mensaje sms correo',      l:'ki'},
        {c:'ki-duotone ki-message-text',     n:'mensaje chat texto',      l:'ki'},
        {c:'ki-duotone ki-notification-on',  n:'notificacion alerta',     l:'ki'},
        {c:'ki-duotone ki-notification',     n:'notificacion campana',    l:'ki'},
        {c:'ki-duotone ki-paper-plane',      n:'enviar mensaje correo',   l:'ki'},
        {c:'ki-duotone ki-phone',            n:'telefono llamada',        l:'ki'},
        {c:'ki-duotone ki-bubble',           n:'burbuja chat dialogo',    l:'ki'},
        // Configuración
        {c:'ki-duotone ki-setting-2',        n:'configuracion ajustes',   l:'ki'},
        {c:'ki-duotone ki-setting-3',        n:'configuracion ajustes',   l:'ki'},
        {c:'ki-duotone ki-wrench',           n:'herramienta llave',       l:'ki'},
        {c:'ki-duotone ki-tool',             n:'herramienta ajuste',      l:'ki'},
        {c:'ki-duotone ki-filter',           n:'filtro search',           l:'ki'},
        {c:'ki-duotone ki-sort',             n:'ordenar sort filtro',     l:'ki'},
        {c:'ki-duotone ki-switch',           n:'interruptor toggle',      l:'ki'},
        // Acciones comunes
        {c:'ki-duotone ki-magnifier',        n:'buscar lupa search',      l:'ki'},
        {c:'ki-duotone ki-search-list',      n:'buscar lista resultados', l:'ki'},
        {c:'ki-duotone ki-pencil',           n:'editar lapiz edit',       l:'ki'},
        {c:'ki-duotone ki-trash',            n:'eliminar borrar trash',   l:'ki'},
        {c:'ki-duotone ki-plus',             n:'agregar nuevo mas',       l:'ki'},
        {c:'ki-duotone ki-plus-circle',      n:'agregar circular',        l:'ki'},
        {c:'ki-duotone ki-minus',            n:'restar quitar menos',     l:'ki'},
        {c:'ki-duotone ki-minus-circle',     n:'quitar circular',         l:'ki'},
        {c:'ki-duotone ki-check',            n:'check confirmar ok',      l:'ki'},
        {c:'ki-duotone ki-check-circle',     n:'check circular ok',       l:'ki'},
        {c:'ki-duotone ki-check-square',     n:'check cuadrado ok',       l:'ki'},
        {c:'ki-duotone ki-cross',            n:'cerrar cancelar x',       l:'ki'},
        {c:'ki-duotone ki-cross-square',     n:'cerrar cuadrado x',       l:'ki'},
        {c:'ki-duotone ki-copy',             n:'copiar duplicar',         l:'ki'},
        {c:'ki-duotone ki-download',         n:'descargar bajar',         l:'ki'},
        {c:'ki-duotone ki-upload',           n:'subir cargar upload',     l:'ki'},
        {c:'ki-duotone ki-printer',          n:'imprimir printer',        l:'ki'},
        {c:'ki-duotone ki-scan',             n:'escanear scan codigo',    l:'ki'},
        {c:'ki-duotone ki-eye',              n:'ver ojo vista',           l:'ki'},
        {c:'ki-duotone ki-eye-slash',        n:'ocultar ojo vista',       l:'ki'},
        // Tiempo
        {c:'ki-duotone ki-calendar',         n:'calendario fecha',        l:'ki'},
        {c:'ki-duotone ki-clock',            n:'reloj tiempo hora',       l:'ki'},
        {c:'ki-duotone ki-timer',            n:'temporizador tiempo',     l:'ki'},
        // Información
        {c:'ki-duotone ki-information-5',    n:'informacion ayuda',       l:'ki'},
        {c:'ki-duotone ki-information',      n:'informacion ayuda',       l:'ki'},
        {c:'ki-duotone ki-question',         n:'pregunta ayuda faq',      l:'ki'},
        // Medios
        {c:'ki-duotone ki-picture',          n:'imagen foto picture',     l:'ki'},
        {c:'ki-duotone ki-photo-camera',     n:'camara foto',             l:'ki'},
        {c:'ki-duotone ki-video',            n:'video camara',            l:'ki'},
        {c:'ki-duotone ki-music',            n:'musica nota audio',       l:'ki'},
        {c:'ki-duotone ki-headphones',       n:'audifonos musica',        l:'ki'},
        {c:'ki-duotone ki-microphone',       n:'microfono audio',         l:'ki'},
        // Ubicación
        {c:'ki-duotone ki-map',             n:'mapa ubicacion',          l:'ki'},
        {c:'ki-duotone ki-map-2',           n:'mapa ubicacion gps',      l:'ki'},
        {c:'ki-duotone ki-location',        n:'ubicacion gps lugar',     l:'ki'},
        {c:'ki-duotone ki-globe',           n:'mundo global internet',   l:'ki'},
        // Otros útiles
        {c:'ki-duotone ki-star',            n:'estrella favorito',       l:'ki'},
        {c:'ki-duotone ki-heart',           n:'corazon favorito like',   l:'ki'},
        {c:'ki-duotone ki-like',            n:'like aprobado',           l:'ki'},
        {c:'ki-duotone ki-ranking',         n:'ranking clasificacion',   l:'ki'},
        {c:'ki-duotone ki-medal',           n:'medalla premio',          l:'ki'},
        {c:'ki-duotone ki-crown',           n:'corona premio top',       l:'ki'},
        {c:'ki-duotone ki-trophy',          n:'trofeo premio ganar',     l:'ki'},
        {c:'ki-duotone ki-flag',            n:'bandera pais',            l:'ki'},
        {c:'ki-duotone ki-pin',             n:'pin marcador',            l:'ki'},
        {c:'ki-duotone ki-bookmark',        n:'marcador guardado',       l:'ki'},
        {c:'ki-duotone ki-rocket',          n:'cohete lanzamiento',      l:'ki'},
        {c:'ki-duotone ki-flash',           n:'rayo flash rapido',       l:'ki'},
        {c:'ki-duotone ki-data',            n:'datos base datos',        l:'ki'},
        {c:'ki-duotone ki-code',            n:'codigo programacion',     l:'ki'},
        {c:'ki-duotone ki-devices',         n:'dispositivos tecnologia', l:'ki'},
        {c:'ki-duotone ki-laptop',          n:'laptop computadora',      l:'ki'},
        {c:'ki-duotone ki-mobile',          n:'celular movil telefono',  l:'ki'},
        {c:'ki-duotone ki-tablet',          n:'tablet dispositivo',      l:'ki'},
        {c:'ki-duotone ki-wifi',            n:'wifi red internet',       l:'ki'},
        {c:'ki-duotone ki-entrance-right',  n:'entrar login acceso',     l:'ki'},
        {c:'ki-duotone ki-exit-right',      n:'salir logout cerrar',     l:'ki'},
        {c:'ki-duotone ki-verify',          n:'verificar confirmar',     l:'ki'},
        {c:'ki-duotone ki-security-check',  n:'seguridad verificar',     l:'ki'},
        {c:'ki-duotone ki-teacher',         n:'maestro educacion',       l:'ki'},
        {c:'ki-duotone ki-graduation-cap',  n:'graduacion educacion',    l:'ki'},
        {c:'ki-duotone ki-briefcase',       n:'maletin trabajo negocios',l:'ki'},
        {c:'ki-duotone ki-abstract-26',     n:'modulo seccion',          l:'ki'},
        {c:'ki-duotone ki-kanban',          n:'tablero kanban proyecto', l:'ki'},
        {c:'ki-duotone ki-layer',           n:'capas layers',            l:'ki'},
        {c:'ki-duotone ki-color-swatch',    n:'colores paleta diseño',   l:'ki'},

        // ─── FONT AWESOME (Free) ─────────────────────────
        // Navegación
        {c:'fas fa-home',                n:'home inicio',             l:'fa'},
        {c:'fas fa-bars',                n:'menu hamburguesa',        l:'fa'},
        {c:'fas fa-th',                  n:'grid cuadricula',         l:'fa'},
        {c:'fas fa-th-list',             n:'lista list',              l:'fa'},
        {c:'fas fa-sitemap',             n:'organigrama estructura',  l:'fa'},
        {c:'fas fa-arrow-left',          n:'flecha izquierda',        l:'fa'},
        {c:'fas fa-arrow-right',         n:'flecha derecha',          l:'fa'},
        {c:'fas fa-chevron-up',          n:'flecha arriba',           l:'fa'},
        {c:'fas fa-chevron-down',        n:'flecha abajo',            l:'fa'},
        // Usuarios
        {c:'fas fa-user',                n:'usuario persona',         l:'fa'},
        {c:'fas fa-users',               n:'usuarios grupo personas', l:'fa'},
        {c:'fas fa-user-cog',            n:'usuario configuracion',   l:'fa'},
        {c:'fas fa-user-shield',         n:'usuario seguridad',       l:'fa'},
        {c:'fas fa-user-check',          n:'usuario verificado',      l:'fa'},
        {c:'fas fa-user-plus',           n:'agregar usuario',         l:'fa'},
        {c:'fas fa-id-card',             n:'identificacion carnet',   l:'fa'},
        {c:'fas fa-id-badge',            n:'insignia badge',          l:'fa'},
        // Seguridad
        {c:'fas fa-shield-alt',          n:'escudo seguridad',        l:'fa'},
        {c:'fas fa-lock',                n:'candado seguridad',       l:'fa'},
        {c:'fas fa-unlock-alt',          n:'desbloqueado abierto',    l:'fa'},
        {c:'fas fa-key',                 n:'llave clave acceso',      l:'fa'},
        {c:'fas fa-fingerprint',         n:'huella biometrico',       l:'fa'},
        {c:'fas fa-eye',                 n:'ver ojo vista',           l:'fa'},
        {c:'fas fa-eye-slash',           n:'ocultar ojo',             l:'fa'},
        // Archivos y documentos
        {c:'fas fa-file-alt',            n:'archivo documento',       l:'fa'},
        {c:'fas fa-folder',              n:'carpeta folder',          l:'fa'},
        {c:'fas fa-folder-open',         n:'carpeta abierta',         l:'fa'},
        {c:'fas fa-file-pdf',            n:'pdf archivo',             l:'fa'},
        {c:'fas fa-file-excel',          n:'excel archivo',           l:'fa'},
        {c:'fas fa-file-word',           n:'word archivo',            l:'fa'},
        {c:'fas fa-clipboard',           n:'portapapeles clipboard',  l:'fa'},
        {c:'fas fa-book',                n:'libro',                   l:'fa'},
        {c:'fas fa-book-open',           n:'libro abierto',           l:'fa'},
        {c:'fas fa-sticky-note',         n:'nota apunte',             l:'fa'},
        {c:'fas fa-paperclip',           n:'adjunto clip',            l:'fa'},
        // Gráficas
        {c:'fas fa-chart-bar',           n:'grafica barras reporte',  l:'fa'},
        {c:'fas fa-chart-line',          n:'grafica linea tendencia', l:'fa'},
        {c:'fas fa-chart-pie',           n:'grafica pastel pie',      l:'fa'},
        {c:'fas fa-chart-area',          n:'grafica area',            l:'fa'},
        {c:'fas fa-poll',                n:'encuesta estadistica',    l:'fa'},
        // Comercio
        {c:'fas fa-shopping-cart',       n:'carrito compras',         l:'fa'},
        {c:'fas fa-shopping-bag',        n:'bolsa compra tienda',     l:'fa'},
        {c:'fas fa-store',               n:'tienda local',            l:'fa'},
        {c:'fas fa-truck',               n:'camion envio delivery',   l:'fa'},
        {c:'fas fa-box',                 n:'caja paquete producto',   l:'fa'},
        {c:'fas fa-boxes',               n:'cajas inventario',        l:'fa'},
        {c:'fas fa-tag',                 n:'etiqueta precio',         l:'fa'},
        {c:'fas fa-tags',                n:'etiquetas precios',       l:'fa'},
        {c:'fas fa-barcode',             n:'codigo barras',           l:'fa'},
        {c:'fas fa-qrcode',              n:'codigo qr',               l:'fa'},
        // Finanzas
        {c:'fas fa-dollar-sign',         n:'dinero dolar',            l:'fa'},
        {c:'fas fa-money-bill-wave',     n:'billete dinero',          l:'fa'},
        {c:'fas fa-credit-card',         n:'tarjeta credito pago',    l:'fa'},
        {c:'fas fa-wallet',              n:'billetera cartera',       l:'fa'},
        {c:'fas fa-university',          n:'banco universidad',       l:'fa'},
        {c:'fas fa-receipt',             n:'recibo factura',          l:'fa'},
        {c:'fas fa-percentage',          n:'porcentaje descuento',    l:'fa'},
        // Comunicación
        {c:'fas fa-envelope',            n:'correo email mensaje',    l:'fa'},
        {c:'fas fa-bell',                n:'campana notificacion',    l:'fa'},
        {c:'fas fa-comment',             n:'comentario chat mensaje', l:'fa'},
        {c:'fas fa-comments',            n:'comentarios chat',        l:'fa'},
        {c:'fas fa-phone',               n:'telefono llamada',        l:'fa'},
        {c:'fas fa-paper-plane',         n:'enviar mensaje',          l:'fa'},
        // Configuración
        {c:'fas fa-cog',                 n:'configuracion ajuste',    l:'fa'},
        {c:'fas fa-cogs',                n:'configuraciones ajustes', l:'fa'},
        {c:'fas fa-sliders-h',           n:'ajustes deslizadores',    l:'fa'},
        {c:'fas fa-wrench',              n:'herramienta llave',       l:'fa'},
        {c:'fas fa-tools',               n:'herramientas ajuste',     l:'fa'},
        {c:'fas fa-filter',              n:'filtro search',           l:'fa'},
        {c:'fas fa-sort',                n:'ordenar sort',            l:'fa'},
        // Acciones
        {c:'fas fa-search',              n:'buscar lupa search',      l:'fa'},
        {c:'fas fa-edit',                n:'editar lapiz',            l:'fa'},
        {c:'fas fa-trash-alt',           n:'eliminar borrar',         l:'fa'},
        {c:'fas fa-plus',                n:'agregar nuevo mas',       l:'fa'},
        {c:'fas fa-minus',               n:'restar quitar',           l:'fa'},
        {c:'fas fa-check',               n:'check ok confirmar',      l:'fa'},
        {c:'fas fa-times',               n:'cerrar cancelar x',       l:'fa'},
        {c:'fas fa-copy',                n:'copiar duplicar',         l:'fa'},
        {c:'fas fa-download',            n:'descargar bajar',         l:'fa'},
        {c:'fas fa-upload',              n:'subir cargar',            l:'fa'},
        {c:'fas fa-print',               n:'imprimir printer',        l:'fa'},
        {c:'fas fa-sync-alt',            n:'actualizar refresh',      l:'fa'},
        // Tiempo
        {c:'fas fa-calendar-alt',        n:'calendario fecha',        l:'fa'},
        {c:'fas fa-clock',               n:'reloj tiempo hora',       l:'fa'},
        {c:'fas fa-hourglass-half',      n:'reloj arena tiempo',      l:'fa'},
        // Información
        {c:'fas fa-info-circle',         n:'informacion ayuda',       l:'fa'},
        {c:'fas fa-question-circle',     n:'pregunta ayuda faq',      l:'fa'},
        {c:'fas fa-exclamation-triangle',n:'alerta advertencia',      l:'fa'},
        // Ubicación
        {c:'fas fa-map-marker-alt',      n:'marcador ubicacion gps',  l:'fa'},
        {c:'fas fa-map',                 n:'mapa plano',              l:'fa'},
        {c:'fas fa-globe',               n:'mundo global internet',   l:'fa'},
        // Medios
        {c:'fas fa-image',               n:'imagen foto',             l:'fa'},
        {c:'fas fa-camera',              n:'camara foto',             l:'fa'},
        {c:'fas fa-video',               n:'video camara',            l:'fa'},
        {c:'fas fa-music',               n:'musica nota audio',       l:'fa'},
        {c:'fas fa-headphones',          n:'audifonos musica',        l:'fa'},
        {c:'fas fa-microphone',          n:'microfono audio',         l:'fa'},
        // Otros
        {c:'fas fa-star',                n:'estrella favorito',       l:'fa'},
        {c:'fas fa-heart',               n:'corazon favorito',        l:'fa'},
        {c:'fas fa-thumbs-up',           n:'like aprobado',           l:'fa'},
        {c:'fas fa-award',               n:'premio medalla',          l:'fa'},
        {c:'fas fa-medal',               n:'medalla premio',          l:'fa'},
        {c:'fas fa-flag',                n:'bandera pais',            l:'fa'},
        {c:'fas fa-bookmark',            n:'marcador guardado',       l:'fa'},
        {c:'fas fa-rocket',              n:'cohete lanzamiento',      l:'fa'},
        {c:'fas fa-bolt',                n:'rayo flash rapido',       l:'fa'},
        {c:'fas fa-database',            n:'base datos servidor',     l:'fa'},
        {c:'fas fa-server',              n:'servidor base datos',     l:'fa'},
        {c:'fas fa-code',                n:'codigo programacion',     l:'fa'},
        {c:'fas fa-laptop',              n:'laptop computadora',      l:'fa'},
        {c:'fas fa-mobile-alt',          n:'celular movil',           l:'fa'},
        {c:'fas fa-tablet-alt',          n:'tablet dispositivo',      l:'fa'},
        {c:'fas fa-wifi',                n:'wifi red internet',       l:'fa'},
        {c:'fas fa-sign-in-alt',         n:'entrar login acceso',     l:'fa'},
        {c:'fas fa-sign-out-alt',        n:'salir logout cerrar',     l:'fa'},
        {c:'fas fa-graduation-cap',      n:'graduacion educacion',    l:'fa'},
        {c:'fas fa-briefcase',           n:'maletin trabajo',         l:'fa'},
        {c:'fas fa-building',            n:'edificio empresa',        l:'fa'},
        {c:'fas fa-hospital',            n:'hospital clinica',        l:'fa'},
        {c:'fas fa-industry',            n:'industria fabrica',       l:'fa'},
    ];

    // ── Renderizar ícono (maneja ki-duotone y fa) ─────────
    function _renderIcono(clase) {
        if (clase.includes('ki-duotone')) {
            return `<i class="${clase}" style="font-size:1.5rem">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>`;
        }
        return `<i class="${clase}" style="font-size:1.4rem"></i>`;
    }

    // ── Renderizar grid ───────────────────────────────────
    function _renderGrid(iconos) {
        const grid = document.getElementById('icon_grid');
        const noResults = document.getElementById('icon_no_results');

        if (iconos.length === 0) {
            grid.innerHTML = '';
            noResults.classList.remove('d-none');
            return;
        }

        noResults.classList.add('d-none');
        grid.innerHTML = iconos.map(ic => `
            <div class="col-6 col-md-2 col-lg-1">
                <div class="icon-item d-flex flex-column align-items-center justify-content-center
                            p-3 rounded cursor-pointer text-center border
                            ${_claseSeleccionada === ic.c ? 'border-primary bg-light-primary' : 'border-transparent'}"
                    data-clase="${ic.c}"
                    title="${ic.c}"
                    style="min-height:70px; transition: all .15s">
                    ${_renderIcono(ic.c)}
                    <span class="text-muted mt-1" style="font-size:8px; word-break:break-all; line-height:1.2">
                        ${ic.c.replace('ki-duotone ki-','').replace('fas fa-','').replace('fab fa-','')}
                    </span>
                </div>
            </div>
        `).join('');

        // Eventos de click en cada ícono
        grid.querySelectorAll('.icon-item').forEach(el => {
            el.addEventListener('mouseenter', function() {
                if (this.dataset.clase !== _claseSeleccionada) {
                    this.classList.add('bg-light');
                }
            });
            el.addEventListener('mouseleave', function() {
                if (this.dataset.clase !== _claseSeleccionada) {
                    this.classList.remove('bg-light');
                }
            });
            el.addEventListener('click', function() {
                _claseSeleccionada = this.dataset.clase;
                // Actualizar visual de selección
                grid.querySelectorAll('.icon-item').forEach(i => {
                    i.classList.remove('border-primary', 'bg-light-primary');
                    i.classList.add('border-transparent');
                });
                this.classList.add('border-primary', 'bg-light-primary');
                this.classList.remove('border-transparent');
                // Preview en toolbar
                document.getElementById('icon_selected_preview').innerHTML =
                    _renderIcono(_claseSeleccionada);
                document.getElementById('icon_selected_name').textContent =
                    _claseSeleccionada;
            });
        });
    }

    // ── Filtrar y renderizar ──────────────────────────────
    function _filtrar() {
        const busqueda = document.getElementById('icon_search').value
                            .toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
        const filtrados = ICONOS.filter(ic => {
            const matchLib  = _libActiva === 'todos' || ic.l === _libActiva;
            const matchBusq = busqueda === '' ||
                ic.n.toLowerCase().includes(busqueda) ||
                ic.c.toLowerCase().includes(busqueda);
            return matchLib && matchBusq;
        });
        _renderGrid(filtrados);
    }

    // ── Actualizar contadores de tabs ─────────────────────
    function _actualizarContadores() {
        document.getElementById('count_todos').textContent = ICONOS.length;
        document.getElementById('count_ki').textContent    = ICONOS.filter(i=>i.l==='ki').length;
        document.getElementById('count_fa').textContent    = ICONOS.filter(i=>i.l==='fa').length;
    }

    // ── API pública ───────────────────────────────────────
    function abrir(inputId, previewId) {
        _inputId   = inputId;
        _previewId = previewId;

        // Pre-seleccionar el ícono actual del campo
        const inputActual = document.getElementById(inputId);
        _claseSeleccionada = inputActual ? inputActual.value.trim() : null;

        // Reset UI
        _libActiva = 'todos';
        document.getElementById('icon_search').value = '';
        document.querySelectorAll('#icon_tabs button').forEach(b => {
            b.className = b.dataset.lib === 'todos'
                ? 'btn btn-primary btn-sm'
                : 'btn btn-light btn-sm';
        });

        // Preview del seleccionado
        if (_claseSeleccionada) {
            document.getElementById('icon_selected_preview').innerHTML =
                _renderIcono(_claseSeleccionada);
            document.getElementById('icon_selected_name').textContent =
                _claseSeleccionada;
        } else {
            document.getElementById('icon_selected_preview').innerHTML =
                '<span class="text-muted fs-8">—</span>';
            document.getElementById('icon_selected_name').textContent = 'ninguno';
        }

        _actualizarContadores();
        _filtrar();

        if (!_modalInstance) {
            _modalInstance = new bootstrap.Modal(document.getElementById('modal_icon_picker'), {
                backdrop: false,
                keyboard: true,
                focus: true
            });
        }

        _modalInstance.show();

    }

    // Al confirmar o cancelar, ocultar backdrop manual
    function _cerrarPicker() {
        const instance = bootstrap.Modal.getInstance(document.getElementById('modal_icon_picker'));
        if (instance) {
            instance.hide();
        }
    }

    // ── Inicializar eventos ───────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('modal_icon_picker');
        _modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement, {
            backdrop: false,
            keyboard: true,
            focus: true
        });

        modalElement.addEventListener('show.bs.modal', function() {
            this.style.zIndex = '1070';
        });

        modalElement.addEventListener('shown.bs.modal', function() {
            document.getElementById('icon_search').focus();
        });


        // Búsqueda en tiempo real
        document.getElementById('icon_search').addEventListener('input', _filtrar);

        // Tabs de librería
        document.querySelectorAll('#icon_tabs button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                _libActiva = this.dataset.lib;
                document.querySelectorAll('#icon_tabs button').forEach(b => {
                    b.className = 'btn btn-light btn-sm';
                });
                this.className = 'btn btn-primary btn-sm';
                _filtrar();
            });
        });

        // Confirmar selección
        document.getElementById('btn_icon_confirmar').addEventListener('click', function() {
            if (!_claseSeleccionada) return;

            // Llenar el input
            const input = document.getElementById(_inputId);
            if (input) {
                input.value = _claseSeleccionada;
                // Disparar evento input para actualizar el preview del formulario
                input.dispatchEvent(new Event('input'));
            }

            // Actualizar preview externo si existe
            if (_previewId) {
                const prev = document.getElementById(_previewId);
                if (prev) {
                    prev.innerHTML = _renderIcono(_claseSeleccionada);
                }
            }

            _cerrarPicker();

        });

        // Botón cancelar (x y botón cancelar)
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.removeAttribute('style');
        });

    });

    return { abrir };
})();
</script>
<!--end::JavaScript del Icon Picker-->
