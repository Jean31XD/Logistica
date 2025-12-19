<?php
require_once __DIR__ . '/../conexionBD/session_config.php';

// Validación de sesión y configuración de headers
verificarAutenticacion();

require_once __DIR__ . '/../conexionBD/conexion.php';

// Sincronizar facturas
$sqlSync = "{CALL SyncCustinvoicejour}";
$stmtSync = sqlsrv_query($conn, $sqlSync);
if ($stmtSync === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error al sincronizar facturas']);
    exit();
}
sqlsrv_free_stmt($stmtSync);

// Parámetros de búsqueda
$transportista = $_POST['transportista'] ?? '';
$desde = $_POST['desde'] ?: '1900-01-01';
$hasta = $_POST['hasta'] ?: '2100-12-31';
$fechaRecibido = $_POST['fechaRecibido'] ?? null;
$fechaRecepcion = $_POST['fechaRecepcion'] ?? null;
$estatus = $_POST['estatus'] ?? '';
$usuario = $_POST['usuario'] ?? '';
$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
$limite = 50;
$offset = ($pagina - 1) * $limite;

// Filtro de búsqueda de factura
$buscarFactura = $_POST['buscarFactura'] ?? '';

// === Total de registros ===
$sqlCount = "SELECT COUNT(*) AS total FROM custinvoicejour WHERE 1=1";
$paramsCount = [];

if ($transportista) {
    $sqlCount .= " AND Transportista = ?";
    $paramsCount[] = $transportista;
}
$sqlCount .= " AND Fecha BETWEEN ? AND ?";
$paramsCount[] = $desde;
$paramsCount[] = $hasta;

if ($fechaRecibido) {
    $sqlCount .= " AND CONVERT(date, Fecha_scanner) = ?";
    $paramsCount[] = $fechaRecibido;
}
if ($fechaRecepcion) {
    $sqlCount .= " AND CONVERT(date, recepcion) = ?";
    $paramsCount[] = $fechaRecepcion;
}
if ($estatus) {
    $sqlCount .= " AND Validar = ?";
    $paramsCount[] = $estatus;
}
if ($usuario) {
    $sqlCount .= " AND Usuario = ?";
    $paramsCount[] = $usuario;
}
if ($buscarFactura) {
    $sqlCount .= " AND Factura LIKE ?";
    $paramsCount[] = "%$buscarFactura%";
}

$stmtCount = sqlsrv_query($conn, $sqlCount, $paramsCount);
$totalFilas = 0;
if ($stmtCount !== false) {
    $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalFilas = $rowCount['total'] ?? 0;
}
$totalPaginas = ceil($totalFilas / $limite);

// === KPIs por transportista ===
$kpiTotal = 0;
$kpiCompletadas = 0;
$kpiPendientes = 0;

// Construir filtros base para KPIs (solo transportista y fechas)
$sqlKpi = "SELECT 
    COUNT(*) AS Total,
    SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
    SUM(CASE WHEN Validar IS NULL OR Validar = '' OR Validar = 'RE' THEN 1 ELSE 0 END) AS Pendientes
FROM custinvoicejour WHERE 1=1";
$paramsKpi = [];

if ($transportista) {
    $sqlKpi .= " AND Transportista = ?";
    $paramsKpi[] = $transportista;
}
$sqlKpi .= " AND Fecha BETWEEN ? AND ?";
$paramsKpi[] = $desde;
$paramsKpi[] = $hasta;

$stmtKpi = sqlsrv_query($conn, $sqlKpi, $paramsKpi);
if ($stmtKpi !== false) {
    $rowKpi = sqlsrv_fetch_array($stmtKpi, SQLSRV_FETCH_ASSOC);
    $kpiTotal = $rowKpi['Total'] ?? 0;
    $kpiCompletadas = $rowKpi['Completadas'] ?? 0;
    $kpiPendientes = $rowKpi['Pendientes'] ?? 0;
}

// === Consulta de datos ===
$sql = "SELECT Factura, Fecha, Validar, Transportista, Fecha_scanner, Usuario, recepcion, Usuario_de_recepcion
        FROM custinvoicejour WHERE 1=1";
$params = [];

if ($transportista) {
    $sql .= " AND Transportista = ?";
    $params[] = $transportista;
}
$sql .= " AND Fecha BETWEEN ? AND ?";
$params[] = $desde;
$params[] = $hasta;

if ($fechaRecibido) {
    $sql .= " AND CONVERT(date, Fecha_scanner) = ?";
    $params[] = $fechaRecibido;
}
if ($fechaRecepcion) {
    $sql .= " AND CONVERT(date, recepcion) = ?";
    $params[] = $fechaRecepcion;
}
if ($estatus) {
    $sql .= " AND Validar = ?";
    $params[] = $estatus;
}
if ($usuario) {
    $sql .= " AND Usuario = ?";
    $params[] = $usuario;
}
if ($buscarFactura) {
    $sql .= " AND Factura LIKE ?";
    $params[] = "%$buscarFactura%";
}

