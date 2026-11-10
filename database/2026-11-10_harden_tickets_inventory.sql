-- =====================================================
-- Migracion: M03.3 Endurecimiento de indices de rif_tickets
-- Modulo: Rifas / Inventario de tickets
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. UNIQUE KEY (tick_rifa_id, tick_numero_int) para impedir que un
--      cambio de padding en tick_numero permita dos filas para el mismo
--      numero entero dentro de una rifa.
--   2. KEY (tick_rifa_id, tick_estado, tick_numero_int) para acelerar
--      consultas de bloqueados/mapa por estado y rango.
--
-- Idempotencia:
--   - Cada indice se valida contra information_schema.STATISTICS por
--     TABLE_SCHEMA + TABLE_NAME + INDEX_NAME antes del ALTER.
--   - Reejecutar el script no crea objetos duplicados.
--
-- Datos:
--   - No se insertan, actualizan ni eliminan filas.
--   - Si existen duplicados de (tick_rifa_id, tick_numero_int), el
--     UNIQUE NO se fuerza: se omite y el SELECT final lo reporta como
--     BLOCKED. El indice no unico si se agrega porque es seguro.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 0. Contexto: tabla y duplicados existentes
-- -----------------------------------------------------
SET @table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
);

SET @dup_count = 0;
SET @sql = IF(@table_exists = 1,
  'SELECT COUNT(*) INTO @dup_count FROM (SELECT 1 FROM `rif_tickets` GROUP BY `tick_rifa_id`, `tick_numero_int` HAVING COUNT(*) > 1) AS d',
  'SET @dup_count = 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 1. UNIQUE: (tick_rifa_id, tick_numero_int)
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND INDEX_NAME = 'uk_ticket_rifa_numero_int'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0 AND @dup_count = 0,
  'ALTER TABLE `rif_tickets` ADD UNIQUE KEY `uk_ticket_rifa_numero_int` (`tick_rifa_id`, `tick_numero_int`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2. KEY: (tick_rifa_id, tick_estado, tick_numero_int)
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND INDEX_NAME = 'idx_ticket_rifa_estado_num'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `rif_tickets` ADD KEY `idx_ticket_rifa_estado_num` (`tick_rifa_id`, `tick_estado`, `tick_numero_int`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. Diagnostico final (solo lectura)
-- -----------------------------------------------------
SELECT
  @table_exists AS rif_tickets_exists,
  @dup_count AS duplicate_pairs,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rif_tickets'
      AND INDEX_NAME = 'uk_ticket_rifa_numero_int') AS has_uk_ticket_rifa_numero_int,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rif_tickets'
      AND INDEX_NAME = 'idx_ticket_rifa_estado_num') AS has_idx_ticket_rifa_estado_num,
  CASE
    WHEN @table_exists = 0 THEN 'BLOCKED: rif_tickets no existe'
    WHEN @dup_count > 0 THEN 'BLOCKED: duplicados detectados; UNIQUE omitido'
    ELSE 'OK'
  END AS migration_status;
