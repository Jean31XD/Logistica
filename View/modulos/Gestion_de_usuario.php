<?php
/**
 * Gestión de Usuarios - MACO Design System
 * Panel de administración de usuarios, transportistas, ventanillas y códigos
 */

require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion([0]); // Solo pantalla 0 (Admin) puede acceder
require_once __DIR__ . '/../../conexionBD/conexion.php';

// CSRF token
$csrfToken = generarTokenCSRF();

// Inicializar variables de mensajes
$mensajeTransportista = "";
$datosConsultados = null;
$mensajeCrear = $alertCrear = "";
$mensajeModificar = $alertModificar = "";
$mensajeEliminar = $alertEliminar = "";
$mensajeVentanilla = $alertVentanilla = "";
$mensajeCodigo = $alertCodigo = "";

// --- PROCESAMIENTO DE FORMULARIOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf)) {
        die("Error: Token CSRF inválido. Por favor, recargue la página e intente de nuevo.");
    }

    switch ($accion) {
        case 'crear':
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $pantalla = filter_var($_POST['pantalla'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 100]]);

            if (!$usuario || !$password || $pantalla === false) {
                $mensajeCrear = "⚠️ Todos los campos son obligatorios y válidos.";
                $alertCrear = "alert-warning";
                break;
            }

            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $usuario)) {
                $mensajeCrear = "❌ Usuario inválido (letras, números, guiones bajos, 3-20 caracteres).";
                $alertCrear = "alert-danger";
                break;
            }

            $stmtCheck = sqlsrv_prepare($conn, "SELECT usuario FROM usuarios WHERE usuario = ?", [$usuario]);
            if (!$stmtCheck || !sqlsrv_execute($stmtCheck)) {
                $mensajeCrear = "❌ Error al verificar existencia del usuario.";
                $alertCrear = "alert-danger";
            } elseif (sqlsrv_fetch($stmtCheck)) {
                $mensajeCrear = "❌ El nombre de usuario ya existe.";
                $alertCrear = "alert-danger";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = sqlsrv_prepare($conn, "INSERT INTO usuarios (usuario, password, pantalla) VALUES (?, ?, ?)", [$usuario, $hash, $pantalla]);
                if ($stmtInsert && sqlsrv_execute($stmtInsert)) {
                    $mensajeCrear = "✅ Usuario <strong>$usuario</strong> creado exitosamente.";
                    $alertCrear = "alert-success";
                } else {
                    $mensajeCrear = "❌ Error al crear el usuario en la base de datos.";
                    $alertCrear = "alert-danger";
                }
            }
            break;

        case 'modificar':
            $usuarioMod = trim($_POST['usuario_modificar'] ?? '');
            $nuevaClave = trim($_POST['password_nuevo'] ?? '');
            $pantallaNuevaInput = $_POST['pantalla_nuevo'] ?? '-1';
            $pantallaNueva = ($pantallaNuevaInput !== '-1') ? filter_var($pantallaNuevaInput, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 100]]) : false;

            if (!$usuarioMod) {
                $mensajeModificar = "⚠️ Especifique el usuario a modificar.";
                $alertModificar = "alert-warning";
                break;
            }

            $updates = [];
            $params = [];

            if ($nuevaClave) {
                $updates[] = "password = ?";
                $params[] = password_hash($nuevaClave, PASSWORD_DEFAULT);
            }

            if ($pantallaNueva !== false) {
                $updates[] = "pantalla = ?";
                $params[] = $pantallaNueva;
            }

            if (!$updates) {
                $mensajeModificar = "⚠️ No se ingresaron cambios para modificar.";
                $alertModificar = "alert-warning";
                break;
            }

            $params[] = $usuarioMod;
            $sql = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE usuario = ?";
            $stmtUpdate = sqlsrv_prepare($conn, $sql, $params);

            if ($stmtUpdate && sqlsrv_execute($stmtUpdate)) {
                if(sqlsrv_rows_affected($stmtUpdate) > 0) {
                    $mensajeModificar = "✅ Usuario <strong>$usuarioMod</strong> modificado.";
                    $alertModificar = "alert-success";
                } else {
                    $mensajeModificar = "🤷 El usuario no existe o no se aplicaron cambios.";
                    $alertModificar = "alert-info";
                }
            } else {
                $mensajeModificar = "❌ Error al ejecutar la modificación.";
                $alertModificar = "alert-danger";
            }
            break;

        case 'eliminar':
            $usuarioEliminar = trim($_POST['usuario_eliminar'] ?? '');

            if (!$usuarioEliminar) {
                $mensajeEliminar = "⚠️ Especifique el usuario a eliminar.";
                $alertEliminar = "alert-warning";
            } elseif ($usuarioEliminar === $_SESSION['usuario']) {
                $mensajeEliminar = "❌ No puede eliminar su propio usuario mientras tiene la sesión activa.";
                $alertEliminar = "alert-danger";
            } else {
                $stmtDelete = sqlsrv_prepare($conn, "DELETE FROM usuarios WHERE usuario = ?", [$usuarioEliminar]);

                if ($stmtDelete && sqlsrv_execute($stmtDelete)) {
                    if (sqlsrv_rows_affected($stmtDelete) > 0) {
                        $mensajeEliminar = "✅ Usuario <strong>$usuarioEliminar</strong> eliminado.";
                        $alertEliminar = "alert-success";
                    } else {
                        $mensajeEliminar = "❌ El usuario no existe y no pudo ser eliminado.";
                        $alertEliminar = "alert-danger";
                    }
                } else {
                    $mensajeEliminar = "❌ Error al ejecutar la eliminación.";
                    $alertEliminar = "alert-danger";
                }
            }
            break;
            
        case 'asignar_ventanilla':
            $usuario_v = trim($_POST['usuario_v']);
            $ventanilla = trim($_POST['ventanilla']);
        
            if (!empty($usuario_v) && !empty($ventanilla)) {
                $sql = "UPDATE Usuarios SET Ventanilla = ? WHERE Usuario = ?";
                $params = [$ventanilla, $usuario_v];
                $stmt = sqlsrv_prepare($conn, $sql, $params);
        
                if ($stmt && sqlsrv_execute($stmt)) {
                    if (sqlsrv_rows_affected($stmt) > 0) {
                        $mensajeVentanilla = "✅ Ventanilla <strong>'$ventanilla'</strong> asignada correctamente al usuario <strong>'$usuario_v'</strong>.";
                        $alertVentanilla = "alert-success";
                    } else {
                        $mensajeVentanilla = "🤷 El usuario no fue encontrado o ya tenía esa ventanilla asignada.";
                        $alertVentanilla = "alert-info";
                    }
                } else {
                    $mensajeVentanilla = "❌ Error al ejecutar la asignación de ventanilla.";
                    $alertVentanilla = "alert-danger";
                }
            } else {
                $mensajeVentanilla = "⚠️ Debe seleccionar un usuario y una ventanilla.";
                $alertVentanilla = "alert-warning";
            }
            break;

        case 'insertar':
        case 'actualizar':
        case 'eliminar_transportista':
            $nombre = $_POST['nombre'] ?? '';
            $cedula = $_POST['cedula'] ?? '';
            $empresa = $_POST['empresa'] ?? '';
            $rnc = $_POST['rnc'] ?? '';
            $matricula = $_POST['matricula'] ?? '';

            if (!isset($_SESSION['usuario'])) {
                die("Acceso denegado. Debe iniciar sesión.");
            }
            $usuario = $_SESSION['usuario'];

            if ($accion === 'insertar') {
                $query = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula, creado_por) VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$nombre, $cedula, $empresa, $rnc, $matricula, $usuario];
                $stmt = sqlsrv_query($conn, $query, $params);
                $mensajeTransportista = $stmt ? "✅ Transportista agregado correctamente." : "❌ Error al insertar transportista.";
            }

            if ($accion === 'actualizar') {
                $query = "UPDATE facebd SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ?, creado_por = ? WHERE Cedula = ?";
                $params = [$nombre, $empresa, $rnc, $matricula, $usuario, $cedula];
                $stmt = sqlsrv_query($conn, $query, $params);
                $mensajeTransportista = $stmt ? "✅ Transportista actualizado correctamente." : "❌ Error al actualizar transportista.";
            }

            if ($accion === 'eliminar_transportista') {
                $query = "DELETE FROM facebd WHERE Cedula = ?";
                $params = [$cedula];
                $stmt = sqlsrv_query($conn, $query, $params);
                $mensajeTransportista = $stmt ? "🗑️ Transportista eliminado correctamente." : "❌ Error al eliminar transportista.";
            }
            break;

        case 'crear_codigo':
            $codigo = trim($_POST['codigo']);
            $almacen = trim($_POST['almacen']) ?: NULL;
            $descripcion = trim($_POST['descripcion']);
            $es_admin = isset($_POST['es_admin']) ? 1 : 0;
            
            $sql = "INSERT INTO codigos_acceso (codigo, almacen, descripcion, es_admin) VALUES (?, ?, ?, ?)";
            $params = [$codigo, $almacen, $descripcion, $es_admin];
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            
            if ($stmt && sqlsrv_execute($stmt)) {
                $mensajeCodigo = "✅ Código creado exitosamente.";
                $alertCodigo = "alert-success";
            } else {
                $mensajeCodigo = "❌ Error al crear código.";
                $alertCodigo = "alert-danger";
            }
            break;
            
        case 'toggle_codigo':
            $id = $_POST['id'];
            $sql = "UPDATE codigos_acceso SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ?";
            $params = [$id];
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            
            if ($stmt && sqlsrv_execute($stmt)) {
                 $mensajeCodigo = "✅ Estado del código actualizado.";
                 $alertCodigo = "alert-info";
            } else {
                 $mensajeCodigo = "❌ Error al actualizar estado.";
                 $alertCodigo = "alert-danger";
            }
            break;
            
        case 'eliminar_codigo':
            $id = $_POST['id'];
            $codigo_valor = $_POST['codigo_valor'] ?? '';
            
            if ($codigo_valor === '0000') {
                 $mensajeCodigo = "❌ No se puede eliminar el código maestro '0000'.";
                 $alertCodigo = "alert-danger";
                 break;
            }
            
            $sql = "DELETE FROM codigos_acceso WHERE id = ?";
            $params = [$id];
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            
            if ($stmt && sqlsrv_execute($stmt)) {
                if (sqlsrv_rows_affected($stmt) > 0) {
                    $mensajeCodigo = "🗑️ Código eliminado exitosamente.";
                    $alertCodigo = "alert-success";
                } else {
                    $mensajeCodigo = "🤷 No se encontró el código a eliminar.";
                    $alertCodigo = "alert-warning";
                }
            } else {
                 $mensajeCodigo = "❌ Error al eliminar código.";
                 $alertCodigo = "alert-danger";
            }
            break;
    }
}

