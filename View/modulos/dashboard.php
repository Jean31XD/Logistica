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
$pantalla = $_SESSION['pantalla'] ?? -1;

// Si es admin (pantalla 0), tiene acceso completo
$tienePermiso = ($pantalla == 0);
$USER_WAREHOUSE = '';
$USER_TYPE = 'admin';

if (!$tienePermiso) {
    // Verificar si tiene el módulo dashboard_general asignado
    $sql = "SELECT modulo FROM usuario_modulos WHERE usuario = ? AND modulo = 'dashboard_general' AND activo = 1";
    $stmt = sqlsrv_query($conn, $sql, [$usuario]);
    
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $tienePermiso = ($row !== null);
    }
}

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

// Mapeo de pantallas a su página principal/inicio
// Todas van a Portal.php donde se muestran los módulos asignados
$homePage = [
    0 => '../pantallas/Portal.php',
    1 => '../pantallas/Portal.php',
    2 => '../pantallas/Portal.php',
    3 => '../pantallas/Portal.php',
    4 => '../pantallas/Portal.php',
    5 => '../pantallas/Portal.php',
    6 => '../pantallas/Portal.php',
    8 => '../pantallas/Portal.php',
    9 => '../pantallas/Portal.php'
];

$homeUrl = $homePage[$pantalla] ?? '../pantallas/Portal.php';

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
    <title>Dashboard de Facturación Avanzado</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS Externo del Dashboard -->
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>/View/assets/css/dashboard.css">
    <style>
        :root {
            --sidebar-bg: #1D3557; --main-bg: #F7FAFC; --card-bg: #ffffff;
            --text-primary: #2D3748; --text-secondary: #718096; --accent-color: #E63946;
            --accent-dark: #D62839; --accent-blue: #457B9D;
            --border-color: #E2E8F0; --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--main-bg); color: var(--text-primary); }
        .dashboard-layout { display: flex; height: 100%; }
        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #1D3557 0%, #0F1F30 100%);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            color: #fff;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        .logo { margin-bottom: 2rem; text-align: center; padding-bottom: 1.5rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1); }
        .logo img { max-width: 100%; height: auto; max-height: 80px; }
        .sidebar-section { margin-bottom: 2rem; }
        .sidebar-section h3 {
            font-size: 0.75rem;
            margin-bottom: 1rem;
            color: #A0AEC0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(230, 57, 70, 0.2);
        }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-item a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: #CBD5E0;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .nav-item a:hover {
            background: rgba(230, 57, 70, 0.1);
            color: #fff;
            transform: translateX(5px);
        }
        .nav-item a.active {
            background: var(--accent-color);
            color: #fff;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.4);
        }

        /* Estilo para el enlace de cerrar sesión del dashboard */
        .nav-item a.logout-link { color: #F56565; border: 1px solid rgba(245, 101, 101, 0.3); }
        .nav-item a.logout-link:hover {
            background: rgba(230, 57, 70, 0.15);
            color: #fff;
            border-color: var(--accent-color);
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .filter-group {
            position: relative;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .filter-group:hover {
            border-color: rgba(230, 57, 70, 0.3);
            background: rgba(255, 255, 255, 0.98);
        }
        .filter-group:focus-within {
            border-color: var(--accent-color);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.2);
            transform: translateY(-2px);
        }
        .filter-group label {
            position: absolute;
            top: 10px;
            left: 14px;
            font-size: 0.7rem;
            color: #718096;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            pointer-events: none;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 2rem 1rem 0.75rem 1rem;
            background: transparent;
            border: none;
            outline: none;
            color: #2D3748;
            font-family: inherit;
            font-size: 1rem;
            appearance: none;
            font-weight: 600;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
        }
        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }
        .filter-group select {
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23E63946%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 1rem top 50%;
            background-size: 0.7em auto;
            cursor: pointer;
            padding-right: 2.5rem;
        }
        .main-content {
            flex-grow: 1;
            padding: 2.5rem;
            overflow-y: auto;
            overflow-x: hidden;
            background: linear-gradient(135deg, #F7FAFC 0%, #EDF2F7 100%);
            max-width: 100%;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 3px solid rgba(230, 57, 70, 0.1);
        }
        header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #E63946 0%, #457B9D 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .loader {
            font-size: 0.9rem;
            color: var(--accent-color);
            opacity: 0;
            transition: opacity 0.3s;
            font-weight: 600;
        }
        .loader.loading { opacity: 1; }
        .view-container { display: none; }
        .view-container.active { display: block; }
        .grid-layout {
            display: grid;
            gap: 2rem;
            grid-template-columns: repeat(2, 1fr);
            max-width: 100%;
            width: 100%;
        }
        .card {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(230, 57, 70, 0.1);
            transition: all 0.3s ease;
            max-width: 100%;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
        .card h2 { margin-top: 0; font-weight: 700; color: var(--text-primary); }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        .kpi-card {
            background: linear-gradient(135deg, #fff 0%, #F7FAFC 100%);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            border-left: 6px solid var(--accent-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -10px rgba(230, 57, 70, 0.3);
            border-left-width: 8px;
        }
        .kpi-card h2 {
            margin: 0 0 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-card p {
            margin: 0;
            font-size: 3rem;
            font-weight: 800;
            color: var(--accent-color);
        }
        .status-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        .status-table th, .status-table td { padding: 1rem; text-align: left; }
        .status-table th {
            background: #1D3557;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 3px solid var(--accent-color);
        }
        .status-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
        }
        .status-table tbody tr:hover { background-color: rgba(69, 123, 157, 0.05); }
        .status-table tbody tr:nth-child(even) { background-color: #F7FAFC; }
        .status-table tfoot td {
            font-weight: 800;
            background: #EDF2F7;
            border-top: 3px solid var(--accent-color);
            padding: 1.25rem 1rem;
            font-size: 1.1rem;
        }
        .status-table tfoot td:last-child {
            color: var(--accent-color);
            font-size: 1.5rem;
            font-weight: 800;
        }

        /* Tabla de detalles de entregas */
        .delivery-details-table {
            width: 100%;
            margin-top: 2rem;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            font-size: 0.85rem;
        }
        .delivery-details-table th {
            background: #457B9D;
            color: white;
            padding: 0.75rem;
            font-weight: 600;
            text-align: left;
            border-bottom: 2px solid var(--accent-color);
        }
        .delivery-details-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        .delivery-details-table tbody tr:hover {
            background-color: rgba(69, 123, 157, 0.08);
        }
        .delivery-details-table tbody tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        .truck-header {
            background: #E63946;
            color: white;
            padding: 1rem;
            font-weight: 700;
            font-size: 1rem;
            text-align: left;
        }
        .truck-summary {
            background: #FEF3C7;
            font-weight: 700;
            border-top: 2px solid #F59E0B;
            text-transform: uppercase;
        }

        /* Estilos para la vista mejorada de transportistas */
        .transportistas-container {
            width: 100%;
        }
        .transportista-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .transportista-card:hover {
            border-color: var(--accent-color);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.15);
        }
        .transportista-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #1D3557 0%, #457B9D 100%);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .transportista-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #E63946 0%, #D62839 100%);
            transition: left 0.4s ease;
            z-index: 0;
        }
        .transportista-header:hover::before {
            left: 0;
        }
        .transportista-header > * {
            position: relative;
            z-index: 1;
        }
        .transportista-nombre {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .transportista-nombre i {
            font-size: 1.5rem;
        }
        .transportista-stats {
            display: flex;
            gap: 1.5rem;
            color: white;
            align-items: center;
        }
        .stat-item {
            text-align: center;
            min-width: 60px;
        }
        .stat-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            opacity: 0.85;
            letter-spacing: 0.8px;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            margin-top: 0.25rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .expand-icon {
            color: white;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        .expand-icon.expanded {
            transform: rotate(180deg);
        }
        .transportista-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }
        .transportista-details.expanded {
            max-height: 2000px;
        }
        .transportista-details-content {
            padding: 1.5rem;
            background: #F7FAFC;
        }
        .no-data-message {
            text-align: center;
            color: var(--text-secondary);
            padding: 3rem;
            background: white;
            border-radius: 8px;
            border: 2px dashed var(--border-color);
        }
        .estado-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .badge-entregado {
            background-color: rgba(72, 187, 120, 0.15);
            color: #2F855A;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }
        .badge-despachado {
            background-color: rgba(237, 137, 54, 0.15);
            color: #C05621;
            border: 1px solid rgba(237, 137, 54, 0.3);
        }

        /* Métricas Resumen */
        .metricas-resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .metrica-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .metrica-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .metrica-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        .metrica-info {
            flex: 1;
        }
        .metrica-label {
            font-size: 0.75rem;
            color: #718096;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .metrica-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2D3748;
            margin-top: 0.25rem;
        }

        /* Tabla de Facturas Wrapper */
        .facturas-table-wrapper {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .table-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .table-header h3 i {
            margin-right: 0.5rem;
        }
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }
        .filter-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .filter-btn.active {
            background: white;
            color: #667eea;
            border-color: white;
        }
        .filter-btn i {
            margin-right: 0.35rem;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            color: #667eea;
        }
        .loading-spinner p {
            margin: 0;
            font-weight: 500;
        }

        /* Tabla mejorada */
        .delivery-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .delivery-details-table thead {
            background: #F7FAFC;
        }
        .delivery-details-table thead th {
            padding: 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #4A5568;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #E2E8F0;
        }
        .delivery-details-table tbody tr {
            border-bottom: 1px solid #E2E8F0;
            transition: background-color 0.2s;
        }
        .delivery-details-table tbody tr:hover {
            background-color: #F7FAFC;
        }
        .delivery-details-table tbody td {
            padding: 1rem;
            font-size: 0.9rem;
            color: #2D3748;
        }
        .delivery-details-table tbody tr[data-estado="ENTREGADO"] {
            background-color: rgba(72, 187, 120, 0.05);
        }
    </style>
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
                    <i class="fas fa-truck"></i>
                    <span>Rendimiento</span>
                </a>
                <a href="#" class="tab-item" data-view="financial">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Financiero</span>
                </a>
            </nav>
            
            <div class="content-area">

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
                    <h2>Tendencia de Facturas y Montos por Día</h2>
                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                        Visualiza la cantidad de facturas y el monto total facturado por día
                    </p>
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
                <div class="grid-layout" style="grid-template-columns: repeat(3, 1fr);">
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
                    <div class="card">
                        <h2>Pendientes VS Entregadas</h2>
                        <p style="color: var(--text-secondary); margin-top: -1rem; margin-bottom: 2rem;">Estado de facturas por camión</p>
                        <div class="chart-container" style="height: 350px;"><canvas id="deliveryComparisonChart"></canvas></div>
                    </div>
                </div>

                <!-- Vista mejorada de transportistas -->
                <div class="card truck-details-card" style="margin-top: 2rem;">
                    <div class="truck-details-header">
                        <div class="truck-details-title">
                            <h2><i class="fas fa-truck"></i> Detalle de Entregas por Camión</h2>
                            <p>Haz clic en cualquier camión para ver sus entregas detalladas</p>
                        </div>
                        <div class="truck-details-stats" id="truckStats">
                            <div class="stat-box">
                                <span class="stat-number" id="totalTrucks">0</span>
                                <span class="stat-label">Camiones</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-number" id="totalDeliveries">0</span>
                                <span class="stat-label">Entregas</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barra de búsqueda -->
                    <div class="truck-search-bar">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="truckSearchInput" placeholder="Buscar por nombre de transportista, chasis o número...">
                            <button type="button" id="clearTruckSearch" class="clear-search-btn" style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="search-results-info" id="searchResultsInfo"></div>
                    </div>

                    <!-- Cards expandibles de cada transportista -->
                    <div id="transportistasContainer" class="transportistas-container">
                        <div class="loading-placeholder">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Cargando detalles de transportistas...</p>
                        </div>
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
        
        // Ruta base para APIs (usa helper centralizado)
        const API_BASE = <?php echo json_encode(getBasePath()); ?> + '/Logica/api_get_data.php';

        let statusChart, trendsChart, ncReasonsChart, truckPerformanceChart, topClientsChart, topWarehousesChart, deliveryComparisonChart;
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
                // El 'almacen' que se pasa aquí ya está decidido (o del admin o del usuario)
                const url = `${API_BASE}?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=${view}`;
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

                if (view === 'performance') {
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

            // Renderizar tabla detallada de entregas
            renderDeliveryDetails(data.truckPerformance || []);
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
                    fetch(`../../Logica/api_get_data.php?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=delivery_details`),
                    fetch(`../../Logica/api_get_data.php?fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&view=dispatched_by_truck`)
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
                    truckLabels.push(truck);
                    despachadasData.push(dispatchedByTruck[truck] || 0);
                    entregadasData.push(entregadasByTruck[truck] || 0);
                });

                if (deliveryComparisonChart) {
                    deliveryComparisonChart.data.labels = truckLabels;
                    deliveryComparisonChart.data.datasets[0].data = despachadasData;
                    deliveryComparisonChart.data.datasets[1].data = entregadasData;
                    deliveryComparisonChart.update();
                }

                // Construir cards expandibles para cada camión
                let transportistasHTML = '<div class="transportistas-container">';

                // Solo mostrar camiones que tengan facturas asignadas
                const trucksWithInvoices = Object.keys(asignadasByTruck).filter(truck => {
                    return asignadasByTruck[truck] > 0;
                });

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

                transportistasHTML += `</div>`;
                transportistasContainer.innerHTML = transportistasHTML;

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
                        const response = await fetch(`../../Logica/api_get_data.php?view=facturas_by_truck&camion=${encodeURIComponent(camion)}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}&almacen=${almacen}`);

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

        const fetchDetails = async (estado, inicio, fin, almacen, page, limit) => {
            detailsCurrentState = estado;
            loaderEl.classList.add('loading');
            const detailsTableBody = document.getElementById('detailsTableBody');
            detailsTableBody.innerHTML = '<tr><td colspan="17" style="text-align:center;">Cargando...</td></tr>';
            try {
                // El 'almacen' que se pasa aquí ya está decidido (o del admin o del usuario)
                const url = `../../Logica/api_get_data.php?view=details&estado=${encodeURIComponent(estado)}&fecha_inicio=${inicio}&fecha_fin=${fin}&almacen=${almacen}&page=${page}&limit=${limit}`;
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

        // --- Búsqueda de camiones ---
        const truckSearchInput = document.getElementById('truckSearchInput');
        const clearTruckSearch = document.getElementById('clearTruckSearch');
        const searchResultsInfo = document.getElementById('searchResultsInfo');

        if (truckSearchInput) {
            truckSearchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase().trim();
                const transportistaCards = document.querySelectorAll('.transportista-card');
                let visibleCount = 0;
                let totalCount = transportistaCards.length;

                // Mostrar/ocultar botón clear
                clearTruckSearch.style.display = searchTerm ? 'block' : 'none';

                transportistaCards.forEach(card => {
                    const nombre = card.dataset.nombre?.toLowerCase() || '';
                    const chasis = card.dataset.chasis?.toLowerCase() || '';
                    const texto = card.textContent.toLowerCase();
                    
                    if (nombre.includes(searchTerm) || chasis.includes(searchTerm) || texto.includes(searchTerm)) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Actualizar info de resultados
                if (searchTerm) {
                    searchResultsInfo.innerHTML = `Mostrando <span class="highlight">${visibleCount}</span> de ${totalCount} camiones`;
                } else {
                    searchResultsInfo.innerHTML = '';
                }

                // Mensaje si no hay resultados
                const container = document.getElementById('transportistasContainer');
                let noResults = container.querySelector('.no-results-message');
                if (visibleCount === 0 && searchTerm) {
                    if (!noResults) {
                        noResults = document.createElement('div');
                        noResults.className = 'no-results-message';
                        noResults.innerHTML = '<i class="fas fa-search"></i><p>No se encontraron camiones con ese término</p>';
                        container.appendChild(noResults);
                    }
                    noResults.style.display = 'block';
                } else if (noResults) {
                    noResults.style.display = 'none';
                }
            });

            // Clear search
            clearTruckSearch.addEventListener('click', () => {
                truckSearchInput.value = '';
                truckSearchInput.dispatchEvent(new Event('input'));
                truckSearchInput.focus();
            });
        }
    });
</script>

</body>
</html>
