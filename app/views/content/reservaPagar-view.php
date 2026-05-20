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

$ultimoComprobante = $insReserva->obtenerUltimoComprobanteSubidoPorCodigoControlador($reserva['reserva_codigo']);
$ultimoComprobanteArchivo = '';
if($ultimoComprobante && !empty($ultimoComprobante['pago_raw'])){
    $raw = json_decode((string)$ultimoComprobante['pago_raw'], true);
    if(is_array($raw) && !empty($raw['archivo'])){
        $ultimoComprobanteArchivo = (string)$raw['archivo'];
    }
}

$cfgQr = __DIR__ . "/../../../config/reserva_pago_qr.php";
$qrData = '';
$qrImgDirect = '';
if(file_exists($cfgQr)){
    require_once $cfgQr;
    if(defined('RESERVA_PAGO_QR_DATA')){ $qrData = (string)RESERVA_PAGO_QR_DATA; }
    if(defined('RESERVA_PAGO_QR_IMAGE')){ $qrImgDirect = (string)RESERVA_PAGO_QR_IMAGE; }
}

// Si el QR viene como ruta relativa, la convertimos a URL pública
if($qrImgDirect!==''){
    $startsHttp = (stripos($qrImgDirect, 'http://')===0) || (stripos($qrImgDirect, 'https://')===0);
    $startsData = (stripos($qrImgDirect, 'data:image')===0);
    if(!$startsHttp && !$startsData){
        $qrImgDirect = APP_URL.ltrim($qrImgDirect, '/');
    }
}

