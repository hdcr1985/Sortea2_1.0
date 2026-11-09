-- Ejecutar una sola instancia de la migración a la vez.
-- Requiere 2026-10-27_create_google_analytics.sql.
ALTER TABLE cab_google_analytics
    ADD COLUMN IF NOT EXISTS ga_reporte_activo TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ga_property_id VARCHAR(20) NOT NULL DEFAULT '';

INSERT INTO cab_menu (men_nombre, men_descripcion, men_url, men_urlamigable, men_imgmenu, men_tipo, men_orden, sub_men_id, men_externo, men_fecha, men_hora)
SELECT 'Reporte Google Analytics', 'Métricas de Google Analytics 4', 'ReportesGoogleAnalytics/index', 'reporte-google-analytics', 'ki-duotone ki-chart-line-up',
       CASE WHEN parent.men_id IS NULL THEN 0 ELSE 1 END, 999, COALESCE(parent.men_id, 0), '0', CURDATE(), CURTIME()
FROM (SELECT 1) seed
LEFT JOIN cab_menu parent ON parent.men_urlamigable = 'reportes-generales'
WHERE NOT EXISTS (SELECT 1 FROM cab_menu WHERE men_urlamigable = 'reporte-google-analytics' OR men_url = 'ReportesGoogleAnalytics/index');

INSERT INTO cab_permisologia (mis_id, mis_permiso, mis_menu, mis_tipomenu, mis_tipoper, mis_activo)
SELECT CONCAT('1000_', m.men_id), g.per_id, m.men_id, m.men_tipo, g.per_tipo, '1'
FROM cab_menu m JOIN cab_grupopermi g ON g.per_id = 1000
WHERE m.men_urlamigable = 'reporte-google-analytics' AND m.men_url = 'ReportesGoogleAnalytics/index'
AND NOT EXISTS (SELECT 1 FROM cab_permisologia p WHERE p.mis_permiso = g.per_id AND p.mis_menu = m.men_id);

INSERT INTO cab_permiaccion (acc_id, acc_permiso, acc_consultar, acc_incluir, acc_modificar, acc_eliminar, acc_imprimir, acc_extra1, acc_extra2)
SELECT p.mis_id, p.mis_permiso, 1, 0, 0, 0, 0, 0, 0
FROM cab_permisologia p JOIN cab_menu m ON m.men_id = p.mis_menu
WHERE p.mis_permiso = 1000 AND m.men_urlamigable = 'reporte-google-analytics' AND m.men_url = 'ReportesGoogleAnalytics/index'
AND NOT EXISTS (SELECT 1 FROM cab_permiaccion a WHERE a.acc_id = p.mis_id);
