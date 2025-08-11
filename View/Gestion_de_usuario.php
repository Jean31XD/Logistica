<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// --- SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN INICIAL ---

// Proteger la página contra el almacenamiento en caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Verificar si el usuario está logueado y es administrador (pantalla = 0)
if (!isset($_SESSION['usuario']) || $_SESSION['pantalla'] != 0) {
    // Si no lo es, redirigir a la página de inicio de sesión
    header("Location: ../index.php");
    exit();
}

// En un escenario real, la conexión vendría de un archivo separado.
// require_once __DIR__ . '/../conexionBD/conexion.php';

/**
 * Establece la conexión con la base de datos SQL Server.
 * (Función mantenida para simular la conexión del require_once)
 * @return false|resource El objeto de conexión o false si falla.
 */
function conectarBD()
{
    $serverName = "sdb-apptransportistas-maco.privatelink.database.windows.net";
    $database   = "db-apptransportistas-maco";
    $username   = "ServiceAppTrans";
    $password   = "⁠nZ(#n41LJm)iLmJP"; // Es mejor manejar esto con variables de entorno.

    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) {
        error_log(print_r(sqlsrv_errors(), true));
        die("<div class='alert alert-danger'>❌ Error de conexión. Por favor, contacte al administrador.</div>");
    }
    return $conn;
}

/**
 * Genera un token CSRF si no existe en la sesión.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Verifica si el token CSRF enviado coincide con el de la sesión.
 * @param string $tokenEnviado El token del formulario.
 * @param string $tokenSesion El token de la sesión.
 * @return bool True si son válidos e iguales, false en caso contrario.
 */
function verificarCSRF($tokenEnviado, $tokenSesion) {
    return is_string($tokenEnviado) && is_string($tokenSesion) && hash_equals($tokenSesion, $tokenEnviado);
}


// Conectar a la base de datos
$conn = conectarBD();

// Inicializar variables de mensajes para ambas secciones
$mensajeTransportista = "";
$datosConsultados = null;
$mensajeCrear = $alertCrear = "";
$mensajeModificar = $alertModificar = "";
$mensajeEliminar = $alertEliminar = "";


