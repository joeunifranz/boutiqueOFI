<?php

require_once __DIR__.'/autoload.php';

header('Content-Type: text/html; charset=UTF-8');

$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

if($to === ''){
	$default = '';
	echo '<!doctype html><html><head><meta charset="utf-8"><title>Test correo</title></head><body style="font-family:Arial,Helvetica,sans-serif;">';
	echo '<h2>Test de envío de correo</h2>';
	echo '<form method="get">';
	echo '<label>Email destino:</label><br>';
	echo '<input type="email" name="to" value="'.htmlspecialchars($default,ENT_QUOTES,'UTF-8').'" style="width:320px; padding:6px;" required>'; 
	echo '<br><br><button type="submit" style="padding:8px 14px;">Enviar</button>';
	echo '</form>';
	echo '<p style="color:#555;">Envía un correo simple usando la configuración SMTP actual (config/mail.php).</p>';
	echo '</body></html>';
	exit;
}

$subject = 'Test correo - '.(defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE');
$html = '<div style="font-family:Arial,Helvetica,sans-serif; font-size:14px;">'
	.'<p>Hola, este es un correo de prueba desde BOUTIQUE.</p>'
	.'<p>Fecha: '.htmlspecialchars(date('Y-m-d H:i:s'),ENT_QUOTES,'UTF-8').'</p>'
	.'</div>';

$mailer = new \app\services\MailService();
$ok = $mailer->sendHtml($to, $subject, $html);

echo '<!doctype html><html><head><meta charset="utf-8"><title>Test correo</title></head><body style="font-family:Arial,Helvetica,sans-serif;">';
echo '<h2>Resultado</h2>';
if($ok){
	echo '<p style="color:green;">OK: correo enviado a '.htmlspecialchars($to,ENT_QUOTES,'UTF-8').'</p>';
}else{
	$err = $mailer->getLastError() ?: 'Falló (sin detalle)';
	echo '<p style="color:red;">ERROR: '.htmlspecialchars($err,ENT_QUOTES,'UTF-8').'</p>';
}
echo '<p><a href="'.htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8').'">Volver</a></p>';
echo '</body></html>';

