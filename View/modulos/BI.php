<?php
/**
 * Reporte de Facturas Recibidas - Business Intelligence
 * Dashboard de análisis y métricas
 */

// Incluir configuración centralizada de sesión y conexión a BD
require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion(); // Acceso general para usuarios autenticados

require_once __DIR__ . '/../../conexionBD/conexion.php';

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

// Cargar almacenes
$almacenes = [];
$astmt = sqlsrv_query($conn, "SELECT DISTINCT inventlocationid FROM Facturas_lineas WHERE inventlocationid IS NOT NULL AND inventlocationid <> '' ORDER BY inventlocationid");
if ($astmt) {
    while ($a = sqlsrv_fetch_array($astmt, SQLSRV_FETCH_ASSOC)) $almacenes[] = $a['inventlocationid'];
}

// Valores iniciales
$filtroTransportista = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$buscarFactura = $_GET['factura'] ?? '';
$prefijo = $_GET['prefijo'] ?? '';
$zona = $_GET['zona'] ?? '';
$almacen = $_GET['almacen'] ?? '';

$pageTitle = "Reporte de Facturas Recibidas | MACO";
$containerClass = "maco-container-fluid";
$additionalCSS = <<<'CSS'
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --bi-primary: #E63946;
        --bi-secondary: #1D3557;
        --bi-accent: #457B9D;
        --bi-success: #22C55E;
        --bi-warning: #F59E0B;
        --bi-danger: #EF4444;
        --bi-bg: #F1F5F9;
        --bi-card: #FFFFFF;
        --bi-border: #E2E8F0;
        --bi-text: #1E293B;
        --bi-muted: #64748B;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bi-bg);
    }

    /* Header Moderno */
    .bi-header {
        background: linear-gradient(135deg, var(--bi-secondary) 0%, var(--bi-accent) 100%);
        padding: 1.5rem 2rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bi-header-info h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .bi-header-info p {
        margin: 0.25rem 0 0;
        opacity: 0.85;
        font-size: 0.9rem;
    }

    /* KPIs en el Header */
    .bi-kpi-row {
        display: flex;
        gap: 1rem;
    }

    .bi-kpi-box {
        text-align: center;
        background: rgba(255,255,255,0.15);
        padding: 0.75rem 1.25rem;
        border-radius: 8px;
        backdrop-filter: blur(10px);
    }

    .bi-kpi-box .number {
        font-size: 1.5rem;
        font-weight: 800;
        display: block;
    }

    .bi-kpi-box .label {
        font-size: 0.65rem;
        text-transform: uppercase;
        opacity: 0.8;
        letter-spacing: 0.5px;
    }

    /* Filtros Horizontales */
    .bi-filters {
        background: var(--bi-card);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }

    .bi-filters-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }

    .bi-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 130px;
        flex: 1;
    }

    .bi-filter-group.wide {
        min-width: 180px;
    }

    .bi-filter-group label {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--bi-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bi-filter-group input,
    .bi-filter-group select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--bi-border);
        border-radius: 6px;
        font-size: 0.85rem;
        background: #fff;
    }

    .bi-filter-group input:focus,
    .bi-filter-group select:focus {
        outline: none;
        border-color: var(--bi-primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    /* Select2 compacto */
    .select2-container .select2-selection--single {
        height: 36px !important;
        border: 1px solid var(--bi-border) !important;
        border-radius: 6px !important;
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 34px !important;
        font-size: 0.85rem !important;
        padding-left: 10px !important;
    }

    .select2-container .select2-selection--single .select2-selection__arrow {
        height: 34px !important;
    }

    /* Tabla Moderna */
    .bi-table-container {
        background: var(--bi-card);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .bi-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--bi-border);
    }

    .bi-table-header h2 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--bi-text);
    }

    .bi-table {
        width: 100%;
        border-collapse: collapse;
    }

    .bi-table thead {
        background: var(--bi-secondary);
        color: #fff;
    }

    .bi-table thead th {
        padding: 0.875rem 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bi-table tbody td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--bi-border);
        font-size: 0.85rem;
        color: var(--bi-text);
    }

    .bi-table tbody tr:hover {
        background: #F8FAFC;
    }

    /* Badges de Estado */
    .badge-status {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-completada {
        background: rgba(34, 197, 94, 0.15);
        color: #16A34A;
    }

    .badge-re {
        background: rgba(239, 68, 68, 0.15);
        color: #DC2626;
    }

    .badge-vacio {
        background: rgba(107, 114, 128, 0.15);
        color: #4B5563;
    }

    /* Loader */
    #loader {
        display: none;
        text-align: center;
        padding: 3rem;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid var(--bi-border);
        border-top-color: var(--bi-primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Paginación */
    .pagination-custom {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem;
        flex-wrap: wrap;
    }

    .page-btn {
        padding: 0.5rem 0.875rem;
        border: 1px solid var(--bi-border);
        background: white;
        color: var(--bi-text);
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .page-btn:hover:not(.active) {
        background: #F1F5F9;
        border-color: var(--bi-accent);
    }

    .page-btn.active {
        background: var(--bi-primary);
        color: white;
        border-color: var(--bi-primary);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .bi-header {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }

        .bi-kpi-row {
            flex-wrap: wrap;
            justify-content: center;
        }

        .bi-filters-row {
            flex-direction: column;
        }

        .bi-filter-group {
            width: 100%;
        }
    }
</style>
CSS;
include __DIR__ . '/../templates/header.php';
?>

<!-- Header con KPIs -->
<div class="bi-header">
    <div class="bi-header-info">
        <h1><i class="fas fa-chart-bar"></i> Reporte de Facturas Recibidas</h1>
        <p>Business Intelligence - Análisis avanzado de facturas y operaciones</p>
    </div>
    <div class="bi-kpi-row">
        <div class="bi-kpi-box">
            <span class="number" id="total-facturas">--</span>
            <span class="label">Total</span>
        </div>
        <div class="bi-kpi-box">
            <span class="number" id="total-completadas">--</span>
            <span class="label">Completadas</span>
        </div>
        <div class="bi-kpi-box">
            <span class="number" id="total-no-completadas">--</span>
            <span class="label">Pendientes</span>
        </div>
        <div class="bi-kpi-box">
            <span class="number" id="total-entregadas-cxc">--</span>
            <span class="label">CxC</span>
        </div>
    </div>
</div>

<!-- Filtros Horizontales -->
<form id="filtroForm" method="get" autocomplete="off">
<div class="bi-filters">
    <div class="bi-filters-row">
        <div class="bi-filter-group">
            <label>Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
        </div>
        
        <div class="bi-filter-group">
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        
        <div class="bi-filter-group">
            <label>Factura</label>
            <input type="text" name="factura" value="<?= htmlspecialchars($buscarFactura) ?>" placeholder="Buscar...">
        </div>
        
        <div class="bi-filter-group">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                <option value="Completada" <?= $estado === 'Completada' ? 'selected' : '' ?>>Completada</option>
                <option value="RE" <?= $estado === 'RE' ? 'selected' : '' ?>>RE</option>
                <option value="vacio" <?= $estado === 'vacio' ? 'selected' : '' ?>>Sin Estado</option>
            </select>
        </div>
        
        <div class="bi-filter-group wide">
            <label>Transportista</label>
            <select name="transportista" id="listaTransportistas">
                <option value="">Todos</option>
                <?php foreach ($transportistas as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="bi-filter-group">
            <label>Usuario ALM</label>
            <select name="usuario" id="usuario">
                <option value="">Todos</option>
                <?php foreach ($usuarios as $u): ?>
                <option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="bi-filter-group">
            <label>Zona</label>
            <select name="zona" id="zona">
                <option value="">Todas</option>
                <?php foreach ($zonas as $z): ?>
                <option value="<?= htmlspecialchars($z) ?>" <?= $zona === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="bi-filter-group">
            <label>Almacén</label>
            <select name="almacen" id="almacen">
                <option value="">Todos</option>
                <?php foreach ($almacenes as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $almacen === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="bi-filter-group">
            <label>Prefijo</label>
            <select name="prefijo">
                <option value="">Todos</option>
                <option value="NC" <?= $prefijo === 'NC' ? 'selected' : '' ?>>Solo NC</option>
                <option value="FT" <?= $prefijo === 'FT' ? 'selected' : '' ?>>Solo FT</option>
            </select>
        </div>
    </div>
</div>
</form>

<!-- Tabla de Resultados -->
<div class="bi-table-container">
    <div class="bi-table-header">
        <h2><i class="fas fa-table"></i> Listado de Facturas</h2>
    </div>

    <div id="loader">
        <div class="spinner"></div>
        <p style="margin-top: 1rem; color: var(--bi-muted);">Cargando datos...</p>
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
                    <th>Almacén</th>
                    <th>Zona</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="pagination-custom" id="paginacion-container"></div>
</div>

<!-- Modal de Detalles de Factura -->
<div id="facturaModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> Detalles de Factura: <span id="modal-factura-id"></span></h3>
            <button class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modal-loader" style="text-align: center; padding: 2rem;">
                <div class="spinner"></div>
                <p>Cargando detalles...</p>
            </div>
            <div id="modal-content" style="display: none;">
                <!-- Info General -->
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Estado:</span> <span id="info-estado"></span></div>
                    <div class="info-item"><span class="info-label">Fecha:</span> <span id="info-fecha"></span></div>
                    <div class="info-item"><span class="info-label">Transportista:</span> <span id="info-transportista"></span></div>
                    <div class="info-item"><span class="info-label">Almacén:</span> <span id="info-almacen"></span></div>
                    <div class="info-item"><span class="info-label">Usuario ALM:</span> <span id="info-usuario-alm"></span></div>
                    <div class="info-item"><span class="info-label">Usuario CC:</span> <span id="info-usuario-cc"></span></div>
                </div>
                
                <!-- Resumen -->
                <div class="totales-grid">
                    <div class="total-box"><span class="total-num" id="total-items">0</span><span class="total-label">Artículos</span></div>
                    <div class="total-box"><span class="total-num" id="total-monto">$0.00</span><span class="total-label">Monto Total</span></div>
                </div>
                
                <!-- Tabla de Líneas -->
                <h4 style="margin: 1rem 0;"><i class="fas fa-list"></i> Líneas de la Factura</h4>
                <div class="table-responsive">
                    <table class="bi-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Precio</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="lineas-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1rem;
    }
    
    .modal-container {
        background: #fff;
        border-radius: 12px;
        max-width: 900px;
        width: 100%;
        max-height: 85vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    }
    
    .modal-header {
        background: var(--bi-secondary);
        color: #fff;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
    }
    
    .modal-close {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0.8;
    }
    
    .modal-close:hover {
        opacity: 1;
    }
    
    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
        background: #F8FAFC;
        padding: 1rem;
        border-radius: 8px;
    }
    
    .info-item {
        font-size: 0.9rem;
    }
    
    .info-label {
        color: var(--bi-muted);
        font-weight: 600;
    }
    
    .totales-grid {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .total-box {
        flex: 1;
        background: linear-gradient(135deg, var(--bi-accent) 0%, var(--bi-secondary) 100%);
        color: #fff;
        padding: 1rem;
        border-radius: 8px;
        text-align: center;
    }
    
    .total-num {
        display: block;
        font-size: 1.5rem;
        font-weight: 800;
    }
    
    .total-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.85;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializar Select2
    $('#listaTransportistas, #usuario, #zona, #almacen').select2({
        placeholder: 'Seleccionar...',
        allowClear: true,
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
            url: '../../Logica/procesar_filtros_ajax.php',
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
                    $('#tabla-container tbody').html('<tr><td colspan="7" style="text-align:center;color:var(--bi-danger);padding:3rem;">Error al cargar los datos. Intente de nuevo.</td></tr>');
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

    // Click en factura para ver detalles
    $(document).on('click', '.factura-link', function(e) {
        e.preventDefault();
        const factura = $(this).text();
        abrirDetalleFactura(factura);
    });

    // Carga inicial
    aplicarFiltros(1);
});

// Funciones del Modal
function abrirDetalleFactura(factura) {
    $('#facturaModal').show();
    $('#modal-factura-id').text(factura);
    $('#modal-loader').show();
    $('#modal-content').hide();
    
    $.ajax({
        url: '../../Logica/api_factura_detalle.php',
        type: 'GET',
        data: { factura: factura },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                alert('Error: ' + response.error);
                cerrarModal();
                return;
            }
            
            // Llenar info general
            const f = response.factura;
            $('#info-estado').html('<span class="badge-status ' + getBadgeClass(f.Estado) + '">' + (f.Estado || 'Sin Estado') + '</span>');
            $('#info-fecha').text(f.Fecha || '—');
            $('#info-transportista').text(f.Transportista || '—');
            $('#info-almacen').text(f.Localizacion || '—');
            $('#info-usuario-alm').text(f.Usuario_ALM || '—');
            $('#info-usuario-cc').text(f.Usuario_CC || '—');
            
            // Totales
            $('#total-items').text(response.totales.items);
            $('#total-monto').text('$' + response.totales.monto);
            
            // Líneas
            let lineasHtml = '';
            if (response.lineas && response.lineas.length > 0) {
                response.lineas.forEach(function(linea) {
                    lineasHtml += '<tr>';
                    lineasHtml += '<td>' + (linea.Codigo || '—') + '</td>';
                    lineasHtml += '<td>' + (linea.Descripcion || '—') + '</td>';
                    lineasHtml += '<td>' + (linea.Cantidad || 0) + '</td>';
                    lineasHtml += '<td>' + (linea.Unidad || '—') + '</td>';
                    lineasHtml += '<td>$' + parseFloat(linea.Precio || 0).toFixed(2) + '</td>';
                    lineasHtml += '<td>$' + parseFloat(linea.Total || 0).toFixed(2) + '</td>';
                    lineasHtml += '</tr>';
                });
            } else {
                lineasHtml = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#64748B;">No hay líneas de detalle</td></tr>';
            }
            $('#lineas-body').html(lineasHtml);
            
            $('#modal-loader').hide();
            $('#modal-content').show();
        },
        error: function() {
            alert('Error al cargar los detalles de la factura');
            cerrarModal();
        }
    });
}

function getBadgeClass(estado) {
    if (estado === 'Completada') return 'badge-completada';
    if (estado === 'RE') return 'badge-re';
    return 'badge-vacio';
}

function cerrarModal() {
    $('#facturaModal').hide();
}

// Cerrar modal con Escape
$(document).on('keyup', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});

// Cerrar modal haciendo clic fuera
$('#facturaModal').on('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
