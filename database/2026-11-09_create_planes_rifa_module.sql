-- =====================================================
-- Migracion: M02 Planes de rifa y asignaciones
-- Modulo: Planes de Rifa
-- Fecha: 2026-11-09
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Crea las tablas rif_planes, rif_plan_asignaciones y
-- rif_plan_consumos, registra el menu dinamico y asigna los
-- permisos CRUD exclusivamente al rol Admin Master (1000).
--
-- La migracion es idempotente:
--   - CREATE TABLE IF NOT EXISTS
--   - Columnas faltantes agregadas solo si no existen
--   - Menu insertado solo si no existe por slug/URL
--   - Permisos insertados solo si no existen
--   - La version de permisos se invalida al final
--
-- NOTA: rif_plan_consumos.cons_rifa_id es una referencia LOGICA a
-- rif_rifas (se crea en M03). No se crea FK fisica por ahora.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Tabla principal de planes de rifa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_planes` (
  `plan_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre del plan de rifa',
  `plan_descripcion` VARCHAR(255) NULL COMMENT 'Descripcion opcional',
  `plan_tipo` ENUM('rifas','ilimitado') NOT NULL DEFAULT 'rifas' COMMENT 'Tipo de plan',
  `plan_limite` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cupo de rifas; 0 para ilimitado',
  `plan_periodo` ENUM('sin_limite','mensual','anual') NOT NULL DEFAULT 'sin_limite' COMMENT 'Periodo de vigencia del cupo',
  `plan_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio monetario del plan',
  `plan_moneda` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'Codigo ISO de moneda',
  `plan_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
  `plan_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = Visible, 1 = Borrado logico',
  `plan_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `plan_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `uk_plan_rifa_nombre` (`plan_nombre`),
  KEY `idx_plan_rifa_status` (`plan_status`),
  KEY `idx_plan_rifa_eliminado` (`plan_eliminado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Tabla de asignaciones de planes a organizadores
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_plan_asignaciones` (
  `asig_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asig_org_id` BIGINT UNSIGNED NOT NULL,
  `asig_plan_id` INT UNSIGNED NOT NULL,
  `asig_origen` ENUM('manual','compra','cortesia','admin') NOT NULL DEFAULT 'manual' COMMENT 'Origen de la asignacion',
  `asig_inicio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `asig_fin` DATETIME NULL COMMENT 'NULL = sin caducidad',
  `asig_rifas_usadas` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Rifas consumidas del cupo asignado',
  `asig_estado` ENUM('activa','vencida','reemplazada','cancelada') NOT NULL DEFAULT 'activa' COMMENT 'Estado de la asignacion',
  `asig_notas` VARCHAR(255) NULL COMMENT 'Notas internas de la asignacion',
  `asig_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `asig_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`asig_id`),
  KEY `idx_asig_org_estado` (`asig_org_id`, `asig_estado`),
  KEY `idx_asig_plan` (`asig_plan_id`),
  KEY `idx_asig_fin` (`asig_fin`),
  CONSTRAINT `fk_asig_org` FOREIGN KEY (`asig_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_asig_plan` FOREIGN KEY (`asig_plan_id`)
    REFERENCES `rif_planes` (`plan_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Tabla de consumos de cupo por rifa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_plan_consumos` (
  `cons_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cons_asig_id` BIGINT UNSIGNED NOT NULL,
  `cons_org_id` BIGINT UNSIGNED NOT NULL,
  `cons_rifa_id` BIGINT UNSIGNED NULL COMMENT 'Referencia logica a rif_rifas (se crea en M03); sin FK por ahora',
  `cons_estado` ENUM('consumido','devuelto') NOT NULL DEFAULT 'consumido' COMMENT 'Estado del consumo',
  `cons_motivo` VARCHAR(255) NULL COMMENT 'Motivo del consumo o devolucion',
  `cons_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cons_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cons_id`),
  UNIQUE KEY `uk_cons_asig_rifa` (`cons_asig_id`, `cons_rifa_id`),
  KEY `idx_cons_org_estado` (`cons_org_id`, `cons_estado`),
  CONSTRAINT `fk_cons_asig` FOREIGN KEY (`cons_asig_id`)
    REFERENCES `rif_plan_asignaciones` (`asig_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cons_org` FOREIGN KEY (`cons_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Asegurar columnas requeridas si la tabla ya existia
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_descripcion'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_descripcion` VARCHAR(255) NULL COMMENT ''Descripcion opcional'' AFTER `plan_nombre`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_tipo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_tipo` ENUM(''rifas'',''ilimitado'') NOT NULL DEFAULT ''rifas'' COMMENT ''Tipo de plan'' AFTER `plan_descripcion`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_limite'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_limite` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Cupo de rifas; 0 para ilimitado'' AFTER `plan_tipo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_periodo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_periodo` ENUM(''sin_limite'',''mensual'',''anual'') NOT NULL DEFAULT ''sin_limite'' COMMENT ''Periodo de vigencia del cupo'' AFTER `plan_limite`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_precio'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''Precio monetario del plan'' AFTER `plan_periodo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_moneda'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_moneda` CHAR(3) NOT NULL DEFAULT ''USD'' COMMENT ''Codigo ISO de moneda'' AFTER `plan_precio`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_status'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''1 = Activo, 0 = Inactivo'' AFTER `plan_moneda`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_eliminado'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 = Visible, 1 = Borrado logico'' AFTER `plan_status`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_created_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `plan_eliminado`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_updated_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_planes` ADD COLUMN `plan_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `plan_created_at`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 5. Registro del menu dinamico
-- -----------------------------------------------------
INSERT INTO `cab_menu` (
  `sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
  `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
  `men_externo`, `men_inicio`
)
SELECT
  0, 'Planes de Rifa', 'Planes, cupos y asignaciones de rifas',
  'PlanesRifa/index', 'planes-rifa', 13, 0, NULL, 'ki-duotone ki-crown',
  CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu`
  WHERE `men_urlamigable` = 'planes-rifa'
     OR TRIM(BOTH '/' FROM `men_url`) = 'PlanesRifa/index'
);

SET @plan_rifa_men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'planes-rifa'
     OR TRIM(BOTH '/' FROM `men_url`) = 'PlanesRifa/index'
  ORDER BY `men_id` ASC
  LIMIT 1
);

-- -----------------------------------------------------
-- 6. Permisos exclusivos para Admin Master (1000)
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
WHERE m.`men_id` = @plan_rifa_men_id
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
  AND p.`mis_menu` = @plan_rifa_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion` a WHERE a.`acc_id` = p.`mis_id`
  );

-- -----------------------------------------------------
-- 7. Invalidar el snapshot de menus de las sesiones activas
-- -----------------------------------------------------
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
