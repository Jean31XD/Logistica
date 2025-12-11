<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/conexion.php'; // aquí ya se crea $conn

$results = [];
$searchTerm = isset($_GET['q']) ? $_GET['q'] : '';

if (!empty($searchTerm) && $conn) {
    $sql = "SELECT TOP 15 itemid, ProductName, unitid 
            FROM inventtable 
            WHERE ProductName LIKE ? 
            ORDER BY ProductName ASC";
    
    $params = ["%" . $searchTerm . "%"];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
}

echo json_encode($results);
?>
