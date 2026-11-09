-- Migración: Crear tabla cab_configuracion_general y registrar módulo en cab_menu
CREATE TABLE IF NOT EXISTS cab_configuracion_general (
    cfg_clave VARCHAR(100) NOT NULL,
    cfg_valor LONGTEXT NULL,
    cfg_tipo VARCHAR(20) NOT NULL DEFAULT 'text',
    cfg_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cfg_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cfg_clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por defecto de configuración general
INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'empresa_nombre', 'Mi Empresa', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'empresa_nombre');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'empresa_documento', '', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'empresa_documento');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'empresa_telefono', '', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'empresa_telefono');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'empresa_whatsapp', '', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'empresa_whatsapp');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'empresa_correo', '', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'empresa_correo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'empresa_direccion', '', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'empresa_direccion');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'moneda_principal_nombre', 'Dólar Estadounidense', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'moneda_principal_nombre');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'moneda_principal_codigo', 'USD', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'moneda_principal_codigo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'moneda_principal_simbolo', '$', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'moneda_principal_simbolo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'moneda_secundaria_nombre', 'Bolívares', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'moneda_secundaria_nombre');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'moneda_secundaria_codigo', 'VES', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'moneda_secundaria_codigo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'moneda_secundaria_simbolo', 'Bs.', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'moneda_secundaria_simbolo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'tasa_cambio_secundaria', '36.50', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'tasa_cambio_secundaria');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mantenimiento_modo', '0', 'bool'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'mantenimiento_modo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mantenimiento_titulo', 'Mantenimiento en Curso', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'mantenimiento_titulo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'mantenimiento_mensaje', 'Estamos actualizando el sistema para ofrecerte una mejor experiencia. Volveremos en breve.', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'mantenimiento_mensaje');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'banner_activo', '0', 'bool'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'banner_activo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'banner_tipo', 'info', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'banner_tipo');

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'banner_texto', '', 'text'
WHERE NOT EXISTS (SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'banner_texto');

-- Insertar módulo en cab_menu
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
    'Configuración General',
    'Ajustes de empresa, monedas, tasas y mantenimiento',
    'ConfiguracionGeneral/index',
    'configuracion-general',
    'ki-duotone ki-setting-2',
    0,
    998,
    0,
    '0',
    CURDATE(),
    CURTIME()
WHERE NOT EXISTS (
    SELECT 1
    FROM cab_menu
    WHERE TRIM(BOTH '/' FROM men_url) = 'ConfiguracionGeneral/index'
       OR TRIM(BOTH '/' FROM men_urlamigable) = 'configuracion-general'
);

-- Asignar permisos automáticos al rol 1000 (Admin Master)
INSERT INTO cab_permisologia (mis_id, mis_permiso, mis_menu, mis_tipomenu, mis_tipoper, mis_activo)
SELECT 
    CONCAT('1000_', m.men_id),
    1000,
    m.men_id,
    m.men_tipo,
    COALESCE((SELECT per_tipo FROM cab_grupopermi WHERE per_id = 1000 LIMIT 1), 1),
    '1'
FROM cab_menu m
WHERE (TRIM(BOTH '/' FROM m.men_url) = 'ConfiguracionGeneral/index' OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'configuracion-general')
  AND NOT EXISTS (
      SELECT 1 FROM cab_permisologia p 
      WHERE p.mis_permiso = 1000 AND p.mis_menu = m.men_id
  );

INSERT INTO cab_permiaccion (acc_id, acc_permiso, acc_consultar, acc_incluir, acc_modificar, acc_eliminar, acc_imprimir, acc_extra1, acc_extra2)
SELECT 
    p.mis_id,
    1000,
    1, 1, 1, 1, 1, 1, 1
FROM cab_permisologia p
JOIN cab_menu m ON m.men_id = p.mis_menu
WHERE p.mis_permiso = 1000
  AND (TRIM(BOTH '/' FROM m.men_url) = 'ConfiguracionGeneral/index' OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'configuracion-general')
  AND NOT EXISTS (
      SELECT 1 FROM cab_permiaccion a WHERE a.acc_id = p.mis_id
  );

