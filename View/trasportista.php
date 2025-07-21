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
        die("❌ Error de conexión: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

$conn = conectarBD();
$mensaje = "";

// --- Agregar Transportista ---
if (isset($_POST['accion']) && $_POST['accion'] === 'insertar') {
    $nombre    = $_POST['nombre'];
    $cedula    = $_POST['cedula'];
    $empresa   = $_POST['empresa'];
    $rnc       = $_POST['rnc'];
    $matricula = $_POST['matricula'];

    $query  = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula) VALUES (?, ?, ?, ?, ?)";
    $params = array($nombre, $cedula, $empresa, $rnc, $matricula);
    $stmt   = sqlsrv_query($conn, $query, $params);

    $mensaje = $stmt ? "✅ Transportista agregado correctamente." : "❌ Error al insertar: " . print_r(sqlsrv_errors(), true);
}

// --- Consultar Transportista ---
if (isset($_GET['cedula'])) {
    $cedula = $_GET['cedula'];

    $query  = "SELECT * FROM facebd WHERE Cedula = ?";
    $params = array($cedula);
    $stmt   = sqlsrv_query($conn, $query, $params);

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $mensaje = "<strong>Transportista encontrado:</strong><br><pre>" . print_r($row, true) . "</pre>";
    } else {
        $mensaje = "⚠️ No se encontró el transportista.";
    }
}

// --- Actualizar Transportista ---
if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $nombre    = $_POST['nombre'];
    $cedula    = $_POST['cedula'];
    $empresa   = $_POST['empresa'];
    $rnc       = $_POST['rnc'];
    $matricula = $_POST['matricula'];

    $query  = "UPDATE facebd SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ? WHERE Cedula = ?";
    $params = array($nombre, $empresa, $rnc, $matricula, $cedula);
    $stmt   = sqlsrv_query($conn, $query, $params);

    $mensaje = $stmt ? "✅ Transportista actualizado correctamente." : "❌ Error al actualizar: " . print_r(sqlsrv_errors(), true);
}

// --- Eliminar Transportista ---
if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $cedula = $_POST['cedula'];

    $query  = "DELETE FROM facebd WHERE Cedula = ?";
    $params = array($cedula);
    $stmt   = sqlsrv_query($conn, $query, $params);

    $mensaje = $stmt ? "🗑️ Transportista eliminado correctamente." : "❌ Error al eliminar: " . print_r(sqlsrv_errors(), true);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Transportistas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <h1 class="mb-4 text-primary">Gestión de Transportistas</h1>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>

    <!-- Agregar -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">Agregar Transportista</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="insertar">
                <div class="mb-3"><input type="text" class="form-control" name="nombre" placeholder="Nombre completo" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="cedula" placeholder="Cédula" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="empresa" placeholder="Empresa" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="rnc" placeholder="RNC" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="matricula" placeholder="Matrícula" required></div>
                <button type="submit" class="btn btn-success">Agregar</button>
            </form>
        </div>
    </div>

    <!-- Consultar -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Consultar Transportista</div>
        <div class="card-body">
            <form method="GET">
                <div class="mb-3">
                    <input type="text" class="form-control" name="cedula" placeholder="Cédula a consultar" required>
                </div>
                <button type="submit" class="btn btn-info text-white">Consultar</button>
            </form>
        </div>
    </div>

    <!-- Actualizar -->
    <div class="card mb-4">
        <div class="card-header bg-warning">Actualizar Transportista</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <div class="mb-3"><input type="text" class="form-control" name="nombre" placeholder="Nuevo Nombre" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="cedula" placeholder="Cédula (clave)" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="empresa" placeholder="Nueva Empresa" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="rnc" placeholder="Nuevo RNC" required></div>
                <div class="mb-3"><input type="text" class="form-control" name="matricula" placeholder="Nueva Matrícula" required></div>
                <button type="submit" class="btn btn-warning">Actualizar</button>
            </form>
        </div>
    </div>

    <!-- Eliminar -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">Eliminar Transportista</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="eliminar">
                <div class="mb-3">
                    <input type="text" class="form-control" name="cedula" placeholder="Cédula a eliminar" required>
                </div>
                <button type="submit" class="btn btn-danger">Eliminar</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
