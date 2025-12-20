<?php
/**
 * Obtener Códigos de Referencia
 * Retorna todos los códigos con filtros y paginación
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

// Verificar autenticación básica
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

// Verificar permiso usando usuario_modulos
if (!tieneModulo('codigos_referencia', $conn)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos']);
    exit();
}

try {
    // Obtener parámetros
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(250, intval($_GET['pageSize']))) : 25;
    $searchNombre = isset($_GET['searchNombre']) ? trim($_GET['searchNombre']) : '';
    $searchCodigo = isset($_GET['searchCodigo']) ? trim($_GET['searchCodigo']) : '';
    $filterEstado = isset($_GET['filterEstado']) ? trim($_GET['filterEstado']) : '';

    $offset = ($page - 1) * $pageSize;

    // Construir condición WHERE
    $whereConditions = "WHERE 1=1";
    $params = array();

    // Filtro por nombre
    if (!empty($searchNombre)) {
        $whereConditions .= " AND Nombre LIKE ?";
        $params[] = '%' . $searchNombre . '%';
    }

    // Filtro por código
    if (!empty($searchCodigo)) {
        $whereConditions .= " AND Codigo_barra LIKE ?";
        $params[] = '%' . $searchCodigo . '%';
    }

    // Filtro por estado
    if ($filterEstado === 'asignado') {
        $whereConditions .= " AND Codigo_barra IS NOT NULL AND Codigo_barra != '' AND LEN(RTRIM(LTRIM(Codigo_barra))) > 0";
    } elseif ($filterEstado === 'sin_asignar') {
        $whereConditions .= " AND (Codigo_barra IS NULL OR Codigo_barra = '' OR LEN(RTRIM(LTRIM(Codigo_barra))) = 0)";
    }

    // Consulta para contar el total de registros
    $sqlCount = "SELECT COUNT(*) as total FROM [dbo].[Arti_codigos] $whereConditions";
    $stmtCount = sqlsrv_query($conn, $sqlCount, $params);

    if ($stmtCount === false) {
        throw new Exception("Error al contar registros: " . print_r(sqlsrv_errors(), true));
    }

    $totalRow = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalItems = $totalRow['total'];
    sqlsrv_free_stmt($stmtCount);

    // Consulta para obtener estadísticas
    $sqlStats = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN Codigo_barra IS NOT NULL AND Codigo_barra != '' AND LEN(RTRIM(LTRIM(Codigo_barra))) > 0 THEN 1 ELSE 0 END) as asignados,
                    SUM(CASE WHEN Codigo_barra IS NULL OR Codigo_barra = '' OR LEN(RTRIM(LTRIM(Codigo_barra))) = 0 THEN 1 ELSE 0 END) as sinAsignar
                 FROM [dbo].[Arti_codigos]";
    $stmtStats = sqlsrv_query($conn, $sqlStats);

    if ($stmtStats === false) {
        throw new Exception("Error al obtener estadísticas: " . print_r(sqlsrv_errors(), true));
    }

    $stats = sqlsrv_fetch_array($stmtStats, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtStats);

    // Consulta para obtener datos con paginación
    $sql = "SELECT id, Nombre, Codigo_barra, Usuario
            FROM [dbo].[Arti_codigos]
            $whereConditions
            ORDER BY id ASC
            OFFSET ? ROWS
            FETCH NEXT ? ROWS ONLY";

    $params[] = $offset;
    $params[] = $pageSize;

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }

    $datos = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $datos[] = array(
            'id' => $row['id'],
            'Nombre' => $row['Nombre'],
            'Codigo_barra' => $row['Codigo_barra'],
            'Usuario' => $row['Usuario']
        );
    }

    sqlsrv_free_stmt($stmt);

    // Calcular información de paginación
    $totalPages = ceil($totalItems / $pageSize);
    $showingFrom = $totalItems > 0 ? $offset + 1 : 0;
    $showingTo = min($offset + $pageSize, $totalItems);

    echo json_encode(array(
        'success' => true,
        'datos' => $datos,
        'stats' => array(
            'total' => $stats['total'],
            'asignados' => $stats['asignados'],
            'sinAsignar' => $stats['sinAsignar']
        ),
        'pagination' => array(
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'showingFrom' => $showingFrom,
            'showingTo' => $showingTo,
            'hasNextPage' => $page < $totalPages,
            'hasPrevPage' => $page > 1
        )
    ));

} catch (Exception $e) {
    error_log("Error en obtener_codigos_referencia.php: " . $e->getMessage());

    echo json_encode(array(
        'success' => false,
        'message' => 'Error al obtener los datos',
        'error' => $e->getMessage()
    ));
}
?>
