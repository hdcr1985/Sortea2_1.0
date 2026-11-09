-- =====================================================================
-- Migración: Módulo "Reporte de Errores" (visor de application/logs)
-- Fecha: 2026-10-28
-- Archivo: database/2026-10-28_create_reporte_errores.sql
-- Motor: MySQL / MariaDB (XAMPP)
--
-- Alcance:
--   1. Claves de configuración en cab_configuracion_general.
--   2. Registro del menú en cab_menu (bajo "Reportes Generales" si existe).
--   3. Permisos exclusivos del rol 1000 (Admin Master).
--
-- Idempotente: seguro de ejecutar dos veces. Cada INSERT usa NOT EXISTS
-- sobre su clave natural (cfg_clave, men_url/men_urlamigable,
-- mis_permiso+mis_menu y acc_id).
--
-- ORDEN DE EJECUCIÓN:
--   Este archivo ordena ANTES de 2026-10-28_security_hardening_rbac.sql.
--   Ese hardening posterior ejecuta un UPDATE que pone a 1 todas las
--   acciones (incluir/consultar/modificar/eliminar/imprimir/extra) del
--   rol 1000. Por tanto, los valores 0 definidos aquí para incluir,
--   modificar, eliminar, imprimir y extras pueden quedar en 1 tras aplicar
--   el hardening. Es aceptable porque este módulo es de solo lectura y no
--   expone endpoints de mutación; la única acción necesaria es consultar.
--
-- NO se crean permisos para los roles 1001, 1002, 1003 ni 1004.
--
-- Esquema verificado en la BD de desarrollo el 2026-10-28:
--   cab_menu: men_id, sub_men_id, men_nombre, men_descripcion, men_url,
--     men_urlamigable, men_orden, men_tipo, men_img, men_imgmenu,
--     men_fecha, men_hora, men_externo, men_inicio.
--   cab_permisologia: mis_id, mis_permiso, mis_menu, mis_tipomenu,
--     mis_tipoper, mis_activo.
--   cab_permiaccion: acc_id, acc_permiso, acc_incluir, acc_consultar,
--     acc_modificar, acc_eliminar, acc_imprimir, acc_extra1, acc_extra2.
--   cab_grupopermi: per_id, per_nombre, per_descripcion, per_tipo (char(1)),
--     per_activo, per_fecha, per_hora.
--   cab_configuracion_general: cfg_clave (PK), cfg_valor LONGTEXT,
--     cfg_tipo, cfg_created_at, cfg_updated_at.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Claves de configuración del visor
-- ---------------------------------------------------------------------
INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'logs_reporte_activo', '1', 'bool'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'logs_reporte_activo'
);

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'logs_reporte_dias', '7', 'number'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'logs_reporte_dias'
);

INSERT INTO cab_configuracion_general (cfg_clave, cfg_valor, cfg_tipo)
SELECT 'logs_reporte_max_entradas', '500', 'number'
WHERE NOT EXISTS (
    SELECT 1 FROM cab_configuracion_general WHERE cfg_clave = 'logs_reporte_max_entradas'
);

-- ---------------------------------------------------------------------
-- 2. Menú: "Reporte de Errores"
--    El padre se resuelve con subconsulta escalar + LIMIT 1 para no
--    multiplicar filas si 'reportes-generales' estuviera duplicado.
--    Si no existe padre: men_tipo = 0 y sub_men_id = 0.
-- ---------------------------------------------------------------------
INSERT INTO cab_menu (
    sub_men_id,
    men_nombre,
    men_descripcion,
    men_url,
    men_urlamigable,
    men_orden,
    men_tipo,
    men_imgmenu,
    men_fecha,
    men_hora,
    men_externo,
    men_inicio
)
SELECT
    COALESCE((
        SELECT p.men_id
        FROM cab_menu p
        WHERE TRIM(BOTH '/' FROM p.men_urlamigable) = 'reportes-generales'
        ORDER BY p.men_id
        LIMIT 1
    ), 0),
    'Reporte de Errores',
    'Visor de errores del log de CodeIgniter',
    'ReportesErrores/index',
    'reporte-errores',
    1001,
    CASE WHEN (
        SELECT p.men_id
        FROM cab_menu p
        WHERE TRIM(BOTH '/' FROM p.men_urlamigable) = 'reportes-generales'
        ORDER BY p.men_id
        LIMIT 1
    ) IS NULL THEN 0 ELSE 1 END,
    'ki-duotone ki-shield-cross',
    CURDATE(),
    CURTIME(),
    '0',
    0
FROM (SELECT 1) AS seed
WHERE NOT EXISTS (
    SELECT 1
    FROM cab_menu m
    WHERE TRIM(BOTH '/' FROM m.men_url) = 'ReportesErrores/index'
       OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'reporte-errores'
);

