-- Tabla de inventario de encajes (para el diseño superior)
-- Precio: por 1.5 m (metro y medio)

CREATE TABLE IF NOT EXISTS `encaje` (
  `encaje_id` INT NOT NULL AUTO_INCREMENT,
  `encaje_nombre` VARCHAR(140) NOT NULL,
  `encaje_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `encaje_imagen` VARCHAR(255) NULL, -- Ruta local (ej: app/views/fotos/encajes/archivo.jpg)
  `encaje_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `encaje_creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`encaje_id`),
  UNIQUE KEY `uq_encaje_nombre` (`encaje_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `encaje` (`encaje_nombre`,`encaje_precio`,`encaje_imagen`,`encaje_activo`) VALUES
('Rosas con piedras del color del vestido',450.00,NULL,1),
('Rosas con brillo pedrería plateada',570.00,NULL,1),
('Ramas con pedrería plateada',570.00,NULL,1),
('Bordado con pedrería',525.00,NULL,1),
('Encaje en 3D',450.00,NULL,1),
('Vipiur de hojas',525.00,NULL,1),
('Vipiur hojas 3D',525.00,NULL,1),
('Encaje sin pedrería',450.00,NULL,1),
('Vipiur de rosas con poca pedrería',450.00,NULL,1),
('Pedrería diseñada de rosas (pura piedra)',525.00,NULL,1)
ON DUPLICATE KEY UPDATE
  `encaje_precio`=VALUES(`encaje_precio`),
  `encaje_imagen`=VALUES(`encaje_imagen`),
  `encaje_activo`=VALUES(`encaje_activo`);
