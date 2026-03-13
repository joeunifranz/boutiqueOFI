import re
from flask import Flask, jsonify, request, send_from_directory
from flask_cors import CORS
from llama_cpp import Llama
import nltk
from nltk.corpus import stopwords
from nltk.stem import SnowballStemmer
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
import os
import glob
import unicodedata
from typing import Optional

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})


@app.route("/")
def index():
    return jsonify({"ok": True, "service": "agente_ia", "endpoint": "/chat"})


@app.route("/agent.js")
def agent_js():
    return send_from_directory(".", "agent.js")
# ==========================================
# CONFIGURACIÓN NLP
# ==========================================
# Asegurarse de tener las dependencias de NLTK
try:
    nltk.data.find('tokenizers/punkt')
except LookupError:
    nltk.download('punkt')
    nltk.download('stopwords')

stemmer = SnowballStemmer('spanish')
stop_words = stopwords.words('spanish')

print("--- CARGANDO CEREBRO CULINARIO (LLM) ---")
# Ajusta la ruta a tu modelo local
LLM = Llama(
    model_path="./models/qwen2.5-1.5b-instruct-q4_k_m.gguf",
    n_ctx=2048, 
    n_threads=4, 
    verbose=False
)

# Base de Conocimiento Global
KNOWLEDGE_BASE = []
SECTION_TITLES = []  # Para búsquedas directas por encabezado

# ==========================================
# FUNCIONES AUXILIARES
# ==========================================

def clean_text(text):
    """Normaliza texto: minúsculas y sin acentos para búsqueda."""
    text = text.lower()
    text = unicodedata.normalize('NFKD', text).encode('ASCII', 'ignore').decode('utf-8')
    return text


def extract_named_block(text: str, block_name: str) -> str:
    """Extrae el contenido de un bloque tipo 'NOMBRE_BLOQUE:' dentro de db_context."""
    if not text:
        return ""
    # Captura desde 'block_name:' hasta el siguiente encabezado en MAYÚSCULAS o fin.
    # Ejemplos esperados de encabezado: 'CATEGORIAS_DISPONIBLES:' o 'PRODUCTOS_RELACIONADOS ...:'
    pattern = re.compile(
        r"(?ms)^" + re.escape(block_name) + r"\s*:\s*\n(.*?)(?:\n\n[A-ZÁÉÍÓÚÜÑ0-9_ \-()]+\s*:\s*\n|\Z)"
    )
    m = pattern.search(text)
    return (m.group(1) or "").strip() if m else ""


def wants_categories(message: str) -> bool:
    m = clean_text(message or "")
    return ("categoria" in m) or ("categorias" in m)


def wants_products(message: str) -> bool:
    m = clean_text(message or "")
    keys = ["producto", "productos", "vestido", "vestidos", "talla", "precio", "presupuesto", "bs", "disponible"]
    return any(k in m for k in keys)


def wants_reservation(message: str) -> bool:
    m = clean_text(message or "")
    keys = ["reserva", "reservar", "cita", "citas", "pagar", "qr", "abono", "confirmar", "codigo"]
    return any(k in m for k in keys)


def maybe_answer_from_db_context(user_msg: str, db_context: str) -> Optional[str]:
    """Respuestas determinísticas cuando db_context trae datos estructurados.

    Esto evita que el LLM 'invente' categorías/productos cuando ya están disponibles.
    """
    if not db_context:
        return None

    if wants_categories(user_msg):
        cats = extract_named_block(db_context, "CATEGORIAS_DISPONIBLES")
        if cats:
            return "Estas son las categorías disponibles ahora mismo:\n" + cats

    if wants_products(user_msg):
        prods = extract_named_block(db_context, "PRODUCTOS_RELACIONADOS (CATALOGO_PUBLICO)")
        if prods:
            return "Estos son algunos productos relacionados del catálogo:\n" + prods

    if wants_reservation(user_msg):
        reserva = extract_named_block(db_context, "RESERVA_DEL_CLIENTE")
        if reserva:
            return "Esto es lo que veo de tu reserva:\n" + reserva

    return None

