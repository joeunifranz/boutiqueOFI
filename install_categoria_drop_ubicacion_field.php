<?php
/*
  Instalador rápido para eliminar el campo `categoria_ubicacion` de la tabla `categoria`.
  Uso: abre http://localhost/BOUTIQUE/install_categoria_drop_ubicacion_field.php

  Nota: este cambio es irreversible (se perderán los datos de ubicacion).
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

	$check_table = $conexion->query("SHOW TABLES LIKE 'categoria'");
	if($check_table->rowCount() <= 0){
		echo "<p>No existe la tabla 'categoria' en la base de datos.</p>";
		exit();
	}

	if(!columnExists($conexion, 'categoria', 'categoria_ubicacion')){
		echo "<p>No hay cambios: el campo 'categoria_ubicacion' ya no existe en la tabla 'categoria'.</p>";
		exit();
	}

	$conexion->exec("ALTER TABLE `categoria` DROP COLUMN `categoria_ubicacion`");
	echo "<p>Listo: el campo 'categoria_ubicacion' fue eliminado de la tabla 'categoria'.</p>";

} catch (PDOException $e) {
	echo "<p>Error: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</p>";
}
