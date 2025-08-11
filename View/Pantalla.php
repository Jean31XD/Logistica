<?php
// --- Lógica para obtener datos ---
function obtenerDatosDeTickets() {
    // Asegúrate de que la ruta a tu archivo de conexión es la correcta
    require_once __DIR__ . '/../conexionBD/conexion.php'; 
    
    // Tus variables de conexión deben estar definidas en el archivo de arriba
    $connectionInfo = [
        "Database" => $database, 
        "UID" => $username, 
        "PWD" => $password,
        "TrustServerCertificate" => true, 
        "CharacterSet" => "UTF-8"
    ];
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) { return []; }

    $sql = "SELECT log.Tiket, log.NombreTR, log.Empresa, log.Estatus, usuarios.ventanilla
            FROM log 
            LEFT JOIN usuarios ON log.Asignar = usuarios.usuario";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) { sqlsrv_close($conn); return []; }

    $datos = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
        $datos[] = $row; 
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    return $datos;
}

// --- LÓGICA AJAX PARA EL FRONTEND ---
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(obtenerDatosDeTickets());
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Tíckets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />

    <style>
        :root {
            --rojo-principal: #e31f25;
            --gris-claro: #f8f9fa;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #751010ff, #bb1b1bff, #cb1717ef, #ff0000ff);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: var(--gris-claro);
            overflow-x: hidden;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .header-flotante {
            position: sticky; top: 0; z-index: 1020; padding: 1rem 0;
            background: rgba(0, 0, 0, 0.2); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: center;
        }
        .header-flotante img { max-width: 350px; width: 100%; filter: drop-shadow(0 0 15px rgba(0,0,0,0.5)); }
        .main-container { padding: 2rem 1rem; }
        .tabla-container {
            background-color: rgba(0, 0, 0, 0.3); border-radius: 15px;
            padding: 1.5rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table {
            color: var(--gris-claro); font-size: 1.2rem;
            border-collapse: separate; border-spacing: 0 10px;
        }
        .table thead th {
            color: #fff; background-color: transparent; border: none;
            text-transform: uppercase; letter-spacing: 1px; font-size: 1rem;
            padding: 1rem 1.5rem; text-align: center;
            border-bottom: 2px solid var(--rojo-principal);
        }
        .table tbody tr {
            background-color: rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); border-radius: 10px;
        }
        .table tbody tr:hover {
            transform: translateY(-5px) scale(1.01);
            background-color: rgba(255, 255, 255, 0.15) !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        .table tbody td { padding: 1.5rem; vertical-align: middle; border: none; text-align: center; }
        .table tbody tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table tbody tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .tiempo-celda { font-weight: 600; font-size: 1.3rem; }

        /* --- ESTILOS PARA EL COLOR DE LAS FILAS --- */
/* --- ESTILOS PARA EL COLOR DE LAS FILAS (CORREGIDO) --- */
.table tbody tr.fila-facturacion {
    background: linear-gradient(90deg, rgba(25, 135, 84, 0.4), rgba(25, 135, 84, 0.15)) !important;
    border-left: 5px solid #198754; /* Verde */
}
.table tbody tr.fila-retencion {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.4), rgba(220, 53, 69, 0.15)) !important;
    border-left: 5px solid #dc3545; /* Rojo */
}

    </style>
