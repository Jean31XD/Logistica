<?php
require '../conexionBD/conexion.php'; // Tu conexión a la base de datos en $conn

header('Content-Type: application/json; charset=utf-8');

    // Obtener parámetros del GET
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$view = $_GET['view'] ?? 'overview'; // El valor por defecto es 'overview'

$whereClause = "";
$params = [];

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $whereClause = " WHERE c.Fecha BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
}

$response = [];

try {
    switch ($view) {

        case 'user_activity':
            // 1️⃣ Facturas registradas por usuario
            $response['registrosPorUsuario'] = [];
            $response['despachadasPorUsuario'] = [];

            if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                $sqlRegistros = "
                    SELECT Registrado_por, COUNT(No_Factura) AS Total
                    FROM Factura_Programa_Despacho_MACOR
                    WHERE Fecha_de_Registro IS NOT NULL
                      AND Fecha_de_Registro BETWEEN ? AND ?
                    GROUP BY Registrado_por
                    ORDER BY Total DESC
                ";
                $paramsRegistros = [$fecha_inicio, $fecha_fin];
            } else {
                $sqlRegistros = "
                    SELECT Registrado_por, COUNT(No_Factura) AS Total
                    FROM Factura_Programa_Despacho_MACOR
                    WHERE Fecha_de_Registro IS NOT NULL
                    GROUP BY Registrado_por
                    ORDER BY Total DESC
                ";
                $paramsRegistros = [];
            }

            $stmtRegistros = sqlsrv_query($conn, $sqlRegistros, $paramsRegistros);
            if ($stmtRegistros) {
                while ($row = sqlsrv_fetch_array($stmtRegistros, SQLSRV_FETCH_ASSOC)) {
                    $response['registrosPorUsuario'][] = $row;
                }
            }

            // 2️⃣ Facturas despachadas por usuario
         // 2️⃣ Facturas despachadas por usuario
if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sqlDespachadas = "
        SELECT Despachado_por, COUNT(No_Factura) AS Total
        FROM Factura_Programa_Despacho_MACOR
        WHERE Fecha_de_Despacho IS NOT NULL
          AND Fecha_de_Despacho BETWEEN ? AND ?
        GROUP BY Despachado_por
        ORDER BY Total DESC
    ";
    $paramsDespachadas = [$fecha_inicio, $fecha_fin];
} else {
    $sqlDespachadas = "
        SELECT Despachado_por, COUNT(No_Factura) AS Total
        FROM Factura_Programa_Despacho_MACOR
        WHERE Fecha_de_Despacho IS NOT NULL
        GROUP BY Despachado_por
        ORDER BY Total DESC
    ";
    $paramsDespachadas = [];
}

$stmtDespachadas = sqlsrv_query($conn, $sqlDespachadas, $paramsDespachadas);
if ($stmtDespachadas) {
    while ($row = sqlsrv_fetch_array($stmtDespachadas, SQLSRV_FETCH_ASSOC)) {
        $response['despachadasPorUsuario'][] = $row;
    }
}

                
            
            break;

        case 'trends':
            $response['tendenciaRegistros'] = [];

            if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                $sqlTendencia = "
                    SELECT CAST(Fecha_de_Registro AS DATE) AS Dia, COUNT(No_Factura) AS Total
                    FROM Factura_Programa_Despacho_MACOR
                    WHERE Fecha_de_Registro IS NOT NULL
                      AND Fecha_de_Registro BETWEEN ? AND ?
                    GROUP BY CAST(Fecha_de_Registro AS DATE)
                    ORDER BY Dia ASC
                ";
                $paramsTendencia = [$fecha_inicio, $fecha_fin];
            } else {
                $sqlTendencia = "
                    SELECT CAST(Fecha_de_Registro AS DATE) AS Dia, COUNT(No_Factura) AS Total
                    FROM Factura_Programa_Despacho_MACOR
                    WHERE Fecha_de_Registro IS NOT NULL
                    GROUP BY CAST(Fecha_de_Registro AS DATE)
                    ORDER BY Dia ASC
                ";
                $paramsTendencia = [];
            }

            $stmtTendencia = sqlsrv_query($conn, $sqlTendencia, $paramsTendencia);
            if ($stmtTendencia) {
                while ($row = sqlsrv_fetch_array($stmtTendencia, SQLSRV_FETCH_ASSOC)) {
                    $response['tendenciaRegistros'][] = [
                        'Dia' => $row['Dia']->format('Y-m-d'),
                        'Total' => $row['Total']
                    ];
                }
            }
            break;

        case 'overview':
        default:
            $response['totalEmitidas'] = 0;
            $response['sinEstado'] = 0;
            $response['estadosData'] = [];

            // Total de facturas emitidas
            $sqlTotal = "SELECT COUNT(*) AS TotalEmitidas FROM custinvoicejour c" . $whereClause;
            $stmtTotal = sqlsrv_query($conn, $sqlTotal, $params);
            if ($stmtTotal) {
                $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC);
                $response['totalEmitidas'] = $row['TotalEmitidas'] ?? 0;
            }

            // Facturas agrupadas por estado
            $sqlEstados = "
                SELECT d.Estado, COUNT(*) AS Total
                FROM custinvoicejour c
                INNER JOIN Factura_Programa_Despacho_MACOR d ON c.FACTURA = d.No_Factura
                " . $whereClause . "
                GROUP BY d.Estado ORDER BY Total DESC
            ";
            $stmtEstados = sqlsrv_query($conn, $sqlEstados, $params);
            if ($stmtEstados) {
                while ($row = sqlsrv_fetch_array($stmtEstados, SQLSRV_FETCH_ASSOC)) {
                    $response['estadosData'][] = $row;
                }
            }

            // Facturas emitidas sin estado
            $sqlSinEstado = "
                SELECT COUNT(*) AS SinEstado
                FROM custinvoicejour c
                LEFT JOIN Factura_Programa_Despacho_MACOR d ON c.FACTURA = d.No_Factura
                WHERE d.No_Factura IS NULL " . (empty($whereClause) ? '' : ' AND' . substr($whereClause, 6));
            $stmtSinEstado = sqlsrv_query($conn, $sqlSinEstado, $params);
            if ($stmtSinEstado) {
                $row = sqlsrv_fetch_array($stmtSinEstado, SQLSRV_FETCH_ASSOC);
                $response['sinEstado'] = $row['SinEstado'] ?? 0;
            }

            if ($response['sinEstado'] > 0) {
                $response['estadosData'][] = ['Estado' => 'Sin estado', 'Total' => $response['sinEstado']];
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
} finally {
    if (isset($conn)) sqlsrv_close($conn);
}

echo json_encode($response);
