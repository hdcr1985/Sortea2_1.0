-- =====================================================
-- Migracion: M02.1 Numeraciones, opciones por plan y compras por cantidad
-- Modulo: Planes de Rifa / Numeraciones
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Crear el catalogo global rif_numeraciones (100 / 1.000 / 10.000).
--   2. Crear rif_plan_numeraciones: numeraciones disponibles por plan,
--      con su precio por rifa.
--   3. Extender rif_plan_asignaciones con la numeracion elegida o comprada,
--      la cantidad y los importes del lote.
--   4. Extender rif_rifas con la asignacion/lote y la numeracion usadas al
--      publicar. La numeracion propia existente se conserva como snapshot.
--
-- Dependencias:
--   - rif_planes, rif_plan_asignaciones (M02)
--   - rif_rifas (M03)
--
-- La migracion es idempotente:
--   - CREATE TABLE IF NOT EXISTS
--   - Columnas, indices y FKs agregados solo si no existen
--     (checks contra information_schema)
--   - Semillas insertadas solo si no existe el rango
--   - No elimina ni renombra columnas, filas ni datos existentes
--   - No registra menus ni permisos nuevos
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Catalogo global de numeraciones
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_numeraciones` (
  `num_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `num_label` VARCHAR(60) NOT NULL COMMENT 'Ej: 100 números, 1.000 números',
  `num_cantidad` INT UNSIGNED NOT NULL COMMENT 'Cantidad total de números',
  `num_desde` INT UNSIGNED NOT NULL,
  `num_hasta` INT UNSIGNED NOT NULL,
  `num_padding` TINYINT UNSIGNED NOT NULL,
  `num_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `num_orden` INT UNSIGNED NOT NULL DEFAULT 0,
  `num_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `num_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`num_id`),
  UNIQUE KEY `uk_num_rango` (`num_desde`, `num_hasta`, `num_padding`),
  KEY `idx_num_status` (`num_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Semilla idempotente del catalogo de numeraciones
-- -----------------------------------------------------
INSERT INTO `rif_numeraciones`
  (`num_label`, `num_cantidad`, `num_desde`, `num_hasta`, `num_padding`, `num_status`, `num_orden`)
SELECT '100 números', 100, 0, 99, 2, 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_numeraciones`
  WHERE `num_desde` = 0 AND `num_hasta` = 99 AND `num_padding` = 2
);

INSERT INTO `rif_numeraciones`
  (`num_label`, `num_cantidad`, `num_desde`, `num_hasta`, `num_padding`, `num_status`, `num_orden`)
SELECT '1.000 números', 1000, 0, 999, 3, 1, 2
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_numeraciones`
  WHERE `num_desde` = 0 AND `num_hasta` = 999 AND `num_padding` = 3
);

INSERT INTO `rif_numeraciones`
  (`num_label`, `num_cantidad`, `num_desde`, `num_hasta`, `num_padding`, `num_status`, `num_orden`)
