<?php

	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	use app\controllers\encajeController;

	header('Content-Type: application/json; charset=utf-8');

	if(isset($_POST['modulo_encaje'])){
		$insEncaje = new encajeController();

		switch($_POST['modulo_encaje']){
			case 'listarPublico':
				echo $insEncaje->listarEncajesPublicoControlador();
				break;
			case 'registrar':
				echo $insEncaje->registrarEncajeControlador();
				break;
			case 'actualizar':
				echo $insEncaje->actualizarEncajeControlador();
				break;
			default:
				echo json_encode([
					'ok'=>false,
					'error'=>'unknown_action'
				]);
				break;
		}
		exit();
	}

	// Si alguien entra directo
	session_destroy();
	header("Location: ".APP_URL."login/");
	exit();
