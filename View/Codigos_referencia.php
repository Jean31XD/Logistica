<?php
/**
 * Módulo de Códigos de Referencia - MACO Design System
 * Visualización de todos los códigos de barras asignados
 */

// Incluir configuración centralizada de sesión
require_once __DIR__ . '/../conexionBD/session_config.php';

// Verificar autenticación (0=Admin, 5=Admin-limitado, 12=Códigos de Referencia)
verificarAutenticacion([0, 5, 12]);

$pageTitle = "Códigos de Referencia | MACO";
$additionalCSS = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />';
include __DIR__ . '/templates/header.php';
?>

<style>
    /* Estilos modernos para panel de códigos de referencia */
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

    .panel-header {
        background: white;
        padding: 2rem;
        border-radius: var(--radius-xl);
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        border-left: 5px solid var(--primary);
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .header-title-section h1 {
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary), #ff6b6b);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 0.5rem;
    }

    .header-title-section p {
        color: var(--text-secondary);
        font-size: 1.1rem;
    }

    .header-actions {
        display: flex;
        gap: 1rem;
    }

    .btn-export {
        padding: 1rem 2rem;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        border-radius: var(--radius-lg);
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        position: relative;
        overflow: hidden;
    }

    .btn-export::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s;
    }

    .btn-export:hover::before {
        left: 100%;
    }

    .btn-export:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
    }

    .btn-export:active {
        transform: translateY(-1px);
    }

    .search-filter-section {
        background: white;
        padding: 2rem;
        border-radius: var(--radius-xl);
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        border-top: 4px solid var(--primary);
    }

    .search-filter-section h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
    }

    .search-filter-section h3 i {
        color: var(--primary);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
    }

    .filter-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-input, .filter-select {
        padding: 0.75rem;
        border: 2px solid var(--border);
        border-radius: var(--radius);
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .filter-input:focus, .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .stats-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-box {
        background: white;
        padding: 2rem;
        border-radius: var(--radius-xl);
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stat-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), #ff6b6b);
    }

    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 50px rgba(0,0,0,0.15);
    }

    .stat-box .stat-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.2;
    }

    .stat-box .stat-value {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary), #ff6b6b);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 0.5rem;
    }

    .stat-box .stat-label {
        font-size: 0.95rem;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .table-container {
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .table-codigos {
        width: 100%;
        border-collapse: collapse;
    }

    .table-codigos thead {
        background: linear-gradient(135deg, #1f2937, #374151);
        color: white;
    }

    .table-codigos thead th {
        padding: 1.25rem 1rem;
        font-weight: 700;
        text-align: left;
        border: none;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.875rem;
    }

    .table-codigos tbody td {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .table-codigos tbody tr {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .table-codigos tbody tr:hover {
        background: linear-gradient(90deg, #fef2f2, #fff);
        transform: scale(1.01);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .badge-assigned {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border-radius: var(--radius-full);
        font-size: 0.875rem;
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
    }

    .badge-unassigned {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border-radius: var(--radius-full);
        font-size: 0.875rem;
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
    }

    .codigo-display {
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        padding: 0.75rem 1rem;
        border-radius: var(--radius-lg);
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 1px;
        border-left: 4px solid var(--primary);
        display: inline-block;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: var(--radius);
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-edit {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
    }

    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
    }

    .btn-delete {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
    }

    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
    }

    .pagination-container {
        background: white;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        margin-top: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-info {
        color: var(--text-secondary);
        font-size: 0.95rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .pagination-btn {
        padding: 0.5rem 1rem;
        border: 2px solid var(--primary);
        background: white;
        color: var(--primary);
        border-radius: var(--radius);
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 600;
    }

    .pagination-btn:hover:not(:disabled) {
        background: var(--primary);
        color: white;
    }

    .pagination-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        border-color: var(--border);
        color: var(--text-secondary);
    }

    .pagination-btn.active {
        background: var(--primary);
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    .loading-spinner {
        text-align: center;
        padding: 2rem;
    }

    .loading-spinner i {
        font-size: 3rem;
        color: var(--primary);
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: stretch;
        }

        .header-actions {
            width: 100%;
        }

        .btn-export {
            width: 100%;
            justify-content: center;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .stats-bar {
            grid-template-columns: 1fr;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-codigos {
            font-size: 0.875rem;
        }

        .table-codigos thead th,
        .table-codigos tbody td {
            padding: 0.75rem 0.5rem;
        }

        .pagination-container {
            flex-direction: column;
        }

        .pagination-controls {
            width: 100%;
            justify-content: center;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
</style>

<!-- Encabezado principal -->
<div class="panel-header">
    <div class="header-content">
        <div class="header-title-section">
            <h1><i class="fas fa-barcode"></i> Códigos de Referencia</h1>
            <p>Visualización completa de códigos de barras asignados</p>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="stats-bar">
    <div class="stat-box animate__animated animate__fadeIn">
        <div class="stat-value" id="totalRegistros">0</div>
        <div class="stat-label">Total de Registros</div>
    </div>
    <div class="stat-box animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
        <div class="stat-value" id="totalAsignados">0</div>
        <div class="stat-label">Códigos Asignados</div>
    </div>
    <div class="stat-box animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
        <div class="stat-value" id="totalSinAsignar">0</div>
        <div class="stat-label">Sin Asignar</div>
    </div>
</div>

<!-- Filtros y búsqueda -->
<div class="search-filter-section animate__animated animate__fadeIn">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; color: var(--text-primary);">
            <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
        </h3>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div id="autoRefreshIndicator" style="
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                background: rgba(16, 185, 129, 0.1);
                border: 2px solid #10b981;
                border-radius: var(--radius-lg);
                color: #10b981;
                font-weight: 600;
                font-size: 0.875rem;
                cursor: pointer;
                transition: all 0.3s ease;
            " onclick="toggleAutoRefresh()" title="Click para activar/desactivar actualización automática">
                <i class="fas fa-sync fa-spin" style="font-size: 1rem;"></i>
                <span>Auto-actualización activa</span>
            </div>
            <button type="button" id="btnExportar" class="btn-export">
                <i class="fas fa-file-excel"></i>
                Exportar a Excel
            </button>
        </div>
    </div>
    <div class="filter-grid">
        <div class="filter-item">
            <label class="filter-label">Buscar por nombre</label>
            <input type="text" id="searchNombre" class="filter-input" placeholder="Nombre del artículo...">
        </div>
        <div class="filter-item">
            <label class="filter-label">Buscar por código</label>
            <input type="text" id="searchCodigo" class="filter-input" placeholder="Código de barras...">
        </div>
        <div class="filter-item">
            <label class="filter-label">Estado</label>
            <select id="filterEstado" class="filter-select">
                <option value="">Todos</option>
                <option value="asignado">Con código asignado</option>
                <option value="sin_asignar">Sin código asignar</option>
            </select>
        </div>
        <div class="filter-item">
            <label class="filter-label">Registros por página</label>
            <select id="pageSize" class="filter-select">
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="250">250</option>
            </select>
        </div>
    </div>
</div>

<!-- Tabla de códigos -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table-codigos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre del Artículo</th>
                    <th>Código de Barras</th>
                    <th>Usuario Asignado</th>
                    <th>Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaCodigos">
                <tr>
                    <td colspan="6" class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Cargando datos...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginación -->
<div class="pagination-container" id="paginationContainer">
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
        <span id="pageNumbers" style="display: flex; gap: 0.5rem;"></span>
        <button class="pagination-btn" id="btnNext" title="Siguiente">
            <i class="fas fa-angle-right"></i>
        </button>
        <button class="pagination-btn" id="btnLast" title="Última página">
            <i class="fas fa-angle-double-right"></i>
        </button>
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
        url: '../Logica/obtener_codigos_referencia.php',
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

        const row = $(`
            <tr class="animate__animated animate__fadeIn" style="animation-delay: ${index * 0.02}s;">
                <td>${item.id}</td>
                <td><strong>${item.Nombre}</strong></td>
                <td>${codigoDisplay}</td>
                <td>${usuario}</td>
                <td><span class="${badgeClass}">${badgeText}</span></td>
                <td style="text-align: center;">
                    <div class="action-buttons">
                        <button class="btn-action btn-edit" onclick="editarCodigo(${item.id}, '${item.Nombre.replace(/'/g, "\\'")}', '${item.Codigo_barra || ''}')">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        ${tieneCodigoRow ? `
                            <button class="btn-action btn-delete" onclick="eliminarCodigo(${item.id}, '${item.Nombre.replace(/'/g, "\\'")}', '${item.Codigo_barra}')">
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `);
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
        url: '../Logica/verificar_cambios_codigos.php',
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

        window.location.href = '../Logica/exportar_codigos.php?' + params.toString();
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
        url: '../Logica/editar_codigo.php',
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
        url: '../Logica/eliminar_codigo.php',
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

include __DIR__ . '/templates/footer.php';
?>
