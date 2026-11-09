CREATE TABLE IF NOT EXISTS cab_configuracion_tema (
    cfg_clave VARCHAR(100) NOT NULL,
    cfg_valor LONGTEXT NULL,
    cfg_tipo VARCHAR(20) NOT NULL DEFAULT 'text',
    cfg_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cfg_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cfg_clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;