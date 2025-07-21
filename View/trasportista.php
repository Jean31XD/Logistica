<?php
// --- Conexión SQL Server ---
function conectarBD() {
    $serverName = "tcp:sdb-apptransportistas-maco.database.windows.net,1433";
    $database = "db-apptransportistas-maco";
    $username = "ServiceAppTrans";
    $password = "nZ(#n41LJm)iLmJP";

    $connectionOptions = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false) {
        die("Error de conexión: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

$conn = conectarBD();

// --- Agregar Transportista ---
if (isset($_POST['accion']) && $_POST['accion'] === 'insertar') {
    $nombre = $_POST['nombre'];
    $cedula = $_POST['cedula'];
    $empresa = $_POST['empresa'];
    $rnc = $_POST['rnc'];
    $matricula = $_POST['matricula'];

    $query = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula) VALUES (?, ?, ?, ?, ?)";
    $params = array($nombre, $cedula, $empresa, $rnc, $matricula);
    $stmt = sqlsrv_query($conn, $query, $params);

    echo $stmt ? "✅ Transportista agregado correctamente." : "❌ Error al insertar: " . print_r(sqlsrv_errors(), true);
}

// --- Consultar Transportista ---
if (isset($_GET['cedula'])) {
    $cedula = $_GET['cedula'];

    $query = "SELECT * FROM facebd WHERE Cedula = ?";
    $params = array($cedula);
    $stmt = sqlsrv_query($conn, $query, $params);

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    } else {
        echo "⚠️ No se encontró el transportista.";
    }
}

// --- Actualizar Transportista ---
if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $nombre = $_POST['nombre'];
    $cedula = $_POST['cedula'];
    $empresa = $_POST['empresa'];
    $rnc = $_POST['rnc'];
    $matricula = $_POST['matricula'];

    $query = "UPDATE facebd SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ? WHERE Cedula = ?";
    $params = array($nombre, $empresa, $rnc, $matricula, $cedula);
    $stmt = sqlsrv_query($conn, $query, $params);

    echo $stmt ? "✅ Transportista actualizado correctamente." : "❌ Error al actualizar: " . print_r(sqlsrv_errors(), true);
}

// --- Eliminar Transportista ---
if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $cedula = $_POST['cedula'];

    $query = "DELETE FROM facebd WHERE Cedula = ?";
    $params = array($cedula);
    $stmt = sqlsrv_query($conn, $query, $params);

    echo $stmt ? "🗑️ Transportista eliminado correctamente." : "❌ Error al eliminar: " . print_r(sqlsrv_errors(), true);
}
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Transportistas</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">

<h2>Agregar Transportista</h2>
<form method="POST">
    <input type="hidden" name="accion" value="insertar">
    <input type="text" name="nombre" placeholder="Nombre completo" required><br><br>
    <input type="text" name="cedula" placeholder="Cédula" required><br><br>
    <input type="text" name="empresa" placeholder="Empresa" required><br><br>
    <input type="text" name="rnc" placeholder="RNC" required><br><br>
    <input type="text" name="matricula" placeholder="Matrícula" required><br><br>
    <button type="submit">Agregar</button>
</form>

<hr>

<h2>Consultar Transportista</h2>
<form method="GET">
    <input type="text" name="cedula" placeholder="Cédula a consultar" required>
    <button type="submit">Consultar</button>
</form>

<hr>

<h2>Actualizar Transportista</h2>
<form method="POST">
    <input type="hidden" name="accion" value="actualizar">
    <input type="text" name="nombre" placeholder="Nuevo Nombre" required><br><br>
    <input type="text" name="cedula" placeholder="Cédula (clave)" required><br><br>
    <input type="text" name="empresa" placeholder="Nueva Empresa" required><br><br>
    <input type="text" name="rnc" placeholder="Nuevo RNC" required><br><br>
    <input type="text" name="matricula" placeholder="Nueva Matrícula" required><br><br>
    <button type="submit">Actualizar</button>
</form>

<hr>

<h2>Eliminar Transportista</h2>
<form method="POST">
    <input type="hidden" name="accion" value="eliminar">
    <input type="text" name="cedula" placeholder="Cédula a eliminar" required><br><br>
    <button type="submit" style="color: red;">Eliminar</button>
</form>

</body>
</html>
