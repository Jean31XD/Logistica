<?php 
session_start();
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');


if (!isset($_SESSION['usuario'])) {
    die("Acceso no autorizado.");
}

require_once __DIR__ . '/../conexionBD/conexion.php';

$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

$sql = "SELECT l.Tiket, l.NombreTR, f.Cedula, f.Matricula, l.Empresa, l.Asignar, l.Estatus 
        FROM [log] l
        LEFT JOIN facebd f ON l.NombreTR = f.Nombres";

$result = sqlsrv_query($conn, $sql);

if ($result === false) {
    die("Error al ejecutar la consulta: " . print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $estatus = htmlspecialchars($row['Estatus']);
    $tiket = htmlspecialchars($row['Tiket']);
    $asignado = trim($row['Asignar']);

    $despacharDeshabilitado = (empty($asignado) || $estatus === 'Retencion') 
        ? "disabled title='Debe estar asignado y no en Retención'" 
        : "";

    $retencionDeshabilitado = empty($asignado) 
        ? "disabled title='Debe estar asignado para aplicar retención'" 
        : "";

    if ($estatus === 'Retencion') {
        $claseFila = "table-danger";
        $selectDisabled = "disabled";
    } elseif ($estatus === 'Facturación') {
        $claseFila = "table-success";
        $selectDisabled = "";
    } else {
        $claseFila = "";
        $selectDisabled = "";
    }

    echo "<tr class='$claseFila' id='row_$tiket'>";
    echo "<td>$tiket</td>";

    echo "<td>" . htmlspecialchars($row['NombreTR']) .
         "<br><strong>Cédula:</strong> " . htmlspecialchars($row['Cedula']) .
         "<br><strong>Matrícula:</strong> " . htmlspecialchars($row['Matricula']) . "</td>";

    echo "<td>" . htmlspecialchars($row['Empresa']) . "</td>";

    echo "<td class='estatus'>
        <select class='form-select estatus-select' data-tiket='$tiket' $selectDisabled>
            <option value=' ' " . ($estatus == ' ' ? 'selected' : '') . "> </option>
            <option value='Verificación de pedido' " . ($estatus == 'Verificación de pedido' ? 'selected' : '') . ">Verificación de pedido</option>
            <option value='Pedido preparandose' " . ($estatus == 'Pedido preparandose' ? 'selected' : '') . ">Pedido preparándose</option>
            <option value='En proceso de empaque' " . ($estatus == 'En proceso de empaque' ? 'selected' : '') . ">En proceso de empaque</option>
            <option value='Facturación' " . ($estatus == 'Facturación' ? 'selected' : '') . ">Facturación</option>
        </select>
    </td>";

    echo "<td class='asignado-a'>" . (!empty($asignado) ? htmlspecialchars($asignado) : "No asignado") . "</td>";

    echo "<td><button class='btn btn-primary btn-action' onclick='asignarTicket(\"$tiket\")'>Asignar</button></td>";
    echo "<td><button class='btn btn-danger btn-action btn-despachar' data-tiket='$tiket' $despacharDeshabilitado>Despachar</button></td>";
    echo "<td><button class='btn btn-warning btn-retencion btn-action' data-tiket='$tiket' $retencionDeshabilitado>Retención</button></td>";
    echo "</tr>";
}

sqlsrv_close($conn);
?>
