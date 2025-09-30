<?php
require '../conexionBD/conexion.php'; // Tu conexión a la base de datos en $conn

header('Content-Type: application/json; charset=utf-8');

    // Obtener parámetros del GET
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$view = $_GET['view'] ?? 'overview';

$response = [];

try {
    switch ($view) {
case 'details':
    // Normalizar fechas a 'YYYY-MM-DD' para evitar problemas con formatos
    if (!empty($fecha_inicio)) $fecha_inicio = date('Y-m-d', strtotime($fecha_inicio));
    if (!empty($fecha_fin))    $fecha_fin    = date('Y-m-d', strtotime($fecha_fin));

    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $estado = isset($_GET['estado']) ? trim(urldecode($_GET['estado'])) : '';
    $detailsData = [];

    if (empty($estado) || empty($fecha_inicio) || empty($fecha_fin)) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan parámetros para obtener los detalles.']);
        exit;
    }

    if ($estado === 'Sin estado') {
        // Conteo usando CAST sobre c.Fecha
        $sqlCount = "
            SELECT COUNT(c.FACTURA) AS Total
            FROM custinvoicejour c
            LEFT JOIN Factura_Programa_Despacho_MACOR d ON c.FACTURA = d.No_Factura
            WHERE d.No_Factura IS NULL 
              AND CAST(c.Fecha AS DATE) BETWEEN ? AND ?
        ";
        $countParams = [$fecha_inicio, $fecha_fin];

        // Datos (mismas columnas fijas), usando CAST(c.Fecha AS DATE)
        $sqlDetails = "
            SELECT 
                NULL AS ID,
                c.FACTURA AS No_Factura,
                c.Fecha AS Fecha_de_Registro,
                NULL AS Registrado_por,
                NULL AS Camion,
                NULL AS Fecha_de_Despacho,
                NULL AS Despachado_por,
                NULL AS Fecha_de_Entregado,
                NULL AS Entregado_por,
                'Sin estado' AS Estado,
                NULL AS Fecha_Reversada,
                NULL AS Reversado_Por,
                NULL AS Fecha_de_NC,
                NULL AS NC_Realizado_Por,
                NULL AS Motivo_NC,
                NULL AS Camion2
            FROM custinvoicejour c
            LEFT JOIN Factura_Programa_Despacho_MACOR d ON c.FACTURA = d.No_Factura
            WHERE d.No_Factura IS NULL 
              AND CAST(c.Fecha AS DATE) BETWEEN ? AND ?
            ORDER BY c.Fecha DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ";
        $detailsParams = [$fecha_inicio, $fecha_fin, $offset, $limit];

    } else {
        // Conteo usando CAST sobre Fecha_de_Registro
        $sqlCount = "
            SELECT COUNT(*) AS Total
            FROM Factura_Programa_Despacho_MACOR
            WHERE Estado = ? 
              AND CAST(Fecha_de_Registro AS DATE) BETWEEN ? AND ?
        ";
        $countParams = [$estado, $fecha_inicio, $fecha_fin];

        // Datos usando CAST sobre Fecha_de_Registro
        $sqlDetails = "
            SELECT 
                ID,
                No_Factura,
                Fecha_de_Registro,
                Registrado_por,
                Camion,
                Fecha_de_Despacho,
                Despachado_por,
                Fecha_de_Entregado,
                Entregado_por,
                Estado,
                Fecha_Reversada,
                Reversado_Por,
                Fecha_de_NC,
                NC_Realizado_Por,
                Motivo_NC,
                Camion2
            FROM Factura_Programa_Despacho_MACOR
            WHERE Estado = ? 
              AND CAST(Fecha_de_Registro AS DATE) BETWEEN ? AND ?
            ORDER BY Fecha_de_Registro DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ";
        $detailsParams = [$estado, $fecha_inicio, $fecha_fin, $offset, $limit];
    }

    // Ejecutar conteo
    $stmtCount = sqlsrv_query($conn, $sqlCount, $countParams);
    $totalRecords = 0;
    if ($stmtCount && $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
        $totalRecords = $row['Total'] ?? 0;
    }

    // Ejecutar consulta de detalles
    $stmtDetails = sqlsrv_query($conn, $sqlDetails, $detailsParams);
    if ($stmtDetails) {
        while ($row = sqlsrv_fetch_array($stmtDetails, SQLSRV_FETCH_ASSOC)) {
            $detailsData[] = array_merge([
                "ID" => null,
                "No_Factura" => null,
                "Fecha_de_Registro" => null,
                "Registrado_por" => null,
                "Camion" => null,
                "Fecha_de_Despacho" => null,
                "Despachado_por" => null,
                "Fecha_de_Entregado" => null,
                "Entregado_por" => null,
                "Estado" => null,
                "Fecha_Reversada" => null,
                "Reversado_Por" => null,
                "Fecha_de_NC" => null,
                "NC_Realizado_Por" => null,
                "Motivo_NC" => null,
                "Camion2" => null
            ], $row);
        }
    }

    $response = [
        'data' => $detailsData,
        'totalRecords' => $totalRecords,
        'currentPage' => $page,
        'limit' => $limit,
        'totalPages' => ($limit>0) ? ceil($totalRecords / $limit) : 0
    ];
    break;

case 'trends':
    $response['tendenciaRegistros'] = [];

    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sqlTendencia = "
            SELECT 
                CAST(Fecha_de_Registro AS DATE) AS Dia,
                DATENAME(weekday, Fecha_de_Registro) AS DiaSemana,
                COUNT(No_Factura) AS Total
            FROM Factura_Programa_Despacho_MACOR
            WHERE Fecha_de_Registro IS NOT NULL
              AND CAST(Fecha_de_Registro AS DATE) BETWEEN ? AND ?
            GROUP BY CAST(Fecha_de_Registro AS DATE), DATENAME(weekday, Fecha_de_Registro)
            ORDER BY Dia ASC
        ";
        $paramsTendencia = [$fecha_inicio, $fecha_fin];
    } else {
        $sqlTendencia = "
            SELECT 
                CAST(Fecha_de_Registro AS DATE) AS Dia,
                DATENAME(weekday, Fecha_de_Registro) AS DiaSemana,
                COUNT(No_Factura) AS Total
            FROM Factura_Programa_Despacho_MACOR
            WHERE Fecha_de_Registro IS NOT NULL
            GROUP BY CAST(Fecha_de_Registro AS DATE), DATENAME(weekday, Fecha_de_Registro)
            ORDER BY Dia ASC
        ";
        $paramsTendencia = [];
    }

    $stmtTendencia = sqlsrv_query($conn, $sqlTendencia, $paramsTendencia);
    if ($stmtTendencia) {
        while ($row = sqlsrv_fetch_array($stmtTendencia, SQLSRV_FETCH_ASSOC)) {
            $response['tendenciaRegistros'][] = [
                'Dia' => $row['Dia']->format('Y-m-d'),      // Fecha exacta
                'DiaSemana' => $row['DiaSemana'],           // Nombre del día de la semana
                'Total' => $row['Total']
            ];
        }
    }
    break;

            
    case 'overview':