def parse_and_index():
    """
    Parsea los documentos de la boutique.
    Segmenta por párrafos para capturar secciones clave del contenido.
    """
    global KNOWLEDGE_BASE, SECTION_TITLES
    KNOWLEDGE_BASE = []
    SECTION_TITLES = []
    
    files = glob.glob(os.path.join('docs', '*.txt'))
    print("\n--- INDEXANDO BOUTIQUE ---")
    
    for filename in files:
        with open(filename, 'r', encoding='utf-8') as f:
            content = f.read()
            
            # Segmentar por párrafos (doble salto de línea)
            blocks = [b.strip() for b in re.split(r'\n\s*\n', content) if b.strip()]

            for block in blocks:
                lines = block.splitlines()
                title = lines[0].strip() if lines else "Sección"
                SECTION_TITLES.append(clean_text(title))
                KNOWLEDGE_BASE.append(block)
                print(f"-> Sección indexada: {title[:60]}")

    print(f"--- SYSTEM READY: {len(KNOWLEDGE_BASE)} segmentos indexados ---")

# Ejecutar indexación al inicio
parse_and_index()

# ==========================================
# LÓGICA DE BÚSQUEDA & MEMORIA
# ==========================================

def preprocess_search(text):
    """Tokeniza y extrae raíces para TF-IDF."""
    clean = clean_text(text)
    # Tokenización robusta (sin depender de 'punkt'/'punkt_tab')
    tokens = re.findall(r"[a-z0-9]+", clean)
    stem_tokens = [stemmer.stem(t) for t in tokens if t not in stop_words]
    return " ".join(stem_tokens)

def search_context(query):
    query_lower = clean_text(query)
    # 1. ROUTER: UBICACIÓN
    if any(k in query_lower for k in ["ubicacion", "ubicación", "direccion", "dirección", "donde", "avenida", "maximiliano"]):
        for doc in KNOWLEDGE_BASE:
            if "maximiliano" in clean_text(doc) or "direccion" in clean_text(doc) or "dirección" in doc.lower():
                return doc

    # 2. ROUTER: PRECIOS / PRESUPUESTOS
    if any(k in query_lower for k in ["precio", "precios", "costo", "presupuesto", "bs", "boliviano"]):
        for doc in KNOWLEDGE_BASE:
            if "precio aproximado" in doc.lower() or "bs." in doc.lower():
                return doc

    # 3. ROUTER: EDAD
    if "edad" in query_lower or "años" in query_lower:
        for doc in KNOWLEDGE_BASE:
            if "novias de 18" in doc.lower() or "novias de 26" in doc.lower() or "novias de 36" in doc.lower() or "novias mayores" in doc.lower():
                return doc

    # 4. ROUTER: ALTURA
    if "altura" in query_lower or "estatura" in query_lower or "alta" in query_lower or "baja" in query_lower:
        for doc in KNOWLEDGE_BASE:
            if "estatura" in doc.lower() or "alta" in doc.lower():
                return doc

    # 5. ROUTER: TIPO DE CUERPO
    if "cuerpo" in query_lower or "figura" in query_lower or "silue" in query_lower:
        for doc in KNOWLEDGE_BASE:
            if "reloj de arena" in doc.lower() or "pera" in doc.lower() or "manzana" in doc.lower() or "rectangular" in doc.lower():
                return doc

    # 6. ROUTER: PERSONALIZACIÓN / PROCESO
    if "personalizacion" in query_lower or "personalización" in query_lower or "proceso" in query_lower or "ajuste" in query_lower:
        for doc in KNOWLEDGE_BASE:
            if "personalización" in doc.lower() or "personalizacion" in doc.lower() or "proceso" in doc.lower():
                return doc

    # 7. FALLBACK: BÚSQUEDA SEMÁNTICA (TF-IDF)
    vectorizer = TfidfVectorizer(preprocessor=preprocess_search)
    try:
        corpus = KNOWLEDGE_BASE + [query_lower]
        tfidf = vectorizer.fit_transform(corpus)
        # Calcular similitud del coseno entre la query (último item) y los documentos
        cosine = cosine_similarity(tfidf[-1], tfidf[:-1])
        idx = np.argmax(cosine)
        
        # Umbral de confianza
        if cosine[0][idx] > 0.05:
            return KNOWLEDGE_BASE[idx]
    except Exception as e:
        print(f"Error en búsqueda vectorial: {e}")
        
    return None

