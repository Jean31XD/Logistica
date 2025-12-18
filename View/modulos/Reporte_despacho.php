<?php
/**
 * Reporte de Despacho - MACO Design System
 * Dashboard organizado por secciones con tendencias claras
 */

require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion([0, 1, 5]);

$pageTitle = "Reporte de Despacho | MACO";
$additionalCSS = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
';
include __DIR__ . '/../templates/header.php';
?>

<style>
    :root {
        --section-gap: 3rem;
    }

    .report-hero {
        background: linear-gradient(135deg, var(--primary) 0%, #c1121f 100%);
        padding: 2.5rem;
        border-radius: var(--radius-xl);
        color: white;
        margin-bottom: 2rem;
        text-align: center;
    }
    .report-hero h1 { font-size: 2.25rem; font-weight: 700; margin: 0 0 0.5rem; }
    .report-hero p { opacity: 0.9; margin: 0; font-size: 1.1rem; }

    /* Filtros */
    .filters-bar {
        background: white;
        padding: 1.25rem 2rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        margin-bottom: var(--section-gap);
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--border);
    }
    .filter-item { display: flex; align-items: center; gap: 0.75rem; }
    .filter-item label {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 0.9rem;
    }
    .filter-item input {
        padding: 0.625rem 1rem;
        border: 2px solid var(--border);
        border-radius: var(--radius);
        font-size: 0.9rem;
        transition: all 0.3s;
        font-weight: 500;
    }
    .filter-item input:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.15);
    }
    .loading-indicator {
        display: none;
        color: var(--primary);
        font-weight: 600;
    }
    .loading-indicator.active { display: flex; align-items: center; gap: 0.5rem; }

    /* Secciones */
    .section {
        margin-bottom: var(--section-gap);
    }
    .section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 3px solid var(--primary);
    }
    .section-icon {
        width: 50px;
        height: 50px;
        background: var(--primary);
        color: white;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .section-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-primary);
        margin: 0;
    }
    .section-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin: 0.25rem 0 0;
    }

    /* KPIs Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    .kpi-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .kpi-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 5px;
    }
    .kpi-card.blue::before { background: #3b82f6; }
    .kpi-card.green::before { background: #10b981; }
    .kpi-card.yellow::before { background: #f59e0b; }
    .kpi-card.red::before { background: #ef4444; }
    .kpi-card.purple::before { background: #8b5cf6; }
    .kpi-card.cyan::before { background: #06b6d4; }
    
    .kpi-icon {
        width: 60px; height: 60px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        display: flex;
        align-items: center; justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    .kpi-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .kpi-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
    .kpi-icon.yellow { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .kpi-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .kpi-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .kpi-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0891b2); }
    
    .kpi-value { font-size: 2.5rem; font-weight: 800; color: var(--text-primary); margin: 0; }
    .kpi-label { font-size: 0.9rem; color: var(--text-secondary); margin: 0.5rem 0 0; font-weight: 600; }

    /* Chart Cards */
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    .chart-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-lg);
    }
    .chart-card.full-width { grid-column: span 2; }
    .chart-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
    }
    .chart-header i { color: var(--primary); font-size: 1.25rem; }
    .chart-header h3 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text-primary); }
    .chart-container { height: 280px; position: relative; }

    /* Data Cards */
    .data-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 1.5rem;
    }
    .data-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border);
    }
    .data-header-left { display: flex; align-items: center; gap: 0.75rem; }
    .data-header i { color: var(--primary); font-size: 1.25rem; }
    .data-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }

    /* Tables */
    .report-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .report-table th {
        background: linear-gradient(135deg, var(--primary), #c1121f);
        color: white;
        padding: 1rem;
        text-align: left;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .report-table th:first-child { border-radius: var(--radius-lg) 0 0 0; }
    .report-table th:last-child { border-radius: 0 var(--radius-lg) 0 0; }
    .report-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.9rem;
        vertical-align: middle;
    }
    .report-table tbody tr:hover { background: var(--bg-hover); }
    .report-table tbody tr:last-child td { border-bottom: none; }
    
    /* Badges */
    .time-badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 700;
    }
    .time-badge.fast { background: #dcfce7; color: #166534; }
    .time-badge.normal { background: #fef9c3; color: #854d0e; }
    .time-badge.slow { background: #fee2e2; color: #991b1b; }

    .rank-badge {
        width: 32px; height: 32px;
        display: inline-flex;
        align-items: center; justify-content: center;
        border-radius: 50%;
        font-weight: 800;
        font-size: 0.9rem;
        color: white;
    }
    .rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
    .rank-2 { background: linear-gradient(135deg, #9ca3af, #6b7280); }
    .rank-3 { background: linear-gradient(135deg, #cd7c2f, #b45309); }
    .rank-other { background: var(--gray-400); font-size: 0.75rem; }

    .status-badge {
        padding: 0.35rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 700;
    }
    .status-ok { background: #dcfce7; color: #166534; }
    .status-warning { background: #fef9c3; color: #854d0e; }
    .status-danger { background: #fee2e2; color: #991b1b; }

    /* Clientes expandibles */
    .expandable-row { cursor: pointer; transition: background 0.2s; }
    .expandable-row:hover { background: var(--bg-hover); }
    .expandable-row td:first-child::before {
        content: '▶';
        margin-right: 0.5rem;
        font-size: 0.7rem;
        transition: transform 0.2s;
    }
    .expandable-row.expanded td:first-child::before { transform: rotate(90deg); display: inline-block; }
    
    .detail-row { display: none; }
    .detail-row.show { display: table-row; }
    .detail-content {
        background: #f8fafc;
        padding: 1.25rem;
        border-radius: var(--radius);
    }
    .client-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .client-row {
        background: white;
        border: 1px solid var(--border);
        border-left: 4px solid var(--primary);
        padding: 0.75rem 1rem;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    .client-row:hover {
        background: var(--bg-hover);
        transform: translateX(5px);
    }
    .client-row .empresa-name {
        font-weight: 600;
        color: var(--text-primary);
    }
    .client-row .ticket-count {
        background: var(--primary);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius-full);
        font-weight: 700;
        font-size: 0.8rem;
    }

    @media (max-width: 1024px) {
        .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        .chart-grid { grid-template-columns: 1fr; }
        .chart-card.full-width { grid-column: span 1; }
    }
    @media (max-width: 640px) {
        .kpi-grid { grid-template-columns: 1fr; }
    }
</style>

<!-- HERO -->
<div class="report-hero animate__animated animate__fadeIn">
    <h1><i class="fas fa-chart-line me-3"></i>Reporte de Despacho</h1>
    <p>Análisis completo de rendimiento, tiempos y tendencias</p>
</div>

<!-- FILTROS -->
<div class="filters-bar">
    <div class="filter-item">
        <label><i class="fas fa-calendar me-2"></i>Fecha Inicio:</label>
        <input type="date" id="filtroFechaInicio" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
    </div>
    <div class="filter-item">
        <label><i class="fas fa-calendar-check me-2"></i>Fecha Fin:</label>
        <input type="date" id="filtroFechaFin" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="loading-indicator" id="loadingIndicator">
        <i class="fas fa-circle-notch fa-spin"></i> Cargando datos...
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 1: RESUMEN GENERAL -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-tachometer-alt"></i></div>
        <div>
            <h2 class="section-title">Resumen General</h2>
            <p class="section-subtitle">Métricas principales del período seleccionado</p>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon blue"><i class="fas fa-check-double"></i></div>
            <p class="kpi-value" id="statDespachados">0</p>
            <p class="kpi-label">Tickets Despachados</p>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon green"><i class="fas fa-stopwatch"></i></div>
            <p class="kpi-value" id="statTiempoPromedio">0 min</p>
            <p class="kpi-label">Tiempo Promedio de Atención</p>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon purple"><i class="fas fa-user-friends"></i></div>
            <p class="kpi-value" id="statTotalUsuarios">0</p>
            <p class="kpi-label">Usuarios Activos</p>
        </div>
        <div class="kpi-card yellow">
            <div class="kpi-icon yellow"><i class="fas fa-pause"></i></div>
            <p class="kpi-value" id="statEnRetencion">0</p>
            <p class="kpi-label">Tickets en Retención Actual</p>
        </div>
        <div class="kpi-card red">
            <div class="kpi-icon red"><i class="fas fa-hourglass-end"></i></div>
            <p class="kpi-value" id="statTiempoRetencion">0 min</p>
            <p class="kpi-label">Tiempo Promedio de Retención</p>
        </div>
        <div class="kpi-card cyan">
            <div class="kpi-icon cyan"><i class="fas fa-building"></i></div>
            <p class="kpi-value" id="statTotalEmpresas">0</p>
            <p class="kpi-label">Empresas Atendidas</p>
        </div>
        <div class="kpi-card" style="border-left: 4px solid #f97316;">
            <div class="kpi-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);"><i class="fas fa-running"></i></div>
            <p class="kpi-value" id="statSeFue">0</p>
            <p class="kpi-label">"Se Fue" (Cliente no esperó)</p>
        </div>
        <div class="kpi-card" style="border-left: 4px solid #22c55e;">
            <div class="kpi-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);"><i class="fas fa-dollar-sign"></i></div>
            <p class="kpi-value" id="statMontoTotal">$0</p>
            <p class="kpi-label">Monto Total Despachado</p>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 1.5: COMPORTAMIENTO EN EL TIEMPO -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon" style="background: #3b82f6;"><i class="fas fa-chart-line"></i></div>
        <div>
            <h2 class="section-title">Comportamiento en el Tiempo</h2>
            <p class="section-subtitle">Tendencia diaria de tickets despachados vs retenciones</p>
        </div>
    </div>

    <div class="chart-card" style="margin-bottom: 0;">
        <div class="chart-header">
            <i class="fas fa-chart-area" style="color: #3b82f6;"></i>
            <h3>Despachos vs Retenciones por Día</h3>
        </div>
        <div class="chart-container" style="height: 320px;">
            <canvas id="chartTendencia"></canvas>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 2: TENDENCIAS DE ATENCIÓN -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-chart-bar"></i></div>
        <div>
            <h2 class="section-title">Tendencias de Atención</h2>
            <p class="section-subtitle">Distribución y comparativa visual del rendimiento</p>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-users"></i>
                <h3>Tickets Despachados por Usuario</h3>
            </div>
            <div class="chart-container">
                <canvas id="chartTicketsUsuario"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-clock"></i>
                <h3>Clasificación de Tiempos</h3>
            </div>
            <div class="chart-container">
                <canvas id="chartTiempos"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 3: RANKING DE USUARIOS -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-trophy"></i></div>
        <div>
            <h2 class="section-title">Ranking de Usuarios</h2>
            <p class="section-subtitle">Ordenados por tiempo promedio de atención (más rápido primero). Click en una fila para ver clientes atendidos.</p>
        </div>
    </div>

    <div class="data-card">
        <div class="table-responsive">
            <table class="report-table" id="tablaUsuarios">
                <thead>
                    <tr>
                        <th style="width: 60px">Puesto</th>
                        <th>Usuario</th>
                        <th style="width: 100px">Tickets</th>
                        <th style="width: 130px">Tiempo Promedio</th>
                        <th style="width: 100px">Más Rápido</th>
                        <th style="width: 100px">Más Lento</th>
                        <th style="width: 120px">Empresas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="text-center text-muted py-4">Cargando datos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 4: EMPRESAS ATENDIDAS -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-building"></i></div>
        <div>
            <h2 class="section-title">Top 10 Empresas</h2>
            <p class="section-subtitle">Las empresas con mayor volumen de tickets despachados</p>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-chart-pie"></i>
                <h3>Distribución por Empresa</h3>
            </div>
            <div class="chart-container">
                <canvas id="chartEmpresas"></canvas>
            </div>
        </div>
        <div class="data-card" style="margin-bottom: 0;">
            <div class="data-header">
                <div class="data-header-left">
                    <i class="fas fa-list-ol"></i>
                    <h3>Detalle de Empresas</h3>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="report-table" id="tablaEmpresas">
                    <thead>
                        <tr>
                            <th style="width: 50px">#</th>
                            <th>Empresa</th>
                            <th style="width: 90px">Tickets</th>
                            <th style="width: 100px">Usuarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 4.5: MONTO POR ALMACÉN -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon" style="background: #6366f1;"><i class="fas fa-warehouse"></i></div>
        <div>
            <h2 class="section-title">Monto por Almacén</h2>
            <p class="section-subtitle">Distribución de montos despachados por ubicación de almacén</p>
        </div>
    </div>

    <div class="data-card" style="margin-bottom: 0;">
        <div class="data-header">
            <div class="data-header-left">
                <i class="fas fa-boxes"></i>
                <h3>Detalle por Almacén</h3>
            </div>
        </div>
        <div class="table-responsive">
            <table class="report-table" id="tablaAlmacenes">
                <thead>
                    <tr>
                        <th style="width: 50px">#</th>
                        <th>Almacén</th>
                        <th style="width: 100px">Facturas</th>
                        <th style="width: 150px">Monto Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SECCIÓN 5: RETENCIONES -->
<!-- ============================================== -->
<div class="section">
    <div class="section-header">
        <div class="section-icon" style="background: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <h2 class="section-title">Análisis de Retenciones</h2>
            <p class="section-subtitle">Tickets que fueron retenidos y tiempos de espera por usuario</p>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-chart-bar" style="color: #ef4444;"></i>
                <h3>Retenciones por Usuario</h3>
            </div>
            <div class="chart-container">
                <canvas id="chartRetenciones"></canvas>
            </div>
        </div>
        <div class="data-card" style="margin-bottom: 0;">
            <div class="data-header">
                <div class="data-header-left">
                    <i class="fas fa-clock" style="color: #ef4444;"></i>
                    <h3>Tiempos de Retención</h3>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="report-table" id="tablaRetenciones">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th style="width: 110px">Retenciones</th>
                            <th style="width: 130px">Tiempo Prom.</th>
                            <th style="width: 100px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
let chartTickets = null, chartTiempos = null, chartEmpresas = null, chartRetenciones = null, chartTendencia = null;
let clientesData = {};

$(document).ready(function() {
    const colores = [
        '#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', 
        '#06b6d4', '#ec4899', '#f97316', '#22c55e', '#6366f1'
    ];

    function formatearTiempo(minutos) {
        if (minutos === null || isNaN(minutos)) return '0 min';
        if (minutos < 60) return Math.round(minutos) + ' min';
        if (minutos < 1440) {
            const horas = Math.floor(minutos / 60);
            const mins = Math.round(minutos % 60);
            return horas + 'h ' + mins + 'm';
        }
        const dias = Math.floor(minutos / 1440);
        const horas = Math.floor((minutos % 1440) / 60);
        return dias + 'd ' + horas + 'h';
    }

    function getTimeBadgeClass(minutos) {
        if (minutos < 30) return 'fast';
        if (minutos < 120) return 'normal';
        return 'slow';
    }

    function formatearMonto(monto) {
        if (monto === null || isNaN(monto)) return '$0';
        if (monto >= 1000000) {
            return '$' + (monto / 1000000).toFixed(1) + 'M';
        } else if (monto >= 1000) {
            return '$' + (monto / 1000).toFixed(1) + 'K';
        }
        return '$' + monto.toLocaleString('es-DO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function getRankBadge(index) {
        if (index === 0) return '<span class="rank-badge rank-1">1°</span>';
        if (index === 1) return '<span class="rank-badge rank-2">2°</span>';
        if (index === 2) return '<span class="rank-badge rank-3">3°</span>';
        return '<span class="rank-badge rank-other">' + (index + 1) + '°</span>';
    }

    function showLoading() { $('#loadingIndicator').addClass('active'); }
    function hideLoading() { $('#loadingIndicator').removeClass('active'); }

    function cargarResumen() {
        const fechaInicio = $('#filtroFechaInicio').val();
        const fechaFin = $('#filtroFechaFin').val();
        
        $.ajax({
            url: '../../Logica/api_reporte_tickets.php',
            data: { tipo: 'resumen', fecha_inicio: fechaInicio, fecha_fin: fechaFin },
            dataType: 'json',
            success: function(data) {
                $('#statDespachados').text(data.totalDespachados || 0);
                $('#statTiempoPromedio').text(formatearTiempo(data.tiempoPromedioGeneral || 0));
                $('#statEnRetencion').text(data.ticketsEnRetencion || 0);
                $('#statTiempoRetencion').text(formatearTiempo(data.tiempoPromedioRetencion || 0));
                $('#statSeFue').text(data.ticketsSeFue || 0);
                $('#statMontoTotal').text(formatearMonto(data.montoTotal || 0));
                
                // Tabla de montos por almacén
                const tbodyAlmacen = $('#tablaAlmacenes tbody');
                tbodyAlmacen.empty();
                
                if (data.montoPorAlmacen && data.montoPorAlmacen.length > 0) {
                    data.montoPorAlmacen.forEach(function(alm, index) {
                        tbodyAlmacen.append(`
                            <tr>
                                <td><span class="rank-badge rank-other">${index + 1}</span></td>
                                <td><strong><i class="fas fa-warehouse me-2" style="color: #6366f1;"></i>${alm.almacen}</strong></td>
                                <td><span class="badge bg-primary">${alm.totalFacturas}</span></td>
                                <td><span style="color: #22c55e; font-weight: 700;">$${alm.monto.toLocaleString('es-DO')}</span></td>
                            </tr>
                        `);
                    });
                } else {
                    tbodyAlmacen.append('<tr><td colspan="4" class="text-center text-muted">Sin datos de almacén</td></tr>');
                }
            }
        });
    }

    function cargarTiempoPorUsuario() {
        const fechaInicio = $('#filtroFechaInicio').val();
        const fechaFin = $('#filtroFechaFin').val();
        
        $.ajax({
            url: '../../Logica/api_reporte_tickets.php',
            data: { tipo: 'tiempo_usuario', fecha_inicio: fechaInicio, fecha_fin: fechaFin },
            dataType: 'json',
            success: function(data) {
                const tbody = $('#tablaUsuarios tbody');
                tbody.empty();
                
                if (data.usuarios && data.usuarios.length > 0) {
                    $('#statTotalUsuarios').text(data.usuarios.length);

                    data.usuarios.forEach(function(u, index) {
                        const badgeClass = getTimeBadgeClass(u.tiempoPromedio);
                        const clientes = clientesData[u.usuario] || [];
                        const userId = u.usuario.replace(/[^a-zA-Z0-9]/g, '_');
                        
                        tbody.append(`
                            <tr class="expandable-row" data-user-id="${userId}">
                                <td>${getRankBadge(index)}</td>
                                <td><strong>${u.usuario}</strong></td>
                                <td><span class="badge bg-primary">${u.totalTickets}</span></td>
                                <td><span class="time-badge ${badgeClass}">${formatearTiempo(u.tiempoPromedio)}</span></td>
                                <td>${formatearTiempo(u.tiempoMinimo)}</td>
                                <td>${formatearTiempo(u.tiempoMaximo)}</td>
                                <td><span class="badge bg-secondary">${clientes.length} empresa${clientes.length !== 1 ? 's' : ''}</span></td>
                            </tr>
                            <tr class="detail-row" id="detail-${userId}">
                                <td colspan="7">
                                    <div class="detail-content">
                                        <p style="margin: 0 0 0.75rem; font-weight: 600; color: var(--text-secondary);">
                                            <i class="fas fa-building me-2"></i>Empresas atendidas por ${u.usuario}:
                                        </p>
                                        <div class="client-list">
                                            ${clientes.length > 0 ? clientes.map(c => 
                                                `<div class="client-row">
                                                    <span class="empresa-name"><i class="fas fa-circle me-2" style="font-size: 0.5rem; color: var(--primary);"></i>${c.empresa}</span>
                                                    <div style="display: flex; gap: 1rem; align-items: center;">
                                                        <span class="ticket-count">${c.totalTickets} ticket${c.totalTickets !== 1 ? 's' : ''}</span>
                                                        <span style="color: #22c55e; font-weight: 700; font-size: 0.85rem;">$${c.monto.toLocaleString('es-DO')}</span>
                                                    </div>
                                                </div>`
                                            ).join('') : '<span class="text-muted">Sin datos de empresas</span>'}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        `);
                    });

                    // Gráfica de tickets por usuario
                    const top10 = data.usuarios.slice(0, 10);
                    if (chartTickets) chartTickets.destroy();
                    chartTickets = new Chart(document.getElementById('chartTicketsUsuario'), {
                        type: 'bar',
                        data: {
                            labels: top10.map(u => u.usuario),
                            datasets: [{
                                label: 'Tickets',
                                data: top10.map(u => u.totalTickets),
                                backgroundColor: colores,
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } } }
                        }
                    });

                    // Gráfica de distribución de tiempos
                    let rapidos = 0, normales = 0, lentos = 0;
                    data.usuarios.forEach(u => {
                        if (u.tiempoPromedio < 30) rapidos += u.totalTickets;
                        else if (u.tiempoPromedio < 120) normales += u.totalTickets;
                        else lentos += u.totalTickets;
                    });

                    if (chartTiempos) chartTiempos.destroy();
                    chartTiempos = new Chart(document.getElementById('chartTiempos'), {
                        type: 'doughnut',
                        data: {
                            labels: ['⚡ Rápido (< 30 min)', '⏱️ Normal (30 min - 2h)', '🐢 Lento (> 2h)'],
                            datasets: [{
                                data: [rapidos, normales, lentos],
                                backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                                borderWidth: 0,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { size: 12 } } } }
                        }
                    });
                } else {
                    tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">No hay datos para este período</td></tr>');
                    $('#statTotalUsuarios').text('0');
                }
            }
        });
    }

    function cargarClientesPorUsuario() {
        const fechaInicio = $('#filtroFechaInicio').val();
        const fechaFin = $('#filtroFechaFin').val();
        
        $.ajax({
            url: '../../Logica/api_reporte_tickets.php',
            data: { tipo: 'clientes_usuario', fecha_inicio: fechaInicio, fecha_fin: fechaFin },
            dataType: 'json',
            success: function(data) {
                clientesData = {};
                if (data.clientesPorUsuario) {
                    data.clientesPorUsuario.forEach(item => { clientesData[item.usuario] = item.clientes; });
                }

                // Top empresas
                const tbodyEmpresas = $('#tablaEmpresas tbody');
                tbodyEmpresas.empty();
                
                if (data.topEmpresas && data.topEmpresas.length > 0) {
                    $('#statTotalEmpresas').text(data.topEmpresas.length);
                    
                    data.topEmpresas.forEach((e, index) => {
                        tbodyEmpresas.append(`
                            <tr>
                                <td>${getRankBadge(index)}</td>
                                <td><strong>${e.empresa}</strong></td>
                                <td><span class="badge bg-primary">${e.totalTickets}</span></td>
                                <td><span class="badge bg-info">${e.usuariosQueAtendieron}</span></td>
                            </tr>
                        `);
                    });

                    // Gráfica de empresas
                    if (chartEmpresas) chartEmpresas.destroy();
                    chartEmpresas = new Chart(document.getElementById('chartEmpresas'), {
                        type: 'bar',
                        data: {
                            labels: data.topEmpresas.map(e => e.empresa.substring(0, 20) + (e.empresa.length > 20 ? '...' : '')),
                            datasets: [{
                                label: 'Tickets',
                                data: data.topEmpresas.map(e => e.totalTickets),
                                backgroundColor: colores,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true } }
                        }
                    });
                } else {
                    tbodyEmpresas.append('<tr><td colspan="4" class="text-center text-muted">No hay datos</td></tr>');
                }

                cargarTiempoPorUsuario();
            }
        });
    }

    function cargarRetencionesPorUsuario() {
        const fechaInicio = $('#filtroFechaInicio').val();
        const fechaFin = $('#filtroFechaFin').val();
        
        $.ajax({
            url: '../../Logica/api_reporte_tickets.php',
            data: { tipo: 'retenciones', fecha_inicio: fechaInicio, fecha_fin: fechaFin },
            dataType: 'json',
            success: function(data) {
                const tbody = $('#tablaRetenciones tbody');
                tbody.empty();
                
                if (data.retencionPorUsuario && data.retencionPorUsuario.length > 0) {
                    data.retencionPorUsuario.forEach(r => {
                        const badgeClass = getTimeBadgeClass(r.tiempoPromedio);
                        let estado, estadoClass;
                        if (r.tiempoPromedio < 60) { estado = 'Normal'; estadoClass = 'status-ok'; }
                        else if (r.tiempoPromedio < 180) { estado = 'Alerta'; estadoClass = 'status-warning'; }
                        else { estado = 'Crítico'; estadoClass = 'status-danger'; }

                        tbody.append(`
                            <tr>
                                <td><strong>${r.usuario}</strong></td>
                                <td><span class="badge bg-danger">${r.totalRetenciones}</span></td>
                                <td><span class="time-badge ${badgeClass}">${formatearTiempo(r.tiempoPromedio)}</span></td>
                                <td><span class="status-badge ${estadoClass}">${estado}</span></td>
                            </tr>
                        `);
                    });

                    // Gráfica de retenciones
                    if (chartRetenciones) chartRetenciones.destroy();
                    chartRetenciones = new Chart(document.getElementById('chartRetenciones'), {
                        type: 'bar',
                        data: {
                            labels: data.retencionPorUsuario.map(r => r.usuario),
                            datasets: [{
                                label: 'Retenciones',
                                data: data.retencionPorUsuario.map(r => r.totalRetenciones),
                                backgroundColor: '#ef4444',
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                } else {
                    tbody.append('<tr><td colspan="4" class="text-center text-muted">No hay retenciones registradas</td></tr>');
                }
                hideLoading();
            }
        });
    }

    function cargarTendencia() {
        const fechaInicio = $('#filtroFechaInicio').val();
        const fechaFin = $('#filtroFechaFin').val();
        
        $.ajax({
            url: '../../Logica/api_reporte_tickets.php',
            data: { tipo: 'tendencia_diaria', fecha_inicio: fechaInicio, fecha_fin: fechaFin },
            dataType: 'json',
            success: function(data) {
                // Combinar fechas de todos los datasets
                const todasFechas = new Set();
                (data.despachados || []).forEach(d => todasFechas.add(d.fecha));
                (data.retenciones || []).forEach(r => todasFechas.add(r.fecha));
                (data.seFue || []).forEach(s => todasFechas.add(s.fecha));
                
                const fechasOrdenadas = Array.from(todasFechas).sort();
                
                // Crear mapas para acceso rápido
                const despMap = {};
                const retMap = {};
                const seFueMap = {};
                (data.despachados || []).forEach(d => despMap[d.fecha] = d.total);
                (data.retenciones || []).forEach(r => retMap[r.fecha] = r.total);
                (data.seFue || []).forEach(s => seFueMap[s.fecha] = s.total);
                
                // Datos alineados por fecha
                const despachosData = fechasOrdenadas.map(f => despMap[f] || 0);
                const retencionesData = fechasOrdenadas.map(f => retMap[f] || 0);
                const seFueData = fechasOrdenadas.map(f => seFueMap[f] || 0);
                
                // Formatear etiquetas de fecha
                const labels = fechasOrdenadas.map(f => {
                    const d = new Date(f + 'T00:00:00');
                    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' });
                });
                
                if (chartTendencia) chartTendencia.destroy();
                chartTendencia = new Chart(document.getElementById('chartTendencia'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Despachados',
                                data: despachosData,
                                borderColor: '#22c55e',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#22c55e'
                            },
                            {
                                label: 'Se Fue',
                                data: seFueData,
                                borderColor: '#f97316',
                                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#f97316'
                            },
                            {
                                label: 'Retenciones',
                                data: retencionesData,
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#ef4444'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { usePointStyle: true, padding: 20 }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                padding: 12,
                                titleFont: { size: 14 },
                                bodyFont: { size: 13 }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        });
    }

    function cargarTodo() {
        showLoading();
        cargarResumen();
        cargarTendencia();
        cargarClientesPorUsuario();
        cargarRetencionesPorUsuario();
    }

    // Toggle para filas expandibles
    $(document).on('click', '.expandable-row', function() {
        $(this).toggleClass('expanded');
        const userId = $(this).data('user-id');
        $('#detail-' + userId).toggleClass('show');
    });

    // Cargar al inicio
    cargarTodo();

    // Auto-refresh al cambiar fechas
    $('#filtroFechaInicio, #filtroFechaFin').on('change', cargarTodo);
});
</script>
<?php
$additionalJS = ob_get_clean();
include __DIR__ . '/../templates/footer.php';
?>
