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

			<a class="button is-medium is-rounded has-text-weight-semibold is-warning inicio-btn-gold" href="<?php echo APP_URL; ?>tablaTallas/" aria-label="Ver tabla de tallas en 3D">
				<span class="icon">
					<i class="fas fa-ruler-combined" aria-hidden="true"></i>
				</span>
				<span>Tabla de tallas</span>
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
				<a class="inicio-login-btn js-cliente-auth-open" href="<?php echo APP_URL; ?>clienteLogin/?redirect_to=productosCliente/" data-auth-intent="login" data-redirect-to="productosCliente/">
					<i class="fas fa-sign-in-alt"></i>
					<span>Iniciar sesión</span>
				</a>

				<a class="inicio-register-btn js-cliente-auth-open" href="<?php echo APP_URL; ?>registroCliente/?redirect_to=productosCliente/" data-auth-intent="register" data-redirect-to="productosCliente/">
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

		<!-- CATÁLOGO (carrusel) -->
		<section class="inicio-catalogo">
			<div class="container">
				<?php echo $insProductoInicio->catalogoInicioHTMLControlador(); ?>
			</div>
		</section>
	</main>

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

</div>

<?php
	if(!$clienteLogueado){
		$cliente_auth_redirect_to = 'inicio/';
		require_once "./app/views/inc/cliente_auth_modal.php";
	}
?>

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
	/* Sin margen externo */
	margin: 0 !important;

	/* Tamaño consistente */
	min-height: 46px;
	padding: 0 1.3rem;

	/* Bordes suaves */
	border-radius: 14px !important;
	position: relative;
	overflow: hidden;

	/* Sin fondo (solo texto con efecto) */
	background: transparent !important;

	border: 1px solid rgba(255, 221, 150, 0.70);
	box-shadow:
		0 14px 36px rgba(0,0,0,0.34),
		0 0 0 1px rgba(255, 208, 120, 0.10),
		0 0 22px rgba(255, 200, 90, 0.28);
	backdrop-filter: blur(6px);

	transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.inicio-btn-gold .icon{
	display: inline-flex;
	align-items: center;
}

/* Icono en dorado sólido (FontAwesome usa ::before, no soporta bien background-clip) */
.inicio-btn-gold .icon i{
	color: #ffe08a !important;
	filter:
		drop-shadow(0 1px 0 rgba(0,0,0,0.25))
		drop-shadow(0 0 10px rgba(255, 205, 92, 0.28));
}

/* Efecto solo en el texto (sin fondo) */
.inicio-btn-gold span:not(.icon){
	display: inline-block;
	background-image:
		linear-gradient(180deg,
			#fff4cf 0%,
			#ffe08a 18%,
			#f2c24a 40%,
			#d9a61d 60%,
			#b57f00 82%,
			#ffe6a0 100%
		),
		linear-gradient(90deg,
			rgba(255,255,255,0.0) 0%,
			rgba(255,255,255,0.95) 50%,
			rgba(255,255,255,0.0) 80%
		);
	background-size: 100% 100%, 38% 100%;
	background-position: 0 0, -60% 0;
	-webkit-background-clip: text;
	background-clip: text;
	color: transparent !important;
	-webkit-text-fill-color: transparent;
	filter:
		drop-shadow(0 1px 0 rgba(0,0,0,0.25))
		drop-shadow(0 0 10px rgba(255, 205, 92, 0.28));
	animation: inicioGoldTextSweep 2.2s linear infinite;
}

@keyframes inicioGoldTextSweep{
	0%{ background-position: 0 0, -60% 0; }
	100%{ background-position: 0 0, 160% 0; }
}

@media (prefers-reduced-motion: reduce){
	.inicio-btn-gold span:not(.icon){ animation: none; }
	.inicio-btn-gold{ transition: none; }
}

.inicio-btn-gold:hover,
.inicio-btn-gold:focus{
	transform: translateY(-1px);
	border-color: rgba(255, 238, 190, 0.95);
	box-shadow:
		0 18px 46px rgba(0,0,0,0.40),
		0 0 0 1px rgba(255, 210, 120, 0.16),
		0 0 30px rgba(255, 205, 92, 0.45);
}

