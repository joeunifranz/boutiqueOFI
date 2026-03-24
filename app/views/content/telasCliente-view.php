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
				<div class="is-flex is-justify-content-space-between is-align-items-center">
					<h3 class="subtitle is-6" style="margin-bottom:0;">Previsualización 3D de la tela</h3>
					<button
						id="openFabricPreviewModal"
						type="button"
						class="button is-link is-light is-rounded is-small js-modal-trigger"
						data-target="modalFabricPreview"
					>
						Ver grande
					</button>
				</div>
				<div class="fabric-preview-wrap mt-3">
					<canvas id="fabricPreviewCanvas" style="width:100%; display:block;"></canvas>
				</div>

				<p class="mt-4">
					<strong>Precio con esta tela:</strong>
					<span id="telaPrecioTexto">—</span>
				</p>
			</div>
		</div>
	</div>
</div>

<!-- Modal: Previsualización 3D grande -->
<div id="modalFabricPreview" class="modal">
	<div class="modal-background"></div>
	<div class="modal-card" style="width: min(92vw, 980px);">
		<header class="modal-card-head">
			<p class="modal-card-title">Previsualización 3D de la tela</p>
			<button class="delete" aria-label="close"></button>
		</header>
		<section class="modal-card-body">
			<canvas id="fabricPreviewCanvasModal" class="fabric-preview-canvas-modal"></canvas>
		</section>
		<footer class="modal-card-foot" style="justify-content:flex-end;">
			<button class="button is-link is-light is-rounded">Cerrar</button>
		</footer>
	</div>
</div>

<script>
	window.APP_URL = "<?php echo APP_URL; ?>";
	window.MONEDA_SIMBOLO = "<?php echo MONEDA_SIMBOLO; ?>";
</script>

<script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/js/telasCliente.js"></script>
