# MEMORY: Memoria y Base de Conocimiento del Proyecto Base

Este documento es la **fuente única de verdad (Single Source of Truth)** sobre la arquitectura, estado, módulos, base de datos y decisiones técnicas de este proyecto base. Permite que cualquier agente o desarrollador conozca de inmediato todo el contexto histórico y operativo.

---

## 1. Identidad y Propósito del Proyecto

- **Nombre**: Proyecto Base (Starter Kit Administrativo Metronic + CodeIgniter 3).
- **Propósito**: Servir como cimiento reutilizable, seguro y modular para desarrollar **cualquier sistema personal o empresarial** (ERP, CRM, Inventarios, Facturación, Gestión de Citas, Paneles de Control, etc.).
- **Regla Estricta de Búsqueda y Referencias UI**:
  - **ÚNICO DIRECTORIO DE REFERENCIA PERMITIDO**: `c:\xampp\htdocs\proyecto_base_metronic\`.
  - Queda **terminantemente prohibido** buscar, inspeccionar o leer directorios ajenos (como `PROYECTO_NUEVO`, `rose`, etc.). Todo componente, estilo, HTML o script de referencia debe obtenerse **exclusivamente** de `proyecto_base_metronic`.
- **Pila Tecnológica**:
  - **Backend**: PHP 7.4+ / 8.x con framework CodeIgniter 3.
  - **Servidor Local**: XAMPP (Apache + MySQL / MariaDB).
  - **Frontend / UI**: Metronic demo1 (Bootstrap 5, KeenIcons, DataTables, SweetAlert2, Select2, Flatpickr).
  - **Almacenamiento de Sesiones**: Archivos nativos de CodeIgniter (`ci_session*`).
  - **Manejo de Medios**: Controlador virtual seguro (`Media.php`) para evitar exposición directa del sistema de archivos.

---

## 2. Decisiones de Arquitectura Registradas (ADRs)

### ADR-001: Rutas Dinámicas desde BD vs `routes.php` Fijo
- **Decisión**: Los módulos del sistema NO se agregan manualmente a `application/config/routes.php`. Se registran en la tabla `cab_menu` (`men_url` y `men_urlamigable`).
- **Motivo**: Permite crear módulos, renombrar URLs y asignar permisos dinámicamente sin modificar el código fuente del framework ni reiniciar servicios.
- **Excepción**: En `routes.php` únicamente viven rutas públicas o fijas del sistema (`login`, `logout`, `verificar-correo`, etc.).

### ADR-002: Contrato Estricto Server-Side DataTables
- **Decisión**: Toda tabla administrativa debe operar en modo **server-side** a través del endpoint `tabla()` y el modelo correspondiente.
- **Formato Obligatorio**:
  ```json
  {
    "draw": 1,
    "recordsTotal": 150,
    "recordsFiltered": 25,
    "data": [ ... ]
  }
  ```
- **Regla**: `recordsTotal` es el total de registros no eliminados; `recordsFiltered` es el conteo tras aplicar el filtro `$search`. Nunca deben confundirse.

### ADR-003: Servido de Medios Protegidos vía `Media.php`
- **Decisión**: Los avatares y archivos de usuarios no se colocan en carpetas públicas con acceso web directo.
- **Motivo**: Previene la descarga indiscriminada de archivos y ejecución de scripts maliciosos. El controlador `Media::usuario($id)` valida sesión, detecta el tipo MIME real (`finfo`) e inyecta cabeceras `nosniff`.

### ADR-004: Monitor de Sesiones Activas en Tiempo Real
- **Decisión**: El reporte de sesiones activas (`ReportesSesionesActivas`) NO usa una tabla histórica `cab_session`. Lee directamente los archivos `ci_session*` generados por el driver de sesiones de CI.
- **Motivo**: Garantiza datos 100% reales en tiempo de ejecución, permitiendo cerrar sesiones remotamente o banear usuarios eliminando sus archivos de sesión al instante.
- **Lectura concurrente (Windows)**: el driver de CI mantiene un `flock(LOCK_EX)` sobre el archivo de la petición en curso; en Windows `file_get_contents()` devuelve vacío. Por eso la sesión propia se resuelve desde `$_SESSION` y el resto se lee con `fopen` + `flock(LOCK_SH | LOCK_NB)` y reintentos breves. Un archivo bloqueado por una petición concurrente larga puede omitirse temporalmente.
- **Parseo seguro**: se usa un parser nativo del formato `clave|valor_serializado` con `unserialize(..., ['allowed_classes' => FALSE])`; no se usa `session_decode()` para no mutar `$_SESSION`.
- **Referencia opaca**: la tabla expone un `session_token` HMAC-SHA256 del nombre del archivo, nunca `ci_session<session_id>` ni la ruta física. `detalle` compara con `hash_equals()`.
- **Autorización**: además del chequeo de `MY_Controller`, cada endpoint llama `_autorizar_modulo()` (menú `sesiones-activas`); `banear` y `cerrar_sesion` exigen POST y protegen al rol con cobertura total cuando el actor no la tiene. La vista se auto-refresca cada 10 s pausando con pestaña oculta, modal o SweetAlert.

### ADR-005: Protección del Rol de Cobertura Total (Admin Master)
- **Decisión**: El rol con cobertura de todos los menús activos está reservado al superadministrador; en la base de datos actual es `1000`, pero el código no lo referencia por ID.
- **Motivo**: Ningún usuario con menor cobertura puede asignar ese rol, modificar sus cuentas ni desactivarlas. El backend compara cobertura de menús (`Menu_model::rol_es_master()` / `cobertura_rol()`) y la interfaz oculta las opciones.

### ADR-006: Separación de Estado Funcional y Borrado Lógico
- **Decisión**: Se utilizan dos banderas independientes:
  - `<prefijo>_status`: `1` (Activo) / `0` (Inactivo o Suspendido).
  - `<prefijo>_eliminado`: `0` (Visible) / `1` (Papelera / Borrado Lógico).
- **Motivo**: Evita la pérdida accidental de datos históricos y permite reactivar registros sin corromper la integridad referencial.

### ADR-007: Consulta Preferente de Demo 10 y Uso de Demos de Metronic (`proyecto_base_metronic/dist/html/`)
- **Decisión**: Al maquetar vistas, dashboards o componentes nuevos, la fuente de referencia principal y **preferida** es `c:\xampp\htdocs\proyecto_base_metronic\dist\html\demo10/`. No obstante, **queda plenamente habilitada la consulta e importación de componentes y layouts de cualquiera de los demás demos** (`demo1`, `demo2`, ..., `demo9`) dentro de `proyecto_base_metronic/dist/html/`.
- **Motivo**: Aunque `demo10` concentra plantillas de alta fidelidad para tiendas, membresías, perfiles y tablas administrativas, otros demos (como `demo1`, `demo2`, etc.) ofrecen barras laterales, headers, tarjetas de analítica y widgets especializados que complementan y enriquecen la interfaz.
- **Catálogo de Referencia en `demo10` (Preferido)**:
  * `account/billing/`: Facturación, planes, historial de cobros.
  * `account/members/`: Roles, permisos toggle, tablas de integrantes y equipos (`team-members-datatable.html`).
  * `account/security/`: IPs permitidas, sesiones activas, logs de auditoría y seguridad.
  * `store-client/`: E-commerce, inventario, catálogo, productos, carrito, checkout, órdenes y recibos.
  * `network/user-table/`: Tablas especializadas (`app-roster.html`, `saas-users.html`, `store-clients.html`, `visitors.html`).
  * `public-profile/`: Vistas de perfil CRM, corporativo, tarjetas y proyectos.
  * `plugins/`: Calendarios e integraciones avanzadas.
- **Otros Demos Habilitados (`dist/html/`)**:
  * `demo1/`: Layout canónico, sidebars oscuros/claros (`dashboards/dark-sidebar.html`), headers y widgets generales.
  * `demo2` a `demo9`: Tableros analíticos, CRM, paneles de control y vistas complementarias.
- **Procedimiento**:
  1. Revisar preferentemente `demo10` o el demo idóneo (`demo1` a `demo9`) según el tipo de componente requerido.
  2. Extraer o adaptar la apariencia, clases CSS, badges y KeenIcons en `application/views/pages/<modulo>/index.php`.
  3. Vincular los formularios y DataTables al estándar canónico server-side y endpoints de `proyecto_base`.

### ADR-008: Autenticación Social OAuth (Google & Apple)
- **Decisión**: Los botones de inicio de sesión con Google y Apple son completamente configurables desde `ConfiguracionTema`.
- **Comportamiento**:
  - Si ambos están inactivos, la interfaz de login y registro oculta los botones y el separador, dejando únicamente los campos tradicionales.
  - Si uno o ambos están activos, se muestran en grilla responsiva (`col-6` si están ambos, `col-12` si está uno).
  - Al autenticar:
    * Si el email ya existe en `cab_usuarios`, valida que esté activo y loguea directamente registrando en `cab_bitacora` (`LOGIN_GOOGLE` / `LOGIN_APPLE`).
    * Si no existe y el registro público está activado, crea el usuario automáticamente con estado verificado (`usu_status = '1'`) y el rol público asignado, iniciando sesión sin pasos intermedios.
    * Si no existe y el registro público está desactivado, muestra mensaje explicativo bloqueando el acceso.

### ADR-009: MY_Email y Resiliencia en Transporte SMTP
- **Decisión**: Extender `CI_Email` mediante `application/libraries/MY_Email.php` para implementar negociación TLS moderna (TLS 1.2 / TLS 1.3), `stream_context` con opciones SSL seguras y detección de timeout en streams.
- **Motivo**: CodeIgniter 3 original utiliza constantes TLS obsoletas en `stream_socket_enable_crypto()` que provocan `SSL: Handshake timed out` o exceden el `max_execution_time` de 30s en PHP 8.x en entornos Windows/XAMPP locales si el servidor SMTP bloquea o retarda la negociación. Además, el flujo de recuperación de clave ahora captura excepciones de envío sin interrumpir la experiencia del usuario.

### ADR-010: Rediseño del Módulo de Perfil basado en Metronic Demo 10
- **Decisión**: Reestructurar la vista `application/views/pages/perfil/index.php` adoptando el patrón hero banner y navegación por pestañas de `account/home/user-profile.html`, los controles de formulario y avatar de `account/home/settings-sidebar.html`, y las tablas de sesiones y auditoría de `account/security/current-sessions.html` y `account/security/security-log.html`.
- **Mejoras**:
  1. Hero card superior con avatar de 130px, indicador de estado online, badges de rol, correo, teléfono y métricas rápidas.
  2. Pestañas (`Información Personal`, `Seguridad y Clave`, `Sesiones y Actividad`) sin recarga de página.
  3. Soporte del componente nativo `KTImageInput` para previsualización inmediata de imagen sin guardar.
  4. Selector de visibilidad de contraseña (ojito) en los 3 campos de clave.

### ADR-011: Autenticación de Dos Pasos (2FA) Demo10 Classic y Mitigación de Fuerza Bruta
- **Decisión**: Implementar la verificación 2FA basada en Metronic Demo10 Classic (`application/views/auth/dos_factores.php`) con inputs PIN compactos en una sola línea, avance y retroceso automático, soporte de pegado rápido (paste de 6 dígitos) y protección estricta contra fuerza bruta a nivel de base de datos.
- **Mecanismo de Seguridad Anti-Fuerza Bruta**:
  1. **Límite de Intentos**: Máximo 5 intentos consecutivos fallidos de verificación del código token.
  2. **Bloqueo Persistente en BD**: En la tabla `cab_usuarios` se registran `usu_2fa_intentos` y `usu_2fa_bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE)`. Al persistirse en base de datos, el atacante no puede eludir el bloqueo abriendo nuevas ventanas en incógnito o cambiando de sesión.
  3. **Comportamiento Progresivo**:
     - Intentos 1 al 4: Notifica al usuario en pantalla la cantidad de intentos restantes antes del bloqueo de seguridad.
     - 5° intento fallido: Bloquea la cuenta por 15 minutos, expulsa la sesión temporal 2FA, envía un correo de alerta de seguridad inmediata (`_enviar_alerta_bloqueo_2fa`) y redirige al login con el tiempo de espera.
  4. **Protección en Todos los Puntos de Entrada**: `autenticar()`, `dos_factores()`, `verificar_factor()` y `reenviar_factor()` verifican si existe un bloqueo activo antes de procesar cualquier acción.
  5. **Autocuración**: Al cumplirse el plazo de 15 minutos o al introducir el código correcto, los contadores se restablecen automáticamente (`usu_2fa_intentos = 0`, `usu_2fa_bloqueado_hasta = NULL`).
  6. **Auditoría Completa**: Registros detallados en `cab_bitacora` (`LOGIN_2FA_FALLIDO`, `LOGIN_2FA_BLOQUEO_FUERZA_BRUTA`, `LOGIN_2FA_BLOQUEADO`, `LOGIN_2FA`).
  5. **Tablas con estilo Metronic nativo**:
     - *Inicios de Sesión Recientes* (`current-sessions.html`): `table-row-dashed table-row-gray-200 align-middle`, iconos dinámicos de navegadores (Chrome, Firefox, Safari, Edge, Android), badge con viñeta (`bullet-dot`) y fecha relativa + exacta.
     - *Registro de Seguridad y Auditoría* (`security-log.html`): columnas estructuradas (*Evento*, *Acción y Detalle*, *IP Origen*, *Navegador*, *Fecha/Hora*), filas con micro-interacción hover (`hover-elevate-up`) y badges según severidad/tipo de acción.

### ADR-011: Estandarización de Tablas Administrativas y Directorio de Referencia
- **Decisión 1 (Directorio de Referencia Exclusivo)**: Únicamente está permitido consultar el proyecto hermano `c:\xampp\htdocs\proyecto_base_metronic\`. Queda terminantemente prohibido acceder o solicitar acceso a directorios ajenos (`PROYECTO_NUEVO`, `rose`, etc.).
- **Decisión 2 (Diseño de Tablas y Acciones Metronic)**: Todas las tablas administrativas (`Usuarios`, `Perfil`, etc.) deben adoptar la estructura visual de Metronic Demo 10 (`network/user-table/saas-users.html` y `account/members/team-members-datatable.html`):
  1. Card contenedora: `card card-flush shadow-sm` con buscador integrado en el header (`card-title`).
  2. Encabezado de tabla: `table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4` con `text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200`.
  3. Estado: Badges con viñeta (`bullet bullet-dot bg-success/danger me-1`).
  4. Acciones: Botones compactos con elevación sutil (`shadow-xs`), tooltips nativos de Bootstrap y badge protegido con icono para registros de alta jerarquía.

### ADR-012: Módulo Núcleo de Configuración General y Control Global
- **Decisión**: Implementar un módulo maestro `ConfiguracionGeneral` respaldado por la tabla clave-valor `cab_configuracion_general` para centralizar:
  1. **Identidad Corporativa**: Nombre comercial, identificación fiscal (RIF/Tax ID), canales de soporte (correo, teléfono, WhatsApp) y dirección física.
  2. **Configuración Multi-Moneda y Tasas**: Moneda principal (ej. USD, $), moneda secundaria (ej. VES, Bs.) y tasa de cambio del día, listas para ser consumidas por cualquier módulo de ventas, rifas o recargas.
  3. **Modo de Mantenimiento Global**: Switch que restringe el acceso a todo el sistema a roles sin cobertura total mostrando una pantalla con título y mensaje personalizados, permitiendo a los roles con cobertura total seguir operando.
  4. **Banner Global de Avisos**: Cintillo informativo configurable (tipo de alerta: info, warning, danger, success) que se proyecta en la parte superior de todas las pantallas protegidas a través de `MY_Controller` y `main.php`.
- **Diseño**: Basado en `account/home/company-profile.html` de Metronic Demo 10 con cards `card-flush`, switch components y validación server-side.

### ADR-013: Arquitectura del Sidebar Oscuro Demo 1 y su Integración con el Sistema de Temas Dinámicos
- **Decisión**: Adoptar la estructura visual, dimensiones y micro-interacciones del sidebar oscuro de Metronic Demo 1 (`proyecto_base_metronic/dist/html/demo1/dashboards/dark-sidebar.html`) centralizado en `assets/css/demo1-dark-sidebar.css` y `application/views/partials/_sidebar.php`, garantizando al mismo tiempo la **configurabilidad total** desde el módulo `ConfiguracionTema`.
- **Componentes y Estilo Metronic Demo 1**:
  1. **Estructura y Dimensiones**: Ancho canónico de 280px (80px al colapsar), cabecera de 70px con línea divisoria sólida y soporte responsivo completo (`data-kt-drawer`).
  2. **Botón Toggle Flotante**: Botón circular de 30x30px (`app-sidebar-toggle`) posicionado directamente sobre el borde divisorio derecho, con fondo blanco, sombra sutil (`box-shadow: 0 1px 4px rgba(0,0,0,0.12)`), borde de 1px e icono `ki-black-left-line`.
  3. **Hover Refinado**: Reemplazo de bloques opacos invasivos por un efecto moderno con fondo translúcido (`rgba(255, 255, 255, 0.05)`), texto en brillo activo e icono iluminado con el color primario/activo.
  4. **Árbol de Submenús**: Línea vertical de jerarquía (`border-left: 1px solid var(--demo1-sidebar-border)`) con viñetas circulares de 6px (`.menu-bullet`) que escalan e iluminan en hover y activo.
  5. **Submenú Activo en Píldora**: Resaltado estilizado con fondo translúcido al 15% y borde al 25% del color activo configurado.
- **Contrato de Configurabilidad Total (Tema Dinámico)**:
  - Todas las variables CSS del sidebar consumen los valores dinámicos administrados en `cab_configuracion_tema` vía `Configuracion_tema_model.php`:
    * `sidebar_bg_color` → `--app-sidebar-bg` / `--demo1-sidebar-bg`: Fondo del sidebar.
    * `sidebar_text_color` → `--app-sidebar-text` / `--demo1-sidebar-text`: Color de títulos y menús inactivos.
    * `sidebar_active_bg_color` → `--app-sidebar-active-bg` / `--demo1-sidebar-primary`: Fondo del ítem activo raíz, píldora de submenús activos, icono activo/hover y viñetas.
    * `sidebar_active_text_color` → `--app-sidebar-active-text` / `--demo1-sidebar-active-text`: Color de texto del ítem activo y texto en hover.
    * `sidebar_width_px` → `--app-sidebar-width` / `--demo1-sidebar-width`: Ancho del sidebar y desplazamiento del layout.
    * `sidebar_font_weight` → `--app-sidebar-font-weight` / `--demo1-sidebar-font-weight`: Peso tipográfico del menú.
    * `dashboard_logo_height_px` → `--app-dashboard-logo-height`: Altura del logo principal en la cabecera.
  - Se eliminaron las reglas inline rígidas con `!important` que bloqueaban la personalización o ensuciaban el hover, manteniendo un puente desacoplado entre el CSS de Demo 1 y el generador de estilos en tiempo de ejecución.

### ADR-014: Trazabilidad y Registro Obligatorio en Bitácora en Todo Nuevo Módulo
- **Decisión**: **ABSOLUTAMENTE TODOS los módulos nuevos** que se creen en el sistema (administrativos, operativos, comerciales, inventarios, facturación, etc.) **DEBEN registrar en `cab_bitacora`** cada una de las mutaciones o eventos de cambio de estado a través del helper nativo de `MY_Controller`:
  ```php
  $this->bitacora($accion, $detalle);
  ```
- **Reglas Estrictas de Implementación**:
  1. **Nomenclatura Estándar de `$accion`**: Formato `<MODULO>_<OPERACION>` en mayúsculas (ejemplos: `CLIENTE_CREAR`, `CLIENTE_ACTUALIZAR`, `CLIENTE_ELIMINAR`, `VENTA_PROCESAR`, `PRODUCTO_STOCK_AJUSTAR`, `PEDIDO_CAMBIAR_ESTADO`).
  2. **Contenido Obligatorio de `$detalle`**: Debe especificar como mínimo el identificador (`ID`) del registro impactado y un resumen legible de los campos clave o el cambio realizado (ejemplo: `ID: 120 | Nombre: Juan Pérez | RIF: V-12345678 | Acción: Registro creado`).
  3. **Métodos Mutadores Afectados**: Todo método que altere la base de datos (`crear()`, `actualizar()`, `eliminar()`, cambio de switches de estado, etc.) debe invocar `bitacora()` **inmediatamente después de confirmar la persistencia exitosa** y antes de retornar la respuesta JSON.
- **Motivo**: Garantizar auditoría forense integral, trazabilidad de responsabilidades (usuario, fecha, hora, dirección IP) y transparencia operativa en cualquier vertical construida sobre este proyecto base.

### ADR-015: Estandarización de Botones de Acción Compactos (btn-sm) e Iconografía Obligatoria
- **Decisión 1 (Tamaño Compacto `btn-sm`)**: Todos los botones de acción del sistema (Guardar, Cancelar, Enviar, Actualizar, Volver, etc.), tanto en modales (`modal-footer`), como en tarjetas de configuración y barras de herramientas (`card-toolbar` / `card-footer`), **deben utilizar obligatoriamente el modificador de tamaño compacto `btn-sm`** (ejemplos: `btn btn-sm btn-primary`, `btn btn-sm btn-light`, `btn btn-sm btn-light-primary`).
- **Decisión 2 (Iconografía Obligatoria en Botones con Texto)**: **Absolutamente todo botón de acción que contenga texto DEBE incluir un icono de la librería KeenIcons (`ki-duotone`) representativo de la acción que realiza**:
  * **Guardar / Confirmar**: `<i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Cancelar**: `<i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Volver**: `<i class="ki-duotone ki-arrow-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Enviar**: `<i class="ki-duotone ki-send fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Actualizar Clave / Seguridad**: `<i class="ki-duotone ki-key fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Nuevo Registro**: `<i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Exportar**: `<i class="ki-duotone ki-exit-up fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
  * **Eliminar**: `<i class="ki-duotone ki-trash fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>`
