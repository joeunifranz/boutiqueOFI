-- Tabla para solicitudes de vestido personalizado (cliente)
-- Guarda tela + encaje + cita y bloquea el horario igual que una reserva.

CREATE TABLE IF NOT EXISTS `solicitud_personalizada` (
  `solicitud_id` INT NOT NULL AUTO_INCREMENT,
  `cliente_id` INT NOT NULL,
  `cita_fecha` DATE NOT NULL,
  `cita_hora` VARCHAR(12) NOT NULL,
  `talla` VARCHAR(10) NULL,
  `tela_id` INT NOT NULL,
  `tela_nombre` VARCHAR(80) NOT NULL,
  `tela_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `metros_estimados` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
  `encaje_id` INT NULL,
  `encaje_key` VARCHAR(80) NOT NULL,
  `encaje_nombre` VARCHAR(140) NOT NULL,
  `encaje_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `vestido_detalle` VARCHAR(500) NULL,
  `estado` VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`solicitud_id`),
  KEY `idx_fecha_hora` (`cita_fecha`, `cita_hora`),
  KEY `idx_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
