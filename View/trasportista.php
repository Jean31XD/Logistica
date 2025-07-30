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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <!-- AOS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Montserrat', sans-serif;
    }

    html, body {
      height: 100%;
      background: #000;
      overflow-x: hidden;
    }

    .grid-background {
      position: fixed;
      top: 0;
      left: 0;
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
    }

    .grid-background span {
      display: block;
      width: calc(6.25vw - 2px);
      height: calc(6.25vw - 2px);
      background: #181818;
      transition: 1.5s;
    }

    .grid-background span:hover {
      background: #f00;
      transition: 0s;
    }

    .container {
      position: relative;
      z-index: 1;
      background: #181818dd;
      border-radius: 12px;
      padding: 40px;
      color: #fff;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.9);
    }

    input, button {
      font-size: 14px;
    }
  </style>
</head>
<body>

<!-- Fondo animado -->
<section class="grid-background">
  <?php for ($i = 0; $i < 400; $i++) echo "<span></span>"; ?>
</section>

<div class="container mt-5">
  <h1 class="text-center mb-4 animate__animated animate__fadeInDown">Gestión de Transportistas</h1>

  <!-- Procesamiento PHP -->
  <?php
  // Aquí conectarías a tu base de datos si es necesario

  $datosConsultados = null;

  if ($_SERVER["REQUEST_METHOD"] == "POST" || $_SERVER["REQUEST_METHOD"] == "GET") {
      $accion = $_POST['accion'] ?? '';
      $cedula = $_POST['cedula'] ?? $_GET['cedula'] ?? '';

      if ($accion === 'insertar') {
          echo '<div class="alert alert-success">✅ Transportista agregado correctamente.</div>';
      } elseif ($accion === 'actualizar') {
          echo '<div class="alert alert-warning">✏️ Transportista actualizado correctamente.</div>';
      } elseif ($accion === 'eliminar') {
          echo '<div class="alert alert-danger">🗑️ Transportista eliminado correctamente.</div>';
      } elseif (!empty($cedula)) {
          $datosConsultados = [
              'nombre' => 'Juan Pérez',
              'cedula' => $cedula,
              'empresa' => 'Transporte XYZ',
              'rnc' => '123456789',
              'matricula' => 'AB1234'
          ];
      }
  }
  ?>

  <div class="accordion" id="accordionTransportistas">

    <!-- Agregar -->
    <div class="accordion-item" data-aos="fade-up">
      <h2 class="accordion-header">
        <button class="accordion-button bg-success text-white fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAgregar">
          ➕ Agregar Transportista
        </button>
      </h2>
      <div id="collapseAgregar" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <form method="POST">
            <input type="hidden" name="accion" value="insertar">
            <div class="row g-3">
              <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required></div>
              <div class="col-md-6"><input type="text" name="cedula" class="form-control" placeholder="Cédula" required></div>
              <div class="col-md-4"><input type="text" name="empresa" class="form-control" placeholder="Empresa" required></div>
              <div class="col-md-4"><input type="text" name="rnc" class="form-control" placeholder="RNC" required></div>
              <div class="col-md-4"><input type="text" name="matricula" class="form-control" placeholder="Matrícula" required></div>
            </div>
            <div class="mt-3 text-end">
              <button type="submit" class="btn btn-success"><i class="fa fa-plus-circle me-1"></i>Agregar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Consultar -->
    <div class="accordion-item" data-aos="fade-up" data-aos-delay="100">
      <h2 class="accordion-header">
        <button class="accordion-button bg-primary text-white fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConsultar">
          🔎 Consultar Transportista
        </button>
      </h2>
      <div id="collapseConsultar" class="accordion-collapse collapse">
        <div class="accordion-body">
          <form method="GET" class="row g-3">
            <div class="col-md-8"><input type="text" name="cedula" class="form-control" placeholder="Cédula a consultar" required></div>
            <div class="col-md-4"><button type="submit" class="btn btn-outline-light bg-primary"><i class="fa fa-search me-1"></i>Consultar</button></div>
          </form>
          <?php if ($datosConsultados): ?>
            <hr>
            <div class="alert alert-secondary animate__animated animate__fadeIn">
              <strong>Resultado:</strong><br>
              <pre><?= print_r($datosConsultados, true) ?></pre>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Actualizar -->
    <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
      <h2 class="accordion-header">
        <button class="accordion-button bg-warning text-dark fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseActualizar">
          ✏️ Actualizar Transportista
        </button>
      </h2>
      <div id="collapseActualizar" class="accordion-collapse collapse">
        <div class="accordion-body">
          <form method="POST">
            <input type="hidden" name="accion" value="actualizar">
            <div class="row g-3">
              <div class="col-md-6"><input type="text" name="nombre" class="form-control" placeholder="Nuevo Nombre" required></div>
              <div class="col-md-6"><input type="text" name="cedula" class="form-control" placeholder="Cédula (clave)" required></div>
              <div class="col-md-4"><input type="text" name="empresa" class="form-control" placeholder="Nueva Empresa" required></div>
              <div class="col-md-4"><input type="text" name="rnc" class="form-control" placeholder="Nuevo RNC" required></div>
              <div class="col-md-4"><input type="text" name="matricula" class="form-control" placeholder="Nueva Matrícula" required></div>
            </div>
            <div class="mt-3 text-end">
              <button type="submit" class="btn btn-warning"><i class="fa fa-pen me-1"></i>Actualizar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Eliminar -->
    <div class="accordion-item" data-aos="fade-up" data-aos-delay="300">
      <h2 class="accordion-header">
        <button class="accordion-button bg-danger text-white fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEliminar">
          🗑️ Eliminar Transportista
        </button>
      </h2>
      <div id="collapseEliminar" class="accordion-collapse collapse">
        <div class="accordion-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="accion" value="eliminar">
            <div class="col-md-8"><input type="text" name="cedula" class="form-control" placeholder="Cédula a eliminar" required></div>
            <div class="col-md-4"><button type="submit" class="btn btn-danger"><i class="fa fa-trash me-1"></i>Eliminar</button></div>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init();
</script>

</body>
</html>
