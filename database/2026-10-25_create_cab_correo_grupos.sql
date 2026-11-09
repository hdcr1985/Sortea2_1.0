-- Migración: Crear tablas de grupos de destinatarios para Correos Promocionales
-- Fecha: 2026-10-25
-- Descripción: Permite agrupar usuarios (ej. Usuarios más activos, Clientes VIP) para envíos segmentados masivos.

CREATE TABLE IF NOT EXISTS `cab_correo_grupos` (
  `cgr_id` int(11) NOT NULL AUTO_INCREMENT,
  `cgr_nombre` varchar(100) NOT NULL,
  `cgr_descripcion` varchar(255) DEFAULT NULL,
  `cgr_color` varchar(20) NOT NULL DEFAULT 'primary',
  `cgr_status` tinyint(1) NOT NULL DEFAULT 1,
  `cgr_eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `cgr_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cgr_updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cgr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `det_correo_grupos_usuarios` (
  `dgu_id` int(11) NOT NULL AUTO_INCREMENT,
  `dgu_grupo_id` int(11) NOT NULL,
  `dgu_usuario_id` varchar(50) NOT NULL,
  `dgu_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`dgu_id`),
  UNIQUE KEY `uk_grupo_usuario` (`dgu_grupo_id`, `dgu_usuario_id`),
  KEY `idx_grupo_id` (`dgu_grupo_id`),
  KEY `idx_usuario_id` (`dgu_usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
