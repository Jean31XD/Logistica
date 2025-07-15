<?php
date_default_timezone_set('America/Santo_Domingo');

include '../conexionBD/conexion.php';

$sqlSync = "{CALL SyncCustinvoicejour}";
$stmtSync = sqlsrv_query($conn, $sqlSync);
if ($stmtSync === false) {
    die("Error al sincronizar facturas: " . print_r(sqlsrv_errors(), true));
}

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

// === Render de tabla ===
echo "<h4>Facturas " . ($transportista ? "de " . htmlspecialchars($transportista) : "todas") . "</h4>" ;
echo "<table class='table table-bordered'>";
echo "<thead class='table-light'>
        <tr>
            <th>Factura</th>
            <th>Fecha</th>
            <th>Transportista</th>
            <th>Estado</th>
            <th>Fecha de recibido logistica</th>
            <th>Usuario Logistica</th>
            <th>Fecha de recibido CXC</th>
            <th>Usuario de CXC</th>
        </tr>
      </thead><tbody>";

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $factura = htmlspecialchars($row['Factura'] ?? '');
    $fecha = (isset($row['Fecha']) && is_object($row['Fecha'])) ? $row['Fecha']->format('Y-m-d') : ($row['Fecha'] ?? '');
    $validar = trim($row['Validar'] ?? '');
    $estadoNormalizado = strtolower($validar);
    $transportistaRow = htmlspecialchars($row['Transportista'] ?? '');
    $usuario = htmlspecialchars($row['Usuario'] ?? '');

    $fechaScanner = '';
    if (!empty($row['Fecha_scanner'])) {
        $fechaScanner = is_object($row['Fecha_scanner']) 
            ? $row['Fecha_scanner']->format('Y-m-d') 
            : $row['Fecha_scanner'];
    }
    $recepcion = '';
    if (!empty($row['recepcion'])) {
        $recepcion = is_object($row['recepcion']) 
            ? $row['recepcion']->format('Y-m-d') 
            : htmlspecialchars($row['recepcion']);
    }
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
echo "</tbody></table>";

// === Paginación ===
echo "<div class='mt-3 d-flex justify-content-center'>";
if ($pagina > 1) {
    echo "<button class='btn btn-secondary me-2' onclick='cargarFacturas(" . ($pagina - 1) . ")'>Anterior</button>";
}
echo "<span class='align-self-center'>Página $pagina de $totalPaginas</span>";
if ($pagina < $totalPaginas) {
    echo "<button class='btn btn-secondary ms-2' onclick='cargarFacturas(" . ($pagina + 1) . ")'>Siguiente</button>";
}
echo "</div>";

