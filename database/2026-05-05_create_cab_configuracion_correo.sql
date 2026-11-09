CREATE TABLE IF NOT EXISTS cab_configuracion_correo (
    cfg_clave VARCHAR(100) NOT NULL,
    cfg_valor LONGTEXT NULL,
    cfg_tipo VARCHAR(20) NOT NULL DEFAULT 'text',
    cfg_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cfg_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cfg_clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_enabled', '0', 'bool'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_enabled'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_driver', 'smtp', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_driver'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_host', '', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_host'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_port', '587', 'number'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_port'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_encryption', 'tls', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_encryption'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_user', '', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_user'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_password', '', 'password'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_password'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_from_email', '', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_from_email'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_from_name', 'Notificaciones del Sistema', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_from_name'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_reply_to', '', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_reply_to'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_timeout', '15', 'number'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_timeout'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_charset', 'utf-8', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_charset'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mailtype', 'html', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mailtype'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_template_default', 'metronic_base', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_template_default'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_newline', '\\r\\n', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_newline'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_crlf', '\\r\\n', 'text'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_crlf'
);

INSERT INTO cab_configuracion_correo (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mail_debug', '0', 'bool'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_correo WHERE cfg_clave = 'mail_debug'
);

INSERT INTO cab_menu (
    men_nombre,
    men_descripcion,
    men_url,
    men_urlamigable,
    men_imgmenu,
    men_tipo,
    men_orden,
    sub_men_id,
    men_externo,
    men_fecha,
    men_hora
)
SELECT
    'Configuración de Correos',
    'Configuración del correo saliente del sistema',
    'ConfiguracionCorreos/index',
    'configuracion-correos',
    'ki-duotone ki-sms',
    0,
    999,
    0,
    '0',
    CURDATE(),
    CURTIME()
WHERE NOT EXISTS (
    SELECT 1
    FROM cab_menu
    WHERE TRIM(BOTH '/' FROM men_url) = 'ConfiguracionCorreos/index'
       OR TRIM(BOTH '/' FROM men_urlamigable) = 'configuracion-correos'
);

-- Ajusta permisos en cab_permisologia/cab_permiaccion para los roles que deban ver este módulo.