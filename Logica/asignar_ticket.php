<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar Content-Type
validarContentType(['application/x-www-form-urlencoded', 'application/json']);

// Rate limiting: máximo 10 intentos por minuto
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('asignar_ticket', 10, 60)) {
    rateLimitExceeded('Demasiados intentos de asignación. Espere un momento.');
}

// Validar CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($csrf)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Token CSRF inválido']));
    }
}

header('Content-Type: application/json');

// 1. Incluir la conexión. ESTO YA CREA LA VARIABLE $conn.
require_once __DIR__ . '/../conexionBD/conexion.php';

// Si la conexión desde el archivo incluido falla, $conn será false.
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con la base de datos (desde conexion.php).']);
    exit();
}

// 2. Obtener datos de la petición AJAX
$tiket = $_POST['tiket'] ?? null;
$password = $_POST['password'] ?? null;
$currentAssignee = $_POST['current_assignee'] ?? ''; // El usuario que tiene el ticket ahora
$sessionUser = $_SESSION['usuario']; // El usuario que quiere tomar el ticket

if (!$tiket || !$password) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos (ticket o contraseña).']);
    exit();
}

// 3. El resto de tu lógica (que ya está bien)
// Determinar qué usuario debemos verificar
$user_to_check = '';
if (!empty($currentAssignee)) {
    // --- CASO 1: RE-ASIGNACIÓN ---
    if ($currentAssignee === $sessionUser) {
        echo json_encode(['success' => false, 'message' => 'No puedes reasignarte un ticket que ya es tuyo.']);
        sqlsrv_close($conn);
        exit();
    }
    $user_to_check = $currentAssignee;
} else {
    // --- CASO 2: ASIGNACIÓN NUEVA ---
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
    // No revelar si el usuario existe o no (prevenir enumeración)
    require_once __DIR__ . '/../conexionBD/log_manager.php';
    logWithRotation("Intento de asignación con usuario no encontrado: $user_to_check", 'WARNING', 'AUTH');
    echo json_encode(['success' => false, 'message' => 'Error de autenticación. Verifique su contraseña.']);
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
        // Regenerar sesión por cambio de privilegios (ahora tiene un ticket asignado)
        regenerarSesionPorCambioPrivilegios();
        echo json_encode(['success' => true, 'message' => 'Ticket asignado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al asignar el ticket. Intente de nuevo.']);
    }
} else {
    // Contraseña incorrecta - mismo mensaje que usuario no encontrado (prevenir enumeración)
    require_once __DIR__ . '/../conexionBD/log_manager.php';
    logWithRotation("Intento fallido de asignación para usuario: $user_to_check", 'WARNING', 'AUTH');
    echo json_encode(['success' => false, 'message' => 'Error de autenticación. Verifique su contraseña.']);
}

sqlsrv_close($conn);
?>