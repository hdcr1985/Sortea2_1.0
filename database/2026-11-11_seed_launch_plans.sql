-- =====================================================
-- Seed: Planes de lanzamiento Sortea2 - M08
-- Fecha: 2026-11-11
-- Motor: MySQL / MariaDB
-- =====================================================
-- Objetivo:
--   Dejar tres planes por rifas con precio de lanzamiento:
--     Sortea2 S1 - 100 numeros     -> regular USD 2.00 / actual USD 1.00
--     Sortea2 S2 - 1.000 numeros   -> regular USD 18.00 / actual USD 7.00
--     Sortea2 S3 - 10.000 numeros  -> regular USD 150.00 / actual USD 50.00
--
--   Para planes por rifas, `plan_precio` guarda el precio regular de lista
--   y `pnum_precio` el precio actual de lanzamiento. La UI muestra el
--   regular tachado y el actual como oferta.
--
-- Idempotente:
--   - rif_planes usa plan_nombre UNIQUE.
--   - rif_plan_numeraciones usa uk_pnum_plan_num (plan, numeracion).
--   - No elimina asignaciones, ordenes ni historial.
-- =====================================================

SET NAMES utf8mb4;

-- 1. Planes base. El nombre se mantiene corto porque la oferta visible
--    agrega la etiqueta de la numeracion: "Sortea2 S1 — 100 numeros".
INSERT INTO `rif_planes` (
  `plan_nombre`, `plan_descripcion`, `plan_tipo`, `plan_limite`,
  `plan_periodo`, `plan_precio`, `plan_moneda`, `plan_status`,
  `plan_eliminado`, `plan_created_at`, `plan_updated_at`
) VALUES
  ('Sortea2 S1', 'Ideal para comenzar', 'rifas', 100,
   'sin_limite', 2.00, 'USD', 1, 0, NOW(), NOW()),
  ('Sortea2 S2', 'Para organizadores que ya venden más', 'rifas', 1000,
   'sin_limite', 18.00, 'USD', 1, 0, NOW(), NOW()),
  ('Sortea2 S3', 'Máximo volumen al menor costo por número', 'rifas', 10000,
   'sin_limite', 150.00, 'USD', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `plan_descripcion` = VALUES(`plan_descripcion`),
  `plan_tipo`        = VALUES(`plan_tipo`),
  `plan_limite`      = VALUES(`plan_limite`),
  `plan_periodo`     = VALUES(`plan_periodo`),
  `plan_precio`      = VALUES(`plan_precio`),
  `plan_moneda`      = VALUES(`plan_moneda`),
  `plan_status`      = VALUES(`plan_status`),
  `plan_eliminado`   = VALUES(`plan_eliminado`),
  `plan_updated_at`  = NOW();

-- 2. Opciones de numeracion/precio por plan.
--    100 = num_id 1, 1.000 = num_id 2, 10.000 = num_id 3.
INSERT INTO `rif_plan_numeraciones` (
  `pnum_plan_id`, `pnum_num_id`, `pnum_precio`, `pnum_status`,
  `pnum_orden`, `pnum_created_at`, `pnum_updated_at`
)
SELECT p.`plan_id`, 1, 1.00, 1, 1, NOW(), NOW()
FROM `rif_planes` p
WHERE p.`plan_nombre` = 'Sortea2 S1'
ON DUPLICATE KEY UPDATE
  `pnum_precio`     = VALUES(`pnum_precio`),
  `pnum_status`     = VALUES(`pnum_status`),
  `pnum_orden`      = VALUES(`pnum_orden`),
  `pnum_updated_at` = NOW();

INSERT INTO `rif_plan_numeraciones` (
  `pnum_plan_id`, `pnum_num_id`, `pnum_precio`, `pnum_status`,
  `pnum_orden`, `pnum_created_at`, `pnum_updated_at`
)
SELECT p.`plan_id`, 2, 7.00, 1, 1, NOW(), NOW()
FROM `rif_planes` p
WHERE p.`plan_nombre` = 'Sortea2 S2'
ON DUPLICATE KEY UPDATE
  `pnum_precio`     = VALUES(`pnum_precio`),
  `pnum_status`     = VALUES(`pnum_status`),
  `pnum_orden`      = VALUES(`pnum_orden`),
  `pnum_updated_at` = NOW();

INSERT INTO `rif_plan_numeraciones` (
  `pnum_plan_id`, `pnum_num_id`, `pnum_precio`, `pnum_status`,
  `pnum_orden`, `pnum_created_at`, `pnum_updated_at`
)
SELECT p.`plan_id`, 3, 50.00, 1, 1, NOW(), NOW()
FROM `rif_planes` p
WHERE p.`plan_nombre` = 'Sortea2 S3'
ON DUPLICATE KEY UPDATE
  `pnum_precio`     = VALUES(`pnum_precio`),
  `pnum_status`     = VALUES(`pnum_status`),
  `pnum_orden`      = VALUES(`pnum_orden`),
  `pnum_updated_at` = NOW();
