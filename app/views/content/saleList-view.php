<div class="container is-fluid mb-6">
	<h1 class="title">Ventas</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Lista de Ventas</h2>
	<p class="has-text-right mt-3">
		<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>exportarVentas/">
			<i class="fas fa-file-pdf"></i> &nbsp; Exportar PDF
		</a>
	</p>
</div>
<div class="container pb-6 pt-6">

	<div class="form-rest mb-6 mt-6"></div>

	<?php
		use app\controllers\saleController;

		$insVenta = new saleController();

		echo $insVenta->listarVentaControlador($url[1],15,$url[0],"");

		include "./app/views/inc/print_invoice_script.php";
	?>
</div>