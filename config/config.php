<?php
/**
 * Archivo de Configuración Segura
 * Este archivo carga las variables de entorno desde .env
 */

// Cargar variables de entorno desde .env
function cargarVariablesEntorno($ruta_archivo = __DIR__ . '/../.env') {
    if (!file_exists($ruta_archivo)) {
        $error = "ERROR CRÍTICO: Archivo .env no encontrado en: " . $ruta_archivo;
        error_log($error);
        die("<div style='padding:20px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;'><strong>Error de configuración</strong><br>El archivo .env no existe. Ubicación esperada: " . $ruta_archivo . "</div>");
    }

    $lineas = file($ruta_archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        die("<div style='padding:20px;background:#f8d7da;color:#721c24;'>No se pudo leer el archivo .env</div>");
    }

    foreach ($lineas as $linea) {
        // Ignorar comentarios
        if (strpos(trim($linea), '#') === 0) {
            continue;
        }

        // Parsear línea KEY=VALUE
        if (strpos($linea, '=') !== false) {
            list($nombre, $valor) = explode('=', $linea, 2);
            $nombre = trim($nombre);
            $valor = trim($valor);

            // Remover comillas si existen
            $valor = trim($valor, '"\'');

            // Establecer variable de entorno
            if (!array_key_exists($nombre, $_ENV)) {
                $_ENV[$nombre] = $valor;
                putenv("{$nombre}={$valor}");
            }
        }
    }

    // Verificar que las variables críticas existan
    $vars_requeridas = ['DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($vars_requeridas as $var) {
        if (empty($_ENV[$var])) {
            die("<div style='padding:20px;background:#f8d7da;color:#721c24;'>Variable de entorno requerida no encontrada: {$var}</div>");
        }
    }
}

// Cargar variables de entorno
cargarVariablesEntorno();

// Configuración de la base de datos
define('DB_SERVER', getenv('DB_SERVER') ?: $_ENV['DB_SERVER']);
define('DB_NAME', getenv('DB_NAME') ?: $_ENV['DB_NAME']);
define('DB_USER', getenv('DB_USER') ?: $_ENV['DB_USER']);
define('DB_PASS', getenv('DB_PASS') ?: $_ENV['DB_PASS']);

// Configuración de la aplicación
define('APP_ENV', getenv('APP_ENV') ?: $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', (getenv('APP_DEBUG') ?: $_ENV['APP_DEBUG'] ?? 'false') === 'true');

// Timezone
date_default_timezone_set('America/Santo_Domingo');

// Configuración de sesión segura (solo si la sesión NO está activa)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 1800); // 30 minutos

    // Si estamos en HTTPS, asegurar cookies
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
}

// Configuración de errores según el entorno
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}
?>
