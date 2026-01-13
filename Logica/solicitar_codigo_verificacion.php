<?php
/**
 * Endpoint para solicitar código de verificación por correo
 * Para reasignación de tickets
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar Content-Type
validarContentType(['application/x-www-form-urlencoded', 'application/json']);

// Rate limiting: máximo 3 solicitudes por minuto por usuario
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('solicitar_codigo', 3, 60)) {
    rateLimitExceeded('Demasiadas solicitudes de código. Espere un momento.');
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

require_once __DIR__ . '/../conexionBD/conexion.php';
require_once __DIR__ . '/../conexionBD/email_helper.php';

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con la base de datos.']);
    exit();
}

// Obtener datos
$usuario = $_POST['usuario'] ?? '';
$ticket = $_POST['ticket'] ?? '';

if (empty($usuario)) {
    echo json_encode(['success' => false, 'message' => 'Falta el nombre de usuario.']);
    exit();
}

// Verificar que el usuario existe y obtener su email
$sqlUsuario = "SELECT email FROM usuarios WHERE usuario = ?";
$stmtUsuario = sqlsrv_query($conn, $sqlUsuario, [$usuario]);

$userEmail = null;

if ($stmtUsuario !== false) {
    $rowUsuario = sqlsrv_fetch_array($stmtUsuario, SQLSRV_FETCH_ASSOC);
    if ($rowUsuario && !empty($rowUsuario['email'])) {
        $userEmail = $rowUsuario['email'];
    }
}

// Si no tiene email en la BD, construirlo
if (empty($userEmail)) {
    $userEmail = $usuario . '@corripio.com.do';
}

// Limpiar códigos expirados del usuario
$sqlLimpiar = "DELETE FROM codigos_verificacion WHERE usuario = ? AND (expira < GETDATE() OR usado = 1)";
sqlsrv_query($conn, $sqlLimpiar, [$usuario]);

// Verificar si ya tiene un código activo (no expirado)
$sqlCheck = "SELECT COUNT(*) as cnt FROM codigos_verificacion WHERE usuario = ? AND expira > GETDATE() AND usado = 0";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$usuario]);
$rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if ($rowCheck && $rowCheck['cnt'] > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ya existe un código activo para este usuario. Espere a que expire o úselo.'
    ]);
    sqlsrv_close($conn);
    exit();
}

// Generar código de 6 dígitos
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Calcular fecha de expiración (5 minutos)
$expira = new DateTime();
$expira->add(new DateInterval('PT5M'));

// Guardar código en la base de datos
$sqlInsert = "INSERT INTO codigos_verificacion (codigo, usuario, ticket, expira, ip_solicitud) VALUES (?, ?, ?, ?, ?)";
$paramsInsert = [
    $codigo,
    $usuario,
    $ticket,
    $expira->format('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

$stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

if ($stmtInsert === false) {
    $errors = sqlsrv_errors();
    error_log("Error al guardar código de verificación: " . print_r($errors, true));
    echo json_encode(['success' => false, 'message' => 'Error al generar el código.']);
    sqlsrv_close($conn);
    exit();
}

// Éxito - el código se guardó y se mostrará en la pantalla del usuario asignado
require_once __DIR__ . '/../conexionBD/log_manager.php';
logWithRotation("Código de verificación generado para: $usuario - Ticket: $ticket", 'INFO', 'AUTH');

echo json_encode([
    'success' => true,
    'message' => "Código generado. El usuario '$usuario' verá el código en su pantalla.",
    'usuario_destino' => $usuario
]);

sqlsrv_close($conn);
?>
