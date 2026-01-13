<?php
/**
 * Script de debug para simular la petición AJAX
 */

// Simular los parámetros que envía ReporteFacturas.php
$_GET['desde'] = '2026-01-13';
$_GET['hasta'] = '2026-01-13';
$_GET['factura'] = '';
$_GET['estado'] = '';
$_GET['transportista'] = '';
$_GET['usuario'] = '';
$_GET['zona'] = '';
$_GET['almacen'] = '';
$_GET['prefijo'] = '';
$_GET['filtroCxC'] = '';
$_GET['page'] = '1';

echo "<h2>Debug de procesar_filtros_ajax.php</h2>";
echo "<p>Intentando ejecutar el archivo con los parámetros simulados...</p>";
echo "<hr>";

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir el archivo
try {
    ob_start();
    include __DIR__ . '/../../Logica/procesar_filtros_ajax.php';
    $output = ob_get_clean();

    echo "<h3>✅ Ejecución completada</h3>";
    echo "<h4>Output recibido:</h4>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";

    // Intentar decodificar como JSON
    $json = json_decode($output, true);
    if ($json) {
        echo "<h4>JSON decodificado correctamente:</h4>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    } else {
        echo "<h4>⚠️ No se pudo decodificar como JSON. Error: " . json_last_error_msg() . "</h4>";
    }

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Error capturado:</h3>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Mostrar errores PHP si los hay
$errors = error_get_last();
if ($errors) {
    echo "<h3 style='color:orange;'>⚠️ Último error PHP:</h3>";
    echo "<pre>" . print_r($errors, true) . "</pre>";
}
?>