default:
    $params = [];
    $whereClause = "";
 if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $whereClause = " WHERE CAST(c.Fecha AS DATE) BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
}


    // 1. Total de facturas emitidas (todas en custinvoicejour)
    $sqlTotal = "SELECT COUNT(*) AS TotalEmitidas FROM custinvoicejour c" . $whereClause;
    $stmtTotal = sqlsrv_query($conn, $sqlTotal, $params);
    $rowTotal = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC);
    $response['totalEmitidas'] = $rowTotal['TotalEmitidas'] ?? 0;

   // 2. Facturas agrupadas por estado (solo en la tabla MACOR)
$sqlEstados = "
    SELECT Estado, COUNT(*) AS Total
    FROM Factura_Programa_Despacho_MACOR
    WHERE CAST(Fecha_de_Registro AS DATE) BETWEEN ? AND ?
    GROUP BY Estado
    ORDER BY Total DESC
";
$stmtEstados = sqlsrv_query($conn, $sqlEstados, $params);
$response['estadosData'] = [];
if ($stmtEstados) {
    while ($row = sqlsrv_fetch_array($stmtEstados, SQLSRV_FETCH_ASSOC)) {
        $response['estadosData'][] = $row;
    }
}


    // 3. Facturas emitidas SIN estado (facturas en custinvoicejour que no están en MACOR)
    $whereClauseSinEstado = empty($whereClause) ? '' : ' AND' . substr($whereClause, 6);
    $sqlSinEstado = "
        SELECT COUNT(*) AS SinEstado
        FROM custinvoicejour c
        LEFT JOIN Factura_Programa_Despacho_MACOR d ON c.FACTURA = d.No_Factura
        WHERE d.No_Factura IS NULL " . $whereClauseSinEstado;
    $stmtSinEstado = sqlsrv_query($conn, $sqlSinEstado, $params);
    $rowSinEstado = sqlsrv_fetch_array($stmtSinEstado, SQLSRV_FETCH_ASSOC);
    $response['sinEstado'] = $rowSinEstado['SinEstado'] ?? 0;

    // 🔹 No lo mezclamos con estadosData para no afectar los demás conteos
    break;

    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
} finally {
    if (isset($conn)) sqlsrv_close($conn);
}

    echo json_encode($response);