<?php
// --- PHP (sin cambios) ---
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Mantener en 0 para desarrollo local (http)
ini_set('session.use_strict_mode', 1);

session_start();
session_regenerate_id(true);

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 1, 5])) {
    header("Location: ../index.php");
    exit();
}
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pantalla de Tickets ✨</title>
    <link rel="icon" href="../IMG/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <style>
        /* --- ESTILOS (sin cambios) --- */
        :root { --theme-red: #d32f2f; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818); background-size: 400% 400%; animation: gradientBG 25s ease infinite; color: #fff; padding: 1.5rem; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .header-panel { background: #ffffff; color: #333; border-radius: 1.5rem; padding: 1rem 2rem; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25); display: flex; justify-content: space-between; align-items: center; }
        .header-panel .logo img { height: 60px; }
        .header-panel h1 { font-weight: 700; color: var(--theme-red); text-shadow: none; }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 1.5rem; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2); padding: 1.5rem 2rem; }
        .table-container { margin-top: 2rem; }
        .table { color: #fff; border-color: rgba(255, 255, 255, 0.2); }
        .table thead th { background: #ffffff; color: var(--theme-red); border-color: #dee2e6; font-weight: 700; }
        .table tbody tr { transition: background-color 0.3s ease, opacity 0.5s ease; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table-danger, .table-danger:hover { background-color: rgba(220, 53, 69, 0.4) !important; border-color: rgba(220, 53, 69, 0.6) !important; }
        .modal-content { background: rgba(10, 25, 40, 0.85); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; }
        .modal-header, .modal-footer { border-color: rgba(255, 255, 255, 0.2); }
        .form-control, .form-select { background-color: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.4); color: #fff; }
        .form-control:focus { background-color: rgba(0, 0, 0, 0.4); color: #fff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="header-panel mb-4 animate__animated animate__fadeInDown">
        <div class="logo"><img src="../IMG/LOGO MC - NEGRO.png" alt="Logo"></div>
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
        <div><a href="../Logica/logout.php" class="btn btn-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar Sesión</a></div>
    </div>
    <div class="table-container glass-panel animate__animated animate__fadeInUp">
        <table id="tablaTickets" class="table table-bordered text-center align-middle">
            <thead>
                <tr>
                    <th><i class="fa-solid fa-ticket me-2"></i>Ticket</th>
                    <th><i class="fa-solid fa-user me-2"></i>Nombre</th>
                    <th><i class="fa-solid fa-building me-2"></i>Empresa</th>
                    <th><i class="fa-solid fa-info-circle me-2"></i>Estatus</th>
                    <th><i class="fa-solid fa-user-check me-2"></i>Asignado A</th>
                    <th>Asignar</th>
                    <th>Despachar</th>
                    <th>Retención</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="asignarModal" tabindex="-1" aria-labelledby="asignarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAsignar">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignarModalLabel"><i class="fa-solid fa-lock me-2"></i>Confirmar Asignación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Para asignarte el ticket <strong id="asignarTicketId"></strong>, por favor ingresa tu contraseña.</p>
                    <input type="hidden" id="asignarTiketInput">
                    <div class="mb-3">
                        <label for="usuarioPassword" class="form-label">Contraseña:</label>
                        <input type="password" id="usuarioPassword" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-2"></i>Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- INICIO SCRIPT CON MEJORAS ---
$(document).ready(function () {
    const usuarioSesion = "<?php echo $_SESSION['usuario']; ?>";
    let lastCheckTimestamp = 0; // Se usará para pedir solo los datos nuevos.
    let updateInterval;

    // --- Lógica de actualización inteligente (DELTA) ---
    function actualizarTablaInteligentemente() {
        // Obtenemos los IDs de los tickets que ya están visibles en la tabla
        const currentTicketIds = $('#tablaTickets tbody tr').map(function() {
            return $(this).data('tiket-id');
        }).get();

        $.ajax({
            url: '../Logica/obtener_tickets_delta.php',
            method: 'POST',
            data: { 
                since: lastCheckTimestamp,
                current_ids: currentTicketIds // Enviamos los IDs actuales
            },
            dataType: 'json',
            success: function(response) {
                // Actualizar o agregar filas
                if (response.updates && response.updates.length > 0) {
                    response.updates.forEach(ticket => {
                        const existingRow = $(`#row_${ticket.tiket}`);
                        if (existingRow.length > 0) {
                            // Si la fila existe, la reemplaza con una animación sutil
                            existingRow.addClass('animate__animated animate__pulse');
                            setTimeout(() => existingRow.replaceWith(ticket.html), 250);
                        } else {
                            // Si es nueva, la añade al principio con animación
                            const newRow = $(ticket.html).addClass('animate__animated animate__fadeInDown');
                            $('#tablaTickets tbody').prepend(newRow);
                        }
                    });
                }

                // Eliminar filas que ya no están activas
                if (response.deletions && response.deletions.length > 0) {
                    response.deletions.forEach(tiketId => {
                        $(`#row_${tiketId}`).fadeOut(500, function() { $(this).remove(); });
                    });
                }
                
                // Actualizamos el timestamp para la próxima petición
                lastCheckTimestamp = response.timestamp;
            },
            error: function() {
                console.error("Error al actualizar la tabla.");
            }
        });
    }

    // --- Delegación de eventos para botones dinámicos ---
    $('#tablaTickets').on('click', '.btn-asignar', function() {
        const tiket = $(this).data('tiket');
        $('#asignarTicketId').text(tiket);
        $('#asignarTiketInput').val(tiket);
        $('#usuarioPassword').val(''); // Limpiar campo de contraseña
        const asignarModal = new bootstrap.Modal(document.getElementById('asignarModal'));
        asignarModal.show();
        // Enfocar el campo de contraseña al abrir el modal
        $('#asignarModal').off('shown.bs.modal').on('shown.bs.modal', function () {
            $('#usuarioPassword').focus();
        });
    });

    // --- Manejador del formulario de asignación ---
    $('#formAsignar').on('submit', function(e) {
        e.preventDefault();
        const tiket = $('#asignarTiketInput').val();
        const password = $('#usuarioPassword').val();

        if (!password) {
            alert('Por favor, ingresa tu contraseña.');
            return;
        }

        $.ajax({
            url: '../Logica/asignar_ticket.php',
            method: 'POST',
            data: { tiket: tiket, password: password },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                    actualizarTablaInteligentemente(); // Forzar actualización inmediata
                } else {
                    alert('Error: ' + response.message);
                    $('#usuarioPassword').val('').focus(); // Limpiar y enfocar de nuevo
                }
            },
            error: function() {
                alert('Ocurrió un error de comunicación. Inténtalo de nuevo.');
            }
        });
    });

    // --- Iniciar la actualización ---
    actualizarTablaInteligentemente(); // Carga inicial
    updateInterval = setInterval(actualizarTablaInteligentemente, 3000); // Revisa cambios cada 3 segundos
});
</script>
</body>
</html>