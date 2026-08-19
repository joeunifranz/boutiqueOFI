-- Registro de sesiones del probador virtual (integración REST)
CREATE TABLE IF NOT EXISTS `probador_virtual` (
  `probador_id` INT NOT NULL AUTO_INCREMENT,
  `fecha` DATETIME NOT NULL,
  `sesion` VARCHAR(255) NOT NULL,
  `cliente_id` INT NOT NULL,
  `probador_imagen` VARCHAR(255) NULL,
  PRIMARY KEY (`probador_id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_sesion` (`sesion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Relación opcional con solicitud personalizada
-- ALTER TABLE solicitud_personalizada ADD COLUMN probador_id INT NULL AFTER creado_en;
