---
name: qa-module
description: >-
  Verifica cambios de módulos CodeIgniter mediante pruebas estáticas, funcionales
  y de regresión. Usar antes de aprobar cualquier cambio funcional.
---

# Skill: QA de Módulos

## Propósito

Verificar funcionalidad, seguridad básica y ausencia de regresiones antes de
aprobar un cambio.

## Cuándo utilizarla

Aplicar después de cualquier cambio funcional y obligatoriamente antes de
cerrar un módulo nuevo.

## Checklist

1. Revisar sintaxis PHP de archivos modificados.
2. Revisar sintaxis y coherencia de migraciones SQL.
3. Ejecutar pruebas automatizadas disponibles; si no existe `tests/` o PHPUnit,
   registrar la limitación y ejecutar las verificaciones manuales aplicables.
4. Probar autenticación, permisos permitidos y denegados.
5. Probar respuestas 200, 403, 404, 422 y 500 cuando correspondan.
6. Probar campos vacíos, duplicados, IDs inválidos y paginación extrema.
7. Verificar DataTables: `draw`, `recordsTotal`, `recordsFiltered` y `data`.
8. Verificar bitácora después de cada mutación.
9. Revisar logs y errores visibles al usuario.
10. Revisar modo claro, oscuro, responsive y navegación por teclado en cambios
    de interfaz.

## Verificaciones disponibles en este proyecto

- Ejecutar `php -l` sobre cada archivo PHP modificado.
- Revisar migraciones con una conexión MySQL/MariaDB disponible; no asumir que
  una migración fue aplicada si no se puede consultar el esquema.
- Usar el navegador o peticiones HTTP autenticadas para endpoints AJAX cuando
  el entorno XAMPP esté disponible.
- Si no existe un runner automatizado, reportar cada prueba manual con pasos,
  respuesta observada y evidencia disponible.

## Reglas

- Diferenciar fallos del entorno de fallos del código.
- No marcar como aprobada una prueba que no se pudo ejecutar.
- Reproducir y documentar cada defecto.

## Resultado

Entregar una lista con prueba, resultado, evidencia, limitación y veredicto:
aprobado, aprobado con riesgos o bloqueado.
