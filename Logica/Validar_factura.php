<?php 
session_start();
date_default_timezone_set('America/Santo_Domingo');
if (!isset($_SESSION['usuario'])) {
    http_response_code(401); // No autorizado
    echo json_encode(['error' => 'Sesión expirada. Inicie sesión nuevamente.']);
    exit;
}


include '../conexionBD/conexion.php';

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

if ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $fechaScanner = $row['Fecha_scanner'];

    if (!$fechaScanner) {
  
        $fechaActual = date('Y-m-d'); 
        $update = "UPDATE custinvoicejour 
                   SET Validar = 'Completada', Fecha_scanner = ?, Usuario = ?
                   WHERE Factura = ? AND Transportista = ?";
        $updateParams = [$fechaActual, $usuario, $factura, $transportista];
        sqlsrv_query($conn, $update, $updateParams);

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
