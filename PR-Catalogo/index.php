<?php
// index.php (en la raíz del proyecto)

// --- INICIO: ARREGLO DE REDIRECCIÓN Y ERRORES ---
ob_start(); // ¡MUY IMPORTANTE! Inicia el búfer de salida.

// Habilitar reporte de errores (SOLO PARA DEBUG)
// Esto nos mostrará si hay un error fatal al cargar bootstrap.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- FIN: ARREGLO DE REDIRECCIÓN Y ERRORES ---


// --- ARREGLO DE CACHÉ (Lo mantenemos) ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
// --- FIN: ARREGLO DE CACHÉ ---


// 1. CARGAR BOOTSTRAP (Sesión, Env, Autoload, Auth object)
try {
    // $auth ahora contiene nuestra instancia de la clase Auth
    $auth = require_once __DIR__ . '/src/bootstrap.php';
} catch (Exception $e) {
    ob_end_clean(); // Limpiar búfer antes de mostrar error
    die('<h1>Error Fatal al cargar Bootstrap</h1><p>No se pudo cargar src/bootstrap.php.</p><p><strong>Detalle:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>');
}


// 2. PROTEGER LA APLICACIÓN
// Verificamos si el usuario está autenticado usando el método estático
if (!Auth::isAuthenticated()) {
    // Si no está autenticado, redirigir a la página de login
    header('Location: public/login.php');
    
    ob_end_flush(); // Enviar la redirección (limpiando el búfer)
    exit();
}

// --- Si llegamos aquí, el usuario ESTÁ AUTENTICADO ---

// 3. CARGAR ARCHIVOS DE LÓGICA Y CONEXIÓN
// (bootstrap.php ya cargó autoload y Auth.php)
require_once __DIR__ . '/src/database.php'; // Carga la función connect_to_database()
require_once __DIR__ . '/src/cache.php';    // Carga get_cached_data()
require_once __DIR__ . '/src/azure.php';    // Carga TODAS las funciones (fetch_product_data, etc.)

// 4. CONECTAR A LA BASE DE DATOS
$db_conn = connect_to_database();

// Verificar si la conexión a la BD fue exitosa
if ($db_conn === false) {
    header("HTTP/1.1 500 Internal Server Error");
    die("<h1>Error 500</h1><p>No se pudo conectar a la base de datos. Por favor, intente más tarde.</p>");
}

// 5. ENRUTADOR DE API (ROUTER)
$action = $_GET['action'] ?? null;

if ($action) {
    switch ($action) {
        case 'get_products':
            require __DIR__ . '/api/get_products.php';
            break;
        case 'get_stats':
            require __DIR__ . '/api/get_stats.php';
            break;
        default:
            // Buena práctica: manejar acciones desconocidas
            http_response_code(400); // Bad Request
            echo 'Acción desconocida.';
    }
    // Termina el script para las llamadas de la API
    exit;
}

// 6. RENDERIZADO DE LA PÁGINA COMPLETA
try {
    // (Todas estas funciones SÍ existen porque se cargaron desde src/azure.php)
    $categorias = get_distinct_values($db_conn, 'Categoria');
    $marcas = get_distinct_values($db_conn, 'Marca');
    $global_stats = get_inventory_stats($db_conn);
    $top_10_products = get_top_10_products($db_conn);
    $product_area_data = fetch_product_data($db_conn);

    // Carga la plantilla HTML final
    require __DIR__ . '/templates/main_view.php';

} catch (Exception $e) {
    // Manejo de error genérico para la carga de la página
    error_log('Error al renderizar la página principal: ' . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    die("<h1>Error 500</h1><p>No se pudo cargar la información de la página. Por favor, intente más tarde.</p>");
}

// Cierra la conexión para la carga de la página principal
if (isset($db_conn) && $db_conn) {
    sqlsrv_close($db_conn);
}

// --- INICIO: ARREGLO DE REDIRECCIÓN (FINAL) ---
// Envía toda la página HTML (que estaba en el búfer) al navegador
ob_end_flush(); 
// --- FIN: ARREGLO DE REDIRECCIÓN (FINAL) ---
?>