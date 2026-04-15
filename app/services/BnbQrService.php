<?php

namespace app\services;

class BnbQrService{

	public function __construct(){
		$this->cargarConfig();
	}

	private function cargarConfig(){
		if(!defined('BNB_API_BASE_URL')){
			$ruta_config = __DIR__."/../../config/bnb_qr.php";
			if(file_exists($ruta_config)){
				require_once $ruta_config;
			}
		}
	}

	public function configuracionValida(): bool{
		return defined('BNB_API_BASE_URL')
			&& defined('BNB_ENDPOINT_TOKEN')
			&& defined('BNB_ENDPOINT_CONSUMIR_QR')
			&& defined('BNB_ACCOUNT_ID')
			&& defined('BNB_AUTHORIZATION_ID')
			&& (string)BNB_API_BASE_URL !== ''
			&& (string)BNB_API_BASE_URL !== 'https://API_BASE_URL_DE_BNB'
			&& (string)BNB_ACCOUNT_ID !== ''
			&& (string)BNB_ACCOUNT_ID !== 'TU_ACCOUNT_ID'
			&& (string)BNB_AUTHORIZATION_ID !== ''
			&& (string)BNB_AUTHORIZATION_ID !== 'TU_AUTHORIZATION_ID'
			&& function_exists('curl_init');
	}

	private function buildUrl(string $path): string{
		$base = rtrim((string)BNB_API_BASE_URL, '/');
		$path = '/'.ltrim($path, '/');
		return $base.$path;
	}

	private function requestForm(string $path, array $formFields): ?array{
		if(!$this->configuracionValida()){
			return null;
		}

		$url = $this->buildUrl($path);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/x-www-form-urlencoded; charset=utf-8'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formFields));

		$response = curl_exec($ch);
		if($response===false){
			curl_close($ch);
			return null;
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if(!is_array($data)){
			$data = ['raw' => $response];
		}
		$data['_http_code'] = $httpCode;
		return $data;
	}

	private function buildA1FromArray(array $payload): string{
		// El sandbox del portal envía el JSON sin llaves: "k":"v",...
		// Aquí armamos ese mismo formato.
		$parts = [];
		foreach($payload as $k=>$v){
			$k = (string)$k;
			if(is_bool($v)){
				$vStr = $v ? 'true' : 'false';
				$parts[] = '"'.$k.'":'.$vStr;
				continue;
			}
			if(is_int($v) || is_float($v)){
				$parts[] = '"'.$k.'":'.(string)$v;
				continue;
			}
			$parts[] = '"'.$k.'":"'.str_replace('"','\\"',(string)$v).'"';
		}
		return implode(',', $parts);
	}

	public function obtenerToken(): ?string{
		if(!$this->configuracionValida()){
			return null;
		}

		$a1 = $this->buildA1FromArray([
			'accountId' => (string)BNB_ACCOUNT_ID,
			'authorizationId' => (string)BNB_AUTHORIZATION_ID,
		]);

		$resp = $this->requestForm((string)BNB_ENDPOINT_TOKEN, [
			'a1' => $a1,
			'a2' => 'token'
		]);

		if(!is_array($resp) || (($resp['_http_code'] ?? 0) < 200) || (($resp['_http_code'] ?? 0) >= 300)){
			return null;
		}

		// Algunos gateways devuelven el token directo en campos dedicados.
		foreach(['Token','token','access_token','AccessToken','accessToken'] as $k){
			if(isset($resp[$k]) && is_string($resp[$k]) && trim($resp[$k])!==''){
				return trim((string)$resp[$k]);
			}
		}

		$mensaje = $resp['Mensaje'] ?? null;
		if(!is_string($mensaje) || trim($mensaje)===''){
			return null;
		}

		// A veces el mensaje es el token directo; otras, puede venir como JSON.
		$mensaje = trim($mensaje);
		$asJson = json_decode($mensaje, true);
		if(is_array($asJson)){
			$token = $asJson['token'] ?? ($asJson['access_token'] ?? ($asJson['Token'] ?? null));
			if(is_string($token) && trim($token)!==''){
				return trim($token);
			}
		}
		return $mensaje;
	}

	/**
	 * Genera un QR con imagen (base64) usando getQRWithImageAsync.
	 * Retorno esperado (según sandbox): Mensaje con JSON que incluye qrImage + qrCode.
	 */
	public function getQRWithImageAsync(string $gloss, float $amount, bool $singleUse = true, ?string $expirationDate = null, ?string $token = null): ?array{
		if(!$this->configuracionValida()){
			return null;
		}

		if($token===null || trim($token)===''){
			$token = $this->obtenerToken();
		}
		if(!is_string($token) || trim($token)===''){
			return null;
		}

		$currency = defined('BNB_CURRENCY_ID') ? (string)BNB_CURRENCY_ID : 'BOB';
		$payload = [
			'currency' => $currency,
			'gloss' => $gloss,
			'amount' => (float)$amount,
			'singleUse' => $singleUse,
		];
		if($expirationDate!==null && trim($expirationDate)!==''){
			$payload['expirationDate'] = trim($expirationDate);
		}

		$a1 = $this->buildA1FromArray($payload);
		$resp = $this->requestForm((string)BNB_ENDPOINT_CONSUMIR_QR, [
			'a1' => $a1,
			'a2' => 'getQRWithImageAsync',
			'a3' => $token,
		]);

		if(!is_array($resp)){
			return null;
		}
		return $resp;
	}

	public function getQRStatusAsync(string $qrId, ?string $token = null): ?array{
		if(!$this->configuracionValida()){
			return null;
		}

		$qrId = trim($qrId);
		if($qrId===''){
			return null;
		}

		if($token===null || trim($token)===''){
			$token = $this->obtenerToken();
		}
		if(!is_string($token) || trim($token)===''){
			return null;
		}

		$a1 = $this->buildA1FromArray([
			'qrId' => $qrId,
		]);

		$resp = $this->requestForm((string)BNB_ENDPOINT_CONSUMIR_QR, [
			'a1' => $a1,
			'a2' => 'getQRStatusAsync',
			'a3' => $token,
		]);

		if(!is_array($resp)){
			return null;
		}
		return $resp;
	}
}
