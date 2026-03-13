<?php
/*
  Instalador rápido de campo `usuario_rol` en la tabla `usuario`.
  Uso: abre http://localhost/BOUTIQUE/install_usuario_rol_field.php
*/

require_once "./config/server.php";

try {
    $conexion = new PDO(
        "mysql:host=".DB_SERVER.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $check_table = $conexion->query("SHOW TABLES LIKE 'usuario'");
    if($check_table->rowCount() <= 0){
        echo "<p>No existe la tabla 'usuario' en la base de datos.</p>";
        exit();
    }

    $check_col = $conexion->query("SHOW COLUMNS FROM usuario LIKE 'usuario_rol'");

    if($check_col->rowCount() <= 0){
        $sql = "ALTER TABLE usuario ADD COLUMN usuario_rol VARCHAR(20) COLLATE utf8_spanish2_ci NOT NULL DEFAULT 'Usuario'";
        $conexion->exec($sql);
        echo "<p>El campo 'usuario_rol' fue agregado correctamente a la tabla 'usuario'.</p>";
    } else {
        echo "<p>El campo 'usuario_rol' ya existe en la tabla 'usuario'.</p>";
    }

} catch (PDOException $e) {
    echo "<p>Error: ".htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')."</p>";
}
