-- =====================================================
-- Migracion: M04 Monedas y tasas de cambio
-- Modulo: Monedas
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Crear el catalogo de monedas (rif_monedas).
--   2. Crear el historial de tasas de cambio (rif_tasas_cambio).
--   3. Sembrar las monedas iniciales y la tasa base de USD.
--   4. Registrar el menu dinamico y los permisos CRUD exclusivos del
--      rol Admin Master (1000).
--
-- Dependencias:
--   - rif_tasas_cambio depende de rif_monedas mediante fk_tasa_moneda.
--   - cab_menu / cab_permisologia / cab_permiaccion para el RBAC.
--
-- Idempotencia:
--   - CREATE TABLE IF NOT EXISTS.
--   - Indices, UNIQUE y FK con guardas contra information_schema.
--   - Seeds con NOT EXISTS por codigo de moneda / moneda+fecha.
--   - Menu y permisos con NOT EXISTS.
--   - Reejecutar no duplica filas ni objetos de esquema.
--
-- NOTA DE DISENO:
--   El requerimiento pedia mon_codigo CHAR(3) (ISO 4217), pero el seed
--   incluye USDT (4 caracteres). Con STRICT_TRANS_TABLES activo el
--   INSERT fallaria. Se usa VARCHAR(10) para admitir ISO 4217 y tickers
--   cripto futuros (USDT, USDC, etc.).
--
-- NOTA: no se inventan tasas para monedas distintas de USD.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Catalogo de monedas
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_monedas` (
  `mon_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mon_codigo` VARCHAR(10) NOT NULL COMMENT 'ISO 4217 o ticker cripto, en mayusculas',
  `mon_nombre` VARCHAR(80) NOT NULL,
  `mon_simbolo` VARCHAR(10) NOT NULL DEFAULT '',
  `mon_decimales` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `mon_es_base` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `mon_orden` INT NOT NULL DEFAULT 1,
  `mon_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `mon_api_fuente` VARCHAR(50) NOT NULL DEFAULT 'manual' COMMENT 'manual|api futura',
  `mon_api_codigo` VARCHAR(30) NULL,
  `mon_api_ultima_sync` DATETIME NULL,
  `mon_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `mon_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mon_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mon_id`),
  UNIQUE KEY `uk_moneda_codigo` (`mon_codigo`),
  KEY `idx_moneda_status` (`mon_status`, `mon_eliminado`, `mon_orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Tasas de cambio historicas
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_tasas_cambio` (
  `tasa_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tasa_mon_id` INT UNSIGNED NOT NULL,
  `tasa_valor` DECIMAL(20,10) NOT NULL COMMENT 'unidades de la moneda por 1 USD',
  `tasa_fecha` DATE NOT NULL,
  `tasa_origen` ENUM('manual','api') NOT NULL DEFAULT 'manual',
  `tasa_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `tasa_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tasa_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tasa_id`),
  UNIQUE KEY `uk_tasa_moneda_fecha` (`tasa_mon_id`, `tasa_fecha`),
  KEY `idx_tasa_moneda_fecha` (`tasa_mon_id`, `tasa_fecha`, `tasa_status`),
  CONSTRAINT `fk_tasa_moneda` FOREIGN KEY (`tasa_mon_id`)
    REFERENCES `rif_monedas` (`mon_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Guardas de esquema: rif_monedas
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_monedas'
    AND INDEX_NAME = 'uk_moneda_codigo'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_monedas` ADD UNIQUE KEY `uk_moneda_codigo` (`mon_codigo`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_monedas'
    AND INDEX_NAME = 'idx_moneda_status'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_monedas` ADD KEY `idx_moneda_status` (`mon_status`, `mon_eliminado`, `mon_orden`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 4. Guardas de esquema: rif_tasas_cambio
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tasas_cambio'
    AND INDEX_NAME = 'uk_tasa_moneda_fecha'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_tasas_cambio` ADD UNIQUE KEY `uk_tasa_moneda_fecha` (`tasa_mon_id`, `tasa_fecha`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tasas_cambio'
    AND INDEX_NAME = 'idx_tasa_moneda_fecha'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_tasas_cambio` ADD KEY `idx_tasa_moneda_fecha` (`tasa_mon_id`, `tasa_fecha`, `tasa_status`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tasas_cambio'
    AND COLUMN_NAME = 'tasa_mon_id'
    AND REFERENCED_TABLE_NAME = 'rif_monedas'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_tasas_cambio` ADD CONSTRAINT `fk_tasa_moneda` FOREIGN KEY (`tasa_mon_id`) REFERENCES `rif_monedas` (`mon_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 5. Seeds de monedas (solo si no existe el codigo)
-- -----------------------------------------------------
INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'USD', 'Dólar estadounidense', '$', 2, 1, 1, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'USD');

INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'EUR', 'Euro', '€', 2, 0, 2, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'EUR');

INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'VES', 'Bolívar venezolano', 'Bs.', 2, 0, 3, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'VES');

INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'COP', 'Peso colombiano', '$', 2, 0, 4, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'COP');

INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'MXN', 'Peso mexicano', '$', 2, 0, 5, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'MXN');

INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'USDT', 'Tether', '₮', 2, 0, 6, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'USDT');

INSERT INTO `rif_monedas`
  (`mon_codigo`, `mon_nombre`, `mon_simbolo`, `mon_decimales`, `mon_es_base`, `mon_orden`, `mon_status`, `mon_api_fuente`)
SELECT 'GBP', 'Libra esterlina', '£', 2, 0, 7, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM `rif_monedas` WHERE `mon_codigo` = 'GBP');

-- -----------------------------------------------------
-- 6. Seed de la tasa base USD (solo si no existe para hoy)
-- -----------------------------------------------------
INSERT INTO `rif_tasas_cambio`
  (`tasa_mon_id`, `tasa_valor`, `tasa_fecha`, `tasa_origen`, `tasa_status`)
SELECT m.`mon_id`, 1.0000000000, CURDATE(), 'manual', 1
FROM `rif_monedas` m
WHERE m.`mon_codigo` = 'USD'
  AND NOT EXISTS (
    SELECT 1 FROM `rif_tasas_cambio` t
    WHERE t.`tasa_mon_id` = m.`mon_id`
      AND t.`tasa_fecha` = CURDATE()
  );

-- -----------------------------------------------------
-- 7. Registro del menu dinamico
-- -----------------------------------------------------
INSERT INTO `cab_menu` (
  `sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
  `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
  `men_externo`, `men_inicio`
)
SELECT
  0, 'Monedas', 'Monedas y tasas de cambio',
  'Monedas/index', 'monedas', 15, 0, NULL, 'ki-duotone ki-dollar',
  CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu` WHERE `men_urlamigable` = 'monedas'
);

SET @men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'monedas'
  ORDER BY `men_id` ASC
  LIMIT 1
);

-- -----------------------------------------------------
-- 8. Permisos exclusivos para Admin Master (1000)
-- -----------------------------------------------------
INSERT INTO `cab_permisologia` (
  `mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`
)
SELECT
  CONCAT('1000_', @men_id), 1000, @men_id, 0, 0, '1'
WHERE @men_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia`
    WHERE `mis_permiso` = 1000 AND `mis_menu` = @men_id
  );

INSERT INTO `cab_permiaccion` (
  `acc_id`, `acc_permiso`, `acc_incluir`, `acc_consultar`, `acc_modificar`,
  `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`
)
SELECT
  CONCAT('1000_', @men_id), 1000, 1, 1, 1, 1, 1, 0, 0
WHERE @men_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion`
    WHERE `acc_id` = CONCAT('1000_', @men_id)
  );

-- -----------------------------------------------------
-- 9. Invalidar el snapshot de menus de las sesiones activas
-- -----------------------------------------------------
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