# ==========================================
# ENDPOINT API
# ==========================================
@app.route('/chat', methods=['POST'])
def chat():
    data = request.json or {}
    user_msg = (data.get('message', '') or '').strip()
    db_context = (data.get('db_context', '') or '').strip()
    page_context = (data.get('page_context', '') or '').strip()
    user_context = data.get('user_context', {}) if isinstance(data.get('user_context', {}), dict) else {}

    deterministic = maybe_answer_from_db_context(user_msg, db_context)
    if deterministic:
        return jsonify({"response": deterministic})
    
    context = search_context(user_msg)
    
    client_logged_in = bool(user_context.get('client_logged_in', False))
    client_name = (user_context.get('client_name', '') or '').strip()

    base_rules = """
REGLAS DE SEGURIDAD (OBLIGATORIAS):
- Estás atendiendo a un cliente (interfaz pública). NO reveles información administrativa.
- No respondas sobre: ventas/ganancias, usuarios/empleados, reportes internos, costos/precio de compra, contraseñas/tokens, lista de clientes o correos.
- Si te piden algo de administración, responde: "No tengo permisos para esa información" y ofrece ayuda con catálogo y reservas.
- No inventes datos de la tienda: si no está en el contexto recuperado o en DATOS_EN_VIVO, dilo y pide un dato adicional.
""".strip()

    live_block = ""
    if page_context:
        live_block += f"\n\nCONTEXTO_DE_PAGINA:\n{page_context}"
    if db_context:
        live_block += f"\n\nDATOS_EN_VIVO (BASE DE DATOS, SOLO PUBLICO/CLIENTE):\n{db_context}"

    # Prompt del Sistema Adaptado a Boutique Dorita
    if context:
        sys_msg = f"""Eres la asesora elegante de Boutique Dorita (venta y personalización de vestidos de novia).

INFORMACION_RECUPERADA (DOCUMENTOS):
{context}

{base_rules}

INSTRUCCIONES DE RESPUESTA:
- Tono: cálido, profesional y tranquilizador; transmite exclusividad sin ser rebuscada.
- Claridad: responde en 2-6 líneas o viñetas breves.
- Prioriza DATOS_EN_VIVO para catálogo, disponibilidad y estado de reserva.
- Si el cliente está logueado y pregunta por su reserva, usa el bloque RESERVA_DEL_CLIENTE.
- Si aplica, incluye enlaces internos de la app cuando estén en el contexto.
{live_block}
"""
    else:
        sys_msg = f"""Eres la asesora elegante de Boutique Dorita (venta y personalización de vestidos de novia).

{base_rules}

No encontraste información exacta en documentos. Puedes orientar sobre ubicación, rangos de precios, recomendaciones por edad/altura/cuerpo, personalización y proceso.
Haz 1-2 preguntas para completar datos faltantes.
{live_block}
"""

    if client_logged_in and client_name:
        sys_msg += f"\n\nEl nombre del cliente es: {client_name}."

    # Formato ChatML
    prompt = f"<|im_start|>system\n{sys_msg}<|im_end|>\n<|im_start|>user\n{user_msg}<|im_end|>\n<|im_start|>assistant\n"

    try:
        # Generación
        output = LLM(prompt, max_tokens=512, stop=["<|im_end|>"], echo=False, temperature=0.2)
        response = output['choices'][0]['text'].strip()
    except Exception as e:
        response = "Lo siento, tuve un problema en la cocina (Error del servidor)."
        print(e)

    return jsonify({"response": response})

if __name__ == '__main__':
    # Evitar errores de consola (Click/Colorama) en algunos entornos Windows
    try:
        import flask.cli
        flask.cli.show_server_banner = lambda *args, **kwargs: None
    except Exception:
        pass

    port = int(os.environ.get('PORT', '5000'))
    debug = str(os.environ.get('FLASK_DEBUG', '0')).lower() in ('1', 'true')
    app.run(host='127.0.0.1', port=port, debug=debug, use_reloader=False)