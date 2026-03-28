<?php

use app\controllers\reservationController;
use app\controllers\saleController;

$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
$clienteId = $clienteLogueado ? (int)$_SESSION['cliente_id'] : 0;

$insReserva = new reservationController();
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

$waMsg = 'Hola, tengo una consulta sobre mis reservas/compras.';
$waUrl = ($telDigits !== '') ? ('https://wa.me/'.$telDigits.'?text='.urlencode($waMsg)) : '';
$mapsUrl = ($direccion !== '') ? ('https://www.google.com/maps/search/?api=1&query='.urlencode($direccion)) : '';

$reservas = $clienteLogueado ? $insReserva->obtenerReservasPorClienteControlador($clienteId) : [];
$ventas = $clienteLogueado ? $insVenta->obtenerVentasPorClienteControlador($clienteId) : [];

?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title">Reservas y compras</h1>
	<p class="subtitle">Consulta tu seguimiento y detalles.</p>

	<?php if(!$clienteLogueado){ ?>
		<article class="message is-warning">
			<div class="message-body">
				Debes iniciar sesión para ver tus reservas y compras.
				<div class="buttons mt-3">
					<a class="button is-link" href="<?php echo APP_URL; ?>clienteLogin/?redirect_to=<?php echo urlencode('reservasComprasCliente/'); ?>">Iniciar sesión</a>
				</div>
			</div>
		</article>
		<?php return; ?>
	<?php } ?>

	<article class="message is-warning is-light">
		<div class="message-body">
			<h2 class="title is-6 mb-2"><i class="fas fa-map-marker-alt"></i> &nbsp; Ubicación de la boutique</h2>
			<?php if($direccion !== ''){ ?>
				<p class="mb-3"><?php echo htmlspecialchars($direccion,ENT_QUOTES,'UTF-8'); ?></p>
				<div class="buttons">
					<?php if($mapsUrl !== ''){ ?>
						<a class="button is-danger" href="<?php echo htmlspecialchars($mapsUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Ver en Google Maps</a>
					<?php } ?>
					<?php if($waUrl !== ''){ ?>
						<a class="button is-success" href="<?php echo htmlspecialchars($waUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> &nbsp; WhatsApp</a>
					<?php } ?>
				</div>
			<?php }else{ ?>
				<p class="has-text-grey">Ubicación no configurada en el sistema.</p>
				<?php if($waUrl !== ''){ ?>
					<div class="buttons mt-3">
						<a class="button is-success" href="<?php echo htmlspecialchars($waUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> &nbsp; WhatsApp</a>
					</div>
				<?php } ?>
			<?php } ?>
		</div>
	</article>

	<h2 class="title is-4 has-text-centered">Reservas</h2>
	<div class="table-container" style="max-width: 980px; margin: 0 auto;">
		<table class="table is-bordered is-striped is-hoverable is-fullwidth is-size-5">
			<thead>
				<tr>
					<th class="has-text-centered">Vestido</th>
					<th class="has-text-centered">Día de su cita</th>
					<th class="has-text-centered">Ver más detalles</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!empty($reservas)){ ?>
					<?php foreach($reservas as $r){
						$codigo = (string)($r['reserva_codigo'] ?? '');
						$seguimientoUrl = APP_URL.'seguimientoReservaCliente/'.urlencode($codigo).'/';
					?>
						<tr class="has-text-centered">
							<td class="has-text-left">
								<a href="<?php echo htmlspecialchars($seguimientoUrl,ENT_QUOTES,'UTF-8'); ?>">
									<?php echo htmlspecialchars((string)($r['producto_nombre'] ?? ''),ENT_QUOTES,'UTF-8'); ?>
								</a>
							</td>
							<td><?php echo htmlspecialchars(trim((string)($r['reserva_fecha'] ?? '').' '.(string)($r['reserva_hora'] ?? '')),ENT_QUOTES,'UTF-8'); ?></td>
							<td>
								<div class="buttons is-centered" style="flex-wrap:wrap;">
									<a class="button is-link is-small" href="<?php echo htmlspecialchars($seguimientoUrl,ENT_QUOTES,'UTF-8'); ?>">Detalle</a>
								</div>
							</td>
						</tr>
					<?php } ?>
				<?php }else{ ?>
					<tr>
						<td colspan="3" class="has-text-centered">Aún no tienes reservas.</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

	<h2 class="title is-4 mt-6">Compras</h2>
	<div class="table-container">
		<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
			<thead>
				<tr>
					<th class="has-text-centered">Código</th>
					<th class="has-text-centered">Fecha</th>
					<th class="has-text-centered">Total</th>
					<th class="has-text-centered">Ítems</th>
					<th class="has-text-centered">Ver más detalles</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!empty($ventas)){ ?>
					<?php foreach($ventas as $v){
						$cod = (string)($v['venta_codigo'] ?? '');
						$seguimientoUrl = APP_URL.'seguimientoCompraCliente/'.urlencode($cod).'/';
						$ticketUrl = APP_URL.'app/pdf/ticket.php?code='.urlencode($cod);
						$items = (int)($v['items'] ?? 0);
					?>
						<tr class="has-text-centered">
							<td><?php echo htmlspecialchars($cod,ENT_QUOTES,'UTF-8'); ?></td>
							<td><?php echo htmlspecialchars(trim((string)($v['venta_fecha'] ?? '').' '.(string)($v['venta_hora'] ?? '')),ENT_QUOTES,'UTF-8'); ?></td>
							<td><?php echo MONEDA_SIMBOLO.number_format((float)($v['venta_total'] ?? 0),2); ?></td>
							<td><?php echo $items; ?></td>
							<td>
								<div class="buttons is-centered" style="flex-wrap:wrap;">
									<a class="button is-link is-small" href="<?php echo htmlspecialchars($seguimientoUrl,ENT_QUOTES,'UTF-8'); ?>">Detalle</a>
									<a class="button is-light is-small" href="<?php echo htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Ticket</a>
								</div>
							</td>
						</tr>
					<?php } ?>
				<?php }else{ ?>
					<tr>
						<td colspan="5" class="has-text-centered">Aún no tienes compras registradas.</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

	<div class="buttons is-centered mt-6">
		<a class="button is-light" href="<?php echo APP_URL; ?>productosCliente/">Volver a la tienda</a>
	</div>
</div>
