<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Mantener en 0 para desarrollo local (no HTTPS)
ini_set('session.use_strict_mode', 1);

session_start();
session_regenerate_id(true);

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 1, 5])) {
    header("Location: ../index.php");
    exit();
}

// El logout se maneja en su propio script, no aquí.
// if (isset($_GET['logout'])) { ... }

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
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #0d6efd, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientBG 25s ease infinite;
            color: #fff;
            padding: 1.5rem;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            padding: 1.5rem 2rem;
        }

        .header-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-panel .logo img {
            height: 60px;
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: 50%;
            padding: 5px;
        }
        .header-panel h1 {
            font-weight: 600;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
        }
        
        .table-container { margin-top: 2rem; }
        .table { color: #fff; border-color: rgba(255, 255, 255, 0.2); }
        .table thead th { background: rgba(0, 0, 0, 0.3); border-color: rgba(255, 255, 255, 0.3); }
        .table tbody tr { transition: background-color 0.3s ease; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table td, .table th { vertical-align: middle; }
        
        .table-danger, .table-danger:hover {
            background-color: rgba(220, 53, 69, 0.3) !important;
            border-color: rgba(220, 53, 69, 0.5) !important;
        }

        .btn { font-weight: 600; }
        .btn:disabled { transform: none; box-shadow: none; }
        
        /* Estilos Modal */
        .modal-content {
            background: rgba(10, 25, 40, 0.8);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .modal-header, .modal-footer { border-color: rgba(255, 255, 255, 0.2); }
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: #fff;
        }
        .form-control:focus { background-color: rgba(0, 0, 0, 0.4); color: #fff; }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.6); }

    </style>
</head>
<body>
<div class="container-fluid">
    <div class="header-panel glass-panel mb-4 animate__animated animate__fadeInDown">
        <div class="logo"><img src="../IMG/LOGO MC - NEGRO.png" alt="Logo"></div>
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
        <div><a href="../Logica/logout.php" class="btn btn-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar Sesión</a></div>
    </div>

    <div class="table-container glass-panel animate__animated animate__fadeInUp">
        <table id="tablaTickets" class="table table-bordered text-center">
            <thead class="table-dark-transparent"> <tr>
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
            <tbody>
                </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="facturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formFactura">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-truck-fast me-2"></i>Despachar Ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="facturaTiket">
                    <input type="text" id="facturaNumero" class="form-control" placeholder="Ej: FT001122334;FT001122335">
                    <small class="text-muted">Puede ingresar múltiples facturas separadas por punto y coma (;)</small>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="seFueCheckbox" value="1">
                        <label class="form-check-label" for="seFueCheckbox">Marcar como <strong>Se fue</strong></label>
                    </div>

                    <div class="mt-3" id="codigoSeFueContainer" style="display:none;">
                        <label for="codigoSeFue" class="form-label">Código para despachar como "Se fue":</label>
                        <input type="password" id="codigoSeFue" class="form-control" placeholder="Código de autorización">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-paper-plane me-2"></i>Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- INICIO SCRIPT ---
const usuarioSesion = "<?php echo $_SESSION['usuario']; ?>";
let timers = {}, retencionClicks = {}, retencionBloqueado = {};

function cargarTickets() {
    $.get('../Logica/obtener_tickets.php', function(response) {
        $('#tablaTickets tbody').html(response);
        $('#tablaTickets tbody tr').each(function() {
            let fila = $(this);
            let estatus = fila.find('.estatus').text().trim();
            let asignado = fila.find('.asignado-a').text().trim();
            let tiket = fila.find('.btn-despachar').data('tiket');

            // Lógica de habilitación/deshabilitación
            if (asignado && asignado !== usuarioSesion) {
                fila.find('.btn-despachar, .btn-retencion, .estatus-select').prop('disabled', true)
                    .attr('title', 'Solo el usuario asignado puede ejecutar esta acción');
            }
            if (estatus === "Retención") {
                fila.find('.btn-despachar, .estatus-select').prop('disabled', true)
                    .attr('title', 'No se puede modificar en estado de retención');
            }

            // Iniciar timer si no existe
            if (tiket && !(tiket in timers)) {
                timers[tiket] = 0;
                setInterval(() => timers[tiket]++, 1000);
            }
        });
    });
}

function despacharTicket(tiket, factura) {
    let tiempo = timers[tiket] || 0;
    $.post('../Logica/despachar_ticket.php', { tiket, tiempo, factura }, function(response) {
        if (!response.toLowerCase().includes('error')) {
            delete timers[tiket];
            cargarTickets();
        } else {
            alert(response);
        }
    });
}

function cambiarEstatus(tiket, nuevoEstatus) {
    $.post('../Logica/actualizar_estatus.php', { tiket, estatus: nuevoEstatus }, function(response) {
        console.log("Estatus actualizado: " + response);
    });
}

function asignarTicket(tiket) {
    $.post('../Logica/asignar_ticket.php', { tiket }, function() {
        cargarTickets();
    });
}

function manejarRetencion(tiket, boton) {
    if (retencionBloqueado[tiket]) return;
    retencionBloqueado[tiket] = true;
    $(boton).prop('disabled', true);

    let contador = retencionClicks[tiket] || 0;
    let accion = (contador === 0) ? 'insertar' : 'actualizar';

    $.post('../Logica/accion_retencion.php', { tiket, accion }, function(response) {
        if(accion === 'insertar') {
            retencionClicks[tiket] = 1;
        } else {
            retencionClicks[tiket] = 2;
        }
        cargarTickets(); // Recargar para obtener el estado y estilo del servidor
        retencionBloqueado[tiket] = false; // Desbloquear después de la respuesta
    });
}

$(document).ready(function () {
    cargarTickets();
    setInterval(cargarTickets, 10000); // Recarga cada 10 segundos

    // --- Manejadores de eventos ---
    $('#seFueCheckbox').on('change', function () {
        const isChecked = this.checked;
        $('#facturaNumero').prop('disabled', isChecked).val(isChecked ? '' : $('#facturaNumero').val());
        $('#codigoSeFueContainer').toggle(isChecked);
        if(!isChecked) $('#codigoSeFue').val('');
    });

    $('#formFactura').on('submit', function (e) {
        e.preventDefault();
        const tiket = $('#facturaTiket').val();
        const seFue = $('#seFueCheckbox').is(':checked');
        const facturas = $('#facturaNumero').val().trim();
        const myModal = bootstrap.Modal.getInstance(document.getElementById('facturaModal'));

        if (seFue) {
            if ($('#codigoSeFue').val().trim() !== 'LogisicA*2025*') {
                alert('Código incorrecto para despachar como "Se fue".');
                return;
            }
            if (confirm("¿Estás seguro de despachar este ticket como 'Se fue'?")) {
                despacharTicket(tiket, "Se fue");
                myModal.hide();
            }
            return;
        }

        if (!facturas) {
            alert("Por favor ingrese al menos un número de factura.");
            return;
        }

        const listaFacturas = facturas.split(';').map(f => f.trim()).filter(Boolean);
        for (let f of listaFacturas) {
            if (f.length !== 11) {
                alert(`Cada número de factura debe tener 11 caracteres. Error en: "${f}"`);
                return;
            }
        }
        
        myModal.hide();
        listaFacturas.forEach(f => despacharTicket(tiket, f));
    });

    // Delegación de eventos para elementos dinámicos
    $(document).on('click', '.btn-despachar', function() {
        const tiket = $(this).data('tiket');
        $('#facturaTiket').val(tiket);
        $('#formFactura')[0].reset(); // Limpiar formulario
        $('#facturaNumero').prop('disabled', false);
        $('#codigoSeFueContainer').hide();
        new bootstrap.Modal(document.getElementById('facturaModal')).show();
    });

    $(document).on('click', '.btn-asignar', function() {
        asignarTicket($(this).data('tiket'));
    });
    
    $(document).on('change', '.estatus-select', function() {
        cambiarEstatus($(this).data('tiket'), $(this).val());
    });

    $(document).on('click', '.btn-retencion', function () {
        manejarRetencion($(this).data('tiket'), this);
    });

    // --- Helpers y otros ---
    $('#facturaNumero').on('input', function (e) {
        let valor = e.target.value.replace(/[^A-Za-z0-9]/g, '');
        let bloques = [];
        for (let i = 0; i < valor.length; i += 11) {
            bloques.push(valor.substring(i, i + 11));
        }
        e.target.value = bloques.join(';').toUpperCase();
    }).on('keydown', function(e) {
        if (e.key === 'Enter') e.preventDefault();
    });
    
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.getEntriesByType("navigation")[0].type === "back_forward")) {
            window.location.reload();
        }
    });
});
</script>
</body>
</html>