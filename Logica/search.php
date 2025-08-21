<?php
// 🔹 Respuesta siempre en JSON
header('Content-Type: application/json; charset=utf-8');

// 🔹 Conexión
require_once __DIR__ . '/../conexionBD/conexion.php';

// 🔹 Inicializamos respuesta
$results = [];
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    // Validar si hay búsqueda
    if (!empty($searchTerm)) {
        // Conectar a SQL Server
        $conn = sqlsrv_connect($serverName, $connectionOptions);

        if ($conn === false) {
            throw new Exception("Error al conectar con la base de datos");
        }

        // 🔹 Consulta con parámetro
        $sql = "SELECT TOP 15 
                    itemid, 
                    ProductName, 
                    unitid
                FROM inventtable
                WHERE ProductName LIKE ?
                ORDER BY ProductName ASC";

        $params = ["%" . $searchTerm . "%"];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Error al ejecutar la consulta");
        }

        // 🔹 Convertimos resultados a arreglo
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = [
                "itemid"      => $row["itemid"],
                "ProductName" => $row["ProductName"],
                "unitid"      => $row["unitid"]
            ];
        }

        // Liberar recursos
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    }

    // 🔹 Respuesta final
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
