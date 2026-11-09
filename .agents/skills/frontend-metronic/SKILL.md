---
name: frontend-metronic
description: >-
  Construye y revisa vistas PHP, JavaScript, DataTables y modales Metronic
  compatibles con temas claro y oscuro. Usar al modificar la interfaz de un
  módulo administrativo.
---

# Skill: Frontend Metronic

## Propósito

Construir interfaces consistentes con el sistema visual existente y seguras
para usuarios de escritorio y dispositivos móviles.

## Cuándo utilizarla

Aplicar al modificar vistas PHP, JavaScript, DataTables, formularios, modales,
componentes Metronic o estilos de un módulo.

## Procedimiento

1. Revisar la vista y JavaScript de un módulo existente equivalente.
2. Consultar únicamente `c:\xampp\htdocs\proyecto_base_metronic\` como
   referencia visual autorizada.
3. Implementar estados de carga, vacío, error y éxito.
4. Escapar valores dinámicos y evitar HTML generado con entrada sin validar.
5. Verificar modo claro, modo oscuro, responsive y navegación por teclado.

## Reglas

- Usar clases semánticas del tema y variables CSS.
- No usar colores rígidos que rompan el modo oscuro.
- Usar el estándar de acciones y tooltips definido abajo.
- Mantener DataTables server-side cuando el módulo lo requiera.
- No ocultar controles de seguridad únicamente en el frontend.

### Estándar obligatorio para tablas con Acciones/Actions

Todas las tablas deben usar exactamente el mismo patrón:

```html
<th class="min-w-120px text-end pe-2">Acciones</th>

<div class="d-flex justify-content-end align-items-center gap-2">
  <button type="button"
    class="btn btn-sm btn-icon btn-light-primary shadow-xs"
    data-bs-toggle="tooltip"
    data-bs-placement="top"
    title="Editar"
    aria-label="Editar">
    <i class="ki-duotone ki-pencil fs-4">
      <span class="path1"></span><span class="path2"></span>
    </i>
  </button>
</div>
```

Reglas específicas:

- No usar texto visible dentro de las acciones; usar tooltip.
- Icono siempre `fs-4`.
- Colores semánticos: primary editar/ver/reutilizar, danger eliminar/banear,
  warning desactivar/cerrar, success activar/asignar/usar.
- No usar `me-1`, `py-1 px-2`, `fs-8`, `fs-3` ni `fs-5` en acciones de tabla.
- DataTables: `className: 'text-end pe-2'`.
- Tooltips dinámicos: `application/views/partials/_scripts.php` los inicializa
  para filas generadas por AJAX/DataTables.
- Revisar todas las tablas del módulo antes de cerrar un CRUD.

Referencia permanente: `MEMORY.md` (ADR de estándar visual de acciones).

## Resultado

Informar vistas, scripts, componentes de referencia, estados implementados y
validaciones visuales realizadas.
