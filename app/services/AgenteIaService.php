<?php

namespace app\services;

class AgenteIaService{
	private string $apiUrl;

	public function __construct(?string $apiUrl = null){
		$this->apiUrl = $apiUrl ?: (defined('AGENTE_IA_API_URL') ? (string)AGENTE_IA_API_URL : '');
	}

	public function isConfigured(): bool{
		return trim($this->apiUrl) !== '';
	}

	/**
	 * Envía un mensaje al backend del agente (Flask) y devuelve el texto de respuesta.
	 * Espera una API tipo: POST JSON {"message":"..."} -> {"response":"..."}
	 */
	public function chat(string $message, array $options = []): array{
		$message = trim($message);
		if($message === ''){
			return ['ok'=>false, 'error'=>'Mensaje vacío'];
		}
		if(!$this->isConfigured()){
			return ['ok'=>false, 'error'=>'AGENTE_IA_API_URL no configurada'];
		}

		$payloadArr = ['message'=>$message];
		if(isset($options['db_context']) && is_string($options['db_context']) && trim($options['db_context']) !== ''){
			$payloadArr['db_context'] = (string)$options['db_context'];
		}
		if(isset($options['page_context']) && is_string($options['page_context']) && trim($options['page_context']) !== ''){
			$payloadArr['page_context'] = (string)$options['page_context'];
		}
		if(isset($options['user_context']) && is_array($options['user_context']) && !empty($options['user_context'])){
			$payloadArr['user_context'] = $options['user_context'];
		}

		$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
		if(!is_string($payload)){
			return ['ok'=>false, 'error'=>'No se pudo serializar el mensaje'];
		}

		$rawStr = '';
		$http = 0;
		if(function_exists('curl_init')){
			$ch = curl_init($this->apiUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Accept: application/json',
			]);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

			$raw = curl_exec($ch);
			$errno = curl_errno($ch);
			$err = curl_error($ch);
			$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if($raw === false){
				return ['ok'=>false, 'error'=>'No se pudo conectar con el agente: '.($err ?: 'error de red'), 'errno'=>$errno];
			}
			$rawStr = (string)$raw;
		}else{
			$context = stream_context_create([
				'http' => [
					'method' => 'POST',
					'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
					'content' => $payload,
					'timeout' => 60,
				],
			]);

			$raw = @file_get_contents($this->apiUrl, false, $context);
			if($raw === false){
				return ['ok'=>false, 'error'=>'No se pudo conectar con el agente (sin cURL)'];
			}
			$rawStr = (string)$raw;
			// No es trivial obtener HTTP code aquí sin parsear $http_response_header
			$http = 200;
		}
		$data = json_decode($rawStr, true);
		if(!is_array($data)){
			return ['ok'=>false, 'error'=>'Respuesta inválida del agente', 'http'=>$http, 'raw'=>$rawStr];
		}

		$response = isset($data['response']) ? (string)$data['response'] : '';
		if($response === ''){
			return ['ok'=>false, 'error'=>'El agente no devolvió respuesta', 'http'=>$http, 'data'=>$data];
		}

		return ['ok'=>true, 'response'=>$response, 'http'=>$http];
	}
}
