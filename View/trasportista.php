
<?php
// --- INICIO DEL CÓDIGO PHP (SIN CAMBIOS EN LA LÓGICA) ---
function conectarBD()
{
    $serverName = "sdb-apptransportistas-maco.privatelink.database.windows.net";
    $database   = "db-apptransportistas-maco";
    $username   = "ServiceAppTrans";
    $password   = "⁠nZ(#n41LJm)iLmJP"; // Cuidado con exponer contraseñas

    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) {
        // En un entorno de producción, es mejor registrar los errores en un archivo en lugar de mostrarlos en pantalla.
        error_log(print_r(sqlsrv_errors(), true));
        die("<div class='alert alert-danger'>❌ Error de conexión. Por favor, contacte al administrador.</div>");
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
if (isset($_GET['cedula_consulta']) && !empty($_GET['cedula_consulta'])) {
    $cedula_a_consultar = $_GET['cedula_consulta'];
    $query = "SELECT * FROM facebd WHERE Cedula = ?";
    $params = [$cedula_a_consultar];
    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt) {
        $datosConsultados = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
    if (!$datosConsultados) {
        $mensaje = "🤷 No se encontraron datos para la cédula proporcionada.";
    }
}
// --- FIN DEL CÓDIGO PHP ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Transportistas ✨</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            color: #fff;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .main-title {
            font-weight: 700;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
        }

        /* Estilo Glassmorphism para las tarjetas */
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); /* Para Safari */
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 40px 0 rgba(0, 0, 0, 0.3);
        }

        .card-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            background-color: transparent !important;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            border-radius: 0.5rem;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .btn {
            font-weight: 600;
            border-radius: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Estilos para el resultado de la consulta */
        .result-card {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0.5rem;
            padding: 1rem;
            font-family: monospace;
        }
        .result-key {
            color: var(--warning-color);
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container-fluid py-5 px-4">
    <h1 class="text-center mb-5 main-title animate__animated animate__fadeInDown">
        <i class="fa-solid fa-truck-fast me-2"></i>Gestión de Transportistas
    </h1>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info alert-dismissible fade show animate__animated animate__fadeInUp" role="alert" style="background: rgba(255, 255, 255, 0.2); border: none; color: #fff;">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6 mb-4 animate__animated animate__zoomIn">
            <div class="card h-100">
                <div class="card-header text-white"><i class="fa-solid fa-plus-circle me-2" style="color: var(--success-color);"></i>Agregar Transportista</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="insertar">
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required></div>
                            <div class="col-md-6"><input type="text" name="cedula" class="form-control" placeholder="Cédula" required></div>
                            <div class="col-md-4"><input type="text" name="empresa" class="form-control" placeholder="Empresa" required></div>
                            <div class="col-md-4"><input type="text" name="rnc" class="form-control" placeholder="RNC" required></div>
                            <div class="col-md-4"><input type="text" name="matricula" class="form-control" placeholder="Matrícula" required></div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success"><i class="fa fa-plus-circle me-1"></i>Agregar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
            <div class="card h-100">
                <div class="card-header text-white"><i class="fa-solid fa-pencil-alt me-2" style="color: var(--warning-color);"></i>Actualizar Transportista</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="actualizar">
                        <div class="row g-3">
                            <div class="col-12"><input type="text" name="cedula" class="form-control" placeholder="Cédula del transportista a actualizar" required></div>
                            <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nuevo Nombre" required></div>
                            <div class="col-md-6"><input type="text" name="empresa" class="form-control" placeholder="Nueva Empresa" required></div>
                            <div class="col-md-6"><input type="text" name="rnc" class="form-control" placeholder="Nuevo RNC" required></div>
                            <div class="col-md-6"><input type="text" name="matricula" class="form-control" placeholder="Nueva Matrícula" required></div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-warning text-dark"><i class="fa fa-pen me-1"></i>Actualizar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 mb-4 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
            <div class="card">
                <div class="card-header text-white"><i class="fa-solid fa-search me-2" style="color: var(--primary-color);"></i>Consultar Transportista</div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col">
                            <input type="text" name="cedula_consulta" class="form-control" placeholder="Ingresa la Cédula para buscar" required value="<?= htmlspecialchars($_GET['cedula_consulta'] ?? '') ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search me-1"></i>Consultar</button>
                        </div>
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
                <div class="card-header text-white"><i class="fa-solid fa-trash-alt me-2" style="color: var(--danger-color);"></i>Eliminar Transportista</div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-center">
                        <input type="hidden" name="accion" value="eliminar">
                        <div class="col">
                            <input type="text" name="cedula" class="form-control" placeholder="Cédula del transportista a eliminar" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-danger"><i class="fa fa-trash me-1"></i>Eliminar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```