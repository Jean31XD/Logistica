<?php
/**
 * Script de prueba para verificar autenticación con Microsoft
 * Prueba el flujo ROPC (Resource Owner Password Credentials)
 */

require_once __DIR__ . '/../conexionBD/conexion.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test de Autenticación Microsoft - ROPC</h2>";
echo "<p><small>Este script prueba si puedes autenticar con Microsoft usando usuario y contraseña directamente.</small></p>";
echo "<hr>";

// Verificar configuración
$tenantId = getenv('AZURE_TENANT_ID');
$clientId = getenv('AZURE_CLIENT_ID');
$clientSecret = getenv('AZURE_CLIENT_SECRET');

echo "<h3>1. Verificar Configuración</h3>";
if (empty($tenantId)) {
    echo "<p style='color:red;'>❌ AZURE_TENANT_ID no configurado</p>";
} else {
    echo "<p style='color:green;'>✅ AZURE_TENANT_ID: " . substr($tenantId, 0, 8) . "...</p>";
}

if (empty($clientId)) {
    echo "<p style='color:red;'>❌ AZURE_CLIENT_ID no configurado</p>";
} else {
    echo "<p style='color:green;'>✅ AZURE_CLIENT_ID: " . substr($clientId, 0, 8) . "...</p>";
}

if (empty($clientSecret)) {
    echo "<p style='color:red;'>❌ AZURE_CLIENT_SECRET no configurado</p>";
} else {
    echo "<p style='color:green;'>✅ AZURE_CLIENT_SECRET configurado</p>";
}

if (empty($tenantId) || empty($clientId) || empty($clientSecret)) {
    die("<p style='color:red;'><strong>Error:</strong> Faltan configuraciones en el archivo .env</p>");
}

echo "<hr>";

// Formulario de prueba
if (!isset($_POST['test_email'])) {
    ?>
    <h3>2. Probar Autenticación</h3>
    <form method="post" style="background:#f5f5f5;padding:20px;border-radius:8px;max-width:500px;">
        <p><strong>Ingresa las credenciales de Microsoft para probar:</strong></p>

        <div style="margin-bottom:15px;">
            <label>Email corporativo:</label><br>
            <input type="email" name="test_email" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;"
                   placeholder="usuario@corripio.com.do">
        </div>

        <div style="margin-bottom:15px;">
            <label>Contraseña:</label><br>
            <input type="password" name="test_password" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
        </div>

        <button type="submit" style="background:#0078D4;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;">
            Probar Autenticación
        </button>
    </form>

    <p style="margin-top:20px;"><small><strong>Nota:</strong> Este test intenta autenticar usando el flujo ROPC. Si falla, puede ser que:</small></p>
    <ul style="font-size:14px;">
        <li>Las credenciales sean incorrectas</li>
        <li>El flujo ROPC no esté habilitado en Azure Portal</li>
        <li>La cuenta tenga MFA activado (el flujo ROPC no soporta MFA)</li>
    </ul>
    <?php
    exit();
}

// Realizar prueba
$testEmail = $_POST['test_email'];
$testPassword = $_POST['test_password'];

echo "<h3>2. Intentando Autenticación</h3>";
echo "<p><strong>Email:</strong> " . htmlspecialchars($testEmail) . "</p>";

$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

$postData = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => 'https://graph.microsoft.com/.default',
    'username' => $testEmail,
    'password' => $testPassword,
    'grant_type' => 'password'
];

echo "<p>Conectando a Microsoft...</p>";

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<hr>";
echo "<h3>3. Resultado</h3>";

if (!empty($curlError)) {
    echo "<div style='background:#fee;padding:15px;border-left:4px solid red;border-radius:4px;'>";
    echo "<p style='color:red;'><strong>❌ Error de conexión</strong></p>";
    echo "<p>Error cURL: " . htmlspecialchars($curlError) . "</p>";
    echo "</div>";
    exit();
}

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

$tokenData = json_decode($response, true);

if ($httpCode === 200 && isset($tokenData['access_token'])) {
    echo "<div style='background:#dfd;padding:15px;border-left:4px solid green;border-radius:4px;'>";
    echo "<p style='color:green;'><strong>✅ AUTENTICACIÓN EXITOSA</strong></p>";
    echo "<p>El usuario se autenticó correctamente con Microsoft.</p>";
    echo "<p><strong>Token recibido:</strong> " . substr($tokenData['access_token'], 0, 30) . "...</p>";
    echo "</div>";
    echo "<hr>";
    echo "<p><strong>La reasignación de tickets debería funcionar correctamente.</strong></p>";
} else {
    echo "<div style='background:#fee;padding:15px;border-left:4px solid red;border-radius:4px;'>";
    echo "<p style='color:red;'><strong>❌ AUTENTICACIÓN FALLIDA</strong></p>";

    $errorCode = $tokenData['error'] ?? 'unknown';
    $errorDesc = $tokenData['error_description'] ?? 'No description';

    echo "<p><strong>Error:</strong> " . htmlspecialchars($errorCode) . "</p>";
    echo "<p><strong>Descripción:</strong> " . htmlspecialchars($errorDesc) . "</p>";

    echo "<hr>";
    echo "<h4>Posibles causas:</h4>";
    echo "<ul>";

    if ($errorCode === 'invalid_grant') {
        echo "<li><strong>Contraseña incorrecta</strong> - Verifique las credenciales</li>";
        echo "<li><strong>Cuenta bloqueada</strong> - La cuenta puede estar bloqueada en Azure AD</li>";
        echo "<li><strong>MFA activado</strong> - El flujo ROPC no funciona con autenticación multifactor</li>";
    } elseif ($errorCode === 'unauthorized_client') {
        echo "<li><strong>ROPC no habilitado</strong> - Debe habilitar 'Allow public client flows' en Azure Portal</li>";
        echo "<li>Ve a: Azure Portal > App registrations > Tu App > Authentication > Advanced settings</li>";
        echo "<li>Habilita: 'Allow public client flows' = YES</li>";
    } elseif ($errorCode === 'invalid_client') {
        echo "<li><strong>Client ID o Secret incorrectos</strong> - Verifica las credenciales en .env</li>";
    } else {
        echo "<li><strong>Error desconocido</strong> - Revisa la configuración de Azure</li>";
    }

    echo "</ul>";
    echo "</div>";

    echo "<hr>";
    echo "<details>";
    echo "<summary><strong>Ver respuesta completa de Microsoft</strong></summary>";
    echo "<pre style='background:#f5f5f5;padding:15px;border-radius:4px;overflow:auto;'>";
    echo htmlspecialchars(json_encode($tokenData, JSON_PRETTY_PRINT));
    echo "</pre>";
    echo "</details>";
}

echo "<hr>";
echo "<p><a href='test_microsoft_auth.php'>← Probar con otras credenciales</a></p>";
?>
