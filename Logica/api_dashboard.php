<?php
// Requerir la conexión a la base de datos
require '../conexionBD/conexion.php'; 
// Establecer el encabezado de respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

// --- Inicialización de variables ---
$response = []; 
$http_code = 200; 

try {
    // --- 1. VERIFICACIÓN DE CONEXIÓN ---
    if (!isset($conn) || $conn === false) {
        throw new Exception('No se pudo establecer la conexión a la base de datos.', 503);
    }

    // --- Obtención y normalización de parámetros ---
    $action = $_GET['action'] ?? '';
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d'); // Default a hoy si está vacío
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d'); // Default a hoy si está vacío
    $almacen = $_GET['almacen'] ?? '';

    if (empty($fecha_inicio)) $fecha_inicio = date('Y-m-d');
    if (empty($fecha_fin)) $fecha_fin = date('Y-m-d');
    
    $fecha_inicio = date('Y-m-d', strtotime($fecha_inicio));
    $fecha_fin = date('Y-m-d', strtotime($fecha_fin));


    // --- 2. CONSTRUCCIÓN DE LA CTE DE FACTURAS ---
    $cte_facturas = "
    WITH Facturas_CTE AS (
        SELECT
            fl.invoiceid,
            fl.inventlocationid, -- Se selecciona directamente el almacén
            
            -- Se agrupan los datos de la factura por almacén
            MAX(CAST(fl.invoicedate AS DATE)) AS invoicedate,
            SUM(fl.lineamount + fl.lineamounttax) AS invoiceamountmst,
            MAX(fl.invoicingname) AS invoicingname
            
        FROM Facturas_lineas fl
        -- Se agrupa por factura Y por almacén para tratar cada parte por separado
        GROUP BY fl.invoiceid, fl.inventlocationid 
    )
    ";

    // --- 3. CONSTRUCCIÓN DE FILTROS DINÁMICOS ---
    $almacenParams = [];
    $almacenSqlAnd = '';
    if (!empty($almacen)) {
        $almacenSqlAnd = " AND f.inventlocationid = ? ";
        $almacenParams[] = $almacen;
    }
    
    // Parámetros base para la mayoría de las consultas
    $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

    // --- 4. PROCESAMIENTO DE LA VISTA SOLICITADA ---
    // MODIFICADO: Solo aceptamos 'getData' o 'getDetails'
    
    if ($action === 'getData') {
        
        $responseData = [
            'kpi' => ['total_emitidas' => 0, 'sin_estado' => 0],
            'statusDistribution' => [],
            'trends' => [],
            'performance' => ['avg_time_to_dispatch' => 0, 'avg_dispatch_to_deliver' => 0, 'avg_total_cycle' => 0],
            'ncReasons' => [],
            'truckPerformance' => [],
            'financials' => ['total_amount' => 0, 'sin_estado_amount' => 0, 'nc_amount' => 0],
            'topClients' => [],
            'topWarehouses' => [],
            'allAlmacenes' => [] // Contenedor para la lista de almacenes
        ];

        // --- Query 1: Overview (KPIs y Distribución de Estados) ---
        $sqlOverview = $cte_facturas . "
            SELECT 
                ISNULL(m.Estado, 'Sin estado') AS Estado,
                COUNT(f.invoiceid) AS Total
            FROM Facturas_CTE f
            LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
            WHERE f.invoicedate BETWEEN ? AND ?
            $almacenSqlAnd
            GROUP BY ISNULL(m.Estado, 'Sin estado')
        ";
        $stmtOverview = sqlsrv_query($conn, $sqlOverview, $baseParams);
        if ($stmtOverview === false) throw new Exception('Error al consultar el resumen de estados.');

        while ($row = sqlsrv_fetch_array($stmtOverview, SQLSRV_FETCH_ASSOC)) {
            $total = (int)$row['Total'];
            $responseData['kpi']['total_emitidas'] += $total;
            if ($row['Estado'] === 'Sin estado') {
                $responseData['kpi']['sin_estado'] = $total;
            }
            // Agregamos todos los estados a la distribución
            $responseData['statusDistribution'][] = ['estado' => $row['Estado'], 'total' => $total];
        }

        // --- Query 2: Tendencias (Trends) ---
        $sqlTrends = $cte_facturas . "
            SELECT 
                f.invoicedate as Dia, 
                COUNT(f.invoiceid) as Total
            FROM Facturas_CTE f
            WHERE f.invoicedate BETWEEN ? AND ?
            $almacenSqlAnd
            GROUP BY f.invoicedate
            ORDER BY Dia ASC
        ";
        $stmtTrends = sqlsrv_query($conn, $sqlTrends, $baseParams);
        if ($stmtTrends === false) throw new Exception('Error al consultar tendencias.');
        while($row = sqlsrv_fetch_array($stmtTrends, SQLSRV_FETCH_ASSOC)) {
            if ($row['Dia'] instanceof DateTime) {
                $row['Dia'] = $row['Dia']->format('Y-m-d');
            }
            $responseData['trends'][] = $row;
        }

        // --- Query 3: KPIs de Rendimiento (Performance) ---
        $sqlPerfKpis = $cte_facturas . "
            SELECT 
                AVG(CAST(DATEDIFF(hour, f.invoicedate, m.Fecha_de_Despacho) AS FLOAT)) AS AvgTimeToDispatch,
                AVG(CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT)) AS AvgDispatchToDeliver,
                AVG(CAST(DATEDIFF(hour, f.invoicedate, m.Fecha_de_Entregado) AS FLOAT)) AS AvgTotalCycle
            FROM Facturas_CTE f
            JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
            WHERE f.invoicedate BETWEEN ? AND ?
                AND m.Fecha_de_Despacho IS NOT NULL
                AND m.Fecha_de_Entregado IS NOT NULL
                $almacenSqlAnd
        ";
        $stmtPerfKpis = sqlsrv_query($conn, $sqlPerfKpis, $baseParams);
        if ($stmtPerfKpis === false) throw new Exception('Error al calcular KPIs de rendimiento.');
        $perfKpisRow = sqlsrv_fetch_array($stmtPerfKpis, SQLSRV_FETCH_ASSOC);
        if ($perfKpisRow) {
            $responseData['performance']['avg_time_to_dispatch'] = $perfKpisRow['AvgTimeToDispatch'];
            $responseData['performance']['avg_dispatch_to_deliver'] = $perfKpisRow['AvgDispatchToDeliver'];
            $responseData['performance']['avg_total_cycle'] = $perfKpisRow['AvgTotalCycle'];
        }

        // --- Query 4: Razones NC (Performance) ---
        $sqlNc = $cte_facturas . "
            SELECT 
                ISNULL(m.Motivo_NC, 'No especificado') as motivo_nc,
                COUNT(m.No_Factura) as total
            FROM Factura_Programa_Despacho_MACOR m
            JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
            WHERE m.Estado = 'NC' 
                AND f.invoicedate BETWEEN ? AND ?
                $almacenSqlAnd
            GROUP BY ISNULL(m.Motivo_NC, 'No especificado')
            ORDER BY total DESC
        ";
        $stmtNc = sqlsrv_query($conn, $sqlNc, $baseParams);
        if ($stmtNc === false) throw new Exception('Error al analizar motivos de NC.');
        while($row = sqlsrv_fetch_array($stmtNc, SQLSRV_FETCH_ASSOC)) {
            $responseData['ncReasons'][] = $row;
        }

        // --- Query 5: Top Camiones (Performance) ---
        $sqlTrucks = $cte_facturas . "
            SELECT TOP 5
                m.Camion,
                COUNT(m.No_Factura) as total_entregas
            FROM Factura_Programa_Despacho_MACOR m
            JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
            WHERE m.Estado = 'ENTREGado' 
                AND m.Camion IS NOT NULL AND m.Camion <> ''
                AND f.invoicedate BETWEEN ? AND ?
                $almacenSqlAnd
            GROUP BY m.Camion
            ORDER BY total_entregas DESC
        ";
        $stmtTrucks = sqlsrv_query($conn, $sqlTrucks, $baseParams);
        if ($stmtTrucks === false) throw new Exception('Error al analizar rendimiento de camiones.');
        while($row = sqlsrv_fetch_array($stmtTrucks, SQLSRV_FETCH_ASSOC)) {
            $responseData['truckPerformance'][] = $row;
        }

        // --- Query 6: KPIs Financieros (Financial) ---
        $sqlFinKpis = $cte_facturas . "
            SELECT 
                ISNULL(SUM(f.invoiceamountmst), 0) AS totalAmount,
                ISNULL(SUM(CASE WHEN m.No_Factura IS NULL THEN f.invoiceamountmst ELSE 0 END), 0) AS sinEstadoAmount,
                ISNULL(SUM(CASE WHEN m.Estado = 'NC' OR LEFT(f.invoiceid, 2) = 'NC' THEN f.invoiceamountmst ELSE 0 END), 0) AS ncAmount
            FROM Facturas_CTE f
            LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
            WHERE f.invoicedate BETWEEN ? AND ?
            $almacenSqlAnd
        ";
        $stmtFinKpis = sqlsrv_query($conn, $sqlFinKpis, $baseParams);
        if ($stmtFinKpis === false) throw new Exception('Error al calcular KPIs financieros.');
        $finKpisRow = sqlsrv_fetch_array($stmtFinKpis, SQLSRV_FETCH_ASSOC);
        if ($finKpisRow) {
            $responseData['financials']['total_amount'] = $finKpisRow['totalAmount'];
            $responseData['financials']['sin_estado_amount'] = $finKpisRow['sinEstadoAmount'];
            $responseData['financials']['nc_amount'] = $finKpisRow['ncAmount'];
        }

        // --- Query 7: Top Clientes (Financial) ---
        $sqlClients = $cte_facturas . "
            SELECT TOP 10
                f.invoicingname AS cliente,
                SUM(f.invoiceamountmst) AS monto_total
            FROM Facturas_CTE f
            WHERE f.invoicedate BETWEEN ? AND ?
            $almacenSqlAnd
            GROUP BY f.invoicingname
            ORDER BY monto_total DESC
        ";
        $stmtClients = sqlsrv_query($conn, $sqlClients, $baseParams);
        if ($stmtClients === false) throw new Exception('Error al obtener top clientes.');
        while($row = sqlsrv_fetch_array($stmtClients, SQLSRV_FETCH_ASSOC)) {
            $responseData['topClients'][] = $row;
        }

        // --- Query 8: Top Almacenes (Financial) ---
        $sqlWarehouses = $cte_facturas . "
            SELECT TOP 10
                f.inventlocationid AS almacen,
                SUM(f.invoiceamountmst) AS monto_total
            FROM Facturas_CTE f
            WHERE f.invoicedate BETWEEN ? AND ?
                AND f.inventlocationid IS NOT NULL AND f.inventlocationid <> ''
                $almacenSqlAnd
            GROUP BY f.inventlocationid
            ORDER BY monto_total DESC
        ";
        $stmtWarehouses = sqlsrv_query($conn, $sqlWarehouses, $baseParams); 
        if ($stmtWarehouses === false) throw new Exception('Error al obtener top almacenes.');
        while($row = sqlsrv_fetch_array($stmtWarehouses, SQLSRV_FETCH_ASSOC)) {
            $responseData['topWarehouses'][] = $row;
        }
        
        // --- Query 9: Lista de Almacenes (para el filtro) ---
        $sqlAlmacenes = "
            SELECT DISTINCT inventlocationid as almacen 
            FROM Facturas_lineas 
            WHERE inventlocationid IS NOT NULL AND inventlocationid <> ''
            ORDER BY inventlocationid ASC
        ";
        $stmtAlmacenes = sqlsrv_query($conn, $sqlAlmacenes);
        if ($stmtAlmacenes === false) throw new Exception('Error al obtener la lista de almacenes.');
        while ($row = sqlsrv_fetch_array($stmtAlmacenes, SQLSRV_FETCH_ASSOC)) {
            $responseData['allAlmacenes'][] = $row;
        }

        // --- Enviar respuesta combinada ---
        $response = [
            'success' => true,
            'data' => $responseData
        ];

    } elseif ($action === 'getDetails') {
        // --- ESTA LÓGICA DE 'details' YA ESTABA BIEN, SOLO CAMBIAMOS EL 'view' POR 'action' ---
        
        $estado = isset($_GET['estado']) ? trim(urldecode($_GET['estado'])) : '';
        if (empty($estado) || empty($fecha_inicio) || empty($fecha_fin)) {
            throw new Exception('Faltan parámetros (estado, fecha_inicio, fecha_fin) para obtener los detalles.', 400);
        }
        
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;

        $whereSql = "";
        $countParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
        $detailsParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
        $orderBy = "f.invoicedate DESC"; 

        // Renombramos 'ALL' a 'TOTAL' y 'Sin estado' a 'SIN_ESTADO' para que coincida con el JS
        if ($estado === 'TOTAL') { 
            $whereSql = " WHERE f.invoicedate BETWEEN ? AND ? $almacenSqlAnd ";
        } elseif ($estado === 'SIN_ESTADO') {
            $whereSql = " 
                WHERE m.No_Factura IS NULL 
                AND f.invoicedate BETWEEN ? AND ? 
                $almacenSqlAnd
            ";
        } else {
            $whereSql = " 
                WHERE m.Estado = ? 
                AND f.invoicedate BETWEEN ? AND ? 
                $almacenSqlAnd
            ";
            array_unshift($countParams, $estado);
            array_unshift($detailsParams, $estado);
            $orderBy = "m.Fecha_de_Registro DESC";
        }

        $sqlCount = $cte_facturas . "
            SELECT COUNT(f.invoiceid) AS Total
            FROM Facturas_CTE f
            LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
            $whereSql
        ";
        $sqlDetails = $cte_facturas . "
            SELECT 
                f.invoiceid AS NoFactura, f.invoicedate AS FechaRegistro, f.invoicingname AS Cliente, f.invoiceamountmst AS Monto,
                m.Registrado_por AS RegistradoPor, m.Camion, m.Fecha_de_Despacho AS FechaDespacho, m.Despachado_por AS DespachadoPor, 
                m.Fecha_de_Entregado AS FechaEntregado, m.Entregado_por AS EntregadoPor, 
                ISNULL(m.Estado, 'Sin estado') AS Estado, m.Fecha_Reversada AS FechaReversada, 
                m.Reversado_Por AS ReversadoPor, m.Fecha_de_NC AS FechaNC, m.NC_Realizado_Por AS NCRealizadoPor, 
                m.Motivo_NC AS MotivoNC, m.Camion2
            FROM Facturas_CTE f
            LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
            $whereSql
            ORDER BY $orderBy
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ";

        $stmtCount = sqlsrv_query($conn, $sqlCount, $countParams);
        if ($stmtCount === false) throw new Exception('Error en la consulta de conteo de detalles.');
        $totalRecords = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['Total'] ?? 0;
        
        $detailsData = [];
        if ($totalRecords > 0) {
            $detailsParams = array_merge($detailsParams, [$offset, $limit]);
            $stmtDetails = sqlsrv_query($conn, $sqlDetails, $detailsParams);
            if ($stmtDetails === false) throw new Exception('Error en la consulta de obtención de detalles.');
            while ($row = sqlsrv_fetch_array($stmtDetails, SQLSRV_FETCH_ASSOC)) {
                foreach ($row as $k => $v) {
                    if ($v instanceof DateTime) $row[$k] = $v->format('Y-m-d H:i:s');
                }
                $detailsData[] = $row;
            }
        }
        
        $response = [
            'success' => true,
            'details' => $detailsData, // El JS espera 'details'
            'pagination' => [ // El JS espera 'pagination'
                'total_count' => (int)$totalRecords,
                'currentPage' => $page,
                'limit' => $limit,
                'totalPages' => ($limit > 0) ? ceil($totalRecords / $limit) : 0
            ]
        ];

    } else {
        throw new Exception('Acción no válida.', 400);
    }

} catch (Exception $e) {
    // --- 5. MANEJO CENTRALIZADO DE ERRORES ---
    $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    $response = ['success' => false, 'message' => $e->getMessage()];
    if ($sqlsrv_errors = sqlsrv_errors(SQLSRV_ERR_ERRORS)) {
        $response['sqlsrv_details'] = $sqlsrv_errors;
        error_log(print_r($sqlsrv_errors, true));
    }
} finally {
    if (isset($conn) && $conn !== false) sqlsrv_close($conn);
}

// --- 6. SALIDA FINAL ---
http_response_code($http_code);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>