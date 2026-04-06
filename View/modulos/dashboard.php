<?php
require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion();

// =========================================================================
// VERIFICAR PERMISO DEL MÓDULO DASHBOARD
// =========================================================================
require_once __DIR__ . '/../../conexionBD/conexion.php';

if (!$conn) {
    header("Location: ../pantallas/Portal.php?error=db");
    exit();
}

date_default_timezone_set('America/Santo_Domingo');

$usuario = $_SESSION['usuario'];

// Verificar permiso usando solo usuario_modulos
$tienePermiso = tieneModulo('dashboard_general', $conn);
$USER_WAREHOUSE = '';
$USER_TYPE = $tienePermiso ? 'admin' : 'user';

if (!$tienePermiso) {
    // No tiene permiso, redirigir a Portal
    header("Location: ../pantallas/Portal.php?error=sin_permiso");
    exit();
}

// Obtener almacén asignado al usuario (con manejo de error si columna no existe)
$USER_WAREHOUSE = '';
$sqlAlmacen = "SELECT dashboard_almacen FROM usuarios WHERE usuario = ?";
$stmtAlmacen = @sqlsrv_query($conn, $sqlAlmacen, [$usuario]);

if ($stmtAlmacen !== false) {
    $rowAlmacen = sqlsrv_fetch_array($stmtAlmacen, SQLSRV_FETCH_ASSOC);
    if ($rowAlmacen && isset($rowAlmacen['dashboard_almacen'])) {
        $USER_WAREHOUSE = $rowAlmacen['dashboard_almacen'] ?? '';
    }
}
// Si la columna no existe, USER_WAREHOUSE queda vacío (acceso a todos)

// Determinar tipo de usuario (admin = ve todos, warehouse = ve solo su almacén)
$USER_TYPE = empty($USER_WAREHOUSE) ? 'admin' : 'warehouse';

$homeUrl = '../pantallas/Portal.php';

// =========================================================================
// LOGOUT HANDLER
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    header("Location: ../../index.php");
    exit();
}

// =========================================================================
// DASHBOARD - ACCESO CONCEDIDO
// =========================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Facturación | MACO</title>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>/View/assets/css/dashboard.css">

