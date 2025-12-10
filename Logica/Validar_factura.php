<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// Headers para respuesta JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Validación de sesión
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'encontrada' => false,
        'error' => 'Sesión expirada. Por favor, inicia sesión nuevamente.'
    ]);
    exit();
}

include '../conexionBD/conexion.php';

// Validar parámetros requeridos
$factura = trim($_POST['factura'] ?? '');
$transportista = trim($_POST['transportista'] ?? '');
$usuario = $_SESSION['usuario'];

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
