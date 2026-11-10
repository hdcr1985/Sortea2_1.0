-- =====================================================
-- Migracion: Medios visuales para metodos de pago
-- Modulo: MetodosPago
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Agregar `met_icono` VARCHAR(100) NULL a `rif_metodos_pago`
--      despues de `met_instrucciones`.
--   2. Agregar `met_imagen` VARCHAR(255) NULL despues de `met_icono`.
--      `met_imagen` almacena una URL http/https; NO es un archivo subido.
--   3. Sembrar el icono por defecto de los 6 metodos globales solo cuando
--      `met_icono` sea NULL o cadena vacia. Nunca sobreescribe un icono
--      definido por el administrador.
--
-- Idempotencia:
--   - Cada columna se agrega solo si no existe en information_schema.COLUMNS.
--   - Los seeds usan UPDATE con guarda `met_icono IS NULL OR met_icono = ''`.
--   - Reejecutar el script no duplica columnas ni sobreescribe iconos.
--
-- Alcance:
--   - Solo modifica la tabla `rif_metodos_pago`.
--   - No altera migraciones previas, .env ni otras tablas/datos.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. met_icono: clase de icono (KeenIcons) del metodo
-- -----------------------------------------------------
SET @col_met_icono = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodos_pago'
    AND COLUMN_NAME = 'met_icono'
);
SET @sql = IF(@col_met_icono = 0,
  'ALTER TABLE `rif_metodos_pago` ADD COLUMN `met_icono` VARCHAR(100) NULL COMMENT ''Clase de icono KeenIcons (ej. ki-bank)'' AFTER `met_instrucciones`',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2. met_imagen: URL http/https de la imagen del metodo
--    (no es un archivo subido; no se almacena en disco)
-- -----------------------------------------------------
SET @col_met_imagen = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodos_pago'
    AND COLUMN_NAME = 'met_imagen'
);
SET @sql = IF(@col_met_imagen = 0,
  'ALTER TABLE `rif_metodos_pago` ADD COLUMN `met_imagen` VARCHAR(255) NULL COMMENT ''URL http/https de la imagen; no es archivo subido'' AFTER `met_icono`',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. Seed de iconos por defecto (solo globales y solo si vacio/NULL)
-- -----------------------------------------------------
UPDATE `rif_metodos_pago`
SET `met_icono` = 'ki-bank'
WHERE `met_scope` = 'global'
  AND `met_nombre` = 'Transferencia bancaria'
  AND (`met_icono` IS NULL OR `met_icono` = '');

UPDATE `rif_metodos_pago`
SET `met_icono` = 'ki-phone'
WHERE `met_scope` = 'global'
  AND `met_nombre` = 'Pago móvil'
  AND (`met_icono` IS NULL OR `met_icono` = '');

UPDATE `rif_metodos_pago`
SET `met_icono` = 'ki-dollar'
WHERE `met_scope` = 'global'
  AND `met_nombre` = 'Zelle'
  AND (`met_icono` IS NULL OR `met_icono` = '');

UPDATE `rif_metodos_pago`
SET `met_icono` = 'ki-wallet'
WHERE `met_scope` = 'global'
  AND `met_nombre` = 'Binance Pay'
  AND (`met_icono` IS NULL OR `met_icono` = '');

UPDATE `rif_metodos_pago`
SET `met_icono` = 'ki-credit-cart'
WHERE `met_scope` = 'global'
  AND `met_nombre` = 'PayPal'
  AND (`met_icono` IS NULL OR `met_icono` = '');

UPDATE `rif_metodos_pago`
SET `met_icono` = 'ki-bill'
WHERE `met_scope` = 'global'
  AND `met_nombre` = 'Efectivo'
  AND (`met_icono` IS NULL OR `met_icono` = '');
