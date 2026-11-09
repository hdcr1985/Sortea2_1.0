# SKILL: Catálogo y Guía de Habilidades del Proyecto Base

Este catálogo es independiente del IDE. Las habilidades son procedimientos
que cualquier modelo puede seguir; no dependen de nombres de herramientas,
agentes o proveedores concretos.

Este documento reúne el catálogo de habilidades (**Skills**) del proyecto base, diseñadas para acelerar el desarrollo y garantizar la consistencia técnica entre agentes y desarrolladores.

---

## 1. Índice de Habilidades Disponibles

| Habilidad | Ubicación | Cuándo Utilizarla |
| :--- | :--- | :--- |
| **`create-crud-module`** | `.agents/skills/create-crud-module/SKILL.md` | Al crear un nuevo módulo administrativo completo (Controlador, Modelo, Vista Metronic, DataTables, Modales AJAX, Bitácora). |
| **`db-migration`** | `.agents/skills/db-migration/SKILL.md` | Al diseñar, crear o aplicar cambios en la base de datos MySQL (tablas `cab_`/`det_`, colaciones, índices, seeds en `cab_menu`). |
| **`security-audit`** | `.agents/skills/security-audit/SKILL.md` | Al verificar control de acceso por URL, permisos de rol, protección del rol `Admin Master` (1000), seguridad en subida de archivos y sesiones activas. |
| **`module-architecture`** | `.agents/skills/module-architecture/SKILL.md` | Antes de implementar un módulo o cambio que afecte varias capas. |
| **`backend-ci3`** | `.agents/skills/backend-ci3/SKILL.md` | Al crear o modificar modelos, controladores, endpoints AJAX y validaciones. |
| **`frontend-metronic`** | `.agents/skills/frontend-metronic/SKILL.md` | Al crear o modificar vistas, JavaScript, DataTables, modales y temas. |
| **`qa-module`** | `.agents/skills/qa-module/SKILL.md` | Al verificar cualquier cambio antes de considerarlo terminado. |

---

## 2. Guía Rápida de Habilidades

### Habilidad 1: Creación de Módulos CRUD (`create-crud-module`)
- **Paso Previo de Inspección**: Consultar preferentemente `c:\xampp\htdocs\proyecto_base_metronic\dist\html\demo10/` o cualquiera de los demás demos (`demo1` a `demo9`) para encontrar plantillas y componentes HTML afines (`store-client`, `account/billing`, `account/members`, etc.) y replicar su estructura visual.
- **Controlador**: Hereda de `MY_Controller`. Implementa `index()`, `tabla()`, `datos($id)`, `crear()`, `actualizar($id)`, `eliminar($id)`.
- **Rutas**: Se resuelven con `module_path()` sin rutas fijas en `routes.php`.
- **DataTables**: Contrato estricto `draw`, `recordsTotal`, `recordsFiltered`, `data`.
- **Bitácora**: Auditoría obligatoria con `$this->bitacora('ACCION', 'Detalle')`.

### Habilidad 2: Migraciones de Base de Datos (`db-migration`)
- **Archivos**: Guardar en `database/YYYY-MM-DD_<nombre>.sql`.
- **Estructura**: Tablas principales con prefijo `cab_` y columnas con prefijo corto (`<prefijo>_id`, `<prefijo>_status`, `<prefijo>_eliminado`).
- **Colación**: Preferir `utf8mb4` para tablas nuevas y verificar compatibilidad
  con las tablas existentes, que usan colaciones y motores mixtos.
- **Menú y Permisos**: Incluir inserciones automáticas en `cab_menu`, `cab_permisologia` y `cab_permiaccion` para rol `1000`.

### Habilidad 3: Auditoría y Seguridad (`security-audit`)
- **Validación de sesión y estatus**: `MY_Controller` destruye sesiones si `usu_status != 1`.
- **Protección de Superadmin**: Nadie que no sea `1000` puede tocar a usuarios `1000` ni asignarlo.
- **Autoprotección**: Un usuario activo no puede desactivarse ni borrarse a sí mismo.
- **Archivos seguros**: Subidas validadas por MIME real y servidas a través de `Media::usuario($id)`.

### Flujo mínimo de calidad

Todo cambio complejo debe pasar por `module-architecture`, la skill técnica de
la capa modificada, `security-audit` cuando exista autenticación o datos de
usuario, y `qa-module` antes del cierre. Los agentes y sus skills asociadas
están definidos en `.agents/contracts/PROJECT_CONTRACTS.md`.
