<?php

use app\controllers\reservationController;
use app\controllers\saleController;

$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
$clienteId = $clienteLogueado ? (int)$_SESSION['cliente_id'] : 0;

$code = isset($url[1]) ? (string)$url[1] : '';

$insReserva = new reservationController();
$reserva = ($clienteLogueado && $code !== '') ? $insReserva->obtenerReservaPorCodigoParaClienteControlador($code, $clienteId) : null;

$insVenta = new saleController();
$empresa = [];
try{
	$empresaStmt = $insVenta->seleccionarDatos('Normal', 'empresa LIMIT 1', '*', 0);
	$empresa = $empresaStmt && $empresaStmt->rowCount() >= 1 ? (array)$empresaStmt->fetch() : [];
}catch(Throwable $e){
	$empresa = [];
}

$direccion = trim((string)($empresa['empresa_direccion'] ?? ''));
$telefonoRaw = trim((string)($empresa['empresa_telefono'] ?? ''));
$telDigits = preg_replace('/\D+/', '', $telefonoRaw);
if(is_string($telDigits) && strlen($telDigits) <= 8 && $telDigits !== ''){
	$telDigits = '591'.$telDigits;
}

$waMsg = 'Hola, tengo una consulta sobre mi reserva.';
$waUrl = ($telDigits !== '') ? ('https://wa.me/'.$telDigits.'?text='.urlencode($waMsg)) : '';
$mapsUrl = ($direccion !== '') ? ('https://www.google.com/maps/search/?api=1&query='.urlencode($direccion)) : '';

?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title">Seguimiento de reserva</h1>

	<?php if(!$clienteLogueado){ ?>
		<article class="message is-warning"><div class="message-body">Debes iniciar sesión para ver el seguimiento.</div></article>
		<?php return; ?>
	<?php } ?>

	<?php if(!$reserva){ ?>
		<article class="message is-danger"><div class="message-body">Reserva no encontrada.</div></article>
		<div class="buttons"><a class="button is-light" href="<?php echo APP_URL; ?>reservasComprasCliente/">Volver</a></div>
		<?php return; ?>
	<?php } ?>

	<?php
		$codigo = (string)($reserva['reserva_codigo'] ?? '');
		$estado = (string)($reserva['reserva_estado'] ?? '');
		$total = (float)($reserva['reserva_total'] ?? 0);
		$abono = (float)($reserva['reserva_abono'] ?? 0);
		$saldo = $total - $abono;
		if($saldo < 0){ $saldo = 0; }
		$foto = (string)($reserva['producto_foto'] ?? '');
		$pagarUrl = APP_URL.'reservaPagar/'.urlencode($codigo).'/';
		$ticketUrl = APP_URL.'app/pdf/reserva_ticket.php?code='.urlencode($codigo);
	?>

	<div class="box">
		<h2 class="title is-6 mb-3">Seguimiento</h2>
		<div class="content">
			<ul>
				<li><strong>Paso 1:</strong> Reserva registrada</li>
			</ul>
		</div>

		<div class="columns is-variable is-5 is-centered" style="align-items:flex-start; max-width: 920px; margin: 0 auto;">
			<div class="column" style="max-width: 560px; margin: 0 auto;">
				<div class="table-container">
					<table class="table is-narrow is-fullwidth">
						<tbody>
							<tr>
								<td class="has-text-weight-semibold is-size-5">Código</td>
								<td class="is-size-5"><?php echo htmlspecialchars($codigo,ENT_QUOTES,'UTF-8'); ?></td>
							</tr>
							<tr>
								<td class="has-text-weight-semibold is-size-5">Vestido</td>
								<td class="is-size-5"><?php echo htmlspecialchars((string)($reserva['producto_nombre'] ?? ''),ENT_QUOTES,'UTF-8'); ?></td>
							</tr>
							<tr>
								<td class="has-text-weight-semibold is-size-5">Cita</td>
								<td class="is-size-5"><?php echo htmlspecialchars(trim((string)($reserva['reserva_fecha'] ?? '').' '.(string)($reserva['reserva_hora'] ?? '')),ENT_QUOTES,'UTF-8'); ?></td>
							</tr>
							<tr>
								<td class="has-text-weight-semibold is-size-5">Estado</td>
								<td class="is-size-5"><?php echo htmlspecialchars($estado,ENT_QUOTES,'UTF-8'); ?></td>
							</tr>
							<tr>
								<td class="has-text-weight-semibold is-size-5">Abono</td>
								<td class="is-size-5"><?php echo MONEDA_SIMBOLO.number_format($abono,2); ?> <?php echo MONEDA_NOMBRE; ?></td>
							</tr>
							<tr>
								<td class="has-text-weight-semibold is-size-5">Debe pagar</td>
								<td class="is-size-5"><?php echo MONEDA_SIMBOLO.number_format($saldo,2); ?> <?php echo MONEDA_NOMBRE; ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<?php if($foto!=='' && is_file("./app/views/productos/".$foto)){ ?>
				<div class="column is-narrow has-text-centered">
					<figure class="image" style="width:260px; margin: 0 auto;">
						<img src="<?php echo APP_URL; ?>app/views/productos/<?php echo htmlspecialchars($foto,ENT_QUOTES,'UTF-8'); ?>" alt="">
					</figure>
				</div>
			<?php } ?>
		</div>

		<div class="buttons mt-4" style="flex-wrap:wrap;">
			<a class="button is-light" href="<?php echo htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt"></i> &nbsp; Ver ticket de reserva</a>
			<?php if($saldo > 0 && $estado !== 'rechazada' && $estado !== 'completada'){ ?>
				<a class="button is-success" href="<?php echo htmlspecialchars($pagarUrl,ENT_QUOTES,'UTF-8'); ?>">Pagar</a>
			<?php } ?>
			<a class="button is-light" href="<?php echo APP_URL; ?>reservasComprasCliente/">Volver</a>
		</div>

		<?php if(!empty($reserva['reserva_observacion'])){ ?>
			<article class="message is-info mt-4"><div class="message-body"><strong>Nota:</strong> <?php echo htmlspecialchars((string)$reserva['reserva_observacion'],ENT_QUOTES,'UTF-8'); ?></div></article>
		<?php } ?>

		<?php if($estado==='reprogramada'){ ?>
			<article class="message is-warning mt-4"><div class="message-body"><strong>Importante:</strong> si no asistes a la cita reasignada, se entiende que no hay devolución.</div></article>
		<?php } ?>
	</div>

	<article class="message is-warning is-light">
		<div class="message-body">
			<h2 class="title is-6 mb-2"><i class="fas fa-map-marker-alt"></i> &nbsp; Ubicación y contacto</h2>
			<?php if($direccion !== ''){ ?>
				<p class="mb-3"><?php echo htmlspecialchars($direccion,ENT_QUOTES,'UTF-8'); ?></p>
			<?php } ?>
			<div class="buttons">
				<?php if($mapsUrl !== ''){ ?>
					<a class="button is-danger" href="<?php echo htmlspecialchars($mapsUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Ver en Google Maps</a>
				<?php } ?>
				<?php if($waUrl !== ''){ ?>
					<a class="button is-success" href="<?php echo htmlspecialchars($waUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> &nbsp; WhatsApp</a>
				<?php } ?>
			</div>
		</div>
	</article>
</div>
