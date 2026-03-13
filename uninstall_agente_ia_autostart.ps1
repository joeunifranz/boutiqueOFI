param(
	[string]$TaskName = "BOUTIQUE_AgenteIA"
)

$ErrorActionPreference = "Stop"

schtasks /Delete /TN $TaskName /F | Out-String
Write-Host "Task eliminado: $TaskName"
