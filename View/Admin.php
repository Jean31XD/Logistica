<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// --- INICIO DEL CÓDIGO PHP (LÓGICA MEJORADA Y SEGURA) ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['usuario']) || $_SESSION['pantalla'] != 0) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verificarCSRF($tokenEnviado, $tokenSesion) {
    return is_string($tokenEnviado) && is_string($tokenSesion) && hash_equals($tokenSesion, $tokenEnviado);
}

$mensajeCrear = $alertCrear = "";
$mensajeEliminar = $alertEliminar = "";
$mensajeModificar = $alertModificar = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verificarCSRF($csrf, $_SESSION['csrf_token'])) {
        die("Error: Token CSRF inválido.");
    }

    switch ($accion) {
        case 'crear':
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            // Corrección: El rango máximo debe ser 6 para incluir todas las opciones.
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
                $mensajeCrear = "❌ Error al verificar usuario.";
                $alertCrear = "alert-danger";
            } elseif (sqlsrv_fetch($stmtCheck)) {
                $mensajeCrear = "❌ El usuario ya existe.";
                $alertCrear = "alert-danger";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = sqlsrv_prepare($conn, "INSERT INTO usuarios (usuario, password, pantalla) VALUES (?, ?, ?)", [$usuario, $hash, $pantalla]);
                if ($stmtInsert && sqlsrv_execute($stmtInsert)) {
                    $mensajeCrear = "✅ Usuario <strong>$usuario</strong> creado exitosamente.";
                    $alertCrear = "alert-success";
                } else {
                    $mensajeCrear = "❌ Error al crear usuario.";
                    $alertCrear = "alert-danger";
                }
            }
            break;

        case 'eliminar':
            $usuarioEliminar = trim($_POST['usuario_eliminar'] ?? '');
            if ($usuarioEliminar === $_SESSION['usuario']) {
                $mensajeEliminar = "❌ No puede eliminar su propio usuario.";
                $alertEliminar = "alert-danger";
            } elseif (!$usuarioEliminar) {
                $mensajeEliminar = "⚠️ Especifique el usuario a eliminar.";
                $alertEliminar = "alert-warning";
            } else {
                $stmtDelete = sqlsrv_prepare($conn, "DELETE FROM usuarios WHERE usuario = ?", [$usuarioEliminar]);
                $rows_affected = sqlsrv_rows_affected($stmtDelete);
                if ($stmtDelete && sqlsrv_execute($stmtDelete) && $rows_affected > 0) {
                    $mensajeEliminar = "✅ Usuario <strong>$usuarioEliminar</strong> eliminado.";
                    $alertEliminar = "alert-success";
                } else {
                    $mensajeEliminar = "❌ Error al eliminar o el usuario no existe.";
                    $alertEliminar = "alert-danger";
                }
            }
            break;

        case 'modificar':
            $usuarioMod = trim($_POST['usuario_modificar'] ?? '');
            $nuevaClave = trim($_POST['password_nuevo'] ?? '');
            // Lógica mejorada para manejar la opción "Sin cambio".
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
                $mensajeModificar = "❌ Error al modificar usuario.";
                $alertModificar = "alert-danger";
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel de Administración ✨</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #cf8888ff, #e14646ff, #6d5656ff, #d33939ff);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            min-height: 100vh;
            color: #fff;
            padding-top: 100px;
            padding-bottom: 3rem;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .floating-header {
            position: fixed;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            width: 95%;
            max-width: 800px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px 25px;
            border-radius: 50px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            z-index: 1100;
        }

        /* Estilo para hacer visible el logo negro */
        .floating-header .logo img {
            height: 48px;
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: 50%;
            padding: 5px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .floating-header .username {
            font-weight: 600;
            font-size: 1.1rem;
            text-shadow: 1px 1px 5px rgba(0,0,0,0.2);
        }

        .floating-header .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 25px;
            transition: all 0.3s ease;
            text-decoration: none;
            padding: 8px 20px;
            border: none;
        }
        .floating-header .logout-btn:hover { background-color: rgba(220, 53, 69, 0.8); }

        .main-title, .section-title {
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            text-align: center;
        }
        .section-title { font-size: 1.8rem; margin-top: 3rem; margin-bottom: 2rem; }
        
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: #fff;
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 40px 0 rgba(0, 0, 0, 0.3);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            font-size: 1.2rem;
            text-align: center;
        }
        .card-body { padding: 1.5rem; flex-grow: 1; }
        .card-body p, .card-body .form-label {
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }
        
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            border-radius: 0.5rem;
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.3);
            color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.3);
        }
        select.form-select option { background: #333; color: #fff; }

        .btn {
            font-weight: 600;
            border-radius: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
        }
        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .alert {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid;
            color: #fff;
        }
        .alert-success { border-color: var(--success-color); }
        .alert-danger { border-color: var(--danger-color); }
        .alert-warning { border-color: var(--warning-color); color: #000; }
        .alert-info { border-color: var(--info-color); }
    </style>
</head>
<body>

<header class="floating-header animate__animated animate__fadeInDown" role="banner">
    <div class="logo" aria-hidden="true">
        <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo de la empresa" />
    </div>
    <div class="username" aria-live="polite">
        <i class="fa-solid fa-user-shield me-2"></i><?= htmlspecialchars($_SESSION['usuario']) ?>
    </div>
    <a href="../Logica/logout.php" class="logout-btn" role="button">
        <i class="fa-solid fa-right-from-bracket me-1"></i>Cerrar Sesión
    </a>
</header>

<main class="container-fluid" role="main">
    <h1 class="main-title mb-4 animate__animated animate__fadeInDown" style="animation-delay: 0.2s;">Panel de Administración</h1>

    <section aria-labelledby="acceso-rapido-title">
        <h2 id="acceso-rapido-title" class="section-title animate__animated animate__fadeInUp">Acceso Rápido</h2>
        <div class="cards-container mb-5">
            <?php 
                $cards = [
                    ['title' => 'Despacho de Factura', 'desc' => 'Gestiona los envíos y entregas.', 'link' => '../View/Inicio.php', 'icon' => 'fa-truck-fast'],
                    ['title' => 'Validación', 'desc' => 'Valida facturas escaneadas.', 'link' => '../View/facturas.php', 'icon' => 'fa-check-double'],
                    ['title' => 'Recepción', 'desc' => 'Control de recepción de documentos.', 'link' => '../View/facturas-recepcion.php', 'icon' => 'fa-inbox'],
                    ['title' => 'Reporte de Facturas', 'desc' => 'Reporte por Transportista.', 'link' => '../View/Reporte.php', 'icon' => 'fa-chart-pie'],
                    ['title' => 'Reporte Facturas CXC', 'desc' => 'Reporte de Facturas faltantes.', 'link' => '../View/BI.php', 'icon' => 'fa-file-invoice-dollar']
                ];
                $delay = 0.2;
                foreach ($cards as $card):
            ?>
            <article class="card animate__animated animate__zoomIn" style="animation-delay: <?= $delay ?>s;">
                <div class="card-header"><i class="fa-solid <?= $card['icon'] ?> me-2"></i><?= $card['title'] ?></div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <p><?= $card['desc'] ?></p>
                    <a href="<?= $card['link'] ?>" class="btn btn-outline-light w-100 mt-3" role="link">Ingresar</a>
                </div>
            </article>
            <?php $delay += 0.1; endforeach; ?>
        </div>
    </section>

    <section aria-labelledby="gestion-usuarios-title">
        <h2 id="gestion-usuarios-title" class="section-title animate__animated animate__fadeInUp">Gestión de Usuarios</h2>
        <div class="cards-container">
            <article class="card animate__animated animate__zoomIn" style="animation-delay: 0.8s;">
                <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Crear Usuario</div>
                <div class="card-body">
                    <?php if ($mensajeCrear): ?><div class="alert <?= $alertCrear ?>"><?= $mensajeCrear ?></div><?php endif; ?>
                    <form method="post" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                        <input type="hidden" name="accion" value="crear" />
                        <div class="mb-3"><label for="usuario" class="form-label">Usuario</label><input type="text" id="usuario" name="usuario" class="form-control" required /></div>
                        <div class="mb-3"><label for="password" class="form-label">Contraseña</label><input type="password" id="password" name="password" class="form-control" required /></div>
                        <div class="mb-3"><label for="pantalla" class="form-label">Nivel de Acceso</label><select id="pantalla" name="pantalla" class="form-select" required><option value="" selected disabled>Seleccione...</option><option value="1">Despacho</option><option value="2">Validación</option><option value="3">Recepción</option><option value="0">Administrador</option><option value="4">Reportes</option><option value="5">Admin-limitado</option><option value="6">Reportes Faltantes</option></select></div>
                        <button type="submit" class="btn btn-success w-100 mt-2"><i class="fa-solid fa-plus-circle me-1"></i>Crear</button>
                    </form>
                </div>
            </article>

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
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>