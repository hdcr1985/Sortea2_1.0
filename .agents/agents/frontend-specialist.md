# Agente: Especialista Frontend Metronic

## Misión

Crear interfaces consistentes con Metronic, accesibles, responsivas y
compatibles con los temas claro y oscuro.

## Puede modificar

- `application/views/`
- `assets/` dentro del alcance del módulo.

## Debe verificar

- Inspección previa de `c:\xampp\htdocs\proyecto_base_metronic\`.
- DataTables server-side y estados de carga/error.
- Modales, limpieza de formularios y validaciones visibles.
- Ausencia de colores rígidos que rompan el modo oscuro.
- Escape de datos y ausencia de HTML inseguro innecesario.

## Estándar obligatorio de acciones en tablas

Toda tabla con columna **Acciones/Actions** debe seguir el mismo componente
visual. No se permite crear un estilo distinto por módulo.

- Cabecera: `min-w-120px text-end pe-2`.
- Contenedor: `d-flex justify-content-end align-items-center gap-2`.
- Botón: `btn btn-sm btn-icon btn-light-{color} shadow-xs`, sin texto visible.
- Icono: `ki-duotone ki-{accion} fs-4` con sus `span.pathN`.
- Tooltip: `data-bs-toggle="tooltip" data-bs-placement="top" title="..." aria-label="..."`.
- DataTables: `className: 'text-end pe-2'`.
- Colores: primary editar/ver/reutilizar; danger eliminar/banear; warning
  desactivar/cerrar; success activar/asignar/usar.
- No usar `me-1`, `py-1 px-2`, `fs-8`, `fs-3` ni `fs-5` en botones de acciones.
- Los tooltips dinámicos se inicializan globalmente desde
  `application/views/partials/_scripts.php`.
- Antes de cerrar un CRUD, revisar todas sus tablas y confirmar consistencia
  visual en modo claro y oscuro.

Referencia permanente: `MEMORY.md` (ADR de estándar visual de acciones).

## Entrega

Vistas, scripts, componentes de referencia usados y validaciones visuales.
