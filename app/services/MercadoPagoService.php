<?php

namespace app\services;

class MercadoPagoService{

	public function __construct(){
		$this->cargarConfig();
	}

	private function cargarConfig(){
		if(!defined('MP_ACCESS_TOKEN')){
			$ruta_config = __DIR__."/../../config/mercadopago.php";
			if(file_exists($ruta_config)){
				require_once $ruta_config;
			}
		}
	}

	public function configuracionValida(): bool{
		return defined('MP_ACCESS_TOKEN')
			&& MP_ACCESS_TOKEN !== ''
			&& MP_ACCESS_TOKEN !== 'TU_MP_ACCESS_TOKEN'
			&& function_exists('curl_init');
	}

	public function crearPreferencia(array $params): ?array{
		if(!$this->configuracionValida()){
			return null;
		}

		$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer '.MP_ACCESS_TOKEN,
			'Content-Type: application/json'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

		$response = curl_exec($ch);
		if($response===false){
			curl_close($ch);
			return null;
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if(!is_array($data)){
			return null;
		}

		// Mercado Pago devuelve errores en { message, error, status, cause }
		if($httpCode < 200 || $httpCode >= 300){
			$data['_http_code'] = $httpCode;
			return $data;
		}

		return $data;
	}

	public function obtenerPago($paymentId): ?array{
		if(!$this->configuracionValida()){
			return null;
		}

		$paymentId = preg_replace('/[^0-9]/', '', (string)$paymentId);
		if($paymentId===''){
			return null;
		}

		$url = 'https://api.mercadopago.com/v1/payments/'.$paymentId;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer '.MP_ACCESS_TOKEN,
			'Content-Type: application/json'
		]);

		$response = curl_exec($ch);
		if($response===false){
			curl_close($ch);
			return null;
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if(!is_array($data)){
			return null;
		}

		$data['_http_code'] = $httpCode;
		return $data;
	}
}
