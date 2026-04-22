-- Agrega soporte de notificaciones cliente para reservas
-- Ejecutar sobre la base de datos de BOUTIQUE

ALTER TABLE `reserva`
  ADD COLUMN `reserva_cliente_notificacion` INT NOT NULL DEFAULT 0
  AFTER `reserva_estado`;
