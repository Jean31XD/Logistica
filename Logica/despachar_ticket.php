<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// Validar CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($csrf)) {
        http_response_code(403);
        die(json_encode(['error' => 'Token CSRF inválido']));
    }
}

require_once __DIR__ . '/../conexionBD/conexion.php';

if (!$conn) {
    die("Conexión fallida a la base de datos.");
}

$tiket = $_POST['tiket'];
$facturasRaw = trim($_POST['factura']);

// Validación del código para "Se fue" en el servidor
if ($facturasRaw === "Se fue") {
    $codigoSeFue = $_POST['codigo'] ?? '';
    if ($codigoSeFue !== getenv('SE_FUE_CODE')) {
        http_response_code(403);
        echo "Error: Código incorrecto para despachar como 'Se fue'.";
        sqlsrv_close($conn);
        exit();
    }
}

// Si la factura es distinta de "Se fue", validar duplicados y longitud
if ($facturasRaw !== "Se fue") {
    $facturas = array_filter(array_map('trim', explode(';', $facturasRaw)));

    // Validar duplicados en tabla 'analisis'
    foreach ($facturas as $factura) {
        $sqlCheck = "SELECT COUNT(*) AS total FROM analisis WHERE Factura = ?";
        $paramsCheck = [$factura];
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

        if ($stmtCheck === false) {
            error_log("Error SQL en despachar_ticket.php (verificar duplicado): " . print_r(sqlsrv_errors(), true));
            echo "Error interno del servidor al verificar facturas.";
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
    error_log("Error SQL en despachar_ticket.php (actualizar ticket): " . print_r(sqlsrv_errors(), true));
    http_response_code(500);
    echo "Error interno del servidor al actualizar el ticket.";
    sqlsrv_close($conn);
    exit();
}

// Llamar al procedimiento almacenado
$sqlSP = "{CALL SP_Insertar_Analisis2(?)}";
$paramsSP = [$tiket];
$stmtSP = sqlsrv_query($conn, $sqlSP, $paramsSP);

if ($stmtSP === false) {
    error_log("Error SQL en despachar_ticket.php (SP_Insertar_Analisis2): " . print_r(sqlsrv_errors(), true));
    echo "Ticket despachado, pero error al insertar análisis.";
} else {
    echo "Ticket despachado y análisis insertado correctamente.";
}

sqlsrv_close($conn);
?>
