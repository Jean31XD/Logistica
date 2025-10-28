<?php
// PR-Catalogo/src/bootstrap.php

/*
|--------------------------------------------------------------------------
| Archivo de Arranque Global
|--------------------------------------------------------------------------
|
| Este archivo maneja tareas esenciales:
| 1. Inicia la sesión de forma segura (con tiempo de inactividad).
| 2. Carga el autoloader de Composer.
| 3. Carga la clase de Autenticación (Auth.php).
| 4. Carga y valida las variables de entorno de .env.
| 5. Crea y devuelve la instancia del proveedor de Autenticación.
|
*/

// 1. Iniciar la sesión de forma segura
$session_path = dirname(__DIR__) . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0700, true);
}
session_save_path($session_path);

// --- INICIO: LÓGICA DE TIEMPO DE INACTIVIDAD (10 MINUTOS) ---
define('SESSION_TIMEOUT', 600); // 10 minutos * 60 segundos

// Configurar el tiempo de vida de la cookie y del recolector de basura
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', 0); // La cookie expira al cerrar el navegador, pero el servidor la invalida
// --- FIN: LÓGICA DE TIEMPO DE INACTIVIDAD ---

// Configuración de seguridad de la sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Si estás en producción (HTTPS), descomenta la siguiente línea:
// ini_set('session.cookie_secure', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- INICIO: CHEQUEO DE INACTIVIDAD ---
if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        // El tiempo ha expirado, destruir la sesión
        session_unset();
        session_destroy();
        
        // Iniciar una nueva sesión limpia
        session_start();
        
        // NOTA: No necesitamos redirigir aquí.
        // El script 'index.php' que incluyó este archivo detectará
        // que la sesión ya no está autenticada y redirigirá al login.
    }
}
// Actualizar el tiempo de la última actividad en CADA carga de página
$_SESSION['LAST_ACTIVITY'] = time();
// --- FIN: CHEQUEO DE INACTIVIDAD ---


// 2. Cargar Composer Autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 3. Cargar la clase de Autenticación
// (Ya la tienes en PR-Catalogo/src/Auth.php)
require_once __DIR__ . '/Auth.php';

// 4. Cargar variables de entorno (desde .env)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    
    // Validar que las variables de Azure existan
    $dotenv->required([
        'AZURE_CLIENT_ID',
        'AZURE_CLIENT_SECRET',
        'AZURE_TENANT_ID',
        'AZURE_REDIRECT_URI'
    ]);

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    die('Error crítico: No se pudo cargar la configuración de entorno (.env). Detalles: ' . $e->getMessage());
}

// 5. Crear y devolver la instancia de Auth
$authConfig = [
    'clientId'     => $_ENV['AZURE_CLIENT_ID'],
    'clientSecret' => $_ENV['AZURE_CLIENT_SECRET'],
    'redirectUri'  => $_ENV['AZURE_REDIRECT_URI'],
    'tenantId'     => $_ENV['AZURE_TENANT_ID'],
];

// Devolvemos la instancia para que quien incluya este archivo la reciba
return new Auth($authConfig);

?>