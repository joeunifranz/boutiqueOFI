# Cómo Habilitar la Extensión GD en XAMPP

## 📋 Pasos para Habilitar GD

### Paso 1: Encontrar el archivo php.ini

1. Abre el **Panel de Control de XAMPP**
2. Haz clic en **"Config"** junto a Apache
3. Selecciona **"PHP (php.ini)"**
   - Esto abrirá el archivo `php.ini` en el editor

**O manualmente:**
- Ve a: `C:\xampp\php\php.ini`
- Ábrelo con el Bloc de notas o cualquier editor de texto

### Paso 2: Buscar la línea de GD

1. Presiona **Ctrl + F** para buscar
2. Busca: `;extension=gd`
   - Nota: el punto y coma `;` al inicio significa que está comentado (deshabilitado)

### Paso 3: Habilitar GD

1. Encuentra esta línea:
   ```
   ;extension=gd
   ```

2. Elimina el punto y coma `;` al inicio para que quede así:
   ```
   extension=gd
   ```

3. **Guarda el archivo** (Ctrl + S)

### Paso 4: Reiniciar Apache

1. En el **Panel de Control de XAMPP**
2. Haz clic en **"Stop"** en Apache
3. Espera unos segundos
4. Haz clic en **"Start"** en Apache

### Paso 5: Verificar que Funciona

1. Abre en tu navegador: `http://localhost/BOUTIQUE/test_gd.php`
2. Deberías ver: **"✓ GD está HABILITADO"**

## 🔍 Ubicaciones Comunes de php.ini en XAMPP

- `C:\xampp\php\php.ini` (más común)
- `C:\xampp\apache\bin\php.ini` (a veces)

## ⚠️ Si No Encuentras `;extension=gd`

Si no encuentras esa línea, agrega esta línea al final del archivo `php.ini`:

```
extension=gd
```

## 🐛 Solución de Problemas

**Si después de reiniciar Apache sigue sin funcionar:**

1. Verifica que editaste el `php.ini` correcto
   - Puedes crear un archivo `phpinfo.php` con este contenido:
   ```php
   <?php phpinfo(); ?>
   ```
   - Ábrelo en el navegador y busca "Loaded Configuration File"
   - Esa es la ruta del `php.ini` que estás usando

2. Verifica que la extensión existe:
   - Busca en `php.ini`: `extension_dir`
   - Debe apuntar a algo como: `C:\xampp\php\ext`
   - Verifica que existe el archivo `php_gd2.dll` en esa carpeta

3. Si no existe `php_gd2.dll`:
   - Descarga PHP desde el sitio oficial de XAMPP
   - O reinstala XAMPP

## ✅ Verificación Final

Después de seguir estos pasos, prueba el probador virtual nuevamente. Debería funcionar correctamente.

