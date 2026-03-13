param(
	[string]$Name = "BOUTIQUE_AgenteIA"
)

$ErrorActionPreference = "Stop"

$startupDir = Join-Path $env:APPDATA "Microsoft\Windows\Start Menu\Programs\Startup"
$cmdPath = Join-Path $startupDir ("$Name.cmd")

if(Test-Path $cmdPath){
	Remove-Item -Force $cmdPath
	Write-Host "Eliminado autostart: $cmdPath"
}else{
	Write-Host "No existe: $cmdPath"
}
