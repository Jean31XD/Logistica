<?php
// /api/get_stats.php

header('Content-Type: application/json');

try {
    // Preparamos la respuesta por defecto
    $stats_data = ['total_items' => 0, 'with_image' => 0, 'without_image' => 0];

    // Obtenemos ambos filtros de la petición
    $categoria = $_GET['categoria'] ?? '';
    $marca = $_GET['marca'] ?? '';

    // Solo procedemos si al menos un filtro está activo y tenemos conexión
    if ((!empty($categoria) || !empty($marca)) && isset($db_conn)) {

        // --- PASO 1: Construir la cláusula WHERE y los parámetros dinámicamente ---
        $where_conditions = [];
        $params = [];

        if (!empty($categoria)) {
            $where_conditions[] = "Categoria = ?";
            $params[] = $categoria;
        }
        if (!empty($marca)) {
            $where_conditions[] = "Marca = ?";
            $params[] = $marca;
        }
        
        $where_sql = "WHERE " . implode(' AND ', $where_conditions);

        // --- PASO 2: Obtener la lista de IDs de imágenes ---
        if (!function_exists('get_blob_item_ids')) {
            throw new Exception('Error interno: La función get_blob_item_ids() no está definida.');
        }
        $blob_ids_with_image = get_blob_item_ids();
        $image_id_set = array_flip($blob_ids_with_image);

        // --- PASO 3: Obtener TODOS los itemid que coincidan con los filtros ---
        $sql = "SELECT itemid FROM dbo.inventtable " . $where_sql;
        $stmt = sqlsrv_query($db_conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception('Error al ejecutar la consulta de estadísticas: ' . print_r(sqlsrv_errors(), true));
        }

        $total_items = 0;
        $items_with_image = 0;

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $total_items++;
            if (isset($image_id_set[$row['itemid']])) {
                $items_with_image++;
            }
        }
        
        $stats_data['total_items'] = $total_items;
        $stats_data['with_image'] = $items_with_image;
        $stats_data['without_image'] = $total_items - $items_with_image;
    }
    
    // Devolvemos el resultado final en formato JSON
    echo json_encode($stats_data);
    
    // --- ARREGLO 3: Cerrar la conexión en las llamadas de API ---
    if (isset($db_conn) && $db_conn) {
        sqlsrv_close($db_conn);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('Error en get_stats.php: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo procesar la solicitud de estadísticas.']);
    
    // --- ARREGLO 3 (Alternativo): Asegurarse de cerrar también si hay error ---
    if (isset($db_conn) && $db_conn) {
        sqlsrv_close($db_conn);
    }
}