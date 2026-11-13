-- =====================================================
-- Migracion: soporte y captura de pago en ordenes publicas
-- Modulo: Rifas / Checkout publico
-- Fecha: 2026-11-13
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   Guardar el soporte/referencia y la captura del pago enviados por el
--   comprador al crear la orden publica, para que el organizador pueda
--   revisarlos antes de aprobar.
--
-- Idempotencia:
--   Cada columna se agrega solo si no existe en information_schema.
--   No se modifican filas, indices, claves foraneas ni otras tablas.
-- =====================================================

SET NAMES utf8mb4;

SET @table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
);

-- ord_pago_soporte
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_pago_soporte'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_pago_soporte` VARCHAR(120) NULL COMMENT ''Referencia o soporte del pago''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ord_pago_captura
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_pago_captura'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_pago_captura` VARCHAR(255) NULL COMMENT ''Archivo de captura del pago''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ord_pago_captura_mime
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_pago_captura_mime'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_pago_captura_mime` VARCHAR(100) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ord_pago_captura_size
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_pago_captura_size'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_pago_captura_size` INT UNSIGNED NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ord_pago_captura_at
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_pago_captura_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_pago_captura_at` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rif_ordenes'
      AND COLUMN_NAME IN (
        'ord_pago_soporte',
        'ord_pago_captura',
        'ord_pago_captura_mime',
        'ord_pago_captura_size',
        'ord_pago_captura_at'
      )) AS payment_proof_columns,
  CASE
    WHEN @table_exists = 0 THEN 'BLOCKED: rif_ordenes no existe'
    ELSE 'OK'
  END AS migration_status;
