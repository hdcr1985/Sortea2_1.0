-- =====================================================
-- Migración: Módulo Historial de Transacciones
-- Fecha: 2026-11-03
-- =====================================================
-- Historial de solo lectura. El menú se asigna únicamente al rol Admin
-- Master (1000); otros roles se habilitan desde /permisos.
-- =====================================================

INSERT INTO `cab_menu`
    (`sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
     `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
     `men_externo`, `men_inicio`)
SELECT
    0,
    'Historial de Transacciones',
    'Historial detallado de compras de créditos y transacciones de pago',
    'OrderHistory/index',
    'historial-de-transacciones',
    13,
    0,
    NULL,
    'ki-duotone ki-time',
    CURDATE(),
    CURTIME(),
    '0',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM `cab_menu`
    WHERE `men_urlamigable` = 'historial-de-transacciones'
       OR TRIM(BOTH '/' FROM `men_url`) = 'OrderHistory/index'
);

SET @order_history_men_id = (
    SELECT `men_id` FROM `cab_menu`
    WHERE `men_urlamigable` = 'historial-de-transacciones'
       OR TRIM(BOTH '/' FROM `men_url`) = 'OrderHistory/index'
    ORDER BY `men_id` ASC
    LIMIT 1
);

INSERT INTO `cab_permisologia`
    (`mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`)
SELECT
    CONCAT('1000_', m.`men_id`),
    1000,
    m.`men_id`,
    m.`men_tipo`,
    COALESCE((SELECT g.`per_tipo` FROM `cab_grupopermi` g WHERE g.`per_id` = 1000 LIMIT 1), 0),
    '1'
FROM `cab_menu` m
WHERE m.`men_id` = @order_history_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia` p
    WHERE p.`mis_permiso` = 1000 AND p.`mis_menu` = m.`men_id`
  );

INSERT INTO `cab_permiaccion`
    (`acc_id`, `acc_permiso`, `acc_incluir`, `acc_consultar`, `acc_modificar`,
     `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`)
SELECT
    p.`mis_id`, 1000, 0, 1, 0, 0, 0, 0, 0
FROM `cab_permisologia` p
WHERE p.`mis_permiso` = 1000
  AND p.`mis_menu` = @order_history_men_id
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion` a WHERE a.`acc_id` = p.`mis_id`
  );

INSERT INTO `cab_configuracion_general`
    (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
    ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `cfg_valor` = VALUES(`cfg_valor`),
    `cfg_updated_at` = NOW();

SELECT men_id, men_nombre, men_url, men_urlamigable, men_tipo, men_orden
FROM `cab_menu`
WHERE men_urlamigable = 'historial-de-transacciones';
