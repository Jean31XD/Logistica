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
        
        /* === ESTILOS DE LOGIN === */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .login-box {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .login-logo {
            margin-bottom: 2rem;
        }
        .login-logo img {
            max-width: 150px;
            height: auto;
        }
        .login-box h2 {
            margin: 0 0 0.5rem;
            font-size: 1.8rem;
            color: var(--text-primary);
        }
        .login-box p {
            margin: 0 0 2rem;
            color: var(--text-secondary);
        }
        .pin-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        .pin-digit {
            width: 60px;
            height: 70px;
            font-size: 2rem;
            text-align: center;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.2s;
        }
        .pin-digit:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }
        .login-btn:hover {
            background-color: #c53030;
        }
        .login-btn:active {
            transform: scale(0.98);
        }
        .login-btn:disabled {
            background-color: #cbd5e0;
            cursor: not-allowed;
        }
        .error-message {
            color: #e53e3e;
            margin-top: 1rem;
            font-size: 0.9rem;
            min-height: 20px;
        }
        .hidden {
            display: none !important;
        }
        
        /* === ESTILOS DEL DASHBOARD === */
        .dashboard-layout { display: flex; height: 100%; }
        .sidebar { width: 280px; background-color: var(--sidebar-bg); padding: 2rem; display: flex; flex-direction: column; color: #fff; position: relative; }
        .user-info {
            background-color: #2d3748;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .user-info-title {
            color: #a0aec0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        .user-info-content {
            color: #fff;
            font-weight: 700;
        }
        .logout-btn {
            background-color: #742a2a;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            margin-top: auto;
            transition: background-color 0.2s;
        }
        .logout-btn:hover {
            background-color: #c53030;
        }
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
    <!-- PANTALLA DE LOGIN -->
    <div id="login-screen" class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <img src="../IMG/LOGO MC - COLOR.png" alt="Logo">
            </div>
            <h2>Acceso al Dashboard</h2>
            <p>Ingresa tu código PIN de 4 dígitos</p>
            <form id="login-form">
                <div class="pin-input-container">
                    <input type="password" maxlength="1" class="pin-digit" id="pin1" pattern="\d" inputmode="numeric">
                    <input type="password" maxlength="1" class="pin-digit" id="pin2" pattern="\d" inputmode="numeric">
                    <input type="password" maxlength="1" class="pin-digit" id="pin3" pattern="\d" inputmode="numeric">
                    <input type="password" maxlength="1" class="pin-digit" id="pin4" pattern="\d" inputmode="numeric">
                </div>
                <button type="submit" class="login-btn" id="login-btn">Ingresar</button>
                <div class="error-message" id="error-message"></div>
            </form>
        </div>
    </div>

    <!-- DASHBOARD -->
    <div id="dashboard-screen" class="dashboard-layout hidden">
        <aside class="sidebar">
            <div class="logo">
                <img src="../IMG/LOGO MC - COLOR.png" alt="Logo">
            </div>
            
            <div class="user-info">
                <div class="user-info-title">Usuario Activo</div>
                <div class="user-info-content" id="user-info-display">Cargando...</div>
            </div>
            
            <div class="sidebar-section">
                <h3>Análisis</h3>
                <ul class="sidebar-nav">
                    <li class="nav-item"><a href="#" class="active" data-view="overview">Resumen General</a></li>
                    <li class="nav-item"><a href="#" data-view="trends">Tendencias Diarias</a></li>
                    <li class="nav-item"><a href="#" data-view="performance">Rendimiento y Calidad</a></li>
                    <li class="nav-item"><a href="#" data-view="financial">Análisis Financiero</a></li>
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
                    <div class="filter-group" id="almacen-filter-container">
                        <label for="filtro_almacen">Almacén:</label>
                        <select id="filtro_almacen" name="filtro_almacen">
                            <option value="">Todos los Almacenes</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <button class="logout-btn" id="logout-btn">🚪 Cerrar Sesión</button>
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
            
            <div id="view-financial" class="view-container">
                <div class="grid-layout" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 1.5rem;">
                    <div class="kpi-card" style="border-left-color: #3182ce; cursor:default;">
                        <h2>Monto Total Emitido</h2>
                        <p id="financial-kpi-total-amount">--</p>
                    </div>
                    <div class="kpi-card" style="border-left-color: #d69e2e; cursor:default;">
                        <h2>Monto Sin Estado</h2>
                        <p id="financial-kpi-sin-estado-amount">--</p>
                    </div>
                    <div class="kpi-card" style="border-left-color: #e53e3e; cursor:default;">
                        <h2>Monto Total NC</h2>
                        <p id="financial-kpi-nc-amount">--</p>
                    </div>
                </div>
                <div class="grid-layout">
                    <div class="card">
                        <h2>Top 10 Clientes por Monto</h2>
                        <div class="chart-container" style="height: 450px;"><canvas id="topClientsChart"></canvas></div>
                    </div>
                    <div class="card">
                        <h2>Top 10 Almacenes por Monto</h2>
                        <div class="chart-container" style="height: 450px;"><canvas id="topWarehousesChart"></canvas></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
<script>
    // ===== SISTEMA DE LOGIN =====
    const loginScreen = document.getElementById('login-screen');
    const dashboardScreen = document.getElementById('dashboard-screen');
    const loginForm = document.getElementById('login-form');
    const errorMessage = document.getElementById('error-message');
    const pinInputs = [
        document.getElementById('pin1'),
        document.getElementById('pin2'),
        document.getElementById('pin3'),
        document.getElementById('pin4')
    ];
    
    let userSession = {
        almacen: null,
        esAdmin: false,
        descripcion: ''
    };

    // Auto-focus en siguiente input
    pinInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value && index < 3) {
                pinInputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                pinInputs[index - 1].focus();
            }
        });
    });

    // Focus inicial
    pinInputs[0].focus();

    // Manejo del login
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorMessage.textContent = '';
        
        const codigo = pinInputs.map(input => input.value).join('');
        
        if (codigo.length !== 4) {
            errorMessage.textContent = 'Por favor ingresa los 4 dígitos';
            return;
        }
        
        const loginBtn = document.getElementById('login-btn');
        loginBtn.disabled = true;
        loginBtn.textContent = 'Verificando...';
        
        try {
            const response = await fetch('../Logica/api_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ codigo })
            });
            
            const result = await response.json();
            
            if (result.success) {
                userSession = {
                    almacen: result.data.almacen,
                    esAdmin: result.data.es_admin,
                    descripcion: result.data.descripcion
                };
                showDashboard();
            } else {
                errorMessage.textContent = result.message || 'Código inválido';
                pinInputs.forEach(input => input.value = '');
                pinInputs[0].focus();
            }
        } catch (error) {
            errorMessage.textContent = 'Error de conexión. Intenta nuevamente.';
            console.error('Error:', error);
        } finally {
            loginBtn.disabled = false;
            loginBtn.textContent = 'Ingresar';
        }
    });

    // Logout
    document.getElementById('logout-btn').addEventListener('click', async () => {
        try {
            await fetch('../Logica/api_login.php?action=logout');
            location.reload();
        } catch (error) {
            console.error('Error al cerrar sesión:', error);
            location.reload();
        }
    });

    function showDashboard() {
        loginScreen.classList.add('hidden');
        dashboardScreen.classList.remove('hidden');
        
        // Actualizar info de usuario
        document.getElementById('user-info-display').textContent = userSession.descripcion;
        
        // Si NO es admin, configurar filtro de almacén
        const almacenFilterContainer = document.getElementById('almacen-filter-container');
        const almacenFilterInput = document.getElementById('filtro_almacen');
        
        if (!userSession.esAdmin && userSession.almacen) {
            // Ocultar filtro y forzar el almacén del usuario
            almacenFilterContainer.style.display = 'none';
            almacenFilterInput.value = userSession.almacen;
            almacenFilterInput.disabled = true;
        } else {
            // Admin puede ver todos
            almacenFilterContainer.style.display = 'block';
            almacenFilterInput.disabled = false;
        }
        
        // Inicializar dashboard
        initializeDashboard();
    }

    // ===== CÓDIGO DEL DASHBOARD (existente) =====
    function initializeDashboard() {
        let statusChart, trendsChart, ncReasonsChart, truckPerformanceChart, topClientsChart, topWarehousesChart;
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
                type: 'doughnut', data: { labels: [], datasets: [{ data: [], backgroundColor: ['#e53e3e', '#dd6b20', '#d69e2e', '#38a169', '#3182ce', '#805ad5', '#d53f8c'] }] }, options: doughnutOptions