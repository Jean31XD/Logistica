<?php
/**
 * Reporte de Despacho - MACO Design System
 * Dashboard organizado por secciones con tendencias claras
 */

require_once __DIR__ . '/../../conexionBD/session_config.php';
require_once __DIR__ . '/../../conexionBD/conexion.php';

// Verificar autenticación básica
if (!isset($_SESSION['usuario'])) {
    header("Location: " . getLoginUrl());
    exit();
}

// Verificar permiso usando usuario_modulos
if (!tieneModulo('reporte_despacho', $conn)) {
    header("Location: " . getBaseUrl() . "/View/pantallas/Portal.php?error=permisos");
    exit();
}

$pageTitle = "Reporte de Despacho | MACO";
$additionalCSS = '
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
';
include __DIR__ . '/../templates/header.php';
?>

<style>
    :root {
        --rd-primary: #E63946;
        --rd-secondary: #1D3557;
        --rd-accent: #457B9D;
        --rd-success: #22C55E;
        --rd-warning: #F59E0B;
        --rd-danger: #EF4444;
        --rd-bg: linear-gradient(135deg, #F7FAFC 0%, #EDF2F7 100%);
        --rd-card: #FFFFFF;
        --rd-border: #E2E8F0;
        --rd-text: #2D3748;
        --rd-muted: #718096;
        --rd-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        --rd-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    }

    body {
        font-family: 'Plus Jakarta Sans', 'Inter', var(--font-family);
        background: var(--rd-bg);
    }

    /* Header Moderno */
    .rd-header {
        background: linear-gradient(135deg, var(--rd-secondary) 0%, var(--rd-accent) 100%);
        padding: 1.5rem 2rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .rd-header-info h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .rd-header-info p {
        margin: 0.25rem 0 0;
        opacity: 0.85;
        font-size: 0.9rem;
    }

    /* KPIs en el Header */
    .rd-kpi-row {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .rd-kpi-box {
        text-align: center;
        background: rgba(255,255,255,0.15);
        padding: 0.6rem 1rem;
        border-radius: 8px;
        backdrop-filter: blur(10px);
        min-width: 80px;
    }

    .rd-kpi-box .number {
        font-size: 1.35rem;
        font-weight: 800;
        display: block;
    }

    .rd-kpi-box .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        opacity: 0.8;
        letter-spacing: 0.5px;
    }

    /* Filtros Horizontales */
    .rd-filters {
        background: var(--rd-card);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .rd-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .rd-filter-group label {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--rd-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .rd-filter-group input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--rd-border);
        border-radius: 6px;
        font-size: 0.85rem;
        background: #fff;
    }

    .rd-filter-group input:focus {
        outline: none;
        border-color: var(--rd-primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .loading-indicator {
        display: none;
        color: var(--rd-primary);
        font-weight: 600;
        font-size: 0.85rem;
    }
    .loading-indicator.active { display: flex; align-items: center; gap: 0.5rem; }

    /* Secciones */
    .rd-section {
        margin-bottom: 1.5rem;
    }

    .rd-section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--rd-border);
    }

    .rd-section-icon {
        width: 36px;
        height: 36px;
        background: var(--rd-secondary);
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .rd-section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--rd-text);
        margin: 0;
    }

    .rd-section-subtitle {
        font-size: 0.75rem;
        color: var(--rd-muted);
        margin: 0.15rem 0 0;
    }

    /* Tarjetas estilo Dashboard */
    .rd-card {
        background: linear-gradient(135deg, #fff 0%, #F7FAFC 100%);
        border-radius: 16px;
        box-shadow: var(--rd-shadow-lg);
        overflow: hidden;
        border-left: 6px solid var(--rd-primary);
        transition: all 0.3s ease;
    }

    .rd-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }

    .rd-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--rd-border);
        background: rgba(247, 250, 252, 0.5);
    }

    .rd-card-header i { color: var(--rd-primary); font-size: 1.1rem; }
    .rd-card-header h3 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--rd-text); }
    
    .rd-card-body { padding: 1.5rem; }
    .chart-container { height: 320px; position: relative; }
    .chart-container.tall { height: 360px; }

    /* Grid de Charts */
    .rd-chart-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }

    .rd-chart-grid .full-width { grid-column: span 2; }

    /* Tablas */
    .rd-table {
        width: 100%;
        border-collapse: collapse;
    }

    .rd-table thead {
        background: var(--rd-secondary);
        color: #fff;
    }

    .rd-table thead th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .rd-table tbody td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--rd-border);
        font-size: 0.85rem;
        color: var(--rd-text);
    }

    .rd-table tbody tr:hover { background: #F8FAFC; }

    /* Badges */
    .time-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .time-badge.fast { background: rgba(34, 197, 94, 0.15); color: #16A34A; }
    .time-badge.normal { background: rgba(245, 158, 11, 0.15); color: #D97706; }
    .time-badge.slow { background: rgba(239, 68, 68, 0.15); color: #DC2626; }

    .rank-badge {
        width: 28px; height: 28px;
        display: inline-flex;
        align-items: center; justify-content: center;
        border-radius: 50%;
        font-weight: 800;
        font-size: 0.75rem;
        color: white;
    }
    .rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
    .rank-2 { background: linear-gradient(135deg, #9ca3af, #6b7280); }
    .rank-3 { background: linear-gradient(135deg, #cd7c2f, #b45309); }
    .rank-other { background: var(--rd-muted); font-size: 0.65rem; }

    .status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-ok { background: rgba(34, 197, 94, 0.15); color: #16A34A; }
    .status-warning { background: rgba(245, 158, 11, 0.15); color: #D97706; }
    .status-danger { background: rgba(239, 68, 68, 0.15); color: #DC2626; }

    /* Filas expandibles */
    .expandable-row { cursor: pointer; transition: background 0.2s; }
    .expandable-row:hover { background: #F8FAFC; }
    .expandable-row td:first-child::before {
        content: '▶';
        margin-right: 0.5rem;
        font-size: 0.6rem;
        transition: transform 0.2s;
        display: inline-block;
    }
    .expandable-row.expanded td:first-child::before { transform: rotate(90deg); }
    
    .detail-row { display: none; }
    .detail-row.show { display: table-row; }
    .detail-content {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 6px;
    }
    .client-list { display: flex; flex-direction: column; gap: 0.4rem; }
    .client-row {
        background: white;
        border: 1px solid var(--rd-border);
        border-left: 3px solid var(--rd-primary);
        padding: 0.6rem 0.9rem;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.8rem;
        transition: all 0.2s;
    }
    .client-row:hover { background: #f1f5f9; transform: translateX(3px); }
    .client-row .empresa-name { font-weight: 600; color: var(--rd-text); }
    .client-row .ticket-count {
        background: var(--rd-primary);
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.7rem;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .rd-chart-grid { grid-template-columns: 1fr; }
        .rd-chart-grid .full-width { grid-column: span 1; }
    }

    @media (max-width: 768px) {
        .rd-header {
            flex-direction: column;
            text-align: center;
        }
        .rd-kpi-row { justify-content: center; }
        .rd-filters { flex-direction: column; gap: 1rem; }
    }
</style>

<!-- HEADER CON KPIs -->
<div class="rd-header">
    <div class="rd-header-info">
        <h1><i class="fas fa-chart-line"></i> Reporte de Despacho</h1>
        <p>Análisis de rendimiento, tiempos y tendencias</p>
    </div>
    <div class="rd-kpi-row">
        <div class="rd-kpi-box">
            <span class="number" id="statDespachados">--</span>
            <span class="label">Despachados</span>
        </div>
        <div class="rd-kpi-box">
            <span class="number" id="statTiempoPromedio">--</span>
            <span class="label">Tiempo Prom.</span>
        </div>
        <div class="rd-kpi-box">
            <span class="number" id="statTotalUsuarios">--</span>
            <span class="label">Usuarios</span>
        </div>
        <div class="rd-kpi-box">
            <span class="number" id="statEnRetencion">--</span>
            <span class="label">En Retención</span>
        </div>
        <div class="rd-kpi-box">
            <span class="number" id="statSeFue">--</span>
            <span class="label">Se Fue</span>
        </div>
        <div class="rd-kpi-box">
            <span class="number" id="statMontoTotal">--</span>
            <span class="label">Monto Total</span>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="rd-filters">
    <div class="rd-filter-group">
        <label>Fecha Inicio</label>
        <input type="date" id="filtroFechaInicio" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
    </div>
    <div class="rd-filter-group">
        <label>Fecha Fin</label>
        <input type="date" id="filtroFechaFin" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="loading-indicator" id="loadingIndicator">
        <i class="fas fa-circle-notch fa-spin"></i> Cargando...
    </div>
</div>

<!-- ESTADÍSTICAS ADICIONALES -->
<div class="rd-chart-grid" style="margin-bottom: 1.5rem;">
    <div class="rd-card">
        <div class="rd-card-header">
            <i class="fas fa-hourglass-half"></i>
            <h3>Tiempo Prom. Retención</h3>
        </div>
        <div class="rd-card-body" style="text-align: center; padding: 1.5rem;">
            <p style="font-size: 2rem; font-weight: 800; color: var(--rd-danger); margin: 0;" id="statTiempoRetencion">0 min</p>
        </div>
    </div>
    <div class="rd-card">
        <div class="rd-card-header">
            <i class="fas fa-building"></i>
            <h3>Empresas Atendidas</h3>
        </div>
        <div class="rd-card-body" style="text-align: center; padding: 1.5rem;">
            <p style="font-size: 2rem; font-weight: 800; color: var(--rd-accent); margin: 0;" id="statTotalEmpresas">0</p>
        </div>
    </div>
</div>

<!-- SECCIÓN: COMPORTAMIENTO EN EL TIEMPO -->
<div class="rd-section">
    <div class="rd-section-header">
        <div class="rd-section-icon" style="background: #3b82f6;"><i class="fas fa-chart-line"></i></div>
        <div>
            <h2 class="rd-section-title">Comportamiento en el Tiempo</h2>
            <p class="rd-section-subtitle">Tendencia diaria de tickets despachados vs retenciones</p>
        </div>
    </div>

    <div class="rd-card">
        <div class="rd-card-header">
            <i class="fas fa-chart-area" style="color: #3b82f6;"></i>
            <h3>Despachos vs Retenciones por Día</h3>
        </div>
        <div class="rd-card-body">
            <div class="chart-container tall">
                <canvas id="chartTendencia"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- SECCIÓN: TENDENCIAS DE ATENCIÓN -->
<div class="rd-section">
    <div class="rd-section-header">
        <div class="rd-section-icon"><i class="fas fa-chart-bar"></i></div>
        <div>
            <h2 class="rd-section-title">Tendencias de Atención</h2>
            <p class="rd-section-subtitle">Distribución y comparativa visual del rendimiento</p>
        </div>
    </div>

    <div class="rd-chart-grid">
        <div class="rd-card">
            <div class="rd-card-header">
                <i class="fas fa-users"></i>
                <h3>Tickets Despachados por Usuario</h3>
            </div>
            <div class="rd-card-body">
                <div class="chart-container">
                    <canvas id="chartTicketsUsuario"></canvas>
                </div>
            </div>
        </div>
        <div class="rd-card">
            <div class="rd-card-header">
                <i class="fas fa-clock"></i>
                <h3>Clasificación de Tiempos</h3>
            </div>
            <div class="rd-card-body">
                <div class="chart-container">
                    <canvas id="chartTiempos"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECCIÓN: RANKING DE USUARIOS -->
<div class="rd-section">
    <div class="rd-section-header">
        <div class="rd-section-icon" style="background: #f59e0b;"><i class="fas fa-trophy"></i></div>
        <div>
            <h2 class="rd-section-title">Ranking de Usuarios</h2>
            <p class="rd-section-subtitle">Ordenados por tiempo promedio (click para ver empresas atendidas)</p>
        </div>
    </div>

    <div class="rd-card">
        <div class="table-responsive">
            <table class="rd-table" id="tablaUsuarios">
                <thead>
                    <tr>
                        <th style="width: 50px">Puesto</th>
                        <th>Usuario</th>
                        <th style="width: 90px">Tickets</th>
                        <th style="width: 110px">Tiempo Prom.</th>
                        <th style="width: 90px">Más Rápido</th>
                        <th style="width: 90px">Más Lento</th>
                        <th style="width: 100px">Empresas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="text-center text-muted py-4">Cargando datos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SECCIÓN: TOP EMPRESAS -->
<div class="rd-section">
    <div class="rd-section-header">
        <div class="rd-section-icon" style="background: #06b6d4;"><i class="fas fa-building"></i></div>
        <div>
            <h2 class="rd-section-title">Top 10 Empresas</h2>
            <p class="rd-section-subtitle">Empresas con mayor volumen de tickets despachados</p>
        </div>
    </div>

    <div class="rd-chart-grid">
        <div class="rd-card">
            <div class="rd-card-header">
                <i class="fas fa-chart-pie"></i>
                <h3>Distribución por Empresa</h3>
            </div>
            <div class="rd-card-body">
                <div class="chart-container">
                    <canvas id="chartEmpresas"></canvas>
                </div>
            </div>
        </div>
        <div class="rd-card">
            <div class="rd-card-header">
                <i class="fas fa-list-ol"></i>
                <h3>Detalle de Empresas</h3>
            </div>
            <div class="rd-card-body" style="max-height: 300px; overflow-y: auto; padding: 0;">
                <table class="rd-table" id="tablaEmpresas">
                    <thead>
                        <tr>
                            <th style="width: 40px">#</th>
                            <th>Empresa</th>
                            <th style="width: 80px">Tickets</th>
                            <th style="width: 90px">Usuarios</th>
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

<!-- SECCIÓN: MONTO POR ALMACÉN -->
<div class="rd-section">
    <div class="rd-section-header">
        <div class="rd-section-icon" style="background: #6366f1;"><i class="fas fa-warehouse"></i></div>
        <div>
            <h2 class="rd-section-title">Monto por Almacén</h2>
            <p class="rd-section-subtitle">Distribución de montos por ubicación de almacén</p>
        </div>
    </div>

    <div class="rd-card">
        <div class="rd-card-header">
            <i class="fas fa-boxes"></i>
            <h3>Detalle por Almacén</h3>
        </div>
        <div class="rd-card-body" style="padding: 0;">
            <table class="rd-table" id="tablaAlmacenes">
                <thead>
                    <tr>
                        <th style="width: 40px">#</th>
                        <th>Almacén</th>
                        <th style="width: 90px">Facturas</th>
                        <th style="width: 130px">Monto Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SECCIÓN: RETENCIONES -->
<div class="rd-section">
    <div class="rd-section-header">
        <div class="rd-section-icon" style="background: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <h2 class="rd-section-title">Análisis de Retenciones</h2>
            <p class="rd-section-subtitle">Tickets retenidos y tiempos de espera por usuario</p>
        </div>
    </div>

    <div class="rd-chart-grid">
        <div class="rd-card">
            <div class="rd-card-header">
                <i class="fas fa-chart-bar" style="color: #ef4444;"></i>
                <h3>Retenciones por Usuario</h3>
            </div>
            <div class="rd-card-body">
                <div class="chart-container">
                    <canvas id="chartRetenciones"></canvas>
                </div>
            </div>
        </div>
        <div class="rd-card">
            <div class="rd-card-header">
                <i class="fas fa-clock" style="color: #ef4444;"></i>
                <h3>Tiempos de Retención</h3>
            </div>
            <div class="rd-card-body" style="max-height: 300px; overflow-y: auto; padding: 0;">
                <table class="rd-table" id="tablaRetenciones">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th style="width: 100px">Retenciones</th>
                            <th style="width: 110px">Tiempo Prom.</th>
                            <th style="width: 90px">Estado</th>
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
                $('#statTotalEmpresas').text(data.totalEmpresas || 0);
                
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
