<?php
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));

	use app\controllers\productController;
	use app\controllers\reservationController;
	$insProductoNavbar = new productController();
	$notifReservas = 0;
	if($clienteLogueado){
		try{
			$insReservaNavbar = new reservationController();
			$notifReservas = $insReservaNavbar->contarNotificacionesReservaClienteControlador((int)$_SESSION['cliente_id']);
			if($notifReservas < 0){ $notifReservas = 0; }
		}catch(Throwable $e){
			$notifReservas = 0;
		}
	}
?>

<nav class="navbar is-white boutique-navbar" role="navigation" aria-label="main navigation">
	<div class="navbar-brand">
		<a class="navbar-item boutique-brand" href="<?php echo APP_URL; ?>inicio/">
			<strong class="boutique-brand-text"><?php echo APP_NAME; ?></strong>
		</a>
		<a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarCliente">
			<span aria-hidden="true"></span>
			<span aria-hidden="true"></span>
			<span aria-hidden="true"></span>
		</a>
	</div>

	<div id="navbarCliente" class="navbar-menu">
		<div class="navbar-start">
			<a class="navbar-item boutique-nav-pill" href="<?php echo APP_URL; ?>productosCliente/">
				<span class="icon" aria-hidden="true"><i class="fas fa-store"></i></span>
				<span>TIENDA</span>
			</a>
			<a class="navbar-item boutique-nav-pill boutique-nav-pill-strong" href="<?php echo APP_URL; ?>telasCliente/">
				<span class="icon" aria-hidden="true"><i class="fas fa-magic"></i></span>
				<span>PERSONALIZA TU VESTIDO</span>
			</a>
			<?php if($clienteLogueado){ ?>
				<a class="navbar-item boutique-nav-pill" href="<?php echo APP_URL; ?>reservasComprasCliente/">
					<span class="icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
					<span>RESERVAS Y COMPRAS</span>
					<?php if($notifReservas > 0){ ?>
						<span class="tag is-rounded ml-2 boutique-notif-badge">
							<?php echo (int)$notifReservas; ?>
						</span>
					<?php } ?>
				</a>
			<?php } ?>
			<div class="navbar-item">
				<div id="categoriasDropdownNavbar" class="dropdown">
					<div class="dropdown-trigger">
						<button id="btnCategoriasNavbar" class="button is-light boutique-nav-btn" aria-haspopup="true" aria-controls="dropdown-menu-categorias-navbar">
							<span class="icon" aria-hidden="true"><i class="fas fa-bars"></i></span>
							<span>Categorías</span>
							<span class="icon is-small">
								<i class="fas fa-angle-down" aria-hidden="true"></i>
							</span>
						</button>
					</div>
					<div class="dropdown-menu" id="dropdown-menu-categorias-navbar" role="menu">
						<div class="dropdown-content">
							<a href="<?php echo APP_URL; ?>productosCliente/" class="dropdown-item">Todos los productos</a>
							<hr class="dropdown-divider">
							<?php echo $insProductoNavbar->listarCategoriasInicio(); ?>
						</div>
					</div>
				</div>
			</div>
			<div class="navbar-item">
				<div id="tallasDropdownNavbar" class="dropdown">
					<div class="dropdown-trigger">
						<button id="btnTallasNavbar" class="button is-light boutique-nav-btn" aria-haspopup="true" aria-controls="dropdown-menu-tallas">
							<span class="icon" aria-hidden="true"><i class="fas fa-ruler"></i></span>
							<span>Tallas</span>
							<span class="icon is-small">
								<i class="fas fa-angle-down" aria-hidden="true"></i>
							</span>
						</button>
					</div>
					<div class="dropdown-menu" id="dropdown-menu-tallas" role="menu">
						<div class="dropdown-content">
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=XXXS" class="dropdown-item">XXXS</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=XXS" class="dropdown-item">XXS</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=XS" class="dropdown-item">XS</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=S" class="dropdown-item">S</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=M" class="dropdown-item">M</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=L" class="dropdown-item">L</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=XL" class="dropdown-item">XL</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=XXL" class="dropdown-item">XXL</a>
							<a href="<?php echo APP_URL; ?>productosCliente/?talla=XXXL" class="dropdown-item">XXXL</a>
						</div>
					</div>
				</div>
			</div>

		</div>

		<div class="navbar-end">
			<?php if($clienteLogueado){ ?>
				<div class="navbar-item">
					<a class="button is-success is-light mr-2 boutique-nav-btn" href="<?php echo APP_URL; ?>productosCliente/?reco=1#recoFoto">
						<i class="fas fa-camera"></i> &nbsp; SUGERENCIAS DE VESTIDO
					</a>
					<span class="mr-2 boutique-nav-greeting">Hola, <?php echo htmlspecialchars($_SESSION['cliente_nombre']); ?></span>
					<a class="button is-light boutique-nav-btn" href="<?php echo APP_URL; ?>clienteLogOut/">Cerrar sesión</a>
				</div>
			<?php }else{ ?>
				<div class="navbar-item">
					<div class="buttons">
						<a class="button is-light boutique-nav-btn js-cliente-auth-open" href="<?php echo APP_URL; ?>clienteLogin/" data-auth-intent="login">Iniciar sesión</a>
						<a class="button is-info boutique-nav-btn js-cliente-auth-open" href="<?php echo APP_URL; ?>registroCliente/" data-auth-intent="register">Registrar</a>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>
</nav>

<style>
/* Navbar cliente: estilo más uniforme y sofisticado */
.boutique-navbar{
	position: sticky;
	top: 0;
	z-index: 50;
	background: rgba(255,255,255,0.78) !important;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	border-bottom: 1px solid rgba(0,0,0,0.06);
}

/* Variante "tipo inicio": cuando el navbar vive dentro del fondo boutique (catálogo/detalle/reserva) */
.boutique-bg .boutique-navbar{
	background: transparent !important;
	border-bottom: 1px solid rgba(255, 221, 150, 0.22);
	box-shadow: 0 12px 40px rgba(0,0,0,0.25);
}

.boutique-bg .boutique-brand-text,
.boutique-bg .boutique-navbar .navbar-item,
.boutique-bg .boutique-navbar .navbar-item a,
.boutique-bg .boutique-navbar .navbar-link{
	color: rgba(255,255,255,0.92) !important;
}

/* Brand más auténtico (sin fuentes nuevas: usa OswaldLight ya cargada) */
.boutique-bg .boutique-brand-text{
	font-family: "OswaldLight";
	font-size: 1.15rem;
	letter-spacing: .26em;
	text-transform: uppercase;
	background-image:
		linear-gradient(180deg,
			#fff4cf 0%,
			#ffe08a 18%,
			#f2c24a 40%,
			#d9a61d 60%,
			#b57f00 82%,
			#ffe6a0 100%
		);
	-webkit-background-clip: text;
	background-clip: text;
	color: transparent !important;
	-webkit-text-fill-color: transparent;
	filter:
		drop-shadow(0 1px 0 rgba(0,0,0,0.25))
		drop-shadow(0 0 12px rgba(255, 205, 92, 0.32));
}

.boutique-bg .boutique-nav-greeting{ color: rgba(255,255,255,0.78) !important; }

.boutique-bg .navbar-burger span{ background-color: rgba(255,255,255,0.85); }

/* Pills/botones dorados glass (inspirado en Inicio) */
.boutique-bg .boutique-nav-pill,
.boutique-bg .boutique-navbar .boutique-nav-btn{
	background: rgba(255,255,255,0.04) !important;
	border: 1px solid rgba(255, 221, 150, 0.88) !important;
	box-shadow:
		0 16px 42px rgba(0,0,0,0.38),
		0 0 0 1px rgba(255, 208, 120, 0.14),
		0 0 30px rgba(255, 200, 90, 0.34);
	backdrop-filter: blur(6px);
	-webkit-backdrop-filter: blur(6px);
	color: rgba(255,255,255,0.92) !important;
}

/* Texto: blanco (el usuario pidió letras blancas) */
.boutique-bg .boutique-nav-pill > span:not(.icon),
.boutique-bg .boutique-navbar .boutique-nav-btn > span:not(.icon){
	background-image: none !important;
	-webkit-background-clip: initial;
	background-clip: initial;
	color: rgba(255,255,255,0.92) !important;
	-webkit-text-fill-color: rgba(255,255,255,0.92) !important;
	filter: drop-shadow(0 1px 0 rgba(0,0,0,0.25));
}

.boutique-bg .boutique-nav-pill:hover,
.boutique-bg .boutique-navbar .boutique-nav-btn:hover{
	background: rgba(255,255,255,0.07) !important;
	border-color: rgba(255, 238, 190, 0.98) !important;
	box-shadow:
		0 20px 52px rgba(0,0,0,0.46),
		0 0 0 1px rgba(255, 210, 120, 0.20),
		0 0 42px rgba(255, 205, 92, 0.52);
}

.boutique-bg .boutique-nav-pill .icon i,
.boutique-bg .boutique-navbar .boutique-nav-btn .icon i{
	color: #ffe08a !important;
	filter:
		drop-shadow(0 1px 0 rgba(0,0,0,0.25))
		drop-shadow(0 0 10px rgba(255, 205, 92, 0.28));
}

/* Dropdowns en modo oscuro/glass */
.boutique-bg .dropdown-content{
	background: rgba(11, 15, 26, 0.92);
	border: 1px solid rgba(255, 221, 150, 0.18);
	box-shadow: 0 22px 70px rgba(0,0,0,0.55);
}
.boutique-bg .dropdown-item{ color: rgba(255,255,255,0.88) !important; }
.boutique-bg .dropdown-item:hover{ background: rgba(255,255,255,0.06); }
.boutique-bg .dropdown-divider{ background: rgba(255,255,255,0.12); }

/* Badge numérico (Reservas y Compras): círculo rojo claro intenso y número blanco */
.boutique-notif-badge{
	background-color: #ff3b3b !important;
	color: #fff !important;
	min-width: 1.9em;
	height: 1.9em;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding-left: .6em;
	padding-right: .6em;
	font-weight: 800;
}

/* Tipografía/tamaño uniforme en todo el navbar */
.boutique-navbar .navbar-item,
.boutique-navbar .navbar-item a,
.boutique-navbar .button,
.boutique-navbar .dropdown-item,
.boutique-navbar .navbar-link{
	font-size: 0.9rem;
	line-height: 1.2;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.boutique-navbar .navbar-item,
.boutique-navbar .button{
	font-weight: 700;
}

.boutique-brand{ padding-left: 1rem; }
.boutique-brand-text{ font-size: 0.9rem; letter-spacing: .14em; text-transform: uppercase; }

/* Links del lado izquierdo como “pills” */
.boutique-nav-pill{
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 42px;
	margin: .25rem .15rem;
	padding: 0 .95rem;
	border-radius: 14px;
	border: 1px solid rgba(255, 221, 150, 0.45);
	background: rgba(255,255,255,0.55);
	box-shadow: 0 10px 22px rgba(0,0,0,0.08);
	transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
}
.boutique-nav-pill:hover{
	background: rgba(255,255,255,0.72);
	border-color: rgba(255, 221, 150, 0.70);
	transform: translateY(-1px);
	box-shadow: 0 14px 30px rgba(0,0,0,0.12);
}
.boutique-nav-pill-strong{
	text-transform: uppercase;
	letter-spacing: .06em;
}

/* Botones (dropdowns y acciones de la derecha) con mismo tamaño */
.boutique-navbar .boutique-nav-btn{
	min-height: 42px;
	border-radius: 14px;
	font-weight: 700;
	letter-spacing: .03em;
}

.boutique-nav-greeting{
	font-weight: 700;
	letter-spacing: .03em;
	color: rgba(0,0,0,0.70);
}

@media (max-width: 1023px){
	.boutique-nav-pill{ width: 100%; justify-content: flex-start; }
	.boutique-navbar .navbar-start .navbar-item{ width: 100%; }
}
</style>

<script>
	(function(){
		const burgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
		if (burgers.length > 0) {
			burgers.forEach(function (el) {
				el.addEventListener('click', function () {
					const target = el.dataset.target;
					const $target = document.getElementById(target);
					el.classList.toggle('is-active');
					if($target){
						$target.classList.toggle('is-active');
					}
				});
			});
		}

		const ddTallas = document.getElementById('tallasDropdownNavbar');
		const btnTallas = document.getElementById('btnTallasNavbar');
		const ddCategorias = document.getElementById('categoriasDropdownNavbar');
		const btnCategorias = document.getElementById('btnCategoriasNavbar');

		const setupDropdown = (dd, btn) => {
			if(!dd || !btn) return;
			const toggle = () => dd.classList.toggle('is-active');
			btn.addEventListener('click', function(e){
				e.preventDefault();
				toggle();
			});

			document.addEventListener('click', function(e){
				if(!dd.classList.contains('is-active')) return;
				if(dd.contains(e.target)) return;
				dd.classList.remove('is-active');
			});

			dd.addEventListener('click', function(e){
				const a = e.target && e.target.closest ? e.target.closest('a.dropdown-item') : null;
				if(a){
					dd.classList.remove('is-active');
				}
			});
		};

		setupDropdown(ddCategorias, btnCategorias);
		setupDropdown(ddTallas, btnTallas);
	})();
</script>

<?php
	if(!$clienteLogueado){
		require_once "./app/views/inc/cliente_auth_modal.php";
	}
?>

<?php if(defined('AGENTE_IA_ENABLED') && AGENTE_IA_ENABLED === true){ ?>
	<script>
		window.APP_URL = <?php echo json_encode(APP_URL, JSON_UNESCAPED_SLASHES); ?>;
		window.BOUTIQUE_CLIENTE_ID = <?php echo json_encode((isset($_SESSION['cliente_id']) ? (int)$_SESSION['cliente_id'] : 0)); ?>;
	</script>
	<script src="<?php echo APP_URL; ?>agente_ia/agent.js"></script>
<?php } ?>
