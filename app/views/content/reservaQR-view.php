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

$afterUpload = isset($_GET['after_upload']) && $_GET['after_upload']!=='0';

$total = (float)$reserva['reserva_total'];
$minimo = $total * 0.50;
?>

<section class="boutique-bg boutique-client-page">
    <div class="boutique-bg-slider" aria-hidden="true">
        <div class="boutique-bg-slide s1"></div>
        <div class="boutique-bg-slide s2"></div>
        <div class="boutique-bg-slide s3"></div>
        <div class="boutique-bg-slide s4"></div>
        <div class="boutique-bg-slide s5"></div>
        <div class="boutique-bg-slide s6"></div>
    </div>
    <div class="boutique-bg-overlay" aria-hidden="true"></div>
    <?php require_once "./app/views/inc/navbar_cliente.php"; ?>
    <div class="boutique-client-content">
        <div class="container">
            <div class="boutique-glass p-5">
                <div class="has-text-centered">
                <h1 class="title is-4">Tu QR de reserva</h1>
                <?php if($afterUpload){ ?>
                    <p class="has-text-grey mb-4">¡Listo! Ya enviaste tu comprobante. Descarga este QR y <strong>guárdalo por favor</strong>, lo necesitarás para tu reserva.</p>
                <?php }else{ ?>
                    <p class="has-text-grey mb-4">Escanea este QR para abrir tu reserva. Ahí verás el QR de pago y podrás subir tu comprobante.</p>
                <?php } ?>

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
                    <button class="button is-success" type="button" id="btnDescargarQr">Descargar QR</button>
                    <a class="button is-light" href="<?php echo APP_URL; ?>productosCliente/">Volver a la tienda</a>
                </div>

                <?php if($afterUpload){ ?>
                    <p class="has-text-grey is-size-7 mt-4">Tip: guarda una captura o la imagen del QR en tu celular.</p>
                <?php }else{ ?>
                    <p class="has-text-grey is-size-7 mt-4">Nota: el QR abre la pantalla de pago (QR estático) y subida de comprobante.</p>
                <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function(){
    var btn = document.getElementById('btnDescargarQr');
    if(!btn) return;

    var qrUrl = <?php echo json_encode($qrImg, JSON_UNESCAPED_SLASHES); ?>;
    var filename = <?php echo json_encode('QR-reserva-'.$reserva['reserva_codigo'].'.png', JSON_UNESCAPED_SLASHES); ?>;

    function fallbackOpen(){
        window.open(qrUrl, '_blank', 'noopener');
    }

    btn.addEventListener('click', async function(){
        try{
            var res = await fetch(qrUrl, { mode: 'cors', cache: 'no-store' });
            if(!res.ok) throw new Error('fetch_failed');
            var blob = await res.blob();
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }catch(e){
            if(window.Swal){
                Swal.fire({
                    icon: 'info',
                    title: 'Descarga alternativa',
                    text: 'No pude descargar automáticamente el QR (posible bloqueo de internet). Se abrirá la imagen para que la guardes.',
                    confirmButtonText: 'Abrir QR'
                }).then(fallbackOpen);
            }else{
                fallbackOpen();
            }
        }
    });
})();
</script>
