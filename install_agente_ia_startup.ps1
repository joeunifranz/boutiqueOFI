param(
	[string]$Name = "BOUTIQUE_AgenteIA",
	[string]$App = "app_v1.py",
	[int]$Port = 5000
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$runner = Join-Path $root "run_agente_ia.ps1"
if(!(Test-Path $runner)){
	throw "No existe: $runner"
}

$startupDir = Join-Path $env:APPDATA "Microsoft\Windows\Start Menu\Programs\Startup"
if(!(Test-Path $startupDir)){
	throw "No existe Startup dir: $startupDir"
}

$cmdPath = Join-Path $startupDir ("$Name.cmd")

# .cmd para que corra sin depender de políticas de ejecución del sistema
$cmd = "@echo off`r`n"
$cmd += 'start "" /min powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + $runner + '" -App "' + $App + '" -Port ' + $Port.ToString() + "`r`n"

Set-Content -Path $cmdPath -Value $cmd -Encoding ASCII

Write-Host "Instalado autostart en Startup: $cmdPath"
Write-Host "Se ejecutará al iniciar sesión. Para probar ahora: `"$cmdPath`""
