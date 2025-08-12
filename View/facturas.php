<?php
// Seguridad de sesión
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
date_default_timezone_set('America/Santo_Domingo');

// Cierre por inactividad (200 segundos)
$inactividadLimite = 200;

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempoInactivo = time() - $_SESSION['ultimo_acceso'];
    if ($tiempoInactivo > $inactividadLimite) {
        session_unset();
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
}
$_SESSION['ultimo_acceso'] = time();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../View/index.php");
    exit();
}

session_regenerate_id(true);
include '../conexionBD/conexion.php';

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 2, 3, 5])) {
    header("Location: ../index.php");
    exit();
}

// Cargar datos para los filtros
$transportistas = [];
$t_result = sqlsrv_query($conn, "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL ORDER BY Transportista");
while ($row = sqlsrv_fetch_array($t_result, SQLSRV_FETCH_ASSOC)) $transportistas[] = $row['Transportista'];

$usuarios = [];
if (in_array($_SESSION['pantalla'], [0, 2, 5])) {
    $u_result = sqlsrv_query($conn, "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario");
    while ($row = sqlsrv_fetch_array($u_result, SQLSRV_FETCH_ASSOC)) $usuarios[] = $row['Usuario'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepción de Facturas ✨</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        :root {
            --bs-body-bg: #1a1c23; --bs-body-color: #e2e8f0; --bs-border-color: #3e4452;
            --bs-primary: #22c55e; --bs-secondary: #475569; --bs-danger: #ef4444;
            --bs-light: #334155; --bs-dark: #1e293b; --font-family-sans-serif: 'Inter', sans-serif;
        }
        body { background-color: var(--bs-body-bg); color: var(--bs-body-color); }
        .main-panel { background-color: var(--bs-dark); border-radius: 1rem; padding: 1.5rem; border: 1px solid var(--bs-border-color); }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .accordion-button { background-color: var(--bs-light); color: var(--bs-body-color); }
        .accordion-button:not(.collapsed) { background-color: var(--bs-primary); color: #fff; }
        .form-control, .form-select, .select2-selection { background-color: var(--bs-secondary) !important; border: 1px solid var(--bs-border-color) !important; color: var(--bs-body-color) !important; }
        .select2-dropdown { background-color: #2c3440; border-color: var(--bs-border-color); }
        .select2-results__option--highlighted { background-color: var(--bs-primary); }
        .table > :not(caption) > * > * { background-color: transparent; border-bottom-color: var(--bs-border-color); vertical-align: middle; }
        .table thead th { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem; }
        .table tbody td { font-size: 0.9rem; }
        .table tbody tr.table-success, .table tbody tr.table-success:hover { background-color: rgba(var(--bs-primary-rgb), 0.1) !important; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
        .badge-status { padding: 0.4em 0.7em; font-size: 0.75rem; font-weight: 600; }
        .badge-completada { background-color: rgba(var(--bs-primary-rgb), 0.15); color: var(--bs-primary); }
        .badge-re { background-color: rgba(var(--bs-danger-rgb), 0.15); color: var(--bs-danger); }
        #loader { display: none; text-align: center; padding: 3rem; }
        @media (max-width: 992px) {
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100% !important; }
            .table tr { background-color: var(--bs-dark); border-radius: 0.75rem; margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--bs-border-color); }
            .table td { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border: none; border-bottom: 1px dashed var(--bs-border-color); }
            .table td:last-child { border-bottom: none; }
            .table td::before { content: attr(data-label); font-weight: 600; color: #94a3b8; margin-right: 1rem; }
            .table .estado-validar { background-color: var(--bs-light) !important; }
        }
    </style>
</head>
<body class="p-3 p-md-4">
    <header class="dashboard-header">
        <img src="../IMG/LOGO MC - BLANCO.png" alt="Logo" style="height: 40px;">
        <a href="../Logica/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a>
    </header>

    <main>
        <div class="main-panel mb-4" id="action-bar">
            <h5 class="mb-3">Recibir Nueva Factura</h5>
            <div class="input-group">
                <input type="text" id="inputFactura" class="form-control form-control-lg" placeholder="Escanear o digitar N° de Factura (11 dígitos)" maxlength="11" />
                <button class="btn btn-primary px-4" onclick="validarFactura()" title="Recibir factura"><i class="fa-solid fa-box-arrow-in-down me-2"></i>Recibir</button>
            </div>
        </div>

        <div class="accordion mb-4" id="filtroAccordion">
            <div class="accordion-item" style="border:0; background:transparent;">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros">
                        <i class="fa-solid fa-filter me-2"></i> Filtros de Búsqueda
                    </button>
                </h2>
                <div id="collapseFiltros" class="accordion-collapse collapse">
                    <div class="main-panel p-4">
                        <div class="row g-3">
                            <div class="col-md-6 col-lg-4"><label class="form-label">Transportista</label><select id="listaTransportistas" class="form-select"><option value="">Todos</option><?php foreach ($transportistas as $t): ?><option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Desde</label><input type="date" id="fechaInicio" class="form-control" /></div>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Hasta</label><input type="date" id="fechaFin" class="form-control" /></div>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Fecha Recibido</label><input type="date" id="fechaRecibido" class="form-control" /></div>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Fecha Recepción</label><input type="date" id="fechaRecepcion" class="form-control" /></div>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Estatus</label><select id="filtroEstatus" class="form-select"><option value="">Todos</option><option value="Completada">Completada</option><option value="RE">RE</option></select></div>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Buscar Factura</label><input type="text" id="buscarFactura" class="form-control" placeholder="Ej: 12345678901" maxlength="11" /></div>
                            <?php if (in_array($_SESSION['pantalla'], [0, 2, 5])): ?>
                            <div class="col-md-6 col-lg-4"><label class="form-label">Usuario</label><select id="filtroUsuario" class="form-select"><option value="">Todos</option><?php foreach ($usuarios as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?></select></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-panel">
            <div id="loader"><div class="spinner-border text-primary" role="status"></div></div>
            <div class="table-responsive" id="tabla-container">
                <table class="table">
                    <thead><tr><th>Factura</th><th>Fecha</th><th>Transportista</th><th>Estado</th><th>Fecha Scanner</th><th>Acción</th></tr></thead>
                    <tbody id="facturas-tbody"></tbody>
                </table>
            </div>
            <nav class="d-flex justify-content-center pt-4" id="paginacion-container"></nav>
        </div>
    </main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let paginaActual = 1;
let currentRequest = null;
let inactivityTimer;

function setupSelect2(selector, placeholder) {
    $(selector).select2({ placeholder, allowClear: true, theme: 'bootstrap-5', width: '100%' });
}

function cargarFacturas(pagina = 1) {
    paginaActual = pagina;
    $('#loader').show();
    $('#tabla-container').hide();

    if (currentRequest) currentRequest.abort();

    const filters = {
        transportista: $('#listaTransportistas').val(),
        desde: $('#fechaInicio').val(),
        hasta: $('#fechaFin').val(),
        fechaRecibido: $('#fechaRecibido').val(),
        fechaRecepcion: $('#fechaRecepcion').val(),
        estatus: $('#filtroEstatus').val(),
        usuario: $('#filtroUsuario').length ? $('#filtroUsuario').val() : '',
        buscarFactura: $('#buscarFactura').val().trim(),
        pagina: pagina
    };

    currentRequest = $.get('../Logica/get_facturas.php', filters)
        .done(function(html) {
            $('#facturas-tbody').html(html);
        })
        .fail(function(jqXHR, textStatus) {
            if (textStatus !== 'abort') {
                $('#facturas-tbody').html('<tr><td colspan="6" class="text-center text-danger py-5">Error al cargar los datos.</td></tr>');
            }
        })
        .always(function() {
            $('#loader').hide();
            $('#tabla-container').show();
            currentRequest = null;
        });
}

function validarFactura() {
    const factura = $('#inputFactura').val().trim();
    const transportista = $('#listaTransportistas').val();
    if (factura.length !== 11 || !transportista) {
        alert("Debe seleccionar un transportista e ingresar una factura de 11 dígitos.");
        return;
    }
    $.post('../Logica/Validar_factura.php', { factura, transportista })
        .done(function(respuesta) {
            if (respuesta.encontrada) {
                const fila = $(`#fila_${factura}`);
                if (fila.length) {
                    fila.addClass('table-success').find('.estado-validar').val('Completada');
                }
                $('#inputFactura').val('').focus();
                cargarFacturas(paginaActual); 
            } else {
                alert("Factura no encontrada para el transportista seleccionado.");
            }
        })
        .fail(() => alert("Error al validar la factura."));
}

function actualizarEstado(factura, nuevoEstado) {
    $.post('../Logica/actualizar_estado.php', { factura, nuevoEstado })
        .done(respuesta => { if (!respuesta.success) alert("No se pudo actualizar el estado."); })
        .fail(() => alert("Error al conectar con el servidor."));
}

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        alert("Su sesión ha expirado por inactividad.");
        window.location.href = "../Logica/logout.php";
    }, 200 * 1000); // 200 segundos
}

$(document).ready(function () {
    setupSelect2('#listaTransportistas', 'Buscar transportista');
    setupSelect2('#filtroUsuario', 'Buscar usuario');

    const filterElements = '#listaTransportistas, #fechaInicio, #fechaFin, #fechaRecibido, #fechaRecepcion, #filtroEstatus, #filtroUsuario';
    $(document).on('change', filterElements, () => cargarFacturas(1));

    let searchTimeout;
    $('#buscarFactura').on('keyup', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => cargarFacturas(1), 500);
    });

    $('#inputFactura').on('keypress', function(e) { if (e.which === 13) validarFactura(); });

    $(document).on('click', '#paginacion-container .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && !$(this).parent().hasClass('disabled')) {
            cargarFacturas(page);
        }
    });
    
    $(document).on('change', '.estado-validar', function() {
        actualizarEstado($(this).data('factura'), $(this).val());
    });

    $(window).on('mousemove keydown click scroll', resetInactivityTimer);

    cargarFacturas();
    resetInactivityTimer();
});
</script>
</body>
</html>