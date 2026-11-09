-- =====================================================
-- Migration: optional authenticator-app (TOTP) 2FA
-- Date: 2026-11-07
-- Portable idempotent DDL for MariaDB and MySQL 8+
-- =====================================================

SET @schema_name := DATABASE();

SET @has_totp_enabled := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa_totp_enabled'
);
SET @sql := IF(@has_totp_enabled = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa_totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `usu_2fa`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_totp_secret := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa_totp_secret'
);
SET @sql := IF(@has_totp_secret = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa_totp_secret` VARCHAR(255) NULL AFTER `usu_2fa_totp_enabled`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_totp_confirmed := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa_totp_confirmed_at'
);
SET @sql := IF(@has_totp_confirmed = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa_totp_confirmed_at` DATETIME NULL AFTER `usu_2fa_totp_secret`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_totp_last_step := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cab_usuarios'
      AND COLUMN_NAME = 'usu_2fa_totp_last_step'
);
SET @sql := IF(@has_totp_last_step = 0,
    'ALTER TABLE `cab_usuarios` ADD COLUMN `usu_2fa_totp_last_step` BIGINT UNSIGNED NULL AFTER `usu_2fa_totp_confirmed_at`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `cab_usuario_2fa_recovery` (
    `rec_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rec_usuario_id` VARCHAR(100) NOT NULL,
    `rec_code_hash` CHAR(64) NOT NULL,
    `rec_used_at` DATETIME NULL,
    `rec_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`rec_id`),
    UNIQUE KEY `uk_usuario_2fa_recovery` (`rec_usuario_id`, `rec_code_hash`),
    KEY `idx_usuario_2fa_recovery_user` (`rec_usuario_id`, `rec_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_usuarios'
  AND COLUMN_NAME IN (
      'usu_2fa_totp_enabled', 'usu_2fa_totp_secret',
      'usu_2fa_totp_confirmed_at', 'usu_2fa_totp_last_step'
  );
