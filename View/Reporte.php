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

// --- Excluir transportistas con "Contado" ---
// Modificación para el TOTAL:
$sqlTotal = "SELECT COUNT(DISTINCT Transportista) AS total
             FROM custinvoicejour
             WHERE Transportista IS NOT NULL
               AND Validar = 'Completada'
               AND Fecha BETWEEN ? AND ?
               AND Factura NOT LIKE 'NC%'
               AND Transportista NOT LIKE '%Contado%'"; // <--- Añadido para excluir 'Contado'
$paramsTotal = [$desdeSQL, $hastaSQL];

if ($filtro) {
    // Asegurarse de que el filtro también excluya "Contado" si el usuario no lo especificó
    $sqlTotal .= " AND Transportista LIKE ?";
    $paramsTotal[] = "%" . str_replace("Contado", "", $filtro) . "%"; // Filtrar, pero siempre excluyendo "Contado"
}

$stmtTotal = sqlsrv_query($conn, $sqlTotal, $paramsTotal);
$totalRow = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC);
$totalPaginas = ceil($totalRow['total'] / $porPagina);

// --- Modificación para la consulta PRINCIPAL:
$sql = "SELECT Transportista, COUNT(*) AS Cantidad, MIN(zona) AS zona
        FROM custinvoicejour
        WHERE Transportista IS NOT NULL
          AND Validar = 'Completada'
          AND Fecha_Scanner BETWEEN ? AND ?
          AND Factura NOT LIKE 'NC%'
          AND Transportista NOT LIKE '%Contado%'"; // <--- Añadido para excluir 'Contado'
$params = [$desdeSQL, $hastaSQL];

