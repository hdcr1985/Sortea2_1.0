CREATE TABLE IF NOT EXISTS cab_usuario_verificacion (
    uver_usuario_id VARCHAR(20) NOT NULL,
    uver_tipo VARCHAR(32) NOT NULL,
    uver_token_hash CHAR(64) NOT NULL,
    uver_expira DATETIME NOT NULL,
    uver_ip_address VARCHAR(45) NOT NULL,
    uver_send_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    uver_window_started_at DATETIME NOT NULL,
    uver_last_sent_at DATETIME NOT NULL,
    uver_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uver_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (uver_usuario_id, uver_tipo),
    UNIQUE KEY uq_cab_usuario_verificacion_token (uver_token_hash),
    KEY idx_cab_usuario_verificacion_expira (uver_expira),
    KEY idx_cab_usuario_verificacion_ip_created (uver_ip_address, uver_created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
