<?php 
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #f5f7fa, #c3cfe2);
            font-family: 'Segoe UI', sans-serif;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            font-weight: bold;
            color: #007bff;
            animation: fadeInDown 1s;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            transition: 0.3s ease-in-out;
            animation: fadeInUp 1s;
            margin: 10px; /* Espaciado entre tarjetas */
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .alert {
            animation: fadeIn 0.5s;
        }

        input.form-control {
            transition: 0.3s;
        }

        input.form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 10px #007bff33;
        }

        button {
            transition: transform 0.2s ease;
        }

        button:hover {
            transform: scale(1.05);
        }

        @keyframes fadeIn {
            from {opacity: 0;}
            to {opacity: 1;}
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1><i class="fa-solid fa-truck-moving me-2"></i>Gestión de Transportistas</h1>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Agregar -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white fw-bold">➕ Agregar Transportista</div>
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
                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus-circle me-1"></i>Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Consultar -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white fw-bold">🔎 Consultar Transportista</div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="cedula" class="form-control" placeholder="Cédula a consultar" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-light bg-primary"><i class="fa fa-search me-1"></i>Consultar</button>
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
        <div class="card-header bg-warning text-dark fw-bold">✏️ Actualizar Transportista</div>
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
                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-warning"><i class="fa fa-pen me-1"></i>Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Eliminar -->
    <div class="card mb-5">
        <div class="card-header bg-danger text-white fw-bold">🗑️ Eliminar Transportista</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="accion" value="eliminar">
                <div class="col-md-8">
                    <input type="text" name="cedula" class="form-control" placeholder="Cédula a eliminar" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-danger"><i class="fa fa-trash me-1"></i>Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
