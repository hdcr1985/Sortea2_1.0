---
name: module-architecture
description: >-
  Define el alcance, contrato de datos, dependencias, permisos y criterios de
  aceptación antes de implementar cambios que afecten varias capas.
---

# Skill: Arquitectura de Módulos

## Propósito

Definir una solución coherente antes de implementar cambios que afecten varias
capas del proyecto.

## Cuándo utilizarla

Aplicar al crear módulos, modificar flujos entre frontend y backend, cambiar
tablas existentes o introducir integraciones nuevas.

## Procedimiento

1. Leer `AGENTS.md`, `MEMORY.md` y las skills aplicables.
2. Inspeccionar módulos y tablas similares existentes.
3. Definir alcance y criterios de aceptación.
4. Definir tablas, columnas, relaciones, endpoints, payloads y permisos.
5. Identificar archivos permitidos, archivos protegidos y dependencias.
6. Documentar riesgos de seguridad, datos y regresión.
7. Entregar el contrato al Coordinador antes de implementar.

## Reglas

- Reutilizar patrones existentes antes de crear abstracciones nuevas.
- No modificar el núcleo para resolver un problema específico de un módulo.
- Mantener separados estado funcional y borrado lógico.
- Tratar `usu_id` como cadena cuando sea una referencia de usuario.

## Resultado

El documento de arquitectura debe contener alcance, estructura de archivos,
contrato de datos, permisos, riesgos y criterios verificables de aceptación.
