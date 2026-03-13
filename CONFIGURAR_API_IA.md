# 🚀 Guía para Configurar API de IA para Virtual Try-On

Esta guía te ayudará a configurar una API real de IA para que el probador virtual funcione de manera profesional, adaptando el vestido al cuerpo de la persona.

## 📋 Opciones de APIs

### 1. Replicate (Recomendado) ⭐
- **Ventajas**: Fácil de usar, buena documentación, modelos pre-entrenados
- **Costo**: Pay-as-you-go (aproximadamente $0.01-0.05 por imagen)
- **Modelo recomendado**: `cuuupid/idm-vton` o `levihsu/ootdiffusion`

### 2. Alternativas
- **Hugging Face**: Modelos gratuitos pero más complejos de implementar
- **Custom API**: Si tienes tu propio servidor con modelos de IA

---

## 🔧 Paso 1: Obtener API Key de Replicate

1. **Regístrate en Replicate**
   - Ve a: https://replicate.com
   - Crea una cuenta (puedes usar GitHub, Google, etc.)

2. **Obtén tu API Token**
   - Ve a: https://replicate.com/account/api-tokens
   - Haz clic en "Create token"
   - Copia el token (se muestra solo una vez, guárdalo bien)

3. **Agrega créditos** (opcional para empezar)
   - Replicate da $5 de crédito gratis al registrarte
   - Ve a: https://replicate.com/account/billing

---

## 🔧 Paso 2: Configurar en tu Proyecto

### Opción A: Usar solo Replicate (más simple)

1. **Edita `config/app.php`**:
```php
// Configuración de IA para Virtual Try-On
const REPLICATE_API_KEY = "r8_Pgrzfs3RW7svNiAdXzk0tg2m1Kz8Vde3vz4eY"; // Pega tu API key aquí
const REPLICATE_MODEL = "cuuupid/idm-vton";
const USE_AI_SIMULATION = false; // Cambiar a false para usar API real
```

2. **Configurar subida de imágenes públicas**

Para que Replicate pueda acceder a tus imágenes, necesitas que estén en URLs públicas. Tienes dos opciones:

#### Opción 1: Usar ImgBB (Gratis, recomendado para desarrollo)

1. Obtén API key de ImgBB:
   - Ve a: https://api.imgbb.com/
   - Regístrate (gratis)
   - Copia tu API key

2. Edita `app/controllers/virtualTryOnController.php`:
   - Busca la función `subirImagenTemporal()`
   - En la línea donde dice `$imgbb_api_key = "d60d185f159459c2eb3f01f67560249b";`
   - Pega tu API key: `$imgbb_api_key = "d60d185f159459c2eb3f01f67560249b";`

#### Opción 2: Usar ngrok para desarrollo local

1. **Instala ngrok**: https://ngrok.com/download
2. **Ejecuta ngrok**:
```bash
ngrok http 80
```
3. **Copia la URL pública** (ej: `https://abc123.ngrok.io`)
4. **Actualiza `APP_URL` en `config/app.php`**:
```php
const APP_URL="https://abc123.ngrok.io/BOUTIQUE/";
```

#### Opción 3: Subir a servidor público

Si tu sitio ya está en un servidor público (no localhost), las imágenes ya serán accesibles.

---

## 🧪 Paso 3: Probar la Configuración

1. **Abre tu aplicación** en el navegador
2. **Ve al probador virtual** (botón "Probar Vestidos con IA")
3. **Sube una foto** y selecciona un vestido
4. **Procesa con IA**

### ✅ Si funciona correctamente:
- Verás un mensaje: "El vestido ha sido aplicado exitosamente con IA"
- El resultado mostrará el vestido adaptado al cuerpo de la persona
- El procesamiento puede tardar 10-30 segundos

### ❌ Si hay errores:

**Error: "API key no configurada"**
- Verifica que `REPLICATE_API_KEY` tenga tu token
- Verifica que `USE_AI_SIMULATION = false`

**Error: "No se encontraron las imágenes necesarias"**
- Verifica que las imágenes estén en `app/views/productos/`
- Verifica que ImgBB esté configurado o que uses ngrok

**Error: "Error al procesar con IA"**
- Revisa la consola del navegador (F12) para ver errores
- Verifica que tu API key de Replicate sea válida
- Verifica que tengas créditos en Replicate

---

## 💰 Costos Aproximados

- **Replicate**: ~$0.01-0.05 por imagen procesada
- **ImgBB**: Gratis hasta 32MB por imagen
- **Crédito inicial de Replicate**: $5 gratis al registrarte

**Ejemplo**: 
- 100 imágenes procesadas = ~$1-5
- 1000 imágenes procesadas = ~$10-50

---

## 🔍 Solución de Problemas

### Problema: Las imágenes no se suben
**Solución**: 
- Verifica que ImgBB API key esté configurada
- O usa ngrok para exponer tu servidor local

### Problema: La API tarda mucho
**Solución**: 
- Es normal, el procesamiento de IA puede tardar 10-60 segundos
- El código espera hasta 60 segundos (30 intentos × 2 segundos)

### Problema: Error 401 (Unauthorized)
**Solución**: 
- Verifica que tu API key de Replicate sea correcta
- Verifica que no tenga espacios extra

### Problema: Error 402 (Payment Required)
**Solución**: 
- Agrega créditos a tu cuenta de Replicate
- Ve a: https://replicate.com/account/billing

---

## 📝 Notas Importantes

1. **Seguridad**: Nunca subas tu API key a repositorios públicos
2. **Límites**: Replicate tiene límites de rate (velocidad), revisa su documentación
3. **Modelos**: Puedes cambiar el modelo en `REPLICATE_MODEL` si quieres probar otros
4. **Fallback**: Si la API falla, automáticamente usará la simulación

---

## 🎯 Próximos Pasos

Una vez configurado:
1. Prueba con diferentes fotos y vestidos
2. Ajusta los parámetros del modelo si es necesario
3. Considera implementar caché para resultados ya procesados
4. Monitorea el uso de créditos en Replicate

---

## 📞 ¿Necesitas Ayuda?

Si tienes problemas:
1. Revisa los logs de PHP (errores en consola)
2. Verifica la documentación de Replicate: https://replicate.com/docs
3. Revisa que todas las extensiones de PHP estén habilitadas (curl, gd)

---

**¡Listo!** Con esto deberías tener funcionando el probador virtual con IA real. 🎉

