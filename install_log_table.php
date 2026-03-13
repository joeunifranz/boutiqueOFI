<?php
/**
 * Script para crear la tabla de logs de acceso
 * Ejecuta este archivo una vez desde el navegador: http://localhost/BOUTIQUE/install_log_table.php
 */

require_once "./config/server.php";
require_once "./config/app.php";

try {
    $conexion = new PDO("mysql:host=".DB_SERVER.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->exec("SET CHARACTER SET utf8");

    // Verificar si la tabla ya existe
    $check_table = $conexion->query("SHOW TABLES LIKE 'log_acceso'");
    
    if($check_table->rowCount() == 0){
        // Crear la tabla
        $sql = "CREATE TABLE `log_acceso` (
          `log_id` int(11) NOT NULL AUTO_INCREMENT,
          `usuario_id` int(7) NOT NULL,
          `usuario_nombre` varchar(100) COLLATE utf8_spanish2_ci NOT NULL,
          `usuario_usuario` varchar(30) COLLATE utf8_spanish2_ci NOT NULL,
          `log_fecha` date NOT NULL,
          `log_hora` varchar(17) COLLATE utf8_spanish2_ci NOT NULL,
          `log_ip` varchar(45) COLLATE utf8_spanish2_ci NOT NULL,
                    `log_accion` varchar(255) COLLATE utf8_spanish2_ci DEFAULT NULL,
          PRIMARY KEY (`log_id`),
          KEY `usuario_id` (`usuario_id`),
          KEY `log_fecha` (`log_fecha`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci";

        $conexion->exec($sql);
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Instalación de Logs</title>
            <style>
                body { font-family: Arial; padding: 50px; background: #f5f5f5; }
                .success { background: #4CAF50; color: white; padding: 20px; border-radius: 5px; }
                .info { background: #2196F3; color: white; padding: 15px; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='success'>
                <h2>✓ Tabla creada exitosamente</h2>
                <p>La tabla 'log_acceso' ha sido creada correctamente.</p>
            </div>
            <div class='info'>
                <p><strong>Puedes eliminar este archivo (install_log_table.php) ahora.</strong></p>
                <p><a href='".APP_URL."login/' style='color: white; text-decoration: underline;'>Ir al login</a></p>
            </div>
        </body>
        </html>";
    } else {

        // Si la tabla existe pero no tiene la columna log_accion, agregarla
        $check_column = $conexion->query("SHOW COLUMNS FROM log_acceso LIKE 'log_accion'");
        if($check_column->rowCount() == 0){
            $conexion->exec("ALTER TABLE log_acceso ADD COLUMN log_accion varchar(255) COLLATE utf8_spanish2_ci DEFAULT NULL");
        }

        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Instalación de Logs</title>
            <style>
                body { font-family: Arial; padding: 50px; background: #f5f5f5; }
                .info { background: #2196F3; color: white; padding: 20px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='info'>
                <h2>ℹ La tabla ya existe</h2>
                <p>La tabla 'log_acceso' ya existe en la base de datos.</p>
                <p><strong>Puedes eliminar este archivo (install_log_table.php) ahora.</strong></p>
                <p><a href='".APP_URL."login/' style='color: white; text-decoration: underline;'>Ir al login</a></p>
            </div>
        </body>
        </html>";
    }

} catch(PDOException $e) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <style>
            body { font-family: Arial; padding: 50px; background: #f5f5f5; }
            .error { background: #f44336; color: white; padding: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>✗ Error</h2>
            <p>Error al crear la tabla: " . $e->getMessage() . "</p>
            <p>Verifica la configuración de la base de datos en config/server.php</p>
        </div>
    </body>
    </html>";
}

