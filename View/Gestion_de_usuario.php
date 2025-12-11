<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([0]); // Solo pantalla 0 (Admin) puede acceder
require_once __DIR__ . '/../conexionBD/conexion.php';

// CSRF token
$csrfToken = generarTokenCSRF();

// La conexión $conn ya está disponible desde conexion.php

// Inicializar variables de mensajes
$mensajeTransportista = "";
$datosConsultados = null;
$mensajeCrear = $alertCrear = "";
$mensajeModificar = $alertModificar = "";
$mensajeEliminar = $alertEliminar = "";
$mensajeVentanilla = $alertVentanilla = "";
// --- 🚀 NUEVO: Mensajes para la sección de Códigos ---
$mensajeCodigo = $alertCodigo = "";


// --- SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS (POST) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    // Validar token CSRF para TODAS las acciones de tipo POST
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
                // Se asume que la columna es 'Ventanilla' en la tabla 'Usuarios'
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

        // --- Lógica para Gestión de Transportistas ---
        case 'insertar':
        case 'actualizar':
        case 'eliminar_transportista':

            // Recuperar datos del formulario
            $nombre = $_POST['nombre'] ?? '';
            $cedula = $_POST['cedula'] ?? '';
            $empresa = $_POST['empresa'] ?? '';
            $rnc = $_POST['rnc'] ?? '';
            $matricula = $_POST['matricula'] ?? '';

            // Usuario autenticado
            if (!isset($_SESSION['usuario'])) {
                die("Acceso denegado. Debe iniciar sesión.");
            }
            $usuario = $_SESSION['usuario'];

            if ($accion === 'insertar') {
                $query = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula, creado_por) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$nombre, $cedula, $empresa, $rnc, $matricula, $usuario];
                $stmt = sqlsrv_query($conn, $query, $params);
                $mensajeTransportista = $stmt ? "✅ Transportista agregado correctamente." : "❌ Error al insertar transportista.";
            }

            if ($accion === 'actualizar') {
                $query = "UPDATE facebd 
                            SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ?, creado_por = ?
                            WHERE Cedula = ?";
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

        // --- 🚀 SECCIÓN 5: GESTIÓN DE CÓDIGOS (NUEVO) ---
        
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
                 // error_log(print_r(sqlsrv_errors(), true)); // Descomentar para depurar
            }
            break;
            
        case 'toggle_codigo':
            $id = $_POST['id'];
            // SQL Server usa CASE para alternar booleanos (bit)
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

    } // Fin del switch
} // Fin del POST

// --- SECCIÓN 3: LÓGICA DE CONSULTA DE TRANSPORTISTA (GET) ---

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

// --- SECCIÓN 4: OBTENER DATOS PARA MOSTRAR ---
// Obtener todos los usuarios con sus ventanillas para la tabla de visualización
$listaUsuarios = [];
$sqlUsuarios = "SELECT Usuario, Ventanilla FROM Usuarios ORDER BY Usuario ASC";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
if ($stmtUsuarios) {
    while ($row = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
        $listaUsuarios[] = $row;
    }
}

// --- 🚀 NUEVO: Obtener datos para la sección de Códigos ---
// Obtener códigos de acceso
$listaCodigos = [];
$sqlCodigos = "SELECT * FROM codigos_acceso ORDER BY es_admin DESC, almacen ASC, codigo ASC";
$stmtCodigos = sqlsrv_query($conn, $sqlCodigos);
if ($stmtCodigos) {
    while ($row = sqlsrv_fetch_array($stmtCodigos, SQLSRV_FETCH_ASSOC)) {
        $listaCodigos[] = $row;
    }
}

// Obtener almacenes
$listaAlmacenes = [];
$sqlAlmacenes = "SELECT DISTINCT inventlocationid FROM inventlocation ORDER BY inventlocationid";
$stmtAlmacenes = sqlsrv_query($conn, $sqlAlmacenes);
if ($stmtAlmacenes) {
    while ($row = sqlsrv_fetch_array($stmtAlmacenes, SQLSRV_FETCH_ASSOC)) {
        $listaAlmacenes[] = $row;
    }
}

// Obtener estadísticas de acceso (Traducción de MySQL a SQL Server)
$sqlStats = "SELECT 
                COUNT(*) as total_intentos,
                SUM(CASE WHEN exito = 1 THEN 1 ELSE 0 END) as exitosos,
                SUM(CASE WHEN exito = 0 THEN 1 ELSE 0 END) as fallidos
            FROM log_accesos
            WHERE fecha_hora >= DATEADD(day, -7, GETDATE())"; // Sintaxis SQL Server
