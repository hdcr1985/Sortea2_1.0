-- =====================================================
-- Backfill M03.1: lotes legacy sin numeración
-- =====================================================
-- Objetivo:
--   1. Crear una opción de numeración para planes por rifas
--      que fueron creados antes de M02.1 y no tienen opciones.
--   2. Asignar asig_num_id/precio/total a lotes legacy.
--   3. Vincular rifas ya consumidas con su lote y numeración.
--
-- Es idempotente: solo actúa donde faltan datos.
-- =====================================================

-- 1. Opción de numeración para planes legacy sin opciones.
--    Toma la numeración de la rifa más reciente del plan o, si no
--    existe, la primera numeración del catálogo (100 números).
INSERT INTO `rif_plan_numeraciones` (
  `pnum_plan_id`, `pnum_num_id`, `pnum_precio`, `pnum_status`,
  `pnum_orden`, `pnum_created_at`, `pnum_updated_at`
)
SELECT
  p.`plan_id`,
  COALESCE(
    (
      SELECT r.`rifa_num_id`
      FROM `rif_rifas` r
      INNER JOIN `rif_plan_asignaciones` a ON a.`asig_id` = r.`rifa_asig_id`
      WHERE a.`asig_plan_id` = p.`plan_id`
        AND r.`rifa_num_id` IS NOT NULL
      ORDER BY r.`rifa_id` DESC
      LIMIT 1
    ),
    (
      SELECT n.`num_id`
      FROM `rif_numeraciones` n
      INNER JOIN `rif_rifas` r
        ON n.`num_desde` = r.`rifa_numero_desde`
       AND n.`num_hasta` = r.`rifa_numero_hasta`
       AND n.`num_padding` = r.`rifa_padding`
      INNER JOIN `rif_plan_asignaciones` a ON a.`asig_org_id` = r.`rifa_org_id`
      WHERE a.`asig_plan_id` = p.`plan_id`
      ORDER BY r.`rifa_id` DESC
      LIMIT 1
    ),
    1
  ),
  p.`plan_precio`,
  1,
  1,
  NOW(),
  NOW()
FROM `rif_planes` p
WHERE p.`plan_tipo` = 'rifas'
  AND p.`plan_eliminado` = 0
  AND NOT EXISTS (
    SELECT 1
    FROM `rif_plan_numeraciones` pn
    WHERE pn.`pnum_plan_id` = p.`plan_id`
  );

-- 2. Completar lotes legacy limitados con la primera opción activa del plan.
UPDATE `rif_plan_asignaciones` a
INNER JOIN (
  SELECT
    `pnum_plan_id`,
    MIN(`pnum_num_id`) AS `num_id`,
    MIN(`pnum_precio`) AS `precio`
  FROM `rif_plan_numeraciones`
  WHERE `pnum_status` = 1
  GROUP BY `pnum_plan_id`
) o ON o.`pnum_plan_id` = a.`asig_plan_id`
SET
  a.`asig_num_id` = COALESCE(a.`asig_num_id`, o.`num_id`),
  a.`asig_precio_unitario` = IF(a.`asig_precio_unitario` = 0, o.`precio`, a.`asig_precio_unitario`),
  a.`asig_total` = IF(
    a.`asig_total` = 0 AND a.`asig_cantidad` IS NOT NULL,
    o.`precio` * a.`asig_cantidad`,
    a.`asig_total`
  )
WHERE a.`asig_num_id` IS NULL
  AND a.`asig_cantidad` IS NOT NULL;

-- 3. Vincular rifas ya consumidas con su lote y numeración.
UPDATE `rif_rifas` r
INNER JOIN `rif_plan_consumos` c
  ON c.`cons_rifa_id` = r.`rifa_id`
 AND c.`cons_estado` = 'consumido'
INNER JOIN `rif_plan_asignaciones` a ON a.`asig_id` = c.`cons_asig_id`
SET
  r.`rifa_asig_id` = COALESCE(r.`rifa_asig_id`, c.`cons_asig_id`),
  r.`rifa_num_id` = COALESCE(r.`rifa_num_id`, a.`asig_num_id`)
WHERE r.`rifa_asig_id` IS NULL
   OR r.`rifa_num_id` IS NULL;
