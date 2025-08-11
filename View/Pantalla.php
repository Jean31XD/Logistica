<?php
// --- Lógica para obtener datos ---
// Esta sección se podría mantener en un archivo separado si se prefiere,
// pero la incluimos aquí para tener un solo archivo.

function obtenerDatosDeTickets() {
    // Incluye los datos de conexión desde tu archivo de configuración
    require_once __DIR__ . '/../conexionBD/conexion.php';

    // Array de conexión para SQL Server
    $connectionInfo = [
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8" // Asegura la codificación correcta
    ];

    // Establece la conexión
    $conn = sqlsrv_connect($serverName, $connectionInfo);

    // Verifica si la conexión fue exitosa
    if ($conn === false) {
        // En un entorno de producción, registrarías este error en lugar de mostrarlo.
        // error_log("Error de conexión a SQL Server: " . print_r(sqlsrv_errors(), true));
        return []; // Devuelve un array vacío en caso de error de conexión
    }

    // Consulta SQL para obtener los datos de los tickets
    $sql = "SELECT log.Tiket, log.NombreTR, log.Empresa, log.Estatus, usuarios.ventanilla
            FROM log 
            LEFT JOIN usuarios ON log.Asignar = usuarios.usuario";

    $stmt = sqlsrv_query($conn, $sql);

    // Verifica si la consulta se ejecutó correctamente
    if ($stmt === false) {
        // error_log("Error en la consulta SQL: " . print_r(sqlsrv_errors(), true));
        sqlsrv_close($conn);
        return []; // Devuelve un array vacío en caso de error de consulta
    }

    $datos = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $datos[] = $row;
    }

    // Libera los recursos y cierra la conexión
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    return $datos;
}

// --- NUEVA LÓGICA AJAX ---
// Si la solicitud es para actualizar datos (AJAX)
if (isset($_GET['ajax'])) {
    // Indicamos que la respuesta será en formato JSON
    header('Content-Type: application/json');
    
    $datos = obtenerDatosDeTickets();
    
    // Codificamos el array de datos a formato JSON y lo enviamos
    echo json_encode($datos);
    
    exit; // Termina el script para no enviar el HTML completo
}

