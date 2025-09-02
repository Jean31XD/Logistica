<?php
$serverName = "sdb-apptransportistas-maco.privatelink.database.windows.net";
$database = "db-apptransportistas-maco";
$username = "ServiceAppTrans";
$password = "⁠nZ(#n41LJm)iLmJP";

$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true,
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    // Conexión exitosa (puedes borrar este echo en producción)
    // echo "✅ Conexión establecida."; 
} else {
    echo "❌ Error de conexión: ";
    // die() detiene la ejecución para que no continúe con errores.
    die(print_r(sqlsrv_errors(), true));
}
?>