-- =====================================================
-- Migracion: Agrupar Organizadores y Rifas bajo Administracion
-- Fecha: 2026-11-11
-- Motor: MySQL / MariaDB
-- =====================================================
-- Objetivo:
--   Crear el menu padre "Administración" y mover a sus hijos:
--     - Organizadores (organizadores)
--     - Rifas (rifas)
--
-- La migracion es idempotente:
--   - El padre se inserta solo si no existe por slug.
--   - Los hijos se actualizan por slug.
--   - Los permisos del padre se insertan solo para el rol 1000.
--   - No se eliminan menus ni se tocan los permisos existentes de los hijos.
-- =====================================================

SET NAMES utf8mb4;

-- 1. Crear menu padre si no existe.
INSERT INTO `cab_menu` (
  `sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`,
  `men_urlamigable`, `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`,
  `men_fecha`, `men_hora`, `men_externo`, `men_inicio`
)
SELECT
  0,
  'Administración',
  'Organizadores y moderación de rifas',
  '#',
  'administracion',
  9,
  0,
  NULL,
  'ki-duotone ki-shield-tick',
  CURDATE(),
  CURTIME(),
  '0',
  0
WHERE NOT EXISTS (
  SELECT 1 FROM `cab_menu`
  WHERE `men_urlamigable` = 'administracion'
);

SET @admin_men_id = (
  SELECT `men_id` FROM `cab_menu`
  WHERE `men_urlamigable` = 'administracion'
  ORDER BY `men_id` ASC
  LIMIT 1
);

-- 2. Mover los hijos bajo el padre.
UPDATE `cab_menu`
SET
  `sub_men_id` = @admin_men_id,
  `men_tipo`   = 1,
  `men_orden`  = 1
WHERE `men_urlamigable` = 'organizadores';

UPDATE `cab_menu`
SET
  `sub_men_id` = @admin_men_id,
  `men_tipo`   = 1,
  `men_orden`  = 2
WHERE `men_urlamigable` = 'rifas';

-- 3. Permiso del padre para el rol Admin Master (1000).
INSERT INTO `cab_permisologia` (
  `mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`
)
SELECT
  CONCAT('1000_', @admin_men_id),
  1000,
  @admin_men_id,
  0,
  0,
  '1'
WHERE @admin_men_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia`
    WHERE `mis_id` = CONCAT('1000_', @admin_men_id)
  );

INSERT INTO `cab_permiaccion` (
  `acc_id`, `acc_permiso`, `acc_consultar`, `acc_incluir`,
  `acc_modificar`, `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`
)
SELECT
  CONCAT('1000_', @admin_men_id),
  1000,
  1, 1, 1, 1, 1, 0, 0
WHERE @admin_men_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion`
    WHERE `acc_id` = CONCAT('1000_', @admin_men_id)
  );

-- 4. Invalidar snapshots de menu de sesiones activas.
INSERT INTO `cab_configuracion_general`
  (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
  ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `cfg_valor` = VALUES(`cfg_valor`),
  `cfg_updated_at` = NOW();