</head>
<body>

    <header class="header-flotante animate__animated animate__fadeInDown">
        <img src="../IMG/LOGO MC - BLANCO.png" alt="Logo de la Empresa">
    </header>

    <div class="main-container text-center">
        <div class="tabla-container animate__animated animate__fadeIn" style="animation-delay: 0.5s;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ticket</th><th>Nombre</th><th>Empresa</th><th>Estatus</th><th>Ventanilla</th><th>Tiempo</th>
                        </tr>
                    </thead>
                    <tbody id="tablaDatos"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tiemposInicio = {};

            function actualizarTiempos() {
                document.querySelectorAll(".tiempo-celda").forEach(celda => {
                    const ticketID = celda.dataset.ticketId;
                    if (!tiemposInicio[ticketID]) { tiemposInicio[ticketID] = Date.now(); }

                    const diferencia = Math.floor((Date.now() - tiemposInicio[ticketID]) / 1000);
                    const horas = Math.floor(diferencia / 3600);
                    const minutos = Math.floor((diferencia % 3600) / 60);
                    const segundos = diferencia % 60;

                    celda.textContent = 
                        `${horas.toString().padStart(2, "0")}:${minutos.toString().padStart(2, "0")}:${segundos.toString().padStart(2, "0")}`;
                });
            }

            function actualizarDatos() {
                fetch('?ajax=1')
                    .then(response => response.ok ? response.json() : Promise.reject('Error de red'))
                    .then(data => {
                        const tbody = document.getElementById('tablaDatos');
                        const mapaFilasActuales = new Map(Array.from(tbody.querySelectorAll('tr[data-ticket]')).map(fila => [fila.dataset.ticket, fila]));
                        const ticketsActivos = new Set();

                        if (data.length === 0) {
                            if (!tbody.querySelector('td[colspan="6"]')) {
                                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-info-circle me-2"></i>No hay tickets activos.</td></tr>';
                            }
                            mapaFilasActuales.forEach(fila => fila.remove());
                            return; 
                        }
                        
                        if (tbody.querySelector('td[colspan="6"]')) { tbody.innerHTML = ''; }
                        
                        data.forEach(ticket => {
                            const ticketID = String(ticket.Tiket);
                            ticketsActivos.add(ticketID);

                            const estatus = ticket.Estatus;
                            let icono = 'fa-cogs';
                            let claseFila = '';
                            
                    $estatus = htmlspecialchars($row['Estatus']);
                    $claseFila = "";
                    if ($estatus === "Retencion") {
                        $claseFila = "table-danger";
                    } elseif ($estatus === "Facturación") {
                        $claseFila = "table-success";
                    }

                            const ventanillaHTML = ticket.ventanilla ? ticket.ventanilla : '<span class="text-muted">N/A</span>';

                            if (mapaFilasActuales.has(ticketID)) {
                                const fila = mapaFilasActuales.get(ticketID);
                                fila.cells[3].innerHTML = `<i class="fas ${icono} me-2"></i>${estatus}`;
                                fila.cells[4].innerHTML = ventanillaHTML;

                                fila.classList.remove('fila-facturacion', 'fila-retencion');
                                if (claseFila) {
                                    fila.classList.add(claseFila);
                                }
                                
                            } else {
                                const nuevaFila = tbody.insertRow();
                                nuevaFila.className = `animate__animated animate__fadeIn ${claseFila}`;
                                nuevaFila.dataset.ticket = ticketID;

                                nuevaFila.innerHTML = `
                                    <td>${ticketID}</td>
                                    <td>${ticket.NombreTR}</td>
                                    <td>${ticket.Empresa}</td>
                                    <td><i class="fas ${icono} me-2"></i>${estatus}</td>
                                    <td>${ventanillaHTML}</td>
                                    <td class="tiempo-celda" data-ticket-id="${ticketID}">00:00:00</td>
                                `;
                            }
                        });

                        mapaFilasActuales.forEach((fila, ticketID) => {
                            if (!ticketsActivos.has(ticketID)) {
                                fila.classList.remove('animate__fadeIn');
                                fila.classList.add('animate__fadeOut');
                                fila.addEventListener('animationend', () => fila.remove());
                                delete tiemposInicio[ticketID];
                            }
                        });

                    })
                    .catch(error => {
                        console.error('Error al actualizar:', error);
                        const tbody = document.getElementById('tablaDatos');
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar datos.</td></tr>';
                    });
            }

            setInterval(actualizarTiempos, 1000);
            setInterval(actualizarDatos, 3000);
            actualizarDatos(); // Carga inicial de datos
        });
    </script>

</body>
</html>