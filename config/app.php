<?php
/**
 * Configuración de la Aplicación - MACO
 * 
 * Archivo centralizado de configuración del sistema.
 * 
 * @package    MACO
 * @author     MACO Team
 * @version    1.0.0
 */

// Prevenir acceso directo
if (!defined('MACO_BASE_PATH')) {
    define('MACO_BASE_PATH', dirname(__DIR__));
}

return [
    /*
    |--------------------------------------------------------------------------
    | Información de la Aplicación
    |--------------------------------------------------------------------------
    */
    'app' => [
        'name' => 'MACO Logística',
        'version' => '2.0.0',
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => 'America/Santo_Domingo',
        'locale' => 'es_DO',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Sesión
    |--------------------------------------------------------------------------
    */
    'session' => [
        'timeout' => 1800, // 30 minutos
        'regenerate_interval' => 900, // 15 minutos
        'cookie_secure' => true,
        'cookie_httponly' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // file, memory
        'ttl' => 300, // 5 minutos por defecto
        'path' => MACO_BASE_PATH . '/cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'rate_limit' => 100, // Requests por minuto
        'pagination' => [
            'default_per_page' => 20,
            'max_per_page' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Logs
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'path' => MACO_BASE_PATH . '/logs',
        'max_files' => 30, // Días de retención
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Archivos
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'xlsx', 'xls'],
        'path' => MACO_BASE_PATH . '/uploads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Azure Blob Storage
    |--------------------------------------------------------------------------
    */
    'azure' => [
        'account_name' => 'catalogodeimagenes',
        'container_name' => 'imagenes-productos',
        'blob_url' => 'https://catalogodeimagenes.blob.core.windows.net',
        // Nota: La connection_string debe estar en .env por seguridad
        'connection_string' => getenv('AZURE_STORAGE_CONNECTION_STRING') ?: '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles y Permisos
    |--------------------------------------------------------------------------
    | PANTALLAS: Vistas de acceso según rol del usuario (login redirect)
    | MÓDULOS: Funcionalidades del sistema mostradas en Admin.php
    */
    'roles' => [
        // =====================================================================
        // PANTALLAS DE ACCESO POR ROL (donde redirige el login)
        // =====================================================================
        0 => ['name' => 'Administrador', 'page' => 'Admin.php', 'type' => 'pantalla'],
        1 => ['name' => 'Gestión', 'page' => 'Inicio_gestion.php', 'type' => 'pantalla'],
        2 => ['name' => 'Facturas', 'page' => 'facturas.php', 'type' => 'pantalla'],
        3 => ['name' => 'CXC', 'page' => 'CXC.php', 'type' => 'pantalla'],
        5 => ['name' => 'Panel Admin', 'page' => 'Paneladmin.php', 'type' => 'pantalla'],
        6 => ['name' => 'BI', 'page' => 'BI.php', 'type' => 'pantalla'],
        8 => ['name' => 'Etiquetas', 'page' => 'Listo-etiquetas.php', 'type' => 'pantalla'],
        9 => ['name' => 'Dashboard', 'page' => 'dashboard.php', 'type' => 'pantalla'],
        10 => ['name' => 'Inventario', 'page' => 'Listo_inventario.php', 'type' => 'pantalla'],
        11 => ['name' => 'Códigos de Barras', 'page' => 'Codigos_de_barras.php', 'type' => 'pantalla'],
        12 => ['name' => 'Códigos Referencia', 'page' => 'Codigos_referencia.php', 'type' => 'pantalla'],
        13 => ['name' => 'Gestión Imágenes', 'page' => 'Gestion_imagenes.php', 'type' => 'pantalla'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulos del Sistema (mostrados en Admin.php)
    |--------------------------------------------------------------------------
    */
    'modulos' => [
        'despacho_factura' => [
            'name' => 'Despacho de Factura',
            'description' => 'Gestiona envíos y entregas en tiempo real. Control completo de tickets y asignaciones.',
            'page' => 'Despacho_factura.php',
            'icon' => 'fa-truck',
            'tag' => 'OPERATIVO',
            'color' => '#E63946'
        ],
        'validacion_facturas' => [
            'name' => 'Validación de Facturas',
            'description' => 'Valida y procesa facturas escaneadas. Sistema de verificación automática.',
            'page' => 'facturas.php',
            'icon' => 'fa-check-circle',
            'tag' => 'ACTIVO',
            'color' => '#E63946'
        ],
        'recepcion_documentos' => [
            'name' => 'Recepción de Documentos',
            'description' => 'Control de recepción de documentos. Registro y seguimiento completo.',
            'page' => 'facturas-recepcion.php',
            'icon' => 'fa-file-alt',
            'tag' => 'DISPONIBLE',
            'color' => '#E63946'
        ],
        'business_intelligence' => [
            'name' => 'Reporte de Facturas Recibidas',
            'description' => 'Business Intelligence - Análisis avanzado de facturas y operaciones.',
            'page' => 'BI.php',
            'icon' => 'fa-chart-bar',
            'tag' => 'BI',
            'color' => '#E63946'
        ],
        'sistema_etiquetado' => [
            'name' => 'Sistema de Etiquetado',
            'description' => 'Gestión completa de etiquetas. Crea, modifica y elimina etiquetas del sistema.',
            'page' => 'Listo-etiquetas.php',
            'icon' => 'fa-tags',
            'tag' => 'GESTIÓN',
            'color' => '#E63946'
        ],
        'gestion_usuarios' => [
            'name' => 'Gestión de Usuarios',
            'description' => 'Administración completa de usuarios. Crea, modifica permisos y roles.',
            'page' => 'Gestion_de_usuario.php',
            'icon' => 'fa-users-cog',
            'tag' => 'ADMIN',
            'color' => '#E63946'
        ],
        'dashboard_general' => [
            'name' => 'Dashboard General',
            'description' => 'Visión general del sistema. Métricas consolidadas y estadísticas globales.',
            'page' => 'dashboard.php',
            'icon' => 'fa-tachometer-alt',
            'tag' => 'OVERVIEW',
            'color' => '#E63946'
        ],
        'listo_inventario' => [
            'name' => 'Listo de Inventario',
            'description' => 'Inventario de Listo Ferreteria.',
            'page' => 'Listo_inventario.php',
            'icon' => 'fa-boxes',
            'tag' => 'OVERVIEW',
            'color' => '#E63946'
        ],
        'codigos_barras' => [
            'name' => 'Códigos de Barras',
            'description' => 'Escaneo y asignación de códigos de barras a artículos. Control de inventario.',
            'page' => 'Codigos_de_barras.php',
            'icon' => 'fa-barcode',
            'tag' => 'INVENTARIO',
            'color' => '#E63946'
        ],
        'codigos_referencia' => [
            'name' => 'Códigos de Referencia',
            'description' => 'Visualización completa de códigos de barras asignados. Exportación a Excel disponible.',
            'page' => 'Codigos_referencia.php',
            'icon' => 'fa-list-alt',
            'tag' => 'REPORTES',
            'color' => '#E63946'
        ],
        'gestion_imagenes' => [
            'name' => 'Gestión de Imágenes',
            'description' => 'Administra imágenes de productos en Azure Blob Storage. Sube, visualiza y elimina imágenes.',
            'page' => 'Gestion_imagenes.php',
            'icon' => 'fa-images',
            'tag' => 'AZURE',
            'color' => '#E63946'
        ],
        'gestion_transportistas' => [
            'name' => 'Gestión de Transportistas',
            'description' => 'Crear, editar y eliminar transportistas del sistema. Control de datos de conductores.',
            'page' => 'Gestion_transportistas.php',
            'icon' => 'fa-truck',
            'tag' => 'OPERATIVO',
            'color' => '#E63946'
        ],
        'reporte_despacho' => [
            'name' => 'Reporte de Despacho',
            'description' => 'Estadísticas de tiempos de atención y retención de tickets. Análisis por usuario.',
            'page' => 'Reporte_despacho.php',
            'icon' => 'fa-chart-line',
            'tag' => 'REPORTES',
            'color' => '#E63946'
        ],
        'reporte_facturas' => [
            'name' => 'Reporte de Facturas',
            'description' => 'Análisis completo de facturas registradas en el sistema. Dashboard interactivo con filtros avanzados.',
            'page' => 'ReporteFacturas.php',
            'icon' => 'fa-file-invoice-dollar',
            'tag' => 'REPORTES',
            'color' => '#E63946'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados de Facturas
    |--------------------------------------------------------------------------
    */
    'estados' => [
        'PENDIENTE' => ['label' => 'Pendiente', 'color' => '#F6AD55', 'class' => 'badge-warning'],
        'DESPACHADO' => ['label' => 'Despachado', 'color' => '#ED8936', 'class' => 'badge-despachado'],
        'ENTREGADO' => ['label' => 'Entregado', 'color' => '#48BB78', 'class' => 'badge-entregado'],
        'NC' => ['label' => 'Nota de Crédito', 'color' => '#E53E3E', 'class' => 'badge-danger'],
        'REVERSADO' => ['label' => 'Reversado', 'color' => '#A0AEC0', 'class' => 'badge-secondary'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'theme' => 'light', // light, dark, auto
        'primary_color' => '#E63946',
        'accent_color' => '#457B9D',
        'sidebar_width' => '300px',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exportación
    |--------------------------------------------------------------------------
    */
    'export' => [
        'excel' => [
            'max_rows' => 10000,
            'chunk_size' => 1000,
        ],
        'pdf' => [
            'orientation' => 'landscape',
            'paper_size' => 'letter',
        ],
    ],
];
