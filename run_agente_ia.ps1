param(
	[string]$App = "app_v1.py",
	[int]$Port = 5000
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$agentDir = Join-Path $root "agente_ia"
if(!(Test-Path $agentDir)){
	throw "No se encontró la carpeta: $agentDir"
}

$venvPy = Join-Path $agentDir ".venv\Scripts\python.exe"
if(!(Test-Path $venvPy)){
	throw "No se encontró el Python del venv. Ejecuta primero: .\start_agente_ia.ps1"
}

$appPath = Join-Path $agentDir $App
if(!(Test-Path $appPath)){
	throw "No existe el archivo de app: $appPath"
}

# Si ya hay algo escuchando el puerto, no volver a levantar
try{
	$alreadyUp = Test-NetConnection -ComputerName "127.0.0.1" -Port $Port -InformationLevel Quiet -WarningAction SilentlyContinue
	if($alreadyUp){
		exit 0
	}
}catch{
	# Si Test-NetConnection no está disponible, seguimos y dejamos que falle por puerto en uso.
}

$env:PORT = "$Port"
Set-Location $agentDir

& $venvPy $appPath
