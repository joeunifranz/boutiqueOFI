(function(){
	function qs(sel){ return document.querySelector(sel); }

	const estado = qs('#telasEstado');
	const listWrap = qs('#telasList');
	const precioTexto = qs('#telaPrecioTexto');
	const canvasPreview = qs('#fabricPreviewCanvas');
	const dressCanvas = qs('#dress3dCanvas');

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

	function createPreview3D(targetCanvas){
		if(!window.THREE || !targetCanvas) return null;

		const renderer = new THREE.WebGLRenderer({ canvas: targetCanvas, antialias: true, alpha: true });
		renderer.setPixelRatio(window.devicePixelRatio || 1);

		const scene = new THREE.Scene();
		const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
		camera.position.set(0, 0.9, 2.2);

		const ambient = new THREE.AmbientLight(0xffffff, 1.0);
		scene.add(ambient);
		const dir = new THREE.DirectionalLight(0xffffff, 0.8);
		dir.position.set(2, 3, 2);
		scene.add(dir);

		const geometry = new THREE.PlaneGeometry(1.6, 1.1, 1, 1);
		const material = new THREE.MeshStandardMaterial({ color: 0xdddddd, roughness: 0.9, metalness: 0.0 });
		const mesh = new THREE.Mesh(geometry, material);
		mesh.rotation.x = -0.35;
		scene.add(mesh);

		const textureLoader = new THREE.TextureLoader();
		let currentTexture = null;

		function resize(){
			const rect = targetCanvas.getBoundingClientRect();
			const w = Math.max(1, Math.floor(rect.width));
			const h = Math.max(1, Math.floor(rect.height));
			renderer.setSize(w, h, false);
			camera.aspect = w / h;
			camera.updateProjectionMatrix();
		}

		function setTexture(url){
			if(!url){
				if(currentTexture){
					currentTexture.dispose();
					currentTexture = null;
				}
				mesh.material.map = null;
				mesh.material.needsUpdate = true;
				mesh.material.color.setHex(0xdddddd);
				return;
			}

			textureLoader.load(
				url,
				(tex) => {
					tex.wrapS = THREE.RepeatWrapping;
					tex.wrapT = THREE.RepeatWrapping;
					tex.repeat.set(1.6, 1.1);
					if(currentTexture){
						currentTexture.dispose();
					}
					currentTexture = tex;
					mesh.material.map = tex;
					mesh.material.color.setHex(0xffffff);
					mesh.material.needsUpdate = true;
				},
				undefined,
				() => {
					// Si falla la textura, no bloqueamos.
					setTexture(null);
				}
			);
		}

		let animId = 0;
		function animate(){
			animId = requestAnimationFrame(animate);
			mesh.rotation.z += 0.005;
			renderer.render(scene, camera);
		}

		resize();
		animate();

		window.addEventListener('resize', resize);

		return { setTexture, resize, stop: () => cancelAnimationFrame(animId) };
	}

	const fabricPreview = createPreview3D(canvasPreview);
	createPreview3D(dressCanvas); // placeholder: sin textura por ahora

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
		if(precioTexto) precioTexto.textContent = formatMoney(precio);
		if(fabricPreview) fabricPreview.setTexture(textura);
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
