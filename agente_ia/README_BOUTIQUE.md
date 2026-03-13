# Agente IA (backend) dentro de BOUTIQUE

Este proyecto usa un backend Python (Flask) para el chat. En el frontend de clientes, el chat se consume vía el proxy PHP:

- `BOUTIQUE/app/ajax/agenteIaAjax.php` → reenvía al backend Flask
- URL esperada por defecto: `http://127.0.0.1:5000/chat`

## Levantar el backend desde el proyecto

Desde PowerShell en la raíz `BOUTIQUE/` (por defecto levanta `app_v1.py`):

```powershell
.\start_agente_ia.ps1
```

Opciones:

```powershell
# Elegir script
.\start_agente_ia.ps1 -App "app_v1.py"

# (Opcional) Usar el script alternativo
.\start_agente_ia.ps1 -App "app_v2.py"

# Cambiar puerto (si tu script respeta $env:PORT)
.\start_agente_ia.ps1 -Port 5000
```

## Probar el endpoint

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:5000/chat" -Method Post -ContentType "application/json" -Body (@{ message = "hola" } | ConvertTo-Json)
```

## Si quieres apuntar a otra URL

Define variables de entorno (y reinicia Apache):

- `BOUTIQUE_AGENT_IA_ENABLED=1`
- `BOUTIQUE_AGENT_IA_API_URL=http://127.0.0.1:5000/chat`
