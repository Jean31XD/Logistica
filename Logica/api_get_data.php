<?php
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

// === PROTECCIÓN ANTI-SOBRECARGA ===
// Limitar peticiones globales al dashboard (max 40 req en 10 segundos para TODOS los usuarios)
require_once __DIR__ . '/../conexionBD/global_rate_limiter.php';
if (!checkGlobalRateLimit('api_get_data', 40, 10)) {
    GlobalRateLimiter::tooManyRequests('Dashboard sobrecargado. Reintentar en 5 segundos.');
}

// Conexión a BD para verificar permisos
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar permiso del módulo dashboard_general usando solo usuario_modulos
$usuario = $_SESSION['usuario'];
$tienePermiso = tieneModulo('dashboard_general', $conn);

if (!$tienePermiso) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado para acceder al dashboard.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener almacén asignado al usuario
$USER_WAREHOUSE = '';
$sqlAlmacen = "SELECT dashboard_almacen FROM usuarios WHERE usuario = ?";
$stmtAlmacen = @sqlsrv_query($conn, $sqlAlmacen, [$usuario]);

if ($stmtAlmacen !== false) {
    $rowAlmacen = sqlsrv_fetch_array($stmtAlmacen, SQLSRV_FETCH_ASSOC);
    if ($rowAlmacen && isset($rowAlmacen['dashboard_almacen'])) {
        $USER_WAREHOUSE = $rowAlmacen['dashboard_almacen'] ?? '';
    }
}

$USER_TYPE = empty($USER_WAREHOUSE) ? 'admin' : 'warehouse';

// Establecer timeout de bloqueo para evitar esperas indefinidas
$timeoutQuery = "SET LOCK_TIMEOUT 15000"; // 15 segundos
sqlsrv_query($conn, $timeoutQuery);

// Cargar wrapper de queries con protección de timeout
require_once __DIR__ . '/../conexionBD/query_wrapper.php';

// Cargar sistema de caché para consultas repetitivas
require_once __DIR__ . '/../conexionBD/cache_manager.php';
$cache = getCache();

// 3. Cargar autoloader del proyecto (habilita clases y helpers)
require_once __DIR__ . '/../src/autoload.php';

// Establecer el encabezado de respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

// --- Inicialización de variables ---
$response = []; 
$http_code = 200;
$useCache = true; // Habilitar caché para este endpoint
$cacheTTL = 120;  // 2 minutos de caché 


