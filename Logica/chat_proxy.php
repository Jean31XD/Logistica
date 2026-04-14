<?php
/**
 * Chat Proxy — Asistente Técnico MACOR (Powered by Gemini)
 *
 * Reenvía mensajes del chat widget al API de Google Gemini.
 * Solo acepta peticiones de usuarios autenticados.
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../src/autoload.php';

verificarAutenticacion();

// Rate limiting
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('chat_proxy', 10, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiadas solicitudes. Espere un momento.']);
    exit;
}

// Cargar variables de entorno
if (empty(getenv('GEMINI_API_KEY'))) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            [$key, $val] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($val));
            $_ENV[trim($key)] = trim($val);
        }
    }
}

header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Validar CSRF token
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validarTokenCSRF($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
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

if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'El mensaje es demasiado largo (máx. 2000 caracteres)']);
    exit;
}

// --- Credenciales Gemini ---
$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'API Key de Gemini no configurada']);
    exit;
}

// --- System Prompt del Asistente ---
$systemPrompt = <<<'PROMPT'
Eres el **Asistente Técnico MACOR**, un agente de soporte técnico interno para los empleados de la empresa Corripio (MACOR - Manufactura y Comercio).

## Tu personalidad
- Eres profesional, amable y eficiente.
- Respondes en español con tono profesional pero cercano.
- Vas directo al punto sin ser demasiado breve.
- Si no sabes algo con certeza, lo indicas honestamente.

## Tus áreas de especialización
1. **Windows y PC**: Solución de problemas comunes, configuración de impresoras, red, VPN, conectividad.
2. **Microsoft Office**: Excel (fórmulas, tablas dinámicas, macros), Word, PowerPoint, Outlook, Teams.
3. **ERP y Sistemas Internos**: Navegación básica en Dynamics 365, reportes, consultas.
4. **MACO Logística**: El sistema web de logística donde te encuentras. Módulos: Despacho de Facturas, Validación, Recepción de Documentos, Dashboard, Códigos de Barras, Gestión de Imágenes, Reportes.
5. **Ciberseguridad**: Buenas prácticas de contraseñas, phishing, protección de datos.
6. **Procedimientos internos**: Apertura de tickets en Zendesk (gcmda.corripio.com.do), solicitudes de soporte.

## Reglas importantes
- NUNCA reveles información confidencial como contraseñas, tokens ni detalles de infraestructura.
- Si te preguntan algo fuera de tu ámbito técnico, sugiere amablemente contactar al departamento correspondiente.
- Para problemas complejos que requieren intervención presencial, guía al usuario a abrir un ticket en Zendesk: https://gcmda.corripio.com.do
- Mantén tus respuestas concisas (máximo 3 párrafos a menos que se necesite más detalle).
- Usa formato simple: negritas con ** y listas con - cuando sea útil.
PROMPT;

// --- Historial de conversación (máximo 10 intercambios) ---
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Construir el array de contents para Gemini
$contents = [];

// Agregar historial previo
foreach ($_SESSION['chat_history'] as $entry) {
    $contents[] = ['role' => 'user',  'parts' => [['text' => $entry['user']]]];
    $contents[] = ['role' => 'model', 'parts' => [['text' => $entry['model']]]];
}

// Agregar mensaje actual del usuario
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

// --- Llamar a Gemini API ---
$model = 'gemini-2.0-flash';
$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$requestBody = json_encode([
    'system_instruction' => [
        'parts' => [['text' => $systemPrompt]]
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature'     => 0.7,
        'topP'            => 0.95,
        'maxOutputTokens' => 1024,
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',       'threshold' => 'BLOCK_ONLY_HIGH'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',      'threshold' => 'BLOCK_ONLY_HIGH'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
    ],
]);

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $requestBody,
        'timeout' => 30,
        'ignore_errors' => true,
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar con el servicio de IA']);
    exit;
}

$data = json_decode($response, true);

// Manejar errores de la API
if (isset($data['error'])) {
    $errorCode = $data['error']['code'] ?? 500;
    $errorMsg  = $data['error']['message'] ?? 'Error desconocido';

    error_log("[ChatProxy] Gemini error ({$errorCode}): {$errorMsg}");

    // Si es rate limit, dar mensaje amigable
    if ($errorCode === 429) {
        echo json_encode([
            'reply' => 'El servicio está temporalmente ocupado. Por favor intenta de nuevo en unos segundos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(502);
    echo json_encode(['error' => 'El asistente no pudo procesar la solicitud.']);
    exit;
}

// Extraer la respuesta
$reply = null;

if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = $data['candidates'][0]['content']['parts'][0]['text'];
} elseif (isset($data['candidates'][0]['content']['parts'])) {
    // Concatenar todas las partes de texto
    $parts = $data['candidates'][0]['content']['parts'];
    $reply = implode('', array_column($parts, 'text'));
}

if (!$reply) {
    // Verificar si fue bloqueado por seguridad
    if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
        $reply = 'No puedo responder a esa consulta. Por favor reformula tu pregunta.';
    } else {
        error_log('[ChatProxy] Respuesta inesperada: ' . $response);
        $reply = 'No pude generar una respuesta. Por favor intenta de nuevo.';
    }
}

// Guardar en historial de sesión (máximo 10 intercambios)
$_SESSION['chat_history'][] = [
    'user'  => $message,
    'model' => $reply,
];

// Mantener solo los últimos 10 intercambios
if (count($_SESSION['chat_history']) > 10) {
    $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
