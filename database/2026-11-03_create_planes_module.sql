-- =====================================================
-- Migracion: Modulo Planes para proyecto_base
-- =====================================================
-- Crea la tabla cab_planes, registra el menu dinamico y asigna
-- los permisos CRUD exclusivamente al rol Admin Master (1000).
--
-- La migracion es idempotente:
--   - CREATE TABLE IF NOT EXISTS
--   - Columnas faltantes agregadas solo si no existen
--   - Menu insertado solo si no existe por slug/URL
--   - Permisos insertados solo si no existen
-- =====================================================

-- -----------------------------------------------------
-- 1. Tabla principal de planes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cab_planes` (
  `pla_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pla_nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre del plan',
  `pla_descripcion` VARCHAR(255) NULL COMMENT 'Descripcion opcional',
  `pla_numero` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Creditos asignados al usuario tras la compra',
  `pla_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio monetario del plan',
  `pla_moneda` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT 'Codigo de moneda (ej: USD, EUR)',
  `pla_paypal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = Habilitado para pagos Stripe/PayPal',
  `pla_crypto` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = Habilitado para pagos cripto',
  `pla_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
  `pla_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = Visible, 1 = Borrado logico',
  `pla_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pla_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pla_id`),
  UNIQUE KEY `uk_plan_nombre` (`pla_nombre`),
  KEY `idx_plan_status` (`pla_status`),
  KEY `idx_plan_eliminado` (`pla_eliminado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Asegurar columnas requeridas si la tabla ya existia
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_descripcion'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_descripcion` VARCHAR(255) NULL COMMENT ''Descripcion opcional'' AFTER `pla_nombre`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_numero'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_numero` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Creditos asignados'' AFTER `pla_descripcion`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_precio'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''Precio monetario'' AFTER `pla_numero`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_moneda'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_moneda` VARCHAR(10) NOT NULL DEFAULT ''USD'' COMMENT ''Codigo de moneda'' AFTER `pla_precio`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_paypal'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_paypal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = Stripe/PayPal'' AFTER `pla_moneda`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_crypto'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_crypto` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''1 = Cripto'' AFTER `pla_paypal`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_status'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''1 = Activo'' AFTER `pla_crypto`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_eliminado'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 = Visible'' AFTER `pla_status`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_created_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `pla_eliminado`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_updated_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `pla_created_at`',
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
  0, 'Planes', 'Gestion de planes de suscripcion y creditos',
  'Plan/index', 'planes', 11, 0, NULL, 'ki-duotone ki-element-11',
  CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu`
  WHERE `men_urlamigable` = 'planes'
     OR TRIM(BOTH '/' FROM `men_url`) = 'Plan/index'
);

SET @plan_men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'planes'
     OR TRIM(BOTH '/' FROM `men_url`) = 'Plan/index'
  ORDER BY `men_id` ASC
  LIMIT 1
);

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
WHERE m.`men_id` = @plan_men_id
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
  AND p.`mis_menu` = @plan_men_id
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
