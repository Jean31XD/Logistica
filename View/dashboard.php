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
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 1.75rem 0.75rem 0.75rem 0.75rem;
            border: none;
            background: transparent;
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            appearance: none;
            font-weight: 700;
        }        
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
        .filter-group select { background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23a0aec0%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right .7em top 50%; background-size: .65em auto; cursor: pointer; background-color: #1a202c;}
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
        .kpi-card { background-color: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); border-left: 5px solid var(--accent-color); cursor: pointer; transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 6px 15px -3px rgba(0,0,0,0.15), 0 4px 8px -2px rgba(0,0,0,0.08); }
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
                    <li class="nav-item"><a href="#" data-view="performance">Rendimiento y Calidad</a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <h3>Filtros</h3>
                <div class="filter-form">
                    <div class="filter-group">
                        <label for="fecha_inicio">Desde:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio">
                    </div>
                    <div class="filter-group">
                        <label for="fecha_fin">Hasta:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin">
                    </div>
                    <div class="filter-group">
                        <label for="filtro_almacen">Almacén:</label>
                        <select id="filtro_almacen" name="filtro_almacen">
                            <option value="">Todos los Almacenes</option>
                        </select>
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
                    <div class="kpi-card" id="kpi-total-emitidas">
                        <h2>Total Emitidas</h2>
                        <p id="total-emitidas">--</p>
                    </div>
                    <div class="kpi-card" id="kpi-sin-estado">
                        <h2>Sin Estado Asignado</h2>
                        <p id="sin-estado">--</p>
                    </div>
                </div>
                <div class="card" style="margin-top: 1.5rem;">
                    <h2>Distribución por Estado</h2>
                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                    <table class="status-table">
                        <thead><tr><th>Estado</th><th>Total de Facturas</th></tr></thead>
                        <tbody id="statusTableBody"></tbody>
                        <tfoot><tr><td>TOTAL GENERAL</td><td id="statusTableTotal">--</td></tr></tfoot>
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
                        <button id="back-to-overview" style="background-color: #2d3748; color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 700;">&larr; Volver al Resumen</button>
                    </header>
                    <p id="details-period" style="margin-top:0; color: var(--text-secondary);">Mostrando resultados para el período seleccionado.</p>
                    <div style="overflow-x: auto;">
                        <table class="status-table">
                            <thead>
                                <tr>
                                    <th>No. Factura</th><th>Fecha Registro</th><th>Cliente</th><th>Monto</th><th>Registrado Por</th><th>Camión</th>
                                    <th>Fecha Despacho</th><th>Despachado Por</th><th>Fecha Entregado</th><th>Entregado Por</th>
                                    <th>Estado</th><th>Fecha Reversada</th><th>Reversado Por</th><th>Fecha NC</th>
                                    <th>NC Realizado Por</th><th>Motivo NC</th><th>Camión 2</th>
                                </tr>
                            </thead>
                            <tbody id="detailsTableBody"></tbody>
                        </table>
                    </div>
                    <div id="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                        <select id="details-limit" style="padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border-color);">
                            <option value="10">10 por página</option><option value="25">25 por página</option>
                            <option value="50" selected>50 por página</option><option value="100">100 por página</option>
                        </select>
                        <div>
                            <span id="page-info" style="margin-right: 1rem; color: var(--text-secondary);">Página 1 de 1 (Total: 0)</span>
                            <button id="prev-page" disabled style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; background: #fff; margin-right: 5px;">&larr; Anterior</button>
                            <button id="next-page" disabled style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; background: #fff;">Siguiente &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="view-performance" class="view-container">
                <div class="grid-layout" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 1.5rem;">
                    <div class="kpi-card" style="border-left-color: #3182ce; cursor: default;">
                        <h2>Registro &rarr; Despacho</h2>
                        <p id="perf-kpi-time-to-dispatch">-- horas</p>
                    </div>
                    <div class="kpi-card" style="border-left-color: #38a169; cursor: default;">
                        <h2>Despacho &rarr; Entrega</h2>
                        <p id="perf-kpi-dispatch-to-deliver">-- horas</p>
                    </div>
                    <div class="kpi-card" style="border-left-color: #dd6b20; cursor: default;">
                        <h2>Ciclo Total (Registro &rarr; Entrega)</h2>
                        <p id="perf-kpi-total-cycle">-- horas</p>
                    </div>
                </div>
                <div class="grid-layout">
                    <div class="card">
                        <h2>Motivos de Notas de Crédito</h2>
                        <p style="color: var(--text-secondary); margin-top: -1rem; margin-bottom: 2rem;">¿Por qué se anulan las facturas?</p>
                        <div class="chart-container" style="height: 350px;"><canvas id="ncReasonsChart"></canvas></div>
                    </div>
                    <div class="card">
                        <h2>Top 5 Camiones por Entregas</h2>
                        <p style="color: var(--text-secondary); margin-top: -1rem; margin-bottom: 2rem;">Rendimiento de la flota en el período.</p>
                        <div class="chart-container" style="height: 350px;"><canvas id="truckPerformanceChart"></canvas></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        let statusChart, trendsChart, ncReasonsChart, truckPerformanceChart;
        let currentView = 'overview';
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        const almacenFilterInput = document.getElementById('filtro_almacen');
        const loaderEl = document.getElementById('loader');
        const mainTitle = document.getElementById('main-title');
        let detailsCurrentState = ''; 
        let detailsCurrentPage = 1;
        let detailsLimit = parseInt(document.getElementById('details-limit').value);
        let detailsTotalPages = 1;
        
        const initializeCharts = () => {
            const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };
            statusChart = new Chart(document.getElementById('statusChart').getContext('2d'), { type: 'bar', data: { labels: [], datasets: [{ data: [], backgroundColor: 'rgba(229, 62, 62, 0.7)' }] }, options: chartOptions });
            trendsChart = new Chart(document.getElementById('trendsChart').getContext('2d'), { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: 'rgba(229, 62, 62, 1)', tension: 0.1, fill: false }] }, options: chartOptions });
            
            const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } };
            ncReasonsChart = new Chart(document.getElementById('ncReasonsChart').getContext('2d'), {
                type: 'doughnut',
                data: { labels: [], datasets: [{ data: [], backgroundColor: ['#e53e3e', '#dd6b20', '#d69e2e', '#38a169', '#3182ce', '#805ad5'] }] },
                options: doughnutOptions
            });
            
            const barOptions = { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } };
            truckPerformanceChart = new Chart(document.getElementById('truckPerformanceChart').getContext('2d'), {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'Total Entregas', data: [], backgroundColor: 'rgba(54, 162, 235, 0.7)' }] },
                options: barOptions
            });
        };
        
        const populateAlmacenFilter = async () => {
            try {
                const response = await fetch('api_get_data.php?view=almacenes');
                if (!response.ok) throw new Error('No se pudo cargar la lista de almacenes');
                const almacenes = await response.json();
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
                const url = `api_get_data.php?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=${view}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                const data = await response.json();
                if (data.error) throw new Error(data.error);

                if (view === 'performance') {
                    updatePerformanceView(data);
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
                trendsChart.data.datasets[0].data = data.tendenciaRegistros.map(d => d.Total);
                trendsChart.update();
            }
        };

        const formatDate = (dateStr) => {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr);
                return isNaN(date.getTime()) ? 'N/A' : date.toLocaleString('es-DO', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
            } catch (e) { return 'N/A'; }
        };
        
        // MODIFICACIÓN: Se actualiza la función para renderizar los nuevos datos y se ajusta el colspan.
        const populateDetailsTable = (facturas) => {
            const tableBody = document.getElementById('detailsTableBody');
            tableBody.innerHTML = !facturas || facturas.length === 0 ? '<tr><td colspan="17" style="text-align:center;">No se encontraron facturas.</td></tr>' : '';
            if(!facturas || facturas.length === 0) return;
            
            const currencyFormatter = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' });

            facturas.forEach(f => {
                const row = tableBody.insertRow();
                row.insertCell().textContent = f.No_Factura || 'N/A';
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
    
        const updatePaginationControls = ({ currentPage, totalPages, totalRecords }) => {
            document.getElementById('page-info').textContent = `Página ${currentPage} de ${totalPages} (Total: ${totalRecords})`;
            document.getElementById('prev-page').disabled = currentPage <= 1;
            document.getElementById('next-page').disabled = currentPage >= totalPages;
        };

        // MODIFICACIÓN: Se ajusta el colspan para los mensajes de carga y error.
        const fetchDetails = async (estado, inicio, fin, almacen, page, limit) => {
            detailsCurrentState = estado;
            loaderEl.classList.add('loading');
            const detailsTableBody = document.getElementById('detailsTableBody');
            detailsTableBody.innerHTML = '<tr><td colspan="17" style="text-align:center;">Cargando...</td></tr>';
            try {
                const url = `api_get_data.php?view=details&estado=${encodeURIComponent(estado)}&fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&page=${page}&limit=${limit}`;
                const response = await fetch(url);
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
            const almacen = almacenFilterInput.value; 
            const displayTitle = (estado === 'ALL') ? 'TOTAL DE FACTURAS EMITIDAS' : `Detalle de Facturas: ${estado}`;
            document.getElementById('details-title').textContent = displayTitle;
            document.getElementById('details-period').innerHTML = `Mostrando resultados del <strong>${inicio}</strong> al <strong>${fin}</strong>.`;
            detailsCurrentPage = 1; 
            fetchDetails(estado, inicio, fin, almacen, detailsCurrentPage, detailsLimit);
        };
        
        const applyFiltersAndFetchData = () => {
            const inicio = fechaInicioInput.value;
            const fin = fechaFinInput.value;
            const almacen = almacenFilterInput.value;
            if (inicio && fin) {
                if (currentView === 'details' && detailsCurrentState) {
                    detailsCurrentPage = 1;
                    fetchDetails(detailsCurrentState, inicio, fin, almacen, detailsCurrentPage, detailsLimit);
                } else if (currentView !== 'details') {
                    fetchData(inicio, fin, almacen, currentView);
                }
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
setDateDefaults();
initializeCharts();
populateAlmacenFilter();
applyFiltersAndFetchData();
setupKpiClickEvents();

// Eventos
document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        document.querySelector('.sidebar-nav a.active')?.classList.remove('active');
        link.classList.add('active');
        document.querySelectorAll('.view-container').forEach(v => v.classList.remove('active'));
        document.getElementById(`view-${link.dataset.view}`).classList.add('active');
        currentView = link.dataset.view;
        applyFiltersAndFetchData();
    });
});

document.getElementById('back-to-overview').addEventListener('click', () => {
    document.getElementById('view-details').classList.remove('active');
    document.getElementById('view-overview').classList.add('active');
    currentView = 'overview';
    applyFiltersAndFetchData();
});

fechaInicioInput.addEventListener('change', applyFiltersAndFetchData);
fechaFinInput.addEventListener('change', applyFiltersAndFetchData);
almacenFilterInput.addEventListener('change', applyFiltersAndFetchData);

// Paginación
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

    });
</script>
</body>
</html>