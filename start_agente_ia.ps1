param(
	[switch]$Venv,
	[string]$App = "app_v1.py",
	[int]$Port = 5000
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$agentDir = Join-Path $root "agente_ia"
if(!(Test-Path $agentDir)){
	throw "No se encontró la carpeta: $agentDir"
}

Set-Location $agentDir

# Crear/activar venv local
$venvDir = Join-Path $agentDir ".venv"
if($Venv -or !(Test-Path $venvDir)){
	py -m venv .venv
}

# Activar venv (solo para esta sesión)
Set-ExecutionPolicy -Scope Process Bypass
. .\.venv\Scripts\Activate.ps1

# Instalar deps si hay requirements.txt
$req = Join-Path $agentDir "requirements.txt"
if(Test-Path $req){
	python -m pip install --upgrade pip
	python -m pip install -r requirements.txt
}else{
	python -m pip install --upgrade pip
	# Dependencias mínimas para app_v2.py (y también app_v1.py)
	python -m pip install flask flask-cors llama-cpp-python nltk scikit-learn
	# Datos requeridos por NLTK (se descarga una vez)
	python -c "import nltk; nltk.download('stopwords')"
}

# Ejecutar (si el script acepta puerto por env var, puedes usarlo ahí)
$env:PORT = "$Port"
python $App
