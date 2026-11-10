-- =====================================================
-- Migracion: M07 Checkout publico y ordenes
-- Modulo: Rifas / Ordenes de compra
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Crear rif_ordenes: orden publica de compra de tickets con codigo
--      publico unico, datos del comprador, metodo de pago elegido y su
--      snapshot, importes, estados y marcas de tiempo del ciclo de vida.
--   2. Agregar el indice idx_ticket_orden (tick_orden_id) en rif_tickets
--      para resolver los numeros asociados a una orden.
--
-- Dependencias (deben existir antes de aplicar):
--   - rif_rifas (rifa_id), M03.
--   - rif_organizadores (org_id), M01.
--   - rif_org_metodos (orgmet_id), M06 metodos de pago.
--   - rif_tickets (tabla a indexar), M03.3.
--
-- Modelo de datos:
--   - ord_codigo es el identificador publico de la orden (alta entropia,
--     generado por backend); UNIQUE evita colisiones y permite lookup
--     publico sin exponer ord_id.
--   - ord_metodo_snapshot guarda el JSON del metodo de pago al momento
--     de la compra (nombre, instrucciones, campos/valores), de modo que
--     la orden no cambie si el organizador edita el metodo luego.
--   - ord_orgmet_id es una referencia viva al metodo configurado; si se
--     elimina, la orden conserva el snapshot (ON DELETE SET NULL).
--   - Los tickets mantienen tick_orden_id como referencia LOGICA a
--     rif_ordenes (M07). No se crea FK desde rif_tickets para no acoplar
--     el inventario al ciclo de la orden; solo el indice de busqueda.
--
-- Idempotencia:
--   - CREATE TABLE IF NOT EXISTS.
--   - Columnas, UNIQUE, indices y FKs validados contra
--     information_schema antes del ALTER.
--   - El UNIQUE de ord_codigo solo se agrega si no hay duplicados.
--   - Reejecutar no crea objetos duplicados ni modifica filas.
--
-- Datos:
--   - No se insertan, actualizan ni eliminan filas.
--   - No se tocan rif_rifas, rif_organizadores, rif_tickets ni
--     rif_ticket_historial; solo se agrega un indice a rif_tickets.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 0. Contexto de esquema
-- -----------------------------------------------------
SET @table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
);

-- -----------------------------------------------------
-- 1. Tabla rif_ordenes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_ordenes` (
  `ord_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ord_rifa_id` BIGINT UNSIGNED NOT NULL,
  `ord_org_id` BIGINT UNSIGNED NOT NULL,
  `ord_codigo` VARCHAR(40) NOT NULL COMMENT 'Codigo publico unico de la orden',
  `ord_comprador_nombre` VARCHAR(150) NOT NULL,
  `ord_comprador_email` VARCHAR(150) NOT NULL,
  `ord_comprador_telefono` VARCHAR(50) NULL,
  `ord_comprador_documento` VARCHAR(50) NULL,
  `ord_cantidad` INT UNSIGNED NOT NULL,
  `ord_precio_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `ord_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `ord_moneda` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `ord_orgmet_id` BIGINT UNSIGNED NULL COMMENT 'Metodo elegido; FK SET NULL',
  `ord_metodo_snapshot` TEXT NULL COMMENT 'JSON del metodo al momento de la compra',
  `ord_estado` ENUM('pendiente','en_revision','pagada','rechazada','cancelada','expirada') NOT NULL DEFAULT 'pendiente',
  `ord_notas_comprador` VARCHAR(500) NULL,
  `ord_notas_organizador` VARCHAR(500) NULL,
  `ord_motivo_rechazo` VARCHAR(255) NULL,
  `ord_reserva_expira_at` DATETIME NULL,
  `ord_pagada_at` DATETIME NULL,
  `ord_rechazada_at` DATETIME NULL,
  `ord_cancelada_at` DATETIME NULL,
  `ord_expirada_at` DATETIME NULL,
  `ord_ip` VARCHAR(45) NULL,
  `ord_user_agent` VARCHAR(255) NULL,
  `ord_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ord_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ord_id`),
  UNIQUE KEY `uk_orden_codigo` (`ord_codigo`),
  KEY `idx_orden_rifa_estado` (`ord_rifa_id`, `ord_estado`, `ord_created_at`),
  KEY `idx_orden_org_estado` (`ord_org_id`, `ord_estado`, `ord_created_at`),
  KEY `idx_orden_email` (`ord_comprador_email`),
  KEY `idx_orden_orgmet` (`ord_orgmet_id`),
  CONSTRAINT `fk_orden_rifa` FOREIGN KEY (`ord_rifa_id`)
    REFERENCES `rif_rifas` (`rifa_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_orden_org` FOREIGN KEY (`ord_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_orden_orgmet` FOREIGN KEY (`ord_orgmet_id`)
    REFERENCES `rif_org_metodos` (`orgmet_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
);

