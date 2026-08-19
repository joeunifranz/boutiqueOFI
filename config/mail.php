<?php

/**
 * Configuración de correo.
 *
 * Driver soportado: smtp
 *
 * Recomendación (Gmail):
 * - host: smtp.gmail.com
 * - port: 587
 * - secure: tls
 * - username: tu correo
 * - password: contraseña de aplicación (NO tu clave normal)
 *
 * Puedes sobreescribir valores con variables de entorno:
 * - BOUTIQUE_SMTP_HOST, BOUTIQUE_SMTP_PORT, BOUTIQUE_SMTP_SECURE
 * - BOUTIQUE_SMTP_USERNAME, BOUTIQUE_SMTP_PASSWORD
 * - BOUTIQUE_SMTP_VERIFY_PEER, BOUTIQUE_SMTP_TIMEOUT
 * - BOUTIQUE_MAIL_FROM_EMAIL, BOUTIQUE_MAIL_FROM_NAME
 */

return [
	'driver' => 'smtp',
	'from' => [
		'email' => '',
		'name' => defined('APP_NAME') ? (string)APP_NAME : 'CITASS',
	],
	'smtp' => [
		'host' => 'smtp.gmail.com',
		'port' => 587,
		'secure' => 'tls', // tls|ssl|null
		'username' => '',
		// Define las credenciales en .env, nunca aquí.
		'password' => '',
		'timeout' => 20,
		'verify_peer' => true,
		// 'cafile' => 'C:/ruta/ca-bundle.crt',
		// 'capath' => 'C:/ruta/certs/',
	],
];
