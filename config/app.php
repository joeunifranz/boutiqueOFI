<?php

	const APP_URL="http://localhost/BOUTIQUE/";
	const APP_NAME="BOUTIQUE";
	const APP_SESSION_NAME="POS";

	/*----------  Tipos de documentos  ----------*/
	const DOCUMENTOS_USUARIOS=["Cedula","Licencia","DNI","Otro"];
	// Tipos de documento permitidos únicamente para clientes
	const DOCUMENTOS_CLIENTE=["Cedula","Otro"];


	/*----------  Tipos de unidades de productos  ----------*/
	const PRODUCTO_UNIDAD=["Unidad","Otro"];

	/*----------  Configuración de moneda  ----------*/
	const MONEDA_SIMBOLO="Bs";
	const MONEDA_NOMBRE="Bolivianos";
	const MONEDA_DECIMALES="2";
	const MONEDA_SEPARADOR_MILLAR=",";
	const MONEDA_SEPARADOR_DECIMAL=".";


	/*----------  Marcador de campos obligatorios (Font Awesome) ----------*/
	const CAMPO_OBLIGATORIO='&nbsp; <i class="fas fa-edit"></i> &nbsp;';

	/*----------  Zona horaria  ----------*/
	date_default_timezone_set("America/La_Paz");

	/*----------  Configuración de IA para Virtual Try-On  ----------*/
	// Opción 1: Replicate API (recomendado)
	// Obtén tu API key en: https://replicate.com/account/api-tokens
	// Guía completa: Ver archivo CONFIGURAR_API_IA.md
	const REPLICATE_API_KEY = "r8_Pgrzfs3RW7svNiAdXzk0tg2m1Kz8Vde3vz4eY"; // Tu API key de Replicate
	
	// Modelo de virtual try-on en Replicate
	// Puedes usar: "cuuupid/idm-vton" o "levihsu/ootdiffusion"
	const REPLICATE_MODEL = "cuuupid/idm-vton";
	
	// Opción 2: Usar simulación (sin API real)
	// Cambiar a false cuando tengas API key configurada
	const USE_AI_SIMULATION = false; // true = simulación, false = API real
	
	// Opción 3: ImgBB API (para subir imágenes temporalmente)
	// Obtén tu API key en: https://api.imgbb.com/ (gratis)
	// Necesario para que Replicate pueda acceder a tus imágenes
	const IMGBB_API_KEY = "d60d185f159459c2eb3f01f67560249b"; // Tu API key de ImgBB

	/*----------  Agente IA (chat para clientes)  ----------*/
	// Backend por defecto: servidor local (Flask) del proyecto agente_ia
	// Puedes sobreescribir con variables de entorno y reiniciar Apache:
	// - BOUTIQUE_AGENT_IA_ENABLED=1|0
	// - BOUTIQUE_AGENT_IA_API_URL=http://127.0.0.1:5000/chat
	if(!defined('AGENTE_IA_ENABLED')){
		$enabledEnv = getenv('BOUTIQUE_AGENT_IA_ENABLED');
		define('AGENTE_IA_ENABLED', ($enabledEnv===false) ? true : ((string)$enabledEnv==='1' || strtolower((string)$enabledEnv)==='true'));
	}
	if(!defined('AGENTE_IA_API_URL')){
		$apiEnv = getenv('BOUTIQUE_AGENT_IA_API_URL');
		define('AGENTE_IA_API_URL', ($apiEnv===false || trim((string)$apiEnv)==='') ? 'http://127.0.0.1:5000/chat' : trim((string)$apiEnv));
	}

