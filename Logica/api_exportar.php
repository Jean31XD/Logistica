<?php
/**
 * API Exportar Datos
 * 
 * Endpoint para exportar datos a Excel y PDF.
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

use MACO\Services\ExportService;
use MACO\Services\ResponseService;

// Obtener parámetros
$tipo = $_GET['tipo'] ?? 'excel'; // excel, pdf
$reporte = $_GET['reporte'] ?? 'facturas'; // facturas, entregas, resumen
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Determinar almacén según tipo de usuario
$userType = $_SESSION['dashboard_user_type'] ?? 'guest';
$userWarehouse = $_SESSION['dashboard_warehouse'] ?? '';
$almacen = ($userType === 'admin') ? ($_GET['almacen'] ?? '') : $userWarehouse;

try {
    if (!isset($conn) || $conn === false) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $export = new ExportService();
    $data = [];
    $columns = [];
    $title = '';

    // CTE común
    $cte = "
        WITH Facturas_CTE AS (
            SELECT invoiceid, invoicingname, invoiceamountmst, invoicedate, inventlocationid
            FROM dbo.CUSTINVOICEJOUR_MXM
            WHERE invoicedate BETWEEN ? AND ?
        )
    ";

    $almacenSql = !empty($almacen) ? "AND f.inventlocationid = ?" : "";
    $params = [$fechaInicio, $fechaFin];
    if (!empty($almacen)) {
        $params[] = $almacen;
    }

    switch ($reporte) {
        case 'facturas':
            $title = 'Reporte de Facturas';
            $columns = [
                'No_Factura' => 'No. Factura',
                'invoicingname' => 'Cliente',
                'invoiceamountmst' => 'Monto',
                'Estado' => 'Estado',
                'inventlocationid' => 'Almacén',
                'FechaRegistro' => 'Fecha Registro',
                'FechaDespacho' => 'Fecha Despacho',
                'FechaEntrega' => 'Fecha Entrega',
                'Camion' => 'Camión'
            ];

            $sql = $cte . "
                SELECT
                    m.No_Factura,
                    f.invoicingname,
                    f.invoiceamountmst,
                    ISNULL(m.Estado, 'Sin estado') AS Estado,
                    f.inventlocationid,
                    m.Fecha_de_Registro AS FechaRegistro,
                    m.Fecha_de_Despacho AS FechaDespacho,
                    m.Fecha_de_Entregado AS FechaEntrega,
                    m.Camion
                FROM Facturas_CTE f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE 1=1 $almacenSql
                ORDER BY f.invoicedate DESC
            ";
            break;

        case 'entregas':
            $title = 'Reporte de Entregas por Transportista';
            $columns = [
                'Transportista' => 'Transportista',
                'Placa' => 'Placa',
                'Chasis' => 'Chasis',
                'TotalAsignadas' => 'Asignadas',
                'TotalDespachadas' => 'Despachadas',
                'TotalEntregadas' => 'Entregadas',
                'PorcentajeEntrega' => '% Entrega'
            ];

            $sql = $cte . "
                SELECT
                    ISNULL(c.nombre, 'Sin asignar') AS Transportista,
                    ISNULL(c.placa, 'N/A') AS Placa,
                    m.Camion AS Chasis,
                    COUNT(*) AS TotalAsignadas,
                    SUM(CASE WHEN m.Estado = 'DESPACHADO' THEN 1 ELSE 0 END) AS TotalDespachadas,
                    SUM(CASE WHEN m.Estado = 'ENTREGADO' THEN 1 ELSE 0 END) AS TotalEntregadas,
                    CAST(
                        ROUND(
                            SUM(CASE WHEN m.Estado = 'ENTREGADO' THEN 1.0 ELSE 0 END) / 
                            NULLIF(COUNT(*), 0) * 100, 1
                        ) AS VARCHAR
                    ) + '%' AS PorcentajeEntrega
                FROM Factura_Programa_Despacho_MACOR m
                JOIN Facturas_CTE f ON m.No_Factura = f.invoiceid
                LEFT JOIN Camiones_PW c ON m.Camion = c.chasis
                WHERE m.Fecha_de_Despacho IS NOT NULL
                $almacenSql
                GROUP BY c.nombre, c.placa, m.Camion
                ORDER BY TotalAsignadas DESC
            ";
            break;

        case 'resumen':
            $title = 'Resumen Ejecutivo';
            $columns = [
                'Metrica' => 'Métrica',
                'Valor' => 'Valor'
            ];

            // Obtener estadísticas
            $sqlStats = $cte . "
                SELECT
                    COUNT(DISTINCT f.invoiceid) AS total,
                    SUM(f.invoiceamountmst) AS monto,
                    COUNT(DISTINCT CASE WHEN m.Estado IS NULL THEN f.invoiceid END) AS sinEstado,
                    COUNT(DISTINCT CASE WHEN m.Estado = 'ENTREGADO' THEN f.invoiceid END) AS entregadas,
                    COUNT(DISTINCT CASE WHEN m.Estado = 'DESPACHADO' THEN f.invoiceid END) AS despachadas
                FROM Facturas_CTE f
                LEFT JOIN Factura_Programa_Despacho_MACOR m ON f.invoiceid = m.No_Factura
                WHERE 1=1 $almacenSql
            ";

            $stmtStats = sqlsrv_query($conn, $sqlStats, $params);
            if ($stmtStats && $row = sqlsrv_fetch_array($stmtStats, SQLSRV_FETCH_ASSOC)) {
                $data = [
                    ['Metrica' => 'Total Facturas', 'Valor' => number_format($row['total'])],
                    ['Metrica' => 'Monto Total', 'Valor' => 'RD$ ' . number_format($row['monto'], 2)],
                    ['Metrica' => 'Sin Estado', 'Valor' => number_format($row['sinEstado'])],
                    ['Metrica' => 'Entregadas', 'Valor' => number_format($row['entregadas'])],
                    ['Metrica' => 'Despachadas', 'Valor' => number_format($row['despachadas'])],
                ];
                sqlsrv_free_stmt($stmtStats);
            }
            break;

        default:
            throw new Exception('Tipo de reporte no válido');
    }

    // Ejecutar query si no es resumen (que ya tiene data)
    if ($reporte !== 'resumen') {
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            throw new Exception('Error al consultar datos');
        }

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Formatear fechas
            foreach ($row as $key => $value) {
                if ($value instanceof \DateTime) {
                    $row[$key] = $value->format('Y-m-d H:i:s');
                }
            }
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    // Opciones de exportación
    $options = [
        'subtitle' => $almacen ? "Almacén: $almacen" : 'Todos los almacenes',
        'dateRange' => "Período: $fechaInicio a $fechaFin",
        'showTotals' => true,
        'logo' => '../IMG/LOGO MC - NEGRO.png'
    ];

    // Exportar según tipo
    if ($tipo === 'pdf') {
        $export->toPdf($data, $columns, $title, $options);
    } else {
        $export->toExcel($data, $columns, $title);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
