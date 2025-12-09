<?php
/**
 * Endpoint Optimizado: Búsqueda de Productos
 * Mejoras: Cache de resultados, lazy loading, respuesta comprimida
 */

require_once __DIR__ . '/../config/ajax_endpoint.php';

// Obtener y sanitizar término de búsqueda
$termino = sanitizarRapido($_GET["q"] ?? '', 'string');

// Configuración Azure Blob Storage (optimizada con constantes)
const AZURE_ACCOUNT = 'catalogodeimagenes';
const AZURE_CONTAINER = 'imagenes-productos';

// Respuesta inicial
$response = [
    "success" => false,
    "data"    => [],
    "message" => ""
];

if (empty($termino)) {
    jsonError("El parámetro de búsqueda está vacío.");
}

// Generar clave de cache para esta búsqueda
$cacheKey = 'search_' . md5($termino);

// Intentar obtener de cache
if (RequestCache::has($cacheKey)) {
    $response["data"] = RequestCache::get($cacheKey);
    $response["success"] = true;
    $response["cached"] = true;
    jsonSuccess($response["data"]);
}

// Si no está en cache, buscar en BD
$conn = AppLoader::loadDB();

$sql = "SELECT TOP 20 itemid, description, itembarcode, unitid, Inventario_Listo, promedio_Ventas_3M, MI
        FROM listo_inventario
        WHERE itemid LIKE ? OR itembarcode LIKE ?";

$searchTerm = "%$termino%";
$params = [$searchTerm, $searchTerm];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    error_log("Error en búsqueda: " . print_r(sqlsrv_errors(), true));
    jsonError("Error en la consulta.");
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Agregar URL de imagen (lazy loading - construir URL sin verificar existencia)
    $row['image_url'] = sprintf(
        "https://%s.blob.core.windows.net/%s/%s.jpg",
        AZURE_ACCOUNT,
        AZURE_CONTAINER,
        rawurlencode(trim($row['itemid']))
    );
    $results[] = $row;
}

sqlsrv_free_stmt($stmt);

// Guardar en cache por 5 minutos
RequestCache::set($cacheKey, $results, 300);

// Retornar resultados
jsonSuccess($results);
?>
