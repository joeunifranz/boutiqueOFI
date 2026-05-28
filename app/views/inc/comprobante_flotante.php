<?php
// Visor flotante (modal) para comprobantes (imagen/PDF)
?>

<div class="modal" id="modalComprobanteViewer">
	<div class="modal-background"></div>
	<div class="modal-card" style="width:min(96vw, 980px); height:min(92vh, 760px);">
		<header class="modal-card-head" style="justify-content:flex-start; gap:10px;">
			<button class="button is-light is-small" type="button" id="btnCloseComprobanteViewer" aria-label="Atrás">
				<i class="fas fa-arrow-left"></i> &nbsp; Atrás
			</button>
			<p class="modal-card-title" style="margin-left:6px;">Comprobante</p>
			<button class="delete" aria-label="close" style="margin-left:auto;"></button>
		</header>
		<section class="modal-card-body" style="overflow:hidden;">
			<div id="comprobanteViewerBody" style="width:100%; height:100%;"></div>
		</section>
	</div>
</div>

<script>
(function(){
	if(window.__comprobanteViewerInit) return;
	window.__comprobanteViewerInit = true;

	var modal = document.getElementById('modalComprobanteViewer');
	if(!modal) return;

	var bg = modal.querySelector('.modal-background');
	var closeX = modal.querySelector('.delete');
	var closeBtn = document.getElementById('btnCloseComprobanteViewer');
	var body = document.getElementById('comprobanteViewerBody');

	function openViewer(url){
		if(!url || !body) return;
		body.innerHTML = '';

		var cleanUrl = String(url);
		var lower = cleanUrl.toLowerCase();
		var isPdf = lower.indexOf('.pdf') !== -1;
		var isImg = (lower.indexOf('.jpg') !== -1) || (lower.indexOf('.jpeg') !== -1) || (lower.indexOf('.png') !== -1) || (lower.indexOf('.webp') !== -1) || (lower.indexOf('data:image') === 0);

		if(isPdf){
			var iframe = document.createElement('iframe');
			iframe.src = cleanUrl;
			iframe.style.width = '100%';
			iframe.style.height = '100%';
			iframe.style.border = '0';
			iframe.setAttribute('title','Comprobante PDF');
			body.appendChild(iframe);
		}else if(isImg){
			var wrap = document.createElement('div');
			wrap.style.width = '100%';
			wrap.style.height = '100%';
			wrap.style.display = 'flex';
			wrap.style.alignItems = 'center';
			wrap.style.justifyContent = 'center';
			wrap.style.overflow = 'auto';

			var img = document.createElement('img');
			img.src = cleanUrl;
			img.alt = 'Comprobante';
			img.style.maxWidth = '100%';
			img.style.height = 'auto';
			img.style.borderRadius = '10px';
			wrap.appendChild(img);
			body.appendChild(wrap);
		}else{
			var a = document.createElement('a');
			a.href = cleanUrl;
			a.target = '_blank';
			a.rel = 'noopener';
			a.className = 'button is-link';
			a.textContent = 'Abrir comprobante';
			body.appendChild(a);
		}

		modal.classList.add('is-active');
	}

	function closeViewer(){
		modal.classList.remove('is-active');
		if(body) body.innerHTML = '';
	}

	if(bg) bg.addEventListener('click', closeViewer);
	if(closeX) closeX.addEventListener('click', closeViewer);
	if(closeBtn) closeBtn.addEventListener('click', closeViewer);

	// Delegación: cualquier link con class js-open-comprobante
	document.addEventListener('click', function(e){
		var el = e.target;
		if(!el) return;
		var a = el.closest ? el.closest('a.js-open-comprobante') : null;
		if(!a) return;

		var url = a.getAttribute('data-comprobante-url') || a.getAttribute('href') || '';
		if(!url) return;
		e.preventDefault();
		openViewer(url);
	});

	// ESC cierra
	document.addEventListener('keydown', function(e){
		if(e.key === 'Escape' && modal.classList.contains('is-active')){
			closeViewer();
		}
	});
})();
</script>
