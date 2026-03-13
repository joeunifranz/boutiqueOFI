<?php
/*
  Instalador rápido de tabla `reserva_pago`.
  Uso: abre http://localhost/BOUTIQUE/install_reserva_pago_table.php
*/

require_once "./config/server.php";

try {
    $conexion = new PDO(
        "mysql:host=".DB_SERVER.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $check_table = $conexion->query("SHOW TABLES LIKE 'reserva_pago'");

    if($check_table->rowCount() <= 0){
        $sql = "CREATE TABLE `reserva_pago` (
            `reserva_pago_id` int(30) NOT NULL AUTO_INCREMENT,
            `reserva_codigo` varchar(200) COLLATE utf8_spanish2_ci NOT NULL,
            `pago_proveedor` varchar(50) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'mercadopago',
            `pago_preference_id` varchar(120) COLLATE utf8_spanish2_ci DEFAULT NULL,
            `pago_init_point` text COLLATE utf8_spanish2_ci,
            `pago_payment_id` varchar(120) COLLATE utf8_spanish2_ci DEFAULT NULL,
            `pago_qr_id` varchar(120) COLLATE utf8_spanish2_ci DEFAULT NULL,
            `pago_qr_string` text COLLATE utf8_spanish2_ci,
            `pago_status` varchar(40) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'created',
            `pago_monto` decimal(30,2) NOT NULL DEFAULT '0.00',
            `pago_moneda` varchar(10) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'BOB',
            `pago_creado_en` datetime NOT NULL,
            `pago_actualizado_en` datetime DEFAULT NULL,
            `pago_aprobado_en` datetime DEFAULT NULL,
            `pago_raw` longtext COLLATE utf8_spanish2_ci,
            PRIMARY KEY (`reserva_pago_id`),
            KEY `reserva_codigo` (`reserva_codigo`),
            UNIQUE KEY `pago_payment_id_unique` (`pago_payment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;";

        $conexion->exec($sql);
        echo "<p>La tabla 'reserva_pago' ha sido creada correctamente.</p>";
    } else {
        echo "<p>La tabla 'reserva_pago' ya existe en la base de datos.</p>";
    }

} catch (PDOException $e) {
    echo "<p>Error: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</p>";
}
