/* Widget chat flotante para BOUTIQUE
 * Adaptación: en lugar de llamar directo a Flask (CORS), usa el proxy PHP: app/ajax/agenteIaAjax.php
 */
(function(){
	"use strict";

	function getAppBase(){
		// Intentamos usar APP_URL si existe; fallback a '/'
		try{
			if(typeof window.APP_URL === 'string' && window.APP_URL.length){
				return window.APP_URL;
			}
		}catch(e){}
		return '/';
	}

	function joinUrl(base, path){
		if(!base.endsWith('/')) base += '/';
		if(path.startsWith('/')) path = path.slice(1);
		return base + path;
	}

	var API_URL = joinUrl(getAppBase(), 'app/ajax/agenteIaAjax.php');
	var RECO_URL = joinUrl(getAppBase(), 'app/ajax/recomendadorAjax.php');

	var style = document.createElement('style');
	style.textContent = `
		#rag-chat-widget {
			position: fixed;
			bottom: 20px;
			right: 20px;
			width: 360px;
			max-width: calc(100vw - 40px);
			height: 500px;
			max-height: calc(100vh - 40px);
			display: none;
			flex-direction: column;
			overflow: hidden;
			z-index: 99999;
		}
		#rag-chat-widget.open { display: flex; }
		#rag-chat-card { height: 100%; display: flex; flex-direction: column; }
		#rag-chat-messages {
			flex: 1;
			padding: 12px;
			overflow-y: auto;
		}
		#rag-chat-reco {
			border-top: 1px solid rgba(0,0,0,0.06);
		}
		.rag-msg { margin-bottom: 10px; display: flex; }
		.rag-msg.user { justify-content: flex-end; }
		.rag-bubble {
			max-width: 85%;
			padding: 9px 10px;
			border-radius: 10px;
			font-size: 13px;
			line-height: 1.35;
			white-space: pre-wrap;
			word-wrap: break-word;
		}
		.rag-msg.user .rag-bubble { border-bottom-right-radius: 3px; }
		.rag-msg.bot .rag-bubble { border-bottom-left-radius: 3px; }
		#rag-chat-toggle {
			position: fixed;
			bottom: 20px;
			right: 20px;
			width: 54px;
			height: 54px;
			border-radius: 999px;
			box-shadow: 0 6px 20px rgba(0,0,0,0.18);
			cursor: pointer;
			z-index: 99998;
			padding: 0;
		}
		@media (max-width: 480px){
			#rag-chat-widget { width: calc(100vw - 30px); height: calc(100vh - 90px); right: 15px; bottom: 75px; }
			#rag-chat-toggle { right: 15px; bottom: 15px; }
		}
		/* Botón-link dentro de mensajes */
		.rag-bubble a.rag-link-btn{
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 6px 10px;
			border-radius: 999px;
			text-decoration: none;
			font-weight: 600;
			border: 1px solid rgba(0,0,0,0.08);
			box-shadow: 0 10px 25px rgba(0,0,0,0.08);
		}
		.rag-bubble a.rag-link-btn:focus{ outline: 2px solid rgba(50,115,220,0.35); outline-offset: 2px; }
	`;
	document.head.appendChild(style);

	var widget = document.createElement('div');
	widget.id = 'rag-chat-widget';
	widget.innerHTML = `
		<div id="rag-chat-card" class="box p-0">
			<header class="p-3 is-flex is-align-items-center is-justify-content-space-between has-background-danger-light">
				<div class="has-text-weight-semibold has-text-danger-dark">Asistente</div>
				<button id="rag-chat-close" class="delete" type="button" aria-label="Cerrar"></button>
			</header>
			<div id="rag-chat-messages" aria-live="polite"></div>
			<div id="rag-chat-reco" class="p-3 has-background-danger-light">
				<div class="is-flex is-align-items-center is-justify-content-space-between mb-2">
					<div class="has-text-weight-semibold has-text-danger-dark" style="font-size: 13px;">SUGERENCIAS DE VESTIDO</div>
					<span class="tag is-danger is-light">Foto + tipo de cuerpo</span>
				</div>
				<div class="columns is-mobile is-variable is-2" style="margin-bottom: .25rem;">
					<div class="column" style="padding-top: .25rem; padding-bottom: .25rem;">
						<div class="field">
							<div class="control">
								<div class="select is-fullwidth is-small is-danger">
									<select id="rag-reco-tipo">
										<option value="" selected>Tipo de cuerpo (opcional)</option>
										<option value="reloj_arena">Reloj de arena</option>
										<option value="pera">Pera</option>
										<option value="manzana">Manzana</option>
										<option value="rectangular">Rectangular</option>
										<option value="triangulo_invertido">Triángulo invertido</option>
										<option value="no_se">No estoy segura</option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="column" style="padding-top: .25rem; padding-bottom: .25rem;">
						<div class="field">
							<div class="file has-name is-fullwidth is-small is-danger">
								<label class="file-label">
									<input id="rag-reco-foto" class="file-input" type="file" accept="image/jpeg,image/png">
									<span class="file-cta">
										<span class="file-icon"><i class="fas fa-camera"></i></span>
										<span class="file-label">Foto</span>
									</span>
									<span class="file-name" id="rag-reco-foto-name">JPG/PNG</span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<div class="field is-grouped is-grouped-right" style="margin-top: .25rem;">
					<div class="control">
						<button id="rag-reco-btn" class="button is-danger is-small is-rounded" type="button">Sugerir</button>
					</div>
				</div>
			</div>
			<div class="p-3">
				<div class="field has-addons">
					<div class="control is-expanded">
						<input id="rag-chat-text" class="input" type="text" placeholder="Escribe tu mensaje..." autocomplete="off" />
					</div>
					<div class="control">
						<button id="rag-chat-send" class="button is-info" type="button">Enviar</button>
					</div>
				</div>
			</div>
		</div>
	`;
	document.body.appendChild(widget);

	var toggle = document.createElement('button');
	toggle.id = 'rag-chat-toggle';
	toggle.type = 'button';
	toggle.className = 'button is-danger is-rounded';
	toggle.setAttribute('aria-label', 'Abrir chat');
	toggle.textContent = 'IA';
	document.body.appendChild(toggle);

	var closeBtn = document.getElementById('rag-chat-close');
	var messagesEl = document.getElementById('rag-chat-messages');
	var inputEl = document.getElementById('rag-chat-text');
	var sendBtn = document.getElementById('rag-chat-send');
	var recoTipoEl = document.getElementById('rag-reco-tipo');
	var recoFotoEl = document.getElementById('rag-reco-foto');
	var recoFotoNameEl = document.getElementById('rag-reco-foto-name');
	var recoBtn = document.getElementById('rag-reco-btn');

	function openWidget(){
		widget.classList.add('open');
		setTimeout(function(){ inputEl.focus(); }, 0);
	}
	function closeWidget(){
		widget.classList.remove('open');
	}
	function isOpen(){
		return widget.classList.contains('open');
	}

	function escapeHtml(str){
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function simpleMarkdownToHtml(text){
		// Mínimo: líneas + **negrita** + enlaces http(s)
		var safe = escapeHtml(String(text));

		// 1) Convertir enlaces Markdown a placeholders para que el linkificador no los rompa
		var mdLinks = [];
		safe = safe.replace(/\[([^\]]+?)\]\((https?:\/\/[^\s)]+)\)/g, function(_, label, url){
			var idx = mdLinks.length;
			mdLinks.push('<a class="rag-link-btn button is-small is-link is-light is-rounded" href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
			return '@@MDLINK_' + idx + '@@';
		});

		// 2) Negrita
		safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

		// 3) Linkificar URLs sueltas (solo fuera de los placeholders)
		safe = safe.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');

		// 4) Restaurar enlaces Markdown
		for(var i = 0; i < mdLinks.length; i++){
			safe = safe.replace('@@MDLINK_' + i + '@@', mdLinks[i]);
		}

		safe = safe.replace(/\n/g, '<br/>');
		return safe;
	}

	function addMessage(role, text){
		var msg = document.createElement('div');
		msg.className = 'rag-msg ' + (role === 'user' ? 'user' : 'bot');
		var bubble = document.createElement('div');
		bubble.className = 'rag-bubble ' + (role === 'user' ? 'has-background-info-light' : 'has-background-light');
		bubble.innerHTML = simpleMarkdownToHtml(text);
		msg.appendChild(bubble);
		messagesEl.appendChild(msg);
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	var busy = false;
	function setBusy(v){
		busy = v;
		sendBtn.disabled = v;
		inputEl.disabled = v;
		if(recoBtn) recoBtn.disabled = v;
		if(recoTipoEl) recoTipoEl.disabled = v;
		if(recoFotoEl) recoFotoEl.disabled = v;
	}

	async function sendReco(){
		if(busy) return;
		if(!recoFotoEl || !recoFotoEl.files || !recoFotoEl.files.length){
			addMessage('bot', 'Para darte **SUGERENCIAS DE VESTIDO**, sube una **foto** (JPG/PNG) en el bloque rosado.');
			return;
		}

		var tipo = (recoTipoEl && recoTipoEl.value) ? String(recoTipoEl.value) : '';
		addMessage('user', 'Quiero sugerencias de vestido' + (tipo ? (' (tipo: ' + tipo + ')') : '') + '.');
		setBusy(true);

		try{
			var fd = new FormData();
			fd.append('modulo_reco', 'sugerir');
			fd.append('tipo_cuerpo', tipo);
			fd.append('categoria_id', '0');
			fd.append('talla', '');
			fd.append('max_price', '');
			fd.append('foto', recoFotoEl.files[0]);

			var resp = await fetch(RECO_URL, {
				method: 'POST',
				body: fd
			});
			var data = null;
			try{ data = await resp.json(); }catch(e){ data = null; }

			if(!resp.ok || !data || data.ok !== true){
				var msg = (data && data.message) ? String(data.message) : 'No pude generar sugerencias ahora mismo.';
				addMessage('bot', msg);
				setBusy(false);
				return;
			}

			addMessage('bot', String(data.message || 'Aquí tienes sugerencias:'));
			var items = Array.isArray(data.items) ? data.items : [];
			if(!items.length){
				addMessage('bot', 'No encontré resultados con el catálogo actual.');
				setBusy(false);
				return;
			}

			for(var i=0; i<items.length; i++){
				var it = items[i] || {};
				var nombre = (it.nombre != null) ? String(it.nombre) : 'Vestido';
				var precio = (it.precio != null && !isNaN(Number(it.precio))) ? Number(it.precio) : null;
				var url = (it.detalle_url != null) ? String(it.detalle_url) : '';
				var line = '**' + nombre + '**';
				if(precio !== null){ line += ' — Bs ' + precio.toFixed(2); }
				if(url){ line += '\n[Abrir detalle](' + url + ')'; }
				addMessage('bot', line);
			}

			// limpiar foto seleccionada (para la siguiente sugerencia)
			recoFotoEl.value = '';
			if(recoFotoNameEl) recoFotoNameEl.textContent = 'JPG/PNG';
			setBusy(false);
		}catch(e){
			addMessage('bot', 'No pude generar sugerencias ahora mismo.');
			setBusy(false);
		}
	}

	async function sendMessage(){
		if(busy) return;
		var text = (inputEl.value || '').trim();
		if(!text) return;

		addMessage('user', text);
		inputEl.value = '';
		setBusy(true);

		try{
			var resp = await fetch(API_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify({ message: text, page: (window.location ? (window.location.pathname + window.location.search) : '') })
			});

			var data = null;
			try{ data = await resp.json(); }catch(e){ data = null; }

			if(!resp.ok || !data || data.ok !== true){
				var errText = (data && data.error) ? String(data.error) : ('Error (' + resp.status + ')');
				addMessage('bot', 'No pude responder ahora mismo. ' + errText);
				setBusy(false);
				return;
			}

			addMessage('bot', String(data.response || ''));
			setBusy(false);
		}catch(e){
			addMessage('bot', 'No pude conectarme con el asistente.');
			setBusy(false);
		}
	}

	toggle.addEventListener('click', function(){
		if(isOpen()) closeWidget(); else openWidget();
	});
	closeBtn.addEventListener('click', closeWidget);
	sendBtn.addEventListener('click', sendMessage);
	inputEl.addEventListener('keydown', function(e){
		if(e.key === 'Enter') sendMessage();
	});

	if(recoFotoEl && recoFotoNameEl){
		recoFotoEl.addEventListener('change', function(){
			var name = '';
			try{
				name = (recoFotoEl.files && recoFotoEl.files[0]) ? String(recoFotoEl.files[0].name || '') : '';
			}catch(e){ name = ''; }
			recoFotoNameEl.textContent = name ? name : 'JPG/PNG';
		});
	}
	if(recoBtn){
		recoBtn.addEventListener('click', sendReco);
	}

	// Mensaje inicial
	addMessage('bot', 'Hola, soy tu asistente. ¿En qué puedo ayudarte?');
})();
