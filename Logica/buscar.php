<?php

require_once __DIR__ . '/../conexionBD/conexion.php';



$termino = trim($_GET["q"] ?? "");



$resultados = [];



if (!empty($termino)) {
$sql = "SELECT TOP 20 itemid, description, itembarcode, unitid, Inventario_Listo
 FROM listo_inventario WHERE itemid LIKE ? OR itembarcode LIKE ?"; 

$params = ["%$termino%", "%$termino%"];



$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt !== false) {

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

$resultados[] = $row;

 }

 }

}



header("Content-Type: application/json; charset=UTF-8");

echo json_encode($resultados);

