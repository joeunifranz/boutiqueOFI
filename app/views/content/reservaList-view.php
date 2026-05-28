<?php

$esAdmin = false;
if(isset($_SESSION['rol']) && $_SESSION['rol']==="Administrador"){
    $esAdmin = true;
}elseif(isset($_SESSION['usuario']) && $_SESSION['usuario']==="Administrador"){
    $esAdmin = true;
}elseif(isset($_SESSION['id']) && (int)$_SESSION['id']===1){
    $esAdmin = true;
}

if(!$esAdmin){
    echo "<div class='has-text-centered mt-6'><article class='message is-danger'><div class='message-body'><strong>Acceso restringido</strong><br>Solo el administrador puede ver la lista de reservas.</div></article></div>";
    return;
}

?>

<div class="container is-fluid mb-6">
	<h1 class="title">Reservas</h1>
	<h2 class="subtitle"><i class="fas fa-clipboard-list fa-fw"></i> &nbsp; Lista de reservas</h2>
	<nav class="level mt-3">
		<div class="level-left">
			<div class="level-item">
				<button class="button is-dark is-rounded" type="button" id="btnOpenReservaQrScanner">
					<i class="fas fa-qrcode"></i> &nbsp; Escanear QR
				</button>
			</div>
		</div>
		<div class="level-right">
			<div class="level-item">
				<a class="button is-link is-light is-rounded" href="<?php echo APP_URL; ?>exportarReservas/">
					<i class="fas fa-file-pdf"></i> &nbsp; Exportar PDF
				</a>
			</div>
		</div>
	</nav>
</div>

<!-- Modal: Escanear QR de reserva (cámara o imagen) -->
<div class="modal" id="modalReservaQrScanner">
	<div class="modal-background"></div>
	<div class="modal-card" style="width: min(92vw, 820px);">
		<header class="modal-card-head">
			<p class="modal-card-title">Escanear QR de reserva</p>
			<button class="delete" aria-label="close"></button>
		</header>
		<section class="modal-card-body">
			<article class="message is-info">
				<div class="message-body">
					Escanea con la cámara o sube una imagen del QR. Te llevaré al <strong>detalle</strong> de la reserva.
				</div>
			</article>

			<div class="columns is-variable is-5">
				<div class="column is-7">
					<div class="box">
						<p class="has-text-weight-semibold mb-2">Cámara</p>
						<video id="qrVideo" style="width:100%; border-radius: 10px; background: #111;" autoplay playsinline muted></video>
						<p class="help" id="qrVideoHelp">Presiona “Iniciar cámara”.</p>
						<div class="buttons mt-3">
							<button class="button is-success" type="button" id="btnStartQrCamera">Iniciar cámara</button>
							<button class="button is-light" type="button" id="btnStopQrCamera" disabled>Detener</button>
						</div>
					</div>
				</div>
				<div class="column is-5">
					<div class="box">
						<p class="has-text-weight-semibold mb-2">Subir imagen del QR</p>
						<div class="file has-name is-fullwidth">
							<label class="file-label">
								<input class="file-input" type="file" id="qrImageFile" accept="image/*">
								<span class="file-cta">
									<span class="file-icon"><i class="fas fa-upload"></i></span>
									<span class="file-label">Seleccionar imagen</span>
								</span>
								<span class="file-name" id="qrImageFileName">Ninguno</span>
							</label>
						</div>
						<p class="help">Funciona mejor con fotos nítidas y con buena luz.</p>
					</div>
					<div id="qrScanResult"></div>
				</div>
			</div>
		</section>
		<footer class="modal-card-foot" style="justify-content:flex-end;">
			<button class="button is-light is-rounded" type="button" id="btnCloseReservaQrScanner">Cerrar</button>
		</footer>
	</div>
</div>

<div class="container pb-6 pt-6">
	<div class="form-rest mb-6 mt-6"></div>

	<?php
		$busqueda = isset($_GET['q']) ? (string)$_GET['q'] : '';
		$estado = isset($_GET['estado']) ? (string)$_GET['estado'] : '';
	?>

	<div class="box">
		<form method="GET" action="<?php echo APP_URL; ?>reservaList/">
			<div class="columns is-multiline is-vcentered">
				<div class="column is-6">
					<div class="field">
						<label class="label">Buscar</label>
						<div class="control">
							<input class="input" type="text" name="q" value="<?php echo htmlspecialchars($busqueda,ENT_QUOTES,'UTF-8'); ?>" placeholder="Código de reserva o nombre/apellido/email del cliente">
						</div>
					</div>
				</div>
				<div class="column is-3">
					<div class="field">
						<label class="label">Estado</label>
						<div class="control">
							<div class="select is-fullwidth">
								<select name="estado">
									<option value="" <?php echo ($estado==='' ? 'selected' : ''); ?>>(Todos)</option>
									<option value="pendiente" <?php echo ($estado==='pendiente' ? 'selected' : ''); ?>>pendiente</option>
									<option value="confirmada" <?php echo ($estado==='confirmada' ? 'selected' : ''); ?>>confirmada</option>
									<option value="reprogramada" <?php echo ($estado==='reprogramada' ? 'selected' : ''); ?>>reprogramada</option>
									<option value="completada" <?php echo ($estado==='completada' ? 'selected' : ''); ?>>completada</option>
									<option value="rechazada" <?php echo ($estado==='rechazada' ? 'selected' : ''); ?>>rechazada</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="column is-3 has-text-right">
					<label class="label">&nbsp;</label>
					<div class="buttons is-right">
						<button class="button is-link" type="submit"><i class="fas fa-search"></i> &nbsp; Buscar</button>
						<a class="button is-light" href="<?php echo APP_URL; ?>reservaList/"><i class="fas fa-eraser"></i> &nbsp; Limpiar</a>
					</div>
				</div>
			</div>
		</form>
	</div>

	<?php
		use app\controllers\reservationController;
		$insReserva = new reservationController();
		echo $insReserva->listarReservaControlador($url[1] ?? 1, 15, $url[0] ?? 'reservaList', $busqueda, $estado);
	?>
