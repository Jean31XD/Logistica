<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();


require_once __DIR__ . '/../conexionBD/conexion.php';

if (!$conn) {
    die("Error de conexión a la base de datos.");
}


$tiket = $_POST['tiket'];
$accion = $_POST['accion'];

if ($accion === 'insertar') {
    $query = "EXEC SP_Insertar_Retencion @Tiket = ?";
    $params = array($tiket);
    echo "Retención aplicada correctamente.";
} elseif ($accion === 'actualizar') {
    $query = "EXEC SP_Actualizar_Despacho @Tiket = ?";
    $params = array($tiket);
    echo "Se saco de la retención correctamente.";
}


$stmt = sqlsrv_query($conn, $query, $params);

if ($stmt === false) {
    error_log("Error SQL en accion_retencion.php: " . print_r(sqlsrv_errors(), true));
    http_response_code(500);
    die(json_encode(['error' => 'Error interno del servidor']));
}



sqlsrv_close($conn);
?>
