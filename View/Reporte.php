<?php
// --- INICIO DE LÓGICA PHP ---
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
date_default_timezone_set('America/Santo_Domingo');

// Session access control based on 'pantalla'
if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 4, 5])) {
    header("Location: ../index.php");
    exit();
}

include '../conexionBD/conexion.php';
// Check for database connection
if (!$conn) die("Error de conexión: " . print_r(sqlsrv_errors(), true));

// Initialize filter parameters from GET requests
$filtro = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

// Set default dates if not provided
$hoy = date('Y-m-d');
if (!$desde) $desde = $hoy;
if (!$hasta) $hasta = $hoy;

$desdeSQL = $desde;
$hastaSQL = $hasta;

// --- Query for Total Carriers (for pagination) ---
$sqlTotal = "SELECT COUNT(DISTINCT Transportista) AS total
             FROM custinvoicejour
             WHERE Transportista IS NOT NULL
               AND Validar = 'Completada'
               AND Fecha_Scanner BETWEEN ? AND ?
               AND Factura NOT LIKE 'NC%'"; // Changed to Fecha_Scanner for consistency with main query
$paramsTotal = [$desdeSQL, $hastaSQL];

if ($filtro) {
    $sqlTotal .= " AND Transportista LIKE ?";
    $paramsTotal[] = "%$filtro%";
}

$stmtTotal = sqlsrv_query($conn, $sqlTotal, $paramsTotal);
if ($stmtTotal === false) { // Check for query execution errors
    die("Error al consultar el total de transportistas: " . print_r(sqlsrv_errors(), true));
}
$totalRow = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC);
$totalCarriers = $totalRow['total'] ?? 0; // Safely get total
$totalPaginas = ceil($totalCarriers / $porPagina);

// --- Main Query for Carrier Report ---
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte por Transportista ✨</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            color: #fff;
            padding: 1.5rem;
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
            padding: 2rem;
        }

        .main-container {
            display: flex;
            flex-direction: row-reverse; /* Sidebar on the right */
            gap: 1.5rem;
            align-items: flex-start;
            height: 100vh; /* Ensure full height */
        }
        .main-content { flex: 1; }
        .sidebar { width: 380px; position: sticky; top: 1.5rem; }

        .form-label { font-weight: 600; color: #fff; margin-bottom: 0.5rem;}
        .form-control {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff !important;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-control:focus {
            background-color: rgba(0, 0, 0, 0.3);
            border-color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
            color: #fff;
        }
        
        .btn-red {
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
            color: #fff; /* Ensure header text is white */
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 0.9rem 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff; /* Ensure table body text is white */
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table-responsive {
            overflow-x: auto; /* Ensures table is scrollable on small screens */
            border-radius: 1.5rem; /* Apply border-radius to the container */
        }
        .table-bordered {
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Dropdown specific styles */
        .dropdown-menu {
            background-color: #f8f9fa; /* Light background for dropdown */
            border: 1px solid #6c757d;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .dropdown-item {
            color: #000; /* Dark text for dropdown items */
            white-space: normal;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: #e9ecef; /* Light gray on hover */
            color: #000;
        }
        .dropdown-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }

        .pagination .page-link {
            background: transparent;
            border-color: rgba(255,255,255,0.3);
            color: #fff;
        }
        .pagination .page-item.active .page-link {
            background-color: #e31f25;
            border-color: #e31f25;
            color: #fff;
        }
        .pagination .page-item.disabled .page-link {
            background-color: rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.5);
        }

        /* --- RESPONSIVE STYLES --- */
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
                padding-top: 1.5rem;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <main class="main-content glass-panel animate__animated animate__fadeInRight">
        <h2 class="mb-4 text-center" style="color:#e31f25;">Reporte de Facturas por Transportista</h2>
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
                    <?php if (sqlsrv_has_rows($stmt)): ?>
                        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Transportista']) ?></td>
                            <td><?= $row['Cantidad'] ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-outline-light-red btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Ver Facturas — Zona: <?= htmlspecialchars($row['zona'] ?? 'N/A') ?>
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
                                        if ($stmtFacturas === false) {
                                            echo '<li><span class="dropdown-item text-danger">Error al cargar detalles.</span></li>';
                                        } else if (sqlsrv_has_rows($stmtFacturas)) {
                                            while ($fact = sqlsrv_fetch_array($stmtFacturas, SQLSRV_FETCH_ASSOC)): ?>
                                            <li>
                                                <span class="dropdown-item">
                                                    <strong><?= htmlspecialchars($fact['Factura']) ?></strong> — <?= htmlspecialchars($fact['Clientes']) ?> — <?= htmlspecialchars($fact['zona']) ?>
                                                </span>
                                            </li>
                                            <?php endwhile;
                                        } else {
                                            echo '<li><span class="dropdown-item">No se encontraron facturas.</span></li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">No se encontraron resultados para los filtros seleccionados.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?transportista=<?= urlencode($filtro) ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&pagina=<?= $pagina - 1 ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="?transportista=<?= urlencode($filtro) ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&pagina=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?transportista=<?= urlencode($filtro) ?>&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&pagina=<?= $pagina + 1 ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </main>

    <aside class="sidebar glass-panel animate__animated animate__fadeInLeft">
        <div class="text-center mb-4">
            <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo" style="max-width: 150px; height: auto; margin-bottom: 1.5rem;">
            <h4 class="mt-3 mb-0">Filtros de Reporte</h4>
        </div>
        <form method="get" id="formFiltros" class="w-100">
            <div class="mb-3">
                <label for="inputTransportista" class="form-label">Transportista:</label>
                <input type="text" name="transportista" id="inputTransportista" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($filtro) ?>">
            </div>

            <div class="mb-3">
                <label for="desde" class="form-label">Desde:</label>
                <input type="date" name="desde" id="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
            </div>

            <div class="mb-4">
                <label for="hasta" class="form-label">Hasta:</label>
                <input type="date" name="hasta" id="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
            </div>
            <input type="hidden" name="pagina" value="1"> <div class="d-grid gap-3 mb-4">
                <button type="submit" class="btn btn-red animate__animated animate__pulse animate__infinite"><i class="fas fa-filter"></i> Aplicar Filtros</button>
                <a href="reporte_transportista.php" class="btn btn-outline-light-red"><i class="fas fa-broom"></i> Limpiar Filtros</a>
            </div>
            
            <form method="get" action="../Logica/exportar_csv.php" class="d-grid mb-3">
                <input type="hidden" name="transportista" value="<?= htmlspecialchars($filtro) ?>">
                <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                <button type="submit" class="btn btn-outline-light-red"><i class="fas fa-file-excel"></i> Exportar a Excel</button>
            </form>

            <div class="mt-4 text-center">
                <a href="../Logica/logout.php" class="btn btn-link">Cerrar Sesión <i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </aside>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript for filter submission
    const form = document.getElementById('formFiltros');
    document.getElementById('inputTransportista').addEventListener('input', function () {
        clearTimeout(this._delay);
        this._delay = setTimeout(() => {
            form.querySelector('input[name="pagina"]').value = 1; // Reset page to 1 on filter change
            form.submit();
        }, 500); // Debounce input to prevent too many requests
    });
    document.getElementById('desde').addEventListener('change', () => {
        form.querySelector('input[name="pagina"]').value = 1; // Reset page to 1 on filter change
        form.submit();
    });
    document.getElementById('hasta').addEventListener('change', () => {
        form.querySelector('input[name="pagina"]').value = 1; // Reset page to 1 on filter change
        form.submit();
    });
</script>
</body>
</html>