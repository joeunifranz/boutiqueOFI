<?php

$code = (isset($_GET['code'])) ? (string)$_GET['code'] : '';

require_once "../../config/app.php";
require_once "../../autoload.php";

use app\services\TicketPdfService;

$svc = new TicketPdfService();
$pdf = $svc->generarTicketReserva($code);

if(is_string($pdf) && $pdf !== ''){
	$filename = 'Ticket_Reserva_'.$code.'.pdf';
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="'.$filename.'"');
	header('Content-Length: '.strlen($pdf));
	echo $pdf;
	exit;
}

?><!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<title><?php echo defined('APP_NAME') ? APP_NAME : 'BOUTIQUE'; ?></title>
	<?php include '../views/inc/head.php'; ?>
</head>
<body>
	<div class="main-container">
		<section class="hero-body">
			<div class="hero-body">
				<p class="has-text-centered has-text-white pb-3">
					<i class="fas fa-rocket fa-5x"></i>
				</p>
				<p class="title has-text-white">¡Ocurrió un error!</p>
				<p class="subtitle has-text-white"><?php echo htmlspecialchars($svc->getLastError() ?: 'No se pudo generar el ticket de reserva', ENT_QUOTES, 'UTF-8'); ?></p>
			</div>
		</section>
	</div>
	<?php include '../views/inc/script.php'; ?>
</body>
</html>
