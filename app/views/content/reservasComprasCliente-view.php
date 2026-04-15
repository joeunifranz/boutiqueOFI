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
	<div class="has-text-centered mb-5">
		<h1 class="title">Reservas y compras</h1>
		<p class="subtitle">Consulta tu seguimiento y detalles.</p>
	</div>

	<?php if(!$clienteLogueado){ ?>
		<article class="message is-warning">
			<div class="message-body">
				Debes iniciar sesión para ver tus reservas y compras.
				<div class="buttons mt-3">
					<a class="button is-link js-cliente-auth-open" href="#" data-auth-intent="login" data-redirect-to="reservasComprasCliente/">Iniciar sesión</a>
				</div>
			</div>
		</article>
		<?php return; ?>
	<?php } ?>

	<div class="columns is-variable is-6">
		<div class="column is-8">
			<h2 class="title is-4 has-text-centered mt-6 mb-4"><i class="fas fa-calendar-check"></i> &nbsp; Reservas</h2>
			<div class="box">
				<div class="table-container">
					<table class="table is-hoverable is-fullwidth is-size-6">
						<thead class="has-background-light">
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
							<td>
								<span class="tag is-light is-rounded">
									<?php echo htmlspecialchars(trim((string)($r['reserva_fecha'] ?? '').' '.(string)($r['reserva_hora'] ?? '')),ENT_QUOTES,'UTF-8'); ?>
								</span>
							</td>
							<td>
								<div class="buttons is-centered">
									<a class="button is-link is-small is-rounded" href="<?php echo htmlspecialchars($seguimientoUrl,ENT_QUOTES,'UTF-8'); ?>">Ver</a>
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
			</div>

			<h2 class="title is-4 has-text-centered mt-6 mb-4"><i class="fas fa-shopping-bag"></i> &nbsp; Compras</h2>
			<div class="box">
				<div class="table-container">
					<table class="table is-hoverable is-fullwidth is-size-6">
						<thead class="has-background-light">
				<tr>
					<th class="has-text-centered">Código</th>
					<th class="has-text-centered">Fecha</th>
					<th class="has-text-centered">Total</th>
					<th class="has-text-centered">Ítems</th>
					<th class="has-text-centered">Acción</th>
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
							<td>
								<span class="tag is-light is-rounded">
									<?php echo htmlspecialchars(trim((string)($v['venta_fecha'] ?? '').' '.(string)($v['venta_hora'] ?? '')),ENT_QUOTES,'UTF-8'); ?>
								</span>
							</td>
							<td><span class="tag is-light"><?php echo MONEDA_SIMBOLO.number_format((float)($v['venta_total'] ?? 0),2); ?></span></td>
							<td><span class="tag is-light"><?php echo $items; ?></span></td>
							<td>
								<div class="buttons is-centered">
									<a class="button is-link is-small is-rounded" href="<?php echo htmlspecialchars($seguimientoUrl,ENT_QUOTES,'UTF-8'); ?>">Ver</a>
									<a class="button is-light is-small is-rounded" href="<?php echo htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Ticket</a>
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
			</div>
		</div>

		<div class="column is-4">
			<div class="box has-background-light">
				<h2 class="title is-5 mb-2"><i class="fas fa-map-marker-alt"></i> &nbsp; Ubicación y contacto</h2>
				<p class="has-text-grey mb-4">Para recoger tu vestido o consultar.</p>
				<?php if($direccion !== ''){ ?>
					<p class="mb-4">
						<span class="tag is-light is-rounded">
							<?php echo htmlspecialchars($direccion,ENT_QUOTES,'UTF-8'); ?>
						</span>
					</p>
				<?php }else{ ?>
					<p class="has-text-grey mb-4">Ubicación no configurada en el sistema.</p>
				<?php } ?>

				<div class="buttons is-right" style="flex-wrap:wrap;">
					<?php if($mapsUrl !== ''){ ?>
						<a class="button is-danger is-rounded" href="<?php echo htmlspecialchars($mapsUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Google Maps</a>
					<?php } ?>
					<?php if($waUrl !== ''){ ?>
						<a class="button is-success is-rounded" href="<?php echo htmlspecialchars($waUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> &nbsp; WhatsApp</a>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>

	<div class="buttons is-centered mt-6">
		<a class="button is-light" href="<?php echo APP_URL; ?>productosCliente/">Volver a la tienda</a>
	</div>
</div>
