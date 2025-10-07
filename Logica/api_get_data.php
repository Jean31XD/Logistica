<?php
// Requerir la conexión a la base de datos
require '../conexionBD/conexion.php'; 
// Establecer el encabezado de respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

// --- Inicialización de variables ---
$response = []; 
$http_code = 200; 

// Constante para el ITBIS (18%)
const ITBIS_RATE = 0.18;

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

    // --- 2. CONSTRUCCIÓN DE LA CTE DE FACTURAS (REEMPLAZO DE Facturas_ALM) ---
    // Esta CTE pre-calcula los totales por factura, incluyendo el ITBIS.
    // Es la base para todas las consultas posteriores.
    $cte_facturas = "
        WITH Facturas_CTE AS (
            SELECT
                fl.invoiceid,
                MAX(CAST(fl.invoicedate AS DATE)) AS invoicedate,
                SUM(fl.lineamount * (1 + " . ITBIS_RATE . ")) AS invoiceamountmst, -- MONTO TOTAL DE LA FACTURA (Base + ITBIS 18%)
                MAX(fl.invoicingname) AS invoicingname,
                -- Se asume que una factura pertenece a un solo almacén. MAX() es para agregación.
                MAX(fl.inventlocationid) AS inventlocationid
            FROM Facturas_lineas fl
            GROUP BY fl.invoiceid
        )
    ";

    // --- 3. CONSTRUCCIÓN DE FILTROS DINÁMICOS ---
    // Filtro de almacén optimizado para ser más directo y rápido.
    $almacenParams = [];
    $almacenSqlAnd = '';
    if (!empty($almacen)) {
        $almacenSqlAnd = " AND f.inventlocationid = ? ";
        $almacenParams[] = $almacen;
    }
    
    // --- 4. PROCESAMIENTO DE LA VISTA SOLICITADA ---
    switch ($view) {
        case 'almacenes':
            // Esta consulta es independiente y carga la lista de almacenes para el filtro.
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
            // Lógica de detalles con paginación
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

            if ($estado === 'ALL') {
                $whereSql = " WHERE f.invoicedate BETWEEN ? AND ? $almacenSqlAnd ";
            } elseif ($estado === 'Sin estado') {
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
                    f.invoiceid AS No_Factura, f.invoicedate AS Fecha_de_Registro, f.invoicingname, f.invoiceamountmst,
                    m.Registrado_por, m.Camion, m.Fecha_de_Despacho, m.Despachado_por, m.Fecha_de_Entregado, 
                    m.Entregado_por, ISNULL(m.Estado, 'Sin estado') AS Estado, m.Fecha_Reversada, 
                    m.Reversado_Por, m.Fecha_de_NC, m.NC_Realizado_Por, m.Motivo_NC, m.Camion2
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

            $response = [ 'kpis' => [], 'topClients' => [], 'topWarehouses' => [] ];
            
            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

            // 1. KPIs de Montos Totales (ahora respeta el filtro de almacén)
            $sqlKpis = $cte_facturas . "
                SELECT 
                    ISNULL(SUM(f.invoiceamountmst), 0) AS totalAmount,
                    ISNULL(SUM(CASE WHEN m.No_Factura IS NULL THEN f.invoiceamountmst ELSE 0 END), 0) AS sinEstadoAmount,
                    ISNULL(SUM(CASE WHEN m.Estado = 'NC' OR LEFT(f.invoiceid, 2) = 'NC' THEN f.invoiceamountmst ELSE 0 END), 0) AS ncAmount
                FROM Facturas_CTE f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE f.invoicedate BETWEEN ? AND ?
                $almacenSqlAnd
            ";
            $stmtKpis = sqlsrv_query($conn, $sqlKpis, $baseParams);
            if ($stmtKpis === false) throw new Exception('Error al calcular KPIs financieros.');
            $response['kpis'] = sqlsrv_fetch_array($stmtKpis, SQLSRV_FETCH_ASSOC);

            // 2. Top 10 Clientes por Monto (respeta el filtro de almacén)
            $sqlClients = $cte_facturas . "
                SELECT TOP 10
                    f.invoicingname AS Cliente,
                    SUM(f.invoiceamountmst) AS TotalAmount
                FROM Facturas_CTE f
                WHERE f.invoicedate BETWEEN ? AND ?
                $almacenSqlAnd
                GROUP BY f.invoicingname
                ORDER BY TotalAmount DESC
            ";
            $stmtClients = sqlsrv_query($conn, $sqlClients, $baseParams);
            if ($stmtClients === false) throw new Exception('Error al obtener top clientes.');
            while($row = sqlsrv_fetch_array($stmtClients, SQLSRV_FETCH_ASSOC)) {
                $response['topClients'][] = $row;
            }

            // 3. Top 10 Almacenes por Monto (ahora respeta el filtro de almacén)
            $sqlWarehouses = $cte_facturas . "
                SELECT TOP 10
                    f.inventlocationid AS Almacen,
                    SUM(f.invoiceamountmst) AS TotalAmount
                FROM Facturas_CTE f
                WHERE f.invoicedate BETWEEN ? AND ?
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

            $response = [ 'kpis' => [], 'ncReasons' => [], 'truckPerformance' => [] ];
            
            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

            // 1. KPIs de Tiempos Promedio
            $sqlKpis = $cte_facturas . "
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
            $stmtKpis = sqlsrv_query($conn, $sqlKpis, $baseParams);
            if ($stmtKpis === false) throw new Exception('Error al calcular KPIs de rendimiento.');
            $response['kpis'] = sqlsrv_fetch_array($stmtKpis, SQLSRV_FETCH_ASSOC) ?: [
                'AvgTimeToDispatch' => 0, 'AvgDispatchToDeliver' => 0, 'AvgTotalCycle' => 0
            ];

            // 2. Análisis de Motivos de Nota de Crédito (NC)
            $sqlNc = $cte_facturas . "
                SELECT 
                    ISNULL(m.Motivo_NC, 'No especificado') as Motivo,
                    COUNT(m.No_Factura) as Total
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'NC' 
                    AND f.invoicedate BETWEEN ? AND ?
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
            $sqlTrucks = $cte_facturas . "
                SELECT TOP 5
                    m.Camion,
                    COUNT(m.No_Factura) as TotalEntregas,
                    AVG(CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT)) AS AvgDeliveryTime
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'ENTREGADO' 
                    AND m.Camion IS NOT NULL AND m.Camion <> ''
                    AND f.invoicedate BETWEEN ? AND ?
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
            // Lógica de resumen (Overview)
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar el resumen.', 400);
            }

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
    // --- 5. MANEJO CENTRALIZADO DE ERRORES ---
    $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    $response = ['error' => $e->getMessage()];
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