$sql .= " ORDER BY Fecha DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = $limite;
$stmt = sqlsrv_query($conn, $sql, $params);

// === Generar HTML ===
ob_start();

$titulo = $transportista ? "Facturas de " . htmlspecialchars($transportista) : "Facturas Recibidas";

echo "<h3 class='text-center mb-4' style='color: var(--primary); font-weight: 700;'>$titulo</h3>";
echo "<div class='table-facturas'>";
echo "<table class='table table-hover align-middle text-center'>";
echo "<thead><tr>";
echo "<th>Factura</th>";
echo "<th>Fecha</th>";
echo "<th>Transportista</th>";
echo "<th>Estado</th>";
echo "<th>Fecha Recibido Logística</th>";
echo "<th>Usuario Logística</th>";
echo "<th>Fecha Recibido CxC</th>";
echo "<th>Usuario CxC</th>";
echo "</tr></thead>";
echo "<tbody>";

if ($stmt === false) {
    echo "<tr><td colspan='8' class='text-center text-danger'>Error al cargar facturas</td></tr>";
} else {
    $hayDatos = false;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hayDatos = true;
        $factura = htmlspecialchars($row['Factura'] ?? '');
        $fecha = (isset($row['Fecha']) && is_object($row['Fecha'])) ? $row['Fecha']->format('Y-m-d') : ($row['Fecha'] ?? '');
        $validar = trim($row['Validar'] ?? '');
        $estadoNormalizado = strtolower($validar);
        $transportistaRow = htmlspecialchars($row['Transportista'] ?? '');
        $usuarioRow = htmlspecialchars($row['Usuario'] ?? '');

        $fechaScanner = !empty($row['Fecha_scanner']) ? (is_object($row['Fecha_scanner']) ? $row['Fecha_scanner']->format('Y-m-d') : $row['Fecha_scanner']) : '—';
        $recepcion = !empty($row['recepcion']) ? (is_object($row['recepcion']) ? $row['recepcion']->format('Y-m-d') : htmlspecialchars($row['recepcion'])) : '—';
        $usuarioRecepcion = htmlspecialchars($row['Usuario_de_recepcion'] ?? '—');

        $opciones = ['', 'RE'];
        $deshabilitar = ($estadoNormalizado === 'completada') ? 'disabled' : '';
        $class = ($estadoNormalizado === 'completada') ? 'table-success' : '';

        echo "<tr id='fila_$factura' class='$class'>";
        echo "<td>$factura</td>";
        echo "<td>$fecha</td>";
        echo "<td>$transportistaRow</td>";

        // Celda de estado con select
        echo "<td class='celda-estado'>";
        echo "<select class='form-select form-select-sm estado-validar' onchange=\"actualizarEstado('$factura', this.value)\" $deshabilitar>";
        foreach ($opciones as $op) {
            $sel = ($validar === $op) ? 'selected' : '';
            $label = $op ?: '--';
            echo "<option value='$op' $sel>$label</option>";
        }
        if ($estadoNormalizado === 'completada') {
            echo "<option value='completada' selected>Completada</option>";
        }
        echo "</select>";
        echo "</td>";

        echo "<td class='fecha-scanner'>$fechaScanner</td>";
        echo "<td>$usuarioRow</td>";
        echo "<td>$recepcion</td>";
        echo "<td class='celda-usuario-recepcion'>$usuarioRecepcion</td>";
        echo "</tr>";
    }
    if (!$hayDatos) {
        echo "<tr><td colspan='8' class='text-center' style='padding: 3rem; color: var(--text-secondary);'>No se encontraron facturas con los filtros aplicados</td></tr>";
    }
}

echo "</tbody>";
echo "</table>";
echo "</div>";

$html = ob_get_clean();

// === Paginación ===
ob_start();

echo "<div class='d-flex justify-content-center align-items-center gap-2'>";

if ($pagina > 1) {
    $prevPage = $pagina - 1;
    echo "<button class='btn btn-outline-primary' onclick='cargarFacturas($prevPage)'>";
    echo "<i class='fas fa-chevron-left'></i> Anterior";
    echo "</button>";
}

echo "<span class='mx-3' style='font-weight: 600;'>Página $pagina de $totalPaginas</span>";

if ($pagina < $totalPaginas) {
    $nextPage = $pagina + 1;
    echo "<button class='btn btn-outline-primary' onclick='cargarFacturas($nextPage)'>";
    echo "Siguiente <i class='fas fa-chevron-right'></i>";
    echo "</button>";
}

echo "</div>";

$paginacion = ob_get_clean();

// === Devolver JSON ===
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'html' => $html,
    'paginacion' => $paginacion,
    'totalFilas' => $totalFilas,
    'totalPaginas' => $totalPaginas,
    'paginaActual' => $pagina,
    'kpiTotal' => $kpiTotal,
    'kpiCompletadas' => $kpiCompletadas,
    'kpiPendientes' => $kpiPendientes
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

sqlsrv_close($conn);
?>
