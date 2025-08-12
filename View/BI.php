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
    if (empty($dateValue)) return '—';
    try {
        $dateObj = ($dateValue instanceof DateTime) ? $dateValue : new DateTime($dateValue);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return 'Fecha Inválida';
    }
}

// --- LÓGICA DE FILTROS (SIN CAMBIOS) ---
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
    COUNT(*) as TotalFacturas,
    SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
    SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
    SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
    SUM(CASE WHEN Usuario_de_recepcion IS NOT NULL AND LTRIM(RTRIM(Usuario_de_recepcion)) <> '' THEN 1 ELSE 0 END) AS EntregadasCC,
    SUM(CASE WHEN Factura LIKE 'NC%' THEN 1 ELSE 0 END) AS NC
FROM custinvoicejour
$where
";
$resumen_stmt = sqlsrv_query($conn, $resumen_sql, $params);
$resumen = sqlsrv_fetch_array($resumen_stmt, SQLSRV_FETCH_ASSOC);
$noCompletadas = ($resumen['RE'] ?? 0) + ($resumen['SinEstado'] ?? 0);

$total_rows = $resumen['TotalFacturas'] ?? 0;
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
    <title>Dashboard de Facturación ✨</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        :root {
            --bs-body-bg: #1a1c23;
            --bs-body-color: #e2e8f0;
            --bs-border-color: #3e4452;
            --bs-primary: #3b82f6;
            --bs-secondary: #475569;
            --bs-success: #22c55e;
            --bs-danger: #ef4444;
            --bs-warning: #f59e0b;
            --bs-info: #38bdf8;
            --bs-light: #334155;
            --bs-dark: #1e293b;
            --font-family-sans-serif: 'Inter', sans-serif;
        }
        body { background-color: var(--bs-body-bg); color: var(--bs-body-color); font-family: var(--font-family-sans-serif); }
        .main-panel { background-color: var(--bs-dark); border-radius: 1rem; padding: 1.5rem; border: 1px solid var(--bs-border-color); }
        
        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        /* Tarjetas de Resumen */
        .card-resumen {
            background-color: var(--bs-dark);
            border-radius: 0.75rem;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 5px solid var(--bs-primary);
            transition: background-color 0.2s ease-in-out;
        }
        .card-resumen:hover { background-color: var(--bs-light); }
        .card-resumen .icon { font-size: 1.75rem; width: 48px; height: 48px; display: grid; place-items: center; border-radius: 50%; background-color: rgba(255,255,255,0.05); }
        .card-resumen h5 { font-size: 0.9rem; font-weight: 500; margin: 0; color: #94a3b8; }
        .card-resumen p { font-size: 1.75rem; font-weight: 700; margin: 0; }

        /* Filtros */
        .accordion-button { background-color: var(--bs-light); color: var(--bs-body-color); }
        .accordion-button:not(.collapsed) { background-color: var(--bs-primary); color: #fff; }
        .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.5); }
        .accordion-body { background-color: var(--bs-dark); }
        .form-control, .form-select, .select2-selection {
            background-color: var(--bs-secondary) !important;
            border: 1px solid var(--bs-border-color) !important;
            color: var(--bs-body-color) !important;
        }
        .select2-dropdown { background-color: #2c3440; border-color: var(--bs-border-color); }
        .select2-results__option { color: var(--bs-body-color); }
        .select2-results__option--highlighted { background-color: var(--bs-primary); }

        /* Tabla */
        .table-responsive { overflow-x: auto; }
        .table { min-width: 1200px; } /* Evita que la tabla se comprima demasiado */
        .table > :not(caption) > * > * { background-color: transparent; border-bottom-color: var(--bs-border-color); vertical-align: middle; }
        .table thead th { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .table tbody td { font-size: 0.9rem; }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
        .factura-id { font-weight: 600; color: var(--bs-info); }
        .badge-status { padding: 0.4em 0.7em; font-size: 0.75rem; font-weight: 600; }
        .badge-completada { background-color: rgba(var(--bs-success-rgb), 0.15); color: var(--bs-success); }
        .badge-re { background-color: rgba(var(--bs-danger-rgb), 0.15); color: var(--bs-danger); }
        .badge-vacio { background-color: rgba(var(--bs-secondary-rgb), 0.15); color: var(--bs-secondary); }

        /* Responsividad de Tabla a Tarjetas */
        @media (max-width: 992px) {
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100% !important; }
            .table tr {
                background-color: var(--bs-dark);
                border-radius: 0.75rem;
                margin-bottom: 1rem;
                padding: 1rem;
                border: 1px solid var(--bs-border-color);
            }
            .table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border: none;
                border-bottom: 1px dashed var(--bs-border-color);
            }
            .table td:last-child { border-bottom: none; }
            .table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #94a3b8;
                margin-right: 1rem;
            }
        }
    </style>
