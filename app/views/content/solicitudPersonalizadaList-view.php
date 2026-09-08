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
	echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede ver las solicitudes personalizadas.</div></article></div>";
	return;
}

$busqueda = isset($_GET['q']) ? (string)$_GET['q'] : '';
$estado = isset($_GET['estado']) ? (string)$_GET['estado'] : '';

?>

<div class="container is-fluid mb-6">
	<h1 class="title">Reservas</h1>
	<h2 class="subtitle"><i class="fas fa-star fa-fw"></i> &nbsp; Solicitudes personalizadas</h2>
	<p class="has-text-grey">Aquí verás las solicitudes enviadas desde <strong>telasCliente</strong> (vestido + tela + encaje + cita).</p>
</div>

<div class="container pb-6 pt-6">
	<div class="form-rest mb-6 mt-6"></div>

	<div class="box">
		<form method="GET" action="<?php echo APP_URL; ?>solicitudPersonalizadaList/">
			<div class="columns is-multiline is-vcentered">
				<div class="column is-6">
					<div class="field">
						<label class="label">Buscar</label>
						<div class="control">
							<input class="input" type="text" name="q" value="<?php echo htmlspecialchars($busqueda,ENT_QUOTES,'UTF-8'); ?>" placeholder="ID, nombre o email del cliente">
						</div>
					</div>
				</div>
				<div class="column is-3">
					<div class="field">
						<label class="label">Estado</label>
						<div class="control">
							<div class="select is-fullwidth">
								<select name="estado">
									<option value="" <?php echo ($estado==='' ? 'selected' : ''); ?>>(Todos)</option>
									<option value="pendiente" <?php echo ($estado==='pendiente' ? 'selected' : ''); ?>>pendiente</option>
									<option value="aprobada" <?php echo ($estado==='aprobada' ? 'selected' : ''); ?>>aprobada</option>
									<option value="rechazada" <?php echo ($estado==='rechazada' ? 'selected' : ''); ?>>rechazada</option>
									<option value="cancelada" <?php echo ($estado==='cancelada' ? 'selected' : ''); ?>>cancelada</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="column is-3 has-text-right">
					<label class="label">&nbsp;</label>
					<div class="buttons is-right">
						<button class="button is-link" type="submit"><i class="fas fa-search"></i> &nbsp; Buscar</button>
						<a class="button is-light" href="<?php echo APP_URL; ?>solicitudPersonalizadaList/"><i class="fas fa-eraser"></i> &nbsp; Limpiar</a>
					</div>
				</div>
			</div>
		</form>
	</div>

	<?php
		use app\controllers\reservationController;
		$insReserva = new reservationController();
		echo $insReserva->listarSolicitudesPersonalizadasAdminControlador($busqueda, $estado);
	?>
</div>

<div id="modalAgendarSolicitud" class="modal">
	<div class="modal-background"></div>
	<div class="modal-card">
		<header class="modal-card-head">
			<p class="modal-card-title">Agendar entrega de personalización</p>
			<button type="button" class="delete js-cerrar-agendar" aria-label="Cerrar"></button>
		</header>
		<section class="modal-card-body">
			<div id="agendarSolicitudMensaje" class="notification is-light" style="display:none;"></div>
			<input type="hidden" id="agendarSolicitudId" value="">
			<div class="field">
				<label class="label" for="agendarSolicitudFecha">Fecha</label>
				<div class="control"><input id="agendarSolicitudFecha" class="input" type="date" min="<?php echo date('Y-m-d'); ?>"></div>
			</div>
			<div class="field">
				<label class="label" for="agendarSolicitudHora">Horario disponible</label>
				<div class="control"><div class="select is-fullwidth"><select id="agendarSolicitudHora"><option value="">Selecciona una fecha</option></select></div></div>
			</div>
		</section>
		<footer class="modal-card-foot">
			<button type="button" class="button is-link" id="btnGuardarAgendarSolicitud"><span class="icon"><i class="fas fa-calendar-check"></i></span><span>Guardar agenda</span></button>
			<button type="button" class="button js-cerrar-agendar">Cancelar</button>
		</footer>
	</div>
