<?php
/**
 * Pantalla de Despacho/Tickets - MACO Design System
 */

// Incluir configuración centralizada de sesión
require_once __DIR__ . '/../../conexionBD/session_config.php';
require_once __DIR__ . '/../../conexionBD/conexion.php';

// Verificar autenticación básica
if (!isset($_SESSION['usuario'])) {
    header("Location: " . getLoginUrl());
    exit();
}

// Verificar permiso usando usuario_modulos
if (!tieneModulo('despacho_factura', $conn)) {
    header("Location: " . getBaseUrl() . "/View/pantallas/Portal.php?error=permisos");
    exit();
}

$pageTitle = "Despacho de Tickets | MACO";
$additionalCSS = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />';
include __DIR__ . '/../templates/header.php';
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

    /* Toast de código de verificación */
    .codigo-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 350px;
        max-width: 450px;
        background: linear-gradient(135deg, #1D3557 0%, #457B9D 100%);
        color: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        padding: 20px;
        animation: slideInRight 0.5s ease-out;
        display: none;
    }
    
    .codigo-toast.show {
        display: block;
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .codigo-toast-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .codigo-toast-header i {
        font-size: 1.5rem;
        color: #ffc107;
    }
    
    .codigo-toast-header h5 {
        margin: 0;
        font-size: 1.1rem;
    }
    
    .codigo-display {
        background: rgba(255,255,255,0.15);
        border: 2px dashed rgba(255,255,255,0.4);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        margin: 15px 0;
    }
    
    .codigo-numero {
        font-size: 2.5rem;
        font-weight: bold;
        letter-spacing: 10px;
        font-family: 'Courier New', monospace;
        color: #ffc107;
    }
    
    .codigo-ticket {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-top: 10px;
    }
    
    .codigo-timer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
        color: #ffc107;
    }
    
    .codigo-toast-footer {
        margin-top: 15px;
        font-size: 0.85rem;
        opacity: 0.8;
        text-align: center;
    }
</style>

<!-- Toast de Código de Verificación -->
<div id="codigoToast" class="codigo-toast">
    <div class="codigo-toast-header">
        <i class="fa-solid fa-key"></i>
        <h5>Código de Verificación Solicitado</h5>
    </div>
    <p style="margin: 0; opacity: 0.9;">Alguien quiere reasignar tu ticket. Comparte este código:</p>
    <div class="codigo-display">
        <div class="codigo-numero" id="codigoNumero">------</div>
        <div class="codigo-ticket" id="codigoTicket">Ticket: ---</div>
    </div>
    <div class="codigo-timer">
        <i class="fa-solid fa-clock"></i>
        <span>Expira en: <strong id="codigoTiempo">5:00</strong></span>
    </div>
    <div class="codigo-toast-footer">
        <i class="fa-solid fa-info-circle me-1"></i>
        Proporciona este código verbalmente a quien lo solicite
    </div>