- **Prohibición**: Queda terminantemente prohibido utilizar botones de tamaño por defecto sin `btn-sm`, botones con padding sobredimensionado (`px-6 py-3`), o botones con texto plano sin icono en acciones de formularios o modales.
- **Motivo**: Mantener una interfaz refinada, compacta, profesional y visualmente autoexplicativa coherente con Metronic Demo 10.

### ADR-016: Adaptación y Visibilidad de Íconos en Colapso del Sidebar Metronic Demo 1
- **Decisión**: El colapso del sidebar mediante `#kt_app_sidebar_toggle` (`body[data-kt-app-sidebar-minimize="on"]`) adapta integralmente la interfaz tanto en modo retraído (80px) como en expansión flotante por hover:
  1. **Adaptación de Contenedores Globales**: En pantallas de escritorio (`min-width: 992px`), `.app-wrapper` y `.app-header` transicionan fluidamente a `margin-left: 80px` y `left: 80px` (sin dejar espacios vacíos respecto al sidebar).
  2. **Contenedores y Centrado de Íconos con Separación Vertical**: `#kt_app_sidebar_menu_wrapper`, `#kt_app_sidebar_menu_scroll` y `.menu` adaptan su ancho al 100% (80px), y cada enlace (`.menu-link`) se convierte en una baldosa cuadrada centrada de 44x44px (`border-radius: 8px`). Los ítems cuentan con una generosa separación vertical (`gap: 14px` en colapso, `gap: 8px` en expandido) para evitar que se vean amontonados. El ícono KeenIcons se mantiene centrado (20px), perfectamente visible y con sus colores configurados.
  3. **Ocultamiento Limpio y Flecha de Acordeón (`menu-arrow`)**: Títulos (`.menu-title`), flechas (`.menu-arrow`), submenús (`.menu-sub-accordion`) y encabezados se ocultan limpiamente en colapso (`display: none !important`), mientras que en modo expandido y hover `.menu-arrow:after` renderiza un chevron SVG nítido y visible (`background-color: rgba(255, 255, 255, 0.6)`) que rota 90° hacia abajo (`rotate(90deg)`) y se ilumina en blanco al abrir el submenú (`.show`).
  4. **Logos y Botón Toggle**: Oculta el logo extendido y centra el logo compacto (`.app-sidebar-logo-minimize`). El botón `#kt_app_sidebar_toggle` mantiene su acabado limpio y neutro original en blanco/gris (sin tintes de color), incorporando el ícono moderno de doble chevron (`ki-duotone ki-double-left`) que rota 180° indicando con precisión la dirección de repliegue/expansión.
  5. **Expansión al Posicionar Cursor (Hover Expansion)**: Al pasar el cursor sobre el sidebar colapsado, este se expande a 280px con sombra `0 0 32px rgba(0,0,0,0.45)`, mostrando temporalmente los textos y menús sin desplazar el contenido del layout, y replegándose suavemente al retirar el cursor.

### ADR-017: Barra Superior: Foto de Perfil (shrink-0), Menú de Usuario y Notificaciones estilo Metronic Demo 1
- **Decisión**: Modernizar la cabecera del sistema (`application/views/partials/_header.php`) y los estilos complementarios en `assets/css/demo1-dark-sidebar.css` siguiendo con total fidelidad el diseño de **Metronic Demo 1** (`demo1/index.html`):
  1. **Disparador de Foto de Usuario (`shrink-0`)**:
     - Contenedor con la clase `.shrink-0` y activación por click/hover.
     - Foto de 36px (`.topbar-avatar-user`) o iniciales fallback (`.topbar-avatar-user-initial`) con anillo de conexión verde esmeralda idéntico a Demo 1 (`border: 2px solid #22c55e`), y efecto de resplandor `box-shadow` al estar en hover o desplegado (`.shrink-0.open` / `.shrink-0.show`).
  2. **Menú Desplegable de Usuario (`kt-dropdown-menu` / `menu-sub-dropdown`)**:
     - Cabecera compacta con foto de 36px, nombre de usuario, correo electrónico y badge outline dinámico con el rol (`Admin Master` / perfil actual).
     - Ítems de navegación con KeenIcons (`Mi Perfil` con `ki-profile-circle`, `Configuración` con `ki-setting-2`).
     - Fila interactiva de Modo Oscuro con interruptor switch (`#kt_user_menu_dark_mode_toggle`) que alterna dinámicamente entre `light` y `dark` persistiendo en `localStorage`.
     - Botón de cierre de sesión estilizado (`btn btn-sm btn-outline btn-outline-default`) con icono obligatorio `ki-exit-right`.
  3. **Disparador y Menú de Notificaciones**:
     - Botón circular fantasma de 35px con icono `ki-notification-status`, punto indicador animado (`animation-blink`) y contador de insignias.
     - Menú con cabecera, botón de marcado de lectura, pestañas de filtrado ("Todas" y "No leídas"), contenedor con scroll y pie de enlace a la vista completa con icono `ki-eye`.

### ADR-018: Gestión de Paletas de Alto Contraste (Claro y Oscuro) en Configuración del Tema
- **Decisión**: Separar y organizar integralmente los colores del sistema en dos pestañas dentro del módulo `ConfiguracionTema` (*Modo Claro* y *Modo Oscuro*), dotando a la barra superior (`#kt_app_header`), sidebar, footer y login de variables independientes para ambos modos.
- **Valores por Defecto (Inspirados en Metronic Demo 1 y Demo 10)**:
  * **Modo Oscuro**:
    - `dark_primary_color`: `#1B84FF` (azul vibrante Metronic).
    - `dark_button_hover_color`: `#056EE9`.
    - `dark_header_bg_color`: `#0D0E12` (header oscuro canónico con borde `rgba(255, 255, 255, 0.07)` y sin sombras blancas).
    - `dark_header_text_color`: `#FFFFFF` (texto y botones de alto contraste).
    - `dark_sidebar_bg_color`: `#0F1014` (sidebar Demo 1).
    - `dark_sidebar_text_color`: `#9A9CAE`.
    - `dark_sidebar_active_bg_color`: `#1B84FF`.
    - `dark_sidebar_active_text_color`: `#FFFFFF`.
    - `dark_footer_bg_color`: `#0D0E12`.
    - `dark_footer_text_color`: `#78829D`.
    - `dark_body_bg_color`: `#0F1014`.
    - `dark_login_card_bg_color`: `#15171C`.
- **Implementación Técnica**:
  1. `cab_configuracion_tema`: Nuevas claves almacenadas y migradas en `database/2026-10-25_add_dark_mode_colors_cab_configuracion_tema.sql`.
  2. `Configuracion_tema_model::build_theme_css()`: Genera dinámicamente las variables CSS bajo `:root, [data-bs-theme="light"]` y `[data-bs-theme="dark"]`, permitiendo una alternancia instantánea sin recarga al accionar el switch de la barra superior.
  3. Vista `pages/configuracion_tema/index.php`: Pestañas Metronic `nav-line-tabs` con selector interactivo de color e indicación visual de código hexadecimal.
  4. **Regla Obligatoria para Nuevos Módulos**: Todo nuevo módulo debe maquetarse sin colores fijos quemados (`style="background: #fff"`, `style="color: #000"`), empleando clases utilitarias semánticas de Bootstrap/Metronic (`text-gray-900`, `text-muted`, `bg-body`, `card card-flush`, `table-row-dashed`) o variables CSS de tema (`var(--bs-primary)`, `var(--bs-body-bg)`), garantizando una adaptación automática e impecable tanto en Modo Claro como en Modo Oscuro.

### ADR-019: Grupos de Destinatarios Segmentados en Correos Promocionales
- **Decisión**: Extender el módulo `CorreosPromocionales` con capacidad para crear, editar, eliminar y aplicar grupos de usuarios segmentados (ej. "Usuarios Más Activos", "Clientes VIP", "Administradores") para seleccionar destinatarios en masa con un solo clic.
- **Estructura de Datos**:
  - `cab_correo_grupos`: `cgr_id`, `cgr_nombre`, `cgr_descripcion`, `cgr_color`, `cgr_status`, `cgr_eliminado`, marcas de tiempo.
  - `det_correo_grupos_usuarios`: `dgu_id`, `dgu_grupo_id`, `dgu_usuario_id VARCHAR(50)`, `dgu_created_at`.
- **Implementación**:
  1. **Barra de Pills Dinámica**: Renderiza los grupos activos con distintivos de color y conteo de miembros. Al hacer clic en un pill, marca automáticamente todos los destinatarios correspondientes mediante `/CorreosPromocionales/grupo_usuarios/:id` y actualiza el contador global.
  2. **Modal Gestionar Grupos (`#modal_grupos`)**: Pestañas para ver mis grupos (con opciones de usar, editar o eliminar con SweetAlert) y pestaña para crear/editar con buscador de usuarios y selección masiva.
  3. **Auditoría**: Registra eventos en `cab_bitacora` (`CORREO_GRUPO_CREAR`, `CORREO_GRUPO_ACTUALIZAR`, `CORREO_GRUPO_ELIMINAR`).
  4. **Migración SQL y Auto-curación**: `database/2026-10-25_create_cab_correo_grupos.sql` y `database/2026-10-25_alter_det_correo_grupos_usuarios_varchar.sql` con auto-aseguramiento en tiempo de ejecución en `asegurar_tablas_grupos()`.

### ADR-020: Convención Estricta de Identificadores de Usuario Alfanuméricos (`usu_id VARCHAR`)
- **Decisión**: En `cab_usuarios`, los identificadores de usuario (`usu_id`) se generan con ceros a la izquierda (formato alfanumérico fijo de longitud 6, ej. `"000001"`, `"000002"`).
- **Regla de Integridad**:
  - Toda tabla relacional o de detalle que guarde una referencia a un usuario debe definir el campo como `VARCHAR(50) NOT NULL` (nunca `INT` o `BIGINT`), para evitar la truncación o pérdida de los ceros a la izquierda.
  - En backend (controladores y modelos PHP), se debe tratar el ID siempre como `string` (mediante `trim((string)$uid)` o `strval()`), prohibiendo expresamente el casteo `(int)$uid`.
  - En vistas HTML y frontend JavaScript, los valores de checkboxes y opciones deben imprimirse como cadena (`value="<?php echo htmlspecialchars((string)$dest['usu_id']); ?>"`) y compararse como strings normalizados (`String(id).trim()`).

