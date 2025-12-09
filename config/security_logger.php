<?php
/**
 * Sistema de Logging de Seguridad
 * Registra eventos de seguridad importantes
 */

/**
 * Registra un evento de seguridad en la base de datos y/o archivo de log
 * @param string $tipo_evento Tipo de evento (login_exitoso, login_fallido, acceso_denegado, etc.)
 * @param string|null $usuario Usuario que realizó la acción
 * @param array $detalles Detalles adicionales del evento
 */
function registrarEventoSeguridad($tipo_evento, $usuario = null, $detalles = []) {
    // Obtener información del contexto
    $ip = obtenerIPReal();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    $url = $_SERVER['REQUEST_URI'] ?? 'Desconocido';

    // Agregar información adicional a los detalles
    $detalles['user_agent'] = $user_agent;
    $detalles['url'] = $url;

    // Convertir detalles a JSON
    $detalles_json = json_encode($detalles, JSON_UNESCAPED_UNICODE);

    // Registrar en archivo de log
    registrarEnArchivoLog($tipo_evento, $usuario, $ip, $detalles_json);

    // Registrar en base de datos (si la conexión está disponible)
    registrarEnBaseDatos($tipo_evento, $usuario, $ip, $detalles_json);
}

/**
 * Obtiene la dirección IP real del usuario (considera proxies)
 * @return string Dirección IP
 */
function obtenerIPReal() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'Desconocido';
}

/**
 * Registra el evento en un archivo de log
 */
function registrarEnArchivoLog($tipo_evento, $usuario, $ip, $detalles_json) {
    $log_dir = __DIR__ . '/../logs';

    // Crear directorio de logs si no existe
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/security_' . date('Y-m-d') . '.log';

    $timestamp = date('Y-m-d H:i:s');
    $usuario_str = $usuario ?? 'N/A';

    $log_message = sprintf(
        "[%s] [%s] Usuario: %s | IP: %s | Detalles: %s\n",
        $timestamp,
        strtoupper($tipo_evento),
        $usuario_str,
        $ip,
        $detalles_json
    );

    // Escribir en el archivo de log
    @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Registra el evento en la base de datos
 */
function registrarEnBaseDatos($tipo_evento, $usuario, $ip, $detalles_json) {
    try {
        // Intentar incluir la conexión solo si no está ya incluida
        $conn_file = __DIR__ . '/../conexionBD/conexion.php';

        if (file_exists($conn_file)) {
            // Verificar si $conn ya existe (evitar múltiples conexiones)
            if (!isset($GLOBALS['conn']) || $GLOBALS['conn'] === false) {
                require_once $conn_file;
                $conn = $GLOBALS['conn'] ?? null;
            } else {
                $conn = $GLOBALS['conn'];
            }

            if ($conn && $conn !== false) {
                // Preparar consulta SQL
                $sql = "INSERT INTO security_logs (evento_tipo, usuario, ip_address, detalles, fecha_hora)
                        VALUES (?, ?, ?, ?, GETDATE())";

                $params = [
                    substr($tipo_evento, 0, 50), // Limitar longitud
                    $usuario ? substr($usuario, 0, 50) : null,
                    substr($ip, 0, 45),
                    $detalles_json
                ];

                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    // Si falla, al menos registrar en el log de PHP
                    error_log("Error al registrar evento de seguridad en BD: " . print_r(sqlsrv_errors(), true));
                }

                if ($stmt) {
                    sqlsrv_free_stmt($stmt);
                }
            }
        }
    } catch (Exception $e) {
        // En caso de error, registrar en el log de errores de PHP
        error_log("Excepción al registrar evento de seguridad: " . $e->getMessage());
    }
}

/**
 * Registra un intento de login
 * @param string $usuario Usuario que intentó iniciar sesión
 * @param bool $exitoso Si el login fue exitoso
 * @param string $razon Razón del fallo (si aplica)
 */
function registrarIntentoLogin($usuario, $exitoso, $razon = '') {
    $tipo_evento = $exitoso ? 'login_exitoso' : 'login_fallido';
    $detalles = ['razon' => $razon];

    registrarEventoSeguridad($tipo_evento, $usuario, $detalles);
}

/**
 * Limpia logs antiguos (ejecutar periódicamente con cron)
 * @param int $dias_antiguedad Número de días a mantener
 */
function limpiarLogsAntiguos($dias_antiguedad = 90) {
    $log_dir = __DIR__ . '/../logs';

    if (!is_dir($log_dir)) {
        return;
    }

    $archivos = glob($log_dir . '/security_*.log');
    $tiempo_limite = time() - ($dias_antiguedad * 24 * 60 * 60);

    foreach ($archivos as $archivo) {
        if (filemtime($archivo) < $tiempo_limite) {
            @unlink($archivo);
        }
    }
}
?>
