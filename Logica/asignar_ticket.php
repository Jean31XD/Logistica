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

if (!isset($_POST['tiket']) || !isset($_POST['passwordNuevo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos para realizar la operación.']);
    exit;
}

$tiket = $_POST['tiket'];
$passwordNuevo = $_POST['passwordNuevo']; // La contraseña del usuario que quiere asignar
$passwordActual = $_POST['passwordActual'] ?? ''; // La contraseña del usuario actual (si existe)
$usuarioAsignar = $_SESSION['usuario'];
$asignadoActual = $_POST['asignadoActual'] ?? 'No asignado';

// 2. Conexión a la BD
require_once __DIR__ . '/../conexionBD/conexion.php';
$connectionInfo = ["Database" => $database, "UID" => $username, "PWD" => $password, "TrustServerCertificate" => true];
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

// 3. Lógica de verificación de contraseñas
try {
    // Si el ticket ya está asignado, verificar la contraseña del usuario actual
    if ($asignadoActual !== 'No asignado') {
        $sqlCheckCurrentPassword = "SELECT password FROM usuarios WHERE usuario = ?";
        $paramsCheckCurrentPassword = [$asignadoActual];
        $stmtCheckCurrentPassword = sqlsrv_query($conn, $sqlCheckCurrentPassword, $paramsCheckCurrentPassword);

        if ($stmtCheckCurrentPassword === false) {
            throw new Exception("Error al consultar la contraseña del usuario actual.");
        }

        $rowCurrent = sqlsrv_fetch_array($stmtCheckCurrentPassword, SQLSRV_FETCH_ASSOC);

        if (!$rowCurrent || !password_verify($passwordActual, $rowCurrent['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña del usuario actual incorrecta.']);
            sqlsrv_free_stmt($stmtCheckCurrentPassword);
            sqlsrv_close($conn);
            exit;
        }
        sqlsrv_free_stmt($stmtCheckCurrentPassword);
    }
    
    // Verificar la contraseña del usuario que quiere asignar
    $sqlCheckNewPassword = "SELECT password FROM usuarios WHERE usuario = ?";
    $paramsCheckNewPassword = [$usuarioAsignar];
    $stmtCheckNewPassword = sqlsrv_query($conn, $sqlCheckNewPassword, $paramsCheckNewPassword);

    if ($stmtCheckNewPassword === false) {
        throw new Exception("Error al consultar la contraseña del nuevo usuario.");
    }

    $rowNew = sqlsrv_fetch_array($stmtCheckNewPassword, SQLSRV_FETCH_ASSOC);
    
    if (!$rowNew || !password_verify($passwordNuevo, $rowNew['password'])) {
        echo json_encode(['success' => false, 'message' => 'Tu contraseña es incorrecta.']);
        sqlsrv_free_stmt($stmtCheckNewPassword);
        sqlsrv_close($conn);
        exit;
    }
    sqlsrv_free_stmt($stmtCheckNewPassword);
    
    // 4. Si todas las contraseñas son correctas, proceder a asignar el ticket
    // Actualizar el ticket
    $sql = "UPDATE log SET Asignar = ?, Estatus = 'Verificación de pedido' WHERE Tiket = ?";
    $params = [$usuarioAsignar, $tiket];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception('Error al asignar el ticket: ' . print_r(sqlsrv_errors(), true));
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if ($conn) {
        sqlsrv_close($conn);
    }
}
?>