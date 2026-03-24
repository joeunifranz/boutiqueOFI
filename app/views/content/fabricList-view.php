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
		$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
		$activo = isset($_GET['activo']) ? trim((string)$_GET['activo']) : '';
	?>

	<form action="<?php echo APP_URL; ?>fabricList/" method="GET" autocomplete="off" class="mb-5">
		<div class="columns is-variable is-2">
			<div class="column">
				<label class="label">Buscar por nombre</label>
				<input class="input" type="text" name="q" maxlength="80" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ej: Seda">
			</div>
			<div class="column is-4">
				<label class="label">Activo</label>
				<div class="select is-fullwidth">
					<select name="activo">
						<option value="" <?php echo ($activo==='' ? 'selected' : ''); ?>>Todos</option>
						<option value="1" <?php echo ($activo==='1' ? 'selected' : ''); ?>>Sí</option>
						<option value="0" <?php echo ($activo==='0' ? 'selected' : ''); ?>>No</option>
					</select>
				</div>
			</div>
			<div class="column is-narrow is-flex is-align-items-end" style="gap:0.5rem;">
				<button type="submit" class="button is-info is-rounded">
					<i class="fas fa-search"></i> &nbsp; Buscar
				</button>
				<a class="button is-light is-rounded" href="<?php echo APP_URL; ?>fabricList/">
					Limpiar
				</a>
			</div>
		</div>
	</form>

	<?php
		use app\controllers\fabricController;
		$insTela = new fabricController();
		echo $insTela->listarTelasAdminControlador($url[1] ?? 1, 15, $url[0], $q, $activo);
	?>
</div>
