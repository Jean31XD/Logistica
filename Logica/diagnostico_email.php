<?php
/**
 * Script de diagnóstico para el envío de correos con Microsoft Graph
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Diagnóstico de Envío de Correos - Microsoft Graph</h1>";
echo "<hr>";

// Paso 1: Verificar variables de entorno
echo "<h2>1️⃣ Variables de Entorno</h2>";

// Cargar .env si existe
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
    echo "<p style='color:green'>✅ Archivo .env cargado</p>";
} else {
    echo "<p style='color:red'>❌ Archivo .env NO encontrado en: $envPath</p>";
}

$tenantId = getenv('AZURE_TENANT_ID');
$clientId = getenv('AZURE_CLIENT_ID');
$clientSecret = getenv('AZURE_CLIENT_SECRET');

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Variable</th><th>Estado</th><th>Valor (parcial)</th></tr>";

echo "<tr><td>AZURE_TENANT_ID</td>";
echo $tenantId ? "<td style='color:green'>✅ Configurado</td><td>" . substr($tenantId, 0, 8) . "...</td>" : "<td style='color:red'>❌ NO configurado</td><td>-</td>";
echo "</tr>";

echo "<tr><td>AZURE_CLIENT_ID</td>";
echo $clientId ? "<td style='color:green'>✅ Configurado</td><td>" . substr($clientId, 0, 8) . "...</td>" : "<td style='color:red'>❌ NO configurado</td><td>-</td>";
echo "</tr>";

echo "<tr><td>AZURE_CLIENT_SECRET</td>";
echo $clientSecret ? "<td style='color:green'>✅ Configurado</td><td>****" . substr($clientSecret, -4) . "</td>" : "<td style='color:red'>❌ NO configurado</td><td>-</td>";
echo "</tr>";
echo "</table>";

if (!$tenantId || !$clientId || !$clientSecret) {
    echo "<p style='color:red; font-weight:bold'>⛔ Faltan variables de entorno. Configura el archivo .env antes de continuar.</p>";
    exit;
}

// Paso 2: Obtener token de acceso
echo "<h2>2️⃣ Obtención de Token de Acceso</h2>";

$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

$postData = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => 'https://graph.microsoft.com/.default',
    'grant_type' => 'client_credentials'
];

echo "<p>URL del token: <code>$tokenUrl</code></p>";

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p>Código HTTP: <strong>$httpCode</strong></p>";

if ($curlError) {
    echo "<p style='color:red'>❌ Error de CURL: $curlError</p>";
    exit;
}

$tokenData = json_decode($response, true);

if ($httpCode === 200 && isset($tokenData['access_token'])) {
    echo "<p style='color:green'>✅ Token obtenido exitosamente</p>";
    $accessToken = $tokenData['access_token'];
    echo "<p>Token (primeros 50 chars): <code>" . substr($accessToken, 0, 50) . "...</code></p>";
} else {
    echo "<p style='color:red'>❌ Error al obtener token</p>";
    echo "<pre>" . htmlspecialchars(json_encode($tokenData, JSON_PRETTY_PRINT)) . "</pre>";
    exit;
}

// Paso 3: Probar envío de correo
echo "<h2>3️⃣ Prueba de Envío de Correo</h2>";

// Email de prueba - usar el usuario actual de sesión si está disponible
$testEmail = $_GET['email'] ?? 'jean.sencion@corripio.com.do';
$fromEmail = $_GET['from'] ?? 'noreply@corripio.com.do';

echo "<p>📧 Email destino: <strong>$testEmail</strong></p>";
echo "<p>📤 Email remitente: <strong>$fromEmail</strong></p>";
echo "<p><small>(Puedes cambiar agregando ?email=tu@correo.com&from=remitente@correo.com a la URL)</small></p>";

$message = [
    'message' => [
        'subject' => '🧪 Prueba de Correo - MACO Logística',
        'body' => [
            'contentType' => 'HTML',
            'content' => '<h1>Prueba Exitosa</h1><p>Este es un correo de prueba del sistema de verificación de MACO Logística.</p><p>Si recibes este correo, el sistema está funcionando correctamente.</p>'
        ],
        'toRecipients' => [
            [
                'emailAddress' => [
                    'address' => $testEmail
                ]
            ]
        ]
    ],
    'saveToSentItems' => 'false'
];

$graphUrl = "https://graph.microsoft.com/v1.0/users/{$fromEmail}/sendMail";
echo "<p>URL de Graph API: <code>$graphUrl</code></p>";

$ch = curl_init($graphUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($message),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p>Código HTTP: <strong>$httpCode</strong></p>";

if ($curlError) {
    echo "<p style='color:red'>❌ Error de CURL: $curlError</p>";
}

if ($httpCode === 202) {
    echo "<p style='color:green; font-size:1.5em'>✅ ¡CORREO ENVIADO EXITOSAMENTE!</p>";
    echo "<p>Revisa la bandeja de entrada de <strong>$testEmail</strong></p>";
} else {
    echo "<p style='color:red'>❌ Error al enviar correo</p>";
    
    $errorData = json_decode($response, true);
    if ($errorData) {
        echo "<h3>Detalles del Error:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($errorData, JSON_PRETTY_PRINT)) . "</pre>";
        
        // Diagnóstico específico
        if (isset($errorData['error']['code'])) {
            $errorCode = $errorData['error']['code'];
            echo "<h3>🔍 Diagnóstico:</h3>";
            
            switch ($errorCode) {
                case 'Authorization_RequestDenied':
                    echo "<p style='color:orange'>⚠️ <strong>Permisos insuficientes.</strong></p>";
                    echo "<p>Solución: Ve a Azure Portal → App Registrations → Tu App → API Permissions</p>";
                    echo "<ul>";
                    echo "<li>Agrega: Microsoft Graph → Application permissions → <strong>Mail.Send</strong></li>";
                    echo "<li>Haz clic en <strong>'Grant admin consent'</strong></li>";
                    echo "</ul>";
                    break;
                    
                case 'ResourceNotFound':
                    echo "<p style='color:orange'>⚠️ <strong>El email del remitente no existe o no tiene licencia.</strong></p>";
                    echo "<p>El email '<strong>$fromEmail</strong>' debe ser un buzón válido en tu organización.</p>";
                    echo "<p>Opciones:</p>";
                    echo "<ul>";
                    echo "<li>Usa un email de usuario real que exista en tu tenant</li>";
                    echo "<li>Crea un buzón compartido para 'noreply@corripio.com.do'</li>";
                    echo "</ul>";
                    break;
                    
                case 'MailboxNotEnabledForRESTAPI':
                    echo "<p style='color:orange'>⚠️ <strong>El buzón no tiene habilitada la API REST.</strong></p>";
                    echo "<p>El buzón de '<strong>$fromEmail</strong>' necesita una licencia de Exchange Online.</p>";
                    break;
                    
                default:
                    echo "<p>Código de error: <strong>$errorCode</strong></p>";
            }
        }
    } else {
        echo "<p>Respuesta raw: " . htmlspecialchars($response) . "</p>";
    }
}

echo "<hr>";
echo "<h2>📋 Resumen de Configuración Necesaria en Azure</h2>";
echo "<ol>";
echo "<li><strong>Azure Portal</strong> → Azure Active Directory → App registrations</li>";
echo "<li>Selecciona tu aplicación</li>";
echo "<li>API permissions → Add a permission → Microsoft Graph → Application permissions</li>";
echo "<li>Busca y agrega: <strong>Mail.Send</strong></li>";
echo "<li>Haz clic en <strong>'Grant admin consent for [tu tenant]'</strong></li>";
echo "<li>Asegúrate de que el email del remitente existe como buzón en tu organización</li>";
echo "</ol>";
?>
