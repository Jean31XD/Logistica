<?php
session_start();
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');


if (!isset($_SESSION['usuario'])) {
    exit("❌ Acceso no autorizado.");
}

if (!isset($_POST['tiket'])) {
    exit("❌ Error: No se recibió el ticket.");
}

$asignado = $_SESSION['usuario'];

require_once __DIR__ . '/../conexionBD/conexion.php';


$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    die("❌ Error de conexión: " . print_r(sqlsrv_errors(), true));
}

$tiket = $_POST['tiket'];

$sqlCheck = "SELECT * FROM log WHERE Tiket = ?";
$paramsCheck = array($tiket);
$stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

if ($stmtCheck === false || sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) === null) {
    exit("⚠️ Error: Ticket no encontrado.");
}

$sql = "UPDATE log SET Asignar = ? WHERE Tiket = ?";
$params = array($asignado, $tiket);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    exit("❌ Error al asignar el ticket: " . print_r(sqlsrv_errors(), true));
}

echo "✅ Ticket asignado correctamente a $asignado.";

sqlsrv_close($conn);
?>
