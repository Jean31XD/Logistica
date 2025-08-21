<?php
// Establecer la cabecera para devolver contenido JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/conexion.php';


$results = [];
$searchTerm = isset($_GET['q']) ? $_GET['q'] : '';

if (!empty($searchTerm)) {
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn) {
        // Agregamos unitid a la consulta
        $sql = "SELECT TOP 15 itemid, ProductName, unitid 
                FROM inventtable 
                WHERE ProductName LIKE ? 
                ORDER BY ProductName ASC";
        
        $params = array("%" . $searchTerm . "%");
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $results[] = $row;
            }
        }
        
        if (isset($stmt)) sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    }
}

echo json_encode($results);
?>
