<?php
require_once __DIR__ . '/../conexionBD/conexion.php';

$termino = trim($_GET["q"] ?? "");

$response = [
    "success" => false,
    "data"    => [],
    "message" => ""
];

if (!empty($termino)) {
    $sql = "SELECT TOP 20 itemid, description, itembarcode, unitid, Inventario_Listo
            FROM listo_inventario
            WHERE itemid LIKE ? OR itembarcode LIKE ?";
    $params = ["%$termino%", "%$termino%"];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $response["message"] = "Error en la consulta: " . print_r(sqlsrv_errors(), true);
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $response["data"][] = $row;
        }
        $response["success"] = true;
    }
} else {
    $response["message"] = "El parámetro de búsqueda está vacío.";
}

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($response);
