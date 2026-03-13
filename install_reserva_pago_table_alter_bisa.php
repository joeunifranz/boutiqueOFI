<?php
/*
  Ajuste opcional de tabla `reserva_pago` para uso con BISA.
  Solo agrega columnas si no existen.
  Uso: abre http://localhost/BOUTIQUE/install_reserva_pago_table_alter_bisa.php
*/

require_once "./config/server.php";

function columnExists(PDO $pdo, string $table, string $column): bool{
	$stmt = $pdo->prepare("SHOW COLUMNS FROM `".$table."` LIKE :col");
	$stmt->bindParam(':col', $column);
	$stmt->execute();
	return $stmt->rowCount() >= 1;
}

try {
    $conexion = new PDO(
        "mysql:host=".DB_SERVER.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$check_table = $conexion->query("SHOW TABLES LIKE 'reserva_pago'");
	if($check_table->rowCount() <= 0){
		echo "<p>No existe la tabla 'reserva_pago'. Ejecuta primero install_reserva_pago_table.php</p>";
		exit;
	}

	$alter = [];
	if(!columnExists($conexion, 'reserva_pago', 'pago_qr_id')){
		$alter[] = "ADD COLUMN `pago_qr_id` varchar(120) COLLATE utf8_spanish2_ci DEFAULT NULL";
	}
	if(!columnExists($conexion, 'reserva_pago', 'pago_qr_string')){
		$alter[] = "ADD COLUMN `pago_qr_string` text COLLATE utf8_spanish2_ci";
	}
	if(count($alter) > 0){
		$sql = "ALTER TABLE `reserva_pago` ".implode(', ', $alter).";";
		$conexion->exec($sql);
		echo "<p>Tabla 'reserva_pago' actualizada para BISA.</p>";
	}else{
		echo "<p>No hay cambios: la tabla 'reserva_pago' ya tiene columnas BISA.</p>";
	}

} catch (PDOException $e) {
    echo "<p>Error: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</p>";
}
