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

### 8. MACO LOGÍSTICA — GUÍA COMPLETA DEL SISTEMA

**MACO Logística** es el sistema web interno donde te encuentras ahora mismo. Es una aplicación desarrollada en PHP que gestiona toda la cadena logística de la empresa Corripio.

**URL del sistema:** https://app-apptransportistas-web-g3fgcmegd3cedteh.eastus-01.azurewebsites.net
**Versión actual:** 2.5.0

**Inicio de Sesión:**
- El acceso es exclusivamente mediante cuenta corporativa de Microsoft (Azure AD / Entra ID).
- Al hacer clic en "Continuar con Microsoft", el sistema redirige a la autenticación oficial de Microsoft.
- Si el usuario ve un error de autenticación, debe verificar que su cuenta corporativa esté activa y contactar a IT si persiste.
- No existe la opción de usuario y contraseña manual; todo es a través de Microsoft.

**Portal Principal (después del login):**
- Tras iniciar sesión, el usuario ve su Portal personalizado con los módulos que tiene asignados.
- Solo aparecen los módulos a los que el administrador le ha dado acceso.
- Si no se ven módulos, debe contactar al administrador para que se los asigne.

**Roles del Sistema (determinan a qué pantalla redirige el login):**
- Código 0: Administrador (acceso total)
- Código 1: Gestión
- Código 2: Facturas
- Código 3: CXC (Cuentas por Cobrar)
- Código 5: Panel Admin
- Código 6: BI (Business Intelligence)
- Código 8: Etiquetas
- Código 9: Dashboard
- Código 10: Inventario
- Código 11: Códigos de Barras
- Código 12: Códigos de Referencia
- Código 13: Gestión de Imágenes

**Módulos Detallados:**

1. **Despacho de Facturas** — El módulo principal de operaciones diarias.
   - Permite asignar tickets de despacho a transportistas.
   - Se pueden buscar facturas por número, cliente o fecha.
   - Los estados de una factura son: Pendiente → Despachado → Entregado.
   - También existen estados especiales: NC (Nota de Crédito) y Reversado.
   - Si una factura no aparece, verificar que la empresa seleccionada en el ERP (Dynamics 365) sea la correcta.

2. **Validación de Facturas** — Para verificar facturas escaneadas.
   - Escanear el código de barras de la factura física.
   - El sistema compara contra la base de datos y confirma si es válida.
   - Si marca error, puede ser que la factura ya fue procesada o el código está dañado.

3. **Recepción de Documentos** — Control de recepción.
   - Registra la llegada de documentos físicos al almacén.
   - Permite marcar usuario y fecha de recepción.

4. **Dashboard General** — Panel de métricas y estadísticas.
   - Muestra KPIs: total de facturas, pendientes, despachadas, entregadas.
   - Gráficos de rendimiento por transportista.
   - Filtros por fecha, empresa y transportista.
   - Pestaña "Camiones": Detalle de entregas por camión con efectividad por transportista.
   - Pestaña "Rendimiento": Tiempos promedio de atención.
   - Para exportar datos, usar el botón de descarga CSV/Excel en cada sección.

5. **Códigos de Barras** — Escaneo y asignación.
   - Permite asignar códigos de barras a artículos del inventario.
   - Usa el lector de código de barras conectado por USB.
   - Si el lector no funciona, verificar que esté en modo "emulación de teclado".

6. **Códigos de Referencia** — Visualización y exportación.
   - Lista completa de todos los códigos de barras asignados.
   - Permite exportar a Excel para auditorías.
   - Búsqueda por código, descripción o referencia.

7. **Gestión de Imágenes** — Administración de fotos de productos.
   - Sube imágenes a Azure Blob Storage.
   - Formatos aceptados: JPG, JPEG, PNG, GIF (máximo 10MB).
   - Las imágenes se almacenan en el contenedor "imagenes-productos" de Azure.

8. **Business Intelligence (BI)** — Reportes avanzados.
   - Reporte de facturas recibidas con análisis detallado.
   - Permite filtrar por rango de fechas y empresa.

9. **Sistema de Etiquetado** — Gestión de etiquetas.
   - Crear, modificar y eliminar etiquetas del sistema.

10. **Inventario (Listo)** — Control de inventario de Listo Ferretería.

11. **Gestión de Usuarios** — Solo para administradores.
    - Crear nuevos usuarios y asignarles módulos.
    - Cambiar roles y permisos.
    - Desactivar cuentas.

12. **Gestión de Transportistas** — Administración de conductores.
    - Crear, editar y eliminar transportistas.
    - Asignar datos de contacto y vehículo.

13. **Reporte de Despacho** — Estadísticas de tiempos.
    - Tiempos de atención y retención de tickets.
    - Análisis por usuario despachador.

