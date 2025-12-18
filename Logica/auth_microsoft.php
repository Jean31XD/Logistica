<?php
/**
 * Iniciar autenticación con Microsoft Entra ID
 * Redirige al usuario a Microsoft para login
 */

session_start();
require_once __DIR__ . '/../conexionBD/conexion.php'; // Para cargar .env

// Configuración de Azure desde variables de entorno
$clientId = getenv('AZURE_CLIENT_ID');
$tenantId = getenv('AZURE_TENANT_ID');

if (empty($clientId) || empty($tenantId)) {
    error_log("Error: AZURE_CLIENT_ID o AZURE_TENANT_ID no configurados en .env");
    header('Location: ../index.php?error=config');
    exit();
}

// Detectar URL base automáticamente
// En Azure, usar HTTPS y detectar si estamos detrás de proxy
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
         || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
         || (strpos($_SERVER['HTTP_HOST'] ?? '', 'azurewebsites.net') !== false);

$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Detectar si estamos en Azure (sin subcarpeta) o local (con subcarpeta)
$isAzure = strpos($host, 'azurewebsites.net') !== false;
$basePath = $isAzure ? '' : '/MACO.AppLogistica.Web-1';

$baseUrl = $protocol . '://' . $host . $basePath;
$redirectUri = $baseUrl . '/Logica/auth_callback.php';

// Generar state para protección CSRF
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;

// Construir URL de autorización de Microsoft
$authUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?" . http_build_query([
    'client_id' => $clientId,
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'response_mode' => 'query',
    'scope' => 'openid profile email User.Read',
    'state' => $state,
    'prompt' => 'select_account' // Permite seleccionar cuenta
]);

// Redirigir a Microsoft
header('Location: ' . $authUrl);
exit();
?>
