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
// Se buscan los que no están en un estado "final" como 'Despachado' o 'Se fue'
$sqlUpdates = "SELECT l.Tiket, l.NombreTR, f.Cedula, f.Matricula, l.Empresa, l.Asignar, l.Estatus
               FROM [log] l
               LEFT JOIN facebd f ON l.NombreTR = f.Nombres
               WHERE l.FechaModificacion > ? AND l.Estatus NOT IN ('Despachado', 'Se fue')";

$paramsUpdates = [$sinceDate];
$stmtUpdates = sqlsrv_query($conn, $sqlUpdates, $paramsUpdates);

if ($stmtUpdates === false) {
    // No terminar la ejecución, solo loguear el error si es necesario.
    // Enviar una respuesta vacía es mejor que cortar la comunicación.
} else {
    while ($row = sqlsrv_fetch_array($stmtUpdates, SQLSRV_FETCH_ASSOC)) {
        $tiket = htmlspecialchars($row['Tiket']);
        $response['updates'][] = [
            'tiket' => $tiket,
            'html'  => generateRowHtml($row) // Generar el HTML de la fila
        ];
    }
}

// 4. Determinar qué tickets han sido eliminados (despachados, etc.) de la vista
// Comparamos los IDs que el cliente tiene con los que deberían estar activos ahora.
if (!empty($clientTicketIds)) {
    // Creamos placeholders (?) para cada ID para una consulta segura
    $placeholders = implode(',', array_fill(0, count($clientTicketIds), '?'));
    
    $sqlCheckActive = "SELECT Tiket FROM [log] WHERE Tiket IN ($placeholders) AND Estatus NOT IN ('Despachado', 'Se fue')";
    
    $stmtCheckActive = sqlsrv_query($conn, $sqlCheckActive, $clientTicketIds);

    $activeIdsInClientList = [];
    if ($stmtCheckActive) {
        while ($row = sqlsrv_fetch_array($stmtCheckActive, SQLSRV_FETCH_ASSOC)) {
            $activeIdsInClientList[] = $row['Tiket'];
        }
    }
    
    // Los eliminados son los que el cliente tiene en su lista pero ya no están activos
    $response['deletions'] = array_diff($clientTicketIds, $activeIdsInClientList);
}

sqlsrv_close($conn);
echo json_encode($response);


/**
 * Función que genera el HTML para una sola fila de la tabla.
 * Mantener esta lógica en el servidor simplifica el JavaScript.
 */
function generateRowHtml($row) {
    global $usuarioSesion; // Necesitamos el usuario de la sesión para la lógica de deshabilitación
    if (!isset($usuarioSesion)) $usuarioSesion = $_SESSION['usuario'];

    $tiket = htmlspecialchars($row['Tiket']);
    $estatus = htmlspecialchars($row['Estatus']);
    $asignado = trim($row['Asignar']);

    // Lógica para deshabilitar botones
    $isAsignado = !empty($asignado);
    $isRetencion = $estatus === 'Retencion';
    $isAsignadoAOtro = $isAsignado && ($asignado !== $usuarioSesion);

    $claseFila = $isRetencion ? 'table-danger' : '';
    
    $asignarDisabled = $isAsignado ? "disabled title='Ya asignado a $asignado'" : "";
    $despacharDisabled = (!$isAsignado || $isRetencion || $isAsignadoAOtro) ? "disabled" : "";
    $retencionDisabled = (!$isAsignado || $isAsignadoAOtro) ? "disabled" : "";
    $selectDisabled = ($isRetencion || $isAsignadoAOtro) ? "disabled" : "";

    $html = "<tr class='$claseFila animate__animated' id='row_$tiket' data-tiket-id='$tiket'>";
    $html .= "<td>$tiket</td>";
    $html .= "<td>" . htmlspecialchars($row['NombreTR']) .
             "<br><small class='text-white-50'><strong>Cédula:</strong> " . htmlspecialchars($row['Cedula'] ?? 'N/A') .
             "<br><strong>Matrícula:</strong> " . htmlspecialchars($row['Matricula'] ?? 'N/A') . "</small></td>";
    $html .= "<td>" . htmlspecialchars($row['Empresa']) . "</td>";
    $html .= "<td>
        <select class='form-select form-select-sm estatus-select' data-tiket='$tiket' $selectDisabled>
            <option value=' ' " . ($estatus == ' ' ? 'selected' : '') . ">Pendiente</option>
            <option value='Verificación de pedido' " . ($estatus == 'Verificación de pedido' ? 'selected' : '') . ">Verificación</option>
            <option value='Pedido preparandose' " . ($estatus == 'Pedido preparandose' ? 'selected' : '') . ">Preparándose</option>
            <option value='En proceso de empaque' " . ($estatus == 'En proceso de empaque' ? 'selected' : '') . ">Empaque</option>
            <option value='Facturación' " . ($estatus == 'Facturación' ? 'selected' : '') . ">Facturación</option>
            <option value='Retencion' " . ($estatus == 'Retencion' ? 'selected' : '') . ">Retención</option>
        </select>
    </td>";
    $html .= "<td class='asignado-a'>" . ($isAsignado ? htmlspecialchars($asignado) : "<em>No asignado</em>") . "</td>";
    $html .= "<td><button class='btn btn-primary btn-sm btn-asignar' data-tiket='$tiket' $asignarDisabled>Asignar</button></td>";
    $html .= "<td><button class='btn btn-success btn-sm btn-despachar' data-tiket='$tiket' $despacharDisabled>Despachar</button></td>";
    $html .= "<td><button class='btn btn-warning btn-sm btn-retencion' data-tiket='$tiket' $retencionDisabled>Retención</button></td>";
    $html .= "</tr>";

    return $html;
}
?>