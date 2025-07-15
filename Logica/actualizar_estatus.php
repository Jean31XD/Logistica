<?php
session_start();
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');


// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    die("Acceso no autorizado.");
}

// Datos de conexión
require_once __DIR__ . '/../conexionBD/conexion.php';


$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    die(json_encode(["error" => "Error de conexión: " . print_r(sqlsrv_errors(), true)]));
}

// Obtener datos del formulario
$tiket = $_POST['tiket'] ?? null;
$estatus = $_POST['estatus'] ?? null;

if (!$tiket || !$estatus) {
    die(json_encode(["error" => "Datos incompletos."]));
}

$sql = "UPDATE [log] SET Estatus = ? WHERE Tiket = ?";
$params = array($estatus, $tiket);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(json_encode(["error" => "Error al actualizar el estatus: " . print_r(sqlsrv_errors(), true)]));
}

echo json_encode(["success" => "Estatus actualizado correctamente."]);

sqlsrv_close($conn);
?>
