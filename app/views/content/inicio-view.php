<?php
	use app\controllers\productController;
	$insProductoInicio = new productController();
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
?>

<div class="inicio-wrapper">

	<!-- Fondo deslizante -->
	<div class="inicio-slider" aria-hidden="true">
		<div class="inicio-slide s1"></div>
		<div class="inicio-slide s2"></div>
		<div class="inicio-slide s3"></div>
		<div class="inicio-slide s4"></div>
		<div class="inicio-slide s5"></div>
		<div class="inicio-slide s6"></div>
	</div>

	<!-- Overlay -->
	<div class="inicio-overlay" aria-hidden="true"></div>

	<!-- HEADER SUPERIOR -->
	<header class="inicio-header">

		<!-- Accesos principales (misma fila / mismo tamaño) -->
		<div class="inicio-left-buttons" aria-label="Accesos rápidos">
			<a class="button is-medium is-rounded has-text-weight-semibold is-warning inicio-btn-gold" href="<?php echo APP_URL; ?>productosCliente/" aria-label="Ver vestidos">
				<span class="icon">
					<i class="fas fa-store" aria-hidden="true"></i>
				</span>
				<span>Vestidos</span>
			</a>

			<div class="inicio-categorias-wrapper">
				<button class="button is-medium is-rounded has-text-weight-semibold is-warning inicio-btn-gold" type="button" onclick="toggleCategorias()" aria-haspopup="true" aria-controls="categoriasDropdown">
					<span class="icon"><i class="fas fa-bars" aria-hidden="true"></i></span>
					<span>Categorías</span>
				</button>
				<div id="categoriasDropdown" class="inicio-categorias-dropdown">
					<?php echo $insProductoInicio->listarCategoriasInicio(); ?>
				</div>
			</div>

			<div class="inicio-categorias-wrapper">
				<button class="button is-medium is-rounded has-text-weight-semibold is-warning inicio-btn-gold" type="button" onclick="toggleTallas()" aria-haspopup="true" aria-controls="tallasDropdown">
					<span class="icon"><i class="fas fa-ruler" aria-hidden="true"></i></span>
					<span>Tallas</span>
				</button>
				<div id="tallasDropdown" class="inicio-categorias-dropdown">
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



		<!-- Botones cliente (derecha) -->
		<div class="inicio-top-buttons">
			<?php if($clienteLogueado){ ?>
				<a class="inicio-login-btn" href="<?php echo APP_URL; ?>productosCliente/">
					<i class="fas fa-store"></i>
					<span>Tienda</span>
				</a>
				<a class="inicio-register-btn" href="<?php echo APP_URL; ?>clienteLogOut/">
					<i class="fas fa-sign-out-alt"></i>
					<span>Cerrar sesión</span>
				</a>
			<?php }else{ ?>
				<a class="inicio-login-btn" href="<?php echo APP_URL; ?>clienteLogin/">
					<i class="fas fa-sign-in-alt"></i>
					<span>Iniciar sesión</span>
				</a>

				<a class="inicio-register-btn" href="<?php echo APP_URL; ?>registroCliente/">
					<i class="fas fa-user-plus"></i>
					<span>Registrar</span>
				</a>
			<?php } ?>
		</div>

	</header>

	<!-- HERO -->
	<main class="inicio-hero">
		<h1 class="inicio-titulo">
			<span class="inicio-titulo-a">BOUTIQUE</span>
			<span class="inicio-titulo-b">DORITA</span>
		</h1>

		<p class="inicio-descripcion">
			Donde la elegancia encuentra tu estilo. 
			Vestidos que cuentan tu historia, tejidos con pasión 
			para que cada momento sea memorable.
		</p>

		<!-- (Los accesos rápidos ahora están arriba a la izquierda) -->

		<section class="inicio-ubicacion" aria-label="Ubicación de la Boutique">
			<div class="inicio-ubicacion-texto">
				<h2 class="inicio-ubicacion-titulo">Ubicación</h2>
				<p class="inicio-ubicacion-direccion">
					Avenida Maximiliano Paredes, N.º 873 – Boutique Dorita
				</p>
				<p class="inicio-ubicacion-ayuda">
					Mapa referencial para llegar a la boutique.
				</p>
			</div>

			<div class="inicio-ubicacion-mapa">
				<div class="inicio-mapa-embed">
					<iframe
						title="Google Maps - Boutique Dorita"
						loading="lazy"
						referrerpolicy="no-referrer-when-downgrade"
						src="https://www.google.com/maps?output=embed&cid=7166701914346393588"
					></iframe>
				</div>
			</div>
		</section>
	</main>

	<!-- CATÁLOGO -->
	<section class="inicio-catalogo">
		<div class="container">
			<h2 class="title is-3 has-text-centered has-text-white">
				Explora nuestro catálogo
			</h2>

			<p class="has-text-centered inicio-catalogo-subtitle">
				Colecciones por categoría con productos disponibles ahora mismo.
			</p>

			<?php echo $insProductoInicio->catalogoInicioHTMLControlador(); ?>
		</div>
	</section>

	<!-- MODAL PROBADOR -->
	<div id="tryonModal" class="modal">

		<div class="modal-background" onclick="cerrarProbador()"></div>

		<div class="modal-card tryon-modal-card">
			
			<header class="modal-card-head">
				<p class="modal-card-title">
					<i class="fas fa-magic"></i> Probador Virtual con IA
				</p>
				<button class="delete" type="button" onclick="cerrarProbador()"></button>
			</header>

			<section class="modal-card-body">
				<div class="columns">

					<div class="column is-5">
						<div class="tryon-upload-section">
							<h3 class="title is-5">1. Sube tu foto</h3>

							<div class="file has-name is-boxed">
								<label class="file-label">
									<input 
										class="file-input" 
										type="file" 
										id="fotoPersona" 
										accept="image/jpeg,image/png"
										onchange="previewFoto(this)"
									>
									<span class="file-cta">
										<span class="file-icon">
											<i class="fas fa-upload"></i>
										</span>
										<span class="file-label">Elegir foto</span>
									</span>
									<span class="file-name" id="fileName">
										Ningún archivo seleccionado
									</span>
								</label>
							</div>

							<div id="previewContainer" class="mt-4" style="display:none;">
								<img id="previewFoto" alt="Vista previa" class="tryon-preview-img">
							</div>
						</div>

						<div class="tryon-products-section mt-6">
							<h3 class="title is-5">2. Selecciona un vestido</h3>

							<div id="productosContainer" class="tryon-products-grid">
								<p class="has-text-centered">
									<i class="fas fa-spinner fa-spin"></i> 
									Cargando vestidos...
								</p>
							</div>
						</div>
					</div>

					<div class="column is-7">
						<div class="tryon-result-section">
							<h3 class="title is-5">3. Resultado</h3>

							<div id="resultadoContainer" class="tryon-result-box">
								<div class="tryon-placeholder">
									<i class="fas fa-image fa-5x"></i>
									<p class="mt-4">El resultado aparecerá aquí</p>
								</div>
							</div>

							<div class="has-text-centered mt-4">
								<button 
									class="button is-success is-large" 
									id="btnProcesar"
									type="button"
									onclick="procesarTryOn()"
									disabled
								>
									<i class="fas fa-magic"></i>
									Aplicar Vestido con IA
								</button>
							</div>
						</div>
					</div>

				</div>
			</section>

		</div>
	</div>

	<!-- BOTÓN FLOTANTE IA -->
	<button 
		class="inicio-tryon-floating"
		type="button"
		onclick="abrirProbador()"
	>
		<i class="fas fa-magic"></i>
		<span>Probar con IA</span>
	</button>

