<?php
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
	$modelPath = defined('TABLA_TALLAS_MODEL_PATH') ? (string)TABLA_TALLAS_MODEL_PATH : '';
	$modelUrl = ($modelPath !== '' && defined('APP_URL')) ? ((string)APP_URL.$modelPath) : '';

	$localModelFile = ($modelPath !== '') ? ("./".$modelPath) : '';
	$modelExists = ($localModelFile !== '' && is_file($localModelFile));
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title has-text-centered">Tabla de tallas</h1>
	<p class="has-text-centered mb-5">Gira el maniquí en 3D para ver la referencia de tallas.</p>

	<?php if(!$modelExists){ ?>
		<article class="message is-warning">
			<div class="message-body">
				No se encontró el modelo 3D del maniquí.<br>
				Coloca tu archivo en <strong><?php echo htmlspecialchars($modelPath, ENT_QUOTES, 'UTF-8'); ?></strong> (por ejemplo <strong>maniqui.glb</strong>) y recarga.
			</div>
		</article>
	<?php } ?>

	<div class="box">
		<div class="tabla-tallas-3d" style="height:min(70vh, 620px);">
			<model-viewer
				<?php echo ($modelExists && $modelUrl !== '') ? ('src="'.htmlspecialchars($modelUrl, ENT_QUOTES, 'UTF-8').'"') : ''; ?>
				alt="Maniquí 3D - Tabla de tallas"
				auto-rotate
				camera-controls
				touch-action="pan-y"
				style="width:100%; height:100%; background: #f9fafb; border-radius: 10px;"
			></model-viewer>
		</div>
	</div>

	<hr>
	<div class="buttons is-centered">
		<a class="button is-light is-rounded" href="<?php echo APP_URL; ?>inicio/">Volver al inicio</a>
		<a class="button is-link is-rounded" href="<?php echo APP_URL; ?>productosCliente/">Ver vestidos</a>
	</div>
</div>

<script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
