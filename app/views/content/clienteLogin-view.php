<div class="main-container">

	<?php
		$redirect_to = '';
		if(isset($_GET['redirect_to'])){
			$tmp = (string)$_GET['redirect_to'];
			if(preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $tmp)){
				$redirect_to = $tmp;
			}
		}
	?>

	<form class="box login" action="" method="POST" autocomplete="off">
		<p class="has-text-centered">
			<i class="fas fa-user-circle fa-5x"></i>
		</p>
		<h5 class="title is-5 has-text-centered">Acceso de clientes</h5>
		<p class="has-text-centered mb-3">
			Ingresa con el <strong>correo</strong> que usaste al registrarte
			o utiliza tu cuenta de Google.
		</p>

		<?php
			if(isset($_POST['cliente_email'])){
				$insLogin->iniciarSesionClientePorCorreoControlador();
			}
		?>

		<div class="field">
			<input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to,ENT_QUOTES,'UTF-8'); ?>">
			<label class="label"><i class="fas fa-envelope"></i> &nbsp; Correo electrónico</label>
			<div class="control">
				<input class="input" type="email" name="cliente_email" maxlength="70" required>
			</div>
		</div>

		<p class="has-text-centered mb-4 mt-3">
			<button type="submit" class="button is-info is-rounded">ENTRAR</button>
		</p>

		<p class="has-text-centered mb-4">
			<span class="has-text-grey">o</span>
		</p>

		<p class="has-text-centered mb-4">
			<a href="<?php echo APP_URL; ?>googleClienteAuth/" class="button is-light is-rounded">
				<span class="icon">
					<i class="fab fa-google"></i>
				</span>
				<span>Continuar con Google</span>
			</a>
		</p>

		<p class="has-text-centered">
			<small>¿Aún no tienes cuenta? 
				<a href="<?php echo APP_URL; ?>registroCliente/?redirect_to=<?php echo urlencode($redirect_to); ?>">Regístrate aquí</a>.
			</small>
		</p>

	</form>
</div>

