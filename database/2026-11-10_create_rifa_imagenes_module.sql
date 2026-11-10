-- =====================================================
-- Migracion: M03.2 Galeria de imagenes por rifa
-- Modulo: Rifas
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   Crear la tabla rif_rifa_imagenes para almacenar hasta 3
--   imagenes por rifa (1 portada + 2 adicionales) y migrar la
--   portada legacy rif_rifas.rifa_imagen sin modificar su valor.
--
-- Dependencias:
--   - rif_rifas (M03), en particular rifa_id y rifa_imagen.
--
-- Idempotencia:
--   - CREATE TABLE IF NOT EXISTS.
--   - Guardas para indice y FK si la tabla ya existia incompleta.
--   - Backfill con NOT EXISTS: una rifa sin filas recibe su portada
--     una sola vez. Reejecutar no duplica ni altera datos.
--
-- Nota:
--   Las reglas de negocio (maximo 3 imagenes y una sola portada)
--   se validan en la capa backend; aqui solo se define el esquema.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Tabla de imagenes por rifa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_rifa_imagenes` (
  `img_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `img_rifa_id` BIGINT UNSIGNED NOT NULL,
  `img_archivo` VARCHAR(255) NOT NULL COMMENT 'Nombre de archivo en upload/rifas/{org_id}/',
  `img_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `img_orden` INT UNSIGNED NOT NULL DEFAULT 0,
  `img_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `img_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`img_id`),
  KEY `idx_img_rifa` (`img_rifa_id`, `img_principal`, `img_orden`),
  CONSTRAINT `fk_img_rifa` FOREIGN KEY (`img_rifa_id`)
    REFERENCES `rif_rifas` (`rifa_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Guardas de esquema para instalaciones con tabla parcial
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifa_imagenes'
    AND INDEX_NAME = 'idx_img_rifa'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_rifa_imagenes` ADD KEY `idx_img_rifa` (`img_rifa_id`, `img_principal`, `img_orden`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifa_imagenes'
    AND COLUMN_NAME = 'img_rifa_id'
    AND REFERENCED_TABLE_NAME = 'rif_rifas'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_rifa_imagenes` ADD CONSTRAINT `fk_img_rifa` FOREIGN KEY (`img_rifa_id`) REFERENCES `rif_rifas` (`rifa_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. Backfill de la portada legacy
-- -----------------------------------------------------
-- Solo inserta para rifas con rifa_imagen no vacio que aun no
-- tengan ninguna fila en rif_rifa_imagenes. No modifica
-- rif_rifas.rifa_imagen.
INSERT INTO `rif_rifa_imagenes`
  (`img_rifa_id`, `img_archivo`, `img_principal`, `img_orden`)
SELECT
  r.`rifa_id`,
  r.`rifa_imagen`,
  1,
  0
FROM `rif_rifas` r
WHERE r.`rifa_imagen` IS NOT NULL
  AND TRIM(r.`rifa_imagen`) <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM `rif_rifa_imagenes` i
    WHERE i.`img_rifa_id` = r.`rifa_id`
  );
