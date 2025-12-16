<?php
/**
 * Test simple de subida - para debugging
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Subida de Imágenes</h1>";

// Test 1: Verificar que el script carga
echo "<h2>1. Script PHP cargado</h2>";
echo "✓ PHP funcionando<br>";
echo "Versión PHP: " . phpversion() . "<br>";

// Test 2: Verificar archivos requeridos
echo "<h2>2. Archivos Requeridos</h2>";

$archivos = [
    'session_config.php' => __DIR__ . '/conexionBD/session_config.php',
    'vendor/autoload.php' => __DIR__ . '/vendor/autoload.php',
    'src/azure.php' => __DIR__ . '/src/azure.php'
];

foreach ($archivos as $nombre => $ruta) {
    if (file_exists($ruta)) {
        echo "✓ $nombre<br>";
    } else {
        echo "✗ $nombre NO EXISTE<br>";
    }
}

// Test 3: Cargar dependencias
echo "<h2>3. Cargar Dependencias</h2>";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✓ Autoloader cargado<br>";

    require_once __DIR__ . '/src/azure.php';
    echo "✓ src/azure.php cargado<br>";

    if (function_exists('upload_image_to_azure')) {
        echo "✓ Función upload_image_to_azure() disponible<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Simular respuesta JSON
echo "<h2>4. Test de Respuesta JSON</h2>";

function testRespuesta() {
    ob_start();
    header('Content-Type: application/json');

    $response = [
        'success' => true,
        'message' => 'Test exitoso',
        'timestamp' => time()
    ];

    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    echo $json;

    $output = ob_get_clean();
    return $output;
}

$respuesta = testRespuesta();
echo "Respuesta generada:<br>";
echo "<pre>" . htmlspecialchars($respuesta) . "</pre>";

// Test 5: Verificar que la función enviarRespuesta funciona
echo "<h2>5. Test de Función enviarRespuesta</h2>";

// Simular la función
function enviarRespuestaTest($success, $message) {
    ob_start();

    $response = [
        'success' => $success,
        'message' => $message
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ob_get_clean();
}

$test1 = enviarRespuestaTest(true, 'Mensaje de éxito');
$test2 = enviarRespuestaTest(false, 'Mensaje de error');

echo "Test éxito:<br><pre>" . htmlspecialchars($test1) . "</pre>";
echo "Test error:<br><pre>" . htmlspecialchars($test2) . "</pre>";

// Test 6: Verificar manejo de errores
echo "<h2>6. Test de Manejo de Errores</h2>";

set_error_handler(function($errno, $errstr) {
    echo "✓ Error capturado: $errstr<br>";
    return true;
});

// Generar warning intencional
@trigger_error("Test de error", E_USER_WARNING);

restore_error_handler();

echo "<hr>";
echo "<h2>✅ Todos los Tests Completados</h2>";
echo "<p>Si ves este mensaje, el sistema básico está funcionando.</p>";
echo "<p><a href='View/Gestion_imagenes.php'>Ir a Gestión de Imágenes</a></p>";
?>
