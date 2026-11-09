# AGENTS: Sistema Multi-Agente y Reglas Maestras del Proyecto Base

Este archivo es portable y puede ser interpretado por Antigravity, OpenCode,
Claude, Gemini, GPT u otro asistente. Los nombres de herramientas del IDE no
son parte del contrato: cada agente debe usar la capacidad equivalente
disponible en su entorno.

Este documento establece la **arquitectura del sistema multi-agente** y las **reglas de ingeniería de software** para el desarrollo y escalamiento de este proyecto base en cualquier sistema administrativo, operativo o comercial.

---

## 1. Estructura del Sistema Multi-Agente

Los perfiles ejecutables de los agentes se encuentran en
`.agents/agents/`. Cada perfil define una responsabilidad, sus archivos
permitidos y sus validaciones. Un agente no debe asumir el trabajo de otro ni
editar archivos fuera de su alcance sin autorización del Coordinador.

Para garantizar un código limpio, desacoplado y libre de errores, el desarrollo se organiza en roles especializados. Cada agente asume una responsabilidad clara dentro del flujo de trabajo:

```
                  ┌────────────────────────────────────────┐
                  │           LEAD ORCHESTRATOR            │
                  │ (Planificación, Coordinación y Cierre) │
                  └───────────────────┬────────────────────┘
                                      │
         ┌───────────────┬────────────┴─────────────┬────────────────┐
         ▼               ▼                          ▼                ▼
┌────────────────┐┌────────────────┐      ┌────────────────┐┌────────────────┐
│MODULE ARCHITECT││DATABASE SPEC.  │      │BACKEND SPEC.   ││FRONTEND SPEC.  │
│ (Estructura y  ││ (Migraciones,  │      │(Controladores, ││  (Metronic UI, │
│  Convenciones) ││ Esquemas, Menu)│      │ CI3, DataTables││  JS, Modales)  │
└───────┬────────┘└───────┬────────┘      └────────┬───────┘└────────┬───────┘
        │                 │                        │                 │
        └─────────────────┴───────────┬────────────┴─────────────────┘
                                      ▼
                      ┌────────────────────────────────┐
                      │    SECURITY & AUTH GUARDIAN    │
                      │ (RBAC, Sesiones CI, Bitácora)  │
                      └───────────────┬────────────────┘
                                      ▼
                      ┌────────────────────────────────┐
                      │    QA & VERIFICATION SPEC.     │
                      │ (Testing, JSON, Error Logs)    │
                      └────────────────────────────────┘
```

### Roles y Responsabilidades

#### 1. Lead Orchestrator (Coordinador General)
- **Función**: Analiza la solicitud del usuario, formula el plan de ejecución, delega tareas a los roles especialistas en el orden correcto y realiza la revisión final.
- **Cuándo interviene**: Al recibir cualquier requerimiento complejo, refactorización o creación de nuevos módulos.
- **Perfil**: `.agents/agents/orchestrator.md`

#### 2. Module Architect (`module-architect`)
- **Función**: Define la arquitectura del módulo, nombres de dominio (controladores, modelos, vistas, slugs), asegurando que se respete el patrón canónico de `Usuarios` y la consulta previa de plantillas en `proyecto_base_metronic/dist/html/` (`demo10` preferido o cualquiera de los demás demos).
- **Habilidades clave**: `.agents/skills/module-architecture/SKILL.md`, `.agents/skills/create-crud-module/SKILL.md`
- **Perfil**: `.agents/agents/module-architect.md`

#### 3. Database Specialist (`database-specialist`)
- **Función**: Diseña esquemas SQL relacionales, claves foráneas, índices de alto rendimiento, y scripts de migración idempotentes en `database/` incluyendo registros en `cab_menu`, `cab_permisologia` y `cab_permiaccion`.
- **Habilidad clave**: `.agents/skills/db-migration/SKILL.md`
- **Perfil**: `.agents/agents/database-specialist.md`

#### 4. Backend Specialist (`backend-specialist`)
- **Función**: Construye el controlador (extendiendo `MY_Controller`) y el modelo CI3. Implementa los métodos `index()`, `tabla()`, `datos()`, `crear()`, `actualizar()`, `eliminar()`, y garantiza el contrato server-side estricto de DataTables.
- **Regla central**: El controlador solo normaliza parámetros y formatea HTML; el modelo concentra queries y conteos.
- **Habilidad clave**: `.agents/skills/backend-ci3/SKILL.md`
- **Perfil**: `.agents/agents/backend-specialist.md`

