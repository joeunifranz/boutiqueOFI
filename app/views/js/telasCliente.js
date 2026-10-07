(function(){
	function qs(sel){ return document.querySelector(sel); }
	function qsa(sel){ return Array.from(document.querySelectorAll(sel)); }
	function eventTargetElement(e){
		const t = e && e.target ? e.target : null;
		if(!t) return null;
		// Si es un nodo de texto, usar el elemento padre
		if(t.nodeType === 3){
			return t.parentElement;
		}
		return t;
	}

	const estado = qs('#telasEstado');
	const listWrap = qs('#telasList');
	const metrosTexto = qs('#telaMetrosTexto');
	const totalTexto = qs('#telaTotalTexto');
	const dressCanvas = qs('#dress3dCanvas');
	const dressModalCanvas = qs('#dress3dCanvasModal');
	const tallaSel = qs('#tallaVestido');
	const alturaInput = qs('#alturaVestido');
	const formulaTexto = qs('#telaFormulaTexto');
	const resumenAltura = qs('#resumenAltura');
	const resumenTotal = qs('#resumenTotal');
	// Fórmula de metros enviada por el servidor (config/app.php)
	const MEDIDAS = Object.assign({
		metrosBase: { XS: 2.4, S: 2.6, M: 2.8, L: 3.0, XL: 3.2, XXL: 3.4 },
		complejidad: 1.15,
		alturaMin: 140,
		alturaMax: 195,
		alturaReferencia: 160,
		parteLargo: 0.6
	}, window.VESTIDO_MEDIDAS || {});

	const wizardMsg = qs('#wizardMsg');
	const wizardTabs = qs('#wizardTabs');
	const steps = {
		1: qs('#wizardStep1'),
		2: qs('#wizardStep2'),
		3: qs('#wizardStep3'),
		4: qs('#wizardStep4'),
	};
	let currentStep = 1;

	const vestidoDetalle = qs('#vestidoDetalle');

	const encajeCarousel = qs('#encajeCarousel');
	const encajeSelTexto = qs('#encajeSeleccionTexto');
	const encajePrevBtn = qs('#encajePrev');
	const encajeNextBtn = qs('#encajeNext');

	const citaFecha = qs('#cita_fecha_personalizada');
	const citaHora = qs('#cita_hora_personalizada');
	const citaHelp = qs('#cita_help_personalizada');
	const btnEnviar = qs('#btnEnviarSolicitud');
	const btnSolicitudesAnteriores = qs('#btnSolicitudesAnteriores');
	const solicitudesBadge = qs('#solicitudesBadge');
	const solicitudesAnterioresEstado = qs('#solicitudesAnterioresEstado');
	const solicitudesAnterioresWrap = qs('#solicitudesAnterioresWrap');
	const solicitudesAnterioresTbody = qs('#solicitudesAnterioresTbody');

	const solDetalleFecha = qs('#solDetalleFecha');
	const solDetalleHora = qs('#solDetalleHora');
	const solDetalleTalla = qs('#solDetalleTalla');
	const solDetalleTela = qs('#solDetalleTela');
	const solDetalleEncaje = qs('#solDetalleEncaje');
	const solDetalleVestido = qs('#solDetalleVestido');

	const resumenTalla = qs('#resumenTalla');
	const resumenTela = qs('#resumenTela');
	const resumenEncaje = qs('#resumenEncaje');

	const CLIENTE_LOGUEADO = (window.CLIENTE_LOGUEADO === true);
	const CLIENTE_ID = Number(window.TELAS_CLIENTE_ID || window.BOUTIQUE_CLIENTE_ID || 0);
	const PROBADOR_STORAGE_KEY = 'boutique_probador_id_cliente_' + (isFinite(CLIENTE_ID) && CLIENTE_ID > 0 ? String(CLIENTE_ID) : '0');

	let ENCAJES = [];
	let lastSolicitudSnapshot = null;
	let SOLICITUDES_ANTERIORES = [];
	let CURRENT_PROBADOR_ID = 0;

	function getProbadorIdFromQuery(){
		try{
			const params = new URLSearchParams(window.location.search || '');
			const raw = String(params.get('probador_id') || '').trim();
			if(!/^\d+$/.test(raw)) return 0;
			const n = Number(raw);
			return (isFinite(n) && n > 0) ? Math.trunc(n) : 0;
		}catch(e){
			return 0;
		}
	}

	function cleanProbadorQueryParam(){
		try{
			const url = new URL(window.location.href);
			if(!url.searchParams.has('probador_id')) return;
			url.searchParams.delete('probador_id');
			window.history.replaceState({}, document.title, url.toString());
		}catch(e){}
	}

	function loadProbadorIdState(){
		const fromQuery = getProbadorIdFromQuery();
		if(fromQuery > 0){
			CURRENT_PROBADOR_ID = fromQuery;
			try{ localStorage.setItem(PROBADOR_STORAGE_KEY, String(fromQuery)); }catch(e){}
			cleanProbadorQueryParam();
			return;
		}
		try{
			const raw = String(localStorage.getItem(PROBADOR_STORAGE_KEY) || '').trim();
			if(/^\d+$/.test(raw)){
				const n = Number(raw);
				if(isFinite(n) && n > 0){
					CURRENT_PROBADOR_ID = Math.trunc(n);
				}
			}
		}catch(e){}
	}

	/* ---------- Borrador: lo que la clienta eligió sobrevive a una recarga ----------
	 * Solo con sesión iniciada; se guarda por cliente en este navegador y se borra
	 * al enviar la solicitud o a los 30 días. */
	const BORRADOR_KEY = (CLIENTE_LOGUEADO && CLIENTE_ID > 0) ? ('boutique_personaliza_borrador_cliente_' + String(CLIENTE_ID)) : '';
	const BORRADOR_DIAS = 30;

	function leerBorrador(){
		if(!BORRADOR_KEY) return null;
		try{
			const d = JSON.parse(localStorage.getItem(BORRADOR_KEY) || 'null');
			if(!d || d.v !== 1) return null;
			if(Date.now() - Number(d.guardado || 0) > BORRADOR_DIAS * 864e5){
				localStorage.removeItem(BORRADOR_KEY);
				return null;
			}
			return d;
		}catch(e){
			return null;
		}
	}

	const BORRADOR_INICIAL = leerBorrador();
	let borradorTimer = 0;
	let borradorListo = false; // no guardar hasta terminar de restaurar

	function guardarBorradorAhora(){
		if(!BORRADOR_KEY || !borradorListo) return;
		const previo = leerBorrador() || {};
		const telaSel = currentSelection();
		const encajeSel = document.querySelector('input[type="radio"][name="encaje_id"]:checked');
		// Si una lista todavía no cargó, se conserva lo que ya estaba guardado
		const horaLista = citaHora && !citaHora.disabled;
		const datos = {
			v: 1,
			guardado: Date.now(),
			paso: currentStep,
			telaId: telaSel ? String(telaSel.value) : (previo.telaId || ''),
			talla: tallaSel ? String(tallaSel.value || '') : (previo.talla || ''),
			altura: getAltura(),
			encajeId: encajeSel ? String(encajeSel.value) : (previo.encajeId || ''),
			vista: vistaEstudio,
			citaFecha: citaFecha ? String(citaFecha.value || '') : '',
			citaHora: horaLista ? String(citaHora.value || '') : (previo.citaHora || ''),
		};
		try{ localStorage.setItem(BORRADOR_KEY, JSON.stringify(datos)); }catch(e){}
	}

	function guardarBorrador(){
		clearTimeout(borradorTimer);
		borradorTimer = setTimeout(guardarBorradorAhora, 250);
	}

	function borrarBorrador(){
		clearTimeout(borradorTimer);
		if(!BORRADOR_KEY) return;
		try{ localStorage.removeItem(BORRADOR_KEY); }catch(e){}
	}

	function clearProbadorIdState(){
		CURRENT_PROBADOR_ID = 0;
		try{ localStorage.removeItem(PROBADOR_STORAGE_KEY); }catch(e){}
	}

	function openModalById(modalId){
		const id = String(modalId || '').trim();
		if(!id) return;
		const el = document.getElementById(id);
		if(el) el.classList.add('is-active');
	}

	function closeModalById(modalId){
		const id = String(modalId || '').trim();
		if(!id) return;
		const el = document.getElementById(id);
		if(el) el.classList.remove('is-active');
	}

	function buildSolicitudSnapshot(){
		const talla = tallaSel ? String(tallaSel.value || 'M') : 'M';
		const altura = getAltura();
		const tela = getSelectedTela();
		const encaje = getSelectedEncaje();
		const metros = estimateMeters(talla || 'M', altura);
		const total = (tela && isFinite(tela.precio) && isFinite(metros)) ? (tela.precio * metros) : NaN;
		return {
			fecha: citaFecha ? String(citaFecha.value || '') : '',
			hora: citaHora ? String(citaHora.value || '') : '',
			talla,
			altura,
			telaNombre: tela ? String(tela.nombre || '') : '',
			telaPrecio: tela ? Number(tela.precio) : NaN,
			metros,
			total,
			encajeNombre: encaje ? String(encaje.encaje_nombre || '') : '',
			encajePrecio: encaje ? Number(encaje.encaje_precio) : NaN,
			vestidoDetalle: vestidoDetalle ? String(vestidoDetalle.value || '') : '',
		};
	}

	function renderSolicitudDetalle(snapshot){
		if(!snapshot) snapshot = {};
		if(solDetalleFecha) solDetalleFecha.textContent = snapshot.fecha ? snapshot.fecha : '—';
		if(solDetalleHora) solDetalleHora.textContent = snapshot.hora ? snapshot.hora : '—';
		if(solDetalleTalla) solDetalleTalla.textContent = snapshot.talla ? snapshot.talla : '—';

		if(solDetalleTela){
			if(snapshot.telaNombre || isFinite(snapshot.telaPrecio)){
				const parts = [];
				if(snapshot.telaNombre) parts.push(snapshot.telaNombre);
				if(isFinite(snapshot.telaPrecio)) parts.push(formatMoney(snapshot.telaPrecio) + ' / m');
				if(isFinite(snapshot.metros)) parts.push(snapshot.metros.toFixed(1) + ' m');
				if(isFinite(snapshot.total)) parts.push('Total: ' + formatMoney(snapshot.total));
				solDetalleTela.textContent = parts.join(' — ');
			}else{
				solDetalleTela.textContent = '—';
			}
		}

		if(solDetalleEncaje){
			if(snapshot.encajeNombre || isFinite(snapshot.encajePrecio)){
				const parts = [];
				if(snapshot.encajeNombre) parts.push(snapshot.encajeNombre);
				if(isFinite(snapshot.encajePrecio)) parts.push(formatMoney(snapshot.encajePrecio) + ' / 1.5 m');
				solDetalleEncaje.textContent = parts.join(' — ');
			}else{
				solDetalleEncaje.textContent = '—';
			}
		}

		if(solDetalleVestido){
			const txt = snapshot.vestidoDetalle ? snapshot.vestidoDetalle.trim() : '';
			solDetalleVestido.textContent = txt ? txt : '—';
		}
	}

	function showSolicitudesEstado(msg, type){
		if(!solicitudesAnterioresEstado) return;
		solicitudesAnterioresEstado.style.display = 'block';
		solicitudesAnterioresEstado.className = 'notification is-' + (type || 'light');
		solicitudesAnterioresEstado.textContent = msg;
		if(solicitudesAnterioresWrap) solicitudesAnterioresWrap.style.display = 'none';
	}

	function renderSolicitudesAnteriores(rows){
		SOLICITUDES_ANTERIORES = Array.isArray(rows) ? rows : [];
		if(!solicitudesAnterioresTbody || !solicitudesAnterioresWrap || !solicitudesAnterioresEstado) return;
		solicitudesAnterioresTbody.innerHTML = '';
		if(SOLICITUDES_ANTERIORES.length === 0){
			showSolicitudesEstado('Aún no tienes solicitudes personalizadas.', 'light');
			return;
		}
		solicitudesAnterioresEstado.style.display = 'none';
		solicitudesAnterioresWrap.style.display = '';

		SOLICITUDES_ANTERIORES.forEach((r) => {
			const cita = String((r.cita_fecha || '') + ' ' + (r.cita_hora || '')).trim();
			const estado = String(r.estado || '');
			const creado = String(r.creado_en || '');
			const index = SOLICITUDES_ANTERIORES.indexOf(r);
			const tr = document.createElement('tr');
			tr.innerHTML =
				'<td>' + escapeHtml(cita || '—') + '</td>' +
				'<td>' + escapeHtml(estado || '—') + '</td>' +
				'<td>' + escapeHtml(creado || '—') + '</td>' +
				'<td class="has-text-right">' +
					'<button type="button" class="button is-small is-link is-light is-rounded js-modal-trigger js-ver-solicitud" data-target="modalSolicitudDetalle" data-sol-index="' + String(index) + '">Ver detalle</button>' +
				'</td>';
			solicitudesAnterioresTbody.appendChild(tr);
		});
	}

	async function cargarSolicitudesAnteriores(){
		if(!CLIENTE_LOGUEADO){
			showSolicitudesEstado('Debes iniciar sesión para ver tus solicitudes.', 'warning');
			return;
		}
		if(solicitudesBadge) solicitudesBadge.style.display = 'none';
		showSolicitudesEstado('Cargando...', 'light');
		try{
			const fd = new FormData();
			fd.append('modulo_reserva', 'personalizada_listar_cliente');
			const resp = await fetch((window.APP_URL || '') + 'app/ajax/reservaAjax.php', { method: 'POST', body: fd });
			const json = await resp.json();
			if(!json || json.ok !== true){
				showSolicitudesEstado((json && json.mensaje) ? json.mensaje : 'No se pudo cargar el historial.', 'danger');
				return;
			}
			renderSolicitudesAnteriores(Array.isArray(json.data) ? json.data : []);
		}catch(e){
			showSolicitudesEstado('No se pudo cargar el historial.', 'danger');
		}
	}

	function showEstado(msg, type){
		if(!estado) return;
		estado.style.display = 'block';
		estado.className = 'notification is-' + (type || 'info') + ' is-light';
		estado.textContent = msg;
	}

	function showWizardMsg(msg, type){
		if(!wizardMsg) return;
		wizardMsg.style.display = 'block';
		wizardMsg.className = 'notification is-' + (type || 'light');
		wizardMsg.textContent = msg;
	}

	function clearWizardMsg(){
		if(!wizardMsg) return;
		wizardMsg.style.display = 'none';
		wizardMsg.textContent = '';
	}

	function formatMoney(value){
		const n = Number(value);
		if(!isFinite(n)) return '—';
		return (window.MONEDA_SIMBOLO || '') + n.toFixed(2);
	}

	function formatMeters(value){
		const n = Number(value);
		if(!isFinite(n) || n<=0) return '—';
		return n.toFixed(1) + ' m';
	}

	function getAltura(){
		const n = alturaInput ? parseInt(alturaInput.value, 10) : NaN;
		if(!isFinite(n)) return MEDIDAS.alturaReferencia;
		return Math.min(MEDIDAS.alturaMax, Math.max(MEDIDAS.alturaMin, n));
	}

	// Igual que reservationController::estimarMetrosPorTalla (el servidor recalcula al guardar)
	function estimateMeters(talla, altura){
		const bases = MEDIDAS.metrosBase;
		const base = bases[String(talla || '').toUpperCase()] ?? bases['M'];
		const h = isFinite(altura) ? altura : MEDIDAS.alturaReferencia;
		const factorAltura = (1 - MEDIDAS.parteLargo) + MEDIDAS.parteLargo * (h / MEDIDAS.alturaReferencia);
		// Redondeo al 0.1
		return Math.round((base * MEDIDAS.complejidad * factorAltura) * 10) / 10;
	}

	function getSelectedTela(){
		const sel = listWrap ? listWrap.querySelector('input[type="radio"][name="tela_id"]:checked') : null;
		if(!sel) return null;
		return {
			id: String(sel.value || ''),
			precio: Number(sel.getAttribute('data-precio')),
			textura: sel.getAttribute('data-textura') || '',
			nombre: sel.getAttribute('data-nombre') || '',
		};
	}

	function getSelectedEncaje(){
		const sel = document.querySelector('input[type="radio"][name="encaje_id"]:checked');
		if(!sel) return null;
		const id = String(sel.value || '');
		return ENCAJES.find(e => String(e.encaje_id) === id) || null;
	}

	function updateResumen(){
		const talla = tallaSel ? String(tallaSel.value || '') : '';
		const altura = getAltura();
		const tela = getSelectedTela();
		const encaje = getSelectedEncaje();

		if(resumenTalla) resumenTalla.textContent = talla || '—';
		if(resumenAltura) resumenAltura.textContent = altura + ' cm';
		if(resumenTotal){
			const metrosTot = estimateMeters(talla || 'M', altura);
			const totalTela = (tela && isFinite(tela.precio)) ? tela.precio * metrosTot : NaN;
			const totalEncaje = encaje ? Number(encaje.encaje_precio) : 0;
			resumenTotal.textContent = isFinite(totalTela) ? formatMoney(totalTela + (isFinite(totalEncaje) ? totalEncaje : 0)) : '—';
		}
		if(resumenTela){
			if(tela && tela.id){
				const metros = estimateMeters(talla || 'M', altura);
				const precioTxt = isFinite(tela.precio) ? (formatMoney(tela.precio) + ' / m') : '—';
				resumenTela.textContent = (tela.nombre ? (tela.nombre + ' — ') : '') + precioTxt + (isFinite(metros) ? (' — ' + metros.toFixed(1) + ' m aprox.') : '');
			}else{
				resumenTela.textContent = '—';
			}
		}
		if(resumenEncaje){
			resumenEncaje.textContent = encaje ? (String(encaje.encaje_nombre || '') + ' — ' + formatMoney(encaje.encaje_precio) + ' / 1.5 m') : '—';
		}
	}

	function canSubmit(){
		if(!CLIENTE_LOGUEADO) return false;
		const tela = getSelectedTela();
		const encaje = getSelectedEncaje();
		if(!tela || !tela.id) return false;
		if(!encaje) return false;
		if(!citaFecha || !citaHora) return false;
		if(!citaFecha.value) return false;
		if(!citaHora.value) return false;
		return true;
	}

	function refreshSubmitState(){
		if(!btnEnviar) return;
		btnEnviar.disabled = !canSubmit();
	}

	function setActiveTab(step){
		if(!wizardTabs) return;
		qsa('#wizardTabs li[data-step]').forEach(li => {
			const s = Number(li.getAttribute('data-step'));
			if(s === step) li.classList.add('is-active');
			else li.classList.remove('is-active');
		});
	}

	function showStep(step){
		clearWizardMsg();
		const s = Number(step);
		if(!steps[s]) return;
		Object.keys(steps).forEach(k => {
			const el = steps[k];
			if(!el) return;
			el.style.display = (Number(k) === s) ? '' : 'none';
		});
		currentStep = s;
		setActiveTab(s);

		// Recalcular renders al mostrar el paso 2 (canvas suele medir 0 si estaba oculto)
		if(s === 2){
			setTimeout(() => {
				if(dressScene) dressScene.resize();
			}, 60);
		}
		if(s === 3){
			setTimeout(() => {
				if(encajeCarousel){
					encajeCarousel.scrollLeft = 0;
				}
			}, 10);
		}
		updateResumen();
		refreshSubmitState();
		guardarBorrador();
	}

	function resolveTextureUrl(url){
		if(!url) return null;
		const u = String(url).trim();
		if(u === '') return null;
		if(/^https?:\/\//i.test(u)) return u;
		// Si viene con ruta relativa del proyecto, la hacemos absoluta
		const base = (window.APP_URL || '').replace(/\/$/, '');
		if(u.startsWith('/')) return base + u;
		return base + '/' + u.replace(/^\.\//, '');
	}

	function seededColor(seed){
		// Color determinístico por id (sin guardar en BD)
		let x = 0;
		for(let i=0;i<seed.length;i++) x = (x * 31 + seed.charCodeAt(i)) >>> 0;
		const r = 80 + (x & 0x7F);
		const g = 80 + ((x >> 8) & 0x7F);
		const b = 80 + ((x >> 16) & 0x7F);
		return { r, g, b };
	}

	function generateWeaveTexture(seed){
		if(!window.THREE) return null;
		const size = 256;
		const canvas = document.createElement('canvas');
		canvas.width = size;
		canvas.height = size;
		const ctx = canvas.getContext('2d');
		const c = seededColor(seed);
		ctx.fillStyle = `rgb(${c.r},${c.g},${c.b})`;
		ctx.fillRect(0,0,size,size);

		// Trama simple tipo tejido
		ctx.globalAlpha = 0.25;
		for(let y=0;y<size;y+=8){
			ctx.fillStyle = (y%16===0) ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.25)';
			ctx.fillRect(0,y,size,1);
		}
		for(let x=0;x<size;x+=8){
			ctx.fillStyle = (x%16===0) ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.20)';
			ctx.fillRect(x,0,1,size);
		}
		ctx.globalAlpha = 1;

		const tex = new THREE.CanvasTexture(canvas);
		tex.wrapS = THREE.RepeatWrapping;
		tex.wrapT = THREE.RepeatWrapping;
		tex.repeat.set(2,2);
		tex.anisotropy = 4;
		tex.needsUpdate = true;
		return tex;
	}

	/* ---------- Estudio 3D (js/estudio3d.js): vestido y tela en una sola escena ---------- */
	const OPCIONES_ESTUDIO = { resolverUrl: resolveTextureUrl, texturaRespaldo: generateWeaveTexture };
	const dressScene = window.crearEstudio3D ? window.crearEstudio3D(dressCanvas, OPCIONES_ESTUDIO) : null;
	let dressPreviewModal = null;
	let vistaEstudio = 'vestido';

	function telaParaEstudio(){
		const sel = currentSelection();
		if(!sel) return null;
		return {
			textura: sel.getAttribute('data-textura'),
			seed: sel.value || 'tela',
			nombre: sel.getAttribute('data-nombre') || '',
			precio: Number(sel.getAttribute('data-precio')),
		};
	}

	function actualizarEtiquetaEstudio(){
		const etiqueta = qs('#estudio3dEtiqueta');
		const tela = telaParaEstudio();
		if(!etiqueta) return;
		etiqueta.textContent = tela ? (tela.nombre + (isFinite(tela.precio) ? ' · ' + formatMoney(tela.precio) + ' / m' : '')) : '';
		etiqueta.hidden = !tela;
	}

	function cambiarVistaEstudio(vista){
		vistaEstudio = vista;
		qsa('.estudio3d-vista').forEach(b => b.setAttribute('aria-selected', b.getAttribute('data-vista') === vista ? 'true' : 'false'));
		if(dressScene) dressScene.setVista(vista);
		if(dressPreviewModal) dressPreviewModal.setVista(vista);
		guardarBorrador();
	}

	qsa('.estudio3d-vista').forEach(b => b.addEventListener('click', () => cambiarVistaEstudio(b.getAttribute('data-vista'))));

	function currentSelection(){
		return listWrap ? listWrap.querySelector('input[type="radio"][name="tela_id"]:checked') : null;
	}

	function refreshSummary(precioPorMetro){
		const talla = tallaSel ? tallaSel.value : 'M';
		const metros = estimateMeters(talla, getAltura());
		if(metrosTexto) metrosTexto.textContent = formatMeters(metros);
		const p = Number(precioPorMetro);
		animarPrecio(isFinite(p) ? metros * p : NaN);
		if(formulaTexto){
			formulaTexto.textContent = isFinite(p)
				? (metros.toFixed(1) + ' m × ' + formatMoney(p) + ' por metro')
				: 'Elige una tela para ver el precio';
		}
		actualizarRollo(metros);
		updateResumen();
		refreshSubmitState();
	}

	/* ---------- Medidor de talla y altura (animaciones) ---------- */
	const reducirMovimiento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const ALTURA_ESCALA_CM = 210; // la escena del medidor representa 0-210 cm (deja ver la marca de 200)
	let precioMostrado = NaN;
	let precioAnim = 0;

	function animarPrecio(destino){
		if(!totalTexto) return;
		cancelAnimationFrame(precioAnim);
		if(!isFinite(destino)){
			precioMostrado = NaN;
			totalTexto.textContent = '—';
			return;
		}
		const desde = isFinite(precioMostrado) ? precioMostrado : 0;
		if(Math.abs(desde - destino) < 0.005 || reducirMovimiento){
			precioMostrado = destino;
			totalTexto.textContent = formatMoney(destino);
			return;
		}
		totalTexto.classList.remove('cambia');
		void totalTexto.offsetWidth; // reinicia la animación de pulso
		totalTexto.classList.add('cambia');
		const inicio = performance.now();
		const dura = 650;
		const paso = (ahora) => {
			const k = Math.min(1, (ahora - inicio) / dura);
			const suave = 1 - Math.pow(1 - k, 3);
			precioMostrado = desde + (destino - desde) * suave;
			totalTexto.textContent = formatMoney(precioMostrado);
			if(k < 1) precioAnim = requestAnimationFrame(paso);
			else precioMostrado = destino;
		};
		precioAnim = requestAnimationFrame(paso);
	}

	let metrosAnteriores = NaN;
	function actualizarRollo(metros){
		const tira = qs('#telaRolloTira');
		if(!tira) return;
		tira.style.setProperty('--avance', String(Math.min(1, Math.max(0, metros / 5))));
		if(isFinite(metrosAnteriores) && metros !== metrosAnteriores){
			tira.classList.remove('brilla');
			void tira.offsetWidth;
			tira.classList.add('brilla');
		}
		metrosAnteriores = metros;
		// La tira y el vestido de la silueta usan la textura de la tela elegida
		const tela = getSelectedTela();
		const url = tela ? resolveTextureUrl(tela.textura) : null;
		const vestido = qs('#figuraVestido');
		const img = qs('#vestidoTexturaImg');
		if(url){
			tira.style.setProperty('--tela-fondo', 'url("' + url.replace(/"/g, '%22') + '")');
			if(img) img.setAttribute('href', url);
			if(vestido) vestido.classList.add('con-textura');
		}else{
			tira.style.removeProperty('--tela-fondo');
			if(vestido) vestido.classList.remove('con-textura');
		}
	}

	function pintarAltura(){
		const altura = getAltura();
		const escena = qs('.altura-escena');
		if(escena) escena.style.setProperty('--escala', String(altura / ALTURA_ESCALA_CM));
		const valor = qs('#alturaValor');
		if(valor) valor.textContent = String(altura);
		const burbuja = qs('#alturaBurbuja');
		if(burbuja) burbuja.textContent = altura + ' cm';
		if(alturaInput){
			const k = (altura - MEDIDAS.alturaMin) / (MEDIDAS.alturaMax - MEDIDAS.alturaMin);
			alturaInput.style.setProperty('--llenado', (k * 100).toFixed(1) + '%');
			alturaInput.setAttribute('aria-valuetext', altura + ' centímetros');
		}
	}

	function alCambiarMedidas(){
		pintarAltura();
		const sel = currentSelection();
		if(sel) refreshSummary(sel.getAttribute('data-precio'));
		else refreshSummary(NaN);
	}

	function initMedidor(){
		// Regla: marcas cada 5 cm desde 100 cm, con número cada 20 cm
		const regla = qs('#alturaRegla');
		if(regla){
			for(let cm = 100; cm <= 200; cm += 5){
				const marca = document.createElement('div');
				const larga = cm % 20 === 0;
				marca.className = 'marca' + (larga ? ' larga' : '');
				marca.style.bottom = (cm / ALTURA_ESCALA_CM * 100) + '%';
				if(larga) marca.innerHTML = '<span>' + cm + '</span>';
				regla.appendChild(marca);
			}
		}

		// Botones de talla -> select oculto (que es el que lee el resto del código)
		const chips = qsa('.talla-chip');
		const marcarChip = (valor) => {
			chips.forEach(c => {
				const activo = c.getAttribute('data-talla') === valor;
				c.setAttribute('aria-checked', activo ? 'true' : 'false');
				c.tabIndex = activo ? 0 : -1;
			});
		};
		chips.forEach((chip, i) => {
			chip.addEventListener('click', () => {
				if(!tallaSel) return;
				tallaSel.value = chip.getAttribute('data-talla');
				marcarChip(tallaSel.value);
				tallaSel.dispatchEvent(new Event('change', { bubbles: true }));
			});
			// Flechas para moverse entre tallas (patrón radiogroup)
			chip.addEventListener('keydown', (e) => {
				const dir = (e.key === 'ArrowRight' || e.key === 'ArrowDown') ? 1 : (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ? -1 : 0;
				if(!dir) return;
				e.preventDefault();
				const sig = chips[(i + dir + chips.length) % chips.length];
				sig.focus();
				sig.click();
			});
		});
		if(tallaSel) marcarChip(tallaSel.value);

		if(alturaInput){
			alturaInput.addEventListener('input', alCambiarMedidas);
		}
		qsa('[data-altura-paso]').forEach(btn => {
			btn.addEventListener('click', () => {
				if(!alturaInput) return;
				alturaInput.value = String(getAltura() + Number(btn.getAttribute('data-altura-paso')));
				alCambiarMedidas();
			});
		});
		pintarAltura();
	}

	function syncModalDress(){
		if(!dressModalCanvas || !window.crearEstudio3D) return;
		if(!dressPreviewModal){
			dressPreviewModal = window.crearEstudio3D(dressModalCanvas, OPCIONES_ESTUDIO);
		}
		if(!dressPreviewModal) return;
		dressPreviewModal.setVista(vistaEstudio);
		const tela = telaParaEstudio();
		if(tela) dressPreviewModal.setFabric(tela.textura, tela.seed, tela.nombre);
		setTimeout(() => dressPreviewModal && dressPreviewModal.resize(), 50);
	}

	// Cuando se abre el modal, sincronizar la tela y ajustar el renderer
	document.addEventListener('click', (e) => {
		const el = eventTargetElement(e);
		const btn = (el && el.closest) ? el.closest('.js-modal-trigger') : null;
		if(!btn) return;
		const targetId = btn.getAttribute('data-target') || '';
		// Dar tiempo a que Bulma muestre el modal
		if(targetId === 'modalDressPreview') setTimeout(syncModalDress, 80);
		if(targetId === 'modalSolicitudesAnteriores') setTimeout(cargarSolicitudesAnteriores, 80);
	});

	// Abrir detalle desde el historial
	document.addEventListener('click', (e) => {
		const el = eventTargetElement(e);
		const btn = (el && el.closest) ? el.closest('.js-ver-solicitud') : null;
		if(!btn) return;
		const index = Number(btn.getAttribute('data-sol-index'));
		if(!Number.isInteger(index) || index < 0) return;
		const r = SOLICITUDES_ANTERIORES[index];
		if(!r) return;
		const telaPrecio = Number(r.tela_precio);
		const metros = Number(r.metros_estimados);
		const encajePrecio = Number(r.encaje_precio);
		lastSolicitudSnapshot = {
			fecha: String(r.cita_fecha || ''),
			hora: String(r.cita_hora || ''),
			talla: String(r.talla || ''),
			telaNombre: String(r.tela_nombre || ''),
			telaPrecio: telaPrecio,
			metros: metros,
			total: (isFinite(telaPrecio) && isFinite(metros)) ? (telaPrecio * metros) : NaN,
			encajeNombre: String(r.encaje_nombre || ''),
			encajePrecio: encajePrecio,
			vestidoDetalle: String(r.vestido_detalle || ''),
		};
		renderSolicitudDetalle(lastSolicitudSnapshot);
		// Los botones "Ver detalle" son dinámicos, así que abrimos el modal manualmente
		closeModalById('modalSolicitudesAnteriores');
		openModalById('modalSolicitudDetalle');
	});

	async function cargarTelas(){
		try{
			showEstado('Cargando telas...', 'info');
			const fd = new FormData();
			fd.append('modulo_tela', 'listarPublico');
			const res = await fetch((window.APP_URL || '') + 'app/ajax/telaAjax.php', { method: 'POST', body: fd });
			const json = await res.json();

			if(!json || json.ok !== true){
				showEstado('No se pudo cargar el listado de telas.', 'danger');
				return;
			}

			const telas = Array.isArray(json.data) ? json.data : [];
			if(telas.length === 0){
				showEstado(json.message || 'No hay telas activas para mostrar.', 'warning');
				listWrap.innerHTML = '';
				return;
			}

			estado.style.display = 'none';
			listWrap.innerHTML = '';

			// Cuadrícula compacta: miniatura de la textura + nombre + precio.
			// La descripción se muestra una sola vez, debajo, para la tela elegida.
			const telaGuardada = BORRADOR_INICIAL ? String(BORRADOR_INICIAL.telaId || '') : '';
			const telaInicial = telas.some(x => String(x.tela_id) === telaGuardada) ? telaGuardada : String(telas[0].tela_id);
			const form = document.createElement('div');
			form.className = 'telas-grilla';
			form.setAttribute('role', 'radiogroup');
			form.setAttribute('aria-label', 'Tipo de tela');
			telas.forEach((t, idx) => {
				const id = String(t.tela_id);
				const nombre = t.tela_nombre || 'Tela';
				const precio = t.tela_precio;
				const desc = t.tela_descripcion || '';
				const textura = t.tela_textura_imagen || '';
				const texturaUrl = resolveTextureUrl(textura);

				const opcion = document.createElement('label');
				opcion.className = 'tela-opcion';
				opcion.title = desc ? (nombre + ' — ' + desc) : nombre;
				opcion.innerHTML =
					'<input type="radio" class="is-sr-only" name="tela_id" value="' + id.replace(/"/g,'') + '" ' + (id === telaInicial ? 'checked' : '') +
					' data-precio="' + String(precio).replace(/"/g,'') + '" data-textura="' + textura.replace(/"/g,'') + '"' +
					' data-nombre="' + escapeHtml(nombre) + '" data-descripcion="' + escapeHtml(desc) + '">' +
					'<span class="tela-muestra"' + (texturaUrl ? ' style="background-image:url(&quot;' + escapeHtml(texturaUrl) + '&quot;)"' : '') + '></span>' +
					'<span class="tela-texto">' +
						'<strong class="tela-nombre">' + escapeHtml(nombre) + '</strong>' +
						'<span class="tela-precio">' + escapeHtml(formatMoney(precio)) + ' / m</span>' +
					'</span>';

				form.appendChild(opcion);
			});

			listWrap.appendChild(form);
			const detalle = document.createElement('p');
			detalle.className = 'tela-detalle';
			detalle.id = 'telaDetalle';
			listWrap.appendChild(detalle);

			// Selección inicial (la del borrador si existe)
			const first = listWrap.querySelector('input[type="radio"][name="tela_id"]:checked');
			if(first){
				applySelection(first);
			}

			listWrap.addEventListener('change', (e) => {
				const target = e.target;
				if(target && target.matches('input[type="radio"][name="tela_id"]')){
					applySelection(target);
				}
			});
		}catch(err){
			showEstado('Error al cargar telas.', 'danger');
		}
	}

	async function cargarEncajes(){
		try{
			if(encajeCarousel){
				encajeCarousel.innerHTML = '<div class="notification is-light">Cargando encajes...</div>';
			}
			const fd = new FormData();
			fd.append('modulo_encaje','listarPublico');
			const res = await fetch((window.APP_URL || '') + 'app/ajax/encajeAjax.php', { method: 'POST', body: fd });
			const json = await res.json();
			if(!json || json.ok !== true){
				if(encajeCarousel){
					encajeCarousel.innerHTML = '<div class="notification is-warning">No se pudieron cargar los encajes.</div>';
				}
				ENCAJES = [];
				updateResumen();
				refreshSubmitState();
				return;
			}
			ENCAJES = Array.isArray(json.data) ? json.data : [];
			renderEncajes();
		}catch(err){
			if(encajeCarousel){
				encajeCarousel.innerHTML = '<div class="notification is-warning">No se pudieron cargar los encajes.</div>';
			}
			ENCAJES = [];
			updateResumen();
			refreshSubmitState();
		}
	}

	function applySelection(radio){
		const detalle = qs('#telaDetalle');
		if(detalle){
			const nombre = radio.getAttribute('data-nombre') || '';
			const desc = radio.getAttribute('data-descripcion') || '';
			detalle.innerHTML = '<strong>' + escapeHtml(nombre) + '</strong>' + (desc ? (' · ' + escapeHtml(desc)) : '');
		}
		const textura = radio.getAttribute('data-textura');
		const precio = radio.getAttribute('data-precio');
		const seed = radio.value || radio.getAttribute('value') || 'tela';
		const nombreTela = radio.getAttribute('data-nombre') || '';
		refreshSummary(precio);
		actualizarEtiquetaEstudio();
		if(dressScene) dressScene.setFabric(textura, seed, nombreTela);
		if(dressPreviewModal) dressPreviewModal.setFabric(textura, seed, nombreTela);
	}

	function renderEncajes(){
		if(!encajeCarousel) return;
		encajeCarousel.innerHTML = '';
		const base = (window.APP_URL || '').replace(/\/$/, '');
		const defaultImg = base + '/app/views/productos/default.png';

		if(!Array.isArray(ENCAJES) || ENCAJES.length === 0){
			encajeCarousel.innerHTML = '<div class="notification is-light">No hay encajes activos para mostrar.</div>';
			if(encajeSelTexto) encajeSelTexto.textContent = '—';
			updateResumen();
			refreshSubmitState();
			return;
		}

		const encajeGuardado = BORRADOR_INICIAL ? String(BORRADOR_INICIAL.encajeId || '') : '';
		const encajeInicial = ENCAJES.some(x => String(x.encaje_id) === encajeGuardado) ? encajeGuardado : String(ENCAJES[0].encaje_id || '');
		ENCAJES.forEach((e, idx) => {
			const card = document.createElement('div');
			card.className = 'card encaje-card';

			const rawImg = String(e.encaje_imagen || '').trim();
			const imgUrl = rawImg ? (base + '/' + rawImg.replace(/^\.\//, '')) : defaultImg;
			const id = String(e.encaje_id || '');
			const nombre = String(e.encaje_nombre || 'Encaje');
			const precio = Number(e.encaje_precio);

			card.innerHTML =
				'<div class="card-image">' +
					'<figure class="image is-4by3">' +
						'<img src="' + imgUrl.replace(/"/g,'') + '" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'' + defaultImg + '\'' + ';">' +
					'</figure>' +
				'</div>' +
				'<div class="card-content" style="padding: .9rem;">' +
					'<label class="radio" style="display:block;">' +
						'<input type="radio" name="encaje_id" value="' + id.replace(/"/g,'') + '" ' + (id === encajeInicial ? 'checked' : '') + '> ' +
						'<strong>' + escapeHtml(nombre) + '</strong>' +
						'<span class="is-pulled-right">' + escapeHtml(formatMoney(precio)) + '</span>' +
					'</label>' +
					'<p class="mt-2 mb-0 has-text-grey is-size-7">Precio por 1.5 m</p>' +
				'</div>';

			encajeCarousel.appendChild(card);
		});

		const updateEncajeTexto = () => {
			const enc = getSelectedEncaje();
			if(encajeSelTexto){
				encajeSelTexto.textContent = enc ? (String(enc.encaje_nombre || '') + ' — ' + formatMoney(enc.encaje_precio) + ' / 1.5 m') : '—';
			}
			updateResumen();
			refreshSubmitState();
		};

		updateEncajeTexto();
		document.addEventListener('change', (ev) => {
			const t = ev.target;
			if(t && t.matches && t.matches('input[type="radio"][name="encaje_id"]')){
				updateEncajeTexto();
			}
		});
	}

	function initCarouselControls(){
		if(!encajeCarousel) return;
		const scrollBy = (dir) => {
			const amount = 260;
			encajeCarousel.scrollBy({ left: dir * amount, behavior: 'smooth' });
		};
		if(encajePrevBtn) encajePrevBtn.addEventListener('click', () => scrollBy(-1));
		if(encajeNextBtn) encajeNextBtn.addEventListener('click', () => scrollBy(1));
	}

	function initWizardNav(){
		// Tabs click
		if(wizardTabs){
			wizardTabs.addEventListener('click', (e) => {
				const el = eventTargetElement(e);
				const li = (el && el.closest) ? el.closest('li[data-step]') : null;
				if(!li) return;
				const step = Number(li.getAttribute('data-step'));
				if(!step) return;
				showStep(step);
			});
		}

		// Next/prev buttons
		document.addEventListener('click', (e) => {
			const el = eventTargetElement(e);
			const next = (el && el.closest) ? el.closest('[data-next-step]') : null;
			const prev = (el && el.closest) ? el.closest('[data-prev-step]') : null;
			if(next){
				const step = Number(next.getAttribute('data-next-step'));
				if(step) showStep(step);
			}
			if(prev){
				const step = Number(prev.getAttribute('data-prev-step'));
				if(step) showStep(step);
			}
		});
	}

	function initCita(){
		if(!citaFecha || !citaHora || !citaHelp) return;
		const today = new Date();
		const y = today.getFullYear();
		const m = String(today.getMonth()+1).padStart(2,'0');
		const d = String(today.getDate()).padStart(2,'0');
		citaFecha.min = `${y}-${m}-${d}`;

		const resetTimes = (msg) => {
			citaHora.innerHTML = `<option value="">${msg}</option>`;
			citaHora.disabled = true;
			refreshSubmitState();
		};

		const loadTimes = async () => {
			const fecha = citaFecha.value;
			if(!fecha){
				citaHelp.textContent = 'Horario: 10:00 am a 07:00 pm';
				return resetTimes('Selecciona una fecha primero');
			}

			citaHelp.textContent = 'Cargando horarios disponibles...';
			citaHora.innerHTML = '<option value="">Cargando...</option>';
			citaHora.disabled = true;
			refreshSubmitState();

			try{
				const fd = new FormData();
				fd.append('modulo_reserva','horarios');
				fd.append('cita_fecha', fecha);

				const resp = await fetch((window.APP_URL || '') + 'app/ajax/reservaAjax.php', {
					method: 'POST',
					body: fd
				});
				const json = await resp.json();
				if(!json || json.ok !== true){
					citaHelp.textContent = (json && json.mensaje) ? json.mensaje : 'No se pudieron cargar horarios';
					return resetTimes('Sin horarios');
				}

				const available = Array.isArray(json.available) ? json.available : [];
				if(available.length === 0){
					citaHelp.textContent = 'No hay horarios disponibles para esta fecha';
					return resetTimes('Sin horarios disponibles');
				}

				citaHora.innerHTML = '<option value="">Selecciona una hora</option>' +
					available.map(h => `<option value="${h}">${h}</option>`).join('');
				citaHora.disabled = false;
				if(horaPendiente && available.includes(horaPendiente)) citaHora.value = horaPendiente;
				horaPendiente = '';
				citaHelp.textContent = 'Horario: 10:00 am a 07:00 pm';
				refreshSubmitState();
			}catch(e){
				citaHelp.textContent = 'No se pudieron cargar horarios';
				resetTimes('Sin horarios');
			}
		};

		citaFecha.addEventListener('change', loadTimes);
		citaHora.addEventListener('change', refreshSubmitState);
		return loadTimes;
	}

	let horaPendiente = '';

	async function enviarSolicitud(){
		if(!CLIENTE_LOGUEADO){
			showWizardMsg('Debes iniciar sesión para enviar la solicitud.', 'warning');
			return;
		}

		const tela = getSelectedTela();
		const encaje = getSelectedEncaje();
		if(!tela || !tela.id){
			showWizardMsg('Selecciona una tela antes de enviar.', 'warning');
			showStep(2);
			return;
		}
		if(!encaje){
			showWizardMsg('Selecciona un encaje antes de enviar.', 'warning');
			showStep(3);
			return;
		}
		if(!citaFecha || !citaHora || !citaFecha.value || !citaHora.value){
			showWizardMsg('Selecciona fecha y hora de la cita.', 'warning');
			return;
		}

		if(btnEnviar) btnEnviar.disabled = true;
		showWizardMsg('Enviando solicitud...', 'info');

		try{
			const fd = new FormData();
			fd.append('modulo_reserva', 'personalizada_crear');
			fd.append('cita_fecha', citaFecha.value);
			fd.append('cita_hora', citaHora.value);
			fd.append('talla', tallaSel ? String(tallaSel.value || 'M') : 'M');
			fd.append('altura', String(getAltura()));
			fd.append('tela_id', tela.id);
			fd.append('encaje_id', String(encaje.encaje_id || ''));
			if(CURRENT_PROBADOR_ID > 0){
				fd.append('probador_id', String(CURRENT_PROBADOR_ID));
			}
			fd.append('vestido_detalle', vestidoDetalle ? String(vestidoDetalle.value || '') : '');

			const resp = await fetch((window.APP_URL || '') + 'app/ajax/reservaAjax.php', {
				method: 'POST',
				body: fd
			});
			const json = await resp.json();
			if(!json || json.ok !== true){
				showWizardMsg((json && json.mensaje) ? json.mensaje : 'No se pudo enviar la solicitud.', 'danger');
				refreshSubmitState();
				return;
			}

			showWizardMsg('Se realizó correctamente.', 'success');
			borrarBorrador();
			lastSolicitudSnapshot = buildSolicitudSnapshot();
			if(CURRENT_PROBADOR_ID > 0){
				clearProbadorIdState();
			}
			if(solicitudesBadge) solicitudesBadge.style.display = 'inline-flex';
			if(btnEnviar) btnEnviar.disabled = true;
		}catch(e){
			showWizardMsg('No se pudo enviar la solicitud. Intenta nuevamente.', 'danger');
			refreshSubmitState();
		}
	}

	function escapeHtml(str){
		return String(str)
			.replace(/&/g,'&amp;')
			.replace(/</g,'&lt;')
			.replace(/>/g,'&gt;')
			.replace(/"/g,'&quot;')
			.replace(/\'/g,'&#039;');
	}

	document.addEventListener('DOMContentLoaded', () => {
		loadProbadorIdState();
		cargarTelas();
		cargarEncajes();
		initCarouselControls();
		initWizardNav();
		const cargarHorarios = initCita();

		const b = BORRADOR_INICIAL;
		if(b){
			if(tallaSel && b.talla && MEDIDAS.metrosBase[b.talla] !== undefined) tallaSel.value = b.talla;
			if(alturaInput && b.altura) alturaInput.value = String(b.altura);
		}
		initMedidor();

		const hoyIso = new Date(Date.now() - new Date().getTimezoneOffset() * 6e4).toISOString().slice(0, 10);
		if(b && b.citaFecha && b.citaFecha >= hoyIso && citaFecha && cargarHorarios){
			citaFecha.value = b.citaFecha;
			horaPendiente = String(b.citaHora || '');
			cargarHorarios();
		}
		if(b && (b.vista === 'tela' || b.vista === 'vestido')) cambiarVistaEstudio(b.vista);
		showStep(b && steps[b.paso] ? b.paso : 1);
		borradorListo = true;
		if(b){
			showWizardMsg('Recuperamos lo que habías elegido. Puedes seguir donde lo dejaste.', 'info');
		}

		// Cualquier cambio dentro del personalizador actualiza el borrador
		const wizard = qs('#wizardStep1') ? qs('#wizardStep1').parentElement : document;
		wizard.addEventListener('change', guardarBorrador);
		if(alturaInput) alturaInput.addEventListener('input', guardarBorrador);
		if(btnEnviar){
			btnEnviar.addEventListener('click', enviarSolicitud);
		}
	});
	if(tallaSel){
		tallaSel.addEventListener('change', () => {
			const sel = currentSelection();
			if(sel) applySelection(sel);
			else refreshSummary(NaN);
			refreshSubmitState();
		});
	}
})();
