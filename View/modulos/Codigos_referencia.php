<?php
/**
 * Módulo de Códigos de Referencia - MACO Design System
 * Visualización de todos los códigos de barras asignados
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
if (!tieneModulo('codigos_referencia', $conn)) {
    header("Location: " . getBaseUrl() . "/View/pantallas/Portal.php?error=permisos");
    exit();
}

$pageTitle = "Códigos de Referencia | MACO";
$additionalCSS = '
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
';
include __DIR__ . '/../templates/header.php';
?>

<style>
    :root {
        --cr-primary: #E63946;
        --cr-secondary: #1D3557;
        --cr-accent: #457B9D;
        --cr-success: #22C55E;
        --cr-warning: #F59E0B;
        --cr-danger: #EF4444;
        --cr-bg: linear-gradient(135deg, #F7FAFC 0%, #EDF2F7 100%);
        --cr-card: #FFFFFF;
        --cr-border: #E2E8F0;
        --cr-text: #2D3748;
        --cr-muted: #718096;
        --cr-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        --cr-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    }

    body {
        font-family: 'Plus Jakarta Sans', 'Inter', var(--font-family);
        background: var(--cr-bg);
    }

    /* Header con KPIs en glassmorphism */
    .cr-header {
        background: linear-gradient(135deg, var(--cr-secondary) 0%, var(--cr-accent) 100%);
        padding: 1.5rem 2rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .cr-header-info h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .cr-header-info p {
        margin: 0.25rem 0 0;
        opacity: 0.85;
        font-size: 0.9rem;
    }

    .cr-kpi-row {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .cr-kpi-box {
        text-align: center;
        background: rgba(255,255,255,0.15);
        padding: 0.6rem 1.25rem;
        border-radius: 8px;
        backdrop-filter: blur(10px);
        min-width: 90px;
    }

    .cr-kpi-box .number {
        font-size: 1.5rem;
        font-weight: 800;
        display: block;
    }

    .cr-kpi-box .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        opacity: 0.8;
        letter-spacing: 0.5px;
    }

    /* Filtros horizontales */
    .cr-filters {
        background: var(--cr-card);
        padding: 1rem 1.5rem;
        border-radius: 16px;
        box-shadow: var(--cr-shadow-lg);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-end;
        gap: 1rem;
        flex-wrap: wrap;
        border-left: 6px solid var(--cr-primary);
    }

    .cr-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        flex: 1;
        min-width: 180px;
    }

    .cr-filter-group label {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--cr-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .cr-filter-group input,
    .cr-filter-group select {
        padding: 0.6rem 0.75rem;
        border: 2px solid var(--cr-border);
        border-radius: 8px;
        font-size: 0.9rem;
        background: #fff;
        transition: all 0.2s;
    }

    .cr-filter-group input:focus,
    .cr-filter-group select:focus {
        outline: none;
        border-color: var(--cr-primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .cr-btn-export {
        padding: 0.6rem 1.5rem;
        background: linear-gradient(135deg, var(--cr-success), #16a34a);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 10px rgba(34, 197, 94, 0.3);
    }

    .cr-btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(34, 197, 94, 0.4);
    }

    .loading-indicator {
        display: none;
        color: var(--cr-primary);
        font-weight: 600;
        font-size: 0.85rem;
    }
    .loading-indicator.active { display: flex; align-items: center; gap: 0.5rem; }

    /* Card de tabla */
    .cr-card {
        background: linear-gradient(135deg, #fff 0%, #F7FAFC 100%);
        border-radius: 16px;
        box-shadow: var(--cr-shadow-lg);
        overflow: hidden;
        border-left: 6px solid var(--cr-primary);
        transition: all 0.3s ease;
    }

    .cr-card:hover {
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }

    .cr-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--cr-border);
        background: rgba(247, 250, 252, 0.5);
    }

    .cr-card-header-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .cr-card-header i { color: var(--cr-primary); font-size: 1.1rem; }
    .cr-card-header h3 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--cr-text); }

    /* Tabla */
    .cr-table {
        width: 100%;
        border-collapse: collapse;
    }

    .cr-table thead {
        background: var(--cr-secondary);
        color: #fff;
    }

    .cr-table thead th {
        padding: 0.875rem 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .cr-table tbody td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid var(--cr-border);
        font-size: 0.9rem;
        color: var(--cr-text);
    }

    .cr-table tbody tr {
        transition: all 0.2s ease;
    }

    .cr-table tbody tr:hover {
        background: #F8FAFC;
    }

    /* Badges */
    .badge-assigned {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        background: rgba(34, 197, 94, 0.15);
        color: #16A34A;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-unassigned {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        background: rgba(239, 68, 68, 0.15);
        color: #DC2626;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .codigo-display {
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 1px;
        border-left: 3px solid var(--cr-primary);
    }

    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: 0.4rem;
    }

    .btn-action {
        padding: 0.4rem 0.75rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .btn-edit {
        background: rgba(59, 130, 246, 0.15);
        color: #2563EB;
    }
    .btn-edit:hover {
        background: #3b82f6;
        color: white;
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.15);
        color: #DC2626;
    }
    .btn-delete:hover {
        background: #ef4444;
        color: white;
    }

    /* Paginación */
    .cr-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--cr-border);
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-info {
        color: var(--cr-muted);
        font-size: 0.85rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.4rem;
        align-items: center;
    }

    .pagination-btn {
        padding: 0.5rem 0.875rem;
        border: 1px solid var(--cr-border);
        background: white;
        color: var(--cr-text);
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .pagination-btn:hover:not(:disabled) {
        background: var(--cr-primary);
        color: white;
        border-color: var(--cr-primary);
    }

    .pagination-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .pagination-btn.active {
        background: var(--cr-primary);
        color: white;
        border-color: var(--cr-primary);
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--cr-muted);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .cr-header {
            flex-direction: column;
            text-align: center;
        }
        .cr-kpi-row { justify-content: center; }
        .cr-filters { flex-direction: column; }
        .cr-filter-group { width: 100%; }
        .cr-pagination { flex-direction: column; }
    }