#### 5. Frontend Metronic Specialist (`frontend-specialist`)
- **Función**: Desarrolla la interfaz en `application/views/pages/<modulo>/index.php`. **REGLA**: Antes de maquetar cualquier vista nueva, debe inspeccionar `c:\xampp\htdocs\proyecto_base_metronic\dist\html/` (consultando preferentemente `demo10/` o cualquiera de los demás demos `demo1` a `demo9`) para encontrar la plantilla o componentes afines (facturación, tienda/store, membresías, tablas, seguridad, dashboards) y seguir fielmente su estructura y apariencia HTML.
- **Regla central**: Modales independientes, botones de acción compactos (`btn-sm`) con icono obligatorio en acciones con texto (Guardar, Cancelar, etc.), limpieza de formularios con `limpiar_modal()`, y renderizado reactivo sin variables globales ocultas.
- **Regla de Temas**: Garantiza compatibilidad nativa e impecable con el Modo Claro y Modo Oscuro dinámico (`data-bs-theme`), utilizando clases semánticas (`bg-body`, `text-gray-900`, `text-gray-700`, `text-muted`, `border-gray-200`) y variables CSS sin colores rígidos quemados (`style="color: black"`, `style="background: white"` prohibidos).
- **Habilidad clave**: `.agents/skills/frontend-metronic/SKILL.md`
- **Perfil**: `.agents/agents/frontend-specialist.md`

#### 6. Security & Auth Guardian (`security-specialist`)
- **Función**: Audita el control de acceso basado en roles (RBAC), protege el rol `1000` (Admin Master), valida sesiones activas en archivos CI, supervisa el streaming seguro en `Media.php` y exige trazabilidad completa en `cab_bitacora`.
- **Habilidad clave**: `.agents/skills/security-audit/SKILL.md`
- **Perfil**: `.agents/agents/security-specialist.md`

#### 7. QA & Verification Specialist (`qa-specialist`)
- **Función**: Verifica códigos de respuesta HTTP (200, 404, 422, 500), inspecciona los archivos de registro en `application/logs/`, comprueba casos borde (búsquedas vacías, paginaciones extremas) y valida la integridad de los flujos AJAX.
- **Perfil**: `.agents/agents/qa-specialist.md`
- **Habilidad clave**: `.agents/skills/qa-module/SKILL.md`

### Protocolo obligatorio de ejecución

Para un cambio que involucre más de un archivo, el Coordinador debe ejecutar o
delegar estas fases en orden:

1. **Arquitectura**: definir alcance, archivos, contrato de datos y riesgos.
2. **Base de datos**: diseñar y revisar migraciones, índices, menú y permisos.
3. **Backend**: implementar modelo, controlador, validaciones y auditoría.
4. **Frontend**: implementar vista, AJAX, DataTables y compatibilidad de temas.
5. **Seguridad**: revisar autenticación, autorización, entrada de datos,
   archivos, sesiones y bitácora.
6. **QA**: ejecutar pruebas estáticas, funcionales y de regresión; el cambio
   no se considera terminado si QA reporta un bloqueo.
7. **Cierre**: revisar el diff completo, actualizar documentación y entregar
   archivos modificados, pruebas, riesgos y pendientes.

Las fases 2 y 3 pueden ejecutarse en paralelo únicamente después de aprobar el
contrato de arquitectura. Las fases 5 y 6 deben ejecutarse después de la
implementación. El Coordinador es el único responsable de declarar el cambio
listo.

### Reglas de aislamiento de agentes

- Ningún agente debe modificar `.env`, credenciales o secretos.
- No se modifican migraciones que puedan estar aplicadas; se crea una nueva.
- `MY_Controller.php`, `Auth.php` y el núcleo de CodeIgniter requieren revisión
  explícita del Coordinador antes de modificarse.
- Solo el Coordinador actualiza `MEMORY.md` después de aceptar el cierre.
- Cada agente debe inspeccionar primero el código y las convenciones existentes.
- Cada agente debe informar archivos modificados, validaciones ejecutadas,
  riesgos y trabajo pendiente.
