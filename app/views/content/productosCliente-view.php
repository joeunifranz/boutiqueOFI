<?php
	use app\controllers\productController;

	$insProductoCliente = new productController();

	// Detectar si el cliente está logueado (opcional)
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));

	// Capturar categoría desde la URL
	$categoria = isset($url[1]) ? (int)$url[1] : 0;
	$categoriaNombre = "";
	if($categoria>0){
		$categoriaNombre = $insProductoCliente->obtenerNombreCategoriaPorIdControlador($categoria);
	}

	// Obtener productos filtrados
	$productos = $insProductoCliente->productosPorCategoriaControlador($categoria);
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title has-text-centered">
		<?php if($categoria>0 && $categoriaNombre!=""){ ?>
			<?php echo htmlspecialchars($categoriaNombre); ?>
		<?php }else{ ?>
			Productos disponibles
		<?php } ?>
	</h1>

	<div class="columns is-centered mb-5">
		<div class="column is-10-mobile is-6-tablet is-5-desktop">
			<div class="categorias-dropdown-wrap">
				<div id="categoriasDropdownProductos" class="dropdown is-fullwidth">
					<div class="dropdown-trigger is-fullwidth">
						<button id="btnCategoriasProductos" class="button is-light is-rounded is-fullwidth" aria-haspopup="true" aria-controls="dropdown-menu-categorias">
							<span class="is-flex is-align-items-center is-justify-content-center" style="width:100%;">
								<span><i class="fas fa-bars"></i> &nbsp; Categorías</span>
								<span class="icon is-small" style="margin-left:auto;">
									<i class="fas fa-angle-down" aria-hidden="true"></i>
								</span>
							</span>
						</button>
					</div>
					<div class="dropdown-menu" id="dropdown-menu-categorias" role="menu">
						<div class="dropdown-content">
							<a href="<?php echo APP_URL; ?>productosCliente/" class="dropdown-item">Todos los productos</a>
							<hr class="dropdown-divider">
							<?php echo $insProductoCliente->listarCategoriasInicio(); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<p class="has-text-centered mb-5">
	<?php if($clienteLogueado){ ?>
		Bienvenido <?php echo htmlspecialchars($_SESSION['cliente_nombre']." ".$_SESSION['cliente_apellido']); ?>,
	<?php } ?>
		<?php if($categoria>0 && $categoriaNombre!=""){ ?>
			Explora los productos de esta categoría.
		<?php }else{ ?>
			Descubre nuestros productos disponibles.
		<?php } ?>
	</p>

	<div class="columns is-multiline">

	<?php if(!empty($productos)){ ?>

		<?php foreach($productos as $producto){ ?>

			<div class="column is-3">
				<div class="card">

					<div class="card-image">
						<figure class="image is-4by5">
							<?php
								if(is_file("./app/views/productos/".$producto['producto_foto'])){
									echo '<img src="'.APP_URL.'app/views/productos/'.$producto['producto_foto'].'" alt="">';
								}else{
									echo '<img src="'.APP_URL.'app/views/productos/default.png" alt="">';
								}
							?>
						</figure>
					</div>

					<div class="card-content">
						<p class="title is-6">
							<?php echo htmlspecialchars($producto['producto_nombre']); ?>
						</p>

						<p class="subtitle is-6 has-text-success">
							<?php echo MONEDA_SIMBOLO.number_format($producto['producto_precio_venta'],2); ?>
						</p>

						<!-- Botón Ver Detalle -->
						<a href="<?php echo APP_URL; ?>productoDetalle/<?php echo $producto['producto_id']; ?>/" 
	   					class="button is-dark is-fullwidth mb-2">
	   					Ver detalle
						</a>

						<?php if($clienteLogueado){ ?>
							<a class="button is-danger is-fullwidth" href="<?php echo APP_URL; ?>reservaNueva/<?php echo (int)$producto['producto_id']; ?>/">
								<i class="fas fa-qrcode"></i> Reservar con 50%
							</a>
						<?php }else{ ?>
							<a class="button is-danger is-fullwidth" href="<?php echo APP_URL; ?>reservaNueva/<?php echo (int)$producto['producto_id']; ?>/">
								<i class="fas fa-qrcode"></i> Reservar con 50%
							</a>
						<?php } ?>

					</div>
				</div>
			</div>

		<?php } ?>

	<?php }else{ ?>

		<div class="column is-12 has-text-centered">
			<p>No hay productos disponibles.</p>
		</div>

	<?php } ?>

	</div>

	<p class="has-text-centered mt-5">
		<a class="button is-light is-rounded" href="<?php echo APP_URL; ?>inicio/">
			<i class="fas fa-home"></i> &nbsp; Volver al inicio
		</a>
	</p>
