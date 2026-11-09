---
name: backend-ci3
description: >-
  Implementa y revisa modelos, controladores y endpoints AJAX seguros en
  CodeIgniter 3. Usar al modificar backend, validaciones, DataTables o reglas
  de negocio.
---

# Skill: Backend CodeIgniter 3

## Propósito

Implementar backend consistente, validado y auditable en CodeIgniter 3.

## Cuándo utilizarla

Aplicar al modificar modelos, controladores, servicios, endpoints AJAX o reglas
de negocio.

## Reglas obligatorias

- Los controladores protegidos extienden `MY_Controller`.
- Las consultas y conteos permanecen en el modelo.
- Validar y normalizar toda entrada del usuario.
- Usar listas blancas para columnas y direcciones de ordenamiento.
- Respetar respuestas JSON y códigos HTTP documentados.
- Registrar toda mutación exitosa en `cab_bitacora`.
- No exponer trazas, SQL, rutas físicas ni secretos en respuestas.
- `MY_Controller` comprueba el rol para URLs registradas en menú; las URLs no
  registradas pasan sin esa comprobación. Comprobar explícitamente las acciones
  CRUD mediante `tiene_permiso()` y revisar rutas directas y métodos HTTP.

## Verificación

Revisar autenticación, autorización, protección CSRF efectiva, IDOR, SQL
injection, XSS almacenado, duplicados, IDs inexistentes, errores de base de
datos y concurrencia básica. Reportar como riesgo la ausencia de protección CSRF;
no asumir que basta con que una petición sea AJAX.

## Resultado

Informar archivos modificados, endpoints, reglas de negocio, consultas,
validaciones, pruebas y riesgos pendientes.
