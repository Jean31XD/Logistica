<?php
/**
 * Tarea de Mantenimiento Diario - MACO
 * Ejecuta el SP de limpieza de duplicados
 * 
 * LLAMAR DESDE:
 * - Azure WebJob (programado cada 24h)
 * - Cron job: 0 2 * * * curl https://tu-app.azurewebsites.net/Logica/ejecutar_mantenimiento.php?key=SECRET
 * - Manualmente cuando sea necesario
 */

// Clave de seguridad para evitar ejecuciones no autorizadas
$CLAVE_MANTENIMIENTO = getenv('MAINTENANCE_KEY') ?: 'maco_maint_2024_secret';

// Validar clave de acceso
$claveRecibida = $_GET['key'] ?? $_SERVER['HTTP_X_MAINTENANCE_KEY'] ?? '';
if ($claveRecibida !== $CLAVE_MANTENIMIENTO) {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso no autorizado']));
}

// Cargar conexión
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!$conn) {
    http_response_code(500);
    die(json_encode(['error' => 'Error de conexión a BD']));
}

header('Content-Type: application/json; charset=utf-8');

// Verificar última ejecución (evitar ejecutar más de 1 vez cada 20 horas)
$sqlUltimaEjecucion = "
    SELECT TOP 1 fecha_ejecucion 
    FROM log_mantenimiento 
    WHERE procedimiento = 'sp_MantenimientoDiario' AND estado = 'OK'
    ORDER BY fecha_ejecucion DESC
";
$stmtUltima = @sqlsrv_query($conn, $sqlUltimaEjecucion);

if ($stmtUltima) {
    $row = sqlsrv_fetch_array($stmtUltima, SQLSRV_FETCH_ASSOC);
    if ($row && isset($row['fecha_ejecucion'])) {
        $ultimaEjecucion = $row['fecha_ejecucion'];
        if (is_string($ultimaEjecucion)) {
            $ultimaEjecucion = new DateTime($ultimaEjecucion);
        }
        $ahora = new DateTime();
        $diferencia = $ahora->diff($ultimaEjecucion);
        $horasDesdeUltima = ($diferencia->days * 24) + $diferencia->h;
        
        if ($horasDesdeUltima < 20) {
            echo json_encode([
                'status' => 'skipped',
                'message' => "Mantenimiento ejecutado hace {$horasDesdeUltima} horas. Próxima ejecución en " . (24 - $horasDesdeUltima) . " horas.",
                'ultima_ejecucion' => $ultimaEjecucion->format('Y-m-d H:i:s')
            ]);
            exit;
        }
    }
}

// Ejecutar SP de mantenimiento
$sql = "{CALL sp_MantenimientoDiario}";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    error_log("Error ejecutando mantenimiento: " . print_r($errors, true));
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error ejecutando mantenimiento',
        'details' => $errors[0]['message'] ?? 'Error desconocido'
    ]);
    exit;
}

// Obtener resultado
$result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($result) {
    echo json_encode([
        'status' => 'success',
        'filas_eliminadas' => $result['FilasEliminadas'] ?? 0,
        'estado' => $result['Estado'] ?? 'OK',
        'mensaje' => $result['Mensaje'] ?? 'Mantenimiento completado',
        'fecha' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'message' => 'Mantenimiento ejecutado (sin datos de retorno)',
        'fecha' => date('Y-m-d H:i:s')
    ]);
}

sqlsrv_close($conn);
?>
