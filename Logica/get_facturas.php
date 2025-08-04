<?php
date_default_timezone_set('America/Santo_Domingo');
include '../conexionBD/conexion.php';

echo <<<HTML
<style>
    h4 {
        color: white;
        font-weight: bold;
        margin-bottom: 1rem;
        text-align: center;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        padding: 10px;
        backdrop-filter: blur(10px);
    }


</style>
HTML;

$sqlSync = "{CALL SyncCustinvoicejour}";
$stmtSync = sqlsrv_query($conn, $sqlSync);
if ($stmtSync === false) {
    die("Error al sincronizar facturas: " . print_r(sqlsrv_errors(), true));
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

$buscarFactura = $_POST['buscarFactura'] ?? '';
if ($buscarFactura) {
    $sql .= " AND Factura LIKE ?";
    $params[] = "%$buscarFactura%";
}

$sql .= " ORDER BY Fecha DESC OFFSET $offset ROWS FETCH NEXT $limite ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql, $params);

// === Render HTML ===
echo "<h3 class='titulo-tabla text-white text-center mb-4'>Facturas " . ($transportista ? "de " . htmlspecialchars($transportista) : "Recibidas") . "</h3>";

echo "<div class='table-responsive shadow rounded-4 glass-effect'>";
echo "<table class='table table-bordered table-hover align-middle text-center text-white'>";
echo "<thead class='table-danger'>
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
      </thead><tbody>";

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $factura = htmlspecialchars($row['Factura'] ?? '');
    $fecha = (isset($row['Fecha']) && is_object($row['Fecha'])) ? $row['Fecha']->format('Y-m-d') : ($row['Fecha'] ?? '');
    $validar = trim($row['Validar'] ?? '');
    $estadoNormalizado = strtolower($validar);
    $transportistaRow = htmlspecialchars($row['Transportista'] ?? '');
    $usuario = htmlspecialchars($row['Usuario'] ?? '');

    $fechaScanner = !empty($row['Fecha_scanner']) ? (is_object($row['Fecha_scanner']) ? $row['Fecha_scanner']->format('Y-m-d') : $row['Fecha_scanner']) : '';
    $recepcion = !empty($row['recepcion']) ? (is_object($row['recepcion']) ? $row['recepcion']->format('Y-m-d') : htmlspecialchars($row['recepcion'])) : '';
    $usuarioRecepcion = htmlspecialchars($row['Usuario_de_recepcion'] ?? '');

    $opciones = ['', 'RE'];
    $deshabilitar = ($estadoNormalizado === 'completada') ? 'disabled' : '';

    $select = "<select class='form-select form-select-sm estado-validar' onchange=\"actualizarEstado('$factura', this.value)\" $deshabilitar>";
    foreach ($opciones as $op) {
        $sel = ($validar === $op) ? 'selected' : '';
        $label = $op ?: '--';
        $select .= "<option value='$op' $sel>$label</option>";
    }
    if ($estadoNormalizado === 'completada') {
        $select .= "<option value='completada' selected hidden>completada</option>";
    }
    $select .= "</select>";

    $class = ($estadoNormalizado === 'completada') ? 'table-success' : '';

    echo "<tr id='fila_$factura' class='$class'>
            <td>$factura</td>
            <td>$fecha</td>
            <td>$transportistaRow</td>
            <td class='celda-estado'>$select</td>
            <td class='fecha-scanner'>$fechaScanner</td>
            <td>$usuario</td>
            <td>$recepcion</td>
            <td class='celda-usuario-recepcion'>$usuarioRecepcion</td>
          </tr>";
}
echo "</tbody></table></div>";

// === Paginación ===
echo "<div class='mt-4 d-flex justify-content-center align-items-center'>";
if ($pagina > 1) {
    echo "<button class='btn btn-outline-light me-2' onclick='cargarFacturas(" . ($pagina - 1) . ")'>Anterior</button>";
}
echo "<span class='mx-2'>Página $pagina de $totalPaginas</span>";
if ($pagina < $totalPaginas) {
    echo "<button class='btn btn-outline-light ms-2' onclick='cargarFacturas(" . ($pagina + 1) . ")'>Siguiente</button>";
}
echo "</div>";
