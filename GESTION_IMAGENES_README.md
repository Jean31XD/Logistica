# Sistema de Gestión de Imágenes - Azure Blob Storage

Sistema completo para administrar imágenes de productos en Azure Blob Storage para MACO Logística.

## 📋 Características

- ✅ **Subida MÚLTIPLE de imágenes** a Azure Blob Storage
- ✅ **Asignación manual de SKU** para cada imagen
- ✅ **Vista previa** de todas las imágenes antes de subir
- ✅ **Drag & Drop** para seleccionar archivos
- ✅ **Barra de progreso** en tiempo real
- ✅ **Validación de archivos** (tipo y tamaño)
- ✅ **Resultados detallados** por cada archivo
- ✅ **Sistema de caché** para mejorar el rendimiento
- ✅ **Protección CSRF** en todas las operaciones

## 🚀 Instalación

### Paso 1: Instalar Composer (si no está instalado)

Descarga e instala Composer desde: https://getcomposer.org/download/

### Paso 2: Instalar Azure SDK

Abre una terminal en la raíz del proyecto y ejecuta:

```bash
cd C:\xampp\htdocs\MACO.AppLogistica.Web-1
composer require microsoft/azure-storage-blob
```

Esto creará una carpeta `vendor/` con todas las dependencias necesarias.

### Paso 3: Verificar la instalación

Verifica que se haya creado la carpeta `vendor/` en la raíz del proyecto y que contenga:
- `vendor/autoload.php`
- `vendor/microsoft/`

## 📁 Archivos Creados

El sistema ha creado los siguientes archivos:

```
MACO.AppLogistica.Web-1/
├── src/
│   ├── cache.php           # Sistema de caché
│   └── azure.php           # Funciones de Azure Blob Storage
├── cache/                  # Directorio de caché (se crea automáticamente)
├── Logica/
│   ├── subir_imagen.php    # Lógica para subir imágenes
│   └── eliminar_imagen.php # Lógica para eliminar imágenes
├── View/
│   └── Gestion_imagenes.php # Interfaz de administración
└── GESTION_IMAGENES_README.md # Esta documentación
```

## 🔧 Configuración

### Credenciales de Azure

Las credenciales ya están configuradas en `src/azure.php`:

```php
$azure_connection_string = 'DefaultEndpointsProtocol=https;AccountName=catalogodeimagenes;AccountKey=...';
$azure_container_name = "imagenes-productos";
$azure_account_name = 'catalogodeimagenes';
```

⚠️ **IMPORTANTE**: En producción, estas credenciales deberían estar en variables de entorno o un archivo `.env` para mayor seguridad.

## 📖 Uso

### Acceder al Sistema

1. Inicia sesión como **administrador** (pantalla 0)
2. Ve al **Panel de Administración** (Admin.php)
3. Haz clic en el módulo **"Gestión de Imágenes"**

### Subir Múltiples Imágenes

1. **Selecciona las imágenes**:
   - Haz clic en el área de subida o
   - Arrastra múltiples archivos al área punteada

2. **Asigna SKUs (OPCIONAL)**:
   - Aparecerá una tabla con todas las imágenes seleccionadas
   - Verás una vista previa de cada imagen
   - **Opción 1**: Deja el campo vacío para usar el nombre original del archivo
   - **Opción 2**: Ingresa un SKU/Código personalizado para cambiar el nombre
   - Puedes eliminar archivos de la lista si te equivocaste

3. **Sube todo**:
   - Haz clic en **"Subir Todas las Imágenes"**
   - Verás una barra de progreso en tiempo real
   - Al finalizar, verás un resumen con éxitos y errores

4. **Resultado**:
   - Si dejaste el campo vacío: Se guarda como `[nombre-original].jpg`
   - Si ingresaste un SKU: Se guarda como `[SKU].jpg`
   - Si todas fueron exitosas, el formulario se limpia automáticamente
   - Puedes continuar subiendo más imágenes

### Ejemplos de Flujo de Trabajo

**Ejemplo 1 - Usar nombres originales:**
```
1. Seleccionas: tornillo-m8.jpg, tuerca-m10.png, arandela.jpg
2. Dejas todos los campos SKU vacíos
3. Resultado:
   ✓ tornillo-m8.jpg subida
   ✓ tuerca-m10.jpg subida
   ✓ arandela.jpg subida
```

**Ejemplo 2 - Mezclar nombres originales y personalizados:**
```
1. Seleccionas: IMG_001.jpg, IMG_002.jpg, IMG_003.jpg
2. SKUs:
   - IMG_001.jpg → Dejas vacío (se sube como "IMG_001")
   - IMG_002.jpg → Escribes "PROD-ESPECIAL"
   - IMG_003.jpg → Dejas vacío (se sube como "IMG_003")
3. Resultado:
   ✓ IMG_001.jpg subida
   ✓ PROD-ESPECIAL.jpg subida
   ✓ IMG_003.jpg subida
```

