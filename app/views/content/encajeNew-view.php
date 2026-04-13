<div class="container is-fluid mb-6">
	<h1 class="title">Encajes</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Nuevo encaje</h2>
</div>

<div class="container pb-6 pt-6">

	<div class="form-rest mb-6 mt-6"></div>

	<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/encajeAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >

		<input type="hidden" name="modulo_encaje" value="registrar">

		<div class="columns">
			<div class="column">
				<div class="control">
					<label>Nombre <?php echo CAMPO_OBLIGATORIO; ?></label>
					<input class="input" type="text" name="encaje_nombre" maxlength="140" required>
				</div>
			</div>
			<div class="column">
				<div class="control">
					<label>Precio (por 1.5 m) <?php echo CAMPO_OBLIGATORIO; ?></label>
					<input class="input" type="number" name="encaje_precio" min="0" step="0.01" required>
				</div>
			</div>
		</div>

		<div class="columns">
			<div class="column">
				<label>Foto (opcional)</label>
				<div class="file has-name is-boxed">
					<label class="file-label">
						<input class="file-input" type="file" name="encaje_imagen" accept=".jpg, .jpeg, .png, .webp" >
						<span class="file-cta">
							<span class="file-label">Seleccione una foto</span>
						</span>
						<span class="file-name">JPG, JPEG, PNG, WEBP</span>
					</label>
				</div>
				<p class="help">Se guardará en <strong>app/views/fotos/encajes/</strong></p>
			</div>
			<div class="column">
				<label>Activo</label>
				<div class="select is-fullwidth">
					<select name="encaje_activo">
						<option value="1" selected>Si</option>
						<option value="0">No</option>
					</select>
				</div>
			</div>
		</div>

		<p class="has-text-centered">
			<button type="reset" class="button is-link is-light is-rounded"><i class="fas fa-paint-roller"></i> &nbsp; Limpiar</button>
			<button type="submit" class="button is-info is-rounded"><i class="far fa-save"></i> &nbsp; Guardar</button>
		</p>

		<p class="has-text-centered mt-4">
			<a class="button is-light is-rounded" href="<?php echo APP_URL; ?>encajeList/"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Ver lista</a>
		</p>

		<p class="has-text-centered pt-6">
			<small>Los campos marcados con <?php echo CAMPO_OBLIGATORIO; ?> son obligatorios</small>
		</p>
	</form>
</div>
