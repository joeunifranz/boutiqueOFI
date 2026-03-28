<?php

use app\controllers\saleController;

$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
$clienteId = $clienteLogueado ? (int)$_SESSION['cliente_id'] : 0;

$code = isset($url[1]) ? (string)$url[1] : '';

$insVenta = new saleController();
$data = ($clienteLogueado && $code !== '') ? $insVenta->obtenerVentaPorCodigoParaClienteControlador($code, $clienteId) : null;

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

$waMsg = 'Hola, tengo una consulta sobre mi compra.';
$waUrl = ($telDigits !== '') ? ('https://wa.me/'.$telDigits.'?text='.urlencode($waMsg)) : '';
$mapsUrl = ($direccion !== '') ? ('https://www.google.com/maps/search/?api=1&query='.urlencode($direccion)) : '';

?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title">Seguimiento de compra</h1>

	<?php if(!$clienteLogueado){ ?>
		<article class="message is-warning"><div class="message-body">Debes iniciar sesión para ver el detalle.</div></article>
		<?php return; ?>
	<?php } ?>

	<?php if(!$data){ ?>
		<article class="message is-danger"><div class="message-body">Compra no encontrada.</div></article>
		<div class="buttons"><a class="button is-light" href="<?php echo APP_URL; ?>reservasComprasCliente/">Volver</a></div>
		<?php return; ?>
	<?php } ?>

	<?php $venta = (array)($data['venta'] ?? []); $detalle = (array)($data['detalle'] ?? []); ?>
	<?php $ticketUrl = APP_URL.'app/pdf/ticket.php?code='.urlencode((string)($venta['venta_codigo'] ?? '')); ?>

	<div class="box">
		<p><strong>Código:</strong> <?php echo htmlspecialchars((string)($venta['venta_codigo'] ?? ''),ENT_QUOTES,'UTF-8'); ?></p>
		<p><strong>Fecha:</strong> <?php echo htmlspecialchars(trim((string)($venta['venta_fecha'] ?? '').' '.(string)($venta['venta_hora'] ?? '')),ENT_QUOTES,'UTF-8'); ?></p>
		<p><strong>Total:</strong> <?php echo MONEDA_SIMBOLO.number_format((float)($venta['venta_total'] ?? 0),2); ?> <?php echo MONEDA_NOMBRE; ?></p>
		<div class="buttons mt-3">
			<a class="button is-link" href="<?php echo htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt"></i> &nbsp; Ver ticket</a>
			<a class="button is-light" href="<?php echo APP_URL; ?>reservasComprasCliente/">Volver</a>
		</div>
	</div>

	<div class="table-container">
		<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
			<thead>
				<tr>
					<th class="has-text-centered">#</th>
					<th>Producto</th>
					<th class="has-text-centered">Cant.</th>
					<th class="has-text-centered">Precio</th>
					<th class="has-text-centered">Subtotal</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!empty($detalle)){ $i=1; foreach($detalle as $d){ ?>
					<tr class="has-text-centered">
						<td><?php echo $i; ?></td>
						<td class="has-text-left"><?php echo htmlspecialchars((string)($d['venta_detalle_descripcion'] ?? ''),ENT_QUOTES,'UTF-8'); ?></td>
						<td><?php echo (int)($d['venta_detalle_cantidad'] ?? 0); ?></td>
						<td><?php echo MONEDA_SIMBOLO.number_format((float)($d['venta_detalle_precio_venta'] ?? 0),2); ?></td>
						<td><?php echo MONEDA_SIMBOLO.number_format((float)($d['venta_detalle_total'] ?? 0),2); ?></td>
					</tr>
				<?php $i++; } }else{ ?>
					<tr><td colspan="5" class="has-text-centered">Sin detalle.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

	<div class="box mt-5">
		<h2 class="title is-6 mb-2"><i class="fas fa-map-marker-alt"></i> &nbsp; Ubicación y contacto</h2>
		<?php if($direccion !== ''){ ?>
			<p class="mb-3"><?php echo htmlspecialchars($direccion,ENT_QUOTES,'UTF-8'); ?></p>
		<?php } ?>
		<div class="buttons">
			<?php if($mapsUrl !== ''){ ?>
				<a class="button is-light" href="<?php echo htmlspecialchars($mapsUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Ver en Google Maps</a>
			<?php } ?>
			<?php if($waUrl !== ''){ ?>
				<a class="button is-success" href="<?php echo htmlspecialchars($waUrl,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> &nbsp; WhatsApp</a>
			<?php } ?>
		</div>
	</div>
</div>
