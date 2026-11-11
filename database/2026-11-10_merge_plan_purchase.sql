-- =====================================================
-- Migracion: M08 Compra de planes/lotes reutilizando Comprar Creditos
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Extender cab_planes con la clasificacion de oferta vendible:
--      creditos o plan_rifa, vinculada a rif_planes/rif_numeraciones.
--   2. Crear rif_plan_compras: orden de compra de un plan/lote de rifa.
--   3. Generar/actualizar ofertas en cab_planes a partir de rif_planes,
--      rif_plan_numeraciones y rif_numeraciones.
--   4. Retirar el menu del modulo Planes de creditos (Plan/index) y
--      renombrar Comprar Creditos a "Comprar Planes".
--
-- Dependencias:
--   - cab_planes, cab_menu, cab_permisologia, cab_permiaccion
--   - rif_organizadores, rif_planes, rif_numeraciones,
--     rif_plan_numeraciones, rif_plan_asignaciones
--
-- La migracion es idempotente:
--   - Columnas, indices y FKs agregados solo si no existen.
--   - Ofertas insertadas solo si no existe (pla_rif_plan_id, pla_rif_num_id).
--   - Ofertas existentes actualizadas sin duplicarse.
--   - Menu y permisos retirados por slug/URL; se puede reejecutar.
--   - No elimina tablas, columnas ni historial (cab_planes/cab_movplanes).
--
-- Notas:
--   - cab_movplanes puede no ser InnoDB: compra_mov_id es referencia logica,
--     SIN FK fisica.
--   - compra_num_id = 0 representa plan ilimitado/sin numeracion; por eso
--     NO se crea FK para esa columna.
-- =====================================================

SET NAMES utf8mb4;
SET @db_name = DATABASE();