$statsAcceso = sqlsrv_fetch_array(sqlsrv_query($conn, $sqlStats), SQLSRV_FETCH_ASSOC);
// --- FIN DATOS CÓDIGOS ---

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gestión</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #E63946;
            --primary-dark: #D62839;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #E63946;
            --info-color: #457B9D;
            --accent-color: #457B9D;
            --accent-dark: #1D3557;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--accent-dark);
            background-size: 100% 100%;
            animation: none;
            min-height: 100vh;
            color: #fff;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .main-title {
            font-weight: 800;
            text-shadow: 3px 3px 15px rgba(0, 0, 0, 0.4);
            font-size: 2.5rem;
            margin-bottom: 2rem;
        }
        .section-title {
            font-weight: 700;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
            margin-bottom: 2.5rem;
            text-align: center;
            font-size: 2rem;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px 0 rgba(0, 0, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }
        .card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 60px 0 rgba(0, 0, 0, 0.4);
            border-color: rgba(230, 57, 70, 0.5);
        }
        .card-header {
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            background: linear-gradient(135deg, rgba(230, 57, 70, 0.2) 0%, rgba(69, 123, 157, 0.2) 100%);
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            padding: 1.25rem 1.5rem;
            border-radius: 1.5rem 1.5rem 0 0;
        }
        .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
            background-color: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.25);
            color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.2);
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
        }
        .form-select, .select2-container--bootstrap-5 .select2-selection { color-scheme: dark; color: #fff; }
        select.form-select option, .select2-results__option { background: #1D3557; color: #fff; }
        .form-check-input {
            background-color: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            width: 1.5rem;
            height: 1.5rem;
        }
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .form-check-label {
            font-weight: 500;
            margin-left: 0.5rem;
        }
        .btn {
            font-weight: 700;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        .btn:active {
            transform: translateY(0);
        }
        .btn-success {
            background: var(--success);
        }
        .btn-danger {
            background: linear-gradient(135deg, #E63946 0%, #D62839 100%);
        }
        .btn-warning {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: #fff !important;
        }
        .btn-info {
            background: linear-gradient(135deg, #457B9D 0%, #1D3557 100%);
        }
        .btn-primary {
            background: linear-gradient(135deg, #457B9D 0%, #1D3557 100%);
        }
        .result-card {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 0.75rem;
            padding: 1.25rem;
            font-family: 'Courier New', monospace;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .result-key { color: var(--warning-color); font-weight: bold; }
        .alert {
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .alert-success { background: linear-gradient(135deg, rgba(16, 185, 129, 0.9) 0%, rgba(5, 150, 105, 0.9) 100%); color: #fff; }
        .alert-danger { background: linear-gradient(135deg, rgba(230, 57, 70, 0.9) 0%, rgba(214, 40, 57, 0.9) 100%); color: #fff; }
        .alert-warning { background: linear-gradient(135deg, rgba(245, 158, 11, 0.9) 0%, rgba(217, 119, 6, 0.9) 100%); color: #fff; }
        .alert-info { background: linear-gradient(135deg, rgba(69, 123, 157, 0.9) 0%, rgba(29, 53, 87, 0.9) 100%); color: #fff; }
        .table-custom {
            background-color: rgba(0, 0, 0, 0.25);
            color: #fff;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .table-custom th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            font-size: 0.85rem;
        }
        .table-custom td {
            padding: 0.875rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table-custom tbody tr:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        .table-custom .badge { min-width: 80px; text-align: center; font-weight: 600; padding: 0.5rem 0.75rem; }
        .table-custom .actions-col { display: flex; gap: 0.5rem; justify-content: center; }
        .badge {
            padding: 0.5rem 0.875rem;
            border-radius: 0.5rem;
            font-weight: 700;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>

<main class="container-fluid py-5 px-4">

    <section id="gestion-transportistas" class="mb-5">
        <h1 class="text-center mb-5 main-title animate__animated animate__fadeInDown">
            <i class="fa-solid fa-truck-fast me-2"></i>Gestión de Transportistas
        </h1>
        <?php if (!empty($mensajeTransportista)): ?>
            <div class="alert alert-info alert-dismissible fade show animate__animated animate__fadeInUp" role="alert">
                <?= $mensajeTransportista // No necesita htmlspecialchars porque el mensaje se construye en el servidor ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-6 mb-4 animate__animated animate__zoomIn">
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-plus-circle me-2" style="color: var(--success-color);"></i>Agregar Transportista</div>
                    <div class="card-body">
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
                            <div class="mt-4 text-end"> <button type="submit" class="btn btn-success"><i class="fa fa-plus-circle me-1"></i>Agregar</button> </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-pencil-alt me-2" style="color: var(--warning-color);"></i>Actualizar Transportista</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="actualizar">
                            <div class="row g-3">
                                <div class="col-12"><input type="text" name="cedula" class="form-control" placeholder="Cédula del transportista a actualizar" required></div>
                                <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nuevo Nombre" required></div>
                                <div class="col-md-6"><input type="text" name="empresa" class="form-control" placeholder="Nueva Empresa" required></div>
                                <div class="col-md-6"><input type="text" name="rnc" class="form-control" placeholder="Nuevo RNC" required></div>
                                <div class="col-md-6"><input type="text" name="matricula" class="form-control" placeholder="Nueva Matrícula" required></div>
                            </div>
                            <div class="mt-4 text-end"> <button type="submit" class="btn btn-warning text-dark"><i class="fa fa-pen me-1"></i>Actualizar</button> </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
             <div class="col-lg-6 mb-4 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-search me-2" style="color: var(--primary-color);"></i>Consultar Transportista</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col"> <input type="text" name="cedula_consulta" class="form-control" placeholder="Ingresa la Cédula para buscar" required value="<?= htmlspecialchars($_GET['cedula_consulta'] ?? '') ?>"> </div>
                            <div class="col-auto"> <button type="submit" class="btn btn-primary"><i class="fa fa-search me-1"></i>Consultar</button> </div>
                        </form>
                        <?php if ($datosConsultados): ?>
                            <hr style="border-color: rgba(255,255,255,0.3); margin-top: 1.5rem;">
                            <h5 class="text-white mt-3">Resultado de la Búsqueda:</h5>
                            <div class="result-card">
                                <?php foreach ($datosConsultados as $key => $value): ?>
                                    <div><span class="result-key"><?= htmlspecialchars($key) ?>:</span> <?= htmlspecialchars($value) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4 animate__animated animate__zoomIn" style="animation-delay: 0.6s;">
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-trash-alt me-2" style="color: var(--danger-color);"></i>Eliminar Transportista</div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <form method="POST" class="row g-3 align-items-center">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="eliminar_transportista">
                            <div class="col"> <input type="text" name="cedula" class="form-control" placeholder="Cédula del transportista a eliminar" required> </div>
                            <div class="col-auto"> <button type="submit" class="btn btn-danger"><i class="fa fa-trash me-1"></i>Eliminar</button> </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <hr style="border-width: 2px; border-color: rgba(255,255,255,0.3); margin: 3rem 0;">

    <section id="gestion-usuarios" aria-labelledby="gestion-usuarios-title">
        <h2 id="gestion-usuarios-title" class="section-title animate__animated animate__fadeInUp">Gestión de Usuarios</h2>
        <div class="row justify-content-center">
             <div class="col-lg-4 mb-4">
                <article class="card animate__animated animate__zoomIn" style="animation-delay: 0.8s;">
                    <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Crear Usuario</div>
                    <div class="card-body">
                        <?php if ($mensajeCrear): ?><div class="alert <?= $alertCrear ?>"><?= $mensajeCrear ?></div><?php endif; ?>
                        <form method="post" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
                            <input type="hidden" name="accion" value="crear" />
                            <div class="mb-3"><label for="usuario" class="form-label">Usuario</label><input type="text" id="usuario" name="usuario" class="form-control" required pattern="[a-zA-Z0-9_]{3,20}" /></div>
                            <div class="mb-3"><label for="password" class="form-label">Contraseña</label><input type="password" id="password" name="password" class="form-control" required /></div>
                            <div class="mb-3"><label for="pantalla" class="form-label">Nivel de Acceso</label><select id="pantalla" name="pantalla" class="form-select" required><option value="" selected disabled>Seleccione...</option><option value="1">Despacho</option><option value="2">Validación</option><option value="3">Recepción</option><option value="0">Administrador</option><option value="4">Reportes</option><option value="5">Admin-limitado</option><option value="6">Reportes Faltantes</option></option><option value="8">Listo etiquetas</option></select></div>
                            <button type="submit" class="btn btn-success w-100 mt-2"><i class="fa-solid fa-plus-circle me-1"></i>Crear</button>
                        </form>
                    </div>
                </article>
            </div>
             <div class="col-lg-4 mb-4">
                <article class="card animate__animated animate__zoomIn" style="animation-delay: 0.9s;">
                    <div class="card-header"><i class="fa-solid fa-user-pen me-2"></i>Modificar Usuario</div>
                    <div class="card-body">
                        <?php if ($mensajeModificar): ?><div class="alert <?= $alertModificar ?>"><?= $mensajeModificar ?></div><?php endif; ?>
                        <form method="post" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
                            <input type="hidden" name="accion" value="modificar" />
                            <div class="mb-3"><label for="usuario_modificar" class="form-label">Usuario a Modificar</label><input type="text" id="usuario_modificar" name="usuario_modificar" class="form-control" required /></div>
                            <div class="mb-3"><label for="password_nuevo" class="form-label">Nueva Contraseña</label><input type="password" id="password_nuevo" name="password_nuevo" class="form-control" placeholder="Dejar vacío para no cambiar" /></div>
                            <div class="mb-3"><label for="pantalla_nuevo" class="form-label">Nuevo Nivel de Acceso</label><select id="pantalla_nuevo" name="pantalla_nuevo" class="form-select"><option value="-1" selected>Sin cambio</option><option value="1">Despacho</option><option value="2">Validación</option><option value="3">Recepción</option><option value="0">Administrador</option><option value="5">Admin-limitado</option><option value="4">Reportes</option><option value="6">Reporte de faltantes</option><option value="8">Listo etiquetas</option> <option value="10">Listo inventario</option></select></div>
                            <button type="submit" class="btn btn-warning text-dark w-100 mt-2"><i class="fa-solid fa-pen me-1"></i>Modificar</button>
                        </form>
                    </div>
                </article>
            </div>
             <div class="col-lg-4 mb-4">
                 <article class="card animate__animated animate__zoomIn" style="animation-delay: 1.0s;">
                    <div class="card-header"><i class="fa-solid fa-user-minus me-2"></i>Eliminar Usuario</div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <?php if ($mensajeEliminar): ?><div class="alert <?= $alertEliminar ?>"><?= $mensajeEliminar ?></div><?php endif; ?>
                        <form method="post" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
                            <input type="hidden" name="accion" value="eliminar" />
                            <div class="mb-3">
                                <label for="usuario_eliminar" class="form-label">Usuario a Eliminar</label>
                                <input type="text" id="usuario_eliminar" name="usuario_eliminar" class="form-control" required />
                            </div>
                            <button type="submit" class="btn btn-danger w-100 mt-2"><i class="fa-solid fa-trash me-1"></i>Eliminar</button>
                        </form>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <hr style="border-width: 2px; border-color: rgba(255,255,255,0.3); margin: 3rem 0;">

    <section id="gestion-ventanillas" aria-labelledby="gestion-ventanillas-title">
        <h2 id="gestion-ventanillas-title" class="section-title animate__animated animate__fadeInUp">Gestión de Ventanillas</h2>
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card animate__animated animate__fadeInLeft">
                    <div class="card-header"><i class="fa-solid fa-desktop me-2"></i>Asignar Ventanilla a Usuario</div>
                    <div class="card-body">
                        <?php if ($mensajeVentanilla): ?>
                            <div class="alert <?= $alertVentanilla ?> alert-dismissible fade show" role="alert">
                                <?= $mensajeVentanilla ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="asignar_ventanilla">
                            <div class="mb-3">
                                <label for="usuario_v" class="form-label">Usuario</label>
                                <select id="usuario_v" name="usuario_v" class="form-select" required>
                                    <option value="">Seleccione un usuario</option>
                                    <?php
                                    // Re-usamos la lista de usuarios ya obtenida
                                    foreach ($listaUsuarios as $user) {
                                        echo "<option value='" . htmlspecialchars($user['Usuario']) . "'>" . htmlspecialchars($user['Usuario']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="ventanilla" class="form-label">Ventanilla</label>
                                <select id="ventanilla" name="ventanilla" class="form-select" required>
                                    <option value="" selected disabled>Seleccione...</option>
                                    <option value="Ventanilla 1">Ventanilla 1</option>
                                    <option value="Ventanilla 2">Ventanilla 2</option>
                                    <option value="Ventanilla 3">Ventanilla 3</option>
                                    <option value="Ventanilla 4">Ventanilla 4</option>
                                    <option value="Ventanilla 5">Ventanilla 5</option>
                                    <option value="">Quitar Asignación</option> </select>
                            </div>
                            <button type="submit" class="btn btn-info w-100"><i class="fa-solid fa-check-double me-1"></i>Asignar / Modificar</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card animate__animated animate__fadeInRight">
                     <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Usuarios y Ventanillas</div>
                     <div class="card-body">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-custom table-sm">
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
                                            <td><?= htmlspecialchars($user['Ventanilla'] ?: 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                     </div>
                </div>
            </div>
            
        </div>
    </section>

    <hr style="border-width: 2px; border-color: rgba(255,255,255,0.3); margin: 3rem 0;">

    <section id="gestion-codigos" aria-labelledby="gestion-codigos-title">
        <h2 id="gestion-codigos-title" class="section-title animate__animated animate__fadeInUp">🔐 Gestión de Códigos de Acceso</h2>
        
        <?php if ($mensajeCodigo): ?>
            <div class="alert <?= $alertCodigo ?> alert-dismissible fade show" role="alert">
                <?= $mensajeCodigo ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card animate__animated animate__fadeInLeft">
                    <div class="card-header"><i class="fa-solid fa-plus-circle me-2"></i>Crear Nuevo Código</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="accion" value="crear_codigo">
                            
                            <div class="mb-3">
                                <label for="codigo" class="form-label">Código PIN (4 dígitos) *</label>
                                <input type="text" id="codigo" name="codigo" class="form-control" pattern="\d{4}" required 
                                       placeholder="Ej: 1234" maxlength="4">
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción *</label>
                                <input type="text" id="descripcion" name="descripcion" class="form-control" required 
                                       placeholder="Ej: Gerente de Ventas - Juan Pérez">
                            </div>
                            
                            <div class="mb-3">
                                <label for="almacen" class="form-label">Almacén (dejar vacío para acceso total)</label>
                                <select id="almacen" name="almacen" class="form-select">
                                    <option value="">-- Acceso a todos los almacenes --</option>
                                    <?php foreach ($listaAlmacenes as $alm): ?>
                                        <option value="<?= htmlspecialchars($alm['inventlocationid']) ?>">
                                            <?= htmlspecialchars($alm['inventlocationid']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" id="es_admin" name="es_admin" class="form-check-input">
                                <label for="es_admin" class="form-check-label">Es Administrador (acceso total)</label>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 mt-2"><i class="fa-solid fa-plus-circle me-1"></i>Crear Código</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card animate__animated animate__fadeInRight">
                    <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Códigos Existentes</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Almacén</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Último Acceso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listaCodigos as $codigo): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($codigo['codigo']) ?></strong></td>
                                            <td><?= htmlspecialchars($codigo['descripcion']) ?></td>
                                            <td><?= $codigo['almacen'] ? htmlspecialchars($codigo['almacen']) : '<em>Todos</em>' ?></td>
                                            <td>
                                                <?php if ($codigo['es_admin']): ?>
                                                    <span class="badge bg-danger">ADMIN</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark">USUARIO</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($codigo['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $codigo['ultimo_acceso'] ? $codigo['ultimo_acceso']->format('d/m/Y H:i') : 'Nunca' ?>
                                            </td>
                                            <td>
                                                <div class="actions-col">
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="action" value="toggle_codigo">
                                                        <input type="hidden" name="id" value="<?= $codigo['id'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= $codigo['activo'] ? 'btn-warning text-dark' : 'btn-info' ?>"
                                                                title="<?= $codigo['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                            <i class="fa-solid <?= $codigo['activo'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if ($codigo['codigo'] !== '0000'): // Proteger código maestro ?>
                                                        <form method="POST" style="margin:0;" 
                                                              onsubmit="return confirm('¿Estás seguro de eliminar este código?');">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="action" value="eliminar_codigo">
                                                            <input type="hidden" name="id" value="<?= $codigo['id'] ?>">
                                                            <input type="hidden" name="codigo_valor" value="<?= htmlspecialchars($codigo['codigo']) ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($listaCodigos)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No hay códigos creados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
                    <div class="card-header"><i class="fa-solid fa-chart-line me-2"></i>Estadísticas de Acceso (Últimos 7 días)</div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-md-4">
                                <div class="p-3 rounded" style="background-color: rgba(0, 0, 0, 0.2);">
                                    <h2 class="fw-bold mb-1"><?= $statsAcceso['total_intentos'] ?? 0 ?></h2>
                                    <p class="mb-0 text-white-50">Total Intentos</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded text-white" style="background-color: rgba(25, 135, 84, 0.5);">
                                    <h2 class="fw-bold mb-1"><?= $statsAcceso['exitosos'] ?? 0 ?></h2>
                                    <p class="mb-0">Exitosos</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded text-white" style="background-color: rgba(220, 53, 69, 0.5);">
                                    <h2 class="fw-bold mb-1"><?= $statsAcceso['fallidos'] ?? 0 ?></h2>
                                    <p class="mb-0">Fallidos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializar Select2 en el selector de usuarios para la asignación de ventanilla
    $('#usuario_v').select2({
        theme: "bootstrap-5",
        placeholder: "Buscar y seleccionar un usuario...",
        allowClear: true
    });
});
</script>
</body>
</html>