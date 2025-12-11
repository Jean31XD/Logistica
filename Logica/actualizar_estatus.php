<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Datos de conexión
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!$conn) {
    die(json_encode(["error" => "Error de conexión a la base de datos."]));
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
    error_log("Error SQL en actualizar_estatus.php: " . print_r(sqlsrv_errors(), true));
    http_response_code(500);
    die(json_encode(["error" => "Error interno del servidor"]));
}

echo json_encode(["success" => "Estatus actualizado correctamente."]);

sqlsrv_close($conn);
?>
