<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();


require_once __DIR__ . '/../conexionBD/conexion.php';
if (!$conn) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

// --- Lógica de Filtros ---
$filtroTransportista = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$entregadasCC = isset($_GET['entregadasCC']);
$buscarFactura = $_GET['factura'] ?? '';
$prefijo = $_GET['prefijo'] ?? '';
$zona = $_GET['zona'] ?? '';
$almacen = $_GET['almacen'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    $fechaDesde = new DateTime($desde);
    $fechaHasta = new DateTime($hasta);
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Fechas inválidas']);
    exit();
}

// --- Construcción de WHERE para custinvoicejour ---
$where = "WHERE c.Fecha BETWEEN ? AND ? AND c.Transportista NOT LIKE '%Contado%'";
$params = [$fechaDesde->format('Y-m-d'), $fechaHasta->format('Y-m-d')];
if ($estado === 'vacio') $where .= " AND (c.Validar IS NULL OR LTRIM(RTRIM(c.Validar)) = '')";
elseif (!empty($estado)) { $where .= " AND c.Validar = ?"; $params[] = $estado; }
if (!empty($usuario)) { $where .= " AND c.Usuario = ?"; $params[] = $usuario; }
if ($entregadasCC) $where .= " AND c.Usuario_de_recepcion IS NOT NULL AND LTRIM(RTRIM(c.Usuario_de_recepcion)) <> ''";
if (!empty($filtroTransportista)) { $where .= " AND c.Transportista = ?"; $params[] = $filtroTransportista; }
if (!empty($buscarFactura)) { $where .= " AND c.Factura LIKE ?"; $params[] = '%' . $buscarFactura . '%'; }
if ($prefijo === 'NC') $where .= " AND c.Factura LIKE 'NC%'";
if ($prefijo === 'FT') $where .= " AND c.Factura LIKE 'FT%'";
if (!empty($zona)) { $where .= " AND c.zona = ?"; $params[] = $zona; }
if (!empty($almacen)) { $where .= " AND fl.inventlocationid = ?"; $params[] = $almacen; }

// --- Obtener Resumen y Total (CORREGIDO) ---
// PROBLEMA: No se puede usar COUNT(DISTINCT) con SUM(CASE)
// SOLUCIÓN: Usar subquery con facturas únicas filtradas
$resumen_sql = "
SELECT
    COUNT(*) as TotalFacturas,
    SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
    SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
    SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
    SUM(CASE WHEN Usuario_de_recepcion IS NOT NULL AND LTRIM(RTRIM(Usuario_de_recepcion)) <> '' THEN 1 ELSE 0 END) AS EntregadasCC
FROM (
    SELECT DISTINCT c.Factura, c.Validar, c.Usuario_de_recepcion
    FROM custinvoicejour c
    LEFT JOIN (SELECT DISTINCT invoiceid, inventlocationid FROM Facturas_lineas) fl ON c.Factura = fl.invoiceid
    $where
) AS FacturasUnicas";
$resumen_stmt = sqlsrv_query($conn, $resumen_sql, $params);
if ($resumen_stmt === false) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error en consulta resumen', 'sql_errors' => sqlsrv_errors()]);
    exit();
}
$resumen = sqlsrv_fetch_array($resumen_stmt, SQLSRV_FETCH_ASSOC);
$resumen['NoCompletadas'] = ($resumen['RE'] ?? 0) + ($resumen['SinEstado'] ?? 0);
$total_rows = $resumen['TotalFacturas'] ?? 0;
$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

// --- Obtener datos para la tabla (con almacén) ---
$sql = "
SELECT c.Factura, c.Fecha, c.Validar AS Estado, c.Transportista, c.Fecha_scanner AS Recepcion_ALM,
       c.Usuario AS Usuario_ALM, c.recepcion AS Recepcion_CC, c.Usuario_de_recepcion AS Usuario_CC, 
       c.zona AS Localizacion, fl.inventlocationid AS Almacen
FROM custinvoicejour c
LEFT JOIN (SELECT DISTINCT invoiceid, inventlocationid FROM Facturas_lineas) fl ON c.Factura = fl.invoiceid
$where 
ORDER BY c.Fecha DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = $limit;
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error en consulta tabla', 'sql_errors' => sqlsrv_errors()]);
    exit();
}

// --- Generar HTML de la tabla ---
ob_start();
if ($stmt && $total_rows > 0) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $estadoClase = '';
        if ($row['Estado'] === 'Completada') $estadoClase = 'badge-completada';
        elseif ($row['Estado'] === 'RE') $estadoClase = 'badge-re';
        else $estadoClase = 'badge-vacio';
        ?>
        <tr>
            <td><a href="#" class="factura-link"><?= htmlspecialchars($row['Factura'] ?? '') ?></a></td>
            <td><?= $row['Fecha'] ? $row['Fecha']->format('d/m/Y') : '—' ?></td>
            <td><span class="badge-status <?= $estadoClase ?>"><?= htmlspecialchars($row['Estado'] ?: 'Sin Estado') ?></span></td>
            <td><?= htmlspecialchars($row['Transportista'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['Usuario_ALM'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['Usuario_CC'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['Almacen'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['Localizacion'] ?? '—') ?></td>
        </tr>
        <?php
    }
} else {
    echo '<tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-secondary);">No se encontraron resultados con los filtros aplicados.</td></tr>';
}
$tablaHtml = ob_get_clean();

// --- Generar HTML de la paginación ---
ob_start();
if ($total_pages > 1) {
    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);

    if ($page > 1) {
        echo '<button class="page-btn" data-page="' . ($page - 1) . '">← Anterior</button>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<button class="page-btn ' . $active . '" data-page="' . $i . '">' . $i . '</button>';
    }

    if ($page < $total_pages) {
        echo '<button class="page-btn" data-page="' . ($page + 1) . '">Siguiente →</button>';
    }
}
$paginacionHtml = ob_get_clean();


// --- Devolver todos los datos como JSON ---
header('Content-Type: application/json');
echo json_encode([
    'resumen' => $resumen,
    'tablaHtml' => $tablaHtml,
    'paginacionHtml' => $paginacionHtml
]);

sqlsrv_close($conn);
?>