### ADR-021: Control de Sesiones por Inactividad Real (Sliding Expiration) y Mitigación de Concurrencia CI3
- **Decisión**: La expiración de la sesión se rige exclusivamente por el tiempo de **inactividad real del usuario**, y nunca por un plazo fijo desde el inicio de sesión.
- **Implementación Técnica**:
  1. **Sliding Expiration en `MY_Controller::_verificar_sesion()`**:
     - Cada interacción genuina del usuario actualiza la marca de tiempo `last_activity` y desliza la expiración de la cookie del navegador mediante `_renovar_cookie_sesion()`.
     - Si el tiempo transcurrido desde `last_activity` supera el límite configurado (`sesion_inactividad_minutos` de `ConfiguracionGeneral` o `SESSION_EXPIRATION`), la sesión se destruye y se redirige a `login?expired=1` (o código HTTP 401 en AJAX).
  2. **Aislamiento de Sondeos en Segundo Plano (`X-Background-Poll`)**:
     - Las consultas automáticas como el feed de notificaciones cada 60s envían la cabecera `X-Background-Poll: 1`. Esto permite verificar que la sesión siga viva sin renovar artificialmente `last_activity` si el usuario abandonó el equipo.
  3. **Heartbeat de Interacción en Cliente (`_scripts.php`)**:
     - Un detector pasivo en JavaScript (`mousemove`, `keydown`, `mousedown`, `scroll`, `touchstart`) envía un ping liviano a `/ping-sesion` como máximo cada 2 minutos mientras el usuario interactúa, protegiendo tareas largas de redacción o edición sin necesidad de recargar la página.
  4. **Eliminación de Condiciones de Carrera (`SESSION_TIME_TO_UPDATE=0`)**:
     - Se desactivó la regeneración automática de ID de sesión a mitad de uso (`SESSION_TIME_TO_UPDATE=0`), eliminando la creación de sesiones vacías huérfanas por concurrencia AJAX. La regeneración se realiza de forma segura y única al autenticar con éxito en `Auth.php` (`$this->session->sess_regenerate(FALSE)`).
  5. **Configuración Dinámica en `ConfiguracionGeneral`**:
     - Se incorporó el control visual `Tiempo de Inactividad de Sesión` en `ConfiguracionGeneral` (120 minutos por defecto, editable entre 5 y 1440 min).





### ADR-022: Módulo de Plantillas de Correo Transaccionales (`PlantillasCorreo`)
- **Decisión**: Centralizar y dinamizar los correos del sistema en la tabla `cab_plantillas_correo` (`BIENVENIDA`, `RECUPERAR_CLAVE`, `VERIFICAR_CORREO`, `LOGIN_2FA`, `AVISO_SEGURIDAD_IP`), permitiendo al administrador editar el asunto, cuerpo HTML con CKEditor 5, botones CTA y footer sin tocar código fuente.
- **Características**:
  - Píldoras clicables de variables dinámicas (`{usuario_nombre}`, `{enlace_accion}`, `{codigo_verificacion}`, `{empresa_nombre}`, etc.).
  - Previsualizador responsivo con conmutador Desktop (600px) y Móvil (375px).
  - Envío de prueba inmediato a cualquier dirección usando el transporte SMTP configurado.
  - Integración nativa con `Auth.php` para que los flujos de recuperación y verificación usen las plantillas dinámicas con fallback seguro a archivos PHP.
  - Protección de plantillas del sistema (no pueden ser eliminadas, solo desactivadas o editadas).

---

### ADR-023: Endurecimiento de Seguridad Integral (RBAC, CSRF, Sesiones, Correo y Cabeceras)

- **Fecha**: 2026-10-28.
- **Contexto**: La auditoría encontró bypass de RBAC por URL directa, ausencia de
  CSRF global, contraseñas heredadas en MD5, OAuth sin 2FA, verificación TLS
  desactivada en SMTP, XSS en plantillas/preview, archivos sensibles servidos
  públicamente y un rol de registro público (`1002`) con CRUD sobre Usuarios.
- **Decisión 1 — RBAC deny-by-default**: `MY_Controller::_verificar_acceso_url()`
  resuelve el módulo actual desde el snapshot de menús de la sesión
  (`menu_estructura`) comparando la URL con `men_url`/`men_urlamigable`. Si el
  módulo no está en la estructura del rol, se bloquea. El rol con cobertura
  total es la única excepción global. `media`, `errors`, `perfil`, `dashboard`,
  `logout`, `ping-sesion`, `sin-permisos` y `mantenimiento` son rutas libres; el
  feed de notificaciones se declara en `$metodos_libres`.
- **Decisión 2 — Autorización por acción**: Todo endpoint de mutación debe llamar
  `autorizar_accion('incluir|consultar|modificar|eliminar', 'Mensaje')` después
  de validar que sea AJAX/POST. El menú se resuelve por la URL del módulo, sin
  IDs. Aplicado en `Usuarios`, `Menus`, `Permisos`, `Notificaciones`,
  `ConfiguracionTema`, `ConfiguracionCorreos`, `CorreosPromocionales`,
  `ConfiguracionGeneral`, `PlantillasCorreo`, `ReportesSesionesActivas`,
  `ReportesBitacora` y `ReportesLogins`. Toda mutación exige POST (GET devuelve
  405/404); `Permisos` impide que un rol sin cobertura total modifique el rol
  maestro.
- **Decisión 3 — Jerarquía de roles**: `Usuarios` y `Permisos` comparan la
  cobertura de menús de los roles (`Menu_model::cobertura_rol()`), no IDs:
  nadie asigna roles de cobertura igual o superior a la propia, nadie gestiona
  cuentas de cobertura igual/superior (salvo la propia) y solo el rol con
  cobertura total cambia la clave de otro usuario. Un usuario no puede cambiar
  su propio rol.
- **Decisión 4 — Registro público**: La configuración
  `public_registration_role_id` apunta actualmente al rol `1002`; el rol `1004`
  ("Registrado") solo existe en `cab_permisologia` y no en `cab_grupopermi`, por
  lo que no es asignable. `rol_registro_publico_valido()` exige rol activo, no
  maestro y sin menús administrativos sensibles (resueltos por
  `men_urlamigable`). Migración:
  `database/2026-10-28_security_hardening_rbac.sql` (idempotente).
- **Decisión 5 — CSRF global**: `csrf_protection=TRUE` con token `csrf_token`,
  cookie `csrf_cookie`, sin regeneración por petición y exclusión únicamente de
  los flujos de `Auth`. `partials/_csrf.php` publica `csrf-name`/`csrf-token` e
  inyecta el token en `fetch`, `XMLHttpRequest` y formularios POST del mismo
  origen. Se incluye desde `partials/_head.php` y `auth/_auth_top.php`.
- **Decisión 6 — Contraseñas**: Se acepta MD5 solo para cuentas heredadas,
  comparando con `hash_equals` y re-hasheando inmediatamente a
  `password_hash(PASSWORD_DEFAULT)`; también se re-hashea cuando
  `password_needs_rehash()` lo indica. Las claves nuevas se guardan siempre con
  `password_hash`.
- **Decisión 7 — OAuth**: Un usuario con `usu_2fa=1` no puede entrar por Google o
  Apple; debe usar usuario/contraseña + 2FA. El `id_token` de Apple se verifica
  criptográficamente (RS256, JWKS de Apple con caché de 1 h, `iss`, `aud`, `exp`
  e `iat`), no solo se decodifica.
- **Decisión 8 — Correo**: `MY_Email` verifica TLS por defecto
  (`MAIL_VERIFY_PEER=1`); solo un servidor heredado puede desactivarlo por
  entorno. La clave SMTP nunca se imprime en la vista: el campo queda vacío con
  placeholder y, si se envía vacío, el controlador conserva la clave guardada.
- **Decisión 9 — XSS en correo**: El cuerpo de plantillas se sanitiza al guardar
  (elimina `script/iframe/object/embed/form/meta/link/base`, manejadores `on*` y
  esquemas `javascript:`/`data:`/`vbscript:`). La previsualización se sirve con
  `Content-Security-Policy: sandbox; default-src 'none'`. Los SweetAlert usan
  `text:` en lugar de `html:` cuando interpolan datos del usuario.
- **Decisión 10 — Archivos y cabeceras**: `.htaccess` (Apache) y `web.config`
  (IIS) bloquean `.env*`, `composer.*`, `MEMORY.md`, `AGENTS.md`, `README.md`,
  `web.config`, `*.sql|md|ini|log|lock|bak|dist|swp|old` y los directorios
  `application`, `system`, `database`, `.git`, `upload`. Las cabeceras
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, `X-Permitted-Cross-Domain-Policies` y
  `Cross-Origin-Opener-Policy` se envían desde el servidor web para cubrir
  estáticos; `hooks/Security_headers.php` agrega CSP, HSTS (solo HTTPS) y elimina
  `X-Powered-By` en respuestas dinámicas.
- **Regla para módulos nuevos**: registrar el menú en `cab_menu`, asignarlo en
  `cab_permisologia`, crear su fila en `cab_permiaccion`, llamar
  `autorizar_accion()` en cada mutación, usar `partials/_csrf.php` (ya incluido
  por el layout) y no introducir colores rígidos ni HTML sin escapar.
- **Validación ejecutada el 2026-10-28**:
  - `php -l` sobre 139 archivos PHP: 0 errores.
  - `.env`, `MEMORY.md`, `database/*.sql` y `application/*`: HTTP 403.
  - Rol `1004`: dashboard 200; `Usuarios`, `Menus` y `ConfiguracionGeneral`
    redirigen a `sin-permisos`; `notificaciones/feed` responde 200.
  - Rol `1001`: reportes (93/115) y correos promocionales (118) 200; módulos de
    administración 403/redirección.
  - Rol `1000`: Usuarios, Menús, Permisos, Configuración y Notificaciones 200.
  - POST AJAX sin token CSRF: 403; con token pero sin permiso: 403 JSON.
  - GET a endpoints de mutación (`Usuarios/eliminar`, `Menus/eliminar`,
    `Permisos/eliminar`, `PlantillasCorreo/eliminar`,
    `Notificaciones/eliminar`, `CorreosPromocionales/grupo_eliminar`): 405.
  - `Perfil/guardar` y `Perfil/cambiar_clave` por GET: 404; solo aceptan POST.
  - El usuario temporal de QA (`999999`) fue eliminado al terminar.


### ADR-024: Visor de Errores del Log de CodeIgniter

- **Fecha**: 2026-10-28.

---

### Menú Planes – Verificación (2026-11-01)

- **Estado**: Implementado. `men_id` 138 registrado en `cab_menu` con
  `men_url='Plan/index'`, `men_urlamigable='planes'`, y permisos/acciones en
  `cab_permisologia`/`cab_permiaccion` para el rol con cobertura total.
- **Resolución**: `MY_Controller` resuelve el menú por URL (`Plan/index` o
  `planes`), sin ID en el controlador.
- **Decisión**: Crear el módulo de solo lectura `ReportesErrores` para consultar
  `application/logs` desde la web. El controlador extiende `MY_Controller`, el
  menú se resuelve dinámicamente desde `cab_menu` y todos los endpoints llaman
  `autorizar_accion('consultar', 'Mensaje')`. Solo el rol con cobertura total
  recibe el permiso inicial por la sensibilidad de los logs.
- **Configuración**: claves `logs_reporte_activo` (`bool`, `1/0`),
  `logs_reporte_dias` (`number`, 1..90) y `logs_reporte_max_entradas`
  (`number`, 50..5000) en `cab_configuracion_general`, editables desde
  Configuración General. Los valores se normalizan también en backend.
- **Límites de lectura**: 2 MB de cola por archivo, 8 MB de mensajes por
  petición, 8 KB por entrada y 64 KB por detalle. La ventana se recorta a
  `[hoy-(dias-1), hoy]`; los offsets del detalle son absolutos
  (`YYYY-MM-DD|offset`) y el offset `0` es válido.
- **Seguridad**: no se hace `include/require` del log, no se llama
  `log_message()` desde el parser, se valida `realpath` dentro del directorio de
  logs, se normalizan rutas físicas, se enmascaran credenciales, se sanea UTF-8
  y se escapan todos los campos de salida. Las respuestas llevan `no-store`,
  `no-cache` y `nosniff`. CSRF es global y `tabla`/`detalle` exigen POST + AJAX.
- **Migración**: `database/2026-10-28_create_reporte_errores.sql` (idempotente),
  aplicada el 2026-10-28 en la BD de desarrollo configurada en `.env`. Crea las tres claves, el menú
  `men_id=137` (`reporte-errores`) y el permiso `1000_137` de solo consulta.
- **Validación**: `php -l` sin errores; migración ejecutada dos veces sin
  duplicados; HTTP autenticado 200 en página, DataTables y detalle; 403 para rol
  `1001`, visor desactivado y POST sin CSRF; 404 para detalle inexistente; 405
  para GET en endpoints POST; 422 para fechas invertidas, IDs, niveles,
  paginación y arreglos inválidos. Se corrigió el detalle de la primera entrada
  (`offset=0`) y se sustituyeron iconos inexistentes (`ki-arrow-path`,
  `ki-information-5`) por `ki-arrows-circle` y `ki-information-4`.
- **Riesgos residuales**: la lectura no se registra en `cab_bitacora` (solo se
  audita la configuración); los logs pueden contener secretos que el enmascarado
  no detecte; una ventana de 90 días con archivos grandes puede ser lenta. Las
  pruebas visuales de tema claro/oscuro, responsive y teclado quedaron
  `NOT_RUN`; deben hacerse en navegador antes de una entrega formal.

### ADR-025: Arquitectura y Regla Estricta de Claves RBAC (`cab_menu`, `cab_permisologia`, `cab_permiaccion`)
- **Problema Detectado**: En módulos nuevos (por ejemplo, Planes), modelos anteriores no supieron cómo generar las claves foráneas en las tablas de permisos, intentando asignar números secuenciales arbitrarios o el ID del menú (`men_id`) en `cab_permiaccion.acc_id`. Esto causaba una ruptura total de la integridad referencial y bloqueos "403 Sin Permisos", ya que el sistema consulta permisos mediante un `INNER JOIN` estricto entre `cab_permiaccion.acc_id` y `cab_permisologia.mis_id`.
- **Decisión y Regla de Oro Contractual**:
  1. **`cab_menu`** (`men_id` INT PK): Define el ítem de navegación.
     - `men_url`: `'<Controlador>/index'` o slug del módulo.
     - `men_urlamigable`: slug amigable sin barras (ej. `'planes'`).
     - `men_tipo`: `0` para raíz (primer nivel) o `1` para submenú.
     - `sub_men_id`: `0` para raíz o el `men_id` padre para submenú.
  2. **`cab_permisologia`** (`mis_id` VARCHAR PK): Asigna el menú a un rol.
     - **Regla de Formación de `mis_id`**: Clave compuesta alfanumérica formada obligatoriamente por:
       `mis_id = CONCAT(mis_permiso, '_', mis_menu)`  (Ejemplo: `'1000_138'`).
     - `mis_permiso`: ID del rol en `cab_grupopermi` (ej. `1000`).
     - `mis_menu`: ID del menú en `cab_menu` (ej. `138`).
     - `mis_tipomenu`: Copiado de `men_tipo` (`0` o `1`).
     - `mis_tipoper`: Tipo de rol obtenido de `cab_grupopermi.per_tipo`.
     - `mis_activo`: `'1'`.
  3. **`cab_permiaccion`** (`acc_id` VARCHAR PK & FK): Asigna las banderas de acciones CRUD (`acc_consultar`, `acc_incluir`, `acc_modificar`, `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`).
     - **Regla Estricta de `acc_id`**: **DEBE SER EXACTAMENTE IGUAL AL `mis_id` DE `cab_permisologia`**.
       `acc_id = p.mis_id = CONCAT(rol_id, '_', men_id)` (Ejemplo: `'1000_138'`).
     - **`acc_id` NUNCA es un entero autoincremental ni el ID del menú**.
     - `acc_permiso`: ID del rol asignado (ej. `1000`).
  4. **Regla de Exclusividad en Migraciones (Solo Rol 1000)**:
     - Al registrar un nuevo menú o módulo mediante scripts de migración, los permisos deben asignarse **ÚNICA Y EXCLUSIVAMENTE al rol Admin Master (`1000`)**.
     - **PROHIBIDO asignar permisos a otros roles/grupos (1001, 1002, 1003, 1004, etc.) en las migraciones SQL**.
     - Los demás grupos permanecen sin acceso por defecto (principio de menor privilegio). Si un rol secundario requiere acceso, el administrador debe habilitarlo explícitamente desde la interfaz administrativa (`/permisos/asignar`).
- **Fundamento en el Código Núcleo**:
  - `Menu_model::verificar_permiso()`:
    ```sql
    FROM cab_permiaccion a
    INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id
    INNER JOIN cab_grupopermi g ON g.per_id = p.mis_permiso
    WHERE p.mis_menu = ? AND g.per_id = ?
    ```
  - `Permisos_model::guardar_permisos()`:
    ```php
    $mis_id = $rol_id . '_' . $men_id;
    $this->db->insert('cab_permisologia', ['mis_id' => $mis_id, ...]);
    $this->db->insert('cab_permiaccion',  ['acc_id' => $mis_id, 'acc_permiso' => $rol_id, ...]);
    ```
  - `Permisos_model::get_permisos_rol()`:
    ```php
    $this->db->join('cab_permiaccion a', 'a.acc_id = p.mis_id', 'left');
    ```
