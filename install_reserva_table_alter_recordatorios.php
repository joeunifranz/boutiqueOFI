<?php
/*
  Ajuste opcional de tabla `reserva` para recordatorios por correo.
  Solo agrega columnas si no existen.
  Uso: abre http://localhost/BOUTIQUE/install_reserva_table_alter_recordatorios.php
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

	$check_table = $conexion->query("SHOW TABLES LIKE 'reserva'");
	if($check_table->rowCount() <= 0){
		echo "<p>No existe la tabla 'reserva'. Ejecuta primero install_reserva_table.php</p>";
		exit;
	}

	$alter = [];
	if(!columnExists($conexion, 'reserva', 'reserva_recordatorio_1d_enviado')){
		$alter[] = "ADD COLUMN `reserva_recordatorio_1d_enviado` tinyint(1) NOT NULL DEFAULT 0";
	}
	if(!columnExists($conexion, 'reserva', 'reserva_recordatorio_1d_enviado_en')){
		$alter[] = "ADD COLUMN `reserva_recordatorio_1d_enviado_en` datetime DEFAULT NULL";
	}
	if(!columnExists($conexion, 'reserva', 'reserva_recordatorio_1d_ultimo_intento')){
		$alter[] = "ADD COLUMN `reserva_recordatorio_1d_ultimo_intento` datetime DEFAULT NULL";
	}
	if(!columnExists($conexion, 'reserva', 'reserva_recordatorio_1d_error')){
		$alter[] = "ADD COLUMN `reserva_recordatorio_1d_error` varchar(255) COLLATE utf8_spanish2_ci DEFAULT NULL";
	}

	if(count($alter) > 0){
		$sql = "ALTER TABLE `reserva` ".implode(', ', $alter).";";
		$conexion->exec($sql);
		echo "<p>Tabla 'reserva' actualizada: recordatorios por correo habilitados.</p>";
	}else{
		echo "<p>No hay cambios: la tabla 'reserva' ya tiene columnas de recordatorio.</p>";
	}

} catch (PDOException $e) {
	echo "<p>Error: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</p>";
}
