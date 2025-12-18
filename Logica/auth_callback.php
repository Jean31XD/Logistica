<?php
/**
 * Callback de Microsoft Entra ID
 * Procesa la respuesta de autenticación y crea/busca usuario
 */

session_start();
require_once __DIR__ . '/../conexionBD/conexion.php';

// Configuración de Azure
$clientId = getenv('AZURE_CLIENT_ID') ?: '22655584-42f3-4fbb-acec-42d657113f51';
$clientSecret = getenv('AZURE_CLIENT_SECRET') ?: '07V8Q~zgWnToXFGK45sijoHBYXaOrPrEsqx8nbdx';
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

/**
 * Mostrar error y redirigir
 */
function showError($message) {
    $_SESSION['auth_error'] = $message;
    header('Location: ../index.php?error=auth');
    exit();
}

// Verificar errores de Microsoft
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $description = $_GET['error_description'] ?? 'Error desconocido';
    error_log("Microsoft Auth Error: $error - $description");
    showError("Error de autenticación: " . htmlspecialchars($description));
}

// Verificar que tenemos el código de autorización
if (!isset($_GET['code'])) {
    showError("No se recibió código de autorización");
}

// Verificar state para protección CSRF
$state = $_GET['state'] ?? '';
if (empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    showError("Error de seguridad: state inválido");
}
unset($_SESSION['oauth_state']); // Limpiar state usado

$code = $_GET['code'];

try {
    // 1. Intercambiar código por token
    $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
    
    $tokenData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
        'scope' => 'openid profile email User.Read'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $tokenUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($tokenData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Token exchange failed: HTTP $httpCode - $tokenResponse");
        showError("Error al obtener token de acceso");
    }
    
    $tokenJson = json_decode($tokenResponse, true);
    if (!isset($tokenJson['access_token'])) {
        error_log("No access_token in response: $tokenResponse");
        showError("Respuesta de token inválida");
    }
    
    $accessToken = $tokenJson['access_token'];
    
    // 2. Obtener información del usuario desde Microsoft Graph
    $graphUrl = "https://graph.microsoft.com/v1.0/me";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $graphUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $userResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Graph API failed: HTTP $httpCode - $userResponse");
        showError("Error al obtener información del usuario");
    }
    
    $userInfo = json_decode($userResponse, true);
    
    // Extraer datos del usuario
    $email = $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '';
    $displayName = $userInfo['displayName'] ?? 'Usuario Microsoft';
    $firstName = $userInfo['givenName'] ?? '';
    $lastName = $userInfo['surname'] ?? '';
    
    if (empty($email)) {
        showError("No se pudo obtener el email del usuario");
    }
    
    // 3. Buscar o crear usuario en la base de datos
    // Usar la parte antes del @ como username
    $username = strtolower(explode('@', $email)[0]);
    
    // Buscar usuario existente por username
    $sqlFind = "SELECT usuario, pantalla FROM usuarios WHERE usuario = ?";
    $stmt = sqlsrv_query($conn, $sqlFind, [$username]);
    
    if ($stmt === false) {
        error_log("DB Error finding user: " . print_r(sqlsrv_errors(), true));
        showError("Error de base de datos");
    }
    
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($user) {
        // Usuario existe - usar datos existentes
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['pantalla'] = $user['pantalla'];
        $_SESSION['nombre_completo'] = $displayName;
        $_SESSION['auth_method'] = 'microsoft';
    } else {
        // OPCIÓN B: Crear usuario automáticamente
        // Pantalla por defecto = 1 (usuario básico, el admin puede cambiarlo después)
        $pantallaDefault = 1;
        
        // Generar un password hash aleatorio (no se usará para login Microsoft)
        $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        
        $sqlInsert = "INSERT INTO usuarios (usuario, password, pantalla, email, nombre_completo, auth_method, fecha_creacion) 
                      VALUES (?, ?, ?, ?, ?, 'microsoft', GETDATE())";
        $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$username, $randomPassword, $pantallaDefault, $email, $displayName]);
        
        if ($stmtInsert === false) {
            // Si falla por columnas que no existen, intentar insert simple
            $sqlInsertSimple = "INSERT INTO usuarios (usuario, password, pantalla) VALUES (?, ?, ?)";
            $stmtInsert = sqlsrv_query($conn, $sqlInsertSimple, [$username, $randomPassword, $pantallaDefault]);
            
            if ($stmtInsert === false) {
                error_log("DB Error creating user: " . print_r(sqlsrv_errors(), true));
                showError("Error al crear usuario. Contacte al administrador.");
            }
        }
        
        $_SESSION['usuario'] = $username;
        $_SESSION['pantalla'] = $pantallaDefault;
        $_SESSION['nombre_completo'] = $displayName;
        $_SESSION['auth_method'] = 'microsoft';
        $_SESSION['new_user'] = true; // Flag para mostrar mensaje de bienvenida
    }
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    $_SESSION['ultimo_acceso'] = time();
    
    // Log de acceso exitoso
    error_log("Microsoft login successful: {$_SESSION['usuario']} ({$email})");
    
    // Sincronizar facturas (igual que login normal)
    $sqlSync = "{CALL SyncCustinvoicejour}";
    $stmtSync = sqlsrv_query($conn, $sqlSync);
    if ($stmtSync !== false) {
        sqlsrv_free_stmt($stmtSync);
    }
    
    // Redirigir al portal
    header('Location: ../View/pantallas/Portal.php');
    exit();
    
} catch (Exception $e) {
    error_log("Microsoft Auth Exception: " . $e->getMessage());
    showError("Error inesperado durante la autenticación");
}
?>
