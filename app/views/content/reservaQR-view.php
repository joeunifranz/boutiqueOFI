<?php
use app\controllers\reservationController;

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

$target = APP_URL."reservaPagar/".urlencode($reserva['reserva_codigo'])."/";
$qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=".urlencode($target);

$total = (float)$reserva['reserva_total'];
$minimo = $total * 0.50;
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
    <div class="columns is-centered">
        <div class="column is-6">
            <div class="box has-text-centered">
                <h1 class="title is-4">Tu QR de reserva</h1>
                <p class="has-text-grey mb-4">Escanea este QR para pagar el abono o el total de tu reserva.</p>

                <figure class="image is-inline-block" style="width:260px; height:260px;">
                    <img src="<?php echo htmlspecialchars($qrImg,ENT_QUOTES,'UTF-8'); ?>" alt="QR Reserva" onerror="this.style.display='none'; document.getElementById('qrFallback').style.display='block';">
                </figure>

                <div id="qrFallback" class="notification is-warning" style="display:none;">
                    No se pudo cargar la imagen del QR (posible falta de internet/bloqueo).<br>
                    Usa este enlace en caja: <a href="<?php echo htmlspecialchars($target,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($target); ?></a>
                </div>

                <div class="content mt-4">
                    <p><strong>Código:</strong> <?php echo htmlspecialchars($reserva['reserva_codigo']); ?></p>
                    <p><strong>Producto:</strong> <?php echo htmlspecialchars($reserva['producto_nombre']); ?></p>
                    <p><strong>Total:</strong> <?php echo MONEDA_SIMBOLO.number_format($total,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                    <p><strong>Abono mínimo (50%):</strong> <?php echo MONEDA_SIMBOLO.number_format($minimo,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                    <p><strong>Estado:</strong> <?php echo htmlspecialchars($reserva['reserva_estado']); ?></p>
                </div>

                <div class="buttons is-centered mt-4">
                    <a class="button is-link" href="<?php echo htmlspecialchars($target,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Abrir enlace del QR</a>
                    <a class="button is-light" href="<?php echo APP_URL; ?>productosCliente/">Volver a la tienda</a>
                </div>

                <p class="has-text-grey is-size-7 mt-4">
                    Nota: el QR abre la pantalla de pago. La confirmación automática depende de la configuración de BISA QR.
                </p>
            </div>
        </div>
    </div>
</div>
