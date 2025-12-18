<?php
/**
 * api_search.php - API unificada de búsqueda
 * Unifica: buscar.php (inventario) + search.php (productos)
 * 
 * Uso:
 *   GET /Logica/api_search.php?type=inventario&q=term
 *   GET /Logica/api_search.php?type=productos&q=term
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Rate limiting: máximo 30 búsquedas por minuto
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('api_search', 30, 60)) {
    rateLimitExceeded('Demasiadas búsquedas. Espere un momento.');
}

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexionBD/conexion.php';

$type = $_GET['type'] ?? 'inventario';
$termino = trim($_GET['q'] ?? '');

$response = [
    'success' => false,
    'data' => [],
    'message' => '',
    'type' => $type
];

if (empty($termino)) {
    $response['message'] = 'El parámetro de búsqueda está vacío.';
    echo json_encode($response);
    exit;
}

switch ($type) {
    case 'inventario':
        // Búsqueda en listo_inventario (antes buscar.php)
        // Cargar configuración de Azure desde config/app.php
        $config = require __DIR__ . '/../config/app.php';
        $azure_account_name = $config['azure']['account_name'];
        $azure_container_name = $config['azure']['container_name'];
        
        $sql = "SELECT TOP 20 itemid, description, itembarcode, unitid, Inventario_Listo, promedio_Ventas_3M, MI
                FROM listo_inventario
                WHERE itemid LIKE ? OR itembarcode LIKE ?";
        $params = ["%$termino%", "%$termino%"];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            error_log("Error SQL en api_search.php (inventario): " . print_r(sqlsrv_errors(), true));
            $response['message'] = 'Error interno del servidor';
        } else {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Agregar URL de imagen de Azure
                $row['image_url'] = "https://{$azure_account_name}.blob.core.windows.net/{$azure_container_name}/" . rawurlencode(trim($row['itemid'])) . ".jpg";
                $response['data'][] = $row;
            }
            $response['success'] = true;
        }
        break;
        
    case 'productos':
        // Búsqueda en inventtable (antes search.php)
        $sql = "SELECT TOP 15 itemid, ProductName, unitid 
                FROM inventtable 
                WHERE ProductName LIKE ? OR itemid LIKE ?
                ORDER BY ProductName ASC";
        $params = ["%$termino%", "%$termino%"];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            error_log("Error SQL en api_search.php (productos): " . print_r(sqlsrv_errors(), true));
            $response['message'] = 'Error interno del servidor';
        } else {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $response['data'][] = $row;
            }
            $response['success'] = true;
            sqlsrv_free_stmt($stmt);
        }
        break;
        
    default:
        $response['message'] = 'Tipo de búsqueda no válido. Use: inventario o productos';
}

sqlsrv_close($conn);
echo json_encode($response);
