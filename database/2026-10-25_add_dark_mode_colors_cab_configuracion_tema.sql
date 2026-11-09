-- Migración: Asignar colores por defecto para Modo Oscuro en cab_configuracion_tema
-- Fecha: 2026-10-25
-- Descripción: Registra la paleta de colores de alto contraste inspirada en Metronic Demo 1 y Demo 10

INSERT INTO `cab_configuracion_tema` (`cfg_clave`, `cfg_valor`, `cfg_tipo`, `cfg_created_at`, `cfg_updated_at`)
VALUES
    ('dark_primary_color', '#1B84FF', 'text', NOW(), NOW()),
    ('dark_button_hover_color', '#056EE9', 'text', NOW(), NOW()),
    ('dark_header_bg_color', '#0D0E12', 'text', NOW(), NOW()),
    ('dark_header_text_color', '#FFFFFF', 'text', NOW(), NOW()),
    ('dark_sidebar_bg_color', '#0F1014', 'text', NOW(), NOW()),
    ('dark_sidebar_text_color', '#9A9CAE', 'text', NOW(), NOW()),
    ('dark_sidebar_active_bg_color', '#1B84FF', 'text', NOW(), NOW()),
    ('dark_sidebar_active_text_color', '#FFFFFF', 'text', NOW(), NOW()),
    ('dark_footer_bg_color', '#0D0E12', 'text', NOW(), NOW()),
    ('dark_footer_text_color', '#78829D', 'text', NOW(), NOW()),
    ('dark_body_bg_color', '#0F1014', 'text', NOW(), NOW()),
    ('dark_login_card_bg_color', '#15171C', 'text', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `cfg_updated_at` = NOW();