-- -----------------------------------------------------
-- 2. Guardas de columnas (instalaciones parciales)
--    Nota: si la tabla ya existiera incompleta, las columnas faltantes
--    se agregan al final. En una creacion desde cero el orden es el de
--    la definicion anterior.
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_rifa_id'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_rifa_id` BIGINT UNSIGNED NOT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_org_id'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_org_id` BIGINT UNSIGNED NOT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_codigo'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_codigo` VARCHAR(40) NOT NULL COMMENT ''Codigo publico unico de la orden''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_comprador_nombre'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_comprador_nombre` VARCHAR(150) NOT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_comprador_email'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_comprador_email` VARCHAR(150) NOT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_comprador_telefono'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_comprador_telefono` VARCHAR(50) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_comprador_documento'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_comprador_documento` VARCHAR(50) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_cantidad'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_cantidad` INT UNSIGNED NOT NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_precio_unitario'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_precio_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_total'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_moneda'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_moneda` VARCHAR(10) NOT NULL DEFAULT ''USD''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_orgmet_id'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_orgmet_id` BIGINT UNSIGNED NULL COMMENT ''Metodo elegido; FK SET NULL''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_metodo_snapshot'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_metodo_snapshot` TEXT NULL COMMENT ''JSON del metodo al momento de la compra''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_estado'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_estado` ENUM(''pendiente'',''en_revision'',''pagada'',''rechazada'',''cancelada'',''expirada'') NOT NULL DEFAULT ''pendiente''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_notas_comprador'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_notas_comprador` VARCHAR(500) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_notas_organizador'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_notas_organizador` VARCHAR(500) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_motivo_rechazo'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_motivo_rechazo` VARCHAR(255) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_reserva_expira_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_reserva_expira_at` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_pagada_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_pagada_at` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_rechazada_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_rechazada_at` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_cancelada_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_cancelada_at` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_expirada_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_expirada_at` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_ip'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_ip` VARCHAR(45) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_user_agent'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_user_agent` VARCHAR(255) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_created_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_updated_at'
);
SET @sql = IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD COLUMN `ord_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3. Guardas de indices: rif_ordenes
-- -----------------------------------------------------
-- 3.1 UNIQUE ord_codigo (solo si no hay duplicados)
SET @dup_codigo = 0;
SET @sql = IF(@table_exists = 1,
  'SELECT COUNT(*) INTO @dup_codigo FROM (SELECT 1 FROM `rif_ordenes` GROUP BY `ord_codigo` HAVING COUNT(*) > 1) AS d',
  'SET @dup_codigo = 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND INDEX_NAME = 'uk_orden_codigo'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0 AND @dup_codigo = 0,
  'ALTER TABLE `rif_ordenes` ADD UNIQUE KEY `uk_orden_codigo` (`ord_codigo`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.2 KEY idx_orden_rifa_estado
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND INDEX_NAME = 'idx_orden_rifa_estado'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD KEY `idx_orden_rifa_estado` (`ord_rifa_id`, `ord_estado`, `ord_created_at`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.3 KEY idx_orden_org_estado
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND INDEX_NAME = 'idx_orden_org_estado'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD KEY `idx_orden_org_estado` (`ord_org_id`, `ord_estado`, `ord_created_at`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.4 KEY idx_orden_email
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND INDEX_NAME = 'idx_orden_email'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD KEY `idx_orden_email` (`ord_comprador_email`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3.5 KEY idx_orden_orgmet
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND INDEX_NAME = 'idx_orden_orgmet'
);
SET @sql = IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD KEY `idx_orden_orgmet` (`ord_orgmet_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 4. Guardas de claves foraneas: rif_ordenes
--    Cada FK se agrega solo si la tabla destino existe y la relacion
--    aun no esta registrada para esa columna.
-- -----------------------------------------------------
SET @dep_rifas = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
);
SET @dep_orgs = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_organizadores'
);
SET @dep_orgmet = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_org_metodos'
);

