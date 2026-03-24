(function(){
	function qs(sel){ return document.querySelector(sel); }

	const estado = qs('#telasEstado');
	const listWrap = qs('#telasList');
	const precioTexto = qs('#telaPrecioTexto');
	const canvasPreview = qs('#fabricPreviewCanvas');
	const dressCanvas = qs('#dress3dCanvas');
	const modalCanvas = qs('#fabricPreviewCanvasModal');

	function showEstado(msg, type){
		if(!estado) return;
		estado.style.display = 'block';
		estado.className = 'notification is-' + (type || 'info') + ' is-light';
		estado.textContent = msg;
	}

	function formatMoney(value){
		const n = Number(value);
		if(!isFinite(n)) return '—';
		return (window.MONEDA_SIMBOLO || '') + n.toFixed(2);
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
			dress.position.y = 0.0;
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

			lookAtTarget.set(0, 0.55, 0);
			camera.position.set(0, 1.05, 2.6);
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

	function currentSelection(){
		return listWrap ? listWrap.querySelector('input[type="radio"][name="tela_id"]:checked') : null;
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

	// Cuando se abre el modal, sincronizar la tela y ajustar el renderer
	document.addEventListener('click', (e) => {
		const t = e.target;
		if(!t) return;
		if(t.matches('.js-modal-trigger')){
			// Dar tiempo a que Bulma muestre el modal
			setTimeout(syncModalFabric, 80);
		}
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
				const textura = t.tela_textura_url || '';

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

	function applySelection(radio){
		const precio = radio.getAttribute('data-precio');
		const textura = radio.getAttribute('data-textura');
		const seed = radio.value || radio.getAttribute('value') || 'tela';
		if(precioTexto) precioTexto.textContent = formatMoney(precio);
		if(fabricPreview) fabricPreview.setFabric(textura, seed);
		if(dressScene) dressScene.setFabric(textura, seed);
		if(fabricPreviewModal) fabricPreviewModal.setFabric(textura, seed);
	}

	function escapeHtml(str){
		return String(str)
			.replace(/&/g,'&amp;')
			.replace(/</g,'&lt;')
			.replace(/>/g,'&gt;')
			.replace(/"/g,'&quot;')
			.replace(/\'/g,'&#039;');
	}

	document.addEventListener('DOMContentLoaded', cargarTelas);
})();
