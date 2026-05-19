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
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede ver y aprobar reservas.</div></article></div>";
    return;
}

$insReserva = new reservationController();
$reservas = $insReserva->listarReservasPendientesControlador(80);

$cfgQr = __DIR__ . "/../../../config/reserva_pago_qr.php";
$qrImgCfg = '';
if(file_exists($cfgQr)){
    require_once $cfgQr;
    if(defined('RESERVA_PAGO_QR_IMAGE')){
        $qrImgCfg = (string)RESERVA_PAGO_QR_IMAGE;
    }
}

if($qrImgCfg!==''){
    $startsHttp = (stripos($qrImgCfg, 'http://')===0) || (stripos($qrImgCfg, 'https://')===0);
    $startsData = (stripos($qrImgCfg, 'data:image')===0);
    if(!$startsHttp && !$startsData){
        $qrImgCfg = APP_URL.ltrim($qrImgCfg, '/');
    }
}

?>

<div class="container is-fluid mb-6">
    <h1 class="title">Reservas</h1>
    <h2 class="subtitle"><i class="fas fa-clipboard-check fa-fw"></i> &nbsp; Pendientes / Aprobar</h2>
    <p class="has-text-right mt-3">
        <a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>exportarReservasPendientes/">
            <i class="fas fa-file-pdf"></i> &nbsp; Descargar reporte PDF
        </a>
        <button class="button is-warning is-light is-rounded" type="button" id="btnToggleQrConfig">
            <i class="fas fa-qrcode"></i> &nbsp; Subir/Actualizar QR estático
        </button>
    </p>
</div>

<div class="container is-fluid" id="qrConfigWrap" style="display:none;">
    <div class="box">
        <div class="columns is-vcentered">
            <div class="column is-6">
                <p class="has-text-grey mb-2">QR actual (se usa en el pago de reserva):</p>
                <?php if($qrImgCfg!==''){ ?>
                    <figure class="image" style="max-width:260px;">
                        <img src="<?php echo htmlspecialchars($qrImgCfg,ENT_QUOTES,'UTF-8'); ?>" alt="QR actual">
                    </figure>
                <?php }else{ ?>
                    <article class="message is-warning"><div class="message-body">Aún no hay QR configurado.</div></article>
                <?php } ?>
            </div>
            <div class="column is-6">
                <form id="formQrEstatico" enctype="multipart/form-data">
                    <input type="hidden" name="modulo_reserva" value="qr_estatico_subir">
                    <div class="field">
                        <label class="label">Subir imagen del QR (PNG/JPG)</label>
                        <div class="file has-name is-fullwidth">
                            <label class="file-label">
                                <input class="file-input" type="file" name="qr_image" accept="image/png,image/jpeg" required>
                                <span class="file-cta">
                                    <span class="file-icon"><i class="fas fa-upload"></i></span>
                                    <span class="file-label">Seleccionar imagen</span>
                                </span>
                                <span class="file-name" id="qrFileName">Ninguno</span>
                            </label>
                        </div>
                        <p class="help">Se guardará como <strong>app/views/img/qr_reserva.png</strong> o <strong>.jpg</strong> y se actualizará la configuración automáticamente.</p>
                    </div>
                    <div class="buttons">
                        <button type="submit" class="button is-warning">Actualizar QR</button>
                    </div>
                </form>
                <div id="qrConfigResult" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
    (function(){
        var btn = document.getElementById('btnToggleQrConfig');
        var wrap = document.getElementById('qrConfigWrap');
        if(btn && wrap){
            btn.addEventListener('click', function(){
                wrap.style.display = (wrap.style.display==='none' || wrap.style.display==='') ? 'block' : 'none';
            });
        }

        var form = document.getElementById('formQrEstatico');
        if(!form) return;
        var input = form.querySelector('input[type="file"][name="qr_image"]');
        var nameEl = document.getElementById('qrFileName');
        var resultEl = document.getElementById('qrConfigResult');
        if(input && nameEl){
            input.addEventListener('change', function(){
                nameEl.textContent = (input.files && input.files[0]) ? input.files[0].name : 'Ninguno';
            });
        }
        form.addEventListener('submit', async function(e){
            e.preventDefault();
            if(resultEl){ resultEl.innerHTML=''; }
            try{
                var fd = new FormData(form);
                var res = await fetch('<?php echo APP_URL; ?>app/ajax/reservaAjax.php', { method:'POST', body: fd });
                var data = await res.json();
                if(data && data.ok){
                    if(resultEl){ resultEl.innerHTML = '<article class="message is-success"><div class="message-body">'+(data.message || 'QR actualizado.')+' Recarga la página para ver el preview.</div></article>'; }
                }else{
                    if(resultEl){ resultEl.innerHTML = '<article class="message is-danger"><div class="message-body">'+((data && data.message) ? data.message : 'No se pudo actualizar el QR.')+'</div></article>'; }
                }
            }catch(err){
                if(resultEl){ resultEl.innerHTML = '<article class="message is-danger"><div class="message-body">Error al actualizar el QR.</div></article>'; }
            }
        });
    })();