-- 4.1 fk_orden_rifa
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_rifa_id'
    AND REFERENCED_TABLE_NAME = 'rif_rifas'
);
SET @sql = IF(@table_exists = 1 AND @dep_rifas = 1 AND @fk_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD CONSTRAINT `fk_orden_rifa` FOREIGN KEY (`ord_rifa_id`) REFERENCES `rif_rifas` (`rifa_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.2 fk_orden_org
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_org_id'
    AND REFERENCED_TABLE_NAME = 'rif_organizadores'
);
SET @sql = IF(@table_exists = 1 AND @dep_orgs = 1 AND @fk_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD CONSTRAINT `fk_orden_org` FOREIGN KEY (`ord_org_id`) REFERENCES `rif_organizadores` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.3 fk_orden_orgmet
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ordenes'
    AND COLUMN_NAME = 'ord_orgmet_id'
    AND REFERENCED_TABLE_NAME = 'rif_org_metodos'
);
SET @sql = IF(@table_exists = 1 AND @dep_orgmet = 1 AND @fk_exists = 0,
  'ALTER TABLE `rif_ordenes` ADD CONSTRAINT `fk_orden_orgmet` FOREIGN KEY (`ord_orgmet_id`) REFERENCES `rif_org_metodos` (`orgmet_id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 5. Indice de orden en rif_tickets (sin tocar datos)
-- -----------------------------------------------------
SET @tickets_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_tickets'
);
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND INDEX_NAME = 'idx_ticket_orden'
);
SET @sql = IF(@tickets_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE `rif_tickets` ADD KEY `idx_ticket_orden` (`tick_orden_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 6. Diagnostico final (solo lectura)
-- -----------------------------------------------------
SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes') AS rif_ordenes_exists,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes') AS rif_ordenes_columns,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND INDEX_NAME = 'uk_orden_codigo') AS uk_orden_codigo,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND INDEX_NAME = 'idx_orden_rifa_estado') AS idx_orden_rifa_estado,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND INDEX_NAME = 'idx_orden_org_estado') AS idx_orden_org_estado,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND INDEX_NAME = 'idx_orden_email') AS idx_orden_email,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND INDEX_NAME = 'idx_orden_orgmet') AS idx_orden_orgmet,
  (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND CONSTRAINT_NAME = 'fk_orden_rifa') AS fk_orden_rifa,
  (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND CONSTRAINT_NAME = 'fk_orden_org') AS fk_orden_org,
  (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_ordenes'
      AND CONSTRAINT_NAME = 'fk_orden_orgmet') AS fk_orden_orgmet,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_tickets'
      AND INDEX_NAME = 'idx_ticket_orden') AS idx_ticket_orden,
  (SELECT COUNT(*) FROM `rif_ordenes`) AS rif_ordenes_rows,
  CASE
    WHEN @table_exists = 0 THEN 'BLOCKED: rif_ordenes no existe'
    WHEN @dup_codigo > 0 THEN 'BLOCKED: ord_codigo duplicado; UNIQUE omitido'
    WHEN @tickets_exists = 0 THEN 'ACCEPTED_WITH_RISKS: rif_tickets ausente; indice omitido'
    ELSE 'OK'
  END AS migration_status;
