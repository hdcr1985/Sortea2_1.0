-- =====================================================
-- Migracion: limite de solicitudes publicas por IP
-- Modulo: Rifas / ficha publica
-- Fecha: 2026-11-13
-- Motor: MySQL / MariaDB (XAMPP)
-- =====================================================
-- Objetivo:
--   Persistir contadores de ventana fija para limitar disponibilidad,
--   seleccion aleatoria y subidas de comprobante por IP, sin depender
--   exclusivamente de la cookie de sesion.
--
-- Idempotencia:
--   CREATE TABLE IF NOT EXISTS. No modifica datos ni otras tablas.
-- =====================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `rif_rate_limits` (
  `rate_clave` VARCHAR(150) NOT NULL,
  `rate_inicio` DATETIME NOT NULL,
  `rate_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`rate_clave`),
  KEY `idx_rate_inicio` (`rate_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rif_rate_limits') AS rate_limits_exists,
  'OK' AS migration_status;
