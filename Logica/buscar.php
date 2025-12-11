<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Rate limiting: máximo 30 búsquedas por minuto
require_once __DIR__ . '/../conexionBD/rate_limiter.php';
if (!checkRateLimit('buscar', 30, 60)) {
    rateLimitExceeded('Demasiadas búsquedas. Espere un momento.');
}

require_once __DIR__ . '/../conexionBD/conexion.php';

$termino = trim($_GET["q"] ?? "");

// Configuración de Azure Blob Storage
$azure_account_name = 'catalogodeimagenes';
$azure_container_name = 'imagenes-productos';

$response = [
    "success" => false,
    "data"    => [],
    "message" => ""
];

if (!empty($termino)) {
    $sql = "SELECT TOP 20 itemid, description, itembarcode, unitid, Inventario_Listo, promedio_Ventas_3M, MI
            FROM listo_inventario
            WHERE itemid LIKE ? OR itembarcode LIKE ?";
    $params = ["%$termino%", "%$termino%"];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("Error SQL en buscar.php: " . print_r(sqlsrv_errors(), true));
        $response["message"] = "Error interno del servidor";
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Agregar URL de imagen de Azure
            $row['image_url'] = "https://{$azure_account_name}.blob.core.windows.net/{$azure_container_name}/" . rawurlencode(trim($row['itemid'])) . ".jpg";
            $response["data"][] = $row;
        }
        $response["success"] = true;
    }
} else {
    $response["message"] = "El parámetro de búsqueda está vacío.";
}

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($response);