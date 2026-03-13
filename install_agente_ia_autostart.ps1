param(
	[string]$TaskName = "BOUTIQUE_AgenteIA",
	[string]$App = "app_v1.py",
	[int]$Port = 5000
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$runner = Join-Path $root "run_agente_ia.ps1"
if(!(Test-Path $runner)){
	throw "No existe: $runner"
}

# Comando que ejecutará el task (sin ventana)
$tr = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$runner`" -App `"$App`" -Port $Port"

# Crear task al iniciar sesión del usuario actual
schtasks /Create /TN $TaskName /TR $tr /SC ONLOGON /RL LIMITED /F | Out-String

Write-Host "Task instalado: $TaskName"
Write-Host "Para ejecutarlo ahora: schtasks /Run /TN $TaskName"
Write-Host "Para borrarlo: .\uninstall_agente_ia_autostart.ps1 -TaskName $TaskName"
