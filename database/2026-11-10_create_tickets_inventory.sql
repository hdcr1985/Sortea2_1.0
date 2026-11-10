-- =====================================================
-- Migracion: M03.3 Inventario de tickets, bloqueos y venta presencial
-- Modulo: Rifas
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Extender rif_rifas con el modo de asignacion y la visibilidad
--      de reservados/bloqueados.
--   2. Crear el inventario de numeros ocupados (rif_tickets) con sus
--      estados y trazabilidad de comprador/orden.
--   3. Crear el historial de movimientos (rif_ticket_historial).
--
-- Dependencias:
--   - rif_rifas (M03) y sus columnas de numeracion
--     (rifa_numero_desde, rifa_numero_hasta, rifa_padding,
--     rifa_total_numeros) y rifa_imagen.
--   - Las columnas tick_orden_id / tick_comprador_id son referencias
--     LOGICAS a las ordenes y compradores de M07. No se agregan FK
--     porque esas tablas aun no forman parte de este esquema.
--
-- Modelo de datos:
--   - El inventario SOLO guarda numeros ocupados (reservado, pagado
--     o bloqueado). NO se precargan filas para todos los numeros
--     disponibles; la disponibilidad se calcula entre
--     rifa_numero_desde/rifa_numero_hasta menos las filas ocupadas.
--   - tick_numero guarda el numero con padding (ej. 0010) y
--     tick_numero_int el entero sin padding para rangos y orden.
--
-- Idempotencia:
--   - CREATE TABLE IF NOT EXISTS para ambas tablas.
--   - Columnas nuevas de rif_rifas validadas contra
--     information_schema.COLUMNS antes del ALTER.
--   - Indices, UNIQUE y FKs con guardas para instalaciones donde la
--     tabla ya exista de forma parcial.
--   - Reejecutar no duplica filas ni objetos de esquema.
--
-- Nota:
--   Las reglas de negocio (bloqueo por rangos/listas, liberacion,
--   venta presencial, asignacion aleatoria, unicidad concurrente y
--   transiciones de estado) se implementan en la capa backend. Aqui
--   solo se define el esquema.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Columnas nuevas en rif_rifas
-- -----------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_modo_asignacion'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_modo_asignacion` ENUM(''manual'',''automatica'') NOT NULL DEFAULT ''manual'' COMMENT ''automatica = asignación aleatoria al comprar'' AFTER `rifa_reserva_auto`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rif_rifas'
    AND COLUMN_NAME = 'rifa_mostrar_reservados'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `rif_rifas` ADD COLUMN `rifa_mostrar_reservados` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''1 = mostrar reservados/bloqueados; 0 = mostrar solo disponibles'' AFTER `rifa_modo_asignacion`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2. Inventario de numeros ocupados
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_tickets` (
  `tick_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tick_rifa_id` BIGINT UNSIGNED NOT NULL,
  `tick_numero` VARCHAR(20) NOT NULL COMMENT 'Número con padding, ej. 0010',
  `tick_numero_int` INT UNSIGNED NOT NULL COMMENT 'Número sin padding para rangos/orden',
  `tick_estado` ENUM('reservado','pagado','bloqueado') NOT NULL,
  `tick_orden_id` BIGINT UNSIGNED NULL COMMENT 'Orden de compra (M07)',
  `tick_comprador_id` BIGINT UNSIGNED NULL COMMENT 'Comprador (M07)',
  `tick_comprador_nombre` VARCHAR(150) NULL COMMENT 'Nombre para venta presencial',
  `tick_precio` DECIMAL(12,2) NULL,
  `tick_moneda` CHAR(3) NULL,
  `tick_motivo` VARCHAR(255) NULL,
  `tick_reservado_at` DATETIME NULL,
  `tick_pagado_at` DATETIME NULL,
  `tick_liberado_at` DATETIME NULL,
  `tick_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tick_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tick_id`),
  UNIQUE KEY `uk_ticket_rifa_numero` (`tick_rifa_id`, `tick_numero`),
  KEY `idx_ticket_rifa_estado` (`tick_rifa_id`, `tick_estado`),
  KEY `idx_ticket_rifa_numero_int` (`tick_rifa_id`, `tick_numero_int`),
  CONSTRAINT `fk_ticket_rifa` FOREIGN KEY (`tick_rifa_id`)
    REFERENCES `rif_rifas` (`rifa_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Historial de movimientos del ticket
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_ticket_historial` (
  `hist_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hist_rifa_id` BIGINT UNSIGNED NOT NULL,
  `hist_numero` VARCHAR(20) NOT NULL,
  `hist_orden_id` BIGINT UNSIGNED NULL,
  `hist_accion` ENUM('reservado','pagado','bloqueado','liberado','rechazado','expirado','venta_presencial') NOT NULL,
  `hist_actor` VARCHAR(100) NULL,
  `hist_motivo` VARCHAR(255) NULL,
  `hist_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hist_id`),
  KEY `idx_hist_rifa_numero` (`hist_rifa_id`, `hist_numero`),
  KEY `idx_hist_rifa_fecha` (`hist_rifa_id`, `hist_at`),
  CONSTRAINT `fk_hist_rifa` FOREIGN KEY (`hist_rifa_id`)
    REFERENCES `rif_rifas` (`rifa_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Guardas de esquema: rif_tickets
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND INDEX_NAME = 'uk_ticket_rifa_numero'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_tickets` ADD UNIQUE KEY `uk_ticket_rifa_numero` (`tick_rifa_id`, `tick_numero`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND INDEX_NAME = 'idx_ticket_rifa_estado'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_tickets` ADD KEY `idx_ticket_rifa_estado` (`tick_rifa_id`, `tick_estado`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND INDEX_NAME = 'idx_ticket_rifa_numero_int'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_tickets` ADD KEY `idx_ticket_rifa_numero_int` (`tick_rifa_id`, `tick_numero_int`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_tickets'
    AND COLUMN_NAME = 'tick_rifa_id'
    AND REFERENCED_TABLE_NAME = 'rif_rifas'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_tickets` ADD CONSTRAINT `fk_ticket_rifa` FOREIGN KEY (`tick_rifa_id`) REFERENCES `rif_rifas` (`rifa_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 5. Guardas de esquema: rif_ticket_historial
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ticket_historial'
    AND INDEX_NAME = 'idx_hist_rifa_numero'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_ticket_historial` ADD KEY `idx_hist_rifa_numero` (`hist_rifa_id`, `hist_numero`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ticket_historial'
    AND INDEX_NAME = 'idx_hist_rifa_fecha'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_ticket_historial` ADD KEY `idx_hist_rifa_fecha` (`hist_rifa_id`, `hist_at`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_ticket_historial'
    AND COLUMN_NAME = 'hist_rifa_id'
    AND REFERENCED_TABLE_NAME = 'rif_rifas'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_ticket_historial` ADD CONSTRAINT `fk_hist_rifa` FOREIGN KEY (`hist_rifa_id`) REFERENCES `rif_rifas` (`rifa_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
