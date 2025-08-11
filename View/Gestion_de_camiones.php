<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// Bloquear caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Solo admin
if (!isset($_SESSION['usuario']) || $_SESSION['pantalla'] != 0) {
    header("Location: ../index.php");
    exit();
}

// Conexión SQL Server
function conectarBD()
{
    $conn = sqlsrv_connect(
        "sdb-apptransportistas-maco.privatelink.database.windows.net",
        [
            "Database" => "db-apptransportistas-maco",
            "UID" => "ServiceAppTrans",
            "PWD" => "⁠nZ(#n41LJm)iLmJP",
            "TrustServerCertificate" => true,
            "CharacterSet" => "UTF-8"
        ]
    );
    if ($conn === false) die("Error de conexión");
    return $conn;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verificarCSRF($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

$conn = conectarBD();
$mensaje = "";
$datosConsultados = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) die("CSRF inválido");

    $accion = $_POST['accion'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $cedula = $_POST['cedula'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $rnc = $_POST['rnc'] ?? '';
    $matricula = $_POST['matricula'] ?? '';

    switch ($accion) {
        case 'insertar':
            $stmt = sqlsrv_query($conn, "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula) VALUES (?, ?, ?, ?, ?)", [$nombre, $cedula, $empresa, $rnc, $matricula]);
            $mensaje = $stmt ? "✅ Transportista agregado" : "❌ Error al insertar";
            break;
        case 'actualizar':
            $stmt = sqlsrv_query($conn, "UPDATE facebd SET Nombres=?, Empresa=?, RNC=?, Matricula=? WHERE Cedula=?", [$nombre, $empresa, $rnc, $matricula, $cedula]);
            $mensaje = $stmt ? "✅ Transportista actualizado" : "❌ Error al actualizar";
            break;
        case 'eliminar_transportista':
            $stmt = sqlsrv_query($conn, "DELETE FROM facebd WHERE Cedula=?", [$cedula]);
            $mensaje = $stmt ? "🗑️ Transportista eliminado" : "❌ Error al eliminar";
            break;
    }
}

// Consulta GET
if (!empty($_GET['cedula_consulta'])) {
    $stmt = sqlsrv_query($conn, "SELECT * FROM facebd WHERE Cedula = ?", [$_GET['cedula_consulta']]);
    $datosConsultados = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    if (!$datosConsultados) $mensaje = "🤷 No se encontraron datos";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Transportistas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {font-family: 'Poppins', sans-serif; background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: #fff;}
        @keyframes gradientBG {0% {background-position: 0% 50%;}50% {background-position: 100% 50%;}100% {background-position: 0% 50%;}}
        .card {background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 1rem; border: 1px solid rgba(255,255,255,0.2);}
        .form-control, .form-select {background-color: rgba(255,255,255,0.2); color: #fff;}
        select.form-select option {background: #fff; color: #000;}
        .alert {border: none;}
    </style>
</head>
<body class="container py-5">

<h1 class="text-center mb-5"><i class="fa-solid fa-truck-fast me-2"></i>Gestión de Transportistas</h1>
<?php if ($mensaje): ?>
    <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Agregar -->
    <div class="col-lg-6">
        <div class="card p-4">
            <h5><i class="fa fa-plus-circle me-2 text-success"></i>Agregar</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="insertar">
                <input type="text" name="nombre" class="form-control mb-2" placeholder="Nombre completo" required>
                <input type="text" name="cedula" class="form-control mb-2" placeholder="Cédula" required>
                <input type="text" name="empresa" class="form-control mb-2" placeholder="Empresa" required>
                <input type="text" name="rnc" class="form-control mb-2" placeholder="RNC" required>
                <input type="text" name="matricula" class="form-control mb-3" placeholder="Matrícula" required>
                <button class="btn btn-success w-100">Agregar</button>
            </form>
        </div>
    </div>
    <!-- Actualizar -->
    <div class="col-lg-6">
        <div class="card p-4">
            <h5><i class="fa fa-pen me-2 text-warning"></i>Actualizar</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="actualizar">
                <input type="text" name="cedula" class="form-control mb-2" placeholder="Cédula" required>
                <input type="text" name="nombre" class="form-control mb-2" placeholder="Nuevo nombre" required>
                <input type="text" name="empresa" class="form-control mb-2" placeholder="Nueva empresa" required>
                <input type="text" name="rnc" class="form-control mb-2" placeholder="Nuevo RNC" required>
                <input type="text" name="matricula" class="form-control mb-3" placeholder="Nueva matrícula" required>
                <button class="btn btn-warning w-100">Actualizar</button>
            </form>
        </div>
    </div>
    <!-- Consultar -->
    <div class="col-lg-6">
        <div class="card p-4">
            <h5><i class="fa fa-search me-2 text-primary"></i>Consultar</h5>
            <form method="GET" class="d-flex mb-3">
                <input type="text" name="cedula_consulta" class="form-control me-2" placeholder="Cédula" value="<?= htmlspecialchars($_GET['cedula_consulta'] ?? '') ?>" required>
                <button class="btn btn-primary">Buscar</button>
            </form>
            <?php if ($datosConsultados): ?>
                <?php foreach ($datosConsultados as $k => $v): ?>
                    <div><strong><?= htmlspecialchars($k) ?>:</strong> <?= htmlspecialchars($v) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Eliminar -->
    <div class="col-lg-6">
        <div class="card p-4">
            <h5><i class="fa fa-trash me-2 text-danger"></i>Eliminar</h5>
            <form method="POST" class="d-flex">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="eliminar_transportista">
                <input type="text" name="cedula" class="form-control me-2" placeholder="Cédula" required>
                <button class="btn btn-danger">Eliminar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