.inicio-btn-gold:active{
	transform: translateY(0px);
	box-shadow:
		0 10px 24px rgba(0,0,0,0.22),
		0 0 18px rgba(255, 205, 92, 0.30);
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
	flex-wrap: wrap;
	justify-content: flex-end;
	max-width: calc(100vw - 2rem);
}
.inicio-login-btn,
.inicio-register-btn{
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: .55rem;
	margin: 0;
	min-height: 46px;
	padding: 0 1.15rem;
	border-radius: 14px;
	position: relative;
	overflow: hidden;
	color: #fff;
	background: transparent;
	border: 1px solid rgba(255, 221, 150, 0.70);
	box-shadow:
		0 14px 36px rgba(0,0,0,0.34),
		0 0 0 1px rgba(255, 208, 120, 0.10),
		0 0 22px rgba(255, 200, 90, 0.28);
	backdrop-filter: blur(6px);
	-webkit-backdrop-filter: blur(6px);
	font-weight: 700;
	font-size: 0.85rem;
	letter-spacing: .04em;
	transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
}
.inicio-login-btn:hover,
.inicio-register-btn:hover{
	color: #fff;
	background: rgba(255,255,255,.06);
	transform: translateY(-1px);
	border-color: rgba(255, 221, 150, 0.88);
	box-shadow:
		0 16px 42px rgba(0,0,0,0.44),
		0 0 0 1px rgba(255, 208, 120, 0.16),
		0 0 28px rgba(255, 200, 90, 0.34);
}
.inicio-login-btn:focus-visible,
.inicio-register-btn:focus-visible{
	outline: 2px solid rgba(255, 221, 150, 0.75);
	outline-offset: 3px;
}
.inicio-login-btn i,
.inicio-register-btn i{
	margin-right: 0;
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
	padding: 3.25rem 2rem 2rem;
	text-align: center;
	width: 100%;
}
.inicio-titulo{
	margin: 1.15rem 0 0;
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
	margin: 1.6rem auto 0;
	font-size: 1.05rem;
	line-height: 1.8;
	color: rgba(255,255,255,.92);
	font-weight: 300;
	letter-spacing: 0.08em;
	animation: inicioTituloFade 1.2s ease-out 0.3s both;
}

/* Ubicación + mapa (derecha) */
.inicio-ubicacion{
	position: relative;
	z-index: 10;
	margin-top: 2.25rem;
	margin-left: auto;
	margin-right: auto;
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

/* Cuando el carrusel vive dentro del hero, evitamos el offset/padding extra */
.inicio-hero .inicio-catalogo{
	margin-top: 3.25rem;
	padding: 0;
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
	-webkit-overflow-scrolling: touch;
	overscroll-behavior-x: contain;
	cursor: grab;
}
.inicio-catalogo-row.is-dragging{
	cursor: grabbing;
	user-select: none;
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

// Auto-scroll del catálogo por categoría
document.addEventListener('DOMContentLoaded', function(){
	const filas = document.querySelectorAll('.inicio-catalogo-row[data-autoscroll="true"]');
	filas.forEach(function(fila){
		let scrollPos = 0;
		const paso = 1;
		const intervalo = 25;
		let paused = false;
		let resumeTimer = null;

		const pauseAuto = () => {
			paused = true;
			if(resumeTimer){
				clearTimeout(resumeTimer);
				resumeTimer = null;
			}
		};
		const resumeAutoSoon = () => {
			if(resumeTimer){
				clearTimeout(resumeTimer);
			}
			resumeTimer = setTimeout(function(){
				paused = false;
			}, 1800);
		};

		if(fila.scrollWidth <= fila.clientWidth){
			return;
		}

		// Scroll con rueda (vertical -> horizontal)
		fila.addEventListener('wheel', function(e){
			const absX = Math.abs(e.deltaX || 0);
			const absY = Math.abs(e.deltaY || 0);
			if(absY > absX){
				pauseAuto();
				fila.scrollLeft += e.deltaY;
				e.preventDefault();
				resumeAutoSoon();
			}
		}, { passive: false });

		// Drag-to-scroll (mouse/touch)
		let isDown = false;
		let startX = 0;
		let startScrollLeft = 0;
		let moved = false;
		let isDragging = false;
		let suppressClickUntil = 0;
		let capturedPointerId = null;

		fila.addEventListener('pointerdown', function(e){
			if(e.pointerType === 'mouse' && e.button !== 0) return;
			isDown = true;
			moved = false;
			isDragging = false;
			capturedPointerId = null;
			startX = e.clientX;
			startScrollLeft = fila.scrollLeft;
			pauseAuto();
		});

		fila.addEventListener('pointermove', function(e){
			if(!isDown) return;
			const dx = e.clientX - startX;

			// No bloquear el click: solo empezar el arrastre cuando el movimiento supera el umbral
			if(!isDragging){
				if(Math.abs(dx) <= 6){
					return;
				}
				isDragging = true;
				moved = true;
				fila.classList.add('is-dragging');
				try{
					fila.setPointerCapture(e.pointerId);
					capturedPointerId = e.pointerId;
				}catch(err){}
			}

			fila.scrollLeft = startScrollLeft - dx;
			e.preventDefault();
		});

		const endDrag = function(e){
			if(!isDown) return;
			isDown = false;
			fila.classList.remove('is-dragging');
			if(moved){
				suppressClickUntil = Date.now() + 350;
			}
			isDragging = false;
			resumeAutoSoon();
			if(capturedPointerId !== null){
				try{ fila.releasePointerCapture(capturedPointerId); }catch(err){}
				capturedPointerId = null;
			}
		};
		fila.addEventListener('pointerup', endDrag);
		fila.addEventListener('pointercancel', endDrag);
		fila.addEventListener('pointerleave', function(){
			if(isDown){
				isDown = false;
				fila.classList.remove('is-dragging');
				resumeAutoSoon();
			}
		});

		fila.addEventListener('click', function(e){
			if(Date.now() < suppressClickUntil){
				e.preventDefault();
				e.stopPropagation();
			}
		}, true);

		fila.addEventListener('mouseenter', pauseAuto);
		fila.addEventListener('mouseleave', resumeAutoSoon);
		fila.addEventListener('focusin', pauseAuto);
		fila.addEventListener('focusout', resumeAutoSoon);

		setInterval(function(){
			if(paused) return;
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