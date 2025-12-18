<?php
/**
 * Endpoint para obtener un token CSRF fresco.
 * Devuelve el token actual de la sesión en formato JSON.
 */
require_once __DIR__ . '/../conexionBD/session_config.php';

// Asegurarse de que el usuario está autenticado para obtener un token
verificarAutenticacion();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Genera o recupera el token de la sesión actual y lo devuelve
echo json_encode([
    'success' => true,
    'csrf_token' => generarTokenCSRF()
]);
?>