**Ejemplo 3 - Todos personalizados:**
```
1. Seleccionas 10 imágenes genéricas
2. Ingresas SKUs: PROD001, PROD002, ..., PROD010
3. Resultado:
   ✓ PROD001.jpg subida
   ✓ PROD002.jpg subida
   ... (todas con nombres personalizados)
```

## 👥 Niveles de Acceso

El sistema de gestión de imágenes está disponible para dos niveles de acceso:

### Pantalla 0 - Administrador
- Acceso total al sistema incluyendo gestión de imágenes
- Puede acceder desde el Panel de Administración

### Pantalla 13 - Gestión de Imágenes (NUEVO)
- Acceso exclusivo a la gestión de imágenes
- Al iniciar sesión, va directamente a la pantalla de subida de imágenes
- Ideal para usuarios que solo necesitan administrar imágenes de productos

### Crear un Usuario con Acceso a Gestión de Imágenes

**Opción 1 - Script PHP (Recomendado):**

1. Accede al script en tu navegador:
   ```
   http://localhost/MACO.AppLogistica.Web-1/crear_usuario_imagenes.php
   ```

2. El script:
   - Crea automáticamente un usuario llamado `imagenes` con contraseña `Imagenes2025!`
   - O actualiza un usuario existente para darle acceso a pantalla 13
   - Muestra todos los usuarios del sistema

3. **¡IMPORTANTE!** Borra el archivo después de usarlo por seguridad:
   ```bash
   del "C:\xampp\htdocs\MACO.AppLogistica.Web-1\crear_usuario_imagenes.php"
   ```

**Opción 2 - SQL Manual:**

1. Hashea la contraseña en PHP:
   ```php
   <?php
   echo password_hash('tu_contraseña', PASSWORD_DEFAULT);
   ?>
   ```

2. Ejecuta en SQL Server:
   ```sql
   INSERT INTO usuarios (usuario, password, pantalla)
   VALUES ('nombre_usuario', 'HASH_GENERADO', 13);
   ```

**Opción 3 - Actualizar Usuario Existente:**

```sql
UPDATE usuarios SET pantalla = 13 WHERE usuario = 'nombre_usuario';
```

## 🔒 Seguridad

El sistema incluye:

- ✅ **Autenticación obligatoria** (solo pantallas 0 y 13)
- ✅ **Tokens CSRF** en todos los formularios
- ✅ **Validación de tipos de archivo** (solo imágenes)
- ✅ **Límite de tamaño** (máx. 5MB)
- ✅ **Sanitización de nombres** de archivo
- ✅ **Logging de operaciones**

## 🎨 Formatos de Imagen Soportados

- JPG/JPEG
- PNG
- GIF
- WEBP

**Tamaño máximo**: 5MB por imagen

## ⚡ Caché

El sistema usa un sistema de caché en archivos para:

- Lista de IDs de imágenes en Azure (TTL: 1 hora)
- Categorías, marcas y otros datos del catálogo (TTL: 1 hora)

### Limpiar la Caché Manualmente

Para forzar la actualización de datos:

```bash
cd C:\xampp\htdocs\MACO.AppLogistica.Web-1
del /Q cache\*.cache
```

O desde PHP:

```php
require_once 'src/cache.php';
clear_cache(); // Limpia toda la caché
clear_cache('blob_item_ids_list'); // Limpia solo las imágenes
```

## 🐛 Solución de Problemas

### Error: "Azure SDK no está instalado"

**Solución**: Ejecuta `composer require microsoft/azure-storage-blob` en la raíz del proyecto.

### Las imágenes no aparecen después de subirlas

**Solución**: Limpia la caché:
```bash
del /Q cache\*.cache
```

### Error al subir: "El archivo es demasiado grande"

**Solución**:
1. Reduce el tamaño de la imagen (máx. 5MB)
2. O aumenta el límite en `Logica/subir_imagen.php`:
   ```php
   $max_size = 10 * 1024 * 1024; // 10MB
   ```

### Error de conexión a Azure

**Verificaciones**:
1. Las credenciales en `src/azure.php` son correctas
2. El contenedor `imagenes-productos` existe en Azure
3. La cuenta de Azure tiene acceso público para lectura de blobs

## 📊 Estadísticas

La página de gestión muestra:

- **Total de imágenes** en Azure
- **Imágenes en la página actual**
- **Total de páginas** de la paginación

## 🔄 Integración con el Catálogo de Productos

Las imágenes se integran automáticamente con el sistema de productos existente:

- El archivo `src/azure.php` contiene la función `fetch_product_data()` que:
  - Ordena productos con imagen primero
  - Muestra productos sin imagen al final
  - Aplica todos los filtros (categoría, marca, búsqueda)

## 📞 Soporte

Para reportar problemas o sugerencias, contacta al equipo de desarrollo de MACO Logística.

---

**Versión**: 1.0
**Fecha**: 2025
**Desarrollado para**: MACO Logística
