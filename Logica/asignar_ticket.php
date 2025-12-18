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
$password = $_POST['password'] ?? '';
$currentAssignee = $_POST['current_assignee'] ?? ''; // El usuario que tiene el ticket ahora
$sessionUser = $_SESSION['usuario']; // El usuario que quiere tomar el ticket

if (!$tiket) {
    echo json_encode(['success' => false, 'message' => 'Falta el número de ticket.']);
    exit();
}

// 3. Determinar si es asignación nueva o reasignación
$isReassignment = !empty($currentAssignee);

if ($isReassignment) {
    // --- CASO 1: RE-ASIGNACIÓN (se requiere contraseña) ---
    if ($currentAssignee === $sessionUser) {
        echo json_encode(['success' => false, 'message' => 'No puedes reasignarte un ticket que ya es tuyo.']);
        sqlsrv_close($conn);
        exit();
    }
    
    // Verificar contraseña del usuario que tiene el ticket
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Para reasignar, ingrese la contraseña del usuario actual.']);
        sqlsrv_close($conn);
        exit();
    }
    
    $sql_get_hash = "SELECT password FROM usuarios WHERE usuario = ?";
    $stmt_get_hash = sqlsrv_query($conn, $sql_get_hash, [$currentAssignee]);
    
    if ($stmt_get_hash === false) {
        echo json_encode(['success' => false, 'message' => 'Error al consultar el usuario.']);
        sqlsrv_close($conn);
        exit();
    }
    
    $row = sqlsrv_fetch_array($stmt_get_hash, SQLSRV_FETCH_ASSOC);
    
    if (!$row || !isset($row['password'])) {
        require_once __DIR__ . '/../conexionBD/log_manager.php';
        logWithRotation("Intento de reasignación - usuario no encontrado: $currentAssignee", 'WARNING', 'AUTH');
        echo json_encode(['success' => false, 'message' => 'Error de autenticación. Verifique la contraseña.']);
        sqlsrv_close($conn);
        exit();
    }
    
    if (!password_verify($password, $row['password'])) {
        require_once __DIR__ . '/../conexionBD/log_manager.php';
        logWithRotation("Intento fallido de reasignación - contraseña incorrecta para: $currentAssignee", 'WARNING', 'AUTH');
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
        sqlsrv_close($conn);
        exit();
    }
    
    // Contraseña correcta, proceder con reasignación
} else {
    // --- CASO 2: ASIGNACIÓN NUEVA (usuario ya autenticado en sesión) ---
    // No se requiere contraseña adicional, el usuario ya inició sesión
}

// Proceder con la asignación del ticket
$sql_update = "UPDATE log SET Asignar = ?, FechaModificacion = GETDATE() WHERE Tiket = ?";
$params_update = [$sessionUser, $tiket];
$stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

if ($stmt_update && sqlsrv_rows_affected($stmt_update) > 0) {
    regenerarSesionPorCambioPrivilegios();
    $actionType = $isReassignment ? 'reasignado' : 'asignado';
    echo json_encode(['success' => true, 'message' => "Ticket $actionType correctamente."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al asignar el ticket. Intente de nuevo.']);
}

sqlsrv_close($conn);
?>