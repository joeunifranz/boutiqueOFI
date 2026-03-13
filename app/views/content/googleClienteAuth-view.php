<?php

/* 
 * Vista de inicio de sesión de cliente con Google.
 * Aquí solo redirigimos a Google usando el controlador de login.
 * 
 * En la mayoría de casos, $insLogin ya viene creado desde index.php.
 * Si no existe, instanciamos el controlador directamente.
 */

if(!isset($insLogin)){
	$insLogin = new \app\controllers\loginController();
}

$insLogin->redirigirGoogleClienteControlador();

