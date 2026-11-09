---
name: db-migration
description: >-
  Procedimiento portable para diseñar, crear y verificar migraciones,
  relaciones, índices y permisos de base de datos en proyecto_base.
---

# Skill: Migraciones y Esquema de Base de Datos

## Uso

Aplicar cuando una solicitud modifique el esquema, datos iniciales, menú o
permisos. No asumir que una migración es ejecutable hasta revisar el estado
actual y las migraciones existentes.

Guía operativa para gestionar el ciclo de vida de la base de datos en `proyecto_base`.

## Convenciones de Base de Datos

1. **Nomenclatura de Tablas**:
   - Tablas principales del sistema llevan prefijo `cab_` (ej. `cab_usuarios`, `cab_clientes`, `cab_menu`).
   - Tablas de detalle llevan prefijo `det_` (ej. `det_factura_items`).
   - La autorización del proyecto usa `cab_grupopermi` para roles,
     `cab_permisologia` para asignar menús a roles y `cab_permiaccion` para
     acciones CRUD. No crear ni documentar una tabla genérica `cab_permisos`.
2. **Nomenclatura de Columnas**:
   - Cada tabla debe tener un prefijo de 3-4 caracteres para sus columnas (ej. `usu_` para `cab_usuarios`, `cli_` para `cab_clientes`, `men_` para `cab_menu`).
   - Clave primaria: `<prefijo>_id` INT AUTO_INCREMENT.
3. **Cotejamiento y Juego de Caracteres**:
   - La base actual mezcla tablas heredadas MyISAM/utf8mb3 con tablas nuevas
     InnoDB/utf8mb4. Verificar la tabla real antes de elegir motor y colación;
     las tablas nuevas deben preferir InnoDB y utf8mb4 salvo compatibilidad
     explícita.

---

## Estructura de Archivos de Migración

Toda modificación a la base de datos debe documentarse en un script SQL dentro del directorio `database/`:

```
database/
  YYYY-MM-DD_<descripcion_breve>.sql
```

Ejemplo orientativo para adaptar y probar en una base aislada. Verificar el rol,
la jerarquía e iconos del menú, los tipos reales y conflictos existentes antes
de aplicarlo; `NOT EXISTS` no garantiza exclusión ante ejecuciones concurrentes.
```sql
-- Migración: Crear tabla de clientes y registrar menú
-- Fecha: 2026-10-25

CREATE TABLE IF NOT EXISTS `cab_clientes` (
  `cli_id` int(11) NOT NULL AUTO_INCREMENT,
  `cli_documento` varchar(20) NOT NULL,
  `cli_nombre` varchar(150) NOT NULL,
  `cli_telefono` varchar(30) DEFAULT NULL,
  `cli_email` varchar(120) DEFAULT NULL,
  `cli_direccion` text DEFAULT NULL,
  `cli_status` tinyint(1) NOT NULL DEFAULT 1,
  `cli_eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `cli_fechareg` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cli_usureg` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`cli_id`),
  KEY `idx_cli_documento` (`cli_documento`),
  KEY `idx_cli_status` (`cli_status`, `cli_eliminado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro en menú
INSERT INTO `cab_menu`
  (`men_nombre`, `men_url`, `men_urlamigable`, `men_orden`, `men_tipo`,
   `men_img`, `men_imgmenu`, `men_inicio`)
SELECT 'Clientes', 'Clientes/index', 'clientes', 15, 1, NULL, NULL, 0
WHERE NOT EXISTS (SELECT 1 FROM `cab_menu` WHERE `men_urlamigable` = 'clientes');

-- Asignación idempotente de permisos al rol Admin Master (1000)
INSERT INTO `cab_permisologia`
  (`mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`)
SELECT
  CONCAT('1000_', m.men_id),
  1000,
  m.men_id,
  m.men_tipo,
  COALESCE((SELECT per_tipo FROM cab_grupopermi WHERE per_id = 1000 LIMIT 1), 1),
  '1'
FROM `cab_menu` m
WHERE m.men_urlamigable = 'clientes'
AND NOT EXISTS (
  SELECT 1 FROM `cab_permisologia` p
  WHERE p.mis_permiso = 1000 AND p.mis_menu = m.men_id
);

INSERT INTO `cab_permiaccion`
  (`acc_id`, `acc_permiso`, `acc_consultar`, `acc_incluir`, `acc_modificar`,
   `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`)
SELECT p.mis_id, 1000, 1, 1, 1, 1, 1, 1, 1
FROM `cab_permisologia` p
JOIN `cab_menu` m ON m.men_id = p.mis_menu
WHERE p.mis_permiso = 1000
  AND m.men_urlamigable = 'clientes'
  AND NOT EXISTS (
    SELECT 1 FROM `cab_permiaccion` a WHERE a.acc_id = p.mis_id
  );
```

---

## Verificación de Integridad

Al aplicar cambios:
1. Verificar que no se rompan consultas existentes.
2. Comprobar compatibilidad de tipos y colaciones de los campos de unión (JOIN)
   con las tablas reales; no asumir una colación uniforme en todo el esquema.
3. Entregar al Coordinador el nombre de la migración y el resumen para que él
   decida si actualiza `MEMORY.md`; el especialista de BD no lo modifica por
   defecto.
4. No asumir columnas históricas documentadas en ejemplos anteriores. Para este
   esquema, `cab_menu` usa `sub_men_id` en lugar de `men_padre` y no tiene
   `men_icono` ni `men_status`.

## Entrega obligatoria

Informar archivo creado, tablas afectadas, índices, permisos, compatibilidad,
validaciones ejecutadas y cualquier paso manual requerido.