try {
    // --- 1. VERIFICACIÓN DE CONEXIÓN ---
    if (!isset($conn) || $conn === false) {
        throw new Exception('No se pudo establecer la conexión a la base de datos.', 503);
    }

    // --- Obtención y normalización de parámetros ---
    $fecha_inicio = $_GET['fecha_inicio'] ?? '';
    $fecha_fin = $_GET['fecha_fin'] ?? '';
    $view = $_GET['view'] ?? 'overview';
    $almacen_param = $_GET['almacen'] ?? ''; // Parámetro del GET

    // --- MODIFICACIÓN: Forzar almacén según sesión ---
    $almacen = '';
    if ($USER_TYPE === 'admin') {
        $almacen = $almacen_param; // Admin puede usar el filtro que quiera
    } else {
        // Usuario no-admin USA SU PROPIO ALMACÉN, ignorando el parámetro del GET
        $almacen = $USER_WAREHOUSE; 
    }
    // ----------------------------------------------

    if (!empty($fecha_inicio)) $fecha_inicio = date('Y-m-d', strtotime($fecha_inicio));
    if (!empty($fecha_fin)) $fecha_fin = date('Y-m-d', strtotime($fecha_fin));

    // --- 2. CONSTRUCCIÓN DE LA CTE DE FACTURAS (OPTIMIZADA) ---
    // La función buildFacturasCTE genera la CTE con filtros aplicados DENTRO de la sub-consulta
    // para evitar escanear toda la tabla Facturas_lineas innecesariamente.
    function buildFacturasCTE($fecha_inicio, $fecha_fin, $almacen) {
        $whereClause = "";
        $conditions = [];
        
        if (!empty($fecha_inicio) && !empty($fecha_fin)) {
            $conditions[] = "fl.invoicedate BETWEEN '$fecha_inicio' AND '$fecha_fin'";
        }
        if (!empty($almacen)) {
            $conditions[] = "fl.inventlocationid = '$almacen'";
        }
        
        if (!empty($conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        return "
        WITH Facturas_CTE AS (
            SELECT
                fl.invoiceid,
                fl.inventlocationid,
                MAX(CAST(fl.invoicedate AS DATE)) AS invoicedate,
                SUM(fl.lineamount + fl.lineamounttax) AS invoiceamountmst,
                MAX(fl.invoicingname) AS invoicingname
            FROM Facturas_lineas fl
            $whereClause
            GROUP BY fl.invoiceid, fl.inventlocationid 
        )
        ";
    }
    
    // Generar la CTE con los filtros aplicados
    $cte_facturas = buildFacturasCTE($fecha_inicio, $fecha_fin, $almacen);

    // --- 3. CONSTRUCCIÓN DE FILTROS DINÁMICOS ---
    // Nota: almacenSqlAnd ya no es necesario si el filtro está en la CTE,
    // pero lo mantenemos para compatibilidad con consultas existentes que lo usan.
    $almacenParams = [];
    $almacenSqlAnd = '';
    if (!empty($almacen)) {
        $almacenSqlAnd = " AND f.inventlocationid = ? ";
        $almacenParams[] = $almacen;
    }
    
    // --- 4. PROCESAMIENTO DE LA VISTA SOLICITADA ---
    switch ($view) {
        case 'almacenes':
            // --- MODIFICACIÓN: Esta vista solo debe ser para admins ---
            if ($USER_TYPE !== 'admin') {
                 throw new Exception('Acceso denegado a esta función.', 403); // 403 Forbidden
            }
            
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

        // El resto de tus 'case' (trends, details, financial, performance, overview)
        // no necesitan cambios, ya que la variable $almacenSqlAnd y $almacenParams
        // ya están correctamente filtradas por la lógica de sesión del inicio.
        
        case 'trends':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar tendencias.', 400);
            }

            $response['tendenciaRegistros'] = [];
            $sqlTrends = $cte_facturas . "
                SELECT
                    f.invoicedate as Dia,
                    COUNT(f.invoiceid) as Total,
                    SUM(f.invoiceamountmst) as TotalMonto
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
                    f.invoiceid AS No_Factura, f.invoicedate AS Fecha_Factura, m.Fecha_de_Registro, f.invoicingname, f.invoiceamountmst,
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

            $sqlTrucks = $cte_facturas . "
                SELECT TOP 5
                    m.Entregado_por AS Camion,
                    COUNT(m.No_Factura) as TotalEntregas,
                    AVG(CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT)) AS AvgDeliveryTime
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'ENTREGado' 
                    AND m.Entregado_por IS NOT NULL AND m.Entregado_por <> ''
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
                GROUP BY m.Entregado_por
                ORDER BY TotalEntregas DESC
            ";
            $stmtTrucks = sqlsrv_query($conn, $sqlTrucks, $baseParams);
            if ($stmtTrucks === false) throw new Exception('Error al analizar rendimiento de camiones.');
            while($row = sqlsrv_fetch_array($stmtTrucks, SQLSRV_FETCH_ASSOC)) {
                $response['truckPerformance'][] = $row;
            }
            break;

        case 'delivery_details':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar detalles de entregas.', 400);
            }

            $sqlDetails = $cte_facturas . "
                SELECT
                    f.invoiceid AS Factura,
                    f.invoicingname AS Cliente,
                    m.Entregado_por AS Camion,
                    m.Fecha_de_Despacho AS FechaDespacho,
                    m.Despachado_por AS DespachadoPor,
                    m.Fecha_de_Entregado AS FechaEntregado,
                    CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT) AS DeliveryTimeHours
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'ENTREGado'
                    AND m.Entregado_por IS NOT NULL AND m.Entregado_por <> ''
                    AND m.Fecha_de_Despacho IS NOT NULL
                    AND m.Fecha_de_Entregado IS NOT NULL
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
                ORDER BY m.Entregado_por, m.Fecha_de_Entregado
            ";

            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
            $stmtDetails = sqlsrv_query($conn, $sqlDetails, $baseParams);

            if ($stmtDetails === false) {
                throw new Exception('Error al consultar detalles de entregas.');
            }

            $response = [];
            while ($row = sqlsrv_fetch_array($stmtDetails, SQLSRV_FETCH_ASSOC)) {
                // Convertir objetos DateTime a strings
                if ($row['FechaDespacho'] instanceof DateTime) {
                    $row['FechaDespacho'] = $row['FechaDespacho']->format('Y-m-d H:i:s');
                }
                if ($row['FechaEntregado'] instanceof DateTime) {
                    $row['FechaEntregado'] = $row['FechaEntregado']->format('Y-m-d H:i:s');
                }
                $response[] = $row;
            }
            break;

        case 'dispatched_by_truck':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar facturas despachadas.', 400);
            }

            // Consulta: Para cada transportista (desde Camiones_PW), contar facturas según su estado
            // Relaciona Camiones_PW.chasis con Factura_Programa_Despacho_MACOR.Camion
            $sqlDispatched = $cte_facturas . "
                SELECT
                    m.Camion AS Camion,
                    ISNULL(c.nombre, 'Sin asignar') AS Transportista,
                    ISNULL(c.placa, m.Camion) AS Placa,
                    ISNULL(c.modelo, 'N/A') AS Modelo,
                    ISNULL(c.ficha, 'N/A') AS Ficha,
                    -- Total de facturas asignadas a este camión
                    COUNT(DISTINCT m.No_Factura) AS TotalAsignadas,
                    -- Facturas con Estado = 'ENTREGADO'
                    COUNT(DISTINCT CASE
                        WHEN m.Estado = 'ENTREGADO'
                        THEN m.No_Factura
                        ELSE NULL
                    END) AS TotalEntregadas,
                    -- Facturas con Estado = 'DESPACHADO' u otros estados
                    COUNT(DISTINCT CASE
                        WHEN m.Estado <> 'ENTREGADO' OR m.Estado IS NULL
                        THEN m.No_Factura
                        ELSE NULL
                    END) AS TotalDespachadas
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                LEFT JOIN Camiones_PW c ON m.Camion = c.chasis
                WHERE m.Fecha_de_Despacho IS NOT NULL
                    AND f.invoicedate BETWEEN ? AND ?
                    AND m.Camion IS NOT NULL
                    $almacenSqlAnd
                GROUP BY m.Camion, c.nombre, c.placa, c.modelo, c.ficha
                ORDER BY c.nombre, c.placa
            ";

            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
            $stmtDispatched = sqlsrv_query($conn, $sqlDispatched, $baseParams);

            if ($stmtDispatched === false) {
                throw new Exception('Error al consultar facturas despachadas por camión.');
            }

            $response = [];
            while ($row = sqlsrv_fetch_array($stmtDispatched, SQLSRV_FETCH_ASSOC)) {
                $response[] = $row;
            }
            break;

        case 'facturas_by_truck':
            // Obtener todas las facturas de un camión específico (por chasis)
            $chasisCamion = $_GET['camion'] ?? '';
            if (empty($chasisCamion) || empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros (camion, fecha_inicio, fecha_fin).', 400);
            }

            $sqlFacturas = $cte_facturas . "
                SELECT
                    m.No_Factura AS Factura,
                    f.invoicingname AS Cliente,
                    m.Estado,
                    m.Camion AS Chasis,
                    ISNULL(c.placa, m.Camion) AS Placa,
                    ISNULL(c.nombre, 'Sin asignar') AS Transportista,
                    m.Fecha_de_Despacho AS FechaDespacho,
                    m.Despachado_por AS DespachadoPor,
                    m.Fecha_de_Entregado AS FechaEntregado
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                LEFT JOIN Camiones_PW c ON m.Camion = c.chasis
                WHERE m.Camion = ?
                    AND m.Fecha_de_Despacho IS NOT NULL
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
                ORDER BY m.Estado DESC, m.Fecha_de_Registro DESC
            ";

            $facturasParams = array_merge([$chasisCamion, $fecha_inicio, $fecha_fin], $almacenParams);
            $stmtFacturas = sqlsrv_query($conn, $sqlFacturas, $facturasParams);

            if ($stmtFacturas === false) {
                $errors = sqlsrv_errors();
                error_log("Error SQL facturas_by_truck: " . print_r($errors, true));
                throw new Exception('Error al consultar facturas del transportista.');
            }

            $response = [];
            while ($row = sqlsrv_fetch_array($stmtFacturas, SQLSRV_FETCH_ASSOC)) {
                // Convertir objetos DateTime a strings
                if (isset($row['FechaDespacho']) && $row['FechaDespacho'] instanceof DateTime) {
                    $row['FechaDespacho'] = $row['FechaDespacho']->format('Y-m-d H:i:s');
                }
                if (isset($row['FechaEntregado']) && $row['FechaEntregado'] instanceof DateTime) {
                    $row['FechaEntregado'] = $row['FechaEntregado']->format('Y-m-d H:i:s');
                }
                $response[] = $row;
            }
            error_log("facturas_by_truck: Found " . count($response) . " facturas for camion (chasis): $chasisCamion");
            break;

        case 'drivers_list':
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar la lista de choferes.', 400);
            }

            $sqlDrivers = $cte_facturas . "
                SELECT 
                    m.Entregado_por AS DriverName,
                    COUNT(m.No_Factura) as TotalDeliveries
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE m.Estado = 'ENTREGado' 
                    AND m.Entregado_por IS NOT NULL AND m.Entregado_por <> ''
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
                GROUP BY m.Entregado_por
                ORDER BY TotalDeliveries DESC
            ";
            
            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);
            $stmtDrivers = sqlsrv_query($conn, $sqlDrivers, $baseParams);
            if ($stmtDrivers === false) {
                throw new Exception('Error al consultar la lista de choferes.');
            }
            
            $response = [];
            while($row = sqlsrv_fetch_array($stmtDrivers, SQLSRV_FETCH_ASSOC)) {
                $response[] = $row;
            }
            break;

        case 'driver_deliveries':
            $driver_id = $_GET['driver_id'] ?? '';
            if (empty($driver_id) || empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros (driver_id, fecha_inicio, fecha_fin) para obtener las entregas.', 400);
            }

            $sqlDeliveries = $cte_facturas . "
                SELECT
                    f.invoiceid AS Factura,
                    f.invoicingname AS Cliente,
                    m.Fecha_de_Despacho AS FechaDespacho,
                    m.Despachado_por AS DespachadoPor,
                    m.Fecha_de_Entregado AS FechaEntregado,
                    CAST(DATEDIFF(hour, m.Fecha_de_Despacho, m.Fecha_de_Entregado) AS FLOAT) AS DeliveryTimeHours
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE m.Entregado_por = ?
                    AND m.Estado = 'ENTREGado'
                    AND m.Fecha_de_Despacho IS NOT NULL
                    AND m.Fecha_de_Entregado IS NOT NULL
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
                ORDER BY m.Fecha_de_Entregado DESC
            ";

            $deliveriesParams = array_merge([$driver_id, $fecha_inicio, $fecha_fin], $almacenParams);
            $stmtDeliveries = sqlsrv_query($conn, $sqlDeliveries, $deliveriesParams);

            if ($stmtDeliveries === false) {
                throw new Exception('Error al consultar las entregas del chofer.');
            }

            $response = [];
            while ($row = sqlsrv_fetch_array($stmtDeliveries, SQLSRV_FETCH_ASSOC)) {
                if ($row['FechaDespacho'] instanceof DateTime) {
                    $row['FechaDespacho'] = $row['FechaDespacho']->format('Y-m-d H:i:s');
                }
                if ($row['FechaEntregado'] instanceof DateTime) {
                    $row['FechaEntregado'] = $row['FechaEntregado']->format('Y-m-d H:i:s');
                }
                $response[] = $row;
            }
            break;

        case 'entregas_sin_qr':
            // Obtener facturas donde Entrega_sin_QR = TRUE (valor 1)
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar entregas sin QR.', 400);
            }

            $baseParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

            // Contar total de registros sin QR
            $sqlCount = $cte_facturas . "
                SELECT COUNT(DISTINCT m.No_Factura) AS Total
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                WHERE ISNULL(m.Entrega_sin_QR, 0) = 1
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
            ";
            $stmtCount = sqlsrv_query($conn, $sqlCount, $baseParams);
            if ($stmtCount === false) throw new Exception('Error al contar entregas sin QR.');
            $totalSinQR = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['Total'] ?? 0;

            // Obtener detalle de entregas sin QR
            $sqlSinQR = $cte_facturas . "
                SELECT
                    m.No_Factura AS Factura,
                    f.invoicingname AS Cliente,
                    m.Estado,
                    m.Camion,
                    ISNULL(c.nombre, 'Sin asignar') AS Transportista,
                    ISNULL(c.placa, m.Camion) AS Placa,
                    m.Fecha_de_Despacho AS FechaDespacho,
                    m.Despachado_por AS DespachadoPor,
                    m.Fecha_de_Entregado AS FechaEntregado,
                    m.Entregado_por AS EntregadoPor
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                LEFT JOIN Camiones_PW c ON m.Camion = c.chasis
                WHERE ISNULL(m.Entrega_sin_QR, 0) = 1
                    AND f.invoicedate BETWEEN ? AND ?
                    $almacenSqlAnd
                ORDER BY m.Fecha_de_Entregado DESC, m.Fecha_de_Despacho DESC
            ";

            $stmtSinQR = sqlsrv_query($conn, $sqlSinQR, $baseParams);
            if ($stmtSinQR === false) {
                $errors = sqlsrv_errors();
                error_log("Error SQL entregas_sin_qr: " . print_r($errors, true));
                throw new Exception('Error al consultar entregas sin QR.');
            }

            $entregas = [];
            while ($row = sqlsrv_fetch_array($stmtSinQR, SQLSRV_FETCH_ASSOC)) {
                if (isset($row['FechaDespacho']) && $row['FechaDespacho'] instanceof DateTime) {
                    $row['FechaDespacho'] = $row['FechaDespacho']->format('Y-m-d H:i:s');
                }
                if (isset($row['FechaEntregado']) && $row['FechaEntregado'] instanceof DateTime) {
                    $row['FechaEntregado'] = $row['FechaEntregado']->format('Y-m-d H:i:s');
                }
                $entregas[] = $row;
            }

            $response = [
                'total' => (int)$totalSinQR,
                'entregas' => $entregas
            ];
            break;

        case 'overview':

        default:
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros de fecha para consultar el resumen.', 400);
            }

            // --- CACHÉ: Intentar obtener del caché primero ---
            $cacheKey = $cache->generateKey('overview', [
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'almacen' => $almacen,
                'user_type' => $USER_TYPE
            ]);
            
            $cachedResponse = $useCache ? $cache->get($cacheKey) : null;
            
            if ($cachedResponse !== null) {
                // Cache hit - retornar datos cacheados
                $response = $cachedResponse;
                $response['_cached'] = true; // Indicador para debugging
            } else {
                // Cache miss - consultar BD
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
                    error_log("SQL Error in overview: " . print_r(sqlsrv_errors(), true));
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
                
                // Guardar en caché para próximas solicitudes
                $cache->set($cacheKey, $response, $cacheTTL);
                $response['_cached'] = false;
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
?>