**Navegación General del Sistema:**
- La barra superior muestra: Logo MACO, botón Inicio, nombre del usuario, rol, y botón Salir.
- El botón "Inicio" siempre regresa al Portal de módulos.
- La sesión expira después de 30 minutos de inactividad.
- Al expirar, se redirige automáticamente al login.

**Problemas Comunes y Soluciones:**

- **"No puedo ver ningún módulo":** Tu administrador no te ha asignado módulos. Contacta a tu supervisor o al administrador del sistema.
- **"La sesión se cierra sola":** La sesión expira por inactividad (30 minutos). Esto es por seguridad. Inicia sesión de nuevo.
- **"Error al iniciar sesión con Microsoft":** Verifica que tu cuenta corporativa esté activa. Si persiste, abre un ticket en Zendesk.
- **"No puedo exportar datos":** Verifica que no tengas un bloqueador de ventanas emergentes activo en tu navegador.
- **"La página se ve rara o descuadrada":** Presiona Ctrl + F5 para forzar la recarga. Si persiste, prueba en otro navegador (Chrome recomendado).
- **"No encuentro una factura":** Verifica la empresa seleccionada en el filtro. Las facturas están separadas por entidad jurídica (MACO, MCPE, etc.).

### 9. CIBERSEGURIDAD

**Identificación de Phishing:**
- Desconfiar de correos con remitentes desconocidos, lenguaje urgente o amenazante, y enlaces que soliciten credenciales.
- Si recibe un correo sospechoso de un compañero, llamarlo o escribirle por Teams para confirmar antes de abrir adjuntos.

**Uso Responsable:**
- Evitar alimentos o líquidos cerca de la laptop.
- No instalar software no autorizado por IT; podría abrir brechas de seguridad en la red corporativa.
- Siempre bloquear la sesión (Win + L) al alejarse del puesto de trabajo.

### 10. CONTACTO Y SOPORTE

- **Soporte Técnico IT:** Registrar ticket en https://gcmda.corripio.com.do
- **Horario:** Lunes a Viernes, 8:00 AM – 6:00 PM
- **Emergencias fuera de horario:** Contactar al supervisor directo.
- **Desarrollador del sistema MACO Logística:** Departamento de IT - Desarrollo.
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

// --- Llamar a Gemini API con fallback automático ---
$models = [
    'gemini-2.5-flash',       // Primario: más potente
    'gemini-2.5-flash-lite',  // Fallback 1: ligero y rápido
    'gemini-2.0-flash',       // Fallback 2: versión estable
    'gemini-2.0-flash-lite',  // Fallback 3: versión ligera
];

$requestPayload = [
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
];

$requestBody = json_encode($requestPayload);
$data = null;
$response = null;
$usedModel = null;

foreach ($models as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $requestBody,
            'timeout' => 30,
            'ignore_errors' => true,
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("[ChatProxy] No se pudo conectar con modelo: {$model}");
        continue; // Intentar siguiente modelo
    }

    $data = json_decode($response, true);

    // Si es rate limit (429) o servicio no disponible (503), probar siguiente modelo
    if (isset($data['error'])) {
        $errorCode = $data['error']['code'] ?? 500;
        if ($errorCode === 429 || $errorCode === 503) {
            error_log("[ChatProxy] Modelo {$model} no disponible (code {$errorCode}), intentando siguiente...");
            continue; // Intentar siguiente modelo
        }
    }

    // Si llegamos aquí, el modelo respondió (con éxito o error no-429)
    $usedModel = $model;
    break;
}

// Si ningún modelo respondió
if ($usedModel === null) {
    echo json_encode([
        'reply' => 'Todos los modelos de IA están temporalmente ocupados. Por favor intenta de nuevo en unos segundos.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Manejar errores que no sean rate limit
if (isset($data['error'])) {
    $errorCode = $data['error']['code'] ?? 500;
    $errorMsg  = $data['error']['message'] ?? 'Error desconocido';
    error_log("[ChatProxy] Gemini error en {$usedModel} ({$errorCode}): {$errorMsg}");
    http_response_code(502);
    echo json_encode(['error' => 'El asistente no pudo procesar la solicitud.']);
    exit;
}

// Extraer la respuesta
$reply = null;

if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = $data['candidates'][0]['content']['parts'][0]['text'];
} elseif (isset($data['candidates'][0]['content']['parts'])) {
    $parts = $data['candidates'][0]['content']['parts'];
    $reply = implode('', array_column($parts, 'text'));
}

if (!$reply) {
    if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
        $reply = 'No puedo responder a esa consulta. Por favor reformula tu pregunta.';
    } else {
        error_log('[ChatProxy] Respuesta inesperada de ' . $usedModel . ': ' . $response);
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

