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
    $fecha_inicio = $_GET['fecha_inicio'] ?? '';
    $fecha_fin = $_GET['fecha_fin'] ?? '';
    $view = $_GET['view'] ?? 'overview';
    $almacen = $_GET['almacen'] ?? '';

    if (!empty($fecha_inicio)) $fecha_inicio = date('Y-m-d', strtotime($fecha_inicio));
    if (!empty($fecha_fin)) $fecha_fin = date('Y-m-d', strtotime($fecha_fin));

    // --- 2. CONSTRUCCIÓN DE FILTROS DINÁMICOS ---
    $almacenParams = [];
    $almacenSqlAnd = '';
    
    if (!empty($almacen)) {
        // MODIFICACIÓN: La lógica de filtrado ahora usa una subconsulta EXISTS en la tabla Facturas_lineas.
        // Esto asegura que se incluyan las facturas que tengan al menos una línea en el almacén seleccionado.
        $almacenSqlAnd = " AND EXISTS (SELECT 1 FROM Facturas_lineas fl WHERE fl.invoiceid = f.invoiceid AND fl.inventlocationid = ?) "; 
        $almacenParams[] = $almacen;
    }

    // --- 3. PROCESAMIENTO DE LA VISTA SOLICITADA ---
    switch ($view) {
        case 'almacenes':
            // MODIFICACIÓN: La fuente para la lista de almacenes es ahora la tabla Facturas_lineas.
            $sqlAlmacenes = "
                SELECT DISTINCT inventlocationid 
                FROM Facturas_lineas 
                WHERE inventlocationid IS NOT NULL AND inventlocationid <> ''
                ORDER BY inventlocationid ASC
            ";
            $stmtAlmacenes = sqlsrv_query($conn, $sqlAlmacenes);
            if ($stmtAlmacenes === false) {
                throw new Exception('Error al obtener la lista de almacenes.');
            }
            while ($row = sqlsrv_fetch_array($stmtAlmacenes, SQLSRV_FETCH_ASSOC)) {
                $response[] = $row;
            }
            break;

        case 'trends':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar tendencias.', 400);
            }

            $response['tendenciaRegistros'] = [];
            $sqlTrends = "
                SELECT 
                    CAST(f.invoicedate AS DATE) as Dia, 
                    COUNT(f.invoiceid) as Total
                FROM Facturas_ALM f
                WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                $almacenSqlAnd
                GROUP BY CAST(f.invoicedate AS DATE)
                ORDER BY Dia ASC
            ";
            
            $trendsParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
            $stmtTrends = sqlsrv_query($conn, $sqlTrends, $trendsParams);
            
            if ($stmtTrends === false) {
                throw new Exception('Error al consultar tendencias.');
            }
            while($row = sqlsrv_fetch_array($stmtTrends, SQLSRV_FETCH_ASSOC)) {
                if ($row['Dia'] instanceof DateTime) {
                    $row['Dia'] = $row['Dia']->format('Y-m-d');
                }
                $response['tendenciaRegistros'][] = $row;
            }
            break;

        case 'details':
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
            $orderBy = "f.invoicedate DESC"; // Orden por defecto

            if ($estado === 'ALL') {
                $whereSql = " WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ? $almacenSqlAnd ";
            } elseif ($estado === 'Sin estado') {
                $whereSql = " 
                    WHERE m.No_Factura IS NULL 
                    AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ? 
                    $almacenSqlAnd
                ";
            } else {
                $whereSql = " 
                    WHERE m.Estado = ? 
                    AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ? 
                    $almacenSqlAnd
                ";
                array_unshift($countParams, $estado);
                array_unshift($detailsParams, $estado);
                $orderBy = "m.Fecha_de_Registro DESC";
            }

            $sqlCount = "
                SELECT COUNT(f.invoiceid) AS Total
                FROM Facturas_ALM f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                $whereSql
            ";
            $sqlDetails = "
                SELECT 
                    f.invoiceid AS No_Factura, f.invoicedate AS Fecha_de_Registro, f.invoicingname, f.invoiceamountmst,
                    m.Registrado_por, m.Camion, m.Fecha_de_Despacho, m.Despachado_por, m.Fecha_de_Entregado, 
                    m.Entregado_por, ISNULL(m.Estado, 'Sin estado') AS Estado, m.Fecha_Reversada, 
                    m.Reversado_Por, m.Fecha_de_NC, m.NC_Realizado_Por, m.Motivo_NC, m.Camion2
                FROM Facturas_ALM f
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
                'data' => $detailsData,
                'totalRecords' => (int)$totalRecords,
                'currentPage' => $page,
                'limit' => $limit,
                'totalPages' => ($limit > 0) ? ceil($totalRecords / $limit) : 0
            ];
            break;

        case 'financial':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para el análisis financiero.', 400);
            }

            $response = [
                'kpis' => [],
                'topClients' => [],
                'topWarehouses' => []
            ];
            
            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

            // 1. KPIs de Montos Totales
            $sqlKpis = "
                SELECT 
                    ISNULL(SUM(f.invoiceamountmst), 0) AS totalAmount,
                    ISNULL(SUM(CASE WHEN m.No_Factura IS NULL THEN f.invoiceamountmst ELSE 0 END), 0) AS sinEstadoAmount,
                    ISNULL(SUM(CASE WHEN m.Estado = 'NC' THEN f.invoiceamountmst ELSE 0 END), 0) AS ncAmount
                FROM Facturas_ALM f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                $almacenSqlAnd
            ";
            $stmtKpis = sqlsrv_query($conn, $sqlKpis, $baseParams);
            if ($stmtKpis === false) throw new Exception('Error al calcular KPIs financieros.');
            $response['kpis'] = sqlsrv_fetch_array($stmtKpis, SQLSRV_FETCH_ASSOC);

            // 2. Top 10 Clientes por Monto
            $sqlClients = "
                SELECT TOP 10
                    f.invoicingname AS Cliente,
                    SUM(f.invoiceamountmst) AS TotalAmount
                FROM Facturas_ALM f
                WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                $almacenSqlAnd
                GROUP BY f.invoicingname
                ORDER BY TotalAmount DESC
            ";
            $stmtClients = sqlsrv_query($conn, $sqlClients, $baseParams);
            if ($stmtClients === false) throw new Exception('Error al obtener top clientes.');
            while($row = sqlsrv_fetch_array($stmtClients, SQLSRV_FETCH_ASSOC)) {
                $response['topClients'][] = $row;
            }

            // 3. Top 10 Almacenes por Monto
            $sqlWarehouses = "
                SELECT TOP 10
                    f.inventlocationid AS Almacen,
                    SUM(f.invoiceamountmst) AS TotalAmount
                FROM Facturas_ALM f
                WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                  AND f.inventlocationid IS NOT NULL AND f.inventlocationid <> ''
                $almacenSqlAnd
                GROUP BY f.inventlocationid
                ORDER BY TotalAmount DESC
            ";
            $stmtWarehouses = sqlsrv_query($conn, $sqlWarehouses, $baseParams);
            if ($stmtWarehouses === false) throw new Exception('Error al obtener top almacenes.');
            while($row = sqlsrv_fetch_array($stmtWarehouses, SQLSRV_FETCH_ASSOC)) {
                $response['topWarehouses'][] = $row;
            }
            break;

        case 'performance':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para el análisis de rendimiento.', 400);
            }

            $response = [
                'kpis' => [],
                'ncReasons' => [],
                'truckPerformance' => []
            ];
            
            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

            // 1. KPIs de Tiempos Promedio
            $sqlKpis = "
                SELECT 
                    AVG(CAST(DATEDIFF(hour, f.invoicedate, m.Fecha_de_Despacho) AS FLOAT)) AS AvgTimeToDispatch,
                    AVG(CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT)) AS AvgDispatchToDeliver,
                    AVG(CAST(DATEDIFF(hour, f.invoicedate, m.Fecha_de_Entregado) AS FLOAT)) AS AvgTotalCycle
                FROM Facturas_ALM f
                JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                  AND m.Fecha_de_Despacho IS NOT NULL
                  AND m.Fecha_de_Entregado IS NOT NULL
                  $almacenSqlAnd
            ";
            $stmtKpis = sqlsrv_query($conn, $sqlKpis, $baseParams);
            if ($stmtKpis === false) throw new Exception('Error al calcular KPIs de rendimiento.');
            $response['kpis'] = sqlsrv_fetch_array($stmtKpis, SQLSRV_FETCH_ASSOC) ?: [
                'AvgTimeToDispatch' => 0, 'AvgDispatchToDeliver' => 0, 'AvgTotalCycle' => 0
            ];

            // 2. Análisis de Motivos de Nota de Crédito (NC)
            $sqlNc = "
                SELECT 
                    ISNULL(m.Motivo_NC, 'No especificado') as Motivo,
                    COUNT(m.No_Factura) as Total
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_ALM f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'NC' 
                  AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                  $almacenSqlAnd
                GROUP BY ISNULL(m.Motivo_NC, 'No especificado')
                ORDER BY Total DESC
            ";
            $stmtNc = sqlsrv_query($conn, $sqlNc, $baseParams);
            if ($stmtNc === false) throw new Exception('Error al analizar motivos de NC.');
            while($row = sqlsrv_fetch_array($stmtNc, SQLSRV_FETCH_ASSOC)) {
                $response['ncReasons'][] = $row;
            }

            // 3. Rendimiento de Camiones (Top 5 con más entregas)
            $sqlTrucks = "
                SELECT TOP 5
                    m.Camion,
                    COUNT(m.No_Factura) as TotalEntregas,
                    AVG(CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT)) AS AvgDeliveryTime
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_ALM f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'ENTREGADO' 
                  AND m.Camion IS NOT NULL AND m.Camion <> ''
                  AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                  $almacenSqlAnd
                GROUP BY m.Camion
                ORDER BY TotalEntregas DESC
            ";
            $stmtTrucks = sqlsrv_query($conn, $sqlTrucks, $baseParams);
            if ($stmtTrucks === false) throw new Exception('Error al analizar rendimiento de camiones.');
            while($row = sqlsrv_fetch_array($stmtTrucks, SQLSRV_FETCH_ASSOC)) {
                $response['truckPerformance'][] = $row;
            }
            break;

        case 'overview':
        default:
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar el resumen.', 400);
            }

            $sqlOverview = "
                SELECT 
                    ISNULL(m.Estado, 'Sin estado') AS Estado,
                    COUNT(f.invoiceid) AS Total
                FROM Facturas_ALM f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                $almacenSqlAnd
                GROUP BY ISNULL(m.Estado, 'Sin estado')
            ";
            
            $overviewParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
            $stmtOverview = sqlsrv_query($conn, $sqlOverview, $overviewParams);

            if ($stmtOverview === false) {
                throw new Exception('Error al consultar el resumen de estados.');
            }

            $response['totalEmitidas'] = 0;
            $response['sinEstado'] = 0;
            $response['estadosData'] = [];
            
            while ($row = sqlsrv_fetch_array($stmtOverview, SQLSRV_FETCH_ASSOC)) {
                $total = (int)$row['Total'];
                $response['totalEmitidas'] += $total;
                if ($row['Estado'] === 'Sin estado') {
                    $response['sinEstado'] = $total;
                } else {
                    $response['estadosData'][] = ['Estado' => $row['Estado'], 'Total' => $total];
                }
            }
            break;
    }
} catch (Exception $e) {
    // --- 4. MANEJO CENTRALIZADO DE ERRORES ---
    $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    $response = ['error' => $e->getMessage()];
    if ($sqlsrv_errors = sqlsrv_errors(SQLSRV_ERR_ERRORS)) {
        $response['sqlsrv_details'] = $sqlsrv_errors;
        error_log(print_r($sqlsrv_errors, true));
    }
} finally {
    if (isset($conn) && $conn !== false) sqlsrv_close($conn);
}

// --- 5. SALIDA FINAL ---
http_response_code($http_code);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>