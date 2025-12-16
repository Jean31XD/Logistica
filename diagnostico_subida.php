<?php
/**
 * Script de diagnóstico para subida de imágenes
 * Ejecuta: http://localhost/MACO.AppLogistica.Web-1/diagnostico_subida.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico de Subida de Imágenes</h1>";

// Test 1: Verificar sesión
echo "<h2>1. Test de Sesión</h2>";
try {
    session_start();
    echo "✓ Sesión iniciada<br>";
} catch (Exception $e) {
    echo "✗ Error al iniciar sesión: " . $e->getMessage() . "<br>";
}

// Test 2: Verificar archivos requeridos
echo "<h2>2. Archivos Requeridos</h2>";

$archivos = [
    'session_config.php' => __DIR__ . '/conexionBD/session_config.php',
    'conexion.php' => __DIR__ . '/conexionBD/conexion.php',
    'vendor/autoload.php' => __DIR__ . '/vendor/autoload.php',
    'src/azure.php' => __DIR__ . '/src/azure.php',
    'src/cache.php' => __DIR__ . '/src/cache.php'
];

foreach ($archivos as $nombre => $ruta) {
    if (file_exists($ruta)) {
        echo "✓ $nombre existe<br>";
    } else {
        echo "✗ $nombre NO EXISTE: $ruta<br>";
    }
}

// Test 3: Cargar session_config
echo "<h2>3. Cargar session_config.php</h2>";
try {
    require_once __DIR__ . '/conexionBD/session_config.php';
    echo "✓ session_config.php cargado<br>";

    if (function_exists('generarTokenCSRF')) {
        echo "✓ Función generarTokenCSRF() disponible<br>";
    } else {
        echo "✗ Función generarTokenCSRF() NO disponible<br>";
    }

    if (function_exists('validarTokenCSRF')) {
        echo "✓ Función validarTokenCSRF() disponible<br>";
    } else {
        echo "✗ Función validarTokenCSRF() NO disponible<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 4: Cargar Azure SDK
echo "<h2>4. Cargar Azure SDK</h2>";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✓ Autoloader de Composer cargado<br>";

    if (class_exists('MicrosoftAzure\Storage\Blob\BlobRestProxy')) {
        echo "✓ Clase BlobRestProxy disponible<br>";
    } else {
        echo "✗ Clase BlobRestProxy NO disponible<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 5: Cargar src/azure.php
echo "<h2>5. Cargar src/azure.php</h2>";
try {
    require_once __DIR__ . '/src/azure.php';
    echo "✓ src/azure.php cargado<br>";

    if (function_exists('upload_image_to_azure')) {
        echo "✓ Función upload_image_to_azure() disponible<br>";
    } else {
        echo "✗ Función upload_image_to_azure() NO disponible<br>";
    }
} catch (Exception $e) {
    echo "✗ Error al cargar src/azure.php: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 6: Verificar extensiones PHP
echo "<h2>6. Extensiones PHP</h2>";

$extensiones = ['fileinfo', 'curl', 'openssl', 'mbstring'];
foreach ($extensiones as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext cargada<br>";
    } else {
        echo "✗ $ext NO cargada<br>";
    }
}

// Test 7: Verificar permisos de directorio cache
echo "<h2>7. Permisos de Directorio</h2>";

$cache_dir = __DIR__ . '/cache';
if (is_dir($cache_dir)) {
    echo "✓ Directorio cache existe<br>";
    if (is_writable($cache_dir)) {
        echo "✓ Directorio cache es escribible<br>";
    } else {
        echo "✗ Directorio cache NO es escribible<br>";
    }
} else {
    echo "✗ Directorio cache NO existe<br>";
}

// Test 8: Verificar límites de PHP
echo "<h2>8. Configuración PHP</h2>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Test 9: Test simple de subida
echo "<h2>9. Test de Funciones Básicas</h2>";

// Simular datos de subida
if (function_exists('upload_image_to_azure')) {
    echo "✓ Función upload_image_to_azure disponible para testing<br>";
    echo "<small>Nota: No se ejecutará subida real sin archivo</small><br>";
} else {
    echo "✗ No se puede probar upload_image_to_azure<br>";
}

echo "<hr>";
echo "<h2>✅ Diagnóstico Completo</h2>";
echo "<p>Si todos los tests anteriores pasan con ✓, el sistema debería funcionar.</p>";
echo "<p>Si hay errores ✗, revisa los mensajes específicos arriba.</p>";
echo "<hr>";
echo "<p><strong>Siguiente paso:</strong> Intenta subir una imagen desde la interfaz</p>";
echo "<p><a href='View/Gestion_imagenes.php'>Ir a Gestión de Imágenes</a></p>";
?>