</style>
<!-- HEADER CON KPIs -->
<div class="cr-header">
    <div class="cr-header-info">
        <h1><i class="fas fa-barcode"></i> Códigos de Referencia</h1>
        <p>Visualización y gestión de códigos de barras</p>
    </div>
    <div class="cr-kpi-row">
        <div class="cr-kpi-box">
            <span class="number" id="totalRegistros">--</span>
            <span class="label">Total</span>
        </div>
        <div class="cr-kpi-box">
            <span class="number" id="totalAsignados">--</span>
            <span class="label">Asignados</span>
        </div>
        <div class="cr-kpi-box">
            <span class="number" id="totalSinAsignar">--</span>
            <span class="label">Sin Asignar</span>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="cr-filters">
    <div class="cr-filter-group">
        <label>Buscar por nombre</label>
        <input type="text" id="searchNombre" placeholder="Nombre del artículo...">
    </div>
    <div class="cr-filter-group">
        <label>Buscar por código</label>
        <input type="text" id="searchCodigo" placeholder="Código de barras...">
    </div>
    <div class="cr-filter-group" style="min-width: 130px; flex: 0.4;">
        <label>Estado</label>
        <select id="filterEstado">
            <option value="">Todos</option>
            <option value="asignado">Asignados</option>
            <option value="sin_asignar">Sin asignar</option>
        </select>
    </div>
    <div class="cr-filter-group" style="min-width: 100px; flex: 0.3;">
        <label>Por página</label>
        <select id="pageSize">
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="250">250</option>
        </select>
    </div>
    <button type="button" id="btnExportar" class="cr-btn-export">
        <i class="fas fa-file-excel"></i> Exportar
    </button>
    <div class="loading-indicator" id="loadingIndicator">
        <i class="fas fa-circle-notch fa-spin"></i> Cargando...
    </div>
</div>

<!-- TABLA DE CÓDIGOS -->
<div class="cr-card">
    <div class="cr-card-header">
        <div class="cr-card-header-left">
            <i class="fas fa-list-ol"></i>
            <h3>Listado de Códigos</h3>
        </div>
    </div>
    <div style="overflow-x: auto;">
        <table class="cr-table">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Nombre del Artículo</th>
                    <th>Código de Barras</th>
                    <th>Usuario</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 120px; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaCodigos">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--cr-muted);">
                        <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                        <p style="margin-top: 0.5rem;">Cargando datos...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="cr-pagination" id="paginationContainer">
        <div class="pagination-info">
            Mostrando <strong id="showingFrom">0</strong> - <strong id="showingTo">0</strong> de <strong id="totalItems">0</strong> registros
        </div>
        <div class="pagination-controls">
            <button class="pagination-btn" id="btnFirst" title="Primera página">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button class="pagination-btn" id="btnPrev" title="Anterior">
                <i class="fas fa-angle-left"></i>
            </button>
            <span id="pageNumbers" style="display: flex; gap: 0.4rem;"></span>
            <button class="pagination-btn" id="btnNext" title="Siguiente">
                <i class="fas fa-angle-right"></i>
            </button>
            <button class="pagination-btn" id="btnLast" title="Última página">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    </div>
