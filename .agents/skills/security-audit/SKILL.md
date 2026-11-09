---
name: security-audit
description: >-
  Procedimiento portable para auditar RBAC, protección de URLs, sesiones,
  subidas de archivos y trazabilidad en proyecto_base.
---

# Skill: Auditoría de Seguridad y Permisos

## Uso

Aplicar cuando se cree, modifique o revise código que controle acceso, usuarios,
sesiones, archivos, permisos o cambios persistidos. Inspeccionar primero el
código existente y adaptar las validaciones al entorno disponible.

Procedimiento para asegurar la integridad, control de acceso y trazabilidad en `proyecto_base`.

## 1. Puntos de Control Críticos

### A. Control de Acceso por URL y Menú
- Todo controlador administrativo debe extender `MY_Controller`.
- `MY_Controller` intercepta la petición y valida la sesión activa (`$this->session->userdata('usuario')`).
- Verifica si el usuario sigue activo en base de datos (`usu_status == 1`). Si fue desactivado, destruye la sesión y redirige inmediatamente.
- `MY_Controller` comprueba la asignación del rol al menú en
  `cab_permisologia`, pero no comprueba automáticamente las acciones CRUD en
  `cab_permiaccion`. Revisar expresamente cada endpoint y su acción.
- Actualmente, si la URL base no existe en `cab_menu`, el controlador permite
  continuar; revisar también las rutas directas por nombre de controlador y no
  asumir que están protegidas por el menú.
- Si la URL existe en menú y el rol no tiene permiso: deniega acceso (HTTP 403 o redirección al dashboard).

### B. Protección de Roles Especiales (Admin Master: ID 1000)
- El rol `1000` (`Admin Master`) tiene privilegios totales en el sistema.
- **Regla inviolable**: Ningún usuario cuyo `usu_tipoper != 1000` puede:
  1. Asignar el rol `1000` a otros usuarios.
  2. Modificar datos de un usuario con rol `1000`.
  3. Desactivar o eliminar usuarios con rol `1000`.
- Esta validación debe aplicarse tanto en backend (`Usuarios.php` / `Permisos.php`) como ocultarse en las opciones del frontend para usuarios sin privilegios.

### C. Autoprotección del Usuario en Sesión
- Un usuario logueado nunca debe poder:
  1. Desactivarse a sí mismo (`usu_status = 0`).
  2. Eliminarse a sí mismo. La tabla actual `cab_usuarios` no tiene
     `usu_eliminado`; `Usuarios_model::eliminar()` ejecuta un borrado físico.
  3. Cambiarse su propio rol para degradar su acceso accidentalmente.

### D. Trazabilidad en Bitácora (`cab_bitacora`)
- Toda mutación de estado relevante (creación, actualización, eliminación, login, bloqueo) debe invocar:
  ```php
  $this->bitacora('ACCION_NOMBRE', 'Detalle descriptivo de la operación (ID, campos cambiados)');
  ```
- Nombres de acción en mayúsculas con formato `<MODULO>_<VERBO>` (ej. `USUARIO_CREAR`, `CONFIG_TEMA_EDITAR`).

### E. Manejo Seguro de Archivos y Subidas
- No exponer rutas físicas del servidor (ej. `C:/xampp/...` o carpetas directas con permisos de ejecución PHP).
- Las subidas deben validarse estrictamente por MIME real (`finfo` / `$this->upload`) y extensión permitida.
- Los archivos privados deben servirse a través de endpoints controlados como `Media::usuario($id)` con encabezados `X-Content-Type-Options: nosniff`.

## Entrega obligatoria

Informar archivos revisados, controles comprobados, hallazgos por severidad,
correcciones aplicadas o propuestas y riesgos que permanecen.
