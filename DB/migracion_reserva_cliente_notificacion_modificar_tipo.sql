-- Si ya existe la columna como TINYINT, la convertimos a INT para soportar múltiples acciones
-- Ejecutar sobre la base de datos de BOUTIQUE

ALTER TABLE `reserva`
  MODIFY COLUMN `reserva_cliente_notificacion` INT NOT NULL DEFAULT 0;
