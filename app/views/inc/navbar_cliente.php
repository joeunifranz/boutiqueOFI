<?php
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));

	use app\controllers\productController;
	$insProductoNavbar = new productController();
?>

<nav class="navbar is-white" role="navigation" aria-label="main navigation">
	<div class="navbar-brand">
		<a class="navbar-item" href="<?php echo APP_URL; ?>inicio/">
			<strong><?php echo APP_NAME; ?></strong>
		</a>
		<a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarCliente">
			<span aria-hidden="true"></span>
			<span aria-hidden="true"></span>
			<span aria-hidden="true"></span>
		</a>
	</div>

	<div id="navbarCliente" class="navbar-menu">
		<div class="navbar-start">
			<a class="navbar-item" href="<?php echo APP_URL; ?>productosCliente/">Tienda</a>
			<div class="navbar-item">
				<div id="categoriasDropdownNavbar" class="dropdown">
					<div class="dropdown-trigger">
						<button id="btnCategoriasNavbar" class="button is-light" aria-haspopup="true" aria-controls="dropdown-menu-categorias-navbar">
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
						<button id="btnTallasNavbar" class="button is-light" aria-haspopup="true" aria-controls="dropdown-menu-tallas">
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
			<?php if($clienteLogueado){ ?>
				<a class="navbar-item" href="<?php echo APP_URL; ?>reservasComprasCliente/">Reservas y compras</a>
			<?php } ?>
		</div>

		<div class="navbar-end">
			<?php if($clienteLogueado){ ?>
				<div class="navbar-item">
					<span class="mr-2">Hola, <?php echo htmlspecialchars($_SESSION['cliente_nombre']); ?></span>
					<a class="button is-light" href="<?php echo APP_URL; ?>clienteLogOut/">Cerrar sesión</a>
				</div>
			<?php }else{ ?>
				<div class="navbar-item">
					<div class="buttons">
						<a class="button is-light" href="<?php echo APP_URL; ?>clienteLogin/">Iniciar sesión</a>
						<a class="button is-info" href="<?php echo APP_URL; ?>registroCliente/">Registrar</a>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>
</nav>

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

<?php if(defined('AGENTE_IA_ENABLED') && AGENTE_IA_ENABLED === true){ ?>
	<script>
		// Base URL de la app para que el widget arme rutas internas
		window.APP_URL = <?php echo json_encode(APP_URL, JSON_UNESCAPED_SLASHES); ?>;
	</script>
	<script src="<?php echo APP_URL; ?>agente_ia/agent.js"></script>
<?php } ?>
