<?php
$serverName = "sdb-apptransportistas-maco.database.windows.net";
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
    
} else {
    echo "❌ Error de conexión: ";
    print_r(sqlsrv_errors());
}
?>