// --- CONSULTA DE TRANSPORTISTA (GET) ---
if (isset($_GET['cedula_consulta']) && !empty($_GET['cedula_consulta'])) {
    $cedula_a_consultar = $_GET['cedula_consulta'];
    $query = "SELECT * FROM facebd WHERE Cedula = ?";
    $params = [$cedula_a_consultar];
    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt) {
        $datosConsultados = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
    if (!$datosConsultados) {
        $mensajeTransportista = "🤷 No se encontraron datos para la cédula proporcionada.";
    }
}

// --- OBTENER DATOS PARA MOSTRAR ---
$listaUsuarios = [];
$sqlUsuarios = "SELECT Usuario, Ventanilla, pantalla FROM Usuarios ORDER BY Usuario ASC";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
if ($stmtUsuarios) {
    while ($row = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
        $listaUsuarios[] = $row;
    }
}

$listaCodigos = [];
$sqlCodigos = "SELECT * FROM codigos_acceso ORDER BY es_admin DESC, almacen ASC, codigo ASC";
$stmtCodigos = sqlsrv_query($conn, $sqlCodigos);
if ($stmtCodigos) {
    while ($row = sqlsrv_fetch_array($stmtCodigos, SQLSRV_FETCH_ASSOC)) {
        $listaCodigos[] = $row;
    }
}

