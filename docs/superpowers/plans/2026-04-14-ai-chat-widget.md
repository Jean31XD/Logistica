# AI Chat Widget — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a floating AI chat widget (bottom-right corner) to all screens, connected to the Microsoft Fabric MCP agent "Técnico MACOR" via a PHP proxy with Azure AD authentication.

**Architecture:** A PHP proxy (`Logica/chat_proxy.php`) handles Azure AD token acquisition and forwards messages to the Fabric MCP endpoint — credentials never reach the browser. The widget HTML/CSS/JS is loaded globally via `View/templates/footer.php` and `View/templates/header.php`. The JS calls the proxy via `fetch()` using a PHP-injected base URL.

**Tech Stack:** PHP (vanilla, `file_get_contents` + stream context), Vanilla JS (ES6), Bootstrap 5.3, Font Awesome 6.5, MACO Design System CSS variables, Azure AD `client_credentials` flow, Microsoft Fabric MCP JSON-RPC 2.0.

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `assets/css/ai-chat.css` | Widget styles using MACO design tokens |
| Create | `assets/js/ai-chat.js` | Widget open/close, send message, render bubbles |
| Create | `Logica/chat_proxy.php` | Azure AD token + Fabric MCP call |
| Modify | `View/templates/header.php` | Load `ai-chat.css` globally |
| Modify | `View/templates/footer.php` | Widget HTML + inject base URL + load `ai-chat.js` |

---

## Task 1: Widget CSS

**Files:**
- Create: `assets/css/ai-chat.css`

- [ ] **Step 1: Create the CSS file**

```css
/* ============================================
   AI Chat Widget — MACO Design System
   ============================================ */

/* Botón flotante */
#ai-chat-toggle {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    cursor: pointer;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    z-index: 9999;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#ai-chat-toggle:hover {
    transform: scale(1.08);
    box-shadow: var(--shadow-xl);
}

#ai-chat-toggle .ai-chat-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    background: var(--danger);
    border-radius: 50%;
    font-size: 0.65rem;
    font-weight: 700;
    display: none;
    align-items: center;
    justify-content: center;
    color: #fff;
}

#ai-chat-toggle .ai-chat-badge.visible {
    display: flex;
}

/* Panel de chat */
#ai-chat-panel {
    position: fixed;
    bottom: 92px;
    right: 24px;
    width: 360px;
    height: 480px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--shadow-xl);
    display: flex;
    flex-direction: column;
    z-index: 9998;
    overflow: hidden;
    transform: translateY(20px);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

#ai-chat-panel.open {
    transform: translateY(0);
    opacity: 1;
    pointer-events: all;
}

/* Header del panel */
.ai-chat-header {
    padding: 14px 16px;
    background: var(--primary);
    color: var(--text-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.ai-chat-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 0.95rem;
}

.ai-chat-close {
    background: none;
    border: none;
    color: var(--text-light);
    font-size: 1.1rem;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    opacity: 0.8;
    transition: opacity 0.15s;
}

.ai-chat-close:hover { opacity: 1; }

/* Área de mensajes */
.ai-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: var(--bg-secondary);
}

.ai-chat-messages::-webkit-scrollbar { width: 4px; }
.ai-chat-messages::-webkit-scrollbar-track { background: transparent; }
.ai-chat-messages::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 2px; }

/* Burbujas */
.ai-bubble {
    max-width: 80%;
    padding: 9px 13px;
    border-radius: 14px;
    font-size: 0.875rem;
    line-height: 1.5;
    word-break: break-word;
}

.ai-bubble.user {
    align-self: flex-end;
    background: var(--primary);
    color: var(--text-light);
    border-bottom-right-radius: 4px;
}

.ai-bubble.bot {
    align-self: flex-start;
    background: var(--bg-primary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-bottom-left-radius: 4px;
    box-shadow: var(--shadow-sm);
}

/* Indicador "escribiendo..." */
.ai-typing {
    align-self: flex-start;
    display: flex;
    gap: 4px;
    padding: 10px 14px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    border-bottom-left-radius: 4px;
}

.ai-typing span {
    width: 7px;
    height: 7px;
    background: var(--gray-400);
    border-radius: 50%;
    animation: ai-bounce 1.2s infinite;
}

.ai-typing span:nth-child(2) { animation-delay: 0.2s; }
.ai-typing span:nth-child(3) { animation-delay: 0.4s; }

@keyframes ai-bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

/* Input */
.ai-chat-footer {
    padding: 12px;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 8px;
    flex-shrink: 0;
    background: var(--bg-primary);
}

.ai-chat-input {
    flex: 1;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.875rem;
    font-family: inherit;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
    background: var(--bg-secondary);
    color: var(--text-primary);
    max-height: 80px;
    overflow-y: auto;
}

.ai-chat-input:focus {
    border-color: var(--primary);
}

.ai-chat-send {
    width: 38px;
    height: 38px;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s;
}

.ai-chat-send:hover { background: var(--primary-dark); }
.ai-chat-send:disabled { background: var(--gray-300); cursor: not-allowed; }

/* Responsive: móvil */
@media (max-width: 480px) {
    #ai-chat-panel {
        width: calc(100vw - 32px);
        right: 16px;
        bottom: 84px;
    }

    #ai-chat-toggle {
        right: 16px;
        bottom: 16px;
    }
}
```

