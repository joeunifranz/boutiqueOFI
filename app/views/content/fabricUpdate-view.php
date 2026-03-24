<?php
	use app\controllers\fabricController;
	$insTela = new fabricController();
	$telaId = isset($url[1]) ? (int)$url[1] : 0;
	$tela = $insTela->obtenerTelaPorIdControlador($telaId);
?>

<div class="container is-fluid mb-6">
	<h1 class="title">Telas</h1>
	<h2 class="subtitle"><i class="fas fa-sync fa-fw"></i> &nbsp; Actualizar tela</h2>
</div>

<div class="container pb-6 pt-6">
	<?php if(!$tela){ ?>
		<div class="notification is-danger">
			No se encontró la tela solicitada.
		</div>
	<?php }else{ ?>
		<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/telaAjax.php" method="POST" autocomplete="off">
			<input type="hidden" name="modulo_tela" value="actualizar">
			<input type="hidden" name="tela_id" value="<?php echo (int)$tela['tela_id']; ?>">

			<div class="columns">
				<div class="column">
					<div class="control">
						<label>Nombre <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="tela_nombre" pattern="[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ \-]{2,80}" maxlength="80" required value="<?php echo htmlspecialchars($tela['tela_nombre']); ?>">
					</div>
				</div>
			</div>

			<div class="columns">
				<div class="column">
					<div class="control">
						<label>Descripción</label>
						<input class="input" type="text" name="tela_descripcion" maxlength="255" value="<?php echo htmlspecialchars($tela['tela_descripcion'] ?? ''); ?>">
					</div>
				</div>
			</div>

			<div class="columns">
				<div class="column">
					<div class="control">
						<label>Precio <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="number" step="0.01" min="0" name="tela_precio" required value="<?php echo htmlspecialchars((string)($tela['tela_precio'] ?? '0')); ?>">
					</div>
				</div>
				<div class="column">
					<div class="control">
						<label>Stock <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="number" step="1" min="0" name="tela_stock" required value="<?php echo (int)($tela['tela_stock'] ?? 0); ?>">
					</div>
				</div>
			</div>

			<div class="columns">
				<div class="column">
					<div class="control">
						<label>URL de textura (opcional)</label>
						<input class="input" type="text" name="tela_textura_url" maxlength="255" value="<?php echo htmlspecialchars($tela['tela_textura_url'] ?? ''); ?>">
					</div>
				</div>
				<div class="column">
					<div class="control">
						<label>Activo</label>
						<div class="select is-fullwidth">
							<select name="tela_activo">
								<option value="1" <?php echo ((int)($tela['tela_activo'] ?? 1)===1) ? 'selected' : ''; ?>>Sí</option>
								<option value="0" <?php echo ((int)($tela['tela_activo'] ?? 1)===0) ? 'selected' : ''; ?>>No</option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<p class="has-text-centered">
				<button type="submit" class="button is-success is-rounded"><i class="fas fa-sync"></i> &nbsp; Actualizar</button>
				<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>fabricList/"><i class="fas fa-arrow-left"></i> &nbsp; Volver</a>
			</p>
		</form>
	<?php } ?>
</div>
