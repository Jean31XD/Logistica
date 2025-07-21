<?php
// --- Conexión SQL Server ---
function conectarBD() {
    $serverName = "sdb-apptransportistas-maco.privatelink.database.windows.net";
    $database   = "db-apptransportistas-maco";
    $username   = "ServiceAppTrans";
    $password   = "⁠nZ(#n41LJm)iLmJP";

    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) {
        die("<div class='alert alert-danger'>❌ Error de conexión: " . print_r(sqlsrv_errors(), true) . "</div>");
    }
    return $conn;
}

$conn = conectarBD();

// --- Procesar formularios ---
$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $cedula = $_POST['cedula'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $rnc = $_POST['rnc'] ?? '';
    $matricula = $_POST['matricula'] ?? '';

    if ($accion === 'insertar') {
        $query = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula) VALUES (?, ?, ?, ?, ?)";
        $params = [$nombre, $cedula, $empresa, $rnc, $matricula];
        $stmt = sqlsrv_query($conn, $query, $params);
        $mensaje = $stmt ? "✅ Transportista agregado correctamente." : "❌ Error al insertar.";
    }

    if ($accion === 'actualizar') {
        $query = "UPDATE facebd SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ? WHERE Cedula = ?";
        $params = [$nombre, $empresa, $rnc, $matricula, $cedula];
        $stmt = sqlsrv_query($conn, $query, $params);
        $mensaje = $stmt ? "✅ Transportista actualizado correctamente." : "❌ Error al actualizar.";
    }

    if ($accion === 'eliminar') {
        $query = "DELETE FROM facebd WHERE Cedula = ?";
        $params = [$cedula];
        $stmt = sqlsrv_query($conn, $query, $params);
        $mensaje = $stmt ? "🗑️ Transportista eliminado correctamente." : "❌ Error al eliminar.";
    }
}

$datosConsultados = null;
if (isset($_GET['cedula'])) {
    $cedula = $_GET['cedula'];
    $query = "SELECT * FROM facebd WHERE Cedula = ?";
    $params = [$cedula];
    $stmt = sqlsrv_query($conn, $query, $params);
    $datosConsultados = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Transportistas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container">
    <h1 class="mb-4 text-primary">🚚 Gestión de Transportistas</h1>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- Agregar -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">Agregar Transportista</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="insertar">
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="cedula" class="form-control" placeholder="Cédula" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="empresa" class="form-control" placeholder="Empresa" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="rnc" class="form-control" placeholder="RNC" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="matricula" class="form-control" placeholder="Matrícula" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Consultar -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Consultar Transportista</div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="cedula" class="form-control" placeholder="Cédula a consultar" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Consultar</button>
                </div>
            </form>

            <?php if ($datosConsultados): ?>
                <hr>
                <h5 class="text-secondary">Resultado:</h5>
                <pre><?= print_r($datosConsultados, true) ?></pre>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actualizar -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">Actualizar Transportista</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="nombre" class="form-control" placeholder="Nuevo Nombre" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="cedula" class="form-control" placeholder="Cédula (clave)" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="empresa" class="form-control" placeholder="Nueva Empresa" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="rnc" class="form-control" placeholder="Nuevo RNC" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="matricula" class="form-control" placeholder="Nueva Matrícula" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-warning">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Eliminar -->
    <div class="card mb-5">
        <div class="card-header bg-danger text-white">Eliminar Transportista</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="accion" value="eliminar">
                <div class="col-md-8">
                    <input type="text" name="cedula" class="form-control" placeholder="Cédula a eliminar" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