- **Plantilla Canónica SQL Idempotente (Exclusiva para Rol 1000)**:
  Toda migración que agregue un módulo debe otorgar acceso única y exclusivamente al rol `1000` siguiendo rigurosamente esta estructura:
  ```sql
  -- 1. Insertar en cab_menu
  INSERT INTO cab_menu (men_id, sub_men_id, men_nombre, men_url, men_urlamigable, men_orden, men_tipo, men_img, men_imgmenu, men_inicio)
  SELECT <NUEVO_ID>, 0, '<Nombre>', '<Controlador>/index', '<slug>', <orden>, 0, '<icono>', NULL, 0
  WHERE NOT EXISTS (SELECT 1 FROM cab_menu WHERE men_id = <NUEVO_ID> OR men_urlamigable = '<slug>');

  -- 2. Asignar rol 1000 en cab_permisologia (mis_id = CONCAT('1000_', men_id))
  INSERT INTO cab_permisologia (mis_id, mis_permiso, mis_menu, mis_tipomenu, mis_tipoper, mis_activo)
  SELECT 
      CONCAT('1000_', m.men_id),
      1000,
      m.men_id,
      m.men_tipo,
      COALESCE((SELECT per_tipo FROM cab_grupopermi WHERE per_id = 1000 LIMIT 1), 1),
      '1'
  FROM cab_menu m
  WHERE m.men_id = <NUEVO_ID>
    AND NOT EXISTS (SELECT 1 FROM cab_permisologia p WHERE p.mis_permiso = 1000 AND p.mis_menu = m.men_id);

  -- 3. Asignar acciones CRUD en cab_permiaccion (acc_id = p.mis_id)
  INSERT INTO cab_permiaccion (acc_id, acc_permiso, acc_consultar, acc_incluir, acc_modificar, acc_eliminar, acc_imprimir, acc_extra1, acc_extra2)
  SELECT 
      p.mis_id,
      1000,
      1, 1, 1, 1, 1, 1, 1
  FROM cab_permisologia p
  WHERE p.mis_id = CONCAT('1000_', <NUEVO_ID>)
    AND NOT EXISTS (SELECT 1 FROM cab_permiaccion a WHERE a.acc_id = p.mis_id);
  ```

### ADR-026: Autorización Dinámica por URL y Snapshot de Menús en Sesión

- **Fecha**: 2026-11-01.
- **Problema**: Centralizar los IDs de menú y rol en constantes seguía
  obligando a declarar y mantener IDs en el código. Además, la sesión ya
  conoce el rol del usuario y la base de datos ya contiene la relación
  rol-menú-acciones, por lo que comparar IDs literales era redundante y
  frágil ante cambios de `men_id`/`per_id`.
- **Decisión**:
  1. Al iniciar sesión, `Auth` construye y serializa en sesión
     (`menu_estructura`) la estructura plana de menús autorizados para el
     rol, con sus flags CRUD (`acc_consultar`, `acc_incluir`,
     `acc_modificar`, `acc_eliminar`, `acc_imprimir`, `acc_extra1`,
     `acc_extra2`).
  2. `MY_Controller` resuelve el menú del módulo actual comparando la URL
     de la petición contra `men_url` (Controlador/acción) y
     `men_urlamigable` (URL amigable). No se declaran `men_id` en
     controladores ni vistas.
  3. `tiene_permiso($accion)` y `autorizar_accion($accion, $mensaje)`
     validan la acción contra los flags del menú resuelto. Las firmas
     heredadas con `$men_id` se conservan como adaptador para módulos que
     resuelven su menú dinámicamente (`GoogleAnalytics`,
     `ReportesErrores`).
  4. El rol se toma siempre de la sesión (`usu_tipoper`). Un rol con
     cobertura de todos los menús activos se considera de nivel maestro
     (`Menu_model::rol_es_master()`); no se codifica ningún `per_id`.
     La jerarquía operativa se compara por cobertura de menús
     (`Menu_model::cobertura_rol()`), no por IDs.
  5. Los módulos sensibles para el registro público se resuelven por
     `men_urlamigable` (`Menu_model::ids_menus_por_slugs()`), no por
     `men_id`.
  6. `Permisos_model::bump_permisos_version()` actualiza
     `cab_configuracion_general.permisos_version` al guardar roles o
     permisos. `MY_Controller` compara esa versión con la de la sesión y
     reconstruye el snapshot cuando cambia, sin exigir un nuevo login.
- **Reglas**:
  - Prohibido escribir IDs numéricos de menú o rol en controladores,
    modelos, vistas, hooks o bibliotecas.
  - Para autorizar una acción se usa el módulo actual:
    `$this->autorizar_accion('modificar', 'Mensaje')`.
  - Para controles cruzados se usa `tiene_permiso_url($url, $accion)`.
  - El snapshot de sesión es la fuente de verdad durante la petición; la
    base de datos solo se consulta al iniciar sesión o al cambiar la
    versión de permisos.
  - Las migraciones `database/*.sql` conservan IDs porque el SQL no consume
    constantes PHP; son la fuente que alimenta `cab_menu`,
    `cab_permisologia` y `cab_permiaccion`.
- **Archivos principales**:
  `application/core/MY_Controller.php`,
  `application/controllers/Auth.php`,
  `application/models/Menu_model.php`,
  `application/models/Permisos_model.php`,
  `application/models/Usuarios_model.php`,
  `application/models/Reportes_sesiones_activas_model.php`,
  `application/config/constants.php` (sin IDs),
  `application/views/partials/_header.php`.
- **Validación**: `php -l` sin errores en los 124 archivos PHP de
  `application/`; resolución de menús y construcción de árbol verificadas
  con un script aislado; `GET /login` responde 200. Las pruebas
  funcionales autenticadas de cada módulo quedaron `NOT_RUN` por no
  disponer de credenciales en el entorno de revisión.

### ADR-027: Módulo Comprar Créditos, pasarelas y liquidación transaccional (2026-11-03)
- **Decisión**: Portar el módulo completo de Comprar Créditos desde VerifyCode2.0 a proyecto_base:
  1. Controladores `BuyCredits`, `ConfiguracionPasarelas` y `Webhook`; modelos `Buy_credits_model` y `Configuracion_pasarelas_model`; librerías `Stripe_client` y `Btcpay_client`; vistas `buy_credits` y `configuracion_pasarelas`.
  2. Tablas nuevas: `cab_movplanes`, `cab_movsms`, `cab_configuracion_pasarelas`, `cab_mov_btcpay`, `cab_mov_btcpay_webhooks`, `cab_mov_stripe` y `cab_mov_stripe_webhooks`.
  3. `cab_usuarios` se convierte a InnoDB y las referencias de usuario de las tablas nuevas usan `utf8mb3_general_ci` para alinear JOINs.
  4. Menús `Comprar Créditos` (`comprar-creditos`) y `Pasarelas de Pago` (`pasarelas-de-pago`), permisos exclusivos del rol `1000`.
  5. Webhooks públicos `/webhook/btcpay`, `/webhook/stripe` y alias `/BuyCredits/btcpay_webhook`, protegidos por HMAC y excluidos de CSRF.
- **Semántica de créditos**: `cab_movsms` es detalle de la orden creada en `procesar_compra()`, no un ledger. El saldo real se acredita únicamente en `liquidar_pago_stripe()` o `liquidar_pago_aprobado()` tras la confirmación de la pasarela.
- **Seguridad**: no se sembraron credenciales; se mantiene el guard de credenciales filtradas. Los secretos deben configurarse desde `/payment-gateways`. El CA bundle TLS se documenta en `.env.example` mediante `CURL_CA_BUNDLE`.
- **Validación**: QA y Seguridad emitieron `ACCEPTED_WITH_RISKS`; las pruebas reales de Stripe/BTCPay, webhooks firmados y navegador quedaron `NOT_RUN` por falta de credenciales/sesión.

### ADR-028: `.env` autoritativo frente a variables persistentes de Apache/PHP (2026-11-05)
- **Problema**: en recargas intermitentes, `load_env_file()` omitía claves que ya existían en `$_ENV`, `$_SERVER` o `getenv()`. Un worker Apache/PHP podía conservar `DB_DATABASE=PROYECTO_NUEVO` de otra vhost/petición y consultar la base equivocada.
- **Decisión**: `index.php::load_env_file()` ahora **sobrescribe siempre** `putenv`, `$_ENV` y `$_SERVER` con los valores del `.env` del proyecto. El `.env` es la fuente autoritativa para `DB_DATABASE`, `APP_BASE_URL`, `APP_ENCRYPTION_KEY`, sesiones y demás configuración local.
- **Validación**: ejecutando con `DB_DATABASE=PROYECTO_NUEVO` y `APP_BASE_URL=http://localhost/otro/` en el proceso, el bootstrap debe resolver `proyecto_base` y `http://localhost/proyecto_base/`. No se modifica `.env`.
---

## 3. Inventario de Módulos Existentes

| Módulo / Controlador | Modelo Asociado | Vista Principal | Responsabilidad |
| :--- | :--- | :--- | :--- |
| **Usuarios** (`Usuarios.php`) | `Usuarios_model.php` | `pages/usuarios/index.php` | **Patrón Canónico**: CRUD completo, DataTables server-side, modal reactivo, borrado lógico y protección del rol con cobertura total. |
| **Perfil** (`Perfil.php`) | `Perfil_model.php` | `pages/perfil/index.php` | Gestión del perfil del usuario en sesión, cambio de clave, avatar con `KTImageInput`, tabla de sesiones y logs de seguridad. |
| **Menús** (`Menus.php`) | `Menus_model.php` | `pages/menus/index.php` | Gestión del árbol de navegación lateral y registro de rutas amigables. |
| **Permisos** (`Permisos.php`) | `Permisos_model.php` | `pages/permisos/index.php`, `asignar.php` | Administración de roles (`cab_grupopermi`) y matriz de permisos sobre el menú con buscador y switches estilo Demo 10. |
| **ConfiguracionTema** (`ConfiguracionTema.php`) | `Configuracion_tema_model.php` | `pages/configuracion_tema/index.php` | Personalización visual: colores, tipografía, logo claro/oscuro, favicon y registro público. |
| **ConfiguracionGeneral** (`ConfiguracionGeneral.php`) | `Configuracion_general_model.php` | `pages/configuracion_general/index.php` | Identidad corporativa, monedas principal y secundaria, tasa de cambio, banner global y modo mantenimiento. |
| **ConfiguracionCorreos** (`ConfiguracionCorreos.php`) | `Configuracion_correos_model.php` | `pages/configuracion_correos/index.php` | Parámetros del servidor SMTP para el envío de notificaciones por email. |
| **PlantillasCorreo** (`PlantillasCorreo.php`) | `Plantillas_correo_model.php` | `pages/plantillas_correo/index.php` | Gestión de plantillas de correo transaccionales con editor, variables dinámicas, preview responsivo y envío de prueba. **Traducción al inglés completada**: UI, seeds, DB rows (`cab_plantillas_correo`), mensajes del controlador, etiquetas de vista y JavaScript traducidos; todas las plantillas y descriptores ahora están en inglés. |
| **Planes** (`Plan.php`) | `Plan_model.php` | `pages/planes/index.php` | Gestión de planes de compra de créditos/suscripción. Incluye configuración de créditos (`pla_numero`), precio para pasarelas (`pla_precio`), tipo de moneda (`pla_moneda`), pasarelas Stripe (`pla_paypal`) y BTCPay (`pla_crypto`), y borrado lógico (`pla_eliminado`). |
| **BuyCredits / Comprar Planes** (`BuyCredits.php`) | `Buy_credits_model.php`, `Plan_compra_model.php` | `pages/buy_credits/index.php` | M08: showcase de planes/lotes de rifa, compra por cantidad, creación de órdenes, pago con Stripe/BTCPay, creación idempotente del lote al liquidar, cancelación e historial. URL canónica `comprar-creditos`, nombre visible `Comprar Planes`. |
| **ConfiguracionPasarelas** (`ConfiguracionPasarelas.php`) | `Configuracion_pasarelas_model.php` | `pages/configuracion_pasarelas/index.php` | Configuración segura de Stripe/BTCPay, prueba de conexión y URLs de webhook. |
| **Webhook** (`Webhook.php`) | `Configuracion_pasarelas_model.php` | N/A (endpoint público) | Recepción HMAC de webhooks BTCPay/Stripe, binding de orden y liquidación idempotente. |
| **CorreosPromocionales** (`CorreosPromocionales.php`) | `Correos_promocionales_model.php` | `pages/correos_promocionales/index.php` | Envío masivo y plantillas de campañas promocionales. |
| **Notificaciones** (`Notificaciones.php`) | `Notificaciones_model.php` | `pages/notificaciones/index.php` | Centro de alertas y notificaciones internas del sistema. |
| **ReportesBitacora** (`ReportesBitacora.php`) | `Reportes_bitacora_model.php` | `pages/reportes_bitacora/index.php` | Auditoría de acciones administrativas realizadas por los usuarios. |
| **ReportesLogins** (`ReportesLogins.php`) | `Reportes_logins_model.php` | `pages/reportes_logins/index.php` | Historial de conexiones IP, navegador, país y ciudad. |
| **ReportesSesionesActivas** (`ReportesSesionesActivas.php`) | `Reportes_sesiones_activas_model.php` | `pages/reportes_sesiones_activas/index.php` | Monitor en vivo de sesiones en archivos CI con expulsión y baneo remoto. |
| **ReportesErrores** (`ReportesErrores.php`) | `Reportes_errores_model.php` | `pages/reportes_errores/index.php` | Visor de solo lectura de `application/logs`, filtros, DataTables y detalle; permiso inicial rol `1000`. |
| **Organizadores** (`Organizadores.php`) | `Organizador_model.php` | `pages/organizadores/index.php` | CRUD admin de tenants M01: listado, edición, estado, cambio de subdominio y borrado lógico. Los permisos se registran solo para el rol `1000`. |
| **MiVitrina** (`MiVitrina.php`) | `Organizador_model.php`, `Plan_asignacion_model.php` | `pages/mi_vitrina/index.php` | Autoservicio M01 para usuarios autenticados: alta de vitrina/subdominio, edición de datos y cambio de slug. Incluye la tarjeta **Mi Plan** de M02. URL libre `mi-vitrina` (alias retrocompatible `mi-tienda`). |
| **PlanesRifa** (`PlanesRifa.php`) | `Plan_rifa_model.php`, `Plan_asignacion_model.php`, `Numeracion_model.php` | `pages/planes_rifa/index.php` | M02/M02.1: catálogo de numeraciones, planes con opciones/precio por rifa, lotes por cantidad, vencimiento y consumos. Menú `planes-rifa`, permisos solo rol `1000`. |
| **MisRifas** (`MisRifas.php`) | `Rifa_model.php`, `Rifa_imagen_model.php`, `Rifa_ticket_model.php` | `pages/mis_rifas/index.php` | M03/M03.1/M03.2/M03.3: autoservicio del organizador para crear/editar rifas seleccionando lote/numeración, descripción con CKEditor sanitizado, galería de hasta 3 imágenes con portada, inventario de números con bloqueos/liberaciones/venta presencial, modo manual o automático aleatorio, visibilidad configurable, publicar/pausar/reactivar/solicitar cancelación/finalizar. URL libre `mis-rifas`. |
| **Rifas** (`Rifas.php`) | `Rifa_model.php`, `Rifa_imagen_model.php`, `Rifa_ticket_model.php` | `pages/rifas/index.php` | M03/M03.2/M03.3: vista administrativa de solo lectura de todas las rifas, con descripción HTML sanitizada, galería y resumen de ocupación de números. Menú `rifas`, permisos solo rol `1000`. |
| **Monedas** (`Monedas.php`) | `Moneda_model.php` | `pages/monedas/index.php` | M04: catálogo de monedas con base USD y tasas históricas contra USD (`1 USD = X`). CRUD admin, protección de USD, bloqueo de eliminación/desactivación de moneda en uso, historial de tasas y estructura de fuente API futura. Menú `monedas` (145), permisos solo rol `1000`. |
| **MetodosPago** (`MetodosPago.php`) | `Metodo_pago_model.php` | `pages/metodos_pago/index.php` | M05: CRUD admin de métodos de pago globales y sus campos dinámicos (text/email/tel/number/url/textarea/select). Protege métodos en uso y no permite desactivar si hay organizadores configurados o rifas activas. Menú `metodos-pago` (146), permisos solo rol `1000`. |
| **MisMetodosPago** (`MisMetodosPago.php`) | `Org_metodo_model.php` | `pages/mis_metodos_pago/index.php` | M05: autoservicio del organizador. Configura métodos globales con sus valores, crea métodos propios con campos personalizados, activa/desactiva y completa datos. URL libre `mis-metodos-pago`; identidad siempre desde sesión. |
| **Publico** (`Publico.php`) | `Orden_model.php`, `Rifa_model.php`, `Rifa_ticket_model.php`, `Org_metodo_model.php`, `Tenant.php` (librería) | `publico/index.php`, `publico/rifa.php`, `publico/orden.php` | M07: sitio público del tenant por host o `/sitio/<slug>`. Home de rifas activas, ficha/checkout, disponibilidad pública, creación de orden y página de orden. No exige sesión; el tenant se resuelve por host/slug. |
| **MisOrdenes** (`MisOrdenes.php`) | `Orden_model.php`, `Organizador_model.php` | `pages/mis_ordenes/index.php` | M07: órdenes del organizador. Lista, detalle, aprobar y rechazar pagos. URL libre `mis-ordenes`; organización desde sesión. |
| **Media** (`Media.php`) | `Rifa_imagen_model.php` | N/A (Stream binario) | Controlador virtual para servir de forma segura imágenes y recursos internos, incluyendo portada y galería de rifas con autorización por estado/rol/organización. |

