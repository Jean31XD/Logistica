<?php
/**
 * Configuración centralizada de sesiones - MACO
 * Usar este archivo en TODOS los módulos
 */

// Cargar helpers centralizados
require_once __DIR__ . '/helpers.php';

// Forzar HTTPS en producción (solo si no estamos en localhost)
// Azure App Service usa X-Forwarded-Proto para indicar el protocolo original
$isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

if (!$isHTTPS) {
    $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);

    // Solo redirigir si NO estamos en localhost y NO estamos ya en HTTPS
    if (!$isLocalhost && php_sapi_name() !== 'cli') {
        $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirectUrl, true, 301);
        exit();
    }
}

// Configuración de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    // Configuración de cookies de sesión
    ini_set('session.cookie_httponly', 1);      // Prevenir acceso JavaScript
    ini_set('session.cookie_samesite', 'Lax');  // Protección CSRF (Lax permite navegación normal)
    ini_set('session.use_strict_mode', 1);      // Rechazar IDs de sesión no inicializados
    ini_set('session.use_only_cookies', 1);     // Solo cookies, no URLs

    // Flag Secure solo en HTTPS (detecta Azure proxy)
    $cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    ini_set('session.cookie_secure', $cookieSecure ? 1 : 0);

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
        header("Location: " . getLoginUrl());
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

// Content Security Policy (CSP)
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.gstatic.com; ";
$csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
$csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; ";
$csp .= "img-src 'self' data: https://*.blob.core.windows.net https://catalogodeimagenes.blob.core.windows.net; ";
$csp .= "connect-src 'self'; ";
$csp .= "frame-ancestors 'none'; ";
$csp .= "base-uri 'self'; ";
$csp .= "form-action 'self';";
header("Content-Security-Policy: " . $csp);

// Permissions Policy (Feature Policy)
$permissions = "geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()";
header("Permissions-Policy: " . $permissions);

// Prevenir cache de páginas autenticadas
header("Cache-Control: no-cache, no-store, must-revalidate, private");
header("Pragma: no-cache");
header("Expires: 0");

// Función helper para verificar autenticación
function verificarAutenticacion($pantallasPermitidas = []) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: " . getLoginUrl());
        exit();
    }

    // Si se especifican pantallas permitidas, verificar permisos
    if (!empty($pantallasPermitidas)) {
        // Convertir pantalla de sesión a int para comparación robusta
        $pantallaUsuario = intval($_SESSION['pantalla'] ?? -1);
        // Convertir array a ints también
        $pantallasPermitidas = array_map('intval', $pantallasPermitidas);
        
        if (!in_array($pantallaUsuario, $pantallasPermitidas, false)) {
            header("Location: " . getLoginUrl());
            exit();
        }
    }
}

// Función para regenerar ID de sesión al cambiar privilegios
function regenerarSesionPorCambioPrivilegios($nuevoPrivilegio = null) {
    // Regenerar ID de sesión
    session_regenerate_id(true);

    // Actualizar timestamp de regeneración
    $_SESSION['ultimo_regenerate'] = time();

    // Si se proporciona nuevo privilegio, actualizarlo
    if ($nuevoPrivilegio !== null) {
        $_SESSION['pantalla'] = $nuevoPrivilegio;
    }

    // Log del cambio
    if (file_exists(__DIR__ . '/log_manager.php')) {
        require_once __DIR__ . '/log_manager.php';
        logWithRotation("Sesión regenerada por cambio de privilegios - Usuario: {$_SESSION['usuario']}", 'INFO', 'SECURITY');
    }
}

// Función helper para generar token CSRF
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función helper para validar token CSRF (retorna boolean, no termina ejecución)
function validarTokenCSRF($token) {
    // Si el token está vacío o no coincide, retornar false
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Función helper para validar Content-Type en requests POST
function validarContentType($allowedTypes = ['application/x-www-form-urlencoded', 'multipart/form-data', 'application/json']) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Solo validar POST requests
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Extraer el tipo base (sin charset u otros parámetros)
    $contentType = strtolower(trim(explode(';', $contentType)[0]));

    // Permitir requests sin Content-Type explícito (PHP los maneja como form-urlencoded)
    if (empty($contentType)) {
        return true;
    }

    foreach ($allowedTypes as $allowedType) {
        if ($contentType === strtolower($allowedType)) {
            return true;
        }
    }

    http_response_code(415); // Unsupported Media Type
    header('Content-Type: application/json');
    die(json_encode([
        'error' => 'Content-Type no soportado',
        'allowed' => $allowedTypes
    ]));
}
?>