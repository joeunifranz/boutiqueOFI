<?php

require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

use app\controllers\reservationController;

if(isset($_POST['modulo_reserva'])){

	header('Content-Type: application/json; charset=utf-8');

    $insReserva = new reservationController();

    if($_POST['modulo_reserva']=="crear"){
        echo $insReserva->crearReservaClienteControlador();
    }

    if($_POST['modulo_reserva']=="horarios"){
        echo $insReserva->horariosDisponiblesControlador();
    }

    if($_POST['modulo_reserva']=="horarios_info_admin"){
        echo $insReserva->horariosInfoAdminControlador();
    }

    if($_POST['modulo_reserva']=="horarios_guardar_admin"){
        echo $insReserva->guardarHorariosAdminControlador();
    }

    if($_POST['modulo_reserva']=="confirmar"){
        echo $insReserva->confirmarReservaControlador();
    }

    if($_POST['modulo_reserva']=="confirmar_online"){
        echo $insReserva->confirmarReservaOnlineControlador();
    }

    if($_POST['modulo_reserva']=="rechazar"){
        echo $insReserva->rechazarReservaControlador();
    }

        if($_POST['modulo_reserva']=="completar"){
            echo $insReserva->completarReservaVentaControlador();
        }

}else{
    session_destroy();
    header("Location: ".APP_URL."inicio/");
}