// --- SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS (POST) ---

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error: Token CSRF inválido. Por favor, recargue la página e intente de nuevo.");
    }

    $accion = $_POST['accion'] ?? '';

    switch ($accion) {
        case 'crear':
            // Código para crear
            break;

        case 'modificar':
            // Código para modificar
            break;

        case 'eliminar':
            // Código para eliminar
            break;

        case 'asignar_ventanilla':
            $usuario_v = trim($_POST['usuario_v'] ?? '');
            $ventanilla = trim($_POST['ventanilla'] ?? '');

            if (!empty($usuario_v) && !empty($ventanilla)) {
                $sql = "UPDATE Usuarios SET Ventanilla = ? WHERE Usuario = ?";
                $params = [$ventanilla, $usuario_v];
                $stmt = sqlsrv_prepare($conn, $sql, $params);

                if ($stmt && sqlsrv_execute($stmt)) {
                    $mensajeVentanilla = "✅ Ventanilla asignada correctamente.";
                    $alertVentanilla = "success";
                } else {
                    $mensajeVentanilla = "❌ Error al asignar ventanilla.";
                    $alertVentanilla = "danger";
                }
            } else {
                $mensajeVentanilla = "⚠️ Complete todos los campos.";
                $alertVentanilla = "warning";
            }
            break;
    }
}

    // Validar token CSRF para TODAS las acciones de tipo POST
    if (!verificarCSRF($csrf, $_SESSION['csrf_token'])) {
        die("Error: Token CSRF inválido. Por favor, recargue la página e intente de nuevo.");
    }

    // --- Lógica para Gestión de Usuarios (CÓDIGO NUEVO Y MEJORADO) ---
    switch ($accion) {
        case 'crear':
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $pantalla = filter_var($_POST['pantalla'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 6]]);

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
                // Asumo que la columna de password en BD es 'password' según el nuevo código
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
            $pantallaNueva = ($pantallaNuevaInput !== '-1') ? filter_var($pantallaNuevaInput, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 6]]) : false;

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
                     // sqlsrv_rows_affected solo funciona después de la ejecución
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
                if (isset($_POST['asignar_ventanilla'])) {
    $usuario_v = trim($_POST['usuario_v']);
    $ventanilla = trim($_POST['ventanilla']);

    if (!empty($usuario_v) && !empty($ventanilla)) {
        $sql = "UPDATE Usuarios SET Ventanilla = ? WHERE Usuario = ?";
        $params = [$ventanilla, $usuario_v];
        $stmt = sqlsrv_prepare($conn, $sql, $params);

        if ($stmt && sqlsrv_execute($stmt)) {
            $mensajeVentanilla = "✅ Ventanilla asignada correctamente.";
            $alertVentanilla = "success";
        } else {
            $mensajeVentanilla = "❌ Error al asignar ventanilla.";
            $alertVentanilla = "danger";
        }
    } else {
        $mensajeVentanilla = "⚠️ Complete todos los campos.";
        $alertVentanilla = "warning";
    }
}

            }
            break;

        // --- Lógica para Gestión de Transportistas ---
        case 'insertar':
        case 'actualizar':
        case 'eliminar_transportista':
            $nombre = $_POST['nombre'] ?? '';
            $cedula = $_POST['cedula'] ?? '';
            $empresa = $_POST['empresa'] ?? '';
            $rnc = $_POST['rnc'] ?? '';
            $matricula = $_POST['matricula'] ?? '';

            if ($accion === 'insertar') {
                $query = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula) VALUES (?, ?, ?, ?, ?)";
                $params = [$nombre, $cedula, $empresa, $rnc, $matricula];
                $stmt = sqlsrv_query($conn, $query, $params);
                $mensajeTransportista = $stmt ? "✅ Transportista agregado correctamente." : "❌ Error al insertar transportista.";
            }

            if ($accion === 'actualizar') {
                $query = "UPDATE facebd SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ? WHERE Cedula = ?";
                $params = [$nombre, $empresa, $rnc, $matricula, $cedula];
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
    }
}


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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de gestion de usuarios </title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0d6efd; --success-color: #198754;
            --warning-color: #ffc107; --danger-color: #dc3545;
            --info-color: #0dcaf0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            color: #fff;
        }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .main-title { font-weight: 700; text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3); }
        .section-title { font-weight: 600; text-shadow: 1px 1px 8px rgba(0,0,0,0.2); margin-bottom: 2rem; text-align: center; }
        .card {
            background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            height: 100%;
        }
        .card:hover { transform: translateY(-10px); box-shadow: 0 16px 40px 0 rgba(0, 0, 0, 0.3); }
        .card-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2); background-color: transparent !important;
            font-size: 1.25rem; font-weight: 600; color: #fff;
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff; border-radius: 0.5rem;
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.7); }
        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.3); color: #fff;
            border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .form-select { color-scheme: dark; }
        
        /* --- REGLA AÑADIDA PARA EL COLOR DE LAS OPCIONES --- */
        select.form-select option {
            background: #fff;
            color: #000;
        }

        .btn { font-weight: 600; border-radius: 0.5rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn:hover { transform: scale(1.05); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
        .result-card { background: rgba(0, 0, 0, 0.2); border-radius: 0.5rem; padding: 1rem; font-family: monospace; }
        .result-key { color: var(--warning-color); font-weight: bold; }
        .alert { border: none; text-shadow: 1px 1px 2px rgba(0,0,0,0.2); }
        .alert-success { background: rgba(25, 135, 84, 0.8); color: #fff; }
        .alert-danger { background: rgba(220, 53, 69, 0.8); color: #fff; }
        .alert-warning { background: rgba(255, 193, 7, 0.8); color: #212529; }
        .alert-info { background: rgba(13, 202, 240, 0.8); color: #fff; }
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
                <?= htmlspecialchars($mensajeTransportista) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-6 mb-4 animate__animated animate__zoomIn">
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-plus-circle me-2" style="color: var(--success-color);"></i>Agregar Transportista</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                            <input type="hidden" name="accion" value="crear" />
                            <div class="mb-3"><label for="usuario" class="form-label">Usuario</label><input type="text" id="usuario" name="usuario" class="form-control" required pattern="[a-zA-Z0-9_]{3,20}" /></div>
                            <div class="mb-3"><label for="password" class="form-label">Contraseña</label><input type="password" id="password" name="password" class="form-control" required /></div>
                            <div class="mb-3"><label for="pantalla" class="form-label">Nivel de Acceso</label><select id="pantalla" name="pantalla" class="form-select" required><option value="" selected disabled>Seleccione...</option><option value="1">Despacho</option><option value="2">Validación</option><option value="3">Recepción</option><option value="0">Administrador</option><option value="4">Reportes</option><option value="5">Admin-limitado</option><option value="6">Reportes Faltantes</option></select></div>
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                            <input type="hidden" name="accion" value="modificar" />
                            <div class="mb-3"><label for="usuario_modificar" class="form-label">Usuario a Modificar</label><input type="text" id="usuario_modificar" name="usuario_modificar" class="form-control" required /></div>
                            <div class="mb-3"><label for="password_nuevo" class="form-label">Nueva Contraseña</label><input type="password" id="password_nuevo" name="password_nuevo" class="form-control" placeholder="Dejar vacío para no cambiar" /></div>
                            <div class="mb-3"><label for="pantalla_nuevo" class="form-label">Nuevo Nivel de Acceso</label><select id="pantalla_nuevo" name="pantalla_nuevo" class="form-select"><option value="-1" selected>Sin cambio</option><option value="1">Despacho</option><option value="2">Validación</option><option value="3">Recepción</option><option value="0">Administrador</option><option value="5">Admin-limitado</option><option value="4">Reportes</option><option value="6">Reporte de faltantes</option></select></div>
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
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
    <!-- FORMULARIO ASIGNAR VENTANILLA -->
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Asignar Ventanilla</h5>
    </div>
    <div class="card-body">
<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="mb-3">
        <label for="usuario_v" class="form-label">Usuario</label>
        <select id="usuario_v" name="usuario_v" class="form-select" required>
            <option value="">Seleccione un usuario</option>
            <?php
            $sqlUsuarios = "SELECT Usuario FROM Usuarios ORDER BY Usuario ASC";
            $stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
            while ($row = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
                echo "<option value='" . htmlspecialchars($row['Usuario']) . "'>" . htmlspecialchars($row['Usuario']) . "</option>";
            }
            ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="ventanilla" class="form-label">Ventanilla</label>
        <select id="ventanilla" name="ventanilla" class="form-select" required>
            <option value="">Seleccione...</option>
            <option value="Ventanilla 1">Ventanilla 1</option>
            <option value="Ventanilla 2">Ventanilla 2</option>
            <option value="Ventanilla 3">Ventanilla 3</option>
            <option value="Ventanilla 4">Ventanilla 4</option>
            <option value="Ventanilla 5">Ventanilla 5</option>
        </select>
    </div>

    <button type="submit" name="asignar_ventanilla" class="btn btn-primary">Asignar</button>
</form>

<script>
$(document).ready(function() {
    $('#usuario_v').select2({
        placeholder: "Buscar usuario...",
        allowClear: true
    });
});
</script>

</script>
</div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>