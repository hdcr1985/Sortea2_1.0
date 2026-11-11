-- =====================================================
-- Seed: Planes ilimitados Sortea2 - M08
-- Fecha: 2026-11-11
-- Motor: MySQL / MariaDB
-- =====================================================
-- Crea tres planes ilimitados:
--   Sortea2 Ilimitado Mensual    -> USD 9.99  / 1 mes
--   Sortea2 Ilimitado Semestral  -> USD 49.99 / 6 meses
--   Sortea2 Ilimitado Anual      -> USD 79.99 / 12 meses
--
-- Los planes ilimitados no usan opciones de numeracion. El organizador
-- elige una numeracion activa al crear cada rifa.
--
-- Idempotente por rif_planes.plan_nombre UNIQUE.
-- No elimina asignaciones, ordenes ni historial.
-- =====================================================

SET NAMES utf8mb4;

INSERT INTO `rif_planes` (
  `plan_nombre`, `plan_descripcion`, `plan_tipo`, `plan_limite`,
  `plan_periodo`, `plan_precio`, `plan_moneda`, `plan_status`,
  `plan_eliminado`, `plan_created_at`, `plan_updated_at`
) VALUES
  ('Sortea2 Ilimitado Mensual', 'Rifas ilimitadas durante 1 mes', 'ilimitado', 0,
   'mensual', 9.99, 'USD', 1, 0, NOW(), NOW()),
  ('Sortea2 Ilimitado Semestral', 'Rifas ilimitadas durante 6 meses', 'ilimitado', 0,
   'semestral', 49.99, 'USD', 1, 0, NOW(), NOW()),
  ('Sortea2 Ilimitado Anual', 'Rifas ilimitadas durante 12 meses. Ahorra 4 meses frente al mensual', 'ilimitado', 0,
   'anual', 79.99, 'USD', 1, 0, NOW(), NOW())
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