SELECT '10.000 números', 10000, 0, 9999, 4, 1, 3
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_numeraciones`
  WHERE `num_desde` = 0 AND `num_hasta` = 9999 AND `num_padding` = 4
);

-- -----------------------------------------------------
-- 3. Numeraciones contratadas/configuradas por plan
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_plan_numeraciones` (
  `pnum_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pnum_plan_id` INT UNSIGNED NOT NULL,
  `pnum_num_id` INT UNSIGNED NOT NULL,
  `pnum_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio por rifa de esta numeración',
  `pnum_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `pnum_orden` INT UNSIGNED NOT NULL DEFAULT 0,
  `pnum_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pnum_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pnum_id`),
  UNIQUE KEY `uk_pnum_plan_num` (`pnum_plan_id`, `pnum_num_id`),
  KEY `idx_pnum_num` (`pnum_num_id`),
  CONSTRAINT `fk_pnum_plan` FOREIGN KEY (`pnum_plan_id`)
    REFERENCES `rif_planes` (`plan_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_pnum_num` FOREIGN KEY (`pnum_num_id`)
    REFERENCES `rif_numeraciones` (`num_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opciones automaticas solo cuando plan_tipo = 'rifas' y plan_limite coincide
-- exactamente con una numeracion del catalogo. No se inventan rangos.
INSERT INTO `rif_plan_numeraciones`
  (`pnum_plan_id`, `pnum_num_id`, `pnum_precio`, `pnum_status`, `pnum_orden`)
SELECT p.`plan_id`, n.`num_id`, 0.00, 1, n.`num_orden`
FROM `rif_planes` p
INNER JOIN `rif_numeraciones` n
  ON n.`num_cantidad` = p.`plan_limite`
WHERE p.`plan_tipo` = 'rifas'
  AND p.`plan_limite` IN (100, 1000, 10000)
  AND NOT EXISTS (
    SELECT 1 FROM `rif_plan_numeraciones` x
    WHERE x.`pnum_plan_id` = p.`plan_id`
      AND x.`pnum_num_id` = n.`num_id`
  );

-- -----------------------------------------------------
-- 4. Columnas nuevas en rif_plan_asignaciones
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_plan_asignaciones'
    AND COLUMN_NAME = 'asig_num_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD COLUMN `asig_num_id` INT UNSIGNED NULL COMMENT ''NULL = plan ilimitado: el organizador elige numeración'' AFTER `asig_plan_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_plan_asignaciones'
    AND COLUMN_NAME = 'asig_cantidad'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD COLUMN `asig_cantidad` INT UNSIGNED NULL COMMENT ''NULL = ilimitado; para planes por rifas = cantidad comprada'' AFTER `asig_num_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_plan_asignaciones'
    AND COLUMN_NAME = 'asig_precio_unitario'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD COLUMN `asig_precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `asig_cantidad`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_plan_asignaciones'
    AND COLUMN_NAME = 'asig_total'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD COLUMN `asig_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `asig_precio_unitario`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 5. Indice y FK de numeracion en rif_plan_asignaciones
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_plan_asignaciones'
    AND INDEX_NAME = 'idx_asig_num'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD KEY `idx_asig_num` (`asig_num_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_plan_asignaciones'
    AND CONSTRAINT_NAME = 'fk_asig_num'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD CONSTRAINT `fk_asig_num` FOREIGN KEY (`asig_num_id`) REFERENCES `rif_numeraciones` (`num_id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 6. Compatibilidad con asignaciones existentes
--    Solo completa asig_cantidad; no toca asig_rifas_usadas.
--    Los planes ilimitados quedan con asig_cantidad = NULL.
-- -----------------------------------------------------
UPDATE `rif_plan_asignaciones` a
INNER JOIN `rif_planes` p ON p.`plan_id` = a.`asig_plan_id`
SET a.`asig_cantidad` = p.`plan_limite`
WHERE a.`asig_cantidad` IS NULL
  AND p.`plan_tipo` = 'rifas'
  AND p.`plan_limite` > 0;

-- -----------------------------------------------------
-- 7. Columnas nuevas en rif_rifas
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_asig_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_asig_id` BIGINT UNSIGNED NULL COMMENT ''Lote/asignación de plan usada al publicar'' AFTER `rifa_org_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_num_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_num_id` INT UNSIGNED NULL COMMENT ''Numeración del catálogo; NULL en gratuitas pendientes'' AFTER `rifa_asig_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 8. Indices y FKs de lote/numeracion en rif_rifas
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND INDEX_NAME = 'idx_rifa_asig'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_rifas` ADD KEY `idx_rifa_asig` (`rifa_asig_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND INDEX_NAME = 'idx_rifa_num'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_rifas` ADD KEY `idx_rifa_num` (`rifa_num_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND CONSTRAINT_NAME = 'fk_rifa_asig'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_rifas` ADD CONSTRAINT `fk_rifa_asig` FOREIGN KEY (`rifa_asig_id`) REFERENCES `rif_plan_asignaciones` (`asig_id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND CONSTRAINT_NAME = 'fk_rifa_num'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_rifas` ADD CONSTRAINT `fk_rifa_num` FOREIGN KEY (`rifa_num_id`) REFERENCES `rif_numeraciones` (`num_id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
