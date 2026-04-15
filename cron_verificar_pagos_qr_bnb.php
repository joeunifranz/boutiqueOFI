<?php
/**
 * Verifica pagos por QR BNB y registra/confirmar automáticamente.
 *
 * Ejecutar por CLI (Tarea Programada / Cron):
 *   C:\xxamp\php\php.exe cron_verificar_pagos_qr_bnb.php 200
 *
 * Parámetros:
 *   1) límite (default 200)
 */

if(PHP_SAPI !== 'cli'){
	http_response_code(403);
	echo "Este script solo se ejecuta por CLI.";
	exit;
}

require_once __DIR__."/config/app.php";
require_once __DIR__."/config/server.php";
require_once __DIR__."/autoload.php";

use app\controllers\reservationController;
use app\services\BnbQrService;

$limite = 200;
if(isset($argv[1]) && is_numeric($argv[1])){
	$limite = (int)$argv[1];
	if($limite <= 0){
		$limite = 200;
	}
}

// Config BNB
$rutaBnb = __DIR__."/config/bnb_qr.php";
if(!file_exists($rutaBnb)){
	fwrite(STDERR, "Falta config/bnb_qr.php\n");
	exit(2);
}
require_once $rutaBnb;

$bnb = new BnbQrService();
if(!$bnb->configuracionValida()){
	fwrite(STDERR, "BNB QR no está configurado (revisa config/bnb_qr.php).\n");
	exit(3);
}

try{
	$dsn = "mysql:host=".DB_SERVER.";dbname=".DB_NAME;
	if(defined('DB_PORT') && (string)DB_PORT !== ''){
		$dsn .= ";port=".DB_PORT;
	}
	$pdo = new PDO(
		$dsn,
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
	exit(4);
}

// Verificar tabla
try{
	$check = $pdo->query("SHOW TABLES LIKE 'reserva_pago'");
	if(!$check || $check->rowCount()<=0){
		fwrite(STDERR, "Falta la tabla reserva_pago. Ejecuta el SQL en DB/reserva.sql\n");
		exit(5);
	}
}catch(Throwable $e){
	fwrite(STDERR, "No se pudo verificar la tabla reserva_pago: ".$e->getMessage()."\n");
	exit(6);
}

$limite = (int)$limite;
$sql = "
	SELECT reserva_pago_id, reserva_codigo, pago_payment_id, pago_qr_id, pago_status, pago_monto, pago_moneda
	FROM reserva_pago
	WHERE pago_proveedor='bnb'
	  AND (pago_status IS NULL OR pago_status IN ('created','pending','in_process','processing'))
	ORDER BY reserva_pago_id ASC
	LIMIT {$limite}
";

$rows = [];
try{
	$rows = $pdo->query($sql)->fetchAll();
}catch(Throwable $e){
	fwrite(STDERR, "No se pudo consultar reserva_pago: ".$e->getMessage()."\n");
	exit(7);
}

if(!$rows){
	echo "OK: no hay pagos BNB pendientes.\n";
	exit(0);
}

$insReserva = new reservationController();

$token = $bnb->obtenerToken();
if(!is_string($token) || trim($token)===''){
	fwrite(STDERR, "No se pudo obtener token BNB. Verifica credenciales.\n");
	exit(8);
}

$procesados = 0;
$aprobados = 0;
$errores = 0;

foreach($rows as $r){
	$codigo = (string)$r['reserva_codigo'];
	$qrId = (string)($r['pago_qr_id'] ?? '');
	if($qrId===''){
		$qrId = (string)($r['pago_payment_id'] ?? '');
	}
	if($codigo==='' || $qrId===''){
		continue;
	}

	$resp = $bnb->getQRStatusAsync($qrId, $token);
	$procesados++;

	if(!is_array($resp) || (($resp['_http_code'] ?? 0) < 200) || (($resp['_http_code'] ?? 0) >= 300)){
		$errores++;
		continue;
	}

	$mensaje = $resp['Mensaje'] ?? null;
	$payload = null;
	if(is_string($mensaje) && trim($mensaje)!==''){
		$payload = json_decode($mensaje, true);
	}
	if(!is_array($payload)){
		$payload = $resp;
	}

	$status = (string)($payload['status'] ?? ($payload['qrStatus'] ?? ($payload['state'] ?? '')));
	if($status===''){
		// Si el API no devuelve status explícito, no auto-aprobamos.
		$status = (string)($payload['message'] ?? 'pending');
	}

	$monto = (float)($payload['amount'] ?? ($payload['transaction_amount'] ?? ($r['pago_monto'] ?? 0)));
	$moneda = (string)($payload['currency'] ?? ($payload['currency_id'] ?? ($r['pago_moneda'] ?? (defined('BNB_CURRENCY_ID') ? BNB_CURRENCY_ID : 'BOB'))));
	$paymentId = (string)($payload['paymentId'] ?? ($payload['transactionId'] ?? ($r['pago_payment_id'] ?? $qrId)));

	$ok = $insReserva->procesarEstadoPagoBnbControlador($codigo, $paymentId, $status, $monto, $moneda, $payload);
	if(!$ok){
		$errores++;
		continue;
	}

	// contar aprobados segun config
	$approved = false;
	if(defined('BNB_APPROVED_STATUSES') && is_array(BNB_APPROVED_STATUSES)){
		$approved = in_array(strtolower($status), array_map('strtolower', BNB_APPROVED_STATUSES), true);
	}
	if($approved){
		$aprobados++;
	}
}

echo "OK: procesados={$procesados}; aprobados={$aprobados}; errores={$errores}\n";
