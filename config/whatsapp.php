<?php

/**
 * Configuración de WhatsApp.
 *
 * Recomendado: usar variables de entorno (BOUTIQUE_WA_*).
 * Nota: WhatsApp Business suele requerir mensajes por plantilla (template)
 * para iniciar conversación fuera de la ventana de 24h.
 */
return [
	// Activar/desactivar globalmente.
	'enabled' => false,

	// Por ahora se soporta Meta WhatsApp Cloud API.
	'provider' => 'meta_cloud',

	// País por defecto para normalizar teléfonos sin código (ej. Bolivia: 591).
	'default_country_code' => '591',

	'meta_cloud' => [
		// Ej: "123456789012345" (Phone Number ID)
		'phone_number_id' => '',
		// Token de acceso (System User / App) con permisos para WhatsApp.
		'access_token' => '',
		// Versión de Graph API.
		'api_version' => 'v21.0',
		// Base URL.
		'base_url' => 'https://graph.facebook.com',
	],

	// Plantillas opcionales (si las tienes aprobadas en WhatsApp Manager).
	// Si están vacías, se intentará enviar texto simple.
	'templates' => [
		'reserva_creada' => [
			'name' => '',
			'language' => 'es',
		],
		'reserva_ticket' => [
			'name' => '',
			'language' => 'es',
		],
		'reprogramacion' => [
			'name' => '',
			'language' => 'es',
		],
		'recordatorio_1d' => [
			'name' => '',
			'language' => 'es',
		],
	],
];
