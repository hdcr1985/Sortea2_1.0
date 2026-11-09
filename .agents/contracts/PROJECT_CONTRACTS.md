# Contratos Compartidos del Proyecto

Este documento es la fuente de verdad para la coordinación entre agentes. Si
otro documento contradice este contrato, el Coordinador debe resolver la
contradicción antes de implementar.

## 1. Matriz De Agentes

| Agente | Skill principal | Entregable | Puede modificar |
| --- | --- | --- | --- |
| `orchestrator` | Todas según alcance | Plan, aceptación de fases y cierre | Cualquier archivo autorizado por el alcance; `MEMORY.md` solo en el cierre |
| `module-architect` | `module-architecture` | Contrato de arquitectura aceptable | Documentación de arquitectura solo si se solicita |
| `database-specialist` | `db-migration` | Migración SQL y validación de esquema | `database/*.sql` |
| `backend-specialist` | `backend-ci3` | Modelo, controlador y contrato HTTP | `application/controllers/`, `application/models/`, `application/libraries/` autorizado |
| `frontend-specialist` | `frontend-metronic` | Vista, JavaScript y validación visual | `application/views/`, `assets/` dentro del alcance |
| `security-specialist` | `security-audit` | Hallazgos por severidad y decisión de seguridad | Revisión por defecto; no corrige silenciosamente |
| `qa-specialist` | `qa-module` | Matriz de pruebas y veredicto | Revisión por defecto; no modifica código salvo autorización |

Las skills complementarias se cargan además de la skill principal cuando el
alcance lo requiera. Un agente no debe asumir responsabilidades de otra fase.

## 2. Ciclo De Fases

1. `ARCHITECTURE`: define alcance, archivos, datos, permisos, riesgos y
   criterios de aceptación.
2. `DATABASE` y `BACKEND`: pueden ejecutarse en paralelo después de aceptar
   `ARCHITECTURE`.
3. `FRONTEND`: inicia con el contrato de arquitectura y los endpoints
   aprobados.
4. `SECURITY`: revisa la implementación completa antes de QA.
5. `QA`: ejecuta pruebas estáticas, funcionales y de regresión después de
   `SECURITY`.
6. `CLOSE`: el Coordinador revisa el diff completo y declara `READY` solo si
   Seguridad y QA no tienen bloqueos.

Una fase puede terminar con `ACCEPTED`, `ACCEPTED_WITH_RISKS`, `REWORK` o
`BLOCKED`. `ACCEPTED_WITH_RISKS` requiere que el riesgo y su responsable queden
documentados; no puede usarse para ignorar un bloqueo crítico.

## 3. Contrato De Handoff

Cada entrega debe incluir estos campos, aunque se comuniquen en Markdown:

```text
phase: ARCHITECTURE | DATABASE | BACKEND | FRONTEND | SECURITY | QA | CLOSE
status: ACCEPTED | ACCEPTED_WITH_RISKS | REWORK | BLOCKED
summary: descripción breve
files_created: lista de rutas
files_modified: lista de rutas
contract_changes: endpoints, tablas, payloads o permisos modificados
validation: pruebas ejecutadas y resultado
evidence: comandos, archivos, respuestas o capturas disponibles
risks: riesgos residuales y severidad
pending: trabajo pendiente o pasos manuales
handoff_to: siguiente agente o Coordinador
```

Una prueba no ejecutada debe aparecer como `NOT_RUN` con su limitación. Nunca
se reporta como aprobada.

## 4. Fuente De Verdad De Permisos

El modelo RBAC usa estas tablas existentes:

- `cab_grupopermi`: roles; el rol `1000` es Admin Master.
- `cab_menu`: módulos y URLs dinámicas.
- `cab_permisologia`: asignación de un menú a un rol mediante `mis_permiso` y
  `mis_menu`.
- `cab_permiaccion`: acciones CRUD de una asignación mediante `acc_id` y las
  columnas `acc_consultar`, `acc_incluir`, `acc_modificar`, `acc_eliminar`,
  `acc_imprimir`, `acc_extra1` y `acc_extra2`.

`tiene_permiso()` consulta acciones y los endpoints deben invocarlo
expresamente o usar `autorizar_accion()`. Verificar las rutas amigables y
directas antes de considerar protegido un módulo.

Desde el 2026-10-28 `MY_Controller` aplica deny-by-default por controlador
registrado en `cab_menu` y valida el menú activo del rol. El helper recomendado
en cada endpoint de mutación es:

```php
$this->autorizar_accion($men_id, 'incluir|consultar|modificar|eliminar', 'Mensaje');
```

`autorizar_accion()` responde 403 JSON en AJAX y redirige a `sin-permisos` en
navegación normal. El rol `1000` conserva privilegios globales. Las rutas
`media`, `errors`, `perfil`, `dashboard`, `logout`, `ping-sesion`,
`sin-permisos` y `mantenimiento` son libres; los métodos globales se declaran
en `$metodos_libres`. El registro público usa el rol `1004` (solo dashboard).

No existe una tabla contractual llamada `cab_permisos`. Las migraciones deben
ser idempotentes usando `NOT EXISTS` y deben crear la asignación en
`cab_permisologia` antes de crear sus acciones en `cab_permiaccion`.
`NOT EXISTS` por sí solo no evita carreras entre ejecuciones concurrentes;
validar unicidad y estrategia de despliegue según el esquema real.

La tabla `cab_menu` usa `sub_men_id`, `men_tipo`, `men_img`, `men_imgmenu` y
`men_inicio`. No usar `men_padre`, `men_icono` ni `men_status` sin verificar una
migración nueva que los agregue.

## 5. Contratos HTTP Y Auditoría

- DataTables siempre responde `draw`, `recordsTotal`, `recordsFiltered` y
  `data`; `recordsTotal` es el total sin filtro.
- `datos($id)` devuelve `200` con `{ok: true, data: ...}` o `404` con
  `{ok: false, msg: ...}`.
- Validaciones devuelven `422` con `{ok: false, errores: ...}`.
- Denegación devuelve `403`; errores inesperados devuelven `500` sin trazas,
  SQL, rutas físicas ni secretos.
- Toda mutación exitosa registra `cab_bitacora` con `<MODULO>_<ACCION>` en
  mayúsculas. Para módulos nuevos se recomienda `ACTUALIZAR`, porque es la
  convención predominante en el código existente; se conservan acciones
  históricas como `EDITAR` cuando ya forman parte del contrato del módulo.
  Ejemplos: `CLIENTE_CREAR`, `CLIENTE_ACTUALIZAR` y `CLIENTE_ELIMINAR`.

## 6. Propiedad De Documentación

Los especialistas entregan el resumen de cambios. Solo el Coordinador actualiza
`MEMORY.md`, después de aceptar la fase de cierre y confirmar la decisión.
