# Sistema Multi-Agente Portable

Esta carpeta contiene perfiles y habilidades que pueden ser utilizados por
cualquier IDE o modelo de inteligencia artificial.

## Cómo seleccionar un agente

- Solicitud nueva o compleja: `agents/orchestrator.md`.
- Diseño previo de módulo: `agents/module-architect.md`.
- SQL, tablas, índices o permisos: `agents/database-specialist.md`.
- PHP, modelos, controladores o endpoints: `agents/backend-specialist.md`.
- Vistas, JavaScript o Metronic: `agents/frontend-specialist.md`.
- Revisión de vulnerabilidades: `agents/security-specialist.md`.
- Pruebas y verificación final: `agents/qa-specialist.md`.

## Orden de trabajo

1. Consultar `AGENTS.md` y `MEMORY.md`.
2. Ejecutar arquitectura antes de implementar cambios grandes.
3. Implementar cada capa con su especialista.
4. Ejecutar seguridad.
5. Ejecutar QA.
6. Revisar el resultado completo y documentar el cierre.

La coordinación formal, la matriz agente-skill y el formato de handoff están en
`contracts/PROJECT_CONTRACTS.md`. En una instalación de opencode, los perfiles
ejecutables se encuentran en `../.opencode/agents/` y las skills se descubren
desde `.agents/skills/` mediante `opencode.json`.

Los alcances de edición declarados en los perfiles son instrucciones. Solo los
perfiles con permisos de OpenCode explícitos aplican una restricción de la
herramienta `edit`; esos permisos no impiden necesariamente escrituras mediante
otros medios como comandos de terminal.

Las skills son procedimientos, no agentes. Un agente puede utilizar varias
skills cuando el alcance lo requiera.

## Detección por herramienta

- **OpenCode**: `opencode.json` registra los contratos y la ubicación de las
  skills; `.opencode/agents/` contiene sus adaptadores de agentes.
- **Claude Code**: `CLAUDE.md` importa las instrucciones, memoria, catálogo y
  contratos de este proyecto.
- **Gemini CLI**: `GEMINI.md` importa esos mismos documentos.
- **GitHub Copilot**: `.github/copilot-instructions.md` indica qué documentos y
  skills consultar. La disponibilidad de instrucciones depende del cliente y
  de su configuración.
- **Herramientas compatibles con `AGENTS.md`**, como Codex: el archivo de la
  raíz identifica los contratos, perfiles y skills del proyecto.

Los adaptadores son de alcance local a este proyecto. Los perfiles y skills
detallados se consultan según la tarea; una referencia a un archivo no implica
necesariamente que la herramienta lo incorpore automáticamente al contexto.
Reinicia la sesión o recarga las instrucciones de cada herramienta y verifica
su panel de contexto. Las instrucciones no sustituyen permisos técnicos.

## Contrato de entrega

Todo agente debe informar:

- Resumen del trabajo.
- Archivos creados o modificados.
- Validaciones y pruebas ejecutadas.
- Problemas encontrados.
- Riesgos y pendientes.
