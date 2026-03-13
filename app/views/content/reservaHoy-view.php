<?php

$esAdmin = false;
if(isset($_SESSION['rol']) && $_SESSION['rol']==="Administrador"){
    $esAdmin = true;
}elseif(isset($_SESSION['usuario']) && $_SESSION['usuario']==="Administrador"){
    $esAdmin = true;
}elseif(isset($_SESSION['id']) && (int)$_SESSION['id']===1){
    $esAdmin = true;
}

if(!$esAdmin){
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede ver las citas de hoy.</div></article></div>";
    return;
}

?>

<div class="container is-fluid mb-6">
    <h1 class="title">Reservas</h1>
    <h2 class="subtitle"><i class="fas fa-calendar-day fa-fw"></i> &nbsp; Citas de hoy</h2>
</div>

<div class="container pb-6 pt-6">
    <div class="form-rest mb-6 mt-6"></div>

    <?php
        use app\controllers\reservationController;
        $insReserva = new reservationController();
        echo $insReserva->listarCitasDeHoyControlador(200);
    ?>
</div>
