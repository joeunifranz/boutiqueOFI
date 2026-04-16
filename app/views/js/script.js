document.addEventListener("DOMContentLoaded", () => {
    const chatbotHTML = `
       <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

            body {
                font-family: 'Poppins', sans-serif;
                margin: 0;
                padding: 0;
            }
            
            #chatbot {
                position: fixed;
                bottom: 80px;
                right: 20px;
                width: 300px;
                max-height: 400px;
                background: #f9f9f9;
                border: 1px solid #ccc;
                border-radius: 10px;
                display: none;
                flex-direction: column;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            }
            
            #chatbot.visible {
                display: flex;
            }
            
            #chat-header {
                background: #1296db;
                color: white;
                padding: 10px;
                text-align: center;
                font-weight: 600;
                border-radius: 10px 10px 0 0;
                font-size: 18px;
            }
            
            #chat-window {
                flex: 1;
                padding: 10px;
                overflow-y: auto;
                font-size: 14px;
                color: #333;
            }
            
            #messages .message {
                margin: 5px 0;
                padding: 10px;
                border-radius: 10px;
                max-width: 80%;
                word-wrap: break-word;
            }
            
            #messages .user {
                align-self: flex-start;
                background: #e0e0e0;
                border-radius: 10px 10px 10px 0;
                margin-left: 5px;
            }
            
            #messages .bot {
                align-self: flex-end;
                background: #1296db;
                color: white;
                text-align: left;
                padding: 10px;
                border-radius: 10px 10px 0 10px;
                max-width: 80%;
                margin-left: auto;
                margin-right: 0;
            }

            .suggestions {
                display: flex;
                justify-content: space-around;
                margin-top: 5px;
            }

            .suggestions button {
                background: #1296db;
                color: white;
                border: none;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 12px;
                cursor: pointer;
                transition: background 0.3s;
            }

            .suggestions button:hover {
                background: #0d74a8;
            }
            
            #input-area {
                display: flex;
                padding: 10px;
                border-top: 1px solid #ccc;
                background: #f9f9f9;
            }
            
            #user-input {
                flex: 1;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 5px;
                font-size: 14px;
                font-family: 'Poppins', sans-serif;
            }
            
            #send-btn {
                background: #1296db;
                color: white;
                border: none;
                padding: 8px 15px;
                margin-left: 5px;
                border-radius: 5px;
                font-size: 14px;
                font-family: 'Poppins', sans-serif;
                cursor: pointer;
                transition: background 0.3s;
            }
            
            #send-btn:hover {
                background: #0d74a8;
            }
            
            #bot-icon {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 74px;
                height: 74px;
                border-radius: 999px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                user-select: none;
                -webkit-tap-highlight-color: transparent;

                background:
                    linear-gradient(180deg,
                        #fff3c4 0%,
                        #ffe08a 16%,
                        #f6c84b 34%,
                        #d7a21a 55%,
                        #b57f00 78%,
                        #ffdf85 100%
                    );
                border: 1px solid rgba(255, 238, 190, 0.85);
                box-shadow:
                    0 16px 44px rgba(0, 0, 0, 0.35),
                    0 0 22px rgba(255, 205, 92, 0.45),
                    inset 0 1px 0 rgba(255, 255, 255, 0.75),
                    inset 0 -10px 18px rgba(120, 75, 0, 0.35);
                overflow: hidden;
            }

            #bot-icon::after{
                content: "";
                position: absolute;
                top: -20%;
                left: -60%;
                width: 45%;
                height: 140%;
                transform: skewX(-20deg);
                background: linear-gradient(90deg,
                    rgba(255,255,255,0.0) 0%,
                    rgba(255,255,255,0.65) 45%,
                    rgba(255,255,255,0.0) 80%
                );
                opacity: 0.75;
                mix-blend-mode: soft-light;
                pointer-events: none;
                animation: botIconSweep 2.2s linear infinite;
            }

            #bot-icon .bot-icon-svg{
                width: 44px;
                height: 44px;
                position: relative;
                z-index: 1;
                filter:
                    drop-shadow(0 1px 0 rgba(255,255,255,0.25))
                    drop-shadow(0 0 10px rgba(255,205,92,0.20));
            }

            @keyframes botIconSweep{
                0%{ left: -60%; }
                100%{ left: 120%; }
            }

            @media (prefers-reduced-motion: reduce){
                #bot-icon::after{ animation: none; }
            }
        </style>
        <div id="chatbot">
            <div id="chat-header">Chatbot</div>
            <div id="chat-window">
                <div id="messages"></div>
            </div>
            <div id="input-area">
                <input type="text" id="user-input" placeholder="Escribe tu mensaje aquí..." />
                <button id="send-btn">Enviar</button>
            </div>
        </div>
        <div id="bot-icon">
            <svg class="bot-icon-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <!-- Cabeza -->
                <circle cx="32" cy="20" r="8" fill="#1c1406" />
                <!-- Torso + vestido -->
                <path d="M32 30c-5.8 0-10.6 4.0-12.4 9.6L12 56h40l-7.6-16.4C42.6 34 37.8 30 32 30z" fill="#1c1406" />
                <path d="M22 56l10-22 10 22H22z" fill="#1c1406" />
            </svg>
        </div>
    `;

    document.body.insertAdjacentHTML("beforeend", chatbotHTML);

    const chatBot = document.getElementById("chatbot");
    const botIcon = document.getElementById("bot-icon");
    const sendBtn = document.getElementById("send-btn");
    const userInput = document.getElementById("user-input");
    const messages = document.getElementById("messages");

    const rules = [
        /* 
            -------------------------------------
            | Repuestas por entradas del usuario |
            -------------------------------------
        */
        { input: "hola", answer: "¡Hola! ¿En qué puedo ayudarte?", type: 0 },
        { input: "como estas", answer: "Muy bien gracias por preguntar que deseas hacer el dia de hoy?", type: 0 },
        { input: "adiós", answer: "Adiós, ¡que tengas un buen día!", type: 0 },
        //Productos
        { input: "producto", answer: "Tenemos la siguiente lista de productos: Pasteles, Queques, Postres", type: 0 },
        { input: "pastel", answer: "🍰 Tenemos pasteles de cumpleaños, casuales y bodas.", type: 0 },
        { input: "cumpleaños", answer: "🎁 Nuestros pasteles de cumpleaños tienen 30 porciones a un precio de 170Bs.", type: 0 },
        // Dirección
        { input: "dirección", answer: "🏃 Nuestra dirección es Calle 17 de Calacote, edificio Río Beni, No 560", type: 0 },
        { input: "horarios", answer: "Atendemos de Lunes a Sábado de 07.30 de la mañana a 21.00 horas.", type: 0 },
        { input: "vestidos", answer: "Claro tenemos vestidos de novia en diferentes estilos", type: 0 },
        { input: "estilo", answer: "Clasico, bohemio, princesa, vintage", type: 0 },
        { input: "clasico", answer: "Este estilo es muy acorde para vestir elegante", type: 0 },
        { input: "bohemio", answer: "Este estilo es un tanto mas imponente", type: 0 },
        { input: "princesa", answer: "Este estilo es muy acorde para personas tiernas o que les guste este estilo de cortes", type: 0 },
        { input: "vintage", answer: "Un estilo que data de los 50 muy imponente", type: 0 },
        { input: "humano", answer: "En este momento nuestros operadores están ocupados; por favor déjenos su número de contacto para comunicarnos. 📲", type: 0 },
        /* 
            --------------------------------------
            | Repuestas gestionadas por el agente |
            --------------------------------------
        */
        { input: "", answer: "producto", type: 1 },
        { input: "", answer: "dirección", type: 1 },
        { input: "", answer: "horarios", type: 1 },
        { input: "", answer: "vestidos", type: 1 },
        { input: "", answer: "estilo", type: 1 },
        { input: "", answer: "clasico", type: 1 },
        { input: "", answer: "bohemio", type: 1 },
        { input: "", answer: "princesa", type: 1 },
        { input: "", answer: "vintage", type: 1 },
    ];

    const sendMessage = () => {
        const input = userInput.value.trim().toLowerCase();
        if (!input) return;

        addMessage("user", input);
        const response = getResponse(input);
        if (response) {
            setTimeout(() => addMessage("bot", response), 500);
        }

        userInput.value = "";
    };

    const addMessage = (sender, text, suggestions = []) => {
        const message = document.createElement("div");
        message.className = `message ${sender}`;
        message.textContent = text;
        messages.appendChild(message);

        // Si hay sugerencias y el mensaje es del bot
        if (sender === "bot" && suggestions.length > 0) {
            const suggestionContainer = document.createElement("div");
            suggestionContainer.className = "suggestions";

            // Generar botones para las sugerencias
            suggestions.forEach(suggestion => {
                const button = document.createElement("button");
                button.textContent = suggestion.answer;
                button.onclick = () => handleSuggestion(suggestion.answer);
                suggestionContainer.appendChild(button);
            });

            // Insertar las sugerencias justo después del mensaje del bot
            message.insertAdjacentElement("afterend", suggestionContainer);
        }

        // Desplazar automáticamente al final
        setTimeout(() => {
            const messagesScroll = document.getElementById("chat-window");
            if (messagesScroll) {
                messagesScroll.scrollTo({
                    top: messagesScroll.scrollHeight,
                    behavior: "smooth",
                });
            }
        }, 0);
    };

    const getResponse = (input) => {
        // Buscar si el texto ingresado contiene alguna palabra clave (reglas de tipo 0)
        const rule = rules.find(r => input.includes(r.input) && r.type === 0);
        if (rule) {
            return rule.answer; // Devuelve la respuesta correspondiente
        }
    
        // Si no hay coincidencias, muestra tres sugerencias al azar (reglas de tipo 1)
        const suggestions = rules
        .filter(r => r.type === 1)
        .sort(() => Math.random() - 0.5) // Mezclar las sugerencias aleatoriamente
        .slice(0, 3); // Tomar solo las primeras tres
        addMessage("bot", "Lo siento, quizá quisiste decir:", suggestions);
        return null; // No devuelve texto adicional porque ya lo maneja addMessage
    };

    const handleSuggestion = (suggestionText) => {
        const rule = rules.find(r => r.input === suggestionText && r.type === 0);
        if (rule) {
            addMessage("user", suggestionText);
            setTimeout(() => addMessage("bot", rule.answer), 500);
        }
    };

    sendBtn.addEventListener("click", sendMessage);
    userInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") sendMessage();
    });

    botIcon.addEventListener("click", () => {
        chatBot.classList.toggle("visible");
    });
});
