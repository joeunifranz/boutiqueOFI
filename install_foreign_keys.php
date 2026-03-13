<?php
/*
  Instalador opcional de relaciones (FOREIGN KEYS) para BOUTIQUE.
  - No toca código del proyecto: solo aplica ALTER TABLE.
  - Antes de crear cada FK, valida que no existan registros huérfanos.

  Uso: abre http://localhost/BOUTIQUE/install_foreign_keys.php
*/

require_once __DIR__."/config/server.php";

header('Content-Type: text/html; charset=utf-8');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function tableExists(PDO $db, string $table): bool{
    $stmt = $db->prepare("SHOW TABLES LIKE :t");
    $stmt->execute([':t' => $table]);
    return $stmt->rowCount() > 0;
}

function columnExists(PDO $db, string $table, string $column): bool{
    $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
    $stmt->execute([':c' => $column]);
    return $stmt->rowCount() > 0;
}

function constraintExists(PDO $db, string $schema, string $table, string $constraintName): bool{
    $stmt = $db->prepare(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=:s AND TABLE_NAME=:t AND CONSTRAINT_NAME=:c
         LIMIT 1"
    );
    $stmt->execute([':s'=>$schema, ':t'=>$table, ':c'=>$constraintName]);
    return (bool)$stmt->fetchColumn();
}

function isInnoDB(PDO $db, string $schema, string $table): bool{
    $stmt = $db->prepare(
        "SELECT ENGINE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t
         LIMIT 1"
    );
    $stmt->execute([':s'=>$schema, ':t'=>$table]);
    $engine = (string)($stmt->fetchColumn() ?: '');
    return strcasecmp($engine, 'InnoDB') === 0;
}

