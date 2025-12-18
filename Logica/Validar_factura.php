<?php
/**
 * Validar_factura.php - Validación de facturas en despacho
 * Mejorado: CSRF, session_config, rate limiting
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($csrf)) {
        http_response_code(403);
        die(json_encode(['encontrada' => false, 'error' => 'Token CSRF inválido']));
    }
}

header('Content-Type: application/json');
require_once __DIR__ . '/../conexionBD/conexion.php';

$factura = $_POST['factura'] ?? '';
$transportista = $_POST['transportista'] ?? '';
$usuario = $_SESSION['usuario'] ?? 'Desconocido';

if (!$factura || !$transportista) {
    echo json_encode(['encontrada' => false]);
    exit;
}

$query = "SELECT Fecha_scanner FROM custinvoicejour WHERE Factura = ? AND Transportista = ?";
$params = [$factura, $transportista];
$result = sqlsrv_query($conn, $query, $params);

if ($result === false) {
    error_log("Error SQL en Validar_factura.php: " . print_r(sqlsrv_errors(), true));
    echo json_encode(['encontrada' => false, 'error' => 'Error de consulta']);
    exit;
}

if ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $fechaScanner = $row['Fecha_scanner'];

    if (!$fechaScanner) {
        $fechaActual = date('Y-m-d'); 
        $update = "UPDATE custinvoicejour 
                   SET Validar = 'Completada', Fecha_scanner = ?, Usuario = ?
                   WHERE Factura = ? AND Transportista = ?";
        $updateParams = [$fechaActual, $usuario, $factura, $transportista];
        $stmtUpdate = sqlsrv_query($conn, $update, $updateParams);

        if ($stmtUpdate === false) {
            error_log("Error SQL al actualizar en Validar_factura.php: " . print_r(sqlsrv_errors(), true));
        }

        echo json_encode([
            'encontrada' => true,
            'fecha_scanner' => $fechaActual
        ]);
    } else {
        $fechaFormateada = is_object($fechaScanner)
            ? $fechaScanner->format('Y-m-d')
            : $fechaScanner;

        echo json_encode([
            'encontrada' => true,
            'fecha_scanner' => $fechaFormateada
        ]);
    }
} else {
    echo json_encode(['encontrada' => false]);
}

sqlsrv_close($conn);
