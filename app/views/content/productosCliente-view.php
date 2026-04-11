<?php
	use app\controllers\productController;

	$insProductoCliente = new productController();

	// Detectar si el cliente está logueado (opcional)
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));

	// Capturar categoría desde la URL
	$categoria = isset($url[1]) ? (int)$url[1] : 0;

	// Filtro opcional por talla vía querystring (?talla=M)
	$talla = isset($_GET['talla']) ? trim((string)$_GET['talla']) : '';
	// Filtro opcional por precio máximo (?max_price=1200)
	$maxPrice = null;
	if(isset($_GET['max_price'])){
		$rawMax = trim((string)$_GET['max_price']);
		// Permitir separadores tipo 1.200 o 1,200 (tomamos solo dígitos)
		$digits = preg_replace('/\D+/', '', $rawMax);
		if($digits !== ''){
			$val = (float)((int)$digits);
			if($val > 0){
				$maxPrice = $val;
			}
		}
	}
	// Página (paginación) vía querystring (?page=2)
	$pagina = isset($_GET['page']) ? (int)$_GET['page'] : 1;
	if($pagina < 1){ $pagina = 1; }
	$mostrarReco = $clienteLogueado && isset($_GET['reco']) && (string)$_GET['reco'] !== '0';
	$categoriaNombre = "";
	if($categoria>0){
		$categoriaNombre = $insProductoCliente->obtenerNombreCategoriaPorIdControlador($categoria);
	}

	// Obtener productos filtrados (paginado, 8 por página)
	$res = $insProductoCliente->productosPorCategoriaPaginadoControlador($categoria, $talla, $pagina, 8, $maxPrice);
	$productos = $res['productos'] ?? [];
	$paginacion = $res['paginacion'] ?? '';
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

	<?php if($mostrarReco){ ?>
		<?php
			// Construir URL de cierre conservando filtros, quitando "reco"
			$closeGet = $_GET;
			unset($closeGet['reco']);
			$qs = http_build_query($closeGet);
			$closeUrl = APP_URL.'productosCliente/'.($categoria>0 ? ((int)$categoria.'/') : '');
			if(is_string($qs) && $qs !== ''){
				$closeUrl .= '?'.$qs;
			}
		?>
		<div id="recoFoto" class="modal is-active">
			<div class="modal-background" id="recoCloseBg"></div>
			<div class="modal-card" style="max-width: 980px; width: calc(100% - 2rem);">
				<header class="modal-card-head">
					<p class="modal-card-title"><i class="fas fa-camera"></i> &nbsp; SUGERENCIAS DE VESTIDO</p>
					<button class="delete" aria-label="close" id="recoCloseBtn"></button>
				</header>
				<section class="modal-card-body">
					<p class="has-text-grey mb-3">
						Sube una foto para colores y elige tu tipo de cuerpo para recomendar cortes.
					</p>
					<form id="recoForm" class="mb-3" enctype="multipart/form-data">
				<input type="hidden" name="modulo_reco" value="sugerir">
				<input type="hidden" name="categoria_id" value="<?php echo (int)$categoria; ?>">
				<input type="hidden" name="talla" value="<?php echo htmlspecialchars($talla, ENT_QUOTES, 'UTF-8'); ?>">
				<input type="hidden" name="max_price" value="<?php echo ($maxPrice !== null ? (int)$maxPrice : ''); ?>">
				<div class="columns is-variable is-3">
					<div class="column is-6">
						<div class="field">
							<label class="label">Tipo de cuerpo (opcional)</label>
							<div class="control">
								<div class="select is-fullwidth">
									<select name="tipo_cuerpo">
										<option value="" selected>Sin especificar</option>
										<option value="reloj_arena">Reloj de arena (cintura marcada)</option>
										<option value="pera">Pera (caderas más marcadas)</option>
										<option value="manzana">Manzana (más volumen en torso)</option>
										<option value="rectangular">Rectangular (pocas curvas)</option>
										<option value="triangulo_invertido">Triángulo invertido (hombros más anchos)</option>
										<option value="no_se">No estoy segura</option>
									</select>
								</div>
							</div>
						</div>
						<div class="field">
							<label class="label">Contorno de cintura (cm) (opcional)</label>
							<div class="control">
								<input class="input" type="number" name="cintura_cm" min="40" max="160" step="1" placeholder="Ej: 74">
							</div>
						</div>
					</div>
					<div class="column is-6">
						<div class="field">
							<label class="label">Foto (para colores)</label>
							<div class="file has-name is-fullwidth">
								<label class="file-label">
									<input class="file-input" type="file" name="foto" accept="image/jpeg,image/png" required>
									<span class="file-cta">
										<span class="file-icon"><i class="fas fa-upload"></i></span>
										<span class="file-label">Elegir foto (JPG/PNG)</span>
									</span>
									<span class="file-name" id="recoFileName">Ningún archivo seleccionado</span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<div class="field is-grouped">
					<div class="control">
						<button id="recoBtn" class="button is-info" type="submit">Sugerir</button>
					</div>
					<div class="control">
						<span id="recoStatus" class="has-text-grey"></span>
					</div>
				</div>
					</form>
					<div id="recoError" class="message is-danger" style="display:none;"><div class="message-body"></div></div>
					<div id="recoResults" class="columns is-multiline" style="display:none;"></div>
				</section>
				<footer class="modal-card-foot" style="justify-content:flex-end;">
					<a class="button is-light" href="<?php echo htmlspecialchars($closeUrl, ENT_QUOTES, 'UTF-8'); ?>">Cerrar</a>
				</footer>
			</div>
		</div>

		<script>
			(function(){
				const closeUrl = <?php echo json_encode($closeUrl, JSON_UNESCAPED_SLASHES); ?>;
				const close = () => { window.location.href = closeUrl; };
				const closeBtn = document.getElementById('recoCloseBtn');
				const closeBg = document.getElementById('recoCloseBg');
				if(closeBtn){ closeBtn.addEventListener('click', function(e){ e.preventDefault(); close(); }); }
				if(closeBg){ closeBg.addEventListener('click', function(e){ e.preventDefault(); close(); }); }
				document.addEventListener('keydown', function(e){
					if(e && e.key === 'Escape'){
						close();
					}
				});

				const form = document.getElementById('recoForm');
				const results = document.getElementById('recoResults');
				const errBox = document.getElementById('recoError');
				const errBody = errBox ? errBox.querySelector('.message-body') : null;
				const statusEl = document.getElementById('recoStatus');
				const btn = document.getElementById('recoBtn');
				const fileInput = form ? form.querySelector('input[type="file"][name="foto"]') : null;
				const fileNameEl = document.getElementById('recoFileName');
				const escapeHtml = (s) => (s || '').toString().replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

				const setError = (msg) => {
					if(errBox && errBody){
						errBody.textContent = msg || 'Ocurrió un error.';
						errBox.style.display = '';
					}
					if(results){ results.style.display = 'none'; results.innerHTML = ''; }
				};
				const clearError = () => {
					if(errBox){ errBox.style.display = 'none'; }
				};

				if(fileInput && fileNameEl){
					fileInput.addEventListener('change', function(){
						const f = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
						fileNameEl.textContent = f ? f.name : 'Ningún archivo seleccionado';
					});
				}

				if(!form) return;
				form.addEventListener('submit', async function(e){
					e.preventDefault();
					clearError();
					if(statusEl){ statusEl.textContent = 'Procesando...'; }
					if(btn){ btn.classList.add('is-loading'); btn.disabled = true; }

					try{
						const fd = new FormData(form);
						const resp = await fetch('<?php echo APP_URL; ?>app/ajax/recomendadorAjax.php', {
							method: 'POST',
							body: fd,
							credentials: 'same-origin'
						});
						const data = await resp.json();
						if(!data || data.ok !== true){
							setError((data && (data.message || data.error)) ? (data.message || data.error) : 'No se pudo generar sugerencias.');
							if(statusEl){ statusEl.textContent = ''; }
							return;
						}

						const items = Array.isArray(data.items) ? data.items : [];
						if(items.length === 0){
							setError('No se encontraron sugerencias con el catálogo actual.');
							if(statusEl){ statusEl.textContent = ''; }
							return;
						}

						if(statusEl){ statusEl.textContent = data.message || ''; }
						if(results){
							results.innerHTML = '';
							items.forEach(function(it){
								const col = document.createElement('div');
								col.className = 'column is-3';
								const nombre = escapeHtml(it.nombre);
								const fotoUrl = (it.foto_url || '').toString();
								const detalleUrl = (it.detalle_url || '').toString();
								const precio = Number(it.precio || 0);
								col.innerHTML = `
									<div class="card">
										<div class="card-image">
											<figure class="image is-4by5">
												<img src="${fotoUrl}" alt="">
											</figure>
										</div>
										<div class="card-content">
											<p class="title is-6">${nombre}</p>
											<p class="subtitle is-6 has-text-success"><?php echo MONEDA_SIMBOLO; ?>${precio.toFixed(2)}</p>
											<a href="${detalleUrl}" class="button is-dark is-fullwidth">Ver detalle</a>
										</div>
									</div>
								`;
								results.appendChild(col);
							});
							results.style.display = '';
						}
					}catch(err){
						setError('No se pudo conectar con el recomendador.');
						if(statusEl){ statusEl.textContent = ''; }
					}finally{
						if(btn){ btn.classList.remove('is-loading'); btn.disabled = false; }
					}
				});
			})();
		</script>
	<?php } ?>

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

	<?php if(!empty($paginacion)){ ?>
		<div class="mt-5">
			<?php echo $paginacion; ?>
		</div>
	<?php } ?>

	<p class="has-text-centered mt-5">
		<a class="button is-light is-rounded" href="<?php echo APP_URL; ?>inicio/">
			<i class="fas fa-home"></i> &nbsp; Volver al inicio
		</a>
	</p>
</div>	
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