</script>

<div class="container pb-6 pt-6">
	<div class="form-rest mb-6 mt-6"></div>

    <article class="message is-info">
        <div class="message-body">
            Aquí se listan las reservas <strong>pendientes</strong>. Para aprobarlas, abre la reserva y registra el abono (mínimo 50%).
        </div>
    </article>

    <?php if(empty($reservas)){ ?>
        <article class="message is-warning">
            <div class="message-body">
                No hay reservas pendientes por aprobar.
            </div>
        </article>
    <?php }else{ ?>

        <div class="box">
            <div class="level">
                <div class="level-left">
                    <p class="has-text-grey">Total pendientes: <strong><?php echo (int)count($reservas); ?></strong></p>
                </div>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="has-text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reservas as $r){
                            $codigo = (string)($r['reserva_codigo'] ?? '');
                            $cliente = trim(($r['cliente_nombre'] ?? '')." ".($r['cliente_apellido'] ?? ''));
                            $fecha = (string)($r['reserva_fecha'] ?? '');
                            $hora = (string)($r['reserva_hora'] ?? '');
                            $producto = (string)($r['producto_nombre'] ?? '');
                            $estado = (string)($r['reserva_estado'] ?? '');
                            $total = (float)($r['reserva_total'] ?? 0);
                            $urlConfirmar = APP_URL."reservaConfirmar/".urlencode($codigo)."/";
                            $urlDetalle = APP_URL."reservaDetalle/".urlencode($codigo)."/";
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($codigo,ENT_QUOTES,'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars(trim($fecha." ".$hora),ENT_QUOTES,'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($cliente,ENT_QUOTES,'UTF-8'); ?><br>
                                <span class="is-size-7 has-text-grey"><?php echo htmlspecialchars((string)($r['cliente_email'] ?? ''),ENT_QUOTES,'UTF-8'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($producto,ENT_QUOTES,'UTF-8'); ?></td>
                            <td><?php echo MONEDA_SIMBOLO.number_format($total, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)." ".MONEDA_NOMBRE; ?></td>
                            <td>
                                <span class="tag is-warning is-light"><?php echo htmlspecialchars($estado,ENT_QUOTES,'UTF-8'); ?></span>
                            </td>
                            <td class="has-text-right">
                                <a class="button is-info is-light is-small" href="<?php echo htmlspecialchars($urlDetalle,ENT_QUOTES,'UTF-8'); ?>">
                                    <i class="fas fa-eye"></i> &nbsp; Detalle
                                </a>
                                <a class="button is-success is-small" href="<?php echo htmlspecialchars($urlConfirmar,ENT_QUOTES,'UTF-8'); ?>">
                                    <i class="fas fa-check"></i> &nbsp; Aprobar
                                </a>
                                <form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off" style="display:inline-block;">
                                    <input type="hidden" name="modulo_reserva" value="rechazar">
                                    <input type="hidden" name="reserva_codigo" value="<?php echo htmlspecialchars($codigo,ENT_QUOTES,'UTF-8'); ?>">
                                    <input type="hidden" name="reserva_observacion" value="">
                                    <button type="submit" class="button is-danger is-small">
                                        <i class="fas fa-times"></i> &nbsp; Rechazar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php } ?>
</div>
