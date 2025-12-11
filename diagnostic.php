<?php
/**
 * Diagnóstico de Azure App Service
 * ELIMINAR ESTE ARCHIVO DESPUÉS DE VERIFICAR
 */

// Evitar acceso no autorizado
$allowedIPs = ['127.0.0.1', '::1']; // Solo localhost en desarrollo
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs) && php_sapi_name() !== 'cli') {
    // En producción, comentar esta línea para permitir acceso temporal
    // die('Acceso denegado');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE AZURE APP SERVICE ===\n\n";

echo "1. INFORMACIÓN DEL SERVIDOR:\n";
echo "   - Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "   - HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "   - Remote Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";
echo "   - PHP SAPI: " . php_sapi_name() . "\n\n";

echo "2. DETECCIÓN DE HTTPS:\n";
echo "   - \$_SERVER['HTTPS']: " . ($_SERVER['HTTPS'] ?? 'NO CONFIGURADO') . "\n";
echo "   - \$_SERVER['HTTP_X_FORWARDED_PROTO']: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NO CONFIGURADO') . "\n";
echo "   - \$_SERVER['HTTP_X_FORWARDED_SSL']: " . ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'NO CONFIGURADO') . "\n";
echo "   - \$_SERVER['REQUEST_SCHEME']: " . ($_SERVER['REQUEST_SCHEME'] ?? 'NO CONFIGURADO') . "\n\n";

$isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

echo "   ✓ HTTPS DETECTADO: " . ($isHTTPS ? 'SÍ' : 'NO') . "\n\n";

echo "3. CONFIGURACIÓN PHP:\n";
echo "   - Version: " . PHP_VERSION . "\n";
echo "   - Extensions SQL Server: " . (extension_loaded('sqlsrv') ? 'Instalada' : 'NO INSTALADA') . "\n";
echo "   - Extension PDO SQL Server: " . (extension_loaded('pdo_sqlsrv') ? 'Instalada' : 'NO INSTALADA') . "\n";
echo "   - Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "   - Memory Limit: " . ini_get('memory_limit') . "\n\n";

echo "4. VARIABLES DE ENTORNO:\n";
echo "   - DB_SERVER: " . (getenv('DB_SERVER') ? 'CONFIGURADO' : 'NO CONFIGURADO') . "\n";
echo "   - DB_NAME: " . (getenv('DB_NAME') ? 'CONFIGURADO' : 'NO CONFIGURADO') . "\n";
echo "   - DB_USERNAME: " . (getenv('DB_USERNAME') ? 'CONFIGURADO' : 'NO CONFIGURADO') . "\n";
echo "   - DB_PASSWORD: " . (getenv('DB_PASSWORD') ? 'CONFIGURADO (oculto)' : 'NO CONFIGURADO') . "\n";
echo "   - SE_FUE_CODE: " . (getenv('SE_FUE_CODE') ? 'CONFIGURADO' : 'NO CONFIGURADO') . "\n\n";

echo "5. ARCHIVOS CRÍTICOS:\n";
$files = [
    'index.php',
    'conexionBD/conexion.php',
    'conexionBD/session_config.php',
    'conexionBD/rate_limiter.php',
    'conexionBD/log_manager.php',
    '.env'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "   - $file: " . (file_exists($path) ? '✓ Existe' : '✗ NO EXISTE') . "\n";
}

echo "\n6. DIRECTORIO DE LOGS:\n";
$logDir = __DIR__ . '/logs';
echo "   - Directorio: " . ($logDir) . "\n";
echo "   - Existe: " . (is_dir($logDir) ? 'SÍ' : 'NO') . "\n";
echo "   - Escribible: " . (is_writable($logDir) ? 'SÍ' : 'NO (¡CREAR DIRECTORIO!)') . "\n\n";

echo "7. PRUEBA DE CONEXIÓN A BASE DE DATOS:\n";
try {
    require_once __DIR__ . '/conexionBD/conexion.php';
    if ($conn) {
        echo "   ✓ CONEXIÓN EXITOSA\n";
        echo "   - Base de datos: " . getenv('DB_NAME') . "\n";
        echo "   - Servidor: " . getenv('DB_SERVER') . "\n";
        sqlsrv_close($conn);
    } else {
        echo "   ✗ ERROR DE CONEXIÓN\n";
    }
} catch (Exception $e) {
    echo "   ✗ EXCEPCIÓN: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
echo "\n⚠️  ELIMINAR ESTE ARCHIVO (diagnostic.php) DESPUÉS DE VERIFICAR\n";
?>