$qrImg = '';
if($qrImgDirect!==''){
    $qrImg = $qrImgDirect;
}elseif($qrData!=='' && $qrData!=='CAMBIAME_POR_TU_QR_ESTATICO'){
    $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=".urlencode($qrData);
}
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
            <div class="columns is-centered">
        <div class="column is-6">
                <h1 class="title is-4 has-text-centered">Pagar tu reserva</h1>

                <div class="content">
                    <p><strong>Código:</strong> <?php echo htmlspecialchars($reserva['reserva_codigo']); ?></p>
                    <p><strong>Producto:</strong> <?php echo htmlspecialchars($reserva['producto_nombre']); ?></p>
                    <p><strong>Total:</strong> <?php echo MONEDA_SIMBOLO.number_format($total,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                    <p><strong>Abono mínimo (50%):</strong> <?php echo MONEDA_SIMBOLO.number_format($minimo,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                    <p><strong>Estado de la reserva:</strong> <?php echo htmlspecialchars($reserva['reserva_estado']); ?></p>
                </div>

                <?php if($reserva['reserva_estado']==='confirmada'){ ?>
                    <article class="message is-success">
                        <div class="message-body">
                            Esta reserva ya está confirmada.
                        </div>
                    </article>
                <?php }else{ ?>
                    <div class="has-text-centered mt-4">
                        <p class="has-text-grey mb-2">Escanea este QR para pagar tu reserva y luego sube tu comprobante.</p>
                        <?php if($qrImg!==''){ ?>
                            <figure class="image is-inline-block" style="width:260px; height:260px;">
                                <img src="<?php echo htmlspecialchars($qrImg,ENT_QUOTES,'UTF-8'); ?>" alt="QR Pago" onerror="this.style.display='none'; document.getElementById('qrFallbackStatic').style.display='block';">
                            </figure>
                            <div id="qrFallbackStatic" class="notification is-warning" style="display:none;">
                                No se pudo cargar la imagen del QR.
                                <?php if($qrData!==''){ ?>
                                    <br>Datos del QR: <code><?php echo htmlspecialchars($qrData,ENT_QUOTES,'UTF-8'); ?></code>
                                <?php } ?>
                            </div>
                        <?php }else{ ?>
                            <article class="message is-warning">
                                <div class="message-body">
                                    QR estático no configurado. Edita <code>config/reserva_pago_qr.php</code> y cambia <code>RESERVA_PAGO_QR_DATA</code>.
                                </div>
                            </article>
                        <?php } ?>
                    </div>

                    <?php if($ultimoComprobanteArchivo!==''){ ?>
                        <article class="message is-info mt-4">
                            <div class="message-body">
                                Ya subiste un comprobante: <a href="<?php echo htmlspecialchars(APP_URL.$ultimoComprobanteArchivo,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Ver comprobante</a>
                            </div>
                        </article>
                    <?php } ?>

                    <div class="mt-5">
                        <form id="formComprobanteReserva" enctype="multipart/form-data">
                            <input type="hidden" name="modulo_reserva" value="comprobante_subir">
                            <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars($reserva['reserva_codigo'],ENT_QUOTES,'UTF-8'); ?>">

                            <div class="field">
                                <label class="label">Subir comprobante</label>
                                <div class="file has-name is-fullwidth">
                                    <label class="file-label">
                                        <input class="file-input" type="file" name="comprobante" accept="image/jpeg,image/png,application/pdf" required>
                                        <span class="file-cta">
                                            <span class="file-icon"><i class="fas fa-upload"></i></span>
                                            <span class="file-label">Seleccionar archivo</span>
                                        </span>
                                        <span class="file-name" id="comprobanteFileName">Ninguno</span>
                                    </label>
                                </div>
                                <p class="help">Formatos: JPG, PNG o PDF.</p>
                            </div>

                            <div class="buttons is-centered">
                                <button type="submit" class="button is-link">Enviar comprobante</button>
                            </div>
                        </form>
                        <div id="comprobanteResult" class="mt-3"></div>
                    </div>

                    <script>
                        (function(){
                            var form = document.getElementById('formComprobanteReserva');
                            if(!form) return;
                            var appUrl = <?php echo json_encode(APP_URL, JSON_UNESCAPED_SLASHES); ?>;
                            var reservaCodigo = <?php echo json_encode($reserva['reserva_codigo'], JSON_UNESCAPED_SLASHES); ?>;
                            var input = form.querySelector('input[type="file"][name="comprobante"]');
                            var nameEl = document.getElementById('comprobanteFileName');
                            var resultEl = document.getElementById('comprobanteResult');
                            if(input && nameEl){
                                input.addEventListener('change', function(){
                                    nameEl.textContent = (input.files && input.files[0]) ? input.files[0].name : 'Ninguno';
                                });
                            }

                            var goToReservaQr = function(){
                                window.location.href = appUrl + 'reservaQR/' + encodeURIComponent(reservaCodigo) + '/?after_upload=1';
                            };

                            form.addEventListener('submit', async function(e){
                                e.preventDefault();
                                if(resultEl){ resultEl.innerHTML = ''; }
                                try{
                                    var fd = new FormData(form);
                                    var res = await fetch('<?php echo APP_URL; ?>app/ajax/reservaAjax.php', { method: 'POST', body: fd });
                                    var data = await res.json();
                                    if(data && data.ok){
                                        if(window.Swal){
                                            Swal.fire({
                                                icon: 'success',
                                                title: '¡Comprobante enviado! ✅',
                                                text: 'Perfecto. Ahora podrás ver y descargar tu QR de reserva. Guárdalo por favor.',
                                                confirmButtonText: 'Ver mi QR de reserva'
                                            }).then(goToReservaQr);
                                        }else{
                                            if(resultEl){ resultEl.innerHTML = '<article class="message is-success"><div class="message-body">'+(data.message || 'Comprobante subido.')+' ✅</div></article>'; }
                                            setTimeout(goToReservaQr, 700);
                                        }
                                    }else{
                                        if(resultEl){ resultEl.innerHTML = '<article class="message is-danger"><div class="message-body">'+((data && data.message) ? data.message : 'No se pudo subir el comprobante.')+'</div></article>'; }
                                    }
                                }catch(err){
                                    if(resultEl){ resultEl.innerHTML = '<article class="message is-danger"><div class="message-body">Error al subir el comprobante.</div></article>'; }
                                }
                            });
                        })();
                    </script>
                <?php } ?>

                <div class="buttons is-centered mt-4">
                    <a class="button is-light" href="<?php echo APP_URL; ?>productosCliente/">Volver a la tienda</a>
                </div>
        </div>
    </div>
            </div>
        </div>
    </div>
</section>
