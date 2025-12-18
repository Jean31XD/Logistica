<?php
/**
 * Módulo de Códigos de Barras - MACO Design System
 * Gestión y asignación de códigos de barras a artículos
 */

// Incluir configuración centralizada de sesión
require_once __DIR__ . '/../../conexionBD/session_config.php';

// Verificar autenticación (0=Admin, 1=Despacho, 5=Admin-limitado, 11=Códigos de Barras)
verificarAutenticacion([0, 1, 5, 11]);

$pageTitle = "Códigos de Barras | MACO";
$additionalCSS = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />';
include __DIR__ . '/../templates/header.php';
?>

<style>
    /* Estilos específicos para el módulo de códigos de barras */
    .articulos-container {
        margin-top: 1rem;
    }

    .scanner-section {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 2rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        margin-bottom: 2rem;
        text-align: center;
    }

    .scanner-section h2 {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .scanner-input {
        padding: 1rem;
        font-size: 1.2rem;
        border: 2px solid white;
        border-radius: var(--radius);
        text-align: center;
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
    }

    .scanner-input:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.3);
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        text-align: center;
    }

    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .stat-card .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-articulos {
        background: white;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .table-articulos thead {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
    }

    .table-articulos thead th {
        padding: 1rem;
        font-weight: 600;
        text-align: center;
        border: none;
    }

    .table-articulos tbody td {
        padding: 0.875rem;
        vertical-align: middle;
        text-align: center;
        border-bottom: 1px solid var(--border);
    }

    .table-articulos tbody tr {
        transition: all 0.3s ease;
    }

    .table-articulos tbody tr:hover {
        background-color: var(--bg-hover);
        transform: translateX(5px);
    }

    .table-articulos tbody tr.selected {
        background-color: rgba(0, 123, 255, 0.15);
        border-left: 4px solid var(--primary);
        font-weight: 600;
    }

    .table-articulos tbody tr.highlight {
        background-color: rgba(255, 193, 7, 0.2);
        animation: pulse 0.5s;
    }

    .btn-seleccionar {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        border-radius: var(--radius);
        transition: all 0.2s ease;
        border: 2px solid var(--primary);
        background: white;
        color: var(--primary);
        cursor: pointer;
    }

    .btn-seleccionar:hover {
        background: var(--primary);
        color: white;
    }

    .btn-seleccionar.active {
        background: var(--primary);
        color: white;
    }

    .scanner-input:disabled {
        background-color: #f0f0f0;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .scanner-status.waiting {
        background-color: rgba(108, 117, 125, 0.2);
        color: #6c757d;
    }

    .scanner-status.error {
        background-color: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        animation: shake 0.5s;
    }

    .btn-limpiar {
        margin-top: 1rem;
        padding: 0.75rem 1.5rem;
        background: white;
        color: var(--primary);
        border: 2px solid var(--primary);
        border-radius: var(--radius-lg);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-limpiar:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
    }

    .btn-limpiar:active {
        transform: translateY(0);
    }

    .scanner-controls {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        20%, 40%, 60%, 80% { transform: translateX(10px); }
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
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

    .scanner-status {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: var(--radius);
        margin-top: 1rem;
        font-weight: 600;
    }

    .scanner-status.ready {
        background-color: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .scanner-status.scanning {
        background-color: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .scanner-status.success {
        background-color: rgba(40, 167, 69, 0.2);
        color: #28a745;
        animation: fadeInOut 2s;
    }

    @keyframes fadeInOut {
        0%, 100% { opacity: 0; }
        10%, 90% { opacity: 1; }
    }

    /* Estilos para búsqueda y paginación */
    .search-container {
        background: white;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        margin-bottom: 1.5rem;
    }

    .search-box {
        position: relative;
        max-width: 500px;
        margin: 0 auto;
    }

    .search-input {
        width: 100%;
        padding: 0.875rem 3rem 0.875rem 1rem;
        border: 2px solid var(--border);
        border-radius: var(--radius-lg);
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .search-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1.2rem;
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

    .page-size-selector {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .page-size-selector select {
        padding: 0.5rem;
        border: 2px solid var(--border);
        border-radius: var(--radius);
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .pagination-container {
            flex-direction: column;
            text-align: center;
        }

        /* Ajustes para móvil */
        .scanner-section {
            padding: 1.5rem 1rem;
        }

        .scanner-section h2 {
            font-size: 1.25rem;
        }

        .scanner-input {
            font-size: 1rem;
            padding: 0.875rem;
            max-width: 100%;
        }

        .stats-container {
            grid-template-columns: 1fr;
        }

        .stat-card {
            padding: 1rem;
        }

        .search-container {
            padding: 1rem;
        }

        .search-input {
            padding: 0.75rem 2.5rem 0.75rem 0.875rem;
            font-size: 0.95rem;
        }

        .table-articulos {
            font-size: 0.875rem;
        }

        .table-articulos thead th,
        .table-articulos tbody td {
            padding: 0.625rem 0.5rem;
        }

        .btn-seleccionar {
            padding: 0.625rem 0.875rem;
            font-size: 0.8rem;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .pagination-info {
            font-size: 0.85rem;
            width: 100%;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .pagination-controls {
            width: 100%;
            justify-content: center;
        }

        .page-size-selector {
            width: 100%;
            justify-content: center;
        }

        /* Hacer la tabla scrolleable en móvil */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .maco-title {
            font-size: 1.75rem !important;
        }

        .maco-subtitle {
            font-size: 0.95rem !important;
        }
    }

    @media (max-width: 480px) {
        .scanner-section h2 {
            font-size: 1.1rem;
        }

        .scanner-input {
            font-size: 0.95rem;
            padding: 0.75rem;
        }

        .table-articulos {
            font-size: 0.8rem;
        }

        .btn-seleccionar {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
        }

        .btn-seleccionar i {
            display: none;
        }

        /* Ajustar notificación de duplicado en móvil */
        .notificacion-duplicado {
            min-width: 300px !important;
            padding: 1.5rem 1.25rem !important;
            font-size: 0.9rem !important;
        }

        /* Ajustar botón limpiar en móvil */
        .btn-limpiar {
            padding: 0.625rem 1.25rem;
            font-size: 0.9rem;
        }

        .scanner-controls {
            width: 100%;
        }

        .scanner-input {
            width: 100% !important;
        }
    }
</style>

<h1 class="maco-title maco-title-gradient">
    <i class="fas fa-barcode"></i>
    Códigos de Barras
</h1>

<p class="maco-subtitle">
    Escanea códigos de barras para asignarlos a los artículos
</p>

<!-- Sección de escaneo -->
<div class="scanner-section animate__animated animate__fadeIn">
    <h2>
        <i class="fas fa-scanner me-2"></i>
        Escáner Activo
    </h2>
    <p class="mb-3" id="scannerInstructions">Primero selecciona un artículo de la tabla</p>
    <div class="scanner-controls">
        <input
            type="text"
            id="scannerInput"
            class="scanner-input"
            placeholder="Primero selecciona un artículo..."
            autocomplete="off"
            disabled
        >
        <button type="button" id="btnLimpiar" class="btn-limpiar">
            <i class="fas fa-broom"></i>
            Limpiar Campos
        </button>
    </div>
    <div id="scannerStatus" class="scanner-status waiting">
        <i class="fas fa-hand-pointer me-2"></i>Esperando selección de artículo
    </div>
</div>

<!-- Estadísticas -->
<div class="stats-container">
    <div class="stat-card animate__animated animate__fadeIn">
        <div class="stat-number" id="totalArticulos">0</div>
        <div class="stat-label">Total de Artículos sin Código</div>
    </div>
    <div class="stat-card animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
        <div class="stat-number" id="articulosAsignados">0</div>
        <div class="stat-label">Asignados en esta sesión</div>
    </div>
    <div class="stat-card animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
        <div class="stat-number" id="articulosPaginaActual">0</div>
        <div class="stat-label">En página actual</div>
    </div>
</div>

<!-- Barra de búsqueda -->
<div class="search-container animate__animated animate__fadeIn">
    <div class="search-box">
        <input
            type="text"
            id="searchInput"
            class="search-input"
            placeholder="Buscar artículo por nombre o ID..."
            autocomplete="off"
        >
        <i class="fas fa-search search-icon"></i>
    </div>
</div>

<!-- Tabla de artículos -->
<div class="articulos-container">
    <div class="maco-card">
        <div class="table-responsive">
            <table id="tablaArticulos" class="table table-articulos mb-0">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-hand-pointer me-2"></i>Seleccionar</th>
                        <th><i class="fa-solid fa-hashtag me-2"></i>ID</th>
                        <th><i class="fa-solid fa-box me-2"></i>Nombre del Artículo</th>
                        <th><i class="fa-solid fa-barcode me-2"></i>Código Actual</th>
                    </tr>
                </thead>
                <tbody id="articulosBody">
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Cargando artículos...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Paginación -->
<div class="pagination-container" id="paginationContainer">
    <div class="pagination-info">
        Mostrando <strong id="showingFrom">0</strong> - <strong id="showingTo">0</strong> de <strong id="totalItems">0</strong> artículos
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
    <div class="page-size-selector">
        <label>Mostrar:</label>
        <select id="pageSize">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
    </div>
</div>

<?php
$usuarioSesion = htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8');
$csrfToken = generarTokenCSRF();
$additionalJS = <<<'JS'
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function () {
    const usuarioSesion = "__USUARIO_SESION__";
    const csrfToken = "__CSRF_TOKEN__";
    let articulosAsignados = 0;
    let articuloSeleccionado = null;
    let currentPage = 1;
    let pageSize = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let totalArticulosPendientes = 0;
    let scannerTimeout = null;
    let lastScanValue = '';

    // Cargar artículos sin código de barras con paginación y búsqueda
    function cargarArticulos(resetPage = false) {
        if (resetPage) {
            currentPage = 1;
        }

        const params = {
            page: currentPage,
            pageSize: pageSize,
            search: searchTerm
        };

        $.ajax({
            url: '../../Logica/obtener_articulos_sin_codigo.php',
            method: 'GET',
            data: params,
            dataType: 'json',
            timeout: 10000, // 10 segundos de timeout
            success: function(response) {
                if (response.success) {
                    // Actualizar el total de artículos pendientes
                    totalArticulosPendientes = response.pagination.totalItems;
                    $('#totalArticulos').text(totalArticulosPendientes);

                    if (response.articulos.length > 0) {
                        renderizarArticulos(response.articulos);
                        actualizarPaginacion(response.pagination);
                    } else {
                        mostrarEstadoVacio();
                        actualizarPaginacion({
                            currentPage: 1,
                            totalPages: 0,
                            totalItems: totalArticulosPendientes,
                            showingFrom: 0,
                            showingTo: 0,
                            hasPrevPage: false,
                            hasNextPage: false
                        });
                    }
                } else {
                    mostrarError();
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al cargar artículos:", {
                    status: status,
                    error: error,
                    xhr: xhr,
                    responseText: xhr.responseText
                });
                mostrarError();
            }
        });
    }

    // Mostrar error
    function mostrarError() {
        $('#articulosBody').html(`
            <tr>
                <td colspan="4" class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error al cargar los artículos</p>
                </td>
            </tr>
        `);
    }

    // Actualizar información de paginación
    function actualizarPaginacion(pagination) {
        $('#showingFrom').text(pagination.showingFrom);
        $('#showingTo').text(pagination.showingTo);
        $('#totalItems').text(pagination.totalItems);

        // Habilitar/deshabilitar botones
        $('#btnFirst, #btnPrev').prop('disabled', !pagination.hasPrevPage);
        $('#btnNext, #btnLast').prop('disabled', !pagination.hasNextPage);

        // Generar números de página
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
                cargarArticulos();
            });
            pageNumbers.append(btn);
        }
    }

    // Renderizar artículos en la tabla
    function renderizarArticulos(articulos) {
        const tbody = $('#articulosBody');
        tbody.empty();

        // Actualizar contador de artículos en página actual
        $('#articulosPaginaActual').text(articulos.length);

        articulos.forEach((articulo, index) => {
            const row = $(`
                <tr id="row_${articulo.id}" data-articulo-id="${articulo.id}" data-articulo-nombre="${articulo.Nombre}" class="animate__animated animate__fadeIn" style="animation-delay: ${index * 0.05}s;">
                    <td>
                        <button class="btn-seleccionar" data-id="${articulo.id}">
                            <i class="fas fa-hand-pointer me-1"></i>Seleccionar
                        </button>
                    </td>
                    <td>${articulo.id}</td>
                    <td style="text-align: left;">${articulo.Nombre}</td>
                    <td>${articulo.Codigo_barra || '<span style="color: #999;">Sin asignar</span>'}</td>
                </tr>
            `);
            tbody.append(row);
        });
    }

    // Mostrar estado vacío
    function mostrarEstadoVacio() {
        $('#articulosBody').html(`
            <tr>
                <td colspan="4" class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No hay artículos pendientes de asignación</p>
                    <p style="font-size: 0.875rem;">Todos los artículos tienen códigos de barras asignados</p>
                </td>
            </tr>
        `);
        $('#articulosPaginaActual').text('0');
    }

    // Manejar selección de artículo
    $(document).on('click', '.btn-seleccionar', function() {
        const articuloId = $(this).data('id');
        const row = $(`#row_${articuloId}`);
        const articuloNombre = row.data('articulo-nombre');

        // Remover selección anterior
        $('.btn-seleccionar').removeClass('active');
        $('#articulosBody tr').removeClass('selected');

        // Seleccionar nuevo artículo
        $(this).addClass('active');
        row.addClass('selected');

        // Guardar artículo seleccionado
        articuloSeleccionado = {
            id: articuloId,
            nombre: articuloNombre
        };

        // Habilitar escáner
        $('#scannerInput')
            .prop('disabled', false)
            .attr('placeholder', 'Escanea el código de barras...')
            .focus();

        $('#scannerStatus')
            .removeClass('waiting')
            .addClass('ready')
            .html('<i class="fas fa-check-circle me-2"></i>Listo para escanear');

        $('#scannerInstructions').html(`Escanea el código para: <strong>${articuloNombre}</strong>`);

        mostrarNotificacion(`Artículo seleccionado: ${articuloNombre}`, 'success');
    });

    // Función para procesar el código escaneado
    function procesarCodigoEscaneado() {
        const codigoBarra = $('#scannerInput').val().trim();

        console.log("===== PROCESAR CÓDIGO ESCANEADO =====");
        console.log("Código leído:", codigoBarra);
        console.log("======================================");

        if (!codigoBarra) {
            console.log("Código vacío, no se procesa");
            return;
        }

        if (!articuloSeleccionado) {
            console.log("No hay artículo seleccionado");
            mostrarNotificacion('Primero debes seleccionar un artículo', 'warning');
            $('#scannerInput').val('');
            return;
        }

        asignarCodigoBarra(codigoBarra);
        $('#scannerInput').val('');
    }

    // Manejar escaneo de código de barras con auto-enter mejorado
    $('#scannerInput').on('input', function(e) {
        const currentValue = $(this).val().trim();

        console.log("📝 Input detectado:", currentValue, "- Longitud:", currentValue.length);

        // Limpiar timeout anterior
        clearTimeout(scannerTimeout);

        // Si hay algún valor, establecer timeout para auto-procesar
        if (currentValue.length > 0) {
            lastScanValue = currentValue;

            // Mostrar indicador visual de que se va a procesar
            $('#scannerStatus')
                .removeClass('ready waiting error')
                .addClass('scanning')
                .html('<i class="fas fa-clock me-2"></i>Procesando en 0.3s...');

            // Auto-procesar después de 300ms sin cambios
            // Los escáneres típicamente ingresan todo el código en menos de 100ms
            // Pero dejamos 300ms para dar tiempo a escritura manual también
            scannerTimeout = setTimeout(function() {
                const valorActual = $('#scannerInput').val().trim();
                if (valorActual.length > 0) {
                    console.log("⏱️ Auto-procesando código después de 300ms:", valorActual);
                    procesarCodigoEscaneado();
                } else {
                    // Si se borró el input, volver al estado ready
                    $('#scannerStatus')
                        .removeClass('scanning')
                        .addClass('ready')
                        .html('<i class="fas fa-check-circle me-2"></i>Listo para escanear');
                }
            }, 300);
        } else {
            // Si el input está vacío, volver al estado ready
            $('#scannerStatus')
                .removeClass('scanning')
                .addClass('ready')
                .html('<i class="fas fa-check-circle me-2"></i>Listo para escanear');
        }
    });

    // También mantener soporte para Enter manual (procesar inmediatamente)
    $('#scannerInput').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            console.log("⌨️ Enter presionado manualmente");
            clearTimeout(scannerTimeout);
            procesarCodigoEscaneado();
        }
    });

    // Asignar código de barras al artículo seleccionado
    function asignarCodigoBarra(codigoBarra) {
        console.log("===== ASIGNAR CÓDIGO =====");
        console.log("Código a asignar:", codigoBarra);
        console.log("Artículo seleccionado:", articuloSeleccionado);
        console.log("Input habilitado:", !$('#scannerInput').prop('disabled'));
        console.log("==========================");

        if (!articuloSeleccionado) {
            mostrarNotificacion('Primero debes seleccionar un artículo', 'warning');
            return;
        }

        const articuloId = articuloSeleccionado.id;
        const articuloRow = $(`#row_${articuloId}`);

        // Actualizar estado del escáner
        $('#scannerStatus')
            .removeClass('ready')
            .addClass('scanning')
            .html('<i class="fas fa-spinner fa-spin me-2"></i>Procesando...');

        // Resaltar el artículo que se está procesando
        articuloRow.addClass('highlight');

        $.ajax({
            url: '../../Logica/asignar_codigo_barra.php',
            method: 'POST',
            data: {
                id: articuloId,
                codigo_barra: codigoBarra,
                usuario: usuarioSesion,
                csrf_token: csrfToken
            },
            dataType: 'json',
            timeout: 10000, // 10 segundos de timeout
            success: function(response) {
                console.log("Respuesta del servidor:", response); // Debug

                if (response && response.success) {
                    // Animar y eliminar la fila
                    articuloRow.addClass('animate__animated animate__fadeOutRight');
                    setTimeout(() => {
                        articuloRow.remove();

                        // Actualizar estadísticas
                        articulosAsignados++;
                        $('#articulosAsignados').text(articulosAsignados);

                        // Limpiar selección
                        articuloSeleccionado = null;

                        // Recargar la página actual
                        cargarArticulos();

                        // Restaurar estado del escáner a espera
                        $('#scannerInput').prop('disabled', true).attr('placeholder', 'Primero selecciona un artículo...');
                        $('#scannerStatus')
                            .removeClass('scanning success')
                            .addClass('waiting')
                            .html('<i class="fas fa-hand-pointer me-2"></i>Esperando selección de artículo');
                        $('#scannerInstructions').html('Primero selecciona un artículo de la tabla');

                        mostrarNotificacion('Código asignado correctamente', 'success');
                    }, 400);
                } else {
                    // Error al asignar
                    console.log("===== ERROR DETECTADO =====");
                    console.log("Response completo:", JSON.stringify(response, null, 2));
                    console.log("isDuplicate:", response ? response.isDuplicate : 'response is null');
                    console.log("isDuplicate type:", response ? typeof response.isDuplicate : 'N/A');
                    console.log("==========================");

                    articuloRow.removeClass('highlight');

                    const errorMsg = (response && response.message) ? response.message : 'Error al asignar el código';

                    // Si es un código duplicado, mostrar mensaje especial
                    if (response && response.isDuplicate === true) {
                        console.warn("⚠️ CÓDIGO DUPLICADO DETECTADO:", response.duplicateInfo);

                        // Limpiar input y mantenerlo habilitado
                        $('#scannerInput').val('').prop('disabled', false);

                        // Mostrar alert SIEMPRE para asegurar que se vea
                        alert('⚠️ CÓDIGO DUPLICADO\n\n' + errorMsg);

                        $('#scannerStatus')
                            .removeClass('scanning ready waiting')
                            .addClass('error')
                            .html('<i class="fas fa-exclamation-triangle me-2"></i>CÓDIGO DUPLICADO');

                        // Mostrar notificación más grande y duradera
                        mostrarNotificacionDuplicado(errorMsg, response.duplicateInfo);

                        // Enfocar el input para que esté listo
                        setTimeout(() => {
                            $('#scannerInput').focus();
                        }, 100);

                        // Volver al estado ready después de 5 segundos
                        setTimeout(() => {
                            $('#scannerStatus')
                                .removeClass('error')
                                .addClass('ready')
                                .html('<i class="fas fa-check-circle me-2"></i>Listo para escanear');
                        }, 5000);
                    } else {
                        // Otro tipo de error
                        $('#scannerInput').val('').prop('disabled', false);

                        $('#scannerStatus')
                            .removeClass('scanning')
                            .addClass('ready')
                            .html('<i class="fas fa-check-circle me-2"></i>Listo para escanear');

                        console.error("Error del servidor (no duplicado):", response);
                        alert('Error: ' + errorMsg);
                        mostrarNotificacion(errorMsg, 'error');

                        // Enfocar el input
                        setTimeout(() => {
                            $('#scannerInput').focus();
                        }, 100);
                    }
                }
            },
            error: function(xhr, status, error) {
                articuloRow.removeClass('highlight');

                // Limpiar input y asegurarse que esté habilitado
                $('#scannerInput').val('').prop('disabled', false);

                $('#scannerStatus')
                    .removeClass('scanning')
                    .addClass('ready')
                    .html('<i class="fas fa-check-circle me-2"></i>Listo para escanear');

                // Mensajes de error más específicos
                let errorMsg = 'Error de comunicación con el servidor';

                if (status === 'timeout') {
                    errorMsg = 'El servidor tardó demasiado en responder';
                } else if (status === 'abort') {
                    errorMsg = 'La solicitud fue cancelada';
                } else if (xhr.status === 404) {
                    errorMsg = 'No se encontró el archivo en el servidor';
                } else if (xhr.status === 500) {
                    errorMsg = 'Error interno del servidor';
                } else if (xhr.status === 403) {
                    errorMsg = 'Acceso denegado. Verifica tu sesión';
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.message || errorMsg;
                    } catch (e) {
                        console.error("Respuesta del servidor:", xhr.responseText);
                    }
                }

                console.error("Error AJAX:", {
                    status: status,
                    error: error,
                    xhr: xhr,
                    responseText: xhr.responseText
                });

                mostrarNotificacion(errorMsg, 'error');
            }
        });
    }

    // Mostrar notificaciones
    function mostrarNotificacion(mensaje, tipo) {
        const colores = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107'
        };

        const iconos = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle'
        };

        const notif = $(`
            <div class="notificacion" style="
                position: fixed;
                top: 100px;
                right: 20px;
                background: ${colores[tipo]};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 9999;
                animation: slideInRight 0.3s ease;
            ">
                <i class="fas fa-${iconos[tipo]} me-2"></i>${mensaje}
            </div>
        `);

        $('body').append(notif);

        setTimeout(() => {
            notif.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Mostrar notificación especial para códigos duplicados
    function mostrarNotificacionDuplicado(mensaje, duplicateInfo) {
        const notif = $(`
            <div class="notificacion-duplicado" style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #dc3545;
                color: white;
                padding: 2rem 2.5rem;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(220, 53, 69, 0.5);
                z-index: 10000;
                animation: shake 0.5s, fadeIn 0.3s;
                text-align: center;
                min-width: 400px;
                max-width: 90%;
                border: 3px solid #fff;
            ">
                <div style="font-size: 3rem; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1rem;">
                    ¡CÓDIGO DUPLICADO!
                </div>
                <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">
                    ${mensaje}
                </div>
                ${duplicateInfo ? `
                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.2); border-radius: 8px; font-size: 0.95rem;">
                        <strong>Código:</strong> ${duplicateInfo.codigo}
                    </div>
                ` : ''}
            </div>
        `);

        // Overlay oscuro
        const overlay = $(`
            <div class="notificacion-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                z-index: 9999;
                animation: fadeIn 0.3s;
            "></div>
        `);

        $('body').append(overlay);
        $('body').append(notif);

        // Cerrar al hacer clic
        overlay.on('click', function() {
            notif.fadeOut(300, function() { $(this).remove(); });
            overlay.fadeOut(300, function() { $(this).remove(); });
        });

        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            notif.fadeOut(300, function() { $(this).remove(); });
            overlay.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }

    // Mantener el foco en el input del escáner solo si está habilitado
    $(document).on('click', function(e) {
        // No hacer focus si se está haciendo clic en un botón de seleccionar
        if ($(e.target).closest('.btn-seleccionar').length > 0) {
            return;
        }

        if (!$('#scannerInput').is(':focus') && !$('#scannerInput').is(':disabled')) {
            $('#scannerInput').focus();
        }
    });

    // Event listeners para paginación
    $('#btnFirst').on('click', function() {
        currentPage = 1;
        cargarArticulos();
    });

    $('#btnPrev').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            cargarArticulos();
        }
    });

    $('#btnNext').on('click', function() {
        currentPage++;
        cargarArticulos();
    });

    $('#btnLast').on('click', function() {
        // Se calculará en base al total
        const totalPages = Math.ceil(parseInt($('#totalItems').text()) / pageSize);
        currentPage = totalPages;
        cargarArticulos();
    });

    // Cambiar tamaño de página
    $('#pageSize').on('change', function() {
        pageSize = parseInt($(this).val());
        cargarArticulos(true);
    });

    // Búsqueda con debounce
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        searchTerm = $(this).val().trim();

        searchTimeout = setTimeout(function() {
            cargarArticulos(true);
        }, 500);
    });

    // Función para limpiar campos
    function limpiarCampos() {
        // Limpiar selección de artículo
        $('.btn-seleccionar').removeClass('active');
        $('#articulosBody tr').removeClass('selected');
        articuloSeleccionado = null;

        // Limpiar y deshabilitar input del escáner
        $('#scannerInput')
            .val('')
            .prop('disabled', true)
            .attr('placeholder', 'Primero selecciona un artículo...');

        // Resetear estado del escáner
        $('#scannerStatus')
            .removeClass('ready scanning error success')
            .addClass('waiting')
            .html('<i class="fas fa-hand-pointer me-2"></i>Esperando selección de artículo');

        // Resetear instrucciones
        $('#scannerInstructions').html('Primero selecciona un artículo de la tabla');

        // Mostrar notificación
        mostrarNotificacion('Campos limpiados correctamente', 'success');
    }

    // Botón de limpiar
    $('#btnLimpiar').on('click', function() {
        limpiarCampos();
    });

    // Cargar artículos al iniciar
    cargarArticulos();

    // Recargar artículos cada 30 segundos (solo si no hay búsqueda activa)
    setInterval(function() {
        if (searchTerm === '') {
            cargarArticulos();
        }
    }, 30000);
});
</script>
<style>
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
JS;

// Reemplazar placeholders con valores reales
$additionalJS = str_replace('__USUARIO_SESION__', $usuarioSesion, $additionalJS);
$additionalJS = str_replace('__CSRF_TOKEN__', $csrfToken, $additionalJS);

include __DIR__ . '/../templates/footer.php';
?>
