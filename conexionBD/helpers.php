<?php
/**
 * Helpers Centralizados - MACO Logística
 * Funciones de utilidad para todo el proyecto
 */

/**
 * Detecta si estamos en Azure o localhost
 */
function isAzureEnvironment(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return strpos($host, 'azurewebsites.net') !== false;
}

/**
 * Obtiene la ruta base del proyecto
 * En Azure: '' (vacío)
 * En Local: '/MACO.AppLogistica.Web-1'
 */
function getBasePath(): string {
    return isAzureEnvironment() ? '' : '/MACO.AppLogistica.Web-1';
}

/**
 * Construye URL completa con protocolo y host
 */
function getBaseUrl(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             || isAzureEnvironment();
    
    $protocol = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    return $protocol . '://' . $host . getBasePath();
}

/**
 * URL para redirección OAuth
 */
function getOAuthRedirectUri(string $path): string {
    return getBaseUrl() . $path;
}

/**
 * URL del index/login
 */
function getLoginUrl(): string {
    return getBaseUrl() . '/index.php';
}

/**
 * Ruta base para APIs
 */
function getApiPath(): string {
    return getBasePath() . '/Logica';
}

/**
 * Escapa HTML de forma segura
 */
function escape($value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formatea fecha para mostrar
 */
function formatDate($date, string $format = 'd/m/Y'): string {
    if ($date instanceof DateTime) {
        return $date->format($format);
    }
    if (is_string($date) && !empty($date)) {
        return date($format, strtotime($date));
    }
    return '';
}

/**
 * Verifica si el usuario tiene permiso para un módulo
 * Usa la tabla usuario_modulos exclusivamente
 * 
 * @param string $modulo Nombre del módulo (ej: 'gestion_imagenes', 'validacion_facturas')
 * @param resource $conn Conexión a la base de datos (opcional, se carga si no se pasa)
 * @return bool
 */
function tieneModulo(string $modulo, $conn = null): bool {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    // Si no se pasa conexión, cargarla
    if ($conn === null) {
        require_once __DIR__ . '/conexion.php';
    }
    
    $sql = "SELECT COUNT(*) as cnt FROM usuario_modulos 
            WHERE usuario = ? AND modulo = ? AND activo = 1";
    $stmt = sqlsrv_query($conn, $sql, [$_SESSION['usuario'], $modulo]);
    
    if ($stmt === false) {
        return false;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $hasPermission = ($row['cnt'] > 0);
    sqlsrv_free_stmt($stmt);
    
    return $hasPermission;
}

/**
 * Verifica si el usuario tiene permiso para un módulo (alias para compatibilidad)
 * @deprecated Usar tieneModulo() directamente
 */
function tienePermisoModulo(string $modulo, $conn): bool {
    return tieneModulo($modulo, $conn);
}
?>
