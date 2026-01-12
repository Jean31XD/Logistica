<?php
/**
 * Conexión a Base de Datos - MACO
 * Compatible con Azure App Service y desarrollo local
 * Usa variables de entorno para mayor seguridad
 */

// Detectar si estamos en Azure App Service
$isAzure = !empty(getenv('WEBSITE_SITE_NAME')) || !empty($_SERVER['WEBSITE_SITE_NAME']);

// Cargar variables de entorno desde .env (solo en desarrollo local)
function cargarEnv($archivo = __DIR__ . '/../.env') {
    // Si las variables ya existen (Azure App Settings), no cargar .env
    if (!empty(getenv('DB_PASSWORD'))) {
        return true;
    }
    
    if (!file_exists($archivo)) {
        error_log("Archivo .env no encontrado en: $archivo");
        return false;
    }

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        // Ignorar comentarios
        if (strpos(trim($linea), '#') === 0) {
            continue;
        }

        // Parsear línea
        if (strpos($linea, '=') === false) {
            continue;
        }

        list($clave, $valor) = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        // Guardar en $_ENV
        $_ENV[$clave] = $valor;
        putenv("$clave=$valor");
    }
    return true;
}

// Cargar configuración (solo carga .env si no hay Azure App Settings)
cargarEnv();

// Configuración de conexión
$serverName = getenv('DB_SERVER') ?: 'sdb-apptransportistas-maco.privatelink.database.windows.net';
$database   = getenv('DB_NAME') ?: 'db-apptransportistas-maco';
$username   = getenv('DB_USERNAME') ?: 'ServiceAppTrans';
$password   = getenv('DB_PASSWORD');

if (empty($password)) {
    error_log("ERROR CRÍTICO: DB_PASSWORD no configurada en .env");
    http_response_code(500);
    die("Error de configuración del servidor. Por favor, contacte al administrador.");
}

$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true,
    "CharacterSet" => "UTF-8",
    "MultipleActiveResultSets" => false,
    // Optimizaciones para Azure SQL Serverless
    "LoginTimeout" => 60,            // 60s para cold start (BD pausada puede tardar)
    "ConnectionPooling" => true,     // Reutilizar conexiones existentes
    "ReturnDatesAsStrings" => true,  // Evitar conversión costosa de fechas
    "Encrypt" => true                // Requerido por Azure SQL
);

/**
 * Conexión con reintentos para Azure SQL Serverless
 * La BD puede estar pausada y tarda ~60s en despertar
 */
function conectarConReintentos($serverName, $connectionInfo, $maxReintentos = 3) {
    $conn = false;
    $intento = 0;
    $errores = [];
    
    while ($conn === false && $intento < $maxReintentos) {
        $intento++;
        $conn = @sqlsrv_connect($serverName, $connectionInfo);
        
        if ($conn === false) {
            $errors = sqlsrv_errors();
            $errores[] = "Intento $intento: " . ($errors[0]['message'] ?? 'Error desconocido');
            
            // Verificar si es error de cold start o transitorio
            $errorCode = $errors[0]['code'] ?? 0;
            $esTransitorio = in_array($errorCode, [
                -1,      // Timeout
                10060,   // Connection timed out
                10053,   // Connection reset
                10054,   // Connection closed
                40613,   // Database unavailable
                40197,   // Service error
                40501,   // Service busy
                49918,   // Cannot process request (scaling)
                49919,   // Cannot process create/update
                49920    // Cannot process request
            ]);
            
            if ($esTransitorio && $intento < $maxReintentos) {
                // Esperar antes de reintentar (backoff exponencial)
                $espera = min(pow(2, $intento), 10); // 2, 4, 8... max 10 segundos
                error_log("Azure SQL Serverless: BD posiblemente pausada. Reintentando en {$espera}s...");
                sleep($espera);
            }
        }
    }
    
    if ($conn === false) {
        error_log("Error de conexión a BD Serverless después de $maxReintentos intentos: " . implode(' | ', $errores));
    }
    
    return $conn;
}

// Intentar conexión con reintentos (maneja cold start de Serverless)
$conn = conectarConReintentos($serverName, $connectionInfo);

if ($conn === false) {
    http_response_code(503); // Service Unavailable (más apropiado para BD pausada)
    header('Retry-After: 30');
    die(json_encode([
        'error' => 'Base de datos temporalmente no disponible. Por favor intente en unos segundos.',
        'retry_after' => 30
    ]));
}

// Configurar timeout de queries después de conexión exitosa
sqlsrv_query($conn, "SET LOCK_TIMEOUT 15000"); // 15 segundos
?>