// --- HTML Principal de la Pantalla ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Tíckets - Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />

    <style>
        :root {
            --rojo-principal: #e31f25;
            --rojo-oscuro: #a9151a;
            --gris-oscuro: #212529;
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
            position: sticky;
            top: 0;
            z-index: 1020;
            padding: 1rem 0;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .header-flotante img {
            max-width: 350px;
            width: 100%;
            filter: drop-shadow(0 0 15px rgba(0,0,0,0.5));
        }

        .main-container {
            padding: 2rem 1rem;
        }

        .titulo-principal {
            font-weight: 700;
            font-size: 2.5rem;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }

        .tabla-container {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table {
            color: var(--gris-claro);
            font-size: 1.2rem; /* Tamaño de fuente base para la tabla */
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .table thead th {
            color: #fff;
            background-color: transparent;
            border: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1rem;
            padding: 1rem 1.5rem;
            text-align: center;
            border-bottom: 2px solid var(--rojo-principal);
        }

        .table tbody tr {
            background-color: rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        
        .table tbody tr:hover {
            transform: translateY(-5px) scale(1.01);
            background-color: rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .table tbody td {
            padding: 1.5rem;
            vertical-align: middle;
            border: none;
            text-align: center;
        }
        
        .table tbody tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table tbody tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        
        .tiempo-celda {
            font-weight: 600;
            font-size: 1.3rem;
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
                            <th>Ticket</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Estatus</th>
                            <th>Ventanilla</th>
                            <th>Tiempo</th>
                        </tr>
                    </thead>
                    <tbody id="tablaDatos">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tiemposInicio = {};

            function actualizarTiempos() {
                const celdasTiempo = document.querySelectorAll(".tiempo-celda");
                celdasTiempo.forEach(celda => {
                    const ticketID = celda.dataset.ticketId;
                    if (!tiemposInicio[ticketID]) {
                        tiemposInicio[ticketID] = Date.now();
                    }

                    const diferencia = Math.floor((Date.now() - tiemposInicio[ticketID]) / 1000);
                    const horas = Math.floor(diferencia / 3600);
                    const minutos = Math.floor((diferencia % 3600) / 60);
                    const segundos = diferencia % 60;

                    celda.textContent = 
                        `${horas.toString().padStart(2, "0")}:${minutos.toString().padStart(2, "0")}:${segundos.toString().padStart(2, "0")}`;
                });
            }

            // --- NUEVO JAVASCRIPT PARA ACTUALIZACIÓN INTELIGENTE ---
            function actualizarDatos() {
                fetch('?ajax=1')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la red o en el servidor.');
                        }
                        return response.json(); // Ahora esperamos una respuesta JSON
                    })
                    .then(data => {
                        const tbody = document.getElementById('tablaDatos');
                        const filasActuales = tbody.querySelectorAll('tr[data-ticket]');
                        const mapaFilasActuales = new Map();
                        filasActuales.forEach(fila => {
                           mapaFilasActuales.set(fila.dataset.ticket, fila);
                        });

                        const ticketsActivos = new Set();

                        // Si no hay datos, mostrar mensaje y limpiar
                        if (data.length === 0) {
                            if (!tbody.querySelector('td[colspan="6"]')) {
                                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-info-circle me-2"></i>No hay tickets activos en este momento.</td></tr>';
                            }
                            return; 
                        }
                        
                        // Limpiar el mensaje de "no hay tickets" si lo hubiera
                        if (tbody.querySelector('td[colspan="6"]')) {
                            tbody.innerHTML = '';
                        }
                        
                        // 1. AÑADIR O ACTUALIZAR FILAS
                        data.forEach(ticket => {
                            const ticketID = String(ticket.Tiket); // Asegurarse que es string para consistencia
                            ticketsActivos.add(ticketID);

                            const estatus = ticket.Estatus;
                            let icono = 'fa-clock';
                            switch (estatus) {
                                case "Retencion": icono = "fa-hand-paper"; break;
                                case "Facturación": icono = "fa-check-circle"; break;
                                case "En Proceso": icono = "fa-cogs"; break;
                            }

                            const ventanillaHTML = ticket.ventanilla ? ticket.ventanilla : '<span class="text-muted">N/A</span>';

                            if (mapaFilasActuales.has(ticketID)) {
                                // --- El ticket ya existe, así que ACTUALIZAMOS ---
                                const fila = mapaFilasActuales.get(ticketID);
                                let estatusCell = fila.cells[3];
                                let ventanillaCell = fila.cells[4];
                                
                                let nuevoEstatusHTML = `<i class="fas ${icono} me-2"></i>${estatus}`;

                                // Solo actualizamos el contenido si ha cambiado
                                if (estatusCell.innerHTML !== nuevoEstatusHTML) {
                                   estatusCell.innerHTML = nuevoEstatusHTML;
                                }
                                if (ventanillaCell.innerHTML !== ventanillaHTML) {
                                    ventanillaCell.innerHTML = ventanillaHTML;
                                }
                                
                            } else {
                                // --- El ticket es nuevo, así que lo AÑADIMOS ---
                                const nuevaFila = document.createElement('tr');
                                nuevaFila.className = 'animate__animated animate__fadeIn'; // Animación solo para los nuevos
                                nuevaFila.dataset.ticket = ticketID;

                                nuevaFila.innerHTML = `
                                    <td>${ticketID}</td>
                                    <td>${ticket.NombreTR}</td>
                                    <td>${ticket.Empresa}</td>
                                    <td><i class="fas ${icono} me-2"></i>${estatus}</td>
                                    <td>${ventanillaHTML}</td>
                                    <td class="tiempo-celda" data-ticket-id="${ticketID}">00:00:00</td>
                                `;
                                tbody.appendChild(nuevaFila);
                            }
                        });

                        // 2. ELIMINAR FILAS QUE YA NO EXISTEN
                        mapaFilasActuales.forEach((fila, ticketID) => {
                            if (!ticketsActivos.has(ticketID)) {
                                // Animación de salida y luego eliminar el elemento
                                fila.classList.remove('animate__fadeIn');
                                fila.classList.add('animate__fadeOut');
                                fila.addEventListener('animationend', () => fila.remove());
                                
                                // Limpiamos su temporizador
                                delete tiemposInicio[ticketID];
                            }
                        });

                    })
                    .catch(error => {
                        console.error('Error al actualizar los datos:', error);
                        const tbody = document.getElementById('tablaDatos');
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar los datos. Verifique la conexión.</td></tr>';
                    });
            }

            // Llama a la función para actualizar los tiempos cada segundo
            setInterval(actualizarTiempos, 1000);
            // Llama a la función para actualizar los datos desde el servidor cada 3 segundos
            setInterval(actualizarDatos, 3000);
            
            // Carga inicial de datos
            actualizarDatos();
        });
    </script>

</body>
</html>