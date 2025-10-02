<?php
// Configuración para evitar que los errores de PHP corrompan la salida JSON
// Es mejor manejar los errores explícitamente que suprimirlos globalmente.
// error_reporting(0);
// ini_set('display_errors', 'Off');

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
        $almacenSqlAnd = " AND f.inventlocationid = ? "; 
        $almacenParams[] = $almacen;
    }

    // --- 3. PROCESAMIENTO DE LA VISTA SOLICITADA ---
    switch ($view) {
        case 'almacenes':
            $sqlAlmacenes = "
                SELECT DISTINCT inventlocationid 
                FROM Facturas_ALM 
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

        // ✅ SECCIÓN 'DETAILS' COMPLETAMENTE CORREGIDA Y MEJORADA
        case 'details':
            $estado = isset($_GET['estado']) ? trim(urldecode($_GET['estado'])) : '';
            if (empty($estado) || empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception('Faltan parámetros (estado, fecha_inicio, fecha_fin) para obtener los detalles.', 400);
            }
            
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            if ($estado === 'Sin estado') {
                // --- CONSULTA PARA FACTURAS SIN ESTADO ---
                $sqlCount = "
                    SELECT COUNT(f.invoiceid) AS Total
                    FROM Facturas_ALM f
                    LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                    WHERE m.No_Factura IS NULL 
                    AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                    $almacenSqlAnd
                ";
                $countParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams);

                $sqlDetails = "
                    SELECT 
                        f.invoiceid AS No_Factura, f.invoicedate AS Fecha_de_Registro, NULL AS Registrado_por, 
                        NULL AS Camion, NULL AS Fecha_de_Despacho, NULL AS Despachado_por, NULL AS Fecha_de_Entregado,
                        NULL AS Entregado_por, 'Sin estado' AS Estado, NULL AS Fecha_Reversada, NULL AS Reversado_Por,
                        NULL AS Fecha_de_NC, NULL AS NC_Realizado_Por, NULL AS Motivo_NC, NULL AS Camion2
                    FROM Facturas_ALM f
                    LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                    WHERE m.No_Factura IS NULL 
                    AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                    $almacenSqlAnd
                    ORDER BY f.invoicedate DESC
                    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                ";
                $detailsParams = array_merge([$fecha_inicio, $fecha_fin], $almacenParams, [$offset, $limit]);
            } else {
                // --- CONSULTA PARA FACTURAS CON ESTADO ---
                $sqlCount = "
                    SELECT COUNT(m.No_Factura) AS Total
                    FROM Factura_Programa_Despacho_MACOR m
                    INNER JOIN Facturas_ALM f ON m.No_Factura = f.invoiceid
                    WHERE m.Estado = ? AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                    $almacenSqlAnd
                ";
                $countParams = array_merge([$estado, $fecha_inicio, $fecha_fin], $almacenParams);

                $sqlDetails = "
                    SELECT 
                        m.ID, m.No_Factura, m.Fecha_de_Registro, m.Registrado_por, m.Camion, 
                        m.Fecha_de_Despacho, m.Despachado_por, m.Fecha_de_Entregado, m.Entregado_por, 
                        m.Estado, m.Fecha_Reversada, m.Reversado_Por, m.Fecha_de_NC, 
                        m.NC_Realizado_Por, m.Motivo_NC, m.Camion2
                    FROM Factura_Programa_Despacho_MACOR m
                    INNER JOIN Facturas_ALM f ON m.No_Factura = f.invoiceid
                    WHERE m.Estado = ? AND CAST(f.invoicedate AS DATE) BETWEEN ? AND ?
                    $almacenSqlAnd
                    ORDER BY m.Fecha_de_Registro DESC
                    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                ";
                $detailsParams = array_merge([$estado, $fecha_inicio, $fecha_fin], $almacenParams, [$offset, $limit]);
            }

            // --- Ejecución de consultas ---
            $stmtCount = sqlsrv_query($conn, $sqlCount, $countParams);
            if ($stmtCount === false) {
                throw new Exception('Error en la consulta de conteo de detalles.');
            }
            $totalRecords = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['Total'] ?? 0;
            
            $detailsData = [];
            if ($totalRecords > 0) {
                $stmtDetails = sqlsrv_query($conn, $sqlDetails, $detailsParams);
                if ($stmtDetails === false) {
                    throw new Exception('Error en la consulta de obtención de detalles.');
                }
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
    // Si el código de la excepción es un código HTTP válido (como 400), úsalo. Si no, usa 500.
    $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    
    // Prepara la respuesta de error
    $response = ['error' => $e->getMessage()];

    // ✅ AÑADE LOS DETALLES DE SQL SERVER AL ERROR SI EXISTEN
    // Esto es crucial para la depuración
    $sqlsrv_errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if ($sqlsrv_errors !== null) {
        $response['sqlsrv_details'] = $sqlsrv_errors;
        // Opcional: Registrar en el log del servidor para no exponerlo en producción
        error_log(print_r($sqlsrv_errors, true));
    }

} finally {
    // Cierra la conexión si existe
    if (isset($conn) && $conn !== false) {
        sqlsrv_close($conn);
    }
}

// --- 5. SALIDA FINAL ---
http_response_code($http_code);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>