-- Migración: Modificar columna dgu_usuario_id a VARCHAR(50) para soportar IDs tipo "000001"
-- Fecha: 2026-10-25

ALTER TABLE `det_correo_grupos_usuarios` MODIFY COLUMN `dgu_usuario_id` VARCHAR(50) NOT NULL;

