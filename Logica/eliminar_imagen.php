<?php
/**
 * Lógica para eliminar imágenes de Azure Blob Storage
 * MACO Logística
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!isset($_SESSION['usuario']) || !tieneModulo('gestion_imagenes', $conn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Sin permisos']));
}

// Validar CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($csrf)) {
        http_response_code(403);
        die(json_encode(['error' => 'Token CSRF inválido']));
    }
}

// Cargar autoloader de Composer para Azure SDK
$composer_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} else {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Error: Azure SDK no está instalado. Por favor ejecute "composer require microsoft/azure-storage-blob"'
    ]));
}

require_once __DIR__ . '/../src/azure.php';

// Validar que se recibió el nombre del blob
$blob_name = trim($_POST['blob_name'] ?? '');
if (empty($blob_name)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'El nombre de la imagen es obligatorio'
    ]));
}

// Validar que la imagen existe antes de intentar eliminarla
if (!image_exists_in_azure($blob_name)) {
    http_response_code(404);
    die(json_encode([
        'success' => false,
        'message' => 'La imagen no existe en Azure'
    ]));
}

// Eliminar la imagen de Azure
$result = delete_image_from_azure($blob_name);

// Registrar la acción en el log
if ($result['success']) {
    error_log("Imagen eliminada exitosamente: $blob_name por usuario " . ($_SESSION['usuario'] ?? 'desconocido'));
} else {
    error_log("Error al eliminar imagen: $blob_name - " . $result['message']);
}

// Retornar respuesta JSON
header('Content-Type: application/json');
http_response_code($result['success'] ? 200 : 500);
echo json_encode($result);
