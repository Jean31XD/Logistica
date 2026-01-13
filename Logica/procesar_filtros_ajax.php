<?php
// Set JSON header FIRST to prevent HTML error pages
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexionBD/session_config.php';

// Verificar autenticación - Enviar JSON si falla
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado - sesión expirada']);
    exit();
}

// === PROTECCIÓN ANTI-SOBRECARGA ===
// Limitar peticiones globales al BI (max 50 req en 10 segundos para TODOS los usuarios)
require_once __DIR__ . '/../conexionBD/global_rate_limiter.php';
if (!checkGlobalRateLimit('procesar_filtros', 50, 10)) {
    GlobalRateLimiter::tooManyRequests('Módulo BI sobrecargado. Reintentar en 5 segundos.');
}

require_once __DIR__ . '/../conexionBD/conexion.php';
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

// Cargar wrapper de queries con protección de timeout
require_once __DIR__ . '/../conexionBD/query_wrapper.php';

// Cargar sistema de caché para consultas repetitivas (solo para resumen)
require_once __DIR__ . '/../conexionBD/cache_manager.php';
$cache = getCache();
$cacheTTL = 60; // 1 minuto de caché para el resumen de BI

// Establecer timeout de bloqueo para evitar queries colgadas (15 segundos)
sqlsrv_query($conn, "SET LOCK_TIMEOUT 15000");

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
$filtroCxC = $_GET['filtroCxC'] ?? ''; // Nuevo filtro CxC

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
if ($filtroCxC === 'si') { $where .= " AND c.recepcion IS NOT NULL"; }
elseif ($filtroCxC === 'no') { $where .= " AND c.recepcion IS NULL"; }

// --- Obtener Resumen y Total (OPTIMIZADO) ---
// OPTIMIZACIÓN: Solo hacer JOIN con Facturas_lineas si filtramos por almacén
if (!empty($almacen)) {
    // Con filtro de almacén - usar LEFT JOIN
    $resumen_sql = "
    SELECT
        COUNT(*) as TotalFacturas,
        SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
        SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
        SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
        SUM(CASE WHEN Usuario_de_recepcion IS NULL OR LTRIM(RTRIM(Usuario_de_recepcion)) = '' THEN 1 ELSE 0 END) AS PendientesCxC
    FROM (
        SELECT DISTINCT c.Factura, c.Validar, c.Usuario_de_recepcion
        FROM custinvoicejour c
        LEFT JOIN (SELECT DISTINCT invoiceid, inventlocationid FROM Facturas_lineas) fl ON c.Factura = fl.invoiceid
        $where
    ) AS FacturasUnicas";
} else {
    // Sin filtro de almacén - consulta directa (mucho más rápida)
    $resumen_sql = "
    SELECT
        COUNT(*) as TotalFacturas,
        SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
        SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
        SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
        SUM(CASE WHEN Usuario_de_recepcion IS NULL OR LTRIM(RTRIM(Usuario_de_recepcion)) = '' THEN 1 ELSE 0 END) AS PendientesCxC
    FROM custinvoicejour c
    $where";
}

// DEBUG: Log SQL for troubleshooting
error_log("BI.php RESUMEN SQL: " . $resumen_sql);
error_log("BI.php PARAMS: " . print_r($params, true));

$resumen_stmt = sqlsrv_query($conn, $resumen_sql, $params);
if ($resumen_stmt === false) {
    $sqlErrors = sqlsrv_errors();
    error_log("BI.php SQL ERROR: " . print_r($sqlErrors, true));
    http_response_code(500);
    echo json_encode(['error' => 'Error en consulta resumen', 'sql_errors' => $sqlErrors, 'sql' => $resumen_sql]);
    exit();
}
$resumen = sqlsrv_fetch_array($resumen_stmt, SQLSRV_FETCH_ASSOC);
$resumen['NoCompletadas'] = ($resumen['RE'] ?? 0) + ($resumen['SinEstado'] ?? 0);
$total_rows = $resumen['TotalFacturas'] ?? 0;
$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

// --- Obtener datos para la tabla (OPTIMIZADO) ---
if (!empty($almacen)) {
    // Con almacén - incluir JOIN
    $sql = "
    SELECT c.Factura, c.Fecha, c.Validar AS Estado, c.Transportista, c.Fecha_scanner AS Recepcion_ALM,
           c.Usuario AS Usuario_ALM, c.recepcion AS Recepcion_CC, c.Usuario_de_recepcion AS Usuario_CC, 
           c.zona AS Localizacion, fl.inventlocationid AS Almacen
    FROM custinvoicejour c
    LEFT JOIN (SELECT DISTINCT invoiceid, inventlocationid FROM Facturas_lineas) fl ON c.Factura = fl.invoiceid
    $where 
    ORDER BY c.Fecha DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
} else {
    // Sin almacén - consulta directa (más rápida)
    $sql = "
    SELECT c.Factura, c.Fecha, c.Validar AS Estado, c.Transportista, c.Fecha_scanner AS Recepcion_ALM,
           c.Usuario AS Usuario_ALM, c.recepcion AS Recepcion_CC, c.Usuario_de_recepcion AS Usuario_CC, 
           c.zona AS Localizacion, c.zona AS Almacen
    FROM custinvoicejour c
    $where 
    ORDER BY c.Fecha DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
}
$params[] = $offset;
$params[] = $limit;
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $sqlErrors = sqlsrv_errors();
    error_log("BI.php TABLA SQL ERROR: " . print_r($sqlErrors, true));
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en consulta tabla', 'sql_errors' => $sqlErrors, 'sql' => $sql]);
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
            <td><?php
                if ($row['Fecha']) {
                    echo is_object($row['Fecha']) ? $row['Fecha']->format('d/m/Y') : date('d/m/Y', strtotime($row['Fecha']));
                } else {
                    echo '—';
                }
            ?></td>
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