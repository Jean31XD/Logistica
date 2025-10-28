<?php
// src/azure.php

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;

// --- Configuración Global ---
$azure_connection_string = 'DefaultEndpointsProtocol=https;AccountName=catalogodeimagenes;AccountKey=TirDXCntIXgHJhbY8YPxD/hw+6Vh5jVOZlQYlPR79IUWLSdB98Tx27t0LhunBYvYQ8pzNdkIejcv+AStSpNYsw==;EndpointSuffix=core.windows.net';
$azure_container_name = "imagenes-productos";
$azure_account_name = 'catalogodeimagenes';

// --- Funciones de Datos ---

function get_blob_item_ids() {
    global $azure_connection_string, $azure_container_name;
    return get_cached_data('blob_item_ids_list', 3600, function() use ($azure_connection_string, $azure_container_name) {
        $item_ids = [];
        try {
            $blob_client = BlobRestProxy::createBlobService($azure_connection_string);
            $marker = null;
            do {
                $options = new ListBlobsOptions();
                $options->setMaxResults(5000);
                if ($marker) $options->setMarker($marker);
                
                $blob_list = $blob_client->listBlobs($azure_container_name, $options);
                foreach ($blob_list->getBlobs() as $blob) {
                    $filename = $blob->getName();
                    $itemId = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
                    $item_ids[] = strtoupper(trim($itemId));
                }
                $marker = $blob_list->getNextMarker();
            } while ($marker);
        } catch (ServiceException $e) {
            error_log("Azure Blob Service Exception: " . $e->getMessage());
            return [];
        }
        return $item_ids;
    });
}

function get_distinct_values($db_conn, $field) {
    return get_cached_data($field . '_list', 3600, function() use ($db_conn, $field) {
        $values = [];
        $sql = "SELECT DISTINCT $field FROM dbo.inventtable WHERE $field IS NOT NULL AND $field != '' ORDER BY $field ASC";
        $stmt = sqlsrv_query($db_conn, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $values[] = $row[$field];
            }
        }
        return $values;
    });
}

function get_inventory_stats($db_conn) {
    return get_cached_data('inventory_stats', 3600, function() use ($db_conn) {
        $sql_total = "SELECT COUNT(itemid) AS total FROM dbo.inventtable";
        $stmt_total = sqlsrv_query($db_conn, $sql_total);
        $total_db_items = ($stmt_total && $row = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC)) ? $row['total'] : 0;
        $blob_ids = get_blob_item_ids();
        $total_blobs = count($blob_ids);
        return [
            'total_db' => $total_db_items,
            'total_images' => $total_blobs,
            'missing_images' => max(0, $total_db_items - $total_blobs)
        ];
    });
}

// === FUNCIÓN fetch_product_data CON LA LÓGICA FINAL Y DEFINITIVA ===
function fetch_product_data($db_conn) {
    global $azure_account_name, $azure_container_name;
    
    // Recolección de filtros
    $search_query = trim($_GET['q'] ?? '');
    $selected_categoria = $_GET['categoria'] ?? '';
    $selected_marca = $_GET['marca'] ?? '';
    $tiene_imagen = $_GET['tiene_imagen'] ?? '';
    $current_page = max(1, intval($_GET['page'] ?? 1));
    $items_per_page = 50;

    $params = [];
    $where_clauses = [];
    $join_clauses = [];

    // Lógica definitiva para el filtro de imagen
    if ($tiene_imagen === 'con') {
        $ids_con_imagen = get_blob_item_ids();

        if (!empty($ids_con_imagen)) {
            // 1. Crear una tabla temporal
            $temp_table_sql = "CREATE TABLE #ImageIDs (itemid NVARCHAR(50) PRIMARY KEY)";
            sqlsrv_query($db_conn, $temp_table_sql);

            // 2. Insertar los IDs de las imágenes en la tabla temporal
            // (Se agrupan en lotes para evitar límites de parámetros)
            $chunks = array_chunk($ids_con_imagen, 1000); // Lotes de 1000
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
                $insert_sql = "INSERT INTO #ImageIDs (itemid) VALUES $placeholders";
                sqlsrv_query($db_conn, $insert_sql, $chunk);
            }

            // 3. Unir la tabla principal con la tabla temporal
            $join_clauses[] = "INNER JOIN #ImageIDs i ON UPPER(TRIM(p.itemid)) = i.itemid";
        } else {
            // Si no hay imágenes, forzar a que no haya resultados.
            $where_clauses[] = "1 = 0"; 
        }
    }

    // Aplicar filtros estándar
    if ($search_query) {
        $where_clauses[] = "(p.ProductName LIKE ? OR p.itemid LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    if ($selected_categoria) {
        $where_clauses[] = "p.Categoria = ?";
        $params[] = $selected_categoria;
    }
    if ($selected_marca) {
        $where_clauses[] = "p.Marca = ?";
        $params[] = $selected_marca;
    }
    
    // Construcción de consultas
    $join_sql = implode(' ', $join_clauses);
    $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

    $count_sql = "SELECT COUNT(*) AS total FROM dbo.inventtable p $join_sql $where_sql";
    $count_stmt = sqlsrv_query($db_conn, $count_sql, $params);
    $total_filtered_items = 0;
    if ($count_stmt && $row = sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC)) {
        $total_filtered_items = $row['total'];
    }

    $offset = ($current_page - 1) * $items_per_page;
    $fetch_sql = "SELECT p.itemid, p.ProductName, p.Categoria, p.Marca FROM dbo.inventtable p $join_sql $where_sql ORDER BY p.itemid ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $fetch_params = array_merge($params, [$offset, $items_per_page]);
    
    $products = [];
    $fetch_stmt = sqlsrv_query($db_conn, $fetch_sql, $fetch_params);
    if ($fetch_stmt) {
        while ($row = sqlsrv_fetch_array($fetch_stmt, SQLSRV_FETCH_ASSOC)) {
            $row['image_url'] = "https://{$azure_account_name}.blob.core.windows.net/{$azure_container_name}/" . rawurlencode(trim($row['itemid'])) . ".jpg";
            $products[] = $row;
        }
    }

    // Si se usó la tabla temporal, eliminarla
    if ($tiene_imagen === 'con') {
        sqlsrv_query($db_conn, "DROP TABLE #ImageIDs");
    }
    
    return compact('products', 'total_filtered_items', 'current_page', 'items_per_page');
}

