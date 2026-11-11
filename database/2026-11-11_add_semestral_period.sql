-- =====================================================
-- Migracion: Periodo semestral para planes ilimitados
-- Fecha: 2026-11-11
-- Motor: MySQL / MariaDB
-- =====================================================
-- Agrega 'semestral' a los ENUM de periodo en:
--   - rif_planes.plan_periodo
--   - cab_planes.pla_rif_periodo
--   - rif_plan_compras.compra_periodo
--
-- Es idempotente: MODIFY COLUMN deja la misma definicion si ya se aplico.
-- No borra datos ni cambia registros existentes.
-- =====================================================

SET NAMES utf8mb4;

ALTER TABLE `rif_planes`
  MODIFY COLUMN `plan_periodo`
    ENUM('sin_limite','mensual','semestral','anual')
    NOT NULL DEFAULT 'sin_limite'
    COMMENT 'Periodo de vigencia del cupo';

ALTER TABLE `cab_planes`
  MODIFY COLUMN `pla_rif_periodo`
    ENUM('sin_limite','mensual','semestral','anual')
    NOT NULL DEFAULT 'sin_limite'
    COMMENT 'Periodo de vigencia de la oferta de plan';

ALTER TABLE `rif_plan_compras`
  MODIFY COLUMN `compra_periodo`
    ENUM('sin_limite','mensual','semestral','anual')
    NOT NULL DEFAULT 'sin_limite';