if ($filtro) {
    // Asegurarse de que el filtro también excluya "Contado" si el usuario no lo especificó
    $sql .= " AND Transportista LIKE ?";
    $params[] = "%" . str_replace("Contado", "", $filtro) . "%"; // Filtrar, pero siempre excluyendo "Contado"
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte por Transportista ✨</title>
    <link rel="icon" href="../IMG/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />

    <style>
        :root {
            --theme-red: #d32f2f; /* Un rojo un poco más oscuro que #e31f25 para consistencia */
            --header-bg-color: #ffffff;
            --header-text-color: #333;
            --glass-panel-bg: rgba(255, 255, 255, 0.1);
            --glass-panel-border: rgba(255, 255, 255, 0.2);
            --glass-panel-shadow: rgba(0, 0, 0, 0.2);
            --table-header-bg: #ffffff;
            --table-header-text: var(--theme-red);
            --table-row-hover: rgba(255, 255, 255, 0.1);
            --form-control-bg: rgba(0, 0, 0, 0.3);
            --form-control-border: rgba(255, 255, 255, 0.4);
            --form-control-text: #fff;
            --form-control-placeholder: rgba(255, 255, 255, 0.6);
            --button-danger-bg: var(--theme-red);
            --button-danger-hover-bg: #b71c1c;
            --button-success-bg: #28a745; /* Verde Bootstrap por defecto */
            --button-success-hover-bg: #218838;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 25s ease infinite;
            color: #fff; /* Texto blanco por defecto */
            padding: 1.5rem;
            min-height: 100vh; /* Asegura que el gradiente ocupe toda la altura */
            display: flex; /* Usamos flexbox para el main-container */
            flex-direction: column; /* Organiza el contenido en columna */
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .main-wrapper {
            display: flex; /* Para el sidebar y el formulario */
            flex-grow: 1; /* Permite que este contenedor crezca y ocupe el espacio restante */
            gap: 2rem; /* Espacio entre el sidebar y el formulario */
            padding-top: 1rem; /* Pequeño padding superior para separar del header */
        }

        /* Panel superior (Header) */
        .header-panel {
            background: var(--header-bg-color);
            color: var(--header-text-color);
            border-radius: 1.5rem;
            padding: 1rem 2rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem; /* Espacio debajo del header */
        }
        .header-panel .logo img {
            height: 60px;
        }
        .header-panel h1 {
            font-weight: 700;
            color: var(--theme-red);
            text-shadow: none;
            margin: 0; /* Eliminar margin por defecto del h1 */
        }

        /* Panel de vidrio (aplicado a sidebar y formulario) */
        .glass-panel {
            background: var(--glass-panel-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-panel-border);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 var(--glass-panel-shadow);
            padding: 1.5rem 2rem;
            box-sizing: border-box; /* Incluir padding en el ancho/alto */
        }

        .sidebar {
            width: 300px;
            flex-shrink: 0; /* Evita que el sidebar se encoja */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem; /* Espacio entre elementos del sidebar */
        }
        .sidebar .form-control,
        .sidebar .btn {
            width: 100%; /* Botones y campos de formulario ocupan el 100% del ancho del sidebar */
        }

        .formulario {
            flex-grow: 1; /* El formulario ocupará el espacio restante */
            overflow-y: auto; /* Permite scroll si el contenido es muy largo */
        }

        .table {
            color: #fff; /* Color de texto blanco para la tabla */
            border-color: rgba(255, 255, 255, 0.2);
            margin-bottom: 1.5rem; /* Espacio debajo de la tabla */
        }
        .table thead th {
            background: var(--table-header-bg);
            color: var(--table-header-text);
            border-color: #dee2e6; /* Borde de Bootstrap */
            font-weight: 700;
            padding: 0.75rem 1rem; /* Espaciado de celdas */
        }
        .table tbody tr {
            transition: background-color 0.3s ease;
        }
        .table tbody tr:hover {
            background-color: var(--table-row-hover);
        }
        .table td {
             padding: 0.75rem 1rem; /* Espaciado de celdas */
             vertical-align: middle; /* Alineación vertical */
        }

        /* Estilo para los inputs y selects dentro de .glass-panel */
        .form-control,
        .form-select {
            background-color: var(--form-control-bg);
            border: 1px solid var(--form-control-border);
            color: var(--form-control-text);
            transition: all 0.3s ease;
        }
        .form-control:focus,
        .form-select:focus {
            background-color: rgba(0, 0, 0, 0.4);
            color: var(--form-control-text);
            border-color: var(--theme-red); /* Resaltar al enfocar */
            box-shadow: 0 0 0 0.25rem rgba(211, 47, 47, 0.25);
        }
        .form-control::placeholder {
            color: var(--form-control-placeholder);
        }

        /* Estilos de botones */
        .btn {
            font-weight: 600;
            border-radius: 0.75rem; /* Bordes más suaves para botones */
            transition: all 0.2s ease;
        }
        .btn-danger {
            background-color: var(--button-danger-bg);
            border-color: var(--button-danger-bg);
        }
        .btn-danger:hover {
            background-color: var(--button-danger-hover-bg);
            border-color: var(--button-danger-hover-bg);
        }
        .btn-success {
            background-color: var(--button-success-bg);
            border-color: var(--button-success-bg);
        }
        .btn-success:hover {
            background-color: var(--button-success-hover-bg);
            border-color: var(--button-success-hover-bg);
        }
        .btn-outline-danger {
            color: var(--theme-red);
            border-color: var(--theme-red);
            background-color: transparent;
        }
        .btn-outline-danger:hover {
            background-color: var(--theme-red);
            color: white;
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            background-color: rgba(10, 25, 40, 0.9); /* Fondo oscuro semitransparente */
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        .dropdown-item {
            color: #fff;
            padding: 0.5rem 1rem;
            white-space: normal; /* Permite que el texto del dropdown se ajuste */
        }
        .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        /* Pagination styles */
        .pagination .page-item .page-link {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            transition: all 0.2s ease;
        }
        .pagination .page-item .page-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--theme-red);
            border-color: var(--theme-red);
            color: white;
            box-shadow: 0 2px 8px rgba(211, 47, 47, 0.4);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .main-wrapper {
                flex-direction: column;
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .sidebar {
                width: 100%;
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header-panel animate__animated animate__fadeInDown">
        <div class="logo"><img src="../IMG/LOGO MC - NEGRO.png" alt="Logo"></div>
        <h1>Reporte de Facturas por Transportista</h1>
        <div>
            <a href="../Logica/logout.php" class="btn btn-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="sidebar glass-panel animate__animated animate__fadeInLeft">
            <h4 style="color: var(--theme-red); font-weight: 600;">Filtros</h4>
            <form method="get" id="formFiltros" class="w-100">
                <div class="mb-3">
                    <label for="inputTransportista" class="form-label text-white">Transportista:</label>
                    <input type="text" name="transportista" id="inputTransportista" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($filtro) ?>">
                </div>

                <div class="mb-3">
                    <label for="desde" class="form-label text-white">Desde:</label>
                    <input type="date" name="desde" id="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
                </div>

                <div class="mb-3">
                    <label for="hasta" class="form-label text-white">Hasta:</label>
                    <input type="date" name="hasta" id="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
                </div>
            </form>
            <form method="get" action="../Logica/exportar_csv.php" class="mb-3 w-100">
                <input type="hidden" name="transportista" value="<?= htmlspecialchars($filtro) ?>">
                <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-file-excel me-2"></i>Exportar a Excel</button>
            </form>
            </div>

        <div class="formulario glass-panel animate__animated animate__fadeInRight">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-truck-moving me-2"></i>Transportista</th>
                            <th><i class="fa-solid fa-file-invoice me-2"></i>Total de Facturas</th>
                            <th><i class="fa-solid fa-info-circle me-2"></i>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Transportista']) ?></td>
                            <td><?= $row['Cantidad'] ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Ver Facturas — Zona: <?= htmlspecialchars($row['zona']) ?>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php
                                            $transportista = $row['Transportista'];
                                            // --- CORRECCIÓN: Filtrar por Fecha_Scanner para consistencia con la consulta principal y excluir "Contado" ---
                                            $sqlFacturas = "SELECT Factura, Clientes, Fecha_Scanner, Fecha_Factura, Recepcion_ALM, Recepcion_CC, zona 
                                                            FROM custinvoicejour 
                                                            WHERE Validar = 'Completada' 
                                                              AND Transportista = ? 
                                                              AND Fecha_Scanner BETWEEN ? AND ?
                                                              AND Factura NOT LIKE 'NC%'
                                                              AND Transportista NOT LIKE '%Contado%'"; // <--- Añadido para excluir 'Contado'
                                            $paramsFacturas = [$transportista, $desdeSQL, $hastaSQL];
                                            $stmtFacturas = sqlsrv_query($conn, $sqlFacturas, $paramsFacturas);
                                            while ($fact = sqlsrv_fetch_array($stmtFacturas, SQLSRV_FETCH_ASSOC)):
                                                // --- Formato de Fechas ---
                                                $fechaFacturaFormatted = ($fact['Fecha_Factura'] instanceof DateTime) ? $fact['Fecha_Factura']->format('d/m/Y') : 'N/A';
                                                $recepcionALMFormatted = ($fact['Recepcion_ALM'] instanceof DateTime) ? $fact['Recepcion_ALM']->format('d/m/Y H:i') : 'N/A'; // O solo 'd/m/Y' si solo quieres la fecha
                                                $recepcionCCFormatted = ($fact['Recepcion_CC'] instanceof DateTime) ? $fact['Recepcion_CC']->format('d/m/Y H:i') : 'N/A'; // O solo 'd/m/Y' si solo quieres la fecha
                                        ?>
                                        <li>
                                            <span class="dropdown-item">
                                                <strong>Factura:</strong> <?= htmlspecialchars($fact['Factura']) ?><br>
                                                <strong>Cliente:</strong> <?= htmlspecialchars($fact['Clientes']) ?><br>
                                                <strong>Zona:</strong> <?= htmlspecialchars($fact['zona']) ?><br>
                                                <strong>Fecha Factura:</strong> <?= $fechaFacturaFormatted ?><br>
                                                <strong>Recepción ALM:</strong> <?= $recepcionALMFormatted ?><br>
                                                <strong>Recepción CC:</strong> <?= $recepcionCCFormatted ?>
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
            <nav aria-label="Page navigation">
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Envío del formulario al cambiar los filtros
    const formFiltros = document.getElementById('formFiltros');
    document.getElementById('inputTransportista').addEventListener('input', function () {
        clearTimeout(this._delay);
        this._delay = setTimeout(() => formFiltros.submit(), 500); // Pequeño retraso para evitar envíos excesivos
    });
    document.getElementById('desde').addEventListener('change', () => formFiltros.submit());
    document.getElementById('hasta').addEventListener('change', () => formFiltros.submit());
</script>
</body>
</html>