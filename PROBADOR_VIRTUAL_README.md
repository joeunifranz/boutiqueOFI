# Probador Virtual con IA - Guía de Configuración

## 🎯 Características

- **Probador Virtual con IA**: Los clientes pueden subir su foto y probar vestidos virtualmente
- **Interfaz Intuitiva**: Modal elegante con selección de vestidos
- **Procesamiento con IA**: Integración con APIs de virtual try-on

## 📋 Requisitos

1. PHP 7.4+ con extensión GD habilitada
2. Servidor web (Apache/Nginx)
3. (Opcional) API key de Replicate para IA real

## 🚀 Configuración Rápida

### Paso 1: Crear directorio temporal

El sistema creará automáticamente el directorio `app/views/temp_tryon/` para almacenar imágenes temporales.

### Paso 2: Configurar API de IA (Opcional)

Para usar IA real en lugar de simulación:

1. **Obtener API key de Replicate:**
   - Ve a https://replicate.com/account/api-tokens
   - Crea una cuenta y obtén tu API key

2. **Configurar en `config/app.php`:**
   ```php
   const REPLICATE_API_KEY = "tu_api_key_aqui";
   const USE_AI_SIMULATION = false;
   const REPLICATE_MODEL = "cuuupid/idm-vton"; // o "levihsu/ootdiffusion"
   ```

### Paso 3: Probar el Sistema

1. Ve a la página de inicio: `http://localhost/BOUTIQUE/`
2. Haz clic en "Probar Vestidos con IA"
3. Sube una foto
4. Selecciona un vestido
5. Haz clic en "Aplicar Vestido con IA"

## 🔧 Modo Simulación vs IA Real

### Modo Simulación (Actual)
- No requiere API key
- Funciona inmediatamente
- Combina imágenes básicamente (superposición)
- Útil para desarrollo y pruebas

### Modo IA Real (Replicate)
- Requiere API key de Replicate
- Procesamiento más realista
- Mejor calidad de resultados
- Puede tener costos según uso

## 📝 Notas Importantes

1. **Límites de tamaño**: Las imágenes deben ser menores a 10MB
2. **Formatos soportados**: JPG y PNG
3. **Imágenes temporales**: Se eliminan después del procesamiento
4. **Rendimiento**: El procesamiento puede tardar 10-30 segundos con IA real

## 🎨 Personalización

Puedes personalizar:
- Estilos en `inicio-view.php` (sección de estilos del probador)
- Modelo de IA en `config/app.php`
- Cantidad de productos mostrados en `virtualTryOnController.php`

## 🐛 Solución de Problemas

**Error: "No se pudo guardar la imagen"**
- Verifica permisos del directorio `app/views/temp_tryon/`
- Asegúrate de que PHP tenga permisos de escritura

**Error: "API key no configurada"**
- El sistema usará simulación automáticamente
- Para usar IA real, configura `REPLICATE_API_KEY` en `config/app.php`

**Resultados no realistas:**
- En modo simulación, los resultados son básicos
- Para mejores resultados, configura API de Replicate

## 📞 Soporte

Para mejorar la integración con IA, considera:
- Usar Cloudinary para almacenar imágenes temporales
- Implementar caché de resultados
- Agregar más modelos de IA según necesidades

