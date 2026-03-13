<?php

namespace app\services;

class BisaQrService{

	public function __construct(){
		$this->cargarConfig();
	}

	private function cargarConfig(){
		if(!defined('BISA_API_BASE_URL')){
			$ruta_config = __DIR__."/../../config/bisa_qr.php";
			if(file_exists($ruta_config)){
				require_once $ruta_config;
			}
		}
	}

	public function configuracionValida(): bool{
		return defined('BISA_API_BASE_URL')
			&& defined('BISA_API_KEY')
			&& BISA_API_BASE_URL !== ''
			&& BISA_API_KEY !== ''
			&& BISA_API_BASE_URL !== 'https://API_BASE_URL_DE_BISA'
			&& BISA_API_KEY !== 'TU_BISA_API_KEY'
			&& function_exists('curl_init');
	}

	private function buildUrl(string $path): string{
		$base = rtrim((string)BISA_API_BASE_URL, '/');
		$path = '/'.ltrim($path, '/');
		return $base.$path;
	}

	private function request(string $method, string $path, ?array $body = null): ?array{
		if(!$this->configuracionValida()){
			return null;
		}

		$url = $this->buildUrl($path);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

		$headers = [
			'Content-Type: application/json',
			// Ajustar header según documentación oficial (algunos usan x-api-key)
			'x-api-key: '.BISA_API_KEY
		];
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if($body !== null){
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		}

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

	/**
	 * Crear QR dinámico.
	 * NOTA: Los nombres exactos de campos dependen del API de BISA.
	 * Retorno esperado (ideal): ['qr_id' => '...', 'qr_string' => '...']
	 */
	public function crearQrDinamico(string $referencia, float $monto, string $moneda, string $descripcion, string $webhookUrl): ?array{
		if(!defined('BISA_ENDPOINT_CREATE_QR')){
			return null;
		}

		$payload = [
			'reference' => $referencia,
			'amount' => $monto,
			'currency' => $moneda,
			'description' => $descripcion,
			'webhook_url' => $webhookUrl
		];

		return $this->request('POST', (string)BISA_ENDPOINT_CREATE_QR, $payload);
	}

	public function obtenerEstado(string $qrId): ?array{
		if(!defined('BISA_ENDPOINT_GET_STATUS')){
			return null;
		}

		$qrId = trim($qrId);
		if($qrId===''){
			return null;
		}

		// Placeholder: muchas APIs usan /estado/{id} o querystring; ajustar según doc.
		return $this->request('GET', (string)BISA_ENDPOINT_GET_STATUS.'?id='.urlencode($qrId), null);
	}
}