---

## 4. Estructura de la Base de Datos

### Tablas Principales del Núcleo
1. **`cab_usuarios`**: Usuarios del sistema (`usu_id`, `usu_usuario`, `usu_clave`, `usu_nombre`, `usu_apellido`, `usu_correo`, `usu_tipoper`, `usu_foto`, `usu_status`, `usu_2fa`, `usu_2fa_intentos`, `usu_2fa_bloqueado_hasta`, `usu_fecha`, `usu_hora`, `usu_created_at`, `usu_updated_at`). No asumir `usu_email`, `usu_eliminado` o `usu_fechareg` sin una migración que los agregue.
2. **`cab_grupopermi`**: Roles y perfiles (`per_id`, `per_nombre`, `per_tipo`, `per_activo`). Rol `1000` = Admin Master.
3. **`cab_menu`**: Módulos del sistema (`men_id`, `sub_men_id`, `men_nombre`, `men_url`, `men_urlamigable`, `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_externo`, `men_inicio`).
4. **`cab_permisologia`**: Relación entre rol y menú.
   - `mis_id`: Clave primaria alfanumérica compuesta: **`CONCAT(mis_permiso, '_', mis_menu)`** (ej. `'1000_138'`).
   - `mis_permiso`: ID del rol en `cab_grupopermi`.
   - `mis_menu`: ID del menú en `cab_menu`.
   - `mis_tipomenu`: `0` (raíz) o `1` (submenú).
   - `mis_tipoper`: Tipo de rol (`per_tipo`).
   - `mis_activo`: `'1'` (activo).
5. **`cab_permiaccion`**: Acciones CRUD asignadas a cada permiso.
   - `acc_id`: Clave primaria y foránea que **DEBE COINCIDIR EXACTAMENTE** con `cab_permisologia.mis_id` (ej. `'1000_138'`). **NUNCA usar números secuenciales ni el ID del menú.**
   - `acc_permiso`: ID del rol en `cab_grupopermi` (ej. `1000`).
   - Flags booleanos: `acc_consultar`, `acc_incluir`, `acc_modificar`, `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`.
   - *Razón*: `Menu_model::verificar_permiso()` y `Permisos_model` enlazan mediante `INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id`. Si `acc_id` no coincide con `mis_id`, el acceso se bloquea con 403.
