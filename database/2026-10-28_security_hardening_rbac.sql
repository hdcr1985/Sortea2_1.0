-- =====================================================================
-- Endurecimiento RBAC y rol de registro público
-- Fecha: 2026-10-28
-- Motor: MySQL / MariaDB (XAMPP)
--
-- Objetivo:
--   1. Crear el rol 1004 "Registrado" con acceso únicamente al dashboard.
--   2. Apuntar el registro público a ese rol.
--   3. Revocar del rol 1002 (Usuario) los menús administrativos 71 y 112.
--   4. Completar acciones CRUD del rol 1000 (Admin Master).
--   5. Ajustar acciones del rol 1001 a lo estrictamente necesario.
--
-- Idempotente: puede ejecutarse más de una vez sin duplicar registros.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Rol 1004 "Registrado"
-- ---------------------------------------------------------------------
INSERT IGNORE INTO cab_grupopermi
    (per_id, per_nombre, per_descripcion, per_tipo, per_activo, per_fecha, per_hora)
SELECT 1004, 'Registrado', 'Usuario creado mediante registro público', '0', '1', CURDATE(), CURTIME();

-- ---------------------------------------------------------------------
-- 2. Acceso al dashboard (menú 113) para el rol 1004
-- ---------------------------------------------------------------------
INSERT IGNORE INTO cab_permisologia
    (mis_id, mis_permiso, mis_menu, mis_tipomenu, mis_tipoper, mis_activo)
SELECT '1004_113', 1004, 113, 0, 0, '1';

INSERT IGNORE INTO cab_permiaccion
    (acc_id, acc_permiso, acc_incluir, acc_consultar, acc_modificar,
     acc_eliminar, acc_imprimir, acc_extra1, acc_extra2)
SELECT '1004_113', 1004, 0, 1, 0, 0, 0, 0, 0;

-- ---------------------------------------------------------------------
-- 3. Registro público apuntando al rol 1004
-- ---------------------------------------------------------------------
UPDATE cab_configuracion_tema
SET cfg_valor = '1004', cfg_updated_at = NOW()
WHERE cfg_clave = 'public_registration_role_id';

-- ---------------------------------------------------------------------
-- 4. Revocar menús administrativos del rol 1002 (Usuario)
-- ---------------------------------------------------------------------
DELETE a
FROM cab_permiaccion a
INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id
WHERE p.mis_permiso = 1002
  AND p.mis_menu IN (71, 112);

DELETE FROM cab_permisologia
WHERE mis_permiso = 1002
  AND mis_menu IN (71, 112);

-- Mantener acceso al dashboard para que el login no quede en bucle.
INSERT IGNORE INTO cab_permisologia
    (mis_id, mis_permiso, mis_menu, mis_tipomenu, mis_tipoper, mis_activo)
SELECT '1002_113', 1002, 113, 0, 0, '1';

INSERT IGNORE INTO cab_permiaccion
    (acc_id, acc_permiso, acc_incluir, acc_consultar, acc_modificar,
     acc_eliminar, acc_imprimir, acc_extra1, acc_extra2)
SELECT '1002_113', 1002, 0, 1, 0, 0, 0, 0, 0;

-- El rol 1003 (Soporte) conserva al menos el dashboard activo.
INSERT IGNORE INTO cab_permisologia
    (mis_id, mis_permiso, mis_menu, mis_tipomenu, mis_tipoper, mis_activo)
SELECT '1003_113', 1003, 113, 0, 0, '1';

INSERT IGNORE INTO cab_permiaccion
    (acc_id, acc_permiso, acc_incluir, acc_consultar, acc_modificar,
     acc_eliminar, acc_imprimir, acc_extra1, acc_extra2)
SELECT '1003_113', 1003, 0, 1, 0, 0, 0, 0, 0;

-- ---------------------------------------------------------------------
-- 5. Admin Master (1000): acciones completas en todos sus menús
-- ---------------------------------------------------------------------
UPDATE cab_permiaccion a
INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id
SET a.acc_incluir   = 1,
    a.acc_consultar = 1,
    a.acc_modificar = 1,
    a.acc_eliminar  = 1,
    a.acc_imprimir  = 1,
    a.acc_extra1    = 1,
    a.acc_extra2    = 1
WHERE p.mis_permiso = 1000;

-- ---------------------------------------------------------------------
-- 6. Rol 1001 (Administrador): consulta en reportes/dashboard
-- ---------------------------------------------------------------------
UPDATE cab_permiaccion a
INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id
SET a.acc_consultar = 1
WHERE p.mis_permiso = 1001
  AND p.mis_menu IN (69, 93, 113, 115, 116);

-- Rol 1001: control total del módulo de correos promocionales (118)
UPDATE cab_permiaccion a
INNER JOIN cab_permisologia p ON p.mis_id = a.acc_id
SET a.acc_incluir   = 1,
    a.acc_consultar = 1,
    a.acc_modificar = 1,
    a.acc_eliminar  = 1,
    a.acc_imprimir  = 1,
    a.acc_extra1    = 1,
    a.acc_extra2    = 1
WHERE p.mis_permiso = 1001
  AND p.mis_menu = 118;

-- =====================================================================
-- Verificación sugerida (ejecutar manualmente tras aplicar):
--
-- SELECT per_id, per_nombre, per_activo FROM cab_grupopermi WHERE per_id = 1004;
-- SELECT * FROM cab_permisologia WHERE mis_permiso = 1004;
-- SELECT * FROM cab_permiaccion  WHERE acc_permiso = 1004;
-- SELECT * FROM cab_permisologia WHERE mis_permiso = 1002;  -- no debe incluir 71/112
-- SELECT cfg_valor FROM cab_configuracion_tema WHERE cfg_clave = 'public_registration_role_id';
-- =====================================================================