</div>

<script>
(function(){
	const modal = document.getElementById('modalAgendarSolicitud');
	const idInput = document.getElementById('agendarSolicitudId');
	const fechaInput = document.getElementById('agendarSolicitudFecha');
	const horaSelect = document.getElementById('agendarSolicitudHora');
	const mensaje = document.getElementById('agendarSolicitudMensaje');
	const ajaxUrl = '<?php echo APP_URL; ?>app/ajax/reservaAjax.php';
	if(!modal || !fechaInput || !horaSelect) return;

	const showMessage = (text, type) => {
		mensaje.textContent = text;
		mensaje.className = 'notification is-' + (type || 'light');
		mensaje.style.display = text ? '' : 'none';
	};
	const loadHours = async () => {
		horaSelect.innerHTML = '<option value="">Cargando horarios...</option>';
		if(!fechaInput.value){
			horaSelect.innerHTML = '<option value="">Selecciona una fecha</option>';
			return;
		}
		const fd = new FormData();
		fd.append('modulo_reserva', 'horarios');
		fd.append('cita_fecha', fechaInput.value);
		try{
			const response = await fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'});
			const data = await response.json();
			if(!data || data.ok !== true){ throw new Error((data && data.mensaje) || 'No se pudieron cargar los horarios'); }
			horaSelect.innerHTML = '<option value="">Selecciona un horario</option>';
			(data.available || []).forEach((hora) => {
				const option = document.createElement('option');
				option.value = hora;
				option.textContent = hora;
				horaSelect.appendChild(option);
			});
			if(!(data.available || []).length) showMessage('No hay horarios disponibles para esa fecha.', 'warning');
		}catch(error){
			horaSelect.innerHTML = '<option value="">No disponible</option>';
			showMessage(error.message || 'No se pudieron cargar los horarios.', 'danger');
		}
	};
	const openModal = (button) => {
		idInput.value = button.dataset.solicitudId || '';
		fechaInput.value = button.dataset.fecha || '';
		showMessage('', 'light');
		modal.classList.add('is-active');
		loadHours().then(() => {
			if(button.dataset.hora){ horaSelect.value = button.dataset.hora; }
		});
	};
	document.addEventListener('click', (event) => {
		const scheduleButton = event.target.closest('.js-agendar-solicitud');
		if(scheduleButton){ openModal(scheduleButton); return; }
		const rejectButton = event.target.closest('.js-rechazar-solicitud');
		if(rejectButton){
			if(!window.confirm('¿Deseas rechazar esta solicitud personalizada?')) return;
			const fd = new FormData();
			fd.append('modulo_reserva', 'personalizada_actualizar_admin');
			fd.append('solicitud_id', rejectButton.dataset.solicitudId || '');
			fd.append('accion', 'rechazar');
			fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'}).then(r => r.json()).then(data => {
				if(!data || data.ok !== true) throw new Error((data && data.mensaje) || 'No se pudo rechazar');
				window.location.reload();
			}).catch(error => window.alert(error.message || 'No se pudo rechazar la solicitud.'));
			return;
		}
		if(event.target.closest('.js-cerrar-agendar') || event.target.classList.contains('modal-background')) modal.classList.remove('is-active');
	});
	fechaInput.addEventListener('change', loadHours);
	document.getElementById('btnGuardarAgendarSolicitud').addEventListener('click', async () => {
		const fd = new FormData();
		fd.append('modulo_reserva', 'personalizada_actualizar_admin');
		fd.append('solicitud_id', idInput.value);
		fd.append('accion', 'agendar');
		fd.append('cita_fecha', fechaInput.value);
		fd.append('cita_hora', horaSelect.value);
		try{
			const response = await fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'});
			const data = await response.json();
			if(!data || data.ok !== true) throw new Error((data && data.mensaje) || 'No se pudo agendar');
			window.location.reload();
		}catch(error){ showMessage(error.message || 'No se pudo agendar la entrega.', 'danger'); }
	});
})();
</script>
