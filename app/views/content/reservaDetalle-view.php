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
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede ver el detalle de reservas.</div></article></div>";
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

if(!$reserva){
    echo "<div class='has-text-centered mt-6'>Reserva no encontrada</div>";
    return;
}

$estado = (string)($reserva['reserva_estado'] ?? '');
$tagClass = 'is-light';
$tagColor = 'is-info';
if($estado==='pendiente'){
    $tagColor = 'is-warning';
}elseif($estado==='confirmada'){
    $tagColor = 'is-success';
}elseif($estado==='completada'){
    $tagColor = 'is-link';
}elseif($estado==='rechazada'){
    $tagColor = 'is-danger';
}

$targetConfirmar = APP_URL."reservaConfirmar/".urlencode($reserva['reserva_codigo'])."/";
$qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=".urlencode($targetConfirmar);

$total = (float)($reserva['reserva_total'] ?? 0);
$abono = (float)($reserva['reserva_abono'] ?? 0);
$minimo = (float)number_format(($total*0.50), MONEDA_DECIMALES, '.', '');

$urlAprobar = APP_URL."reservaConfirmar/".urlencode($reserva['reserva_codigo'])."/";

?>

<div class="container is-fluid mb-6">
    <h1 class="title">Reservas</h1>
    <h2 class="subtitle"><i class="fas fa-info-circle fa-fw"></i> &nbsp; Detalle de reserva</h2>
</div>

<div class="container pb-6 pt-6">
    <div class="form-rest mb-6 mt-6"></div>

    <div class="columns">
        <div class="column is-5">
            <div class="box has-text-centered">
                <h3 class="title is-5">QR de confirmación</h3>
                <p class="has-text-grey">Este es el QR que abre la pantalla de confirmación en caja.</p>

                <figure class="image is-inline-block mt-4" style="width:260px; height:260px;">
                    <img src="<?php echo htmlspecialchars($qrImg,ENT_QUOTES,'UTF-8'); ?>" alt="QR Reserva" onerror="this.style.display='none'; document.getElementById('qrFallbackAdmin').style.display='block';">
                </figure>

                <div id="qrFallbackAdmin" class="notification is-warning" style="display:none;">
                    No se pudo cargar la imagen del QR.<br>
                    Enlace: <a href="<?php echo htmlspecialchars($targetConfirmar,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($targetConfirmar,ENT_QUOTES,'UTF-8'); ?></a>
                </div>

                <div class="buttons is-centered mt-4">
                    <a class="button is-link" href="<?php echo htmlspecialchars($targetConfirmar,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i> &nbsp; Abrir enlace del QR
                    </a>
                </div>
            </div>
        </div>

        <div class="column is-7">
            <div class="box">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <p class="title is-5 mb-1">Código: <?php echo htmlspecialchars((string)$reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?></p>
                            <span class="tag <?php echo $tagColor." ".$tagClass; ?>">Estado: <?php echo htmlspecialchars($estado,ENT_QUOTES,'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="content">
                    <p><strong>Fecha:</strong> <?php echo htmlspecialchars(trim((string)$reserva['reserva_fecha']." ".(string)$reserva['reserva_hora']),ENT_QUOTES,'UTF-8'); ?></p>

                    <p><strong>Cliente:</strong> <?php echo htmlspecialchars(trim((string)$reserva['cliente_nombre']." ".(string)$reserva['cliente_apellido']),ENT_QUOTES,'UTF-8'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars((string)$reserva['cliente_email'],ENT_QUOTES,'UTF-8'); ?></p>

                    <p><strong>Producto:</strong> <?php echo htmlspecialchars((string)$reserva['producto_nombre'],ENT_QUOTES,'UTF-8'); ?></p>

                    <p><strong>Total:</strong> <?php echo MONEDA_SIMBOLO.number_format($total, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)." ".MONEDA_NOMBRE; ?></p>
                    <p><strong>Abono mínimo (50%):</strong> <?php echo MONEDA_SIMBOLO.number_format($minimo, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)." ".MONEDA_NOMBRE; ?></p>
                    <p><strong>Abono registrado:</strong> <?php echo MONEDA_SIMBOLO.number_format($abono, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)." ".MONEDA_NOMBRE; ?></p>

                    <?php if(!empty($reserva['reserva_observacion'])){ ?>
                        <p><strong>Observación:</strong> <?php echo htmlspecialchars((string)$reserva['reserva_observacion'],ENT_QUOTES,'UTF-8'); ?></p>
                    <?php } ?>
                </div>

                <hr>

                <div class="buttons">
                    <button type="button" class="button is-link is-light" onclick="print_ticket('<?php echo APP_URL.'app/pdf/reserva_ticket.php?code='.urlencode((string)$reserva['reserva_codigo']); ?>')" title="Imprimir ticket" >
                        <i class="fas fa-receipt fa-fw"></i> &nbsp; Imprimir ticket
                    </button>

                    <?php if($estado==='pendiente'){ ?>
                        <a class="button is-success" href="<?php echo htmlspecialchars($urlAprobar,ENT_QUOTES,'UTF-8'); ?>">
                            <i class="fas fa-check"></i> &nbsp; Aprobar
                        </a>

                        <form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off" style="display:inline-block;">
                            <input type="hidden" name="modulo_reserva" value="rechazar">
                            <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars((string)$reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?>">

                            <div class="field has-addons">
                                <div class="control is-expanded">
                                    <input class="input" type="text" name="reserva_observacion" maxlength="255" placeholder="Motivo (opcional)">
                                </div>
                                <div class="control">
                                    <button type="submit" class="button is-danger">
                                        <i class="fas fa-times"></i> &nbsp; Rechazar
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php }else{ ?>
                        <a class="button is-light" href="<?php echo APP_URL; ?>reservaAprobar/">
                            <i class="fas fa-arrow-left"></i> &nbsp; Volver
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "./app/views/inc/print_invoice_script.php"; ?>
