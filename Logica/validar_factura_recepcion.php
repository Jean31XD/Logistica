<?php
session_start();

// Headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Validación de sesión
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesión expirada. Por favor, inicia sesión nuevamente.',
        'redirect' => true
    ]);
    exit();
}

date_default_timezone_set('America/Santo_Domingo');
include '../conexionBD/conexion.php';

// Validar que se recibió el número de factura
if (!isset($_POST['numeroFactura']) || empty($_POST['numeroFactura'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Número de factura no proporcionado'
    ]);
    exit();
}

$numeroFactura = trim($_POST['numeroFactura']);
$usuario = $_SESSION['usuario'];
$fechaActual = date('Y-m-d H:i:s');

// Validar que la factura tiene 11 dígitos
if (strlen($numeroFactura) !== 11) {
    echo json_encode([
        'success' => false,
        'message' => 'El número de factura debe tener exactamente 11 dígitos'
    ]);
    exit();
}

// Verificar si la factura existe en la base de datos
$sqlVerificar = "SELECT Factura, Validar FROM custinvoicejour WHERE Factura = ?";
$stmtVerificar = sqlsrv_query($conn, $sqlVerificar, [$numeroFactura]);

if ($stmtVerificar === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar la factura: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit();
}

$factura = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC);

if (!$factura) {
    echo json_encode([
        'success' => false,
        'message' => 'La factura ' . htmlspecialchars($numeroFactura) . ' no existe en el sistema'
    ]);
    exit();
}

// Verificar el estado actual
$estadoActual = trim($factura['Validar'] ?? '');

if (strtolower($estadoActual) === 'completada') {
    echo json_encode([
        'success' => false,
        'message' => 'La factura ' . htmlspecialchars($numeroFactura) . ' ya fue completada anteriormente'
    ]);
    exit();
}

// Actualizar la factura - marcar como recibida en CxC
$sqlActualizar = "UPDATE custinvoicejour
                  SET recepcion = ?,
                      Usuario_de_recepcion = ?
                  WHERE Factura = ?";

$paramsActualizar = [$fechaActual, $usuario, $numeroFactura];
$stmtActualizar = sqlsrv_query($conn, $sqlActualizar, $paramsActualizar);

if ($stmtActualizar === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la factura: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit();
}

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Factura ' . htmlspecialchars($numeroFactura) . ' recibida correctamente por ' . htmlspecialchars($usuario)
]);

sqlsrv_close($conn);
?>
