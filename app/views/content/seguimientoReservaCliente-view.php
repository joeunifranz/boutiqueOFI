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
					<h1 class="title is-4 mb-0">Seguimiento de reserva</h1>
					<p>Inicia sesión para ver el seguimiento.</p>
					<a class="cuenta-boton es-principal js-cliente-auth-open" href="#" data-auth-intent="login" data-redirect-to="reservasComprasCliente/">Iniciar sesión</a>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php return; ?>
	<?php } ?>

	<?php if(!$reserva){ ?>
				<div class="cuenta-vacio">
					<i class="fas fa-search" aria-hidden="true"></i>
					<h1 class="title is-4 mb-0">Reserva no encontrada</h1>
					<p>Revisa el enlace o búscala en tu lista de reservas.</p>
					<a class="cuenta-boton es-principal" href="<?php echo APP_URL; ?>reservasComprasCliente/">Ver mis reservas</a>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php return; ?>
	<?php } ?>

	<?php
		$notifVeces = (int)($reserva['reserva_cliente_notificacion'] ?? 0);
		$esNuevo = $notifVeces > 0;
		if($esNuevo){
			$codigoTmp = (string)($reserva['reserva_codigo'] ?? '');
			if($codigoTmp !== ''){
				$insReserva->marcarNotificacionReservaClienteVistaPorCodigoControlador($codigoTmp, $clienteId);
			}
		}

		$codigo = (string)($reserva['reserva_codigo'] ?? '');
		$estado = (string)($reserva['reserva_estado'] ?? '');
		$estadoInfo = boutique_estado_reserva($estado);
		$total = (float)($reserva['reserva_total'] ?? 0);
		$abono = (float)($reserva['reserva_abono'] ?? 0);
		$saldo = (float)number_format(($total - $abono), (int)MONEDA_DECIMALES, '.', '');
		if($saldo < 0){ $saldo = 0; }
		$pagoCompleto = (strtolower(trim($estado)) === 'completada') || ($saldo <= 0);
		$pct = $total > 0 ? (int)round(min($abono, $total) / $total * 100) : 0;
		$foto = boutique_foto_producto($reserva['producto_foto'] ?? '');
		$pagarUrl = APP_URL.'reservaPagar/'.urlencode($codigo).'/';
		$qrReservaUrl = APP_URL.'reservaConfirmar/'.urlencode($codigo).'/';
		$qrReservaImg = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode($qrReservaUrl);
		$qrReservaPage = APP_URL.'reservaQR/'.urlencode($codigo).'/';
		$ticketUrl = APP_URL.'app/pdf/reserva_ticket.php?code='.urlencode($codigo);
		$f = boutique_fecha_partes($reserva['reserva_fecha'] ?? '', $reserva['reserva_hora'] ?? '');

		// Línea de tiempo según el estado
		$clase = $estadoInfo['clase'];
		if($clase === 'rechazada'){
			$pasos = [['Registrada', 'hecho', 'fa-check'], ['Rechazada', 'error', 'fa-times']];
		}else{
			$orden = ['pendiente' => 1, 'confirmada' => 2, 'reprogramada' => 2, 'completada' => 4];
			$nivel = $orden[$clase] ?? 1;
			$nombres = ['Registrada', 'Confirmada', $clase === 'reprogramada' ? 'Cita reprogramada' : 'Cita en tienda', 'Completada'];
			$iconos = ['fa-check', 'fa-check', 'fa-calendar-alt', 'fa-star'];
			$pasos = [];
			foreach($nombres as $i => $nombre){
				$estadoPaso = $i < $nivel ? 'hecho' : ($i === $nivel ? 'actual' : '');
				$pasos[] = [$nombre, $estadoPaso, $estadoPaso === 'hecho' ? 'fa-check' : $iconos[$i]];
			}
		}
	?>

				<a class="cuenta-boton es-pequeno mb-4" href="<?php echo APP_URL; ?>reservasComprasCliente/"><i class="fas fa-arrow-left" aria-hidden="true"></i> Mis reservas</a>

	<?php if($esNuevo){ ?>
				<div class="cuenta-aviso" role="status">
					<i class="fas fa-bell" aria-hidden="true"></i>
					<span><strong>Novedad<?php echo $notifVeces > 1 ? ' (×'.$notifVeces.')' : ''; ?>:</strong> hay una actualización en esta reserva. Revisa el estado y los detalles.</span>
				</div>
	<?php } ?>

				<div class="cuenta-grilla">
					<div class="cuenta-caja">
						<div class="seguimiento-cabecera">
							<span class="cuenta-foto"><?php if($foto !== ''){ ?><img src="<?php echo $e($foto); ?>" alt="<?php echo $e($reserva['producto_nombre'] ?? ''); ?>"><?php }else{ ?><i class="fas fa-gem" aria-hidden="true"></i><?php } ?></span>
							<div>
								<p class="cuenta-saludo">Seguimiento de reserva</p>
								<h1 class="seguimiento-titulo"><?php echo $e($reserva['producto_nombre'] ?? 'Vestido'); ?></h1>
								<span class="cuenta-estado <?php echo $e($clase); ?>"><i class="fas <?php echo $e($estadoInfo['icono']); ?>" aria-hidden="true"></i><?php echo $e($estadoInfo['texto']); ?></span>
								<?php if($estadoInfo['ayuda'] !== ''){ ?><p class="mt-2 mb-0" style="color: var(--texto-suave); font-size: .88rem;"><?php echo $e($estadoInfo['ayuda']); ?></p><?php } ?>
							</div>
						</div>

						<ol class="seguimiento-pasos" style="--pasos: <?php echo count($pasos); ?>" aria-label="Estado de la reserva">
							<?php foreach($pasos as $p){ ?>
								<li class="seguimiento-paso <?php echo $e($p[1]); ?>"<?php echo $p[1] === 'actual' ? ' aria-current="step"' : ''; ?>>
									<span class="seguimiento-punto"><i class="fas <?php echo $e($p[2]); ?>" aria-hidden="true"></i></span>
									<?php echo $e($p[0]); ?>
								</li>
							<?php } ?>
						</ol>

						<dl class="seguimiento-detalles">
							<div class="seguimiento-detalle es-oro"><dt>Tu cita</dt><dd><?php echo $e(trim($f['semana'].' '.$f['dia'].' '.$f['mes'].' '.$f['anio'])); ?><br><?php echo $e($f['hora']); ?></dd></div>
							<div class="seguimiento-detalle"><dt>Código</dt><dd><?php echo $e($codigo); ?></dd></div>
							<div class="seguimiento-detalle"><dt>Total</dt><dd><?php echo $e(boutique_dinero($total)); ?></dd></div>
							<?php if($pagoCompleto){ ?>
								<div class="seguimiento-detalle es-oro"><dt>Pago</dt><dd>Completado</dd></div>
							<?php }else{ ?>
								<div class="seguimiento-detalle"><dt>Abonado</dt><dd><?php echo $e(boutique_dinero($abono)); ?></dd></div>
								<div class="seguimiento-detalle es-oro"><dt>Falta pagar</dt><dd><?php echo $e(boutique_dinero($saldo)); ?></dd></div>
							<?php } ?>
						</dl>

						<?php if($total > 0 && $clase !== 'rechazada'){ ?>
							<div class="cuenta-pago mb-4" style="max-width:none;" aria-label="Pagado <?php echo $pct; ?> por ciento">
								<div class="cuenta-pago-barra"><span style="width: <?php echo $pct; ?>%"></span></div>
								<div class="cuenta-pago-texto"><span>Pagado <b><?php echo $pct; ?>%</b></span><span><?php echo $pagoCompleto ? '<b>Pago completo</b>' : 'Falta <b>'.$e(boutique_dinero($saldo)).'</b>'; ?></span></div>
							</div>
						<?php } ?>

						<?php if(!empty($reserva['reserva_observacion'])){ ?>
							<div class="cuenta-aviso"><i class="fas fa-sticky-note" aria-hidden="true"></i><span><strong>Nota de la tienda:</strong> <?php echo $e($reserva['reserva_observacion']); ?></span></div>
						<?php } ?>

						<?php if($estado === 'reprogramada'){ ?>
							<div class="cuenta-aviso es-alerta"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><span><strong>Importante:</strong> si no asistes a la cita reasignada, se entiende que no hay devolución.</span></div>
						<?php } ?>

						<div class="cuenta-botones">
							<?php if($saldo > 0 && $estado !== 'rechazada' && $estado !== 'completada'){ ?>
								<a class="cuenta-boton es-principal" href="<?php echo $e($pagarUrl); ?>"><i class="fas fa-credit-card" aria-hidden="true"></i> Pagar saldo</a>
							<?php } ?>
							<a class="cuenta-boton" href="<?php echo $e($ticketUrl); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt" aria-hidden="true"></i> Ticket de reserva</a>
						</div>
					</div>

					<aside class="cuenta-lateral">
						<?php if($clase !== 'rechazada'){ ?>
							<div class="cuenta-caja seguimiento-qr">
								<h2><i class="fas fa-qrcode" aria-hidden="true"></i> Tu QR de reserva</h2>
								<figure>
									<img src="<?php echo $e($qrReservaImg); ?>" alt="Código QR de la reserva <?php echo $e($codigo); ?>" onerror="this.closest('figure').style.display='none'; document.getElementById('qrFallbackCliente').style.display='block';">
								</figure>
								<p id="qrFallbackCliente" style="display:none;">No se pudo cargar el QR. Enlace: <a href="<?php echo $e($qrReservaUrl); ?>" target="_blank" rel="noopener"><?php echo $e($qrReservaUrl); ?></a></p>
								<small>Muéstralo en la tienda el día de tu cita.</small>
								<a class="cuenta-boton es-pequeno" href="<?php echo $e($qrReservaPage); ?>"><i class="fas fa-download" aria-hidden="true"></i> Ver / descargar</a>
							</div>
						<?php } ?>
						<?php boutique_caja_contacto($direccion, $mapsUrl, $waUrl, 'Para asistir a tu cita o hacer una consulta.'); ?>
					</aside>
				</div>
			</div>
		</div>
	</div>
</section>
