<?php
session_start();

// Headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Validación estricta de sesión
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sesión expirada. Por favor, inicia sesión nuevamente.', 'redirect' => true]);
    exit();
}

date_default_timezone_set('America/Santo_Domingo');
include '../conexionBD/conexion.php';

// Sincronizar facturas
$sqlSync = "{CALL SyncCustinvoicejour}";
$stmtSync = sqlsrv_query($conn, $sqlSync);
if ($stmtSync === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error al sincronizar facturas']);
    exit();
}

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

$sql .= " ORDER BY Fecha DESC OFFSET $offset ROWS FETCH NEXT $limite ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql, $params);

// === Generar HTML ===
ob_start();
?>
<h3 class='text-center mb-4' style='color: var(--primary); font-weight: 700;'>
    Facturas <?= $transportista ? "de " . htmlspecialchars($transportista) : "Recibidas" ?>
</h3>

<div class='table-facturas'>
    <table class='table table-hover align-middle text-center'>
        <thead>
            <tr>
                <th>Factura</th>
                <th>Fecha</th>
                <th>Transportista</th>
                <th>Estado</th>
                <th>Fecha Recibido Logística</th>
                <th>Usuario Logística</th>
                <th>Fecha Recibido CxC</th>
                <th>Usuario CxC</th>
            </tr>
        </thead>
        <tbody>
            <?php
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

                    $select = "<select class='form-select form-select-sm estado-validar' onchange=\"actualizarEstado('$factura', this.value)\" $deshabilitar>";
                    foreach ($opciones as $op) {
                        $sel = ($validar === $op) ? 'selected' : '';
                        $label = $op ?: '--';
                        $select .= "<option value='$op' $sel>$label</option>";
                    }
                    if ($estadoNormalizado === 'completada') {
                        $select .= "<option value='completada' selected hidden>Completada</option>";
                    }
                    $select .= "</select>";

                    $class = ($estadoNormalizado === 'completada') ? 'table-success' : '';
                    ?>
                    <tr id='fila_<?= $factura ?>' class='<?= $class ?>'>
                        <td><?= $factura ?></td>
                        <td><?= $fecha ?></td>
                        <td><?= $transportistaRow ?></td>
                        <td class='celda-estado'><?= $select ?></td>
                        <td class='fecha-scanner'><?= $fechaScanner ?></td>
                        <td><?= $usuarioRow ?></td>
                        <td><?= $recepcion ?></td>
                        <td class='celda-usuario-recepcion'><?= $usuarioRecepcion ?></td>
                    </tr>
                    <?php
                }
                if (!$hayDatos) {
                    echo "<tr><td colspan='8' class='text-center' style='padding: 3rem; color: var(--text-secondary);'>No se encontraron facturas con los filtros aplicados</td></tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>
<?php
$html = ob_get_clean();

// === Paginación ===
ob_start();
?>
<div class='d-flex justify-content-center align-items-center gap-2'>
    <?php if ($pagina > 1): ?>
        <button class='btn btn-outline-primary' onclick='cargarFacturas(<?= $pagina - 1 ?>)'>
            <i class='fas fa-chevron-left'></i> Anterior
        </button>
    <?php endif; ?>

    <span class='mx-3' style='font-weight: 600;'>Página <?= $pagina ?> de <?= $totalPaginas ?></span>

    <?php if ($pagina < $totalPaginas): ?>
        <button class='btn btn-outline-primary' onclick='cargarFacturas(<?= $pagina + 1 ?>)'>
            Siguiente <i class='fas fa-chevron-right'></i>
        </button>
    <?php endif; ?>
</div>
<?php
$paginacion = ob_get_clean();

// === Devolver JSON ===
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'paginacion' => $paginacion,
    'totalFilas' => $totalFilas,
    'totalPaginas' => $totalPaginas,
    'paginaActual' => $pagina
]);

sqlsrv_close($conn);
?>
