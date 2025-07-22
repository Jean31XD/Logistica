<?php
session_start();

$serverName = "sdb-apptransportistas-maco.database.windows.net";
$database = "db-apptransportistas-maco";
$username = "ServiceAppTrans";
$password = "⁠nZ(#n41LJm)iLmJP";

$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    echo '<div class="alert alert-danger text-center" role="alert">
            ❌ <strong>Error de conexión:</strong> No se pudo conectar a la base de datos.
         </div>';
    exit;
}

// Consulta SQL
$sql = "SELECT log.Tiket, log.NombreTR, log.Empresa, log.Estatus, usuarios.ventanilla 
        FROM log 
        LEFT JOIN usuarios ON log.Asignar = usuarios.usuario";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo '<div class="alert alert-danger text-center" role="alert">
            ❌ <strong>Error en la consulta:</strong> No se pudieron obtener los datos.
          </div>';
    exit;
}
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
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
        }
        .container-fluid {
            width: 100%;
            max-width: 90vw;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            font-size: 18px;
        }
        th, td {
            padding: 12px;
            text-align: center;
        }
        .table tbody tr {
            height: 50px;
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
                $contador = 0; // Para asignar un ID único a cada fila

                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $tieneDatos = true;
                    $estatus = htmlspecialchars($row['Estatus']);

                    // Definir la clase según el estatus
                    $claseFila = "";
                    if ($estatus == "Retención") {
                        $claseFila = "table-danger";
                    } elseif ($estatus == "Facturación") {
                        $claseFila = "table-success";
                    }

                    // Generar un ID único para cada fila
                    $ticketID = htmlspecialchars($row['Tiket']);
                    $nombreID = "tiempo_" . $contador;

                    echo '<tr class="' . $claseFila . '" id="row_' . $contador . '">';
                    echo '<td>' . $ticketID . '</td>';
                    echo '<td>' . htmlspecialchars($row['NombreTR']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Empresa']) . '</td>';
                    echo '<td>' . $estatus . '</td>';
                    echo '<td>' . (isset($row['ventanilla']) ? htmlspecialchars($row['ventanilla']) : '<span class="text-danger">No asignado</span>') . '</td>';
                    echo '<td id="' . $nombreID . '">00:00:00</td>';
                    echo '</tr>';

                    $contador++; // Incrementar el contador para el siguiente ID
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function startTimers() {
        // Obtener todas las filas de la tabla
        let rows = document.querySelectorAll('#tablaTickets tr');

        rows.forEach((row, index) => {
            let timeCell = row.querySelector('td:last-child'); // Obtener la celda de tiempo
            let timerId = "timer_" + index; // ID único para el temporizador en localStorage

            // Obtener el tiempo almacenado en localStorage (si existe)
            let storedTime = localStorage.getItem(timerId);
            let startTime = storedTime ? parseInt(storedTime) : Math.floor(Date.now() / 1000);

            // Si no hay tiempo almacenado, guardar el tiempo actual
            if (!storedTime) {
                localStorage.setItem(timerId, startTime);
            }

            function updateRowTimer() {
                let elapsedSeconds = Math.floor(Date.now() / 1000) - startTime; // Calcular tiempo transcurrido
                let hours = Math.floor(elapsedSeconds / 3600);
                let minutes = Math.floor((elapsedSeconds % 3600) / 60);
                let seconds = elapsedSeconds % 60;

                let formattedTime = 
                    ('0' + hours).slice(-2) + ":" + 
                    ('0' + minutes).slice(-2) + ":" + 
                    ('0' + seconds).slice(-2);

                timeCell.textContent = formattedTime; // Actualizar la celda
            }

            // Iniciar el temporizador para cada fila
            setInterval(updateRowTimer, 1000);
        });
    }

    // Ejecutar la función después de cargar la página
    window.onload = startTimers;
</script>

</body>
</html>