6. **`cab_bitacora`**: Auditoría de acciones (`bit_id`, `bit_usuario`, `bit_accion`, `bit_detalles`, `bit_ip`, `bit_date`).
7. **`cab_bitacora_ip`**: Auditoría de logins (`bit_id`, `bit_usuario`, `bit_ip`, `bit_navegador`, `bit_city`, `bit_country_code`, `bit_date`).
8. **`cab_usuario_verificacion`**: Tokens de verificación y recuperación con clave compuesta (`uver_usuario_id`, `uver_tipo`, `uver_token_hash`, `uver_expira`, `uver_ip_address`, `uver_send_count`, `uver_window_started_at`, `uver_last_sent_at`, `uver_created_at`, `uver_updated_at`).
9. **`cab_configuracion_tema`**: Ajustes de personalización y branding del sistema.
10. **`cab_configuracion_correo`**: Configuración de transporte SMTP.
11. **`cab_configuracion_general`**: Almacén clave-valor de identidad corporativa, moneda primaria/secundaria, tasa de cambio, banner global, switch de mantenimiento y configuración del visor de errores (`logs_reporte_*`).
12. **`cab_plantillas_correo`**: Plantillas HTML transaccionales personalizables (`plc_id`, `plc_codigo`, `plc_nombre`, `plc_asunto`, `plc_cuerpo`, `plc_status`) que gestionan mensajes como `LOGIN_2FA`, `BIENVENIDA`, `RECUPERAR_CLAVE`, etc.
13. **`cab_planes`**: Configuración de ofertas vendibles (`pla_id`, `pla_nombre`, `pla_descripcion`, `pla_numero`, `pla_precio`, `pla_moneda`, `pla_paypal`, `pla_crypto`, `pla_status`, `pla_eliminado`, `pla_tipo` creditos/plan_rifa, `pla_rif_plan_id`, `pla_rif_num_id` 0=ilimitado, `pla_rif_periodo`, `pla_created_at`, `pla_updated_at`). Las ofertas `plan_rifa` se sincronizan desde `rif_planes`, `rif_plan_numeraciones` y `rif_numeraciones` tras mutaciones administrativas. `pla_precio` y `pla_moneda` representan el monto y divisa procesados por pasarelas (Stripe, BTCPay).
14. **`cab_movplanes`**: Órdenes de compra (`mov_id`, usuario, plan, monto, `mov_sms` = cantidad de lotes o créditos, estado, referencias Stripe/BTCPay). Motor InnoDB. Para M08 el tipo real se deriva de `cab_planes.pla_tipo` y de la fila en `rif_plan_compras`; una orden de plan sin esa fila no se liquida como créditos.
15. **`cab_movsms`**: Detalle de créditos por orden; no es un ledger. El saldo se aplica en `cab_usuarios.usu_credit` al liquidar.
16. **`cab_configuracion_pasarelas`**: Configuración de Stripe/BTCPay, sin credenciales sembradas.
17. **`cab_mov_btcpay`** y **`cab_mov_btcpay_webhooks`**: Auditoría forense de invoices y eventos BTCPay.
18. **`cab_mov_stripe`** y **`cab_mov_stripe_webhooks`**: Auditoría y deduplicación de sesiones/eventos Stripe.
19. **`rif_organizadores`**: Tenants M01 (`org_id`, `org_usuario_id` UNIQUE, `org_slug` UNIQUE, `org_nombre_publico`, `org_email`, `org_telefono`, `org_status`, `org_verificado`, `org_eliminado`, timestamps). Un usuario = una vitrina. `org_usuario_id` es referencia lógica a `cab_usuarios.usu_id` sin FK física.
20. **`rif_dominios`**: Hosts de cada tenant (`dom_id`, `dom_org_id` FK CASCADE, `dom_host` UNIQUE, `dom_tipo` subdominio/personalizado, `dom_verificado`, `dom_token`, `dom_ssl_status`, `dom_status`, timestamps). La resolución de tenant consulta esta tabla; nunca confía en el `Host` por sí solo.
21. **`rif_planes`**: Planes de rifa M02 (`plan_id`, `plan_nombre` UNIQUE, `plan_descripcion`, `plan_tipo` rifas/ilimitado, `plan_limite`, `plan_periodo` sin_limite/mensual/anual, `plan_precio`, `plan_moneda`, `plan_status`, `plan_eliminado`, timestamps). Los planes por rifas no caducan; los ilimitados son mensuales/anuales.
22. **`rif_plan_asignaciones`**: Lotes/asignaciones de plan a organizador (`asig_id`, `asig_org_id` FK RESTRICT, `asig_plan_id` FK RESTRICT, `asig_num_id` FK SET NULL, `asig_cantidad` NULL = ilimitado, `asig_rifas_usadas`, `asig_precio_unitario`, `asig_total`, `asig_origen`, `asig_inicio`, `asig_fin` NULL = sin caducidad, `asig_estado` activa/vencida/reemplazada/cancelada, `asig_notas`, timestamps). Un organizador puede tener varios lotes activos.
23. **`rif_plan_consumos`**: Consumos y devoluciones de cupo (`cons_id`, `cons_asig_id` FK CASCADE, `cons_org_id` FK CASCADE, `cons_rifa_id` referencia lógica a `rif_rifas`, `cons_estado` consumido/devuelto, `cons_motivo`, timestamps, UNIQUE `(cons_asig_id, cons_rifa_id)`). La idempotencia de negocio es por org+rifa.
24. **`rif_rifas`**: Rifas M03 (`rifa_id`, `rifa_org_id` FK CASCADE, `rifa_asig_id` FK SET NULL, `rifa_num_id` FK SET NULL, `rifa_slug` UNIQUE por org, título, descripción, condiciones, tipo paga/gratuita, precio/moneda, numeración 2/3/4/custom, total, max por compra, TTL/auto-reserva, `rifa_modo_asignacion` manual/automatica, `rifa_mostrar_reservados`, fecha de sorteo, imagen, estado, estado previo, fechas de publicación/finalización/cancelación, motivos, timestamps, borrado lógico). Estados: borrador, pendiente_aprobacion, activa, pausada, cancelacion_solicitada, finalizada, cancelada, rechazada.
25. **`rif_numeraciones`**: Catálogo global de numeraciones (`num_id`, `num_label`, `num_cantidad`, `num_desde`, `num_hasta`, `num_padding`, `num_status`, `num_orden`). Semillas 100/1.000/10.000.
26. **`rif_plan_numeraciones`**: Opciones de numeración por plan (`pnum_id`, `pnum_plan_id` FK CASCADE, `pnum_num_id` FK RESTRICT, `pnum_precio` por rifa, `pnum_status`, `pnum_orden`). Unique plan+numeración.
27. **`rif_rifa_imagenes`**: Galería de imágenes por rifa (`img_id`, `img_rifa_id` FK CASCADE, `img_archivo`, `img_principal`, `img_orden`, timestamps). Máximo 3 por rifa; una sola principal; la portada se sincroniza con `rif_rifas.rifa_imagen`.
28. **`rif_tickets`**: Inventario sparse de números ocupados de una rifa (`tick_id`, `tick_rifa_id` FK CASCADE, `tick_numero` con padding, `tick_numero_int`, `tick_estado` reservado/pagado/bloqueado, `tick_orden_id` y `tick_comprador_id` lógicos para M07, `tick_comprador_nombre`, `tick_precio`, `tick_moneda`, `tick_motivo`, fechas, timestamps). UNIQUE `(tick_rifa_id, tick_numero)` y `(tick_rifa_id, tick_numero_int)`; los números sin fila están disponibles.
29. **`rif_ticket_historial`**: Trazabilidad de reservas/ventas/bloqueos/liberaciones (`hist_id`, `hist_rifa_id` FK CASCADE, `hist_numero`, `hist_orden_id`, `hist_accion`, `hist_actor`, `hist_motivo`, `hist_at`).
30. **`rif_monedas`**: Catálogo M04 (`mon_id`, `mon_codigo` VARCHAR(10) UNIQUE, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`, `mon_api_codigo`, `mon_api_ultima_sync`, `mon_eliminado`, timestamps). Semillas USD base + EUR/VES/COP/MXN/USDT/GBP. `mon_codigo` es VARCHAR(10) para admitir USDT.
31. **`rif_tasas_cambio`**: Tasas históricas por moneda y fecha (`tasa_id`, `tasa_mon_id` FK CASCADE, `tasa_valor` DECIMAL(20,10) = unidades por 1 USD, `tasa_fecha`, `tasa_origen` manual/api, `tasa_status`, timestamps). UNIQUE (moneda, fecha); la tasa actual es la última con fecha <= hoy. USD siempre 1.0.
32. **`rif_metodos_pago`**: Plantillas de métodos de pago (`met_id`, `met_scope` global/organizador, `met_org_id` FK CASCADE para propios, `met_nombre`, `met_descripcion`, `met_instrucciones`, `met_icono` KeenIcons, `met_imagen` archivo local del logo, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`, timestamps). Semillas: Transferencia bancaria, Pago móvil, Zelle, Binance Pay, PayPal y Efectivo, con iconos por defecto.
33. **`rif_metodo_campos`**: Campos dinámicos de un método (`campo_id`, `campo_met_id` FK CASCADE, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`, timestamps). UNIQUE (método, clave).
34. **`rif_org_metodos`**: Instancia de un método global para un organizador o método propio (`orgmet_id`, `orgmet_org_id` FK CASCADE, `orgmet_met_id` FK CASCADE, `orgmet_activo`, `orgmet_orden`, `orgmet_notas`, timestamps). UNIQUE (org, método).
35. **`rif_org_metodo_valores`**: Valores que completa el organizador por campo (`val_id`, `val_orgmet_id` FK CASCADE, `val_campo_id` FK CASCADE, `val_valor`, timestamps). UNIQUE (instancia, campo).
36. **`rif_rifa_metodos`**: Métodos seleccionados por una rifa (`rm_id`, `rm_rifa_id` FK CASCADE, `rm_orgmet_id` FK CASCADE, `rm_orden`, `rm_created_at`). UNIQUE (rifa, instancia). Una rifa paga debe conservar al menos un método activo y completo para publicarse/reactivarse.
37. **`rif_ordenes`**: Órdenes del checkout público M07 (`ord_id`, `ord_rifa_id` FK CASCADE, `ord_org_id` FK CASCADE, `ord_codigo` UNIQUE de alta entropía, comprador nombre/email/teléfono/documento, cantidad, precio unitario, total, moneda, `ord_orgmet_id` FK SET NULL, `ord_metodo_snapshot` JSON, `ord_estado` pendiente/en_revision/pagada/rechazada/cancelada/expirada, notas, motivo de rechazo, expiración, fechas de pago/rechazo/cancelación/expiración, IP/UA, timestamps). `rif_tickets.tick_orden_id` enlaza los números reservados/pagados; `idx_ticket_orden` acelera la consulta.
38. **`rif_plan_compras`**: Compra M08 de un plan/lote de rifa (`compra_id`, `compra_mov_id` UNIQUE referencia lógica a `cab_movplanes`, `compra_org_id` FK RESTRICT, `compra_plan_id` FK RESTRICT, `compra_num_id` 0=ilimitado, `compra_cantidad` 0=ilimitado, `compra_periodo`, `compra_precio_unitario`, `compra_total`, `compra_moneda`, `compra_asig_id` FK RESTRICT, `compra_estado` pendiente/pagada/cancelada/expirada/error, timestamps). Se crea junto con la orden y se marca pagada al crear el lote `rif_plan_asignaciones`; es idempotente por `compra_mov_id`.

---
## 5. Guía de Extensión para Nuevos Sistemas

Cuando este proyecto base se utilice para crear un nuevo sistema personal (ej. Sistema de Ventas e Inventario):

1. **Mantener el Núcleo Intacto**: No modificar `MY_Controller`, `Auth.php` ni las tablas base salvo para extender funcionalidad general.
2. **Crear Nuevos Módulos según el Patrón Canónico**:
   - **Paso 0 (Inspección Visual Previa)**: Revisar preferentemente `c:\xampp\htdocs\proyecto_base_metronic\dist\html\demo10\` o cualquiera de los demás demos (`demo1` a `demo9`) para localizar el template HTML o componentes correspondientes a la función del módulo y adoptar su estructura.
    - Ejecutar la migración SQL (`database/YYYY-MM-DD_*.sql`) creando las tablas (`cab_articulos`, `cab_facturas`, etc.) e insertando sus filas en `cab_menu`, `cab_permisologia` y `cab_permiaccion`.
   - Crear el modelo extendiendo `CI_Model` con el contrato DataTables.
   - Crear el controlador extendiendo `MY_Controller` con `module_path()`.
   - **Registro Obligatorio en Bitácora**: En cada método mutador (`crear()`, `actualizar()`, `eliminar()`, cambio de switches de estado, etc.) invocar obligatoriamente `$this->bitacora('<MODULO>_<ACCION>', 'ID: ... | Detalle: ...')`.
   - **Botones Compactos con Icono Obligatorios (`btn-sm`)**: Todo botón de formulario, modal o barra de herramientas que contenga texto (Guardar, Cancelar, etc.) debe usar obligatoriamente `btn-sm` e incorporar su respectivo icono KeenIcons (`ki-check`, `ki-cross`, `ki-arrow-left`, `ki-send`, etc.).
   - Crear la vista en `application/views/pages/<modulo>/index.php` implementando la apariencia seleccionada con la integración AJAX / DataTables de `proyecto_base`.
3. **Documentar**:
   - Registrar los nuevos módulos en este `MEMORY.md`.
    - Asegurar que las reglas de `AGENTS.md` se respeten.

### Estado verificado de la base de datos

Verificado contra la base `proyecto_base` el 2026-10-27, sin leer secretos,
contraseñas ni tokens:

- Motor: MariaDB 11.8.9; base y tablas nuevas usan principalmente `utf8mb4`.
- El esquema contiene tablas heredadas MyISAM/utf8mb3 y tablas nuevas
  InnoDB/utf8mb4; no asumir uniformidad de motor, colación o claves foráneas.
- Las tablas RBAC reales son `cab_grupopermi`, `cab_menu`,
  `cab_permisologia` y `cab_permiaccion`.
- Solo se detectó una clave foránea declarada en el esquema actual:
  `cab_correos_promocionales_destinatarios.cpd_promocion_id` hacia
  `cab_correos_promocionales.cpr_id`.
- `cab_usuarios` usa `usu_id` como `char(100)`, `usu_status` como `char(2)` y
  no contiene `usu_eliminado` ni `usu_fechareg`; conserva columnas heredadas
  como `usu_fecha`, `usu_hora`, `usu_created_at` y `usu_updated_at`.
- `cab_usuario_verificacion` usa clave compuesta (`uver_usuario_id`,
  `uver_tipo`) y `uver_token_hash`; no asumir las columnas genéricas
  `uver_id`, `uver_token` o `uver_expira_en`.
- La matriz de permisos quedó normalizada el 2026-10-28: Admin Master (`1000`)
  tiene CRUD completo en todos sus menús; `1001` conserva consulta en reportes y
  CRUD en correos promocionales; `1002` y `1003` solo tienen dashboard; `1004`
  (Registrado) solo dashboard. Aun así, verificar la acción específica antes de
  mutar datos de roles personalizados.
- Verificación de código del 2026-10-28: `MY_Controller` aplica deny-by-default
  por controlador registrado en `cab_menu` y valida el menú activo del rol;
  `autorizar_accion()` se llama en los endpoints de mutación de los módulos
  administrativos. El rol `1000` es la única excepción global. Las rutas directas
  deben probarse con roles reales antes de dar por aplicada la matriz CRUD.
- La protección CSRF global está activa en `application/config/config.php` con
  `csrf_token`/`csrf_cookie`; `partials/_csrf.php` inyecta el token en fetch,
  XHR y formularios. `Auth.php` conserva además sus tokens propios para login,
  registro y recuperación, y esas rutas están excluidas del CSRF global.
- `Usuarios_model::eliminar()` ejecuta un `DELETE` físico; la regla de borrado
  lógico para módulos nuevos no describe el comportamiento actual de Usuarios.

### ADR-029: Segundo factor adicional con aplicación autenticadora TOTP (2026-11-07)
- **Decisión**: conservar el 2FA por correo (`usu_2fa`) y añadir un método TOTP opcional e independiente. El usuario puede activar ambos; en el inicio de sesión TOTP es el método principal y el correo queda como alternativa cuando también está habilitado.
- **Datos**: `usu_2fa_totp_enabled`, `usu_2fa_totp_secret` (AES-256-GCM), `usu_2fa_totp_confirmed_at` y `usu_2fa_totp_last_step` en `cab_usuarios`; tabla `cab_usuario_2fa_recovery` con hashes bcrypt de códigos de recuperación de un solo uso.
- **Flujo**: `Perfil/totp_iniciar` genera un secreto pendiente en sesión; `Perfil/totp_confirmar` valida el primer código, cifra el secreto y entrega 8 códigos de recuperación una sola vez; `Perfil/totp_desactivar` exige la contraseña actual. `Auth::dos_factores` muestra TOTP o correo, `Auth::verificar_factor` valida TOTP/código de recuperación y aplica el mismo bloqueo de 5 intentos, y `dos-factores/usar-totp` permite volver al método TOTP.
- **QR**: el modal muestra un QR local generado con `assets/plugins/custom/qrcode/qrcode.min.js` (MIT) a partir de la URI `otpauth://`; no se envía el secreto a servicios externos. Se conserva el botón **Abrir en aplicación autenticadora**.
- **Seguridad**: secreto cifrado con la `APP_ENCRYPTION_KEY`, códigos de recuperación hasheados con bcrypt, ventana TOTP ±30s, `usu_2fa_totp_last_step` evita reutilización, auditoría `LOGIN_2FA_TOTP`, `LOGIN_2FA_RECOVERY`, `PERFIL_2FA_TOTP_ACTIVADO/DESACTIVADO`.
- **Validación**: migración aplicada en `proyecto_base`; login TOTP real y setup/recovery codes probados; `php -l` correcto. Datos temporales eliminados.
- **Despliegue**: rotar `APP_ENCRYPTION_KEY` antes de producción; el valor de ejemplo del repositorio es público.

### ADR-030: Operación de producción y variables de entorno (2026-11-07)
- **Documento canónico**: `DEPLOYMENT.md` contiene variables de `.env`, rotación de `APP_ENCRYPTION_KEY`, configuración de PHP/servidor, permisos y checklist de despliegue.
- **Tareas programadas**: `proyecto_base` no tiene workers SMS, archivos `.bat` ni `Sms_worker`; no se deben programar las tareas de VerifyCode2.0 contra esta aplicación. La referencia de Task Scheduler está en `C:\xampp\htdocs\VerifyCode2.0\DEPLOYMENT.md`.
- **Variables críticas de producción**: `CI_ENV=production`, `APP_BASE_URL` con HTTPS, `APP_ENCRYPTION_KEY` aleatoria, `DB_*` reales y de mínimo privilegio, sesiones/cookies seguras, `CURL_CA_BUNDLE`, NTP y permisos de escritura en logs/cache.
- **Pendiente bloqueante**: rotar `APP_ENCRYPTION_KEY` antes de producción y re-cifrar o reinscribir los secretos TOTP existentes; el fallback de `config.php` es público.
- **Validación**: documento creado; no se ejecutaron tareas de Task Scheduler ni pruebas de servidor de producción en este entorno.

### ADR-031: Estándar visual obligatorio de la columna Acciones/Actions en tablas (2026-11-07)
- **Regla obligatoria para todo CRUD nuevo o modificado**: todas las tablas con columna de acciones deben usar el mismo componente visual; no se permite inventar estilos por módulo.
- **Encabezado**: `min-w-120px text-end pe-2`.
- **Contenedor**: `d-flex justify-content-end align-items-center gap-2`.
- **Botón**: `btn btn-sm btn-icon btn-light-{color} shadow-xs`, sin texto visible, sin `me-1`, sin `py-1 px-2`, sin `fs-8`.
- **Icono**: `ki-duotone ki-{accion} fs-4` con sus `pathN`.
- **Tooltip**: `data-bs-toggle="tooltip" data-bs-placement="top" title="..." aria-label="..."`.
- **Celdas DataTables**: `className: 'text-end pe-2'`.
- **Tooltips dinámicos**: el layout global los inicializa desde `application/views/partials/_scripts.php`.
- **Colores**: primary editar/ver/reutilizar; danger eliminar/banear; warning desactivar/cerrar; success activar/asignar/usar.
- **Excepción**: badges de estado protegido/no disponible pueden conservarse.
- **Validación**: revisar todas las tablas del módulo antes de cerrar un CRUD; `php -l`, `node --check` y consistencia visual claro/oscuro.

### ADR-032: Núcleo multi-tenant por subdominio — M01 (2026-11-09)
- **Decisión**: Sortea2 opera como SaaS multi-tenant sobre una sola base de datos. Cada organizador se identifica por `rif_organizadores.org_id`; sus hosts se resuelven contra `rif_dominios.dom_host`. El panel administrativo vive en el host principal (`APP_BASE_URL`); el sitio público en `<slug>.<TENANT_BASE_DOMAIN>`.
- **Resolución**: `application/libraries/Tenant.php` consulta la BD por host (nunca confía en el subdominio) y expone `base_url`, `url()`, `asset()` y `theme()`. En desarrollo se habilitan `/sitio/<slug>` y `?org=<slug>` con `TENANT_ALLOW_PATH_RESOLUTION=true`; en producción debe ser `false`.
- **Aislamiento**: `application/hooks/Tenant_host_guard.php` (`pre_controller`) permite en hosts tenant solo `Publico`, `Media` y `Errors`; cualquier controlador administrativo o de login devuelve 404. Hosts no registrados (incluido `evil.example.com`) también devuelven 404 por deny-by-default.
- **Modelo**: `rif_organizadores` (`org_usuario_id` UNIQUE, `org_slug` UNIQUE, `org_status`, `org_verificado`, `org_eliminado`) y `rif_dominios` (`dom_host` UNIQUE, `dom_tipo`, `dom_verificado`, `dom_status`, FK CASCADE). Un usuario = una vitrina. El alta es autoservicio en `/mi-vitrina` (alias retrocompatible `/mi-tienda`); el admin gestiona en `/organizadores` (menú RBAC, permisos solo rol `1000`).
- **Slugs**: 3-63 caracteres, regex `^[a-z0-9]+(?:-[a-z0-9]+)*$`; lista de reservados centralizada en `Organizador_model::$slugs_reservados`. Se rechazan mayúsculas, guiones consecutivos, guiones en extremos y caracteres no permitidos con HTTP 422.
- **Tema**: `Tenant::theme()` devuelve valores por defecto hasta M04. El sitio público M01 es un placeholder "en construcción", sin formularios ni CSRF.
- **Seguridad**: CSRF global en formularios admin; `MiVitrina` deriva el propietario de la sesión y nunca acepta `org_id` del request; bitácora `ORGANIZADOR_*` en toda mutación; `db_debug` debe estar desactivado en producción.
- **Riesgos/pendientes**: el borrado lógico bloquea la recreación por UNIQUE (política de restauración pendiente); dominios personalizados quedan para una fase posterior; wildcard DNS/TLS es requisito operativo; `TENANT_BASE_DOMAIN` debe existir en el `.env` de producción. Security documentó riesgos residuales de hosts/plantilla `.env`/reactivación de dominio.
- **Validación**: migración aplicada dos veces sin duplicados; BACKEND/FRONTEND con `php -l`; SECURITY `ACCEPTED_WITH_RISKS`; QA `ACCEPTED` (hosts, RBAC, CSRF, DataTables, duplicados, slugs y limpieza). Sin datos temporales en la BD.

### ADR-033: Planes de rifa, cupos y asignaciones — M02 (2026-11-09)
- **Decisión**: cada organizador publica rifas bajo un plan asignado. Planes `rifas` (límite N, sin caducidad) o `ilimitado` (vigencia mensual/anual). El plan se asigna desde `/planes-rifa` y el organizador ve su estado en la tarjeta **Mi Plan** de `/mi-vitrina`.
- **Asignaciones**: una sola activa por organizador. `Plan_asignacion_model::asignar` bloquea organizador y plan con `FOR UPDATE`; si ya existe una activa requiere `reemplazar=1` y la anterior pasa a `reemplazada`. `cancelar` usa transacción y `FOR UPDATE`.
- **Cuota**: `consumir_para_rifa($org_id,$rifa_id)` usa transacción + `FOR UPDATE` + UNIQUE `(asig_id,rifa_id)`. Primero evalúa idempotencia (`ya_consumido`), luego cupo (`sin_cupo`). `devolver_por_rifa` es idempotente, usa `IF(usadas>0, usadas-1, 0)` y permite re-consumir la misma rifa reutilizando la fila `devuelto`.
- **Vencimiento**: `asig_fin` se calcula a +1 mes o +1 año; los planes por rifas quedan sin caducidad. `normalizar_vencidas` se ejecuta en escrituras; en GET se calcula el estado efectivo sin escribir. `get_estado_por_org` devuelve `vencida` cuando corresponde.
- **Integridad**: no se elimina un plan con asignaciones activas ni se cambian tipo/cupo/vigencia con asignaciones activas; `Plan_rifa_model::actualizar/eliminar` bloquean el plan y revalidan. `plan_precio` se valida finito y dentro de `DECIMAL(10,2)`; los campos POST/GET se validan como escalares.
- **Admin**: `/planes-rifa` con tabs Planes/Asignaciones (DataTables, modales, Select2, CSRF, RBAC). Menú `planes-rifa` (`men_id=143`), permisos solo rol `1000`; bitácora `PLAN_RIFA_*`, `PLAN_ASIGNAR`, `PLAN_ASIGNACION_CANCELAR`.
- **Integración M03**: M03 debe verificar que `rifa_id` pertenece a `org_id` antes de `consumir_para_rifa` y registrar `PLAN_CONSUMO`/`PLAN_DEVOLUCION` en `cab_bitacora`. La cancelación/finalización de una rifa debe llamar `devolver_por_rifa`.
- **Riesgos/pendientes**: el deploy debe usar `CI_ENV=production` y `db_debug=false`; la cancelación concurrente puede devolver 500 al perdedor (la invariante de estado se cumple); el admin opera globalmente (revisar al delegar permisos); el historial de re-consumo conserva `cons_created_at` original.
- **Validación**: migración aplicada dos veces; SECURITY `ACCEPTED_WITH_RISKS` tras corregir H1/H2/M1-M5; QA `ACCEPTED` (planes, asignaciones, cuota, re-consumo, underflow, vencimiento, orden DataTables, arrays y limpieza). `tu-suerte` intacto; sin datos temporales.

### ADR-034: Rifas, numeración y ciclo de estados — M03 (2026-11-09)
- **Decisión**: el organizador gestiona sus rifas en `/mis-rifas` (URL libre, `org_id` desde la sesión); el admin supervisa todas en `/rifas` (solo lectura, menú `rifas`, permisos solo rol `1000`).
- **Numeración**: presets `2` (00–99), `3` (000–999) y `4` (0000–9999), más `custom` con desde/hasta/padding. Validación: `0 <= desde < hasta <= 999999`, total <= 1,000,000, padding 1–6. `rifa_total_numeros` se persiste para M07.
- **Estados**: `borrador`, `pendiente_aprobacion`, `activa`, `pausada`, `cancelacion_solicitada`, `finalizada`, `cancelada`, `rechazada`. Transiciones en `Rifa_model` con transacción y `FOR UPDATE`.
- **Cuota**: publicar una rifa paga consume un cupo de M02; una gratuita pasa a `pendiente_aprobacion` sin consumir; finalizar y `cancelar_aprobada` devuelven cupo; reactivar exige plan vigente (no exige cupo libre, porque esta rifa ya lo consumió).
- **Imagen**: opcional, JPG/PNG/WEBP <= 4MB, MIME real, nombre aleatorio, `upload/rifas/{org_id}/`; se sirve por `Media::rifa($id)` con acceso público solo para rifas activas de organizaciones activas, propietario/master autenticados y `nosniff`.
- **Integridad**: la edición en activa/pausada/pendiente/cancelacion_solicitada bloquea tipo, precio, moneda, numeración, max, TTL y auto; solo permite título, descripción, condiciones, imagen y fecha. Borrado lógico solo en borrador/rechazada/cancelada/finalizada. `bit_detalles` se trunca a 255 y las escrituras desactivan `db_debug` temporalmente para devolver 422 ante slugs duplicados sin filtrar SQL.
- **M09**: el modelo ya expone `aprobar_gratuita`, `rechazar_gratuita`, `cancelar_aprobada` y `rechazar_cancelacion`, con validación de estado y `rifa_tipo='gratuita'`; falta la UI/endpoints administrativos y su autorización.
- **Riesgos/pendientes**: deploy con `CI_ENV=production` y `db_debug=false`; `/rifas` depende del RBAC (no impone rol 1000 por código); `DB_CHARSET=utf8` vs `utf8mb4`; migración sin lock concurrente; sin rate limiting; propiedad de `rifa_id` y auditoría de consumos quedan en M03/M07.
- **Validación**: migración aplicada dos veces; SECURITY `ACCEPTED_WITH_RISKS` tras corregir H1/M1/M2/M3 y LOW; QA `ACCEPTED` (CRUD, numeración, estados, cuota, concurrencia, IDOR, uploads, Media, admin, Mi Vitrina y limpieza). `tu-suerte` intacto; sin datos temporales.

### ADR-035: Numeraciones, opciones por plan y lotes por cantidad — M02.1 (2026-11-10)
- **Decisión**: el admin define un catálogo global de numeraciones (`rif_numeraciones`) y cada plan básico ofrece una o varias opciones con precio por rifa (`rif_plan_numeraciones`). El organizador compra/recibe un lote: plan + numeración + cantidad (ej. 5 rifas de 100). Puede tener varios lotes activos; ya no se reemplaza el plan anterior.
- **Planes**: `plan_tipo='rifas'` exige al menos una opción activa y no usa `plan_limite` como cupo único; `plan_tipo='ilimitado'` (mensual/anual) no tiene cantidad ni numeración fija. El precio real de un lote limitado se toma de `pnum_precio`; el de un ilimitado, de `plan_precio`.
- **Lotes**: `rif_plan_asignaciones` agrega `asig_num_id`, `asig_cantidad`, `asig_precio_unitario` y `asig_total`. `asig_cantidad=NULL` representa ilimitado. `get_disponibles_por_org` devuelve todos los lotes vigentes con restantes.
- **Consumo**: `consumir_para_rifa` acepta lote específico; limita por restantes en lotes finitos y no limita en ilimitados. La devolución se aplica al lote del consumo.
- **UI**: tercer tab **Numeraciones** en `/planes-rifa`, opciones de numeración en el modal de plan y asignación de lote con cantidad/precio/total. Mi Vitrina y Mis Rifas muestran los lotes disponibles.
- **Backfill**: `database/2026-11-10_backfill_legacy_lots.sql` crea la opción faltante de planes legacy y vincula lotes/rifas existentes. El plan real `Sortea2 S1` (121) quedó con opción 100/$2.00, lote 89 activo y rifa 103 vinculada a 87/num 1.
- **Validación**: migración aplicada dos veces; SECURITY `ACCEPTED_WITH_RISKS`; QA `ACCEPTED` (numeraciones, planes, lotes, precios, consumos, concurrencia y limpieza). `tu-suerte` intacto.

### ADR-036: La rifa usa lote y numeración del plan — M03.1 (2026-11-10)
- **Decisión**: una rifa paga selecciona un lote (`rifa_asig_id`) y la numeración se deriva del lote (`rifa_num_id`). En un lote limitado la numeración es fija; en uno ilimitado el organizador elige una numeración activa. La numeración libre del formulario queda oculta y el backend la ignora.
- **Persistencia**: `rif_rifas.rifa_asig_id` y `rifa_num_id` se guardan al crear/editar. Una rifa gratuita queda sin lote/numeración (`NULL`) hasta que M09 la apruebe y el admin defina la numeración.
- **Publicación**: consume el lote exacto; finalizar/cancelar devuelve ese lote. Reactivar exige que el lote siga vigente. La idempotencia de consumo es por organización+rifa, no por lote.
- **Edición**: en borrador/rechazada se puede cambiar lote/numeración; en estados publicados se bloquea. El chequeo estructural compara el POST crudo antes de normalizar las gratuitas pendientes.
- **Regresión**: se corrigió la edición de gratuitas pendientes para permitir solo título/descripción/condiciones/imagen/fecha y rechazar precio/numeración/tipo/lote con 422.
- **Validación**: SECURITY `ACCEPTED_WITH_RISKS`; QA `ACCEPTED` (lote/numeración, publicación, gratuita, bloqueos, IDOR, arrays, UI y limpieza). `tu-suerte` intacto.

### ADR-037: Editor HTML y galería de imágenes por rifa — M03.2 (2026-11-10)
- **Editor**: la descripción de la rifa usa CKEditor 5 Classic con fallback a textarea. El HTML se sanitiza en el servidor con `Html_sanitizer` (allowlist de etiquetas/atributos, URLs http/https/mailto, imágenes externas http/https, sin script/iframe/style/on*). El admin renderiza la descripción sanitizada. **Actualización 2026-11-11**: en Mis Rifas se retiró CKEditor por un fallo del bundle con Chrome actual y la descripción quedó como textarea simple (ver ADR-047); el saneamiento del backend se mantiene.
- **Galería**: `rif_rifa_imagenes` guarda hasta 3 imágenes por rifa; una principal. La portada se sincroniza con `rif_rifas.rifa_imagen`. Se puede subir, marcar portada y eliminar; al eliminar la portada se promueve la siguiente. Portada obligatoria para publicar (`motivo=sin_portada`).
- **Archivos**: `upload/rifas/{org_id}/` con MIME real, <=4MB, nombre aleatorio. `Media::rifa($id)` sirve la portada y `Media::rifa_imagen($img_id)` la galería, con autorización por estado/rol real/BD/organización.
- **Concurrencia**: `Rifa_imagen_model` bloquea la rifa padre (`FOR UPDATE`) antes de contar/insertar/marcar/eliminar; el máximo 3 y la unicidad de portada se mantienen. Quedan LOW no bloqueantes de `img_orden` duplicado y 500 transitorio en borrado concurrente.
- **Seguridad**: `Media` revalida `usu_tipoper` real desde `cab_usuarios` (no confía en `es_master` de sesión) y exige `usu_status=1`; el máster real puede ver imágenes de organizaciones suspendidas por moderación.
- **Validación**: SECURITY `ACCEPTED_WITH_RISKS`; QA `ACCEPTED` (sanitizer/XSS, CKEditor, galería, portada, concurrencia, Media, publicación, IDOR, CSRF, admin, regresión M03.1/M02.1 y limpieza). `tu-suerte` intacto.

### ADR-038: Inventario de tickets, bloqueos y venta presencial — M03.3 (2026-11-10)
- **Modelo de inventario**: `rif_tickets` guarda solo números ocupados (sparse). `disponibles = total - reservados - pagados - bloqueados`; no se precargan millones de filas. La numeración total sigue en `rif_rifas`. El UNIQUE por `tick_numero` y por `tick_numero_int` evita dobles ventas y duplicados si cambia el padding.
- **Estados**: `reservado` (comprador, pago pendiente), `pagado` (confirmado por organizador o venta presencial) y `bloqueado` (retirado de la venta por el organizador). Las transiciones se auditan en `rif_ticket_historial`; liberar borra la fila y deja historial.
- **Bloqueos**: el organizador acepta rangos y listas (`10-20, 25, 86`); la operación es todo o nada. Bloquear/liberar usa `SELECT ... FOR UPDATE` sobre la rifa, parseo previo al lock, `affected_rows` y transacción. Liberar solo acepta números bloqueados. El listado de bloqueos se limita a 2,000 filas y la UI avisa si hay más.
- **Venta presencial**: el organizador puede marcar números como pagados con nombre de comprador, para eventos sin compra web; no pisa reservados/pagados, convierte bloqueados a pagados y guarda snapshot de precio/moneda.
- **Asignación**: `rifa_modo_asignacion` manual/automatica. En automática `reservar_automatico()` asigna N números aleatorios con `random_int`, sin duplicados, con lock y UNIQUE; exige modo automatic y admite `org_id`. M07 la usará en el checkout.
- **Visibilidad**: `rifa_mostrar_reservados=1` mostrará reservados/bloqueados en el sitio público; `0` mostrará solo disponibles. M03.3 solo persiste el ajuste; el endpoint público es M07 y no debe reutilizar `get_mapa()` del organizador (expone comprador/motivo).
- **Integridad**: en borrador/rechazada se bloquea cambiar numeración/lote/padding/tipo si ya existen tickets, con revalidación después de normalizar el lote. Los endpoints de organizador filtran por `org_id` de sesión; el admin solo lee con `autorizar_accion('consultar')`.
- **Validación**: DATABASE doble aplicación idempotente (`create_tickets_inventory` + `harden_tickets_inventory`); SECURITY `ACCEPTED`; QA `ACCEPTED_WITH_RISKS` (86/86 pruebas; NOT_RUN HTTP autenticado/CSRF/IDOR dinámico y UI visual por falta de credenciales). `tu-suerte`/rifa 103 intactos; `rif_tickets=0`, `rif_ticket_historial=0` tras limpieza.
- **Pendiente M07**: endpoint público de disponibilidad que respete `rifa_mostrar_reservados`, FKs de `tick_orden_id`/`tick_comprador_id`, rate limiting y prueba de carrera `actualizar` vs `bloquear`.

### ADR-039: Monedas y tasas de cambio — M04 (2026-11-10)
- **Catálogo**: `rif_monedas` con base USD y seeds EUR/VES/COP/MXN/USDT/GBP. `mon_codigo` es VARCHAR(10) para admitir USDT; se ampliaron `rif_rifas.rifa_moneda`, `rif_planes.plan_moneda` y `rif_tickets.tick_moneda` a VARCHAR(10).
- **Tasas**: `rif_tasas_cambio` guarda `tasa_valor` como unidades de la moneda por 1 USD, con UNIQUE (moneda, fecha). La tasa actual es la última con fecha <= hoy. USD siempre 1.0000. El historial queda en la misma tabla; `get_tasa_actual`/`get_tasas` alimentan el modal.
- **Reglas**: USD no puede eliminarse, desactivarse ni cambiar de código. No se elimina/desactiva una moneda en uso por rifas, planes, tickets, órdenes o planes de crédito, ni se cambia su código si está referenciada. Las tasas no-USD se validan con regex de hasta 9 enteros y 10 decimales; valores que redondean a 0 se rechazan.
- **UI**: `/monedas` con DataTables, modal de moneda (código, nombre, símbolo, decimales, orden, estado, fuente API futura y tasa inicial) y modal de tasas (vigente + historial + alta/edición por fecha). La vista oculta acciones según permisos; el backend autoriza por endpoint.
- **Integración**: `MisRifas` y `PlanesRifa` obtienen monedas activas del catálogo y validan contra él; si el catálogo no está disponible, el guardado falla con 422 (sin fallback de validación). Los `<select>` muestran el catálogo activo.
- **API futura**: `mon_api_fuente`, `mon_api_codigo`, `mon_api_ultima_sync` y `tasa_origen=api` quedan preparados; la sincronización automática no está implementada.
- **Validación**: DATABASE idempotente (`create_monedas_module`, `widen_currency_columns`); SECURITY `ACCEPTED` (sin MEDIUM/HIGH abiertos); QA `ACCEPTED_WITH_RISKS` (100/100 pruebas; NOT_RUN HTTP autenticado, CSRF/403 dinámico y UI visual). `tu-suerte`/rifa 103 intactos; `rif_monedas=7`, `rif_tasas_cambio=1`. Nota: rifa 103 tiene 4 tickets bloqueados reales (05,07,22,76, motivo "Para sorteo de premios", actor `hcrespo`) que deben preservarse.
- **Pendientes**: M05 métodos de pago dinámicos; sincronización API de tasas; rechazo estricto de USD distinto de 1 en `guardar-tasa` si se desea; índices en `rif_tickets.tick_moneda` y `cab_movplanes.mov_moneda`; prueba de carrera entre creación de rifa/plan y borrado de moneda.

### ADR-040: Métodos de pago dinámicos — M05 (2026-11-10)
- **Catálogo**: `rif_metodos_pago` distingue plantillas globales (`met_scope='global'`) de métodos propios (`met_scope='organizador'`, `met_org_id`). Los campos viven en `rif_metodo_campos` y soportan text/email/tel/number/url/textarea/select, obligatoriedad, placeholder, ayuda y opciones.
- **Configuración**: cada organizador crea una instancia en `rif_org_metodos` y completa valores en `rif_org_metodo_valores`. Los globales no se editan por el organizador; solo se completan sus valores. Los propios permiten editar nombre, instrucciones y campos.
- **Selección por rifa**: `rif_rifa_metodos` vincula la rifa con instancias activas y completas. `MisRifas` sincroniza `rifa_metodos[]` solo si el bloque fue enviado (`rifa_metodos_present`). Una rifa paga no puede publicarse/reactivarse sin al menos un método activo y completo; una rifa activa/pausada no puede quedar sin métodos al editar.
- **Protecciones**: no se elimina un método propio vinculado a cualquier rifa (histórico incluido); no se desactiva un global con organizadores configurados o rifas activas; no se modifican campos de un método vinculado a una rifa no cerrada. El estado global solo cambia por el endpoint de status, no por el formulario de edición.
- **UI**: `/metodos-pago` administra plantillas globales y campos. `/mis-metodos-pago` permite usar/desconfigurar globales, crear métodos propios, completar valores, activar/desactivar y eliminar propios sin vínculos. El formulario de rifa muestra checkboxes de métodos completos.
- **Validación**: DATABASE idempotente (`create_payment_methods_module`); SECURITY `ACCEPTED` (M1/M2/M3 y LOW relevantes cerrados); QA `ACCEPTED_WITH_RISKS` (F1 corregido; 77/77 regresión; NOT_RUN HTTP autenticado/UI visual/IDOR real). Datos reales preservados: `orgmet 44` (Binance Pay, org 33) con 2 valores, sin vínculos a rifas; 4 tickets bloqueados de rifa 103; `rif_monedas=7`, `rif_tasas_cambio=1`.
- **Pendientes**: M07 checkout público que use los métodos/valores seleccionados y respete `rifa_mostrar_reservados`; snapshots de método/valores en órdenes; guarda de edición de campos acotada a `activa`/`pausada` si se desea permitir editar métodos de borradores; pruebas HTTP autenticadas y UI visual.

### ADR-041: Checkout público y órdenes — M07 (2026-11-10)
- **Sitio público**: `/sitio/{slug}` muestra rifas activas; `/sitio/{slug}/rifa/{rifa_slug}` es la ficha/checkout; `/orden/{codigo}` muestra la orden. Se agregaron rutas equivalentes para hosts tenant (`/rifa/...`, `/orden/...`) y se mantiene la resolución de tenant por host o slug. La ficha respeta `rifa_mostrar_reservados`: si es 0 solo devuelve números disponibles y oculta estados; el mapa público nunca expone comprador ni motivo.
- **Órdenes**: `rif_ordenes` guarda comprador, cantidad, precio/total/moneda, método elegido, snapshot JSON del método/valores, estado y expiración. La creación es transaccional: lock de rifa, parseo/validación previa, reserva atómica de tickets `reservado` y rollback ante conflicto. Manual usa números elegidos; automática asigna aleatorios con `random_int`.
- **Estados**: `pendiente` → `pagada` (aprueba organizador, tickets pasan a `pagado`) o `rechazada`/`expirada` (tickets se liberan y queda historial). No se aprueban/rechazan/expiran órdenes cerradas y no se borran tickets pagados. La aprobación/rechazo se hace desde `/mis-ordenes` (URL libre, organización desde sesión).
- **Abuso**: tope de 10 tickets pendientes por IP y tope global del 80% del inventario; rate-limit de 20 órdenes/IP/15 min; token de checkout por rifa en sesión (máximo 10 tokens por rifa, 5 rifas) y throttle de 10 s; validación/rate-limit antes del lock y revalidación bajo `FOR UPDATE`. TTL: `auto=1` + TTL 0 usa 60 min; `auto=0` no expira.
- **Privacidad**: la orden pública enmascara nombre, email, teléfono y documento; `noindex, nofollow` + `no-store` en `/orden/{codigo}`. CSRF global en el checkout; se usa `REMOTE_ADDR` para rate-limit (no headers CDN).
- **Validación**: DATABASE idempotente (`create_orders_module`); SECURITY `ACCEPTED_WITH_RISKS` (tope distribuido 80% y dependencia de IP real/proxy; sin CAPTCHA/verificación email); QA `ACCEPTED_WITH_RISKS` (120/120 aserciones, HTTP público 200/403/409/422; NOT_RUN E2E autenticado, IDOR con dos organizadores y UI visual). Datos reales intactos: `rif_ordenes=0`, 4 tickets bloqueados de rifa 103, historial 4, orgmet 44 Binance Pay + 2 valores, monedas 7, tasas 1, métodos 6, campos 16.
- **Pendientes**: CAPTCHA/verificación de email; agregación IPv6 /64; garantizar IP real detrás de proxy/CDN; mínimo disponible real; cron de expiración; índices `(ord_rifa_id, ord_ip, ord_created_at)` y `(ord_estado, ord_reserva_expira_at)`; E2E autenticado/IDOR/UI; posible snapshots inmutables en órdenes.

### ADR-042: Icono y logo de métodos de pago — M05/M07 (2026-11-10)
- **Medios**: `rif_metodos_pago` agrega `met_icono` (clase KeenIcons de una whitelist) y `met_imagen` (archivo local del logo). Los globales se siembran con iconos (`ki-bank`, `ki-phone`, `ki-dollar`, `ki-wallet`, `ki-credit-cart`, `ki-bill`). Los métodos propios permiten subir un logo y elegir icono.
- **Subida**: `Metodo_logo` procesa `met_logo` (JPG/PNG/WEBP, máximo 4 MB), valida MIME real y guarda en `upload/metodos/` con nombre aleatorio. No se aceptan URLs externas. `Media::metodo_imagen()` sirve el logo; los activos son públicos y los inactivos solo master/propietario.
- **Persistencia**: admin (`Metodo_pago_model`) y organizador (`Org_metodo_model`) guardan icono/archivo; al reemplazar o quitar el logo se borra el archivo anterior. El snapshot de la orden incluye la URL del logo vigente.
- **Render**: icono/logo se muestran en admin, Mis Métodos, selección de métodos de la rifa, checkout público y orden. Fallback `ki-bill` si no hay logo.
- **Validación**: SECURITY `ACCEPTED_WITH_RISKS` (residual bajo de imágenes externas ya eliminado; ahora archivos locales con MIME/límite); QA manual PASS (persistencia global/custom, snapshot, Media 200/404, validación de archivos, sin residuos). NOT_RUN: admin/organizador autenticado y UI visual.
- **Pendientes**: fallback visual si el archivo se pierde; unificar whitelist de iconos en un helper; limpieza de logos huérfanos si se borra un método directamente en BD.

### ADR-043: Compra de planes/lotes reutilizando Comprar Créditos - M08 (2026-11-10)
- **Decisión**: `Comprar Créditos` (`comprar-creditos`) se reutiliza como **Comprar Planes**. El organizador compra lotes de rifa por cantidad; el menú `Plan/index` se retiró y `men_id=139` se renombró a `Comprar Planes` conservando URL, slug y permisos del rol `1000`. No se creó un alias sin menú porque `MY_Controller` aplica deny-by-default por URL registrada.
- **Ofertas**: `cab_planes` agrega `pla_tipo` (`creditos`/`plan_rifa`), `pla_rif_plan_id`, `pla_rif_num_id` (0=ilimitado) y `pla_rif_periodo`; UNIQUE `(pla_rif_plan_id, pla_rif_num_id)`. `Plan_compra_model` sincroniza ofertas desde `rif_planes`, `rif_plan_numeraciones` y `rif_numeraciones` solo después de mutaciones POST de `PlanesRifa` (crear/actualizar/eliminar/status de planes y numeraciones), nunca desde el GET de compra.
- **Autoridad de datos**: `get_oferta()` relee la fuente autoritativa y devuelve precio/moneda/periodo actuales; rechaza opciones o numeraciones inactivas y tipos incompatibles. `get_ofertas_activas()` también calcula precio/moneda/periodo desde la fuente. El total se calcula siempre en servidor; la cantidad se valida estrictamente como entero 1..10000.
- **Orden y lote**: `rif_plan_compras` guarda `compra_mov_id` UNIQUE, org, plan, opción, cantidad (0=ilimitado), periodo, precio unitario, total, moneda, lote y estado. Al liquidar Stripe/BTCPay se crea `rif_plan_asignaciones` en la misma transacción; `crear_lote_para_orden()` es idempotente por `compra_mov_id`/`compra_asig_id`. Finitos: `asig_num_id` + `asig_cantidad`; ilimitados: ambos NULL y `asig_fin` +1 mes/año.
- **Protecciones**: no se degrada una orden de plan a créditos si falta `rif_plan_compras`; `_orden_es_plan()` aborta el settlement. La cancelación es transaccional y rechaza compras pagadas o con lote. FKs de `rif_plan_compras` y `rif_plan_asignaciones` hacia organizador/lote usan `RESTRICT` para preservar trazabilidad.
- **Migraciones**: `2026-11-10_merge_plan_purchase.sql` (columnas, tabla, backfill, menú) y `2026-11-10_z_harden_plan_purchase_fks.sql` (FKs RESTRICT). Ambas aplicadas e idempotentes; la segunda debe ejecutarse después de la primera.
- **Validación**: SECURITY `ACCEPTED_WITH_RISKS` sin hallazgos altos/críticos; QA `ACCEPTED_WITH_RISKS` tras corregir QA-M08-01/02/03 (ilimitados con vencimiento, cantidad estricta, cancelación atómica). Pruebas CLI con rollback: finitos 2 lotes, ilimitados mensual/anual, Stripe/BTCPay idempotentes, sin degradación a créditos, FKs RESTRICT, datos reales intactos. NOT_RUN: HTTP autenticado, webhooks firmados reales, concurrencia multi-request y UI visual.
- **Pendientes**: prueba E2E autenticada y visual; persistir un tipo inmutable de orden (`mov_tipo`) para no depender de `cab_planes`; rate-limit de sincronización; `compra_mov_id` sin FK física.

### ADR-045: Planes ilimitados mensual, semestral y anual (2026-11-11)
- **Decisión**: se agregó el periodo `semestral` y se crearon tres planes ilimitados de lanzamiento:
  - `Sortea2 Ilimitado Mensual` — USD 9.99 — 1 mes (`plan_id=176`, oferta `pla_id=27`).
  - `Sortea2 Ilimitado Semestral` — USD 49.99 — 6 meses (`plan_id=177`, oferta `pla_id=28`).
  - `Sortea2 Ilimitado Anual` — USD 79.99 — 12 meses (`plan_id=178`, oferta `pla_id=29`).
- **Semestral**: migración `2026-11-11_add_semestral_period.sql` amplía `rif_planes.plan_periodo`, `cab_planes.pla_rif_periodo` y `rif_plan_compras.compra_periodo`. La asignación/compra calcula `asig_fin` a +6 meses; mensual +1 mes; anual +1 año.
- **UI**: en el modal de Planes de Rifa, el campo **Vigencia** se oculta para planes `rifas` y se muestra para `ilimitado` con opciones Mensual, Semestral y Anual. Antes el select deshabilitado solo dejaba ver “Sin límite” para quien no cambiaba el tipo.
- **Seed**: `database/2026-11-11_seed_unlimited_plans.sql` es idempotente y no crea opciones de numeración (los ilimitados eligen numeración por rifa). Se sincronizaron las ofertas en `cab_planes`.
- **Validación**: migración aplicada; `get_oferta`/`get_ofertas_activas` devuelven precios 9.99/49.99/79.99 y periodos correctos; compra/assignment de prueba en transacción semestral generó `asig_fin` +6 meses y `asig_cantidad=NULL`; rollback sin residuos. `php -l` y `node --check` OK. Se detectó una asignación real mensual activa para `org_id=33` (`asig_id=160`, plan 176, fin 2026-12-11, notas "Para prueba mensual"); se preservó sin modificarla.

### ADR-044: Precios de lanzamiento de planes de rifa (2026-11-11)
- **Decisión**: iniciar con precios bajos para atraer organizadores. Los precios son por lote, no por ticket final; el organizador define el precio de venta de cada número.
- **Planes y precios**:
  - `Sortea2 S1` — 100 números — **USD 1.00** (`plan_id=121`).
  - `Sortea2 S2` — 1.000 números — **USD 7.00** (`plan_id=167`).
  - `Sortea2 S3` — 10.000 números — **USD 50.00** (`plan_id=168`).
- **Seed**: `database/2026-11-11_seed_launch_plans.sql` es idempotente y crea/actualiza planes y opciones `rif_plan_numeraciones`. Luego se ejecutó `Plan_compra_model::sincronizar_todas()` para generar las ofertas `cab_planes` 12, 25 y 26.
- **Precio tachado**: para planes por rifas, `rif_planes.plan_precio` guarda el precio regular de lista ($2.00/$18.00/$150.00) y `rif_plan_numeraciones.pnum_precio` el precio actual de lanzamiento ($1.00/$7.00/$50.00). `Plan_compra_model` expone `pla_precio_regular`; la vista muestra el regular tachado, el badge de descuento y el precio actual. El cobro sigue usando `pnum_precio`.
- **Configuración**: en el modal de Planes de Rifa, para `plan_tipo='rifas'` el campo se etiqueta **Precio regular (tachado)** y cada opción usa **Precio actual por rifa**. El porcentaje se calcula automáticamente; no hay campo de porcentaje separado. Para planes ilimitados el campo vuelve a ser **Precio del plan** y no genera descuento.
- **Datos preservados**: la asignación real del plan 121 y la rifa 103 no se tocaron; solo se actualizó el precio de la opción de 100 números y `plan_limite` a 100. El precio de lotes ya asignados no cambia.
- **Validación**: seed aplicado, sincronización `sync=1`, consultas confirman planes/opciones/ofertas con precios 1.00/7.00/50.00 USD. No se ejecutó compra HTTP real.

### ADR-046: Moderación de rifas gratuitas y cancelaciones - M09 (2026-11-11)
- **Decisión**: `/rifas` deja de ser solo lectura. El admin puede aprobar/rechazar rifas gratuitas pendientes y aprobar/rechazar solicitudes de cancelación. Las transiciones se hacen con `Rifa_model` y `FOR UPDATE`, no mediante SQL directo.
- **Rifas gratuitas**: aprobar exige una numeración activa (`num_id`); el modelo valida rango, padding, cantidad, portada, título y fecha futura, asigna `rifa_num_id` y campos de numeración, y publica la rifa (`activa`). Rechazar exige motivo y deja la rifa en `rechazada`.
- **Cancelaciones**: aprobar solo desde `cancelacion_solicitada`; cancela, devuelve cupo y conserva/actualiza el motivo. Rechazar restaura `rifa_estado_previo` y limpia la solicitud. Se bloquea la aprobación si hay tickets `pagado` u órdenes `pagada` para evitar cerrar una rifa con ventas sin reembolso.
- **UI**: acciones en DataTables (`btn-aprobar-gratuita`, `btn-rechazar-gratuita`, `btn-aprobar-cancelacion`, `btn-rechazar-cancelacion`), modales independientes, selector de numeración activa y motivos con validación. Los botones se generan solo si el rol tiene `modificar`.
- **Rutas**: `rifas/aprobar-gratuita/:id`, `rifas/rechazar-gratuita/:id`, `rifas/aprobar-cancelacion/:id`, `rifas/rechazar-cancelacion/:id`. Son POST+AJAX, usan CSRF global y `autorizar_accion('modificar')`.
- **Auditoría**: `RIFA_APROBAR_GRATUITA`, `RIFA_RECHAZAR_GRATUITA`, `RIFA_CANCELACION_APROBAR`, `RIFA_CANCELACION_RECHAZAR`.
- **Validación**: `php -l` OK; render y `node --check` OK; CLI transaccional aprobó una gratuita con numeración 1.000 y revirtió; cancelar/rechazar cancelación OK; cancelación con ticket pagado devuelve `ventas_activas` y sin ticket cancela; rollback sin residuos. Rutas nuevas responden 403 sin sesión. SECURITY `ACCEPTED_WITH_RISKS`; QA `ACCEPTED_WITH_RISKS` (28 PASS / 0 FAIL / 7 NOT_RUN) tras bloquear cancelaciones con ventas pagadas.
- **Riesgos pendientes**: la aprobación no congela ediciones posteriores de portada/fecha/contenido (M-01); bitácora no atómica; TOCTOU de numeración; falta prueba HTTP autenticada y concurrencia real. No hay datos reales de prueba modificados.

### ADR-047: Corrección de UI de Mis Rifas: descripción, imágenes y portada (2026-11-11)
- **Problema**: al crear una rifa, la descripción no aceptaba escritura; la vista previa de "Imagen de la rifa" y de las imágenes adicionales no se mostraba; el campo único de imagen duplicaba la galería y su selección de portada.
- **Causa raíz**:
  1. El bundle `ckeditor-classic.bundle.js` lanza `TypeError: Cannot read properties of undefined (reading 'flush')` al escribir, incluso en una página aislada sin el resto del sistema. El editor quedaba inutilizable.
  2. El CSP enviado por `Security_headers.php` usaba `img-src 'self' data: https:` y bloqueaba las URL `blob:` que genera `URL.createObjectURL()` para las vistas previas locales.
- **Decisión**:
  1. Retirar CKEditor 5 de `MisRifas` (vista y assets del controlador) y dejar `rifa_descripcion` como `textarea` normal. El backend conserva `Html_sanitizer` y el límite de 20000 caracteres.
  2. Eliminar el input legacy `rifa_imagen` de la vista. La galería (`rifa_imagenes[]` en creación y `imagen-subir` en edición) es la única vía de imágenes y define la portada. El backend mantiene el fallback legacy para compatibilidad, pero ya no se expone en el formulario.
  3. Agregar `blob:` a `img-src` del CSP para permitir las vistas previas locales de la galería.
- **Archivos**: `application/views/pages/mis_rifas/index.php`, `application/controllers/MisRifas.php`, `application/hooks/Security_headers.php`.
- **Validación**: `php -l` OK en los tres archivos; `git diff --check` OK. Playwright con Chrome headless y sesión real: el textarea acepta texto; no existen `#rifa_imagen`, etiqueta "Imagen de la rifa", `.ck-editor` ni script de CKEditor; al seleccionar 2 imágenes la galería muestra 2 tarjetas con `blob:` y ambas imágenes visibles (`naturalWidth > 0`); CSP incluye `blob:`; sin errores de página ni de CSP. No se guardó ninguna rifa de prueba.
- **Riesgos/pendientes**: los módulos `CorreosPromocionales` y `PlantillasCorreo` siguen cargando el bundle CKEditor y podrían presentar el mismo fallo; no se tocaron en este cambio. La edición de una rifa existente no se pudo probar desde la tabla porque la sesión de prueba no mostró acciones de organizador; el flujo de edición no se modificó salvo la eliminación del campo legacy.

### ADR-048: Precio del ticket en rifas gratuitas (2026-11-11)
- **Decisión**: el tipo `gratuita` significa **publicación gratuita**: no consume un lote del plan y queda en `pendiente_aprobacion` hasta que el admin la apruebe (M09). No significa que el ticket sea gratis. Toda rifa, incluida la gratuita, debe definir un precio de ticket mayor que cero para recaudar fondos.
- **Backend**: `MisRifas::_recoger_post` normaliza el precio para ambos tipos; `_validar` exige `rifa_precio > 0` y `_mapear_data_guardado` ya no fuerza `0.00` en gratuitas. `Rifa_model::_validar_publicacion` exige precio válido también antes de publicar/aprobar una gratuita. `Orden_model` acepta `paga` y `gratuita` siempre que el precio sea mayor que cero, y `Publico` muestra el checkout de gratuitas con precio en lugar de bloquearlas.
- **UI**: en Mis Rifas el precio, la moneda y los métodos de pago permanecen visibles para gratuitas; el selector aclara “Gratuita (publicación gratis)” y el aviso explica que la numeración se asigna al aprobar. Las tablas de Mis Rifas y Rifas muestran el precio también para gratuitas.
- **Compatibilidad**: el fallback legacy de `rifa_imagen` se conserva en el backend, aunque la vista usa solo la galería. Las gratuitas históricas con precio 0 no podrán publicarse/aprobarse hasta que se corrija su precio.
- **Validación**: `php -l` OK en controladores, modelos y vistas; `git diff --check` OK. Pruebas aisladas: `Rifa_model::_validar_publicacion` acepta gratuita con precio 5 y rechaza precio 0; `MisRifas::_validar` acepta gratuita con precio 5 y rechaza 0; `_tipo_html` de Rifas y MisRifas muestra “Gratuita 5.00 USD”. Playwright/Chrome: al elegir gratuita el precio y métodos siguen visibles, el plan se oculta, se exige precio y el submit con precio 5 pasó la validación (petición interceptada y abortada; no se creó ninguna rifa).
- **Riesgos/pendientes**: los métodos de pago siguen siendo opcionales al crear, pero `publicar` y `reactivar` exigen al menos uno activo y completo para cualquier rifa con precio > 0; una activa/pausada tampoco puede quedarse sin métodos. No se probó un checkout público real de una gratuita porque no había datos de prueba; queda pendiente E2E con una gratuita aprobada.

### ADR-047: Agrupación de Organizadores y Rifas bajo Administración (2026-11-11)
- **Decisión**: crear el menú padre `Administración` y mover `Organizadores` y `Rifas` como submenús. El objetivo es separar la operación administrativa de los módulos de configuración y reportes.
- **Menú**: padre `men_id=147`, slug `administracion`, `men_tipo=0`, orden 9, icono `ki-duotone ki-shield-tick`. Hijos: `Organizadores` (`men_id=142`, orden 1) y `Rifas` (`men_id=144`, orden 2), ambos `men_tipo=1` y `sub_men_id=147`.
- **Permisos**: se creó `cab_permisologia`/`cab_permiaccion` `1000_147` para el rol Admin Master. Los permisos existentes de los hijos no se modificaron.
- **Migración**: `database/2026-11-11_move_admin_menus.sql`, idempotente. Invalida `permisos_version` para que las sesiones reconstruyan el snapshot del menú.
- **Validación**: `Menu_model::get_arbol_por_usuario('000001','1000')` devuelve `Administración` con `Organizadores` y `Rifas` como hijos; el resto del árbol permanece igual.
