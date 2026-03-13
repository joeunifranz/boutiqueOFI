<?php
	
	require_once "../../config/app.php";
	require_once "../../autoload.php";
	
	use app\controllers\virtualTryOnController;

	if(isset($_POST['modulo_tryon'])){

		$insTryOn = new virtualTryOnController();

		if($_POST['modulo_tryon']=="procesar"){
			echo $insTryOn->procesarTryOnControlador();
		}

		if($_POST['modulo_tryon']=="obtenerProductos"){
			echo $insTryOn->obtenerProductosProbadorControlador();
		}
		
	}else{
		header("Location: ".APP_URL."inicio/");
	}

