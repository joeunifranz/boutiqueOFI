<?php
use app\controllers\productController;

$id = 0;
if(isset($url[1]) && $url[1]!==""){
    $id = (int)$url[1];
}elseif(isset($_GET['id'])){
    $id = (int)$_GET['id'];
}

if(!isset($_SESSION['cliente_id']) || $_SESSION['cliente_id']===""){
    $redirect_to = ($id>0) ? "reservaNueva/".$id."/" : "productosCliente/";
    echo "<div class='container py-6'>";
    echo "  <div class='columns is-centered'>";
    echo "    <div class='column is-7 is-6-desktop'>";
    echo "      <div class='box reserva-auth-box has-text-centered'>";
    echo "        <p class='title is-5 mb-2'>Para reservar necesitas una cuenta</p>";
    echo "        <p class='has-text-grey mb-5'>Regístrate o inicia sesión para continuar con tu reserva.</p>";
    echo "        <div class='buttons is-centered are-medium reserva-auth-actions'>";
    echo "          <a class='button is-info is-rounded is-fullwidth-mobile' href='".APP_URL."registroCliente/?redirect_to=".urlencode($redirect_to)."'>Registrarme</a>";
    echo "          <a class='button is-link is-light is-rounded is-fullwidth-mobile' href='".APP_URL."clienteLogin/?redirect_to=".urlencode($redirect_to)."'>Iniciar sesión</a>";
    echo "        </div>";
    echo "      </div>";
    echo "    </div>";
    echo "  </div>";
    echo "</div>";
	echo "<style>\n";
	echo ".reserva-auth-box{ border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 18px 45px rgba(0,0,0,0.10); }\n";
	echo ".reserva-auth-actions .button{ min-width: 220px; }\n";
	echo "@media (max-width: 768px){ .is-fullwidth-mobile{ width: 100%; } }\n";
	echo "</style>";
    return;
}

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

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
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

            <form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off">
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

<style>
.detalle-img{
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}
.detalle-img:hover{
    transform: scale(1.03);
}
</style>

<script>
(() => {
    const feriados = <?php echo json_encode($feriados, JSON_UNESCAPED_UNICODE); ?>;
    const dateInput = document.getElementById('cita_fecha');
    const timeSelect = document.getElementById('cita_hora');
    const help = document.getElementById('cita_help');
    const btn = document.getElementById('btnReservaQR');
    const sizeSelect = document.getElementById('reserva_talla');

    if(!dateInput || !timeSelect || !help || !btn){
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

})();
</script>
