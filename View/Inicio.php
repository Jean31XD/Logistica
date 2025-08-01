<?php
// --- PHP (sin cambios) ---
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
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
        :root { --theme-red: #d32f2f; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 25s ease infinite;
            color: #fff;
            padding: 1.5rem;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; }
        }
        .header-panel {
            background: #ffffff; color: #333; border-radius: 1.5rem; padding: 1rem 2rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25); display: flex; justify-content: space-between; align-items: center;
        }
        .header-panel .logo img { height: 60px; }
        .header-panel h1 { font-weight: 700; color: var(--theme-red); text-shadow: none; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 1.5rem; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2); padding: 1.5rem 2rem;
        }
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
        <table id="tablaTickets" class="table table-bordered text-center">
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

<div class="modal fade" id="facturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formFactura"><div class="modal-header"><h5 class="modal-title">Despachar Ticket</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="facturaTiket"><input type="text" id="facturaNumero" class="form-control" placeholder="Ej: FT001122334;FT001122335"><small class="text-muted">Múltiples facturas separadas por punto y coma (;)</small><div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="seFueCheckbox" value="1"><label class="form-check-label" for="seFueCheckbox">Marcar como <strong>Se fue</strong></label></div><div class="mt-3" id="codigoSeFueContainer" style="display:none;"><label for="codigoSeFue" class="form-label">Código para "Se fue":</label><input type="password" id="codigoSeFue" class="form-control" placeholder="Código de autorización"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Enviar</button></div></form>
        </div>
    </div>
</div>

<div class="modal fade" id="asignarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAsignar">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-lock me-2"></i>Confirmar Asignación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Para asignarte el ticket <strong id="asignarTicketId"></strong>, por favor ingresa tu contraseña.</p>
                    <input type="hidden" id="asignarTiketInput">
                    <label for="usuarioPassword" class="form-label">Contraseña:</label>
                    <input type="password" id="usuarioPassword" class="form-control" required>
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
const usuarioSesion = "<?php echo $_SESSION['usuario']; ?>";
let lastCheck = 0; // Timestamp de la última revisión

// --- Lógica de actualización inteligente ---
function actualizarTablaInteligentemente() {
    $.getJSON('../Logica/obtener_tickets.php', { since: lastCheck }, function(data) {
        if (data.tickets && data.tickets.length > 0) {
            data.tickets.forEach(ticket => {
                const existingRow = $(`#row_${ticket.tiket}`);
                if (existingRow.length > 0) {
                    // Si la fila existe, la actualiza
                    existingRow.replaceWith(ticket.html);
                } else {
                    // Si es nueva, la añade al principio con una animación
                    const newRow = $(ticket.html).css('opacity', 0);
                    $('#tablaTickets tbody').prepend(newRow);
                    newRow.animate({ opacity: 1 }, 500);
                }
            });
        }
        
        if (data.eliminados && data.eliminados.length > 0) {
            data.eliminados.forEach(tiket => {
                $(`#row_${tiket}`).fadeOut(500, function() { $(this).remove(); });
            });
        }
        
        lastCheck = data.timestamp; // Actualiza el timestamp para la próxima petición
    });
}


function asignarTicket(tiket, password) {
    $.post('../Logica/asignar_ticket.php', { tiket, password }, function(response) {
        if (response.success) {
            bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
            actualizarTablaInteligentemente(); // Forzar actualización inmediata
        } else {
            alert(response.message || 'Ocurrió un error.');
        }
    }, 'json');
}

// Lógica de despacho y retención (sin cambios mayores)
function despacharTicket(tiket, factura) { /* ... */ }
function manejarRetencion(tiket, boton) { /* ... */ }

// --- Document Ready: Event Handlers ---
$(document).ready(function () {
    actualizarTablaInteligentemente(); // Carga inicial
    setInterval(actualizarTablaInteligentemente, 5000); // Revisa cambios cada 5 segundos

    // --- Delegación de eventos para botones ---
    $(document).on('click', '.btn-asignar', function() {
        const tiket = $(this).data('tiket');
        $('#asignarTicketId').text(tiket);
        $('#asignarTiketInput').val(tiket);
        $('#usuarioPassword').val('');
        new bootstrap.Modal(document.getElementById('asignarModal')).show();
    });
    
    $('#formAsignar').on('submit', function(e) {
        e.preventDefault();
        const tiket = $('#asignarTiketInput').val();
        const password = $('#usuarioPassword').val();
        if (!password) {
            alert('Por favor, ingresa tu contraseña.');
            return;
        }
        asignarTicket(tiket, password);
    });

    // Otros handlers como despacho, retención, etc.
    // ... (El resto de la lógica de los modals y botones permanece similar)
});
</script>
</body>
</html>