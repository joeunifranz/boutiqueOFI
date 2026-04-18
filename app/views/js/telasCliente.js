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
	const canvasPreview = qs('#fabricPreviewCanvas');
	const dressCanvas = qs('#dress3dCanvas');
	const modalCanvas = qs('#fabricPreviewCanvasModal');
	const dressModalCanvas = qs('#dress3dCanvasModal');
	const tallaSel = qs('#tallaVestido');

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

	let ENCAJES = [];
	let lastSolicitudSnapshot = null;
	let SOLICITUDES_ANTERIORES = [];

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
		const tela = getSelectedTela();
		const encaje = getSelectedEncaje();
		const metros = estimateMeters(talla || 'M');
		const total = (tela && isFinite(tela.precio) && isFinite(metros)) ? (tela.precio * metros) : NaN;
		return {
			fecha: citaFecha ? String(citaFecha.value || '') : '',
			hora: citaHora ? String(citaHora.value || '') : '',
			talla,
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
			const id = String(r.solicitud_id || '');
			const cita = String((r.cita_fecha || '') + ' ' + (r.cita_hora || '')).trim();
			const estado = String(r.estado || '');
			const creado = String(r.creado_en || '');
			const tr = document.createElement('tr');
			tr.innerHTML =
				'<td>' + escapeHtml(id) + '</td>' +
				'<td>' + escapeHtml(cita || '—') + '</td>' +
				'<td>' + escapeHtml(estado || '—') + '</td>' +
				'<td>' + escapeHtml(creado || '—') + '</td>' +
				'<td class="has-text-right">' +
					'<button type="button" class="button is-small is-link is-light is-rounded js-modal-trigger js-ver-solicitud" data-target="modalSolicitudDetalle" data-sol-id="' + escapeHtml(id) + '">Ver detalle</button>' +
				'</td>';
			solicitudesAnterioresTbody.appendChild(tr);
		});
	}

	async function cargarSolicitudesAnteriores(){
		if(!CLIENTE_LOGUEADO){
			showSolicitudesEstado('Debes iniciar sesión para ver tus solicitudes.', 'warning');
			return;
		}
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

	function getAutoComplexityMultiplier(){
		// Complejidad automática (sin selector): ajusta este factor si deseas.
		return 1.15;
	}

	function estimateMeters(talla){
		// Estimación simple (puedes ajustar los números a tu criterio)
		const baseBySize = {
			'XS': 2.4,
			'S': 2.6,
			'M': 2.8,
			'L': 3.0,
			'XL': 3.2,
			'XXL': 3.4
		};
		const base = baseBySize[String(talla || '').toUpperCase()] ?? baseBySize['M'];
		const mult = getAutoComplexityMultiplier();
		// Redondeo al 0.1
		return Math.round((base * mult) * 10) / 10;
	}

	function getSelectedTela(){
		const sel = listWrap ? listWrap.querySelector('input[type="radio"][name="tela_id"]:checked') : null;
		if(!sel) return null;
		const nombreStrong = sel.closest('.box') ? sel.closest('.box').querySelector('strong') : null;
		return {
			id: String(sel.value || ''),
			precio: Number(sel.getAttribute('data-precio')),
			textura: sel.getAttribute('data-textura') || '',
			nombre: nombreStrong ? (nombreStrong.textContent || '') : '',
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
		const tela = getSelectedTela();
		const encaje = getSelectedEncaje();

		if(resumenTalla) resumenTalla.textContent = talla || '—';
		if(resumenTela){
			if(tela && tela.id){
				const metros = estimateMeters(talla || 'M');
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
				if(fabricPreview) fabricPreview.resize();
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

	function createScene3D(targetCanvas, mode){
		if(!window.THREE || !targetCanvas) return null;

		const renderer = new THREE.WebGLRenderer({ canvas: targetCanvas, antialias: true, alpha: true });
		renderer.setPixelRatio(window.devicePixelRatio || 1);

		const scene = new THREE.Scene();
		const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
		// Objetivo para mantener el 3D centrado (se ajusta por modo)
		const lookAtTarget = new THREE.Vector3(0, 0, 0);
		camera.position.set(0, 0.85, 2.2);

		const ambient = new THREE.AmbientLight(0xffffff, 1.0);
		scene.add(ambient);
		const dir = new THREE.DirectionalLight(0xffffff, 0.8);
		dir.position.set(2, 3, 2);
		scene.add(dir);

		const group = new THREE.Group();
		scene.add(group);

		const material = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.85, metalness: 0.0 });
		// Importante: que la tela se vea por ambos lados al rotar
		material.side = THREE.DoubleSide;
		let clothMesh = null;
		let dressMeshes = [];

		if(mode === 'cloth'){
			// Paño ondulante (más "3D" que un plano fijo)
			const geometry = new THREE.PlaneGeometry(1.9, 1.3, 80, 60);
			clothMesh = new THREE.Mesh(geometry, material);
			clothMesh.rotation.x = -0.55;
			clothMesh.position.y = 0.02;
			group.add(clothMesh);
			lookAtTarget.set(0, 0.05, 0);
			camera.position.set(0, 0.65, 2.05);
		}else{
			// Vestido placeholder (sin assets externos)
			const dress = new THREE.Group();
			dress.position.y = 0.08;
			group.add(dress);

			const skirtGeo = new THREE.ConeGeometry(0.85, 1.55, 64, 32, true);
			const skirt = new THREE.Mesh(skirtGeo, material);
			skirt.position.y = -0.1;
			skirt.rotation.y = 0.2;
			dress.add(skirt);

			const topGeo = new THREE.CylinderGeometry(0.38, 0.48, 0.7, 48, 24, true);
			const top = new THREE.Mesh(topGeo, material);
			top.position.y = 0.7;
			dress.add(top);

			const beltGeo = new THREE.TorusGeometry(0.48, 0.06, 16, 64);
			const beltMat = new THREE.MeshStandardMaterial({ color: 0x222222, roughness: 0.9, metalness: 0.0 });
			const belt = new THREE.Mesh(beltGeo, beltMat);
			belt.position.y = 0.35;
			belt.rotation.x = Math.PI/2;
			dress.add(belt);

			dressMeshes = [skirt, top];

			// Centrado del vestido en el canvas
			lookAtTarget.set(0, 0.45, 0);
			camera.position.set(0, 0.95, 2.6);
		}

		const textureLoader = new THREE.TextureLoader();
		let currentTexture = null;
		let currentProcedural = null;

		function resize(){
			const rect = targetCanvas.getBoundingClientRect();
			const w = Math.max(1, Math.floor(rect.width));
			const h = Math.max(1, Math.floor(rect.height));
			renderer.setSize(w, h, false);
			camera.aspect = w / h;
			camera.updateProjectionMatrix();
		}

		function applyMaterialMap(tex){
			material.map = tex;
			material.needsUpdate = true;
		}

		function clearTextures(){
			if(currentTexture){
				currentTexture.dispose();
				currentTexture = null;
			}
			if(currentProcedural){
				currentProcedural.dispose();
				currentProcedural = null;
			}
			applyMaterialMap(null);
			material.color.setHex(0xffffff);
		}

		function setFabricByUrlOrSeed(url, seed){
			clearTextures();
			const resolved = resolveTextureUrl(url);
			if(!resolved){
				currentProcedural = generateWeaveTexture(seed || 'tela');
				applyMaterialMap(currentProcedural);
				return;
			}

			textureLoader.load(
				resolved,
				(tex) => {
					tex.wrapS = THREE.RepeatWrapping;
					tex.wrapT = THREE.RepeatWrapping;
					tex.repeat.set(mode === 'cloth' ? 2.2 : 1.4, mode === 'cloth' ? 2.0 : 1.4);
					tex.anisotropy = 4;
					currentTexture = tex;
					applyMaterialMap(tex);
				},
				undefined,
				() => {
					// Si falla la textura, caer a procedimental.
					currentProcedural = generateWeaveTexture(seed || 'tela');
					applyMaterialMap(currentProcedural);
				}
			);
		}

		let animId = 0;
		let t0 = performance.now();
		function animate(){
			animId = requestAnimationFrame(animate);
			const t = (performance.now() - t0) * 0.001;
			camera.lookAt(lookAtTarget);
			if(mode === 'cloth' && clothMesh){
				// ondas suaves para simular tela
				const pos = clothMesh.geometry.attributes.position;
				for(let i=0;i<pos.count;i++){
					const x = pos.getX(i);
					const y = pos.getY(i);
					const wave = Math.sin((x*2.2) + t*2.2) * 0.04 + Math.cos((y*2.0) + t*1.6) * 0.03;
					pos.setZ(i, wave);
				}
				pos.needsUpdate = true;
				clothMesh.geometry.computeVertexNormals();
				group.rotation.y += 0.003;
			}
			if(mode === 'dress'){
				group.rotation.y += 0.004;
			}
			renderer.render(scene, camera);
		}

		resize();
		animate();

		window.addEventListener('resize', resize);

		return { setFabric: setFabricByUrlOrSeed, resize, stop: () => cancelAnimationFrame(animId) };
	}

	const fabricPreview = createScene3D(canvasPreview, 'cloth');
	const dressScene = createScene3D(dressCanvas, 'dress');
	let fabricPreviewModal = null;
	let dressPreviewModal = null;

	function currentSelection(){
		return listWrap ? listWrap.querySelector('input[type="radio"][name="tela_id"]:checked') : null;
	}

	function refreshSummary(precioPorMetro){
		const talla = tallaSel ? tallaSel.value : 'M';
		const metros = estimateMeters(talla);
		if(metrosTexto) metrosTexto.textContent = formatMeters(metros);
		const p = Number(precioPorMetro);
		if(totalTexto) totalTexto.textContent = (isFinite(p) ? formatMoney(metros * p) : '—');
		updateResumen();
		refreshSubmitState();
	}

	function syncModalFabric(){
		if(!modalCanvas) return;
		if(!fabricPreviewModal){
			fabricPreviewModal = createScene3D(modalCanvas, 'cloth');
		}
		const sel = currentSelection();
		if(sel && fabricPreviewModal){
			const textura = sel.getAttribute('data-textura');
			const seed = sel.value || sel.getAttribute('value') || 'tela';
			fabricPreviewModal.setFabric(textura, seed);
			setTimeout(() => fabricPreviewModal && fabricPreviewModal.resize(), 50);
		}
	}

	function syncModalDress(){
		if(!dressModalCanvas) return;
		if(!dressPreviewModal){
			dressPreviewModal = createScene3D(dressModalCanvas, 'dress');
		}
		const sel = currentSelection();
		if(sel && dressPreviewModal){
			const textura = sel.getAttribute('data-textura');
			const seed = sel.value || sel.getAttribute('value') || 'tela';
			dressPreviewModal.setFabric(textura, seed);
			setTimeout(() => dressPreviewModal && dressPreviewModal.resize(), 50);
		}
	}

	// Cuando se abre el modal, sincronizar la tela y ajustar el renderer
	document.addEventListener('click', (e) => {
		const el = eventTargetElement(e);
		const btn = (el && el.closest) ? el.closest('.js-modal-trigger') : null;
		if(!btn) return;
		const targetId = btn.getAttribute('data-target') || '';
		// Dar tiempo a que Bulma muestre el modal
		if(targetId === 'modalFabricPreview') setTimeout(syncModalFabric, 80);
		if(targetId === 'modalDressPreview') setTimeout(syncModalDress, 80);
		if(targetId === 'modalSolicitudesAnteriores') setTimeout(cargarSolicitudesAnteriores, 80);
	});

	// Abrir detalle desde el historial
	document.addEventListener('click', (e) => {
		const el = eventTargetElement(e);
		const btn = (el && el.closest) ? el.closest('.js-ver-solicitud') : null;
		if(!btn) return;
		const id = String(btn.getAttribute('data-sol-id') || '');
		if(!id) return;
		const r = SOLICITUDES_ANTERIORES.find(x => String(x.solicitud_id || '') === id);
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

			const form = document.createElement('div');
			form.className = 'content';
			telas.forEach((t, idx) => {
				const id = String(t.tela_id);
				const nombre = t.tela_nombre || 'Tela';
				const precio = t.tela_precio;
				const desc = t.tela_descripcion || '';
				const textura = t.tela_textura_imagen || '';

				const box = document.createElement('div');
				box.className = 'box';
				box.style.padding = '0.9rem';

				box.innerHTML =
					'<label class="radio" style="display:block;">' +
						'<input type="radio" name="tela_id" value="' + id.replace(/"/g,'') + '" ' + (idx===0 ? 'checked' : '') +
						' data-precio="' + String(precio).replace(/"/g,'') + '" data-textura="' + textura.replace(/"/g,'') + '">' +
						' <strong>' + escapeHtml(nombre) + '</strong>' +
						' <span class="is-pulled-right">' + escapeHtml(formatMoney(precio)) + '</span>' +
					'</label>' +
					(desc ? ('<p class="mt-2 mb-0">' + escapeHtml(desc) + '</p>') : '');

				form.appendChild(box);
			});

			listWrap.appendChild(form);

			// set initial selection
			const first = listWrap.querySelector('input[type="radio"][name="tela_id"]');
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
		const textura = radio.getAttribute('data-textura');
		const precio = radio.getAttribute('data-precio');
		const seed = radio.value || radio.getAttribute('value') || 'tela';
		refreshSummary(precio);
		if(fabricPreview) fabricPreview.setFabric(textura, seed);
		if(dressScene) dressScene.setFabric(textura, seed);
		if(fabricPreviewModal) fabricPreviewModal.setFabric(textura, seed);
		if(dressPreviewModal) dressPreviewModal.setFabric(textura, seed);
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
						'<input type="radio" name="encaje_id" value="' + id.replace(/"/g,'') + '" ' + (idx===0 ? 'checked' : '') + '> ' +
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
				citaHelp.textContent = 'Horario: 10:00 am a 07:00 pm';
				refreshSubmitState();
			}catch(e){
				citaHelp.textContent = 'No se pudieron cargar horarios';
				resetTimes('Sin horarios');
			}
		};

		citaFecha.addEventListener('change', loadTimes);
		citaHora.addEventListener('change', refreshSubmitState);
	}

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
			fd.append('tela_id', tela.id);
			fd.append('encaje_id', String(encaje.encaje_id || ''));
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

			showWizardMsg(json.mensaje || 'Solicitud enviada. Te contactaremos pronto.', 'success');
			lastSolicitudSnapshot = buildSolicitudSnapshot();
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
		cargarTelas();
		cargarEncajes();
		initCarouselControls();
		initWizardNav();
		initCita();
		showStep(1);
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