-- ---------------------------------------------------------------------
-- 3. Permiso del menú para el rol 1000 (Admin Master) únicamente
-- ---------------------------------------------------------------------
INSERT INTO cab_permisologia (
    mis_id,
    mis_permiso,
    mis_menu,
    mis_tipomenu,
    mis_tipoper,
    mis_activo
)
SELECT
    CONCAT('1000_', m.men_id),
    1000,
    m.men_id,
    m.men_tipo,
    COALESCE((
        SELECT g.per_tipo
        FROM cab_grupopermi g
        WHERE g.per_id = 1000
        LIMIT 1
    ), '0'),
    '1'
FROM cab_menu m
WHERE m.men_id = (
        SELECT mm.men_id
        FROM cab_menu mm
        WHERE TRIM(BOTH '/' FROM mm.men_url) = 'ReportesErrores/index'
           OR TRIM(BOTH '/' FROM mm.men_urlamigable) = 'reporte-errores'
        ORDER BY mm.men_id
        LIMIT 1
    )
  AND NOT EXISTS (
        SELECT 1
        FROM cab_permisologia p
        WHERE p.mis_permiso = 1000
          AND p.mis_menu = m.men_id
    );

-- Acciones: módulo de solo lectura -> únicamente consultar.
INSERT INTO cab_permiaccion (
    acc_id,
    acc_permiso,
    acc_consultar,
    acc_incluir,
    acc_modificar,
    acc_eliminar,
    acc_imprimir,
    acc_extra1,
    acc_extra2
)
SELECT
    p.mis_id,
    p.mis_permiso,
    1,
    0,
    0,
    0,
    0,
    0,
    0
FROM cab_permisologia p
JOIN cab_menu m ON m.men_id = p.mis_menu
WHERE p.mis_permiso = 1000
  AND m.men_id = (
        SELECT mm.men_id
        FROM cab_menu mm
        WHERE TRIM(BOTH '/' FROM mm.men_url) = 'ReportesErrores/index'
           OR TRIM(BOTH '/' FROM mm.men_urlamigable) = 'reporte-errores'
        ORDER BY mm.men_id
        LIMIT 1
    )
  AND NOT EXISTS (
        SELECT 1
        FROM cab_permiaccion a
        WHERE a.acc_id = p.mis_id
    );

-- =====================================================================
-- Verificación sugerida (ejecutar manualmente tras aplicar):
--
-- 1) Claves de configuración (deben ser exactamente 3 filas):
-- SELECT cfg_clave, cfg_valor, cfg_tipo
-- FROM cab_configuracion_general
-- WHERE cfg_clave IN ('logs_reporte_activo','logs_reporte_dias','logs_reporte_max_entradas')
-- ORDER BY cfg_clave;
--
-- 2) Menú único (debe devolver 1 fila con men_tipo=1 y sub_men_id=69
--    cuando existe 'reportes-generales'):
-- SELECT men_id, sub_men_id, men_tipo, men_orden, men_url, men_urlamigable
-- FROM cab_menu
-- WHERE TRIM(BOTH '/' FROM men_url) = 'ReportesErrores/index'
--    OR TRIM(BOTH '/' FROM men_urlamigable) = 'reporte-errores';
--
-- 3) Conteo de duplicados del menú (debe ser 1):
-- SELECT COUNT(*) FROM cab_menu
-- WHERE TRIM(BOTH '/' FROM men_url) = 'ReportesErrores/index'
--    OR TRIM(BOTH '/' FROM men_urlamigable) = 'reporte-errores';
--
-- 4) Permiso del rol 1000 (debe devolver 1 fila):
-- SELECT p.mis_id, p.mis_permiso, p.mis_menu, p.mis_tipomenu,
--        p.mis_tipoper, p.mis_activo,
--        a.acc_consultar, a.acc_incluir, a.acc_modificar
-- FROM cab_permisologia p
-- JOIN cab_menu m ON m.men_id = p.mis_menu
-- LEFT JOIN cab_permiaccion a ON a.acc_id = p.mis_id
-- WHERE p.mis_permiso = 1000
--   AND (TRIM(BOTH '/' FROM m.men_url) = 'ReportesErrores/index'
--        OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'reporte-errores');
--
-- 5) Ausencia de permisos para 1001-1004 (debe devolver 0 filas):
-- SELECT p.mis_permiso, p.mis_menu
-- FROM cab_permisologia p
-- JOIN cab_menu m ON m.men_id = p.mis_menu
-- WHERE p.mis_permiso IN (1001, 1002, 1003, 1004)
--   AND (TRIM(BOTH '/' FROM m.men_url) = 'ReportesErrores/index'
--        OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'reporte-errores');
-- =====================================================================
