-- =====================================================
-- Migracion: M08 - Endurecer FKs de compras y lotes de planes
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   Evitar que el borrado fisico de un organizador destruya el historial de
--   compras de planes o de lotes asignados. Las relaciones financieras deben
--   conservarse para conciliacion y auditoria.
--
-- Cambios:
--   - fk_plan_compra_org:  CASCADE -> RESTRICT
--   - fk_plan_compra_asig: SET NULL -> RESTRICT
--   - fk_asig_org:         CASCADE -> RESTRICT (M02)
--
-- La migracion es idempotente: elimina la FK si existe y la vuelve a crear
-- con la regla RESTRICT. No borra datos ni columnas.
-- =====================================================

SET NAMES utf8mb4;
SET @db_name = DATABASE();

-- 1. rif_plan_compras.fk_plan_compra_org
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_org'
);
SET @sql = IF(@fk_exists > 0,
  'ALTER TABLE `rif_plan_compras` DROP FOREIGN KEY `fk_plan_compra_org`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_org'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD CONSTRAINT `fk_plan_compra_org` FOREIGN KEY (`compra_org_id`) REFERENCES `rif_organizadores` (`org_id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. rif_plan_compras.fk_plan_compra_asig
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_asig'
);
SET @sql = IF(@fk_exists > 0,
  'ALTER TABLE `rif_plan_compras` DROP FOREIGN KEY `fk_plan_compra_asig`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_compras'
    AND CONSTRAINT_NAME = 'fk_plan_compra_asig'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_compras` ADD CONSTRAINT `fk_plan_compra_asig` FOREIGN KEY (`compra_asig_id`) REFERENCES `rif_plan_asignaciones` (`asig_id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. rif_plan_asignaciones.fk_asig_org (M02)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_asignaciones'
    AND CONSTRAINT_NAME = 'fk_asig_org'
);
SET @sql = IF(@fk_exists > 0,
  'ALTER TABLE `rif_plan_asignaciones` DROP FOREIGN KEY `fk_asig_org`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rif_plan_asignaciones'
    AND CONSTRAINT_NAME = 'fk_asig_org'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_plan_asignaciones` ADD CONSTRAINT `fk_asig_org` FOREIGN KEY (`asig_org_id`) REFERENCES `rif_organizadores` (`org_id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