- Si una prueba no puede ejecutarse por falta de entorno, debe documentarse,
  nunca marcarse como aprobada.

### Contratos compartidos

- La fuente de verdad para permisos, handoffs, estados y criterios de cierre es
  `.agents/contracts/PROJECT_CONTRACTS.md`.
- Los agentes entregan sus resultados usando el contrato de handoff de la fase;
  el Coordinador es responsable de aceptar, devolver o bloquear la fase.

---

## 2. Reglas Maestras del Proyecto

### Identidad y Entorno
- Aplicación CodeIgniter 3 sobre entorno XAMPP (Apache + MySQL / MariaDB).
- UI Metronic demo1 integrada con Bootstrap 5 y KeenIcons.
- Módulos administrativos consistentes: Controlador + Modelo + Vista + DataTables/AJAX + Permisos en Menú.

### Estructura Base de Archivos
- Controladores protegidos en `application/controllers/` extendiendo obligatoriamente `MY_Controller`.
- Modelos en `application/models/` con nomenclatura `<Modulo>_model.php`.
- Vistas por módulo en `application/views/pages/<modulo>/index.php`.
- Layout global en `application/views/layouts/main.php` con componentes en `application/views/partials/`.
- Recursos estáticos en `assets/` y archivos subidos o multimedia en `media/`.

### Flujo Común de Módulos Protegidos
- `MY_Controller` valida la sesión activa en cada petición protegida.
- Si el usuario en sesión fue desactivado (`cab_usuarios.usu_status != 1`), `MY_Controller` destruye la sesión y lo expulsa inmediatamente.
- Utilidades nativas en `MY_Controller`:
  - `render($vista, $datos)`: Inyecta la vista dentro del layout Metronic.
  - `json($data, $status)`: Emite respuestas JSON con el código de estado HTTP adecuado.
  - `module_path($path)`: Construye URLs del módulo actual sin rutas fijas quemadas.
  - `bitacora($accion, $detalle)`: Registra eventos en `cab_bitacora`.
- En vistas protegidas usar siempre `base_url($url_base . '/')` para la raíz del módulo.

### Inspección Previa de Templates (`proyecto_base_metronic/dist/html/`)
- Antes de diseñar y maquetar la vista de un nuevo módulo, se debe inspeccionar el repositorio de referencia en `c:\xampp\htdocs\proyecto_base_metronic\dist\html/`, tomando como fuente **preferida** a `demo10/` pero con **pleno acceso a consultar y reutilizar componentes de cualquiera de los demás demos** (`demo1` a `demo9`).
- Metronic demo10 cuenta con pantallas optimizadas para:
  - `store-client/`: E-commerce, tiendas, inventario, catálogo, productos, carrito y órdenes.
  - `account/billing/`: Facturación, planes de suscripción, historial de transacciones.
  - `account/members/`: Roles, switches de permisos, tablas de miembros (`team-members-datatable.html`).
  - `account/security/`: Sesiones en vivo, registros de seguridad y control de acceso.
  - `network/user-table/`: Tablas de usuarios, clientes y registros con filtros avanzados.
- Los demás demos (`demo1` a `demo9`) aportan:
  - `demo1/`: Sidebars claros/oscuros (`dashboards/dark-sidebar.html`), headers, perfiles y widgets base.
  - `demo2` a `demo9`: Dashboards analíticos, módulos de soporte, CRM y tableros específicos.
- Al seleccionar una pantalla o componente afín de cualquier demo en `proyecto_base_metronic`, se debe seguir su estructura HTML, distribución y KeenIcons, adaptándola al estándar de modales y DataTables server-side de `proyecto_base`.

### Enrutamiento y Menús Dinámicos
- `application/config/routes.php` solo contiene rutas públicas o fijas del sistema (`login`, `logout`, etc.).
- Los módulos administrativos se resuelven dinámicamente desde la tabla `cab_menu` mediante `men_url` (ej. `Usuarios/index`) y `men_urlamigable` (ej. `usuarios`). La estructura actual usa `sub_men_id`, `men_tipo`, `men_img`, `men_imgmenu` y `men_inicio`; no asumir columnas `men_padre`, `men_icono` o `men_status`.
- Para URLs registradas, `MY_Controller` comprueba el acceso del rol al menú;
  no comprueba por sí solo las acciones CRUD y permite continuar si la URL base
  no está registrada. Comprobar acciones y rutas directas explícitamente.

