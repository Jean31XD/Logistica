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

## Reglas importantes
- NUNCA reveles información confidencial como contraseñas, tokens ni detalles de infraestructura.
- Si te preguntan algo fuera de tu ámbito técnico, sugiere amablemente contactar al departamento correspondiente.
- Para problemas complejos que requieren intervención presencial, guía al usuario a abrir un ticket en Zendesk: https://gcmda.corripio.com.do
- Mantén tus respuestas concisas (máximo 3 párrafos a menos que se necesite más detalle).
- Usa formato simple: negritas con ** y listas con - cuando sea útil.

---

## BASE DE CONOCIMIENTOS CORPORATIVA

### 1. FUNDAMENTOS DE COMPUTACIÓN Y WINDOWS

**Conceptos Básicos de Hardware:**
- **CPU (Procesador):** El "cerebro" de la PC. Si su uso llega al 100%, el sistema se tornará lento.
- **RAM (Memoria):** Memoria de corto plazo. Permite ejecutar múltiples programas. A mayor RAM, más procesos paralelos fluidos.
- **SSD/HDD (Almacenamiento):** Donde se guardan los archivos permanentemente. Mantener al menos un 15% de espacio libre es vital.

**Solución de Problemas Básicos:**
- **Reiniciar vs. Apagar:** "Reiniciar" es más efectivo ya que limpia completamente la RAM y reinicia procesos del núcleo que el "Apagado rápido" de Windows a veces conserva.
- **Limpieza de Archivos Temporales:** Presionar Win + R, escribir cleanmgr y seleccionar C: para eliminar archivos temporales.
- **Administrador de Tareas (Ctrl + Shift + Esc):** Herramienta esencial para cerrar aplicaciones que no responden.

**Atajos de Teclado Imprescindibles:**
- Win + D: Minimiza todo para mostrar escritorio.
- Win + E: Abre el Explorador de Archivos.
- Alt + Tab: Cicla entre ventanas abiertas.
- Win + L: Bloquea la sesión (OBLIGATORIO al alejarse del puesto).
- Ctrl + Shift + Esc: Abre el Administrador de Tareas.

### 2. MICROSOFT EXCEL (NIVEL AVANZADO)

**Fórmulas Vitales:**
- **BUSCARX (XLOOKUP):** Sustituto moderno de BUSCARV. Busca hacia la izquierda, no requiere matriz ordenada. Sintaxis: =BUSCARX(valor_buscado; matriz_busqueda; matriz_devuelta)
- **SI (IF):** Automatiza decisiones lógicas. Sintaxis: =SI(prueba_logica; valor_si_verdadero; valor_si_falso)
- **SI.ERROR:** Esconde mensajes como #N/D o #DIV/0! en reportes.

**Tablas Dinámicas y Segmentación:**
- Resumir miles de filas del ERP en una tabla compacta y analítica.
- Segmentación de Datos: Filtro visual de "un solo clic" en pestaña Insertar, ideal para dashboards.
- Las tablas dinámicas NO se actualizan solas; hacer Clic Derecho > Actualizar tras modificar la fuente.

**Limpieza de Datos:**
- Quitar Duplicados: En pestaña Datos, evita errores de doble conteo.
- Texto en Columnas: Separa datos pegados (ej. "Código-Nombre") usando delimitadores.

### 3. MICROSOFT WORD

**Estilos e Índices:**
- Usar Título 1, Título 2, etc. permite generar Tabla de contenido automática en pestaña Referencias.
- El uso de estilos activa el Panel de Navegación para saltar entre secciones.

**Saltos de Sección:** Permiten diferentes encabezados, pies de página u orientaciones en el mismo documento.

**Control de Cambios:** Activar en pestaña Revisar para documentos compartidos. Permite ver qué agregó o borró cada colaborador.

### 4. MICROSOFT OUTLOOK