- [ ] **Step 2: Verificar en navegador**

Abrir cualquier pantalla de la app (después de los pasos 3 y 4). El CSS no tiene efecto visible aún — solo confirmar que no hay errores 404 en la pestaña Network del DevTools.

- [ ] **Step 3: Commit**

```bash
git add assets/css/ai-chat.css
git commit -m "feat: add AI chat widget CSS"
```

---

## Task 2: Widget JavaScript

**Files:**
- Create: `assets/js/ai-chat.js`

- [ ] **Step 1: Crear el archivo JS**

```js
/**
 * AI Chat Widget — MACO Logística
 * Conecta con el agente Técnico MACOR via chat_proxy.php
 */
(function () {
    'use strict';

    const toggle   = document.getElementById('ai-chat-toggle');
    const panel    = document.getElementById('ai-chat-panel');
    const closeBtn = document.getElementById('ai-chat-close');
    const messages = document.getElementById('ai-chat-messages');
    const input    = document.getElementById('ai-chat-input');
    const sendBtn  = document.getElementById('ai-chat-send');
    const badge    = document.getElementById('ai-chat-badge');

    // Inyectado por footer.php
    const PROXY_URL = window.AI_CHAT_PROXY_URL || '/Logica/chat_proxy.php';

    let isOpen    = false;
    let unreadCount = 0;

    // --- Abrir / cerrar ---
    function openPanel() {
        isOpen = true;
        panel.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        input.focus();
        clearBadge();
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function clearBadge() {
        unreadCount = 0;
        badge.classList.remove('visible');
        badge.textContent = '';
    }

    toggle.addEventListener('click', function () {
        isOpen ? closePanel() : openPanel();
    });

    closeBtn.addEventListener('click', closePanel);

    // Cerrar con Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closePanel();
    });

    // --- Burbujas ---
    function addBubble(text, role) {
        var div = document.createElement('div');
        div.className = 'ai-bubble ' + role;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function showTyping() {
        var el = document.createElement('div');
        el.className = 'ai-typing';
        el.id = 'ai-typing-indicator';
        el.innerHTML = '<span></span><span></span><span></span>';
        messages.appendChild(el);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping() {
        var el = document.getElementById('ai-typing-indicator');
        if (el) el.remove();
    }

    // --- Enviar mensaje ---
    function sendMessage() {
        var text = input.value.trim();
        if (!text) return;

        addBubble(text, 'user');
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        showTyping();

        fetch(PROXY_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            removeTyping();
            var reply = data.reply || 'Sin respuesta del agente.';
            addBubble(reply, 'bot');
            if (!isOpen) {
                unreadCount++;
                badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                badge.classList.add('visible');
            }
        })
        .catch(function (err) {
            removeTyping();
            addBubble('Error al conectar con el asistente. Intenta de nuevo.', 'bot');
            console.error('[AI Chat]', err);
        })
        .finally(function () {
            sendBtn.disabled = false;
            input.focus();
        });
    }

    sendBtn.addEventListener('click', sendMessage);

    // Enter para enviar (Shift+Enter = nueva línea)
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });

    // Mensaje de bienvenida al abrir por primera vez
    var welcomed = false;
    toggle.addEventListener('click', function () {
        if (!welcomed && isOpen) {
            welcomed = true;
            addBubble('Hola, soy el Asistente Técnico MACOR. ¿En qué te puedo ayudar?', 'bot');
        }
    });

}());
```

- [ ] **Step 2: Verificar en navegador**

Abrir DevTools → Console. No deben aparecer errores. El botón flotante rojo con ícono de robot debe ser visible. Hacer clic lo abre, Escape lo cierra.

- [ ] **Step 3: Commit**

```bash
git add assets/js/ai-chat.js
git commit -m "feat: add AI chat widget JS"
```

---

## Task 3: PHP Proxy

