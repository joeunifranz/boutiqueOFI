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

$total = (float)$reserva['reserva_total'];
$minimo = (float)number_format($total * 0.50, MONEDA_DECIMALES, '.', '');

$pagoInfo = $insReserva->obtenerUltimoPagoAprobadoPorCodigoControlador($reserva['reserva_codigo']);

$pagoQr = $insReserva->obtenerUltimoQrGeneradoPorCodigoControlador($reserva['reserva_codigo']);

$mpResult = $_GET['mp_result'] ?? '';
$qrResult = $_GET['qr_result'] ?? '';
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
    <div class="columns is-centered">
        <div class="column is-6">
            <div class="box">
                <h1 class="title is-4 has-text-centered">Pagar tu reserva</h1>

                <?php if($qrResult==='generated' || $mpResult==='success'){ ?>
                    <article class="message is-success">
                        <div class="message-body">
                            QR generado. Cuando pagues, en unos segundos se actualizará el estado.
                        </div>
                    </article>
                <?php }elseif($mpResult==='failure'){ ?>
                    <article class="message is-danger">
                        <div class="message-body">
                            El pago fue cancelado o falló. Puedes generar el QR nuevamente.
                        </div>
                    </article>
                <?php }elseif($mpResult==='pending'){ ?>
                    <article class="message is-warning">
                        <div class="message-body">
                            Tu pago quedó pendiente. Cuando se apruebe, se confirmará/registrará automáticamente según configuración.
                        </div>
                    </article>
                <?php } ?>

                <div class="content">
                    <p><strong>Código:</strong> <?php echo htmlspecialchars($reserva['reserva_codigo']); ?></p>
                    <p><strong>Producto:</strong> <?php echo htmlspecialchars($reserva['producto_nombre']); ?></p>
                    <p><strong>Total:</strong> <?php echo MONEDA_SIMBOLO.number_format($total,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                    <p><strong>Abono mínimo (50%):</strong> <?php echo MONEDA_SIMBOLO.number_format($minimo,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                    <p><strong>Estado de la reserva:</strong> <?php echo htmlspecialchars($reserva['reserva_estado']); ?></p>
                </div>

                <?php if($pagoInfo){ ?>
                    <article class="message is-info">
                        <div class="message-body">
                            Pago aprobado registrado: <strong><?php echo MONEDA_SIMBOLO.number_format((float)$pagoInfo['pago_monto'],2); ?></strong> <?php echo htmlspecialchars($pagoInfo['pago_moneda']); ?>.
                            Estado MP: <strong><?php echo htmlspecialchars($pagoInfo['pago_status']); ?></strong>.
                        </div>
                    </article>
                <?php } ?>

                <?php if($reserva['reserva_estado']==='confirmada'){ ?>
                    <article class="message is-success">
                        <div class="message-body">
                            Esta reserva ya está confirmada.
                        </div>
                    </article>
                <?php }else{ ?>
                    <div class="buttons is-centered mt-4">
                        <form action="<?php echo APP_URL; ?>pagoBisaQR/" method="POST" style="display:inline-block;">
                            <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars($reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?>">
                            <input type="hidden" name="monto_tipo" value="minimo">
                            <button type="submit" class="button is-link">
                                Generar QR abono (50%)
                            </button>
                        </form>

                        <form action="<?php echo APP_URL; ?>pagoBisaQR/" method="POST" style="display:inline-block;">
                            <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars($reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?>">
                            <input type="hidden" name="monto_tipo" value="total">
                            <button type="submit" class="button is-success">
                                Generar QR total (100%)
                            </button>
                        </form>
                    </div>

                    <?php if($pagoQr && !empty($pagoQr['pago_qr_string'])){ ?>
                        <?php
                            $qrData = (string)$pagoQr['pago_qr_string'];
                            $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=".urlencode($qrData);
                        ?>
                        <div class="has-text-centered mt-5">
                            <p class="has-text-grey mb-2">Escanea este QR para pagar:</p>
                            <figure class="image is-inline-block" style="width:260px; height:260px;">
                                <img src="<?php echo htmlspecialchars($qrImg,ENT_QUOTES,'UTF-8'); ?>" alt="QR Pago" onerror="this.style.display='none'; document.getElementById('qrFallbackPay').style.display='block';">
                            </figure>
                            <div id="qrFallbackPay" class="notification is-warning" style="display:none;">
                                No se pudo cargar la imagen del QR.<br>
                                Datos del QR: <code><?php echo htmlspecialchars($qrData,ENT_QUOTES,'UTF-8'); ?></code>
                            </div>
                            <p class="has-text-grey is-size-7 mt-2">Generado: <?php echo htmlspecialchars($pagoQr['pago_creado_en'] ?? ''); ?></p>
                        </div>
                    <?php } ?>

                    <article class="message is-warning mt-4">
                        <div class="message-body">
                            Importante: el pago se procesa con QR BISA. La aprobación automática depende de que el webhook esté accesible públicamente.
                        </div>
                    </article>
                <?php } ?>

                <div class="buttons is-centered mt-4">
                    <a class="button is-light" href="<?php echo APP_URL; ?>productosCliente/">Volver a la tienda</a>
                </div>
            </div>
        </div>
    </div>
</div>
