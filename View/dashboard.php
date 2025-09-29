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
        /* --- ESTILOS GENERALES (SIN CAMBIOS) --- */
        :root {
            --sidebar-bg: #1a202c;
            --main-bg: #f7fafc;
            --card-bg: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --accent-color: #e53e3e; /* Rojo */
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--main-bg); color: var(--text-primary); }
        .dashboard-layout { display: flex; height: 100%; }
        
        /* --- BARRA LATERAL (SIDEBAR) --- */
        .sidebar { width: 280px; background-color: var(--sidebar-bg); padding: 2rem; display: flex; flex-direction: column; color: #fff; }
        .logo { margin-bottom: 2rem; text-align: center; }
        .logo img { max-width: 100%; height: auto; max-height: 100px; }
        .sidebar-section h3 { font-size: 0.9rem; margin-bottom: 1.5rem; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 0.5rem; border-bottom: 1px solid #4a5568; }

        /* --- NUEVO MENÚ DE NAVEGACIÓN --- */
        .sidebar-nav { list-style: none; padding: 0; margin: 0 0 2rem 0; }
        .nav-item a { display: block; padding: 0.9rem 1rem; color: #cbd5e0; text-decoration: none; border-radius: 8px; margin-bottom: 0.5rem; transition: background-color 0.2s, color 0.2s; }
        .nav-item a:hover { background-color: #2d3748; color: #fff; }
        .nav-item a.active { background-color: var(--accent-color); color: #fff; font-weight: 700; }

        /* --- FILTROS DE FECHA (SIN CAMBIOS) --- */
        .filter-form { display: flex; flex-direction: column; gap: 1.5rem; }
        .filter-group { position: relative; background-color: #2d3748; border-radius: 8px; border: 2px solid #4a5568; transition: border-color 0.2s ease-in-out; }
        .filter-group:focus-within { border-color: var(--accent-color); }
        .filter-group label { position: absolute; top: 8px; left: 12px; font-size: 0.75rem; color: #a0aec0; }
        .filter-group input { width: 100%; padding: 1.75rem 0.75rem 0.75rem 0.75rem; border: none; border-radius: 8px; background-color: transparent; color: #fff; font-family: inherit; font-size: 1rem; }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }

        /* --- CONTENIDO PRINCIPAL --- */
        .main-content { flex-grow: 1; padding: 2rem; overflow-y: auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        header h1 { font-size: 2.25rem; font-weight: 800; }
        .loader { font-size: 0.9rem; color: var(--text-secondary); opacity: 0; transition: opacity 0.3s ease; }
        .loader.loading { opacity: 1; }

        /* --- ESTRUCTURA DE VISTAS --- */
        .view-container { display: none; }
        .view-container.active { display: block; }
        .grid-layout { display: grid; gap: 1.5rem; }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-1 { grid-template-columns: 1fr; }
        
        .card { background-color: var(--card-bg); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow); }
        .card h2 { margin-top: 0; }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .kpi-card { background-color: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); border-left: 5px solid var(--accent-color); }
        .kpi-card h2 { margin: 0 0 0.5rem; font-size: 1rem; color: var(--text-secondary); font-weight: 500;}
        .kpi-card p { margin: 0; font-size: 2.5rem; font-weight: 800; }

        /* --- ESTILOS ADICIONALES PARA LA TABLA DE ESTADOS --- */
        .status-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.95rem; }
        .status-table th, .status-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .status-table th { background-color: #f0f4f8; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px; }
        .status-table tr:hover { background-color: #f7f9fb; }
        .status-table td:last-child { font-weight: 700; text-align: right; }
        .status-table tfoot td { font-weight: 800; background-color: #eef2f5; border-top: 2px solid var(--border-color); }
        .status-table tfoot td:last-child { color: var(--accent-color); font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="logo">
                <img src="../IMG/LOGO MC - COLOR.png" alt="Logo">
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
                <div class="grid-layout grid-cols-2">
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
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Total de Facturas</th>
                            </tr>
                        </thead>
                        <tbody id="statusTableBody">
                            </tbody>
                        <tfoot>
                            <tr>
                                <td>TOTAL GENERAL</td>
                                <td id="statusTableTotal">--</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
            </div>

      <!-- Vista User Activity -->
<div id="view-user_activity" class="view-container">
    <div class="grid-layout grid-cols-2">
        <div class="card">
            <h2>Facturas Registradas por Usuario</h2>
            <table class="status-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Total Registradas</th>
                    </tr>
                </thead>
                <tbody id="registrosTableBody"></tbody>
            </table>
        </div>
        <div class="card">
            <h2>Facturas Entregadas por Usuario</h2>
            <table class="status-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Total Entregadas</th>
                    </tr>
                </thead>
                <tbody id="entregasTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

            
            <div id="view-trends" class="view-container">
                   <div class="card">
                    <h2>Facturas Registradas por Día</h2>
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        // --- VARIABLES GLOBALES ---
        let statusChart, registrosChart, entregasChart, trendsChart;
        let currentView = 'overview'; // Vista activa por defecto
        
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        const loaderEl = document.getElementById('loader');
        const mainTitle = document.getElementById('main-title');
        const statusTableBody = document.getElementById('statusTableBody'); // Referencia al tbody
        const statusTableTotal = document.getElementById('statusTableTotal'); // Referencia al total

        // --- FUNCIONES DE GRÁFICOS ---
        
        // Genera colores aleatorios para los gráficos de pastel/dona
        function generateColors(count) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const r = Math.floor(Math.random() * 200);
                const g = Math.floor(Math.random() * 200);
                const b = Math.floor(Math.random() * 200);
                colors.push(`rgba(${r}, ${g}, ${b}, 0.8)`);
            }
            return colors;
        }

        function initializeCharts() {
            // Gráfico 1: Resumen General (Barras)
            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            statusChart = new Chart(ctxStatus, { type: 'bar', data: { labels: [], datasets: [{ data: [], backgroundColor: 'rgba(229, 62, 62, 0.7)' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });


            // Gráfico 4: Tendencias (Líneas)
            const ctxTrends = document.getElementById('trendsChart').getContext('2d');
            trendsChart = new Chart(ctxTrends, { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: 'rgba(229, 62, 62, 1)', tension: 0.1, fill: false }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
        }


        // --- LÓGICA DE DATOS Y UI ---

        async function fetchData(inicio, fin, view) {
            loaderEl.classList.add('loading');
            try {
                // Simulación de datos (Asume que 'api_get_data.php' devuelve este formato)
                const mockData = {
                    overview: {
                        totalEmitidas: 1250,
                        sinEstado: 50,
                        estadosData: [
                            { Estado: "Aprobada", Total: 700 },
                            { Estado: "Pendiente de Pago", Total: 300 },
                            { Estado: "Rechazada", Total: 100 },
                            { Estado: "En Revisión", Total: 100 },
                            { Estado: "Sin Estado", Total: 50 }, // Nota: Este debería coincidir con sinEstado
                        ]
                    },
                    user_activity: {
                        registrosPorUsuario: [
                            { Registrado_por: "Usuario A", Total: 500 },
                            { Registrado_por: "Usuario B", Total: 450 },
                            { Registrado_por: "Usuario C", Total: 300 },
                        ],
                        entregasPorUsuario: [
                            { Entregado_por: "Repartidor X", Total: 600 },
                            { Entregado_por: "Repartidor Y", Total: 400 },
                            { Entregado_por: "Repartidor Z", Total: 200 },
                        ]
                    },
                    trends: {
                        tendenciaRegistros: [
                            { Dia: "01-Sep", Total: 50 },
                            { Dia: "02-Sep", Total: 65 },
                            { Dia: "03-Sep", Total: 40 },
                            { Dia: "04-Sep", Total: 75 },
                            { Dia: "05-Sep", Total: 90 },
                            { Dia: "06-Sep", Total: 60 },
                        ]
                    }
                };

                // Si estás usando la API real, descomenta esto y comenta la simulación:
                const response = await fetch(`../Logica/api_get_data.php?fecha_inicio=${inicio}&fecha_fin=${fin}&view=${view}`);
                if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                const data = await response.json();
                
                // Si estás usando la simulación, usa:
                // const data = mockData[view]; 

                updateDashboard(data, view);

            } catch (error) {
                console.error("No se pudieron cargar los datos:", error);
            } finally {
                loaderEl.classList.remove('loading');
            }
        }

        function updateDashboard(data, view) {
            const formatter = new Intl.NumberFormat();
            switch (view) {
                case 'overview':
                    document.getElementById('total-emitidas').textContent = formatter.format(data.totalEmitidas);
                    document.getElementById('sin-estado').textContent = formatter.format(data.sinEstado);
                    
                    // Actualizar Gráfico de Estado
                    statusChart.data.labels = data.estadosData.map(d => d.Estado);
                    statusChart.data.datasets[0].data = data.estadosData.map(d => d.Total);
                    statusChart.update();
                    
                    // Llenar Tabla de Estados (NUEVO)
                    statusTableBody.innerHTML = ''; // Limpiar filas anteriores
                    let totalFacturas = 0;
                    data.estadosData.forEach(item => {
                        const row = statusTableBody.insertRow();
                        const cellEstado = row.insertCell();
                        const cellTotal = row.insertCell();
                        cellEstado.textContent = item.Estado;
                        cellTotal.textContent = formatter.format(item.Total);
                        totalFacturas += item.Total;
                    });

                    // Actualizar Total de la Tabla
                    statusTableTotal.textContent = formatter.format(totalFacturas);

                    break;
         case 'user_activity':
    // Tabla de facturas registradas por usuario
    const registrosBody = document.getElementById("registrosTableBody");
    registrosBody.innerHTML = "";
    data.registrosPorUsuario.forEach(item => {
        registrosBody.innerHTML += `
            <tr>
                <td>${item.Registrado_por}</td>
                <td>${formatter.format(item.Total)}</td>
            </tr>
        `;
    });

    // Tabla de facturas entregadas por usuario
    const entregasBody = document.getElementById("entregasTableBody");
    entregasBody.innerHTML = "";
    data.entregasPorUsuario.forEach(item => {
        entregasBody.innerHTML += `
            <tr>
                <td>${item.Entregado_por}</td>
                <td>${formatter.format(item.Total)}</td>
            </tr>
        `;
    });
    break;


                    entregasChart.data.labels = data.entregasPorUsuario.map(d => d.Entregado_por);
                    entregasChart.data.datasets[0].data = data.entregasPorUsuario.map(d => d.Total);
                    entregasChart.data.datasets[0].backgroundColor = generateColors(data.entregasPorUsuario.length);
                    entregasChart.update();
                    break;
                case 'trends':
                    trendsChart.data.labels = data.tendenciaRegistros.map(d => d.Dia);
                    trendsChart.data.datasets[0].data = data.tendenciaRegistros.map(d => d.Total);
                    trendsChart.update();
                    break;
            }
        }

        function handleDateChange() {
            const inicio = fechaInicioInput.value;
            const fin = fechaFinInput.value;
            if (inicio && fin) {
                fetchData(inicio, fin, currentView);
            }
        }

        // --- EVENT LISTENERS ---

        document.addEventListener('DOMContentLoaded', () => {
            initializeCharts();
            
            // Cargar datos del mes actual al iniciar
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            fechaInicioInput.value = firstDay;
            fechaFinInput.value = lastDay;
            fetchData(firstDay, lastDay, currentView);

            // Listeners para el cambio de fecha
            fechaInicioInput.addEventListener('change', handleDateChange);
            fechaFinInput.addEventListener('change', handleDateChange);

            // Listeners para la navegación de vistas
            document.querySelectorAll('.sidebar-nav a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();

                    // Actualizar el estado 'active' en el menú
                    document.querySelector('.sidebar-nav a.active').classList.remove('active');
                    link.classList.add('active');
                    
                    // Cambiar la vista activa
                    currentView = link.dataset.view;
                    mainTitle.textContent = link.textContent; // Actualizar el título principal
                    
                    document.querySelectorAll('.view-container').forEach(view => view.classList.remove('active'));
                    document.getElementById(`view-${currentView}`).classList.add('active');
                    
                    // Volver a cargar los datos para la nueva vista
                    handleDateChange();
                });
            });
        });
    </script>
</body>
</html>