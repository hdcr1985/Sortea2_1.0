CREATE TABLE IF NOT EXISTS cab_correos_promocionales (
    cpr_id INT NOT NULL AUTO_INCREMENT,
    cpr_asunto VARCHAR(180) NOT NULL,
    cpr_template VARCHAR(100) NOT NULL,
    cpr_from_email VARCHAR(150) NOT NULL,
    cpr_from_name VARCHAR(150) NOT NULL,
    cpr_reply_to VARCHAR(150) NULL,
    cpr_body_html LONGTEXT NULL,
    cpr_total_destinatarios INT NOT NULL DEFAULT 0,
    cpr_total_enviados INT NOT NULL DEFAULT 0,
    cpr_total_fallidos INT NOT NULL DEFAULT 0,
    cpr_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    cpr_usuario_id VARCHAR(20) NULL,
    cpr_usuario VARCHAR(100) NULL,
    cpr_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cpr_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cpr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cab_correos_promocionales_destinatarios (
    cpd_id INT NOT NULL AUTO_INCREMENT,
    cpd_promocion_id INT NOT NULL,
    cpd_usuario_id VARCHAR(20) NULL,
    cpd_usuario VARCHAR(100) NULL,
    cpd_nombre VARCHAR(150) NULL,
    cpd_correo VARCHAR(150) NOT NULL,
    cpd_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    cpd_error TEXT NULL,
    cpd_sent_at DATETIME NULL,
    cpd_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cpd_id),
    KEY idx_cpd_promocion_id (cpd_promocion_id),
    CONSTRAINT fk_cpd_promocion FOREIGN KEY (cpd_promocion_id)
        REFERENCES cab_correos_promocionales(cpr_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    'Correos Promocionales',
    'Envio manual de promociones por correo a usuarios seleccionados',
    'CorreosPromocionales/index',
    'correos-promocionales',
    'ki-duotone ki-send',
    0,
    1000,
    0,
    '0',
    CURDATE(),
    CURTIME()
WHERE NOT EXISTS (
    SELECT 1
    FROM cab_menu
    WHERE TRIM(BOTH '/' FROM men_url) = 'CorreosPromocionales/index'
       OR TRIM(BOTH '/' FROM men_urlamigable) = 'correos-promocionales'
);

-- Ajusta permisos en cab_permisologia/cab_permiaccion para los roles que deban usar este módulo.