function orphanCount(PDO $db, string $sql, array $params = []): int{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function orphanDetails(PDO $db, string $sql, array $params = []): array{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function runStep(PDO $db, string $schema, array $step): void{
    $name = $step['name'];
    $child = $step['child_table'];
    $parent = $step['parent_table'];

    echo "<h3>".h($name)."</h3>";

    // Prechecks
    foreach([$child, $parent] as $t){
        if(!tableExists($db, $t)){
            echo "<p style='color:#b00'>Falta la tabla: <b>".h($t)."</b>. Se omite este paso.</p>";
            return;
        }
        if(!isInnoDB($db, $schema, $t)){
            echo "<p style='color:#b00'>La tabla <b>".h($t)."</b> no es InnoDB. Las FK requieren InnoDB. Se omite este paso.</p>";
            return;
        }
    }

    foreach($step['child_columns'] as $col){
        if(!columnExists($db, $child, $col)){
            echo "<p style='color:#b00'>Falta columna <b>".h($child.".".$col)."</b>. Se omite este paso.</p>";
            return;
        }
    }
    foreach($step['parent_columns'] as $col){
        if(!columnExists($db, $parent, $col)){
            echo "<p style='color:#b00'>Falta columna <b>".h($parent.".".$col)."</b>. Se omite este paso.</p>";
            return;
        }
    }

    if(constraintExists($db, $schema, $child, $step['constraint'])){
        echo "<p style='color:#070'>Ya existe la FK <b>".h($step['constraint'])."</b>. Sin cambios.</p>";
        return;
    }

    $orphans = orphanCount($db, $step['orphan_sql']);
    if($orphans > 0){
        echo "<p style='color:#b00'>No se puede crear la FK: hay <b>".h((string)$orphans)."</b> registros huérfanos. Corrige esos datos y vuelve a ejecutar.</p>";
        echo "<details><summary>Ver SQL de verificación</summary><pre>".h($step['orphan_sql'])."</pre></details>";

        if(isset($step['orphan_detail_sql']) && is_string($step['orphan_detail_sql']) && $step['orphan_detail_sql'] !== ''){
            $detailRows = orphanDetails($db, $step['orphan_detail_sql']);
            if(!empty($detailRows)){
                echo "<details><summary>Detalle de huérfanos (agrupado)</summary><pre>".h(json_encode($detailRows, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre></details>";
            }
        }
        return;
    }

    try{
        $db->exec($step['alter_sql']);
        echo "<p style='color:#070'>OK: FK creada.</p>";
        echo "<details><summary>Ver ALTER aplicado</summary><pre>".h($step['alter_sql'])."</pre></details>";
    }catch(Throwable $e){
        echo "<p style='color:#b00'>Error creando FK: ".h($e->getMessage())."</p>";
        echo "<details><summary>Ver ALTER intentado</summary><pre>".h($step['alter_sql'])."</pre></details>";
    }
}

try{
    $db = new PDO(
        "mysql:host=".DB_SERVER.";dbname=".DB_NAME.";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $schema = DB_NAME;

    echo "<h2>Instalador de relaciones (FK) - BOUTIQUE</h2>";
    echo "<p><b>Base de datos:</b> ".h($schema)."</p>";
    echo "<p>Este instalador crea llaves foráneas solo si no hay datos huérfanos. Si falla algún paso, no rompe el proyecto: solo omite ese FK.</p>";

    $steps = [
        [
            'name' => 'reserva(cliente_id) -> cliente(cliente_id)',
            'child_table' => 'reserva',
            'parent_table' => 'cliente',
            'child_columns' => ['cliente_id'],
            'parent_columns' => ['cliente_id'],
            'constraint' => 'reserva_ibfk_1',
            'orphan_sql' => "SELECT COUNT(*) FROM reserva r LEFT JOIN cliente c ON c.cliente_id=r.cliente_id WHERE c.cliente_id IS NULL",
            'alter_sql' => "ALTER TABLE `reserva` ADD CONSTRAINT `reserva_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`cliente_id`) ON DELETE RESTRICT ON UPDATE CASCADE;",
        ],
        [
            'name' => 'reserva(producto_id) -> producto(producto_id)',
            'child_table' => 'reserva',
            'parent_table' => 'producto',
            'child_columns' => ['producto_id'],
            'parent_columns' => ['producto_id'],
            'constraint' => 'reserva_ibfk_2',
            'orphan_sql' => "SELECT COUNT(*) FROM reserva r LEFT JOIN producto p ON p.producto_id=r.producto_id WHERE p.producto_id IS NULL",
            'alter_sql' => "ALTER TABLE `reserva` ADD CONSTRAINT `reserva_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`producto_id`) ON DELETE RESTRICT ON UPDATE CASCADE;",
        ],
        [
            'name' => 'reserva(usuario_id) -> usuario(usuario_id) (nullable)',
            'child_table' => 'reserva',
            'parent_table' => 'usuario',
            'child_columns' => ['usuario_id'],
            'parent_columns' => ['usuario_id'],
            'constraint' => 'reserva_ibfk_3',
            'orphan_sql' => "SELECT COUNT(*) FROM reserva r LEFT JOIN usuario u ON u.usuario_id=r.usuario_id WHERE r.usuario_id IS NOT NULL AND u.usuario_id IS NULL",
            'alter_sql' => "ALTER TABLE `reserva` ADD CONSTRAINT `reserva_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE;",
        ],
        [
            'name' => 'reserva(caja_id) -> caja(caja_id) (nullable)',
            'child_table' => 'reserva',
            'parent_table' => 'caja',
            'child_columns' => ['caja_id'],
            'parent_columns' => ['caja_id'],
            'constraint' => 'reserva_ibfk_4',
            'orphan_sql' => "SELECT COUNT(*) FROM reserva r LEFT JOIN caja c ON c.caja_id=r.caja_id WHERE r.caja_id IS NOT NULL AND c.caja_id IS NULL",
            'alter_sql' => "ALTER TABLE `reserva` ADD CONSTRAINT `reserva_ibfk_4` FOREIGN KEY (`caja_id`) REFERENCES `caja` (`caja_id`) ON DELETE SET NULL ON UPDATE CASCADE;",
        ],
        [
            'name' => 'log_acceso(usuario_id) -> usuario(usuario_id)',
            'child_table' => 'log_acceso',
            'parent_table' => 'usuario',
            'child_columns' => ['usuario_id'],
            'parent_columns' => ['usuario_id'],
            'constraint' => 'log_acceso_ibfk_1',
            'orphan_sql' => "SELECT COUNT(*) FROM log_acceso l LEFT JOIN usuario u ON u.usuario_id=l.usuario_id WHERE u.usuario_id IS NULL",
            'orphan_detail_sql' => "SELECT l.usuario_id, COUNT(*) c FROM log_acceso l LEFT JOIN usuario u ON u.usuario_id=l.usuario_id WHERE u.usuario_id IS NULL GROUP BY l.usuario_id ORDER BY c DESC",
            'alter_sql' => "ALTER TABLE `log_acceso` ADD CONSTRAINT `log_acceso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;",
        ],
        [
            'name' => 'reserva_pago(reserva_codigo) -> reserva(reserva_codigo)',
            'child_table' => 'reserva_pago',
            'parent_table' => 'reserva',
            'child_columns' => ['reserva_codigo'],
            'parent_columns' => ['reserva_codigo'],
            'constraint' => 'reserva_pago_ibfk_1',
            'orphan_sql' => "SELECT COUNT(*) FROM reserva_pago rp LEFT JOIN reserva r ON r.reserva_codigo=rp.reserva_codigo WHERE r.reserva_codigo IS NULL",
            'alter_sql' => "ALTER TABLE `reserva_pago` ADD CONSTRAINT `reserva_pago_ibfk_1` FOREIGN KEY (`reserva_codigo`) REFERENCES `reserva` (`reserva_codigo`) ON DELETE CASCADE ON UPDATE CASCADE;",
        ],
        [
            'name' => 'reserva_horario_bloqueo(usuario_id) -> usuario(usuario_id) (nullable)',
            'child_table' => 'reserva_horario_bloqueo',
            'parent_table' => 'usuario',
            'child_columns' => ['usuario_id'],
            'parent_columns' => ['usuario_id'],
            'constraint' => 'reserva_horario_bloqueo_ibfk_1',
            'orphan_sql' => "SELECT COUNT(*) FROM reserva_horario_bloqueo b LEFT JOIN usuario u ON u.usuario_id=b.usuario_id WHERE b.usuario_id IS NOT NULL AND u.usuario_id IS NULL",
            'alter_sql' => "ALTER TABLE `reserva_horario_bloqueo` ADD CONSTRAINT `reserva_horario_bloqueo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE;",
        ],
    ];

    foreach($steps as $step){
        runStep($db, $schema, $step);
    }

    echo "<hr><p>Fin.</p>";

}catch(Throwable $e){
    echo "<p style='color:#b00'>Error de conexión/ejecución: ".h($e->getMessage())."</p>";
}
