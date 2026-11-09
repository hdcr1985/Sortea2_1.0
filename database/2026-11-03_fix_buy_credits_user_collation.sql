-- =====================================================
-- Migration: align Buy Credits user references with cab_usuarios
-- Date: 2026-11-03
-- =====================================================
-- cab_usuarios.usu_id uses utf8mb3_general_ci in proyecto_base. The new
-- order tables must use the same charset/collation for user_id joins.
-- Idempotent: only ALTERs when the current collation differs.
-- =====================================================

SET @db_name = DATABASE();

SET @needs_movplanes = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'cab_movplanes'
      AND COLUMN_NAME = 'mov_idusuario'
      AND (COLLATION_NAME IS NULL OR COLLATION_NAME <> 'utf8mb3_general_ci')
);

SET @sql_stmt = IF(
    @needs_movplanes > 0,
    'ALTER TABLE `cab_movplanes` MODIFY COLUMN `mov_idusuario` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT ''Buyer user id''',
    'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @needs_movsms = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'cab_movsms'
      AND COLUMN_NAME = 'movs_idusuario'
      AND (COLLATION_NAME IS NULL OR COLLATION_NAME <> 'utf8mb3_general_ci')
);

SET @sql_stmt = IF(
    @needs_movsms > 0,
    'ALTER TABLE `cab_movsms` MODIFY COLUMN `movs_idusuario` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL',
    'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND (
    (TABLE_NAME = 'cab_movplanes' AND COLUMN_NAME = 'mov_idusuario')
    OR (TABLE_NAME = 'cab_movsms' AND COLUMN_NAME = 'movs_idusuario')
  );
