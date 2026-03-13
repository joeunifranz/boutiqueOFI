<?php

namespace app\services;

/**
 * Servicio para manejar el flujo OAuth de Google para clientes.
 * 
 * Centraliza:
 * - Carga de configuración (config/google_oauth.php)
 * - Generación de la URL de autorización
 * - Intercambio de code -> access_token
 * - Obtención de datos básicos del usuario (email, nombre, apellido)
 */
class GoogleClientAuth{

	public function __construct(){
		$this->cargarConfig();
	}

	private function cargarConfig(){
		if(!defined('GOOGLE_CLIENT_ID')){
			$ruta_config = __DIR__."/../../config/google_oauth.php";
			if(file_exists($ruta_config)){
				require_once $ruta_config;
			}
		}
	}

	public function configuracionValida(): bool{
		return defined('GOOGLE_CLIENT_ID')
			&& defined('GOOGLE_CLIENT_SECRET')
			&& defined('GOOGLE_REDIRECT_URI_CLIENT')
			&& GOOGLE_CLIENT_ID !== 'TU_CLIENT_ID_DE_GOOGLE'
			&& GOOGLE_CLIENT_SECRET !== 'TU_CLIENT_SECRET_DE_GOOGLE';
	}

	public function obtenerUrlAutorizacion(): string{
		$params = [
			"client_id" => GOOGLE_CLIENT_ID,
			"redirect_uri" => GOOGLE_REDIRECT_URI_CLIENT,
			"response_type" => "code",
			"scope" => "openid email profile",
			"include_granted_scopes" => "true",
			"access_type" => "online",
			"prompt" => "select_account"
		];

		return "https://accounts.google.com/o/oauth2/v2/auth?".http_build_query($params);
	}

	/**
	 * A partir del "code" devuelto por Google, obtiene los datos del usuario.
	 *
	 * @return array|null Array con claves: email, given_name, family_name, raw
	 */
	public function obtenerUsuarioDesdeCode(string $code): ?array{
		$tokens = $this->solicitarTokens($code);
		if(!$tokens || !isset($tokens['access_token'])){
			return null;
		}

		$userInfo = $this->obtenerUsuario($tokens['access_token']);
		if(!$userInfo || !isset($userInfo['email'])){
			return null;
		}

		return [
			"email"       => $userInfo['email'],
			"given_name"  => $userInfo['given_name']  ?? '',
			"family_name" => $userInfo['family_name'] ?? '',
			"raw"         => $userInfo
		];
	}

	private function solicitarTokens(string $code): ?array{
		if(!function_exists('curl_init')){
			return null;
		}

		$postFields = [
			"code" => $code,
			"client_id" => GOOGLE_CLIENT_ID,
			"client_secret" => GOOGLE_CLIENT_SECRET,
			"redirect_uri" => GOOGLE_REDIRECT_URI_CLIENT,
			"grant_type" => "authorization_code"
		];

		$ch = curl_init("https://oauth2.googleapis.com/token");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));

		$response = curl_exec($ch);
		if($response===false){
			curl_close($ch);
			return null;
		}
		curl_close($ch);

		$data = json_decode($response,true);
		return is_array($data) ? $data : null;
	}

	private function obtenerUsuario(string $accessToken): ?array{
		if(!function_exists('curl_init')){
			return null;
		}

		$ch = curl_init("https://www.googleapis.com/oauth2/v3/userinfo");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Authorization: Bearer ".$accessToken
		]);

		$response = curl_exec($ch);
		if($response===false){
			curl_close($ch);
			return null;
		}
		curl_close($ch);

		$data = json_decode($response,true);
		return is_array($data) ? $data : null;
	}
}

