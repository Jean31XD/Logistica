<?php
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
        $response["message"] = "Error en la consulta: " . print_r(sqlsrv_errors(), true);
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