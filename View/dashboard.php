<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Facturación Avanzado</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        /* Estilos CSS (sin cambios importantes) */
        :root {
            --sidebar-bg: #1a202c; --main-bg: #f7fafc; --card-bg: #ffffff;
            --text-primary: #2d3748; --text-secondary: #718096; --accent-color: #e53e3e;
            --border-color: #e2e8f0; --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--main-bg); color: var(--text-primary); }
        .dashboard-layout { display: flex; height: 100%; }
        .sidebar { width: 280px; background-color: var(--sidebar-bg); padding: 2rem; display: flex; flex-direction: column; color: #fff; }
        .logo { margin-bottom: 2rem; text-align: center; }
        .logo img { max-width: 100%; height: auto; max-height: 100px; }
        .sidebar-section h3 { font-size: 0.9rem; margin-bottom: 1.5rem; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 0.5rem; border-bottom: 1px solid #4a5568; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0 0 2rem 0; }
        .nav-item a { display: block; padding: 0.9rem 1rem; color: #cbd5e0; text-decoration: none; border-radius: 8px; margin-bottom: 0.5rem; transition: background-color 0.2s, color 0.2s; }
        .nav-item a:hover { background-color: #2d3748; color: #fff; }
        .nav-item a.active { background-color: var(--accent-color); color: #fff; font-weight: 700; }
        .filter-form { display: flex; flex-direction: column; gap: 1.5rem; }
        .filter-group { position: relative; background-color: #2d3748; border-radius: 8px; border: 2px solid #4a5568; transition: border-color 0.2s; }
        .filter-group:focus-within { border-color: var(--accent-color); }
        .filter-group label { position: absolute; top: 8px; left: 12px; font-size: 0.75rem; color: #a0aec0; }
        .filter-group input { width: 100%; padding: 1.75rem 0.75rem 0.75rem 0.75rem; border: none; background: transparent; color: #fff; font-family: inherit; font-size: 1rem; }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
        .main-content { flex-grow: 1; padding: 2rem; overflow-y: auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        header h1 { font-size: 2.25rem; font-weight: 800; }
        .loader { font-size: 0.9rem; color: var(--text-secondary); opacity: 0; transition: opacity 0.3s; }
        .loader.loading { opacity: 1; }
        .view-container { display: none; }
        .view-container.active { display: block; }
        .grid-layout { display: grid; gap: 1.5rem; grid-template-columns: repeat(2, 1fr); }
        .card { background-color: var(--card-bg); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow); }
        .card h2 { margin-top: 0; }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .kpi-card { background-color: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); border-left: 5px solid var(--accent-color); }
        .kpi-card h2 { margin: 0 0 0.5rem; font-size: 1rem; color: var(--text-secondary); font-weight: 500;}
        .kpi-card p { margin: 0; font-size: 2.5rem; font-weight: 800; }
        .status-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.85rem; }
        .status-table th, .status-table td { padding: 8px 10px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .status-table th { background-color: #f0f4f8; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px; }
        .status-table tr:hover { background-color: #f7f9fb; }
        .status-table tfoot td { font-weight: 800; background-color: #eef2f5; border-top: 2px solid var(--border-color); }
        .status-table tfoot td:last-child { color: var(--accent-color); font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="logo">
                <img src="LOGO MC - COLOR.png" alt="Logo">
            </div>
            <div class="sidebar-section">
                <h3>Análisis</h3>
                <ul class="sidebar-nav">
                    <li class="nav-item"><a href="#" class="active" data-view="overview">Resumen General</a></li>
                    <li class="nav-item"><a href="#" data-view="trends">Tendencias Diarias</a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <h3>Filtros de Fecha</h3>
                <div class="filter-form">
                    <div class="filter-group">
                        <label for="fecha_inicio">Desde:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio">
                    </div>
                    <div class="filter-group">
                        <label for="fecha_fin">Hasta:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin">
                    </div>
                </div>
            </div>
        </aside>
        
        <main class="main-content">
            <header>
                <h1 id="main-title">Resumen de Facturas</h1>
                <div id="loader" class="loader"><span>Actualizando datos...</span></div>
            </header>

            <div id="view-overview" class="view-container active">
                <div class="grid-layout">
                    <div class="kpi-card">
                        <h2>Total Emitidas</h2>
                        <p id="total-emitidas">--</p>
                    </div>
                    <div class="kpi-card">
                        <h2>Sin Estado Asignado</h2>
                        <p id="sin-estado">--</p>
                    </div>
                </div>
                <div class="card" style="margin-top: 1.5rem;">
                    <h2>Distribución por Estado</h2>
                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Total de Facturas</th>
                            </tr>
                        </thead>
                        <tbody id="statusTableBody"></tbody>
                        <tfoot>
                            <tr>
                                <td>TOTAL GENERAL</td>
                                <td id="statusTableTotal">--</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div id="view-trends" class="view-container">
                <div class="card">
                    <h2>Facturas Registradas por Día</h2>
                    <div class="chart-container"><canvas id="trendsChart"></canvas></div>
                </div>
            </div>

            <div id="view-details" class="view-container">
                <div class="card">
                    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                        <h2 id="details-title" style="margin: 0;">Detalles</h2>
                        <button id="back-to-overview" style="background-color: #2d3748; color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 700;">
                            &larr; Volver al Resumen
                        </button>
                    </header>
                    <p id="details-period" style="margin-top:0; color: var(--text-secondary);">
                        Mostrando resultados para el período seleccionado.
                    </p>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>No. Factura</th>
                                <th>Fecha Registro</th>
                                <th>Registrado Por</th>
                                <th>Camión</th>
                                <th>Fecha Despacho</th>
                                <th>Despachado Por</th>
                                <th>Fecha Entregado</th>
                                <th>Entregado Por</th>
                                <th>Estado</th>
                                <th>Fecha Reversada</th>
                                <th>Reversado Por</th>
                                <th>Fecha NC</th>
                                <th>NC Realizado Por</th>
                                <th>Motivo NC</th>
                                <th>Camión 2</th>
                            </tr>
                        </thead>
                        <tbody id="detailsTableBody"></tbody>
                    </table>

                    <div id="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                        <select id="details-limit" style="padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border-color);">
                            <option value="10">10 por página</option>
                            <option value="25">25 por página</option>
                            <option value="50" selected>50 por página</option>
                            <option value="100">100 por página</option>
                        </select>
                        <div>
                            <span id="page-info" style="margin-right: 1rem; color: var(--text-secondary);">Página 1 de 1 (Total: 0)</span>
                            <button id="prev-page" disabled style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; background: #fff; margin-right: 5px;">&larr; Anterior</button>
                            <button id="next-page" disabled style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; background: #fff;">Siguiente &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // --- VARIABLES GLOBALES ---
        let statusChart, trendsChart;
        let currentView = 'overview';
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        const loaderEl = document.getElementById('loader');
        const mainTitle = document.getElementById('main-title');

        // --- Variables de Paginación para Detalles (NUEVAS) ---
        let detailsCurrentState = ''; 
        let detailsCurrentPage = 1;
        let detailsLimit = parseInt(document.getElementById('details-limit').value);
        let detailsTotalPages = 1;
        
        // --- FUNCIONES DE GRÁFICOS ---
        function initializeCharts() {
            const chartOptions = {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            };
            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            statusChart = new Chart(ctxStatus, { type: 'bar', data: { labels: [], datasets: [{ data: [], backgroundColor: 'rgba(229, 62, 62, 0.7)' }] }, options: chartOptions });

            const ctxTrends = document.getElementById('trendsChart').getContext('2d');
            trendsChart = new Chart(ctxTrends, { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: 'rgba(229, 62, 62, 1)', tension: 0.1, fill: false }] }, options: chartOptions });
        }

        // --- LÓGICA DE DATOS Y UI GENERAL ---
        async function fetchData(inicio, fin, view) {
            loaderEl.classList.add('loading');
            try {
                const response = await fetch(`../Logica/api_get_data.php?fecha_inicio=${inicio}&fecha_fin=${fin}&view=${view}`);
                if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                const data = await response.json();
                
                if (data.error) throw new Error(data.error);

                if (view !== 'details') {
                    updateDashboard(data, view);
                }
            } catch (error) {
                console.error("No se pudieron cargar los datos:", error);
                // Si la vista es "details", no se necesita este error aquí
                if (view !== 'details') {
                    alert('Error al cargar datos del dashboard: ' + error.message);
                }
            } finally {
                loaderEl.classList.remove('loading');
            }
        }

        function updateDashboard(data, view) {
            const formatter = new Intl.NumberFormat();
            if (view === 'overview') {
                document.getElementById('total-emitidas').textContent = formatter.format(data.totalEmitidas || 0);
                document.getElementById('sin-estado').textContent = formatter.format(data.sinEstado || 0);
                
                statusChart.data.labels = data.estadosData.map(d => d.Estado);
                statusChart.data.datasets[0].data = data.estadosData.map(d => d.Total);
                statusChart.update();

                const statusTableBody = document.getElementById('statusTableBody');
                statusTableBody.innerHTML = '';
                let totalFacturas = 0;
                const fechaInicio = fechaInicioInput.value;
                const fechaFin = fechaFinInput.value;

                data.estadosData.forEach(item => {
                    const row = statusTableBody.insertRow();
                    row.style.cursor = 'pointer';
                    row.title = `Haz clic para ver los detalles de "${item.Estado}"`;
                    row.onclick = () => showDetailsView(item.Estado, fechaInicio, fechaFin);
                    
                    row.insertCell().textContent = item.Estado;
                    row.insertCell().textContent = formatter.format(item.Total);
                    totalFacturas += item.Total;
                });
                document.getElementById('statusTableTotal').textContent = formatter.format(totalFacturas);
} else if (view === 'trends') {
    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    trendsChart.data.labels = data.tendenciaRegistros.map(d => {
        const fecha = new Date(d.Dia); // Convertir la fecha string a objeto Date
        const nombreDia = diasSemana[fecha.getDay()]; // Obtener el nombre del día en español
        return `${nombreDia} (${d.Dia})`; // Combinar día de la semana + fecha
    });

    trendsChart.data.datasets[0].data = data.tendenciaRegistros.map(d => d.Total);
    trendsChart.update();
}


        }

        // --- LÓGICA DE DETALLES Y PAGINACIÓN (MODIFICADA) ---
        
        // Función auxiliar para formatear fechas de forma segura
        const formatDate = (dateObj) => {
            // Manejo de valores nulos o no objeto
            if (!dateObj || typeof dateObj !== 'object') return 'N/A';
            
            // Si el objeto es una fecha de JS (desde 'Sin estado'), usarla directamente
            let date = dateObj;
            
            // Si viene del backend (SQLSRV), intentamos usar el formato ISO 8601
            if (dateObj.date) {
                date = new Date(dateObj.date);
            }
            
            // Comprobación final si es una fecha válida
            if (isNaN(date.getTime())) return 'N/A';

            const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            return date.toLocaleString('es-DO', options);
        };
        
        function populateDetailsTable(facturas) {
            const tableBody = document.getElementById('detailsTableBody');
            tableBody.innerHTML = '';

            if (!facturas || facturas.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="15" style="text-align:center;">No se encontraron facturas con estos criterios.</td></tr>';
                return;
            }

            facturas.forEach(factura => {
                const row = tableBody.insertRow();
                
                // Asegurar el orden de las 15 columnas
                row.insertCell().textContent = factura.No_Factura || 'N/A';
                row.insertCell().textContent = formatDate(factura.Fecha_de_Registro);
                row.insertCell().textContent = factura.Registrado_por || 'N/A';
                row.insertCell().textContent = factura.Camion || 'N/A';
                row.insertCell().textContent = formatDate(factura.Fecha_de_Despacho);
                row.insertCell().textContent = factura.Despachado_por || 'N/A';
                row.insertCell().textContent = formatDate(factura.Fecha_de_Entregado);
                row.insertCell().textContent = factura.Entregado_por || 'N/A';
                row.insertCell().textContent = factura.Estado || 'N/A';
                row.insertCell().textContent = formatDate(factura.Fecha_Reversada);
                row.insertCell().textContent = factura.Reversado_Por || 'N/A';
                row.insertCell().textContent = formatDate(factura.Fecha_de_NC);
                row.insertCell().textContent = factura.NC_Realizado_Por || 'N/A';
                row.insertCell().textContent = factura.Motivo_NC || 'N/A';
                row.insertCell().textContent = factura.Camion2 || 'N/A';
            });
        }
        
        function updatePaginationControls(paginationData) {
            const { currentPage, totalPages, totalRecords } = paginationData;
            
            document.getElementById('page-info').textContent = `Página ${currentPage} de ${totalPages} (Total: ${totalRecords})`;
            
            const prevButton = document.getElementById('prev-page');
            const nextButton = document.getElementById('next-page');
            
            prevButton.disabled = currentPage <= 1;
            nextButton.disabled = currentPage >= totalPages;
        }

        async function fetchDetails(estado, inicio, fin, page, limit) {
            detailsCurrentState = estado; // Guardar el estado actual
            
            loaderEl.classList.add('loading');
            const detailsTableBody = document.getElementById('detailsTableBody');
            detailsTableBody.innerHTML = '<tr><td colspan="15" style="text-align:center;">Cargando...</td></tr>';
            
            try {
                const estadoCodificado = encodeURIComponent(estado);
                const response = await fetch(`../Logica/api_get_data.php?view=details&estado=${estadoCodificado}&fecha_inicio=${inicio}&fecha_fin=${fin}&page=${page}&limit=${limit}`);
                
                if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                
                const result = await response.json();
                
                if (result.error) {
                     throw new Error(result.error);
                }

                // Actualizar variables de paginación
                detailsCurrentPage = result.currentPage;
                detailsLimit = result.limit;
                detailsTotalPages = result.totalPages;
                
                // Actualizar tabla y controles de paginación
                populateDetailsTable(result.data);
                updatePaginationControls(result);
                
            } catch (error) {
                console.error("No se pudieron cargar los detalles:", error);
                detailsTableBody.innerHTML = '<tr><td colspan="15" style="text-align:center; color: red;">Error al cargar los datos: ' + error.message + '</td></tr>';
                updatePaginationControls({ currentPage: 1, totalPages: 1, totalRecords: 0 });
            } finally {
                loaderEl.classList.remove('loading');
            }
        }

        async function showDetailsView(estado, inicio, fin) {
            document.querySelector('.sidebar-nav a.active')?.classList.remove('active');
            document.querySelectorAll('.view-container').forEach(view => view.classList.remove('active'));
            document.getElementById('view-details').classList.add('active');
            
            currentView = 'details'; // Importante para que handleDateChange sepa qué hacer
            
            document.getElementById('details-title').textContent = `Detalle de Facturas: ${estado}`;
            document.getElementById('details-period').innerHTML = `Mostrando resultados del <strong>${inicio}</strong> al <strong>${fin}</strong>.`;
            
            // Al mostrar la vista, siempre ir a la primera página con el límite actual
            detailsCurrentPage = 1; 
            await fetchDetails(estado, inicio, fin, detailsCurrentPage, detailsLimit);
        }

        // --- INICIALIZACIÓN Y EVENTOS ---
        document.addEventListener('DOMContentLoaded', () => {
            initializeCharts();
            
            // Establecer fechas por defecto (primer día del mes actual al último)
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            fechaInicioInput.value = firstDay;
            fechaFinInput.value = lastDay;
            fetchData(firstDay, lastDay, currentView);

            // Manejador central para cambios de fecha
            const handleDateChange = () => {
                if (fechaInicioInput.value && fechaFinInput.value) {
                    // Si la vista actual es detalles, recargar los detalles con los nuevos filtros de fecha
                    if (currentView === 'details' && detailsCurrentState) {
                        detailsCurrentPage = 1; // **Reiniciar a la página 1 al cambiar la fecha**
                        fetchDetails(detailsCurrentState, fechaInicioInput.value, fechaFinInput.value, detailsCurrentPage, detailsLimit);
                    } else {
                        // Si es 'overview' o 'trends', cargar los datos normales
                        fetchData(fechaInicioInput.value, fechaFinInput.value, currentView);
                    }
                }
            };
            fechaInicioInput.addEventListener('change', handleDateChange);
            fechaFinInput.addEventListener('change', handleDateChange);

            // Manejo de navegación de vistas
            document.querySelectorAll('.sidebar-nav a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    document.querySelector('.sidebar-nav a.active').classList.remove('active');
                    link.classList.add('active');
                    
                    currentView = link.dataset.view;
                    mainTitle.textContent = link.textContent;
                    
                    document.querySelectorAll('.view-container').forEach(view => view.classList.remove('active'));
                    document.getElementById(`view-${currentView}`).classList.add('active');
                    
                    // Solo cargar datos si la vista no es 'details'
                    if (currentView !== 'details') {
                        handleDateChange();
                    }
                });
            });

            // Botón de Volver al Resumen
            document.getElementById('back-to-overview').addEventListener('click', () => {
                document.getElementById('view-details').classList.remove('active');
                document.getElementById('view-overview').classList.add('active');
                currentView = 'overview';
                mainTitle.textContent = 'Resumen de Facturas';
                // Activar el enlace de resumen en el sidebar
                document.querySelector('.sidebar-nav a.active')?.classList.remove('active');
                document.querySelector('[data-view="overview"]').classList.add('active');
                handleDateChange(); // Opcional: Recargar el resumen por si acaso
            });

            // --- Eventos de Paginación para Detalles ---
            const currentPageFn = () => detailsCurrentPage;
            const limitFn = () => detailsLimit;
            const stateFn = () => detailsCurrentState;
            const startFn = () => fechaInicioInput.value;
            const endFn = () => fechaFinInput.value;

            document.getElementById('prev-page').addEventListener('click', async () => {
                if (currentPageFn() > 1) {
                    await fetchDetails(stateFn(), startFn(), endFn(), currentPageFn() - 1, limitFn());
                }
            });

            document.getElementById('next-page').addEventListener('click', async () => {
                if (currentPageFn() < detailsTotalPages) {
                    await fetchDetails(stateFn(), startFn(), endFn(), currentPageFn() + 1, limitFn());
                }
            });

            document.getElementById('details-limit').addEventListener('change', async (e) => {
                detailsLimit = parseInt(e.target.value);
                detailsCurrentPage = 1; // Reiniciar a la primera página con el nuevo límite
                await fetchDetails(stateFn(), startFn(), endFn(), detailsCurrentPage, detailsLimit);
            });
        });
    </script>
</body>
</html>