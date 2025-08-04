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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Recepción de Facturas ✨</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            color: #fff;
            padding: 1.5rem; /* Increased padding for better spacing */
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            padding: 2rem; /* Increased padding */
        }

        .main-container { display: flex; gap: 1.5rem; align-items: flex-start; }
        .main-content { flex: 1; }
        .sidebar { width: 380px; position: sticky; top: 1.5rem; } /* Slightly wider sidebar */

        .table-container { overflow-x: auto; }
        .table {
            color: #fff;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            table-layout: auto;
        }

        .table thead th {
            background: rgba(0, 0, 0, 0.4);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 0.8rem;
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 0.9rem 0.8rem; /* Adjusted padding for table cells */
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table-success { background-color: rgba(25, 135, 84, 0.3) !important; } /* Use a translucent green for success */

        .paginacion a { color: #fff; text-decoration: none; }
        .paginacion .page-link { background: transparent; border-color: rgba(255,255,255,0.3); color: #fff; }
        .paginacion .page-item.active .page-link { background-color: #fff; color: #b71c1c; border-color: #fff;} /* Red from the new design */
        .paginacion .page-item.disabled .page-link { background-color: rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.5);}
        
        .form-label { font-weight: 600; color: #fff; margin-bottom: 0.5rem;}
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff !important;
            padding: 0.75rem 1rem; /* Added padding to form controls */
            border-radius: 0.75rem; /* More rounded corners */
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.3);
            border-color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
            color: #fff;
        }

        .select2-container--bootstrap-5 .select2-selection {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.3);
            color: #000 !important;
            height: auto;
            border-radius: 0.75rem !important; /* Match form control border-radius */
            padding: 0.375rem 1rem;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #000 !important;
            white-space: normal;
            word-break: break-all;
            line-height: calc(1.5em + 0.75rem + 2px); /* Align with input height */
        }
        select option { background-color: #343a40; }
        
        .select2-dropdown {
            background-color: #f8f9fa;
            border: 1px solid #6c757d;
            border-radius: 0.5rem;
            z-index: 1056;
        }
        .select2-results__option {
            color: #000;
            white-space: normal;
        }
        .select2-results__option--highlighted { background-color: #e31f25; color: #fff; } /* Changed highlight color to red */
        
        .btn-red { /* Custom button style for primary actions */
            background-color: #e31f25;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-red:hover {
            background-color: #b71c1c;
            transform: translateY(-2px);
            color: white;
        }

        .btn-outline-light-red {
            border: 1px solid #e31f25;
            color: #e31f25;
            background-color: transparent;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-outline-light-red:hover {
            background-color: #e31f25;
            color: #fff;
        }


        .btn-link { color: #fff; text-decoration: none;}
        .btn-link:hover { color: rgba(255,255,255,0.8); }

        .input-group .btn-red {
            border-radius: 0 0.75rem 0.75rem 0;
            padding: 0.5rem 1rem; /* Adjust padding for button in input group */
        }
        .input-group .form-control:focus {
            z-index: 1; /* Ensure input focus does not overlap button border */
        }
        .input-group > .form-control {
            border-radius: 0.75rem 0 0 0.75rem;
        }

        /* --- ESTILOS RESPONSIVOS --- */
        @media (max-width: 992px) {
            body {
                padding: 1rem;
            }
            .main-container {
                flex-direction: column;
                gap: 1.5rem;
            }
            .sidebar, .main-content {
                width: 100%;
                position: static;
            }
            .sidebar {
                padding-top: 1.5rem; /* Adjust padding for mobile */
            }
            /* Show table and pagination on smaller screens, just make it scrollable */
            .table-container {
                display: block;
            }
            .paginacion {
                display: flex;
                justify-content: center;
            }
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
