/**
 * Estudio 3D del personalizador (telasCliente, paso 2).
 *
 * Una sola escena con dos puntos de vista:
 *   - "vestido": vestido de una pieza sobre un maniquí de modista, en una tarima.
 *   - "tela":    muestra de la tela colgada de un perchero, movida por una brisa suave.
 * La cámara viaja entre los dos. El material cambia según el tipo de tela
 * (satín brillante, tul/gasa translúcidos sobre forro, visón aterciopelado).
 *
 * Uso:
 *   const estudio = crearEstudio3D(canvas, { resolverUrl, texturaRespaldo });
 *   estudio.setFabric(urlTextura, semilla, nombreTela);
 *   estudio.setVista('vestido' | 'tela');
 *
 * Requiere three.js r160 (global THREE).
 */
(function(){
	'use strict';

	const ALTO_VESTIDO = 1.34;      // borde superior del corpiño
	const POS_PERCHERO = 3.4;       // x de la muestra de tela
	const VISTAS = {
		vestido: { camara: [0, 1.0, 3.75], objetivo: [0, 0.8, 0] },
		tela:    { camara: [POS_PERCHERO + 0.4, 1.2, 3.8], objetivo: [POS_PERCHERO, 1.0, 0] },
	};

	// Caché de imágenes compartido entre estudios (página y "Ver grande")
	const cacheTexturas = new Map();

	function cargarTextura(url){
		if(!cacheTexturas.has(url)){
			cacheTexturas.set(url, new Promise((ok, falla) => {
				new THREE.TextureLoader().load(url, ok, undefined, falla);
			}));
		}
		return cacheTexturas.get(url);
	}

	function perfilTela(nombre){
		const n = String(nombre || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
		const brillo = /brill|diamant|pedrer|glitter|escarch|lentejuel/.test(n);
		if(/satin|saten|raso|charmeuse|seda|mikado/.test(n)) return { tipo: 'satin', brillo };
		if(/tul|gasa|organza|chifon|chiffon|velo|georgette/.test(n)) return { tipo: 'velo', brillo };
		if(/vison|terciopelo|velvet|pana|felpa/.test(n)) return { tipo: 'terciopelo', brillo };
		return { tipo: 'mate', brillo };
	}

	// Color promedio de la textura (para teñir el forro debajo del tul)
	function colorPromedio(imagen){
		try{
			const c = document.createElement('canvas');
			c.width = c.height = 8;
			const ctx = c.getContext('2d');
			ctx.drawImage(imagen, 0, 0, 8, 8);
			const d = ctx.getImageData(0, 0, 8, 8).data;
			let r = 0, g = 0, b = 0;
			for(let i = 0; i < d.length; i += 4){ r += d[i]; g += d[i + 1]; b += d[i + 2]; }
			const n = d.length / 4;
			return new THREE.Color(r / n / 255, g / n / 255, b / n / 255).convertSRGBToLinear();
		}catch(e){
			return new THREE.Color(0xefe6da).convertSRGBToLinear();
		}
	}

	// Perfil suave (radio, y) -> puntos para LatheGeometry
	function perfil(puntos, cantidad){
		const curva = new THREE.CatmullRomCurve3(puntos.map(([r, y]) => new THREE.Vector3(r, y, 0)), false, 'centripetal');
		return curva.getPoints(cantidad).map(p => new THREE.Vector2(Math.max(0.0001, p.x), p.y));
	}

	// Cuerpo ovalado (no cilíndrico): más plano adelante/atrás arriba, más redondo en el ruedo
	function ovalo(y){
		const k = Math.min(1, Math.max(0, y / ALTO_VESTIDO));
		return 0.94 - 0.22 * k;
	}

	function geometriaVestido(){
		const puntos = perfil([
			[0.205, 1.34], [0.214, 1.29], [0.196, 1.19], [0.168, 1.09], [0.172, 1.03],
			[0.24, 0.94], [0.36, 0.77], [0.5, 0.52], [0.62, 0.27], [0.71, 0.07], [0.735, 0.0],
		], 140);
		const g = new THREE.LatheGeometry(puntos, 220);
		const pos = g.attributes.position;
		for(let i = 0; i < pos.count; i++){
			const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i);
			const ang = Math.atan2(x, z);
			let r = Math.hypot(x, z);
			// Pliegues de la falda: nacen en la cadera y se abren hacia el ruedo
			if(y < 0.98){
				const k = Math.pow((0.98 - y) / 0.98, 1.25);
				const pliegue = Math.sin(14 * ang + 0.3) + 0.45 * Math.sin(7 * ang + 1.7) + 0.2 * Math.sin(23 * ang + 0.9);
				r *= 1 + 0.06 * k * pliegue;
			}
			let yy = y;
			if(y < 0.012) yy = y + 0.012 * Math.sin(9 * ang + 0.5); // ruedo irregular
			pos.setXYZ(i, r * Math.sin(ang), yy, r * Math.cos(ang) * ovalo(y));
		}
		g.computeVertexNormals();
		return g;
	}

	function geometriaManiqui(){
		const puntos = perfil([
			[0.19, 1.31], [0.205, 1.36], [0.188, 1.425], [0.14, 1.465], [0.072, 1.495],
			[0.055, 1.54], [0.052, 1.62], [0.06, 1.655], [0.0001, 1.66],
		], 80);
		const g = new THREE.LatheGeometry(puntos, 96);
		const pos = g.attributes.position;
		for(let i = 0; i < pos.count; i++){
			const y = pos.getY(i);
			// hombros más anchos que profundos
			const ancho = 1 + 0.06 * Math.max(0, Math.sin(Math.PI * (y - 1.34) / 0.16));
			pos.setX(i, pos.getX(i) * ancho);
			pos.setZ(i, pos.getZ(i) * ovalo(Math.min(y, ALTO_VESTIDO)));
		}
		g.computeVertexNormals();
		return g;
	}

	// Entorno de estudio para los reflejos: caja oscura con paneles de luz
	function entornoEstudio(renderer){
		const escena = new THREE.Scene();
		const caja = new THREE.Mesh(new THREE.BoxGeometry(10, 10, 10), new THREE.MeshBasicMaterial({ color: 0x2a221e, side: THREE.BackSide }));
		escena.add(caja);
		const panel = (ancho, alto, intensidad, x, y, z, ry, rx) => {
			const m = new THREE.Mesh(new THREE.PlaneGeometry(ancho, alto), new THREE.MeshBasicMaterial({ color: new THREE.Color(intensidad, intensidad * 0.96, intensidad * 0.9) }));
			m.position.set(x, y, z);
			m.rotation.set(rx || 0, ry || 0, 0);
			escena.add(m);
		};
		panel(4, 2, 6, 0, 4.9, 0, 0, Math.PI / 2);          // softbox cenital
		panel(1.2, 5, 3.5, -4.9, 1, 1, Math.PI / 2);        // tira izquierda
		panel(1.2, 5, 2.2, 4.9, 1, -1, -Math.PI / 2);       // tira derecha
		panel(3, 2, 1.4, 0, 1.5, 4.9, Math.PI);             // relleno frontal
		const pmrem = new THREE.PMREMGenerator(renderer);
		const tex = pmrem.fromScene(escena, 0.04).texture;
		pmrem.dispose();
		return tex;
	}

	function crearEstudio3D(canvas, opciones){
		if(!window.THREE || !canvas) return null;
		opciones = opciones || {};
		const resolverUrl = opciones.resolverUrl || (u => u);
		const texturaRespaldo = opciones.texturaRespaldo || (() => null);

		let renderer;
		try{
			renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false, powerPreference: 'high-performance' });
		}catch(e){
			return null; // sin WebGL: la página sigue funcionando sin el 3D
		}
		renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
		renderer.toneMapping = THREE.ACESFilmicToneMapping;
		renderer.toneMappingExposure = 1.05;
		renderer.shadowMap.enabled = true;
		renderer.shadowMap.type = THREE.PCFSoftShadowMap;

		const FONDO = 0x1b1411;
		const escena = new THREE.Scene();
		escena.background = new THREE.Color(FONDO);
		escena.fog = new THREE.Fog(FONDO, 6, 13);
		escena.environment = entornoEstudio(renderer);

		const camara = new THREE.PerspectiveCamera(34, 1, 0.05, 40);
		const objetivo = new THREE.Vector3();

		// Luces: principal cálida con sombra, contraluz y relleno
		escena.add(new THREE.HemisphereLight(0xfff1e0, 0x2b1d16, 0.55));
		const principal = new THREE.DirectionalLight(0xfff0dc, 2.2);
		principal.position.set(2.2, 4.2, 3);
		principal.castShadow = true;
		principal.shadow.mapSize.set(1024, 1024);
		principal.shadow.camera.left = -1.4; principal.shadow.camera.right = 5;
		principal.shadow.camera.top = 2.6; principal.shadow.camera.bottom = -1.2;
		principal.shadow.camera.near = 1; principal.shadow.camera.far = 12;
		principal.shadow.bias = -0.0004;
		principal.target.position.set(1.6, 0.6, 0);
		escena.add(principal, principal.target);
		const contraluz = new THREE.DirectionalLight(0xd9e4ff, 1.1);
		contraluz.position.set(-2.5, 3, -3.5);
		escena.add(contraluz);

		// Piso y tarima
		const piso = new THREE.Mesh(new THREE.CircleGeometry(14, 64), new THREE.MeshStandardMaterial({ color: 0x241a15, roughness: 0.85 }));
		piso.rotation.x = -Math.PI / 2;
		piso.receiveShadow = true;
		escena.add(piso);

		const tarima = new THREE.Mesh(
			new THREE.CylinderGeometry(1.0, 1.05, 0.07, 96),
			new THREE.MeshStandardMaterial({ color: 0x3a2a22, roughness: 0.35, metalness: 0.1 })
		);
		tarima.position.y = 0.035;
		tarima.receiveShadow = true;
		escena.add(tarima);
		const aro = new THREE.Mesh(
			new THREE.TorusGeometry(1.0, 0.008, 12, 128),
			new THREE.MeshStandardMaterial({ color: 0xd9b26f, roughness: 0.25, metalness: 1 })
		);
		aro.rotation.x = Math.PI / 2;
		aro.position.y = 0.071;
		escena.add(aro);

		// ---- Vestido sobre maniquí ----
		const vestido = new THREE.Group();
		vestido.position.y = 0.07;
		escena.add(vestido);

		const geoVestido = geometriaVestido();
		const forro = new THREE.Mesh(geoVestido, new THREE.MeshStandardMaterial());
		forro.castShadow = true;
		forro.receiveShadow = true;
		vestido.add(forro);

		const capaVelo = new THREE.Mesh(geoVestido, new THREE.MeshStandardMaterial());
		capaVelo.scale.set(1.025, 1.004, 1.025);
		capaVelo.castShadow = true;
		capaVelo.visible = false;
		vestido.add(capaVelo);

		const maniqui = new THREE.Mesh(geometriaManiqui(), new THREE.MeshStandardMaterial({ color: 0xd9c8ae, roughness: 0.92 }));
		maniqui.castShadow = true;
		vestido.add(maniqui);
		const remate = new THREE.Mesh(
			new THREE.CylinderGeometry(0.03, 0.04, 0.06, 32),
			new THREE.MeshStandardMaterial({ color: 0x5b3b27, roughness: 0.4, metalness: 0.2 })
		);
		remate.position.y = 1.69;
		vestido.add(remate);

		// ---- Muestra de tela en un perchero ----
		const GIRO_PERCHERO = -0.38; // de tres cuartos, para que se vea el volumen de los pliegues
		const perchero = new THREE.Group();
		perchero.position.x = POS_PERCHERO;
		perchero.rotation.y = GIRO_PERCHERO;
		escena.add(perchero);

		const dorado = new THREE.MeshStandardMaterial({ color: 0xd9b26f, roughness: 0.28, metalness: 1 });
		const barra = new THREE.Mesh(new THREE.CylinderGeometry(0.016, 0.016, 1.46, 24), dorado);
		barra.rotation.z = Math.PI / 2;
		barra.position.y = 1.98;
		perchero.add(barra);
		[-0.72, 0.72].forEach(x => {
			const poste = new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.014, 1.98, 16), dorado);
			poste.position.set(x, 0.99, 0);
			poste.castShadow = true;
			perchero.add(poste);
			const pie = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.12, 0.02, 32), dorado);
			pie.position.set(x, 0.01, 0);
			perchero.add(pie);
			const bola = new THREE.Mesh(new THREE.SphereGeometry(0.03, 24, 16), dorado);
			bola.position.set(x, 1.98, 0);
			perchero.add(bola);
		});

		const ANCHO_MUESTRA = 1.24, ALTO_MUESTRA = 1.6;
		const geoMuestra = new THREE.PlaneGeometry(ANCHO_MUESTRA, ALTO_MUESTRA, 96, 64);
		geoMuestra.translate(0, -ALTO_MUESTRA / 2, 0);
		const basePlano = Float32Array.from(geoMuestra.attributes.position.array);
		const muestra = new THREE.Mesh(geoMuestra, new THREE.MeshStandardMaterial({ side: THREE.DoubleSide }));
		muestra.position.y = 1.96;
		muestra.castShadow = true;
		perchero.add(muestra);

		function moverMuestra(t){
			const pos = geoMuestra.attributes.position;
			for(let i = 0; i < pos.count; i++){
				const x = basePlano[i * 3], y = basePlano[i * 3 + 1];
				const caida = -y / ALTO_MUESTRA; // 0 arriba, 1 abajo
				const pliegue = Math.sin(x * 10 + 0.6) * 0.07 * (0.4 + caida) + Math.sin(x * 23 + 1.2) * 0.012 * caida;
				const brisa = Math.sin(t * 1.3 + x * 2.2 + y * 1.6) * 0.035 * caida * caida;
				pos.setXYZ(i, x * (1 - 0.05 * caida), y, pliegue + brisa);
			}
			pos.needsUpdate = true;
			geoMuestra.computeVertexNormals();
		}
		moverMuestra(0);

		// ---- Materiales según la tela ----
		let texturasActuales = [];
		let peticion = 0;

		function prepararTextura(base, repU, repV){
			const t = base.clone();
			t.wrapS = t.wrapT = THREE.MirroredRepeatWrapping; // sin costuras visibles
			t.repeat.set(repU, repV);
			t.colorSpace = THREE.SRGBColorSpace;
			t.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
			t.needsUpdate = true;
			texturasActuales.push(t);
			return t;
		}

		function material(perfilT, mapa, tinte, paraMuestra){
			const comunes = { map: mapa, side: THREE.DoubleSide };
			if(perfilT.tipo === 'satin'){
				return new THREE.MeshPhysicalMaterial(Object.assign(comunes, {
					roughness: 0.3, sheen: 0.35, sheenRoughness: 0.25, sheenColor: new THREE.Color(0xffffff),
					clearcoat: 0.4, clearcoatRoughness: 0.32,
				}));
			}
			if(perfilT.tipo === 'velo'){
				return new THREE.MeshPhysicalMaterial(Object.assign(comunes, {
					transparent: true, opacity: paraMuestra ? 0.82 : 0.6, depthWrite: false,
					roughness: 0.7, sheen: 1, sheenRoughness: 0.45, sheenColor: new THREE.Color(0xfff6ea),
					clearcoat: perfilT.brillo ? 0.8 : 0, clearcoatRoughness: 0.2,
				}));
			}
			if(perfilT.tipo === 'terciopelo'){
				return new THREE.MeshPhysicalMaterial(Object.assign(comunes, {
					roughness: 0.92, sheen: 1, sheenRoughness: 0.3, sheenColor: tinte.clone().lerp(new THREE.Color(1, 1, 1), 0.5),
				}));
			}
			return new THREE.MeshPhysicalMaterial(Object.assign(comunes, {
				roughness: 0.68, sheen: 0.45, sheenRoughness: 0.5, sheenColor: new THREE.Color(0xffffff),
				clearcoat: perfilT.brillo ? 0.6 : 0, clearcoatRoughness: 0.3,
			}));
		}

		function aplicarTela(base, nombre){
			[forro, capaVelo, muestra].forEach(m => m.material.dispose());
			texturasActuales.forEach(t => t.dispose());
			texturasActuales = [];

			const perfilT = perfilTela(nombre);
			const tinte = base && base.image ? colorPromedio(base.image) : new THREE.Color(0xefe6da);
			const mapaVestido = base ? prepararTextura(base, 9, 5) : null;
			const mapaMuestra = base ? prepararTextura(base, 1, 1) : null;

			if(perfilT.tipo === 'velo'){
				// Tul / gasa: forro satinado del color de la tela + capa translúcida encima
				forro.material = new THREE.MeshPhysicalMaterial({
					color: tinte.clone().multiplyScalar(0.92), roughness: 0.42, sheen: 0.6, sheenRoughness: 0.35,
					sheenColor: new THREE.Color(0xffffff), side: THREE.DoubleSide,
				});
				capaVelo.material = material(perfilT, mapaVestido, tinte, false);
				capaVelo.visible = true;
			}else{
				forro.material = material(perfilT, mapaVestido, tinte, false);
				capaVelo.visible = false;
			}
			muestra.material = material(perfilT, mapaMuestra, tinte, true);
		}

		function setFabric(url, semilla, nombre){
			const miPeticion = ++peticion;
			const resuelta = url ? resolverUrl(url) : null;
			const respaldo = () => {
				if(miPeticion !== peticion) return;
				aplicarTela(texturaRespaldo(semilla || 'tela'), nombre);
			};
			if(!resuelta){ respaldo(); return; }
			cargarTextura(resuelta).then(tex => {
				if(miPeticion === peticion) aplicarTela(tex, nombre);
			}, respaldo);
		}

		// ---- Cámara y transiciones ----
		let vista = 'vestido';
		const desdeCam = new THREE.Vector3(), haciaCam = new THREE.Vector3();
		const desdeObj = new THREE.Vector3(), haciaObj = new THREE.Vector3();
		let viaje = null; // { inicio, dura }

		function colocar(v){
			camara.position.fromArray(VISTAS[v].camara);
			objetivo.fromArray(VISTAS[v].objetivo);
		}
		colocar(vista);

		const reducirMovimiento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function setVista(v){
			if(!VISTAS[v] || v === vista) return;
			vista = v;
			if(reducirMovimiento){ colocar(v); return; }
			desdeCam.copy(camara.position); haciaCam.fromArray(VISTAS[v].camara);
			desdeObj.copy(objetivo); haciaObj.fromArray(VISTAS[v].objetivo);
			viaje = { inicio: performance.now(), dura: 1100 };
		}

		// ---- Girar arrastrando (con inercia); el vestido gira solo si nadie lo toca ----
		let arrastrando = false, ultimoX = 0, velocidad = 0, ultimoToque = -1e9;
		canvas.style.touchAction = 'pan-y';
		canvas.style.cursor = 'grab';
		canvas.addEventListener('pointerdown', e => {
			arrastrando = true; ultimoX = e.clientX; velocidad = 0;
			canvas.setPointerCapture(e.pointerId);
			canvas.style.cursor = 'grabbing';
		});
		canvas.addEventListener('pointermove', e => {
			if(!arrastrando) return;
			const dx = e.clientX - ultimoX;
			ultimoX = e.clientX;
			velocidad = dx * 0.008;
			girar(velocidad);
			ultimoToque = performance.now();
		});
		const soltar = () => { arrastrando = false; canvas.style.cursor = 'grab'; ultimoToque = performance.now(); };
		canvas.addEventListener('pointerup', soltar);
		canvas.addEventListener('pointercancel', soltar);

		function girar(delta){
			if(vista === 'vestido') vestido.rotation.y += delta;
			else perchero.rotation.y = Math.max(GIRO_PERCHERO - 0.8, Math.min(GIRO_PERCHERO + 0.8, perchero.rotation.y + delta));
		}

		// ---- Tamaño y visibilidad ----
		function resize(){
			const w = Math.max(1, Math.floor(canvas.clientWidth));
			const h = Math.max(1, Math.floor(canvas.clientHeight));
			renderer.setSize(w, h, false);
			camara.aspect = w / h;
			// en pantallas angostas alejamos la cámara para que entre el vestido completo
			camara.fov = camara.aspect < 0.9 ? 42 : 34;
			camara.updateProjectionMatrix();
		}
		const ro = window.ResizeObserver ? new ResizeObserver(resize) : null;
		if(ro) ro.observe(canvas); else window.addEventListener('resize', resize);

		let visible = true;
		const io = window.IntersectionObserver ? new IntersectionObserver(es => { visible = es[0].isIntersecting; }) : null;
		if(io) io.observe(canvas);

		// ---- Bucle ----
		let animId = 0, previo = performance.now();
		const t0 = previo;
		function cuadro(ahora){
			animId = requestAnimationFrame(cuadro);
			if(!visible || document.hidden || canvas.clientWidth === 0) { previo = ahora; return; }
			const dt = Math.min(0.05, (ahora - previo) / 1000);
			previo = ahora;

			if(viaje){
				const k = Math.min(1, (ahora - viaje.inicio) / viaje.dura);
				const s = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
				camara.position.lerpVectors(desdeCam, haciaCam, s);
				// arco: la cámara se eleva un poco a mitad del viaje
				camara.position.y += Math.sin(Math.PI * s) * 0.25;
				objetivo.lerpVectors(desdeObj, haciaObj, s);
				if(k >= 1) viaje = null;
			}

			if(!arrastrando){
				if(Math.abs(velocidad) > 0.0001){ girar(velocidad); velocidad *= 0.92; }
				if(vista === 'vestido' && ahora - ultimoToque > 2500 && !reducirMovimiento){
					vestido.rotation.y += 0.25 * dt;
				}
				if(vista === 'tela' && ahora - ultimoToque > 2500){
					perchero.rotation.y += (GIRO_PERCHERO - perchero.rotation.y) * 0.03; // vuelve a su ángulo
				}
			}
			if(vista === 'tela' || viaje) moverMuestra(reducirMovimiento ? 0 : (ahora - t0) / 1000);

			camara.lookAt(objetivo);
			renderer.render(escena, camara);
		}
		resize();
		animId = requestAnimationFrame(cuadro);

		return {
			setFabric,
			setVista,
			getVista: () => vista,
			resize,
			stop(){
				cancelAnimationFrame(animId);
				if(ro) ro.disconnect();
				if(io) io.disconnect();
			},
		};
	}

	window.crearEstudio3D = crearEstudio3D;
})();
