<?php
/**
 * Template Optimizado para Endpoints AJAX
 * Úsalo como base para archivos en Logica/
 */

// Cargar bootstrap una sola vez
require_once __DIR__ . '/bootstrap.php';

// Iniciar sesión
iniciarSesionOptimizada();

// Cargar módulos necesarios
AppLoader::loadAuth();
AppLoader::loadCSRF();
AppLoader::loadLogger();

// Verificar autenticación
verificarAutenticacion([], true);

// Verificar método POST (si aplica)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarMetodoPOST();
    validarCSRF(true);
}

// Configurar respuesta JSON
header('Content-Type: application/json; charset=UTF-8');

/**
 * Función helper para respuestas JSON optimizadas
 */
function jsonResponse($success, $data = null, $message = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = ['success' => $success];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Función helper para respuesta de error
 */
function jsonError($message, $httpCode = 400) {
    jsonResponse(false, null, $message, $httpCode);
}

/**
 * Función helper para respuesta exitosa
 */
function jsonSuccess($data = null, $message = null) {
    jsonResponse(true, $data, $message, 200);
}

/**
 * Validación rápida de parámetros requeridos
 */
function requireParams($params, $source = 'POST') {
    $data = $source === 'POST' ? $_POST : $_GET;
    $missing = [];

    foreach ($params as $param) {
        if (!isset($data[$param]) || trim($data[$param]) === '') {
            $missing[] = $param;
        }
    }

    if (!empty($missing)) {
        jsonError('Parámetros requeridos faltantes: ' . implode(', ', $missing), 400);
    }
}

/**
 * Obtiene parámetros sanitizados
 */
function getParams($keys, $source = 'POST', $types = []) {
    $data = $source === 'POST' ? $_POST : $_GET;
    $result = [];

    foreach ($keys as $key) {
        if (isset($data[$key])) {
            $type = $types[$key] ?? 'string';
            $result[$key] = sanitizarRapido($data[$key], $type);
        } else {
            $result[$key] = null;
        }
    }

    return $result;
}
?>
