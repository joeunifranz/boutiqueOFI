-- Tabla de inventario de telas
-- Ejecuta este script en tu BD (boutique) si aún no existe la tabla.

-- MIGRACIÓN (si ya tenías la tabla creada con el campo anterior):
-- ALTER TABLE `tela` CHANGE `tela_textura_url` `tela_textura_imagen` VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS `tela` (
  `tela_id` INT NOT NULL AUTO_INCREMENT,
  `tela_nombre` VARCHAR(80) NOT NULL,
  `tela_descripcion` VARCHAR(255) NULL,
  `tela_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tela_stock` INT NOT NULL DEFAULT 0,
  `tela_textura_imagen` VARCHAR(255) NULL, -- Ruta local de imagen (ej: app/views/fotos/telas/archivo.jpg)
  `tela_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `tela_creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tela_id`),
  UNIQUE KEY `uq_tela_nombre` (`tela_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Datos de ejemplo (puedes editar/eliminar)
INSERT INTO `tela` (`tela_nombre`,`tela_descripcion`,`tela_precio`,`tela_stock`,`tela_textura_imagen`,`tela_activo`) VALUES
('Tull español',NULL,35.00,50,NULL,1),
('Tull diamante',NULL,55.00,50,NULL,1),
('Gasa azucarada',NULL,70.00,50,NULL,1),
('Tull americano brilloso',NULL,70.00,50,NULL,1),
('Gasa boal',NULL,35.00,50,NULL,1),
('Visón americano',NULL,30.00,50,NULL,1),
('Tull jarrón (con bordes incluidos)',NULL,115.00,50,NULL,1),
('Tull con pestaña (con bordes incluidos)',NULL,115.00,50,NULL,1)
ON DUPLICATE KEY UPDATE
  `tela_descripcion`=VALUES(`tela_descripcion`),
  `tela_precio`=VALUES(`tela_precio`),
  `tela_stock`=VALUES(`tela_stock`),
  `tela_textura_imagen`=VALUES(`tela_textura_imagen`),
  `tela_activo`=VALUES(`tela_activo`);
