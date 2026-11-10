-- =====================================================
-- Migracion: M05 Metodos de pago dinamicos
-- Modulo: MetodosPago
-- Fecha: 2026-11-10
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   1. Crear el catalogo de metodos de pago (rif_metodos_pago), con
--      alcance global u organizador.
--   2. Crear la definicion dinamica de campos por metodo
--      (rif_metodo_campos).
--   3. Crear la habilitacion de metodos por organizador
--      (rif_org_metodos) y los valores capturados
--      (rif_org_metodo_valores).
--   4. Crear la asignacion de metodos habilitados por rifa
--      (rif_rifa_metodos).
--   5. Sembrar 6 metodos globales y sus campos.
--   6. Registrar el menu dinamico y los permisos CRUD exclusivos del
--      rol Admin Master (1000).
--
-- Dependencias:
--   - rif_organizadores (M01) para met_org_id, orgmet_org_id.
--   - rif_rifas (M03) para rm_rifa_id.
--   - cab_menu / cab_permisologia / cab_permiaccion para el RBAC.
--
-- Idempotencia:
--   - CREATE TABLE IF NOT EXISTS.
--   - Indices, UNIQUE y FK con guardas contra information_schema.
--   - Seeds de metodos con NOT EXISTS por scope + nombre.
--   - Seeds de campos con NOT EXISTS por metodo + clave.
--   - Menu y permisos con NOT EXISTS.
--   - Reejecutar no duplica filas ni objetos de esquema.
--
-- NOTA: met_created_by es una referencia LOGICA a cab_usuarios.usu_id.
-- No se crea FK fisica para evitar conflictos de tipo/collation.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Catalogo de metodos de pago
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_metodos_pago` (
  `met_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `met_scope` ENUM('global','organizador') NOT NULL DEFAULT 'global',
  `met_org_id` BIGINT UNSIGNED NULL COMMENT 'Organizador dueno del metodo; NULL para metodos globales',
  `met_nombre` VARCHAR(120) NOT NULL,
  `met_descripcion` VARCHAR(255) NULL,
  `met_instrucciones` TEXT NULL,
  `met_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `met_orden` INT NOT NULL DEFAULT 1,
  `met_created_by` BIGINT UNSIGNED NULL COMMENT 'Referencia logica a cab_usuarios.usu_id (sin FK fisica)',
  `met_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `met_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `met_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`met_id`),
  KEY `idx_met_scope` (`met_scope`, `met_status`, `met_eliminado`, `met_orden`),
  KEY `idx_met_org` (`met_org_id`, `met_status`, `met_eliminado`),
  CONSTRAINT `fk_met_org` FOREIGN KEY (`met_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Campos dinamicos por metodo
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_metodo_campos` (
  `campo_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campo_met_id` BIGINT UNSIGNED NOT NULL,
  `campo_clave` VARCHAR(60) NOT NULL,
  `campo_etiqueta` VARCHAR(120) NOT NULL,
  `campo_tipo` ENUM('text','email','tel','number','url','textarea','select') NOT NULL DEFAULT 'text',
  `campo_requerido` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `campo_placeholder` VARCHAR(150) NULL,
  `campo_ayuda` VARCHAR(255) NULL,
  `campo_opciones` TEXT NULL COMMENT 'Opciones separadas por salto de linea para select',
  `campo_orden` INT NOT NULL DEFAULT 1,
  `campo_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `campo_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `campo_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`campo_id`),
  UNIQUE KEY `uk_campo_met_clave` (`campo_met_id`, `campo_clave`),
  KEY `idx_campo_met_orden` (`campo_met_id`, `campo_status`, `campo_orden`),
  CONSTRAINT `fk_campo_metodo` FOREIGN KEY (`campo_met_id`)
    REFERENCES `rif_metodos_pago` (`met_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Metodos habilitados por organizador
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_org_metodos` (
  `orgmet_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orgmet_org_id` BIGINT UNSIGNED NOT NULL,
  `orgmet_met_id` BIGINT UNSIGNED NOT NULL,
  `orgmet_activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `orgmet_orden` INT NOT NULL DEFAULT 1,
  `orgmet_notas` VARCHAR(255) NULL,
  `orgmet_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `orgmet_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`orgmet_id`),
  UNIQUE KEY `uk_org_metodo` (`orgmet_org_id`, `orgmet_met_id`),
  KEY `idx_orgmet_org` (`orgmet_org_id`, `orgmet_activo`, `orgmet_orden`),
  KEY `idx_orgmet_met` (`orgmet_met_id`),
  CONSTRAINT `fk_orgmet_org` FOREIGN KEY (`orgmet_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_orgmet_metodo` FOREIGN KEY (`orgmet_met_id`)
    REFERENCES `rif_metodos_pago` (`met_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Valores capturados por organizador y campo
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_org_metodo_valores` (
  `val_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `val_orgmet_id` BIGINT UNSIGNED NOT NULL,
  `val_campo_id` BIGINT UNSIGNED NOT NULL,
  `val_valor` TEXT NULL,
  `val_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `val_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`val_id`),
  UNIQUE KEY `uk_valor_orgmet_campo` (`val_orgmet_id`, `val_campo_id`),
  KEY `idx_valor_campo` (`val_campo_id`),
  CONSTRAINT `fk_valor_orgmet` FOREIGN KEY (`val_orgmet_id`)
    REFERENCES `rif_org_metodos` (`orgmet_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_valor_campo` FOREIGN KEY (`val_campo_id`)
    REFERENCES `rif_metodo_campos` (`campo_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. Metodos asignados a cada rifa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_rifa_metodos` (
  `rm_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rm_rifa_id` BIGINT UNSIGNED NOT NULL,
  `rm_orgmet_id` BIGINT UNSIGNED NOT NULL,
  `rm_orden` INT NOT NULL DEFAULT 1,
  `rm_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rm_id`),
  UNIQUE KEY `uk_rifa_metodo` (`rm_rifa_id`, `rm_orgmet_id`),
  KEY `idx_rm_orgmet` (`rm_orgmet_id`),
  CONSTRAINT `fk_rm_rifa` FOREIGN KEY (`rm_rifa_id`)
    REFERENCES `rif_rifas` (`rifa_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_rm_orgmet` FOREIGN KEY (`rm_orgmet_id`)
    REFERENCES `rif_org_metodos` (`orgmet_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. Guardas de esquema: rif_metodos_pago
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodos_pago'
    AND INDEX_NAME = 'idx_met_scope'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_metodos_pago` ADD KEY `idx_met_scope` (`met_scope`, `met_status`, `met_eliminado`, `met_orden`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodos_pago'
    AND INDEX_NAME = 'idx_met_org'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_metodos_pago` ADD KEY `idx_met_org` (`met_org_id`, `met_status`, `met_eliminado`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodos_pago'
    AND COLUMN_NAME = 'met_org_id'
    AND REFERENCED_TABLE_NAME = 'rif_organizadores'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_metodos_pago` ADD CONSTRAINT `fk_met_org` FOREIGN KEY (`met_org_id`) REFERENCES `rif_organizadores` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 7. Guardas de esquema: rif_metodo_campos
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodo_campos'
    AND INDEX_NAME = 'uk_campo_met_clave'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_metodo_campos` ADD UNIQUE KEY `uk_campo_met_clave` (`campo_met_id`, `campo_clave`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodo_campos'
    AND INDEX_NAME = 'idx_campo_met_orden'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_metodo_campos` ADD KEY `idx_campo_met_orden` (`campo_met_id`, `campo_status`, `campo_orden`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_metodo_campos'
    AND COLUMN_NAME = 'campo_met_id'
    AND REFERENCED_TABLE_NAME = 'rif_metodos_pago'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_metodo_campos` ADD CONSTRAINT `fk_campo_metodo` FOREIGN KEY (`campo_met_id`) REFERENCES `rif_metodos_pago` (`met_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 8. Guardas de esquema: rif_org_metodos
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodos'
    AND INDEX_NAME = 'uk_org_metodo'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_org_metodos` ADD UNIQUE KEY `uk_org_metodo` (`orgmet_org_id`, `orgmet_met_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodos'
    AND INDEX_NAME = 'idx_orgmet_org'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_org_metodos` ADD KEY `idx_orgmet_org` (`orgmet_org_id`, `orgmet_activo`, `orgmet_orden`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodos'
    AND INDEX_NAME = 'idx_orgmet_met'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_org_metodos` ADD KEY `idx_orgmet_met` (`orgmet_met_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodos'
    AND COLUMN_NAME = 'orgmet_org_id'
    AND REFERENCED_TABLE_NAME = 'rif_organizadores'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_org_metodos` ADD CONSTRAINT `fk_orgmet_org` FOREIGN KEY (`orgmet_org_id`) REFERENCES `rif_organizadores` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodos'
    AND COLUMN_NAME = 'orgmet_met_id'
    AND REFERENCED_TABLE_NAME = 'rif_metodos_pago'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_org_metodos` ADD CONSTRAINT `fk_orgmet_metodo` FOREIGN KEY (`orgmet_met_id`) REFERENCES `rif_metodos_pago` (`met_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 9. Guardas de esquema: rif_org_metodo_valores
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodo_valores'
    AND INDEX_NAME = 'uk_valor_orgmet_campo'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_org_metodo_valores` ADD UNIQUE KEY `uk_valor_orgmet_campo` (`val_orgmet_id`, `val_campo_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodo_valores'
    AND INDEX_NAME = 'idx_valor_campo'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_org_metodo_valores` ADD KEY `idx_valor_campo` (`val_campo_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodo_valores'
    AND COLUMN_NAME = 'val_orgmet_id'
    AND REFERENCED_TABLE_NAME = 'rif_org_metodos'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_org_metodo_valores` ADD CONSTRAINT `fk_valor_orgmet` FOREIGN KEY (`val_orgmet_id`) REFERENCES `rif_org_metodos` (`orgmet_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_org_metodo_valores'
    AND COLUMN_NAME = 'val_campo_id'
    AND REFERENCED_TABLE_NAME = 'rif_metodo_campos'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_org_metodo_valores` ADD CONSTRAINT `fk_valor_campo` FOREIGN KEY (`val_campo_id`) REFERENCES `rif_metodo_campos` (`campo_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 10. Guardas de esquema: rif_rifa_metodos
-- -----------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifa_metodos'
    AND INDEX_NAME = 'uk_rifa_metodo'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_rifa_metodos` ADD UNIQUE KEY `uk_rifa_metodo` (`rm_rifa_id`, `rm_orgmet_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifa_metodos'
    AND INDEX_NAME = 'idx_rm_orgmet'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `rif_rifa_metodos` ADD KEY `idx_rm_orgmet` (`rm_orgmet_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifa_metodos'
    AND COLUMN_NAME = 'rm_rifa_id'
    AND REFERENCED_TABLE_NAME = 'rif_rifas'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_rifa_metodos` ADD CONSTRAINT `fk_rm_rifa` FOREIGN KEY (`rm_rifa_id`) REFERENCES `rif_rifas` (`rifa_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rif_rifa_metodos'
    AND COLUMN_NAME = 'rm_orgmet_id'
    AND REFERENCED_TABLE_NAME = 'rif_org_metodos'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rif_rifa_metodos` ADD CONSTRAINT `fk_rm_orgmet` FOREIGN KEY (`rm_orgmet_id`) REFERENCES `rif_org_metodos` (`orgmet_id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 11. Seed de metodos globales (solo si no existe scope+nombre)
-- -----------------------------------------------------
INSERT INTO `rif_metodos_pago`
  (`met_scope`, `met_org_id`, `met_nombre`, `met_descripcion`, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`)
SELECT 'global', NULL, 'Transferencia bancaria', 'Transferencia o depósito bancario', 1, 1, NULL, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Transferencia bancaria'
);

INSERT INTO `rif_metodos_pago`
  (`met_scope`, `met_org_id`, `met_nombre`, `met_descripcion`, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`)
SELECT 'global', NULL, 'Pago móvil', 'Pago móvil bancario', 1, 2, NULL, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Pago móvil'
);

INSERT INTO `rif_metodos_pago`
  (`met_scope`, `met_org_id`, `met_nombre`, `met_descripcion`, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`)
SELECT 'global', NULL, 'Zelle', 'Transferencia Zelle', 1, 3, NULL, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Zelle'
);

INSERT INTO `rif_metodos_pago`
  (`met_scope`, `met_org_id`, `met_nombre`, `met_descripcion`, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`)
SELECT 'global', NULL, 'Binance Pay', 'Pago con Binance Pay', 1, 4, NULL, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Binance Pay'
);

INSERT INTO `rif_metodos_pago`
  (`met_scope`, `met_org_id`, `met_nombre`, `met_descripcion`, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`)
SELECT 'global', NULL, 'PayPal', 'Pago con PayPal', 1, 5, NULL, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'PayPal'
);

INSERT INTO `rif_metodos_pago`
  (`met_scope`, `met_org_id`, `met_nombre`, `met_descripcion`, `met_status`, `met_orden`, `met_created_by`, `met_eliminado`)
SELECT 'global', NULL, 'Efectivo', 'Pago en efectivo', 1, 6, NULL, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Efectivo'
);

-- -----------------------------------------------------
-- 12. Resolucion de IDs de los metodos sembrados
-- -----------------------------------------------------
SET @met_transferencia = (
  SELECT `met_id` FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Transferencia bancaria'
  ORDER BY `met_id` ASC LIMIT 1
);

SET @met_pago_movil = (
  SELECT `met_id` FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Pago móvil'
  ORDER BY `met_id` ASC LIMIT 1
);

SET @met_zelle = (
  SELECT `met_id` FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Zelle'
  ORDER BY `met_id` ASC LIMIT 1
);

SET @met_binance = (
  SELECT `met_id` FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Binance Pay'
  ORDER BY `met_id` ASC LIMIT 1
);

SET @met_paypal = (
  SELECT `met_id` FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'PayPal'
  ORDER BY `met_id` ASC LIMIT 1
);

SET @met_efectivo = (
  SELECT `met_id` FROM `rif_metodos_pago`
  WHERE `met_scope` = 'global' AND `met_nombre` = 'Efectivo'
  ORDER BY `met_id` ASC LIMIT 1
);

-- -----------------------------------------------------
-- 13. Seed de campos: Transferencia bancaria
-- -----------------------------------------------------
INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_transferencia, 'banco', 'Banco', 'text', 1, NULL, NULL, NULL, 1, 1
WHERE @met_transferencia IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_transferencia AND `campo_clave` = 'banco'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_transferencia, 'titular', 'Titular', 'text', 1, NULL, NULL, NULL, 2, 1
WHERE @met_transferencia IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_transferencia AND `campo_clave` = 'titular'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_transferencia, 'numero_cuenta', 'Número de cuenta', 'text', 1, NULL, NULL, NULL, 3, 1
WHERE @met_transferencia IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_transferencia AND `campo_clave` = 'numero_cuenta'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_transferencia, 'tipo_cuenta', 'Tipo de cuenta', 'select', 1, NULL, NULL, CONCAT('Ahorro', CHAR(10), 'Corriente'), 4, 1
WHERE @met_transferencia IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_transferencia AND `campo_clave` = 'tipo_cuenta'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_transferencia, 'documento_identidad', 'Documento de identidad', 'text', 0, NULL, NULL, NULL, 5, 1
WHERE @met_transferencia IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_transferencia AND `campo_clave` = 'documento_identidad'
  );

-- -----------------------------------------------------
-- 14. Seed de campos: Pago movil
-- -----------------------------------------------------
INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_pago_movil, 'banco', 'Banco', 'text', 1, NULL, NULL, NULL, 1, 1
WHERE @met_pago_movil IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_pago_movil AND `campo_clave` = 'banco'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_pago_movil, 'telefono', 'Teléfono', 'tel', 1, NULL, NULL, NULL, 2, 1
WHERE @met_pago_movil IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_pago_movil AND `campo_clave` = 'telefono'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_pago_movil, 'cedula_rif', 'Cédula/RIF', 'text', 1, NULL, NULL, NULL, 3, 1
WHERE @met_pago_movil IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_pago_movil AND `campo_clave` = 'cedula_rif'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_pago_movil, 'codigo_bancario', 'Código bancario', 'text', 0, NULL, NULL, NULL, 4, 1
WHERE @met_pago_movil IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_pago_movil AND `campo_clave` = 'codigo_bancario'
  );

-- -----------------------------------------------------
-- 15. Seed de campos: Zelle
-- -----------------------------------------------------
INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_zelle, 'correo_telefono', 'Correo o teléfono', 'text', 1, NULL, NULL, NULL, 1, 1
WHERE @met_zelle IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_zelle AND `campo_clave` = 'correo_telefono'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_zelle, 'titular', 'Titular', 'text', 1, NULL, NULL, NULL, 2, 1
WHERE @met_zelle IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_zelle AND `campo_clave` = 'titular'
  );

-- -----------------------------------------------------
-- 16. Seed de campos: Binance Pay
-- -----------------------------------------------------
INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_binance, 'binance_id', 'ID de Binance', 'text', 1, NULL, NULL, NULL, 1, 1
WHERE @met_binance IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_binance AND `campo_clave` = 'binance_id'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_binance, 'titular', 'Titular', 'text', 1, NULL, NULL, NULL, 2, 1
WHERE @met_binance IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_binance AND `campo_clave` = 'titular'
  );

-- -----------------------------------------------------
-- 17. Seed de campos: PayPal
-- -----------------------------------------------------
INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_paypal, 'correo_paypal', 'Correo PayPal', 'email', 1, NULL, NULL, NULL, 1, 1
WHERE @met_paypal IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_paypal AND `campo_clave` = 'correo_paypal'
  );

INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_paypal, 'titular', 'Titular', 'text', 1, NULL, NULL, NULL, 2, 1
WHERE @met_paypal IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_paypal AND `campo_clave` = 'titular'
  );

-- -----------------------------------------------------
-- 18. Seed de campos: Efectivo
-- -----------------------------------------------------
INSERT INTO `rif_metodo_campos`
  (`campo_met_id`, `campo_clave`, `campo_etiqueta`, `campo_tipo`, `campo_requerido`, `campo_placeholder`, `campo_ayuda`, `campo_opciones`, `campo_orden`, `campo_status`)
SELECT @met_efectivo, 'instrucciones', 'Instrucciones', 'textarea', 1, NULL, NULL, NULL, 1, 1
WHERE @met_efectivo IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `rif_metodo_campos`
    WHERE `campo_met_id` = @met_efectivo AND `campo_clave` = 'instrucciones'
  );

-- -----------------------------------------------------
-- 19. Registro del menu dinamico
-- -----------------------------------------------------
INSERT INTO `cab_menu` (
  `sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
  `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
  `men_externo`, `men_inicio`
)
SELECT
  0, 'Métodos de Pago', 'Métodos de pago globales y campos',
  'MetodosPago/index', 'metodos-pago', 16, 0, NULL, 'ki-duotone ki-bill',
  CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu` WHERE `men_urlamigable` = 'metodos-pago'
);

SET @men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'metodos-pago'
  ORDER BY `men_id` ASC
  LIMIT 1
);

-- -----------------------------------------------------
-- 20. Permisos exclusivos para Admin Master (1000)
-- -----------------------------------------------------
INSERT INTO `cab_permisologia` (
  `mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`
)
SELECT
  CONCAT('1000_', @men_id), 1000, @men_id, 0, 0, '1'
WHERE @men_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia`
    WHERE `mis_permiso` = 1000 AND `mis_menu` = @men_id
  );

INSERT INTO `cab_permiaccion` (
  `acc_id`, `acc_permiso`, `acc_incluir`, `acc_consultar`, `acc_modificar`,
  `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`
)
SELECT
  CONCAT('1000_', @men_id), 1000, 1, 1, 1, 1, 1, 0, 0
WHERE @men_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion`
    WHERE `acc_id` = CONCAT('1000_', @men_id)
  );

-- -----------------------------------------------------
-- 21. Invalidar el snapshot de menus de las sesiones activas
-- -----------------------------------------------------
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
