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
$codigoVerificacion = $_POST['codigo_verificacion'] ?? ''; // Código de 6 dígitos enviado por correo
$currentAssignee = $_POST['current_assignee'] ?? ''; // El usuario que tiene el ticket ahora
$sessionUser = $_SESSION['usuario']; // El usuario que quiere tomar el ticket

if (!$tiket) {
    echo json_encode(['success' => false, 'message' => 'Falta el número de ticket.']);
    exit();
}

// 3. Determinar si es asignación nueva o reasignación
$isReassignment = !empty($currentAssignee);

if ($isReassignment) {
    // --- CASO 1: RE-ASIGNACIÓN (se requiere código de verificación) ---
    if ($currentAssignee === $sessionUser) {
        echo json_encode(['success' => false, 'message' => 'No puedes reasignarte un ticket que ya es tuyo.']);
        sqlsrv_close($conn);
        exit();
    }

    // Verificar código de verificación
    if (empty($codigoVerificacion)) {
        echo json_encode(['success' => false, 'message' => 'Para reasignar, ingrese el código de verificación enviado por correo.']);
        sqlsrv_close($conn);
        exit();
    }

    // Buscar el código en la base de datos
    $sqlCodigo = "
        SELECT id, usado, expira
        FROM codigos_verificacion
        WHERE codigo = ?
        AND usuario = ?
        AND usado = 0";

    $stmtCodigo = sqlsrv_query($conn, $sqlCodigo, [$codigoVerificacion, $currentAssignee]);

    if ($stmtCodigo === false) {
        require_once __DIR__ . '/../conexionBD/log_manager.php';
        logWithRotation("Error al verificar código para: $currentAssignee", 'ERROR', 'AUTH');
        echo json_encode(['success' => false, 'message' => 'Error al verificar el código.']);
        sqlsrv_close($conn);
        exit();
    }

    $rowCodigo = sqlsrv_fetch_array($stmtCodigo, SQLSRV_FETCH_ASSOC);

    if (!$rowCodigo) {
        require_once __DIR__ . '/../conexionBD/log_manager.php';
        logWithRotation("Código inválido o ya usado para: $currentAssignee - Código: $codigoVerificacion", 'WARNING', 'AUTH');
        echo json_encode(['success' => false, 'message' => 'Código inválido o ya fue utilizado.']);
        sqlsrv_close($conn);
        exit();
    }

    // Verificar si el código ha expirado
    $expira = $rowCodigo['expira'];
    $ahora = new DateTime();

    if ($expira < $ahora) {
        require_once __DIR__ . '/../conexionBD/log_manager.php';
        logWithRotation("Código expirado para: $currentAssignee - Código: $codigoVerificacion", 'WARNING', 'AUTH');
        echo json_encode(['success' => false, 'message' => 'El código ha expirado. Solicite uno nuevo.']);
        sqlsrv_close($conn);
        exit();
    }

    // Marcar el código como usado
    $codigoId = $rowCodigo['id'];
    $sqlMarcarUsado = "UPDATE codigos_verificacion SET usado = 1 WHERE id = ?";
    sqlsrv_query($conn, $sqlMarcarUsado, [$codigoId]);

    // Código válido, proceder con reasignación
    require_once __DIR__ . '/../conexionBD/log_manager.php';
    logWithRotation("Reasignación autorizada con código para: $currentAssignee → $sessionUser (Ticket: $tiket)", 'INFO', 'AUTH');
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