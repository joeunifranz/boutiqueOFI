<?php
	use app\controllers\encajeController;
	$insEncaje = new encajeController();
	$encajeId = isset($url[1]) ? (int)$url[1] : 0;
	$encaje = $insEncaje->obtenerEncajePorIdControlador($encajeId);
?>

<div class="container is-fluid mb-6">
	<h1 class="title">Encajes</h1>
	<h2 class="subtitle"><i class="fas fa-sync fa-fw"></i> &nbsp; Actualizar encaje</h2>
</div>

<div class="container pb-6 pt-6">
	<?php if(!$encaje){ ?>
		<div class="notification is-danger">
			No se encontró el encaje solicitado.
		</div>
	<?php }else{ ?>
		<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/encajeAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
			<input type="hidden" name="modulo_encaje" value="actualizar">
			<input type="hidden" name="encaje_id" value="<?php echo (int)$encaje['encaje_id']; ?>">

			<div class="columns">
				<div class="column">
					<div class="control">
						<label>Nombre <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="encaje_nombre" maxlength="140" required value="<?php echo htmlspecialchars((string)($encaje['encaje_nombre'] ?? '')); ?>">
					</div>
				</div>
				<div class="column">
					<div class="control">
						<label>Precio (por 1.5 m) <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="number" name="encaje_precio" min="0" step="0.01" required value="<?php echo htmlspecialchars((string)($encaje['encaje_precio'] ?? '0')); ?>">
					</div>
				</div>
			</div>

			<div class="columns">
				<div class="column">
					<label>Foto actual</label>
					<div>
						<?php
							$img = (string)($encaje['encaje_imagen'] ?? '');
							if($img !== '' && is_file('./'.$img)){
								echo '<figure class="image is-128x128"><img style="object-fit:cover; width:128px; height:128px; border-radius:10px;" src="'.APP_URL.htmlspecialchars($img,ENT_QUOTES,'UTF-8').'" alt=""></figure>';
							}else{
								echo '<span class="tag is-light">Sin foto</span>';
							}
						?>
					</div>
					<p class="help">Si subes una nueva imagen, reemplazará la actual.</p>
				</div>
				<div class="column">
					<div class="control">
						<label>Subir nueva foto (opcional)</label>
						<input class="input" type="file" name="encaje_imagen" accept=".jpg, .jpeg, .png, .webp">
					</div>
					<p class="help">Se guardará en <strong>app/views/fotos/encajes/</strong></p>
				</div>
			</div>

			<div class="columns">
				<div class="column">
					<label>Activo</label>
					<div class="select is-fullwidth">
						<select name="encaje_activo">
							<option value="1" <?php echo ((int)($encaje['encaje_activo'] ?? 1)===1) ? 'selected' : ''; ?>>Si</option>
							<option value="0" <?php echo ((int)($encaje['encaje_activo'] ?? 1)===0) ? 'selected' : ''; ?>>No</option>
						</select>
					</div>
				</div>
			</div>

			<p class="has-text-centered">
				<button type="submit" class="button is-success is-rounded"><i class="fas fa-sync"></i> &nbsp; Actualizar</button>
				<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>encajeList/"><i class="fas fa-arrow-left"></i> &nbsp; Volver</a>
			</p>
		</form>
	<?php } ?>
</div>
