<?php
/**
 * API Reportes de Tickets - MACO
 * 
 * Retorna estadísticas de tiempo de atención y retención de tickets.
 */

require_once __DIR__ . '/../conexionBD/session_config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexionBD/conexion.php';

// Establecer timeout de bloqueo para evitar esperas indefinidas
$timeoutQuery = "SET LOCK_TIMEOUT 10000"; // 10 segundos
sqlsrv_query($conn, $timeoutQuery);

if (!isset($_SESSION['usuario']) || !tieneModulo('reporte_despacho', $conn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Sin permisos']));
}

if (!$conn) {
    http_response_code(500);
    die(json_encode(['error' => 'Error de conexión a la base de datos.']));
}

// Parámetros
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'resumen'; // resumen, tiempo_usuario, retenciones

try {
    $response = [];

    switch ($tipo) {
        case 'resumen':
            // Estadísticas generales desde tabla analisis
            $sqlResumen = "
                SELECT 
                    COUNT(*) AS TotalDespachados,
                    AVG(DATEPART(HOUR, Tiempo) * 60 + DATEPART(MINUTE, Tiempo)) AS TiempoPromedioGeneral
                FROM analisis
                WHERE Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
            ";
            $stmt = sqlsrv_query($conn, $sqlResumen, [$fechaInicio, $fechaFin]);
            
            if ($stmt === false) {
                $response['error_resumen'] = sqlsrv_errors();
            } elseif ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $response['totalDespachados'] = $row['TotalDespachados'] ?? 0;
                $response['tiempoPromedioGeneral'] = round($row['TiempoPromedioGeneral'] ?? 0, 1);
            }

            // Tickets en retención actual
            $sqlRetencion = "SELECT COUNT(*) AS EnRetencion FROM [log] WHERE Estatus = 'Retencion'";
            $stmtRet = sqlsrv_query($conn, $sqlRetencion);
            if ($stmtRet && $row = sqlsrv_fetch_array($stmtRet, SQLSRV_FETCH_ASSOC)) {
                $response['ticketsEnRetencion'] = $row['EnRetencion'] ?? 0;
            }

            // Tiempo promedio de retención desde tabla retenciones
            $sqlAvgRet = "
                SELECT AVG(DATEDIFF(MINUTE, Fecha_de_Creacion, COALESCE(Fecha_de_Despacho, GETDATE()))) AS TiempoPromedioRetencion
                FROM retenciones
                WHERE Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
            ";
            $stmtAvgRet = sqlsrv_query($conn, $sqlAvgRet, [$fechaInicio, $fechaFin]);
            if ($stmtAvgRet && $row = sqlsrv_fetch_array($stmtAvgRet, SQLSRV_FETCH_ASSOC)) {
                $response['tiempoPromedioRetencion'] = round($row['TiempoPromedioRetencion'] ?? 0, 1);
            }

            // Tickets "Se fue" (Factura = 'Se fue' en tabla analisis)
            $sqlSeFue = "
                SELECT COUNT(*) AS TotalSeFue
                FROM analisis
                WHERE Factura = 'Se fue'
                    AND Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
            ";
            $stmtSeFue = sqlsrv_query($conn, $sqlSeFue, [$fechaInicio, $fechaFin]);
            if ($stmtSeFue && $row = sqlsrv_fetch_array($stmtSeFue, SQLSRV_FETCH_ASSOC)) {
                $response['ticketsSeFue'] = $row['TotalSeFue'] ?? 0;
            }

            // Monto total de facturas despachadas (usando Facturas_lineas)
            // OPTIMIZADO: Se usa una sub-consulta para primero filtrar analisis
            $sqlMonto = "
                SELECT 
                    COALESCE(SUM(f.lineamount), 0) AS MontoTotal
                FROM (
                    SELECT DISTINCT Factura 
                    FROM analisis 
                    WHERE Fecha_de_Creacion >= ?
                        AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                        AND Factura != 'Se fue'
                ) a
                INNER JOIN Facturas_lineas f ON a.Factura = f.invoiceid
            ";
            $stmtMonto = sqlsrv_query($conn, $sqlMonto, [$fechaInicio, $fechaFin]);
            if ($stmtMonto && $row = sqlsrv_fetch_array($stmtMonto, SQLSRV_FETCH_ASSOC)) {
                $response['montoTotal'] = round($row['MontoTotal'] ?? 0, 2);
            }

            // Monto por almacén
            // OPTIMIZADO: Se usa una sub-consulta para primero filtrar analisis
            $sqlMontoAlmacen = "
                SELECT 
                    f.inventlocationid AS Almacen,
                    COALESCE(SUM(f.lineamount), 0) AS Monto,
                    COUNT(DISTINCT a.Factura) AS TotalFacturas
                FROM (
                    SELECT DISTINCT Factura 
                    FROM analisis 
                    WHERE Fecha_de_Creacion >= ?
                        AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                        AND Factura != 'Se fue'
                ) a
                INNER JOIN Facturas_lineas f ON a.Factura = f.invoiceid
                GROUP BY f.inventlocationid
                ORDER BY Monto DESC
            ";
            $stmtAlmacen = sqlsrv_query($conn, $sqlMontoAlmacen, [$fechaInicio, $fechaFin]);
            $response['montoPorAlmacen'] = [];
            
            if ($stmtAlmacen) {
                while ($row = sqlsrv_fetch_array($stmtAlmacen, SQLSRV_FETCH_ASSOC)) {
                    $response['montoPorAlmacen'][] = [
                        'almacen' => $row['Almacen'] ?? 'Sin almacén',
                        'monto' => round($row['Monto'] ?? 0, 2),
                        'totalFacturas' => $row['TotalFacturas']
                    ];
                }
            }

            // Total de empresas únicas atendidas
            $sqlEmpresas = "
                SELECT COUNT(DISTINCT Empresa) AS TotalEmpresas
                FROM analisis
                WHERE Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                    AND Empresa IS NOT NULL
                    AND Empresa != ''
            ";
            $stmtEmpresas = sqlsrv_query($conn, $sqlEmpresas, [$fechaInicio, $fechaFin]);
            if ($stmtEmpresas && $row = sqlsrv_fetch_array($stmtEmpresas, SQLSRV_FETCH_ASSOC)) {
                $response['totalEmpresas'] = $row['TotalEmpresas'] ?? 0;
            }
            break;

        case 'tendencia_diaria':
            // Datos diarios para gráfico de líneas (despachados y retenciones por día)
            $sqlDespachados = "
                SELECT 
                    CAST(Fecha_de_Creacion AS DATE) AS Fecha,
                    COUNT(*) AS Total
                FROM analisis
                WHERE Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                GROUP BY CAST(Fecha_de_Creacion AS DATE)
                ORDER BY Fecha
            ";
            $stmtDesp = sqlsrv_query($conn, $sqlDespachados, [$fechaInicio, $fechaFin]);
            $response['despachados'] = [];
            
            if ($stmtDesp) {
                while ($row = sqlsrv_fetch_array($stmtDesp, SQLSRV_FETCH_ASSOC)) {
                    $fecha = $row['Fecha'] instanceof DateTime ? $row['Fecha']->format('Y-m-d') : $row['Fecha'];
                    $response['despachados'][] = [
                        'fecha' => $fecha,
                        'total' => $row['Total']
                    ];
                }
            }
            
            // Retenciones por día
            $sqlRetenciones = "
                SELECT 
                    CAST(Fecha_de_Creacion AS DATE) AS Fecha,
                    COUNT(*) AS Total
                FROM retenciones
                WHERE Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                GROUP BY CAST(Fecha_de_Creacion AS DATE)
                ORDER BY Fecha
            ";
            $stmtRet = sqlsrv_query($conn, $sqlRetenciones, [$fechaInicio, $fechaFin]);
            $response['retenciones'] = [];
            
            if ($stmtRet) {
                while ($row = sqlsrv_fetch_array($stmtRet, SQLSRV_FETCH_ASSOC)) {
                    $fecha = $row['Fecha'] instanceof DateTime ? $row['Fecha']->format('Y-m-d') : $row['Fecha'];
                    $response['retenciones'][] = [
                        'fecha' => $fecha,
                        'total' => $row['Total']
                    ];
                }
            }
            
            // "Se fue" por día
            $sqlSeFue = "
                SELECT 
                    CAST(Fecha_de_Creacion AS DATE) AS Fecha,
                    COUNT(*) AS Total
                FROM analisis
                WHERE Factura = 'Se fue'
                    AND Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                GROUP BY CAST(Fecha_de_Creacion AS DATE)
                ORDER BY Fecha
            ";
            $stmtSeFue = sqlsrv_query($conn, $sqlSeFue, [$fechaInicio, $fechaFin]);
            $response['seFue'] = [];
            
            if ($stmtSeFue) {
                while ($row = sqlsrv_fetch_array($stmtSeFue, SQLSRV_FETCH_ASSOC)) {
                    $fecha = $row['Fecha'] instanceof DateTime ? $row['Fecha']->format('Y-m-d') : $row['Fecha'];
                    $response['seFue'][] = [
                        'fecha' => $fecha,
                        'total' => $row['Total']
                    ];
                }
            }
            break;

        case 'tiempo_usuario':
            // Tiempo promedio de atención por usuario desde tabla analisis
            $sql = "
                SELECT 
                    Asignado AS Usuario,
                    COUNT(*) AS TotalTickets,
                    AVG(DATEPART(HOUR, Tiempo) * 60 + DATEPART(MINUTE, Tiempo)) AS TiempoPromedioMinutos,
                    MIN(DATEPART(HOUR, Tiempo) * 60 + DATEPART(MINUTE, Tiempo)) AS TiempoMinimo,
                    MAX(DATEPART(HOUR, Tiempo) * 60 + DATEPART(MINUTE, Tiempo)) AS TiempoMaximo
                FROM analisis
                WHERE Asignado IS NOT NULL
                    AND Asignado != ''
                    AND Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                GROUP BY Asignado
                ORDER BY TiempoPromedioMinutos ASC
            ";
            
            $stmt = sqlsrv_query($conn, $sql, [$fechaInicio, $fechaFin]);
            $response['usuarios'] = [];
            
            if ($stmt === false) {
                $response['error_usuarios'] = sqlsrv_errors();
            } else {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $response['usuarios'][] = [
                        'usuario' => $row['Usuario'],
                        'totalTickets' => $row['TotalTickets'],
                        'tiempoPromedio' => round($row['TiempoPromedioMinutos'] ?? 0, 1),
                        'tiempoMinimo' => round($row['TiempoMinimo'] ?? 0, 1),
                        'tiempoMaximo' => round($row['TiempoMaximo'] ?? 0, 1)
                    ];
                }
            }
            break;

        case 'retenciones':
            // Detalles de retenciones
            $sql = "
                SELECT 
                    r.Tiket,
                    r.Asignado AS Usuario,
                    r.Fecha_de_Creacion AS FechaInicio,
                    r.Fecha_de_Despacho AS FechaFin,
                    DATEDIFF(MINUTE, r.Fecha_de_Creacion, COALESCE(r.Fecha_de_Despacho, GETDATE())) AS TiempoRetencionMinutos
                FROM retenciones r
                WHERE r.Fecha_de_Creacion >= ?
                    AND r.Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                ORDER BY r.Fecha_de_Creacion DESC
            ";
            
            $stmt = sqlsrv_query($conn, $sql, [$fechaInicio, $fechaFin]);
            $response['retenciones'] = [];
            
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $fechaInicio_ret = $row['FechaInicio'] instanceof DateTime ? $row['FechaInicio']->format('Y-m-d H:i') : $row['FechaInicio'];
                    $fechaFin_ret = $row['FechaFin'] instanceof DateTime ? $row['FechaFin']->format('Y-m-d H:i') : ($row['FechaFin'] ?? 'En curso');
                    
                    $response['retenciones'][] = [
                        'tiket' => $row['Tiket'],
                        'usuario' => $row['Usuario'] ?? 'N/A',
                        'fechaInicio' => $fechaInicio_ret,
                        'fechaFin' => $fechaFin_ret,
                        'tiempoMinutos' => round($row['TiempoRetencionMinutos'] ?? 0, 1)
                    ];
                }
            }

            // Agregado: Tiempo promedio por usuario
            $sqlByUser = "
                SELECT 
                    r.Asignado AS Usuario,
                    COUNT(*) AS TotalRetenciones,
                    AVG(DATEDIFF(MINUTE, r.Fecha_de_Creacion, COALESCE(r.Fecha_de_Despacho, GETDATE()))) AS TiempoPromedioMinutos
                FROM retenciones r
                WHERE r.Fecha_de_Creacion >= ?
                    AND r.Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                    AND r.Asignado IS NOT NULL
                    AND r.Asignado != ''
                GROUP BY r.Asignado
                ORDER BY TiempoPromedioMinutos DESC
            ";
            $stmtByUser = sqlsrv_query($conn, $sqlByUser, [$fechaInicio, $fechaFin]);
            $response['retencionPorUsuario'] = [];
            
            if ($stmtByUser) {
                while ($row = sqlsrv_fetch_array($stmtByUser, SQLSRV_FETCH_ASSOC)) {
                    $response['retencionPorUsuario'][] = [
                        'usuario' => $row['Usuario'],
                        'totalRetenciones' => $row['TotalRetenciones'],
                        'tiempoPromedio' => round($row['TiempoPromedioMinutos'] ?? 0, 1)
                    ];
                }
            }
            break;

        case 'clientes_usuario':
            // Clientes (empresas) atendidos por cada usuario
            $sql = "
                SELECT 
                    a.Asignado AS Usuario,
                    a.Empresa,
                    COUNT(*) AS TotalTickets,
                    AVG(DATEPART(HOUR, a.Tiempo) * 60 + DATEPART(MINUTE, a.Tiempo)) AS TiempoPromedio,
                    COALESCE(SUM(f.lineamount), 0) AS MontoTotal
                FROM analisis a
                LEFT JOIN Facturas_lineas f ON a.Factura = f.invoiceid
                WHERE a.Asignado IS NOT NULL
                    AND a.Asignado != ''
                    AND a.Fecha_de_Creacion >= ?
                    AND a.Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                GROUP BY a.Asignado, a.Empresa
                ORDER BY a.Asignado, TotalTickets DESC
            ";
            
            $stmt = sqlsrv_query($conn, $sql, [$fechaInicio, $fechaFin]);
            $response['clientesPorUsuario'] = [];
            
            if ($stmt) {
                $usuarioActual = '';
                $clientesUsuario = [];
                
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $usuario = $row['Usuario'];
                    
                    if ($usuarioActual !== $usuario && $usuarioActual !== '') {
                        $response['clientesPorUsuario'][] = [
                            'usuario' => $usuarioActual,
                            'clientes' => $clientesUsuario
                        ];
                        $clientesUsuario = [];
                    }
                    
                    $usuarioActual = $usuario;
                    $clientesUsuario[] = [
                        'empresa' => $row['Empresa'] ?? 'Sin empresa',
                        'totalTickets' => $row['TotalTickets'],
                        'tiempoPromedio' => round($row['TiempoPromedio'] ?? 0, 1),
                        'monto' => round($row['MontoTotal'] ?? 0, 2)
                    ];
                }
                
                // Añadir último usuario
                if ($usuarioActual !== '') {
                    $response['clientesPorUsuario'][] = [
                        'usuario' => $usuarioActual,
                        'clientes' => $clientesUsuario
                    ];
                }
            }
            
            // Resumen: Top empresas más atendidas
            $sqlTop = "
                SELECT TOP 10
                    Empresa,
                    COUNT(*) AS TotalTickets,
                    COUNT(DISTINCT Asignado) AS UsuariosQueAtendieron
                FROM analisis
                WHERE Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                GROUP BY Empresa
                ORDER BY TotalTickets DESC
            ";
            $stmtTop = sqlsrv_query($conn, $sqlTop, [$fechaInicio, $fechaFin]);
            $response['topEmpresas'] = [];
            
            if ($stmtTop) {
                while ($row = sqlsrv_fetch_array($stmtTop, SQLSRV_FETCH_ASSOC)) {
                    $response['topEmpresas'][] = [
                        'empresa' => $row['Empresa'] ?? 'Sin empresa',
                        'totalTickets' => $row['TotalTickets'],
                        'usuariosQueAtendieron' => $row['UsuariosQueAtendieron']
                    ];
                }
            }
            break;

        case 'detalle_usuario':
            // Detalle completo de un usuario específico
            $usuario = $_GET['usuario'] ?? '';
            if (empty($usuario)) {
                throw new Exception('Usuario requerido');
            }
            
            // Tickets del usuario
            $sqlTickets = "
                SELECT 
                    Nombre,
                    Empresa,
                    Factura,
                    Fecha_de_Creacion,
                    Fecha_de_Despacho,
                    DATEPART(HOUR, Tiempo) * 60 + DATEPART(MINUTE, Tiempo) AS TiempoMinutos
                FROM analisis
                WHERE Asignado = ?
                    AND Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                ORDER BY Fecha_de_Creacion DESC
            ";
            $stmt = sqlsrv_query($conn, $sqlTickets, [$usuario, $fechaInicio, $fechaFin]);
            $response['tickets'] = [];
            
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $fechaCreacion = $row['Fecha_de_Creacion'] instanceof DateTime ? $row['Fecha_de_Creacion']->format('Y-m-d H:i') : $row['Fecha_de_Creacion'];
                    $fechaDespacho = $row['Fecha_de_Despacho'] instanceof DateTime ? $row['Fecha_de_Despacho']->format('Y-m-d H:i') : $row['Fecha_de_Despacho'];
                    
                    $response['tickets'][] = [
                        'nombre' => $row['Nombre'],
                        'empresa' => $row['Empresa'],
                        'factura' => $row['Factura'],
                        'fechaCreacion' => $fechaCreacion,
                        'fechaDespacho' => $fechaDespacho,
                        'tiempoMinutos' => $row['TiempoMinutos']
                    ];
                }
            }
            
            // Retenciones del usuario
            $sqlRet = "
                SELECT 
                    Tiket,
                    Empresa,
                    Fecha_de_Creacion,
                    Fecha_de_Despacho,
                    DATEDIFF(MINUTE, Fecha_de_Creacion, COALESCE(Fecha_de_Despacho, GETDATE())) AS TiempoMinutos
                FROM retenciones
                WHERE Asignado = ?
                    AND Fecha_de_Creacion >= ?
                    AND Fecha_de_Creacion <= DATEADD(DAY, 1, CAST(? AS DATE))
                ORDER BY Fecha_de_Creacion DESC
            ";
            $stmtRet = sqlsrv_query($conn, $sqlRet, [$usuario, $fechaInicio, $fechaFin]);
            $response['retenciones'] = [];
            
            if ($stmtRet) {
                while ($row = sqlsrv_fetch_array($stmtRet, SQLSRV_FETCH_ASSOC)) {
                    $fechaInicio_r = $row['Fecha_de_Creacion'] instanceof DateTime ? $row['Fecha_de_Creacion']->format('Y-m-d H:i') : $row['Fecha_de_Creacion'];
                    $fechaFin_r = $row['Fecha_de_Despacho'] instanceof DateTime ? $row['Fecha_de_Despacho']->format('Y-m-d H:i') : ($row['Fecha_de_Despacho'] ?? 'En curso');
                    
                    $response['retenciones'][] = [
                        'tiket' => $row['Tiket'],
                        'empresa' => $row['Empresa'],
                        'fechaInicio' => $fechaInicio_r,
                        'fechaFin' => $fechaFin_r,
                        'tiempoMinutos' => $row['TiempoMinutos']
                    ];
                }
            }
            break;

        default:
            throw new Exception('Tipo de reporte no válido');
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

sqlsrv_close($conn);
?>
