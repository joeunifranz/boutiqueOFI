<?php

require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

use app\services\AgenteIaService;
use app\services\AgenteIaClientContextService;
use app\controllers\productController;

header('Content-Type: application/json; charset=utf-8');

if(!defined('AGENTE_IA_ENABLED') || AGENTE_IA_ENABLED !== true){
	http_response_code(404);
	echo json_encode(['ok'=>false, 'error'=>'Agente IA deshabilitado'], JSON_UNESCAPED_UNICODE);
	exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
	http_response_code(405);
	echo json_encode(['ok'=>false, 'error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
	exit;
}

$raw = file_get_contents('php://input');
$body = json_decode((string)$raw, true);
if(!is_array($body)){
	http_response_code(400);
	echo json_encode(['ok'=>false, 'error'=>'JSON inválido'], JSON_UNESCAPED_UNICODE);
	exit;
}

$message = isset($body['message']) ? (string)$body['message'] : '';
$message = trim($message);
if($message === ''){
	http_response_code(400);
	echo json_encode(['ok'=>false, 'error'=>'Mensaje vacío'], JSON_UNESCAPED_UNICODE);
	exit;
}

// Límite simple para evitar abuso
if(mb_strlen($message) > 2000){
	http_response_code(400);
	echo json_encode(['ok'=>false, 'error'=>'Mensaje demasiado largo'], JSON_UNESCAPED_UNICODE);
	exit;
}

$pagePath = isset($body['page']) ? (string)$body['page'] : '';
$pagePath = trim($pagePath);

// Historial opcional del chat (para mantener contexto entre recargas)
$chatHistory = [];
if(isset($body['chat_history']) && is_array($body['chat_history'])){
	// Sanitizar y recortar
	foreach(array_slice($body['chat_history'], -12) as $m){
		if(!is_array($m)){
			continue;
		}
		$role = isset($m['role']) ? (string)$m['role'] : '';
		$content = isset($m['content']) ? (string)$m['content'] : '';
		$role = trim($role);
		$content = trim($content);
		if($content === ''){
			continue;
		}
		if($role !== 'user' && $role !== 'assistant'){
			continue;
		}
		if(mb_strlen($content) > 1200){
			$content = mb_substr($content, 0, 1200);
		}
		$chatHistory[] = ['role'=>$role, 'content'=>$content];
	}
}

/**
 * Respuestas rápidas orientadas a compra.
 * Devuelven links clicables (http/https) para que el widget los convierta en <a>.
 */
function agenteIa_normalize(string $s): string{
	$s = trim($s);
	$s = mb_strtolower($s, 'UTF-8');
	$s = preg_replace('/\s+/u', ' ', $s);
	return trim((string)$s);
}

function agenteIa_extractCategoriaIdFromPath(string $pagePath): int{
	$pagePath = preg_replace('~[\?#].*$~', '', $pagePath);
	if(preg_match('~productosCliente/(\d+)~', $pagePath, $m)){
		return (int)$m[1];
	}
	return 0;
}

function agenteIa_extractTalla(string $message): string{
	// Prioridad: "talla X" (incluye número)
	if(preg_match('/\btalla\s*(XXXS|XXS|XS|S|M|L|XL|XXL|XXXL|\d{2})\b/iu', $message, $m)){
		return strtoupper((string)$m[1]);
	}
	// Alternativa: "soy S/M/L" (solo letras para evitar falsos positivos)
	if(preg_match('/\b(XXS|XS|S|M|L|XL|XXL|XXXL)\b/u', strtoupper($message), $m)){
		$norm = strtoupper((string)$m[1]);
		// Requiere que el mensaje hable de tallas
		$low = agenteIa_normalize($message);
		if(str_contains($low, 'talla') || str_contains($low, 'soy ') || str_contains($low, 'tengo ')){
			return $norm;
		}
	}
	return '';
}

function agenteIa_extractCategoriaFromMessage(string $message): array{
	// Devuelve ['id'=>int, 'name'=>string]
	$id = 0;
	$name = '';
	if(preg_match('/\bcategor(?:ia|ía)\s*(\d+)\b/iu', $message, $m)){
		$id = (int)$m[1];
	}
	if($id <= 0 && preg_match('/\bcategor(?:ia|ía)\s+([\p{L}\p{N}][\p{L}\p{N}\s\-]{1,60})/iu', $message, $m)){
		$name = trim((string)$m[1]);
		// Cortar por signos comunes
		$name = preg_split('/[\?\!\.,;\n]/u', $name)[0] ?? $name;
		$name = trim((string)$name);
	}
	return ['id'=>$id, 'name'=>$name];
}

function agenteIa_extractBudgetBs(string $message): int{
	// Ejemplos soportados: "1200bs", "1200 bs", "presupuesto 1200", "hasta 1.200 Bs"
	$budgetRaw = '';
	if(preg_match('/\b(?:presupuesto|hasta|maximo|máximo|max|tope)\s*(?:de\s*)?(\d[\d\.,\s]{0,12})\s*(?:bs|bss|bolivianos)?\b/iu', $message, $m)){
		$budgetRaw = (string)$m[1];
	}else if(preg_match('/\b(\d[\d\.,\s]{0,12})\s*(?:bs|bss|bolivianos)\b/iu', $message, $m)){
		$budgetRaw = (string)$m[1];
	}
	if($budgetRaw === ''){
		return 0;
	}
	// Convertir a entero, removiendo separadores
	$digits = preg_replace('/\D+/', '', $budgetRaw);
	$val = (int)$digits;
	// Rangos de sanidad
	if($val < 50 || $val > 20000){
		return 0;
	}
	return $val;
}

function agenteIa_extractReservaCodigoFromPath(string $pagePath): string{
	$pagePath = preg_replace('~[\?#].*$~', '', $pagePath);
	if(preg_match('~seguimientoReservaCliente/([^/]+)/~', $pagePath, $m)){
		return trim((string)$m[1]);
	}
	if(preg_match('~reservaPagar/([^/]+)/~', $pagePath, $m)){
		return trim((string)$m[1]);
	}
	if(preg_match('~reservaTicket\?code=([^&]+)~', $pagePath, $m)){
		return trim((string)$m[1]);
	}
	return '';
}

function agenteIa_quickReply(string $message, string $pagePath): string{
	$low = agenteIa_normalize($message);
	$base = (defined('APP_URL') ? (string)APP_URL : '');

	// Menú / ayuda (lo que puede hacer el cliente)
	if(
		str_contains($low, 'ayuda') ||
		str_contains($low, 'menu') ||
		str_contains($low, 'menú') ||
		str_contains($low, 'opciones') ||
		str_contains($low, 'que puedo hacer') ||
		str_contains($low, 'qué puedo hacer') ||
		str_contains($low, 'como funciona') ||
		str_contains($low, 'cómo funciona')
	){
		if($base === ''){
			return "Puedo ayudarte con catálogo, reservas y contacto.";
		}
		return "Aquí tienes lo que puedes hacer como cliente:\n\n".
			"- Ver catálogo: [Click aquí](".$base."productosCliente/)\n".
			"- Sugerencias de vestido (foto + tipo de cuerpo): [Click aquí](".$base."productosCliente/?reco=1#recoFoto)\n".
			"- Filtrar por talla (ej. talla M): dime \"talla M\" y te paso el link\n".
			"- Buscar por presupuesto (ej. 1200 Bs): dime \"presupuesto 1200 bs\"\n".
			"- Ver tus reservas y compras: [Click aquí](".$base."reservasComprasCliente/)\n".
			"- Seguimiento de una reserva (desde tu lista)\n".
			"- Pagar una reserva (desde el seguimiento)\n".
			"- Ubicación / WhatsApp / teléfono (dime \"ubicación\" o \"whatsapp\")";
	}

		// Sugerencias de vestido (guía)
		if(
			str_contains($low, 'sugerencia') ||
			str_contains($low, 'sugerencias') ||
			str_contains($low, 'sugier') ||
			str_contains($low, 'vestido') ||
			str_contains($low, 'vestidos') ||
			str_contains($low, 'recomiend') ||
			str_contains($low, 'recomendacion') ||
			str_contains($low, 'recomendación') ||
			str_contains($low, 'look') ||
			str_contains($low, 'outfit') ||
			str_contains($low, 'qué me pongo') ||
			str_contains($low, 'que me pongo') ||
			str_contains($low, 'probar') ||
			str_contains($low, 'probador')
		){
			$out = [];
			$out[] = "¡Claro! Para darte **SUGERENCIAS DE VESTIDO** necesito 2 cosas:";
			$out[] = "";
			$out[] = "1) Tu **tipo de cuerpo** (elige en el panel rosado).";
			$out[] = "2) Una **foto** (JPG/PNG) del vestido/estilo que te gusta (sube en el panel).";
			$out[] = "";
			$out[] = "Luego presiona **Sugerir** y te mostraré opciones del catálogo.";
			if($base !== ''){
				$out[] = "";
				$out[] = "También puedes abrirlo desde el catálogo: [Click aquí](".$base."productosCliente/?reco=1#recoFoto)";
			}
			return implode("\n", $out);
		}
	// No puedo asistir / reprogramar cita (redirigir + WhatsApp)
	if(
		str_contains($low, 'no puedo asistir') ||
		str_contains($low, 'no podre asistir') ||
		str_contains($low, 'no podré asistir') ||
		str_contains($low, 'no puedo ir') ||
		str_contains($low, 'no voy a poder') ||
		str_contains($low, 'reprogram') ||
		str_contains($low, 'cambiar fecha') ||
		str_contains($low, 'posponer') ||
		str_contains($low, 'cancelar cita')
	){
		$telefono = '+59178791595';
		$codigo = agenteIa_extractReservaCodigoFromPath($pagePath);
		$waMsg = ($codigo !== '')
			? ("Hola, no podré asistir a mi cita. Reserva: ".$codigo.". ¿Podemos reprogramar? Gracias.")
			: "Hola, no podré asistir a mi cita. ¿Podemos reprogramar? Gracias.";
		$wa = 'https://wa.me/59178791595?text='.urlencode($waMsg);
		$out = [];
		$out[] = "Claro, avísanos y la reprogramamos.";
		if($base !== ''){
			$out[] = "- Ver tus reservas y abrir el seguimiento: [Click aquí](".$base."reservasComprasCliente/)";
		}
		$out[] = "- WhatsApp (mensaje listo): [Click aquí]({$wa})";
		$out[] = "- Teléfono: {$telefono}";
		return implode("\n", $out);
	}

	// Presupuesto (recomendación con productos del sistema)
	$budget = agenteIa_extractBudgetBs($message);
	if($budget > 0 && (
		str_contains($low, 'presupuesto') ||
		str_contains($low, 'bs') ||
		str_contains($low, 'barato') ||
		str_contains($low, 'econ') ||
		str_contains($low, 'menor precio') ||
		str_contains($low, 'menos de')
	)){
		if($base === ''){
			return '';
		}
		// Margen: un poquito arriba, sin exagerar
		$margen = (int)ceil($budget * 0.15);
		if($margen > 200){ $margen = 200; }
		if($margen < 50){ $margen = 50; }
		$upper = $budget + $margen;

		$talla = agenteIa_extractTalla($message);
		$catFromPath = agenteIa_extractCategoriaIdFromPath($pagePath);
		$catFromMsg = agenteIa_extractCategoriaFromMessage($message);
		$catId = (int)($catFromMsg['id'] ?? 0);
		$catName = (string)($catFromMsg['name'] ?? '');
		if($catId <= 0 && $catName !== ''){
			try{
				$pc = new productController();
				$catId = $pc->obtenerCategoriaIdPorNombreControlador($catName);
			}catch(\Throwable $e){
				$catId = 0;
			}
		}
		if($catId <= 0){
			$catId = $catFromPath;
		}

		try{
			$pc = new productController();
			$items = $pc->buscarProductosPorMaxPrecioControlador($upper, $catId, $talla, 10);
		}catch(\Throwable $e){
			$items = [];
		}

		if(empty($items)){
			$catalogUrl = ($catId > 0) ? ($base."productosCliente/".$catId."/") : ($base."productosCliente/");
			$qs = [];
			$qs[] = 'max_price='.urlencode((string)$upper);
			if($talla !== ''){ $qs[] = 'talla='.urlencode($talla); }
			return "Con presupuesto {$budget} Bs no encontré productos dentro de {$upper} Bs ahora mismo.\n".
				"- Ver catálogo filtrado: [Click aquí](".$catalogUrl.'?'.implode('&', $qs).")";
		}

		$below = [];
		$above = [];
		foreach($items as $it){
			$precio = (float)($it['producto_precio_venta'] ?? 0);
			if($precio <= $budget){
				$below[] = $it;
			}else{
				$above[] = $it;
			}
		}
		$suggestions = array_merge(array_slice($below, 0, 4), array_slice($above, 0, 2));
		if(empty($suggestions)){
			$suggestions = array_slice($items, 0, 6);
		}

		$out = [];
		$out[] = "Con tu presupuesto de **{$budget} Bs**, te paso opciones recomendadas (hasta **{$upper} Bs**):";
		foreach($suggestions as $it){
			$id = (int)($it['producto_id'] ?? 0);
			$nombre = trim((string)($it['producto_nombre'] ?? 'Producto'));
			$precio = (float)($it['producto_precio_venta'] ?? 0);
			$tag = ($precio > $budget) ? ' (un poquito arriba)' : '';
			$link = $base.'productoDetalle/'.$id.'/';
			$out[] = "- {$nombre} — ".MONEDA_SIMBOLO.number_format($precio, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)."{$tag} — [Ver detalle]({$link})";
		}

		$catalogBase = ($catId > 0) ? ($base."productosCliente/".$catId."/") : ($base."productosCliente/");
		$qsBudget = [];
		$qsBudget[] = 'max_price='.urlencode((string)$budget);
		if($talla !== ''){ $qsBudget[] = 'talla='.urlencode($talla); }
		$qsUpper = [];
		$qsUpper[] = 'max_price='.urlencode((string)$upper);
		if($talla !== ''){ $qsUpper[] = 'talla='.urlencode($talla); }
		$out[] = "";
		$out[] = "Ver todo el catálogo filtrado:";
		$out[] = "- Hasta {$budget} Bs: [Click aquí](".$catalogBase.'?'.implode('&', $qsBudget).")";
		$out[] = "- Hasta {$upper} Bs (un poquito arriba): [Click aquí](".$catalogBase.'?'.implode('&', $qsUpper).")";
		if($talla === ''){
			$out[] = "";
			$out[] = "Si me dices tu **talla** (ej. talla M), te afino las opciones.";
		}
		return implode("\n", $out);
	}

	// Teléfono / contacto
	if(
		str_contains($low, 'telefono') || str_contains($low, 'teléfono') ||
		str_contains($low, 'numero') || str_contains($low, 'número') ||
		str_contains($low, 'celular') || str_contains($low, 'whatsapp') ||
		str_contains($low, 'contacto') || str_contains($low, 'llamar')
	){
		$telefono = '+59178791595';
		$wa = 'https://wa.me/59178791595';
		return "Puedes contactarnos aquí:\n- WhatsApp: [Click aquí]({$wa})\n- Teléfono: {$telefono}";
	}

	// Ubicación / dirección
	if(
		str_contains($low, 'direccion') || str_contains($low, 'dirección') ||
		str_contains($low, 'ubicacion') || str_contains($low, 'ubicación') ||
		str_contains($low, 'mapa') || str_contains($low, 'maps') ||
		str_contains($low, 'como llego') || str_contains($low, 'cómo llego')
	){
		$maps = 'https://maps.app.goo.gl/posRJ3ei9ufSqyNt5';
		return "Nuestra ubicación en Google Maps: [Click aquí]({$maps})";
	}

	// Talla (link al catálogo filtrado)
	$talla = agenteIa_extractTalla($message);
	if($talla !== '' && $base !== ''){
		$catFromPath = agenteIa_extractCategoriaIdFromPath($pagePath);
		$catFromMsg = agenteIa_extractCategoriaFromMessage($message);
		$catId = (int)($catFromMsg['id'] ?? 0);
		$catName = (string)($catFromMsg['name'] ?? '');

		// Si viene un nombre de categoría, intentamos resolverlo a ID (best-effort)
		if($catId <= 0 && $catName !== ''){
			try{
				$pc = new productController();
				$catId = $pc->obtenerCategoriaIdPorNombreControlador($catName);
			}catch(\Throwable $e){
				$catId = 0;
			}
		}
		if($catId <= 0){
			$catId = $catFromPath;
		}

		$url = ($catId > 0)
			? ($base."productosCliente/".$catId."/?talla=".urlencode($talla))
			: ($base."productosCliente/?talla=".urlencode($talla));
		return "Vestidos en talla {$talla}: [Click aquí]({$url})";
	}

	// Categoría (por ID o por nombre)
	if(str_contains($low, 'categoria') || str_contains($low, 'categoría')){
		$cat = agenteIa_extractCategoriaFromMessage($message);
		$catId = (int)($cat['id'] ?? 0);
		$catName = (string)($cat['name'] ?? '');

		if($catId > 0 && $base !== ''){
			$url = $base."productosCliente/".$catId."/";
			return "Vestidos de esa categoría: [Click aquí]({$url})";
		}

		if($catName !== '' && $base !== ''){
			try{
				$pc = new productController();
				$foundId = $pc->obtenerCategoriaIdPorNombreControlador($catName);
				if($foundId > 0){
					$url = $base."productosCliente/".$foundId."/";
					return "Categoría \"{$catName}\": [Click aquí]({$url})";
				}
			}catch(\Throwable $e){
				// ignorar
			}
		}
	}

	// Flujo de compra / cómo reservar
	if(str_contains($low, 'como comprar') || str_contains($low, 'cómo comprar') || str_contains($low, 'como reservo') || str_contains($low, 'cómo reservo') || str_contains($low, 'reservar') || str_contains($low, 'abono') || str_contains($low, 'pago')){
		if($base !== ''){
			return "Flujo de compra/reserva:\n1) Entra al catálogo: [Click aquí](".$base."productosCliente/)\n2) Abre un vestido y toca **Reservar con 50%**\n3) El sistema genera tu QR de reserva\n4) Pagas el abono o el total y se confirma tu reserva.";
		}
	}

	return '';
}

function agenteIa_isCatalogQuestion(string $message): bool{
	$low = agenteIa_normalize($message);
	$keys = [
		'vestido', 'vestidos', 'catalogo', 'catálogo', 'producto', 'productos',
		'largo', 'larga', 'corto', 'corta', 'sirena', 'princesa', 'encaje', 'strapless',
		'talla', 'precio', 'presupuesto', 'bs'
	];
	foreach($keys as $k){
		if(str_contains($low, $k)){
			return true;
		}
	}
	return false;
}

function agenteIa_buildCatalogReply(string $message, array $ctx, string $pagePath): string{
	$base = (defined('APP_URL') ? (string)APP_URL : '');
	if($base === ''){
		return '';
	}

	// Preguntas guiadas (solo preguntamos lo que falte)
	$tallaMsg = agenteIa_extractTalla($message);
	$low = agenteIa_normalize($message);
	$hasStyle = (
		str_contains($low, 'sirena') ||
		str_contains($low, 'princesa') ||
		str_contains($low, 'strapless') ||
		str_contains($low, 'encaje') ||
		str_contains($low, 'largo') ||
		str_contains($low, 'larga') ||
		str_contains($low, 'corto') ||
		str_contains($low, 'corta')
	);
	$hasBudget = (str_contains($low, 'bs') || preg_match('/\b\d{2,6}\b/u', $message));
	$guidedQs = [];
	if($tallaMsg === ''){
		$guidedQs[] = "¿Qué **talla** usas? (XS/S/M/L/XL o número)";
	}
	if(!$hasStyle){
		$guidedQs[] = "¿Qué **estilo** buscas? (sirena, princesa, strapless, encaje, largo/corto)";
	}
	if(!$hasBudget){
		$guidedQs[] = "¿Cuál es tu **presupuesto** aproximado en Bs?";
	}

	$db = isset($ctx['db_context']) ? (string)$ctx['db_context'] : '';
	$matches = [];
	if($db !== '' && preg_match('/PRODUCTOS_RELACIONADOS[\s\S]*?:\R(.+?)(?:\R\R|\z)/u', $db, $m)){
		$block = trim((string)$m[1]);
		$lines = preg_split('/\R/u', $block);
		if(is_array($lines)){
			foreach($lines as $line){
				$line = trim((string)$line);
				if($line === '' || !str_starts_with($line, '-')){
					continue;
				}
				$matches[] = $line;
			}
		}
	}

	// Si encontramos coincidencias, rearmamos la respuesta con links "Click aquí"
	if(!empty($matches)){
		$out = [];
		$out[] = "Perfecto. Encontré estas opciones en nuestro catálogo:";

		$max = min(6, count($matches));
		for($i=0; $i<$max; $i++){
			$line = $matches[$i];
			// Extraer link
			$link = '';
			if(preg_match('/\bLink:\s*(\S+)/u', $line, $mm)){
				$link = (string)$mm[1];
			}
			// Texto sin URL
			$text = preg_replace('/\s+—\s+Link:\s*\S+/u', '', $line);
			$text = preg_replace('/^\-\s*/u', '', (string)$text);
			$text = trim((string)$text);

			if($link !== ''){
				$out[] = "- {$text} — [Click aquí]({$link})";
			}else{
				$out[] = "- {$text}";
			}
		}

		// Si el bloque viene "lleno" (el contexto trae hasta 8), asumimos que hay más resultados
		if(count($matches) >= 8){
			$out[] = "\nVeo varias opciones. Para afinar y enviarte las mejores:";
		}
		if(!empty($guidedQs)){
			$out[] = implode("\n", array_map(fn($q) => "- {$q}", $guidedQs));
		}else{
			$out[] = "\n¿Quieres que te muestre más opciones o prefieres otra categoría?";
		}
		return implode("\n", $out);
	}

	// Si no hay coincidencias, damos ruta clara dentro del sistema
	$catId = agenteIa_extractCategoriaIdFromPath($pagePath);
	$url = ($catId > 0) ? ($base."productosCliente/".$catId."/") : ($base."productosCliente/");
	$out = [];
	$out[] = "No encontré coincidencias exactas en el catálogo con esa descripción.";
	$out[] = "";
	$out[] = "- Ver catálogo: [Click aquí]({$url})";
	$out[] = "";
	if(!empty($guidedQs)){
		$out[] = "Para ayudarte mejor, respóndeme:";
		$out[] = implode("\n", array_map(fn($q) => "- {$q}", $guidedQs));
	}else{
		$out[] = "Dime una palabra clave extra (ej. 'sirena', 'princesa', 'encaje', 'strapless', 'largo') y te paso opciones.";
	}
	return implode("\n", $out);
}

function agenteIa_responseLooksGeneric(string $response): bool{
	$r = mb_strtolower(trim($response), 'UTF-8');
	if($r === ''){
		return true;
	}
	// Frases típicas de modelo genérico + tiendas externas
	$patterns = [
		'como asistente de inteligencia artificial',
		'no tengo la capacidad',
		'no puedo buscar',
		'no tengo acceso',
		'no tengo la capacidad de buscar',
		'visita tiendas',
		'tiendas en línea',
		'zara', 'mango', 'h&m', 'amazon', 'ebay', 'asos', 'fashion nova', 'google shopping'
	];
	foreach($patterns as $p){
		if(str_contains($r, $p)){
			return true;
		}
	}
	return false;
}

$quick = agenteIa_quickReply($message, $pagePath);
if($quick !== ''){
	http_response_code(200);
	echo json_encode([
		'ok'=>true,
		'response'=>$quick,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

$ctxSvc = new AgenteIaClientContextService();
$ctx = $ctxSvc->build($message, $_SESSION ?? [], ($pagePath!=='' ? $pagePath : null));

// Si el usuario está buscando vestidos/productos, respondemos directo con catálogo (sin LLM)
if(agenteIa_isCatalogQuestion($message)){
	$catalogReply = agenteIa_buildCatalogReply($message, $ctx, $pagePath);
	if($catalogReply !== ''){
		http_response_code(200);
		echo json_encode([
			'ok'=>true,
			'response'=>$catalogReply,
		], JSON_UNESCAPED_UNICODE);
		exit;
	}
}

// Si detecta pregunta de admin, respondemos sin llamar al LLM
if(isset($ctx['blocked']) && $ctx['blocked'] === true){
	http_response_code(200);
	echo json_encode([
		'ok'=>true,
		'response'=>(string)($ctx['response'] ?? 'No tengo permisos para esa información.'),
	], JSON_UNESCAPED_UNICODE);
	exit;
}

$service = new AgenteIaService();
$result = $service->chat($message, [
	'db_context' => (string)($ctx['db_context'] ?? ''),
	'page_context' => (string)($ctx['page_context'] ?? ''),
	'user_context' => (array)($ctx['user_context'] ?? []),
	'chat_history' => $chatHistory,
]);

if(!isset($result['ok']) || $result['ok'] !== true){
	http_response_code(502);
	echo json_encode(['ok'=>false, 'error'=>$result['error'] ?? 'Error del agente'], JSON_UNESCAPED_UNICODE);
	exit;
}

// Respuesta normalizada
$resp = (string)$result['response'];

// Fallback: si el modelo responde genérico o recomienda tiendas externas
if(agenteIa_responseLooksGeneric($resp)){
	$catalogFallback = agenteIa_buildCatalogReply($message, $ctx, $pagePath);
	if($catalogFallback !== ''){
		$resp = $catalogFallback;
	}
}

http_response_code(200);
echo json_encode([
	'ok'=>true,
	'response'=>$resp,
], JSON_UNESCAPED_UNICODE);