function render_product_area($data) {
    extract($data);
    ob_start();

    echo '<div class="product-grid" id="product-grid">';
    if (!empty($products)) {
        foreach ($products as $product) {
            $image_url = htmlspecialchars($product['image_url']);
            $product_name = htmlspecialchars($product['ProductName']);
            
            echo '<div class="product-card">
                <div class="image-container" data-full-src="' . $image_url . '">
                    <img src="' . $image_url . '" loading="lazy" 
onerror="this.onerror=null; this.src=\'https://via.placeholder.com/280x250.png?text=Sin+Imagen\';"> </div>
                <div class="product-info">
                    <h3>' . $product_name . '</h3>
                    <p><strong>Marca:</strong> ' . htmlspecialchars($product['Marca']) . '</p>
                    <p><strong>Categoría:</strong> ' . htmlspecialchars($product['Categoria']) . '</p>
                    <p class="sku">SKU: ' . htmlspecialchars($product['itemid']) . '</p>
                </div>
            </div>';
        }
    } else {
        echo "<div class='message-box'><h3>No se encontraron productos</h3><p>Intenta cambiar los filtros o la búsqueda.</p></div>";
    }
    echo '</div>';

    echo '<nav class="pagination" id="pagination">';
    $total_pages = ceil($total_filtered_items / $items_per_page);
    $current_params = $_GET;
    unset($current_params['action'], $current_params['page']);

    if ($current_page > 1) {
        $prev_params = $current_params;
        $prev_params['page'] = $current_page - 1;
        echo '<a href="?' . http_build_query($prev_params) . '">&laquo; Anterior</a>';
    } else {
        echo '<a href="#" class="disabled">&laquo; Anterior</a>';
    }

    echo "<span>Página {$current_page} de {$total_pages}</span>";

    if ($current_page < $total_pages) {
        $next_params = $current_params;
        $next_params['page'] = $current_page + 1;
        echo '<a href="?' . http_build_query($next_params) . '">Siguiente &raquo;</a>';
    } else {
        echo '<a href="#" class="disabled">Siguiente &raquo;</a>';
    }
    echo '</nav>';
    
    return ob_get_clean();
}

function get_top_10_products($db_conn) {
    return get_cached_data('top_10_products_list', 14400, function() use ($db_conn) {
        $top_products = [];
        $sql = "
            SELECT TOP (10)
                tp.itemid,
                inv.ProductName
            FROM
                dbo.top_productos AS tp
            INNER JOIN
                dbo.inventtable AS inv ON tp.itemid = inv.itemid
            ORDER BY
                tp.[TOP] ASC;
        ";
        
        $stmt = sqlsrv_query($db_conn, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $top_products[] = $row;
            }
        }
        return $top_products;
    });
}