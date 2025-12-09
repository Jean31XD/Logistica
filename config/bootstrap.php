<?php
/**
 * Bootstrap Optimizado - Carga Única de Configuración
 * Este archivo centraliza toda la inicialización para evitar requires múltiples
 */

// Evitar carga múltiple
if (defined('APP_BOOTSTRAP_LOADED')) {
    return;
}
define('APP_BOOTSTRAP_LOADED', true);

// Iniciar buffer de salida para compresión (desactivado temporalmente para debugging)
// if (!ob_get_level() && PHP_SAPI !== 'cli') {
//     ob_start();
// }

// Cargar configuración base (una sola vez)
require_once __DIR__ . '/config.php';

// Aplicar headers de seguridad
require_once __DIR__ . '/security_headers.php';

/**
 * Función optimizada para iniciar sesión con configuración correcta
 */
function iniciarSesionOptimizada() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Carga lazy (bajo demanda) de módulos
 */
class AppLoader {
    private static $loaded = [];

    public static function loadAuth() {
        if (!isset(self::$loaded['auth'])) {
            require_once __DIR__ . '/auth_middleware.php';
            self::$loaded['auth'] = true;
        }
    }

    public static function loadCSRF() {
        if (!isset(self::$loaded['csrf'])) {
            require_once __DIR__ . '/csrf_helper.php';
            self::$loaded['csrf'] = true;
        }
    }

    public static function loadLogger() {
        if (!isset(self::$loaded['logger'])) {
            require_once __DIR__ . '/security_logger.php';
            self::$loaded['logger'] = true;
        }
    }

    public static function loadDB() {
        if (!isset(self::$loaded['db'])) {
            require_once __DIR__ . '/../conexionBD/conexion.php';
            self::$loaded['db'] = true;
        }
        return $GLOBALS['conn'] ?? null;
    }
}

/**
 * Funciones de utilidad optimizadas
 */

// Cache de variables de entorno en memoria
$GLOBALS['env_cache'] = [];

function getEnvCached($key, $default = null) {
    if (!isset($GLOBALS['env_cache'][$key])) {
        $GLOBALS['env_cache'][$key] = getenv($key) ?: ($_ENV[$key] ?? $default);
    }
    return $GLOBALS['env_cache'][$key];
}

// Sanitización optimizada con cache de patrones
function sanitizarRapido($valor, $tipo = 'string') {
    if ($valor === null) return null;

    switch($tipo) {
        case 'int':
            return filter_var($valor, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($valor, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($valor, FILTER_VALIDATE_EMAIL);
        case 'string':
        default:
            return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
    }
}

// Preparar statement con cache
$GLOBALS['stmt_cache'] = [];

function prepararQueryOptimizada($conn, $sql, $params = []) {
    $cache_key = md5($sql);

    // Ejecutar directamente (SQL Server no soporta prepared statements reutilizables del mismo modo que MySQL)
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        if (APP_DEBUG) {
            error_log("Error en query: " . print_r(sqlsrv_errors(), true));
        }
        return false;
    }

    return $stmt;
}

/**
 * Sistema de cache simple en memoria para esta petición
 */
class RequestCache {
    private static $cache = [];

    public static function set($key, $value, $ttl = 60) {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }

    public static function get($key, $default = null) {
        if (isset(self::$cache[$key])) {
            if (time() < self::$cache[$key]['expires']) {
                return self::$cache[$key]['value'];
            }
            unset(self::$cache[$key]);
        }
        return $default;
    }

    public static function has($key) {
        return isset(self::$cache[$key]) && time() < self::$cache[$key]['expires'];
    }
}

/**
 * Optimización de salida: comprimir HTML/JSON
 */
function finalizarRespuesta() {
    if (ob_get_level()) {
        $output = ob_get_clean();

        // Comprimir con gzip si el cliente lo soporta Y no hay headers ya enviados
        if (!headers_sent() &&
            isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
            strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false &&
            function_exists('gzencode') &&
            // Solo comprimir respuestas grandes (> 1KB)
            strlen($output) > 1024) {

            header('Content-Encoding: gzip');
            echo gzencode($output, 6); // Nivel 6 de compresión (balance velocidad/tamaño)
        } else {
            echo $output;
        }
    }
}

// Registrar función de finalización
register_shutdown_function('finalizarRespuesta');
?>
