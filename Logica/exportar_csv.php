<?php 
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

require_once __DIR__ . '/../conexionBD/conexion.php';

$filtro = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=detalle_facturas_transportistas.csv');

echo "xEFxBBxBF"; // BOM para Excel
$output = fopen('php://output', 'w');

// Encabezado actualizado
fputcsv($output, ['Transportista', 'Factura', 'Clientes', 'Fecha Scanner', 'Zona', 'Fecha']);

$paramsTransportistas = [$desde, $hasta];
$sqlTransportistas = "SELECT DISTINCT Transportista
                      FROM custinvoicejour
                      WHERE Transportista IS NOT NULL
                        AND Validar = 'Completada'
                        AND Fecha_Scanner BETWEEN ? AND ?
                        AND Factura NOT LIKE 'NC%'";

if ($filtro) {
    $sqlTransportistas .= " AND Transportista LIKE ?";
    $paramsTransportistas[] = "%$filtro%";
}

$sqlTransportistas .= " ORDER BY Transportista ASC";
$stmtTransportistas = sqlsrv_query($conn, $sqlTransportistas, $paramsTransportistas);

while ($row = sqlsrv_fetch_array($stmtTransportistas, SQLSRV_FETCH_ASSOC)) {
    $transportista = $row['Transportista'];

    $sqlFacturas = "SELECT Factura, Clientes, Fecha_Scanner, zona, Fecha
                    FROM custinvoicejour
                    WHERE Transportista = ?
                      AND Validar = 'Completada'
                      AND Fecha_Scanner BETWEEN ? AND ?
                      AND Factura NOT LIKE 'NC%'";
    $paramsFact = [$transportista, $desde, $hasta];
    $stmtFact = sqlsrv_query($conn, $sqlFacturas, $paramsFact);

    while ($fact = sqlsrv_fetch_array($stmtFact, SQLSRV_FETCH_ASSOC)) {
        // Formateo de Fecha Scanner
        $fechaScanner = $fact['Fecha_Scanner'];
        $fechaFormateada = '';
        if (!empty($fechaScanner)) {
            if ($fechaScanner instanceof DateTime || (is_object($fechaScanner) && method_exists($fechaScanner, 'format'))) {
                $fechaFormateada = $fechaScanner->format('Y-m-d H:i');
            } else {
                $fechaFormateada = (string)$fechaScanner;
            }
        }

        // Formateo de Fecha regular
        $fechaSimple = $fact['Fecha'];
        $fechaOriginal = '';
        if (!empty($fechaSimple)) {
            if ($fechaSimple instanceof DateTime || (is_object($fechaSimple) && method_exists($fechaSimple, 'format'))) {
                $fechaOriginal = $fechaSimple->format('Y-m-d');
            } else {
                $fechaOriginal = (string)$fechaSimple;
            }
        }

        fputcsv($output, [
            $transportista,
            $fact['Factura'],
            $fact['Clientes'],
            $fechaFormateada,
            $fact['zona'] ?? '',
            $fechaOriginal
        ]);
    }
}

fclose($output);
exit;
?>