-- =====================================================
-- Migration: widen cab_movplanes.mov_coin
-- Date: 2026-11-03
-- =====================================================
-- BTCPay payment method ids can exceed 10 characters
-- (for example BTC-LightningNetwork). Store the full value (up to 32).
-- Idempotent: only ALTERs when the current type is shorter than 32.
-- =====================================================

SET @db_name = DATABASE();

SET @needs_alter = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'cab_movplanes'
      AND COLUMN_NAME = 'mov_coin'
      AND CHARACTER_MAXIMUM_LENGTH < 32
);

SET @sql_stmt = IF(
    @needs_alter > 0,
    'ALTER TABLE `cab_movplanes` MODIFY COLUMN `mov_coin` varchar(32) DEFAULT NULL COMMENT ''Crypto coin or payment detail''',
    'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SHOW FULL COLUMNS FROM `cab_movplanes` WHERE Field = 'mov_coin';
