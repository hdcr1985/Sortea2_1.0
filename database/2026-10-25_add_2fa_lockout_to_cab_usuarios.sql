-- Agrega columnas para protección contra ataques de fuerza bruta en autenticación 2FA
SET @existe_intentos = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa_intentos'
);

SET @sql_intentos = IF(
    @existe_intentos = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa_intentos` INT NOT NULL DEFAULT 0 COMMENT \'Intentos fallidos de 2FA\' AFTER `usu_2fa`',
    'SELECT "La columna usu_2fa_intentos ya existe" AS resultado'
);

PREPARE stmt1 FROM @sql_intentos;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @existe_bloqueo = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa_bloqueado_hasta'
);

SET @sql_bloqueo = IF(
    @existe_bloqueo = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa_bloqueado_hasta` DATETIME NULL DEFAULT NULL COMMENT \'Bloqueo temporal por fuerza bruta 2FA\' AFTER `usu_2fa_intentos`',
    'SELECT "La columna usu_2fa_bloqueado_hasta ya existe" AS resultado'
);

PREPARE stmt2 FROM @sql_bloqueo;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

