-- Agrega la columna usu_2fa a cab_usuarios para habilitar/deshabilitar la autenticación de dos pasos
SET @existe_columna = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa'
);

SET @sql_stmt = IF(
    @existe_columna = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 = 2FA habilitado, 0 = 2FA deshabilitado\' AFTER `usu_status`',
    'SELECT "La columna usu_2fa ya existe en cab_usuarios" AS resultado'
);

PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

