<?php
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');

include '../conexionBD/conexion.php';

$factura = $_POST['factura'];
$valor = $_POST['valor'];
$transportista = $_POST['transportista'];

$sql = "UPDATE custinvoicejour SET Validar = ? WHERE Factura = ? AND Transportista = ?";
$params = [$valor, $factura, $transportista];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo "ERROR";
} else {
    echo "OK";
}
