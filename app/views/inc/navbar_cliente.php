<?php
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
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
	})();
</script>

<?php if(defined('AGENTE_IA_ENABLED') && AGENTE_IA_ENABLED === true){ ?>
	<script>
		// Base URL de la app para que el widget arme rutas internas
		window.APP_URL = <?php echo json_encode(APP_URL, JSON_UNESCAPED_SLASHES); ?>;
	</script>
	<script src="<?php echo APP_URL; ?>agente_ia/agent.js"></script>
<?php } ?>
