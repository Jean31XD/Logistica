<?php  
session_start();
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');


if (!isset($_SESSION['usuario'])) {
    die("Acceso no autorizado.");
}

require_once __DIR__ . '/../conexionBD/conexion.php';

$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);
if (!$conn) {
    die("Conexión fallida: " . print_r(sqlsrv_errors(), true));
}

$tiket = $_POST['tiket'];
$facturasRaw = $_POST['factura'];

$facturas = array_filter(array_map('trim', explode(';', $facturasRaw)));
$facturasConcatenadas = implode(';', $facturas);

foreach ($facturas as $factura) {
    $sqlCheck = "SELECT COUNT(*) AS total FROM analisis WHERE Factura = ?";
    $paramsCheck = [$factura];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

    if ($stmtCheck === false) {
        echo "Error al verificar duplicado de factura: " . print_r(sqlsrv_errors(), true);
        sqlsrv_close($conn);
        exit();
    }

    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if ($row['total'] > 0) {
        echo "Error: La factura '$factura' ya existe en el análisis.";
        sqlsrv_close($conn);
        exit();
    }
}

$sqlUpdate = "UPDATE log SET Estatus = 'Despachado', Factura = ? WHERE Tiket = ?";
$paramsUpdate = [$facturasConcatenadas, $tiket];
$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

if (!$stmtUpdate) {
    echo "Error al actualizar el ticket: " . print_r(sqlsrv_errors(), true);
    sqlsrv_close($conn);
    exit();
}

$sqlSP = "{CALL SP_Insertar_Analisis2(?)}";
$paramsSP = [$tiket];
$stmtSP = sqlsrv_query($conn, $sqlSP, $paramsSP);

if ($stmtSP === false) {
    echo "Ticket despachado, pero error al ejecutar SP_Insertar_Analisis2: " . print_r(sqlsrv_errors(), true);
} else {
    echo "Ticket despachado y análisis insertado correctamente.";
}

sqlsrv_close($conn);
?>
