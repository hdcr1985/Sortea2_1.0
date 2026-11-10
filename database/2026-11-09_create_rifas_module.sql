-- =====================================================
-- Migracion: M03 Rifas: CRUD, numeracion y estados
-- Modulo: Rifas
-- Fecha: 2026-11-09
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Crea la tabla rif_rifas, registra el menu dinamico y asigna los
-- permisos CRUD exclusivamente al rol Admin Master (1000).
--
-- Dependencias:
--   - rif_organizadores (M01) para la FK fk_rifa_org.
--
-- La migracion es idempotente:
--   - CREATE TABLE IF NOT EXISTS
--   - Columnas faltantes agregadas solo si no existen
--   - Menu insertado solo si no existe por slug/URL
--   - Permisos insertados solo si no existen
--   - La version de permisos se invalida al final
--
-- NOTA: rif_plan_consumos.cons_rifa_id sigue siendo una referencia LOGICA
-- a rif_rifas. La FK de esa relacion no se agrega en esta migracion.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Tabla principal de rifas
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_rifas` (
  `rifa_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rifa_org_id` BIGINT UNSIGNED NOT NULL,
  `rifa_slug` VARCHAR(120) NOT NULL,
  `rifa_titulo` VARCHAR(150) NOT NULL,
  `rifa_descripcion` TEXT NULL,
  `rifa_condiciones` TEXT NULL,
  `rifa_tipo` ENUM('paga','gratuita') NOT NULL DEFAULT 'paga',
  `rifa_precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rifa_moneda` CHAR(3) NOT NULL DEFAULT 'USD',
  `rifa_numeracion_tipo` ENUM('2','3','4','custom') NOT NULL DEFAULT '2',
  `rifa_numero_desde` INT UNSIGNED NOT NULL DEFAULT 0,
  `rifa_numero_hasta` INT UNSIGNED NOT NULL DEFAULT 99,
  `rifa_padding` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `rifa_total_numeros` INT UNSIGNED NOT NULL DEFAULT 100,
  `rifa_max_por_compra` INT UNSIGNED NOT NULL DEFAULT 10,
  `rifa_reserva_ttl_min` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT '0 = sin expiración automática',
  `rifa_reserva_auto` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `rifa_fecha_sorteo` DATETIME NULL,
  `rifa_imagen` VARCHAR(255) NULL COMMENT 'Nombre de archivo en upload/rifas/{org_id}/',
  `rifa_estado` ENUM('borrador','pendiente_aprobacion','activa','pausada','cancelacion_solicitada','finalizada','cancelada','rechazada') NOT NULL DEFAULT 'borrador',
  `rifa_estado_previo` VARCHAR(20) NULL COMMENT 'Estado antes de solicitar cancelación',
  `rifa_publicada_at` DATETIME NULL,
  `rifa_finalizada_at` DATETIME NULL,
  `rifa_cancelada_at` DATETIME NULL,
  `rifa_cancelacion_motivo` VARCHAR(255) NULL,
  `rifa_cancelacion_solicitada_at` DATETIME NULL,
  `rifa_aprobacion_motivo` VARCHAR(255) NULL COMMENT 'Motivo de solicitud/rechazo de rifa gratuita',
  `rifa_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rifa_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `rifa_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`rifa_id`),
  UNIQUE KEY `uk_rifa_org_slug` (`rifa_org_id`, `rifa_slug`),
  KEY `idx_rifa_org_estado` (`rifa_org_id`, `rifa_estado`),
  KEY `idx_rifa_estado` (`rifa_estado`),
  KEY `idx_rifa_eliminado` (`rifa_eliminado`),
  KEY `idx_rifa_fecha_sorteo` (`rifa_fecha_sorteo`),
  CONSTRAINT `fk_rifa_org` FOREIGN KEY (`rifa_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Asegurar columnas requeridas si la tabla ya existia
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_org_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_org_id` BIGINT UNSIGNED NOT NULL AFTER `rifa_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_slug'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_slug` VARCHAR(120) NOT NULL AFTER `rifa_org_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_titulo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_titulo` VARCHAR(150) NOT NULL AFTER `rifa_slug`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_descripcion'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_descripcion` TEXT NULL AFTER `rifa_titulo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_condiciones'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_condiciones` TEXT NULL AFTER `rifa_descripcion`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_tipo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_tipo` ENUM(''paga'',''gratuita'') NOT NULL DEFAULT ''paga'' AFTER `rifa_condiciones`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_precio'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `rifa_tipo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_moneda'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_moneda` CHAR(3) NOT NULL DEFAULT ''USD'' AFTER `rifa_precio`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_numeracion_tipo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_numeracion_tipo` ENUM(''2'',''3'',''4'',''custom'') NOT NULL DEFAULT ''2'' AFTER `rifa_moneda`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_numero_desde'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_numero_desde` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `rifa_numeracion_tipo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_numero_hasta'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_numero_hasta` INT UNSIGNED NOT NULL DEFAULT 99 AFTER `rifa_numero_desde`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_padding'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_padding` TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER `rifa_numero_hasta`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_total_numeros'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_total_numeros` INT UNSIGNED NOT NULL DEFAULT 100 AFTER `rifa_padding`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_max_por_compra'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_max_por_compra` INT UNSIGNED NOT NULL DEFAULT 10 AFTER `rifa_total_numeros`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_reserva_ttl_min'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_reserva_ttl_min` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT ''0 = sin expiración automática'' AFTER `rifa_max_por_compra`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_reserva_auto'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_reserva_auto` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `rifa_reserva_ttl_min`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_fecha_sorteo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_fecha_sorteo` DATETIME NULL AFTER `rifa_reserva_auto`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_imagen'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_imagen` VARCHAR(255) NULL COMMENT ''Nombre de archivo en upload/rifas/{org_id}/'' AFTER `rifa_fecha_sorteo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_estado'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_estado` ENUM(''borrador'',''pendiente_aprobacion'',''activa'',''pausada'',''cancelacion_solicitada'',''finalizada'',''cancelada'',''rechazada'') NOT NULL DEFAULT ''borrador'' AFTER `rifa_imagen`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_estado_previo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_estado_previo` VARCHAR(20) NULL COMMENT ''Estado antes de solicitar cancelación'' AFTER `rifa_estado`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_publicada_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_publicada_at` DATETIME NULL AFTER `rifa_estado_previo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_finalizada_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_finalizada_at` DATETIME NULL AFTER `rifa_publicada_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_cancelada_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_cancelada_at` DATETIME NULL AFTER `rifa_finalizada_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_cancelacion_motivo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_cancelacion_motivo` VARCHAR(255) NULL AFTER `rifa_cancelada_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_cancelacion_solicitada_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_cancelacion_solicitada_at` DATETIME NULL AFTER `rifa_cancelacion_motivo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_aprobacion_motivo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_aprobacion_motivo` VARCHAR(255) NULL COMMENT ''Motivo de solicitud/rechazo de rifa gratuita'' AFTER `rifa_cancelacion_solicitada_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_created_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `rifa_aprobacion_motivo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_updated_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `rifa_created_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_eliminado'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `rifa_updated_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. Registro del menu dinamico
-- -----------------------------------------------------
INSERT INTO `cab_menu` (
  `sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
  `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
  `men_externo`, `men_inicio`
)
SELECT
  0, 'Rifas', 'Gestión de rifas de los organizadores',
  'Rifas/index', 'rifas', 14, 0, NULL, 'ki-duotone ki-gift',
  CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu`
  WHERE `men_urlamigable` = 'rifas'
     OR TRIM(BOTH '/' FROM `men_url`) = 'Rifas/index'
);

SET @rifa_men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'rifas'
     OR TRIM(BOTH '/' FROM `men_url`) = 'Rifas/index'
  ORDER BY `men_id` ASC
  LIMIT 1
);

-- Sincronizar la metadata visible del menu en instalaciones existentes.
-- Se usa comparacion BINARY porque las colaciones *_ci consideran iguales
-- cadenas que solo difieren por acentos (ej: "Gestion" = "Gestión").
UPDATE `cab_menu`
SET `men_descripcion` = 'Gestión de rifas de los organizadores',
    `men_imgmenu` = 'ki-duotone ki-gift'
WHERE `men_id` = @rifa_men_id
  AND (`men_descripcion` IS NULL
       OR BINARY `men_descripcion` <> BINARY 'Gestión de rifas de los organizadores'
       OR `men_imgmenu` IS NULL
       OR `men_imgmenu` <> 'ki-duotone ki-gift');

-- -----------------------------------------------------
-- 4. Permisos exclusivos para Admin Master (1000)
-- -----------------------------------------------------
INSERT INTO `cab_permisologia` (
  `mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`
)
SELECT
  CONCAT('1000_', m.`men_id`),
  1000,
  m.`men_id`,
  m.`men_tipo`,
  COALESCE((SELECT g.`per_tipo` FROM `cab_grupopermi` g WHERE g.`per_id` = 1000 LIMIT 1), 0),
  '1'
FROM `cab_menu` m
WHERE m.`men_id` = @rifa_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia` p
    WHERE p.`mis_permiso` = 1000 AND p.`mis_menu` = m.`men_id`
  );

INSERT INTO `cab_permiaccion` (
  `acc_id`, `acc_permiso`, `acc_consultar`, `acc_incluir`, `acc_modificar`,
  `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`
)
SELECT
  p.`mis_id`, 1000, 1, 1, 1, 1, 1, 0, 0
FROM `cab_permisologia` p
WHERE p.`mis_permiso` = 1000
  AND p.`mis_menu` = @rifa_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion` a WHERE a.`acc_id` = p.`mis_id`
  );

-- -----------------------------------------------------
-- 5. Invalidar el snapshot de menus de las sesiones activas
-- -----------------------------------------------------
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
