<?php
/**
 * Conexión a Base de Datos - MACO
 * Usa variables de entorno para mayor seguridad
 */

// Cargar variables de entorno desde .env
function cargarEnv($archivo = __DIR__ . '/../.env') {
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

// Cargar configuración
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
    "MultipleActiveResultSets" => false
);

// Intentar conexión
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    // Log error interno (NO mostrar al usuario)
    error_log("Error de conexión a BD: " . print_r(sqlsrv_errors(), true));

    // Mensaje genérico al usuario (sin detalles técnicos)
    http_response_code(500);
    die("Error de conexión con el servidor. Por favor, contacte al administrador.");
}

// Conexión exitosa (no hacer echo en producción)
?>
