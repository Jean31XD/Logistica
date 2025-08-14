<?php
// --- Conexión a SQL Server ---
include '../conexionBD/conexion.php';

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Carpeta donde están las imágenes
$imgDir = __DIR__ . "/IMG/"; // Ruta absoluta
$imgUrl = "IMG/"; // Ruta relativa para mostrar en HTML

// Consulta de todos los productos
$sql = "SELECT itemid, ProductName, Categoria, Subcategoria FROM dbo.inventtable";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Coincidencia de Imágenes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        .table img {
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        .no-img {
            color: white;
            background-color: #dc3545;
            text-align: center;
            border-radius: 5px;
            padding: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body class="p-4">

<div class="container">
    <h2 class="mb-4 text-center">Coincidencia de Imágenes con Inventario</h2>
    <div class="table-responsive shadow-sm rounded">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Subcategoría</th>
                    <th>Imagen</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $itemid = $row['itemid'];
                    $foundImg = null;

                    // Buscar imagen con extensiones comunes
                    foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
                        $filePath = $imgDir . $itemid . "." . $ext;
                        if (file_exists($filePath)) {
                            $foundImg = $imgUrl . $itemid . "." . $ext;
                            break;
                        }
                    }

                    echo "<tr>";
                    echo "<td>{$itemid}</td>";
                    echo "<td>{$row['ProductName']}</td>";
                    echo "<td>{$row['Categoria']}</td>";
                    echo "<td>{$row['Subcategoria']}</td>";

                    if ($foundImg) {
                        echo "<td><img src='{$foundImg}' alt='Imagen de {$itemid}' width='80'></td>";
                    } else {
                        echo "<td><div class='no-img'>No encontrada</div></td>";
                    }

                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php
// Cerrar conexión
sqlsrv_close($conn);
?>