$listaAlmacenes = [];
$sqlAlmacenes = "SELECT DISTINCT inventlocationid FROM inventlocation ORDER BY inventlocationid";
$stmtAlmacenes = sqlsrv_query($conn, $sqlAlmacenes);
if ($stmtAlmacenes) {
    while ($row = sqlsrv_fetch_array($stmtAlmacenes, SQLSRV_FETCH_ASSOC)) {
        $listaAlmacenes[] = $row;
    }
}

$sqlStats = "SELECT 
                COUNT(*) as total_intentos,
                SUM(CASE WHEN exito = 1 THEN 1 ELSE 0 END) as exitosos,
                SUM(CASE WHEN exito = 0 THEN 1 ELSE 0 END) as fallidos
            FROM log_accesos
            WHERE fecha_hora >= DATEADD(day, -7, GETDATE())";
$statsAcceso = sqlsrv_fetch_array(sqlsrv_query($conn, $sqlStats), SQLSRV_FETCH_ASSOC);

// Niveles de acceso
$nivelesAcceso = [
    0 => 'Administrador',
    1 => 'Despacho',
    2 => 'Validación',
    3 => 'Recepción',
    5 => 'Admin Limitado',
    6 => 'Reportes Faltantes',
    8 => 'Listo Etiquetas',
    9 => 'Dashboard',
    10 => 'Listo Inventario',
    11 => 'Códigos de Barras',
    12 => 'Códigos Referencia',
    13 => 'Gestión Imágenes'
];

// Pestaña activa
$tabActiva = $_GET['tab'] ?? 'usuarios';

