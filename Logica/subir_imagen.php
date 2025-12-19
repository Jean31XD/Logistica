<?php
/**
 * Lógica para subir imágenes a Azure Blob Storage
 * MACO Logística
 */

// LOG: Inicio del script
error_log("========== subir_imagen.php: INICIO ==========");
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NONE'));
error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'NONE'));

// Desactivar output de errores en pantalla (solo logs)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Iniciar output buffering
ob_start();
error_log("subir_imagen.php: Output buffering iniciado");

// Headers
header('Content-Type: application/json; charset=utf-8');

// Función para enviar respuesta y terminar
function enviarRespuesta($success, $message, $url = null, $code = 200) {
    error_log("enviarRespuesta() llamada: success=$success, code=$code, message=" . substr($message, 0, 100));

    // Limpiar cualquier output
    $buffers_cleared = 0;
    while (ob_get_level() > 0) {
        ob_end_clean();
        $buffers_cleared++;
    }
    error_log("enviarRespuesta(): $buffers_cleared buffers limpiados");

    http_response_code($code);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($url) {
        $response['url'] = $url;
    }

    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log("enviarRespuesta(): JSON generado: " . substr($json, 0, 200));

    echo $json;
    error_log("enviarRespuesta(): JSON enviado, ejecutando exit");
    exit;
}

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Error fatal en subir_imagen.php: " . json_encode($error));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal del servidor: ' . $error['message']
        ]);
    }
});

// Capturar excepciones no manejadas
set_exception_handler(function($e) {
    error_log("Excepción no manejada en subir_imagen.php: " . $e->getMessage());
    enviarRespuesta(false, 'Error: ' . $e->getMessage(), null, 500);
});

try {
    error_log("subir_imagen.php: Entrando al bloque try principal");

    // 1. Cargar sesión
    error_log("subir_imagen.php: Verificando session_config.php");
    if (!file_exists(__DIR__ . '/../conexionBD/session_config.php')) {
        throw new Exception('Archivo session_config.php no encontrado');
    }

    error_log("subir_imagen.php: Cargando session_config.php");
    require_once __DIR__ . '/../conexionBD/session_config.php';
    error_log("subir_imagen.php: session_config.php cargado");

    // 2. Verificar autenticación básica (solo que esté logueado)
    error_log("subir_imagen.php: Verificando autenticación");
    if (!isset($_SESSION['usuario'])) {
        error_log("subir_imagen.php: Usuario no autenticado");
        enviarRespuesta(false, 'No autenticado. Inicie sesión.', null, 401);
    }
    error_log("subir_imagen.php: Autenticación OK - Usuario: " . $_SESSION['usuario']);
    
    // 3. Verificar permisos usando tabla usuario_modulos
    error_log("subir_imagen.php: Verificando permiso del módulo");
    require_once __DIR__ . '/../conexionBD/conexion.php';
    
    $usuario = $_SESSION['usuario'];
    $tienePermiso = false;
    
    $sqlPermiso = "SELECT COUNT(*) as cnt FROM usuario_modulos WHERE usuario = ? AND modulo = 'gestion_imagenes' AND activo = 1";
    $stmtPermiso = sqlsrv_query($conn, $sqlPermiso, [$usuario]);
    
    if ($stmtPermiso) {
        $row = sqlsrv_fetch_array($stmtPermiso, SQLSRV_FETCH_ASSOC);
        $tienePermiso = ($row['cnt'] > 0);
        sqlsrv_free_stmt($stmtPermiso);
    }
    
    if (!$tienePermiso) {
        error_log("subir_imagen.php: Usuario $usuario NO tiene permiso para gestion_imagenes");
        enviarRespuesta(false, 'No tienes permiso para subir imágenes', null, 403);
    }
    error_log("subir_imagen.php: Permiso verificado OK");

    // 3. Validar CSRF token
    error_log("subir_imagen.php: Validando CSRF token");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        error_log("subir_imagen.php: CSRF token recibido: " . substr($csrf, 0, 20) . "...");
        if (!validarTokenCSRF($csrf)) {
            error_log("subir_imagen.php: CSRF token INVÁLIDO");
            enviarRespuesta(false, 'Token CSRF inválido', null, 403);
        }
        error_log("subir_imagen.php: CSRF token VÁLIDO");
    }

    // 4. Cargar Azure SDK
    error_log("subir_imagen.php: Cargando Azure SDK");
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log("subir_imagen.php: ERROR - autoload.php no existe");
        enviarRespuesta(false, 'Azure SDK no instalado', null, 500);
    }

    require_once $autoload;
    require_once __DIR__ . '/../src/azure.php';
    error_log("subir_imagen.php: Azure SDK cargado");

    // 5. Validar archivo recibido
    error_log("subir_imagen.php: Validando archivo");
    error_log("subir_imagen.php: FILES array: " . json_encode(array_keys($_FILES)));
    if (!isset($_FILES['imagen'])) {
        error_log("subir_imagen.php: ERROR - No se recibió archivo 'imagen'");
        enviarRespuesta(false, 'No se recibió el archivo', null, 400);
    }
    error_log("subir_imagen.php: Archivo recibido - size: " . $_FILES['imagen']['size'] . " bytes");

    if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'Archivo muy grande (límite PHP)',
            UPLOAD_ERR_FORM_SIZE => 'Archivo muy grande (límite formulario)',
            UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó archivo'
        ];

        $mensaje = $errores[$_FILES['imagen']['error']] ?? 'Error al subir archivo';
        enviarRespuesta(false, $mensaje, null, 400);
    }

    // 6. Validar SKU
    $itemid = trim($_POST['itemid'] ?? '');
    if (empty($itemid)) {
        enviarRespuesta(false, 'El SKU es obligatorio', null, 400);
    }

    // 7. Validar tipo de archivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        throw new Exception('No se pudo inicializar finfo');
    }

    $mime = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
    finfo_close($finfo);

    if (!$mime) {
        enviarRespuesta(false, 'No se pudo determinar tipo de archivo', null, 400);
    }

    $permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $permitidos)) {
        enviarRespuesta(false, "Tipo no permitido: $mime", null, 400);
    }

    // 8. Validar tamaño (5MB)
    $max = 5 * 1024 * 1024;
    if ($_FILES['imagen']['size'] > $max) {
        $mb = round($_FILES['imagen']['size'] / 1024 / 1024, 2);
        enviarRespuesta(false, "Archivo muy grande: {$mb}MB (máx 5MB)", null, 400);
    }

    // 9. Subir a Azure
    $blob = $itemid . '.jpg';
    $resultado = upload_image_to_azure($_FILES['imagen']['tmp_name'], $blob, $mime);

    // 10. Log y respuesta
    $usuario = $_SESSION['usuario'] ?? 'desconocido';

    if ($resultado['success']) {
        error_log("✓ Imagen subida: $blob por $usuario");
        enviarRespuesta(true, 'Imagen subida correctamente', $resultado['url'] ?? null);
    } else {
        error_log("✗ Error subiendo $blob: " . $resultado['message']);
        enviarRespuesta(false, $resultado['message'], null, 500);
    }

} catch (Exception $e) {
    error_log("Excepción en subir_imagen.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    enviarRespuesta(false, 'Error: ' . $e->getMessage(), null, 500);
}
