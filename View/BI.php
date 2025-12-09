<?php
/**
 * Business Intelligence - MACO Design System
 * Dashboard de análisis y métricas
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    session_start();
}

date_default_timezone_set('America/Santo_Domingo');

if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit();
}

include '../conexionBD/conexion.php';

// Obtenemos los datos para los SELECT
$transportistas = [];
$tstmt = sqlsrv_query($conn, "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL AND Transportista NOT LIKE '%Contado%' ORDER BY Transportista");
while ($t = sqlsrv_fetch_array($tstmt, SQLSRV_FETCH_ASSOC)) $transportistas[] = $t['Transportista'];

$usuarios = [];
$ustmt = sqlsrv_query($conn, "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario");
while ($u = sqlsrv_fetch_array($ustmt, SQLSRV_FETCH_ASSOC)) $usuarios[] = $u['Usuario'];

$zonas = [];
$zstmt = sqlsrv_query($conn, "SELECT DISTINCT zona FROM custinvoicejour WHERE zona IS NOT NULL ORDER BY zona");
while ($z = sqlsrv_fetch_array($zstmt, SQLSRV_FETCH_ASSOC)) $zonas[] = $z['zona'];

// Valores iniciales
$filtroTransportista = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$buscarFactura = $_GET['factura'] ?? '';
$prefijo = $_GET['prefijo'] ?? '';
$zona = $_GET['zona'] ?? '';

$pageTitle = "Business Intelligence | MACO";
$containerClass = "maco-container-fluid";
$additionalCSS = <<<'CSS'
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    :root {
        --bi-gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --bi-gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --bi-gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --bi-gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .bi-header {
        background: var(--primary);
        padding: 3rem 2rem;
        border-radius: var(--radius-xl);
        margin-bottom: 2rem;
        color: white;
        text-align: center;
        box-shadow: var(--shadow-xl);
    }

    .bi-header h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .bi-header p {
        font-size: 1.125rem;
        opacity: 0.95;
    }

    .filters-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
    }

    .filters-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-200);
    }

    .filters-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.25rem;
    }

    .filter-group label {
        display: block;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    .form-control, .form-select {
        padding: 0.75rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius);
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .select2-container--bootstrap-5 .select2-selection {
        border: 2px solid var(--gray-200) !important;
        border-radius: var(--radius) !important;
        padding: 0.5rem !important;
    }

    .select2-container--bootstrap-5 .select2-selection:focus {
        border-color: var(--primary) !important;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        transition: all 0.3s ease;
        border-left: 5px solid;
    }

    .stat-card:nth-child(1) { border-left-color: #3b82f6; }
    .stat-card:nth-child(2) { border-left-color: #10b981; }
    .stat-card:nth-child(3) { border-left-color: #ef4444; }
    .stat-card:nth-child(4) { border-left-color: #f59e0b; }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
    }

    .stat-icon {
        width: 64px;
        height: 64px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }

    .stat-card:nth-child(1) .stat-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .stat-card:nth-child(2) .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-card:nth-child(3) .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .stat-card:nth-child(4) .stat-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .stat-info h5 {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin: 0 0 0.5rem 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-info p {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-primary);
        margin: 0;
    }

    .table-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-200);
    }

    .table-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .bi-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .bi-table thead th {
        background: var(--primary);
        color: white;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bi-table thead th:first-child {
        border-top-left-radius: var(--radius);
    }

    .bi-table thead th:last-child {
        border-top-right-radius: var(--radius);
    }

    .bi-table tbody td {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
        font-size: 0.95rem;
    }

    .bi-table tbody tr:hover {
        background: var(--gray-50);
    }

    .factura-link {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
    }

    .factura-link:hover {
        text-decoration: underline;
    }

    .badge-status {
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-completada {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .badge-re {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .badge-vacio {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
    }

    #loader {
        display: none;
        text-align: center;
        padding: 3rem;
    }

    .spinner {
        width: 48px;
        height: 48px;
        border: 4px solid var(--gray-200);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .pagination-custom {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .page-btn {
        padding: 0.5rem 1rem;
        border: 2px solid var(--gray-200);
        background: white;
        color: var(--text-primary);
        border-radius: var(--radius);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .page-btn:hover:not(.active) {
        background: var(--gray-100);
        border-color: var(--gray-300);
    }

    .page-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    @media (max-width: 768px) {
        .bi-header h1 {
            font-size: 2rem;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
CSS;
include __DIR__ . '/templates/header.php';
?>

<!-- Header BI -->
<div class="bi-header animate__animated animate__fadeIn">
    <i class="fas fa-chart-line" style="font-size: 3rem; margin-bottom: 1rem;"></i>
    <h1>Business Intelligence</h1>
    <p>Dashboard de análisis y métricas del sistema de facturación</p>
</div>

<!-- Filtros -->
<div class="filters-card animate__animated animate__fadeInUp">
    <div class="filters-header">
        <i class="fas fa-filter" style="font-size: 1.5rem; color: var(--primary);"></i>
        <h2>Filtros de Búsqueda</h2>
    </div>

    <form id="filtroForm" method="get" autocomplete="off">
        <div class="filter-grid">
            <div class="filter-group">
                <label><i class="fas fa-calendar me-2"></i>Desde</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
            </div>

            <div class="filter-group">
                <label><i class="fas fa-calendar me-2"></i>Hasta</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
            </div>

            <div class="filter-group">
                <label><i class="fas fa-file-invoice me-2"></i>Factura</label>
                <input type="text" name="factura" value="<?= htmlspecialchars($buscarFactura) ?>" class="form-control" placeholder="Buscar factura...">
            </div>

            <div class="filter-group">
                <label><i class="fas fa-check-circle me-2"></i>Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="Completada" <?= $estado === 'Completada' ? 'selected' : '' ?>>Completada</option>
                    <option value="RE" <?= $estado === 'RE' ? 'selected' : '' ?>>RE</option>
                    <option value="vacio" <?= $estado === 'vacio' ? 'selected' : '' ?>>Sin Estado</option>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-truck me-2"></i>Transportista</label>
                <select name="transportista" id="listaTransportistas" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($transportistas as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-user me-2"></i>Usuario ALM</label>
                <select name="usuario" id="usuario" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-map-marker-alt me-2"></i>Localización</label>
                <select name="zona" id="zona" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($zonas as $z): ?>
                    <option value="<?= htmlspecialchars($z) ?>" <?= $zona === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-tag me-2"></i>Prefijo</label>
                <select name="prefijo" class="form-select">
                    <option value="">Todos</option>
                    <option value="NC" <?= $prefijo === 'NC' ? 'selected' : '' ?>>Solo NC</option>
                    <option value="FT" <?= $prefijo === 'FT' ? 'selected' : '' ?>>Solo FT</option>
                </select>
            </div>
        </div>
    </form>
</div>

<!-- Estadísticas -->
<div class="stats-grid animate__animated animate__fadeInUp">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-info">
            <h5>Total Facturas</h5>
            <p id="total-facturas">0</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-info">
            <h5>Completadas</h5>
            <p id="total-completadas">0</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-triangle-exclamation"></i>
        </div>
        <div class="stat-info">
            <h5>No Completadas</h5>
            <p id="total-no-completadas">0</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-building-columns"></i>
        </div>
        <div class="stat-info">
            <h5>Entregadas CxC</h5>
            <p id="total-entregadas-cxc">0</p>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="table-card animate__animated animate__fadeInUp">
    <div class="table-header">
        <h2><i class="fas fa-table me-2"></i>Listado de Facturas</h2>
    </div>

    <div id="loader">
        <div class="spinner"></div>
        <p style="margin-top: 1rem; color: var(--text-secondary);">Cargando datos...</p>
    </div>

    <div class="table-responsive" id="tabla-container">
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Transportista</th>
                    <th>Usuario ALM</th>
                    <th>Usuario CC</th>
                    <th>Localización</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="pagination-custom" id="paginacion-container"></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializar Select2
    $('#listaTransportistas, #usuario, #zona').select2({
        placeholder: 'Seleccionar...',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%'
    });

    let currentRequest = null;

    function aplicarFiltros(page = 1) {
        $('#loader').show();
        $('#tabla-container').hide();

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
                $('#total-facturas').text(new Intl.NumberFormat().format(response.resumen.TotalFacturas || 0));
                $('#total-completadas').text(new Intl.NumberFormat().format(response.resumen.Completadas || 0));
                $('#total-no-completadas').text(new Intl.NumberFormat().format(response.resumen.NoCompletadas || 0));
                $('#total-entregadas-cxc').text(new Intl.NumberFormat().format(response.resumen.EntregadasCC || 0));

                $('#tabla-container tbody').html(response.tablaHtml);
                $('#paginacion-container').html(response.paginacionHtml);

                $('#loader').hide();
                $('#tabla-container').show();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if (textStatus !== 'abort') {
                    $('#loader').hide();
                    $('#tabla-container').show();
                    $('#tabla-container tbody').html('<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:3rem;">Error al cargar los datos. Por favor, intente de nuevo.</td></tr>');
                    console.error("Error en AJAX:", textStatus, errorThrown);
                }
            },
            complete: function() {
                currentRequest = null;
            }
        });
    }

    // Event handlers
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

    $(document).on('click', '.page-btn', function(e) {
        e.preventDefault();
        if (!$(this).hasClass('active')) {
            const page = $(this).data('page');
            aplicarFiltros(page);
        }
    });

    // Carga inicial
    aplicarFiltros(1);
});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
