# Solución de Problemas - Gestión de Imágenes

## Error 1: "Failed to execute 'json' on 'Response': Unexpected end of JSON input"

### Causa
Este error ocurre cuando el servidor no devuelve un JSON válido o devuelve una respuesta vacía.

## Error 2: "500 Internal Server Error"

### Causa
Error en el servidor PHP que impide que se ejecute el script correctamente.

### Solución Implementada

✅ **Reescritura completa de `subir_imagen.php`**
- Código simplificado y más robusto
- Eliminado `register_shutdown_function` problemático
- Manejo de errores más directo con try-catch
- Siempre devuelve JSON válido

### Cómo Verificar
1. Ve a: `http://localhost/MACO.AppLogistica.Web-1/diagnostico_subida.php`
2. Verifica que todos los tests pasen con ✓
3. Intenta subir una imagen desde la interfaz

## Error 3: "Failed to execute 'text' on 'Response': body stream already read"

### Causa
JavaScript intentaba leer el cuerpo de la respuesta HTTP múltiples veces. Una vez que lees un Response stream (con `.text()`, `.json()`, etc.), **no puedes volver a leerlo**.

### Problema en el Código Anterior
```javascript
// INCORRECTO ❌
const errorData = await response.json();  // Primera lectura
// ...
const errorText = await response.text();  // ❌ Error: ya se leyó
```

### Solución Implementada

✅ **Lectura única del response stream**
```javascript
// CORRECTO ✅
const responseText = await response.text();  // Leer UNA vez
const data = JSON.parse(responseText);       // Parsear el texto
```

**Mejoras:**
- Se lee el response body UNA SOLA VEZ como texto
- Se parsea el texto como JSON
- Si falla el parse, se muestra el error con el texto ya leído
- No se vuelve a intentar leer el stream

### Cómo Verificar
1. Abre la consola del navegador (F12)
2. Intenta subir una imagen
3. Ya no deberías ver el error "body stream already read"
4. Verás logs claros de éxito o error

### Soluciones Implementadas

✅ **1. Output Buffering**
- Se agregó `ob_start()` al inicio del script para capturar cualquier salida accidental
- Previene que espacios en blanco o warnings contaminen la respuesta JSON

✅ **2. Manejo de Errores Mejorado**
- Función `enviarRespuestaJSON()` que SIEMPRE devuelve JSON válido
- Captura de errores fatales con `register_shutdown_function()`
- Try-catch en todas las operaciones críticas

✅ **3. Validaciones Robustas**
- Validación mejorada del tipo de archivo con manejo de excepciones
- Mensajes de error más descriptivos
- Logging detallado de todos los errores

✅ **4. Frontend Mejorado**
- Mejor manejo de respuestas HTTP no exitosas
- Captura de errores de parsing JSON
- Logs en consola para debugging

### Cómo Verificar si el Problema Está Resuelto

1. **Abre la consola del navegador** (F12)
2. **Intenta subir la imagen problemática**
3. **Revisa los logs**:
   - Si ves un mensaje de error claro → El sistema está funcionando correctamente
   - Si ves "Respuesta vacía del servidor" → Revisa los logs de PHP

### Revisar Logs de PHP

**Windows (XAMPP):**
```
C:\xampp\php\logs\php_error_log
```

**Linux:**
```
/var/log/apache2/error.log
/var/log/php/error.log
```

### Problemas Comunes y Soluciones

#### 1. Archivo Demasiado Grande

**Síntoma:**
```
Error: El archivo es demasiado grande (máximo permitido por PHP)
```

**Solución:**
Editar `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 10M
```

Reiniciar Apache después del cambio.

#### 2. Tipo de Archivo No Permitido

**Síntoma:**
```
Tipo de archivo no permitido. Tipo detectado: [tipo]
```

**Solución:**
- Asegúrate que la imagen sea JPG, PNG, GIF o WEBP
- Algunos archivos pueden tener extensiones engañosas
- Convierte la imagen a un formato compatible

#### 3. Error de Conexión a Azure

**Síntoma:**
```
Error al subir la imagen: [mensaje de Azure]
```

**Soluciones:**
1. Verifica las credenciales en `src/azure.php`
2. Verifica que el contenedor existe en Azure
3. Verifica la conexión a internet
4. Revisa los permisos del contenedor

#### 4. Nombre de Archivo con Caracteres Especiales

**Síntoma:**
La imagen no se sube o se sube con nombre corrupto

**Solución:**
- Usa nombres de archivo simples (letras, números, guiones)
- Evita caracteres especiales: `&, %, #, @, !, espacios`
- El sistema limpia automáticamente el nombre pero es mejor prevenir

#### 5. Respuesta Vacía del Servidor

**Síntoma:**
```
Error: Respuesta inválida del servidor
```

**Pasos de Debugging:**

1. **Revisar logs de PHP**:
   ```bash
   tail -f C:\xampp\php\logs\php_error_log
   ```

2. **Probar con imagen simple**:
   - Crea una imagen pequeña (100x100 px)
   - Nombra sin caracteres especiales: `test123.jpg`
   - Intenta subirla

3. **Verificar permisos**:
   ```bash
   # Windows
   icacls "C:\xampp\htdocs\MACO.AppLogistica.Web-1\cache" /grant Users:F

   # Linux
   chmod 777 cache/
   ```

4. **Verificar Azure SDK**:
   ```bash
   cd C:\xampp\htdocs\MACO.AppLogistica.Web-1
   composer show microsoft/azure-storage-blob
   ```

### Debugging Avanzado

#### Ver Respuesta Completa del Servidor

Abre la consola del navegador (F12) y ejecuta:

```javascript
fetch('../Logica/subir_imagen.php', {
    method: 'POST',
    body: new FormData(document.querySelector('form'))
})
.then(response => response.text())
.then(text => {
    console.log('Respuesta del servidor:', text);
    try {
        const json = JSON.parse(text);
        console.log('JSON parseado:', json);
    } catch (e) {
        console.error('No es JSON válido:', e);
    }
});
```

#### Activar Logging Detallado

Editar `php.ini`:
```ini
error_reporting = E_ALL
display_errors = On
log_errors = On
error_log = C:\xampp\php\logs\php_error_log
```

### Casos Específicos Resueltos

#### Caso: "Gemini_Generated_Image_6oda3f6oda3f6oda"

**Problema Original:**
Nombre de archivo con caracteres especiales y formato inusual.

**Solución:**
El sistema ahora:
1. Valida el tipo MIME correctamente
2. Maneja nombres con guiones bajos y números
3. Devuelve errores descriptivos si algo falla

**Cómo Subir Esta Imagen:**
1. Opción A: Renombrar a algo simple: `imagen001.jpg`
2. Opción B: Dejar que el sistema use el nombre original (se limpiará automáticamente)

### Verificación Post-Actualización

Ejecuta este checklist:

- [ ] `php -l Logica/subir_imagen.php` → Sin errores
- [ ] `php -l View/Gestion_imagenes.php` → Sin errores
- [ ] Subir imagen de prueba pequeña → Funciona
- [ ] Revisar logs de PHP → Sin errores
- [ ] Verificar Azure SDK → Instalado

### Soporte Adicional

Si el problema persiste:

1. **Captura de pantalla** de la consola del navegador (F12)
2. **Últimas líneas** del log de PHP
3. **Nombre y tamaño** del archivo que falla
4. **Tipo de archivo** según `file` o propiedades de Windows

---

**Última actualización:** 2025-12-16
**Versión del sistema:** 1.1
