<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

if (!isset($_SESSION['usuario'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

include '../conexionBD/conexion.php';
if (!$conn) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

// --- Lógica de Filtros (exactamente la misma que tenías) ---
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
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Fechas inválidas']);
    exit();
}

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

// --- Obtener Resumen y Total ---
$resumen_sql = "
SELECT
    COUNT(*) as TotalFacturas,
    SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
    SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
    SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
    SUM(CASE WHEN Usuario_de_recepcion IS NOT NULL AND LTRIM(RTRIM(Usuario_de_recepcion)) <> '' THEN 1 ELSE 0 END) AS EntregadasCC
FROM custinvoicejour $where";
$resumen_stmt = sqlsrv_query($conn, $resumen_sql, $params);
$resumen = sqlsrv_fetch_array($resumen_stmt, SQLSRV_FETCH_ASSOC);
$resumen['NoCompletadas'] = ($resumen['RE'] ?? 0) + ($resumen['SinEstado'] ?? 0);
$total_rows = $resumen['TotalFacturas'] ?? 0;
$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

// --- Obtener datos para la tabla ---
$sql = "
SELECT Factura, Fecha, Validar AS Estado, Transportista, Fecha_scanner AS Recepcion_ALM,
       Usuario AS Usuario_ALM, recepcion AS Recepcion_CC, Usuario_de_recepcion AS Usuario_CC, zona AS Localizacion
FROM custinvoicejour $where ORDER BY Fecha DESC OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql, $params);

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
            <td data-label="Factura"><span class="factura-id"><?= htmlspecialchars($row['Factura'] ?? '') ?></span></td>
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($row['Fecha']->format('Y-m-d'))) ?></td>
            <td data-label="Estado"><span class="badge-status <?= $estadoClase ?>"><?= htmlspecialchars($row['Estado'] ?: 'Sin Estado') ?></span></td>
            <td data-label="Transportista"><?= htmlspecialchars($row['Transportista'] ?? '') ?></td>
            <td data-label="Usuario ALM"><?= htmlspecialchars($row['Usuario_ALM'] ?? '—') ?></td>
            <td data-label="Usuario CC"><?= htmlspecialchars($row['Usuario_CC'] ?? '—') ?></td>
            <td data-label="Localización"><?= htmlspecialchars($row['Localizacion'] ?? '—') ?></td>
        </tr>
        <?php
    }
} else {
    echo '<tr><td colspan="8" class="text-center py-5">No se encontraron resultados.</td></tr>';
}
$tablaHtml = ob_get_clean();

// --- Generar HTML de la paginación ---
ob_start();
if ($total_pages > 1) { ?>
    <ul class="pagination">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= $page - 1 ?>">&laquo;</a>
        </li>
        <li class="page-item active" aria-current="page">
            <span class="page-link"><?= $page ?> de <?= $total_pages ?></span>
        </li>
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= $page + 1 ?>">&raquo;</a>
        </li>
    </ul>
<?php }
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