**Files:**
- Create: `Logica/chat_proxy.php`

- [ ] **Step 1: Crear el proxy**

```php
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
```

- [ ] **Step 2: Verificar manualmente el proxy**

Desde el navegador (con sesión iniciada), abrir DevTools → Console y ejecutar:

```js
fetch('/Logica/chat_proxy.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({message: '¿Qué puedes hacer?'})
}).then(r => r.json()).then(console.log)
```

Resultado esperado: `{ reply: "..." }` con una respuesta del agente.

Si el proxy devuelve `{"error":"Credenciales Azure no configuradas"}`, verificar que el `.env` tiene `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`.

Si devuelve `{"error":"No se pudo autenticar con Azure AD"}`, revisar `logs/php_errors.log` — el mensaje de error del token estará ahí.

> **Nota:** Si la estructura de la respuesta del agente no coincide, ajustar la lógica de extracción `$reply` en `chat_proxy.php` según lo que retorne el agente real.

- [ ] **Step 3: Commit**

```bash
git add Logica/chat_proxy.php
git commit -m "feat: add Fabric MCP proxy with Azure AD auth"
```

---

## Task 4: Cargar CSS en header.php

**Files:**
- Modify: `View/templates/header.php:78`

- [ ] **Step 1: Agregar link al CSS del chat después del Design System**

En `View/templates/header.php`, después de la línea:
```php
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/maco-design-system.css">
```

Agregar:
```php
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/ai-chat.css">
```

- [ ] **Step 2: Verificar en navegador**

Recargar cualquier pantalla. En DevTools → Network filtrar por `ai-chat.css` — debe cargar con status 200.

- [ ] **Step 3: Commit**

```bash
git add View/templates/header.php
git commit -m "feat: load AI chat CSS globally in header"
```

---

## Task 5: Widget HTML en footer.php

**Files:**
- Modify: `View/templates/footer.php`

- [ ] **Step 1: Agregar HTML del widget y script antes de `</body>`**

En `View/templates/footer.php`, antes de `</body>` (al final del archivo, después de Bootstrap JS y `$additionalJS`), agregar:

```php
<!-- AI Chat Widget -->
<script>
window.AI_CHAT_PROXY_URL = '<?= $basePath ?>/Logica/chat_proxy.php';
</script>

<button id="ai-chat-toggle"
        aria-label="Abrir asistente virtual"
        aria-expanded="false"
        aria-controls="ai-chat-panel"
        title="Asistente Técnico MACOR">
    <i class="fas fa-robot" aria-hidden="true"></i>
    <span class="ai-chat-badge" id="ai-chat-badge" aria-live="polite"></span>
</button>

<div id="ai-chat-panel" role="dialog" aria-modal="true" aria-label="Asistente Técnico MACOR">
    <div class="ai-chat-header">
        <div class="ai-chat-header-title">
            <i class="fas fa-robot" aria-hidden="true"></i>
            <span>Asistente MACOR</span>
        </div>
        <button class="ai-chat-close" id="ai-chat-close" aria-label="Cerrar chat">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <div class="ai-chat-messages" id="ai-chat-messages" role="log" aria-live="polite"></div>
    <div class="ai-chat-footer">
        <textarea
            class="ai-chat-input"
            id="ai-chat-input"
            placeholder="Escribe tu pregunta..."
            rows="1"
            aria-label="Mensaje al asistente"
            maxlength="2000"
        ></textarea>
        <button class="ai-chat-send" id="ai-chat-send" aria-label="Enviar mensaje">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
        </button>
    </div>
</div>

<script src="<?= $assetsPath ?>/js/ai-chat.js"></script>
```

- [ ] **Step 2: Verificar integración completa**

1. Abrir cualquier pantalla (ej. Dashboard)
2. El botón rojo con robot debe aparecer en esquina inferior derecha
3. Al hacer clic → panel se abre con animación
4. Escribir "Hola" → aparece mensaje de bienvenida del bot, luego la respuesta real del agente
5. Verificar en móvil (o DevTools mobile mode) que el panel ocupa casi toda la pantalla

- [ ] **Step 3: Commit**

```bash
git add View/templates/footer.php
git commit -m "feat: add AI chat widget HTML to global footer"
```

---

## Notas de ajuste post-implementación

Si el agente Fabric devuelve la respuesta en un campo distinto al esperado, la lógica de extracción en `chat_proxy.php` (sección "Extraer el texto de la respuesta MCP") debe ajustarse. Loguear `$response` temporalmente para inspeccionar la estructura real:

```php
error_log('[ChatProxy] Raw response: ' . $response);
```
