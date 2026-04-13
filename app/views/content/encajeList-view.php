<div class="container is-fluid mb-6">
	<h1 class="title">Encajes</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Lista de encajes</h2>
	<p class="has-text-right mt-3">
		<a class="button is-info is-rounded" href="<?php echo APP_URL; ?>encajeNew/">
			<i class="fas fa-plus"></i> &nbsp; Nuevo encaje
		</a>
	</p>
</div>

<div class="container pb-6 pt-6">
	<?php
		use app\controllers\encajeController;
		$insEncaje = new encajeController();
		echo $insEncaje->listarEncajesAdminControlador();
	?>
</div>
