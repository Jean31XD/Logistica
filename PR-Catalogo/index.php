<?php
// index.php (en la raíz del proyecto)

// 1. CARGAR ARCHIVOS DE LÓGICA Y CONEXIÓN
// --- CAMBIO AQUÍ: Se quitó el '../' ---
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/database.php'; // Carga la función connect_to_database()
require_once __DIR__ . '/src/cache.php';    // Carga get_cached_data()
require_once __DIR__ . '/src/azure.php';    // Carga TODAS las funciones (fetch_product_data, etc.)

// 2. CONECTAR A LA BASE DE DATOS
$db_conn = connect_to_database();

// Verificar si la conexión a la BD fue exitosa
if ($db_conn === false) {
    header("HTTP/1.1 500 Internal Server Error");
    die("<h1>Error 500</h1><p>No se pudo conectar a la base de datos. Por favor, intente más tarde.</p>");
}

// 3. ENRUTADOR DE API (ROUTER)
$action = $_GET['action'] ?? null;

if ($action) {
    switch ($action) {
        case 'get_products':
            // --- CAMBIO AQUÍ: Se quitó el '../' ---
            require __DIR__ . '/api/get_products.php';
            break;
        case 'get_stats':
            // --- CAMBIO AQUÍ: Se quitó el '../' ---
            require __DIR__ . '/api/get_stats.php';
            break;
        default:
            // Buena práctica: manejar acciones desconocidas
            http_response_code(400); // Bad Request
            echo 'Acción desconocida.';
    }
    // Termina el script para las llamadas de la API
    // (La conexión a la BD se cierra dentro de los scripts de la API)
    exit;
}

// --- ARREGLO 2: Añadir try...catch para la carga de datos de la página principal ---
try {
    // 4. RENDERIZADO DE LA PÁGINA COMPLETA (si no es una llamada a la API)
    // (Todas estas funciones SÍ existen porque se cargaron desde src/azure.php)
    $categorias = get_distinct_values($db_conn, 'Categoria');
    $marcas = get_distinct_values($db_conn, 'Marca');
    $global_stats = get_inventory_stats($db_conn);
    $top_10_products = get_top_10_products($db_conn);
    $product_area_data = fetch_product_data($db_conn);

    // Carga la plantilla HTML final
    // --- CAMBIO AQUÍ: Se quitó el '../' ---
    require __DIR__ . '/templates/main_view.php';

} catch (Exception $e) {
    // Manejo de error genérico para la carga de la página
    error_log('Error al renderizar la página principal: ' . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    die("<h1>Error 500</h1><p>No se pudo cargar la información de la página. Por favor, intente más tarde.</p>");
}
// --- FIN ARREGLO 2 ---

// Cierra la conexión para la carga de la página principal
if (isset($db_conn) && $db_conn) {
    sqlsrv_close($db_conn);
}