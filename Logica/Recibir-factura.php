<?php   
session_start(); 
date_default_timezone_set('America/Santo_Domingo');

include '../conexionBD/conexion.php';

$factura = $_POST['factura'] ?? '';
$transportista = $_POST['transportista'] ?? '';
$response = ['encontrada' => false];

if ($factura && $transportista) {
    $sql = "SELECT * FROM custinvoicejour WHERE Factura = ? AND Transportista = ?";
    $params = [$factura, $transportista];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $estatus = strtolower(trim($row['Validar'] ?? ''));

        if ($estatus === 'completada') {
            $fechaActual = date('Y-m-d');
            $usuarioRecepcion = $_SESSION['usuario'] ?? null;

            $sqlUpdate = "UPDATE custinvoicejour SET recepcion = ?, Usuario_de_recepcion = ? WHERE Factura = ? AND Transportista = ?";
            $paramsUpdate = [$fechaActual, $usuarioRecepcion, $factura, $transportista];
            $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

            if ($stmtUpdate) {
                $response['encontrada'] = true;
                $response['fecha_recepcion'] = $fechaActual;
                $response['usuario_recepcion'] = $usuarioRecepcion;
                $response['fecha_scanner'] = isset($row['Fecha_scanner']) && is_object($row['Fecha_scanner'])
                    ? $row['Fecha_scanner']->format('Y-m-d')
                    : null;
            }
        } else {
            $response['error'] = 'Solo se pueden recepcionar facturas con estatus "Completada".';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
