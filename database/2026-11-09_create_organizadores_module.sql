-- =====================================================
-- Migracion: M01 Nucleo multi-tenant y subdominios
-- Modulo: Organizadores
-- Fecha: 2026-11-09
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Crea las tablas rif_organizadores y rif_dominios, registra el
-- menu dinamico y asigna los permisos CRUD exclusivamente al rol
-- Admin Master (1000).
--
-- La migracion es idempotente:
--   - CREATE TABLE IF NOT EXISTS
--   - Menu insertado solo si no existe por slug/URL
--   - Permisos insertados solo si no existen
--   - La version de permisos se invalida al final
--
-- NOTA: rif_organizadores.org_usuario_id es una referencia LOGICA a
-- cab_usuarios.usu_id. No se crea FK fisica para evitar conflictos de
-- tipo/collation entre ambas tablas.
-- =====================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- 1. Tabla de organizadores
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_organizadores` (
  `org_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `org_usuario_id` VARCHAR(100) NOT NULL COMMENT 'Referencia logica a cab_usuarios.usu_id (sin FK fisica)',
  `org_slug` VARCHAR(63) NOT NULL COMMENT 'Slug/subdominio publico del organizador',
  `org_nombre_publico` VARCHAR(150) NOT NULL COMMENT 'Nombre visible del organizador',
  `org_email` VARCHAR(150) NULL COMMENT 'Correo de contacto',
  `org_telefono` VARCHAR(30) NULL COMMENT 'Telefono de contacto',
  `org_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
  `org_verificado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = Verificado, 0 = Pendiente',
  `org_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `org_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `org_eliminado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = Visible, 1 = Borrado logico',
  PRIMARY KEY (`org_id`),
  UNIQUE KEY `uk_org_usuario` (`org_usuario_id`),
  UNIQUE KEY `uk_org_slug` (`org_slug`),
  KEY `idx_org_status` (`org_status`),
  KEY `idx_org_eliminado` (`org_eliminado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Tabla de dominios por organizador
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rif_dominios` (
  `dom_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dom_org_id` BIGINT UNSIGNED NOT NULL,
  `dom_host` VARCHAR(191) NOT NULL COMMENT 'Host completo en minusculas (ej: tusuerte.sortea2.net)',
  `dom_tipo` ENUM('subdominio','personalizado') NOT NULL DEFAULT 'subdominio',
  `dom_verificado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = Verificado, 0 = Pendiente',
  `dom_token` VARCHAR(64) NULL COMMENT 'Reservado para verificacion futura de dominio personalizado',
  `dom_ssl_status` VARCHAR(30) NULL COMMENT 'Estado SSL del dominio',
  `dom_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
  `dom_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dom_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`dom_id`),
  UNIQUE KEY `uk_dom_host` (`dom_host`),
  KEY `idx_dom_org` (`dom_org_id`),
  CONSTRAINT `fk_dom_org` FOREIGN KEY (`dom_org_id`)
    REFERENCES `rif_organizadores` (`org_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Registro del menu dinamico
-- -----------------------------------------------------
INSERT INTO `cab_menu` (
  `sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
  `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
  `men_externo`, `men_inicio`
)
SELECT
  0,   'Organizadores', 'Gestion de organizadores y subdominios',
  'Organizadores/index', 'organizadores', 12, 0, NULL, 'ki-duotone ki-people',
  CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu`
  WHERE `men_urlamigable` = 'organizadores'
     OR TRIM(BOTH '/' FROM `men_url`) = 'Organizadores/index'
);

SET @org_men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'organizadores'
     OR TRIM(BOTH '/' FROM `men_url`) = 'Organizadores/index'
  ORDER BY `men_id` ASC
  LIMIT 1
);

-- Corregir el icono en instalaciones existentes: el sidebar solo renderiza
-- los paths de KeenIcons cuando la clase incluye "ki-duotone".
UPDATE `cab_menu`
SET `men_imgmenu` = 'ki-duotone ki-people'
WHERE `men_id` = @org_men_id
  AND (`men_imgmenu` IS NULL OR `men_imgmenu` <> 'ki-duotone ki-people');

-- -----------------------------------------------------
-- 4. Permisos exclusivos para Admin Master (1000)
-- -----------------------------------------------------
INSERT INTO `cab_permisologia` (
  `mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`
)
SELECT
  CONCAT('1000_', m.`men_id`),
  1000,
  m.`men_id`,
  m.`men_tipo`,
  COALESCE((SELECT g.`per_tipo` FROM `cab_grupopermi` g WHERE g.`per_id` = 1000 LIMIT 1), 0),
  '1'
FROM `cab_menu` m
WHERE m.`men_id` = @org_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia` p
    WHERE p.`mis_permiso` = 1000 AND p.`mis_menu` = m.`men_id`
  );

INSERT INTO `cab_permiaccion` (
  `acc_id`, `acc_permiso`, `acc_consultar`, `acc_incluir`, `acc_modificar`,
  `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`
)
SELECT
  p.`mis_id`, 1000, 1, 1, 1, 1, 1, 0, 0
FROM `cab_permisologia` p
WHERE p.`mis_permiso` = 1000
  AND p.`mis_menu` = @org_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion` a WHERE a.`acc_id` = p.`mis_id`
  );

-- -----------------------------------------------------
-- 5. Invalidar el snapshot de menus de las sesiones activas
-- -----------------------------------------------------
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
