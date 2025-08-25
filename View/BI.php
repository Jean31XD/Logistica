<?php
ini_set('session.use_strict_mode', 1);
session_start();
date_default_timezone_set('America/Santo_Domingo');

if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit();
}

include '../conexionBD/conexion.php';


// Obtenemos los datos para los SELECT la primera vez que carga la página
$transportistas = [];
$tstmt = sqlsrv_query($conn, "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL AND Transportista NOT LIKE '%Contado%' ORDER BY Transportista");
while ($t = sqlsrv_fetch_array($tstmt, SQLSRV_FETCH_ASSOC)) $transportistas[] = $t['Transportista'];

$usuarios = [];
$ustmt = sqlsrv_query($conn, "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario");
while ($u = sqlsrv_fetch_array($ustmt, SQLSRV_FETCH_ASSOC)) $usuarios[] = $u['Usuario'];

$zonas = [];
$zstmt = sqlsrv_query($conn, "SELECT DISTINCT zona FROM custinvoicejour WHERE zona IS NOT NULL ORDER BY zona");
while ($z = sqlsrv_fetch_array($zstmt, SQLSRV_FETCH_ASSOC)) $zonas[] = $z['zona'];

// Valores iniciales de los filtros
$filtroTransportista = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$entregadasCC = isset($_GET['entregadasCC']);
$buscarFactura = $_GET['factura'] ?? '';
$prefijo = $_GET['prefijo'] ?? '';
$zona = $_GET['zona'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard de Facturación </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        :root {
            --bs-body-bg: #1a1c23; --bs-body-color: #e2e8f0; --bs-border-color: #3e4452;
            --bs-primary: #3b82f6; --bs-secondary: #475569; --bs-success: #22c55e;
            --bs-danger: #ef4444; --bs-warning: #f59e0b; --bs-info: #38bdf8;
            --bs-light: #334155; --bs-dark: #1e293b; --font-family-sans-serif: 'Inter', sans-serif;
        }
        body { background-color: var(--bs-body-bg); color: var(--bs-body-color); font-family: var(--font-family-sans-serif); }
        .main-panel, .accordion-body { background-color: var(--bs-dark); border: 1px solid var(--bs-border-color); border-radius: 1rem; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .card-resumen { background-color: var(--bs-dark); border-radius: 0.75rem; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 5px solid var(--bs-primary); transition: background-color 0.2s ease-in-out; }
        .card-resumen:hover { background-color: var(--bs-light); }
        .card-resumen .icon { font-size: 1.75rem; width: 48px; height: 48px; display: grid; place-items: center; border-radius: 50%; background-color: rgba(255,255,255,0.05); }
        .card-resumen h5 { font-size: 0.9rem; font-weight: 500; margin: 0; color: #94a3b8; }
        .card-resumen p { font-size: 1.75rem; font-weight: 700; margin: 0; }
        .accordion-button { background-color: var(--bs-light); color: var(--bs-body-color); }
        .accordion-button:not(.collapsed) { background-color: var(--bs-danger); color: #fff; }
        .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(var(--bs-danger-rgb), 0.5); }
        .form-control, .form-select, .select2-selection { background-color: var(--bs-secondary) !important; border: 1px solid var(--bs-border-color) !important; color: var(--bs-body-color) !important; }
        .select2-dropdown { background-color: #2c3440; border-color: var(--bs-border-color); }
        .select2-results__option { color: var(--bs-body-color); }
        .select2-results__option--highlighted { background-color: var(--bs-danger); }
        .table { min-width: 1000px; }
        .table > :not(caption) > * > * { background-color: transparent; border-bottom-color: var(--bs-border-color); vertical-align: middle; }
        .table thead th { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem; }
        .table tbody td { font-size: 0.9rem; color: #fff; /* LETRAS DE LA TABLA EN BLANCO */ }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
        .factura-id { font-weight: 600; color: var(--bs-info); }
        .badge-status { padding: 0.4em 0.7em; font-size: 0.75rem; font-weight: 600; }
        .badge-completada { background-color: rgba(var(--bs-success-rgb), 0.15); color: var(--bs-success); }
        .badge-re { background-color: rgba(var(--bs-danger-rgb), 0.15); color: var(--bs-danger); }
        .badge-vacio { background-color: rgba(var(--bs-secondary-rgb), 0.15); color: var(--bs-secondary); }
        #loader { display: none; text-align: center; padding: 2rem; }
        @media (max-width: 992px) {
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100% !important; }
            .table tr { background-color: var(--bs-dark); border-radius: 0.75rem; margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--bs-border-color); }
            .table td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border: none; border-bottom: 1px dashed var(--bs-border-color); }
            .table td:last-child { border-bottom: none; }
            .table td::before { content: attr(data-label); font-weight: 600; color: #94a3b8; margin-right: 1rem; }
        }
    </style>
</head>
<body class="p-3 p-md-4">
    <header class="dashboard-header">
        <img src="../IMG/LOGO MC - BLANCO.png" alt="Logo" style="height: 100px;">
        <a href="../Logica/logout.php" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar Sesión</a>
    </header>

    <main>
        <div class="accordion mb-4" id="filtroAccordion">
            <div class="accordion-item" style="border:0; background:transparent;">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="true" aria-controls="collapseFiltros">
                        <i class="fa-solid fa-filter me-2"></i> Opciones de Filtrado
                    </button>
                </h2>
                <div id="collapseFiltros" class="accordion-collapse collapse show" data-bs-parent="#filtroAccordion">
                    <div class="accordion-body p-4">
                        <form id="filtroForm" method="get" autocomplete="off">
                            <div class="row g-3">
                                <div class="col-md-6 col-lg-3"><label class="form-label">Desde</label><input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control"></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Hasta</label><input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control"></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Factura</label><input type="text" name="factura" value="<?= htmlspecialchars($buscarFactura) ?>" class="form-control"></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Estado</label><select name="estado" class="form-select"><option value="">Todos</option><option value="Completada" <?= $estado === 'Completada' ? 'selected' : '' ?>>Completada</option><option value="RE" <?= $estado === 'RE' ? 'selected' : '' ?>>RE</option><option value="vacio" <?= $estado === 'vacio' ? 'selected' : '' ?>>Sin Estado</option></select></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Transportista</label><select name="transportista" id="listaTransportistas" class="form-select"><option value="">Todos</option><?php foreach ($transportistas as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Usuario ALM</label><select name="usuario" id="usuario" class="form-select"><option value="">Todos</option><?php foreach ($usuarios as $u): ?><option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Localización</label><select name="zona" id="zona" class="form-select"><option value="">Todas</option><?php foreach ($zonas as $z): ?><option value="<?= htmlspecialchars($z) ?>" <?= $zona === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6 col-lg-3"><label class="form-label">Prefijo</label><select name="prefijo" class="form-select"><option value="">Todos</option><option value="NC" <?= $prefijo === 'NC' ? 'selected' : '' ?>>Solo NC</option><option value="FT" <?= $prefijo === 'FT' ? 'selected' : '' ?>>Solo FT</option></select></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4" id="resumen-container">
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-info)"><div class="icon" style="color:var(--bs-info)"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><h5>Total Facturas</h5><p id="total-facturas">0</p></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-success)"><div class="icon" style="color:var(--bs-success)"><i class="fa-solid fa-check-double"></i></div><div><h5>Completadas</h5><p id="total-completadas">0</p></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-danger)"><div class="icon" style="color:var(--bs-danger)"><i class="fa-solid fa-triangle-exclamation"></i></div><div><h5>No Completadas</h5><p id="total-no-completadas">0</p></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-warning)"><div class="icon" style="color:var(--bs-warning)"><i class="fa-solid fa-building-columns"></i></div><div><h5>Entregadas a CxC</h5><p id="total-entregadas-cxc">0</p></div></div></div>
        </div>

        <div class="main-panel p-4">
            <div id="loader"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>
            <div class="table-responsive" id="tabla-container">
                <table class="table">
                    <thead><tr><th>Factura</th><th>Fecha</th><th>Estado</th><th>Transportista</th><th>Usuario ALM</th><th>Usuario CC</th><th>Localización</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <nav class="d-flex justify-content-center pt-4" id="paginacion-container"></nav>
        </div>
    </main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // --- INICIALIZACIÓN ---
    initializeSelect2('#listaTransportistas', 'Seleccionar transportista...');
    initializeSelect2('#usuario', 'Seleccionar usuario...');
    initializeSelect2('#zona', 'Seleccionar localización...');

    let currentRequest = null; // Para manejar peticiones múltiples

    // --- FUNCIÓN PRINCIPAL DE AJAX ---
    function aplicarFiltros(page = 1) {
        $('#loader').show();
        $('#tabla-container').hide();

        // Abortar la petición anterior si existe
        if (currentRequest) {
            currentRequest.abort();
        }

        let formData = $('#filtroForm').serialize();
        formData += '&page=' + page;

        currentRequest = $.ajax({
            url: '../Logica/procesar_filtros_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(response) {
                // Actualizar tarjetas de resumen
                $('#total-facturas').text(new Intl.NumberFormat().format(response.resumen.TotalFacturas || 0));
                $('#total-completadas').text(new Intl.NumberFormat().format(response.resumen.Completadas || 0));
                $('#total-no-completadas').text(new Intl.NumberFormat().format(response.resumen.NoCompletadas || 0));
                $('#total-entregadas-cxc').text(new Intl.NumberFormat().format(response.resumen.EntregadasCC || 0));

                // Actualizar tabla y paginación
                $('#tabla-container tbody').html(response.tablaHtml);
                $('#paginacion-container').html(response.paginacionHtml);

                $('#loader').hide();
                $('#tabla-container').show();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if (textStatus !== 'abort') {
                    $('#loader').hide();
                    $('#tabla-container').show();
                    $('#tabla-container tbody').html('<tr><td colspan="8" class="text-center text-danger py-5">Error al cargar los datos. Por favor, intente de nuevo.</td></tr>');
                    console.error("Error en AJAX:", textStatus, errorThrown);
                }
            },
            complete: function() {
                currentRequest = null; // Limpiar la petición actual
            }
        });
    }

    // --- MANEJADORES DE EVENTOS ---
    $('#filtroForm select, #filtroForm input[type="date"]').on('change', function() {
        aplicarFiltros(1);
    });

    let searchTimeout;
    $('#filtroForm input[type="text"]').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            aplicarFiltros(1);
        }, 500);
    });

    // Manejador para la paginación
    $(document).on('click', '#paginacion-container .page-link', function(e) {
        e.preventDefault();
        if (!$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
            const page = $(this).data('page');
            aplicarFiltros(page);
        }
    });

    // --- CARGA INICIAL ---
    aplicarFiltros(1);

});

// --- Funciones auxiliares ---
function initializeSelect2(selector, placeholderText) {
    $(selector).select2({
        placeholder: placeholderText,
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%'
    });
}
</script>
</body>
</html>