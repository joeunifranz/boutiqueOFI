<div class="main-container">

	<?php
		$google_email    = $_SESSION['google_cliente_email']    ?? '';
		$google_nombre   = $_SESSION['google_cliente_nombre']   ?? '';
		$google_apellido = $_SESSION['google_cliente_apellido'] ?? '';
		$redirect_to = '';
		if(isset($_GET['redirect_to'])){
			$tmp = (string)$_GET['redirect_to'];
			if(preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $tmp)){
				$redirect_to = $tmp;
			}
		}
	?>

	<div class="box" style="max-width: 640px; margin: 2rem auto;">
		<h2 class="title is-4 has-text-centered">
			<i class="fas fa-user-plus"></i> &nbsp; Registro de cliente
		</h2>
		<p class="has-text-centered mb-4">
			Regístrate con tu correo para poder ver los productos disponibles.
		</p>

		<p class="has-text-centered mb-4">
			<a href="<?php echo APP_URL; ?>googleClienteAuth/" class="button is-light is-rounded">
				<span class="icon">
					<i class="fab fa-google"></i>
				</span>
				<span>Registrarme usando mi cuenta de Google</span>
			</a>
		</p>

		<p class="has-text-centered mb-4">
			<span class="has-text-grey">o completa este formulario</span>
		</p>

		<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/clienteAjax.php" method="POST" autocomplete="off">

			<input type="hidden" name="modulo_cliente" value="registrar">
			<input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to,ENT_QUOTES,'UTF-8'); ?>">

			<div class="columns is-multiline">
				<div class="column is-6">
					<div class="control">
						<label>Tipo de documento <?php echo CAMPO_OBLIGATORIO; ?></label><br>
						<div class="select is-fullwidth">
							<select name="cliente_tipo_documento" required>
								<option value="" selected>Seleccione una opción</option>
								<?php
									echo $insLogin->generarSelect(DOCUMENTOS_CLIENTE,"VACIO");
								?>
							</select>
						</div>
					</div>
				</div>
				<div class="column is-6">
					<div class="control">
						<label>Número de documento <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="cliente_numero_documento" pattern="[a-zA-Z0-9-]{7,30}" maxlength="30" required>
					</div>
				</div>

				<div class="column is-6">
					<div class="control">
						<label>Nombres <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="cliente_nombre" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}" maxlength="40" required value="<?php echo htmlspecialchars($google_nombre,ENT_QUOTES,'UTF-8'); ?>">
					</div>
				</div>
				<div class="column is-6">
					<div class="control">
						<label>Apellidos <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="cliente_apellido" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}" maxlength="40" required value="<?php echo htmlspecialchars($google_apellido,ENT_QUOTES,'UTF-8'); ?>">
					</div>
				</div>

				<div class="column is-6">
					<div class="control">
						<label>Departamento / Ciudad <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="cliente_ciudad" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{4,30}" maxlength="30" required>
					</div>
				</div>
				<div class="column is-6">
					<div class="control">
						<label>Calle o dirección de casa <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="text" name="cliente_direccion" pattern="[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{4,70}" maxlength="70" required>
					</div>
				</div>

				<div class="column is-6">
					<div class="control">
						<label>Teléfono</label>
						<input class="input" type="text" name="cliente_telefono" pattern="[0-9()+]{8,20}" maxlength="20">
					</div>
				</div>
				<div class="column is-6">
					<div class="control">
						<label>Email <?php echo CAMPO_OBLIGATORIO; ?></label>
						<input class="input" type="email" name="cliente_email" maxlength="70" required value="<?php echo htmlspecialchars($google_email,ENT_QUOTES,'UTF-8'); ?>">
					</div>
				</div>
			</div>

			<p class="has-text-centered mt-4">
				<button type="reset" class="button is-link is-light is-rounded">
					<i class="fas fa-paint-roller"></i> &nbsp; Limpiar
				</button>
				<button type="submit" class="button is-info is-rounded">
					<i class="far fa-save"></i> &nbsp; Registrarme
				</button>
			</p>

			<p class="has-text-centered pt-4">
				<small>Si ya estás registrado, puedes entrar con tu correo desde 
					<a href="<?php echo APP_URL; ?>clienteLogin/">esta página de acceso</a>.
				</small>
			</p>
		</form>
	</div>
</div>