</head>
<body>
    <div class="dashboard-layout no-sidebar">
        <!-- Topbar con Logo -->
        <div class="topbar">
            <div class="topbar-left">
                <div class="logo-small">
                    <img src="../../IMG/LOGO MC - COLOR.png" alt="MACO">
                </div>
                <div class="topbar-title">
                    <h1>Dashboard de Facturación</h1>
                </div>
            </div>
            <div class="topbar-right">
                <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="topbar-btn">
                    <i class="fas fa-home"></i>
                    <span>Portal</span>
                </a>
                <a href="dashboard.php?action=logout" class="topbar-btn logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Salir</span>
                </a>
            </div>
        </div>
        
        <!-- Barra de Filtros -->
        <div class="filter-bar">
            <div class="filter-bar-left">
                <div class="filter-inline">
                    <label for="fecha_inicio"><i class="fas fa-calendar"></i> Desde:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio">
                </div>
                <div class="filter-inline">
                    <label for="fecha_fin"><i class="fas fa-calendar"></i> Hasta:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin">
                </div>
                <?php if ($USER_TYPE === 'admin'): ?>
                <div class="filter-inline">
                    <label for="filtro_almacen"><i class="fas fa-warehouse"></i> Almacén:</label>
                    <select id="filtro_almacen" name="filtro_almacen">
                        <option value="">Todos</option>
                    </select>
                </div>
                <?php else: ?>
                <div class="filter-inline">
                    <label><i class="fas fa-warehouse"></i> Almacén:</label>
                    <span class="filter-value"><?php echo htmlspecialchars($USER_WAREHOUSE); ?></span>
                    <select id="filtro_almacen" name="filtro_almacen" style="display:none;"></select>
                </div>
                <?php endif; ?>
            </div>
            <div class="filter-bar-right">
                <div class="user-badge">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                    <strong><?php echo $USER_TYPE === 'admin' ? 'Admin' : htmlspecialchars($USER_WAREHOUSE); ?></strong>
                </div>
            </div>
        </div>
        
        <!-- Sidebar oculto para mantener compatibilidad JS -->
        <div style="display:none;">
            <ul class="sidebar-nav">
                <li class="nav-item"><a href="#" class="active" data-view="overview"></a></li>
                <li class="nav-item"><a href="#" data-view="trends"></a></li>
                <li class="nav-item"><a href="#" data-view="performance"></a></li>
                <li class="nav-item"><a href="#" data-view="financial"></a></li>
            </ul>
        </div>
        
        <main class="main-content">
            <header class="section-header">
                <h1 id="main-title">Resumen de Facturas</h1>
                <div id="loader" class="loader"><span>Actualizando datos...</span></div>
            </header>
            
            <!-- Tabs de Navegación -->
            <nav class="header-tabs">
                <a href="#" class="tab-item active" data-view="overview">
                    <i class="fas fa-chart-pie"></i>
                    <span>Resumen General</span>
                </a>
                <a href="#" class="tab-item" data-view="trends">
                    <i class="fas fa-chart-line"></i>
                    <span>Tendencias</span>
                </a>
                <a href="#" class="tab-item" data-view="performance">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Rendimiento</span>
                </a>
                <a href="#" class="tab-item" data-view="financial">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Financiero</span>
                </a>
                <a href="#" class="tab-item" data-view="trucks">
                    <i class="fas fa-truck"></i>
                    <span>Camiones</span>
                </a>
            </nav>
            
            <div class="content-area">

            <div id="view-overview" class="view-container active">
                <!-- Alerta de Entregas sin QR -->
                <div id="sinqr-alert" class="sinqr-alert" role="alert">
                    <div class="sinqr-alert-inner">
                        <div class="sinqr-alert-left">
                            <div class="sinqr-icon" aria-hidden="true">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3><i class="fas fa-qrcode me-1" aria-hidden="true"></i> Entregas sin Escaneo QR</h3>
                                <p>Ver tabla de detalles más abajo</p>
                            </div>
                        </div>
                        <div class="sinqr-count">
                            <div id="sinqr-overview-count" class="sinqr-count-number">--</div>
                            <div class="sinqr-count-label">Pendientes</div>
                        </div>
                    </div>
                </div>

                <div class="grid-layout">
                    <div class="kpi-card" id="kpi-total-emitidas">
                        <h2>Total Emitidas</h2>
                        <p id="total-emitidas">--</p>
                    </div>
                    <div class="kpi-card" id="kpi-sin-estado">
                        <h2>Sin Estado Asignado</h2>
                        <p id="sin-estado">--</p>
                    </div>
                </div>
                <div class="card" style="margin-top:0.75rem;">
                    <h2>Distribución por Estado</h2>
                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                    <table class="status-table">
                        <thead><tr><th>Estado</th><th>Total de Facturas</th></tr></thead>
                        <tbody id="statusTableBody"></tbody>
                        <tfoot><tr><td>TOTAL GENERAL</td><td id="statusTableTotal">--</td></tr></tfoot>
                    </table>
                </div>

                <!-- Sección: Entregas sin QR -->
                <div class="card" style="margin-top:0.75rem; border-left: 4px solid #F59E0B;">
                    <div class="sinqr-alert-inner" style="margin-bottom:0.75rem;">
                        <div>
                            <h2 style="color:#D97706; border:none; padding:0; margin:0;">
                                <i class="fas fa-qrcode" aria-hidden="true"></i> Entregas sin Escaneo QR
                            </h2>
                            <p class="maco-text-muted" style="font-size:0.8rem; margin:0.25rem 0 0;">Facturas entregadas sin confirmación de código QR</p>
                        </div>
                        <div class="kpi-card" style="border-left-color:#F59E0B; cursor:default; padding:0.75rem 1rem; margin:0; min-width:100px; text-align:center;">
                            <h2 style="font-size:0.62rem; margin:0;">Total sin QR</h2>
                            <p id="sinqr-total" style="font-size:1.75rem; color:#F59E0B;">--</p>
                        </div>
                    </div>

                    <!-- Tabla de entregas sin QR -->
                    <div class="facturas-table-wrapper">
                        <div class="table-header" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                            <h3><i class="fas fa-list" aria-hidden="true"></i> Detalle de Entregas sin QR</h3>
                        </div>
                        <div style="max-height:300px; overflow-y:auto;">
                            <table class="delivery-details-table" id="sinqr-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> Factura</th>
                                        <th><i class="fas fa-barcode"></i> CL</th>
                                        <th><i class="fas fa-user"></i> Cliente</th>
                                        <th><i class="fas fa-truck"></i> Transportista</th>
                                        <th><i class="fas fa-id-card"></i> Placa</th>
                                        <th><i class="fas fa-info-circle"></i> Estado</th>
                                        <th><i class="fas fa-calendar-alt"></i> F. Despacho</th>
                                        <th><i class="fas fa-calendar-check"></i> F. Entrega</th>
                                        <th><i class="fas fa-user-check"></i> Entregado Por</th>
                                    </tr>
                                </thead>
                                <tbody id="sinqr-table-body">
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 2rem; color: #999;">
                                            <i class="fas fa-spinner fa-spin"></i> Cargando datos...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-controls" style="background:#FEF3C7; border-top: 1px solid #F59E0B;">
                            <span style="color:#92400E; font-size:0.75rem; font-weight:500;">
                                <i class="fas fa-info-circle" aria-hidden="true"></i> Mostrando <span id="sinqr-showing">0</span> registros
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="view-trends" class="view-container">
                <div class="card">
                    <h2>Tendencia de Facturas y Montos por Día</h2>
                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                        Visualiza la cantidad de facturas y el monto total facturado por día
                    </p>
                    <div class="chart-container"><canvas id="trendsChart"></canvas></div>
                </div>
            </div>

            <div id="view-details" class="view-container">
                <div class="card">
                    <div class="sinqr-alert-inner" style="margin-bottom:0.75rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border);">
                        <h2 id="details-title" style="border:none; padding:0; margin:0; font-size:1rem;">Detalles</h2>
                        <button id="back-to-overview">&larr; Volver al Resumen</button>
                    </div>
                    <p id="details-period" style="color:var(--text-muted); font-size:0.8rem; margin-bottom:0.75rem;">Mostrando resultados para el período seleccionado.</p>
                    <div style="overflow-x:auto;">
                        <table class="status-table">
                            <thead>
                                <tr>
                                    <th>No. Factura</th><th>F. Factura</th><th>F. Registro</th><th>Cliente</th><th>Monto</th><th>Registrado Por</th><th>Camión</th>
                                    <th>F. Despacho</th><th>Despachado Por</th><th>F. Entregado</th><th>Entregado Por</th>
                                    <th>Estado</th><th>F. Reversada</th><th>Reversado Por</th><th>F. NC</th>
                                    <th>NC Por</th><th>Motivo NC</th><th>Camión 2</th>
                                </tr>
                            </thead>
                            <tbody id="detailsTableBody"></tbody>
                        </table>
                    </div>
                    <div id="pagination-controls" class="pagination-controls" style="margin-top:0.75rem;">
                        <select id="details-limit">
                            <option value="10">10 / pág.</option>
                            <option value="25">25 / pág.</option>
                            <option value="50" selected>50 / pág.</option>
                            <option value="100">100 / pág.</option>
                        </select>
                        <div class="pagination-buttons">
                            <span id="page-info" style="color:var(--text-muted); font-size:0.75rem; margin-right:0.5rem;">Página 1 de 1 (Total: 0)</span>
                            <button id="prev-page" disabled class="pagination-btn">&larr; Anterior</button>
                            <button id="next-page" disabled class="pagination-btn">Siguiente &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="view-performance" class="view-container">
                <div class="grid-layout" style="grid-template-columns: repeat(3,1fr); margin-bottom:0.75rem;">
                    <div class="kpi-card" style="border-left-color:#3182ce; cursor:default;">
                        <h2>Registro → Despacho</h2>
                        <p id="perf-kpi-time-to-dispatch">-- horas</p>
                    </div>
                    <div class="kpi-card" style="border-left-color:#38a169; cursor:default;">
                        <h2>Despacho → Entrega</h2>
                        <p id="perf-kpi-dispatch-to-deliver">-- horas</p>
                    </div>
                    <div class="kpi-card" style="border-left-color:#dd6b20; cursor:default;">
                        <h2>Ciclo Total (Registro → Entrega)</h2>
                        <p id="perf-kpi-total-cycle">-- horas</p>
                    </div>
                </div>
                <div class="grid-layout">
                    <div class="card">
                        <h2><i class="fas fa-undo-alt" aria-hidden="true"></i> Motivos de NC</h2>
                        <div class="chart-container"><canvas id="ncReasonsChart"></canvas></div>
                    </div>
                    <div class="card">
                        <h2><i class="fas fa-truck" aria-hidden="true"></i> Top 5 Camiones</h2>
                        <div class="chart-container"><canvas id="truckPerformanceChart"></canvas></div>
                    </div>
                </div>
            </div>

            
            <div id="view-financial" class="view-container">
                <div class="grid-layout" style="grid-template-columns: repeat(3,1fr); margin-bottom:0.75rem;">
                    <div class="kpi-card" style="border-left-color:#3182ce; cursor:default;">
                        <h2>Monto Total Emitido</h2>
                        <p id="financial-kpi-total-amount">--</p>
                    </div>
                    <div class="kpi-card" style="border-left-color:#d69e2e; cursor:default;">
                        <h2>Monto Sin Estado</h2>
                        <p id="financial-kpi-sin-estado-amount">--</p>
                    </div>
                    <div class="kpi-card" style="border-left-color:#e53e3e; cursor:default;">
                        <h2>Monto Total NC</h2>
                        <p id="financial-kpi-nc-amount">--</p>
                    </div>
                </div>
                <div class="grid-layout">
                    <div class="card">
                        <h2><i class="fas fa-user" aria-hidden="true"></i> Top 10 Clientes por Monto</h2>
                        <div class="chart-container" style="height:320px;"><canvas id="topClientsChart"></canvas></div>
                    </div>
                    <div class="card">
                        <h2><i class="fas fa-warehouse" aria-hidden="true"></i> Top 10 Almacenes por Monto</h2>
                        <div class="chart-container" style="height:320px;"><canvas id="topWarehousesChart"></canvas></div>
                    </div>
                </div>
            </div>
            
            <!-- ===== VISTA: CAMIONES ===== -->
            <div id="view-trucks" class="view-container" role="region" aria-label="Detalle de entregas por camión">

                <!-- KPIs Camiones -->
                <div class="trucks-kpi-row" role="list">
                    <div class="kpi-card trucks-kpi" style="border-left-color:#1D3557;" role="listitem">
                        <h2>Total Camiones</h2>
                        <p id="trucks-kpi-total">--</p>
                    </div>
                    <div class="kpi-card trucks-kpi" style="border-left-color:#3182ce;" role="listitem">
                        <h2>Total Asignadas</h2>
                        <p id="trucks-kpi-asignadas">--</p>
                    </div>
                    <div class="kpi-card trucks-kpi" style="border-left-color:#38a169;" role="listitem">
                        <h2>Total Entregadas</h2>
                        <p id="trucks-kpi-entregadas">--</p>
                    </div>
                    <div class="kpi-card trucks-kpi" style="border-left-color:#dd6b20;" role="listitem">
                        <h2>Efectividad</h2>
                        <p id="trucks-kpi-efectividad">--</p>
                    </div>
                </div>

                <!-- Gráficas propias de la vista Camiones -->
                <div class="trucks-charts-grid">
                    <div class="card">
                        <h2><i class="fas fa-chart-bar" aria-hidden="true"></i> Despachadas vs Entregadas por Camión</h2>
                        <p class="trucks-chart-subtitle">Comparativo de facturas despachadas (pendientes) y entregadas por unidad</p>
                        <div class="chart-container trucks-bar-chart"><canvas id="deliveryComparisonChart" aria-label="Gráfico comparativo despachadas vs entregadas por camión" role="img"></canvas></div>
                    </div>
                    <div class="card trucks-donut-card">
                        <h2><i class="fas fa-chart-pie" aria-hidden="true"></i> Efectividad Global</h2>
                        <p class="trucks-chart-subtitle">Proporción de facturas entregadas vs pendientes</p>
                        <div class="chart-container trucks-donut-container"><canvas id="trucksDonutChart" aria-label="Gráfico de efectividad global de entregas" role="img"></canvas></div>
                        <div id="trucks-donut-legend" class="trucks-donut-legend" aria-live="polite"></div>
                    </div>
                </div>

                <!-- Acordeón de transportistas -->
                <div class="card">
                    <div class="trucks-section-header">
                        <div>
                            <h2 class="trucks-section-title">
                                <i class="fas fa-truck" aria-hidden="true"></i>
                                Detalle de Entregas por Camión
                            </h2>
                            <p class="trucks-section-desc">Haz clic en cualquier camión para ver sus facturas detalladas.</p>
                        </div>
                        <span id="trucksInfo" class="trucks-count-badge" aria-live="polite">0 camiones</span>
                    </div>

                    <div id="transportistasContainer" role="list" aria-label="Lista de camiones">
                        <p class="trucks-loading-msg"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Cargando datos de camiones...</p>
                    </div>

                    <!-- Paginación de camiones -->
                    <div id="trucksPagination" class="trucks-pagination" role="navigation" aria-label="Paginación de camiones">
                        <div class="trucks-pag-left">
                            <label for="trucksPerPage" class="trucks-pag-label">Mostrar:</label>
                            <select id="trucksPerPage" aria-label="Camiones por página">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        <div id="trucksPageInfo" class="trucks-page-info" aria-live="polite">Página 1 de 1</div>
                        <div class="trucks-pag-btns">
                            <button id="trucksPrevPage" disabled class="trucks-pag-btn" aria-label="Página anterior">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i> Anterior
                            </button>
                            <button id="trucksNextPage" class="trucks-pag-btn" aria-label="Página siguiente">
                                Siguiente <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Profesional -->
            <footer class="dashboard-footer">
                <div class="footer-left">
                    <i class="fas fa-chart-line"></i>
                    <span>© <?php echo date('Y'); ?> <strong>MACO Logística</strong> - Dashboard de Facturación</span>
                </div>
                <div class="footer-right">
                    <span><i class="fas fa-clock"></i> Última actualización: <span id="last-update-time"><?php echo date('H:i'); ?></span></span>
                    <span><i class="fas fa-code-branch"></i> v3.0</span>
                </div>
            </footer>
        </main>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- MODIFICACIÓN: Pasar variables de sesión de PHP a JavaScript ---
        const USER_TYPE = <?php echo json_encode($USER_TYPE); ?>;
        const USER_WAREHOUSE = <?php echo json_encode($USER_WAREHOUSE); ?>;
        
        // Ruta base para APIs (usando helper PHP para consistencia)
        const API_BASE = '<?php echo getApiPath(); ?>/api_get_data.php';

        let statusChart, trendsChart, ncReasonsChart, truckPerformanceChart, topClientsChart, topWarehousesChart, deliveryComparisonChart, trucksDonutChart;
        let currentView = 'overview';
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        // El filtro de almacén puede ser 'null' si no es admin
        const almacenFilterInput = document.getElementById('filtro_almacen');
        const loaderEl = document.getElementById('loader');
        const mainTitle = document.getElementById('main-title');
        let detailsCurrentState = ''; 
        let detailsCurrentPage = 1;
        let detailsLimit = parseInt(document.getElementById('details-limit').value);
        let detailsTotalPages = 1;
        
        const initializeCharts = () => {
            // Tu función initializeCharts (sin cambios)
            const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };
            statusChart = new Chart(document.getElementById('statusChart').getContext('2d'), { type: 'bar', data: { labels: [], datasets: [{ data: [], backgroundColor: 'rgba(229, 62, 62, 0.7)' }] }, options: chartOptions });

            // Gráfico de tendencias con dos líneas (cantidad y monto)
            const trendsOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 1) {
                                        // Formato de moneda para el monto
                                        label += new Intl.NumberFormat('es-DO', {
                                            style: 'currency',
                                            currency: 'DOP'
                                        }).format(context.parsed.y);
                                    } else {
                                        // Número normal para cantidad
                                        label += context.parsed.y + ' facturas';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Facturas',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return Math.round(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Monto Total (DOP)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('es-DO', {
                                    style: 'currency',
                                    currency: 'DOP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            };

            trendsChart = new Chart(document.getElementById('trendsChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Cantidad de Facturas',
                            data: [],
                            borderColor: 'rgba(229, 62, 62, 1)',
                            backgroundColor: 'rgba(229, 62, 62, 0.1)',
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2
                        },
                        {
                            label: 'Monto Total',
                            data: [],
                            borderColor: 'rgba(49, 130, 206, 1)',
                            backgroundColor: 'rgba(49, 130, 206, 0.1)',
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y1',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2
                        }
                    ]
                },
                options: trendsOptions
            });
            
            const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } };
            ncReasonsChart = new Chart(document.getElementById('ncReasonsChart').getContext('2d'), {
                type: 'doughnut', data: { labels: [], datasets: [{ data: [], backgroundColor: ['#e53e3e', '#dd6b20', '#d69e2e', '#38a169', '#3182ce', '#805ad5'] }] }, options: doughnutOptions
            });
            
            const barOptions = { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } };
            truckPerformanceChart = new Chart(document.getElementById('truckPerformanceChart').getContext('2d'), {
                type: 'bar', data: { labels: [], datasets: [{ label: 'Total Entregas', data: [], backgroundColor: 'rgba(54, 162, 235, 0.7)' }] }, options: barOptions
            });

            const barOptionsHorizontal = { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } };
            topClientsChart = new Chart(document.getElementById('topClientsChart').getContext('2d'), {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'Monto Total', data: [], backgroundColor: 'rgba(229, 62, 62, 0.7)' }] },
                options: barOptionsHorizontal
            });
            topWarehousesChart = new Chart(document.getElementById('topWarehousesChart').getContext('2d'), {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'Monto Total', data: [], backgroundColor: 'rgba(49, 130, 206, 0.7)' }] },
                options: barOptionsHorizontal
            });

            // Gráfico de comparación Entregadas VS Despachadas
            const comparisonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { size: 11, weight: 'bold' },
                            padding: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y + ' facturas';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return Math.round(value);
                            }
                        }
                    }
                }
            };

            deliveryComparisonChart = new Chart(document.getElementById('deliveryComparisonChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Pendientes',
                            data: [],
                            backgroundColor: 'rgba(230, 57, 70, 0.7)',
                            borderColor: 'rgba(230, 57, 70, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Entregadas',
                            data: [],
                            backgroundColor: 'rgba(56, 161, 105, 0.7)',
                            borderColor: 'rgba(56, 161, 105, 1)',
                            borderWidth: 2
                        }
                    ]
                },
                options: comparisonOptions
            });

            // Donut — Efectividad Global (vista Camiones)
            trucksDonutChart = new Chart(document.getElementById('trucksDonutChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Entregadas', 'Pendientes'],
                    datasets: [{
                        data: [0, 0],
                        backgroundColor: ['rgba(56,161,105,0.85)', 'rgba(230,57,70,0.75)'],
                        borderColor:     ['rgba(56,161,105,1)',    'rgba(230,57,70,1)'],
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return ` ${context.label}: ${context.parsed} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        };
        
        const populateAlmacenFilter = async () => {
            // Esta función solo se llamará si es admin
            try {
                const response = await fetch(API_BASE + '?view=almacenes');
                if (response.status === 401) { window.location.href = 'dashboard.php'; return; }
                if (!response.ok) throw new Error('No se pudo cargar la lista de almacenes');
                const almacenes = await response.json();
                
                // Chequeo por si la API devuelve error por sesión
                if (almacenes.error) throw new Error(almacenes.error);

                almacenes.forEach(almacen => {
                    const option = document.createElement('option');
                    option.value = almacen.inventlocationid;
                    option.textContent = almacen.inventlocationid;
                    almacenFilterInput.appendChild(option);
                });
            } catch (error) { console.error("Error cargando almacenes:", error); }
        };

        const fetchData = async (inicio, fin, almacen, view) => {
            loaderEl.classList.add('loading');
            try {
                // La vista 'trucks' usa el endpoint de performance
                const apiView = view === 'trucks' ? 'performance' : view;
                const url = `${API_BASE}?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=${apiView}`;
                const response = await fetch(url);

                if (response.status === 401) { // 401 Unauthorized (sesión de dashboard expirada)
                    window.location.href = 'dashboard.php'; // Redirigir a la misma pág (mostrará login)
                    return;
                }
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Respuesta del servidor:', errorText.substring(0, 500));
                    throw new Error(`Error HTTP: ${response.status}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('Error al parsear JSON. Respuesta del servidor:', responseText.substring(0, 500));
                    throw new Error('El servidor no devolvió JSON válido. Revisa la consola para más detalles.');
                }

                if (data.error) throw new Error(data.error);

                if (view === 'trucks') {
                    await updateTrucksView(data);
                } else if (view === 'performance') {
                    updatePerformanceView(data);
                } else if (view === 'financial') {
                    updateFinancialView(data);
                } else if (view !== 'details') {
                    updateDashboard(data, view);
                }
            } catch (error) {
                console.error(`Error al cargar datos para la vista ${view}:`, error);
                if (view !== 'details') alert('Error al cargar datos del dashboard: ' + error.message);
            } finally {
                loaderEl.classList.remove('loading');
            }
        };

        // updateFinancialView y updatePerformanceView
        const updateFinancialView = (data) => {
            const currencyFormatter = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' });

            document.getElementById('financial-kpi-total-amount').textContent = currencyFormatter.format(data.kpis.totalAmount || 0);
            document.getElementById('financial-kpi-sin-estado-amount').textContent = currencyFormatter.format(data.kpis.sinEstadoAmount || 0);
            document.getElementById('financial-kpi-nc-amount').textContent = currencyFormatter.format(data.kpis.ncAmount || 0);

            if (topClientsChart) {
                const clientLabels = data.topClients.map(c => {
                    const name = c.Cliente || 'N/A';
                    return name.length > 30 ? name.substring(0, 27) + '...' : name;
                });
                topClientsChart.data.labels = clientLabels;
                topClientsChart.data.datasets[0].data = data.topClients.map(c => c.TotalAmount);
                topClientsChart.update();
            }

            if (topWarehousesChart) {
                topWarehousesChart.data.labels = data.topWarehouses.map(w => w.Almacen);
                topWarehousesChart.data.datasets[0].data = data.topWarehouses.map(w => w.TotalAmount);
                topWarehousesChart.update();
            }
        };
        
        const updatePerformanceView = (data) => {
            const formatter = new Intl.NumberFormat('es-DO', { maximumFractionDigits: 1 });
            document.getElementById('perf-kpi-time-to-dispatch').textContent = `${formatter.format(data.kpis.AvgTimeToDispatch || 0)} horas`;
            document.getElementById('perf-kpi-dispatch-to-deliver').textContent = `${formatter.format(data.kpis.AvgDispatchToDeliver || 0)} horas`;
            document.getElementById('perf-kpi-total-cycle').textContent = `${formatter.format(data.kpis.AvgTotalCycle || 0)} horas`;

            if (ncReasonsChart) {
                ncReasonsChart.data.labels = data.ncReasons.map(d => d.Motivo);
                ncReasonsChart.data.datasets[0].data = data.ncReasons.map(d => d.Total);
                ncReasonsChart.update();
            }

            if (truckPerformanceChart) {
                truckPerformanceChart.data.labels = data.truckPerformance.map(d => d.Camion);
                truckPerformanceChart.data.datasets[0].data = data.truckPerformance.map(d => d.TotalEntregas);
                truckPerformanceChart.options.plugins.tooltip = {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.x !== null) {
                                label += context.parsed.x;
                                const truckData = data.truckPerformance[context.dataIndex];
                                if (truckData) {
                                    label += ` (Avg: ${formatter.format(truckData.AvgDeliveryTime)} hrs)`;
                                }
                            }
                            return label;
                        }
                    }
                };
                truckPerformanceChart.update();
            }

        };

        // Vista de Camiones — usa datos del endpoint performance
        const updateTrucksView = async (data) => {
            await renderDeliveryDetails(data.truckPerformance || []);
        };

        const renderDeliveryDetails = async (truckData) => {
            const transportistasContainer = document.getElementById('transportistasContainer');

            if (!truckData || truckData.length === 0) {
                transportistasContainer.innerHTML = '<p class="no-data-message">No hay datos de entregas disponibles para el período seleccionado.</p>';
                if (deliveryComparisonChart) {
                    deliveryComparisonChart.data.labels = [];
                    deliveryComparisonChart.data.datasets[0].data = [];
                    deliveryComparisonChart.data.datasets[1].data = [];
                    deliveryComparisonChart.update();
                }
                return;
            }

            try {
                // Obtener detalles completos de cada camión
                const inicio = fechaInicioInput.value;
                const fin = fechaFinInput.value;
                const almacen = almacenFilterInput ? almacenFilterInput.value : '';

                // Obtener entregas y despachadas
                const [deliveriesResponse, dispatchedResponse] = await Promise.all([
                    fetch(`${API_BASE}?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=delivery_details`),
                    fetch(`${API_BASE}?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=dispatched_by_truck`)
                ]);

                const deliveries = await deliveriesResponse.json();
                const dispatchedData = await dispatchedResponse.json();

                console.log('Dispatched Data:', dispatchedData);

                if (deliveries.error) {
                    transportistasContainer.innerHTML = `<p class="no-data-message">${deliveries.error}</p>`;
                    return;
                }

                if (dispatchedData.error) {
                    transportistasContainer.innerHTML = `<p class="no-data-message">${dispatchedData.error}</p>`;
                    return;
                }

                // Agrupar entregas por camión
                const deliveriesByTruck = {};
                deliveries.forEach(delivery => {
                    const truck = delivery.Camion || 'Sin Asignar';
                    if (!deliveriesByTruck[truck]) {
                        deliveriesByTruck[truck] = [];
                    }
                    deliveriesByTruck[truck].push(delivery);
                });

                // Crear mapas de despachadas, entregadas y asignadas por camión (chasis)
                const dispatchedByTruck = {}; // Despachadas pero NO entregadas (pendientes)
                const entregadasByTruck = {}; // Ya entregadas
                const asignadasByTruck = {}; // Total asignadas
                const transportistaByTruck = {}; // Transportista dueño del camión
                const placaByTruck = {}; // Placa del camión
                const modeloByTruck = {}; // Modelo del camión
                const fichaByTruck = {}; // Ficha del camión
                if (!dispatchedData.error && Array.isArray(dispatchedData)) {
                    dispatchedData.forEach(item => {
                        const truck = item.Camion || 'Sin Asignar'; // Ahora es el chasis
                        asignadasByTruck[truck] = parseInt(item.TotalAsignadas) || 0;
                        dispatchedByTruck[truck] = parseInt(item.TotalDespachadas) || 0;
                        entregadasByTruck[truck] = parseInt(item.TotalEntregadas) || 0;
                        transportistaByTruck[truck] = item.Transportista || 'Sin asignar';
                        placaByTruck[truck] = item.Placa || 'N/A';
                        modeloByTruck[truck] = item.Modelo || 'N/A';
                        fichaByTruck[truck] = item.Ficha || 'N/A';
                    });
                }

                // Actualizar gráfico de comparación
                const truckLabels = [];
                const entregadasData = [];
                const despachadasData = [];

                // Usar los datos del endpoint dispatched_by_truck
                // Despachadas (rojas) = Facturas pendientes de entrega
                // Entregadas (verdes) = Facturas ya entregadas (en custinvoicejour)
                Object.keys(asignadasByTruck).sort().forEach(truck => {
                    // Usar nombre de transportista en lugar de placa/chasis
                    const nombreTransportista = transportistaByTruck[truck] || truck;
                    truckLabels.push(nombreTransportista);
                    despachadasData.push(dispatchedByTruck[truck] || 0);
                    entregadasData.push(entregadasByTruck[truck] || 0);
                });

                if (deliveryComparisonChart) {
                    deliveryComparisonChart.data.labels = truckLabels;
                    deliveryComparisonChart.data.datasets[0].data = despachadasData;
                    deliveryComparisonChart.data.datasets[1].data = entregadasData;
                    deliveryComparisonChart.update();
                }

                // ── KPIs de la vista Camiones ──
                const _totalAsig = Object.values(asignadasByTruck).reduce((a, b) => a + b, 0);
                const _totalEntr = Object.values(entregadasByTruck).reduce((a, b) => a + b, 0);
                const _pendientes = Math.max(0, _totalAsig - _totalEntr);
                const _efectividad = _totalAsig > 0 ? ((_totalEntr / _totalAsig) * 100).toFixed(1) : 0;

                const _elTotal  = document.getElementById('trucks-kpi-total');
                const _elAsig   = document.getElementById('trucks-kpi-asignadas');
                const _elEntr   = document.getElementById('trucks-kpi-entregadas');
                const _elEfec   = document.getElementById('trucks-kpi-efectividad');

                // Solo mostrar camiones que tengan facturas asignadas
                const trucksWithInvoices = Object.keys(asignadasByTruck).filter(truck => asignadasByTruck[truck] > 0);

                if (_elTotal)  _elTotal.textContent  = trucksWithInvoices.length;
                if (_elAsig)   _elAsig.textContent   = _totalAsig.toLocaleString('es-DO');
                if (_elEntr)   _elEntr.textContent   = _totalEntr.toLocaleString('es-DO');
                if (_elEfec)   _elEfec.textContent   = `${_efectividad}%`;

                // ── Donut chart ──
                if (trucksDonutChart) {
                    trucksDonutChart.data.datasets[0].data = [_totalEntr, _pendientes];
                    trucksDonutChart.update();
                }
                const _legend = document.getElementById('trucks-donut-legend');
                if (_legend) {
                    _legend.innerHTML = `
                        <span class="donut-leg-item donut-leg-green">
                            <span class="donut-leg-dot"></span>Entregadas: <strong>${_totalEntr.toLocaleString('es-DO')}</strong>
                        </span>
                        <span class="donut-leg-item donut-leg-red">
                            <span class="donut-leg-dot"></span>Pendientes: <strong>${_pendientes.toLocaleString('es-DO')}</strong>
                        </span>
                        <span class="donut-leg-pct">${_efectividad}% efectividad</span>`;
                }

                // Construir cards expandibles para cada camión
                let transportistasHTML = '';

                console.log('Camiones con facturas:', trucksWithInvoices);

                trucksWithInvoices.sort((a, b) => {
                    // Ordenar por transportista primero, luego por placa
                    const nameA = transportistaByTruck[a] || '';
                    const nameB = transportistaByTruck[b] || '';
                    if (nameA !== nameB) return nameA.localeCompare(nameB);
                    return (placaByTruck[a] || '').localeCompare(placaByTruck[b] || '');
                }).forEach((truck, index) => {
                    const deliveriesForTruck = deliveriesByTruck[truck] || [];
                    const totalAsignadas = asignadasByTruck[truck];
                    const totalDespachadas = dispatchedByTruck[truck] || 0;
                    const totalEntregadas = entregadasByTruck[truck] || 0;
                    const transportista = transportistaByTruck[truck] || 'Sin asignar';
                    const placa = placaByTruck[truck] || 'N/A';
                    const modelo = modeloByTruck[truck] || 'N/A';
                    const ficha = fichaByTruck[truck] || 'N/A';
                    const chasis = truck; // El truck ahora es el chasis

                    console.log(`Tarjeta ${index}: Chasis=${chasis}, Placa=${placa}, Transportista=${transportista}, Total=${totalAsignadas}`);

                    let totalTime = 0;
                    deliveriesForTruck.forEach(delivery => {
                        totalTime += delivery.DeliveryTimeHours || 0;
                    });
                    const avgTime = deliveriesForTruck.length > 0 ? (totalTime / deliveriesForTruck.length).toFixed(1) : 0;

                    // Calcular porcentaje de entrega (entregadas vs total asignadas)
                    const porcentajeEntrega = totalAsignadas > 0 ? ((totalEntregadas / totalAsignadas) * 100).toFixed(1) : 0;

                    transportistasHTML += `
                        <div class="transportista-card" id="truck-${index}" data-camion="${truck}">
                            <div class="transportista-header" onclick="toggleTransportista('truck-${index}')">
                                <div class="transportista-nombre">
                                    <i class="fas fa-truck"></i>
                                    <div>
                                        <div style="font-size: 1.1rem; font-weight: 700;">${placa}</div>
                                        <div style="font-size: 0.75rem; opacity: 0.85; margin-top: 0.25rem;">
                                            <i class="fas fa-user" style="margin-right: 0.25rem;"></i>${transportista}
                                        </div>
                                        <div style="font-size: 0.7rem; opacity: 0.75; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.25rem;">
                                            <div><i class="fas fa-barcode" style="margin-right: 0.25rem;"></i> Chasis: ${chasis}</div>
                                            <div><i class="fas fa-cog" style="margin-right: 0.25rem;"></i> Modelo: ${modelo}</div>
                                            <div><i class="fas fa-hashtag" style="margin-right: 0.25rem;"></i> Ficha: ${ficha}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="transportista-stats">
                                    <div class="stat-item">
                                        <div class="stat-label">Total</div>
                                        <div class="stat-value" style="color: rgba(255,255,255,0.9);">${totalAsignadas}</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">Entregadas</div>
                                        <div class="stat-value" style="color: #48BB78;">${totalEntregadas}</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">Despachadas</div>
                                        <div class="stat-value" style="color: #ED8936;">${totalDespachadas}</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">% Entrega</div>
                                        <div class="stat-value" style="color: ${porcentajeEntrega >= 90 ? '#48BB78' : porcentajeEntrega >= 70 ? '#ECC94B' : '#FC8181'};">${porcentajeEntrega}%</div>
                                    </div>
                                    <i class="fas fa-chevron-down expand-icon"></i>
                                </div>
                            </div>
                            <div class="transportista-details">
                                <div class="transportista-details-content">
                                    <!-- Resumen de Métricas -->
                                    <div class="metricas-resumen">
                                        <div class="metrica-card">
                                            <div class="metrica-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <div class="metrica-label">Tiempo Promedio</div>
                                                <div class="metrica-value"><span class="avg-time">--</span> hrs</div>
                                            </div>
                                        </div>
                                        <div class="metrica-card">
                                            <div class="metrica-icon" style="background: linear-gradient(135deg, #48BB78 0%, #38A169 100%);">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <div class="metrica-label">Entregadas</div>
                                                <div class="metrica-value"><span class="entregadas-count">--</span></div>
                                            </div>
                                        </div>
                                        <div class="metrica-card">
                                            <div class="metrica-icon" style="background: linear-gradient(135deg, #ED8936 0%, #DD6B20 100%);">
                                                <i class="fas fa-hourglass-half"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <div class="metrica-label">Pendientes</div>
                                                <div class="metrica-value"><span class="pending-count">--</span></div>
                                            </div>
                                        </div>
                                        <div class="metrica-card">
                                            <div class="metrica-icon" style="background: linear-gradient(135deg, #4299E1 0%, #3182CE 100%);">
                                                <i class="fas fa-box"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <div class="metrica-label">Total Facturas</div>
                                                <div class="metrica-value"><span class="total-facturas">--</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de Facturas -->
                                    <div class="facturas-table-wrapper">
                                        <div class="table-header">
                                            <h3><i class="fas fa-list"></i> Detalle de Facturas</h3>
                                            <div class="table-actions">
                                                <button class="filter-btn" data-filter="all">
                                                    <i class="fas fa-th"></i> Todas
                                                </button>
                                                <button class="filter-btn" data-filter="entregado">
                                                    <i class="fas fa-check"></i> Entregadas
                                                </button>
                                                <button class="filter-btn active" data-filter="despachado">
                                                    <i class="fas fa-shipping-fast"></i> Pendientes
                                                </button>
                                            </div>
                                        </div>
                                        <table class="delivery-details-table">
                                            <thead>
                                                <tr>
                                                    <th><i class="fas fa-hashtag"></i> Factura</th>
                                                    <th><i class="fas fa-user"></i> Cliente</th>
                                                    <th><i class="fas fa-truck"></i> Transportista</th>
                                                    <th><i class="fas fa-info-circle"></i> Estado</th>
                                                    <th><i class="fas fa-calendar-alt"></i> F. Despacho</th>
                                                    <th><i class="fas fa-user-tie"></i> Despachado Por</th>
                                                    <th><i class="fas fa-calendar-check"></i> F. Entrega</th>
                                                    <th><i class="fas fa-stopwatch"></i> Tiempo</th>
                                                </tr>
                                            </thead>
                                            <tbody class="facturas-tbody">
                                                <tr>
                                                    <td colspan="8" style="text-align: center; padding: 2rem;">
                                                        <div class="loading-spinner">
                                                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                                                            <p>Cargando facturas...</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <!-- Controles de Paginación -->
                                        <div class="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #F7FAFC; border-top: 1px solid #E2E8F0;">
                                            <div class="pagination-info" style="color: #718096; font-size: 0.875rem;">
                                                Mostrando <span class="showing-start">0</span>-<span class="showing-end">0</span> de <span class="total-items">0</span> facturas
                                            </div>
                                            <div class="pagination-buttons" style="display: flex; gap: 0.5rem;">
                                                <button class="pagination-btn prev-page" disabled style="padding: 0.5rem 1rem; border: 1px solid #E2E8F0; background: white; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                                    <i class="fas fa-chevron-left"></i> Anterior
                                                </button>
                                                <span class="page-indicator" style="padding: 0.5rem 1rem; background: #E63946; color: white; border-radius: 6px; font-weight: 600;">1</span>
                                                <button class="pagination-btn next-page" style="padding: 0.5rem 1rem; border: 1px solid #E2E8F0; background: white; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                                    Siguiente <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });

                // Guardar todos los camiones para paginación
                window.allTrucksHTML = [];
                trucksWithInvoices.forEach((truck, index) => {
                    window.allTrucksHTML.push({
                        truck: truck,
                        html: document.createElement('div')
                    });
                });

                // Configuración de paginación
                window.trucksCurrentPage = 1;
                window.trucksPerPageValue = parseInt(document.getElementById('trucksPerPage')?.value || 10);
                window.totalTrucks = trucksWithInvoices.length;
                
                // Función para renderizar página actual
                window.renderTrucksPage = function() {
                    const start = (window.trucksCurrentPage - 1) * window.trucksPerPageValue;
                    const end = start + window.trucksPerPageValue;
                    const totalPages = Math.ceil(window.totalTrucks / window.trucksPerPageValue);
                    
                    // Mostrar/ocultar cards según página
                    const allCards = transportistasContainer.querySelectorAll('.transportista-card');
                    allCards.forEach((card, idx) => {
                        if (idx >= start && idx < end) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Actualizar info
                    const trucksInfo = document.getElementById('trucksInfo');
                    if (trucksInfo) trucksInfo.textContent = `${window.totalTrucks} camiones`;
                    
                    const pageInfo = document.getElementById('trucksPageInfo');
                    if (pageInfo) pageInfo.textContent = `Página ${window.trucksCurrentPage} de ${totalPages}`;
                    
                    // Actualizar botones
                    const prevBtn = document.getElementById('trucksPrevPage');
                    const nextBtn = document.getElementById('trucksNextPage');
                    if (prevBtn) prevBtn.disabled = window.trucksCurrentPage <= 1;
                    if (nextBtn) nextBtn.disabled = window.trucksCurrentPage >= totalPages;
                };

                transportistasContainer.innerHTML = transportistasHTML;
                
                // Renderizar primera página
                window.renderTrucksPage();

            } catch (error) {
                console.error('Error cargando detalles de entregas:', error);
                transportistasContainer.innerHTML = '<p class="no-data-message">Error al cargar los detalles de entregas.</p>';
                if (deliveryComparisonChart) {
                    deliveryComparisonChart.data.labels = [];
                    deliveryComparisonChart.data.datasets[0].data = [];
                    deliveryComparisonChart.data.datasets[1].data = [];
                    deliveryComparisonChart.update();
                }
            }
        };

        // Función para expandir/colapsar detalles de transportista
        window.toggleTransportista = async function(truckId) {
            const card = document.getElementById(truckId);
            if (!card) return;

            const details = card.querySelector('.transportista-details');
            const icon = card.querySelector('.expand-icon');

            if (details.classList.contains('expanded')) {
                details.classList.remove('expanded');
                icon.classList.remove('expanded');
            } else {
                details.classList.add('expanded');
                icon.classList.add('expanded');

                const tbody = card.querySelector('.facturas-tbody');
                const avgTimeSpan = card.querySelector('.avg-time');
                const entregadasCountSpan = card.querySelector('.entregadas-count');
                const pendingCountSpan = card.querySelector('.pending-count');
                const totalFacturasSpan = card.querySelector('.total-facturas');
                const paginationControls = card.querySelector('.pagination-controls');
                const alreadyLoaded = tbody.dataset.loaded === 'true';

                if (!alreadyLoaded) {
                    const camion = card.dataset.camion;
                    const fechaInicio = document.getElementById('fecha_inicio').value;
                    const fechaFin = document.getElementById('fecha_fin').value;
                    const almacen = document.getElementById('almacen_filter')?.value || '';

                    console.log('Cargando facturas para camion (chasis):', camion);

                    try {
                        const response = await fetch(`${API_BASE}?view=facturas_by_truck&camion=${encodeURIComponent(camion)}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}&almacen=${almacen}`);

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('Error HTTP:', response.status, errorText);
                            throw new Error('Error al cargar facturas');
                        }

                        const data = await response.json();
                        console.log('Respuesta API facturas_by_truck:', data);
                        console.log('Camion (chasis) solicitado:', camion);

                        // Verificar si hay error en la respuesta
                        if (data.error) {
                            console.error('Error en respuesta:', data.error);
                            throw new Error(data.error);
                        }

                        // Asegurar que sea un array
                        const facturas = Array.isArray(data) ? data : [];
                        console.log('Total facturas encontradas:', facturas.length);

                        if (facturas.length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2rem; color: #999;">
                                        No hay facturas asignadas a este camión
                                    </td>
                                </tr>
                            `;
                            avgTimeSpan.textContent = '0';
                        } else {
                            let facturasHTML = '';
                            let totalTime = 0;
                            let countWithTime = 0;
                            let entregadasCount = 0;
                            let despachadasCount = 0;

                            facturas.forEach(factura => {
                                let tiempoHoras = '-';

                                // Calcular tiempo
                                if (factura.FechaDespacho && factura.FechaEntregado) {
                                    const despacho = new Date(factura.FechaDespacho);
                                    const entregado = new Date(factura.FechaEntregado);
                                    const diffMs = entregado - despacho;
                                    const diffHours = diffMs / (1000 * 60 * 60);

                                    if (diffHours >= 0) {
                                        tiempoHoras = diffHours.toFixed(1);
                                        totalTime += diffHours;
                                        countWithTime++;
                                    }
                                }

                                // Contar estados
                                const estadoUpper = (factura.Estado || '').toUpperCase();
                                if (estadoUpper === 'ENTREGADO') {
                                    entregadasCount++;
                                } else {
                                    despachadasCount++;
                                }

                                const estadoClass = estadoUpper === 'ENTREGADO' ? 'badge-entregado' : 'badge-despachado';
                                const estadoLabel = factura.Estado || 'N/A';

                                facturasHTML += `
                                    <tr data-estado="${factura.Estado || 'PENDIENTE'}">
                                        <td><strong>${factura.Factura || 'N/A'}</strong></td>
                                        <td>${factura.Cliente || 'N/A'}</td>
                                        <td>
                                            <div style="font-size: 0.9rem; font-weight: 600;">${factura.Transportista || 'N/A'}</div>
                                            <div style="font-size: 0.75rem; opacity: 0.7;"><i class="fas fa-id-card"></i> ${factura.Placa || factura.Chasis || 'N/A'}</div>
                                        </td>
                                        <td><span class="estado-badge ${estadoClass}">${estadoLabel}</span></td>
                                        <td><i class="fas fa-calendar"></i> ${factura.FechaDespacho ? new Date(factura.FechaDespacho).toLocaleDateString('es-DO') : 'N/A'}</td>
                                        <td>${factura.DespachadoPor || 'N/A'}</td>
                                        <td>${factura.FechaEntregado ? '<i class="fas fa-calendar-check"></i> ' + new Date(factura.FechaEntregado).toLocaleDateString('es-DO') : '<span style="opacity: 0.5;">-</span>'}</td>
                                        <td><strong>${tiempoHoras !== '-' ? tiempoHoras + ' hrs' : tiempoHoras}</strong></td>
                                    </tr>
                                `;
                            });

                            tbody.innerHTML = facturasHTML;

                            // Actualizar métricas
                            const avgTime = countWithTime > 0 ? (totalTime / countWithTime).toFixed(1) : '0';

                            avgTimeSpan.textContent = avgTime;
                            entregadasCountSpan.textContent = entregadasCount;
                            pendingCountSpan.textContent = despachadasCount;
                            totalFacturasSpan.textContent = facturas.length;

                            // Implementar paginación con filtro
                            const itemsPerPage = 20;
                            let currentPage = 1;
                            let currentFilter = 'despachado'; // Por defecto mostrar pendientes
                            const allRows = Array.from(tbody.querySelectorAll('tr[data-estado]'));

                            const showingStartSpan = card.querySelector('.showing-start');
                            const showingEndSpan = card.querySelector('.showing-end');
                            const totalItemsSpan = card.querySelector('.total-items');
                            const pageIndicator = card.querySelector('.page-indicator');
                            const prevBtn = card.querySelector('.prev-page');
                            const nextBtn = card.querySelector('.next-page');

                            function getFilteredRows() {
                                return allRows.filter(row => {
                                    const estado = (row.dataset.estado || '').toUpperCase();
                                    if (currentFilter === 'all') return true;
                                    if (currentFilter === 'entregado') return estado === 'ENTREGADO';
                                    if (currentFilter === 'despachado') return estado !== 'ENTREGADO';
                                    return true;
                                });
                            }

                            function renderPage(page) {
                                const filteredRows = getFilteredRows();
                                const totalPages = Math.ceil(filteredRows.length / itemsPerPage) || 1;
                                currentPage = Math.min(page, totalPages);
                                const start = (currentPage - 1) * itemsPerPage;
                                const end = Math.min(start + itemsPerPage, filteredRows.length);

                                // Ocultar todas las filas primero
                                allRows.forEach(row => row.style.display = 'none');

                                // Mostrar solo las filas filtradas de la página actual
                                filteredRows.forEach((row, index) => {
                                    row.style.display = (index >= start && index < end) ? '' : 'none';
                                });

                                showingStartSpan.textContent = filteredRows.length > 0 ? start + 1 : 0;
                                showingEndSpan.textContent = end;
                                totalItemsSpan.textContent = filteredRows.length;
                                pageIndicator.textContent = `${currentPage} / ${totalPages}`;

                                prevBtn.disabled = currentPage <= 1;
                                nextBtn.disabled = currentPage >= totalPages;
                            }

                            prevBtn.addEventListener('click', () => {
                                if (currentPage > 1) renderPage(currentPage - 1);
                            });

                            nextBtn.addEventListener('click', () => {
                                const filteredRows = getFilteredRows();
                                const totalPages = Math.ceil(filteredRows.length / itemsPerPage) || 1;
                                if (currentPage < totalPages) renderPage(currentPage + 1);
                            });

                            // Renderizar primera página con filtro de pendientes
                            renderPage(1);

                            // Agregar funcionalidad de filtrado
                            const filterBtns = card.querySelectorAll('.filter-btn');
                            filterBtns.forEach(btn => {
                                btn.addEventListener('click', function() {
                                    // Remover active de todos
                                    filterBtns.forEach(b => b.classList.remove('active'));
                                    // Agregar active al clickeado
                                    this.classList.add('active');

                                    // Actualizar filtro y re-renderizar desde página 1
                                    currentFilter = this.dataset.filter;
                                    renderPage(1);
                                });
                            });
                        }

                        tbody.dataset.loaded = 'true';

                    } catch (error) {
                        console.error('Error:', error);
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: #E53E3E;">
                                    <i class="fas fa-exclamation-triangle"></i> Error al cargar las facturas
                                </td>
                            </tr>
                        `;
                        avgTimeSpan.textContent = 'Error';
                    }
                }
            }
        };

        // updateDashboard (sin cambios)
        const updateDashboard = (data, view) => {
            const formatter = new Intl.NumberFormat();
            if (view === 'overview') {
                document.getElementById('total-emitidas').textContent = formatter.format(data.totalEmitidas || 0);
                document.getElementById('sin-estado').textContent = formatter.format(data.sinEstado || 0);
                
                statusChart.data.labels = data.estadosData.map(d => d.Estado);
                statusChart.data.datasets[0].data = data.estadosData.map(d => d.Total);
                statusChart.update();

                const statusTableBody = document.getElementById('statusTableBody');
                statusTableBody.innerHTML = '';
                const allStatusData = [...data.estadosData, { Estado: 'Sin estado', Total: data.sinEstado }];
                
                allStatusData.forEach(item => {
                    if (item.Total > 0) {
                        const row = statusTableBody.insertRow();
                        row.style.cursor = 'pointer';
                        row.title = `Haz clic para ver los detalles de "${item.Estado}"`;
                        row.onclick = () => showDetailsView(item.Estado);
                        row.insertCell().textContent = item.Estado;
                        row.insertCell().textContent = formatter.format(item.Total);
                    }
                });
                document.getElementById('statusTableTotal').textContent = formatter.format(data.totalEmitidas);
            
            } else if (view === 'trends' && data.tendenciaRegistros) {
                const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                trendsChart.data.labels = data.tendenciaRegistros.map(d => {
                    const fecha = new Date(d.Dia + 'T00:00:00');
                    return `${diasSemana[fecha.getDay()]} (${d.Dia.substring(5)})`;
                });
                // Dataset 0: Cantidad de facturas
                trendsChart.data.datasets[0].data = data.tendenciaRegistros.map(d => d.Total);
                // Dataset 1: Monto total
                trendsChart.data.datasets[1].data = data.tendenciaRegistros.map(d => parseFloat(d.TotalMonto) || 0);
                trendsChart.update();
            }
        };

        // formatDate, populateDetailsTable, updatePaginationControls (sin cambios)
        const formatDate = (dateStr) => {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr);
                return isNaN(date.getTime()) ? 'N/A' : date.toLocaleString('es-DO', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
            } catch (e) { return 'N/A'; }
        };

        // Función para formatear solo la fecha sin hora (evita problemas de zona horaria)
        const formatDateOnly = (dateStr) => {
            if (!dateStr) return 'N/A';
            try {
                // Extraer solo la parte de la fecha (YYYY-MM-DD) del string
                const dateOnly = dateStr.toString().split('T')[0].split(' ')[0];
                if (!dateOnly || dateOnly.length < 10) return 'N/A';
                
                // Parsear manualmente para evitar problemas de zona horaria
                const parts = dateOnly.split('-');
                if (parts.length === 3) {
                    const day = parts[2];
                    const month = parts[1];
                    const year = parts[0];
                    return `${day}/${month}/${year}`;
                }
                return dateOnly;
            } catch (e) { return 'N/A'; }
        };

        
        const populateDetailsTable = (facturas) => {
            const tableBody = document.getElementById('detailsTableBody');
            tableBody.innerHTML = !facturas || facturas.length === 0 ? '<tr><td colspan="18" style="text-align:center;">No se encontraron facturas.</td></tr>' : '';
            if(!facturas || facturas.length === 0) return;
            
            const currencyFormatter = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' });

            facturas.forEach(f => {
                const row = tableBody.insertRow();
                row.insertCell().textContent = f.No_Factura || 'N/A';
                row.insertCell().textContent = formatDateOnly(f.Fecha_Factura);
                row.insertCell().textContent = formatDate(f.Fecha_de_Registro);
                row.insertCell().textContent = f.invoicingname || 'N/A';
                
                const montoCell = row.insertCell();
                montoCell.textContent = currencyFormatter.format(f.invoiceamountmst || 0);
                montoCell.style.textAlign = 'right';

                row.insertCell().textContent = f.Registrado_por || 'N/A';
                row.insertCell().textContent = f.Camion || 'N/A';
                row.insertCell().textContent = formatDate(f.Fecha_de_Despacho);
                row.insertCell().textContent = f.Despachado_por || 'N/A';
                row.insertCell().textContent = formatDate(f.Fecha_de_Entregado);
                row.insertCell().textContent = f.Entregado_por || 'N/A';
                row.insertCell().textContent = f.Estado || 'N/A';
                row.insertCell().textContent = formatDate(f.Fecha_Reversada);
                row.insertCell().textContent = f.Reversado_Por || 'N/A';
                row.insertCell().textContent = formatDate(f.Fecha_de_NC);
                row.insertCell().textContent = f.NC_Realizado_Por || 'N/A';
                row.insertCell().textContent = f.Motivo_NC || 'N/A';
                row.insertCell().textContent = f.Camion2 || 'N/A';
            });
        };
    
        // Función para cargar y mostrar entregas sin QR
        const fetchSinQRData = async () => {
            const inicio = fechaInicioInput.value;
            const fin = fechaFinInput.value;
            const almacen = almacenFilterInput ? almacenFilterInput.value : '';
            
            const sinQRTableBody = document.getElementById('sinqr-table-body');
            const sinQRTotal = document.getElementById('sinqr-total');
            const sinQRShowing = document.getElementById('sinqr-showing');
            
            // Elementos del Overview
            const sinQRAlert = document.getElementById('sinqr-alert');
            const sinQROverviewCount = document.getElementById('sinqr-overview-count');
            const overviewSinQRTotal = document.getElementById('overview-sinqr-total');
            
            try {
                const url = `${API_BASE}?view=entregas_sin_qr&fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}`;
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                const totalCount = data.total || 0;
                
                // Actualizar KPI en Performance
                if (sinQRTotal) sinQRTotal.textContent = totalCount;
                if (sinQRShowing) sinQRShowing.textContent = data.entregas ? data.entregas.length : 0;
                
                // Actualizar contadores en Overview
                if (sinQROverviewCount) sinQROverviewCount.textContent = totalCount;
                if (overviewSinQRTotal) overviewSinQRTotal.textContent = totalCount;
                
                // Mostrar/ocultar alerta en Overview
                if (sinQRAlert) {
                    sinQRAlert.style.display = totalCount > 0 ? 'block' : 'none';
                }
                
                // Renderizar tabla si existe
                if (!sinQRTableBody) return;
                
                if (!data.entregas || data.entregas.length === 0) {
                    sinQRTableBody.innerHTML = `
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: #38A169;">
                                <i class="fas fa-check-circle" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                                <br>No hay entregas sin QR en este período. ¡Excelente!
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                let tableHTML = '';
                data.entregas.forEach(entrega => {
                    const fechaDespacho = entrega.FechaDespacho ? new Date(entrega.FechaDespacho).toLocaleDateString('es-DO') : '--';
                    const fechaEntrega = entrega.FechaEntregado ? new Date(entrega.FechaEntregado).toLocaleDateString('es-DO') : '--';
                    
                    tableHTML += `
                        <tr>
                            <td><strong>${entrega.Factura || 'N/A'}</strong></td>
                            <td>${entrega.CodigoCliente || 'N/A'}</td>
                            <td>${entrega.Cliente || 'N/A'}</td>
                            <td>${entrega.Transportista || 'Sin asignar'}</td>
                            <td>${entrega.Placa || 'N/A'}</td>
                            <td><span class="status-badge status-${(entrega.Estado || '').toLowerCase()}">${entrega.Estado || 'N/A'}</span></td>
                            <td>${fechaDespacho}</td>
                            <td>${fechaEntrega}</td>
                            <td>${entrega.EntregadoPor || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                sinQRTableBody.innerHTML = tableHTML;
                
            } catch (error) {
                console.error('Error al cargar entregas sin QR:', error);
                if (sinQRTableBody) {
                    sinQRTableBody.innerHTML = `
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: #E53E3E;">
                                <i class="fas fa-exclamation-circle"></i> Error al cargar datos: ${error.message}
                            </td>
                        </tr>
                    `;
                }
                // Mostrar 0 en Overview si hay error
                if (sinQROverviewCount) sinQROverviewCount.textContent = '0';
                if (overviewSinQRTotal) overviewSinQRTotal.textContent = '0';
                if (sinQRAlert) sinQRAlert.style.display = 'none';
            }
        };


        const updatePaginationControls = ({ currentPage, totalPages, totalRecords }) => {
            document.getElementById('page-info').textContent = `Página ${currentPage} de ${totalPages} (Total: ${totalRecords})`;
            document.getElementById('prev-page').disabled = currentPage <= 1;
            document.getElementById('next-page').disabled = currentPage >= totalPages;
        };

        const fetchDetails = async (estado, inicio, fin, almacen, page, limit) => {
            detailsCurrentState = estado;
            loaderEl.classList.add('loading');
            const detailsTableBody = document.getElementById('detailsTableBody');
            detailsTableBody.innerHTML = '<tr><td colspan="18" style="text-align:center;">Cargando...</td></tr>';
            try {
                // El 'almacen' que se pasa aquí ya está decidido (o del admin o del usuario)
                const url = `${API_BASE}?view=details&estado=${encodeURIComponent(estado)}&fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&page=${page}&limit=${limit}`;
                const response = await fetch(url);

                if (response.status === 401) {
                    window.location.href = 'dashboard.php'; // Redirigir al login
                    return;
                }
                if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                
                const result = await response.json();
                if (result.error) throw new Error(result.error);
                detailsCurrentPage = result.currentPage;
                detailsLimit = result.limit;
                detailsTotalPages = result.totalPages;
                populateDetailsTable(result.data);
                updatePaginationControls(result);
            } catch (error) {
                console.error("Error al cargar detalles:", error);
                detailsTableBody.innerHTML = `<tr><td colspan="17" style="text-align:center; color: red;">Error: ${error.message}</td></tr>`;
                updatePaginationControls({ currentPage: 1, totalPages: 1, totalRecords: 0 });
            } finally {
                loaderEl.classList.remove('loading');
            }
        };

        const showDetailsView = (estado) => {
            document.querySelector('.sidebar-nav a.active')?.classList.remove('active');
            document.querySelectorAll('.view-container').forEach(v => v.classList.remove('active'));
            document.getElementById('view-details').classList.add('active');
            currentView = 'details';
            
            const inicio = fechaInicioInput.value;
            const fin = fechaFinInput.value;
            
            // --- MODIFICACIÓN: Decidir qué almacén usar ---
            let almacen = '';
            if (USER_TYPE === 'admin') {
                almacen = almacenFilterInput ? almacenFilterInput.value : '';
            } else {
                almacen = USER_WAREHOUSE;
            }
            // --- FIN MODIFICACIÓN ---

            const displayTitle = (estado === 'ALL') ? 'TOTAL DE FACTURAS EMITIDAS' : `Detalle de Facturas: ${estado}`;
            document.getElementById('details-title').textContent = displayTitle;
            document.getElementById('details-period').innerHTML = `Mostrando resultados del <strong>${inicio}</strong> al <strong>${fin}</strong>.`;
            detailsCurrentPage = 1; 
            fetchDetails(estado, inicio, fin, almacen, detailsCurrentPage, detailsLimit);
        };
        
        const applyFiltersAndFetchData = () => {
            const inicio = fechaInicioInput.value;
            const fin = fechaFinInput.value;
            
            // --- MODIFICACIÓN: Decidir qué almacén usar ---
            let almacen = '';
            if (USER_TYPE === 'admin') {
                almacen = almacenFilterInput ? almacenFilterInput.value : '';
            } else {
                almacen = USER_WAREHOUSE;
            }
            // --- FIN MODIFICACIÓN ---

            if (inicio && fin) {
                if (currentView === 'details' && detailsCurrentState) {
                    detailsCurrentPage = 1;
                    fetchDetails(detailsCurrentState, inicio, fin, almacen, detailsCurrentPage, detailsLimit);
                } else if (currentView !== 'details') {
                    fetchData(inicio, fin, almacen, currentView);
                }
                // Actualizar conteo de entregas sin QR
                fetchSinQRData();
            }
        };

        const setupKpiClickEvents = () => {
            document.getElementById('kpi-total-emitidas').onclick = () => showDetailsView('ALL');
            document.getElementById('kpi-sin-estado').onclick = () => showDetailsView('Sin estado');
        };

        const setDateDefaults = () => {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            fechaInicioInput.value = firstDay;
            fechaFinInput.value = lastDay;
        };

        // --- INICIALIZACIÓN ---
        setDateDefaults();
        initializeCharts();
        
        // Cargar almacenes si es admin
        if (USER_TYPE === 'admin') {
            populateAlmacenFilter();
        }
        
        applyFiltersAndFetchData();
        fetchSinQRData(); // Cargar conteo de entregas sin QR para Overview
        setupKpiClickEvents();

        // --- Eventos ---
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', e => {
                
                // --- MODIFICACIÓN ---
                // Si el link es el de logout, no prevenimos la acción por defecto
                // El navegador SÍ debe seguir el enlace "dashboard.php?action=logout"
                if (link.classList.contains('logout-link')) {
                    return; 
                }
                
                // Si es cualquier otro link, prevenimos la acción
                e.preventDefault();
                // --- FIN MODIFICACIÓN ---

                document.querySelector('.sidebar-nav a.active')?.classList.remove('active');
                link.classList.add('active');
                
                const targetView = link.dataset.view;
                document.querySelectorAll('.view-container').forEach(v => v.classList.remove('active'));
                document.getElementById(`view-${targetView}`).classList.add('active');
                
                const viewTitles = {
                    overview: 'Resumen de Facturas',
                    trends: 'Tendencias Diarias de Registros',
                    performance: 'Análisis de Rendimiento y Calidad',
                    financial: 'Análisis Financiero',
                    drivers: 'Rendimiento de Choferes',
                    details: 'Detalle de Facturas'
                };
                mainTitle.textContent = viewTitles[targetView] || 'Dashboard';
                
                currentView = targetView;
                applyFiltersAndFetchData();
                
                // Sincronizar tabs del header
                document.querySelectorAll('.header-tabs .tab-item').forEach(t => t.classList.remove('active'));
                document.querySelector(`.header-tabs .tab-item[data-view="${targetView}"]`)?.classList.add('active');
            });
        });

        // Event listeners para tabs del header
        document.querySelectorAll('.header-tabs .tab-item').forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                
                const targetView = tab.dataset.view;
                
                // Actualizar tabs
                document.querySelectorAll('.header-tabs .tab-item').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Actualizar sidebar
                document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
                document.querySelector(`.sidebar-nav a[data-view="${targetView}"]`)?.classList.add('active');
                
                // Cambiar vista
                document.querySelectorAll('.view-container').forEach(v => v.classList.remove('active'));
                document.getElementById(`view-${targetView}`).classList.add('active');
                
                const viewTitles = {
                    overview: 'Resumen de Facturas',
                    trends: 'Tendencias Diarias de Registros',
                    performance: 'Análisis de Rendimiento y Calidad',
                    financial: 'Análisis Financiero'
                };
                mainTitle.textContent = viewTitles[targetView] || 'Dashboard';
                
                currentView = targetView;
                applyFiltersAndFetchData();
            });
        });

        document.getElementById('back-to-overview').addEventListener('click', () => {
            document.getElementById('view-details').classList.remove('active');
            document.getElementById('view-overview').classList.add('active');
            document.querySelector('.sidebar-nav a.active')?.classList.remove('active');
            document.querySelector('.sidebar-nav a[data-view="overview"]').classList.add('active');
            mainTitle.textContent = 'Resumen de Facturas';
            currentView = 'overview';
            applyFiltersAndFetchData();
        });

        fechaInicioInput.addEventListener('change', applyFiltersAndFetchData);
        fechaFinInput.addEventListener('change', applyFiltersAndFetchData);
        
        // --- MODIFICACIÓN: Solo añadir evento si el filtro existe (admin) ---
        if (USER_TYPE === 'admin' && almacenFilterInput) {
            almacenFilterInput.addEventListener('change', applyFiltersAndFetchData);
        }

        // --- Paginación (sin cambios) ---
        document.getElementById('prev-page').addEventListener('click', () => {
            if (detailsCurrentPage > 1) {
                detailsCurrentPage--;
                applyFiltersAndFetchData();
            }
        });
        document.getElementById('next-page').addEventListener('click', () => {
            if (detailsCurrentPage < detailsTotalPages) {
                detailsCurrentPage++;
                applyFiltersAndFetchData();
            }
        });
        document.getElementById('details-limit').addEventListener('change', e => {
            detailsLimit = parseInt(e.target.value);
            detailsCurrentPage = 1;
            applyFiltersAndFetchData();
        });

        // Event listeners para paginación de camiones
        document.getElementById('trucksPrevPage')?.addEventListener('click', () => {
            if (window.trucksCurrentPage > 1) {
                window.trucksCurrentPage--;
                window.renderTrucksPage();
            }
        });
        
        document.getElementById('trucksNextPage')?.addEventListener('click', () => {
            const totalPages = Math.ceil(window.totalTrucks / window.trucksPerPageValue);
            if (window.trucksCurrentPage < totalPages) {
                window.trucksCurrentPage++;
                window.renderTrucksPage();
            }
        });
        
        document.getElementById('trucksPerPage')?.addEventListener('change', (e) => {
            window.trucksPerPageValue = parseInt(e.target.value);
            window.trucksCurrentPage = 1;
            window.renderTrucksPage();
        });
    });
</script>

</body>
</html>
