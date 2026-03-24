<?php
	// Vista pública (cliente): selección de telas con preview 3D (placeholder)
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title has-text-centered">Selecciona el tipo de tela</h1>
	<p class="has-text-centered mb-5">
		<?php if($clienteLogueado){ ?>
			<?php echo htmlspecialchars($_SESSION['cliente_nombre']." ".($_SESSION['cliente_apellido'] ?? '')); ?>, elige una tela para ver su precio y previsualización.
		<?php }else{ ?>
			Elige una tela para ver su precio y previsualización.
		<?php } ?>
	</p>

	<div class="columns is-variable is-5">
		<div class="column is-7">
			<div class="box">
				<h2 class="subtitle"><i class="fas fa-cube"></i> &nbsp; Vestido en 3D</h2>
				<div id="dress3dContainer" style="width:100%; min-height:420px;">
					<div class="notification is-light">
						Este es el espacio reservado para el visor 3D del vestido (modelo + material).<br>
						Aquí se aplicará la tela seleccionada al modelo 3D.
					</div>
					<canvas id="dress3dCanvas" style="width:100%; height:360px; display:block;"></canvas>
				</div>
			</div>
		</div>

		<div class="column is-5">
			<div class="box">
				<h2 class="subtitle"><i class="fas fa-layer-group"></i> &nbsp; Tipos de tela</h2>

				<div id="telasEstado" class="notification is-info is-light" style="display:none;"></div>
				<div id="telasList"></div>

				<hr>
				<h3 class="subtitle is-6">Previsualización 3D de la tela</h3>
				<canvas id="fabricPreviewCanvas" style="width:100%; height:240px; display:block;"></canvas>

				<p class="mt-4">
					<strong>Precio con esta tela:</strong>
					<span id="telaPrecioTexto">—</span>
				</p>
			</div>
		</div>
	</div>
</div>

<script>
	window.APP_URL = "<?php echo APP_URL; ?>";
	window.MONEDA_SIMBOLO = "<?php echo MONEDA_SIMBOLO; ?>";
</script>

<script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/js/telasCliente.js"></script>
