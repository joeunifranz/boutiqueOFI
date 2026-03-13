<?php
use app\controllers\reservationController;

$esAdmin = false;
if(isset($_SESSION['rol']) && $_SESSION['rol']==="Administrador"){
    $esAdmin = true;
}elseif(isset($_SESSION['usuario']) && $_SESSION['usuario']==="Administrador"){
    $esAdmin = true;
}elseif(isset($_SESSION['id']) && (int)$_SESSION['id']===1){
    $esAdmin = true;
}

if(!$esAdmin){
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede aprobar/confirmar reservas.</div></article></div>";
    return;
}

$codigo = "";
if(isset($url[1]) && $url[1]!==""){
    $codigo = (string)$url[1];
}elseif(isset($_GET['codigo'])){
    $codigo = (string)$_GET['codigo'];
}

$insReserva = new reservationController();
$reserva = $insReserva->obtenerReservaPorCodigoControlador($codigo);

$pagoAprobado = null;
if($reserva){
    $pagoAprobado = $insReserva->obtenerUltimoPagoAprobadoPorCodigoControlador($reserva['reserva_codigo']);
}

if(!$reserva){
    echo "<div class='has-text-centered mt-6'>Reserva no encontrada</div>";
    return;
}

$total = (float)$reserva['reserva_total'];
$minimo = $total * 0.50;
$minimo = (float)number_format($minimo, MONEDA_DECIMALES, '.', '');

?>

<div class="container is-fluid">
    <h1 class="title">Confirmar reserva</h1>

    <div class="columns">
        <div class="column is-5">
            <div class="box">
                <h2 class="subtitle">Detalles</h2>

                <p><strong>Código:</strong> <?php echo htmlspecialchars($reserva['reserva_codigo']); ?></p>
                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($reserva['cliente_nombre']." ".$reserva['cliente_apellido']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($reserva['cliente_email']); ?></p>
                <hr>
                <p><strong>Producto:</strong> <?php echo htmlspecialchars($reserva['producto_nombre']); ?></p>
                <p><strong>Total:</strong> <?php echo MONEDA_SIMBOLO.number_format($total,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                <p><strong>Abono mínimo (50%):</strong> <?php echo MONEDA_SIMBOLO.number_format($minimo,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                <p><strong>Stock actual:</strong> <?php echo (int)$reserva['producto_stock_total']; ?></p>
                <p><strong>Estado:</strong> <?php echo htmlspecialchars($reserva['reserva_estado']); ?></p>
            </div>
        </div>

        <div class="column is-7">
            <div class="box">
                <h2 class="subtitle">Registrar abono</h2>

                <?php if($reserva['reserva_estado']=="confirmada"){ ?>
                    <article class="message is-success">
                        <div class="message-body">
                            Esta reserva ya está confirmada. Abono registrado: <strong><?php echo MONEDA_SIMBOLO.number_format((float)$reserva['reserva_abono'],2); ?></strong>.
                        </div>
                    </article>
                <?php }else{ ?>

                    <?php if($pagoAprobado){ ?>
                        <article class="message is-info">
                            <div class="message-body">
                                Pago online aprobado detectado: <strong><?php echo MONEDA_SIMBOLO.number_format((float)$pagoAprobado['pago_monto'],2); ?></strong>.
                                Puedes confirmar sin sumar a caja_efectivo.
                            </div>
                        </article>

                        <form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off">
                            <input type="hidden" name="modulo_reserva" value="confirmar_online">
                            <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars($reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?>">

                            <div class="field">
                                <button type="submit" class="button is-link">
                                    <i class="fas fa-check"></i> &nbsp; Confirmar usando pago online
                                </button>
                            </div>
                        </form>
                        <hr>
                    <?php } ?>

                    <form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off">
                        <input type="hidden" name="modulo_reserva" value="confirmar">
                        <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars($reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?>">

                        <div class="field">
                            <label class="label">Abono recibido (mínimo 50%)</label>
                            <div class="control">
                                <input class="input" type="text" name="reserva_abono" value="<?php echo htmlspecialchars(number_format($minimo,2,'.',''),ENT_QUOTES,'UTF-8'); ?>" pattern="[0-9.]{1,25}" maxlength="25" required>
                            </div>
                            <p class="help">Mínimo: <?php echo MONEDA_SIMBOLO.number_format($minimo,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                        </div>

                        <div class="field">
                            <button type="submit" class="button is-success">
                                <i class="fas fa-check"></i> &nbsp; Confirmar reserva
                            </button>
                        </div>
                    </form>

                    <article class="message is-warning mt-4">
                        <div class="message-body">
                            Al confirmar: se descuenta 1 unidad del stock y el abono se suma al efectivo de la caja del usuario actual.
                        </div>
                    </article>

                <?php } ?>
            </div>
        </div>
    </div>
</div>
