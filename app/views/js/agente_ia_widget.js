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
			<header class="p-3 is-flex is-align-items-center is-justify-content-space-between has-background-light">
				<div class="has-text-weight-semibold">Asistente</div>
				<button id="rag-chat-close" class="delete" type="button" aria-label="Cerrar"></button>
			</header>
			<div id="rag-chat-messages" aria-live="polite"></div>
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
	toggle.className = 'button is-info is-rounded';
	toggle.setAttribute('aria-label', 'Abrir chat');
	toggle.textContent = 'IA';
	document.body.appendChild(toggle);

	var closeBtn = document.getElementById('rag-chat-close');
	var messagesEl = document.getElementById('rag-chat-messages');
	var inputEl = document.getElementById('rag-chat-text');
	var sendBtn = document.getElementById('rag-chat-send');

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

	// Mensaje inicial
	addMessage('bot', 'Hola, soy tu asistente. ¿En qué puedo ayudarte?');
})();
