<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// Validación de sesión
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo "ERROR: Sesión expirada";
    exit();
}

include '../conexionBD/conexion.php';

// Validar que se recibieron los parámetros
if (!isset($_POST['factura']) || !isset($_POST['valor']) || !isset($_POST['transportista'])) {
    http_response_code(400);
    echo "ERROR: Parámetros faltantes";
    exit();
}

$factura = trim($_POST['factura']);
$valor = trim($_POST['valor']);
$transportista = trim($_POST['transportista']);

// Validar que no estén vacíos
if (empty($factura) || empty($valor) || empty($transportista)) {
    http_response_code(400);
    echo "ERROR: Parámetros vacíos";
    exit();
}

$sql = "UPDATE custinvoicejour SET Validar = ? WHERE Factura = ? AND Transportista = ?";
$params = [$valor, $factura, $transportista];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    error_log("Error en actualizar_validar.php: " . print_r(sqlsrv_errors(), true));
    echo "ERROR: No se pudo actualizar la factura";
} else {
    $rowsAffected = sqlsrv_rows_affected($stmt);
    if ($rowsAffected === 0) {
        echo "ADVERTENCIA: No se encontró la factura o no se realizaron cambios";
    } else {
        echo "OK";
    }
}

sqlsrv_close($conn);