</div>

<style>
.inicio-wrapper{
	position: relative;
	width: 100%;
}

.inicio-left-buttons{
	position: fixed;
	top: 1.5rem;
	left: 1.5rem;
	z-index: 40;
	display: flex;
	align-items: center;
	gap: .65rem;
	flex-wrap: wrap;
}

.inicio-btn-gold{
	/* Mantener mismo tamaño visual en los 3 botones */
	min-height: 44px;
	padding-left: 1.25rem;
	padding-right: 1.25rem;
	position: relative;
	overflow: hidden;

	/* Negro brilloso (glossy) */
	background-color: #0b0f1a !important;
	background-image:
		linear-gradient(180deg,
			rgba(255,255,255,0.22) 0%,
			rgba(255,255,255,0.10) 22%,
			rgba(255,255,255,0.02) 48%,
			rgba(0,0,0,0.22) 100%
		),
		linear-gradient(135deg,
			rgba(255,255,255,0.10) 0%,
			rgba(255,255,255,0.00) 40%,
			rgba(0,0,0,0.28) 100%
		) !important;
	background-blend-mode: screen, normal;
	filter: contrast(1.08) saturate(1.05);

	border: 1px solid rgba(255,255,255,.22);
	color: #fff !important;
	text-shadow: 0 1px 0 rgba(0,0,0,0.18);

	/* Relieve: sombra externa + sombra interna */
	box-shadow:
		0 16px 42px rgba(0,0,0,0.38),
		0 10px 24px rgba(0,0,0,0.24),
		inset 0 1px 0 rgba(255,255,255,0.22),
		inset 0 -10px 22px rgba(0,0,0,0.42);

	backdrop-filter: blur(6px);
	transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

.inicio-btn-gold .icon,
.inicio-btn-gold .icon i,
.inicio-btn-gold span{
	color: #fff !important;
}

/* Brillo tipo "gloss" arriba */
.inicio-btn-gold::before{
	content: "";
	position: absolute;
	left: 10%;
	top: -60%;
	width: 80%;
	height: 140%;
	background: radial-gradient(circle at 50% 35%, rgba(255,255,255,0.55) 0%, rgba(255,255,255,0.0) 62%);
	transform: rotate(-8deg);
	pointer-events: none;
	opacity: 0.55;
}

/* Reflejo fino (metal) */
.inicio-btn-gold::after{
	content: "";
	position: absolute;
	left: 8%;
	top: 10px;
	width: 84%;
	height: 2px;
	background: linear-gradient(90deg, rgba(255,255,255,0.0) 0%, rgba(255,255,255,0.38) 50%, rgba(255,255,255,0.0) 100%);
	opacity: 0.65;
	pointer-events: none;
}

.inicio-btn-gold:hover,
.inicio-btn-gold:focus{
	transform: translateY(-1px);
	filter: contrast(1.10) saturate(1.08) brightness(1.05);
	box-shadow:
		0 20px 54px rgba(0,0,0,0.42),
		0 16px 28px rgba(0,0,0,0.26),
		inset 0 1px 0 rgba(255,255,255,0.26),
		inset 0 -10px 24px rgba(0,0,0,0.46);
}

.inicio-btn-gold:active{
	transform: translateY(0px);
	box-shadow:
		0 10px 24px rgba(0,0,0,0.20),
		inset 0 2px 6px rgba(0,0,0,0.22),
		inset 0 1px 0 rgba(255,255,255,0.35);
}

.inicio-slider,
.inicio-overlay{
	position: absolute;
	inset: 0;
}

.inicio-slider{
	z-index: 1;
	background: #0b1020;
}

.inicio-slide{
	position: absolute;
	inset: 0;
	background-size: cover;
	background-position: center;
	transform: scale(1.05);
	filter: saturate(1.1) contrast(1.05) brightness(0.85);
	opacity: 0;
	animation: inicioFade 30s infinite;
}

/* Imágenes del fondo (tomadas de app/views/productos) */
.inicio-slide.s1{ background-image: url("<?php echo APP_URL; ?>app/views/productos/09877_26.jpg"); animation-delay: 0s; }
.inicio-slide.s2{ background-image: url("<?php echo APP_URL; ?>app/views/productos/1023764_24.jpg"); animation-delay: 5s; }
.inicio-slide.s3{ background-image: url("<?php echo APP_URL; ?>app/views/productos/236742_81.jpg"); animation-delay: 10s; }
.inicio-slide.s4{ background-image: url("<?php echo APP_URL; ?>app/views/productos/28127_69.jpg"); animation-delay: 15s; }
.inicio-slide.s5{ background-image: url("<?php echo APP_URL; ?>app/views/productos/8172_56.jpg"); animation-delay: 20s; }
.inicio-slide.s6{ background-image: url("<?php echo APP_URL; ?>app/views/productos/9876_44.jpg"); animation-delay: 25s; }

@keyframes inicioFade{
	0%   { opacity: 0; transform: scale(1.08) translateX(-2%); }
	5%   { opacity: 1; }
	25%  { opacity: 1; }
	30%  { opacity: 0; transform: scale(1.03) translateX(2%); }
	100% { opacity: 0; }
}

.inicio-overlay{
	z-index: 2;
	background:
		linear-gradient(90deg, rgba(0,0,0,.65) 0%, rgba(0,0,0,.25) 60%, rgba(0,0,0,.35) 100%),
		linear-gradient(180deg, rgba(0,0,0,.55) 0%, rgba(0,0,0,.35) 45%, rgba(0,0,0,.65) 100%);
}

.inicio-top-buttons{
	position: fixed;
	top: 1rem;
	right: 1rem;
	z-index: 30;
	display: flex;
	gap: .75rem;
}
.inicio-login-btn,
.inicio-register-btn{
	display: inline-flex;
	align-items: center;
	gap: .5rem;
	padding: .6rem 1rem;
	border-radius: 999px;
	color: #fff;
	background: rgba(255,255,255,.12);
	border: 1px solid rgba(255,255,255,.25);
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	font-weight: 600;
	font-size: 0.85rem;
}
.inicio-login-btn:hover,
.inicio-register-btn:hover{
	color: #fff;
	background: rgba(255,255,255,.18);
	transform: translateY(-1px);
}
.inicio-login-btn i,
.inicio-register-btn i{
	margin-right: .4rem;
}

.inicio-admin-link{
	position: fixed;
	bottom: 1rem;
	right: 1rem;
	z-index: 30;
	font-size: 0.8rem;
	color: rgba(255,255,255,.7);
}
.inicio-admin-link a{
	color: rgba(255,255,255,.9);
	text-decoration: underline;
}

/* Título y descripción */
.inicio-hero{
	position: relative;
	z-index: 10;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 100vh;
	padding: 2rem;
	text-align: center;
	width: 100%;
}
.inicio-titulo{
	margin: 0;
	font-size: clamp(2.5rem, 8vw, 5rem);
	font-weight: 200;
	letter-spacing: 0.35em;
	line-height: 1.1;
	text-transform: uppercase;
	color: #fff;
	text-shadow: 0 0 40px rgba(255,255,255,.15);
	animation: inicioTituloFade 1.2s ease-out;
}
.inicio-titulo-a{
	display: block;
	font-weight: 300;
	letter-spacing: 0.5em;
	background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,.85) 50%, rgba(255,255,255,.7) 100%);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
}
.inicio-titulo-b{
	display: block;
	font-weight: 700;
	letter-spacing: 0.6em;
	margin-top: 0.15em;
	background: linear-gradient(90deg, #f8e8e8, #fff, #f8e8e8);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
}
.inicio-descripcion{
	max-width: 420px;
	margin: 2rem auto 0;
	font-size: 1.05rem;
	line-height: 1.8;
	color: rgba(255,255,255,.92);
	font-weight: 300;
	letter-spacing: 0.08em;
	animation: inicioTituloFade 1.2s ease-out 0.3s both;
}

/* Ubicación + mapa (derecha) */
.inicio-ubicacion{
	margin-top: 2.25rem;
	width: min(980px, 100%);
	display: flex;
	gap: 1.25rem;
	align-items: stretch;
	padding: 1rem 1.1rem;
	border-radius: 16px;
	background: rgba(255,255,255,.10);
	border: 1px solid rgba(255,255,255,.22);
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
}
.inicio-ubicacion-texto{
	flex: 1 1 52%;
	text-align: left;
	color: #fff;
}
.inicio-ubicacion-titulo{
	margin: 0 0 .5rem;
	font-weight: 700;
	letter-spacing: .08em;
	text-transform: uppercase;
	font-size: 1rem;
	color: rgba(255,255,255,.95);
}
.inicio-ubicacion-direccion{
	margin: 0;
	font-size: 1rem;
	line-height: 1.6;
	color: rgba(255,255,255,.92);
}
.inicio-ubicacion-ayuda{
	margin: .55rem 0 0;
	font-size: .85rem;
	line-height: 1.5;
	letter-spacing: .03em;
	color: rgba(255,255,255,.78);
}
.inicio-ubicacion-mapa{
	flex: 1 1 48%;
	display: flex;
}
.inicio-mapa-embed{
	width: 100%;
	overflow: hidden;
	border-radius: 14px;
	border: 1px solid rgba(255,255,255,.18);
	background: rgba(0,0,0,.25);
}
.inicio-mapa-embed iframe{
	display: block;
	width: 100%;
	height: 240px;
	border: 0;
}

@media (max-width: 768px){
	.inicio-ubicacion{
		flex-direction: column;
		text-align: center;
	}
	.inicio-ubicacion-texto{
		text-align: center;
	}
	.inicio-mapa-embed iframe{
		height: 220px;
	}
}
@keyframes inicioTituloFade{
	from{
		opacity: 0;
		transform: translateY(20px);
	}
	to{
		opacity: 1;
		transform: translateY(0);
	}
}

/* Catálogo en inicio */
.inicio-catalogo{
	position: relative;
	z-index: 5;
	margin-top: -2rem;
	padding: 4rem 1rem 5rem;
}
.inicio-catalogo-subtitle{
	color: rgba(255,255,255,.85);
	margin-bottom: 2.5rem;
}
.inicio-catalogo-categoria{
	margin-bottom: 3rem;
}
.inicio-catalogo-header{
	margin-bottom: 1rem;
}
.inicio-catalogo-row-wrapper{
	overflow: hidden;
}
.inicio-catalogo-row{
	display: flex;
	gap: 1.5rem;
	overflow-x: auto;
	padding-bottom: 0.5rem;
	scroll-behavior: smooth;
}
.inicio-catalogo-row::-webkit-scrollbar{
	height: 6px;
}
.inicio-catalogo-row::-webkit-scrollbar-track{
	background: rgba(255,255,255,0.12);
	border-radius: 10px;
}
.inicio-catalogo-row::-webkit-scrollbar-thumb{
	background: rgba(255,255,255,0.4);
	border-radius: 10px;
}
.inicio-catalogo-item{
	flex: 0 0 auto;
	width: 220px;
	max-width: 240px;
}
.inicio-catalogo-link{
	display: block;
	color: inherit;
	text-decoration: none;
}
.inicio-catalogo-card{
	height: 100%;
	border-radius: 14px;
	overflow: hidden;
	box-shadow: 0 10px 24px rgba(0,0,0,0.35);
	background: rgba(10,10,10,0.9);
	border: 1px solid rgba(255,255,255,0.08);
	transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.inicio-catalogo-card:hover{
	transform: translateY(-4px);
	box-shadow: 0 16px 40px rgba(0,0,0,0.6);
	border-color: rgba(255,255,255,0.35);
}
.inicio-catalogo-card .card-image img{
	object-fit: cover;
}
.inicio-catalogo-card .card-content{
	padding: 0.8rem 0.9rem 0.9rem;
}
.inicio-catalogo-card .title{
	color: #fff;
}
.inicio-catalogo-card .has-text-grey-light,
.inicio-catalogo-card .has-text-grey-lighter{
	color: rgba(255,255,255,0.75) !important;
}

/* Estilos del Probador Virtual */
.inicio-tryon-btn{
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border: none;
	padding: 1.25rem 2.5rem;
	font-weight: 700;
	box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
	transition: all 0.3s;
}
.inicio-tryon-btn:hover{
	transform: translateY(-3px);
	box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
}

.tryon-modal-card{
	max-width: 1200px;
	width: 95%;
	max-height: 90vh;
	overflow-y: auto;
}

.tryon-upload-section, .tryon-products-section, .tryon-result-section{
	padding: 1rem;
	background: #f8f9fa;
	border-radius: 8px;
}

.tryon-preview-img{
	max-width: 100%;
	border-radius: 8px;
	box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.tryon-products-grid{
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
	gap: 1rem;
	max-height: 400px;
	overflow-y: auto;
	padding: 0.5rem;
}

.tryon-product-item{
	cursor: pointer;
	padding: 0.5rem;
	border: 2px solid transparent;
	border-radius: 8px;
	transition: all 0.3s;
	text-align: center;
	background: white;
}
.tryon-product-item:hover{
	border-color: #667eea;
	transform: translateY(-3px);
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.tryon-product-item.selected{
	border-color: #667eea;
	background: #f0f4ff;
}
.tryon-product-item img{
	width: 100%;
	height: 120px;
	object-fit: cover;
	border-radius: 4px;
}
.tryon-product-name{
	font-size: 0.85rem;
	font-weight: 600;
	margin-top: 0.5rem;
	color: #333;
}
.tryon-product-price{
	font-size: 0.75rem;
	color: #667eea;
	font-weight: 700;
}

.tryon-result-box{
	min-height: 400px;
	background: white;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}
.tryon-placeholder{
	text-align: center;
	color: #999;
	padding: 2rem;
}
.tryon-result-box img{
	max-width: 100%;
	max-height: 500px;
	border-radius: 8px;
}

#btnProcesar:disabled{
	opacity: 0.5;
	cursor: not-allowed;
}
/* Botón flotante inferior izquierdo */
.inicio-tryon-floating{
	position: fixed;
	bottom: 1.5rem;
	left: 1.5rem;
	z-index: 50;
	display: flex;
	align-items: center;
	gap: .6rem;
	padding: 1rem 1.8rem;
	border-radius: 999px;
	border: none;
	font-weight: 700;
	font-size: 0.9rem;
	color: #fff;
	cursor: pointer;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	box-shadow: 0 10px 30px rgba(102,126,234,0.4);
	transition: all 0.3s ease;
}

.inicio-tryon-floating:hover{
	transform: translateY(-4px) scale(1.03);
	box-shadow: 0 18px 45px rgba(102,126,234,0.6);
}
.inicio-categorias-wrapper{
	position: relative;
}

.inicio-categorias-dropdown{
	position: absolute;
	top: calc(100% + .5rem);
	left: 0;
	z-index: 60;
	background: rgba(15,15,15,0.95);
	border-radius: 12px;
	padding: 1rem;
	min-width: 220px;
	max-width: 92vw;
	display: none;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: .6rem;
	max-height: calc(100vh - 7rem);
	overflow: auto;
	-webkit-overflow-scrolling: touch;
	overscroll-behavior: contain;
	box-shadow: 0 10px 30px rgba(0,0,0,0.6);
}

/* Tallas: mostrar en 2 filas (para que no quede tan alto) */
#tallasDropdown.inicio-categorias-dropdown{
	grid-template-rows: repeat(2, minmax(0, auto));
	grid-template-columns: none;
	grid-auto-flow: column;
	grid-auto-columns: minmax(88px, max-content);
	min-width: 260px;
}

/* Si alguna vista devuelve <ul>, también lo acomodamos en filas/columnas */
.inicio-categorias-dropdown ul{
	list-style: none;
	padding: 0;
	margin: 0;
	display: contents;
}

.inicio-categorias-dropdown li{
	margin-bottom: 0;
}

.inicio-categorias-dropdown a{
	color: #fff;
	text-decoration: none;
	font-size: .9rem;
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
	white-space: normal;
	line-height: 1.2;
	padding: .55rem .75rem;
	border-radius: 10px;
	background: rgba(255,255,255,.06);
	border: 1px solid rgba(255,255,255,.12);
	transition: 0.2s;
}

/* Bulma: .dropdown-item suele ser block (vertical). Lo sobrescribimos solo aquí */
.inicio-categorias-dropdown .dropdown-item{
	display: flex;
	width: 100%;
}

.inicio-categorias-dropdown a:hover{
	color: #667eea;
	background: rgba(102,126,234,.12);
	border-color: rgba(102,126,234,.35);
}
</style>

<script>
const BASE_URL = "<?php echo rtrim(APP_URL, '/'); ?>";
let productoSeleccionado = null;
let fotoSubida = false;

// Auto-scroll del catálogo por categoría
document.addEventListener('DOMContentLoaded', function(){
	const filas = document.querySelectorAll('.inicio-catalogo-row[data-autoscroll="true"]');
	filas.forEach(function(fila){
		let scrollPos = 0;
		const paso = 1;
		const intervalo = 25;

		if(fila.scrollWidth <= fila.clientWidth){
			return;
		}
		setInterval(function(){
			if(fila.scrollWidth <= fila.clientWidth){
				return;
			}
			scrollPos += paso;
			if(scrollPos >= (fila.scrollWidth - fila.clientWidth)){
				scrollPos = 0;
			}
			fila.scrollLeft = scrollPos;
		}, intervalo);
	});
});

// Abrir modal del probador
function abrirProbador(){
	document.getElementById('tryonModal').classList.add('is-active');
	cargarProductos();
}

// Cerrar modal
function cerrarProbador(){
	document.getElementById('tryonModal').classList.remove('is-active');

	document.getElementById('fotoPersona').value = '';
	document.getElementById('fileName').textContent = 'Ningún archivo seleccionado';
	document.getElementById('previewContainer').style.display = 'none';

	document.getElementById('resultadoContainer').innerHTML =
	'<div class="tryon-placeholder"><i class="fas fa-image fa-5x"></i><p class="mt-4">El resultado aparecerá aquí</p></div>';

	productoSeleccionado = null;
	fotoSubida = false;
	document.getElementById('btnProcesar').disabled = true;
}

// Preview de foto subida
function previewFoto(input){
	if(input.files && input.files[0]){
		const file = input.files[0];

		// Validar tamaño máximo 5MB
		if(file.size > 5 * 1024 * 1024){
			alert("La imagen no debe superar 5MB");
			input.value = "";
			return;
		}

		// Validar tipo real
		if(!["image/jpeg","image/png"].includes(file.type)){
			alert("Solo se permiten imágenes JPG o PNG");
			input.value = "";
			return;
		}

		const reader = new FileReader();
		reader.onload = function(e){
			document.getElementById('previewFoto').src = e.target.result;
			document.getElementById('previewContainer').style.display = 'block';
			document.getElementById('fileName').textContent = file.name;
			fotoSubida = true;
			verificarBoton();
		};
		reader.readAsDataURL(file);
	}
}

// Cargar productos
function cargarProductos(){

fetch(BASE_URL + "/app/ajax/tryonAjax.php", {
	method: 'POST',
	headers: {
		'Content-Type': 'application/x-www-form-urlencoded',
	},
	body: 'modulo_tryon=obtenerProductos'
})
.then(response => {
	if(!response.ok){
		throw new Error("Error en la respuesta del servidor");
	}
	return response.text();
})
.then(html => {

	const container = document.getElementById('productosContainer');
	container.innerHTML = html;

	document.querySelectorAll('.tryon-product-item').forEach(item => {
		item.addEventListener('click', function(){

			document.querySelectorAll('.tryon-product-item')
				.forEach(i => i.classList.remove('selected'));

			this.classList.add('selected');

			productoSeleccionado = this.dataset.productId;
			verificarBoton();
		});
	});
})
.catch(error => {

	console.error('Error al cargar productos:', error);

	document.getElementById('productosContainer').innerHTML = `
		<p class="has-text-danger">
			<i class="fas fa-exclamation-triangle"></i> 
			Error al cargar productos. Recarga la página.
		</p>
	`;
});
}

// Verificar si se puede procesar
function verificarBoton(){
	const btn = document.getElementById('btnProcesar');
	if(fotoSubida && productoSeleccionado){
		btn.disabled = false;
	}else{
		btn.disabled = true;
	}
}

// Procesar try-on
function procesarTryOn(){

if(!fotoSubida || !productoSeleccionado){
	alert('Por favor sube una foto y selecciona un vestido');
	return;
}

const formData = new FormData();
formData.append('modulo_tryon', 'procesar');
formData.append('foto_persona', document.getElementById('fotoPersona').files[0]);
formData.append('producto_id', productoSeleccionado);

const btn = document.getElementById('btnProcesar');
btn.disabled = true;
btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> &nbsp; Procesando con IA...';

fetch(BASE_URL + "/app/ajax/tryonAjax.php", {
	method: 'POST',
	body: formData
})
.then(response => {

	if(!response.ok){
		throw new Error("Error del servidor (" + response.status + ")");
	}

	return response.text();
})
.then(text => {

	try{
		return JSON.parse(text);
	}catch(e){
		console.error("Respuesta no válida:", text);
		throw new Error("El servidor devolvió una respuesta inválida");
	}

})
.then(data => {

	btn.disabled = false;
	btn.innerHTML = '<i class="fas fa-magic"></i> &nbsp; Aplicar Vestido con IA';

	// ✅ CONDICIÓN CORRECTA
	if(data.tipo === 'success' && data.resultado_url){

		document.getElementById('resultadoContainer').innerHTML =
			'<img src="'+data.resultado_url+'?t='+Date.now()+'" alt="Resultado">';

		if(typeof Swal !== 'undefined'){
			Swal.fire({
				title: data.titulo || '¡Listo!',
				text: data.texto || 'El vestido ha sido aplicado exitosamente',
				icon: 'success',
				confirmButtonText: 'Aceptar'
			});
		}else{
			alert(data.titulo || '¡Listo!');
		}

	}else{

		const mensajeError = data.texto || data.mensaje || 'Ocurrió un error al procesar';

		if(typeof Swal !== 'undefined'){
			Swal.fire({
				title: data.titulo || 'Error',
				text: mensajeError,
				icon: 'error',
				confirmButtonText: 'Aceptar'
			});
		}else{
			alert('Error: ' + mensajeError);
		}
	}
})
.catch(error => {

	btn.disabled = false;
	btn.innerHTML = '<i class="fas fa-magic"></i> &nbsp; Aplicar Vestido con IA';

	console.error('Error completo:', error);

	const mensaje = error.message || 'Error al procesar. Intenta nuevamente.';

	if(typeof Swal !== 'undefined'){
		Swal.fire({
			title: 'Error',
			text: mensaje,
			icon: 'error',
			confirmButtonText: 'Aceptar'
		});
	}else{
		alert('Error: ' + mensaje);
	}
});
function fetchConTimeout(url, options, timeout = 30000){
	return Promise.race([
		fetch(url, options),
		new Promise((_, reject) =>
			setTimeout(() => reject(new Error("La IA tardó demasiado en responder")), timeout)
		)
	]);
}
}
document.addEventListener("DOMContentLoaded", function(){
	cargarProductos();
});
function toggleCategorias(){
	const dropdown = document.getElementById('categoriasDropdown');
	ajustarDropdownToViewport(dropdown);
	if(dropdown.style.display === 'grid'){
		dropdown.style.display = 'none';
	}else{
		dropdown.style.display = 'grid';
		requestAnimationFrame(() => ajustarDropdownToViewport(dropdown));
	}
}

function toggleTallas(){
	const dropdown = document.getElementById('tallasDropdown');
	if(!dropdown){
		return;
	}
	ajustarDropdownToViewport(dropdown);
	if(dropdown.style.display === 'grid'){
		dropdown.style.display = 'none';
	}else{
		dropdown.style.display = 'grid';
		requestAnimationFrame(() => ajustarDropdownToViewport(dropdown));
	}
}

function ajustarDropdownToViewport(dropdown){
	if(!dropdown) return;
	// Solo tiene sentido si está visible o por mostrarse
	const prevDisplay = dropdown.style.display;
	if(prevDisplay === 'none' || prevDisplay === ''){
		// No forzamos display aquí para no afectar el toggle; el RAF posterior ajusta.
		return;
	}
	const rect = dropdown.getBoundingClientRect();
	const padding = 16;
	const maxH = Math.max(180, window.innerHeight - rect.top - padding);
	dropdown.style.maxHeight = maxH + 'px';
	dropdown.style.overflow = 'auto';
}

window.addEventListener('resize', () => {
	const cd = document.getElementById('categoriasDropdown');
	if(cd && cd.style.display === 'grid') ajustarDropdownToViewport(cd);
	const td = document.getElementById('tallasDropdown');
	if(td && td.style.display === 'grid') ajustarDropdownToViewport(td);
});

// Cerrar si se hace click afuera
document.addEventListener('click', function(e){
	const dropdownCategorias = document.getElementById('categoriasDropdown');
	if(dropdownCategorias){
		const wrapperCategorias = dropdownCategorias.closest('.inicio-categorias-wrapper');
		if(wrapperCategorias && !wrapperCategorias.contains(e.target)){
			dropdownCategorias.style.display = 'none';
		}
	}

	const dropdownTallas = document.getElementById('tallasDropdown');
	if(dropdownTallas){
		const wrapperTallas = dropdownTallas.closest('.inicio-categorias-wrapper');
		if(wrapperTallas && !wrapperTallas.contains(e.target)){
			dropdownTallas.style.display = 'none';
		}
	}
});
</script>

<?php if(defined('AGENTE_IA_ENABLED') && AGENTE_IA_ENABLED === true){ ?>
	<script>
		// Base URL de la app para que el widget arme rutas internas
		window.APP_URL = <?php echo json_encode(APP_URL, JSON_UNESCAPED_SLASHES); ?>;
	</script>
	<script src="<?php echo APP_URL; ?>agente_ia/agent.js"></script>
<?php } ?>