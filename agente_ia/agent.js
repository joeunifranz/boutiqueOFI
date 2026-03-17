(function() {
    // Evitar inyectar dos veces el widget
    if (document.getElementById('rag-agent-container')) return;

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

    // ============================================
    // 1. ESTILOS CSS (DISEÑO LIMPIO Y GOURMET)
    // ============================================
    const style = document.createElement('style');
    style.innerHTML = `
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');

        /* Contenedor Principal */
        #rag-agent-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; font-family: 'Inter', sans-serif; display: flex; flex-direction: column; align-items: flex-end; }
        
        /* Botón Flotante */
        #rag-agent-button {
            width: 60px;
            height: 60px;
            border-radius: 999px;
            background: linear-gradient(145deg, #8b5cf6, #7c3aed);
            color: white;
            border: 1px solid rgba(255,255,255,0.55);
            cursor: pointer;
            box-shadow:
                0 14px 30px rgba(139, 92, 246, 0.28),
                0 6px 12px rgba(0,0,0,0.12);
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, filter 0.2s;
        }
        #rag-agent-button:hover { transform: translateY(-1px) scale(1.04); filter: brightness(1.03); }
        #rag-agent-button:active { transform: translateY(0px) scale(0.98); }

        /* Etiqueta arriba del botón */
        #rag-agent-label {
            margin-bottom: 10px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(139, 92, 246, 0.10);
            color: #7c3aed;
            border: 1px solid rgba(139, 92, 246, 0.28);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            box-shadow:
                0 10px 22px rgba(139, 92, 246, 0.14),
                0 6px 12px rgba(0,0,0,0.10);
            user-select: none;
            backdrop-filter: blur(6px);
        }

        /* Ventana de Chat */
        #rag-agent-chat-window { display: none; width: 380px; height: 600px; background: #ffffff; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.15); flex-direction: column; overflow: hidden; margin-bottom: 16px; border: 1px solid #e5e7eb; animation: slideIn 0.3s ease-out; }
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
                <span id="rag-close" class="rag-icon-btn">✕</span>
            </div>
            <div id="rag-messages"><div class="message bot-msg">Bienvenida, soy tu asesora de Boutique Dorita. Cuéntame tu estilo, talla o el tipo de vestido que imaginas y te orientaré.</div></div>
            <div class="rag-input-area"><input type="text" id="rag-input" placeholder="Ej: busco sirena para 1.65m, presupuesto 2500 Bs"><button id="rag-send">Enviar</button></div>
        </div>
        <div id="rag-agent-label">HABLA CON TU ASESORA</div>
        <button id="rag-agent-button">👗</button>
    `;
    document.body.appendChild(container);

    const msgsDiv = document.getElementById('rag-messages');
    const input = document.getElementById('rag-input');
    
    // Toggle Ventana
    document.getElementById('rag-agent-button').onclick = () => {
        const win = document.getElementById('rag-agent-chat-window');
        win.style.display = win.style.display === 'flex' ? 'none' : 'flex';
        if(win.style.display === 'flex') input.focus();
    };
    document.getElementById('rag-close').onclick = () => document.getElementById('rag-agent-chat-window').style.display = 'none';

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
    async function handleSend() {
        const text = input.value.trim();
        if (!text) return;

        // Mostrar usuario
        const userDiv = document.createElement('div');
        userDiv.className = 'message user-msg';
        userDiv.innerText = text;
        msgsDiv.appendChild(userDiv);
        input.value = ''; input.disabled = true;
        msgsDiv.scrollTop = msgsDiv.scrollHeight;

        // Mostrar loader
        const loader = document.createElement('div');
        loader.className = 'message bot-msg typing-indicator';
        loader.innerHTML = '<div class="dot"></div><div class="dot"></div>';
        msgsDiv.appendChild(loader);
        
        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text, page: (window.location ? (window.location.pathname + window.location.search) : '') })
            });
            const data = await res.json();
            
            loader.remove(); // Quitar loader

            // Crear respuesta del bot parseada
            const botDiv = document.createElement('div');
            botDiv.className = 'message bot-msg';
            botDiv.innerHTML = parseMarkdown(data.response); // Usamos el nuevo parser
            msgsDiv.appendChild(botDiv);

        } catch (e) {
            loader.remove();
            const err = document.createElement('div');
            err.className = 'message bot-msg';
            err.style.color = '#ef4444';
            err.innerText = "Error: no pude conectar con Boutique Dorita.";
            msgsDiv.appendChild(err);
        } finally {
            input.disabled = false;
            input.focus();
            msgsDiv.scrollTop = msgsDiv.scrollHeight;
        }
    }

    document.getElementById('rag-send').onclick = handleSend;
    input.onkeypress = (e) => { if (e.key === 'Enter') handleSend(); };
})();