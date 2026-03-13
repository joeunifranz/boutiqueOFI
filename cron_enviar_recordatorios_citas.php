<?php
/**
 * Envío de recordatorios por correo: 1 día antes de la cita.
 *
 * Ejecutar por CLI (Tarea Programada / Cron):
 *   C:\xxamp\php\php.exe cron_enviar_recordatorios_citas.php
 *
 * Parámetros opcionales:
 *   C:\xxamp\php\php.exe cron_enviar_recordatorios_citas.php 200 --fecha=2026-03-03
 *   C:\xxamp\php\php.exe cron_enviar_recordatorios_citas.php 200 --force
 */

if(PHP_SAPI !== 'cli'){
	http_response_code(403);
	echo "Este script solo se ejecuta por CLI.";
	exit;
}

require_once __DIR__."/config/app.php";
require_once __DIR__."/config/server.php";
require_once __DIR__."/autoload.php";

use app\services\MailService;

$limite = 200;
if(isset($argv[1]) && is_numeric($argv[1])){
	$limite = (int)$argv[1];
	if($limite <= 0){
		$limite = 200;
	}
}

$fechaObjetivo = null;
foreach($argv as $arg){
	if(is_string($arg) && preg_match('/^--fecha=(\d{4}-\d{2}-\d{2})$/', $arg, $m)){
		$fechaObjetivo = $m[1];
		break;
	}
}

$force = in_array('--force', $argv, true) || in_array('--forzar', $argv, true);

try{
	$pdo = new PDO(
		"mysql:host=".DB_SERVER.";dbname=".DB_NAME,
		DB_USER,
		DB_PASS,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]
	);
	$pdo->exec("SET CHARACTER SET utf8");
}catch(Throwable $e){
	fwrite(STDERR, "No se pudo conectar a la BD: ".$e->getMessage()."\n");
	exit(2);
}

// Verificar columnas de recordatorio
try{
	$check = $pdo->prepare("SHOW COLUMNS FROM `reserva` LIKE 'reserva_recordatorio_1d_enviado'");
	$check->execute();
	if($check->rowCount() <= 0){
		fwrite(STDERR, "Faltan columnas de recordatorio. Ejecuta: http://localhost/BOUTIQUE/install_reserva_table_alter_recordatorios.php\n");
		exit(3);
	}
}catch(Throwable $e){
	fwrite(STDERR, "No se pudo verificar la tabla reserva: ".$e->getMessage()."\n");
	exit(4);
}

// Por defecto: mañana (1 día antes).
$fechaTarget = $fechaObjetivo ?: date('Y-m-d', strtotime('+1 day'));

// Para que sea "a las 10am": lo ideal es programar la tarea a las 10:00.
// Este guard evita envíos accidentales si se ejecuta muy temprano.
// Nota: si el servidor estuvo apagado a las 10:00, la tarea puede ejecutarse tarde;
// en ese caso permitimos el envío (>= 10:00) para no perder recordatorios.
if($fechaObjetivo === null && !$force){
	$horaActual = (int)date('G');
	if($horaActual < 10){
		echo "Guard: este cron está pensado para ejecutarse desde las 10:00. Hora actual: ".date('H:i')."\n";
		echo "Usa --force para enviar fuera de horario o --fecha=YYYY-MM-DD para pruebas.\n";
		exit(0);
	}
}

$sql = "
	SELECT
		r.reserva_id,
		r.reserva_codigo,
		r.reserva_fecha,
		r.reserva_hora,
		r.reserva_estado,
		c.cliente_nombre,
		c.cliente_apellido,
		c.cliente_email,
		p.producto_nombre
	FROM reserva r
	INNER JOIN cliente c ON c.cliente_id = r.cliente_id
	INNER JOIN producto p ON p.producto_id = r.producto_id
	WHERE r.reserva_fecha = :f
	  AND r.reserva_estado NOT IN ('rechazada','completada')
	  AND (r.reserva_recordatorio_1d_enviado = 0 OR r.reserva_recordatorio_1d_enviado IS NULL)
	ORDER BY r.reserva_hora ASC
	LIMIT ".$limite.";
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':f', $fechaTarget);
$stmt->execute();
$pendientes = $stmt->fetchAll();

