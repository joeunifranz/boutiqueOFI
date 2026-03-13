<div class="container is-fluid mb-6">
	<h1 class="title">Productos</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Lista de productos</h2>
	<p class="has-text-right mt-3">
		<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>exportarProductos/">
			<i class="fas fa-file-pdf"></i> &nbsp; Exportar PDF
		</a>
	</p>
</div>
<div class="container pb-6 pt-6">

	<div class="form-rest mb-6 mt-6"></div>

	<?php
		use app\controllers\productController;

		$insProducto = new productController();

		echo $insProducto->listarProductoControlador($url[1],10,$url[0],"",0);
	?>
</div>