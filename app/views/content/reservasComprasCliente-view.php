<?php

use app\controllers\reservationController;
use app\controllers\saleController;

function boutique_format_cita_corta($fecha, $hora): string{
	$fecha = trim((string)$fecha);
	$hora = trim((string)$hora);
	$raw = trim($fecha." ".$hora);
	if($raw===''){
		return '';
	}

	$meses = [
		1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
		7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
	];

	// Caso típico: fecha Y-m-d
	if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)){
		$mesNum = (int)$m[2];
		$dia = (int)$m[3];
		$mesTxt = $meses[$mesNum] ?? $m[2];
		$h = $hora !== '' ? substr($hora, 0, 5) : '';
		return trim($dia.' '.$mesTxt.' '.$h);
	}

	try{
		$dt = new DateTime($raw);
		$dia = (int)$dt->format('j');
		$mesTxt = $meses[(int)$dt->format('n')] ?? $dt->format('m');
		$h = $dt->format('H:i');
		return trim($dia.' '.$mesTxt.' '.$h);
	}catch(Throwable $e){
		return $raw;
	}
}

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

$notifCountReservas = $clienteLogueado ? $insReserva->contarNotificacionesReservaClienteControlador($clienteId) : 0;

require_once "./app/views/inc/cuenta_cliente.php";
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Próximas citas (de la más cercana a la más lejana) y el resto como historial
$hoy = date('Y-m-d');
$proximas = [];
$anteriores = [];
$saldoPendiente = 0.0;
foreach($reservas as $r){
	$estado = strtolower(trim((string)($r['reserva_estado'] ?? '')));
	$cerrada = in_array($estado, ['rechazada', 'completada'], true);
	if(!$cerrada){
		$saldoPendiente += max(0, (float)($r['reserva_total'] ?? 0) - (float)($r['reserva_abono'] ?? 0));
	}
	if(!$cerrada && (string)($r['reserva_fecha'] ?? '') >= $hoy){
		$proximas[] = $r;
	}else{
		$anteriores[] = $r;
	}
}
usort($proximas, fn($a, $b) => strcmp((string)$a['reserva_fecha'].(string)$a['reserva_hora'], (string)$b['reserva_fecha'].(string)$b['reserva_hora']));
$nombreCliente = trim((string)($_SESSION['cliente_nombre'] ?? ''));

