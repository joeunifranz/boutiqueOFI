<?php

/* 
 * Vista de callback de Google para clientes.
 * Procesa el código de autorización y crea/inicia sesión del cliente.
 * 
 * En la mayoría de casos, $insLogin ya viene creado desde index.php.
 * Si no existe, instanciamos el controlador directamente.
 */

if(!isset($insLogin)){
	$insLogin = new \app\controllers\loginController();
}

$insLogin->procesarGoogleClienteCallbackControlador();

