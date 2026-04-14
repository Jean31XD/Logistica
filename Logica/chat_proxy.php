<?php
/**
 * Chat Proxy — Agente Técnico MACOR
 *
 * Obtiene token Azure AD y reenvía mensajes al agente Fabric MCP.
 * Solo acepta peticiones de usuarios autenticados.
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../src/autoload.php';

verificarAutenticacion();

// conexion.php llama cargarEnv() automáticamente al incluirse,
// lo que hace getenv() disponible para las variables del .env
require_once __DIR__ . '/../conexionBD/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Leer body JSON
$body    = file_get_contents('php://input');
$payload = json_decode($body, true);
$message = isset($payload['message']) ? trim($payload['message']) : '';

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'El mensaje no puede estar vacío']);
    exit;
}

// Limitar longitud del mensaje
if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'El mensaje es demasiado largo (máx. 2000 caracteres)']);
    exit;
}

// --- Credenciales Azure ---
$tenantId     = getenv('AZURE_TENANT_ID');
$clientId     = getenv('AZURE_CLIENT_ID');
$clientSecret = getenv('AZURE_CLIENT_SECRET');

if (!$tenantId || !$clientId || !$clientSecret) {
    http_response_code(500);
    echo json_encode(['error' => 'Credenciales Azure no configuradas']);
    exit;
}

// --- Obtener / reutilizar token ---
function getAzureToken($tenantId, $clientId, $clientSecret) {
    // Reutilizar token cacheado en sesión si no ha expirado
    if (
        isset($_SESSION['fabric_token'], $_SESSION['fabric_token_expires']) &&
        time() < $_SESSION['fabric_token_expires'] - 60
    ) {
        return $_SESSION['fabric_token'];
    }

    $tokenUrl = 'https://login.microsoftonline.com/' . urlencode($tenantId) . '/oauth2/v2.0/token';

    $postData = http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => 'https://api.fabric.microsoft.com/.default',
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                         "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 15,
            'ignore_errors' => true,
        ]
    ]);

    $response = file_get_contents($tokenUrl, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        error_log('[ChatProxy] Token error: ' . $response);
        return null;
    }

    $_SESSION['fabric_token']         = $data['access_token'];
    $_SESSION['fabric_token_expires'] = time() + ($data['expires_in'] ?? 3600);

    return $data['access_token'];
}

$token = getAzureToken($tenantId, $clientId, $clientSecret);

if (!$token) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo autenticar con Azure AD']);
    exit;
}

// --- Llamar al agente Fabric MCP ---
$agentUrl = 'https://api.fabric.microsoft.com/v1/mcp/workspaces/'
          . '5da098b2-8be7-4497-b125-ae170c045a07/dataagents/'
          . '04401395-9b65-4c1c-9a1a-337ef470f41a/agent';

$mcpPayload = json_encode([
    'jsonrpc' => '2.0',
    'method'  => 'tools/call',
    'id'      => 1,
    'params'  => [
        'name'      => 'DataAgent_T_cnico_MACOR',
        'arguments' => [
            'query' => $message,
        ],
    ],
]);

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Authorization: Bearer $token\r\n" .
                     "Content-Type: application/json\r\n" .
                     "Content-Length: " . strlen($mcpPayload) . "\r\n",
        'content' => $mcpPayload,
        'timeout' => 30,
        'ignore_errors' => true,
    ]
]);

$response = file_get_contents($agentUrl, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar con el agente']);
    exit;
}

$data = json_decode($response, true);

// Extraer el texto de la respuesta MCP
// La estructura puede variar; intentamos los campos más comunes
$reply = null;

if (isset($data['result']['content'][0]['text'])) {
    $reply = $data['result']['content'][0]['text'];
} elseif (isset($data['result']['text'])) {
    $reply = $data['result']['text'];
} elseif (isset($data['result'])) {
    $reply = is_string($data['result']) ? $data['result'] : json_encode($data['result']);
} elseif (isset($data['error']['message'])) {
    error_log('[ChatProxy] MCP error: ' . json_encode($data['error']));
    http_response_code(502);
    echo json_encode(['error' => 'El agente devolvió un error: ' . $data['error']['message']]);
    exit;
} else {
    error_log('[ChatProxy] Respuesta inesperada: ' . $response);
    $reply = 'Respuesta no reconocida del agente.';
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
