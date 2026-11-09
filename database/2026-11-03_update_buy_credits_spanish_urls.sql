-- =====================================================
-- Migration: Spanish names and URLs for Buy Credits
-- Date: 2026-11-03
-- =====================================================
-- Idempotent update for installations that already ran
-- 2026-11-03_create_buy_credits_module.sql.
-- =====================================================

UPDATE `cab_menu`
SET
    `men_nombre`      = 'Comprar Créditos',
    `men_urlamigable` = 'comprar-creditos',
    `men_descripcion` = 'Planes de créditos, confirmación de compras e historial de órdenes',
    `men_fecha`       = CURDATE(),
    `men_hora`        = CURTIME()
WHERE `men_url` = 'BuyCredits/index'
   OR `men_urlamigable` IN ('buy-credits', 'comprar-creditos');

UPDATE `cab_menu`
SET
    `men_nombre`      = 'Pasarelas de Pago',
    `men_urlamigable` = 'pasarelas-de-pago',
    `men_descripcion` = 'Configuración de pasarelas de pago (BTCPay Server y Stripe)',
    `men_fecha`       = CURDATE(),
    `men_hora`        = CURTIME()
WHERE `men_url` = 'ConfiguracionPasarelas/index'
   OR `men_urlamigable` IN ('payment-gateways', 'pasarelas-de-pago');

INSERT INTO `cab_configuracion_general`
    (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
    ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `cfg_valor` = VALUES(`cfg_valor`),
    `cfg_updated_at` = NOW();

SELECT men_id, men_nombre, men_url, men_urlamigable
FROM `cab_menu`
WHERE men_urlamigable IN ('comprar-creditos', 'pasarelas-de-pago')
ORDER BY men_id;
