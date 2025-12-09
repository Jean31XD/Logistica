<?php
/**
 * Pantalla de Despacho/Tickets - MACO Design System
 */

// Seguridad de sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

session_regenerate_id(true);

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 1, 5])) {
    header("Location: ../index.php");
    exit();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$pageTitle = "Despacho de Tickets | MACO";
$additionalCSS = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />';
include __DIR__ . '/templates/header.php';
?>

<style>
    /* Estilos específicos para la tabla de tickets */
    .tickets-container {
        margin-top: 1rem;
    }

    .table-tickets {
        background: white;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .table-tickets thead {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
    }

    .table-tickets thead th {
        padding: 1rem;
        font-weight: 600;
        text-align: center;
        border: none;
    }

    .table-tickets tbody td {
        padding: 0.875rem;
        vertical-align: middle;
        text-align: center;
        border-bottom: 1px solid var(--border);
    }

    .table-tickets tbody tr {
        transition: background-color 0.2s ease;
    }

    .table-tickets tbody tr:hover {
        background-color: var(--bg-hover);
    }

    .table-tickets tbody tr.table-danger {
        background-color: rgba(220, 53, 69, 0.1) !important;
        border-left: 4px solid var(--danger);
    }

    .estatus-select {
        padding: 0.5rem;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: white;
        font-size: 0.875rem;
        min-width: 120px;
    }

    .btn-table {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        border-radius: var(--radius);
        transition: all 0.2s ease;
    }

    .modal-content {
        border-radius: var(--radius-lg);
        border: none;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        padding: 1.25rem 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        background: var(--bg-secondary);
        border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }

    .form-control, .form-select {
        border-radius: var(--radius);
        border: 1px solid var(--border);
        padding: 0.625rem 0.875rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
    }

    .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }
</style>

<h1 class="maco-title maco-title-gradient">
    <i class="fas fa-ticket-alt"></i>
    Despacho de Tickets
</h1>

<p class="maco-subtitle">
    Gestión y control de tickets de despacho en tiempo real
</p>

<div class="tickets-container">
    <div class="maco-card">
        <div class="table-responsive">
            <table id="tablaTickets" class="table table-tickets mb-0">
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
</div>

<!-- Modal Asignar Ticket -->
<div class="modal fade" id="asignarModal" tabindex="-1" aria-labelledby="asignarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAsignar">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignarModalLabel">
                        <i class="fa-solid fa-lock me-2"></i>Confirmar Asignación
                    </h5>
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
                    <button type="button" class="btn maco-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn maco-btn-primary">
                        <i class="fa-solid fa-check me-2"></i>Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Despachar Ticket -->
<div class="modal fade" id="facturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formFactura">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-truck-fast me-2"></i>Despachar Ticket
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="facturaTiket">

                    <div class="mb-3">
                        <label for="facturaNumero" class="form-label">Número(s) de Factura</label>
                        <input type="text" id="facturaNumero" class="form-control" placeholder="Ej: FT001122334;FT001122335" autocomplete="off">
                        <small class="text-muted">Puede ingresar múltiples facturas separadas por punto y coma (;)</small>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="seFueCheckbox" value="1">
                        <label class="form-check-label" for="seFueCheckbox">
                            Marcar como <strong>Se fue</strong>
                        </label>
                    </div>

                    <div class="mt-3" id="codigoSeFueContainer" style="display:none;">
                        <label for="codigoSeFue" class="form-label">Código para despachar como "Se fue":</label>
                        <input type="password" id="codigoSeFue" class="form-control" placeholder="Código de autorización">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn maco-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnEnviarFactura" class="btn maco-btn-success">
                        <i class="fa-solid fa-paper-plane me-2"></i>Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additionalJS = <<<'JS'
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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

    $('#btnEnviarFactura').on('click', function (e) {
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
JS;

include __DIR__ . '/templates/footer.php';
?>
