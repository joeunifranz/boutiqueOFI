<?php
/**
 * Modal flotante global para autenticación de clientes (Google + registro dentro del modal).
 *
 * Variables opcionales:
 * - $cliente_auth_redirect_to (string)
 */

$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
if($clienteLogueado){
	return;
}

$redirectTo = 'productosCliente/';
if(isset($cliente_auth_redirect_to)){
	$tmp = (string)$cliente_auth_redirect_to;
	if($tmp!=='' && preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $tmp)){
		$redirectTo = $tmp;
	}
}else if(isset($_GET['views'])){
	$tmp = trim((string)$_GET['views']);
	if($tmp!=='' && preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $tmp)){
		if(substr($tmp, -1) !== '/'){
			$tmp .= '/';
		}
		$redirectTo = $tmp;
	}
}

$googleAuthUrl = APP_URL."googleClienteAuth/?redirect_to=".urlencode($redirectTo);

$defaultTitle = 'Para continuar necesitas una cuenta';
if(isset($cliente_auth_title) && (string)$cliente_auth_title !== ''){
	$defaultTitle = (string)$cliente_auth_title;
}

$defaultSubtitle = 'Inicia sesión con Google para continuar.';
if(isset($cliente_auth_subtitle) && (string)$cliente_auth_subtitle !== ''){
	$defaultSubtitle = (string)$cliente_auth_subtitle;
}
?>

