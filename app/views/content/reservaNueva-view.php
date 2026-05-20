<?php
use app\controllers\productController;

$id = 0;
if(isset($url[1]) && $url[1]!==""){
    $id = (int)$url[1];
}elseif(isset($_GET['id'])){
    $id = (int)$_GET['id'];
}

$clienteLogueado = (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] !== "");

$insProducto = new productController();

$producto = $insProducto->obtenerProductoPorIdControlador($id);

if(!$producto){
    echo "<div class='has-text-centered mt-6'>Producto no encontrado</div>";
    return;
}

$total = (float)$producto['producto_precio_venta'];
$minimo = $total * 0.50;

$tallasRaw = isset($producto['producto_talla']) ? (string)$producto['producto_talla'] : '';
$tallas = [];
if(trim($tallasRaw) !== ''){
    $parts = preg_split('/[,;]+/', $tallasRaw);
    if(is_array($parts)){
        foreach($parts as $p){
            $p = trim((string)$p);
            if($p !== ''){ $tallas[] = $p; }
        }
    }
}
if(!empty($tallas)){
    $tallas = array_values(array_unique($tallas));
}

$feriados = [];
$rutaFeriados = "./config/feriados.php";
if(is_file($rutaFeriados)){
    $dataFeriados = include $rutaFeriados;
    if(is_array($dataFeriados)){
        $feriados = array_values($dataFeriados);
    }
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
    <div class="columns is-vcentered">
        <div class="column is-5">
            <figure class="image">
                <?php
                if(is_file("./app/views/productos/".$producto['producto_foto'])){
                    echo '<img class="detalle-img" src="'.APP_URL.'app/views/productos/'.$producto['producto_foto'].'" alt="">';
                }else{
                    echo '<img class="detalle-img" src="'.APP_URL.'app/views/productos/default.png" alt="">';
                }
                ?>
            </figure>
        </div>
        <div class="column is-7">
            <h1 class="title is-3 has-text-weight-light">
                Reservar: <?php echo htmlspecialchars($producto['producto_nombre']); ?>
            </h1>

            <div class="content">
                <p><strong>Precio:</strong> <?php echo MONEDA_SIMBOLO.number_format($total,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                <p><strong>Abono mínimo (50%):</strong> <?php echo MONEDA_SIMBOLO.number_format($minimo,2); ?> <?php echo MONEDA_NOMBRE; ?></p>
                <p class="has-text-grey is-size-7">La reserva se confirma cuando el personal registra el abono. El QR sirve para abrir rápidamente la reserva en caja.</p>
            </div>

            <form id="reservaForm" class="FormularioAjax" data-loading="true" data-loading-title="" data-loading-text="" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off">
                <input type="hidden" name="modulo_reserva" value="crear">
                <input type="hidden" name="producto_id" value="<?php echo (int)$producto['producto_id']; ?>">

                <?php if(!empty($tallas)){ ?>
                    <div class="field">
                        <label class="label">Talla</label>
                        <div class="control">
                            <?php if(count($tallas)===1){ ?>
                                <input type="hidden" name="reserva_talla" value="<?php echo htmlspecialchars((string)$tallas[0],ENT_QUOTES,'UTF-8'); ?>">
                                <p class="help">Talla seleccionada: <strong><?php echo htmlspecialchars((string)$tallas[0],ENT_QUOTES,'UTF-8'); ?></strong></p>
                            <?php }else{ ?>
                                <div class="select is-fullwidth">
                                    <select id="reserva_talla" name="reserva_talla" required>
                                        <option value="">Selecciona una talla</option>
                                        <?php foreach($tallas as $t){ ?>
                                            <option value="<?php echo htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8'); ?>"><?php echo htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8'); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="columns is-multiline">
                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Fecha de cita</label>
                            <div class="control">
                                <input id="cita_fecha" name="cita_fecha" class="input" type="date" required>
                            </div>
                            <p class="help">Disponible de lunes a sábado</p>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Hora</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="cita_hora" name="cita_hora" required disabled>
                                        <option value="">Selecciona una fecha primero</option>
                                    </select>
                                </div>
                            </div>
                            <p id="cita_help" class="help">Horario: 10:00 am a 07:00 pm</p>
                        </div>
                    </div>
                </div>

                <button id="btnReservaQR" type="submit" class="button is-danger is-medium is-fullwidth mb-3" disabled>
                    <i class="fas fa-qrcode"></i> &nbsp; Generar QR de reserva
                </button>
            </form>

            <a href="<?php echo APP_URL; ?>productoDetalle/<?php echo (int)$producto['producto_id']; ?>/" class="button is-light is-fullwidth mb-2">
                Volver al producto
            </a>
            <a href="<?php echo APP_URL; ?>productosCliente/" class="button is-white is-fullwidth">
                Volver a la tienda
            </a>
        </div>
    </div>
            </div>
        </div>
    </div>
</section>

<style>
.detalle-img{ border-radius: 16px; }
</style>

<script>
(() => {
    const clienteLogueado = <?php echo json_encode($clienteLogueado); ?>;
    const productoId = <?php echo (int)$producto['producto_id']; ?>;
	const redirectTo = <?php echo json_encode('reservaNueva/'.(int)$producto['producto_id'].'/', JSON_UNESCAPED_SLASHES); ?>;
	const storageKey = 'boutique_reserva_pendiente_v1';

    const feriados = <?php echo json_encode($feriados, JSON_UNESCAPED_UNICODE); ?>;
    const dateInput = document.getElementById('cita_fecha');
    const timeSelect = document.getElementById('cita_hora');
    const help = document.getElementById('cita_help');
    const btn = document.getElementById('btnReservaQR');
    const sizeSelect = document.getElementById('reserva_talla');
    const form = document.getElementById('reservaForm');

    if(!dateInput || !timeSelect || !help || !btn || !form){
        return;
    }

    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth()+1).padStart(2,'0');
    const d = String(today.getDate()).padStart(2,'0');
    dateInput.min = `${y}-${m}-${d}`;

    const isSunday = (ymd) => {
        const dt = new Date(ymd + 'T00:00:00');
        return dt.getDay() === 0;
    };
    const isHoliday = (ymd) => Array.isArray(feriados) && feriados.includes(ymd);

    const resetTimes = (msg) => {
        timeSelect.innerHTML = `<option value="">${msg}</option>`;
        timeSelect.disabled = true;
        btn.disabled = true;
    };

    const loadTimes = async () => {
        const fecha = dateInput.value;
        if(!fecha){
            help.textContent = 'Horario: 10:00 am a 07:00 pm';
            return resetTimes('Selecciona una fecha primero');
        }

        if(isSunday(fecha)){
            help.textContent = 'Los domingos no están disponibles';
            return resetTimes('Fecha no disponible');
        }

        if(isHoliday(fecha)){
            help.textContent = 'Feriado: no disponible';
            return resetTimes('Fecha no disponible');
        }

        help.textContent = 'Cargando horarios disponibles...';
        timeSelect.innerHTML = '<option value="">Cargando...</option>';
        timeSelect.disabled = true;
        btn.disabled = true;

        try{
            const fd = new FormData();
            fd.append('modulo_reserva','horarios');
            fd.append('cita_fecha', fecha);

            const resp = await fetch('<?php echo APP_URL; ?>app/ajax/reservaAjax.php', {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();
            if(!json || json.ok !== true){
                help.textContent = (json && json.mensaje) ? json.mensaje : 'No se pudieron cargar horarios';
                return resetTimes('Sin horarios');
            }

            const available = Array.isArray(json.available) ? json.available : [];
            if(available.length === 0){
                help.textContent = 'No hay horarios disponibles para esta fecha';
                return resetTimes('Sin horarios disponibles');
            }

            timeSelect.innerHTML = '<option value="">Selecciona una hora</option>' +
                available.map(h => `<option value="${h}">${h}</option>`).join('');
            timeSelect.disabled = false;
            help.textContent = 'Horario: 10:00 am a 07:00 pm';

        }catch(e){
            help.textContent = 'No se pudieron cargar horarios';
            resetTimes('Sin horarios');
        }
    };

    dateInput.addEventListener('change', loadTimes);
    const canSubmit = () => {
        const hasSize = (!sizeSelect) ? true : !!sizeSelect.value;
        return !!(dateInput.value && timeSelect.value && hasSize);
    };

    timeSelect.addEventListener('change', () => {
        btn.disabled = !canSubmit();
    });

    if(sizeSelect){
        sizeSelect.addEventListener('change', () => {
            btn.disabled = !canSubmit();
        });
    }

    const readPending = () => {
        try{
            const raw = sessionStorage.getItem(storageKey);
            if(!raw) return null;
            const parsed = JSON.parse(raw);
            if(!parsed || typeof parsed !== 'object') return null;
            if(Number(parsed.productoId || 0) !== Number(productoId)) return null;
            return parsed;
        }catch(e){
            return null;
        }
    };
    const writePending = (payload) => {
        try{ sessionStorage.setItem(storageKey, JSON.stringify(payload)); }catch(e){}
    };
    const clearPending = () => {
        try{ sessionStorage.removeItem(storageKey); }catch(e){}
    };

    // 1) Si no está logueado: permitir elegir fecha/hora, pero al SUBMIT pedir login.
    form.addEventListener('submit', function(e){
        if(clienteLogueado){
            clearPending();
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();

        if(!canSubmit()){
            Swal.fire({
                icon: 'info',
                title: 'Completa tu cita',
                text: 'Selecciona la talla (si aplica), fecha y hora antes de continuar.'
            });
            return;
        }

        writePending({
            v: 1,
            productoId: productoId,
            reserva_talla: sizeSelect ? String(sizeSelect.value || '') : '',
            cita_fecha: String(dateInput.value || ''),
            cita_hora: String(timeSelect.value || ''),
            createdAt: Date.now()
        });

        if(window.BoutiqueClienteAuthModalOpen){
            window.BoutiqueClienteAuthModalOpen('login', redirectTo, {
                title: 'Inicia sesión para confirmar',
                subtitle: 'Ya elegiste tu cita. Solo falta iniciar sesión para generar el QR de reserva.'
            });
        }else{
            window.location.href = <?php echo json_encode(APP_URL.'clienteLogin/?redirect_to=', JSON_UNESCAPED_SLASHES); ?> + encodeURIComponent(redirectTo);
        }
    }, false);

    // 2) Si volvió logueado y hay datos pendientes: restaurar y auto-enviar.
    (async function resumeIfNeeded(){
        if(!clienteLogueado) return;
        const pending = readPending();
        if(!pending) return;

        // Limpiar si está muy viejo (2h)
        if(pending.createdAt && (Date.now() - Number(pending.createdAt)) > (2*60*60*1000)){
            clearPending();
            return;
        }

        if(sizeSelect && pending.reserva_talla){
            sizeSelect.value = String(pending.reserva_talla);
        }
        if(pending.cita_fecha){
            dateInput.value = String(pending.cita_fecha);
            await loadTimes();
        }
        if(pending.cita_hora){
            timeSelect.value = String(pending.cita_hora);
        }
        btn.disabled = !canSubmit();

        // Si todo ok, enviar automáticamente
        if(canSubmit()){
            clearPending();
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    })();

})();
</script>
