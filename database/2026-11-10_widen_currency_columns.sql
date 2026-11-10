-- =====================================================
-- Migracion: ampliar columnas de moneda en rifas, planes y tickets
-- Modulo: Rifas / Planes / Inventario de tickets (M04 Monedas)
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   Admitir codigos de moneda de hasta 10 caracteres. El catalogo M04
--   incluye USDT (4 caracteres) y se guarda en:
--     - rif_rifas.rifa_moneda
--     - rif_planes.plan_moneda
--     - rif_tickets.tick_moneda
--   Con CHAR(3) y STRICT_TRANS_TABLES el INSERT fallaria o truncaria.
--
-- Cambios de tipo (solo tipo; NO se tocan datos ni otras columnas):
--   - rif_rifas.rifa_moneda : CHAR(3) NOT NULL DEFAULT 'USD'
--                              -> VARCHAR(10) NOT NULL DEFAULT 'USD'
--   - rif_planes.plan_moneda: CHAR(3) NOT NULL DEFAULT 'USD'
--                              -> VARCHAR(10) NOT NULL DEFAULT 'USD'
--   - rif_tickets.tick_moneda: CHAR(3) NULL
--                              -> VARCHAR(10) NULL
--
-- Idempotencia:
--   Cada ALTER se ejecuta solo si la columna NO es ya un VARCHAR con
--   longitud >= 10 (es decir, si su tipo actual es CHAR(3) o distinto de
--   VARCHAR(10)). Se consulta information_schema.COLUMNS antes de cada
--   ALTER. Reejecutar no modifica nada y no pierde datos.
--   La guarda evita ademas encoger una columna que ya fuese mas ancha.
--
-- Integridad:
--   - Se conserva CHARACTER SET utf8mb4 y COLLATE utf8mb4_unicode_ci.
--   - Se conserva la nulabilidad y el DEFAULT original de cada columna.
--   - MODIFY COLUMN no reordena ni altera otras columnas.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. rif_rifas.rifa_moneda -> VARCHAR(10) NOT NULL DEFAULT 'USD'
-- -----------------------------------------------------
SET @needs_alter = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_moneda'
    AND NOT (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 10)
);

SET @sql_stmt = IF(
  @needs_alter > 0,
  'ALTER TABLE `rif_rifas` MODIFY COLUMN `rifa_moneda` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''USD''',
  'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2. rif_planes.plan_moneda -> VARCHAR(10) NOT NULL DEFAULT 'USD'
-- -----------------------------------------------------
SET @needs_alter = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_planes'
    AND COLUMN_NAME = 'plan_moneda'
    AND NOT (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 10)
);

SET @sql_stmt = IF(
  @needs_alter > 0,
  'ALTER TABLE `rif_planes` MODIFY COLUMN `plan_moneda` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''USD''',
  'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. rif_tickets.tick_moneda -> VARCHAR(10) NULL
-- -----------------------------------------------------
SET @needs_alter = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND COLUMN_NAME = 'tick_moneda'
    AND NOT (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 10)
);

SET @sql_stmt = IF(
  @needs_alter > 0,
  'ALTER TABLE `rif_tickets` MODIFY COLUMN `tick_moneda` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL',
  'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 4. Verificacion de tipos finales
-- -----------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
       CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ( (TABLE_NAME = 'rif_rifas'   AND COLUMN_NAME = 'rifa_moneda')
     OR (TABLE_NAME = 'rif_planes'  AND COLUMN_NAME = 'plan_moneda')
     OR (TABLE_NAME = 'rif_tickets' AND COLUMN_NAME = 'tick_moneda') )
ORDER BY TABLE_NAME;
