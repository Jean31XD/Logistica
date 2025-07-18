<?php 
// Seguridad y sesión
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();
date_default_timezone_set('America/Santo_Domingo');

// Verificar si el usuario está autenticado


if (!isset($_SESSION['usuario'], $_SESSION['pantalla']) || $_SESSION['pantalla'] != 7) {
    header("Location: ../index.php");
    exit();
}


// Conexión a la base de datos
include '../conexionBD/conexion.php';
if (!$conn) die("Error de conexión: " . print_r(sqlsrv_errors(), true));

// Parámetros GET
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

// Validar fechas
try {
    $fechaDesde = new DateTime($desde);
    $fechaHasta = new DateTime($hasta);
} catch (Exception $e) {
    die("Fechas inválidas");
}

// Obtener filtros dinámicos (excluyendo transportistas con "Contado")
$transportistas = [];
$tstmt = sqlsrv_query($conn, "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL AND Transportista NOT LIKE '%Contado%' ORDER BY Transportista");
while ($t = sqlsrv_fetch_array($tstmt, SQLSRV_FETCH_ASSOC)) $transportistas[] = $t['Transportista'];

$usuarios = [];
$ustmt = sqlsrv_query($conn, "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario");
while ($u = sqlsrv_fetch_array($ustmt, SQLSRV_FETCH_ASSOC)) $usuarios[] = $u['Usuario'];

$zonas = [];
$zstmt = sqlsrv_query($conn, "SELECT DISTINCT zona FROM custinvoicejour WHERE zona IS NOT NULL ORDER BY zona");
while ($z = sqlsrv_fetch_array($zstmt, SQLSRV_FETCH_ASSOC)) $zonas[] = $z['zona'];

// WHERE dinámico (excluye todos los que contienen "Contado")
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

// Resumen
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
$totalFacturas = $resumen['Completadas'] + $resumen['RE'] + $resumen['SinEstado'];
$noCompletadas = $resumen['RE'] + $resumen['SinEstado'];

// Conteo y paginación
$count_sql = "SELECT COUNT(*) AS total FROM custinvoicejour $where";
$count_stmt = sqlsrv_query($conn, $count_sql, $params);
$total_rows = sqlsrv_fetch_array($count_stmt)['total'];
$total_pages = ceil($total_rows / $limit);

