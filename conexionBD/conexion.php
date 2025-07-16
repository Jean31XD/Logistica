<?php
$serverName = "10.11.0.11";
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