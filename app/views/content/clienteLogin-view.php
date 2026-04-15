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

	<div class="box cliente-login-princess-card">
		<p class="has-text-centered cliente-login-icon">
			<i class="fas fa-crown fa-3x" aria-hidden="true"></i>
		</p>
		<h5 class="title is-4 has-text-centered cliente-login-title">Bienvenida</h5>
		<p class="has-text-centered cliente-login-subtitle mb-5">
			Inicia sesión con tu cuenta de Google para continuar.
		</p>

		<p class="has-text-centered">
			<a href="<?php echo APP_URL; ?>googleClienteAuth/?redirect_to=<?php echo urlencode($redirect_to); ?>" class="button is-rounded cliente-login-google-btn">
				<span class="icon">
					<i class="fab fa-google" aria-hidden="true"></i>
				</span>
				<span>Continuar con Google</span>
			</a>
		</p>

		<p class="has-text-centered mt-4 cliente-login-footnote">
			<small>Si es tu primera vez, te pediremos unos datos para completar tu registro.</small>
		</p>
	</div>
</div>

<style>
.cliente-login-princess-card{
	max-width: 560px;
	margin: 2.25rem auto;
	border-radius: 18px;
	background: #0b0f1a;
	border: 1px solid rgba(255,255,255,0.08);
	box-shadow: 0 22px 70px rgba(0,0,0,0.55);
}

.cliente-login-icon{
	color: rgba(255,255,255,0.92);
	margin-bottom: 0.85rem;
}

.cliente-login-title{
	color: rgba(255,255,255,0.98);
	font-weight: 800;
}

.cliente-login-subtitle{
	color: rgba(255,255,255,0.78);
}

.cliente-login-google-btn{
	background: linear-gradient(135deg, #ff4fa3 0%, #ff8cc8 100%);
	border: none;
	color: #fff;
	box-shadow: 0 14px 34px rgba(255, 79, 163, 0.25);
	min-width: 280px;
}

.cliente-login-google-btn:hover{
	color: #fff;
	filter: brightness(1.02);
}

.cliente-login-footnote{
	color: rgba(255,255,255,0.62);
}

@media (max-width: 768px){
	.cliente-login-google-btn{ width: 100%; min-width: 0; }
}
</style>

