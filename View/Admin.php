<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// Seguridad y control de acceso
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['usuario']) || $_SESSION['pantalla'] != 0) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verificarCSRF($tokenEnviado, $tokenSesion) {
    return is_string($tokenEnviado) && is_string($tokenSesion) && hash_equals($tokenSesion, $tokenEnviado);
}

// Mensajes de feedback
$mensajeCrear = $alertCrear = "";
$mensajeEliminar = $alertEliminar = "";
$mensajeModificar = $alertModificar = "";

// Procesamiento del formulario
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
            $pantalla = filter_var($_POST['pantalla'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 5]]);

            if (!$usuario || !$password || $pantalla === false) {
                $mensajeCrear = "⚠️ Todos los campos son obligatorios y válidos.";
                $alertCrear = "alert-warning";
                break;
            }

            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $usuario)) {
                $mensajeCrear = "❌ Usuario inválido (letras, números y guiones bajos, 3-20 caracteres).";
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
                if ($stmtDelete && sqlsrv_execute($stmtDelete)) {
                    $mensajeEliminar = "✅ Usuario <strong>$usuarioEliminar</strong> eliminado.";
                    $alertEliminar = "alert-success";
                } else {
                    $mensajeEliminar = "❌ Error al eliminar usuario.";
                    $alertEliminar = "alert-danger";
                }
            }
            break;

        case 'modificar':
            $usuarioMod = trim($_POST['usuario_modificar'] ?? '');
            $nuevaClave = trim($_POST['password_nuevo'] ?? '');
            $pantallaNueva = filter_var($_POST['pantalla_nuevo'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 7]]);

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
                $mensajeModificar = "⚠️ No se ingresaron cambios.";
                $alertModificar = "alert-warning";
                break;
            }

            $params[] = $usuarioMod;
            $sql = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE usuario = ?";
            $stmtUpdate = sqlsrv_prepare($conn, $sql, $params);
            if ($stmtUpdate && sqlsrv_execute($stmtUpdate)) {
                $mensajeModificar = "✅ Usuario <strong>$usuarioMod</strong> modificado.";
                $alertModificar = "alert-success";
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
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />

  <title>Panel de Administración</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
      :root {
        --rojo: #e31f25;
        --rojo-oscuro: #b71c1c;
        --fondo-degradado: linear-gradient(to bottom, #e31f25 0%, #ffffff 100%);
      }

      body {
        min-height: 100vh;
        background: var(--fondo-degradado);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        margin: 0;
        padding-top: 100px; /* espacio para el header flotante */
        padding-bottom: 3rem;
      }

      /* Header flotante con logo + usuario + logout */
      .floating-header {
        position: fixed;
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        padding: 12px 40px;
        border-radius: 30px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 40px;
        min-width: 400px;
        max-width: 600px;
        justify-content: space-between;
        z-index: 1100;
        user-select: none;
      }

      /* Logo */
      .floating-header .logo img {
        height: 48px;
        user-select: none;
      }

      /* Nombre de usuario centrado */
      .floating-header .username {
        flex-grow: 1;
        text-align: center;
        font-weight: 700;
        font-size: 1.25rem;
        color: #000; /* negro para mejor contraste */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        user-select: text;
      }

      /* Botón cerrar sesión */
      .floating-header .logout-btn {
        background: var(--rojo);
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease;
        white-space: nowrap;
        text-decoration: none;
        display: inline-block;
      }

      .floating-header .logout-btn:hover {
        background-color: var(--rojo-oscuro);
        color: #fff;
        text-decoration: none;
      }

      /* Título principal */
      .main-title {
        color: #000; /* negro */
        font-weight: 700;
        margin: 2rem 0 1rem;
        text-align: center;
        text-shadow: none;
        background-color: #fff;
        display: inline-block;
        padding: 0.75rem 2rem;
        border-radius: 15px;
        user-select: none;
        box-shadow: 0 3px 8px rgb(0 0 0 / 0.1);
      }

      /* Títulos secciones */
      .section-title {
        color: #000; /* negro */
        font-size: 1.5rem;
        font-weight: 700;
        border-left: 6px solid var(--rojo);
        padding: 0.4rem 1.2rem;
        margin-top: 3rem;
        margin-bottom: 1.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        user-select: none;
        background-color: #fff;
        border-top-right-radius: 15px;
        border-bottom-right-radius: 15px;
        display: inline-block;
        box-shadow: 0 2px 10px rgb(0 0 0 / 0.1);
      }

      /* Tarjetas acceso rápido */
      .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
      }

      .card {
        border-radius: 15px;
        box-shadow: 0 8px 25px rgb(0 0 0 / 0.15);
        border: none;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .card:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgb(0 0 0 / 0.25);
      }

      .card-header {
        background-color: var(--rojo);
        color: #fff;
        font-weight: 700;
        font-size: 1.2rem;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        user-select: none;
        text-align: center;
        padding: 0.9rem 1rem;
      }

      .card-body {
        padding: 1.25rem 1.5rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .card-body p {
        margin-bottom: 1.2rem;
        color: #444;
        font-size: 0.95rem;
      }

      /* Formularios gestión usuarios */
.form-section {
  background-color: #fff;
  padding: 2rem 1.8rem;
  border-radius: 15px;
  box-shadow: 0 0 18px rgb(0 0 0 / 0.1);
  transition: box-shadow 0.3s ease;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  height: 100%;
  min-height: 360px; /* altura mínima igualada a cards de acceso rápido */
}

.user-management-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 2rem;
  margin-bottom: 3rem;
}
      .form-section:hover {
        box-shadow: 0 0 28px rgb(0 0 0 / 0.16);
      }

      /* Títulos formularios */
      .form-section h5 {
        color: var(--rojo);
        font-weight: 700;
        margin-bottom: 1.8rem;
        text-align: center;
        user-select: none;
      }

      /* Botón rojo */
      .btn-rojo {
        background-color: var(--rojo);
        color: #fff;
        font-weight: 700;
        border: none;
        box-shadow: 0 6px 14px rgb(227 31 37 / 0.5);
        transition: background-color 0.3s ease;
      }

      .btn-rojo:hover,
      .btn-rojo:focus {
        background-color: var(--rojo-oscuro);
        box-shadow: 0 8px 20px rgb(183 28 28 / 0.7);
        outline: none;
      }

      .alert {
        font-size: 1rem;
        border-radius: 10px;
        padding: 0.85rem 1.2rem;
        margin-bottom: 1.2rem;
        user-select: none;
      }

      label.form-label {
        font-weight: 700;
        color: var(--rojo-oscuro);
        user-select: none;
      }

      input.form-control,
      select.form-select {
        border-radius: 10px;
        border: 1.7px solid #bbb;
        transition: border-color 0.3s ease;
        font-size: 0.95rem;
        padding: 0.5rem 0.75rem;
      }

      input.form-control:focus,
      select.form-select:focus {
        border-color: var(--rojo);
        box-shadow: 0 0 8px var(--rojo);
        outline: none;
      }

      /* Grid para formularios en pantallas grandes */
      @media (min-width: 768px) {
        .row.g-4 > .col-md-4 {
          display: flex;
        }
      }
    </style>
</head>
<body>

<!-- Panel flotante arriba -->
<div class="floating-header" role="banner" aria-label="Panel de administración superior">
  <div class="logo" aria-hidden="true">
    <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo empresa" />
  </div>
  <div class="username" aria-live="polite" aria-atomic="true">
    <?= htmlspecialchars($_SESSION['usuario']) ?>
  </div>
<a href="../Logica/logout.php" class="logout-btn" role="button" aria-label="Cerrar sesión">Cerrar Sesión</a>
</div>

<div class="container" role="main" aria-labelledby="main-title">
  <h1 id="main-title" class="main-title">Panel de Administración</h1>

  <section aria-labelledby="acceso-rapido-title">
    <h2 id="acceso-rapido-title" class="section-title">Acceso rápido a pantallas</h2>
    <div class="cards-container mb-5">
      <article class="card" tabindex="0" aria-label="Acceso a Despacho de Factura">
        <div class="card-header">Despacho de Factura</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Gestiona los envíos y entregas.</p>
          <a href="../View/Inicio.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
      <article class="card" tabindex="0" aria-label="Acceso a Validación">
        <div class="card-header">Validación</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Valida facturas escaneadas.</p>
          <a href="../View/facturas.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
      <article class="card" tabindex="0" aria-label="Acceso a Recepción">
        <div class="card-header">Recepción</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Control de recepción de documentos.</p>
          <a href="../View/facturas-recepcion.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
      <article class="card" tabindex="0" aria-label="Acceso a Reporte de Facturas">
        <div class="card-header">Reporte de facturas</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Reporte de Facturas por Transportista.</p>
          <a href="../View/Reporte.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
       <article class="card" tabindex="0" aria-label="Acceso a Reporte de Facturas">
        <div class="card-header">Reporte de facturas CXC</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Reporte de Facturas faltantes.</p>
          <a href="../View/BI.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
     
    </div>
  </section>
</div>

<section aria-labelledby="gestion-usuarios-title">
  <h2 id="gestion-usuarios-title" class="section-title">Gestión de Usuarios</h2>

  <div class="user-management-container">
    <!-- Crear Usuario -->
    <article class="card" tabindex="0" aria-label="Formulario Crear Usuario">
      <div class="card-header">Crear Usuario</div>
      <div class="card-body">
        <?php if ($mensajeCrear): ?>
          <div class="alert <?= $alertCrear ?>"><?= $mensajeCrear ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
          <input type="hidden" name="accion" value="crear" />
          <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" id="usuario" name="usuario" class="form-control" required />
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" id="password" name="password" class="form-control" required />
          </div>
          <div class="mb-3">
            <label for="pantalla" class="form-label">Pantalla</label>
            <select id="pantalla" name="pantalla" class="form-select" required>
              <option value="" selected disabled>Seleccione...</option>
              <option value="1">Despacho</option>
              <option value="2">Validación</option>
              <option value="3">Recepción</option>
              <option value="0">Administrador</option>
              <option value="4">Reportes</option>
              <option value="5">Admin-limitado</option>
              <option value="6">Reportes faltantes</option>
            </select>
          </div>
          <button type="submit" class="btn btn-rojo w-100">Crear</button>
        </form>
      </div>
    </article>

    <!-- Eliminar Usuario -->
    <article class="card" tabindex="0" aria-label="Formulario Eliminar Usuario">
      <div class="card-header">Eliminar Usuario</div>
      <div class="card-body">
        <?php if ($mensajeEliminar): ?>
          <div class="alert <?= $alertEliminar ?>"><?= $mensajeEliminar ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
          <input type="hidden" name="accion" value="eliminar" />
          <div class="mb-3">
            <label for="usuario_eliminar" class="form-label">Usuario</label>
            <input type="text" id="usuario_eliminar" name="usuario_eliminar" class="form-control" required />
          </div>
          <button type="submit" class="btn btn-rojo w-100">Eliminar</button>
        </form>
      </div>
    </article>

    <!-- Modificar Usuario -->
    <article class="card" tabindex="0" aria-label="Formulario Modificar Usuario">
      <div class="card-header">Modificar Usuario</div>
      <div class="card-body">
        <?php if ($mensajeModificar): ?>
          <div class="alert <?= $alertModificar ?>"><?= $mensajeModificar ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
          <input type="hidden" name="accion" value="modificar" />
          <div class="mb-3">
            <label for="usuario_modificar" class="form-label">Usuario</label>
            <input type="text" id="usuario_modificar" name="usuario_modificar" class="form-control" required />
          </div>
          <div class="mb-3">
            <label for="password_nuevo" class="form-label">Nueva Contraseña</label>
            <input type="password" id="password_nuevo" name="password_nuevo" class="form-control" placeholder="Dejar vacío para no cambiar" />
          </div>
          <div class="mb-3">
            <label for="pantalla_nuevo" class="form-label">Nivel de Acceso</label>
            <select id="pantalla_nuevo" name="pantalla_nuevo" class="form-select">
              <option value="-1" selected>Sin cambio</option>
              <option value="1">Despacho</option>
              <option value="2">Validación</option>
              <option value="3">Recepción</option>
              <option value="0">Administrador</option>
              <option value="5">Admin-limitado</option>
              <option value="4">Reportes</option>
              <option value="6">Reporte de faltantes</option> 
            </select>
          </div>
          <button type="submit" class="btn btn-rojo w-100">Modificar</button>
        </form>
      </div>
    </article>

  </div>
</section>


</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
