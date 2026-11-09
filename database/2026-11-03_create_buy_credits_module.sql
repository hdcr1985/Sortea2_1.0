-- =====================================================
-- Migration: Buy Credits module for proyecto_base
-- Date: 2026-11-03
-- =====================================================
-- Scope:
--   1. Orders and credit detail tables.
--   2. Payment gateway configuration and audit tables.
--   3. Stripe/BTCPay webhook audit tables.
--   4. InnoDB conversion for cab_usuarios (settlement atomicity).
--   5. Menus and permissions for Admin Master (1000) only.
--
-- The migration is idempotent:
--   - CREATE TABLE IF NOT EXISTS
--   - Menu rows are inserted only when the friendly URL is absent
--   - Permissions are inserted only when absent
--   - ALTER TABLE cab_usuarios runs only when its engine is not InnoDB
--
-- No credentials are seeded. Configure them from /payment-gateways.
-- =====================================================

SET @db_name = DATABASE();

-- -----------------------------------------------------
-- 1. Orders
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cab_movplanes` (
    `mov_id` varchar(20) NOT NULL COMMENT 'Order/invoice number',
    `mov_idusuario` varchar(100) DEFAULT NULL COMMENT 'Buyer user id',
    `mov_idplan` int(10) unsigned DEFAULT NULL COMMENT 'Plan id',
    `mov_nomplan` varchar(150) DEFAULT NULL COMMENT 'Plan name at purchase time',
    `mov_precioplan` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Plan price at purchase time',
    `mov_sms` int(11) DEFAULT NULL COMMENT 'Credits granted by the order',
    `mov_status` char(2) DEFAULT 'A' COMMENT 'Order status (A/V)',
    `mov_fechcomp` date DEFAULT NULL COMMENT 'Order creation date',
    `mov_fechpago` date DEFAULT NULL COMMENT 'Payment date',
    `mov_fechvenc` date DEFAULT NULL COMMENT 'Expiration date',
    `mov_statuspag` char(100) DEFAULT NULL COMMENT 'NoPagado, waiting, in_process, approved, cancelled, expired, rejected, refunded',
    `mov_hora` time DEFAULT NULL COMMENT 'Order creation time',
    `mov_horapag` time DEFAULT NULL COMMENT 'Payment time',
    `mov_idpago` char(200) DEFAULT NULL COMMENT 'Gateway transaction/PaymentIntent reference',
    `mov_refid` char(100) DEFAULT NULL COMMENT 'Additional gateway reference',
    `mov_tippago` char(20) DEFAULT NULL COMMENT 'BTCPay, Stripe, legacy gateway',
    `mov_stripe_session_id` varchar(255) DEFAULT NULL COMMENT 'Stripe Checkout Session bound to the order',
    `mov_stripe_payment_intent` varchar(255) DEFAULT NULL COMMENT 'Stripe PaymentIntent that settled the order',
    `mov_pais` char(5) DEFAULT 'VE' COMMENT 'Country code',
    `mov_moneda` varchar(10) DEFAULT 'USD' COMMENT 'Currency code',
    `mov_coin` varchar(32) DEFAULT NULL COMMENT 'Crypto coin or payment detail',
    PRIMARY KEY (`mov_id`),
    KEY `idx_mov_idusuario` (`mov_idusuario`),
    KEY `idx_mov_statuspag` (`mov_statuspag`),
    KEY `idx_mov_fechcomp` (`mov_fechcomp`),
    KEY `idx_mov_stripe_session` (`mov_stripe_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Credit detail
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cab_movsms` (
    `movs_id` varchar(20) NOT NULL,
    `movs_idusuario` varchar(100) DEFAULT NULL,
    `movs_sms` int(11) DEFAULT NULL,
    PRIMARY KEY (`movs_id`),
    KEY `idx_movsms_idusuario` (`movs_idusuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Payment gateway configuration
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cab_configuracion_pasarelas` (
    `pas_id` int(11) NOT NULL AUTO_INCREMENT,
    `pas_codigo` varchar(50) NOT NULL,
    `pas_nombre` varchar(100) NOT NULL,
    `pas_activo` tinyint(1) NOT NULL DEFAULT 1,
    `pas_ambiente` varchar(20) NOT NULL DEFAULT 'production',
    `pas_url` varchar(255) DEFAULT NULL,
    `pas_store_id` varchar(100) DEFAULT NULL,
    `pas_api_key` text DEFAULT NULL,
    `pas_webhook_secret` varchar(255) DEFAULT NULL,
    `pas_speed_policy` varchar(50) NOT NULL DEFAULT 'MediumSpeed',
    `pas_expiration_minutes` int(11) NOT NULL DEFAULT 60,
    `pas_config_json` longtext DEFAULT NULL,
    `pas_fecha_reg` date DEFAULT NULL,
    `pas_hora_reg` time DEFAULT NULL,
    PRIMARY KEY (`pas_id`),
    UNIQUE KEY `uk_pas_codigo` (`pas_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cab_configuracion_pasarelas`
    (`pas_codigo`, `pas_nombre`, `pas_activo`, `pas_ambiente`, `pas_url`, `pas_store_id`,
     `pas_api_key`, `pas_webhook_secret`, `pas_speed_policy`, `pas_expiration_minutes`,
     `pas_fecha_reg`, `pas_hora_reg`)
SELECT
    'btcpay', 'BTCPay Server (Criptomonedas)', 0, 'production', '', '',
    '', '', 'MediumSpeed', 60, CURDATE(), CURTIME()
WHERE NOT EXISTS (
    SELECT 1 FROM `cab_configuracion_pasarelas` WHERE `pas_codigo` = 'btcpay'
);

INSERT INTO `cab_configuracion_pasarelas`
    (`pas_codigo`, `pas_nombre`, `pas_activo`, `pas_ambiente`, `pas_url`, `pas_store_id`,
     `pas_api_key`, `pas_webhook_secret`, `pas_speed_policy`, `pas_expiration_minutes`,
     `pas_fecha_reg`, `pas_hora_reg`)
SELECT
    'stripe', 'Stripe (Tarjetas de crédito / débito)', 0, 'sandbox', 'https://api.stripe.com', NULL,
    '', '', 'MediumSpeed', 30, CURDATE(), CURTIME()
WHERE NOT EXISTS (
    SELECT 1 FROM `cab_configuracion_pasarelas` WHERE `pas_codigo` = 'stripe'
);

-- -----------------------------------------------------
-- 4. BTCPay audit tables
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cab_mov_btcpay_webhooks` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `deliveryId` varchar(100) DEFAULT NULL,
    `webhookId` varchar(100) DEFAULT NULL,
    `orignalDeliveryId` varchar(100) DEFAULT NULL,
    `isRedelivery` varchar(20) DEFAULT NULL,
    `type` varchar(100) DEFAULT NULL,
    `timestamp` varchar(50) DEFAULT NULL,
    `storeId` varchar(100) DEFAULT NULL,
    `invoiceId` varchar(100) DEFAULT NULL,
    `date` date DEFAULT NULL,
    `time` time DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_btcpay_webhook_invoice` (`invoiceId`),
    KEY `idx_btcpay_webhook_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cab_mov_btcpay` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `id_invoice` varchar(100) NOT NULL,
    `url` varchar(255) DEFAULT NULL,
    `status` varchar(50) DEFAULT NULL,
    `btcPrice` varchar(50) DEFAULT NULL,
    `price` decimal(10,2) DEFAULT 0.00,
    `currency` varchar(10) DEFAULT 'USD',
    `invoiceTime` varchar(50) DEFAULT NULL,
    `expirationTime` varchar(50) DEFAULT NULL,
    `currentTime` varchar(50) DEFAULT NULL,
    `btcPaid` varchar(50) DEFAULT NULL,
    `btcDue` varchar(50) DEFAULT NULL,
    `rate` varchar(50) DEFAULT NULL,
    `exceptionStatus` varchar(50) DEFAULT NULL,
    `buyerFields` longtext DEFAULT NULL,
    `transactionCurrency` varchar(20) DEFAULT NULL,
    `paymentSubtotals` varchar(100) DEFAULT NULL,
    `paymentTotals` varchar(100) DEFAULT NULL,
    `amountPaid` varchar(50) DEFAULT NULL,
    `orderId` varchar(50) DEFAULT NULL,
    `paymentMethods` varchar(50) DEFAULT NULL,
    `date` date DEFAULT NULL,
    `time` time DEFAULT NULL,
    `json_ent` longtext DEFAULT NULL,
    `json_method` longtext DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_btcpay_invoice` (`id_invoice`),
    KEY `idx_btcpay_order` (`orderId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. Stripe audit tables
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cab_mov_stripe_webhooks` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `event_id` varchar(255) NOT NULL,
    `type` varchar(100) DEFAULT NULL,
    `session_id` varchar(255) DEFAULT NULL,
    `payment_intent` varchar(255) DEFAULT NULL,
    `order_id` varchar(20) DEFAULT NULL,
    `livemode` tinyint(1) NOT NULL DEFAULT 0,
    `payload_hash` char(64) DEFAULT NULL,
    `payload_json` longtext DEFAULT NULL,
    `processed` tinyint(1) NOT NULL DEFAULT 0,
    `date` date DEFAULT NULL,
    `time` time DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_stripe_event_id` (`event_id`),
    KEY `idx_stripe_event_type` (`type`),
    KEY `idx_stripe_event_session` (`session_id`),
    KEY `idx_stripe_event_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cab_mov_stripe` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `order_id` varchar(20) NOT NULL,
    `session_id` varchar(255) NOT NULL,
    `payment_intent` varchar(255) DEFAULT NULL,
    `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `currency` char(10) NOT NULL DEFAULT 'USD',
    `amount_total` int(10) unsigned NOT NULL DEFAULT 0,
    `payment_status` varchar(30) DEFAULT NULL,
    `status` varchar(30) DEFAULT NULL,
    `livemode` tinyint(1) NOT NULL DEFAULT 0,
    `customer_email` varchar(255) DEFAULT NULL,
    `raw_json` longtext DEFAULT NULL,
    `date` date DEFAULT NULL,
    `time` time DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_stripe_session_id` (`session_id`),
    KEY `idx_stripe_order_id` (`order_id`),
    KEY `idx_stripe_payment_intent` (`payment_intent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. Settlement atomicity: cab_usuarios must be InnoDB
-- -----------------------------------------------------
SET @engine_usuarios = (
    SELECT ENGINE FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cab_usuarios'
);

SET @sql_stmt = IF(
    @engine_usuarios IS NOT NULL AND @engine_usuarios <> 'InnoDB',
    'ALTER TABLE `cab_usuarios` ENGINE=InnoDB',
    'DO 0'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 7. Menu entries
-- -----------------------------------------------------
INSERT INTO `cab_menu`
    (`sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
     `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
     `men_externo`, `men_inicio`)
SELECT
    0, 'Comprar Créditos',
    'Planes de créditos, confirmación de compras e historial de órdenes',
    'BuyCredits/index', 'comprar-creditos', 12, 0, NULL, 'ki-duotone ki-basket',
    CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
    SELECT 1 FROM `cab_menu`
    WHERE `men_urlamigable` = 'comprar-creditos'
       OR TRIM(BOTH '/' FROM `men_url`) = 'BuyCredits/index'
);

SET @config_menu_id = (
    SELECT `men_id` FROM `cab_menu`
    WHERE `men_urlamigable` = 'configuracion'
    ORDER BY `men_id` ASC
    LIMIT 1
);

INSERT INTO `cab_menu`
    (`sub_men_id`, `men_nombre`, `men_descripcion`, `men_url`, `men_urlamigable`,
     `men_orden`, `men_tipo`, `men_img`, `men_imgmenu`, `men_fecha`, `men_hora`,
     `men_externo`, `men_inicio`)
SELECT
    COALESCE(@config_menu_id, 0), 'Pasarelas de Pago',
    'Configuración de pasarelas de pago (BTCPay Server y Stripe)',
    'ConfiguracionPasarelas/index', 'pasarelas-de-pago', 13, 1, NULL, 'ki-duotone ki-wallet',
    CURDATE(), CURTIME(), '0', 0
WHERE NOT EXISTS (
    SELECT 1 FROM `cab_menu`
    WHERE `men_urlamigable` = 'pasarelas-de-pago'
       OR TRIM(BOTH '/' FROM `men_url`) = 'ConfiguracionPasarelas/index'
);

-- -----------------------------------------------------
-- 8. Permissions for Admin Master (1000) only
-- -----------------------------------------------------
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
WHERE m.`men_urlamigable` IN ('comprar-creditos', 'pasarelas-de-pago')
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permisologia` p
    WHERE p.`mis_permiso` = 1000 AND p.`mis_menu` = m.`men_id`
  );

INSERT INTO `cab_permiaccion`
    (`acc_id`, `acc_permiso`, `acc_incluir`, `acc_consultar`, `acc_modificar`,
     `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`)
SELECT
    p.`mis_id`, 1000, 1, 1, 1, 1, 1, 0, 0
FROM `cab_permisologia` p
JOIN `cab_menu` m ON m.`men_id` = p.`mis_menu`
WHERE p.`mis_permiso` = 1000
  AND m.`men_urlamigable` IN ('comprar-creditos', 'pasarelas-de-pago')
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion` a WHERE a.`acc_id` = p.`mis_id`
  );

-- Invalidate active menu snapshots.
INSERT INTO `cab_configuracion_general`
    (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
    ('permisos_version', CONCAT(UNIX_TIMESTAMP(), '.', MICROSECOND(NOW(6))), 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `cfg_valor` = VALUES(`cfg_valor`),
    `cfg_updated_at` = NOW();

-- -----------------------------------------------------
-- 9. Verification
-- -----------------------------------------------------
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME IN (
    'cab_usuarios', 'cab_movplanes', 'cab_movsms',
    'cab_configuracion_pasarelas', 'cab_mov_btcpay', 'cab_mov_btcpay_webhooks',
    'cab_mov_stripe', 'cab_mov_stripe_webhooks'
  )
ORDER BY TABLE_NAME;

SELECT men_id, men_nombre, men_url, men_urlamigable, sub_men_id, men_tipo
FROM `cab_menu`
WHERE men_urlamigable IN ('comprar-creditos', 'pasarelas-de-pago')
ORDER BY men_id;