// Consulta principal
$sql = "
SELECT
    Factura,
    Fecha,
    Validar AS Estado,
    Transportista,
    Fecha_scanner AS Recepcion_ALM,
    Usuario AS Usuario_ALM,
    recepcion AS Recepcion_CC,
    Usuario_de_recepcion AS Usuario_CC,
    zona AS Localizacion
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
    <title>Reporte de Facturas</title>

    <!-- Bootstrap & Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        body {
            background: linear-gradient(to bottom, #ffffff, #e31f25);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            display: flex;
            min-height: 100vh;
            padding: 20px 40px;
            gap: 30px;
        }
        .formulario {
            flex: 1;
            background: #ffffffd9;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            overflow-y: auto;
        }
        .resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .card-resumen {
            border-left: 5px solid #e31f25;
            border-radius: 8px;
            padding: 15px 20px;
            background-color: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .card-resumen h5 {
            font-size: 1rem;
            margin-bottom: 8px;
            color: #e31f25;
        }
        .card-resumen p {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0;
            color: #333;
        }
        .table {
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 0 12px rgba(0,0,0,0.05);
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        .table th,
        .table td {
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
        }
        .table th {
            background-color: #e31f25;
            color: white;
            font-weight: 600;
        }
        .paginacion {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        .paginacion a {
            color: #e31f25;
            text-decoration: none;
            font-weight: 500;
        }
        .paginacion span.actual {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="formulario">
        <h2>Reporte de Facturas</h2>

        <div class="resumen">
            <div class="card-resumen">
                <h5>Total Facturas</h5>
                <p><?= $totalFacturas ?></p>
            </div>
            <div class="card-resumen">
                <h5>✅ Completadas</h5>
                <p><?= $resumen['Completadas'] ?></p>
            </div>
            <div class="card-resumen">
                <h5>⚠️ No Completadas</h5>
                <p><?= $noCompletadas ?></p>
            </div>
            <div class="card-resumen">
                <h5>📨 Entregadas a Créditos y Cobros</h5>
                <p><?= $resumen['EntregadasCC'] ?></p>
            </div>
            <div class="card-resumen">
                <h5>📄 Facturas NC</h5>
                <p><?= $resumen['NC'] ?></p>
            </div>
            <div class="card-resumen">
                <h5>📑 Facturas FT</h5>
                <p><?= $resumen['FT'] ?></p>
            </div>
            <div class="card-resumen">
                <h5>✅ NC Completadas</h5>
                <p><?= $resumen['NC_Completadas'] ?></p>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Factura</th>
                    <th>Estado</th>
                    <th>Transportista</th>
                    <th>Recepción ALM</th>
                    <th>Usuario ALM</th>
                    <th>Recepción CC</th>
                    <th>Usuario CC</th>
                    <th>Localización</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td><?= isset($row['Fecha']) && is_object($row['Fecha']) ? $row['Fecha']->format('Y-m-d') : '' ?></td>
                    <td><?= htmlspecialchars($row['Factura']) ?></td>
                    <td><?= htmlspecialchars($row['Estado']) ?></td>
                    <td><?= htmlspecialchars($row['Transportista']) ?></td>
<td>
    <?php 
    if (!empty($row['Recepcion_ALM']) && is_object($row['Recepcion_ALM'])) {
        echo $row['Recepcion_CC']->format('Y-m-d');
    } elseif (!empty($row['Recepcion_ALM'])) {
        echo date('Y-m-d', strtotime($row['Recepcion_ALM']));
    } else {
        echo '';
    }
    ?>
</td>
                    <td><?= htmlspecialchars($row['Usuario_ALM']) ?></td>
<td>
    <?php 
    if (!empty($row['Recepcion_CC']) && is_object($row['Recepcion_CC'])) {
        echo $row['Recepcion_CC']->format('Y-m-d');
    } elseif (!empty($row['Recepcion_CC'])) {
        echo date('Y-m-d', strtotime($row['Recepcion_CC']));
    } else {
        echo '';
    }
    ?>
</td>
                    <td><?= htmlspecialchars($row['Usuario_CC']) ?></td>
                    <td><?= htmlspecialchars($row['Localizacion']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="paginacion">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Anterior</a>
            <?php endif; ?>

            <span class="actual">Página <?= $page ?> de <?= $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

<!-- PARTE DERECHA RESTAURADA -->
<aside class="sidebar" style="width: 320px;">
    <div class="card" style="background-color: #ffffffdd; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 25px;">
        <form id="filtroForm" method="get" autocomplete="off">
            <h4 class="mb-4 text-center" style="color: #e31f25;">Filtros</h4>

            

            <div class="mb-3">
                <label for="desde" class="form-label">Desde:</label>
                <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" onchange="this.form.submit()" class="form-control">
            </div>

            <div class="mb-3">
                <label for="hasta" class="form-label">Hasta:</label>
                <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" onchange="this.form.submit()" class="form-control">
            </div>

            <div class="mb-3">
                <label for="estado" class="form-label">Estado:</label>
                <select id="estado" name="estado" onchange="this.form.submit()" class="form-select">
                    <option value="">Todos</option>
                    <option value="Completada" <?= $estado === 'Completada' ? 'selected' : '' ?>>Completada</option>
                    <option value="RE" <?= $estado === 'RE' ? 'selected' : '' ?>>RE</option>
                    <option value="vacio" <?= $estado === 'vacio' ? 'selected' : '' ?>>Vacías</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario:</label>
                <select id="usuario" name="usuario" onchange="this.form.submit()" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="entregadasCC" name="entregadasCC" value="1" <?= $entregadasCC ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label" for="entregadasCC">Entregadas a Créditos y Cobros</label>
            </div>

            <div class="mb-3">
                <label for="transportista" class="form-label">Transportista:</label>
                <select id="listaTransportistas" name="transportista" onchange="this.form.submit()" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($transportistas as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="zona" class="form-label">Localización:</label>
                <select id="zona" name="zona" onchange="this.form.submit()" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($zonas as $z): ?>
                        <option value="<?= htmlspecialchars($z) ?>" <?= $zona === $z ? 'selected' : '' ?>>
                            <?= htmlspecialchars($z) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="factura" class="form-label">Factura:</label>
                <input type="text" id="factura" name="factura" value="<?= htmlspecialchars($buscarFactura) ?>" oninput="this.form.submit()" class="form-control" placeholder="Buscar factura">
            </div>

            <div class="mb-3">
                <label for="prefijo" class="form-label">Prefijo:</label>
                <select id="prefijo" name="prefijo" onchange="this.form.submit()" class="form-select">
                    <option value="">Todos</option>
                    <option value="NC" <?= $prefijo === 'NC' ? 'selected' : '' ?>>Solo NC</option>
                    <option value="FT" <?= $prefijo === 'FT' ? 'selected' : '' ?>>Solo FT</option>
                </select>
            </div>
            <div class="mt-4 text-center">
    <div><a href="../Logica/logout.php" class="btn btn-danger">Cerrar Sesión</a></div>
    </div>


            <input type="hidden" name="page" value="1">
        </form>
    </div>
</aside>


</div>

<!-- jQuery y Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('#listaTransportistas').select2({
            placeholder: "Buscar transportista",
            allowClear: true,
            width: 'resolve'
        });

        $('#listaTransportistas').on('change', function() {
            $('#filtroForm').submit();
        });
    });
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
