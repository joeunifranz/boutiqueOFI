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
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede ver la lista de reservas.</div></article></div>";
    return;
}

?>

<div class="container is-fluid mb-6">
	<h1 class="title">Reservas</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Lista de reservas</h2>
	<p class="has-text-right mt-3">
		<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>exportarReservas/">
			<i class="fas fa-file-pdf"></i> &nbsp; Exportar PDF
		</a>
	</p>
</div>

<div class="container pb-6 pt-6">
	<div class="form-rest mb-6 mt-6"></div>

	<?php
		$busqueda = isset($_GET['q']) ? (string)$_GET['q'] : '';
		$estado = isset($_GET['estado']) ? (string)$_GET['estado'] : '';
	?>

	<div class="box">
		<form method="GET" action="<?php echo APP_URL; ?>reservaList/">
			<div class="columns is-multiline is-vcentered">
				<div class="column is-6">
					<div class="field">
						<label class="label">Buscar</label>
						<div class="control">
							<input class="input" type="text" name="q" value="<?php echo htmlspecialchars($busqueda,ENT_QUOTES,'UTF-8'); ?>" placeholder="Código de reserva o nombre/apellido/email del cliente">
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
									<option value="confirmada" <?php echo ($estado==='confirmada' ? 'selected' : ''); ?>>confirmada</option>
									<option value="reprogramada" <?php echo ($estado==='reprogramada' ? 'selected' : ''); ?>>reprogramada</option>
									<option value="completada" <?php echo ($estado==='completada' ? 'selected' : ''); ?>>completada</option>
									<option value="rechazada" <?php echo ($estado==='rechazada' ? 'selected' : ''); ?>>rechazada</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="column is-3 has-text-right">
					<label class="label">&nbsp;</label>
					<div class="buttons is-right">
						<button class="button is-link" type="submit"><i class="fas fa-search"></i> &nbsp; Buscar</button>
						<a class="button is-light" href="<?php echo APP_URL; ?>reservaList/"><i class="fas fa-eraser"></i> &nbsp; Limpiar</a>
					</div>
				</div>
			</div>
		</form>
	</div>

	<?php
		use app\controllers\reservationController;
		$insReserva = new reservationController();
		echo $insReserva->listarReservaControlador($url[1] ?? 1, 15, $url[0] ?? 'reservaList', $busqueda, $estado);
	?>
</div>

<?php include "./app/views/inc/print_invoice_script.php"; ?>
