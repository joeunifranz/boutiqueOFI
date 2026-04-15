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

-- Pagos online por QR (BISA / otros proveedores)
-- Esta tabla permite:
-- 1) guardar el QR dinámico generado para una reserva
-- 2) registrar el pago cuando llega el webhook/notificación del banco
CREATE TABLE IF NOT EXISTS `reserva_pago` (
  `reserva_pago_id` int(30) NOT NULL AUTO_INCREMENT,
  `reserva_codigo` varchar(200) COLLATE utf8_spanish2_ci NOT NULL,
  `pago_proveedor` varchar(30) COLLATE utf8_spanish2_ci NOT NULL,
  `pago_preference_id` varchar(200) COLLATE utf8_spanish2_ci DEFAULT NULL,
  `pago_init_point` varchar(600) COLLATE utf8_spanish2_ci DEFAULT NULL,
  `pago_payment_id` varchar(200) COLLATE utf8_spanish2_ci DEFAULT NULL,
  `pago_status` varchar(50) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'created',
  `pago_monto` decimal(30,2) NOT NULL DEFAULT '0.00',
  `pago_moneda` varchar(10) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'BOB',
  `pago_qr_id` varchar(200) COLLATE utf8_spanish2_ci DEFAULT NULL,
  `pago_qr_string` text COLLATE utf8_spanish2_ci,
  `pago_raw` longtext COLLATE utf8_spanish2_ci,
  `pago_creado_en` datetime NOT NULL,
  `pago_actualizado_en` datetime NOT NULL,
  `pago_aprobado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`reserva_pago_id`),
  KEY `idx_reserva_codigo` (`reserva_codigo`),
  KEY `idx_status` (`pago_status`),
  UNIQUE KEY `uq_proveedor_payment` (`pago_proveedor`,`pago_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

-- (Opcional) llaves foráneas. Actívalas si tu BD usa FKs.
-- ALTER TABLE `reserva`
--   ADD CONSTRAINT `reserva_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`cliente_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
--   ADD CONSTRAINT `reserva_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`producto_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
--   ADD CONSTRAINT `reserva_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE,
--   ADD CONSTRAINT `reserva_ibfk_4` FOREIGN KEY (`caja_id`) REFERENCES `caja` (`caja_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- (Opcional) FK para pagos (requiere que `reserva_codigo` sea UNIQUE en `reserva`)
-- ALTER TABLE `reserva_pago`
--   ADD CONSTRAINT `reserva_pago_ibfk_1` FOREIGN KEY (`reserva_codigo`) REFERENCES `reserva` (`reserva_codigo`) ON DELETE CASCADE ON UPDATE CASCADE;
