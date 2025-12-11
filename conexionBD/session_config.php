<?php
/**
 * Configuración centralizada de sesiones - MACO
 * Usar este archivo en TODOS los módulos
 */

// Configuración de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    // Configuración de cookies de sesión
    ini_set('session.cookie_httponly', 1);      // Prevenir acceso JavaScript
    ini_set('session.cookie_samesite', 'Lax');  // Protección CSRF (Lax permite navegación normal)
    ini_set('session.use_strict_mode', 1);      // Rechazar IDs de sesión no inicializados
    ini_set('session.use_only_cookies', 1);     // Solo cookies, no URLs
    
    // Tiempo de vida de sesión: 30 minutos (1800 segundos)
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 0);      // Cookie expira al cerrar navegador
    
    // Iniciar sesión
    session_start();
}

// Zona horaria
date_default_timezone_set('America/Santo_Domingo');

// Timeout de inactividad: 30 minutos (mejorado desde 200 segundos)
$inactividadLimite = 1800; // 30 minutos

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempoInactivo = time() - $_SESSION['ultimo_acceso'];
    
    if ($tiempoInactivo > $inactividadLimite) {
        // Sesión expirada por inactividad
        session_unset();
        session_destroy();
        
        // Redirigir a login
        header("Location: " . ($_SERVER['REQUEST_SCHEME'] ?? 'http') . "://" . $_SERVER['HTTP_HOST'] . "/MACO.AppLogistica.Web-1/index.php");
        exit();
    }
}

// Actualizar timestamp de último acceso
$_SESSION['ultimo_acceso'] = time();

// Regenerar ID de sesión cada 15 minutos (NO en cada carga)
if (!isset($_SESSION['ultimo_regenerate'])) {
    $_SESSION['ultimo_regenerate'] = time();
} elseif (time() - $_SESSION['ultimo_regenerate'] > 900) { // 15 minutos
    session_regenerate_id(true);
    $_SESSION['ultimo_regenerate'] = time();
}

// Encabezados de seguridad HTTP
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Prevenir cache de páginas autenticadas
header("Cache-Control: no-cache, no-store, must-revalidate, private");
header("Pragma: no-cache");
header("Expires: 0");

// Función helper para verificar autenticación
function verificarAutenticacion($pantallasPermitidas = []) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: " . ($_SERVER['REQUEST_SCHEME'] ?? 'http') . "://" . $_SERVER['HTTP_HOST'] . "/MACO.AppLogistica.Web-1/index.php");
        exit();
    }
    
    // Si se especifican pantallas permitidas, verificar permisos
    if (!empty($pantallasPermitidas) && !in_array($_SESSION['pantalla'], $pantallasPermitidas)) {
        header("Location: " . ($_SERVER['REQUEST_SCHEME'] ?? 'http') . "://" . $_SERVER['HTTP_HOST'] . "/MACO.AppLogistica.Web-1/index.php");
        exit();
    }
}

// Función helper para generar token CSRF
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función helper para validar token CSRF
function validarTokenCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Token CSRF inválido');
    }
    return true;
}
?>