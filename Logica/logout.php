<?php
require_once __DIR__ . '/../conexionBD/session_config.php';

// Guardar método de autenticación antes de destruir sesión
$authMethod = $_SESSION['auth_method'] ?? 'local';

$_SESSION = [];
session_destroy();

// Eliminar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Si el usuario se autenticó con Microsoft, redirigir al logout de Microsoft
if ($authMethod === 'microsoft') {
    // URL de logout de Microsoft Entra ID
    // post_logout_redirect_uri redirige de vuelta a nuestro login después del logout
    $isAzure = strpos($_SERVER['HTTP_HOST'] ?? '', 'azurewebsites.net') !== false;
    $protocol = $isAzure ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $basePath = $isAzure ? '' : '/MACO.AppLogistica.Web-1';
    $postLogoutUri = urlencode($protocol . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/index.php');
    
    $logoutUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/logout?post_logout_redirect_uri=" . $postLogoutUri;
    header("Location: " . $logoutUrl);
    exit();
}

// Logout local: redirigir al login
header("Location: ../index.php");
exit();