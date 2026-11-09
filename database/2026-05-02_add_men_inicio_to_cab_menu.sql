ALTER TABLE cab_menu
ADD COLUMN men_inicio TINYINT(1) NOT NULL DEFAULT 0 AFTER men_externo;

UPDATE cab_menu
SET men_inicio = 0;

-- Marca un único menú como predeterminado de inicio.
-- Ajusta la condición según el menú que quieras usar, por ejemplo Dashboard.
UPDATE cab_menu
SET men_inicio = 1
WHERE men_url = 'Home/index';