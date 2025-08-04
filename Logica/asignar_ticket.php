<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

header('Content-Type: application/json');

// 1. Verificación de sesión y datos de entrada
if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado. Debes iniciar sesión.']);
    exit;
}

if (!isset($_POST['tiket']) || !isset($_POST['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos para realizar la operación.']);
    exit;
}

$tiket = $_POST['tiket'];
$passwordIngresada = $_POST['password'];
$usuarioAsignar = $_SESSION['usuario'];

// 2. Conexión a la BD
require_once __DIR__ . '/../conexionBD/conexion.php';
$connectionInfo = ["Database" => $database, "UID" => $username, "PWD" => $password, "TrustServerCertificate" => true];
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

// 3. Verificar la contraseña del usuario
// Asumimos que tienes una tabla 'usuarios' con 'usuario' y 'password       '
$sqlUser = "SELECT password FROM usuarios WHERE usuario = ?";
$paramsUser = [$usuarioAsignar];
$stmtUser = sqlsrv_query($conn, $sqlUser, $paramsUser);

if ($stmtUser === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar datos de usuario.']);
    sqlsrv_close($conn);
    exit;
}

$userRow = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC);

if (!$userRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
    sqlsrv_close($conn);
    exit;
    }
// Obtener el hash de la contraseña almacenada
$hashPassword = $userRow['password'];

// ¡La parte clave! Verificar que la contraseña ingresada coincida con el hash guardado.
if (!password_verify($passwordIngresada, $hashPassword )) {
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
    sqlsrv_close($conn);
    exit;
}
    
// 4. Si la contraseña es correcta, proceder a asignar el ticket
// Primero, verificar que el ticket no esté ya asignado para evitar race conditions
$sqlCheck = "SELECT Asignar FROM log WHERE Tiket = ?";
$paramsCheck = [$tiket];
$stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
$ticketData = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if ($ticketData && !empty(trim($ticketData['Asignar']))) {
     echo json_encode(['success' => false, 'message' => 'Este ticket ya fue asignado por otro usuario. La tabla se refrescará.']);
     sqlsrv_close($conn);
     exit;
}

// Actualizar el ticket
$sql = "UPDATE log SET Asignar = ?, Estatus = 'Verificación de pedido' WHERE Tiket = ?";
$params = [$usuarioAsignar, $tiket];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al asignar el ticket: ' . print_r(sqlsrv_errors(), true)]);
} else {
    echo json_encode(['success' => true]);
}

sqlsrv_close($conn);
?>