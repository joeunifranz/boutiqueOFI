<?php

namespace app\services;

class WhatsAppService{
	private ?string $lastError = null;
	private array $config = [];

	private function startsWith(string $s, string $prefix): bool{
		if($prefix === '') return true;
		return substr($s, 0, strlen($prefix)) === $prefix;
	}

	public function __construct(?array $config = null){
		$this->config = is_array($config) ? $config : $this->loadConfig();
	}

	public function getLastError(): ?string{
		return (is_string($this->lastError) && $this->lastError !== '') ? $this->lastError : null;
	}

	private function fail(string $message): bool{
		$this->lastError = $message;
		return false;
	}

	private function loadConfig(): array{
		$path = __DIR__."/../../config/whatsapp.php";
		if(!file_exists($path)){
			return [];
		}
		$cfg = require $path;
		return is_array($cfg) ? $cfg : [];
	}

	private function env(string $key): ?string{
		$v = getenv($key);
		if($v === false){
			return null;
		}
		$v = trim((string)$v);
		return $v === '' ? null : $v;
	}

	private function envBool(string $key): ?bool{
		$v = $this->env($key);
		if($v === null){
			return null;
		}
		$b = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
		return $b;
	}

	public function isEnabled(): bool{
		$envEnabled = $this->envBool('BOUTIQUE_WA_ENABLED');
		if($envEnabled !== null){
			return $envEnabled === true;
		}
		return ($this->config['enabled'] ?? false) === true;
	}

	private function defaultCountryCode(): string{
		$env = $this->env('BOUTIQUE_WA_DEFAULT_COUNTRY_CODE');
		if($env !== null && ctype_digit($env)){
			return $env;
		}
		$cfg = trim((string)($this->config['default_country_code'] ?? ''));
		return ($cfg !== '' && ctype_digit($cfg)) ? $cfg : '591';
	}

	/**
	 * Normaliza un teléfono a formato E.164. Devuelve null si no es usable.
	 */
	public function normalizeToE164(?string $raw): ?string{
		$s = trim((string)$raw);
		if($s === '' || strtoupper($s) === 'N/A'){
			return null;
		}

		// Mantener '+' si existe, pero limpiar el resto.
		$hasPlus = $this->startsWith($s, '+');
		$digits = preg_replace('/\D+/', '', $s);
		if(!is_string($digits) || $digits === ''){
			return null;
		}

		// 00xxxx -> +xxxx
		if($this->startsWith($digits, '00')){
			$digits = substr($digits, 2);
			$hasPlus = true;
		}

		// Quitar ceros a la izquierda si no parece internacional
		if(!$hasPlus){
			$digits = ltrim($digits, '0');
		}

		$cc = $this->defaultCountryCode();
		// Si el número ya incluye país (por longitud) lo usamos tal cual.
		if($hasPlus){
			$e164 = '+'.$digits;
		}else{
			// Bolivia: normalmente 8 dígitos. Si viene corto, asumimos país por defecto.
			if(strlen($digits) <= 10 && !$this->startsWith($digits, $cc)){
				$digits = $cc.$digits;
			}
			$e164 = '+'.$digits;
		}

		// Validación básica E.164 (8 a 15 dígitos).
		$only = preg_replace('/\D+/', '', $e164);
		if(!is_string($only) || strlen($only) < 8 || strlen($only) > 15){
			return null;
		}
		return $e164;
	}

	private function provider(): string{
		$env = $this->env('BOUTIQUE_WA_PROVIDER');
		if($env !== null){
			return strtolower($env);
		}
		$p = (string)($this->config['provider'] ?? 'meta_cloud');
		$p = strtolower(trim($p));
		return $p !== '' ? $p : 'meta_cloud';
	}

	private function metaConfig(): array{
		$meta = $this->config['meta_cloud'] ?? [];
		if(!is_array($meta)){
			$meta = [];
		}

		$phoneNumberId = $this->env('BOUTIQUE_WA_PHONE_NUMBER_ID');
		$accessToken = $this->env('BOUTIQUE_WA_ACCESS_TOKEN');
		$apiVersion = $this->env('BOUTIQUE_WA_API_VERSION');
		$baseUrl = $this->env('BOUTIQUE_WA_BASE_URL');

		if($phoneNumberId !== null){
			$meta['phone_number_id'] = $phoneNumberId;
		}
		if($accessToken !== null){
			$meta['access_token'] = $accessToken;
		}
		if($apiVersion !== null){
			$meta['api_version'] = $apiVersion;
		}
		if($baseUrl !== null){
			$meta['base_url'] = $baseUrl;
		}

		$meta['api_version'] = trim((string)($meta['api_version'] ?? 'v21.0'));
		if($meta['api_version'] === ''){
			$meta['api_version'] = 'v21.0';
		}
		$meta['base_url'] = trim((string)($meta['base_url'] ?? 'https://graph.facebook.com'));
		if($meta['base_url'] === ''){
			$meta['base_url'] = 'https://graph.facebook.com';
		}

		return $meta;
	}