### Compatibilidad Obligatoria con Temas Dinámicos (Modo Claro y Oscuro)
- El sistema cuenta con soporte bi-tema nativo en tiempo real (`data-bs-theme="light"` / `data-bs-theme="dark"`), administrado desde `ConfiguracionTema`.
- **Regla Estricta para Toda Vista Nueva**:
  1. Utilizar exclusivamente clases utilitarias de Bootstrap/Metronic sensibles al tema:
     - Textos: `text-gray-900`, `text-gray-800`, `text-gray-700`, `text-muted`, `text-primary`.
     - Contenedores y Cards: `card card-flush`, `bg-body`, `bg-light`, `bg-light-primary`.
     - Bordes y Separadores: `border-gray-200`, `separator`, `table-row-dashed`.
  2. Prohibición estricta de colores rígidos quemados en atributos `style="..."` (ej. `style="background: #fff;"` o `style="color: #000;"`) que provoquen cajas blancas o textos invisibles en modo oscuro.
  3. Para estilos CSS específicos de un módulo, consumir las variables CSS del tema (`var(--bs-primary)`, `var(--bs-body-bg)`, `var(--bs-card-bg)`, `var(--app-header-bg)`).
  4. Tablas DataTables deben usar el estándar `table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4` que adapta automáticamente sus líneas y estados hover.
  5. Modales deben construirse con la jerarquía canónica `modal-dialog` -> `modal-content` -> `modal-header` / `modal-body` / `modal-footer` asegurando que hereden el fondo y texto del tema configurado.

---

## 3. Patrón Canónico CRUD (Basado en Usuarios)

Todo nuevo módulo CRUD administrativo debe construirse tomando como estándar canónico el módulo de **Usuarios**:

### Estructura del Controlador
- `__construct()`: Carga el modelo, assets vendor (`page_styles`, `page_vendor_scripts`), define `page_title` y `breadcrumb`.
- `index()`: Prepara datos para la vista y llama a `render()`.
- `tabla()`: Resuelve `draw`, `start`, `length`, `search`, `order` para DataTables server-side.
- `datos($id)`: Retorna los datos de un registro en JSON para edición en modal.
- `crear()`: Recoge POST, valida mediante `_validar()`, persiste, registra en bitácora y responde JSON.
- `actualizar($id)`: Carga el registro, valida, persiste cambios, registra en bitácora y responde JSON.
- `eliminar($id)`: Valida existencia, aplica borrado lógico, registra en bitácora y responde JSON.
- **Helpers privados obligatorios**: `_recoger_post()`, `_validar(array $data, $id = 0)`, `_mapear_data_guardado()`, `_formatear_filas_datatable(array $rows)`.

### Contrato DataTables Server-Side Obligatorio
El modelo debe implementar:
- `count_todos()`: Total de registros activos/no eliminados.
- `count_filtrados($search = '')`: Total con filtro aplicado.
- `get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')`.
- `_datatable_query($search = '')`.

Respuesta JSON requerida en `tabla()`:
```json
{
  "draw": 1,
  "recordsTotal": 50,
  "recordsFiltered": 10,
  "data": [ ... ]
}
```
*`recordsTotal` nunca debe devolver el valor filtrado.*

### Contrato JSON para Modales AJAX
- `datos($id)`:
  - Éxito: `{ ok: true, data: { ... } }` (HTTP 200).
  - No encontrado: `{ ok: false, msg: '...' }` (HTTP 404).
- `crear()`, `actualizar()`, `eliminar()`:
  - Éxito: `{ ok: true, msg: 'Mensaje descriptivo' }` (HTTP 200).
  - Error de validación: `{ ok: false, errores: { campo: 'Mensaje' } }` (HTTP 422).
  - Error de servidor o denegación: `{ ok: false, msg: '...' }` (HTTP 500 / 403).

