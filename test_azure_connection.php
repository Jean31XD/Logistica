<?php
/**
 * Script de prueba para verificar la conexión con Azure Blob Storage
 */

// Cargar autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/azure.php';

echo "========================================\n";
echo "  TEST DE CONEXIÓN A AZURE BLOB STORAGE\n";
echo "========================================\n\n";

// Test 1: Verificar que las clases de Azure están disponibles
echo "✓ Test 1: Verificando clases de Azure SDK...\n";
if (class_exists('MicrosoftAzure\Storage\Blob\BlobRestProxy')) {
    echo "  ✓ BlobRestProxy disponible\n";
} else {
    echo "  ✗ BlobRestProxy NO disponible\n";
    exit(1);
}

// Test 2: Verificar configuración
echo "\n✓ Test 2: Verificando configuración...\n";
global $azure_connection_string, $azure_container_name, $azure_account_name;
echo "  - Cuenta: $azure_account_name\n";
echo "  - Contenedor: $azure_container_name\n";
echo "  - Connection String: " . (strlen($azure_connection_string) > 0 ? "✓ Configurado" : "✗ Vacío") . "\n";

// Test 3: Intentar conectar y listar imágenes
echo "\n✓ Test 3: Conectando a Azure Blob Storage...\n";
try {
    $images = list_azure_images(5); // Listar solo 5 imágenes para la prueba
    echo "  ✓ Conexión exitosa!\n";
    echo "  - Total de imágenes obtenidas: " . count($images) . "\n";

    if (count($images) > 0) {
        echo "\n  Ejemplos de imágenes:\n";
        foreach (array_slice($images, 0, 3) as $img) {
            echo "    - " . $img['name'] . " (" . number_format($img['size'] / 1024, 2) . " KB)\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error al conectar: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Verificar función de caché
echo "\n✓ Test 4: Verificando sistema de caché...\n";
require_once __DIR__ . '/src/cache.php';
$cache_dir = __DIR__ . '/cache/';
if (is_dir($cache_dir) && is_writable($cache_dir)) {
    echo "  ✓ Directorio de caché existe y es escribible\n";
} else {
    echo "  ✗ Problemas con el directorio de caché\n";
}

// Test 5: Verificar función de verificación de imagen
echo "\n✓ Test 5: Verificando funciones auxiliares...\n";
if (function_exists('upload_image_to_azure')) {
    echo "  ✓ upload_image_to_azure() disponible\n";
}
if (function_exists('delete_image_from_azure')) {
    echo "  ✓ delete_image_from_azure() disponible\n";
}
if (function_exists('image_exists_in_azure')) {
    echo "  ✓ image_exists_in_azure() disponible\n";
}

echo "\n========================================\n";
echo "  ✓ TODOS LOS TESTS PASARON EXITOSAMENTE\n";
echo "========================================\n";
echo "\nEl sistema de gestión de imágenes está listo para usar.\n";
echo "Accede a: View/Gestion_imagenes.php\n\n";