<div id="clienteAuthModal" class="modal">
	<div class="modal-background"></div>
	<div class="modal-card cliente-auth-modal-card">
		<header class="modal-card-head cliente-auth-modal-head">
			<p class="modal-card-title">
				<span class="icon-text">
					<span class="icon cliente-auth-icon"><i class="fas fa-crown" aria-hidden="true"></i></span>
					<span id="clienteAuthTitle"><?php echo htmlspecialchars($defaultTitle, ENT_QUOTES, 'UTF-8'); ?></span>
				</span>
			</p>
			<button class="delete" type="button" aria-label="Cerrar"></button>
		</header>
		<section class="modal-card-body cliente-auth-modal-body">
			<div id="clienteAuthStepIntro">
				<p id="clienteAuthSubtitle" class="has-text-centered cliente-auth-subtitle mb-4"><?php echo htmlspecialchars($defaultSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
				<div class="buttons is-centered are-medium cliente-auth-actions">
					<a id="clienteAuthGoogleLink" class="button is-rounded cliente-auth-btn cliente-auth-btn-pink" href="<?php echo htmlspecialchars($googleAuthUrl, ENT_QUOTES, 'UTF-8'); ?>">
						<span class="icon"><i class="fab fa-google" aria-hidden="true"></i></span>
						<span>Continuar con Google</span>
					</a>
					<button id="clienteAuthGoRegister" type="button" class="button is-white is-rounded cliente-auth-btn cliente-auth-btn-white">
						<span class="icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
						<span>Crear cuenta</span>
					</button>
				</div>
				<p class="cliente-auth-footnote is-size-7 has-text-centered mt-4">
					Si es tu primera vez, podrás completar tu registro.
				</p>
			</div>

			<div id="clienteAuthStepRegister" style="display:none;">
				<div class="is-flex is-justify-content-space-between is-align-items-center mb-3">
					<p class="cliente-auth-step-title">Completa tu registro</p>
					<button id="clienteAuthBack" type="button" class="button is-small is-white is-rounded cliente-auth-back-btn">
						<i class="fas fa-arrow-left" aria-hidden="true"></i> &nbsp; Volver
					</button>
				</div>

				<?php
					$cliente_registro_redirect_to = $redirectTo;
					require "./app/views/inc/cliente_registro_form.php";
				?>
			</div>
		</section>
	</div>
</div>

<style>
.cliente-auth-modal-card{
	max-width: 720px;
	width: calc(100% - 2rem);
	border-radius: 18px;
	overflow: hidden;
	box-shadow: 0 22px 70px rgba(0,0,0,0.55);
	border: 1px solid rgba(255,255,255,0.08);
}

.cliente-auth-modal-head{
	border-bottom: 1px solid rgba(255,255,255,0.08);
	background: #0b0f1a;
	backdrop-filter: blur(10px);
	color: #fff;
}

.cliente-auth-modal-head .modal-card-title{
	color: #fff;
	font-weight: 700;
}

.cliente-auth-icon{ color: rgba(255,255,255,0.92); }

.cliente-auth-modal-body{
	background: #0b0f1a;
	color: rgba(255,255,255,0.92);
}

.cliente-auth-subtitle{ color: rgba(255,255,255,0.78); }
.cliente-auth-step-title{ color: rgba(255,255,255,0.92); font-weight: 700; }

.cliente-auth-actions{ gap: 0.75rem; }
.cliente-auth-btn{ min-width: 220px; }

.cliente-auth-btn-white{ color: #0b0f1a; border: 1px solid rgba(255,255,255,0.22); }
.cliente-auth-btn-white:hover{ filter: brightness(0.98); }

.cliente-auth-btn-pink{
	background: linear-gradient(135deg, #ff4fa3 0%, #ff8cc8 100%);
	border: none;
	color: #fff;
	box-shadow: 0 14px 34px rgba(255, 79, 163, 0.25);
}
.cliente-auth-btn-pink:hover{ color: #fff; filter: brightness(1.02); }

.cliente-auth-footnote{ color: rgba(255,255,255,0.62); }
.cliente-auth-back-btn{ border: 1px solid rgba(255,255,255,0.22); }

/* Estilo del formulario incrustado */
.cliente-auth-modal-body .label,
.cliente-auth-modal-body .help{ color: rgba(255,255,255,0.78); }
.cliente-auth-modal-body .input,
.cliente-auth-modal-body .select select{
	background: rgba(255,255,255,0.06);
	border-color: rgba(255,255,255,0.10);
	color: rgba(255,255,255,0.92);
}
.cliente-auth-modal-body .input::placeholder{ color: rgba(255,255,255,0.45); }
.cliente-auth-modal-body .cliente-registro-footnote,
.cliente-auth-modal-body .cliente-registro-footnote a{ color: rgba(255,255,255,0.72); }
.cliente-auth-modal-body .cliente-registro-btn-white{
	color: #0b0f1a;
	border: 1px solid rgba(255,255,255,0.22);
}
.cliente-auth-modal-body .cliente-registro-btn-white:hover{ filter: brightness(0.98); }
.cliente-auth-modal-body .cliente-registro-btn-pink{
	background: linear-gradient(135deg, #ff4fa3 0%, #ff8cc8 100%);
	border: none;
	color: #fff;
	box-shadow: 0 14px 34px rgba(255, 79, 163, 0.25);
}
.cliente-auth-modal-body .cliente-registro-btn-pink:hover{ color: #fff; filter: brightness(1.02); }

@media (max-width: 768px){
	.cliente-auth-actions{ flex-direction: column; }
	.cliente-auth-btn{ width: 100%; min-width: 0; }
}
</style>

<script>
	(function(){
		var modal = document.getElementById('clienteAuthModal');
		if(!modal){ return; }

		var stepIntro = document.getElementById('clienteAuthStepIntro');
		var stepRegister = document.getElementById('clienteAuthStepRegister');
		var btnGoRegister = document.getElementById('clienteAuthGoRegister');
		var btnBack = document.getElementById('clienteAuthBack');
		var googleLink = document.getElementById('clienteAuthGoogleLink');
		var titleEl = document.getElementById('clienteAuthTitle');
		var subtitleEl = document.getElementById('clienteAuthSubtitle');

		var defaultTitle = <?php echo json_encode($defaultTitle, JSON_UNESCAPED_SLASHES); ?>;
		var defaultSubtitle = <?php echo json_encode($defaultSubtitle, JSON_UNESCAPED_SLASHES); ?>;

		function showIntro(){
			if(stepIntro){ stepIntro.style.display = ''; }
			if(stepRegister){ stepRegister.style.display = 'none'; }
		}
		function showRegister(){
			if(stepIntro){ stepIntro.style.display = 'none'; }
			if(stepRegister){ stepRegister.style.display = ''; }
		}

		function setRedirectTo(redirectTo){
			var rt = String(redirectTo || <?php echo json_encode($redirectTo, JSON_UNESCAPED_SLASHES); ?>);
			if(googleLink){
				googleLink.href = <?php echo json_encode(APP_URL."googleClienteAuth/?redirect_to=", JSON_UNESCAPED_SLASHES); ?> + encodeURIComponent(rt);
			}
			var input = modal.querySelector('#clienteAuthStepRegister input[name="redirect_to"]');
			if(input){ input.value = rt; }
		}

		function setTexts(title, subtitle){
			if(titleEl){
				titleEl.textContent = String(title || defaultTitle);
			}
			if(subtitleEl){
				subtitleEl.textContent = String(subtitle || defaultSubtitle);
			}
		}

		function closeModal(){
			modal.classList.remove('is-active');
			showIntro();
			setTexts(defaultTitle, defaultSubtitle);
		}
		function openModal(intent, redirectTo, titleOrOptions, subtitle){
			var title = null;
			var sub = null;
			if(titleOrOptions && typeof titleOrOptions === 'object'){
				title = titleOrOptions.title;
				sub = titleOrOptions.subtitle;
			}else{
				title = titleOrOptions;
				sub = subtitle;
			}

			setRedirectTo(redirectTo);
			setTexts(title, sub);
			modal.classList.add('is-active');
			if(intent==='register'){
				showRegister();
			}else{
				showIntro();
			}
		}

		// Exponer helper global para otros componentes (ej: modal de reserva)
		window.BoutiqueClienteAuthModalOpen = openModal;

		if(btnGoRegister){ btnGoRegister.addEventListener('click', function(){ showRegister(); }); }
		if(btnBack){ btnBack.addEventListener('click', function(){ showIntro(); }); }

		modal.querySelectorAll('.modal-background, .delete').forEach(function(el){
			el.addEventListener('click', function(){ closeModal(); });
		});

		document.addEventListener('keydown', function(e){
			if(e.key === 'Escape'){ closeModal(); }
		});

		// Delegación: cualquier link/botón con .js-cliente-auth-open abre el modal
		document.addEventListener('click', function(e){
			var target = e.target && e.target.closest ? e.target.closest('.js-cliente-auth-open') : null;
			if(!target){ return; }
			// Evitar navegar
			e.preventDefault();
			var intent = target.dataset && target.dataset.authIntent ? target.dataset.authIntent : 'login';
			var rt = target.dataset && target.dataset.redirectTo ? target.dataset.redirectTo : <?php echo json_encode($redirectTo, JSON_UNESCAPED_SLASHES); ?>;
			var title = target.dataset && target.dataset.authTitle ? target.dataset.authTitle : null;
			var subtitle = target.dataset && target.dataset.authSubtitle ? target.dataset.authSubtitle : null;
			openModal(intent, rt, title, subtitle);
		});
	})();
</script>
