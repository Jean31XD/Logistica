<?php
/**
 * API Facturas por Camión
 * 
 * Endpoint para obtener facturas asignadas a un camión específico.
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
$camion = $_GET['camion'] ?? '';
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';
$almacen = '';

// Determinar almacén según tipo de usuario
$userType = $_SESSION['dashboard_user_type'] ?? 'guest';
$userWarehouse = $_SESSION['dashboard_warehouse'] ?? '';

if ($userType === 'admin') {
    $almacen = $_GET['almacen'] ?? '';
} else {
    $almacen = $userWarehouse;
}

// Validar parámetros requeridos
if (empty($camion)) {
    http_response_code(400);
    echo json_encode(['error' => 'El parámetro camion es requerido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($conn) || $conn === false) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // CTE para facturas
    $cte = "
        WITH Facturas_CTE AS (
            SELECT invoiceid, invoicingname, invoicedate, inventlocationid
            FROM dbo.CUSTINVOICEJOUR_MXM
        )
    ";

    $almacenSql = !empty($almacen) ? "AND f.inventlocationid = ?" : "";

    $sql = $cte . "
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
            $almacenSql
        ORDER BY m.Estado DESC, m.Fecha_de_Registro DESC
    ";

    $params = [$camion, $fechaInicio, $fechaFin];
    if (!empty($almacen)) {
        $params[] = $almacen;
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        logMessage("Error en api_facturas_camion: " . print_r(sqlsrv_errors(), true), 'ERROR');
        throw new Exception('Error al consultar facturas');
    }

    $facturas = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Formatear fechas
        if (isset($row['FechaDespacho']) && $row['FechaDespacho'] instanceof DateTime) {
            $row['FechaDespacho'] = $row['FechaDespacho']->format('Y-m-d H:i:s');
        }
        if (isset($row['FechaEntregado']) && $row['FechaEntregado'] instanceof DateTime) {
            $row['FechaEntregado'] = $row['FechaEntregado']->format('Y-m-d H:i:s');
        }
        $facturas[] = $row;
    }

    sqlsrv_free_stmt($stmt);
    echo json_encode($facturas, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