// Tarjeta de una reserva (se usa en "próximas" y en "anteriores")
$tarjetaReserva = function(array $r) use ($e){
	$codigo = (string)($r['reserva_codigo'] ?? '');
	$url = APP_URL.'seguimientoReservaCliente/'.urlencode($codigo).'/';
	$notif = (int)($r['reserva_cliente_notificacion'] ?? 0);
	$estado = boutique_estado_reserva((string)($r['reserva_estado'] ?? ''));
	$f = boutique_fecha_partes($r['reserva_fecha'] ?? '', $r['reserva_hora'] ?? '');
	$foto = boutique_foto_producto($r['producto_foto'] ?? '');
	$total = (float)($r['reserva_total'] ?? 0);
	$abono = min($total, max(0, (float)($r['reserva_abono'] ?? 0)));
	$saldo = max(0, $total - $abono);
	$pct = $total > 0 ? round($abono / $total * 100) : 0;
	?>
	<a class="cuenta-tarjeta<?php echo $notif > 0 ? ' es-nuevo' : ''; ?>" href="<?php echo $e($url); ?>">
		<?php if($notif > 0){ ?><span class="cuenta-nuevo">NUEVO<?php echo $notif > 1 ? ' ×'.$notif : ''; ?></span><?php } ?>
		<span class="cuenta-foto"><?php if($foto !== ''){ ?><img src="<?php echo $e($foto); ?>" alt="" loading="lazy"><?php }else{ ?><i class="fas fa-gem" aria-hidden="true"></i><?php } ?></span>
		<span class="cuenta-fecha" aria-hidden="true"><small><?php echo $e($f['semana']); ?></small><strong><?php echo $e($f['dia']); ?></strong><small><?php echo $e($f['mes']); ?></small><em><?php echo $e($f['hora']); ?></em></span>
		<span class="cuenta-info">
			<strong class="cuenta-nombre"><?php echo $e($r['producto_nombre'] ?? 'Vestido'); ?></strong>
			<span class="cuenta-meta">
				<span class="cuenta-estado <?php echo $e($estado['clase']); ?>"><i class="fas <?php echo $e($estado['icono']); ?>" aria-hidden="true"></i><?php echo $e($estado['texto']); ?></span>
				<span class="cuenta-fecha-movil"><i class="far fa-calendar" aria-hidden="true"></i><?php echo $e(trim($f['semana'].' '.$f['dia'].' '.$f['mes'].' '.$f['anio'].' · '.$f['hora'], ' ·')); ?></span>
			</span>
			<?php if($total > 0 && $estado['clase'] !== 'rechazada'){ ?>
				<span class="cuenta-pago" aria-label="Pagado <?php echo $pct; ?> por ciento">
					<span class="cuenta-pago-barra"><span style="width: <?php echo $pct; ?>%"></span></span>
					<span class="cuenta-pago-texto">
						<span>Pagado <b><?php echo $e(boutique_dinero($abono)); ?></b></span>
						<span><?php echo $saldo > 0 ? 'Falta <b>'.$e(boutique_dinero($saldo)).'</b>' : '<b>Pago completo</b>'; ?></span>
					</span>
				</span>
			<?php } ?>
		</span>
		<span class="cuenta-lado">
			<span class="cuenta-total"><?php echo $e(boutique_dinero($total)); ?></span>
			<span class="cuenta-ver">Ver seguimiento <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
		</span>
	</a>
	<?php
};

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
					<h1 class="title is-4 mb-0">Reservas y compras</h1>
					<p>Inicia sesión para ver tus reservas, tus pagos y tus compras.</p>
					<a class="cuenta-boton es-principal js-cliente-auth-open" href="#" data-auth-intent="login" data-redirect-to="reservasComprasCliente/">Iniciar sesión</a>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php return; ?>
	<?php } ?>

				<header class="cuenta-encabezado">
					<div>
						<p class="cuenta-saludo"><?php echo $nombreCliente !== '' ? 'Hola, '.$e($nombreCliente) : 'Tu cuenta'; ?></p>
						<h1 class="cuenta-titulo">Reservas y compras</h1>
					</div>
					<div class="cuenta-resumen">
						<div class="cuenta-dato"><span>Próximas citas</span><strong><?php echo count($proximas); ?></strong></div>
						<div class="cuenta-dato es-oro"><span>Saldo pendiente</span><strong><?php echo $e(boutique_dinero($saldoPendiente)); ?></strong></div>
						<div class="cuenta-dato"><span>Compras</span><strong><?php echo count($ventas); ?></strong></div>
					</div>
				</header>

	<?php if($notifCountReservas > 0){ ?>
				<div class="cuenta-aviso" role="status">
					<i class="fas fa-bell" aria-hidden="true"></i>
					<span>Tienes <strong><?php echo (int)$notifCountReservas; ?></strong> <?php echo $notifCountReservas === 1 ? 'novedad' : 'novedades'; ?> en tus reservas. Búscalas con la etiqueta <strong>NUEVO</strong>.</span>
				</div>
	<?php } ?>

				<div class="cuenta-grilla">
					<div>
						<div class="cuenta-pestanas" role="tablist" aria-label="Reservas o compras">
							<button type="button" class="cuenta-pestana" role="tab" id="tabReservas" aria-controls="panelReservas" aria-selected="true"><i class="fas fa-calendar-check" aria-hidden="true"></i> Reservas <span class="cuenta-contador"><?php echo count($reservas); ?></span></button>
							<button type="button" class="cuenta-pestana" role="tab" id="tabCompras" aria-controls="panelCompras" aria-selected="false" tabindex="-1"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Compras <span class="cuenta-contador"><?php echo count($ventas); ?></span></button>
						</div>

						<!-- Reservas -->
						<section class="cuenta-panel" id="panelReservas" role="tabpanel" aria-labelledby="tabReservas">
	<?php if(empty($reservas)){ ?>
							<div class="cuenta-vacio">
								<i class="far fa-calendar" aria-hidden="true"></i>
								<p>Aún no tienes reservas.</p>
								<a class="cuenta-boton es-principal" href="<?php echo APP_URL; ?>productosCliente/">Ver vestidos</a>
							</div>
	<?php }else{ ?>
							<h2 class="cuenta-seccion-titulo">Próximas citas</h2>
		<?php if(empty($proximas)){ ?>
							<div class="cuenta-vacio">
								<i class="far fa-calendar-check" aria-hidden="true"></i>
								<p>No tienes citas próximas.</p>
								<a class="cuenta-boton es-principal" href="<?php echo APP_URL; ?>productosCliente/">Reservar otro vestido</a>
							</div>
		<?php }else{ ?>
							<div class="cuenta-lista">
								<?php foreach($proximas as $r){ $tarjetaReserva($r); } ?>
							</div>
		<?php } ?>

		<?php if(!empty($anteriores)){ ?>
							<details class="cuenta-mas"<?php echo empty($proximas) ? ' open' : ''; ?>>
								<summary>Historial de reservas (<?php echo count($anteriores); ?>) <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
								<div class="cuenta-lista">
									<?php foreach($anteriores as $r){ $tarjetaReserva($r); } ?>
								</div>
							</details>
		<?php } ?>
	<?php } ?>
						</section>

						<?php
							// Al entrar a esta pantalla, marcamos como vistas las notificaciones de reservas (best-effort)
							if($clienteLogueado && $notifCountReservas > 0){
								$insReserva->marcarNotificacionesReservaClienteVistasControlador($clienteId);
							}
						?>

						<!-- Compras -->
						<section class="cuenta-panel" id="panelCompras" role="tabpanel" aria-labelledby="tabCompras" hidden>
	<?php if(empty($ventas)){ ?>
							<div class="cuenta-vacio">
								<i class="fas fa-shopping-bag" aria-hidden="true"></i>
								<p>Aún no tienes compras registradas.</p>
								<a class="cuenta-boton es-principal" href="<?php echo APP_URL; ?>productosCliente/">Ir a la tienda</a>
							</div>
	<?php }else{ ?>
							<div class="cuenta-lista">
		<?php foreach($ventas as $v){
			$cod = (string)($v['venta_codigo'] ?? '');
			$seguimientoUrl = APP_URL.'seguimientoCompraCliente/'.urlencode($cod).'/';
			$ticketUrl = APP_URL.'app/pdf/ticket.php?code='.urlencode($cod);
			$items = (int)($v['items'] ?? 0);
			$lineas = (int)($v['lineas'] ?? 0);
			$foto = boutique_foto_producto($v['producto_foto'] ?? '');
			$f = boutique_fecha_partes($v['venta_fecha'] ?? '', $v['venta_hora'] ?? '');
			$nombre = trim((string)($v['producto_nombre'] ?? '')) !== '' ? (string)$v['producto_nombre'] : 'Compra';
		?>
								<article class="cuenta-tarjeta es-compra">
									<span class="cuenta-foto"><?php if($foto !== ''){ ?><img src="<?php echo $e($foto); ?>" alt="" loading="lazy"><?php }else{ ?><i class="fas fa-shopping-bag" aria-hidden="true"></i><?php } ?></span>
									<span class="cuenta-info">
										<strong class="cuenta-nombre"><?php echo $e($nombre); ?><?php echo $lineas > 1 ? ' <small class="has-text-weight-normal">y '.($lineas - 1).' más</small>' : ''; ?></strong>
										<span class="cuenta-meta">
											<span class="cuenta-codigo">#<?php echo $e($cod); ?></span>
											<span><i class="far fa-calendar" aria-hidden="true"></i><?php echo $e(trim($f['dia'].' '.$f['mes'].' '.$f['anio'].' · '.$f['hora'], ' ·')); ?></span>
											<span><i class="fas fa-box" aria-hidden="true"></i><?php echo $items; ?> <?php echo $items === 1 ? 'artículo' : 'artículos'; ?></span>
										</span>
									</span>
									<span class="cuenta-lado">
										<span class="cuenta-total"><?php echo $e(boutique_dinero($v['venta_total'] ?? 0)); ?></span>
										<span class="cuenta-botones">
											<a class="cuenta-boton es-pequeno" href="<?php echo $e($ticketUrl); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt" aria-hidden="true"></i> Ticket</a>
											<a class="cuenta-boton es-pequeno es-principal" href="<?php echo $e($seguimientoUrl); ?>">Ver detalle</a>
										</span>
									</span>
								</article>
		<?php } ?>
							</div>
	<?php } ?>
						</section>
					</div>

					<aside class="cuenta-lateral">
						<?php boutique_caja_contacto($direccion, $mapsUrl, $waUrl, 'Para recoger tu vestido o hacer una consulta.'); ?>
						<div class="cuenta-caja">
							<h2><i class="fas fa-magic" aria-hidden="true"></i> ¿Algo a tu medida?</h2>
							<p>Diseña tu vestido eligiendo tela, encaje y talla, y agenda tu cita.</p>
							<a class="cuenta-boton es-principal" href="<?php echo APP_URL; ?>telasCliente/">Personaliza tu vestido</a>
						</div>
						<a class="cuenta-boton" href="<?php echo APP_URL; ?>productosCliente/"><i class="fas fa-arrow-left" aria-hidden="true"></i> Volver a la tienda</a>
					</aside>
				</div>
			</div>
		</div>
	</div>
</section>

<script>
	// Pestañas Reservas | Compras (recuerda la elegida en la URL: #compras)
	(function(){
		var tabs = [document.getElementById('tabReservas'), document.getElementById('tabCompras')];
		if(!tabs[0] || !tabs[1]) return;
		function activar(i, enfocar){
			tabs.forEach(function(t, j){
				var activo = i === j;
				t.setAttribute('aria-selected', activo ? 'true' : 'false');
				t.tabIndex = activo ? 0 : -1;
				document.getElementById(t.getAttribute('aria-controls')).hidden = !activo;
			});
			if(enfocar) tabs[i].focus();
			try{ history.replaceState(null, '', i === 1 ? '#compras' : location.pathname + location.search); }catch(e){}
		}
		tabs.forEach(function(t, i){
			t.addEventListener('click', function(){ activar(i); });
			t.addEventListener('keydown', function(ev){
				if(ev.key === 'ArrowRight' || ev.key === 'ArrowLeft'){ ev.preventDefault(); activar(1 - i, true); }
			});
		});
		if(location.hash === '#compras') activar(1);
	})();
</script>