</div>

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
                        <th><i class="fa-solid fa-clock me-2"></i>Tiempo</th>
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
                        <i class="fa-solid fa-user-check me-2"></i>Confirmar Asignación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalAsignarTexto">¿Deseas asignarte el ticket <strong id="asignarTicketId"></strong>?</p>

                    <input type="hidden" id="asignarTiketInput">
                    <input type="hidden" id="currentAssigneeInput">
                    <input type="hidden" id="isReassignment" value="false">

                    <!-- Campo de código de verificación (solo visible en reasignación) -->
                    <div class="mb-3" id="codigoContainer" style="display: none;">
                        <div class="alert alert-info" id="codigoInfo">
                            <i class="fa-solid fa-desktop me-2"></i>
                            <small>Se está generando un código que aparecerá en la pantalla de <strong id="usuarioActualNombre"></strong>...</small>
                        </div>
                        <label for="codigoVerificacion" class="form-label">Código de verificación (6 dígitos):</label>
                        <input type="text" id="codigoVerificacion" class="form-control" placeholder="000000"
                               maxlength="6" pattern="[0-9]{6}" autocomplete="off"
                               style="font-size: 1.5rem; letter-spacing: 0.5rem; text-align: center; font-family: monospace;">
                        <small class="text-muted">
                            <i class="fa-solid fa-clock me-1"></i>El código expira en 5 minutos
                        </small>
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
$csrfToken = generarTokenCSRF();
$usuarioSesion = htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8');
ob_start();
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
const usuarioSesion = "<?php echo $usuarioSesion; ?>";
const csrfToken = "<?php echo $csrfToken; ?>";
$(document).ready(function () {
    let lastCheckTimestamp = 0;
    let timers = {}, retencionClicks = {}, retencionBloqueado = {};

    function actualizarTablaInteligentemente() {
        const currentTicketIds = $('#tablaTickets tbody tr').map(function() {
            return $(this).data('tiket-id');
        }).get();

        $.ajax({
            url: '../../Logica/obtener_tickets.php',
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

    // === SISTEMA DE CÓDIGOS DE VERIFICACIÓN EN PANTALLA ===
    let codigoActual = null;
    let timerIntervalo = null;
    
    function verificarCodigosPendientes() {
        $.ajax({
            url: '../../Logica/obtener_codigos_pendientes.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.tiene_pendientes) {
                    const codigo = response.codigos[0]; // Mostrar el más reciente
                    
                    // Solo actualizar si es un código nuevo
                    if (!codigoActual || codigoActual.codigo !== codigo.codigo) {
                        codigoActual = codigo;
                        mostrarCodigoToast(codigo);
                    }
                } else {
                    // No hay códigos pendientes, ocultar toast
                    ocultarCodigoToast();
                }
            },
            error: function() {
                console.log('Error al verificar códigos pendientes');
            }
        });
    }
    
    function mostrarCodigoToast(codigo) {
        $('#codigoNumero').text(codigo.codigo);
        $('#codigoTicket').text('Ticket: #' + codigo.ticket);
        $('#codigoToast').addClass('show');
        
        // Iniciar timer de cuenta regresiva
        if (timerIntervalo) clearInterval(timerIntervalo);
        
        let segundos = codigo.segundos_restantes;
        actualizarTimerDisplay(segundos);
        
        timerIntervalo = setInterval(function() {
            segundos--;
            if (segundos <= 0) {
                clearInterval(timerIntervalo);
                ocultarCodigoToast();
                codigoActual = null;
            } else {
                actualizarTimerDisplay(segundos);
            }
        }, 1000);
        
        // Reproducir sonido de notificación (opcional)
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1JQ0NBQEVNWGNteIGLjYmCe3VxcXN4f4aMj5KRjomCe3RtZmBeWlpbXmNqcnt/AIE=');
            audio.volume = 0.3;
            audio.play().catch(() => {}); // Ignorar errores si el navegador bloquea
        } catch(e) {}
    }
    
    function actualizarTimerDisplay(segundos) {
        const mins = Math.floor(segundos / 60);
        const secs = segundos % 60;
        $('#codigoTiempo').text(mins + ':' + String(secs).padStart(2, '0'));
        
        // Cambiar color cuando quede poco tiempo
        if (segundos < 60) {
            $('#codigoTiempo').css('color', '#ff6b6b');
        } else {
            $('#codigoTiempo').css('color', '#ffc107');
        }
    }
    
    function ocultarCodigoToast() {
        $('#codigoToast').removeClass('show');
        if (timerIntervalo) {
            clearInterval(timerIntervalo);
            timerIntervalo = null;
        }
    }
    
    // Iniciar polling de códigos pendientes cada 3 segundos
    verificarCodigosPendientes();
    setInterval(verificarCodigosPendientes, 3000);

    function despacharTicket(tiket, factura, codigo = '') {
        let tiempo = timers[tiket] || 0;
        let data = { tiket, tiempo, factura, csrf_token: csrfToken };
        if (codigo) {
            data.codigo = codigo;
        }
        $.post('../../Logica/despachar_ticket.php', data, function(response) {
            if (!response.toLowerCase().includes('error')) {
                delete timers[tiket];
                // Eliminar la fila inmediatamente con animación
                $('#row_' + tiket).fadeOut(400, function() {
                    $(this).remove();
                });
                alert('Ticket despachado correctamente');
            } else {
                alert(response);
            }
        }).fail(function() {
            alert('Error de comunicación al despachar');
        });
    }

    function manejarRetencion(tiket, boton) {
        if (retencionBloqueado[tiket]) return;
        
        // Verificar si ya está en retención o ya fue liberado
        const fila = $('#row_' + tiket);
        const estaEnRetencion = fila.hasClass('table-danger');
        
        // Si ya fue liberado de retención, no permitir más clicks
        if (retencionClicks[tiket] >= 2) {
            alert('Este ticket ya fue procesado en retención.');
            return;
        }
        
        retencionBloqueado[tiket] = true;
        $(boton).prop('disabled', true);

        let contador = retencionClicks[tiket] || 0;
        let accion = estaEnRetencion ? 'actualizar' : 'insertar';

        $.post('../../Logica/accion_retencion.php', { tiket, accion }, function(response) {
            retencionClicks[tiket] = contador + 1;
            retencionBloqueado[tiket] = false;
            $(boton).prop('disabled', false);
            actualizarTablaInteligentemente();
        }).fail(function() {
            retencionBloqueado[tiket] = false;
            $(boton).prop('disabled', false);
            alert('Error al procesar retención');
        });
    }

    $(document).on('click', '.btn-asignar', function() {
        if ($(this).is(':disabled')) return;

        const tiket = $(this).data('tiket');
        const asignadoA = $(this).data('asignado-a') || '';

        $('#asignarTicketId').text(tiket);
        $('#asignarTiketInput').val(tiket);
        $('#currentAssigneeInput').val(asignadoA);
        $('#codigoVerificacion').val('');

        if (asignadoA) {
            // Reasignación - mostrar campo código y solicitar automáticamente
            $('#isReassignment').val('true');
            $('#modalAsignarTexto').html(`Para re-asignar el ticket de <strong>${asignadoA}</strong>, pídele el código que aparecerá en su pantalla.`);
            $('#usuarioActualNombre').text(asignadoA);
            $('#codigoInfo').html('<i class="fa-solid fa-spinner fa-spin me-2"></i><small>Generando código para <strong>' + asignadoA + '</strong>...</small>');
            $('#codigoContainer').show();
            $('#codigoVerificacion').prop('required', true);

            // Generar código automáticamente
            $.ajax({
                url: '../../Logica/solicitar_codigo_verificacion.php',
                method: 'POST',
                data: {
                    usuario: asignadoA,
                    ticket: tiket,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#codigoInfo').html('<i class="fa-solid fa-check-circle me-2" style="color:green;"></i><small>Código generado. <strong>' + asignadoA + '</strong> lo verá en su pantalla. Pídele el código.</small>');
                    } else {
                        $('#codigoInfo').html('<i class="fa-solid fa-exclamation-triangle me-2" style="color:orange;"></i><small>Error: ' + response.message + '</small>');
                    }
                },
                error: function() {
                    $('#codigoInfo').html('<i class="fa-solid fa-times-circle me-2" style="color:red;"></i><small>Error al generar el código. Intenta cerrar y abrir el modal nuevamente.</small>');
                }
            });
        } else {
            // Asignación nueva - no requiere código
            $('#isReassignment').val('false');
            $('#modalAsignarTexto').html(`¿Deseas asignarte el ticket <strong>${tiket}</strong>?`);
            $('#codigoContainer').hide();
            $('#codigoVerificacion').prop('required', false);
        }

        const asignarModal = new bootstrap.Modal(document.getElementById('asignarModal'));
        asignarModal.show();

        if (asignadoA) {
            $('#asignarModal').off('shown.bs.modal').on('shown.bs.modal', () => {
                setTimeout(() => $('#codigoVerificacion').focus(), 500);
            });
        }
    });

    $('#formAsignar').on('submit', function(e) {
        e.preventDefault();
        const tiket = $('#asignarTiketInput').val();
        const codigoVerificacion = $('#codigoVerificacion').val();
        const currentAssignee = $('#currentAssigneeInput').val();
        const isReassignment = $('#isReassignment').val() === 'true';

        // Solo validar código si es reasignación
        if (isReassignment && !codigoVerificacion) {
            alert('Por favor, ingresa el código de verificación.');
            $('#codigoVerificacion').focus();
            return;
        }

        // Validar formato del código (6 dígitos)
        if (isReassignment && !/^\d{6}$/.test(codigoVerificacion)) {
            alert('El código debe tener 6 dígitos numéricos.');
            $('#codigoVerificacion').focus();
            return;
        }

        $.ajax({
            url: '../../Logica/asignar_ticket.php',
            method: 'POST',
            data: {
                tiket: tiket,
                codigo_verificacion: codigoVerificacion,
                current_assignee: currentAssignee,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                    actualizarTablaInteligentemente();
                } else {
                    alert('Error: ' + response.message);
                    if (isReassignment) {
                        $('#codigoVerificacion').val('').focus();
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
        $.post('../../Logica/actualizar_estatus.php', { 
            tiket: tiket, 
            estatus: nuevoEstatus,
            csrf_token: csrfToken
        }).done(function(response) {
            console.log('Estatus actualizado:', response);
        }).fail(function(xhr) {
            alert('Error al actualizar estatus');
            console.error('Error:', xhr.responseText);
        });
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
            const codigo = $('#codigoSeFue').val().trim();
            if (!codigo) {
                return alert('Debe ingresar un código para despachar como "Se fue".');
            }
            if (confirm("¿Estás seguro de despachar este ticket como 'Se fue'?")) {
                despacharTicket(tiket, "Se fue", codigo);
                myModal.hide();
            }
            return;
        }

        if (!facturas) {
            return alert("Por favor ingrese al menos un número de factura.");
        }

        // Validar que cada factura comience con FT
        const listaFacturas = facturas.split(';').filter(f => f.trim() !== '');
        for (const factura of listaFacturas) {
            const prefijo = factura.trim().substring(0, 2).toUpperCase();
            if (prefijo !== 'FT') {
                return alert(`Error: La factura "${factura.trim()}" debe comenzar con "FT".`);
            }
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
            const url = new URL(window.location.href);
            url.searchParams.set('cache_bust', new Date().getTime());
            window.location.href = url.href;
        }
    });
});
</script>
<?php
$additionalJS = ob_get_clean();

include __DIR__ . '/../templates/footer.php';
?>
