<?php
/*
  Instalador rápido de tabla `reserva`.
  Uso: abre http://localhost/BOUTIQUE/install_reserva_table.php
*/

require_once "./config/server.php";

try {
    $conexion = new PDO(
        "mysql:host=".DB_SERVER.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $check_table = $conexion->query("SHOW TABLES LIKE 'reserva'");

    if($check_table->rowCount() <= 0){
        $sql = "CREATE TABLE `reserva` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;";

        $conexion->exec($sql);
        echo "<p>La tabla 'reserva' ha sido creada correctamente.</p>";
    } else {
        echo "<p>La tabla 'reserva' ya existe en la base de datos.</p>";
    }

} catch (PDOException $e) {
    echo "<p>Error: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</p>";
}
