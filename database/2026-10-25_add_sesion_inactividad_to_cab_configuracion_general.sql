-- ========================================================================
-- Migración: Agregar parámetro sesion_inactividad_minutos a cab_configuracion_general
-- Fecha: 2026-10-25
-- Descripción: Permite configurar el tiempo de inactividad antes de expirar sesión.
-- ========================================================================

INSERT INTO `cab_configuracion_general` (`cfg_clave`, `cfg_valor`, `cfg_tipo`)
VALUES ('sesion_inactividad_minutos', '120', 'int')
ON DUPLICATE KEY UPDATE `cfg_valor` = VALUES(`cfg_valor`);
