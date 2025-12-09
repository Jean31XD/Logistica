<?php
/**
 * Helper para Protección CSRF (Cross-Site Request Forgery)
 * Genera y valida tokens CSRF para todos los formularios
 */

/**
 * Genera un token CSRF y lo almacena en la sesión
 * @return string Token CSRF generado
 */
function generarTokenCSRF() {
    if (session_status() === PHP_SESSION_NONE) {
        // Cargar configuración de sesión antes de iniciarla
        if (!defined('DB_SERVER')) {
            require_once __DIR__ . '/config.php';
        }
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Genera el HTML del campo hidden para el token CSRF
 * @return string HTML del input hidden
 */
function campoTokenCSRF() {
    $token = generarTokenCSRF();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica que el token CSRF enviado sea válido
 * @param string|null $token_enviado Token recibido del formulario
 * @return bool True si el token es válido
 */
function verificarTokenCSRF($token_enviado = null) {
    if (session_status() === PHP_SESSION_NONE) {
        // Cargar configuración de sesión antes de iniciarla
        if (!defined('DB_SERVER')) {
            require_once __DIR__ . '/config.php';
        }
        session_start();
    }

    // Si no se proporciona el token, intentar obtenerlo de POST
    if ($token_enviado === null) {
        $token_enviado = $_POST['csrf_token'] ?? '';
    }

    // Obtener el token de la sesión
    $token_sesion = $_SESSION['csrf_token'] ?? '';

    // Verificar que ambos existan y sean iguales (usando hash_equals para prevenir timing attacks)
    if (empty($token_enviado) || empty($token_sesion)) {
        return false;
    }

    return hash_equals($token_sesion, $token_enviado);
}

/**
 * Verifica el token CSRF y termina la ejecución si es inválido
 * @param bool $es_ajax Si es true, retorna JSON; si es false, redirecciona
 */
function validarCSRF($es_ajax = false) {
    if (!verificarTokenCSRF()) {
        require_once __DIR__ . '/security_logger.php';
        registrarEventoSeguridad('csrf_invalido', $_SESSION['usuario'] ?? 'desconocido', [
            'url' => $_SERVER['REQUEST_URI'] ?? 'desconocido',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconocido'
        ]);

        if ($es_ajax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido. Recargue la página.']);
        } else {
            http_response_code(403);
            die('<h1>Error 403 - Forbidden</h1><p>Token de seguridad inválido. <a href="javascript:history.back()">Volver</a></p>');
        }
        exit;
    }
}

/**
 * Regenera un nuevo token CSRF (útil después de operaciones críticas)
 */
function regenerarTokenCSRF() {
    if (session_status() === PHP_SESSION_NONE) {
        // Cargar configuración de sesión antes de iniciarla
        if (!defined('DB_SERVER')) {
            require_once __DIR__ . '/config.php';
        }
        session_start();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Genera un token CSRF específico para AJAX requests
 * @return array Array con el token y su nombre
 */
function obtenerTokenCSRFParaAjax() {
    return [
        'token' => generarTokenCSRF(),
        'header_name' => 'X-CSRF-Token'
    ];
}
?>
