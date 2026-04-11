<?php

	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	use app\controllers\recommendationController;

	if(isset($_POST['modulo_reco'])){

		$insReco = new recommendationController();

		if($_POST['modulo_reco']=="sugerir"){
			header('Content-Type: application/json; charset=utf-8');
			echo $insReco->recomendarVestidosPorFotoControlador();
			exit();
		}

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['ok'=>false,'error'=>'modulo_no_valido'], JSON_UNESCAPED_UNICODE);
		exit();
	}else{
		header("Location: ".APP_URL."inicio/");
		exit();
	}
