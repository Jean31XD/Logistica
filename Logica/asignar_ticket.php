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

// Se esperan el ticket, la contraseña del usuario actualmente asignado y la del nuevo usuario
if (!isset($_POST['tiket']) || !isset($_POST['password_asignado']) || !isset($_POST['password_nuevo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos para realizar la operación.']);
    exit;
}

$tiket = $_POST['tiket'];
$passwordAsignado = $_POST['password_asignado'];
$passwordNuevo = $_POST['password_nuevo'];
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

// 3. Obtener el usuario actualmente asignado al ticket
$sqlAsignado = "SELECT Asignar FROM log WHERE Tiket = ?";
$paramsAsignado = [$tiket];
$stmtAsignado = sqlsrv_query($conn, $sqlAsignado, $paramsAsignado);
if ($stmtAsignado === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al verificar la asignación actual del ticket.']);
    sqlsrv_close($conn);
    exit;
}

$rowAsignado = sqlsrv_fetch_array($stmtAsignado, SQLSRV_FETCH_ASSOC);
$usuarioAsignadoActual = $rowAsignado['Asignar'] ?? null;

// 4. Verificar la contraseña del usuario actualmente asignado
if ($usuarioAsignadoActual && $usuarioAsignadoActual !== $usuarioAsignar) {
    // Si el ticket ya tiene un dueño diferente al que lo va a tomar, se pide la contraseña del dueño actual
    $sqlUserActual = "SELECT contrasena FROM usuarios WHERE usuario = ?";
    $paramsUserActual = [$usuarioAsignadoActual];
    $stmtUserActual = sqlsrv_query($conn, $sqlUserActual, $paramsUserActual);

    if ($stmtUserActual === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al consultar el usuario actual del ticket.']);
        sqlsrv_close($conn);
        exit;
    }

    $userRowActual = sqlsrv_fetch_array($stmtUserActual, SQLSRV_FETCH_ASSOC);

    if (!$userRowActual || !password_verify($passwordAsignado, $userRowActual['contrasena'])) {
        echo json_encode(['success' => false, 'message' => 'La contraseña del usuario actualmente asignado es incorrecta.']);
        sqlsrv_close($conn);
        exit;
    }
}

// 5. Verificar la contraseña del usuario que va a tomar el ticket
$sqlUserNuevo = "SELECT contrasena FROM usuarios WHERE usuario = ?";
$paramsUserNuevo = [$usuarioAsignar];
$stmtUserNuevo = sqlsrv_query($conn, $sqlUserNuevo, $paramsUserNuevo);

if ($stmtUserNuevo === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar tus datos de usuario.']);
    sqlsrv_close($conn);
    exit;
}

$userRowNuevo = sqlsrv_fetch_array($stmtUserNuevo, SQLSRV_FETCH_ASSOC);
if (!$userRowNuevo || !password_verify($passwordNuevo, $userRowNuevo['contrasena'])) {
    echo json_encode(['success' => false, 'message' => 'Tu propia contraseña es incorrecta. No puedes auto-asignarte el ticket.']);
    sqlsrv_close($conn);
    exit;
}

// 6. Si todas las verificaciones son exitosas, proceder a asignar el ticket
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