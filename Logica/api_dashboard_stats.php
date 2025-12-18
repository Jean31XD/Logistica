<?php
/**
 * API Estadísticas Dashboard
 * 
 * Endpoint para obtener estadísticas generales del dashboard.
 * 
 * @package    MACO\API
 * @author     MACO Team
 * @version    1.0.0
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../src/autoload.php';

verificarAutenticacion();

// Verificar acceso al dashboard
if (!isset($_SESSION['dashboard_access_granted']) || $_SESSION['dashboard_access_granted'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../conexionBD/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Obtener parámetros
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';
$tipo = $_GET['tipo'] ?? 'overview'; // overview, trends, performance, financial

// Determinar almacén según tipo de usuario
$userType = $_SESSION['dashboard_user_type'] ?? 'guest';
$userWarehouse = $_SESSION['dashboard_warehouse'] ?? '';
$almacen = ($userType === 'admin') ? ($_GET['almacen'] ?? '') : $userWarehouse;

if (empty($fechaInicio) || empty($fechaFin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros de fecha requeridos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($conn) || $conn === false) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Cache service (5 minutos de TTL)
    $cache = getCache();
    $cacheKey = "stats_{$tipo}_{$fechaInicio}_{$fechaFin}_{$almacen}";
    
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response = [];
    $almacenSql = !empty($almacen) ? "AND f.inventlocationid = ?" : "";
    $almacenParams = !empty($almacen) ? [$almacen] : [];

    // CTE común
    $cte = "
        WITH Facturas_CTE AS (
            SELECT invoiceid, invoicingname, invoiceamountmst, invoicedate, inventlocationid
            FROM dbo.CUSTINVOICEJOUR_MXM
            WHERE invoicedate BETWEEN ? AND ?
        )
    ";

    switch ($tipo) {
        case 'overview':
            // Estadísticas generales
            $sql = $cte . "
                SELECT
                    COUNT(DISTINCT f.invoiceid) AS totalEmitidas,
                    COUNT(DISTINCT CASE WHEN m.Estado IS NULL THEN f.invoiceid END) AS sinEstado,
                    SUM(f.invoiceamountmst) AS montoTotal
                FROM Facturas_CTE f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE 1=1 $almacenSql
            ";
            
            $params = array_merge([$fechaInicio, $fechaFin], $almacenParams);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $response = [
                    'totalEmitidas' => $row['totalEmitidas'] ?? 0,
                    'sinEstado' => $row['sinEstado'] ?? 0,
                    'montoTotal' => floatval($row['montoTotal'] ?? 0)
                ];
                sqlsrv_free_stmt($stmt);
            }

            // Estados
            $sqlEstados = $cte . "
                SELECT m.Estado, COUNT(DISTINCT f.invoiceid) AS Total
                FROM Facturas_CTE f
                JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE m.Estado IS NOT NULL $almacenSql
                GROUP BY m.Estado
                ORDER BY Total DESC
            ";
            
            $stmtEstados = sqlsrv_query($conn, $sqlEstados, $params);
            $response['estadosData'] = [];
            
            if ($stmtEstados) {
                while ($row = sqlsrv_fetch_array($stmtEstados, SQLSRV_FETCH_ASSOC)) {
                    $response['estadosData'][] = $row;
                }
                sqlsrv_free_stmt($stmtEstados);
            }
            break;

        case 'trends':
            // Tendencias por día
            $sql = $cte . "
                SELECT
                    f.invoicedate as Dia,
                    COUNT(f.invoiceid) as Total,
                    SUM(f.invoiceamountmst) as TotalMonto
                FROM Facturas_CTE f
                WHERE 1=1 $almacenSql
                GROUP BY f.invoicedate
                ORDER BY Dia ASC
            ";
            
            $params = array_merge([$fechaInicio, $fechaFin], $almacenParams);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            $response['tendenciaRegistros'] = [];
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    if ($row['Dia'] instanceof DateTime) {
                        $row['Dia'] = $row['Dia']->format('Y-m-d');
                    }
                    $response['tendenciaRegistros'][] = $row;
                }
                sqlsrv_free_stmt($stmt);
            }
            break;

        case 'performance':
            // Tiempos promedio de ciclo
            $sql = $cte . "
                SELECT
                    AVG(DATEDIFF(HOUR, m.Fecha_de_Registro, m.Fecha_de_Despacho)) AS avgTimeToDispatch,
                    AVG(DATEDIFF(HOUR, m.Fecha_de_Despacho, m.Fecha_de_Entregado)) AS avgDispatchToDeliver,
                    AVG(DATEDIFF(HOUR, m.Fecha_de_Registro, m.Fecha_de_Entregado)) AS avgTotalCycle
                FROM Facturas_CTE f
                JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE m.Fecha_de_Registro IS NOT NULL
                $almacenSql
            ";
            
            $params = array_merge([$fechaInicio, $fechaFin], $almacenParams);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $response = [
                    'avgTimeToDispatch' => round($row['avgTimeToDispatch'] ?? 0, 1),
                    'avgDispatchToDeliver' => round($row['avgDispatchToDeliver'] ?? 0, 1),
                    'avgTotalCycle' => round($row['avgTotalCycle'] ?? 0, 1)
                ];
                sqlsrv_free_stmt($stmt);
            }
            break;

        case 'financial':
            // Estadísticas financieras
            $sql = $cte . "
                SELECT
                    SUM(f.invoiceamountmst) AS totalAmount,
                    SUM(CASE WHEN m.Estado IS NULL THEN f.invoiceamountmst ELSE 0 END) AS sinEstadoAmount,
                    SUM(CASE WHEN m.Estado = 'NC' THEN f.invoiceamountmst ELSE 0 END) AS ncAmount
                FROM Facturas_CTE f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE 1=1 $almacenSql
            ";
            
            $params = array_merge([$fechaInicio, $fechaFin], $almacenParams);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $response = [
                    'totalAmount' => floatval($row['totalAmount'] ?? 0),
                    'sinEstadoAmount' => floatval($row['sinEstadoAmount'] ?? 0),
                    'ncAmount' => floatval($row['ncAmount'] ?? 0)
                ];
                sqlsrv_free_stmt($stmt);
            }
            break;

        default:
            throw new Exception('Tipo de estadística no válido');
    }

    // Guardar en cache
    $cache->set($cacheKey, $response, 300);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
