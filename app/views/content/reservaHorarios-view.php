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
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede configurar horarios.</div></article></div>";
    return;
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

<div class="container is-fluid mb-6">
    <h1 class="title">Reservas</h1>
    <h2 class="subtitle"><i class="fas fa-clock fa-fw"></i> &nbsp; Horarios disponibles</h2>
</div>

<div class="container pb-6 pt-6">
    <div class="columns">
        <div class="column is-5">
            <div class="box">
                <div class="field">
                    <label class="label">Fecha</label>
                    <div class="control">
                        <input id="admin_cita_fecha" class="input" type="date" required>
                    </div>
                    <p class="help">No se permiten domingos ni feriados.</p>
                </div>

                <div id="admin_msg" class="notification is-light" style="display:none;"></div>

                <form id="formAdminHorarios" class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/reservaAjax.php" method="POST" autocomplete="off">
                    <input type="hidden" name="modulo_reserva" value="horarios_guardar_admin">
                    <input type="hidden" name="cita_fecha" id="admin_cita_fecha_hidden" value="">
                    <div id="admin_hidden_bloqueadas"></div>

                    <div class="field mt-4">
                        <button id="btnGuardarHorarios" type="submit" class="button is-link is-fullwidth" disabled>
                            Guardar horarios
                        </button>
                    </div>
                    <p class="help">Desmarca horarios para bloquearlos.</p>
                </form>
            </div>
        </div>

        <div class="column is-7">
            <div class="box">
                <h3 class="title is-6">Horarios del día</h3>
                <div id="admin_slots" class="columns is-multiline"></div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const feriados = <?php echo json_encode($feriados, JSON_UNESCAPED_UNICODE); ?>;

    const dateInput = document.getElementById('admin_cita_fecha');
    const dateHidden = document.getElementById('admin_cita_fecha_hidden');
    const msg = document.getElementById('admin_msg');
    const slots = document.getElementById('admin_slots');
    const hiddenBloq = document.getElementById('admin_hidden_bloqueadas');
    const btnSave = document.getElementById('btnGuardarHorarios');

    if(!dateInput || !dateHidden || !msg || !slots || !hiddenBloq || !btnSave){
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

    const showMsg = (text, type='is-light') => {
        msg.className = `notification ${type}`;
        msg.textContent = text;
        msg.style.display = 'block';
    };

    const clearMsg = () => {
        msg.style.display = 'none';
        msg.textContent = '';
    };

    const clearSlots = () => {
        slots.innerHTML = '';
        hiddenBloq.innerHTML = '';
        btnSave.disabled = true;
    };

    const updateHiddenBloqueadas = () => {
        hiddenBloq.innerHTML = '';
        const fecha = dateInput.value;
        if(!fecha){
            return;
        }
        const inputs = [];
        const checks = slots.querySelectorAll('input.slotAvailable');
        checks.forEach(chk => {
            if(chk.disabled){
                return;
            }
            if(!chk.checked){
                const h = chk.getAttribute('data-hora') || '';
                if(h){
                    inputs.push(h);
                }
            }
        });
        inputs.forEach(h => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'bloqueadas[]';
            inp.value = h;
            hiddenBloq.appendChild(inp);
        });
    };

    const renderSlots = (permitidos, taken, blocked) => {
        slots.innerHTML = '';
        const takenSet = new Set(Array.isArray(taken) ? taken : []);
        const blockedSet = new Set(Array.isArray(blocked) ? blocked : []);

        (Array.isArray(permitidos) ? permitidos : []).forEach(h => {
            const col = document.createElement('div');
            col.className = 'column is-4';

            const box = document.createElement('div');
            box.className = 'field';

            const label = document.createElement('label');
            label.className = 'checkbox';

            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.className = 'slotAvailable';
            chk.setAttribute('data-hora', h);

            const ocupado = takenSet.has(h);
            if(ocupado){
                chk.checked = true;
                chk.disabled = true;
            }else{
                chk.checked = !blockedSet.has(h);
            }

            chk.addEventListener('change', () => {
                updateHiddenBloqueadas();
            });

            label.appendChild(chk);
            label.appendChild(document.createTextNode(' ' + h));

            const tag = document.createElement('span');
            tag.className = ocupado ? 'tag is-warning ml-2' : (chk.checked ? 'tag is-success ml-2' : 'tag is-dark ml-2');
            tag.textContent = ocupado ? 'Ocupado' : (chk.checked ? 'Disponible' : 'Bloqueado');

            chk.addEventListener('change', () => {
                if(chk.disabled){
                    return;
                }
                tag.className = chk.checked ? 'tag is-success ml-2' : 'tag is-dark ml-2';
                tag.textContent = chk.checked ? 'Disponible' : 'Bloqueado';
            });

            box.appendChild(label);
            box.appendChild(tag);
            col.appendChild(box);
            slots.appendChild(col);
        });

        updateHiddenBloqueadas();
        btnSave.disabled = false;
    };

    const loadInfo = async () => {
        clearMsg();
        clearSlots();

        const fecha = dateInput.value;
        dateHidden.value = fecha;

        if(!fecha){
            return;
        }

        if(isSunday(fecha)){
            showMsg('Domingo: no disponible', 'is-warning');
            return;
        }

        if(isHoliday(fecha)){
            showMsg('Feriado: no disponible', 'is-warning');
            return;
        }

        showMsg('Cargando horarios...', 'is-light');

        try{
            const fd = new FormData();
            fd.append('modulo_reserva','horarios_info_admin');
            fd.append('cita_fecha', fecha);

            const resp = await fetch('<?php echo APP_URL; ?>app/ajax/reservaAjax.php', {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();
            if(!json || json.ok !== true){
                showMsg((json && json.mensaje) ? json.mensaje : 'No se pudo cargar', 'is-danger');
                return;
            }

            showMsg('Desmarca horarios para bloquearlos. Los ocupados no se pueden editar.', 'is-info');
            renderSlots(json.permitidos, json.taken, json.blocked);

        }catch(e){
            showMsg('No se pudo cargar', 'is-danger');
        }
    };

    dateInput.addEventListener('change', loadInfo);

})();
</script>
