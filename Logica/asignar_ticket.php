<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Debes iniciar sesión.']);
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

// Obtener datos de la petición AJAX
$tiket = $_POST['tiket'] ?? null;
$password = $_POST['password'] ?? null;
$currentAssignee = $_POST['current_assignee'] ?? ''; // El usuario que tiene el ticket ahora
$sessionUser = $_SESSION['usuario']; // El usuario que quiere tomar el ticket

if (!$tiket || !$password) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos (ticket o contraseña).']);
    exit();
}

// Conectar a la base de datos
$conn = sqlsrv_connect($serverName, ["Database" => $database, "UID" => $username, "PWD" => $password_db, "TrustServerCertificate" => true]);
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con la base de datos.']);
    exit();
}

// Determinar qué usuario debemos verificar
$user_to_check = '';
if (!empty($currentAssignee)) {
    // --- CASO 1: RE-ASIGNACIÓN ---
    // El ticket ya tiene dueño. Debemos verificar la contraseña del dueño actual.
    if ($currentAssignee === $sessionUser) {
        echo json_encode(['success' => false, 'message' => 'No puedes reasignarte un ticket que ya es tuyo.']);
        sqlsrv_close($conn);
        exit();
    }
    $user_to_check = $currentAssignee;
} else {
    // --- CASO 2: ASIGNACIÓN NUEVA ---
    // El ticket está libre. Verificamos la contraseña del usuario que lo está tomando.
    $user_to_check = $sessionUser;
}

// Buscar el hash de la contraseña del usuario a verificar
$sql_get_hash = "SELECT password FROM usuarios WHERE usuario = ?";
$params_get_hash = [$user_to_check];
$stmt_get_hash = sqlsrv_query($conn, $sql_get_hash, $params_get_hash);

if ($stmt_get_hash === false) {
    echo json_encode(['success' => false, 'message' => 'Error al consultar el usuario.']);
    sqlsrv_close($conn);
    exit();
}

$row = sqlsrv_fetch_array($stmt_get_hash, SQLSRV_FETCH_ASSOC);

if (!$row || !isset($row['password'])) {
    echo json_encode(['success' => false, 'message' => "El usuario a verificar ('$user_to_check') no fue encontrado."]);
    sqlsrv_close($conn);
    exit();
}

$hashed_password = $row['password'];

// Verificar la contraseña
if (password_verify($password, $hashed_password)) {
    // Contraseña correcta. Procedemos a asignar el ticket al usuario de la sesión.
    $sql_update = "UPDATE log SET Asignar = ?, FechaModificacion = GETDATE() WHERE Tiket = ?";
    $params_update = [$sessionUser, $tiket];
    $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

    if ($stmt_update && sqlsrv_rows_affected($stmt_update) > 0) {
        echo json_encode(['success' => true, 'message' => 'Ticket asignado a ' . $sessionUser . ' correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contraseña correcta, pero falló la asignación del ticket.']);
    }
} else {
    // Contraseña incorrecta
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
}

sqlsrv_close($conn);