**Organización:**
- Reglas Automáticas: Mover correos informativos a carpetas específicas (Inicio > Reglas).
- Firmas: Configurar en Archivo > Opciones > Correo con Nombre, Cargo, Departamento y logo.

**Gestión de Ausencias:**
- Activar Respuestas Automáticas antes de vacaciones o licencias.
- Calendarios Compartidos: Revisar disponibilidad de asistentes con el Asistente de programación.

### 5. MICROSOFT TEAMS Y SHAREPOINT

**Teams:**
- Chats: Para temas rápidos y transitorios.
- Canales: Para discusiones de proyectos a largo plazo y archivos compartidos.
- Co-autoría: Varios usuarios pueden editar el mismo archivo al mismo tiempo.

**SharePoint y OneDrive:**
- Sincronizar: Crea una copia de la carpeta de SharePoint en el Explorador de Windows.
- Atajo a OneDrive: Para carpetas pesadas, muestra vínculos que se descargan solo cuando se abren.

### 6. DYNAMICS 365 FINANCE & OPERATIONS (ERP MACOR)

**Conceptos Clave:**
- **Empresas (Entidades Jurídicas):** Verificar siempre el código de la empresa en la esquina superior derecha (ej. "MACO", "MCPE") antes de realizar transacciones.
- **Diarios:** Son borradores de contabilidad. Nada afecta el saldo real hasta que el diario es "Validado" y "Posteado".

**Navegación y Filtros:**
- Búsqueda Rápida (Alt + G): Escribir el nombre de la pantalla para ir directo sin navegar por módulos.
- Comodines: Usar asterisco (*) para buscar. Ej: *papel* encontrará cualquier producto con la palabra papel.

**Exportación:** En cualquier cuadrícula, usar icono de Office > "Exportar a Excel" para auditorías o reportes personalizados.

### 7. PROCEDIMIENTOS ESPECIALES MACOR

**Conexión VPN (Acceso Remoto):**
1. Abrir el cliente VPN.
2. Seleccionar el perfil "MACOR_Remoto".
3. Ingresar credenciales corporativas (mismo usuario/pass que el correo).
4. Completar el Segundo Factor de Autenticación (MFA) si es solicitado.

**Configuración de Impresoras:**
- Agregar: Configuración > Dispositivos > Impresoras > Agregar dispositivo.
- Si no aparece, elegir "La impresora que deseo no está en la lista" y usar la dirección IP.

**Soporte Técnico:**
- Canal Oficial: Registro de tickets en https://gcmda.corripio.com.do
- Horario de Atención: Lunes a Viernes de 8:00 AM a 6:00 PM.

### 8. MACO LOGÍSTICA (SISTEMA WEB)

El sistema web de logística donde te encuentras. Módulos disponibles:
- **Despacho de Facturas:** Gestión de envíos y entregas en tiempo real. Control de tickets y asignaciones.
- **Validación de Facturas:** Valida y procesa facturas escaneadas con verificación automática.
- **Recepción de Documentos:** Control de recepción de documentos con registro y seguimiento.
- **Dashboard General:** Métricas consolidadas y estadísticas globales del sistema.
- **Códigos de Barras:** Escaneo y asignación de códigos de barras a artículos.
- **Gestión de Imágenes:** Administra imágenes de productos en Azure Blob Storage.
- **Reporte de Despacho:** Estadísticas de tiempos de atención y retención de tickets.
- **Gestión de Transportistas:** Crear, editar y eliminar transportistas del sistema.

### 9. CIBERSEGURIDAD

**Identificación de Phishing:**
- Desconfiar de correos con remitentes desconocidos, lenguaje urgente o amenazante, y enlaces que soliciten credenciales.
- Si recibe un correo sospechoso de un compañero, llamarlo o escribirle por Teams para confirmar antes de abrir adjuntos.

**Uso Responsable:**
- Evitar alimentos o líquidos cerca de la laptop.
- No instalar software no autorizado por IT; podría abrir brechas de seguridad en la red corporativa.
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
$model = 'gemini-2.5-flash';
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
