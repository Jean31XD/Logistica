<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar Content-Type
validarContentType(['application/x-www-form-urlencoded', 'application/json']);

// Rate limiting: máximo 20 validaciones por minuto
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('validar_factura_recepcion', 20, 60)) {
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

// Validar que se recibió el número de factura
if (!isset($_POST['numeroFactura']) || empty($_POST['numeroFactura'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Número de factura no proporcionado'
    ]);
    exit();
}

$numeroFactura = trim($_POST['numeroFactura']);
logWithRotation("Intento de validación de factura en recepción: " . $numeroFactura, 'INFO', 'RECEPCION');
$usuario = $_SESSION['usuario'];
$fechaActual = date('Y-m-d');

// Validar que la factura tiene 11 dígitos
if (strlen($numeroFactura) !== 11) {
    echo json_encode([
        'success' => false,
        'message' => 'El número de factura debe tener exactamente 11 dígitos'
    ]);
    exit();
}

// Verificar si la factura existe en la base de datos
$sqlVerificar = "SELECT Factura, Validar, recepcion, Usuario_de_recepcion FROM custinvoicejour WHERE Factura = ?";
$stmtVerificar = sqlsrv_query($conn, $sqlVerificar, [$numeroFactura]);

if ($stmtVerificar === false) {
    error_log("Error SQL en validar_factura_recepcion.php: " . print_r(sqlsrv_errors(), true));
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar la factura. Intente de nuevo.'
    ]);
    exit();
}

$factura = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC);

if (!$factura) {
    logWithRotation("Factura no encontrada en recepción: " . $numeroFactura, 'WARNING', 'RECEPCION');
    echo json_encode([
        'success' => false,
        'message' => 'La factura ingresada no se encuentra en el sistema.'
    ]);
    exit();
}

// Verificar si ya fue recibida en CxC
if (!empty($factura['recepcion'])) {
    $usuarioRecepcion = $factura['Usuario_de_recepcion'] ?? 'usuario desconocido';
    $fechaRecepcion = is_object($factura['recepcion']) ? $factura['recepcion']->format('Y-m-d') : $factura['recepcion'];
    echo json_encode([
        'success' => false,
        'message' => 'La factura ' . htmlspecialchars($numeroFactura) . ' ya fue recibida en CxC el ' . $fechaRecepcion . ' por ' . htmlspecialchars($usuarioRecepcion)
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
    error_log("Error SQL al actualizar factura en validar_factura_recepcion.php: " . print_r(sqlsrv_errors(), true));
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la factura. Intente de nuevo.'
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