</head>
<body class="p-3 p-md-4">
    <header class="dashboard-header">
        <div>
            <img src="../IMG/LOGO MC - BLANCO.png" alt="Logo" style="height: 40px;">
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="d-none d-md-block">Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
            <a href="../Logica/logout.php" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main>
        <div class="accordion mb-4" id="filtroAccordion">
            <div class="accordion-item" style="border:0; background:transparent;">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="false" aria-controls="collapseFiltros">
                        <i class="fa-solid fa-filter me-2"></i> Opciones de Filtrado
                    </button>
                </h2>
                <div id="collapseFiltros" class="accordion-collapse collapse" data-bs-parent="#filtroAccordion">
                    <div class="accordion-body">
                        <form id="filtroForm" method="get" autocomplete="off">
                            <div class="row g-3">
                                <div class="col-md-6 col-lg-3"><label for="desde" class="form-label">Desde</label><input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control"></div>
                                <div class="col-md-6 col-lg-3"><label for="hasta" class="form-label">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control"></div>
                                <div class="col-md-6 col-lg-3"><label for="factura" class="form-label">Buscar Factura</label><input type="text" id="factura" name="factura" value="<?= htmlspecialchars($buscarFactura) ?>" class="form-control"></div>
                                <div class="col-md-6 col-lg-3"><label for="estado" class="form-label">Estado</label><select id="estado" name="estado" class="form-select"><option value="">Todos</option><option value="Completada" <?= $estado === 'Completada' ? 'selected' : '' ?>>Completada</option><option value="RE" <?= $estado === 'RE' ? 'selected' : '' ?>>RE</option><option value="vacio" <?= $estado === 'vacio' ? 'selected' : '' ?>>Sin Estado</option></select></div>
                                <div class="col-md-6 col-lg-3"><label for="listaTransportistas" class="form-label">Transportista</label><select id="listaTransportistas" name="transportista" class="form-select"><option value="">Todos</option><?php foreach ($transportistas as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6 col-lg-3"><label for="usuario" class="form-label">Usuario ALM</label><select id="usuario" name="usuario" class="form-select"><option value="">Todos</option><?php foreach ($usuarios as $u): ?><option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6 col-lg-3"><label for="zona" class="form-label">Localización</label><select id="zona" name="zona" class="form-select"><option value="">Todas</option><?php foreach ($zonas as $z): ?><option value="<?= htmlspecialchars($z) ?>" <?= $zona === $z ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6 col-lg-3"><label for="prefijo" class="form-label">Prefijo</label><select id="prefijo" name="prefijo" class="form-select"><option value="">Todos</option><option value="NC" <?= $prefijo === 'NC' ? 'selected' : '' ?>>Solo NC</option><option value="FT" <?= $prefijo === 'FT' ? 'selected' : '' ?>>Solo FT</option></select></div>
                                <div class="col-12"><div class="form-check form-switch pt-2"><input class="form-check-input" type="checkbox" id="entregadasCC" name="entregadasCC" value="1" <?= $entregadasCC ? 'checked' : '' ?>><label class="form-check-label" for="entregadasCC">Mostrar solo entregadas a CxC</label></div></div>
                            </div>
                            <hr class="my-4">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="BI.php" class="btn btn-secondary"><i class="fa-solid fa-eraser me-2"></i>Limpiar Filtros</a>
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-2"></i>Aplicar Filtros</button>
                            </div>
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-info)"><div class="icon" style="color:var(--bs-info)"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><h5>Total Facturas</h5><p><?= number_format($total_rows) ?></p></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-success)"><div class="icon" style="color:var(--bs-success)"><i class="fa-solid fa-check-double"></i></div><div><h5>Completadas</h5><p><?= number_format($resumen['Completadas'] ?? 0) ?></p></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-danger)"><div class="icon" style="color:var(--bs-danger)"><i class="fa-solid fa-triangle-exclamation"></i></div><div><h5>No Completadas</h5><p><?= number_format($noCompletadas) ?></p></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card-resumen" style="border-color:var(--bs-warning)"><div class="icon" style="color:var(--bs-warning)"><i class="fa-solid fa-building-columns"></i></div><div><h5>Entregadas a CxC</h5><p><?= number_format($resumen['EntregadasCC'] ?? 0) ?></p></div></div></div>
        </div>

        <div class="main-panel">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Factura</th><th>Fecha</th><th>Estado</th><th>Transportista</th><th>Recepción ALM</th><th>Usuario ALM</th><th>Recepción CC</th><th>Usuario CC</th><th>Localización</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($stmt && $total_rows > 0): while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                            $estadoClase = '';
                            if ($row['Estado'] === 'Completada') $estadoClase = 'badge-completada';
                            elseif ($row['Estado'] === 'RE') $estadoClase = 'badge-re';
                            else $estadoClase = 'badge-vacio';
                        ?>
                        <tr>
                            <td data-label="Factura"><span class="factura-id"><?= htmlspecialchars($row['Factura'] ?? '') ?></span></td>
                            <td data-label="Fecha"><?= formatDate($row['Fecha'], 'd/m/Y') ?></td>
                            <td data-label="Estado"><span class="badge-status <?= $estadoClase ?>"><?= htmlspecialchars($row['Estado'] ?: 'Sin Estado') ?></span></td>
                            <td data-label="Transportista"><?= htmlspecialchars($row['Transportista'] ?? '') ?></td>
                            <td data-label="Recepción ALM"><?= formatDate($row['Recepcion_ALM'], 'd/m/y H:i') ?></td>
                            <td data-label="Usuario ALM"><?= htmlspecialchars($row['Usuario_ALM'] ?? '—') ?></td>
                            <td data-label="Recepción CC"><?= formatDate($row['Recepcion_CC'], 'd/m/y H:i') ?></td>
                            <td data-label="Usuario CC"><?= htmlspecialchars($row['Usuario_CC'] ?? '—') ?></td>
                            <td data-label="Localización"><?= htmlspecialchars($row['Localizacion'] ?? '—') ?></td>
                            <td data-label="Acciones"><button class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fa-solid fa-ellipsis"></i></button></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10" class="text-center py-5">No se encontraron resultados para los filtros seleccionados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <nav class="d-flex justify-content-center pt-4">
                <ul class="pagination">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a></li>
                    <li class="page-item active" aria-current="page"><span class="page-link"><?= $page ?> de <?= $total_pages ?></span></li>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a></li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
    initializeSelect2('#listaTransportistas', 'Seleccionar transportista...');
    initializeSelect2('#usuario', 'Seleccionar usuario...');
    initializeSelect2('#zona', 'Seleccionar localización...');

    // -- Para el envío automático al cambiar un filtro --
    $('#filtroForm select, #filtroForm input[type="date"], #filtroForm input[type="checkbox"]').on('change', function() {
        $('#filtroForm').submit();
    });

    let searchTimeout;
    $('#factura').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            $('#filtroForm').submit();
        }, 500); // Espera 500ms después de que el usuario deja de escribir
    });
    
    // Anular el envío automático si se presiona el botón "Aplicar Filtros"
    $('#filtroForm').on('submit', function(e) {
        $('input[name="page"]').val(1);
    });
});
</script>
</body>
</html>