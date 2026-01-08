<?php
/**
 * Obtener Artículos sin Código de Barras
 * Retorna artículos que no tienen código asignado o tienen código vacío
 * Soporta paginación y búsqueda
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!isset($_SESSION['usuario']) || !tieneModulo('codigos_barras', $conn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Sin permisos']));
}

try {
    // Obtener parámetros de paginación y búsqueda
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(100, intval($_GET['pageSize']))) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $offset = ($page - 1) * $pageSize;

    // Construir condición WHERE base
    $whereConditions = "WHERE (Codigo_barra IS NULL OR Codigo_barra = '' OR LEN(RTRIM(LTRIM(Codigo_barra))) = 0)";
    $params = array();

    // Agregar búsqueda si existe
    if (!empty($search)) {
        $whereConditions .= " AND (nombre LIKE ? OR id LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Consulta para contar el total de artículos
    $sqlCount = "SELECT COUNT(*) as total
                 FROM [dbo].[Arti_codigos]
                 $whereConditions";

    $stmtCount = sqlsrv_query($conn, $sqlCount, $params);

    if ($stmtCount === false) {
        throw new Exception("Error al contar artículos: " . print_r(sqlsrv_errors(), true));
    }

    $totalRow = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalItems = $totalRow['total'];
    sqlsrv_free_stmt($stmtCount);

    // Consulta para obtener artículos con paginación
    $sql = "SELECT id, nombre, Codigo_barra, Usuario
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

    $articulos = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $articulos[] = array(
            'id' => $row['id'],
            'Nombre' => $row['nombre'],
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
        'articulos' => $articulos,
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
    error_log("Error en obtener_articulos_sin_codigo.php: " . $e->getMessage());

    echo json_encode(array(
        'success' => false,
        'message' => 'Error al obtener los artículos',
        'error' => $e->getMessage()
    ));
}
?>
