<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// 1. Debe estar logueado en el sistema principal
if (!isset($_SESSION['usuario'])) {
    header('Location: ../View/dashboard.php?error=2'); // No autorizado
    exit;
}

// 2. Requerir la conexión
require '../conexionBD/conexion.php'; 

if (!isset($conn) || $conn === false) {
    header('Location: ../View/dashboard.php?error=db'); // Error de DB
    exit;
}

$codigo_ingresado = $_POST['codigo'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'];
$log_exito = 0; // 0 = fallido, 1 = exitoso

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
    $sqlLog = "INSERT INTO log_accesos (codigo, exito, ip, fecha_hora) VALUES (?, ?, ?, GETDATE())";
    sqlsrv_query($conn, $sqlLog, [$codigo_ingresado, $log_exito, $ip]);

    if (isset($conn) && $conn !== false) {
        sqlsrv_close($conn);
    }
}
?>