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
    die("Conexión fallida: " . print_r(sqlsrv_errors(), true));
}

$tiket = $_POST['tiket'];
$facturasRaw = trim($_POST['factura']);

// Si la factura es distinta de "Se fue", validar duplicados y longitud
if ($facturasRaw !== "Se fue") {
    $facturas = array_filter(array_map('trim', explode(';', $facturasRaw)));

    // Validar duplicados en tabla 'analisis'
    foreach ($facturas as $factura) {
        $sqlCheck = "SELECT COUNT(*) AS total FROM analisis WHERE Factura = ?";
        $paramsCheck = [$factura];
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

        if ($stmtCheck === false) {
            echo "Error al verificar duplicado de factura: " . print_r(sqlsrv_errors(), true);
            sqlsrv_close($conn);
            exit();
        }

        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        if ($row['total'] > 0) {
            echo "Error: La factura '$factura' ya existe en el análisis.";
            sqlsrv_close($conn);
            exit();
        }
    }

    // Validar longitud 11 caracteres para cada factura
    foreach ($facturas as $f) {
        if (strlen($f) !== 11) {
            echo "Factura inválida (debe tener 11 caracteres): $f";
            sqlsrv_close($conn);
            exit();
        }
    }

    $facturasConcatenadas = implode(';', $facturas);
} else {
    // Si es "Se fue" simplemente guardar ese texto
    $facturasConcatenadas = "Se fue";
}

// Actualizar la tabla 'log' con el estatus y facturas/factura
$sqlUpdate = "UPDATE log SET Estatus = 'Despachado', Factura = ? WHERE Tiket = ?";
$paramsUpdate = [$facturasConcatenadas, $tiket];
$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

if (!$stmtUpdate) {
    echo "Error al actualizar el ticket: " . print_r(sqlsrv_errors(), true);
    sqlsrv_close($conn);
    exit();
}

// Llamar al procedimiento almacenado
$sqlSP = "{CALL SP_Insertar_Analisis2(?)}";
$paramsSP = [$tiket];
$stmtSP = sqlsrv_query($conn, $sqlSP, $paramsSP);

if ($stmtSP === false) {
    echo "Ticket despachado, pero error al ejecutar SP_Insertar_Analisis2: " . print_r(sqlsrv_errors(), true);
} else {
    echo "Ticket despachado y análisis insertado correctamente.";
}

sqlsrv_close($conn);
?>
