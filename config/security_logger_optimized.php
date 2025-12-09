<?php
/**
 * Sistema de Logging Optimizado
 * - Escritura asíncrona en archivos
 * - Buffer de logs
 * - Inserción batch en BD
 */

// Buffer de logs en memoria
$GLOBALS['log_buffer'] = [];
$GLOBALS['log_buffer_size'] = 10; // Escribir cada 10 logs

/**
 * Registra evento en buffer (escritura diferida)
 */
function registrarEventoOptimizado($tipo_evento, $usuario = null, $detalles = []) {
    // Usar cache para evitar crear arrays repetidamente
    $log_entry = [
        'tipo' => $tipo_evento,
        'usuario' => $usuario,
        'ip' => obtenerIPOptimizada(),
        'detalles' => array_merge($detalles, [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'url' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ]),
        'timestamp' => time()
    ];

    // Agregar al buffer
    $GLOBALS['log_buffer'][] = $log_entry;

    // Escribir si el buffer está lleno
    if (count($GLOBALS['log_buffer']) >= $GLOBALS['log_buffer_size']) {
        flushLogBuffer();
    }
}

/**
 * Obtiene IP de forma optimizada (con cache)
 */
function obtenerIPOptimizada() {
    static $ip_cache = null;

    if ($ip_cache !== null) {
        return $ip_cache;
    }

    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip_cache = $ip;
                return $ip_cache;
            }
        }
    }

    $ip_cache = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    return $ip_cache;
}

/**
 * Escribe buffer de logs en archivo y BD
 */
function flushLogBuffer() {
    if (empty($GLOBALS['log_buffer'])) {
        return;
    }

    $logs = $GLOBALS['log_buffer'];
    $GLOBALS['log_buffer'] = [];

    // Escribir en archivo (rápido, sin locks largos)
    escribirLogsArchivo($logs);

    // Escribir en BD de forma asíncrona (en background si es posible)
    if (function_exists('fastcgi_finish_request')) {
        // Enviar respuesta al cliente inmediatamente
        fastcgi_finish_request();
        // Luego escribir logs en BD
        escribirLogsBD($logs);
    } else {
        // Si no está disponible, escribir normalmente
        escribirLogsBD($logs);
    }
}

/**
 * Escribe logs en archivo (batch)
 */
function escribirLogsArchivo($logs) {
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/security_' . date('Y-m-d') . '.log';

    $log_content = '';
    foreach ($logs as $log) {
        $timestamp = date('Y-m-d H:i:s', $log['timestamp']);
        $detalles_json = json_encode($log['detalles'], JSON_UNESCAPED_UNICODE);

        $log_content .= sprintf(
            "[%s] [%s] Usuario: %s | IP: %s | Detalles: %s\n",
            $timestamp,
            strtoupper($log['tipo']),
            $log['usuario'] ?? 'N/A',
            $log['ip'],
            $detalles_json
        );
    }

    // Escritura única con lock exclusivo
    @file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
}

/**
 * Escribe logs en BD (batch insert)
 */
function escribirLogsBD($logs) {
    try {
        // Obtener conexión
        if (!isset($GLOBALS['conn']) || !$GLOBALS['conn']) {
            return; // Skip si no hay conexión
        }

        $conn = $GLOBALS['conn'];

        // Preparar insert batch
        $values = [];
        $params = [];

        foreach ($logs as $log) {
            $values[] = "(?, ?, ?, ?, GETDATE())";
            $params[] = substr($log['tipo'], 0, 50);
            $params[] = $log['usuario'] ? substr($log['usuario'], 0, 50) : null;
            $params[] = substr($log['ip'], 0, 45);
            $params[] = json_encode($log['detalles'], JSON_UNESCAPED_UNICODE);
        }

        if (!empty($values)) {
            $sql = "INSERT INTO security_logs (evento_tipo, usuario, ip_address, detalles, fecha_hora) VALUES " . implode(', ', $values);
            sqlsrv_query($conn, $sql, $params);
        }
    } catch (Exception $e) {
        error_log("Error escribiendo logs en BD: " . $e->getMessage());
    }
}

/**
 * Wrapper para compatibilidad con código existente
 */
function registrarEventoSeguridad($tipo_evento, $usuario = null, $detalles = []) {
    registrarEventoOptimizado($tipo_evento, $usuario, $detalles);
}

function registrarIntentoLogin($usuario, $exitoso, $razon = '') {
    $tipo_evento = $exitoso ? 'login_exitoso' : 'login_fallido';
    registrarEventoOptimizado($tipo_evento, $usuario, ['razon' => $razon]);
}

// Al final del script, escribir logs pendientes
register_shutdown_function(function() {
    flushLogBuffer();
});
?>
