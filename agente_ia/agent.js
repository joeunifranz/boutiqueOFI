(function() {
    // Evitar inyectar dos veces el widget
    if (document.getElementById('rag-agent-container')) return;

    const STORAGE_VERSION = 1;
    const MAX_STORED_MESSAGES = 120;
    const MAX_SENT_HISTORY = 12;

    function getClientKey(){
        try{
            const id = Number(window.BOUTIQUE_CLIENTE_ID || 0);
            if(Number.isFinite(id) && id > 0){
                return 'cliente:' + String(id);
            }
        }catch(e){}
        return 'guest';
    }

    function getStorageKey(){
        return 'boutique_rag_chat_v' + STORAGE_VERSION + ':' + getClientKey();
    }

    function safeJsonParse(s){
        try{ return JSON.parse(s); }catch(e){ return null; }
    }

    function nowIso(){
        try{ return new Date().toISOString(); }catch(e){ return ''; }
    }

    function defaultState(){
        return {
            version: STORAGE_VERSION,
            open: false,
            draft: '',
            messages: [],
            updatedAt: nowIso(),
        };
    }

    function loadState(){
        try{
            const raw = localStorage.getItem(getStorageKey());
            if(!raw) return defaultState();
            const data = safeJsonParse(raw);
            if(!data || typeof data !== 'object') return defaultState();
            if(data.version !== STORAGE_VERSION) return defaultState();
            if(!Array.isArray(data.messages)) data.messages = [];
            return Object.assign(defaultState(), data);
        }catch(e){
            return defaultState();
        }
    }

    let saveTimer = null;
    function saveState(state){
        state.updatedAt = nowIso();
        if(saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            try{
                localStorage.setItem(getStorageKey(), JSON.stringify(state));
            }catch(e){}
        }, 120);
    }

    function getAppBase(){
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

    // Consumimos el agente por el proxy PHP para evitar CORS y mantener misma-origin
    const API_URL = joinUrl(getAppBase(), 'app/ajax/agenteIaAjax.php');
    const RECO_URL = joinUrl(getAppBase(), 'app/ajax/recomendadorAjax.php');

    // ============================================
    // 1. ESTILOS CSS (DISEÑO LIMPIO Y GOURMET)
    // ============================================
    const style = document.createElement('style');
    style.innerHTML = `
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');

        /* Contenedor Principal */
        #rag-agent-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; font-family: 'Inter', sans-serif; display: flex; flex-direction: column; align-items: flex-end; }
        
        /* Botón Flotante (dorado metálico + shimmer) */
        #rag-agent-button {
            width: 60px;
            height: 60px;
            border-radius: 999px;
            margin: 0;
            position: relative;
            overflow: hidden;

            background-color: #0b0f1a;
            background-image:
                linear-gradient(180deg,
                    rgba(255,255,255,0.22) 0%,
                    rgba(255,255,255,0.06) 35%,
                    rgba(0,0,0,0.30) 100%
                ),
                radial-gradient(120% 140% at 24% 18%, rgba(255, 224, 138, 0.18) 0%, rgba(255, 224, 138, 0.0) 58%),
                radial-gradient(120% 160% at 82% 130%, rgba(0,0,0,0.42) 0%, rgba(0,0,0,0.0) 56%);
            background-blend-mode: screen, normal, normal;

            border: 1px solid rgba(255, 221, 150, 0.58);
            cursor: pointer;
            box-shadow:
                0 16px 45px rgba(0,0,0,0.42),
                0 0 26px rgba(255, 205, 92, 0.26),
                inset 0 1px 0 rgba(255,255,255,0.18),
                inset 0 -12px 20px rgba(0,0,0,0.55);

            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
            transition: transform 0.2s, filter 0.2s, box-shadow 0.2s;
        }

        #rag-agent-button::after{
            content: "";
            position: absolute;
            top: -20%;
            left: -60%;
            width: 45%;
            height: 140%;
            transform: skewX(-20deg);
            background: linear-gradient(90deg,
                rgba(255,255,255,0.0) 0%,
                rgba(255,255,255,0.70) 45%,
                rgba(255,255,255,0.0) 80%
            );
            opacity: 0.70;
            mix-blend-mode: soft-light;
            pointer-events: none;
            animation: ragAgentGoldSweep 2.2s linear infinite;
        }

        @keyframes ragAgentGoldSweep{
            0%{ left: -60%; }
            100%{ left: 120%; }
        }

        .rag-agent-icon{
            width: 30px;
            height: 30px;
            display: block;
            filter:
                drop-shadow(0 1px 0 rgba(0,0,0,0.35))
                drop-shadow(0 0 10px rgba(255,205,92,0.18));
        }

        #rag-agent-button:hover { transform: translateY(-1px) scale(1.05); filter: brightness(1.04); }
        #rag-agent-button:active { transform: translateY(0px) scale(0.98); filter: brightness(0.98); }

        @media (prefers-reduced-motion: reduce){
            #rag-agent-button::after{ animation: none; }
        }

        /* Etiqueta arriba del botón */
        #rag-agent-label {
            margin-bottom: 10px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255, 205, 92, 0.12);
            color: rgba(255, 224, 138, 0.95);
            border: 1px solid rgba(255, 221, 150, 0.35);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            box-shadow:
                0 10px 22px rgba(0,0,0,0.18),
                0 0 18px rgba(255, 205, 92, 0.12);
            user-select: none;
            backdrop-filter: blur(6px);
        }

        /* Ventana de Chat */
        #rag-agent-chat-window { display: none; width: min(380px, calc(100vw - 40px)); height: min(600px, calc(100vh - 140px)); background: #ffffff; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.15); flex-direction: column; overflow: hidden; margin-bottom: 16px; border: 1px solid #e5e7eb; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Header */
        .rag-header { background: #ffffff; color: #1f2937; padding: 16px 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3f4f6; }
        .rag-header span { font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .rag-icon-btn { cursor: pointer; color: #9ca3af; font-size: 18px; padding: 4px; transition: color 0.2s; }
        .rag-icon-btn:hover { color: #4b5563; }

        /* Área de Mensajes */
        #rag-messages { flex: 1; padding: 20px; overflow-y: auto; background: #f9fafb; display: flex; flex-direction: column; gap: 12px; scroll-behavior: smooth; }
        
        /* Burbujas de Mensaje */
        .message { padding: 12px 16px; border-radius: 12px; max-width: 85%; word-wrap: break-word; font-size: 14px; line-height: 1.6; position: relative; }
        
        .user-msg { background: #8b5cf6; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        
        .bot-msg { background: white; color: #374151; align-self: flex-start; border-bottom-left-radius: 2px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }

        /* --- FORMATO RECETA (ESTILO LIMPIO) --- */
        .bot-msg h3 { margin: 8px 0 12px 0; color: #111827; font-size: 18px; font-weight: 700; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; }
        .bot-msg strong { color: #8b5cf6; font-weight: 600; }

        /* Links como botón (Click aquí) */
        .bot-msg a.rag-link-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(139, 92, 246, 0.12);
            color: #7c3aed;
            border: 1px solid rgba(139, 92, 246, 0.28);
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(139, 92, 246, 0.10), 0 6px 12px rgba(0,0,0,0.06);
            user-select: none;
        }
        .bot-msg a.rag-link-btn:hover{ filter: brightness(0.98); transform: translateY(-1px); }
        .bot-msg a.rag-link-btn:active{ transform: translateY(0px); }
        .bot-msg a.rag-link-btn:focus{ outline: 3px solid rgba(139, 92, 246, 0.22); outline-offset: 2px; }

        .bot-msg a.rag-link{
            color: #2563eb;
            text-decoration: underline;
        }
        
        /* Listas de Ingredientes (Bullets) */
        .bot-msg ul { list-style-type: none; padding: 0; margin: 8px 0 16px 0; }
        .bot-msg ul li { position: relative; padding-left: 20px; margin-bottom: 6px; color: #4b5563; }
        .bot-msg ul li::before { content: "•"; color: #2563eb; font-weight: bold; position: absolute; left: 0; }

        /* Listas de Pasos (Números) */
        .bot-msg ol { padding-left: 20px; margin: 8px 0 16px 0; color: #4b5563; }
        .bot-msg ol li { margin-bottom: 8px; padding-left: 5px; }
        
        /* Input Area */
        .rag-input-area { padding: 16px; background: white; border-top: 1px solid #f3f4f6; display: flex; gap: 10px; }
        #rag-input { flex: 1; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 24px; outline: none; background: #f9fafb; font-size: 14px; transition: all 0.2s; }
        #rag-input:focus { border-color: #8b5cf6; background: white; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.12); }
        #rag-send { padding: 0 20px; background: #8b5cf6; color: white; border: none; border-radius: 24px; cursor: pointer; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        #rag-send:hover { background: #7c3aed; }
        #rag-send[disabled]{ opacity: 0.6; cursor: not-allowed; }

        /* Responsive tweaks */
        @media (max-width: 480px){
            #rag-agent-container{ bottom: 12px; right: 12px; }
            #rag-agent-button{ width: 56px; height: 56px; }
            .rag-agent-icon{ width: 28px; height: 28px; }
            #rag-agent-label{ font-size: 11px; }
            #rag-messages{ padding: 14px; }
            .rag-header{ padding: 14px 16px; }
            .rag-input-area{ padding: 12px; }
        }

        /* Loader */
        .typing-indicator { display: flex; align-items: center; gap: 4px; padding: 16px; width: fit-content; }
        .dot { width: 6px; height: 6px; background-color: #9ca3af; border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
        .dot:nth-child(1) { animation-delay: -0.32s; } .dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    `;
    document.head.appendChild(style);

    // ============================================
    // 2. CREACIÓN DEL DOM
    // ============================================
    const container = document.createElement('div');
    container.id = 'rag-agent-container';
    container.innerHTML = `
        <div id="rag-agent-chat-window">
            <div class="rag-header">
                <span>👗 Boutique Dorita</span>
                <span style="display:flex; gap:10px; align-items:center;">
                    <span id="rag-reset" class="rag-icon-btn" title="Nueva conversación">↺</span>
                    <span id="rag-close" class="rag-icon-btn" title="Cerrar">✕</span>
                </span>
            </div>
            <div id="rag-messages"><div class="message bot-msg">Bienvenida, soy tu asesora de Boutique Dorita. Cuéntame tu estilo, talla o el tipo de vestido que imaginas y te orientaré.</div></div>
            <div id="rag-reco-panel" class="p-3 has-background-danger-light" style="border-top: 1px solid rgba(0,0,0,0.06); display:none;">
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
            <div class="rag-input-area"><input type="text" id="rag-input" placeholder="Ej: busco sirena para 1.65m, presupuesto 2500 Bs"><button id="rag-send">Enviar</button></div>
        </div>
        <div id="rag-agent-label">HABLA CON TU ASESORA</div>
        <button id="rag-agent-button" type="button" aria-label="Abrir chat con tu asesora">
            <svg class="rag-agent-icon" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <defs>
                    <linearGradient id="ragFemaleGold" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#fff3c4" />
                        <stop offset="0.28" stop-color="#ffe08a" />
                        <stop offset="0.55" stop-color="#f6c84b" />
                        <stop offset="0.78" stop-color="#b57f00" />
                        <stop offset="1" stop-color="#ffdf85" />
                    </linearGradient>
                </defs>

                <!-- Silueta femenina -->
                <circle cx="32" cy="18" r="9" fill="url(#ragFemaleGold)" />
                <path d="M22 56l7.2-18.2c1.8-4.5 6.1-7.6 10.8-7.6s9 3.1 10.8 7.6L58 56H22z" fill="url(#ragFemaleGold)" />
                <path d="M25 56l7-16 7 16H25z" fill="url(#ragFemaleGold)" opacity="0.95" />
            </svg>
        </button>
    `;
    document.body.appendChild(container);

    const msgsDiv = document.getElementById('rag-messages');
    const input = document.getElementById('rag-input');
    const sendBtn = document.getElementById('rag-send');
    const win = document.getElementById('rag-agent-chat-window');
    const resetBtn = document.getElementById('rag-reset');

    const recoTipoEl = document.getElementById('rag-reco-tipo');
    const recoFotoEl = document.getElementById('rag-reco-foto');
    const recoFotoNameEl = document.getElementById('rag-reco-foto-name');
    const recoBtn = document.getElementById('rag-reco-btn');
    const recoPanelEl = document.getElementById('rag-reco-panel');

    let recoPanelVisible = false;
    function setRecoPanelVisible(v){
        recoPanelVisible = !!v;
        try{
            if(recoPanelEl) recoPanelEl.style.display = recoPanelVisible ? 'block' : 'none';
        }catch(e){}
		try{ msgsDiv.scrollTop = msgsDiv.scrollHeight; }catch(e){}
    }

    function normalizeText(s){
        let t = '';
        try{ t = String(s || '').toLowerCase(); }catch(e){ t = ''; }
        t = t
            .replace(/á/g,'a').replace(/é/g,'e').replace(/í/g,'i').replace(/ó/g,'o').replace(/ú/g,'u')
            .replace(/ñ/g,'n');
        return t;
    }

    function isRecoTriggerMessage(text){
        const low = normalizeText(text);
        if(!low) return false;
        return (
            low.includes('suger') ||
            low.includes('recomend') ||
            low.includes('vestido') ||
            low.includes('que me queda') ||
            low.includes('tipo de cuerpo') ||
            low.includes('foto') ||
            low.includes('outfit')
        );
    }

    const WELCOME_TEXT = 'Bienvenida, soy tu asesora de Boutique Dorita. Cuéntame tu estilo, talla o el tipo de vestido que imaginas y te orientaré.';
    let state = loadState();

    function renderMessage(msg){
        const div = document.createElement('div');
        div.className = 'message ' + (msg.role === 'user' ? 'user-msg' : 'bot-msg');
        if(msg.role === 'user'){
            div.innerText = msg.content || '';
        }else{
            div.innerHTML = parseMarkdown(msg.content || '');
        }
        msgsDiv.appendChild(div);
    }

    function renderAll(){
        msgsDiv.innerHTML = '';
        if(!state.messages || !state.messages.length){
            state.messages = [{ role: 'assistant', content: WELCOME_TEXT, ts: nowIso() }];
            saveState(state);
        }
        state.messages.forEach(renderMessage);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
    }

    function openWindow(){
        win.style.display = 'flex';
        state.open = true;
        saveState(state);
        setTimeout(() => {
            try{ input.focus(); }catch(e){}
        }, 0);
    }
    function closeWindow(){
        win.style.display = 'none';
        state.open = false;
        saveState(state);
    }

    // Restore UI state
    renderAll();
    if(typeof state.draft === 'string' && state.draft){
        input.value = state.draft;
    }
    if(state.open){
        openWindow();
    }
    
    // Toggle Ventana
    document.getElementById('rag-agent-button').onclick = () => {
        if(win.style.display === 'flex'){
            closeWindow();
        }else{
            openWindow();
        }
    };
    document.getElementById('rag-close').onclick = () => closeWindow();
    resetBtn.onclick = () => {
        const ok = window.confirm('¿Quieres iniciar una nueva conversación? Se borrará el historial de este chat.');
        if(!ok) return;
        state.messages = [{ role: 'assistant', content: WELCOME_TEXT, ts: nowIso() }];
        state.draft = '';
        saveState(state);
        renderAll();
        input.value = '';
        setRecoPanelVisible(false);
        try{ input.focus(); }catch(e){}
    };

    // ============================================
    // 3. PARSER DE MARKDOWN MEJORADO (LÓGICA CLAVE)
    // ============================================
    function escapeHtml(str){
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function parseMarkdown(text) {
        // 1. Sanitizar HTML básico para evitar inyección
        let html = escapeHtml(text);

        // 2. Procesar Headers (### Titulo)
        html = html.replace(/### (.*$)/gim, '<h3>$1</h3>');

        // 3. Procesar Negritas (**texto**)
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // 3.1 Enlaces Markdown: [texto](https://...)
        const mdLinks = [];
        html = html.replace(/\[([^\]]+?)\]\((https?:\/\/[^\s)]+)\)/g, function(_, label, url){
            const idx = mdLinks.length;
            mdLinks.push('<a class="rag-link-btn" href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
            return '@@MDLINK_' + idx + '@@';
        });

        // 3.2 URLs sueltas
        html = html.replace(/(https?:\/\/[^\s<]+)/g, '<a class="rag-link" href="$1" target="_blank" rel="noopener noreferrer">$1</a>');

        // 3.3 Restaurar enlaces Markdown
        for(let i = 0; i < mdLinks.length; i++){
            html = html.replace('@@MDLINK_' + i + '@@', mdLinks[i]);
        }

        // 4. Lógica de Listas (State Machine simplificada)
        // Convertimos el texto en lineas para procesar grupos
        let lines = html.split('\n');
        let output = [];
        let inList = false;
        let listType = null; // 'ul' o 'ol'

        lines.forEach(line => {
            let trim = line.trim();

            // Detectar Listas Desordenadas (- item)
            if (trim.startsWith('- ')) {
                if (!inList || listType !== 'ul') {
                    if (inList) output.push(`</${listType}>`); // Cerrar lista anterior si existía
                    output.push('<ul>');
                    inList = true;
                    listType = 'ul';
                }
                output.push(`<li>${trim.substring(2)}</li>`);
            }
            // Detectar Listas Numeradas (1. item)
            else if (/^\d+\.\s/.test(trim)) {
                if (!inList || listType !== 'ol') {
                    if (inList) output.push(`</${listType}>`);
                    output.push('<ol>');
                    inList = true;
                    listType = 'ol';
                }
                // Quitamos el número "1." del texto, el HTML <ol> pone los números
                output.push(`<li>${trim.replace(/^\d+\.\s/, '')}</li>`);
            }
            // Texto normal
            else {
                if (inList) {
                    output.push(`</${listType}>`);
                    inList = false;
                    listType = null;
                }
                if (trim.length > 0) output.push(trim + '<br>'); 
            }
        });

        if (inList) output.push(`</${listType}>`);

        return output.join('');
    }

    // ============================================
    // 4. ENVÍO DE MENSAJES
    // ============================================

    function setBusy(v){
        try{ input.disabled = !!v; }catch(e){}
        try{ sendBtn.disabled = !!v; }catch(e){}
        try{ if(recoBtn) recoBtn.disabled = !!v; }catch(e){}
        try{ if(recoTipoEl) recoTipoEl.disabled = !!v; }catch(e){}
        try{ if(recoFotoEl) recoFotoEl.disabled = !!v; }catch(e){}
    }

    function pushMessage(role, content){
        state.messages = Array.isArray(state.messages) ? state.messages : [];
        state.messages.push({ role: role, content: content, ts: nowIso() });
        if(state.messages.length > MAX_STORED_MESSAGES){
            state.messages = state.messages.slice(-MAX_STORED_MESSAGES);
        }
        saveState(state);
    }

    function appendUserBubble(text){
        const userDiv = document.createElement('div');
        userDiv.className = 'message user-msg';
        userDiv.innerText = text;
        msgsDiv.appendChild(userDiv);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
    }

    function appendBotBubble(markdownText){
        const botDiv = document.createElement('div');
        botDiv.className = 'message bot-msg';
        botDiv.innerHTML = parseMarkdown(markdownText);
        msgsDiv.appendChild(botDiv);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
    }

    function appendLoader(){
        const loader = document.createElement('div');
        loader.className = 'message bot-msg typing-indicator';
        loader.innerHTML = '<div class="dot"></div><div class="dot"></div>';
        msgsDiv.appendChild(loader);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;
        return loader;
    }

    async function handleReco(){
        // asegurar visible
        if(!recoPanelVisible) setRecoPanelVisible(true);

        if(!recoFotoEl || !recoFotoEl.files || !recoFotoEl.files.length){
            appendBotBubble('Para darte **SUGERENCIAS DE VESTIDO**, sube una **foto** (JPG/PNG) en el panel rosado y presiona **Sugerir**.');
            return;
        }

        const tipo = (recoTipoEl && recoTipoEl.value) ? String(recoTipoEl.value) : '';
        const userText = 'Quiero sugerencias de vestido' + (tipo ? (' (tipo de cuerpo: ' + tipo + ')') : '') + '.';
        pushMessage('user', userText);
        appendUserBubble(userText);

        setBusy(true);
        const loader = appendLoader();

        try{
            const fd = new FormData();
            fd.append('modulo_reco', 'sugerir');
            fd.append('tipo_cuerpo', tipo);
            fd.append('categoria_id', '0');
            fd.append('talla', '');
            fd.append('max_price', '');
            fd.append('foto', recoFotoEl.files[0]);

            const res = await fetch(RECO_URL, { method: 'POST', body: fd });
            let data = null;
            try{ data = await res.json(); }catch(e){ data = null; }

            if(loader) loader.remove();

            if(!res.ok || !data || data.ok !== true){
                const msg = (data && typeof data.message === 'string') ? data.message : 'No pude generar sugerencias ahora mismo.';
                appendBotBubble(msg);
                pushMessage('assistant', msg);
                return;
            }

            const items = Array.isArray(data.items) ? data.items : [];
            let out = '### SUGERENCIAS DE VESTIDO\n\n';
            out += (data.message ? String(data.message) : 'Aquí tienes sugerencias:') + '\n\n';

            if(!items.length){
                out += 'No encontré resultados con el catálogo actual.';
                appendBotBubble(out);
                pushMessage('assistant', out);
                return;
            }

            items.forEach(it => {
                const nombre = (it && it.nombre != null) ? String(it.nombre) : 'Vestido';
                const precioNum = (it && it.precio != null && !isNaN(Number(it.precio))) ? Number(it.precio) : null;
                const url = (it && it.detalle_url != null) ? String(it.detalle_url) : '';

                let line = '- **' + nombre + '**';
                if(precioNum !== null){ line += ' — Bs ' + precioNum.toFixed(2); }
                if(url){ line += ' [Abrir detalle](' + url + ')'; }
                out += line + '\n';
            });

            appendBotBubble(out);
            pushMessage('assistant', out);

            // limpiar foto seleccionada para siguiente uso
            recoFotoEl.value = '';
            if(recoFotoNameEl) recoFotoNameEl.textContent = 'JPG/PNG';
        }catch(e){
            if(loader) loader.remove();
            const msg = 'No pude generar sugerencias ahora mismo.';
            appendBotBubble(msg);
            pushMessage('assistant', msg);
        }finally{
            setBusy(false);
            try{ input.focus(); }catch(e){}
        }
    }

    async function handleSend() {
        const text = input.value.trim();
        if (!text) return;

        // Mostrar panel solo cuando el cliente lo pida
        if(!recoPanelVisible && isRecoTriggerMessage(text)){
            setRecoPanelVisible(true);
        }

		// Persist draft cleared
		state.draft = '';

		// Guardar mensaje usuario en estado
		state.messages = Array.isArray(state.messages) ? state.messages : [];
		state.messages.push({ role: 'user', content: text, ts: nowIso() });
		if(state.messages.length > MAX_STORED_MESSAGES){
			state.messages = state.messages.slice(-MAX_STORED_MESSAGES);
		}
		saveState(state);

        // Mostrar usuario
        const userDiv = document.createElement('div');
        userDiv.className = 'message user-msg';
        userDiv.innerText = text;
        msgsDiv.appendChild(userDiv);
        input.value = '';
        setBusy(true);
        msgsDiv.scrollTop = msgsDiv.scrollHeight;

        // Mostrar loader
        const loader = document.createElement('div');
        loader.className = 'message bot-msg typing-indicator';
        loader.innerHTML = '<div class="dot"></div><div class="dot"></div>';
        msgsDiv.appendChild(loader);
        
        try {
            const historyToSend = (state.messages || []).filter(m => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
                .slice(-MAX_SENT_HISTORY)
                .map(m => ({ role: m.role, content: String(m.content).slice(0, 1200) }));

            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: text,
                    page: (window.location ? (window.location.pathname + window.location.search) : ''),
                    chat_history: historyToSend
                })
            });
            const data = await res.json();
            
            loader.remove(); // Quitar loader

            // Crear respuesta del bot parseada
            const botDiv = document.createElement('div');
            botDiv.className = 'message bot-msg';
            const botText = (data && typeof data.response === 'string') ? data.response : (data && data.error ? ('Error: ' + data.error) : 'No recibí respuesta.');
            botDiv.innerHTML = parseMarkdown(botText); // Usamos el nuevo parser
            msgsDiv.appendChild(botDiv);

            // Guardar respuesta del bot
            state.messages = Array.isArray(state.messages) ? state.messages : [];
            state.messages.push({ role: 'assistant', content: botText, ts: nowIso() });
            if(state.messages.length > MAX_STORED_MESSAGES){
                state.messages = state.messages.slice(-MAX_STORED_MESSAGES);
            }
            saveState(state);

        } catch (e) {
            loader.remove();
            const err = document.createElement('div');
            err.className = 'message bot-msg';
            err.style.color = '#ef4444';
            err.innerText = "Error: no pude conectar con Boutique Dorita.";
            msgsDiv.appendChild(err);
            state.messages = Array.isArray(state.messages) ? state.messages : [];
            state.messages.push({ role: 'assistant', content: 'Error: no pude conectar con Boutique Dorita.', ts: nowIso() });
            if(state.messages.length > MAX_STORED_MESSAGES){
                state.messages = state.messages.slice(-MAX_STORED_MESSAGES);
            }
            saveState(state);
        } finally {
            setBusy(false);
            input.focus();
            msgsDiv.scrollTop = msgsDiv.scrollHeight;
        }
    }

    sendBtn.onclick = handleSend;

    if(recoFotoEl && recoFotoNameEl){
        recoFotoEl.addEventListener('change', function(){
            let name = '';
            try{ name = (recoFotoEl.files && recoFotoEl.files[0]) ? String(recoFotoEl.files[0].name || '') : ''; }catch(e){ name = ''; }
            recoFotoNameEl.textContent = name ? name : 'JPG/PNG';
        });
    }
    if(recoBtn){
        recoBtn.onclick = handleReco;
    }

    input.oninput = () => {
        state.draft = input.value || '';
        saveState(state);
    };
    input.onkeypress = (e) => { if (e.key === 'Enter') handleSend(); };
})();