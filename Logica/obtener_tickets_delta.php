<?php
// obtener_tickets_delta.php
session_start();
require_once __DIR__ . '/../conexionBD/conexion.php';

$since = $_GET['since'] ?? 0;
$now = time();

// Consulta para tickets nuevos o modificados desde la última revisión
$sql = "SELECT *, CONVERT(bigint, fecha_actualizacion_ts) as ts FROM tickets_en_espera WHERE CONVERT(bigint, fecha_actualizacion_ts) > ?";
$stmt = sqlsrv_query($conn, $sql, [$since]);

$tickets_actualizados = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Generamos el HTML de la fila aquí mismo
    $row['html'] = generarFilaHTML($row); // Usamos una función para mantener el código limpio
    $tickets_actualizados[] = $row;
}

// Consulta para tickets eliminados (despachados)
$sql_del = "SELECT tiket FROM tickets_despachados WHERE CONVERT(bigint, fecha_despacho_ts) > ?";
$stmt_del = sqlsrv_query($conn, $sql_del, [$since]);
$tickets_eliminados = [];
while ($row = sqlsrv_fetch_array($stmt_del, SQLSRV_FETCH_ASSOC)) {
    $tickets_eliminados[] = $row['tiket'];
}

header('Content-Type: application/json');
echo json_encode([
    'tickets' => $tickets_actualizados,
    'eliminados' => $tickets_eliminados,
    'timestamp' => $now
]);


// Función para generar el HTML de una fila (puedes poner esto en un archivo de helpers)
function generarFilaHTML($row) {
    ob_start();
    // Aquí pegas el código exacto que genera una <tr> de tu `obtener_tickets.php` original
    // Ejemplo:
    ?>
    <tr id="row_<?= htmlspecialchars($row['tiket']) ?>" class="<?= $row['estatus'] == 'Retención' ? 'table-danger' : '' ?>">
        <td><?= htmlspecialchars($row['tiket']) ?></td>
        <td><?= htmlspecialchars($row['nombre']) ?></td>
        <td><?= htmlspecialchars($row['empresa']) ?></td>
        <td class="estatus"><?= htmlspecialchars(   $row['estatus']) ?></td>
        <td class="asignado-a"><?= htmlspecialchars($row['asignado_a']) ?></td>
        <td><button class="btn btn-primary btn-sm btn-asignar" data-tiket="<?= htmlspecialchars($row['tiket']) ?>">Asignar</button></td>
        <td><button class="btn btn-success btn-sm btn-despachar" data-tiket="<?= htmlspecialchars($row['tiket']) ?>">Despachar</button></td>
        <td><button class="btn btn-warning btn-sm btn-retencion" data-tiket="<?= htmlspecialchars($row['tiket']) ?>">Retención</button></td>
    </tr>
    <?php
    return ob_get_clean();
}

// NOTA: Para que esto funcione, tu tabla 'tickets_en_espera' necesita una columna 
// 'fecha_actualizacion_ts' de tipo BIGINT que se actualice con time() cada vez que se 
// inserta o modifica una fila. Similarmente, 'tickets_despachados' necesita 'fecha_despacho_ts'.
// Esto se puede lograr con Triggers en la base de datos o en tu lógica de PHP.
?>