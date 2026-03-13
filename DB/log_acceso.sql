-- Tabla para logs de acceso al sistema
CREATE TABLE IF NOT EXISTS `log_acceso` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(7) NOT NULL,
  `usuario_nombre` varchar(100) COLLATE utf8_spanish2_ci NOT NULL,
  `usuario_usuario` varchar(30) COLLATE utf8_spanish2_ci NOT NULL,
  `log_fecha` date NOT NULL,
  `log_hora` varchar(17) COLLATE utf8_spanish2_ci NOT NULL,
  `log_ip` varchar(45) COLLATE utf8_spanish2_ci NOT NULL,
  `log_accion` varchar(255) COLLATE utf8_spanish2_ci DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `log_fecha` (`log_fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

-- Restricción de clave foránea (opcional, descomentar si quieres mantener integridad referencial)
-- ALTER TABLE `log_acceso`
--   ADD CONSTRAINT `log_acceso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`) ON DELETE CASCADE;

