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

	/*----------  Recomendador de vestidos (local)  ----------*/
	// Ajustes de rendimiento y ponderación del ranking.
	// Se aplican en app/controllers/recommendationController.php
	if(!defined('RECO_MAX_PRODUCTS')){ define('RECO_MAX_PRODUCTS', 260); }
	if(!defined('RECO_MAX_RESULTADOS')){ define('RECO_MAX_RESULTADOS', 8); }
	// Pesos (0..1) que suman 1.0
	if(!defined('RECO_WEIGHT_COLOR')){ define('RECO_WEIGHT_COLOR', 0.35); }
	if(!defined('RECO_WEIGHT_CUERPO')){ define('RECO_WEIGHT_CUERPO', 0.65); }
	// Dentro del score de "cuerpo"
	if(!defined('RECO_WEIGHT_TIPO')){ define('RECO_WEIGHT_TIPO', 0.65); }
	if(!defined('RECO_WEIGHT_CINTURA')){ define('RECO_WEIGHT_CINTURA', 0.35); }

	/*----------  Tabla de tallas (visor 3D del maniquí) ----------*/
	// Coloca tu archivo en: app/views/models/maniqui.glb (por defecto)
	// Si usas otro nombre/ruta, ajusta esta constante.
	const TABLA_TALLAS_MODEL_PATH = "app/views/models/maniqui.glb";

