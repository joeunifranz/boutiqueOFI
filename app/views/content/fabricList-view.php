<div class="container is-fluid mb-6">
	<h1 class="title">Telas</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Inventario de telas</h2>
	<p class="has-text-right mt-3">
		<a class="button is-info is-light is-rounded" href="<?php echo APP_URL; ?>fabricNew/">
			<i class="far fa-plus-square"></i> &nbsp; Nueva tela
		</a>
	</p>
</div>

<div class="container pb-6 pt-6">
	<?php
		use app\controllers\fabricController;
		$insTela = new fabricController();
		echo $insTela->listarTelasAdminControlador($url[1],15,$url[0],"");
	?>
</div>
