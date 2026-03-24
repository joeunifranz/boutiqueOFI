<div class="container is-fluid mb-6">
	<h1 class="title">Telas</h1>
	<h2 class="subtitle"><i class="fas fa-tag fa-fw"></i> &nbsp; Nueva tela</h2>
</div>

<div class="container pb-6 pt-6">
	<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/telaAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
		<input type="hidden" name="modulo_tela" value="registrar">

		<div class="columns">
			<div class="column">
				<div class="control">
					<label>Nombre <?php echo CAMPO_OBLIGATORIO; ?></label>
					<input class="input" type="text" name="tela_nombre" pattern="[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ \-]{2,80}" maxlength="80" required>
				</div>
			</div>
		</div>

		<div class="columns">
			<div class="column">
				<div class="control">
					<label>Descripción</label>
					<input class="input" type="text" name="tela_descripcion" maxlength="255">
				</div>
			</div>
		</div>

		<div class="columns">
			<div class="column">
				<div class="control">
					<label>Precio (por tipo de tela) <?php echo CAMPO_OBLIGATORIO; ?></label>
					<input class="input" type="number" step="0.01" min="0" name="tela_precio" required>
				</div>
			</div>
			<div class="column">
				<div class="control">
					<label>Stock <?php echo CAMPO_OBLIGATORIO; ?></label>
					<input class="input" type="number" step="1" min="0" name="tela_stock" required>
				</div>
			</div>
		</div>

		<div class="columns">
			<div class="column">
				<div class="control">
					<label>Subir textura (opcional)</label>
					<input class="input" type="file" name="tela_textura_file" accept="image/png, image/jpeg, image/webp">
					<p class="help">Si la dejas vacía, el preview 3D usará una textura procedimental.</p>
				</div>
			</div>
			<div class="column">
				<div class="control">
					<label>Activo</label>
					<div class="select is-fullwidth">
						<select name="tela_activo">
							<option value="1" selected>Sí</option>
							<option value="0">No</option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<p class="has-text-centered">
			<button type="reset" class="button is-link is-light is-rounded"><i class="fas fa-paint-roller"></i> &nbsp; Limpiar</button>
			<button type="submit" class="button is-info is-rounded"><i class="far fa-save"></i> &nbsp; Guardar</button>
		</p>
		<p class="has-text-centered pt-6">
			<small>Los campos marcados con <?php echo CAMPO_OBLIGATORIO; ?> son obligatorios</small>
		</p>
	</form>
</div>
