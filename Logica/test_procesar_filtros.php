<?php
/**
 * Test wrapper para procesar_filtros_ajax.php
 * Captura errores y los muestra de forma legible
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de procesar_filtros_ajax.php</h2>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Simular parámetros GET
$_GET['desde'] = '2025-12-01';
$_GET['hasta'] = '2025-12-31';
$_GET['factura'] = '';
$_GET['estado'] = '';
$_GET['transportista'] = '';
$_GET['usuario'] = '';
$_GET['zona'] = '';
$_GET['almacen'] = '';
$_GET['prefijo'] = '';
$_GET['filtroCxC'] = '';
$_GET['page'] = '1';

echo "<h3>Parámetros simulados:</h3>";
echo "<pre>" . print_r($_GET, true) . "</pre>";
echo "<hr>";

// Iniciar sesión si no está iniciada
session_start();

// Verificar si hay usuario en sesión
if (!isset($_SESSION['usuario'])) {
    echo "<p style='color:orange;'>⚠️ No hay usuario en sesión. Simulando usuario...</p>";
    // Simular un usuario para testing
    $_SESSION['usuario'] = 'admin';
    $_SESSION['pantalla'] = 0;
}

echo "<p style='color:green;'>✅ Usuario en sesión: " . $_SESSION['usuario'] . "</p>";
echo "<hr>";

// Capturar salida
ob_start();

try {
    // Buffer de errores personalizado
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    echo "<h3>Ejecutando procesar_filtros_ajax.php...</h3>";

    // Incluir el archivo
    include __DIR__ . '/procesar_filtros_ajax.php';

    $output = ob_get_clean();

    echo "<h3 style='color:green;'>✅ Ejecución completada sin errores</h3>";
    echo "<h4>Output JSON:</h4>";
    echo "<pre style='background:#f5f5f5;padding:15px;border-radius:5px;'>" . htmlspecialchars($output) . "</pre>";

    // Intentar decodificar JSON
    $json = json_decode($output, true);
    if ($json !== null) {
        echo "<h4 style='color:green;'>✅ JSON válido</h4>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    } else {
        echo "<h4 style='color:red;'>❌ JSON inválido. Error: " . json_last_error_msg() . "</h4>";
    }

} catch (Exception $e) {
    ob_end_clean();
    echo "<h3 style='color:red;'>❌ ERROR CAPTURADO</h3>";
    echo "<div style='background:#ffe6e6;padding:15px;border-left:4px solid red;border-radius:5px;'>";
    echo "<p><strong>Tipo:</strong> " . get_class($e) . "</p>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<h4>Stack Trace:</h4>";
    echo "<pre style='background:#fff;padding:10px;'>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
} catch (Error $e) {
    ob_end_clean();
    echo "<h3 style='color:red;'>❌ ERROR FATAL CAPTURADO</h3>";
    echo "<div style='background:#ffe6e6;padding:15px;border-left:4px solid red;border-radius:5px;'>";
    echo "<p><strong>Tipo:</strong> " . get_class($e) . "</p>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<h4>Stack Trace:</h4>";
    echo "<pre style='background:#fff;padding:10px;'>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

// Mostrar errores de PHP
$last_error = error_get_last();
if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    echo "<h3 style='color:orange;'>⚠️ Último error PHP:</h3>";
    echo "<pre>" . print_r($last_error, true) . "</pre>";
}

echo "<hr>";
echo "<p><a href='../View/modulos/ReporteFacturas.php'>← Volver al Reporte de Facturas</a></p>";
?>
