# MACO Logística - Sistema de Gestión

Sistema de gestión logística para control de facturas, despachos y entregas.

## 🚀 Tecnologías

- **Backend:** PHP 7.4+
- **Base de Datos:** SQL Server (Azure)
- **Frontend:** HTML5, CSS3, JavaScript (vanilla)
- **Dependencias:** Composer, Azure Blob Storage SDK

## 📁 Estructura del Proyecto

```
MACO.AppLogistica.Web-1/
├── conexionBD/              # Configuración de conexión y sesiones
│   ├── conexion.php         # Conexión a base de datos
│   ├── session_config.php   # Configuración de sesiones seguras
│   └── log_manager.php      # Sistema de logging
├── Logica/                  # APIs y lógica de negocio
│   ├── api_get_data.php     # API principal de datos
│   └── *.php                # Endpoints específicos
├── View/                    # Vistas y frontend
│   ├── assets/              # Recursos estáticos
│   │   ├── css/             # Hojas de estilo
│   │   └── js/              # Scripts JavaScript
│   ├── components/          # Componentes PHP reutilizables
│   │   ├── card.php         # Componente de tarjetas
│   │   ├── table.php        # Componente de tablas
│   │   ├── modal.php        # Componente de modales
│   │   └── alert.php        # Componente de alertas
│   └── templates/           # Templates base (header, footer)
├── src/                     # Código fuente organizado
│   ├── Controllers/         # Controladores base
│   ├── Models/              # Modelos de datos
│   ├── Services/            # Servicios (cache, etc.)
│   └── Helpers/             # Funciones de utilidad
├── database/                # Scripts SQL de mantenimiento
├── logs/                    # Logs de aplicación
└── cache/                   # Cache de datos
```

## ⚙️ Instalación

### Requisitos
- PHP 7.4 o superior
- Extensión sqlsrv para PHP
- Composer
- XAMPP o servidor web compatible

### Pasos

1. **Clonar el repositorio:**
   ```bash
   git clone <repository-url>
   cd MACO.AppLogistica.Web-1
   ```

2. **Instalar dependencias:**
   ```bash
   composer install
   ```

3. **Configurar variables de entorno:**
   Crear archivo `.env` en la raíz:
   ```env
   DB_SERVER=servidor.database.windows.net
   DB_NAME=nombre_base_datos
   DB_USERNAME=usuario
   DB_PASSWORD=contraseña
   AZURE_BLOB_CONNECTION=connection_string
   AZURE_BLOB_CONTAINER=nombre_contenedor
   ```

4. **Configurar servidor web:**
   - Apuntar DocumentRoot a la carpeta del proyecto
   - Habilitar módulo rewrite de Apache

5. **Acceder a la aplicación:**
   ```
   http://localhost/MACO.AppLogistica.Web-1/
   ```

## 🔑 Roles de Usuario

| Pantalla | Rol | Acceso |
|----------|-----|--------|
| 0 | Administrador | Acceso total |
| 1 | Gestión | Despacho de facturas |
| 2 | Facturas | Validación |
| 9 | Dashboard | Panel de métricas |

## 📊 Módulos Principales

- **Dashboard:** Métricas y KPIs de facturación
- **Despacho:** Gestión de envíos y asignaciones
- **Validación:** Verificación de facturas
- **Reportes:** Análisis por transportista
- **BI:** Business Intelligence y CXC

## 🛠️ Desarrollo

### Componentes Reutilizables

Los componentes en `View/components/` facilitan la creación de interfaces:

```php
// Ejemplo: Renderizar una tarjeta
require_once 'components/card.php';
echo renderCard([
    'title' => 'Total Facturas',
    'value' => 150,
    'icon' => 'fa-file-invoice',
    'color' => '#E63946'
]);

// Ejemplo: Renderizar una alerta
require_once 'components/alert.php';
echo alertSuccess('Operación completada');
```

### Cache

El servicio de cache mejora el rendimiento:

```php
require_once 'src/Services/CacheService.php';
$cache = new \MACO\Services\CacheService();

// Obtener o calcular valor
$data = $cache->remember('key', function() {
    return heavyQuery();
}, 300); // TTL: 5 minutos
```

## 🔒 Seguridad

- Protección CSRF en formularios
- Headers de seguridad HTTP (CSP, X-Frame-Options)
- Sesiones seguras con regeneración automática
- Rate limiting en login
- Variables de entorno para credenciales

## 📝 Mantenimiento

### Limpiar cache
```bash
php -r "require 'src/Services/CacheService.php'; (new \MACO\Services\CacheService())->clear();"
```

### Ejecutar mantenimiento de BD
```bash
cd database
.\ejecutar_mantenimiento.bat
```

## 📄 Licencia

Propiedad de MACO. Todos los derechos reservados.