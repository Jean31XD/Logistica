<?php
// --- INICIO DE LÓGICA PHP ---
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();
date_default_timezone_set('America/Santo_Domingo');

if (
    !isset($_SESSION['usuario'], $_SESSION['pantalla']) ||
    !in_array($_SESSION['pantalla'], [0, 3, 4, 5, 6])
) {
    header("Location: ../index.php");
    exit();
}

include '../conexionBD/conexion.php';
if (!$conn) die("Error de conexión: " . print_r(sqlsrv_errors(), true));

if (!isset($_SESSION['pagina_anterior'])) {
    $_SESSION['pagina_anterior'] = $_SERVER['HTTP_REFERER'] ?? 'index.php';
}

function formatDate($dateValue, $format) {
    if (empty($dateValue)) {
        return '';
    }
    try {
        $dateObj = ($dateValue instanceof DateTime) ? $dateValue : new DateTime($dateValue);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return '';
    }
}

$filtroTransportista = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$entregadasCC = isset($_GET['entregadasCC']);
$buscarFactura = $_GET['factura'] ?? '';
$prefijo = $_GET['prefijo'] ?? '';
$zona = $_GET['zona'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    $fechaDesde = new DateTime($desde);
    $fechaHasta = new DateTime($hasta);
} catch (Exception $e) {
    die("Fechas inválidas");
}

$transportistas = [];
$tstmt = sqlsrv_query($conn, "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL AND Transportista NOT LIKE '%Contado%' ORDER BY Transportista");
while ($t = sqlsrv_fetch_array($tstmt, SQLSRV_FETCH_ASSOC)) $transportistas[] = $t['Transportista'];

$usuarios = [];
$ustmt = sqlsrv_query($conn, "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario");
while ($u = sqlsrv_fetch_array($ustmt, SQLSRV_FETCH_ASSOC)) $usuarios[] = $u['Usuario'];

$zonas = [];
$zstmt = sqlsrv_query($conn, "SELECT DISTINCT zona FROM custinvoicejour WHERE zona IS NOT NULL ORDER BY zona");
while ($z = sqlsrv_fetch_array($zstmt, SQLSRV_FETCH_ASSOC)) $zonas[] = $z['zona'];

$where = "WHERE Fecha BETWEEN ? AND ? AND Transportista NOT LIKE '%Contado%'";
$params = [$fechaDesde->format('Y-m-d'), $fechaHasta->format('Y-m-d')];

if ($estado === 'vacio') $where .= " AND (Validar IS NULL OR LTRIM(RTRIM(Validar)) = '')";
elseif (!empty($estado)) { $where .= " AND Validar = ?"; $params[] = $estado; }
if (!empty($usuario)) { $where .= " AND Usuario = ?"; $params[] = $usuario; }
if ($entregadasCC) $where .= " AND Usuario_de_recepcion IS NOT NULL AND LTRIM(RTRIM(Usuario_de_recepcion)) <> ''";
if (!empty($filtroTransportista)) { $where .= " AND Transportista = ?"; $params[] = $filtroTransportista; }
if (!empty($buscarFactura)) { $where .= " AND Factura LIKE ?"; $params[] = '%' . $buscarFactura . '%'; }
if ($prefijo === 'NC') $where .= " AND Factura LIKE 'NC%'";
if ($prefijo === 'FT') $where .= " AND Factura LIKE 'FT%'";
if (!empty($zona)) { $where .= " AND zona = ?"; $params[] = $zona; }

$resumen_sql = "
SELECT
    SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
    SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
    SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
    SUM(CASE WHEN Usuario_de_recepcion IS NOT NULL AND LTRIM(RTRIM(Usuario_de_recepcion)) <> '' THEN 1 ELSE 0 END) AS EntregadasCC,
    SUM(CASE WHEN Factura LIKE 'NC%' THEN 1 ELSE 0 END) AS NC,
    SUM(CASE WHEN Factura LIKE 'FT%' THEN 1 ELSE 0 END) AS FT,
    SUM(CASE WHEN Factura LIKE 'NC%' AND Validar = 'Completada' THEN 1 ELSE 0 END) AS NC_Completadas
FROM custinvoicejour
$where
";
$resumen_stmt = sqlsrv_query($conn, $resumen_sql, $params);
$resumen = sqlsrv_fetch_array($resumen_stmt, SQLSRV_FETCH_ASSOC);
$totalFacturas = ($resumen['Completadas'] ?? 0) + ($resumen['RE'] ?? 0) + ($resumen['SinEstado'] ?? 0);
$noCompletadas = ($resumen['RE'] ?? 0) + ($resumen['SinEstado'] ?? 0);

