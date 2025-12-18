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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . '/MACO.AppLogistica.Web-1';
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
