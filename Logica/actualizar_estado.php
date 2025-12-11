<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($csrf)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Token CSRF inválido']));
    }
}

require_once __DIR__ . '/../conexionBD/conexion.php';

$factura = $_POST['factura'] ?? null;
$nuevoEstado = $_POST['nuevoEstado'] ?? null;

if (!$factura || !$nuevoEstado) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
    exit();
}

$sql = "UPDATE custinvoicejour SET Validar = ? WHERE Factura = ?";
$params = [$nuevoEstado, $factura];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    error_log("Error SQL en actualizar_estado.php: " . print_r(sqlsrv_errors(), true));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
    exit();
}

$rowsAffected = sqlsrv_rows_affected($stmt);
if ($rowsAffected === false) {
    echo json_encode(['success' => false, 'error' => 'No se pudo obtener el número de filas afectadas.']);
    exit();
} elseif ($rowsAffected === 0) {
    echo json_encode(['success' => false, 'error' => 'No se actualizó ninguna fila.']);
    exit();
}

echo json_encode(['success' => true]);
