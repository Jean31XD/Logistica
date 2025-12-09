<?php
/**
 * Middleware de Autenticación y Autorización
 * Centraliza la verificación de sesión y permisos
 */

require_once __DIR__ . '/security_logger.php';

/**
 * Verifica que el usuario esté autenticado
 * @param array $pantallas_permitidas Array de niveles de pantalla permitidos (ej: [0, 1, 5])
 * @param bool $es_ajax Si es true, retorna JSON en lugar de redireccionar
 */
function verificarAutenticacion($pantallas_permitidas = [], $es_ajax = false) {
    // Iniciar sesión solo si no está activa
    if (session_status() === PHP_SESSION_NONE) {
        // Cargar configuración de sesión antes de iniciarla
        if (!defined('DB_SERVER')) {
            require_once __DIR__ . '/config.php';
        }
        session_start();
    }

    // Verificar que el usuario esté logueado
    if (!isset($_SESSION['usuario'])) {
        registrarEventoSeguridad('acceso_no_autorizado', null, [
            'url' => $_SERVER['REQUEST_URI'] ?? 'desconocido',
            'metodo' => $_SERVER['REQUEST_METHOD'] ?? 'desconocido'
        ]);

        if ($es_ajax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No autorizado. Inicie sesión.']);
            exit;
        } else {
            header('Location: ' . obtenerRutaBase() . '/index.php');
            exit;
        }
    }

    // Verificar permisos de pantalla si se especificaron
    if (!empty($pantallas_permitidas) && !in_array($_SESSION['pantalla'], $pantallas_permitidas, true)) {
        registrarEventoSeguridad('acceso_denegado', $_SESSION['usuario'], [
            'pantalla_usuario' => $_SESSION['pantalla'],
            'pantallas_requeridas' => $pantallas_permitidas,
            'url' => $_SERVER['REQUEST_URI'] ?? 'desconocido'
        ]);

        if ($es_ajax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Acceso denegado. No tiene permisos.']);
            exit;
        } else {
            header('Location: ' . obtenerRutaBase() . '/index.php');
            exit;
        }
    }

    // Regenerar ID de sesión periódicamente para prevenir fijación de sesión
    regenerarSesionPeriodica();

    // Verificar timeout de inactividad
    verificarTimeoutSesion();
}

/**
 * Regenera el ID de sesión cada 5 minutos para seguridad adicional
 */
function regenerarSesionPeriodica($intervalo = 300) {
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > $intervalo) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Verifica el timeout de inactividad de la sesión
 */
function verificarTimeoutSesion($timeout = 1800) { // 30 minutos por defecto
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Sesión expirada por inactividad
        registrarEventoSeguridad('sesion_expirada', $_SESSION['usuario'] ?? 'desconocido', [
            'tiempo_inactividad' => time() - $_SESSION['last_activity']
        ]);

        session_unset();
        session_destroy();

        header('Location: ' . obtenerRutaBase() . '/index.php?error=timeout');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Obtiene la ruta base de la aplicación
 */
function obtenerRutaBase() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);

    // Ajustar para la estructura del proyecto
    $base = rtrim($scriptName, '/');
    if (strpos($base, '/View') !== false) {
        $base = str_replace('/View', '', $base);
    }
    if (strpos($base, '/Logica') !== false) {
        $base = str_replace('/Logica', '', $base);
    }
    if (strpos($base, '/config') !== false) {
        $base = str_replace('/config', '', $base);
    }

    return $protocol . '://' . $host . $base;
}

/**
 * Verifica que la petición sea POST
 */
function verificarMetodoPOST() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }
}

/**
 * Sanitiza una entrada de usuario
 * @param mixed $dato Dato a sanitizar
 * @param string $tipo Tipo de dato esperado
 * @return mixed Dato sanitizado
 */
function sanitizarEntrada($dato, $tipo = 'string') {
    if ($dato === null) {
        return null;
    }

    switch($tipo) {
        case 'int':
            return filter_var($dato, FILTER_VALIDATE_INT);

        case 'float':
            return filter_var($dato, FILTER_VALIDATE_FLOAT);

        case 'email':
            return filter_var($dato, FILTER_VALIDATE_EMAIL);

        case 'url':
            return filter_var($dato, FILTER_VALIDATE_URL);

        case 'boolean':
            return filter_var($dato, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        case 'string':
        default:
            return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Valida un array de parámetros requeridos
 * @param array $parametros Array asociativo de parámetros a validar
 * @param array $requeridos Array de nombres de parámetros requeridos
 * @return array|null Retorna null si todo OK, o array con errores
 */
function validarParametrosRequeridos($parametros, $requeridos) {
    $errores = [];

    foreach ($requeridos as $campo) {
        if (!isset($parametros[$campo]) || trim($parametros[$campo]) === '') {
            $errores[] = "El campo '{$campo}' es requerido";
        }
    }

    return empty($errores) ? null : $errores;
}
?>
