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

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Panel superior blanco */
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

        /* Panel de vidrio para el contenedor de la tabla */
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
        
        /* Encabezado de la tabla blanco */
        .table thead th {
            background: #ffffff;
            color: var(--theme-red);
            border-color: #dee2e6;
            font-weight: 700;
        }

        .table tbody tr { transition: background-color 0.3s ease; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table td, .table th { vertical-align: middle; }
        
        .table-danger, .table-danger:hover {
            background-color: rgba(220, 53, 69, 0.4) !important;
            border-color: rgba(220, 53, 69, 0.6) !important;
        }

        .btn { font-weight: 600; }
        .btn:disabled { transform: none; box-shadow: none; }
        
        /* Modal mantiene el estilo oscuro/vidrio */
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
    let lastCheckTimestamp = 0; // Almacena el tiempo de la última revisión

    /**
     * Función principal para actualizar la tabla de forma inteligente.
     */
    function actualizarTablaInteligentemente() {
        const currentTicketIds = $('#tablaTickets tbody tr').map(function() {
            return $(this).data('tiket-id');
        }).get();

        $.ajax({
            url: '../Logica/obtener_tickets_delta.php',
            method: 'POST',
            data: { 
                since: lastCheckTimestamp,
                current_ids: currentTicketIds
            },
            dataType: 'json',
            success: function(response) {
                // Actualizar o agregar filas
                if (response.updates && response.updates.length > 0) {
                    response.updates.forEach(ticket => {
                        const existingRow = $(`#row_${ticket.tiket}`);
                        if (existingRow.length > 0) {
                            existingRow.addClass('animate__pulse');
                            setTimeout(() => {
                                // Guardar el valor del select si existe
                                const selectVal = existingRow.find('.estatus-select').val();
                                existingRow.replaceWith(ticket.html);
                                // Restaurar el valor del select si la nueva fila aún lo tiene
                                const newSelect = $(`#row_${ticket.tiket}`).find('.estatus-select');
                                if (newSelect.length) newSelect.val(selectVal);

                            }, 500);
                        } else {
                            const newRow = $(ticket.html).addClass('animate__fadeInDown');
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
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error al actualizar la tabla:", textStatus, errorThrown);
            }
        });
    }

    // --- Inicio y el intervalo de actualización ---
    actualizarTablaInteligentemente(); // Carga inicial
    setInterval(actualizarTablaInteligentemente, 3000); // Revisa cambios cada 3 segundos

    
    // --- MANEJADORES DE EVENTOS ---

    // 1. Al hacer clic en "Asignar", ABRE EL MODAL
    $(document).on('click', '.btn-asignar', function() {
        if ($(this).is(':disabled')) return;

        const tiket = $(this).data('tiket');
        // Preparamos el modal
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

    // 2. Al ENVIAR el formulario del modal de asignación
    $('#formAsignar').on('submit', function(e) {
        e.preventDefault(); // Evitamos que la página se recargue

        const tiket = $('#asignarTiketInput').val();
        const password = $('#usuarioPassword').val();

        if (!password) {
            alert('Por favor, ingresa tu contraseña.');
            return;
        }

        // Enviamos los datos al servidor para la verificación
        $.ajax({
            url: '../Logica/asignar_ticket.php',
            method: 'POST',
            data: { tiket: tiket, password: password },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Si es exitoso, cierra el modal y actualiza la tabla
                    bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                    actualizarTablaInteligentemente(); // Forzar actualización inmediata
                } else {
                    // Si falla, muestra el error y permite reintentar
                    alert('Error: ' + response.message);
                    $('#usuarioPassword').val('').focus(); // Limpiar y enfocar de nuevo
                }
            },
            error: function() {
                alert('Ocurrió un error de comunicación. Inténtalo de nuevo.');
            }
        });
    });
    
    // 3. Al cambiar el estatus en el <select>
    $(document).on('change', '.estatus-select', function() {
        if ($(this).is(':disabled')) return;
        const tiket = $(this).data('tiket');
        const nuevoEstatus = $(this).val();
        
        $.post('../Logica/actualizar_estatus.php', { tiket, estatus: nuevoEstatus });
        // No es necesario llamar a la actualización aquí, el trigger de la BD lo hará
        // y el intervalo regular de 3 segundos lo detectará.
    });

});
</script>
</body>
</html>