<?php
/**
 * API para obtener detalles de una factura específica
 * Devuelve las líneas de la factura y datos principales
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
    // Obtener datos principales de la factura (columnas que sabemos que existen)
    $sqlFactura = "
        SELECT 
            Factura, Fecha, Validar AS Estado, Transportista, 
            Usuario AS Usuario_ALM, Usuario_de_recepcion AS Usuario_CC,
            Fecha_scanner AS Fecha_Recibido, recepcion AS Fecha_Recepcion_CC,
            zona AS Localizacion
        FROM custinvoicejour 
        WHERE Factura = ?";
    
    $stmtFactura = sqlsrv_query($conn, $sqlFactura, [$factura]);
    
    if ($stmtFactura === false) {
        $errors = sqlsrv_errors();
        echo json_encode(['error' => 'Error en consulta principal', 'details' => $errors]);
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
    if (isset($facturaData['Fecha_Recibido']) && $facturaData['Fecha_Recibido'] && is_object($facturaData['Fecha_Recibido'])) {
        $facturaData['Fecha_Recibido'] = $facturaData['Fecha_Recibido']->format('d/m/Y H:i');
    }
    if (isset($facturaData['Fecha_Recepcion_CC']) && $facturaData['Fecha_Recepcion_CC'] && is_object($facturaData['Fecha_Recepcion_CC'])) {
        $facturaData['Fecha_Recepcion_CC'] = $facturaData['Fecha_Recepcion_CC']->format('d/m/Y H:i');
    }
    
    // Obtener líneas de la factura
    $sqlLineas = "
        SELECT 
            itemid AS Codigo,
            itemname AS Descripcion,
            qty AS Cantidad,
            salesunit AS Unidad,
            salespricemst AS Precio,
            linetotalmst AS Total,
            inventlocationid AS Almacen
        FROM Facturas_lineas 
        WHERE invoiceid = ?
        ORDER BY linenum";
    
    $stmtLineas = sqlsrv_query($conn, $sqlLineas, [$factura]);
    
    $lineas = [];
    if ($stmtLineas !== false) {
        while ($row = sqlsrv_fetch_array($stmtLineas, SQLSRV_FETCH_ASSOC)) {
            $lineas[] = $row;
        }
    }
    
    // Calcular totales
    $totalMonto = 0;
    $totalItems = count($lineas);
    foreach ($lineas as $linea) {
        $totalMonto += floatval($linea['Total'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'factura' => $facturaData,
        'lineas' => $lineas,
        'totales' => [
            'items' => $totalItems,
            'monto' => number_format($totalMonto, 2, '.', ',')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

sqlsrv_close($conn);
?>