### Reglas de Negocio y Seguridad
1. **Validación Centralizada**: Reglas en `_validar()`, validando tipos, longitudes de BD y unicidad.
2. **Rol Admin Master (`1000`)**: Ningún usuario no-master puede asignar, modificar o eliminar usuarios con rol `1000`.
3. **Autoprotección**: Un usuario activo no puede desactivar ni eliminar su propia cuenta.
4. **Borrado Lógico**: Separar `<prefijo>_status` (1/0) de `<prefijo>_eliminado` (0/1).
5. **Auditoría Obligatoria**: Absolutamente todo nuevo módulo debe registrar en `cab_bitacora` toda mutación (crear, actualizar, eliminar, cambio de status) con formato `<MODULO>_<ACCION>` (ej. `CLIENTE_CREAR`, `CLIENTE_ACTUALIZAR`, `VENTA_PROCESAR`) mediante `$this->bitacora()`.
6. **Streaming Seguro**: Los archivos y avatares se transmiten mediante `Media.php` con validación de sesión y tipos MIME estrictos.
7. **Autorización por Acción**: Todo endpoint de mutación (`crear`, `actualizar`, `eliminar`, `guardar`, `enviar`, `banear`, etc.) debe invocar `autorizar_accion('incluir|consultar|modificar|eliminar', $mensaje)` después de validar el método/AJAX. El menú se resuelve por la URL del módulo a partir del snapshot `menu_estructura` de la sesión; el rol con cobertura total de menús conserva privilegios globales y el resto se rige por `cab_permisologia` y `cab_permiaccion`.
8. **CSRF**: Las vistas usan `partials/_csrf.php` (incluido por el layout) para inyectar `csrf_token` en fetch, XHR y formularios POST. No crear formularios POST administrativos fuera del layout sin incluir el token.
9. **Integridad de Permisos RBAC (`cab_menu`, `cab_permisologia`, `cab_permiaccion`)**: Al registrar un nuevo módulo en la base de datos:
   - `cab_menu`: Ítem de navegación (`men_id`, `men_nombre`, `men_url`, `men_urlamigable`, `men_tipo`, etc.).
   - `cab_permisologia`: Asignación rol-menú con PK obligatoria `mis_id = CONCAT(mis_permiso, '_', mis_menu)` (ej. `'1000_138'`).
   - `cab_permiaccion`: Flags CRUD con PK/FK `acc_id = mis_id = CONCAT(rol_id, '_', men_id)` (ej. `'1000_138'`) y `acc_permiso = rol_id`. **PROHIBIDO usar identificadores secuenciales arbitrarios o el `men_id` en `acc_id`**, pues `Menu_model::verificar_permiso()` evalúa mediante `INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id`. Si no coinciden, el módulo devuelve 403 Forbidden.
   - **Exclusividad para Rol 1000 en Migraciones**: Las migraciones SQL deben asignar permisos **ÚNICA Y EXCLUSIVAMENTE al rol Admin Master (`1000`)**. Está terminantemente prohibido asignar permisos a otros roles/grupos en los scripts de migración. El acceso para otros roles solo se otorga posteriormente desde la interfaz administrativa de permisos (`/permisos`).
10. **Autorización Dinámica sin IDs**: Prohibido escribir `men_id` o `per_id` como literales en controladores, modelos, vistas, hooks o bibliotecas. El rol se toma de la sesión (`usu_tipoper`) y el menú del módulo se resuelve por `men_url`/`men_urlamigable` contra el snapshot `menu_estructura` guardado al iniciar sesión. En cada llamada a `autorizar_accion()`/`tiene_permiso()` se pasa solo la acción; para controles cruzados se usa `tiene_permiso_url($url, $accion)`. Los roles maestros se detectan por cobertura total de menús (`Menu_model::rol_es_master()`), no por ID. Al crear un módulo nuevo, basta registrarlo en `cab_menu` con una URL única y asignar sus acciones; no se agregan constantes de ID.

---

## 4. Documentos Complementarios
- **`MEMORY.md`**: Memoria permanente, inventario de módulos, diccionario de BD y decisiones de arquitectura (ADRs).
- **`SKILL.md`**: Catálogo y procedimientos operativos de habilidades del proyecto.
- **`.agents/skills/`**: Runbooks ejecutables por habilidades individuales.
