CREATE TABLE IF NOT EXISTS `reserva` (
  `reserva_id` int(30) NOT NULL AUTO_INCREMENT,
  `reserva_codigo` varchar(200) COLLATE utf8_spanish2_ci NOT NULL,
  `reserva_fecha` date NOT NULL,
  `reserva_hora` varchar(17) COLLATE utf8_spanish2_ci NOT NULL,
  `reserva_total` decimal(30,2) NOT NULL,
  `reserva_abono` decimal(30,2) NOT NULL DEFAULT '0.00',
  `reserva_estado` varchar(20) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'pendiente',
  `reserva_observacion` varchar(255) COLLATE utf8_spanish2_ci DEFAULT NULL,
  `reserva_recordatorio_1d_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `reserva_recordatorio_1d_enviado_en` datetime DEFAULT NULL,
  `reserva_recordatorio_1d_ultimo_intento` datetime DEFAULT NULL,
  `reserva_recordatorio_1d_error` varchar(255) COLLATE utf8_spanish2_ci DEFAULT NULL,
  `cliente_id` int(10) NOT NULL,
  `producto_id` int(20) NOT NULL,
  `usuario_id` int(7) DEFAULT NULL,
  `caja_id` int(5) DEFAULT NULL,
  PRIMARY KEY (`reserva_id`),
  UNIQUE KEY `reserva_codigo_unique` (`reserva_codigo`),
  KEY `cliente_id` (`cliente_id`),
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

-- (Opcional) llaves foráneas. Actívalas si tu BD usa FKs.
-- ALTER TABLE `reserva`
--   ADD CONSTRAINT `reserva_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`cliente_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
--   ADD CONSTRAINT `reserva_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`producto_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
--   ADD CONSTRAINT `reserva_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE,
--   ADD CONSTRAINT `reserva_ibfk_4` FOREIGN KEY (`caja_id`) REFERENCES `caja` (`caja_id`) ON DELETE SET NULL ON UPDATE CASCADE;
