<?php
	// Vista pública (cliente): personalización por pasos (wizard)
	$clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
	$redirectTo = 'telasCliente/';
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
	<h1 class="title has-text-centered">Personaliza tu vestido</h1>
	<p class="has-text-centered mb-5">
		<?php if($clienteLogueado){ ?>
			<?php echo htmlspecialchars($_SESSION['cliente_nombre']." ".($_SESSION['cliente_apellido'] ?? '')); ?>, completa los pasos para agendar tu cita.
		<?php }else{ ?>
			Completa los pasos. Para enviar la solicitud necesitas iniciar sesión.
		<?php } ?>
	</p>

	<div class="tabs is-boxed is-fullwidth" id="wizardTabs">
		<ul>
			<li class="is-active" data-step="1"><a><span class="has-text-weight-semibold">Paso 1</span>&nbsp;Vestido</a></li>
			<li data-step="2"><a><span class="has-text-weight-semibold">Paso 2</span>&nbsp;Telas</a></li>
			<li data-step="3"><a><span class="has-text-weight-semibold">Paso 3</span>&nbsp;Encaje</a></li>
			<li data-step="4"><a><span class="has-text-weight-semibold">Paso 4</span>&nbsp;Cita</a></li>
		</ul>
	</div>

	<div id="wizardMsg" class="notification is-light" style="display:none;"></div>

	<!-- Paso 1: Vestido personalizado (placeholder) -->
	<section id="wizardStep1" class="wizard-step">
		<div class="box">
			<h2 class="subtitle">Paso 1: Elección de vestido personalizado</h2>
			<article class="message is-info is-light">
				<div class="message-body">
					Esta parte está en preparación. Por ahora puedes describir tu idea para que la dueña/admin la reciba.
				</div>
			</article>

			<div class="field">
				<label class="label">Describe tu vestido (opcional)</label>
				<div class="control">
					<textarea id="vestidoDetalle" class="textarea" rows="4" placeholder="Ej: color, estilo, largo, manga, referencia..."></textarea>
				</div>
				<p class="help">Esto se enviará junto con tu cita.</p>
			</div>

			<div class="buttons is-right">
				<button type="button" class="button is-link is-rounded" data-next-step="2">Continuar</button>
			</div>
		</div>
	</section>

	<!-- Paso 2: Telas (existente) -->
	<section id="wizardStep2" class="wizard-step" style="display:none;">
		<div class="columns is-variable is-5">
			<div class="column is-7">
				<div class="box">
					<div class="is-flex is-justify-content-space-between is-align-items-center">
						<h2 class="subtitle" style="margin-bottom:0;"><i class="fas fa-cube"></i> &nbsp; Vestido en 3D</h2>
						<button
							id="openDressPreviewModal"
							type="button"
							class="button is-link is-light is-rounded is-small js-modal-trigger"
							data-target="modalDressPreview"
						>
							Ver grande
						</button>
					</div>
					<div id="dress3dContainer" style="width:100%; min-height:420px;">
						<div class="notification is-light">
							Aquí se aplicará la tela seleccionada al modelo 3D.
						</div>
						<canvas id="dress3dCanvas" style="width:100%; height:360px; display:block;"></canvas>
					</div>
				</div>
			</div>

			<div class="column is-5">
				<div class="box">
					<h2 class="subtitle"><i class="fas fa-layer-group"></i> &nbsp; Tipos de tela (precio por metro)</h2>

					<div id="telasEstado" class="notification is-info is-light" style="display:none;"></div>
					<div id="telasList"></div>

					<hr>
					<div class="is-flex is-justify-content-space-between is-align-items-center">
						<h3 class="subtitle is-6" style="margin-bottom:0;">Previsualización 3D de la tela</h3>
						<button
							id="openFabricPreviewModal"
							type="button"
							class="button is-link is-light is-rounded is-small js-modal-trigger"
							data-target="modalFabricPreview"
						>
							Ver grande
						</button>
					</div>
					<div class="fabric-preview-wrap mt-3">
						<canvas id="fabricPreviewCanvas" style="width:100%; display:block;"></canvas>
					</div>

					<p class="mt-4 mb-2">
						<strong>Metros estimados:</strong>
						<span id="telaMetrosTexto">—</span>
					</p>
					<p class="mb-4">
						<strong>Total (metros × precio por metro):</strong>
						<span id="telaTotalTexto">—</span>
					</p>

					<div class="field">
						<label class="label" style="margin-bottom:0.35rem;">Talla</label>
						<div class="select is-fullwidth">
							<select id="tallaVestido" name="talla_vestido">
								<option value="XS">XS</option>
								<option value="S">S</option>
								<option value="M" selected>M</option>
								<option value="L">L</option>
								<option value="XL">XL</option>
								<option value="XXL">XXL</option>
							</select>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="buttons is-right">
			<button type="button" class="button is-light is-rounded" data-prev-step="1">Atrás</button>
			<button type="button" class="button is-link is-rounded" data-next-step="3">Continuar</button>
		</div>
	</section>

	<!-- Paso 3: Encaje (carrusel) -->
	<section id="wizardStep3" class="wizard-step" style="display:none;">
		<div class="box">
			<h2 class="subtitle">Paso 3: Selección de encaje (precio por metro y medio)</h2>
			<p class="has-text-grey mb-4">Elige el tipo de encaje para el diseño de arriba.</p>

			<div class="encaje-carousel-toolbar">
				<button type="button" class="button is-light is-rounded" id="encajePrev">&larr;</button>
				<button type="button" class="button is-light is-rounded" id="encajeNext">&rarr;</button>
			</div>
			<div id="encajeCarousel" class="encaje-carousel" aria-label="Carrusel de encajes"></div>

			<hr>
			<p class="mb-1"><strong>Encaje seleccionado:</strong> <span id="encajeSeleccionTexto">—</span></p>
			<p class="has-text-grey is-size-7">Los encajes se administran desde el panel (Encajes) y las fotos se guardan en <code>app/views/fotos/encajes/</code>.</p>

			<div class="buttons is-right mt-4">
				<button type="button" class="button is-light is-rounded" data-prev-step="2">Atrás</button>
				<button type="button" class="button is-link is-rounded" data-next-step="4">Continuar</button>
			</div>
		</div>
	</section>

	<!-- Paso 4: Cita + envío al admin -->
	<section id="wizardStep4" class="wizard-step" style="display:none;">
		<div class="box">
			<h2 class="subtitle">Paso 4: Selecciona tu cita</h2>

			<?php if(!$clienteLogueado){ ?>
				<article class="message is-warning">
					<div class="message-body">
						Debes iniciar sesión para enviar la solicitud.
						<div class="buttons mt-3">
							<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>clienteLogin/?redirect_to=<?php echo urlencode($redirectTo); ?>">Iniciar sesión</a>
							<a class="button is-info is-rounded" href="<?php echo APP_URL; ?>registroCliente/?redirect_to=<?php echo urlencode($redirectTo); ?>">Registrarme</a>
						</div>
					</div>
				</article>
			<?php } ?>

			<div class="columns is-multiline">
				<div class="column is-6">
					<div class="field">
						<label class="label">Fecha de cita</label>
						<div class="control">
							<input id="cita_fecha_personalizada" class="input" type="date" <?php echo $clienteLogueado ? 'required' : ''; ?> >
						</div>
						<p class="help">Disponible de lunes a sábado (no domingos ni feriados).</p>
					</div>
				</div>
				<div class="column is-6">
					<div class="field">
						<label class="label">Hora</label>
						<div class="control">
							<div class="select is-fullwidth">
								<select id="cita_hora_personalizada" <?php echo $clienteLogueado ? 'required' : ''; ?> disabled>
									<option value="">Selecciona una fecha primero</option>
								</select>
							</div>
						</div>
						<p id="cita_help_personalizada" class="help">Horario: 10:00 am a 07:00 pm</p>
					</div>
				</div>
			</div>

			<hr>
			<h3 class="title is-6">Resumen</h3>
			<div class="content">
				<ul>
					<li><strong>Talla:</strong> <span id="resumenTalla">—</span></li>
					<li><strong>Tela:</strong> <span id="resumenTela">—</span></li>
					<li><strong>Encaje:</strong> <span id="resumenEncaje">—</span></li>
				</ul>
			</div>

			<div class="buttons is-right">
				<button type="button" class="button is-light is-rounded" data-prev-step="3">Atrás</button>
				<button id="btnEnviarSolicitud" type="button" class="button is-danger is-rounded" <?php echo $clienteLogueado ? '' : 'disabled'; ?> >
					Enviar solicitud al administrador
				</button>
			</div>
			<p class="help">Se enviará: detalle del vestido, tela, encaje y tu cita.</p>
		</div>
	</section>