-- -----------------------------------------------------
-- 1. Columnas nuevas en cab_planes
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_tipo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_tipo` ENUM(''creditos'',''plan_rifa'') NOT NULL DEFAULT ''creditos'' COMMENT ''Tipo de oferta vendible'' AFTER `pla_descripcion`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_rif_plan_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_rif_plan_id` INT UNSIGNED NULL COMMENT ''Plan de rifa vinculado (rif_planes.plan_id)'' AFTER `pla_tipo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_rif_num_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_rif_num_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Numeracion vinculada (rif_numeraciones.num_id); 0 = sin numeracion/ilimitado'' AFTER `pla_rif_plan_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_planes'
    AND COLUMN_NAME = 'pla_rif_periodo'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `cab_planes` ADD COLUMN `pla_rif_periodo` ENUM(''sin_limite'',''mensual'',''anual'') NOT NULL DEFAULT ''sin_limite'' COMMENT ''Periodo de vigencia de la oferta de plan'' AFTER `pla_rif_num_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2. Indices de cab_planes
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_planes'
    AND INDEX_NAME = 'uk_pla_rif_oferta'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `cab_planes` ADD UNIQUE KEY `uk_pla_rif_oferta` (`pla_rif_plan_id`, `pla_rif_num_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_planes'
    AND INDEX_NAME = 'idx_pla_tipo'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `cab_planes` ADD KEY `idx_pla_tipo` (`pla_tipo`, `pla_status`, `pla_eliminado`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. Tabla de compras de planes de rifa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_plan_compras` (
  `compra_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `compra_mov_id` VARCHAR(20) NOT NULL COMMENT 'Orden en cab_movplanes (referencia logica, sin FK)',
  `compra_org_id` BIGINT UNSIGNED NOT NULL,
  `compra_plan_id` INT UNSIGNED NOT NULL,
  `compra_num_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = ilimitado/sin numeracion',
  `compra_cantidad` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = ilimitado',
  `compra_periodo` ENUM('sin_limite','mensual','anual') NOT NULL DEFAULT 'sin_limite',
  `compra_precio_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `compra_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `compra_moneda` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `compra_asig_id` BIGINT UNSIGNED NULL COMMENT 'Lote generado en rif_plan_asignaciones',
  `compra_estado` ENUM('pendiente','pagada','cancelada','expirada','error') NOT NULL DEFAULT 'pendiente',
  `compra_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `compra_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`compra_id`),
  UNIQUE KEY `uk_plan_compra_mov` (`compra_mov_id`),
  KEY `idx_plan_compra_org` (`compra_org_id`, `compra_estado`),
  KEY `idx_plan_compra_plan` (`compra_plan_id`, `compra_num_id`),
  KEY `idx_plan_compra_asig` (`compra_asig_id`),
  CONSTRAINT `fk_plan_compra_org` FOREIGN KEY (`compra_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_plan_compra_plan` FOREIGN KEY (`compra_plan_id`)
    REFERENCES `rif_planes` (`plan_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_plan_compra_asig` FOREIGN KEY (`compra_asig_id`)
    REFERENCES `rif_plan_asignaciones` (`asig_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.1 Indices faltantes (tabla preexistente o creacion parcial)
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND INDEX_NAME = 'uk_plan_compra_mov'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD UNIQUE KEY `uk_plan_compra_mov` (`compra_mov_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND INDEX_NAME = 'idx_plan_compra_org'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD KEY `idx_plan_compra_org` (`compra_org_id`, `compra_estado`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND INDEX_NAME = 'idx_plan_compra_plan'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD KEY `idx_plan_compra_plan` (`compra_plan_id`, `compra_num_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND INDEX_NAME = 'idx_plan_compra_asig'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD KEY `idx_plan_compra_asig` (`compra_asig_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.2 FKs faltantes (tabla preexistente o creacion parcial)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_org'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD CONSTRAINT `fk_plan_compra_org` FOREIGN KEY (`compra_org_id`) REFERENCES `rif_organizadores` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_plan'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD CONSTRAINT `fk_plan_compra_plan` FOREIGN KEY (`compra_plan_id`) REFERENCES `rif_planes` (`plan_id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_asig'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD CONSTRAINT `fk_plan_compra_asig` FOREIGN KEY (`compra_asig_id`) REFERENCES `rif_plan_asignaciones` (`asig_id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 4. Backfill de ofertas de planes de rifa (tipo 'rifas')
-- -----------------------------------------------------
-- 4.1 Actualizar ofertas ya vinculadas y vigentes.
UPDATE `cab_planes` c
INNER JOIN `rif_planes` p
  ON p.`plan_id` = c.`pla_rif_plan_id`
INNER JOIN `rif_plan_numeraciones` pn
  ON pn.`pnum_plan_id` = p.`plan_id`
 AND pn.`pnum_num_id` = c.`pla_rif_num_id`
INNER JOIN `rif_numeraciones` n
  ON n.`num_id` = pn.`pnum_num_id`
SET
  c.`pla_nombre` = CONCAT(p.`plan_nombre`, ' — ', n.`num_label`),
  c.`pla_descripcion` = p.`plan_descripcion`,
  c.`pla_numero` = 0,
  c.`pla_precio` = pn.`pnum_precio`,
  c.`pla_moneda` = p.`plan_moneda`,
  c.`pla_paypal` = 1,
  c.`pla_crypto` = 1,
  c.`pla_status` = 1,
  c.`pla_eliminado` = 0,
  c.`pla_rif_periodo` = 'sin_limite'
WHERE c.`pla_tipo` = 'plan_rifa'
  AND p.`plan_tipo` = 'rifas'
  AND p.`plan_status` = 1
  AND p.`plan_eliminado` = 0
  AND pn.`pnum_status` = 1
  AND n.`num_status` = 1;

-- 4.2 Insertar ofertas faltantes para planes por rifas.
INSERT INTO `cab_planes` (
  `pla_nombre`, `pla_descripcion`, `pla_tipo`, `pla_rif_plan_id`,
  `pla_rif_num_id`, `pla_rif_periodo`, `pla_numero`, `pla_precio`,
  `pla_moneda`, `pla_paypal`, `pla_crypto`, `pla_status`, `pla_eliminado`
)
SELECT
  CONCAT(p.`plan_nombre`, ' — ', n.`num_label`),
  p.`plan_descripcion`,
  'plan_rifa',
  p.`plan_id`,
  n.`num_id`,
  'sin_limite',
  0,
  pn.`pnum_precio`,
  p.`plan_moneda`,
  1,
  1,
  1,
  0
FROM `rif_planes` p
INNER JOIN `rif_plan_numeraciones` pn
  ON pn.`pnum_plan_id` = p.`plan_id`
INNER JOIN `rif_numeraciones` n
  ON n.`num_id` = pn.`pnum_num_id`
WHERE p.`plan_tipo` = 'rifas'
  AND p.`plan_status` = 1
  AND p.`plan_eliminado` = 0
  AND pn.`pnum_status` = 1
  AND n.`num_status` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `cab_planes` c
    WHERE c.`pla_rif_plan_id` = p.`plan_id`
      AND c.`pla_rif_num_id` = n.`num_id`
  );

-- -----------------------------------------------------
-- 5. Backfill de ofertas de planes ilimitados (num_id = 0)
-- -----------------------------------------------------
-- 5.1 Actualizar ofertas ya vinculadas y vigentes.
UPDATE `cab_planes` c
INNER JOIN `rif_planes` p
  ON p.`plan_id` = c.`pla_rif_plan_id`
SET
  c.`pla_nombre` = p.`plan_nombre`,
  c.`pla_descripcion` = p.`plan_descripcion`,
  c.`pla_numero` = 0,
  c.`pla_precio` = p.`plan_precio`,
  c.`pla_moneda` = p.`plan_moneda`,
  c.`pla_paypal` = 1,
  c.`pla_crypto` = 1,
  c.`pla_status` = 1,
  c.`pla_eliminado` = 0,
  c.`pla_rif_periodo` = p.`plan_periodo`
WHERE c.`pla_tipo` = 'plan_rifa'
  AND c.`pla_rif_num_id` = 0
  AND p.`plan_tipo` = 'ilimitado'
  AND p.`plan_status` = 1
  AND p.`plan_eliminado` = 0;

-- 5.2 Insertar ofertas faltantes para planes ilimitados.
INSERT INTO `cab_planes` (
  `pla_nombre`, `pla_descripcion`, `pla_tipo`, `pla_rif_plan_id`,
  `pla_rif_num_id`, `pla_rif_periodo`, `pla_numero`, `pla_precio`,
  `pla_moneda`, `pla_paypal`, `pla_crypto`, `pla_status`, `pla_eliminado`
)
SELECT
  p.`plan_nombre`,
  p.`plan_descripcion`,
  'plan_rifa',
  p.`plan_id`,
  0,
  p.`plan_periodo`,
  0,
  p.`plan_precio`,
  p.`plan_moneda`,
  1,
  1,
  1,
  0
FROM `rif_planes` p
WHERE p.`plan_tipo` = 'ilimitado'
  AND p.`plan_status` = 1
  AND p.`plan_eliminado` = 0
  AND NOT EXISTS (
    SELECT 1 FROM `cab_planes` c
    WHERE c.`pla_rif_plan_id` = p.`plan_id`
      AND c.`pla_rif_num_id` = 0
  );

-- -----------------------------------------------------
-- 6. Inactivar ofertas vinculadas a planes/opciones no vigentes
--    No se eliminan ofertas; solo se ocultan (pla_status = 0).
-- -----------------------------------------------------
UPDATE `cab_planes` c
INNER JOIN `rif_planes` p
  ON p.`plan_id` = c.`pla_rif_plan_id`
LEFT JOIN `rif_plan_numeraciones` pn
  ON pn.`pnum_plan_id` = c.`pla_rif_plan_id`
 AND pn.`pnum_num_id` = c.`pla_rif_num_id`
LEFT JOIN `rif_numeraciones` n
  ON n.`num_id` = c.`pla_rif_num_id`
SET c.`pla_status` = 0
WHERE c.`pla_tipo` = 'plan_rifa'
  AND (
    p.`plan_status` <> 1
    OR p.`plan_eliminado` <> 0
    OR (
      p.`plan_tipo` = 'rifas'
      AND c.`pla_rif_num_id` > 0
      AND (
        pn.`pnum_id` IS NULL
        OR pn.`pnum_status` <> 1
        OR n.`num_id` IS NULL
        OR n.`num_status` <> 1
      )
    )
  );

-- -----------------------------------------------------
-- 7. Menu: retirar Planes de creditos (Plan/index)
--    No se borran tablas ni historial de planes/movimientos.
-- -----------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `tmp_m08_plan_menu`;
CREATE TEMPORARY TABLE `tmp_m08_plan_menu` (
  `men_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`men_id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_m08_plan_menu` (`men_id`)
SELECT m.`men_id`
FROM `cab_menu` m
WHERE m.`men_urlamigable` = 'planes'
   OR TRIM(BOTH '/' FROM m.`men_url`) = 'Plan/index';

DELETE a
FROM `cab_permiaccion` a
INNER JOIN `cab_permisologia` p ON p.`mis_id` = a.`acc_id`
INNER JOIN `tmp_m08_plan_menu` t ON t.`men_id` = p.`mis_menu`;

DELETE FROM `cab_permiaccion`
WHERE `acc_id` IN (
  SELECT CONCAT(p.`mis_permiso`, '_', p.`mis_menu`)
  FROM `cab_permisologia` p
  INNER JOIN `tmp_m08_plan_menu` t ON t.`men_id` = p.`mis_menu`
);

DELETE p
FROM `cab_permisologia` p
INNER JOIN `tmp_m08_plan_menu` t ON t.`men_id` = p.`mis_menu`;

DELETE m
FROM `cab_menu` m
INNER JOIN `tmp_m08_plan_menu` t ON t.`men_id` = m.`men_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_m08_plan_menu`;

-- -----------------------------------------------------
-- 8. Menu: renombrar Comprar Creditos -> Comprar Planes
--    Se conservan URL y slug para no romper enlaces existentes.
-- -----------------------------------------------------
UPDATE `cab_menu`
SET
  `men_nombre` = 'Comprar Planes',
  `men_descripcion` = 'Compra de planes y lotes de rifa'
WHERE `men_urlamigable` = 'comprar-creditos'
   OR (
     `men_id` = 139
     AND TRIM(BOTH '/' FROM `men_url`) = 'BuyCredits/index'
   );

-- -----------------------------------------------------
-- 9. Invalidar snapshots de menu de sesiones activas
-- -----------------------------------------------------
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