</div>

<?php
$csrfToken = generarTokenCSRF();
$additionalJS = <<<'JS'
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// Variables globales
const csrfToken = "__CSRF_TOKEN__";
let currentPage = 1;
let pageSize = 25;
let searchNombre = '';
let searchCodigo = '';
let filterEstado = '';
let searchTimeout = null;
let autoRefreshInterval = null;
let lastTableHash = null;
let autoRefreshEnabled = true;

// Función global para cargar datos
function cargarDatos(resetPage = false) {
    if (resetPage) {
        currentPage = 1;
    }

    const params = {
        page: currentPage,
        pageSize: pageSize,
        searchNombre: searchNombre,
        searchCodigo: searchCodigo,
        filterEstado: filterEstado
    };

    $.ajax({
        url: '../../Logica/obtener_codigos_referencia.php',
        method: 'GET',
        data: params,
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            if (response && response.success) {
                renderizarTabla(response.datos);
                actualizarEstadisticas(response.stats);
                actualizarPaginacion(response.pagination);
            } else {
                mostrarError('Error al cargar los datos');
            }
        },
        error: function(xhr, status, error) {
            console.error("Error al cargar datos:", {status, error, xhr});
            mostrarError('Error de comunicación con el servidor');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaCodigos');
    tbody.empty();

    if (datos.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="6" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No se encontraron registros</p>
                </td>
            </tr>
        `);
        return;
    }

    datos.forEach((item, index) => {
        const tieneCodigoRow = item.Codigo_barra && item.Codigo_barra.trim() !== '';
        const badgeClass = tieneCodigoRow ? 'badge-assigned' : 'badge-unassigned';
        const badgeText = tieneCodigoRow ? 'Asignado' : 'Sin asignar';
        const codigoDisplay = tieneCodigoRow ?
            `<span class="codigo-display">${item.Codigo_barra}</span>` :
            '<span style="color: #999;">-</span>';
        const usuario = item.Usuario || '-';

        const row = $('<tr>', {
            class: 'animate__animated animate__fadeIn',
            style: 'animation-delay: ' + (index * 0.02) + 's;'
        });

        // Construir celdas usando DOM seguro (evita problemas con caracteres especiales)
        row.append($('<td>').text(item.id));
        row.append($('<td>').html($('<strong>').text(item.Nombre)));
        row.append($('<td>').html(codigoDisplay));
        row.append($('<td>').text(usuario));
        row.append($('<td>').html('<span class="' + badgeClass + '">' + badgeText + '</span>'));

        // Botones de acción con data-attributes (seguro para cualquier carácter)
        const actionDiv = $('<div>', { class: 'action-buttons' });

        const btnEdit = $('<button>', {
            class: 'btn-action btn-edit',
            'data-id': item.id,
            'data-nombre': item.Nombre,
            'data-codigo': item.Codigo_barra || ''
        }).html('<i class="fas fa-edit"></i> Editar');
        actionDiv.append(btnEdit);

        if (tieneCodigoRow) {
            const btnDelete = $('<button>', {
                class: 'btn-action btn-delete',
                'data-id': item.id,
                'data-nombre': item.Nombre,
                'data-codigo': item.Codigo_barra
            }).html('<i class="fas fa-trash-alt"></i> Eliminar');
            actionDiv.append(btnDelete);
        }

        row.append($('<td>', { style: 'text-align: center;' }).append(actionDiv));
        tbody.append(row);
    });
}

// Actualizar estadísticas
function actualizarEstadisticas(stats) {
    $('#totalRegistros').text(stats.total);
    $('#totalAsignados').text(stats.asignados);
    $('#totalSinAsignar').text(stats.sinAsignar);
}

// Actualizar paginación
function actualizarPaginacion(pagination) {
    $('#showingFrom').text(pagination.showingFrom);
    $('#showingTo').text(pagination.showingTo);
    $('#totalItems').text(pagination.totalItems);

    $('#btnFirst, #btnPrev').prop('disabled', !pagination.hasPrevPage);
    $('#btnNext, #btnLast').prop('disabled', !pagination.hasNextPage);

    generarNumerosPagina(pagination);
}

// Generar números de página
function generarNumerosPagina(pagination) {
    const pageNumbers = $('#pageNumbers');
    pageNumbers.empty();

    const maxButtons = 5;
    let startPage = Math.max(1, pagination.currentPage - 2);
    let endPage = Math.min(pagination.totalPages, startPage + maxButtons - 1);

    if (endPage - startPage < maxButtons - 1) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const btn = $(`
            <button class="pagination-btn ${i === pagination.currentPage ? 'active' : ''}" data-page="${i}">
                ${i}
            </button>
        `);
        btn.on('click', function() {
            currentPage = $(this).data('page');
            cargarDatos();
        });
        pageNumbers.append(btn);
    }
}

// Mostrar error
function mostrarError(mensaje) {
    $('#tablaCodigos').html(`
        <tr>
            <td colspan="6" class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${mensaje}</p>
            </td>
        </tr>
    `);
}

// Verificar cambios en la tabla
function verificarCambios() {
    if (!autoRefreshEnabled) {
        console.log("🔇 Auto-refresh deshabilitado");
        return;
    }

    $.ajax({
        url: '../../Logica/verificar_cambios_codigos.php',
        method: 'GET',
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response && response.success) {
                const nuevoHash = response.hash;

                console.log("🔍 Verificando cambios - Hash anterior:", lastTableHash, "- Hash nuevo:", nuevoHash);

                if (lastTableHash !== null && lastTableHash !== nuevoHash) {
                    console.log("🔄 ¡Cambios detectados! Recargando tabla...");

                    // Mostrar indicador de actualización
                    mostrarIndicadorActualizacion();

                    // Recargar la tabla
                    cargarDatos();
                }

                // Actualizar el hash
                lastTableHash = nuevoHash;
            }
        },
        error: function(xhr, status, error) {
            console.error("⚠️ Error al verificar cambios:", error);
        }
    });
}

// Mostrar indicador de actualización
function mostrarIndicadorActualizacion() {
    // Crear notificación flotante
    const notif = $(`
        <div class="auto-refresh-notification" style="
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.4);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInRight 0.3s ease;
            font-weight: 600;
        ">
            <i class="fas fa-sync fa-spin" style="font-size: 1.2rem;"></i>
            <span>Tabla actualizada automáticamente</span>
        </div>
    `);

    $('body').append(notif);

    // Eliminar después de 3 segundos
    setTimeout(() => {
        notif.fadeOut(300, () => notif.remove());
    }, 3000);
}

// Iniciar auto-refresh
function iniciarAutoRefresh() {
    console.log("🔄 Auto-refresh iniciado - verificando cada 5 segundos");

    // Verificar cambios cada 5 segundos
    autoRefreshInterval = setInterval(verificarCambios, 5000);
}

// Detener auto-refresh
function detenerAutoRefresh() {
    console.log("🛑 Auto-refresh detenido");

    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Toggle auto-refresh
function toggleAutoRefresh() {
    autoRefreshEnabled = !autoRefreshEnabled;

    const indicator = $('#autoRefreshIndicator');

    if (autoRefreshEnabled) {
        console.log("✅ Auto-refresh activado");

        // Estilo activo (verde)
        indicator.css({
            'background': 'rgba(16, 185, 129, 0.1)',
            'border-color': '#10b981',
            'color': '#10b981'
        });
        indicator.find('i').addClass('fa-spin');
        indicator.find('span').text('Auto-actualización activa');

        // Reiniciar auto-refresh
        if (!autoRefreshInterval) {
            iniciarAutoRefresh();
        }
    } else {
        console.log("❌ Auto-refresh desactivado");

        // Estilo inactivo (gris)
        indicator.css({
            'background': 'rgba(107, 114, 128, 0.1)',
            'border-color': '#6b7280',
            'color': '#6b7280'
        });
        indicator.find('i').removeClass('fa-spin');
        indicator.find('span').text('Auto-actualización pausada');

        // Detener auto-refresh
        detenerAutoRefresh();
    }
}

// Event listeners
$(document).ready(function () {

    // Event listeners de paginación
    $('#btnFirst').on('click', function() {
        currentPage = 1;
        cargarDatos();
    });

    $('#btnPrev').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            cargarDatos();
        }
    });

    $('#btnNext').on('click', function() {
        currentPage++;
        cargarDatos();
    });

    $('#btnLast').on('click', function() {
        const totalPages = Math.ceil(parseInt($('#totalItems').text()) / pageSize);
        currentPage = totalPages;
        cargarDatos();
    });

    // Cambiar tamaño de página
    $('#pageSize').on('change', function() {
        pageSize = parseInt($(this).val());
        cargarDatos(true);
    });

    // Búsqueda con debounce
    $('#searchNombre, #searchCodigo').on('input', function() {
        clearTimeout(searchTimeout);
        searchNombre = $('#searchNombre').val().trim();
        searchCodigo = $('#searchCodigo').val().trim();

        searchTimeout = setTimeout(function() {
            cargarDatos(true);
        }, 500);
    });

    // Filtro de estado
    $('#filterEstado').on('change', function() {
        filterEstado = $(this).val();
        cargarDatos(true);
    });

    // Exportar a Excel
    $('#btnExportar').on('click', function() {
        const params = new URLSearchParams({
            searchNombre: searchNombre,
            searchCodigo: searchCodigo,
            filterEstado: filterEstado,
            csrf_token: csrfToken
        });

        window.location.href = '../../Logica/exportar_codigos.php?' + params.toString();
    });

    // Event delegation para botones Edit/Delete (seguro con caracteres especiales)
    $(document).on('click', '.btn-edit', function() {
        const btn = $(this);
        editarCodigo(btn.data('id'), btn.data('nombre'), btn.data('codigo'));
    });

    $(document).on('click', '.btn-delete', function() {
        const btn = $(this);
        eliminarCodigo(btn.data('id'), btn.data('nombre'), btn.data('codigo'));
    });

});

// Cargar datos al iniciar (ejecutar después de que el DOM esté listo)
$(document).ready(function() {
    cargarDatos();

    // Iniciar auto-refresh después de cargar los datos iniciales
    setTimeout(function() {
        // Obtener hash inicial
        verificarCambios();

        // Iniciar verificación periódica
        iniciarAutoRefresh();
    }, 1000);
});

// Funciones globales para modales
// Editar código directamente
function editarCodigo(id, nombre, codigoActual) {
    const nuevoCodigo = prompt(
        `Editar código de barras\n\nArtículo: ${nombre}\nCódigo actual: ${codigoActual || '(Sin código)'}\n\nIngrese el nuevo código de barras:`,
        codigoActual || ''
    );

    // Si el usuario cancela, no hacer nada
    if (nuevoCodigo === null) {
        return;
    }

    // Validar que no esté vacío
    const codigo = nuevoCodigo.trim();
    if (!codigo) {
        alert('⚠️ El código de barras no puede estar vacío');
        return;
    }

    // Enviar petición AJAX
    $.ajax({
        url: '../../Logica/editar_codigo.php',
        method: 'POST',
        data: {
            id: id,
            codigo: codigo,
            csrf_token: '__CSRF_TOKEN__'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('✅ Código actualizado correctamente');
                // La tabla se actualizará automáticamente con el auto-refresh
            } else {
                alert('❌ Error: ' + (response.message || 'No se pudo actualizar el código'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al guardar:', {status, error, xhr});
            alert('❌ Error de comunicación con el servidor');
        }
    });
}

// Eliminar código directamente
function eliminarCodigo(id, nombre, codigo) {
    const confirmar = confirm(
        `¿Está seguro que desea eliminar el código de barras?\n\n` +
        `Artículo: ${nombre}\n` +
        `Código: ${codigo}\n\n` +
        `⚠️ Esta acción no se puede deshacer.`
    );

    // Si el usuario cancela, no hacer nada
    if (!confirmar) {
        return;
    }

    // Enviar petición AJAX
    $.ajax({
        url: '../../Logica/eliminar_codigo.php',
        method: 'POST',
        data: {
            id: id,
            csrf_token: '__CSRF_TOKEN__'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('✅ Código eliminado correctamente');
                // La tabla se actualizará automáticamente con el auto-refresh
            } else {
                alert('❌ Error: ' + (response.message || 'No se pudo eliminar el código'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al eliminar:', {status, error, xhr});
            alert('❌ Error de comunicación con el servidor');
        }
    });
}
</script>
JS;

// Reemplazar placeholders con valores reales
$additionalJS = str_replace('__CSRF_TOKEN__', $csrfToken, $additionalJS);
$additionalJS = str_replace('__SESSION_USUARIO__', $_SESSION['usuario'], $additionalJS);

include __DIR__ . '/../templates/footer.php';
?>
