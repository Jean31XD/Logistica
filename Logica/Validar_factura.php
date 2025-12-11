<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar Content-Type
validarContentType(['application/x-www-form-urlencoded', 'application/json']);

// Rate limiting: máximo 20 validaciones por minuto
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('validar_factura', 20, 60)) {
    rateLimitExceeded('Demasiadas validaciones. Espere un momento.');
}

// Validar CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    validarTokenCSRF($csrf);
}

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/conexion.php';
require_once __DIR__ . '/../conexionBD/log_manager.php';

// Validar parámetros requeridos
$factura = trim($_POST['factura'] ?? '');
$transportista = trim($_POST['transportista'] ?? '');
$usuario = $_SESSION['usuario'];
logWithRotation("Intento de validación de factura: $factura, transportista: $transportista", 'INFO', 'VALIDACION');
logWithRotation("Intento de validación de factura: $factura, transportista: $transportista", 'INFO', 'VALIDACION');

if (empty($factura) || empty($transportista)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'encontrada' => false,
        'error' => 'Factura y transportista son requeridos'
    ]);
    exit();
}

// Validar longitud de factura
if (strlen($factura) !== 11) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'encontrada' => false,
        'error' => 'El número de factura debe tener exactamente 11 dígitos'
    ]);
    exit();
}

// Consultar factura
$query = "SELECT Fecha_scanner, Validar FROM custinvoicejour WHERE Factura = ? AND Transportista = ?";
$params = [$factura, $transportista];
$result = sqlsrv_query($conn, $query, $params);

if ($result === false) {
    http_response_code(500);
    error_log("Error en Validar_factura.php: " . print_r(sqlsrv_errors(), true));
    echo json_encode([
        'success' => false,
        'encontrada' => false,
        'error' => 'Error al consultar la base de datos'
    ]);
    sqlsrv_close($conn);
    exit();
}

$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

if (!$row) {
    logWithRotation("Factura no encontrada: $factura, transportista: $transportista", 'WARNING', 'VALIDACION');
    echo json_encode([
        'success' => false,
        'encontrada' => false,
        'error' => 'Factura no encontrada para el transportista especificado'
    ]);
    sqlsrv_close($conn);
    exit();
}

$fechaScanner = $row['Fecha_scanner'];
$estadoValidar = trim($row['Validar'] ?? '');

// Si ya está completada, no permitir reupdates
if (strtolower($estadoValidar) === 'completada' && $fechaScanner) {
    $fechaFormateada = is_object($fechaScanner) ? $fechaScanner->format('Y-m-d') : $fechaScanner;
    echo json_encode([
        'success' => true,
        'encontrada' => true,
        'yaCompletada' => true,
        'fecha_scanner' => $fechaFormateada,
        'mensaje' => 'Esta factura ya fue validada anteriormente'
    ]);
    sqlsrv_close($conn);
    exit();
}

// Si no tiene fecha scanner, actualizar
if (!$fechaScanner) {
    $fechaActual = date('Y-m-d');
    $update = "UPDATE custinvoicejour
               SET Validar = 'Completada', Fecha_scanner = ?, Usuario = ?
               WHERE Factura = ? AND Transportista = ?";
    $updateParams = [$fechaActual, $usuario, $factura, $transportista];
    $updateResult = sqlsrv_query($conn, $update, $updateParams);

    if ($updateResult === false) {
        http_response_code(500);
        error_log("Error al actualizar en Validar_factura.php: " . print_r(sqlsrv_errors(), true));
        echo json_encode([
            'success' => false,
            'encontrada' => true,
            'error' => 'Error al actualizar la factura'
        ]);
        sqlsrv_close($conn);
        exit();
    }

    echo json_encode([
        'success' => true,
        'encontrada' => true,
        'fecha_scanner' => $fechaActual,
        'mensaje' => 'Factura validada correctamente'
    ]);
} else {
    // Ya tiene fecha scanner
    $fechaFormateada = is_object($fechaScanner) ? $fechaScanner->format('Y-m-d') : $fechaScanner;
    echo json_encode([
        'success' => true,
        'encontrada' => true,
        'fecha_scanner' => $fechaFormateada,
        'mensaje' => 'Factura ya validada'
    ]);
}

sqlsrv_close($conn);
