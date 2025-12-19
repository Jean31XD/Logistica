<?php
/**
 * API para obtener detalles de una factura específica
 * Devuelve las líneas de la factura desde Facturas_lineas
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

require_once __DIR__ . '/../conexionBD/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$factura = $_GET['factura'] ?? '';

if (empty($factura)) {
    echo json_encode(['error' => 'Número de factura requerido']);
    exit();
}

if (!$conn) {
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

try {
    // Obtener datos principales de la factura
    $sqlFactura = "
        SELECT 
            Factura, Fecha, Validar, Transportista, 
            Usuario, Usuario_de_recepcion,
            Fecha_scanner, recepcion, zona
        FROM custinvoicejour 
        WHERE Factura = ?";
    
    $stmtFactura = sqlsrv_query($conn, $sqlFactura, [$factura]);
    
    if ($stmtFactura === false) {
        $errors = sqlsrv_errors();
        echo json_encode(['error' => 'Error en consulta custinvoicejour: ' . json_encode($errors)]);
        exit();
    }
    
    $facturaData = sqlsrv_fetch_array($stmtFactura, SQLSRV_FETCH_ASSOC);
    
    if (!$facturaData) {
        echo json_encode(['error' => 'Factura no encontrada: ' . $factura]);
        exit();
    }
    
    // Formatear fechas
    if (isset($facturaData['Fecha']) && $facturaData['Fecha'] && is_object($facturaData['Fecha'])) {
        $facturaData['Fecha'] = $facturaData['Fecha']->format('d/m/Y');
    }
    if (isset($facturaData['Fecha_scanner']) && $facturaData['Fecha_scanner'] && is_object($facturaData['Fecha_scanner'])) {
        $facturaData['Fecha_scanner'] = $facturaData['Fecha_scanner']->format('d/m/Y H:i');
    }
    if (isset($facturaData['recepcion']) && $facturaData['recepcion'] && is_object($facturaData['recepcion'])) {
        $facturaData['recepcion'] = $facturaData['recepcion']->format('d/m/Y H:i');
    }
    
    // Obtener líneas de la factura desde Facturas_lineas
    // Solo columnas que existen: invoiceid, invoicedate, lineamount, lineamounttax, inventlocationid, invoicingname
    $sqlLineas = "
        SELECT 
            invoiceid,
            invoicedate,
            lineamount,
            lineamounttax,
            (lineamount + lineamounttax) AS LineTotal,
            inventlocationid AS Almacen,
            invoicingname AS Cliente
        FROM Facturas_lineas 
        WHERE invoiceid = ?";
    
    $stmtLineas = sqlsrv_query($conn, $sqlLineas, [$factura]);
    
    $lineas = [];
    $totalMonto = 0;
    $totalImpuesto = 0;
    $cliente = '';
    $almacenFactura = '';
    
    if ($stmtLineas !== false) {
        while ($row = sqlsrv_fetch_array($stmtLineas, SQLSRV_FETCH_ASSOC)) {
            $lineas[] = $row;
            $totalMonto += floatval($row['lineamount'] ?? 0);
            $totalImpuesto += floatval($row['lineamounttax'] ?? 0);
            if (empty($cliente) && !empty($row['Cliente'])) {
                $cliente = $row['Cliente'];
            }
            if (empty($almacenFactura) && !empty($row['Almacen'])) {
                $almacenFactura = $row['Almacen'];
            }
        }
    } else {
        $errors = sqlsrv_errors();
        echo json_encode(['error' => 'Error en Facturas_lineas: ' . json_encode($errors)]);
        exit();
    }
    
    // Añadir cliente y almacén al facturaData
    $facturaData['Cliente'] = $cliente;
    $facturaData['Almacen'] = $almacenFactura;
    
    $totalItems = count($lineas);
    $totalConImpuesto = $totalMonto + $totalImpuesto;
    
    echo json_encode([
        'success' => true,
        'factura' => $facturaData,
        'lineas' => $lineas,
        'totales' => [
            'items' => $totalItems,
            'subtotal' => number_format($totalMonto, 2, '.', ','),
            'impuesto' => number_format($totalImpuesto, 2, '.', ','),
            'total' => number_format($totalConImpuesto, 2, '.', ',')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

sqlsrv_close($conn);
?>
