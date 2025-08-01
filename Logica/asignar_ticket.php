<?php
// asignar_ticket.php
session_start();
require_once __DIR__ . '/../conexionBD/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'], $_POST['tiket'], $_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos.']);
    exit();
}

$tiket = $_POST['tiket'];
$password = $_POST['password'];
$usuario_actual = $_SESSION['usuario'];

// 1. Obtener el hash de la contraseña del usuario desde la BD
$sql_user = "SELECT password FROM usuarios WHERE usuario = ?";
$stmt_user = sqlsrv_prepare($conn, $sql_user, [&$usuario_actual]);
sqlsrv_execute($stmt_user);

$user_data = sqlsrv_fetch_array($stmt_user, SQLSRV_FETCH_ASSOC);

if (!$user_data) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
    exit();
}

// 2. Verificar la contraseña
if (password_verify($password, $user_data['password'])) {
    // 3. Si es correcta, asignar el ticket
    $nuevo_estatus = 'En Proceso';
    $timestamp = time(); // Para la actualización inteligente
    
    $sql_update = "UPDATE tickets_en_espera SET asignado_a = ?, estatus = ?, fecha_actualizacion_ts = ? WHERE tiket = ?";
    $params_update = [$usuario_actual, $nuevo_estatus, $timestamp, $tiket];
    $stmt_update = sqlsrv_prepare($conn, $sql_update, $params_update);

    if (sqlsrv_execute($stmt_update)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al asignar el ticket.']);
    }
} else {
    // Si la contraseña es incorrecta
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
}
?>