	private function templates(): array{
		$t = $this->config['templates'] ?? [];
		return is_array($t) ? $t : [];
	}

	private function httpPostJson(string $url, array $headers, array $payload): ?array{
		$this->lastError = null;
		$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
		if(!is_string($body)){
			$this->lastError = 'No se pudo serializar JSON';
			return null;
		}

		$headers[] = 'Content-Type: application/json';

		// Preferir cURL si está disponible.
		if(function_exists('curl_init')){
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			$resp = curl_exec($ch);
			$errno = curl_errno($ch);
			$err = curl_error($ch);
			$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if($resp === false){
				$this->lastError = $errno ? ('cURL error #'.$errno.': '.$err) : 'cURL fallo sin detalle';
				return null;
			}

			$decoded = json_decode((string)$resp, true);
			if($httpCode < 200 || $httpCode >= 300){
				$this->lastError = 'HTTP '.$httpCode.' :: '.(is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_SLASHES) : (string)$resp);
				return null;
			}
			return is_array($decoded) ? $decoded : ['_raw' => (string)$resp];
		}

		$headersStr = implode("\r\n", $headers);
		$ctx = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => $headersStr,
				'content' => $body,
				'timeout' => 20,
				'ignore_errors' => true,
			],
		]);

		$resp = @file_get_contents($url, false, $ctx);
		if($resp === false){
			$this->lastError = 'No se pudo conectar a WhatsApp (file_get_contents)';
			return null;
		}

		// Capturar HTTP code
		$code = 0;
		if(isset($http_response_header) && is_array($http_response_header)){
			foreach($http_response_header as $h){
				if(preg_match('/^HTTP\/[0-9.]+\s+(\d+)/', (string)$h, $m)){
					$code = (int)$m[1];
					break;
				}
			}
		}

		$decoded = json_decode((string)$resp, true);
		if($code < 200 || $code >= 300){
			$this->lastError = 'HTTP '.$code.' :: '.(is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_SLASHES) : (string)$resp);
			return null;
		}
		return is_array($decoded) ? $decoded : ['_raw' => (string)$resp];
	}

	private function metaSend(array $payload): bool{
		if(!$this->isEnabled()){
			return $this->fail('WhatsApp deshabilitado');
		}
		if($this->provider() !== 'meta_cloud'){
			return $this->fail('Proveedor WhatsApp no soportado');
		}

		$meta = $this->metaConfig();
		$phoneNumberId = trim((string)($meta['phone_number_id'] ?? ''));
		$token = trim((string)($meta['access_token'] ?? ''));
		$apiVersion = trim((string)($meta['api_version'] ?? 'v21.0'));
		$baseUrl = rtrim((string)($meta['base_url'] ?? 'https://graph.facebook.com'), '/');

		if($phoneNumberId === ''){
			return $this->fail('Falta BOUTIQUE_WA_PHONE_NUMBER_ID');
		}
		if($token === ''){
			return $this->fail('Falta BOUTIQUE_WA_ACCESS_TOKEN');
		}

		$url = $baseUrl.'/'.$apiVersion.'/'.$phoneNumberId.'/messages';
		$headers = ['Authorization: Bearer '.$token];

		$res = $this->httpPostJson($url, $headers, $payload);
		return is_array($res);
	}

	public function sendText(?string $rawPhone, string $text): bool{
		$this->lastError = null;
		$to = $this->normalizeToE164($rawPhone);
		if($to === null){
			return $this->fail('Teléfono inválido');
		}
		$text = trim($text);
		if($text === ''){
			return $this->fail('Mensaje vacío');
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'to' => ltrim($to, '+'),
			'type' => 'text',
			'text' => [
				'preview_url' => true,
				'body' => $text,
			],
		];

		return $this->metaSend($payload);
	}

	public function sendTemplate(?string $rawPhone, string $templateName, string $language = 'es', array $components = []): bool{
		$this->lastError = null;
		$to = $this->normalizeToE164($rawPhone);
		if($to === null){
			return $this->fail('Teléfono inválido');
		}
		$templateName = trim($templateName);
		if($templateName === ''){
			return $this->fail('Template vacío');
		}
		$language = trim($language);
		if($language === ''){
			$language = 'es';
		}

		$tpl = [
			'name' => $templateName,
			'language' => ['code' => $language],
		];
		if(!empty($components)){
			$tpl['components'] = $components;
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'to' => ltrim($to, '+'),
			'type' => 'template',
			'template' => $tpl,
		];

		return $this->metaSend($payload);
	}

	private function formatFecha(?string $ymd): string{
		$ymd = (string)$ymd;
		$ymd = trim($ymd);
		if($ymd === '') return '';
		try{
			$dt = new \DateTime($ymd);
			return $dt->format('d/m/Y');
		}catch(\Throwable $e){
			return $ymd;
		}
	}

	/**
	 * Notificación: reserva creada (pendiente).
	 * @param array $reserva Debe contener cliente_telefono, reserva_codigo, reserva_fecha, reserva_hora, producto_nombre.
	 */
	public function notifyReservaCreada(array $reserva, ?string $link = null): bool{
		$tel = (string)($reserva['cliente_telefono'] ?? '');
		$codigo = (string)($reserva['reserva_codigo'] ?? '');
		$fecha = $this->formatFecha((string)($reserva['reserva_fecha'] ?? ''));
		$hora = (string)($reserva['reserva_hora'] ?? '');
		$producto = (string)($reserva['producto_nombre'] ?? '');
		$app = defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE';

		$tpl = $this->templates()['reserva_creada'] ?? null;
		if(is_array($tpl) && trim((string)($tpl['name'] ?? '')) !== ''){
			$name = trim((string)$tpl['name']);
			$lang = trim((string)($tpl['language'] ?? 'es'));
			// Componentes placeholder (si tu template los usa)
			$components = [[
				'type' => 'body',
				'parameters' => array_values(array_filter([
					['type' => 'text', 'text' => $codigo],
					['type' => 'text', 'text' => $fecha],
					['type' => 'text', 'text' => $hora],
					['type' => 'text', 'text' => $producto],
					($link ? ['type' => 'text', 'text' => $link] : null),
				], fn($x) => is_array($x))),
			]];
			return $this->sendTemplate($tel, $name, $lang, $components);
		}

		$msg = strtoupper($app).": Tu reserva fue registrada.\n".
			"CÓDIGO: {$codigo}\n".
			($producto !== '' ? ("VESTIDO: {$producto}\n") : '').
			($fecha !== '' ? ("FECHA: {$fecha}\n") : '').
			($hora !== '' ? ("HORA: {$hora}\n") : '').
			"ESTADO: PENDIENTE\n".
			($link ? ("DETALLE: {$link}") : '');

		return $this->sendText($tel, trim($msg));
	}

	/**
	 * Notificación: ticket / confirmación.
	 */
	public function notifyTicketReserva(array $reserva, string $mensajeEstado, ?string $linkPdf = null, ?float $saldo = null): bool{
		$tel = (string)($reserva['cliente_telefono'] ?? '');
		$codigo = (string)($reserva['reserva_codigo'] ?? '');
		$fecha = $this->formatFecha((string)($reserva['reserva_fecha'] ?? ''));
		$hora = (string)($reserva['reserva_hora'] ?? '');
		$producto = (string)($reserva['producto_nombre'] ?? '');
		$estado = strtoupper((string)($reserva['reserva_estado'] ?? ''));
		$app = defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE';

		$tpl = $this->templates()['reserva_ticket'] ?? null;
		if(is_array($tpl) && trim((string)($tpl['name'] ?? '')) !== ''){
			$name = trim((string)$tpl['name']);
			$lang = trim((string)($tpl['language'] ?? 'es'));
			$saldoTxt = ($saldo === null) ? '' : (string)$saldo;
			$components = [[
				'type' => 'body',
				'parameters' => array_values(array_filter([
					['type' => 'text', 'text' => $codigo],
					['type' => 'text', 'text' => $estado],
					['type' => 'text', 'text' => $fecha],
					['type' => 'text', 'text' => $hora],
					['type' => 'text', 'text' => $producto],
					($saldoTxt !== '' ? ['type' => 'text', 'text' => $saldoTxt] : null),
					($linkPdf ? ['type' => 'text', 'text' => $linkPdf] : null),
				], fn($x) => is_array($x))),
			]];
			return $this->sendTemplate($tel, $name, $lang, $components);
		}

		$msg = strtoupper($app).": {$mensajeEstado}\n".
			"CÓDIGO: {$codigo}\n".
			($producto !== '' ? ("VESTIDO: {$producto}\n") : '').
			($fecha !== '' ? ("FECHA: {$fecha}\n") : '').
			($hora !== '' ? ("HORA: {$hora}\n") : '').
			($estado !== '' ? ("ESTADO: {$estado}\n") : '').
			(($saldo !== null) ? ("SALDO: ".number_format(max(0, (float)$saldo), (int)(defined('MONEDA_DECIMALES') ? MONEDA_DECIMALES : 2), (string)(defined('MONEDA_SEPARADOR_DECIMAL') ? MONEDA_SEPARADOR_DECIMAL : '.'), (string)(defined('MONEDA_SEPARADOR_MILLAR') ? MONEDA_SEPARADOR_MILLAR : ','))." ".(defined('MONEDA_NOMBRE') ? (string)MONEDA_NOMBRE : '')."\n") : '').
			($linkPdf ? ("TICKET: {$linkPdf}") : '');

		return $this->sendText($tel, trim($msg));
	}

	public function notifyReprogramacion(array $reserva, string $nuevaFechaPretty, string $nuevaHora, string $motivo, ?float $saldo = null): bool{
		$tel = (string)($reserva['cliente_telefono'] ?? '');
		$codigo = (string)($reserva['reserva_codigo'] ?? '');
		$producto = (string)($reserva['producto_nombre'] ?? '');
		$app = defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE';

		$tpl = $this->templates()['reprogramacion'] ?? null;
		if(is_array($tpl) && trim((string)($tpl['name'] ?? '')) !== ''){
			$name = trim((string)$tpl['name']);
			$lang = trim((string)($tpl['language'] ?? 'es'));
			$saldoTxt = ($saldo === null) ? '' : (string)$saldo;
			$components = [[
				'type' => 'body',
				'parameters' => array_values(array_filter([
					['type' => 'text', 'text' => $codigo],
					['type' => 'text', 'text' => $producto],
					['type' => 'text', 'text' => $nuevaFechaPretty],
					['type' => 'text', 'text' => $nuevaHora],
					['type' => 'text', 'text' => $motivo],
					($saldoTxt !== '' ? ['type' => 'text', 'text' => $saldoTxt] : null),
				], fn($x) => is_array($x))),
			]];
			return $this->sendTemplate($tel, $name, $lang, $components);
		}

		$msg = strtoupper($app).": Tu cita fue REPROGRAMADA.\n".
			"CÓDIGO: {$codigo}\n".
			($producto !== '' ? ("VESTIDO: {$producto}\n") : '').
			"NUEVA FECHA: {$nuevaFechaPretty}\n".
			"NUEVA HORA: {$nuevaHora}\n".
			"MOTIVO: {$motivo}\n".
			(($saldo !== null) ? ("SALDO: ".number_format(max(0, (float)$saldo), (int)(defined('MONEDA_DECIMALES') ? MONEDA_DECIMALES : 2), (string)(defined('MONEDA_SEPARADOR_DECIMAL') ? MONEDA_SEPARADOR_DECIMAL : '.'), (string)(defined('MONEDA_SEPARADOR_MILLAR') ? MONEDA_SEPARADOR_MILLAR : ','))." ".(defined('MONEDA_NOMBRE') ? (string)MONEDA_NOMBRE : '')) : '');

		return $this->sendText($tel, trim($msg));
	}

	public function notifyRecordatorio1d(array $data): bool{
		$tel = (string)($data['cliente_telefono'] ?? '');
		$codigo = (string)($data['reserva_codigo'] ?? '');
		$fecha = $this->formatFecha((string)($data['reserva_fecha'] ?? ''));
		$hora = (string)($data['reserva_hora'] ?? '');
		$producto = (string)($data['producto_nombre'] ?? '');
		$app = defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE';

		$tpl = $this->templates()['recordatorio_1d'] ?? null;
		if(is_array($tpl) && trim((string)($tpl['name'] ?? '')) !== ''){
			$name = trim((string)$tpl['name']);
			$lang = trim((string)($tpl['language'] ?? 'es'));
			$components = [[
				'type' => 'body',
				'parameters' => [
					['type' => 'text', 'text' => $codigo],
					['type' => 'text', 'text' => $fecha],
					['type' => 'text', 'text' => $hora],
					['type' => 'text', 'text' => $producto],
				],
			]];
			return $this->sendTemplate($tel, $name, $lang, $components);
		}

		$msg = strtoupper($app).": Recordatorio de tu cita (MAÑANA).\n".
			"CÓDIGO: {$codigo}\n".
			($producto !== '' ? ("VESTIDO: {$producto}\n") : '').
			($fecha !== '' ? ("FECHA: {$fecha}\n") : '').
			($hora !== '' ? ("HORA: {$hora}") : '');

		return $this->sendText($tel, trim($msg));
	}
}
