-- Tabla de inventario de telas
-- Ejecuta este script en tu BD (boutique) si aún no existe la tabla.

CREATE TABLE IF NOT EXISTS `tela` (
  `tela_id` INT NOT NULL AUTO_INCREMENT,
  `tela_nombre` VARCHAR(80) NOT NULL,
  `tela_descripcion` VARCHAR(255) NULL,
  `tela_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tela_stock` INT NOT NULL DEFAULT 0,
  `tela_textura_url` VARCHAR(255) NULL,
  `tela_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `tela_creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tela_id`),
  UNIQUE KEY `uq_tela_nombre` (`tela_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Datos de ejemplo (puedes editar/eliminar)
INSERT IGNORE INTO `tela` (`tela_nombre`,`tela_descripcion`,`tela_precio`,`tela_stock`,`tela_textura_url`,`tela_activo`) VALUES
('Algodón','Tela fresca y cómoda',25.00,50,NULL,1),
('Seda','Tela premium con brillo',80.00,20,NULL,1),
('Encaje','Acabado elegante',60.00,15,NULL,1),
('Lino','Tela ligera',35.00,30,NULL,1);
