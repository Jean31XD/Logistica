<?php
// --- Configuración de conexión a SQL Server ---
include '../conexionBD/conexion.php';


// Conectar
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Carpeta donde están las imágenes
$imgDir = __DIR__ . "/IMG/"; // ruta absoluta

// Consulta de todos los productos
$sql = "SELECT itemid, ProductName, Categoria, Subcategoria FROM dbo.inventtable";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// HTML de inicio
echo "<table border='1' cellpadding='5'>";
echo "<tr>
        <th>Código</th>
        <th>Nombre</th>
        <th>Categoría</th>
        <th>Subcategoría</th>
        <th>Imagen</th>
      </tr>";

// Recorrer cada producto
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $itemid = $row['itemid'];
    $foundImg = null;

    // Buscar imagen con extensiones comunes
    foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $filePath = $imgDir . $itemid . "." . $ext;
        if (file_exists($filePath)) {
            $foundImg = "img/" . $itemid . "." . $ext;
            break;
        }
    }

    echo "<tr>";
    echo "<td>{$itemid}</td>";
    echo "<td>{$row['ProductName']}</td>";
    echo "<td>{$row['Categoria']}</td>";
    echo "<td>{$row['Subcategoria']}</td>";
    if ($foundImg) {
        echo "<td><img src='{$foundImg}' width='80'></td>";
    } else {
        echo "<td style='color:red;'>Imagen no encontrada</td>";
    }
    echo "</tr>";
}

echo "</table>";

// Cerrar conexión
sqlsrv_close($conn);
?>
