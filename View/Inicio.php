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
                        <p id="modal-text">Para asignarte el ticket <strong id="asignarTicketId"></strong>, por favor ingresa tu contraseña.</p>
                        
                        <input type="hidden" id="asignarTiketInput">
                        <input type="hidden" id="asignadoActualInput">
                        
                        <div id="passwordActualContainer" class="mb-3" style="display:none;">
                            <label for="passwordActual" class="form-label">Contraseña de <span id="passwordLabelActual"></span>:</label>
                            <input type="password" id="passwordActual" class="form-control" required>
                        </div>
                        <div id="passwordNuevoContainer" class="mb-3">
                            <label for="passwordNuevo" class="form-label">Tu Contraseña:</label>
                            <input type="password" id="passwordNuevo" class="form-control" required>
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
        setInterval(actualizarTablaInteligentemente, 3000);

        function despacharTicket(tiket, factura) {
            let tiempo = timers[tiket] || 0;
            $.post('../Logica/despachar_ticket.php', { tiket, tiempo, factura }, function(response) {
                if (!response.toLowerCase().includes('error')) {
                    delete timers[tiket];
                    $(`#row_${tiket}`).fadeOut(400, function() {
                        $(this).remove();
                    });
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
                $(boton).prop('disabled', false);
                actualizarTablaInteligentemente();
            });
        }

        $(document).on('click', '.btn-asignar', function() {
            if ($(this).is(':disabled')) return;
            const tiket = $(this).data('tiket');
            const asignadoActual = $(`#row_${tiket}`).find('.asignado-a').text().trim();
            
            $('#asignarTicketId').text(tiket);
            $('#asignarTiketInput').val(tiket);
            $('#asignadoActualInput').val(asignadoActual);
            
            // Lógica para mostrar/ocultar los campos del modal
            if (asignadoActual === 'No asignado') {
                $('#modal-text').html(`Para asignarte el ticket <strong id="asignarTicketId"></strong>, por favor ingresa tu contraseña.`);
                $('#passwordActualContainer').hide();
                $('#passwordActual').prop('required', false);
                $('#passwordNuevo').prop('required', true).focus();
            } else {
                $('#modal-text').html(`Para reasignarte el ticket <strong id="asignarTicketId"></strong>, por favor ingresa la contraseña del usuario actualmente asignado (<span id="asignadoActualSpan"></span>) y tu propia contraseña.`);
                $('#asignadoActualSpan').text(asignadoActual);
                $('#passwordLabelActual').text(asignadoActual);
                $('#passwordActualContainer').show();
                $('#passwordActual').prop('required', true).focus();
                $('#passwordNuevo').prop('required', true);
            }

            $('#passwordActual').val('');
            $('#passwordNuevo').val('');
            
            const asignarModal = new bootstrap.Modal(document.getElementById('asignarModal'));
            asignarModal.show();
        });

        $('#formAsignar').on('submit', function(e) {
            e.preventDefault();
            const tiket = $('#asignarTiketInput').val();
            const passwordActual = $('#passwordActual').val();
            const passwordNuevo = $('#passwordNuevo').val();
            const asignadoActual = $('#asignadoActualInput').val();

            // Si el ticket no está asignado, solo se valida la contraseña del nuevo usuario
            if (asignadoActual === 'No asignado' && !passwordNuevo) {
                 alert('Por favor, ingresa tu contraseña.');
                 return;
            }
            // Si el ticket ya está asignado, se validan ambas contraseñas
            if (asignadoActual !== 'No asignado' && (!passwordActual || !passwordNuevo)) {
                alert('Por favor, ingresa ambas contraseñas.');
                return;
            }

            $.ajax({
                url: '../Logica/asignar_ticket.php',
                method: 'POST',
                data: { 
                    tiket: tiket,
                    password_actual: passwordActual,
                    password_nuevo: passwordNuevo,
                    asignado_actual: asignadoActual
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                        actualizarTablaInteligentemente();
                    } else {
                        alert('Error: ' + response.message);
                        if (asignadoActual !== 'No asignado') {
                            $('#passwordActual').val('').focus();
                        } else {
                             $('#passwordNuevo').val('').focus();
                        }
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

        $('#formFactura').on('submit', function (e) {
            e.preventDefault();
            const tiket = $('#facturaTiket').val();
            const seFue = $('#seFueCheckbox').is(':checked');
            const facturas = $('#facturaNumero').val().trim();
            const myModal = bootstrap.Modal.getInstance(document.getElementById('facturaModal'));

            if (seFue) {
                if ($('#codigoSeFue').val().trim() !== 'LogisicA*2025*') {
                    return alert('Código incorrecto para despachar como "Se fue".');
                }
                if (confirm("¿Estás seguro de despachar este ticket como 'Se fue'?")) {
                    myModal.hide();
                    despacharTicket(tiket, "Se fue");
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
            $('#facturaNumero').prop('disabled', isChecked).val(isChecked ? '' : '');
            $('#codigoSeFueContainer').toggle(isChecked);
            if(!isChecked) $('#codigoSeFue').val('');
        });
        
        function manejarRetencion(tiket, boton) {
            if (retencionBloqueado[tiket]) return;
            retencionBloqueado[tiket] = true;
            $(boton).prop('disabled', true);
            let contador = retencionClicks[tiket] || 0;
            if (contador === 0) {
                $.post('../Logica/accion_retencion.php', { tiket, accion: 'insertar' }, function(response) {
                    retencionClicks[tiket] = 1;
                    $('#row_' + tiket).addClass('table-danger');
                    $('#row_' + tiket + ' .estatus').text('Retención');
                    $(boton).prop('disabled', false);
                    retencionBloqueado[tiket] = false;
                });
            } else if (contador === 1) {
                $.post('../Logica/accion_retencion.php', { tiket, accion: 'actualizar' }, function(response) {
                    retencionClicks[tiket] = 2;
                    $('#row_' + tiket).removeClass('table-danger');
                    $('#row_' + tiket + ' .estatus').text('En Proceso');
                    $(boton).prop('disabled', true);
                });
            } else {
                alert("Este botón ya no se puede presionar más.");
            }
        }

        $(document).on('click', '.btn-retencion', function () {
            let tiket = $(this).data('tiket');
            manejarRetencion(tiket, this);
        });

        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.getEntriesByType("navigation")[0].type === "back_forward")) {
                window.location.reload();
            }
        });
    });


$(document).ready(function () {
    const usuarioSesion = "<?php echo $_SESSION['usuario']; ?>";
    let lastCheckTimestamp = 0;
    let timers = {}, retencionClicks = {}, retencionBloqueado = {};

    function actualizarTablaInteligentemente() {
        // ... (resto del código igual) ...

        $.ajax({
            url: '../Logica/obtener_tickets.php',
            method: 'POST',
            data: {
                since: lastCheckTimestamp,
                current_ids: currentTicketIds
            },
            dataType: 'json',
            success: function(response) {
                // ... (resto del código igual) ...
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error al actualizar la tabla:", textStatus, errorThrown);
            }
        });
    }

    // ... (otras funciones como despacharTicket, manejarRetencion, etc. sin cambios) ...

    $(document).on('click', '.btn-asignar', function() {
        const tiket = $(this).data('tiket');
        const asignadoActual = $(`#row_${tiket}`).find('.asignado-a').text().trim();
        
        $('#asignarTicketId').text(tiket);
        $('#asignarTiketInput').val(tiket);
        $('#asignadoActualInput').val(asignadoActual);
        
        // Lógica para mostrar/ocultar los campos del modal
        if (asignadoActual === 'No asignado' || asignadoActual === '') {
            $('#modal-text').html(`Para asignarte el ticket <strong>${tiket}</strong>, por favor ingresa tu contraseña.`);
            $('#passwordActualContainer').hide();
            $('#passwordActual').prop('required', false);
            $('#passwordNuevo').prop('required', true).focus();
        } else {
            $('#modal-text').html(`Para reasignarte el ticket <strong>${tiket}</strong>, por favor ingresa la contraseña del usuario actualmente asignado (${asignadoActual}) y tu propia contraseña.`);
            $('#asignadoActualSpan').text(asignadoActual);
            $('#passwordLabelActual').text(asignadoActual);
            $('#passwordActualContainer').show();
            $('#passwordActual').prop('required', true).focus();
            $('#passwordNuevo').prop('required', true);
        }

        $('#passwordActual').val('');
        $('#passwordNuevo').val('');
        
        const asignarModal = new bootstrap.Modal(document.getElementById('asignarModal'));
        asignarModal.show();
    });

    $('#formAsignar').on('submit', function(e) {
        // ... (código para manejar el submit del formulario sin cambios) ...
    });
    
    // ... (resto de los manejadores de eventos sin cambios) ...
});



    </script>
</body>
</html>