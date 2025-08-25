<?php
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
        :root {
            --theme-red: #d32f2f;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 25s ease infinite;
            color: #fff;
            padding: 1.5rem;
        }

        .header-panel {
            background: #ffffff;
            color: #333;
            border-radius: 1.5rem;
            padding: 1rem 2rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-panel .logo img {
            height: 60px;
        }
        .header-panel h1 {
            font-weight: 700;
            color: var(--theme-red);
            text-shadow: none;
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
        
        .table-container { margin-top: 2rem; }
        .table { color: #fff; border-color: rgba(255, 255, 255, 0.2); }
        
        .table thead th {
            background: #ffffff;
            color: var(--theme-red);
            border-color: #dee2e6;
            font-weight: 700;
        }

        .table td, .table th {
            vertical-align: middle;
            padding: 0.75rem 1rem;
        }

        .table tbody tr { transition: background-color 0.3s ease; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        
        .table-danger, .table-danger:hover {
            background-color: rgba(220, 53, 69, 0.4) !important;
            border-color: rgba(220, 53, 69, 0.6) !important;
        }

        .btn { font-weight: 600; }
        .btn:disabled { transform: none; box-shadow: none; }
        
        .modal-content {
            background: rgba(10, 25, 40, 0.85);
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
<div class="modal fade" id="asignarModal" tabindex="-1" aria-labelledby="asignarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAsignar">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignarModalLabel"><i class="fa-solid fa-lock me-2"></i>Confirmar Asignación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalAsignarTexto">Para asignarte el ticket <strong id="asignarTicketId"></strong>, por favor ingresa tu contraseña.</p>
                    
                    <input type="hidden" id="asignarTiketInput">
                    <input type="hidden" id="currentAssigneeInput"> 
                    
                    <div class="mb-3">
                        <label for="usuarioPassword" id="passwordLabel" class="form-label">Tu Contraseña:</label>
                        <input type="password" id="usuarioPassword" class="form-control" required autocomplete="current-password">
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
                    <input type="text" id="facturaNumero" class="form-control" placeholder="Ej: FT001122334;FT001122335" autocomplete="off">
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
                    <button type="button" id="btnEnviarFactura" class="btn btn-success"><i class="fa-solid fa-paper-plane me-2"></i>Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
    const usuarioSesion = "<?php echo $_SESSION['usuario']; ?>";
    let lastCheckTimestamp = 0;
    let timers = {}, retencionClicks = {}, retencionBloqueado = {};

    function actualizarTablaInteligentemente() {
        const currentTicketIds = $('#tablaTickets tbody tr').map(function() {
            return $(this).data('tiket-id');
        }).get();

        $.ajax({
            url: '../Logica/obtener_tickets.php', 
            method: 'POST',
            data: { 
                since: lastCheckTimestamp,
                current_ids: currentTicketIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.updates && response.updates.length > 0) {
                    response.updates.forEach(ticket => {
                        const existingRow = $(`#row_${ticket.tiket}`);
                        if (existingRow.length > 0) {
                            const selectVal = existingRow.find('.estatus-select').val();
                            existingRow.replaceWith(ticket.html);
                            const newSelect = $(`#row_${ticket.tiket}`).find('.estatus-select');
                            if (newSelect.length) newSelect.val(selectVal);
                        } else {
                            const newRow = $(ticket.html);
                            $('#tablaTickets tbody').prepend(newRow);
                        }
                    });
                }
                if (response.deletions && response.deletions.length > 0) {
                    response.deletions.forEach(tiketId => {
                        $(`#row_${tiketId}`).fadeOut(400, function() { $(this).remove(); });
                    });
                }
                lastCheckTimestamp = response.timestamp;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error al actualizar la tabla:", textStatus, errorThrown);
            }
        });
    }

    actualizarTablaInteligentemente();
    setInterval(actualizarTablaInteligentemente, 5000);

    function despacharTicket(tiket, factura) {
        let tiempo = timers[tiket] || 0;
        $.post('../Logica/despachar_ticket.php', { tiket, tiempo, factura }, function(response) {
            if (!response.toLowerCase().includes('error')) {
                delete timers[tiket];
                actualizarTablaInteligentemente(); 
            } else {
                alert(response);
            }
        });
    }

    function manejarRetencion(tiket, boton) {
        if (retencionBloqueado[tiket]) return;
        retencionBloqueado[tiket] = true;
        $(boton).prop('disabled', true);

        let contador = retencionClicks[tiket] || 0;
        let accion = (contador % 2 === 0) ? 'insertar' : 'actualizar';

        $.post('../Logica/accion_retencion.php', { tiket, accion }, function(response) {
            retencionClicks[tiket] = (contador + 1);
            retencionBloqueado[tiket] = false;
            actualizarTablaInteligentemente();
        });
    }

    $(document).on('click', '.btn-asignar', function() {
        if ($(this).is(':disabled')) return;
        
        const tiket = $(this).data('tiket');
        const asignadoA = $(this).data('asignado-a') || '';

        $('#asignarTicketId').text(tiket);
        $('#asignarTiketInput').val(tiket);
        $('#currentAssigneeInput').val(asignadoA);
        $('#usuarioPassword').val('');

        if (asignadoA) {
            $('#modalAsignarTexto').html(`Para re-asignar el ticket de <strong>${asignadoA}</strong>, por favor ingresa la contraseña de <strong>${asignadoA}</strong>.`);
            $('#passwordLabel').text(`Contraseña de ${asignadoA}:`);
        } else {
            $('#modalAsignarTexto').html(`Para asignarte el ticket <strong>${tiket}</strong>, por favor ingresa tu contraseña.`);
            $('#passwordLabel').text('Tu Contraseña:');
        }

        const asignarModal = new bootstrap.Modal(document.getElementById('asignarModal'));
        asignarModal.show();
        $('#asignarModal').off('shown.bs.modal').on('shown.bs.modal', () => $('#usuarioPassword').focus());
    });

    $('#formAsignar').on('submit', function(e) {
        e.preventDefault();
        const tiket = $('#asignarTiketInput').val();
        const password = $('#usuarioPassword').val();
        const currentAssignee = $('#currentAssigneeInput').val();

        if (!password) {
            alert('Por favor, ingresa la contraseña requerida.');
            return;
        }

        $.ajax({
            url: '../Logica/asignar_ticket.php',
            method: 'POST',
            data: {
                tiket: tiket,
                password: password,
                current_assignee: currentAssignee
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                    actualizarTablaInteligentemente(); 
                } else {
                    alert('Error: ' + response.message);
                    $('#usuarioPassword').val('').focus();
                }
            },
            error: () => alert('Ocurrió un error de comunicación. Inténtalo de nuevo.')
        });
    });

    $(document).on('change', '.estatus-select', function() {
        if ($(this).is(':disabled')) return;
        const tiket = $(this).data('tiket');
        const nuevoEstatus = $(this).val();
        $.post('../Logica/actualizar_estatus.php', { tiket, estatus: nuevoEstatus });
    });

    $(document).on('click', '.btn-despachar', function() {
        if ($(this).is(':disabled')) return;
        const tiket = $(this).data('tiket');
        $('#facturaTiket').val(tiket);
        $('#formFactura')[0].reset();
        $('#facturaNumero').prop('disabled', false);
        $('#codigoSeFueContainer').hide();
        new bootstrap.Modal(document.getElementById('facturaModal')).show();
    });
    
    $('#facturaNumero').on('input', function() {
        let valor = $(this).val();
        valor = valor.replace(/;/g, '');
        const regex = new RegExp(`(.{11})`, 'g');
        const nuevoValor = valor.replace(regex, '$1;');
        if (nuevoValor.endsWith(';') && nuevoValor.length > 1) {
            $(this).val(nuevoValor.slice(0, -1));
        } else {
            $(this).val(nuevoValor);
        }
    });

    // Cambiamos el evento a un clic en el botón, no en el submit del formulario
    $('#btnEnviarFactura').on('click', function (e) {
        e.preventDefault(); // Detiene el envío por defecto, aunque el botón es de tipo 'button'
        
        const tiket = $('#facturaTiket').val();
        const seFue = $('#seFueCheckbox').is(':checked');
        const facturas = $('#facturaNumero').val().trim();
        const myModal = bootstrap.Modal.getInstance(document.getElementById('facturaModal'));

        if (seFue) {
            if ($('#codigoSeFue').val().trim() !== 'LogisicA*2025*') {
                return alert('Código incorrecto para despachar como "Se fue".');
            }
            if (confirm("¿Estás seguro de despachar este ticket como 'Se fue'?")) {
                despacharTicket(tiket, "Se fue");
                myModal.hide();
            }
            return;
        }

        if (!facturas) {
            return alert("Por favor ingrese al menos un número de factura.");
        }
        
        myModal.hide();
        despacharTicket(tiket, facturas);
    });

    $(document).on('click', '.btn-retencion', function () {
        let tiket = $(this).data('tiket');
        manejarRetencion(tiket, this);
    });

    $('#seFueCheckbox').on('change', function () {
        const isChecked = this.checked;
        $('#facturaNumero').prop('disabled', isChecked).val(isChecked ? '' : $('#facturaNumero').val());
        $('#codigoSeFueContainer').toggle(isChecked);
        if(!isChecked) $('#codigoSeFue').val('');
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