<?php
/*
 * Ayudas visuales para "Reservas y compras" y sus pantallas de seguimiento.
 * Solo formatean datos ya cargados; no consultan la base de datos.
 */

if(!function_exists('boutique_estado_reserva')){
	// Texto, color (clase CSS), ícono y explicación de cada estado de reserva
	function boutique_estado_reserva(string $estado): array{
		$e = strtolower(trim($estado));
		$mapa = [
			'pendiente'    => ['Pendiente', 'pendiente', 'fa-hourglass-half', 'Estamos revisando tu reserva y tu comprobante.'],
			'confirmada'   => ['Confirmada', 'confirmada', 'fa-check', 'Tu reserva está confirmada. ¡Te esperamos en tu cita!'],
			'reprogramada' => ['Reprogramada', 'reprogramada', 'fa-calendar-alt', 'Tu cita cambió de fecha. Revisa el nuevo horario.'],
			'completada'   => ['Completada', 'completada', 'fa-star', 'Reserva completada. ¡Gracias por elegirnos!'],
			'rechazada'    => ['Rechazada', 'rechazada', 'fa-times', 'Esta reserva no pudo confirmarse. Escríbenos si tienes dudas.'],
		];
		$d = $mapa[$e] ?? [ucfirst($e !== '' ? $e : 'Sin estado'), 'pendiente', 'fa-circle', ''];
		return ['texto' => $d[0], 'clase' => $d[1], 'icono' => $d[2], 'ayuda' => $d[3]];
	}
}

if(!function_exists('boutique_foto_producto')){
	function boutique_foto_producto(?string $foto): string{
		$foto = trim((string)$foto);
		if($foto === '' || !is_file('./app/views/productos/'.$foto)) return '';
		return APP_URL.'app/views/productos/'.rawurlencode($foto);
	}
}

if(!function_exists('boutique_dinero')){
	function boutique_dinero($monto): string{
		return MONEDA_SIMBOLO.' '.number_format((float)$monto, 2, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR);
	}
}

if(!function_exists('boutique_caja_contacto')){
	// Caja "Ubicación y contacto" de la columna lateral
	function boutique_caja_contacto(string $direccion, string $mapsUrl, string $waUrl, string $texto): void{
		$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
		echo '<div class="cuenta-caja">';
		echo '<h2><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Ubicación y contacto</h2>';
		echo '<p>'.$e($texto).'</p>';
		if($direccion !== ''){
			echo '<div class="cuenta-direccion mb-3"><i class="fas fa-store" aria-hidden="true"></i><span>'.$e($direccion).'</span></div>';
		}else{
			echo '<p>Ubicación no configurada en el sistema.</p>';
		}
		echo '<div class="cuenta-botones">';
		if($mapsUrl !== ''){
			echo '<a class="cuenta-boton es-pequeno" href="'.$e($mapsUrl).'" target="_blank" rel="noopener"><i class="fas fa-map" aria-hidden="true"></i> Cómo llegar</a>';
		}
		if($waUrl !== ''){
			echo '<a class="cuenta-boton es-pequeno es-whatsapp" href="'.$e($waUrl).'" target="_blank" rel="noopener"><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>';
		}
		echo '</div></div>';
	}
}

if(!function_exists('boutique_fecha_partes')){
	// "2026-10-13", "10:00 am" -> día, mes corto, año, día de semana, hora
	function boutique_fecha_partes($fecha, $hora = ''): array{
		$meses = [1=>'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
		$semana = ['dom','lun','mar','mié','jue','vie','sáb'];
		$ts = strtotime((string)$fecha);
		if($ts === false){
			return ['dia' => '', 'mes' => '', 'anio' => '', 'semana' => '', 'hora' => trim((string)$hora), 'iso' => ''];
		}
		$h = trim((string)$hora);
		if(preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $h, $m)){
			$h = $m[1].':'.$m[2]; // 24 h sin segundos
		}
		return [
			'dia' => date('j', $ts),
			'mes' => $meses[(int)date('n', $ts)],
			'anio' => date('Y', $ts),
			'semana' => $semana[(int)date('w', $ts)],
			'hora' => $h,
			'iso' => date('Y-m-d', $ts),
		];
	}
}