$mailer = new MailService();

$updOk = $pdo->prepare("UPDATE reserva SET
	reserva_recordatorio_1d_enviado=1,
	reserva_recordatorio_1d_enviado_en=NOW(),
	reserva_recordatorio_1d_ultimo_intento=NOW(),
	reserva_recordatorio_1d_error=NULL
	WHERE reserva_id=:id
	LIMIT 1");

$updErr = $pdo->prepare("UPDATE reserva SET
	reserva_recordatorio_1d_ultimo_intento=NOW(),
	reserva_recordatorio_1d_error=:err
	WHERE reserva_id=:id
	LIMIT 1");

$enviados = 0;
$fallidos = 0;
$omitidos = 0;

foreach($pendientes as $r){
	$reservaId = (int)$r['reserva_id'];
	$codigo = (string)($r['reserva_codigo'] ?? '');
	$email = trim((string)($r['cliente_email'] ?? ''));

	if($email==='' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
		$omitidos++;
		$updErr->execute([
			':err' => 'Email inválido o vacío',
			':id' => $reservaId,
		]);
		continue;
	}

	$cliente = trim((string)($r['cliente_nombre'] ?? '').' '.(string)($r['cliente_apellido'] ?? ''));
	if($cliente===''){
		$cliente = 'Cliente';
	}

	$producto = (string)($r['producto_nombre'] ?? '');
	$hora = (string)($r['reserva_hora'] ?? '');

	$subject = 'Recordatorio de tu cita (mañana) - '.(defined('APP_NAME') ? APP_NAME : 'BOUTIQUE');
	$fechaPretty = $fechaTarget;
	try{
		$dt = new DateTime($fechaTarget);
		$fechaPretty = $dt->format('d/m/Y');
	}catch(Throwable $e){
		// keep
	}

	$html = "
		<div style=\"font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#111;\">
			<p>Hola <strong>".htmlspecialchars($cliente,ENT_QUOTES,'UTF-8')."</strong>,</p>
			<p>Te recordamos que tienes una cita agendada para <strong>mañana</strong>:</p>
			<ul>
				<li><strong>Fecha:</strong> ".htmlspecialchars($fechaPretty,ENT_QUOTES,'UTF-8')."</li>
				<li><strong>Hora:</strong> ".htmlspecialchars($hora,ENT_QUOTES,'UTF-8')."</li>
				<li><strong>Producto:</strong> ".htmlspecialchars($producto,ENT_QUOTES,'UTF-8')."</li>
				<li><strong>Código:</strong> ".htmlspecialchars($codigo,ENT_QUOTES,'UTF-8')."</li>
			</ul>
			<p>Gracias,<br>".htmlspecialchars((defined('APP_NAME') ? APP_NAME : 'BOUTIQUE'),ENT_QUOTES,'UTF-8')."</p>
		</div>
	";

	$ok = $mailer->sendHtml($email, $subject, $html);
	if($ok){
		$enviados++;
		$updOk->execute([':id' => $reservaId]);
	}else{
		$fallidos++;
		$err = $mailer->getLastError() ?: 'Falló envío (sin detalle)';
		fwrite(STDERR, "Fallo [{$codigo}] -> {$email} :: {$err}\n");
		$errDb = function_exists('mb_substr') ? mb_substr($err, 0, 255, 'UTF-8') : substr($err, 0, 255);
		$updErr->execute([
			':err' => $errDb,
			':id' => $reservaId,
		]);
	}
}

echo "Fecha objetivo: {$fechaTarget}\n";
echo "Pendientes: ".count($pendientes)."\n";
echo "Enviados: {$enviados}\n";
echo "Fallidos: {$fallidos}\n";
echo "Omitidos: {$omitidos}\n";
