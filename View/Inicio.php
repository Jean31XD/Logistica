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
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    header("Location: ../View/index.php");
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
    <title>Pantalla de Tickets</title>
    <link rel="icon" href="../IMG/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../View/styles2.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        .btn-action { width: 100%; }
        .time-cell { font-weight: bold; }
    </style>
</head>
<body>
<div class="container bg-white mt-2 p-2 mb-3 rounded shadow">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div><img src="../IMG/LOGO MC - NEGRO.png" alt="Logo" class="img-fluid" style="max-height: 120px;"></div>
        <h1 class="mt-3 text-dark">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
        <div><a href="../Logica/logout.php" class="btn btn-danger">Cerrar Sesión</a></div>
    </div>
</div>

<table id="tablaTickets" class="table table-bordered text-center">
    <thead class="table-dark">
        <tr>
            <th>Ticket</th>
            <th>Nombre</th>
            <th>Empresa</th>
            <th>Estatus</th>
            <th>Asignado A</th>
            <th>Asignar</th>
            <th>Despachar</th>
            <th>Retención</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<!-- Modal -->
<div class="modal fade" id="facturaModal" tabindex="-1" aria-labelledby="facturaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formFactura">
        <div class="modal-header">
          <h5 class="modal-title">Despachar Ticket</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="facturaTiket">
          <input type="text" id="facturaNumero" class="form-control" placeholder="Ej: FT001122334;FT001122335">
          <small class="text-muted">Puede ingresar múltiples facturas separadas por punto y coma (;)</small>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="seFueCheckbox" value="1">
            <label class="form-check-label" for="seFueCheckbox">Marcar como <strong>Se fue</strong></label>
          </div>

          <div class="mt-3" id="codigoSeFueContainer" style="display:none;">
            <label for="codigoSeFue" class="form-label">Código para despachar como "Se fue":</label>
            <input type="password" id="codigoSeFue" class="form-control" placeholder="Código">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Enviar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

            if (asignado !== usuarioSesion) {
                fila.find('.btn-despachar, .btn-retencion, .estatus-select').prop('disabled', true)
                    .attr('title', 'Solo el usuario asignado puede ejecutar esta acción');
            }

            if (estatus === "Retención") {
                fila.find('.btn-despachar, .estatus-select').prop('disabled', true)
                    .attr('title', 'No se puede modificar en estado de retención');
            }

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


$(document).ready(function () {
    cargarTickets();
    setInterval(cargarTickets, 10000);

    $('#seFueCheckbox').on('change', function () {
        if (this.checked) {
            $('#facturaNumero').val('').prop('disabled', true);
            $('#codigoSeFueContainer').show();
        } else {
            $('#facturaNumero').prop('disabled', false);
            $('#codigoSeFueContainer').hide();
            $('#codigoSeFue').val('');
        }
    });

    $('#formFactura').on('submit', function (e) {
        e.preventDefault();
        let tiket = $('#facturaTiket').val();
        let seFue = $('#seFueCheckbox').is(':checked');
        let facturas = $('#facturaNumero').val().trim();

        if (seFue) {
            let codigoIngresado = $('#codigoSeFue').val().trim();
            if (codigoIngresado !== 'LogisicA*2025*') {
                alert('Código incorrecto para despachar como "Se fue".');
                return;
            }
            if (!confirm("¿Estás seguro de despachar este ticket como 'Se fue'?")) return;
            despacharTicket(tiket, "Se fue");
            bootstrap.Modal.getInstance(document.getElementById('facturaModal')).hide();
            return;
        }

        if (!facturas) {
            alert("Por favor ingrese al menos un número de factura.");
            return;
        }

        let listaFacturas = facturas.split(';').map(f => f.trim()).filter(f => f !== '');
        for (let f of listaFacturas) {
            if (f.length !== 11) {
                alert("Cada número de factura debe tener 11 caracteres. Error en: " + f);
                return;
            }
        }

        bootstrap.Modal.getInstance(document.getElementById('facturaModal')).hide();
        listaFacturas.forEach(f => despacharTicket(tiket, f));
    });

    $(document).on('click', '.btn-despachar', function() {
        let tiket = $(this).data('tiket');
        $('#facturaTiket').val(tiket);
        $('#facturaNumero').val('').prop('disabled', false);
        $('#seFueCheckbox').prop('checked', false);
        $('#codigoSeFueContainer').hide();
        $('#codigoSeFue').val('');
        new bootstrap.Modal(document.getElementById('facturaModal')).show();
    });

    $(document).on('change', '.estatus-select', function() {
        cambiarEstatus($(this).data('tiket'), $(this).val());
    });

    $('#facturaNumero').on('keydown', function(e) {
        if (e.key === 'Enter') e.preventDefault();
    });

    document.getElementById('facturaNumero').addEventListener('input', function (e) {
        let valor = e.target.value.replace(/[^A-Za-z0-9]/g, '');
        let bloques = [];
        for (let i = 0; i < valor.length; i += 11) bloques.push(valor.substring(i, i + 11));
        e.target.value = bloques.join(';');
    });

    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.getEntriesByType("navigation")[0].type === "back_forward")) {
            window.location.reload(true);
        }
    });
});

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
        window.location.reload(true);
    }
});

document.getElementById('facturaNumero').addEventListener('input', function (e) {
    let valor = e.target.value.replace(/[^A-Za-z0-9]/g, ''); 
    let bloques = [];

    for (let i = 0; i < valor.length; i += 11) {
        bloques.push(valor.substring(i, i + 11));
    }

    e.target.value = bloques.join(';');

    
});

</script>

</script>
</body>
</html>