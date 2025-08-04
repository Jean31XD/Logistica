<?php   
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
date_default_timezone_set('America/Santo_Domingo');

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 4, 5])) {
    header("Location: ../index.php");
    exit();
}

include '../conexionBD/conexion.php';

$filtro = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

$hoy = date('Y-m-d');
if (!$desde) $desde = $hoy;
if (!$hasta) $hasta = $hoy;

$desdeSQL = $desde;
$hastaSQL = $hasta;

// TOTAL
$sqlTotal = "SELECT COUNT(DISTINCT Transportista) AS total
             FROM custinvoicejour
             WHERE Transportista IS NOT NULL
               AND Validar = 'Completada'
               AND Fecha BETWEEN ? AND ?
               AND Factura NOT LIKE 'NC%'";
$paramsTotal = [$desdeSQL, $hastaSQL];

if ($filtro) {
    $sqlTotal .= " AND Transportista LIKE ?";
    $paramsTotal[] = "%$filtro%";
}

$stmtTotal = sqlsrv_query($conn, $sqlTotal, $paramsTotal);
$totalRow = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC);
$totalPaginas = ceil($totalRow['total'] / $porPagina);

// PRINCIPAL
$sql = "SELECT Transportista, COUNT(*) AS Cantidad, MIN(zona) AS zona
        FROM custinvoicejour
        WHERE Transportista IS NOT NULL
          AND Validar = 'Completada'
          AND Fecha_Scanner BETWEEN ? AND ?
          AND Factura NOT LIKE 'NC%'";
$params = [$desdeSQL, $hastaSQL];

if ($filtro) {
    $sql .= " AND Transportista LIKE ?";
    $params[] = "%$filtro%";
}

$sql .= " GROUP BY Transportista ORDER BY Cantidad DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = $porPagina;

$stmt = sqlsrv_query($conn, $sql, $params);
if (!$stmt) {
    die("Error al consultar: " . print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang='es'>
<head>

<!-- ESTILOS MODERNOS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<style>
    body {
        background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
        background-size: 400% 400%;
        animation: gradientBG 20s ease infinite;
        font-family: 'Segoe UI', sans-serif;
        color: #fff;
        padding: 20px;
    }
    @keyframes gradientBG {
        0% {background-position: 0% 50%;}
        50% {background-position: 100% 50%;}
        100% {background-position: 0% 50%;}
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        padding: 20px;
        margin-bottom: 20px;
        color: white;
    }
    .glass-card h2, .glass-card label, .glass-card .form-label, .glass-card th {
        color: white;
    }
    .table thead th {
        background-color: #9a1a1a;
        color: #fff;
        font-weight: 600;
    }
    .btn-danger {
        background-color: #e31f25;
        border: none;
    }
    .btn-danger:hover {
        background-color: #a31616;
    }
    .select2-container--default .select2-selection--single {
        background-color: #fff !important;
        border-radius: 8px;
        padding: 4px;
        border: 1px solid #ccc;
    }
</style>
</head>
<body>
<div class="main-container">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo" style="max-width: 100%;">
    <form method="get" id="formFiltros" class="w-100">
      <label for="inputTransportista" class="form-label">Transportista:</label>
      <input type="text" name="transportista" id="inputTransportista" class="form-control mb-2" placeholder="Buscar..." value="<?= htmlspecialchars($filtro) ?>">

      <label for="desde" class="form-label">Desde:</label>
      <input type="date" name="desde" class="form-control mb-2" value="<?= htmlspecialchars($desde) ?>">

      <label for="hasta" class="form-label">Hasta:</label>
      <input type="date" name="hasta" class="form-control mb-3" value="<?= htmlspecialchars($hasta) ?>">
    </form>
    <form method="get" action="../Logica/exportar_csv.php" class="mb-3">
      <input type="hidden" name="transportista" value="<?= htmlspecialchars($filtro) ?>">
      <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
      <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
      <button type="submit" class="btn btn-success">Exportar a Excel</button>
    </form>
    <a href="../Logica/logout.php" class="btn btn-danger">Cerrar Sesión</a>
  </div>

  <!-- FORMULARIO -->
  <div class="formulario">
    <h2 style="color:#e31f25;">Reporte de Facturas por Transportista</h2>
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>Transportista</th>
            <th>Total de Facturas</th>
            <th>Detalles</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
          <tr>
            <td><?= htmlspecialchars($row['Transportista']) ?></td>
            <td><?= $row['Cantidad'] ?></td>
            <td>
              <div class="dropdown">
                <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                  Ver Facturas — Zona: <?= htmlspecialchars($row['zona']) ?>
                </button>
                <ul class="dropdown-menu">
                  <?php
                    $transportista = $row['Transportista'];
                    $sqlFacturas = "SELECT Factura, Clientes, zona 
                                    FROM custinvoicejour 
                                    WHERE Validar = 'Completada' 
                                      AND Transportista = ? 
                                      AND Fecha_Scanner BETWEEN ? AND ?
                                      AND Factura NOT LIKE 'NC%'";
                    $paramsFacturas = [$transportista, $desdeSQL, $hastaSQL];
                    $stmtFacturas = sqlsrv_query($conn, $sqlFacturas, $paramsFacturas);
                    while ($fact = sqlsrv_fetch_array($stmtFacturas, SQLSRV_FETCH_ASSOC)): ?>
                    <li>
                      <span class="dropdown-item">
                        <strong><?= htmlspecialchars($fact['Factura']) ?></strong> — <?= htmlspecialchars($fact['Clientes']) ?> — <?= htmlspecialchars($fact['zona']) ?>
                      </span>
                    </li>
                  <?php endwhile; ?>
                </ul>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPaginas > 1): ?>
    <nav>
      <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
          <a class="page-link"
             href="?transportista=<?= urlencode($filtro) ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>&pagina=<?= $i ?>">
            <?= $i ?>
          </a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>

<script>
  const form = document.getElementById('formFiltros');
  document.getElementById('inputTransportista').addEventListener('input', function () {
      clearTimeout(this._delay);
      this._delay = setTimeout(() => form.submit(), 500);
  });
  document.querySelector('input[name="desde"]').addEventListener('change', () => form.submit());
  document.querySelector('input[name="hasta"]').addEventListener('change', () => form.submit());
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