</div>	

<script>
	(function(){
		const dd = document.getElementById('categoriasDropdownProductos');
		const btn = document.getElementById('btnCategoriasProductos');
		if(!dd || !btn) return;

		const toggle = () => dd.classList.toggle('is-active');
		btn.addEventListener('click', function(e){
			e.preventDefault();
			toggle();
		});

		dd.addEventListener('click', function(e){
			// Al seleccionar un item del dropdown, cerrarlo (la navegación sigue igual).
			const a = e.target && e.target.closest ? e.target.closest('a.dropdown-item') : null;
			if(a){
				dd.classList.remove('is-active');
			}
		});

		document.addEventListener('click', function(e){
			if(!dd.classList.contains('is-active')) return;
			if(dd.contains(e.target)) return;
			dd.classList.remove('is-active');
		});
	})();
</script>

<style>
	.categorias-dropdown-wrap{ padding: 0.25rem 0; }
	#btnCategoriasProductos{ box-shadow: 0 14px 32px rgba(0,0,0,0.10); border: 1px solid rgba(0,0,0,0.06); }
	#categoriasDropdownProductos.is-active #btnCategoriasProductos{ box-shadow: 0 18px 45px rgba(0,0,0,0.12); }
	#categoriasDropdownProductos .dropdown-menu{ width: 100%; min-width: 100%; padding-top: 0.65rem; }
	#categoriasDropdownProductos .dropdown-content{ border-radius: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 22px 55px rgba(0,0,0,0.12); }
	#categoriasDropdownProductos .dropdown-item{ padding: 0.85rem 1rem; font-size: 0.98rem; }
	#categoriasDropdownProductos .dropdown-item:hover{ background: rgba(0,0,0,0.03); }
	#categoriasDropdownProductos .dropdown-item:active{ background: rgba(0,0,0,0.05); }
	#categoriasDropdownProductos .dropdown-divider{ margin: 0.35rem 0; background: rgba(0,0,0,0.06); }
</style>
<style>
.productos-publicos-wrapper{
	overflow: hidden;
}
.productos-publicos-slider{
	overflow-x: auto;
	padding-bottom: 0.5rem;
}
.productos-publicos-grid{
	display: flex;
	flex-wrap: nowrap;
	gap: 1.5rem;
	min-width: max-content;
}
.productos-publicos-item{
	flex: 0 0 auto;
	width: 260px;
	max-width: 280px;
}
.productos-publicos-card{
	height: 100%;
	border-radius: 12px;
	overflow: hidden;
	box-shadow: 0 8px 20px rgba(0,0,0,0.08);
	transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.productos-publicos-card:hover{
	transform: translateY(-4px);
	box-shadow: 0 14px 30px rgba(0,0,0,0.15);
}
.productos-publicos-card .card-image img{
	object-fit: cover;
}
.productos-publicos-card .card-content{
	padding: 0.9rem 1rem 1rem;
}

/* Scroll suave y barra discreta */
.productos-publicos-slider{
	scroll-behavior: smooth;
}
.productos-publicos-slider::-webkit-scrollbar{
	height: 6px;
}
.productos-publicos-slider::-webkit-scrollbar-track{
	background: #f0f0f0;
	border-radius: 10px;
}
.productos-publicos-slider::-webkit-scrollbar-thumb{
	background: #c0c0c0;
	border-radius: 10px;
}
</style>