$count_sql = "SELECT COUNT(*) AS total FROM custinvoicejour $where";
$count_stmt = sqlsrv_query($conn, $count_sql, $params);
$total_rows = sqlsrv_fetch_array($count_stmt)['total'] ?? 0;
$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

$sql = "
SELECT
    Factura, Fecha, Validar AS Estado, Transportista, Fecha_scanner AS Recepcion_ALM,
    Usuario AS Usuario_ALM, recepcion AS Recepcion_CC, Usuario_de_recepcion AS Usuario_CC, zona AS Localizacion
FROM custinvoicejour
$where
ORDER BY Fecha DESC
OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
";
$stmt = sqlsrv_query($conn, $sql, $params);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reporte de Facturas ✨</title>

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
            padding: 1rem;
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
            padding: 1.5rem;
        }

        .main-container { display: flex; gap: 1.5rem; align-items: flex-start; }
        .main-content { flex: 1; }
        .sidebar { width: 350px; position: sticky; top: 1.5rem; }

        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .card-resumen {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border-top: 4px solid;
            transition: transform 0.3s ease;
        }
        .card-resumen:hover { transform: translateY(-5px); }
        .card-resumen .icon { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .card-resumen h5 { font-size: 0.9rem; font-weight: 300; margin-bottom: 0.25rem; opacity: 0.8; }
        .card-resumen p { font-size: 1.75rem; font-weight: 700; margin: 0; }
        
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
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 1.2rem 1rem; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .table tbody tr:last-child td {
             border-bottom: none;
        }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .paginacion a { color: #fff; text-decoration: none; }
        .paginacion .page-link { background: transparent; border-color: rgba(255,255,255,0.3); }
        .paginacion .page-item.active .page-link { background-color: #fff; color: #0d6efd; border-color: #fff;}
        .paginacion .page-item.disabled .page-link { background-color: rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.2);}
        .form-label { font-weight: 600; }
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff !important;
        }
        .select2-container--bootstrap-5 .select2-selection {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.3);
            color: #000 !important;
            height: auto;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #000 !important;
            white-space: normal;
            word-break: break-all;
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
        .select2-results__option--highlighted { background-color: #0d6efd; color: #fff; }
        .btn-link { color: #fff; }

        /* --- ESTILOS RESPONSIVOS --- */
        @media (max-width: 992px) {
            body {
                padding: 0.5rem;
            }
            .main-container {
                flex-direction: column;
                gap: 1rem;
            }
            .sidebar, .main-content {
                width: 100%;
                position: static;
            }
            /* --- MEJORA: Ocultar tabla y paginación en móviles --- */
            .table-container, .paginacion {
                display: none;
            }
        }
        #factura {
    background-color: #f8f9fa; /* Fondo claro */
    color: #000 !important;    /* Texto negro */
}
    </style>
</head>
<body>
<div class="main-container">
    <aside class="sidebar glass-panel animate__animated animate__fadeInLeft">
        <div class="text-center mb-4">
            <img src="../IMG/LOGO MC - BLANCO.png" alt="Logo" style="max-width: 300px; height: auto;">
            <h4 class="mt-3 mb-0">Filtros de Búsqueda</h4>
        </div>
        <form id="filtroForm" method="get" autocomplete="off">
            <div class="row g-3">
                <div class="col-6"><label for="desde" class="form-label">Desde:</label><input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control"></div>
                <div class="col-6"><label for="hasta" class="form-label">Hasta:</label><input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control"></div>
                <div class="col-12"><label for="estado" class="form-label">Estado:</label><select id="estado" name="estado" class="form-select"><option value="">Todos</option><option value="Completada" <?= $estado === 'Completada' ? 'selected' : '' ?>>Completada</option><option value="RE" <?= $estado === 'RE' ? 'selected' : '' ?>>RE</option><option value="vacio" <?= $estado === 'vacio' ? 'selected' : '' ?>>Sin Estado</option></select></div>
                <div class="col-12"><label for="transportista" class="form-label">Transportista:</label><select id="listaTransportistas" name="transportista" class="form-select"><option value="">Todos</option><?php foreach ($transportistas as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label for="usuario" class="form-label">Usuario ALM:</label><select id="usuario" name="usuario" class="form-select"><option value="">Todos</option><?php foreach ($usuarios as $u): ?><option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label for="zona" class="form-label">Localización:</label><select id="zona" name="zona" class="form-select"><option value="">Todas</option><?php foreach ($zonas as $z): ?><option value="<?= htmlspecialchars($z) ?>" <?= $zona === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label for="factura" class="form-label">Factura:</label><input type="text" id="factura" name="factura" value="<?= htmlspecialchars($buscarFactura) ?>" class="form-control" placeholder="Buscar por número"></div>
                <div class="col-12"><label for="prefijo" class="form-label">Prefijo:</label><select id="prefijo" name="prefijo" class="form-select"><option value="">Todos</option><option value="NC" <?= $prefijo === 'NC' ? 'selected' : '' ?>>Solo NC</option><option value="FT" <?= $prefijo === 'FT' ? 'selected' : '' ?>>Solo FT</option></select></div>
                <div class="col-12"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" id="entregadasCC" name="entregadasCC" value="1" <?= $entregadasCC ? 'checked' : '' ?>><label class="form-check-label" for="entregadasCC">Entregadas a CxC</label></div></div>
            </div>
            <div class="d-grid gap-2 mt-4">
                 <a href="BI.php" class="btn btn-outline-light w-100">Limpiar Filtros</a>
            </div>
             <div class="mt-4 text-center">
                 <a href="<?= htmlspecialchars($_SESSION['pagina_anterior']) ?>" class="btn btn-link"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a> | 
                 <a href="../Logica/logout.php" class="btn btn-link">Cerrar Sesión <i class="fa-solid fa-right-from-bracket"></i></a>
             </div>
             <input type="hidden" name="page" value="1">
        </form>
    </aside>

    <main class="main-content glass-panel animate__animated animate__fadeInRight">
        <h2 class="mb-4">Reporte de Facturas</h2>
        
        <div class="resumen-grid">
            <div class="card-resumen animate__animated animate__zoomIn" style="border-color: #0dcaf0;"><div class="icon"><i class="fa-solid fa-file-invoice"></i></div><h5>Total Facturas</h5><p><?= number_format($totalFacturas) ?></p></div>
            <div class="card-resumen animate__animated animate__zoomIn" style="border-color: #198754; animation-delay: 0.1s;"><div class="icon"><i class="fa-solid fa-check-circle"></i></div><h5>Completadas</h5><p><?= number_format($resumen['Completadas'] ?? 0) ?></p></div>
            <div class="card-resumen animate__animated animate__zoomIn" style="border-color: #dc3545; animation-delay: 0.2s;"><div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div><h5>No Completadas</h5><p><?= number_format($noCompletadas) ?></p></div>
            <div class="card-resumen animate__animated animate__zoomIn" style="border-color: #ffc107; animation-delay: 0.3s;"><div class="icon"><i class="fa-solid fa-building-columns"></i></div><h5>Entregadas a CxC</h5><p><?= number_format($resumen['EntregadasCC'] ?? 0) ?></p></div>
            <div class="card-resumen animate__animated animate__zoomIn" style="border-color: #6f42c1; animation-delay: 0.4s;"><div class="icon"><i class="fa-solid fa-file-lines"></i></div><h5>Notas de Crédito</h5><p><?= number_format($resumen['NC'] ?? 0) ?></p></div>
        </div>

        <div class="table-container">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Factura</th><th>Estado</th><th>Transportista</th><th>Recepción ALM</th><th>Usuario ALM</th><th>Recepción CC</th><th>Usuario CC</th><th>Localización</th></tr>
                </thead>
                <tbody>
                    <?php if ($stmt && $total_rows > 0): while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><?= formatDate($row['Fecha'], 'd/m/Y') ?></td>
                        <td><?= htmlspecialchars($row['Factura'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Estado'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Transportista'] ?? '') ?></td>
                        <td><?= formatDate($row['Recepcion_ALM'], 'Y-m-d H:i') ?></td>
                        <td><?= htmlspecialchars($row['Usuario_ALM'] ?? '') ?></td>
                        <td><?= formatDate($row['Recepcion_CC'], 'Y-m-d H:i') ?></td>
                        <td><?= htmlspecialchars($row['Usuario_CC'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Localizacion'] ?? '') ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="9" class="text-center py-4">No se encontraron resultados para los filtros seleccionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_rows > $limit): ?>
        <nav class="paginacion mt-4 d-flex justify-content-center">
            <ul class="pagination">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a></li>
                <li class="page-item active"><span class="page-link">Pág. <?= $page ?> de <?= $total_pages ?></span></li>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    function initializeSelect2(selector, placeholderText) {
        $(selector).select2({
            placeholder: placeholderText,
            allowClear: true,
            theme: 'bootstrap-5'
        });
    }
    initializeSelect2('#listaTransportistas', 'Buscar transportista...');
    initializeSelect2('#usuario', 'Buscar usuario...');
    initializeSelect2('#zona', 'Buscar localización...');

    $('#filtroForm select, #filtroForm input[type="date"], #filtroForm input[type="checkbox"]').on('change', function() {
        $('input[name="page"]').val(1); 
        $('#filtroForm').submit();
    });

    let searchTimeout;
    $('#filtroForm input[type="text"]').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            $('input[name="page"]').val(1); 
            $('#filtroForm').submit();
        }, 500);
    });
});
</script>
</body>
</html>