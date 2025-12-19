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
 */
function tienePermisoModulo(string $modulo, $conn): bool {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    // Admin (pantalla 0) tiene acceso a todo
    if (($_SESSION['pantalla'] ?? -1) == 0) {
        return true;
    }
    
    $sql = "SELECT 1 FROM modulos_usuarios mu 
            INNER JOIN modulos m ON mu.id_modulo = m.id_modulo 
            WHERE mu.usuario = ? AND m.nombre_modulo = ?";
    $stmt = sqlsrv_query($conn, $sql, [$_SESSION['usuario'], $modulo]);
    
    if ($stmt === false) {
        return false;
    }
    
    $hasPermission = sqlsrv_fetch_array($stmt) !== null;
    sqlsrv_free_stmt($stmt);
    
    return $hasPermission;
}
?>
