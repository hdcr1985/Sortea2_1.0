CREATE TABLE IF NOT EXISTS cab_notifications_system (
    noti_id INT NOT NULL AUTO_INCREMENT,
    noti_titulo VARCHAR(150) NOT NULL,
    noti_mensaje TEXT NOT NULL,
    noti_tipo VARCHAR(20) NOT NULL DEFAULT 'info',
    noti_enlace VARCHAR(255) NULL,
    noti_es_global TINYINT(1) NOT NULL DEFAULT 0,
    noti_usuario_creador VARCHAR(20) NOT NULL,
    noti_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (noti_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cab_notifications_system_user (
    nfu_id INT NOT NULL AUTO_INCREMENT,
    nfu_notificacion INT NOT NULL,
    nfu_usuario VARCHAR(20) NOT NULL,
    nfu_leida TINYINT(1) NOT NULL DEFAULT 0,
    nfu_fecha_leida DATETIME NULL,
    nfu_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (nfu_id),
    KEY idx_nfu_usuario (nfu_usuario),
    KEY idx_nfu_notificacion (nfu_notificacion),
    CONSTRAINT fk_nfu_notificacion FOREIGN KEY (nfu_notificacion) REFERENCES cab_notifications_system(noti_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;