</div>

<?php include "./app/views/inc/comprobante_flotante.php"; ?>

<?php include "./app/views/inc/print_invoice_script.php"; ?>

<!-- Fallback QR decoder (cuando BarcodeDetector no existe). Se usa solo si hace falta. -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

<script>
(function(){
	var appUrl = <?php echo json_encode(APP_URL, JSON_UNESCAPED_SLASHES); ?>;

	var modal = document.getElementById('modalReservaQrScanner');
	var openBtn = document.getElementById('btnOpenReservaQrScanner');
	if(!modal || !openBtn) return;

	var closeBtn = document.getElementById('btnCloseReservaQrScanner');
	var closeX = modal.querySelector('.delete');
	var bg = modal.querySelector('.modal-background');
	var video = document.getElementById('qrVideo');
	var help = document.getElementById('qrVideoHelp');
	var btnStart = document.getElementById('btnStartQrCamera');
	var btnStop = document.getElementById('btnStopQrCamera');
	var fileInput = document.getElementById('qrImageFile');
	var fileName = document.getElementById('qrImageFileName');
	var resultEl = document.getElementById('qrScanResult');

	var stream = null;
	var scanning = false;
	var detector = null;
	var rafId = 0;
	var lastScanAt = 0;
	var canvas = null;
	var ctx = null;

	function setResult(type, msg){
		if(!resultEl) return;
		resultEl.innerHTML = '<article class="message is-'+type+'"><div class="message-body">'+msg+'</div></article>';
	}

	function open(){
		modal.classList.add('is-active');
		if(resultEl) resultEl.innerHTML = '';
	}
	function close(){
		stopCamera();
		modal.classList.remove('is-active');
	}

	function extractCodigo(text){
		text = (text || '').toString().trim();
		if(!text) return '';
		var m = text.match(/reserva(?:Confirmar|Detalle|Pagar|QR)\/([^\/\?#]+)\//i);
		if(m && m[1]){
			try{ return decodeURIComponent(m[1]); }catch(e){ return m[1]; }
		}
		if(/^[a-z0-9_-]{4,}$/i.test(text)){
			return text;
		}
		return '';
	}

	function goToDetalle(codigo){
		if(!codigo){
			setResult('danger', 'No pude leer el QR. Intenta nuevamente.');
			return;
		}
		window.location.href = appUrl + 'reservaDetalle/' + encodeURIComponent(codigo) + '/';
	}

	function hasBarcodeDetector(){
		return ('BarcodeDetector' in window);
	}

	async function ensureDetector(){
		if(detector) return detector;
		if(!hasBarcodeDetector()){
			throw new Error('barcode_detector_not_supported');
		}
		detector = new window.BarcodeDetector({ formats: ['qr_code'] });
		return detector;
	}

	function ensureCanvas(){
		if(canvas && ctx) return;
		canvas = document.createElement('canvas');
		ctx = canvas.getContext('2d', { willReadFrequently: true });
	}

	function decodeWithJsQRFromImageData(imageData){
		try{
			if(typeof window.jsQR !== 'function') return '';
			var out = window.jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
			return (out && out.data) ? String(out.data) : '';
		}catch(e){
			return '';
		}
	}

	async function startCamera(){
		if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
			setResult('warning', 'Tu navegador no permite cámara aquí. Nota: la cámara requiere https o http://localhost y permisos.');
			return;
		}
		if(!hasBarcodeDetector() && typeof window.jsQR !== 'function'){
			setResult('warning', 'No hay lector QR disponible. Actualiza Edge/Chrome o habilita soporte QR.');
			return;
		}

		try{
			setResult('info', 'Abriendo cámara...');
			stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
			video.srcObject = stream;
			await video.play();
			scanning = true;
			lastScanAt = 0;
			btnStop.disabled = false;
			btnStart.disabled = true;
			if(help) help.textContent = 'Apunta al QR. Se detecta automáticamente.';
			scanLoop();
		}catch(err){
			setResult('danger', 'No se pudo abrir la cámara. Revisa permisos del navegador.');
		}
	}

	function stopCamera(){
		scanning = false;
		if(rafId){
			try{ cancelAnimationFrame(rafId); }catch(e){}
			rafId = 0;
		}
		btnStop.disabled = true;
		btnStart.disabled = false;
		if(help) help.textContent = 'Presiona “Iniciar cámara”.';
		if(stream){
			try{
				stream.getTracks().forEach(function(t){ t.stop(); });
			}catch(e){}
		}
		stream = null;
		if(video){
			video.pause();
			video.srcObject = null;
		}
	}

	async function scanLoop(){
		if(!scanning) return;
		// throttle (≈ 8 fps) para no cargar CPU en fallback jsQR
		var now = Date.now();
		if(lastScanAt && (now - lastScanAt) < 120){
			rafId = requestAnimationFrame(scanLoop);
			return;
		}
		lastScanAt = now;
		try{
			if(hasBarcodeDetector()){
				var det = await ensureDetector();
				var codes = await det.detect(video);
				if(Array.isArray(codes) && codes.length){
					var raw = codes[0].rawValue || '';
					var codigo = extractCodigo(raw);
					stopCamera();
					goToDetalle(codigo);
					return;
				}
			}else{
				ensureCanvas();
				var vw = video.videoWidth || 0;
				var vh = video.videoHeight || 0;
				if(vw > 0 && vh > 0){
					canvas.width = vw;
					canvas.height = vh;
					ctx.drawImage(video, 0, 0, vw, vh);
					var imgData = ctx.getImageData(0, 0, vw, vh);
					var raw2 = decodeWithJsQRFromImageData(imgData);
					if(raw2){
						var codigo2 = extractCodigo(raw2);
						stopCamera();
						goToDetalle(codigo2);
						return;
					}
				}
			}
		}catch(e){
			// ignorar y seguir
		}
		rafId = requestAnimationFrame(scanLoop);
	}

	async function scanImageFile(file){
		try{
			setResult('info', 'Leyendo imagen...');
			// Preferir BarcodeDetector si existe
			if(hasBarcodeDetector()){
				await ensureDetector();
				var bitmap = await createImageBitmap(file);
				var codes = await detector.detect(bitmap);
				if(Array.isArray(codes) && codes.length){
					var raw = codes[0].rawValue || '';
					var codigo = extractCodigo(raw);
					goToDetalle(codigo);
					return;
				}
				setResult('danger', 'No pude detectar un QR en la imagen.');
				return;
			}

			// Fallback jsQR
			if(typeof window.jsQR !== 'function'){
				setResult('warning', 'Tu navegador no soporta BarcodeDetector y no se pudo cargar el fallback (jsQR). Revisa conexión o actualiza el navegador.');
				return;
			}

			ensureCanvas();
			var dataUrl = await new Promise(function(resolve, reject){
				var reader = new FileReader();
				reader.onload = function(){ resolve(String(reader.result || '')); };
				reader.onerror = function(){ reject(new Error('read_failed')); };
				reader.readAsDataURL(file);
			});
			var img = await new Promise(function(resolve, reject){
				var im = new Image();
				im.onload = function(){ resolve(im); };
				im.onerror = function(){ reject(new Error('img_load_failed')); };
				im.src = dataUrl;
			});
			var w = img.naturalWidth || img.width || 0;
			var h = img.naturalHeight || img.height || 0;
			if(w<=0 || h<=0){
				setResult('danger', 'No se pudo leer la imagen.');
				return;
			}
			canvas.width = w;
			canvas.height = h;
			ctx.drawImage(img, 0, 0, w, h);
			var imgData = ctx.getImageData(0, 0, w, h);
			var raw2 = decodeWithJsQRFromImageData(imgData);
			if(raw2){
				var codigo2 = extractCodigo(raw2);
				goToDetalle(codigo2);
			}else{
				setResult('danger', 'No pude detectar un QR en la imagen.');
			}
		}catch(err){
			setResult('danger', 'No se pudo leer la imagen.');
		}
	}

	openBtn.addEventListener('click', open);
	if(closeBtn) closeBtn.addEventListener('click', close);
	if(closeX) closeX.addEventListener('click', close);
	if(bg) bg.addEventListener('click', close);

	if(btnStart) btnStart.addEventListener('click', startCamera);
	if(btnStop) btnStop.addEventListener('click', stopCamera);

	if(fileInput && fileName){
		fileInput.addEventListener('change', function(){
			var f = (fileInput.files && fileInput.files[0]) ? fileInput.files[0] : null;
			fileName.textContent = f ? f.name : 'Ninguno';
			if(f){
				scanImageFile(f);
			}
		});
	}

	// Cerrar con ESC
	document.addEventListener('keydown', function(e){
		if(e.key === 'Escape' && modal.classList.contains('is-active')){
			close();
		}
	});
})();
</script>
