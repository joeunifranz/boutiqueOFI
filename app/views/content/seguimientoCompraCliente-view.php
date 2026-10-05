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

require_once "./app/views/inc/cuenta_cliente.php";
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

?>

<link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/css/clienteCuenta.css">

<section class="boutique-bg boutique-client-page">
	<div class="boutique-bg-slider" aria-hidden="true">
		<div class="boutique-bg-slide s1"></div>
		<div class="boutique-bg-slide s2"></div>
		<div class="boutique-bg-slide s3"></div>
		<div class="boutique-bg-slide s4"></div>
		<div class="boutique-bg-slide s5"></div>
		<div class="boutique-bg-slide s6"></div>
	</div>
	<div class="boutique-bg-overlay" aria-hidden="true"></div>
	<?php require_once "./app/views/inc/navbar_cliente.php"; ?>
	<div class="boutique-client-content">
		<div class="container">
			<div class="boutique-glass p-5 cuenta">

	<?php if(!$clienteLogueado){ ?>
				<div class="cuenta-vacio">
					<i class="fas fa-lock" aria-hidden="true"></i>
					<h1 class="title is-4 mb-0">Seguimiento de compra</h1>
					<p>Inicia sesión para ver el detalle.</p>
					<a class="cuenta-boton es-principal js-cliente-auth-open" href="#" data-auth-intent="login" data-redirect-to="reservasComprasCliente/">Iniciar sesión</a>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php return; ?>
	<?php } ?>

	<?php if(!$data){ ?>
				<div class="cuenta-vacio">
					<i class="fas fa-search" aria-hidden="true"></i>
					<h1 class="title is-4 mb-0">Compra no encontrada</h1>
					<p>Revisa el enlace o búscala en tu lista de compras.</p>
					<a class="cuenta-boton es-principal" href="<?php echo APP_URL; ?>reservasComprasCliente/#compras">Ver mis compras</a>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php return; ?>
	<?php } ?>

	<?php
		$venta = (array)($data['venta'] ?? []);
		$detalle = (array)($data['detalle'] ?? []);
		$codigoVenta = (string)($venta['venta_codigo'] ?? '');
		$ticketUrl = APP_URL.'app/pdf/ticket.php?code='.urlencode($codigoVenta);
		$f = boutique_fecha_partes($venta['venta_fecha'] ?? '', $venta['venta_hora'] ?? '');
		$unidades = 0;
		foreach($detalle as $d){ $unidades += (int)($d['venta_detalle_cantidad'] ?? 0); }
	?>

				<a class="cuenta-boton es-pequeno mb-4" href="<?php echo APP_URL; ?>reservasComprasCliente/#compras"><i class="fas fa-arrow-left" aria-hidden="true"></i> Mis compras</a>

				<div class="cuenta-grilla">
					<div class="cuenta-caja">
						<p class="cuenta-saludo">Seguimiento de compra</p>
						<h1 class="cuenta-titulo mb-3">Compra #<?php echo $e($codigoVenta); ?></h1>

						<dl class="seguimiento-detalles">
							<div class="seguimiento-detalle"><dt>Fecha</dt><dd><?php echo $e(trim($f['semana'].' '.$f['dia'].' '.$f['mes'].' '.$f['anio'])); ?></dd></div>
							<div class="seguimiento-detalle"><dt>Hora</dt><dd><?php echo $e($f['hora'] !== '' ? $f['hora'] : '—'); ?></dd></div>
							<div class="seguimiento-detalle"><dt>Artículos</dt><dd><?php echo $unidades; ?></dd></div>
							<?php $pagada = (float)($venta['venta_pagado'] ?? 0) + 0.005 >= (float)($venta['venta_total'] ?? 0); ?>
							<div class="seguimiento-detalle es-oro"><dt>Estado</dt><dd><?php echo $pagada ? 'Pagada' : 'Con saldo pendiente'; ?></dd></div>
						</dl>

						<h2 class="cuenta-seccion-titulo">Lo que compraste</h2>
						<?php if(!empty($detalle)){ ?>
							<ul class="compra-items">
								<?php foreach($detalle as $d){ $cant = (int)($d['venta_detalle_cantidad'] ?? 0); ?>
									<li class="compra-item">
										<span class="compra-cantidad"><?php echo $cant; ?>×</span>
										<span>
											<strong><?php echo $e($d['venta_detalle_descripcion'] ?? ''); ?></strong>
											<small><?php echo $e(boutique_dinero($d['venta_detalle_precio_venta'] ?? 0)); ?> c/u</small>
										</span>
										<span class="compra-subtotal"><?php echo $e(boutique_dinero($d['venta_detalle_total'] ?? 0)); ?></span>
									</li>
								<?php } ?>
							</ul>
						<?php }else{ ?>
							<div class="cuenta-vacio mb-4"><p>Esta compra no tiene detalle registrado.</p></div>
						<?php } ?>

						<div class="compra-total mb-4">
							<span>Total</span>
							<strong><?php echo $e(boutique_dinero($venta['venta_total'] ?? 0)); ?></strong>
						</div>

						<div class="cuenta-botones">
							<a class="cuenta-boton es-principal" href="<?php echo $e($ticketUrl); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt" aria-hidden="true"></i> Ver ticket</a>
						</div>
					</div>

					<aside class="cuenta-lateral">
						<?php boutique_caja_contacto($direccion, $mapsUrl, $waUrl, 'Para recoger tu pedido o hacer una consulta.'); ?>
					</aside>
				</div>
			</div>
		</div>
	</div>
</section>
