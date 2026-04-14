# AI Chat Widget — Diseño

**Fecha:** 2026-04-14
**Proyecto:** MACO AppLogística Web

---

## Resumen

Agregar un widget de chat con IA en la esquina inferior derecha de todas las pantallas de la aplicación. El chat se conecta al agente Fabric MCP "Técnico MACOR" de Microsoft Fabric usando autenticación Azure AD con `client_credentials`. Las credenciales nunca llegan al browser.

---

## Arquitectura

### Archivos nuevos

| Archivo | Propósito |
|---|---|
| `Logica/chat_proxy.php` | Obtiene token Azure AD y reenvía mensajes al agente Fabric MCP |
| `View/assets/css/ai-chat.css` | Estilos del widget integrados al MACO Design System |
| `View/assets/js/ai-chat.js` | Lógica del widget (abrir/cerrar, enviar, render) |

### Archivo modificado

- `View/templates/footer.php` — incluye HTML del widget + carga `ai-chat.css` y `ai-chat.js`

### Flujo de datos

1. Usuario escribe mensaje → `ai-chat.js` hace `POST` a `Logica/chat_proxy.php`
2. `chat_proxy.php` verifica sesión activa
3. `chat_proxy.php` obtiene/reutiliza token Azure AD (cacheado en `$_SESSION['fabric_token']`)
4. `chat_proxy.php` llama al endpoint Fabric MCP con el token
5. Respuesta JSON devuelta al browser
6. `ai-chat.js` renderiza respuesta en el panel

---

## PHP Proxy (`chat_proxy.php`)

- Requiere `$_SESSION['usuario']` — rechaza si no hay sesión activa (HTTP 401)
- Lee `message` del body JSON del POST (`json_decode(file_get_contents('php://input'))`)
- Token Azure AD: `POST https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token`
  - `grant_type=client_credentials`
  - `client_id=AZURE_CLIENT_ID`
  - `client_secret=AZURE_CLIENT_SECRET`
  - `scope=https://api.fabric.microsoft.com/.default`
- Token cacheado en `$_SESSION['fabric_token']` + `$_SESSION['fabric_token_expires']`
- Endpoint Fabric MCP: `https://api.fabric.microsoft.com/v1/mcp/workspaces/5da098b2-8be7-4497-b125-ae170c045a07/dataagents/04401395-9b65-4c1c-9a1a-337ef470f41a/agent`
- Formato de request al agente: protocolo MCP (`jsonrpc: "2.0"`, método `tools/call` o equivalente que acepte el agente)
- Devuelve `{ "reply": "..." }` al browser

---

## UI del Widget

### Botón flotante (estado cerrado)

- `position: fixed; bottom: 24px; right: 24px`
- Círculo de 56px con ícono `fa-robot` (Font Awesome ya incluido)
- Color de fondo: `var(--maco-primary)`, ícono blanco
- Badge rojo con contador cuando hay mensajes nuevos sin leer

### Panel de chat (estado abierto)

- Aparece sobre el botón con animación `slide-up + fade`
- Dimensiones: `360px` ancho × `480px` alto
- Estructura:
  - **Header:** "Asistente MACOR" + botón × para cerrar
  - **Área de mensajes:** scroll vertical, burbujas diferenciadas (usuario derecha, IA izquierda)
  - **Indicador "escribiendo...":** tres puntos animados mientras espera respuesta
  - **Input:** campo de texto + botón enviar (ícono `fa-paper-plane`)
- En móvil: `width: calc(100vw - 32px)`, panel casi pantalla completa

---

## Seguridad

- Credenciales Azure AD solo en servidor (`.env`), nunca expuestas al browser
- Proxy valida sesión PHP antes de procesar cualquier mensaje
- Input del usuario sanitizado antes de enviarlo al agente
- Sin nuevas dependencias npm/composer

---

## Lo que NO se incluye

- Historial de conversación persistente en base de datos
- Soporte para adjuntar archivos
- Streaming de respuestas (SSE/WebSocket)
- Panel de administración del widget
