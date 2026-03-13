<?php
/*
  Reparación opcional de huérfanos en log_acceso.

  Problema típico: registros con usuario_id=0 (o IDs inexistentes) impiden crear la FK log_acceso(usuario_id)->usuario(usuario_id).

  Uso:
  - Vista/diagnóstico: http://localhost/BOUTIQUE/install_fix_log_acceso_orphans.php
  - Reasignar huérfanos al primer usuario existente: http://localhost/BOUTIQUE/install_fix_log_acceso_orphans.php?action=reassign
  - Eliminar registros huérfanos: http://localhost/BOUTIQUE/install_fix_log_acceso_orphans.php?action=delete

  Nota: Esto modifica datos SOLO de log_acceso.
*/

require_once __DIR__."/config/server.php";

header('Content-Type: text/html; charset=utf-8');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function tableExists(PDO $db, string $table): bool{
    $stmt = $db->prepare("SHOW TABLES LIKE :t");
    $stmt->execute([':t' => $table]);
    return $stmt->rowCount() > 0;
}

try{
    $db = new PDO(
        "mysql:host=".DB_SERVER.";dbname=".DB_NAME.";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>Fix huérfanos log_acceso</h2>";
    echo "<p><b>BD:</b> ".h(DB_NAME)."</p>";

    if(!tableExists($db, 'log_acceso')){
        echo "<p style='color:#b00'>No existe la tabla log_acceso.</p>";
        exit;
    }
    if(!tableExists($db, 'usuario')){
        echo "<p style='color:#b00'>No existe la tabla usuario.</p>";
        exit;
    }

    $orphansSql = "SELECT l.usuario_id, COUNT(*) c
                   FROM log_acceso l
                   LEFT JOIN usuario u ON u.usuario_id=l.usuario_id
                   WHERE u.usuario_id IS NULL
                   GROUP BY l.usuario_id
                   ORDER BY c DESC";

    $orphans = $db->query($orphansSql)->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach($orphans as $r){ $total += (int)($r['c'] ?? 0); }

    echo "<p><b>Huérfanos detectados:</b> ".h((string)$total)."</p>";
    if(!empty($orphans)){
        echo "<details open><summary>Detalle</summary><pre>".h(json_encode($orphans, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre></details>";
    }

    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
    $action = trim($action);

    if($total <= 0){
        echo "<p style='color:#070'>No hay nada que corregir.</p>";
        echo "<p>Puedes ejecutar ahora el instalador de FKs: <a href='install_foreign_keys.php'>install_foreign_keys.php</a></p>";
        exit;
    }

    // Elegir un usuario por defecto para reasignar
    $defaultUserId = (int)($db->query("SELECT usuario_id FROM usuario ORDER BY usuario_id ASC LIMIT 1")->fetchColumn() ?: 0);
    if($defaultUserId <= 0){
        echo "<p style='color:#b00'>No hay usuarios en la tabla usuario. No se puede reasignar.</p>";
        exit;
    }

    if($action === ''){
        echo "<p>Acciones disponibles:</p>";
        echo "<ul>";
        echo "<li><a href='?action=reassign'>Reasignar huérfanos al usuario_id=".h((string)$defaultUserId)."</a></li>";
        echo "<li><a href='?action=delete' onclick='return confirm(\"¿Seguro? Esto borrará registros huérfanos de log_acceso\");'>Eliminar registros huérfanos</a></li>";
        echo "</ul>";
        echo "<p>Luego ejecuta: <a href='install_foreign_keys.php'>install_foreign_keys.php</a></p>";
        exit;
    }

    $db->beginTransaction();

    if($action === 'reassign'){
        $sql = "UPDATE log_acceso l
                LEFT JOIN usuario u ON u.usuario_id=l.usuario_id
                SET l.usuario_id=:uid
                WHERE u.usuario_id IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $defaultUserId]);
        $affected = $stmt->rowCount();
        $db->commit();
        echo "<p style='color:#070'>OK: Reasignados ".h((string)$affected)." registros al usuario_id=".h((string)$defaultUserId).".</p>";
    }elseif($action === 'delete'){
        $sql = "DELETE l FROM log_acceso l
                LEFT JOIN usuario u ON u.usuario_id=l.usuario_id
                WHERE u.usuario_id IS NULL";
        $affected = $db->exec($sql);
        $db->commit();
        echo "<p style='color:#070'>OK: Eliminados ".h((string)$affected)." registros huérfanos.</p>";
    }else{
        $db->rollBack();
        echo "<p style='color:#b00'>Acción inválida.</p>";
        exit;
    }

    echo "<p>Ahora ejecuta el instalador de FKs: <a href='install_foreign_keys.php'>install_foreign_keys.php</a></p>";

}catch(Throwable $e){
    if(isset($db) && $db instanceof PDO && $db->inTransaction()){
        $db->rollBack();
    }
    echo "<p style='color:#b00'>Error: ".h($e->getMessage())."</p>";
}
