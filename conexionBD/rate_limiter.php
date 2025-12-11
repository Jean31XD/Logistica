<?php
/**
 * Rate Limiter - Sistema de limitación de tasa de peticiones
 * Protege contra ataques de fuerza bruta y DoS
 */

/**
 * Verifica si una IP/usuario ha excedido el límite de peticiones
 *
 * @param string $action Acción que se está limitando (ej: 'login', 'asignar_ticket', 'validar_factura')
 * @param int $maxAttempts Número máximo de intentos permitidos
 * @param int $timeWindow Ventana de tiempo en segundos
 * @param string $identifier Identificador único (por defecto: IP del cliente)
 * @return bool true si está dentro del límite, false si excedió
 */
function checkRateLimit($action, $maxAttempts = 10, $timeWindow = 60, $identifier = null) {
    // Si no se especifica identificador, usar IP
    if ($identifier === null) {
        $identifier = $_SERVER['REMOTE_ADDR'];
    }

    // Clave única para esta acción e identificador
    $key = "ratelimit_{$action}_{$identifier}";

    // Usar sesión para almacenar intentos (en producción, usar Redis o Memcached)
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }

    $now = time();

    // Limpiar intentos antiguos fuera de la ventana de tiempo
    if (isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = array_filter(
            $_SESSION['rate_limits'][$key],
            function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            }
        );
    } else {
        $_SESSION['rate_limits'][$key] = [];
    }

    // Verificar si excedió el límite
    if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
        // Registrar intento bloqueado
        error_log("Rate limit excedido para acción '$action' - IP: $identifier - Intentos: " . count($_SESSION['rate_limits'][$key]));
        return false;
    }

    // Registrar este intento
    $_SESSION['rate_limits'][$key][] = $now;

    return true;
}

/**
 * Responde con error 429 (Too Many Requests) cuando se excede el límite
 *
 * @param string $message Mensaje personalizado (opcional)
 */
function rateLimitExceeded($message = null) {
    if ($message === null) {
        $message = 'Demasiadas peticiones. Por favor, espere un momento e intente de nuevo.';
    }

    http_response_code(429);
    header('Content-Type: application/json');
    header('Retry-After: 60'); // Sugerir reintentar después de 60 segundos

    die(json_encode([
        'error' => $message,
        'code' => 429,
        'retry_after' => 60
    ]));
}

/**
 * Limpia los intentos de rate limiting para una acción específica
 * Útil después de una acción exitosa (ej: login correcto)
 *
 * @param string $action Acción a limpiar
 * @param string $identifier Identificador único (por defecto: IP del cliente)
 */
function clearRateLimit($action, $identifier = null) {
    if ($identifier === null) {
        $identifier = $_SERVER['REMOTE_ADDR'];
    }

    $key = "ratelimit_{$action}_{$identifier}";

    if (isset($_SESSION['rate_limits'][$key])) {
        unset($_SESSION['rate_limits'][$key]);
    }
}
?>
