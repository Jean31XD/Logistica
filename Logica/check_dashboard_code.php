<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// 2. Requerir la conexión
require '../conexionBD/conexion.php'; 

if (!isset($conn) || $conn === false) {
    header('Location: ../View/dashboard.php?error=db'); // Error de DB
    exit;
}

$codigo_ingresado = $_POST['codigo'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'];
$log_exito = 0; // 0 = fallido, 1 = exitoso

// --- LÓGICA DE BLOQUEO POR INTENTOS FALLIDOS ---
$max_intentos = 5;
$tiempo_bloqueo = 15; // en minutos

$sql_check_attempts = "SELECT COUNT(*) as attempts FROM log_accesos WHERE ip = ? AND tipo_intento = 'pin' AND exito = 0 AND fecha_hora > DATEADD(minute, -?, GETDATE())";
$params_check_attempts = [$ip, $tiempo_bloqueo];
$stmt_check_attempts = sqlsrv_query($conn, $sql_check_attempts, $params_check_attempts);

if ($stmt_check_attempts === false) {
    // Si la consulta falla, es más seguro bloquear temporalmente que permitir el acceso.
    header('Location: ../View/dashboard.php?error=dberror');
    exit;
}

$attempts_row = sqlsrv_fetch_array($stmt_check_attempts, SQLSRV_FETCH_ASSOC);
$intentos_fallidos = $attempts_row['attempts'] ?? 0;

if ($intentos_fallidos >= $max_intentos) {
    header('Location: ../View/dashboard.php?error=blocked');
    exit;
}

try {
    if (empty($codigo_ingresado)) {
        header('Location: ../View/dashboard.php?error=1');
        exit;
    }

    // 3. Consultar la tabla de códigos (basado en Gestion_de_usuario.php)
    $sql = "SELECT * FROM codigos_acceso WHERE codigo = ? AND activo = 1";
    $params = [$codigo_ingresado];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception('Error en la consulta de código.');
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($row) {
        // --- ÉXITO ---
        $log_exito = 1;
        
        // 4. Crear las variables de sesión del DASHBOARD
        $_SESSION['dashboard_access_granted'] = true;
        $_SESSION['dashboard_user_type'] = $row['es_admin'] ? 'admin' : 'warehouse';
        $_SESSION['dashboard_warehouse'] = $row['almacen']; // Será NULL (o vacío) si es admin
        
        // 5. Actualizar último acceso (Best effort)
        $sqlUpdate = "UPDATE codigos_acceso SET ultimo_acceso = GETDATE() WHERE id = ?";
        sqlsrv_query($conn, $sqlUpdate, [$row['id']]);

        // Limpiar intentos de login fallidos para esta IP
        $sqlClear = "DELETE FROM log_accesos WHERE ip = ? AND tipo_intento = 'pin'";
        sqlsrv_query($conn, $sqlClear, [$ip]);
        
        header('Location: ../View/dashboard.php');
        exit;
        
    } else {
        // --- FALLO ---
        header('Location: ../View/dashboard.php?error=1');
        exit;
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: ../View/dashboard.php?error=1');
    exit;
} finally {
    // 6. Registrar el intento de acceso en el log
    $sqlLog = "INSERT INTO log_accesos (codigo, exito, ip, fecha_hora, tipo_intento) VALUES (?, ?, ?, GETDATE(), 'pin')";
    sqlsrv_query($conn, $sqlLog, [$codigo_ingresado, $log_exito, $ip]);

    if (isset($conn) && $conn !== false) {
        sqlsrv_close($conn);
    }
}
?>