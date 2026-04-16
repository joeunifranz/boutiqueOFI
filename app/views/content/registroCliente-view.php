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
		if($redirect_to=='' && isset($_SESSION['google_cliente_redirect_to'])){
			$tmp = (string)$_SESSION['google_cliente_redirect_to'];
			if(preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $tmp)){
				$redirect_to = $tmp;
			}
		}
	?>

	<div class="cliente-registro-backdrop">
		<div class="box cliente-registro-card">
			<p class="has-text-centered cliente-registro-icon">
				<i class="fas fa-crown fa-3x" aria-hidden="true"></i>
			</p>
			<h2 class="title is-4 has-text-centered cliente-registro-title">Registro de cliente</h2>
			<p class="has-text-centered mb-5 cliente-registro-subtitle">
				Crea tu cuenta para ver los vestidos disponibles y reservar tu cita.
			</p>

			<p class="has-text-centered mb-4">
				<a href="<?php echo APP_URL; ?>googleClienteAuth/?redirect_to=<?php echo urlencode($redirect_to); ?>" class="button is-rounded cliente-registro-google-btn">
				<span class="icon">
					<i class="fab fa-google"></i>
				</span>
				<span>Registrarme usando mi cuenta de Google</span>
			</a>
			</p>

			<p class="has-text-centered mb-4">
				<span class="cliente-registro-or">o completa este formulario</span>
			</p>

			<?php
				$cliente_registro_redirect_to = $redirect_to;
				require "./app/views/inc/cliente_registro_form.php";
			?>
		</div>
	</div>
</div>

<style>
.cliente-registro-backdrop{
	min-height: calc(100vh - 140px);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 2rem 1rem;
}

.cliente-registro-card{
	max-width: 760px;
	width: 100%;
	border-radius: 18px;
	background: #0b0f1a;
	border: 1px solid rgba(255,255,255,0.08);
	box-shadow: 0 22px 70px rgba(0,0,0,0.55);
}

.cliente-registro-icon{
	color: rgba(255,255,255,0.92);
	margin-bottom: 0.75rem;
}

.cliente-registro-title{
	color: rgba(255,255,255,0.98);
	font-weight: 800;
}

.cliente-registro-subtitle{
	color: rgba(255,255,255,0.78);
}

.cliente-registro-or{
	color: rgba(255,255,255,0.62);
}

.cliente-registro-card .label,
.cliente-registro-card .help{
	color: rgba(255,255,255,0.78);
}

.cliente-registro-card .input,
.cliente-registro-card .select select{
	background: rgba(255,255,255,0.06);
	border-color: rgba(255,255,255,0.10);
	color: rgba(255,255,255,0.92);
}

.cliente-registro-card .input::placeholder{
	color: rgba(255,255,255,0.45);
}

.cliente-registro-google-btn{
	background: linear-gradient(135deg, #ff4fa3 0%, #ff8cc8 100%);
	border: none;
	color: #fff;
	box-shadow: 0 14px 34px rgba(255, 79, 163, 0.25);
	min-width: 320px;
}

.cliente-registro-google-btn:hover{
	color: #fff;
	filter: brightness(1.02);
}

.cliente-registro-btn-white{
	color: #0b0f1a;
	border: 1px solid rgba(255,255,255,0.22);
}

.cliente-registro-btn-white:hover{
	filter: brightness(0.98);
}

.cliente-registro-btn-pink{
	background: linear-gradient(135deg, #ff4fa3 0%, #ff8cc8 100%);
	border: none;
	color: #fff;
	box-shadow: 0 14px 34px rgba(255, 79, 163, 0.25);
}

.cliente-registro-btn-pink:hover{
	color: #fff;
	filter: brightness(1.02);
}

.cliente-registro-footnote,
.cliente-registro-footnote a{
	color: rgba(255,255,255,0.72);
}

@media (max-width: 768px){
	.cliente-registro-google-btn{ width: 100%; min-width: 0; }
}
</style>

