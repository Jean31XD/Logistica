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
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte por Transportista</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* General Body Styling */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        /* Dynamic background gradient for a modern feel */
        background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
        background-size: 400% 400%;
        animation: gradientBG 20s ease infinite;
        font-family: 'Poppins', sans-serif; /* Modern font */
        color: #fff; /* Default text color for contrast */
    }

    /* Animation for the background gradient */
    @keyframes gradientBG {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Main Container Layout */
    .main-container {
        display: flex;
        flex-direction: row-reverse; /* Sidebar on the right */
        height: 100vh;
        padding: 20px 40px;
        gap: 30px;
        width: 100%;
        overflow-x: auto;
        /* Center content if it doesn't take full width */
        justify-content: center;
        align-items: flex-start; /* Align items to the top */
    }

    /* Glassmorphism effect for panels */
    .formulario, .sidebar {
        background: rgba(255, 255, 255, 0.1); /* Semi-transparent white */
        backdrop-filter: blur(12px); /* Blur effect */
        -webkit-backdrop-filter: blur(12px); /* Safari support */
        border: 1px solid rgba(255, 255, 255, 0.2); /* Light border */
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
        padding: 30px;
        overflow-y: auto;
    }

    .formulario {
        flex: 1;
    }

    .sidebar {
        min-width: 300px; /* Ensure sidebar has a minimum width */
        max-width: 350px; /* Optional: set max width */
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .sidebar input,
    .sidebar label,
    .sidebar .form-control {
        width: 100%;
        color: #fff; /* White text for sidebar inputs */
        background-color: rgba(0, 0, 0, 0.2); /* Slightly dark transparent background */
        border: 1px solid rgba(255, 255, 255, 0.3); /* Light border */
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
    }

    .sidebar input::placeholder {
        color: rgba(255, 255, 255, 0.7); /* Lighter placeholder text */
    }

    .sidebar input:focus {
        border-color: #fff;
        box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
        background-color: rgba(0, 0, 0, 0.3);
    }
    
    .sidebar label {
        color: #fff; /* White label text */
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    /* Table Styling */
    .table {
        color: #fff; /* White text for table content */
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table th {
        background-color: #e31f25; /* Red header background */
        color: white;
        font-weight: 600;
        padding: 12px 15px;
        text-align: left;
        border-bottom: 2px solid #b71c1c; /* Darker red border */
    }

    .table td {
        padding: 10px 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2); /* Lighter white border for rows */
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.08); /* Subtle highlight on hover */
    }
    
    .table-responsive {
        border-radius: 12px; /* Apply border-radius to the table container */
        overflow: hidden; /* Ensures content stays within rounded corners */
        background-color: rgba(255, 255, 255, 0.05); /* Very slight background for the table itself */
    }

    /* Button Styling */
    .btn-danger {
        background-color: #e31f25; /* Red button */
        color: white;
        font-weight: bold;
        border-radius: 8px;
        text-decoration: none;
        width: 100%;
        text-align: center;
        padding: 8px 12px; /* Slightly more padding */
        transition: background-color 0.3s ease, transform 0.2s ease;
        border: none; /* Remove default button border */
    }
    .btn-danger:hover {
        background-color: #b71c1c; /* Darker red on hover */
        transform: translateY(-2px); /* Slight lift effect */
        color: white; /* Ensure text remains white */
    }

    .btn-success { /* For "Exportar a Excel" button */
        background-color: #28a745; /* Green for success/export */
        color: white;
        font-weight: bold;
        border-radius: 8px;
        text-decoration: none;
        width: 100%;
        text-align: center;
        padding: 8px 12px;
        transition: background-color 0.3s ease, transform 0.2s ease;
        border: none;
    }
    .btn-success:hover {
        background-color: #218838; /* Darker green on hover */
        transform: translateY(-2px);
        color: white;
    }

    /* Dropdown specific styles */
    .dropdown-menu {
        background-color: #f8f9fa; /* Light background for dropdown */
        border: 1px solid #6c757d;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
    }
    .dropdown-item {
        color: #000; /* Dark text for dropdown items for contrast */
        white-space: normal;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    .dropdown-item:hover, .dropdown-item:focus {
        background-color: #e9ecef; /* Light gray on hover */
        color: #000;
    }
    .btn-outline-danger { /* For "Ver Facturas" button */
        border: 1px solid #e31f25;
        color: #e31f25;
        background-color: transparent;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .btn-outline-danger:hover {
        background-color: #e31f25;
        color: white;
    }

    /* Pagination Styling */
    .pagination .page-link {
        background: transparent;
        border-color: rgba(255,255,255,0.3);
        color: #fff;
        transition: all 0.3s ease;
    }
    .pagination .page-link:hover {
        background-color: rgba(255,255,255,0.1);
        color: #fff;
    }
    .pagination .page-item.active .page-link {
        background-color: #e31f25;
        border-color: #e31f25;
        color: #fff;
        font-weight: bold;
    }
    .pagination .page-item.disabled .page-link {
        background-color: rgba(0,0,0,0.2);
        border-color: rgba(255,255,255,0.2);
        color: rgba(255,255,255,0.5);
        cursor: not-allowed;
    }

    /* Header styling */
    h2 {
        color: #fff !important; /* White text for main heading */
        text-align: center;
        margin-bottom: 25px;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .main-container {
            flex-direction: column; /* Stack sidebar and form vertically */
            height: auto; /* Allow height to expand */
            padding: 15px;
            align-items: center; /* Center items when stacked */
        }
        .sidebar, .formulario {
            width: 100%; /* Full width for both on small screens */
            max-width: none; /* Remove max-width constraint */
        }
        .sidebar {
            order: 1; /* Place sidebar above the form on smaller screens */
            margin-bottom: 20px;
        }
        .formulario {
            order: 2;
        }
    }
</style>
</head>
<body>
<div class="main-container">s
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
