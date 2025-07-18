<?php
header('Content-Type: text/html; charset=UTF-8');
include("../conexion.php");

// Consulta SQL
$sql = "SELECT Tiket, NombreTR, Empresa, Estatus, ventanilla FROM [dbo].[Tiket] WHERE Estatus IN ('En proceso', 'Retencion', 'Facturación')";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo "Error en la consulta: ";
    die(print_r(sqlsrv_errors(), true));
}

$tieneDatos = false;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tieneDatos = true;

    // Convertir todos los campos a UTF-8 si son cadenas
    foreach ($row as $key => $value) {
        if (is_string($value)) {
            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
    }

    // Escapar caracteres peligrosos en HTML
    $estatus = htmlspecialchars($row['Estatus']);
    $claseFila = "";

    if ($estatus === "Retencion") {
        $claseFila = "table-danger";
    } elseif ($estatus === "Facturación") {
        $claseFila = "table-success";
    }

    $ticketID = htmlspecialchars($row['Tiket']);
    $nombreID = "tiempo_" . $ticketID;

    echo '<tr class="' . $claseFila . '" id="row_' . $ticketID . '" data-ticket="' . $ticketID . '">';
    echo '<td>' . $ticketID . '</td>';
    echo '<td>' . htmlspecialchars($row['NombreTR']) . '</td>';
    echo '<td>' . htmlspecialchars($row['Empresa']) . '</td>';
    echo '<td>' . $estatus . '</td>';

    // Mostrar ventanilla si está definida
    if (isset($row['ventanilla'])) {
        echo '<td>' . htmlspecialchars($row['ventanilla']) . '</td>';
    } else {
        echo '<td><span class="text-danger">No asignado</span></td>';
    }

    echo '<td id="' . $nombreID . '"></td>';
    echo '</tr>';
}

if (!$tieneDatos) {
    echo '<tr><td colspan="6" class="text-center">No hay tickets en proceso.</td></tr>';
}

sqlsrv_free_stmt($stmt);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Lista de Tickets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
body {
    margin: 0;
    padding: 0;
    background-color: #f8f9fa;
    
}



table {
    width: 100%;
    font-size: 20px;
    table-layout: fixed;     
}

th, td {
    padding: 20px;
    text-align: center;
    word-wrap: break-word;
}

.table tbody tr {
    height: 100px;
    width: 100%;
}

.retencion {
    background-color: #dc3545 !important;
    color: white;
}

.facturacion {
    background-color: #198754 !important;
    color: white;
}


    </style>
</head>
<body>

<div class="container-fluid">
    <div class="table-responsive">
        <table class="table table-bordered table-hover text-center">
            <thead class="table-dark">
                <tr>
                    <th>Tiket</th>
                    <th>Nombre</th>
                    <th>Empresa</th>
                    <th>Estatus</th>
                    <th>Ventanilla</th>
                    <th>Tiempo</th>
                </tr>
            </thead>
            <tbody id="tablaTickets">
                <?php
                $tieneDatos = false;
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $tieneDatos = true;
                    
                    $estatus = htmlspecialchars($row['Estatus']);
                    $claseFila = "";
                    if ($estatus === "Retencion") {
                        $claseFila = "table-danger";
                    } elseif ($estatus === "Facturación") {
                        $claseFila = "table-success";
                    }

                    $ticketID = htmlspecialchars($row['Tiket']);
                    $nombreID = "tiempo_" . $ticketID;
                    
                    echo '<tr class="' . $claseFila . '" id="row_' . $ticketID . '" data-ticket="' . $ticketID . '">';
                    echo '<td>' . $ticketID . '</td>';
                    echo '<td>' . htmlspecialchars($row['NombreTR']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Empresa']) . '</td>';
                    echo '<td>' . $estatus . '</td>';
                    echo '<td>' . (isset($row['ventanilla']) ? htmlspecialchars($row['ventanilla']) : '<span class="text-danger">No asignado</span>') . '</td>';
                    echo '<td id="' . $nombreID . '"></td>'; 
                    echo '</tr>';
                }

                if (!$tieneDatos) {
                    echo '<tr><td colspan="6" class="text-center text-warning">⚠️ No hay datos disponibles</td></tr>';
                }

                sqlsrv_free_stmt($stmt);
                sqlsrv_close($conn);
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const tiemposInicio = {};

    function actualizarTiempos() {
        let filas = document.querySelectorAll("tbody tr");

        filas.forEach(function(fila) {
            let ticketID = fila.children[0].textContent;
            let tiempoID = fila.children[5];

            if (!tiemposInicio[ticketID]) {
                tiemposInicio[ticketID] = Date.now();
            }

            let diferencia = Math.floor((Date.now() - tiemposInicio[ticketID]) / 1000);

            let horas = Math.floor(diferencia / 3600);
            let minutos = Math.floor((diferencia % 3600) / 60);
            let segundos = diferencia % 60;

            let tiempoFormateado = `${horas.toString().padStart(2, "0")}:${minutos.toString().padStart(2, "0")}:${segundos.toString().padStart(2, "0")}`;
            tiempoID.textContent = tiempoFormateado;
        });
    }

    setInterval(actualizarTiempos, 0); 
    actualizarTiempos(); 
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
