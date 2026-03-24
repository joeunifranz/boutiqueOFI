<?php

	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	use app\controllers\fabricController;

	header('Content-Type: application/json; charset=utf-8');

	if(isset($_POST['modulo_tela'])){
		$insTela = new fabricController();

		switch($_POST['modulo_tela']){
			case 'listarPublico':
				echo $insTela->listarTelasPublicoControlador();
				break;
			case 'registrar':
				echo $insTela->registrarTelaControlador();
				break;
			case 'actualizar':
				echo $insTela->actualizarTelaControlador();
				break;
			case 'eliminar':
				echo $insTela->eliminarTelaControlador();
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
