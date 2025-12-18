<?php
/**
 * Iniciar autenticación con Microsoft Entra ID
 * Redirige al usuario a Microsoft para login
 */

session_start();

// Configuración de Azure (se puede mover a .env)
$clientId = getenv('AZURE_CLIENT_ID') ?: '22655584-42f3-4fbb-acec-42d657113f51';
// Tenant de Grupo Corripio (corregido)
$tenantId = '02b9b7a6-8935-407f-8797-f17aa9838e3b';

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
