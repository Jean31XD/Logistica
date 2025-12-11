<?php
require_once __DIR__ . '/../conexionBD/session_config.php';

$_SESSION = [];
session_destroy();

// Eliminar la cookie de sesión también
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirigir al login
header("Location: ../index.php");
exit();

// ¡LA LLAVE "}" EXTRA QUE ESTABA AQUÍ FUE ELIMINADA!