<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    die(json_encode(["error" => "Acceso no autorizado."]));
}

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/conexion.php';

$connectionInfo = ["Database" => $database, "UID" => $username, "PWD" => $password, "TrustServerCertificate" => true];
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    http_response_code(500);
    die(json_encode(["error" => "Error de conexión a la base de datos."]));
}

// --- Lógica de Actualización Delta ---

// 1. Obtener parámetros del cliente
$sinceTimestamp = isset($_POST['since']) ? (int)$_POST['since'] : 0;
$clientTicketIds = isset($_POST['current_ids']) && is_array($_POST['current_ids']) ? $_POST['current_ids'] : [];

// 2. Preparar la respuesta JSON
$response = [
    'updates'   => [],
    'deletions' => [],
    'timestamp' => time() // Nuevo timestamp para la próxima petición
];

// Convertir el timestamp del cliente a un formato de fecha para SQL Server
$sinceDate = date('Y-m-d H:i:s', $sinceTimestamp);

// 3. Buscar tickets actualizados o nuevos desde la última revisión
$sqlUpdates = "SELECT l.Tiket, l.NombreTR, f.Cedula, f.Matricula, l.Empresa, l.Asignar, l.Estatus
               FROM [log] l
               LEFT JOIN facebd f ON l.NombreTR = f.Nombres
               WHERE l.FechaModificacion > ? AND l.Estatus NOT IN ('Despachado', 'Se fue')"; // Asume que 'Despachado' o 'Se fue' los elimina de la vista

$paramsUpdates = [$sinceDate];
$stmtUpdates = sqlsrv_query($conn, $sqlUpdates, $paramsUpdates);

if ($stmtUpdates === false) {
    http_response_code(500);
    die(json_encode(["error" => "Error al buscar actualizaciones: " . print_r(sqlsrv_errors(), true)]));
}

$updatedTicketIds = [];
while ($row = sqlsrv_fetch_array($stmtUpdates, SQLSRV_FETCH_ASSOC)) {
    $tiket = htmlspecialchars($row['Tiket']);
    $updatedTicketIds[] = $tiket;
    $response['updates'][] = [
        'tiket' => $tiket,
        'html'  => generateRowHtml($row)
    ];
}

// 4. Determinar qué tickets han sido eliminados (despachados, etc.)
// Comparamos los IDs que el cliente tiene con los que deberían estar activos ahora.
if (!empty($clientTicketIds)) {
    // Escapar IDs para seguridad en la consulta IN
    $placeholders = implode(',', array_fill(0, count($clientTicketIds), '?'));
    
    $sqlCheckActive = "SELECT Tiket FROM [log] WHERE Tiket IN ($placeholders) AND l.Estatus NOT IN ('Despachado', 'Se fue')";
    $paramsCheckActive = $clientTicketIds;
    
    $stmtCheckActive = sqlsrv_query($conn, $sqlCheckActive, $paramsCheckActive);

    $activeIdsInClientList = [];
    if ($stmtCheckActive) {
        while ($row = sqlsrv_fetch_array($stmtCheckActive, SQLSRV_FETCH_ASSOC)) {
            $activeIdsInClientList[] = $row['Tiket'];
        }
    }
    
    // Los eliminados son los que el cliente tiene pero ya no están activos
    $response['deletions'] = array_diff($clientTicketIds, $activeIdsInClientList);
}

sqlsrv_close($conn);
echo json_encode($response);


// --- Función para generar el HTML de una fila ---
function generateRowHtml($row) {
    $tiket = htmlspecialchars($row['Tiket']);
    $estatus = htmlspecialchars($row['Estatus']);
    $asignado = trim($row['Asignar']);

    $isAsignado = !empty($asignado);
    $isRetencion = $estatus === 'Retencion';

    $claseFila = $isRetencion ? 'table-danger' : '';
    $asignarDisabled = $isAsignado ? "disabled title='Ya está asignado a $asignado'" : "";
    $despacharDisabled = (!$isAsignado || $isRetencion) ? "disabled title='Debe estar asignado y no en Retención'" : "";
    $retencionDisabled = !$isAsignado ? "disabled title='Debe estar asignado para retener'" : "";

    $html = "<tr class='$claseFila' id='row_$tiket' data-tiket-id='$tiket'>";
    $html .= "<td>$tiket</td>";
    $html .= "<td>" . htmlspecialchars($row['NombreTR']) .
             "<br><small class='text-white-50'><strong>Cédula:</strong> " . htmlspecialchars($row['Cedula']) .
             "<br><strong>Matrícula:</strong> " . htmlspecialchars($row['Matricula']) . "</small></td>";
    $html .= "<td>" . htmlspecialchars($row['Empresa']) . "</td>";
    $html .= "<td>" . (!empty($estatus) ? htmlspecialchars($estatus) : '<em>Pendiente</em>') . "</td>";
    $html .= "<td>" . ($isAsignado ? htmlspecialchars($asignado) : "<em>No asignado</em>") . "</td>";
    $html .= "<td><button class='btn btn-primary btn-sm btn-asignar' data-tiket='$tiket' $asignarDisabled>Asignar</button></td>";
    $html .= "<td><button class='btn btn-success btn-sm btn-despachar' data-tiket='$tiket' $despacharDisabled>Despachar</button></td>";
    $html .= "<td><button class='btn btn-warning btn-sm btn-retencion' data-tiket='$tiket' $retencionDisabled>Retención</button></td>";
    $html .= "</tr>";

    return $html;
}
?>