-- Agrega apellido separado al modulo de usuarios y mantiene usu_nombre como nombre completo por compatibilidad
ALTER TABLE `cab_usuarios`
  MODIFY COLUMN `usu_nombre` varchar(150) NOT NULL,
  ADD COLUMN `usu_apellido` varchar(100) NOT NULL DEFAULT '' AFTER `usu_nombre`;