</div>

<!-- Modal: Previsualización 3D grande -->
<div id="modalFabricPreview" class="modal">
	<div class="modal-background"></div>
	<div class="modal-card" style="width: min(92vw, 980px);">
		<header class="modal-card-head">
			<p class="modal-card-title">Previsualización 3D de la tela</p>
			<button class="delete" aria-label="close"></button>
		</header>
		<section class="modal-card-body">
			<canvas id="fabricPreviewCanvasModal" class="fabric-preview-canvas-modal"></canvas>
		</section>
		<footer class="modal-card-foot" style="justify-content:flex-end;">
			<button class="button is-link is-light is-rounded">Cerrar</button>
		</footer>
	</div>
</div>

<!-- Modal: Vestido 3D grande -->
<div id="modalDressPreview" class="modal">
	<div class="modal-background"></div>
	<div class="modal-card" style="width: min(92vw, 980px);">
		<header class="modal-card-head">
			<p class="modal-card-title">Vestido en 3D</p>
			<button class="delete" aria-label="close"></button>
		</header>
		<section class="modal-card-body">
			<canvas id="dress3dCanvasModal" class="fabric-preview-canvas-modal"></canvas>
		</section>
		<footer class="modal-card-foot" style="justify-content:flex-end;">
			<button class="button is-link is-light is-rounded">Cerrar</button>
		</footer>
	</div>
</div>

<script>
	window.APP_URL = "<?php echo APP_URL; ?>";
	window.MONEDA_SIMBOLO = "<?php echo MONEDA_SIMBOLO; ?>";
	window.CLIENTE_LOGUEADO = <?php echo $clienteLogueado ? 'true' : 'false'; ?>;
</script>

<script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/js/telasCliente.js"></script>

<style>
.wizard-step{ scroll-margin-top: 1rem; }
.encaje-carousel-toolbar{ display:flex; gap: .5rem; justify-content:flex-end; margin-bottom: .75rem; }
.encaje-carousel{ display:flex; gap: 1rem; overflow-x: auto; padding-bottom: .75rem; scroll-snap-type: x mandatory; }
.encaje-card{ min-width: 240px; max-width: 240px; scroll-snap-align: start; }
.encaje-card .image img{ object-fit: cover; height: 140px; width: 100%; }
</style>
