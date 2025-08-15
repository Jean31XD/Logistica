<?php 
session_start();

include '../conexionBD/conexion.php';
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Consulta productos + URL de imagen desde ProductImages
$sql = "
SELECT i.itemid, i.ProductName, i.Categoria, i.Subcategoria, p.FotoURL
FROM dbo.inventtable i
LEFT JOIN dbo.ProductImages p
    ON i.itemid = p.itemid
";
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
                    $fotoURL = $row['FotoURL'];

                    if (!empty($fotoURL)) {
                        echo "<tr>
                                <td>{$itemid}</td>
                                <td>{$row['ProductName']}</td>
                                <td>{$row['Categoria']}</td>
                                <td>{$row['Subcategoria']}</td>
                                <td><img src='{$fotoURL}' alt='Imagen de {$itemid}' width='80'></td>
                              </tr>";
                    } else {
                        echo "<tr>
                                <td>{$itemid}</td>
                                <td>{$row['ProductName']}</td>
                                <td>{$row['Categoria']}</td>
                                <td>{$row['Subcategoria']}</td>
                                <td><div class='no-img'>No encontrada</div></td>
                              </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php
sqlsrv_close($conn);
?>