$pageTitle = "Gestión de Usuarios | MACO";
$containerClass = "maco-container-fluid";
$additionalCSS = <<<'CSS'
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* === HERO SECTION === */
    .hero-gestion {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        padding: 2rem 2.5rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .hero-gestion::before {
        content: '';
        position: absolute;
        width: 400px;
        height: 400px;
        background: rgba(230, 57, 70, 0.12);
        border-radius: 50%;
        top: -150px;
        right: -150px;
    }
    .hero-gestion h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .hero-gestion p {
        opacity: 0.85;
        margin: 0.5rem 0 0;
        font-size: 1rem;
        position: relative;
        z-index: 2;
    }

    /* === LAYOUT === */
    .gestion-layout {
        display: flex;
        gap: 2rem;
        min-height: calc(100vh - 280px);
    }
    .gestion-sidebar {
        width: 260px;
        flex-shrink: 0;
    }
    .gestion-content {
        flex: 1;
        min-width: 0;
    }

    /* === NAVIGATION TABS === */
    .nav-tabs-vertical {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        background: white;
        border-radius: 20px;
        padding: 1rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    }
    .nav-tab-item {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        padding: 0.875rem 1.25rem;
        border-radius: 12px;
        color: #64748b;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.25s ease;
        border: 2px solid transparent;
    }
    .nav-tab-item:hover {
        background: #f8fafc;
        color: #1a1a2e;
        transform: translateX(4px);
    }
    .nav-tab-item.active {
        background: linear-gradient(135deg, #E63946 0%, #c1121f 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(230, 57, 70, 0.35);
    }
    .nav-tab-item i {
        font-size: 1.15rem;
        width: 22px;
        text-align: center;
    }

    /* === STATS CARDS === */
    .stats-sidebar {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-top: 1.5rem;
    }
    .stat-mini {
        background: white;
        border-radius: 14px;
        padding: 1rem;
        text-align: center;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        border-left: 4px solid;
    }
    .stat-mini.primary { border-color: #E63946; }
    .stat-mini.info { border-color: #3b82f6; }
    .stat-mini.success { border-color: #10b981; }
    .stat-mini.warning { border-color: #f59e0b; }
    .stat-mini h4 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        color: #1a1a2e;
    }
    .stat-mini span {
        font-size: 0.75rem;
        color: #64748b;
    }

    /* === TAB CONTENT === */
    .tab-content {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    }
    .tab-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }
    .tab-title i { color: #E63946; }

    /* === FORM CARDS === */
    .form-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e5e7eb;
    }
    .form-card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 1rem;
        color: #1a1a2e;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-card-title i {
        color: #E63946;
    }

    /* === TABLES === */
    .users-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .users-table th {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: white;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .users-table th:first-child { border-radius: 12px 0 0 0; }
    .users-table th:last-child { border-radius: 0 12px 0 0; }
    .users-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .users-table tbody tr {
        transition: all 0.2s;
    }
    .users-table tbody tr:hover {
        background: #f8fafc;
    }

    /* === BADGES === */
    .badge-nivel {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .badge-admin { 
        background: linear-gradient(135deg, rgba(230, 57, 70, 0.15) 0%, rgba(193, 18, 31, 0.15) 100%); 
        color: #c1121f; 
    }
    .badge-user { 
        background: rgba(59, 130, 246, 0.12); 
        color: #2563eb; 
    }
    .badge-success {
        background: rgba(16, 185, 129, 0.12);
        color: #059669;
    }
    .badge-warning {
        background: rgba(245, 158, 11, 0.12);
        color: #b45309;
    }

    /* === BUTTONS === */
    .btn-action-sm {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-action-sm.edit { background: #fef3c7; color: #b45309; }
    .btn-action-sm.edit:hover { background: #fcd34d; transform: scale(1.1); }
    .btn-action-sm.delete { background: #fee2e2; color: #dc2626; }
    .btn-action-sm.delete:hover { background: #fca5a5; transform: scale(1.1); }
    .btn-action-sm.toggle { background: #dbeafe; color: #2563eb; }
    .btn-action-sm.toggle:hover { background: #93c5fd; transform: scale(1.1); }

    /* === FORM CONTROLS === */
    .form-control, .form-select {
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 0.65rem 1rem;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #E63946;
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    /* === ALERTS === */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .alert-success { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .alert-danger { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
    .alert-warning { background: rgba(245, 158, 11, 0.1); color: #b45309; }
    .alert-info { background: rgba(59, 130, 246, 0.1); color: #2563eb; }

    /* === RESPONSIVE === */
    @media (max-width: 992px) {
        .gestion-layout { flex-direction: column; }
        .gestion-sidebar { width: 100%; }
        .nav-tabs-vertical {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .nav-tab-item {
            flex: 1;
            min-width: 100px;
            justify-content: center;
            padding: 0.75rem;
        }
        .nav-tab-item span { display: none; }
        .nav-tab-item:hover { transform: none; }
        .stats-sidebar { grid-template-columns: repeat(4, 1fr); }
    }

    /* === SELECT2 CUSTOM === */
    .select2-container--default .select2-selection--single {
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        height: 42px;
        padding: 0.25rem 0.5rem;
    }
    .select2-container--default .select2-selection--single:focus {
        border-color: #E63946;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    .select2-dropdown {
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<!-- Hero Section Oscuro -->
<div class="hero-gestion">
    <h1><i class="fas fa-users-cog"></i>Panel de Gestión</h1>
    <p>Administración de usuarios, ventanillas, códigos de acceso y permisos</p>
</div>

<div class="gestion-layout">
    <!-- Sidebar con pestañas -->
    <nav class="gestion-sidebar">
        <div class="nav-tabs-vertical">
            <a href="?tab=usuarios" class="nav-tab-item <?= $tabActiva === 'usuarios' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Usuarios</span>
            </a>
            <a href="?tab=ventanillas" class="nav-tab-item <?= $tabActiva === 'ventanillas' ? 'active' : '' ?>">
                <i class="fas fa-desktop"></i>
                <span>Ventanillas</span>
            </a>
            <a href="?tab=codigos" class="nav-tab-item <?= $tabActiva === 'codigos' ? 'active' : '' ?>">
                <i class="fas fa-key"></i>
                <span>Códigos</span>
            </a>
            <a href="?tab=permisos" class="nav-tab-item <?= $tabActiva === 'permisos' ? 'active' : '' ?>">
                <i class="fas fa-shield-alt"></i>
                <span>Permisos</span>
            </a>
        </div>
        
        <!-- Stats mejoradas -->
        <div class="stats-sidebar">
            <div class="stat-mini primary">
                <h4><?= count($listaUsuarios) ?></h4>
                <span>Usuarios</span>
            </div>
            <div class="stat-mini info">
                <h4><?= count($listaCodigos) ?></h4>
                <span>Códigos</span>
            </div>
        </div>
    </nav>
    
    <!-- Contenido de la pestaña activa -->
    <div class="gestion-content">
        <div class="tab-content">
            
            <?php if ($tabActiva === 'usuarios'): ?>
            <!-- TAB: USUARIOS -->
            <h2 class="tab-title"><i class="fas fa-users"></i> Gestión de Usuarios</h2>
            
            <!-- Barra de búsqueda -->
            <div class="mb-4">
                <div class="input-group" style="max-width: 400px;">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="buscar_usuario" class="form-control" placeholder="Buscar usuario..." onkeyup="filtrarUsuarios()">
                </div>
            </div>
            
            <!-- Tabla de usuarios -->
            <h3 style="margin-top: 2rem; margin-bottom: 1rem; font-weight: 600;">
                <i class="fas fa-list"></i> Listado de Usuarios (<?= count($listaUsuarios) ?>)
            </h3>
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nivel</th>
                            <th>Ventanilla</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaUsuarios as $user): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($user['Usuario']) ?></strong></td>
                            <td>
                                <span class="badge-nivel <?= ($user['pantalla'] ?? 99) == 0 ? 'badge-admin' : 'badge-user' ?>">
                                    <?= $nivelesAcceso[$user['pantalla'] ?? 99] ?? 'Desconocido' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['Ventanilla'] ?: 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php elseif ($tabActiva === 'transportistas'): ?>
            <!-- TAB: TRANSPORTISTAS -->
            <h2 class="tab-title"><i class="fas fa-truck"></i> Gestión de Transportistas</h2>
            
            <?php if (!empty($mensajeTransportista)): ?>
                <div class="alert alert-info"><?= $mensajeTransportista ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-plus-circle text-success"></i> Agregar Transportista</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="insertar">
                            <div class="row g-3">
                                <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required></div>
                                <div class="col-md-6"><input type="text" name="cedula" class="form-control" placeholder="Cédula" required></div>
                                <div class="col-md-4"><input type="text" name="empresa" class="form-control" placeholder="Empresa" required></div>
                                <div class="col-md-4"><input type="text" name="rnc" class="form-control" placeholder="RNC" required></div>
                                <div class="col-md-4"><input type="text" name="matricula" class="form-control" placeholder="Matrícula" required></div>
                            </div>
                            <button type="submit" class="maco-btn maco-btn-success mt-3">
                                <i class="fa fa-plus-circle"></i> Agregar
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-search text-info"></i> Consultar Transportista</div>
                        <form method="GET" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="transportistas">
                            <input type="text" name="cedula_consulta" class="form-control" placeholder="Ingresa la Cédula" required value="<?= htmlspecialchars($_GET['cedula_consulta'] ?? '') ?>">
                            <button type="submit" class="maco-btn maco-btn-primary">
                                <i class="fa fa-search"></i> Buscar
                            </button>
                        </form>
                        <?php if ($datosConsultados): ?>
                            <div style="background: var(--gray-100); border-radius: var(--radius); padding: 1rem; margin-top: 1rem; font-family: monospace; font-size: 0.875rem;">
                                <?php foreach ($datosConsultados as $key => $value): ?>
                                    <div><strong style="color: var(--primary);"><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-pencil-alt text-warning"></i> Actualizar Transportista</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="actualizar">
                            <div class="row g-3">
                                <div class="col-12"><input type="text" name="cedula" class="form-control" placeholder="Cédula del transportista" required></div>
                                <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nuevo Nombre" required></div>
                                <div class="col-md-6"><input type="text" name="empresa" class="form-control" placeholder="Nueva Empresa" required></div>
                                <div class="col-md-6"><input type="text" name="rnc" class="form-control" placeholder="Nuevo RNC" required></div>
                                <div class="col-md-6"><input type="text" name="matricula" class="form-control" placeholder="Nueva Matrícula" required></div>
                            </div>
                            <button type="submit" class="maco-btn maco-btn-warning mt-3">
                                <i class="fa fa-pen"></i> Actualizar
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-trash-alt text-danger"></i> Eliminar Transportista</div>
                        <form method="POST" class="d-flex gap-2" onsubmit="return confirm('¿Está seguro de eliminar este transportista?');">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="eliminar_transportista">
                            <input type="text" name="cedula" class="form-control" placeholder="Cédula a eliminar" required>
                            <button type="submit" class="maco-btn maco-btn-danger">
                                <i class="fa fa-trash"></i> Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php elseif ($tabActiva === 'ventanillas'): ?>
            <!-- TAB: VENTANILLAS -->
            <h2 class="tab-title"><i class="fas fa-desktop"></i> Gestión de Ventanillas</h2>
            
            <?php if ($mensajeVentanilla): ?>
                <div class="alert <?= $alertVentanilla ?>"><?= $mensajeVentanilla ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-5">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-desktop text-info"></i> Asignar Ventanilla</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="asignar_ventanilla">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <select id="usuario_v" name="usuario_v" class="form-select" required>
                                    <option value="">Seleccione un usuario</option>
                                    <?php foreach ($listaUsuarios as $user): ?>
                                        <option value="<?= htmlspecialchars($user['Usuario']) ?>"><?= htmlspecialchars($user['Usuario']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ventanilla</label>
                                <select name="ventanilla" class="form-select" required>
                                    <option value="" disabled selected>Seleccione...</option>
                                    <option value="Ventanilla 1">Ventanilla 1</option>
                                    <option value="Ventanilla 2">Ventanilla 2</option>
                                    <option value="Ventanilla 3">Ventanilla 3</option>
                                    <option value="Ventanilla 4">Ventanilla 4</option>
                                    <option value="Ventanilla 5">Ventanilla 5</option>
                                    <option value="">Quitar Asignación</option>
                                </select>
                            </div>
                            <button type="submit" class="maco-btn maco-btn-info w-100">
                                <i class="fas fa-check-double"></i> Asignar
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-7">
                    <h3 style="font-weight: 600; margin-bottom: 1rem;">Asignaciones Actuales</h3>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Ventanilla Asignada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaUsuarios as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['Usuario']) ?></td>
                                    <td>
                                        <?php if ($user['Ventanilla']): ?>
                                            <span class="maco-badge maco-badge-info"><?= htmlspecialchars($user['Ventanilla']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php elseif ($tabActiva === 'codigos'): ?>
            <!-- TAB: CÓDIGOS -->
            <h2 class="tab-title"><i class="fas fa-key"></i> Gestión de Códigos de Acceso</h2>
            
            <?php if ($mensajeCodigo): ?>
                <div class="alert <?= $alertCodigo ?>"><?= $mensajeCodigo ?></div>
            <?php endif; ?>
            
            <!-- Stats de acceso -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-value"><?= $statsAcceso['total_intentos'] ?? 0 ?></div>
                    <div class="stat-label">Intentos (7 días)</div>
                </div>
                <div class="stat-card" style="border-color: var(--success);">
                    <div class="stat-value" style="color: var(--success);"><?= $statsAcceso['exitosos'] ?? 0 ?></div>
                    <div class="stat-label">Exitosos</div>
                </div>
                <div class="stat-card" style="border-color: var(--danger);">
                    <div class="stat-value" style="color: var(--danger);"><?= $statsAcceso['fallidos'] ?? 0 ?></div>
                    <div class="stat-label">Fallidos</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-plus-circle text-success"></i> Crear Código</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="crear_codigo">
                            <div class="mb-3">
                                <label class="form-label">Código PIN (4 dígitos)</label>
                                <input type="text" name="codigo" class="form-control" pattern="\d{4}" required placeholder="Ej: 1234" maxlength="4">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="descripcion" class="form-control" required placeholder="Ej: Gerente de Ventas">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Almacén (opcional)</label>
                                <select name="almacen" class="form-select">
                                    <option value="">-- Admin General --</option>
                                    <?php foreach ($listaAlmacenes as $alm): ?>
                                        <option value="<?= htmlspecialchars($alm['inventlocationid']) ?>"><?= htmlspecialchars($alm['inventlocationid']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" name="es_admin" class="form-check-input" id="es_admin">
                                <label for="es_admin" class="form-check-label">Es Administrador</label>
                            </div>
                            <button type="submit" class="maco-btn maco-btn-success w-100">
                                <i class="fas fa-plus-circle"></i> Crear Código
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <h3 style="font-weight: 600; margin-bottom: 1rem;">Códigos Registrados (<?= count($listaCodigos) ?>)</h3>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Almacén</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaCodigos as $codigo): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($codigo['codigo']) ?></strong></td>
                                    <td><?= htmlspecialchars($codigo['descripcion']) ?></td>
                                    <td><?= htmlspecialchars($codigo['almacen'] ?: 'Todos') ?></td>
                                    <td>
                                        <?php if ($codigo['es_admin']): ?>
                                            <span class="maco-badge maco-badge-danger">ADMIN</span>
                                        <?php else: ?>
                                            <span class="maco-badge maco-badge-info">Usuario</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($codigo['activo']): ?>
                                            <span class="maco-badge maco-badge-success">Activo</span>
                                        <?php else: ?>
                                            <span class="maco-badge maco-badge-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <form method="POST" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="accion" value="toggle_codigo">
                                                <input type="hidden" name="id" value="<?= $codigo['id'] ?>">
                                                <button type="submit" class="maco-btn maco-btn-sm <?= $codigo['activo'] ? 'maco-btn-warning' : 'maco-btn-info' ?>" title="<?= $codigo['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas <?= $codigo['activo'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                                </button>
                                            </form>
                                            <?php if ($codigo['codigo'] !== '0000'): ?>
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar este código?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="accion" value="eliminar_codigo">
                                                    <input type="hidden" name="id" value="<?= $codigo['id'] ?>">
                                                    <input type="hidden" name="codigo_valor" value="<?= htmlspecialchars($codigo['codigo']) ?>">
                                                    <button type="submit" class="maco-btn maco-btn-sm maco-btn-danger" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($listaCodigos)): ?>
                                    <tr><td colspan="6" class="text-center">No hay códigos creados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            
            <?php if ($tabActiva === 'permisos'): ?>
            <!-- TAB: PERMISOS -->
            <h2 class="tab-title"><i class="fas fa-shield-alt"></i> Permisos de Módulos</h2>
            <p class="text-muted mb-4">Asigna módulos específicos a cada usuario para controlar su acceso al sistema.</p>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-card">
                        <div class="form-card-title"><i class="fas fa-user-cog text-primary"></i> Seleccionar Usuario</div>
                        
                        <!-- Barra de búsqueda -->
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="buscar_usuario_permisos" class="form-control" placeholder="Buscar usuario..." onkeyup="filtrarListaUsuarios()">
                            </div>
                        </div>
                        
                        <!-- Lista de usuarios -->
                        <div id="lista_usuarios_permisos" class="mb-4" style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 10px;">
                            <?php foreach ($listaUsuarios as $u): ?>
                                <div class="usuario-item p-3 border-bottom" 
                                     style="cursor: pointer; transition: all 0.2s;"
                                     onclick="seleccionarUsuarioPermisos('<?= htmlspecialchars($u['Usuario']) ?>', this)"
                                     data-usuario="<?= htmlspecialchars(strtolower($u['Usuario'])) ?>"
                                     data-nombre="<?= htmlspecialchars(strtolower($u['Nombre'] ?: $u['Usuario'])) ?>">
                                    <i class="fas fa-user text-muted me-2"></i>
                                    <span class="fw-medium"><?= htmlspecialchars($u['Nombre'] ?: $u['Usuario']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <input type="hidden" id="usuario_permisos" value="">
                        
                        <!-- Contenedor de módulos (se carga via AJAX) -->
                        <div id="modulos_container" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><i class="fas fa-th-large"></i> Módulos Disponibles</h5>
                                <div>
                                    <button type="button" class="maco-btn maco-btn-sm maco-btn-secondary" onclick="seleccionarTodos()">
                                        <i class="fas fa-check-double"></i> Todos
                                    </button>
                                    <button type="button" class="maco-btn maco-btn-sm maco-btn-secondary" onclick="deseleccionarTodos()">
                                        <i class="fas fa-times"></i> Ninguno
                                    </button>
                                </div>
                            </div>
                            
                            <div id="modulos_lista" class="row g-3 mb-4">
                                <!-- Los módulos se cargan aquí via AJAX -->
                            </div>
                            
                            <button type="button" class="maco-btn maco-btn-success w-100" onclick="guardarPermisos()">
                                <i class="fas fa-save"></i> Guardar Permisos
                            </button>
                            
                            <div id="permisos_mensaje" class="mt-3" style="display: none;"></div>
                        </div>
                        
                        <div id="loading_permisos" style="display: none;" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Cargando permisos...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
$additionalJS = <<<JSEND
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const csrfToken = '{$csrfToken}';
let usuarioSeleccionado = '';

jQuery(document).ready(function($) {
    $('#usuario_v').select2({
        placeholder: "Buscar usuario...",
        allowClear: true,
        width: '100%'
    });
    
    // Listener para selector de usuario en permisos
    // El selector ahora es una lista clickeable, no un select
});

// Filtrar lista de usuarios en permisos
function filtrarListaUsuarios() {
    var filtro = document.getElementById('buscar_usuario_permisos').value.toLowerCase();
    var items = document.querySelectorAll('#lista_usuarios_permisos .usuario-item');
    
    items.forEach(function(item) {
        var nombre = item.getAttribute('data-nombre') || '';
        var usuario = item.getAttribute('data-usuario') || '';
        if (nombre.includes(filtro) || usuario.includes(filtro)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// Seleccionar usuario de la lista
function seleccionarUsuarioPermisos(usuario, elemento) {
    // Quitar selección anterior
    document.querySelectorAll('#lista_usuarios_permisos .usuario-item').forEach(function(item) {
        item.style.background = '';
        item.style.borderLeft = '';
    });
    
    // Marcar seleccionado
    elemento.style.background = 'linear-gradient(135deg, rgba(230, 57, 70, 0.1) 0%, rgba(230, 57, 70, 0.05) 100%)';
    elemento.style.borderLeft = '4px solid #E63946';
    
    // Guardar usuario seleccionado y cargar módulos
    document.getElementById('usuario_permisos').value = usuario;
    usuarioSeleccionado = usuario;
    cargarModulos(usuario);
}

function cargarModulos(usuario) {
    jQuery('#loading_permisos').show();
    jQuery('#modulos_container').hide();
    jQuery('#permisos_mensaje').hide();
    
    jQuery.ajax({
        url: '../../Logica/api_permisos.php',
        method: 'GET',
        data: { action: 'get', usuario: usuario },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarModulos(response.modulos);
                jQuery('#modulos_container').show();
            } else {
                mostrarMensaje('Error: ' + response.error, 'alert-danger');
            }
        },
        error: function() {
            mostrarMensaje('Error de conexión al servidor', 'alert-danger');
        },
        complete: function() {
            jQuery('#loading_permisos').hide();
        }
    });
}

function renderizarModulos(modulos) {
    let html = '';
    modulos.forEach(function(m) {
        let bgClass = m.asignado ? 'bg-success bg-opacity-10 border-success' : 'bg-light';
        html += '<div class="col-md-4">';
        html += '<div class="form-check p-3 border rounded ' + bgClass + '">';
        html += '<input class="form-check-input modulo-check" type="checkbox" id="mod_' + m.key + '" value="' + m.key + '"' + (m.asignado ? ' checked' : '') + '>';
        html += '<label class="form-check-label" for="mod_' + m.key + '">';
        html += '<i class="fas ' + m.icon + ' me-2"></i>';
        html += '<strong>' + m.name + '</strong>';
        html += '</label></div></div>';
    });
    jQuery('#modulos_lista').html(html);
    
    // Actualizar estilo visual al cambiar checkbox
    jQuery('.modulo-check').on('change', function() {
        var isChecked = this.checked;
        jQuery(this).closest('.form-check')
            .toggleClass('bg-success bg-opacity-10 border-success', isChecked)
            .toggleClass('bg-light', !isChecked);
    });
}

function guardarPermisos() {
    if (!usuarioSeleccionado) {
        mostrarMensaje('Seleccione un usuario primero', 'alert-warning');
        return;
    }
    
    var modulosSeleccionados = [];
    jQuery('.modulo-check:checked').each(function() {
        modulosSeleccionados.push(jQuery(this).val());
    });
    
    jQuery.ajax({
        url: '../../Logica/api_permisos.php',
        method: 'POST',
        data: {
            action: 'save',
            usuario: usuarioSeleccionado,
            modulos: JSON.stringify(modulosSeleccionados),
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarMensaje('✅ ' + response.message, 'alert-success');
            } else {
                mostrarMensaje('❌ ' + response.error, 'alert-danger');
            }
        },
        error: function() {
            mostrarMensaje('Error de conexión al servidor', 'alert-danger');
        }
    });
}

function seleccionarTodos() {
    jQuery('.modulo-check').prop('checked', true).trigger('change');
}

function deseleccionarTodos() {
    jQuery('.modulo-check').prop('checked', false).trigger('change');
}

function mostrarMensaje(texto, clase) {
    jQuery('#permisos_mensaje')
        .removeClass('alert-success alert-danger alert-warning')
        .addClass('alert ' + clase)
        .html(texto)
        .show();
}

// Función para filtrar usuarios en la tabla
function filtrarUsuarios() {
    var filtro = document.getElementById('buscar_usuario').value.toLowerCase();
    var tabla = document.querySelector('.users-table tbody');
    if (!tabla) return;
    
    var filas = tabla.querySelectorAll('tr');
    filas.forEach(function(fila) {
        var textoFila = fila.textContent.toLowerCase();
        if (textoFila.includes(filtro)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
}
</script>
JSEND;

include __DIR__ . '/../templates/footer.php';
?>
