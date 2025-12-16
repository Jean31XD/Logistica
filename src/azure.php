<?php
// src/azure.php
header('Content-Type: text/html; charset=utf-8');

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;

require_once __DIR__ . '/cache.php';

// --- Configuración Global ---
$azure_connection_string = 'DefaultEndpointsProtocol=https;AccountName=catalogodeimagenes;AccountKey=TirDXCntIXgHJhbY8YPxD/hw+6Vh5jVOZlQYlPR79IUWLSdB98Tx27t0LhunBYvYQ8pzNdkIejcv+AStSpNYsw==;EndpointSuffix=core.windows.net';
$azure_container_name = "imagenes-productos";
$azure_account_name = 'catalogodeimagenes';

// --- Funciones de Datos ---

function get_blob_item_ids() {
    global $azure_connection_string, $azure_container_name;
    // IMPORTANTE: Borra los archivos de /cache/ para forzar la actualización de esta lista.
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

                    // --- INICIO DE LA MODIFICACIÓN (LÓGICA DE LIMPIEZA ROBUSTA) ---
                    // 1. Usar pathinfo() para quitar CUALQUIER extensión (.jpg, .JPG, .jpeg...)
                    $itemId = pathinfo($filename, PATHINFO_FILENAME);

                    // 2. Definir lista de caracteres a limpiar
                    $chars_to_remove = ['-', '.', ' ', '(', ')', "'", '/', '\\'];
                    $cleanedItemId = str_replace($chars_to_remove, '', $itemId);

                    // 3. Guardar el ID limpio y en mayúsculas
                    $item_ids[] = strtoupper(trim($cleanedItemId));
                    // --- FIN DE LA MODIFICACIÓN ---
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
    // --- INICIO DE MODIFICACIÓN: Lista de columnas permitidas ---
    // Medida de seguridad básica para evitar inyección en el nombre del campo.
    $allowed_fields = ['Categoria', 'Marca', 'tipo_marca'];
    if (!in_array($field, $allowed_fields)) {
        error_log("Intento de obtener valores distintos de un campo no permitido: $field");
        return [];
    }
    // --- FIN DE MODIFICACIÓN ---

    return get_cached_data($field . '_list_v2', 3600, function() use ($db_conn, $field) { // v2 para invalidar caché
        $values = [];

        // ==== INICIO DE CORRECCIÓN: Usamos UPPER(TRIM()) ====
        // Usamos UPPER(TRIM()) para limpiar y estandarizar mayúsculas
        $sql = "SELECT DISTINCT UPPER(TRIM($field)) AS $field
                FROM dbo.inventtable
                WHERE $field IS NOT NULL AND TRIM($field) != ''
                ORDER BY $field ASC";
        // ==== FIN DE CORRECCIÓN ====

        $stmt = sqlsrv_query($db_conn, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $values[] = $row[$field];
            }
        }
        return $values;
    });
}

// --- INICIO DE NUEVAS FUNCIONES DE MARCAS ---

/**
 * Obtiene solo las marcas que están marcadas como "Marcas propia".
 */
function get_marcas_propias($db_conn) {
    return get_cached_data('marcas_propias_list_v2', 3600, function() use ($db_conn) { // v2 para invalidar caché
        $values = [];

        // ==== INICIO DE CORRECCIÓN: Usar UPPER(TRIM()) para consistencia ====
        $sql = "SELECT DISTINCT UPPER(TRIM(Marca)) AS Marca
                FROM dbo.inventtable
                WHERE UPPER(TRIM(tipo_marca)) = 'MARCAS PROPIA'
                  AND Marca IS NOT NULL AND TRIM(Marca) != ''
                ORDER BY Marca ASC";
        // ==== FIN DE CORRECCIÓN ====

        $stmt = sqlsrv_query($db_conn, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $values[] = $row['Marca'];
            }
        }
        return $values;
    });
}

/**
 * Obtiene solo las marcas que NO son "Marcas propia".
 */
function get_marcas_generales($db_conn) {
    return get_cached_data('marcas_generales_list_v2', 3600, function() use ($db_conn) { // v2 para invalidar caché
        $values = [];

        // ==== INICIO DE CORRECCIÓN: Usar UPPER(TRIM()) para consistencia ====
        $sql = "SELECT DISTINCT UPPER(TRIM(Marca)) AS Marca
                FROM dbo.inventtable
                WHERE (UPPER(TRIM(tipo_marca)) != 'MARCAS PROPIA' OR tipo_marca IS NULL)
                  AND Marca IS NOT NULL AND TRIM(Marca) != ''
                ORDER BY Marca ASC";
        // ==== FIN DE CORRECCIÓN ====

        $stmt = sqlsrv_query($db_conn, $sql);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $values[] = $row['Marca'];
            }
        }
        return $values;
    });
}

// --- FIN DE NUEVAS FUNCIONES DE MARCAS ---


// === FUNCIÓN fetch_product_data CON LA LÓGICA FINAL Y DEFINITIVA ===

// --- INICIO DE MODIFICACIÓN: Aceptar $filters como argumento ---
function fetch_product_data($db_conn, $filters = []) {
// --- FIN DE MODIFICACIÓN ---
    global $azure_account_name, $azure_container_name;

    // --- INICIO DE MODIFICACIÓN: Leer TODOS los filtros del array $filters ---
    // Recolección de filtros
    $search_query = trim($filters['q'] ?? '');
    $selected_categoria = $filters['categoria'] ?? '';
    $selected_marca_general = $filters['marca_general'] ?? '';
    $selected_marca_propia = $filters['marca_propia'] ?? '';
    $current_page = max(1, intval($filters['page'] ?? 1));

    // ----- ¡¡AQUÍ ESTÁ LA LÍNEA CLAVE!! -----
    $solo_marcas_propias = $filters['solo_marcas_propias'] ?? false;
    // --- FIN DE MODIFICACIÓN ---

    $items_per_page = 50;

    $params = [];
    $where_clauses = [];
    $join_clauses = [];

    // --- INICIO DE LA LÓGICA DE ORDENACIÓN POR IMAGEN ---

    // 1. Obtener IDs de imagen de Azure (limpios y en mayúsculas)
    $ids_con_imagen = get_blob_item_ids();

    if (!empty($ids_con_imagen)) {
        // 2. Crear tabla temporal
        $temp_table_sql = "CREATE TABLE #ImageIDs (itemid NVARCHAR(50) PRIMARY KEY)";
        @sqlsrv_query($db_conn, $temp_table_sql); // Usamos @ para suprimir errores

        // 3. Insertar IDs en tabla temporal
        $chunks = array_chunk($ids_con_imagen, 1000); // Lotes de 1000
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
            $insert_sql = "INSERT INTO #ImageIDs (itemid) VALUES $placeholders";
            sqlsrv_query($db_conn, $insert_sql, $chunk);
        }

        // 4. Preparar la lógica de limpieza de SQL (DEBE COINCIDIR CON LA DE PHP)
        $cleaned_itemid_sql = "UPPER(TRIM(p.itemid))";
        $chars_to_remove_sql = ['-', '.', ' ', '(', ')', "'", '/', '\\'];
        foreach ($chars_to_remove_sql as $char) {
            $sql_char = ($char === "'") ? "''" : $char; // Escapar el apóstrofo para SQL
            $cleaned_itemid_sql = "REPLACE($cleaned_itemid_sql, '$sql_char', '')";
        }

        // 5. Añadir un LEFT JOIN
        $join_clauses[] = "LEFT JOIN #ImageIDs i ON $cleaned_itemid_sql = i.itemid";

        // 6. Definir el nuevo orden (Pone los que NO tienen foto de último)
        $order_by_sql = "ORDER BY (CASE WHEN i.itemid IS NOT NULL THEN 1 ELSE 0 END) DESC, p.itemid ASC";

    } else {
        // Si no hay imágenes, simplemente ordenar por itemid
        $order_by_sql = "ORDER BY p.itemid ASC";
    }
    // --- FIN DE LA LÓGICA DE ORDENACIÓN POR IMAGEN ---


    // --- INICIO DE MODIFICACIÓN: Lógica de Búsqueda LIKE Mejorada (Multi-palabra) ---
    if ($search_query) {

        // Búsqueda simple por itemid (suele ser exacta)
        $itemid_search_sql = "p.itemid LIKE ?";
        $params_itemid = ["%$search_query%"];

        // Dividir el término de búsqueda en palabras
        $search_words = preg_split('/\s+/', $search_query, -1, PREG_SPLIT_NO_EMPTY);

        $product_search_clauses = [];
        $params_product = [];

        if (!empty($search_words)) {
            foreach ($search_words as $word) {
                // Añadir un LIKE por CADA palabra para ProductName
                $product_search_clauses[] = "p.ProductName LIKE ?";
                $params_product[] = "%$word%";
            }
        }

        // Unir las búsquedas de palabras con AND
        $product_search_sql = implode(' AND ', $product_search_clauses);

        // La cláusula final es (Búsqueda por itemid) O (Búsqueda por TODAS las palabras en ProductName)
        if (!empty($product_search_sql)) {
            $where_clauses[] = "($itemid_search_sql OR ($product_search_sql))";
            // Añadir los parámetros en el orden correcto
            // (Ojo: array_merge se hace DESPUÉS de añadir otros $params)
            $params = array_merge($params, $params_itemid, $params_product);
        } else {
            // Si la búsqueda estaba vacía o solo espacios, solo buscar por itemid
            $where_clauses[] = $itemid_search_sql;
            $params = array_merge($params, $params_itemid);
        }
    }
    // --- FIN DE MODIFICACIÓN: Lógica de Búsqueda LIKE Mejorada ---


    // ==== INICIO DE CORRECCIÓN: Usar UPPER(TRIM()) al filtrar ====
    if ($selected_categoria) {
        $where_clauses[] = "UPPER(TRIM(p.Categoria)) = ?";
        $params[] = $selected_categoria; // El valor del select ya vendrá LIMPIO Y EN MAYÚSCULAS
    }

    // --- INICIO DE MODIFICACIÓN DE FILTRO DE MARCA (CON LÓGICA DE SWITCH) ---

    if ($solo_marcas_propias) {
        // MODO SWITCH ENCENDIDO:
        // 1. Forzar que sea marca propia
        $where_clauses[] = "UPPER(TRIM(p.tipo_marca)) = 'MARCAS PROPIA'";

        // 2. Si ADEMÁS seleccionó una marca propia específica del dropdown, aplicarla
        if ($selected_marca_propia) {
            $where_clauses[] = "UPPER(TRIM(p.Marca)) = ?";
            $params[] = $selected_marca_propia; // El valor ya viene limpio/mayúsculas
        }

    } else {
        // MODO SWITCH APAGADO: Lógica de exclusión normal de los dropdowns

        if ($selected_marca_general) {
            $where_clauses[] = "UPPER(TRIM(p.Marca)) = ?";
            $params[] = $selected_marca_general;
        }

        if ($selected_marca_propia) {
            $where_clauses[] = "UPPER(TRIM(p.Marca)) = ?";
            $params[] = $selected_marca_propia;
        }
    }
    // --- FIN DE MODIFICACIÓN DE FILTRO DE MARCA ---

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

    $fetch_sql = "SELECT p.itemid, p.ProductName, UPPER(TRIM(p.Categoria)) AS Categoria,
                         UPPER(TRIM(p.Marca)) AS Marca, UPPER(TRIM(p.tipo_marca)) AS tipo_marca
                   FROM dbo.inventtable p $join_sql $where_sql
                   $order_by_sql
                   OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $fetch_params = array_merge($params, [$offset, $items_per_page]);

    $products = [];
    $fetch_stmt = sqlsrv_query($db_conn, $fetch_sql, $fetch_params);
    if ($fetch_stmt) {
        while ($row = sqlsrv_fetch_array($fetch_stmt, SQLSRV_FETCH_ASSOC)) {
            // Se asume que la extensión .jpg es la estándar para todas las imágenes
            $row['image_url'] = "https://{$azure_account_name}.blob.core.windows.net/{$azure_container_name}/" . rawurlencode(trim($row['itemid'])) . ".jpg";
            $products[] = $row;
        }
    }

    // 8. Limpiar la tabla temporal si fue creada
    if (!empty($ids_con_imagen)) {
        @sqlsrv_query($db_conn, "DROP TABLE #ImageIDs");
    }

    // --- ==== INICIO DE NUEVA LÓGICA: MARCAR RECOMENDADOS Y OFERTAS ==== ---

    // 1. Obtener la lista de marcas propias (usará la caché, es rápido)
    $marcas_propias = get_marcas_propias($db_conn);

    // 2. Encontrar las llaves de los productos de marca propia en esta página
    $private_product_keys = [];
    foreach ($products as $key => $product) {
        // Inicializar todos los productos sin recomendación
        $products[$key]['is_recommended'] = 0;

        // --- ==== INICIO MODIFICACIÓN: Lógica de Oferta (CON 2 y 3 ASTERISCOS) ==== ---
        // Inicializar la bandera de oferta
        $products[$key]['is_on_offer'] = 0;

        // Comprobar si el nombre del producto termina en *** O **
        $trimmed_name = trim($product['ProductName']);
        if (substr($trimmed_name, -3) === '***' || substr($trimmed_name, -2) === '**') {
            $products[$key]['is_on_offer'] = 1;
        }
        // --- ==== FIN MODIFICACIÓN: Lógica de Oferta ==== ---

        // Si la marca del producto está en nuestra lista de marcas propias...
        // (Usamos $product['Marca'] que ya viene en mayúsculas)
        if (in_array($product['Marca'], $marcas_propias)) {
            $private_product_keys[] = $key; // Guardamos su índice
        }
    }

    // 3. Barajar las llaves de los productos de marca propia
    shuffle($private_product_keys);

    // --- ==== INICIO DE MODIFICACIÓN: Coger solo UNO ==== ---
    // 4. Coger solo UNA llave aleatoria (la primera después de barajar)
    $recommended_keys = array_slice($private_product_keys, 0, 1);
    // --- ==== FIN DE MODIFICACIÓN ==== ---

    // 5. Marcar ese ÚNICO producto como recomendado
    foreach ($recommended_keys as $key) { // (Este bucle solo se ejecutará una vez, si existe)
        $products[$key]['is_recommended'] = 1;
    }

    // --- ==== FIN DE NUEVA LÓGICA: MARCAR RECOMENDADOS Y OFERTAS ==== ---

    return compact('products', 'total_filtered_items', 'current_page', 'items_per_page');
}

function render_product_area($data) {
    extract($data);
    ob_start();

    // --- MODIFICACIÓN: class="product-grid" (tenía un error tipográfico) ---
    echo '<div class="product-grid" id="product-grid">';
    if (!empty($products)) {
        foreach ($products as $product) {
            $image_url = htmlspecialchars($product['image_url']);
            $product_name = htmlspecialchars($product['ProductName']);

            // --- ==== INICIO DE MODIFICACIÓN: LÓGICA DE RECOMENDADOS Y OFERTAS ==== ---

            // 1. Comprobar ambas banderas
            $is_recommended = $product['is_recommended'] ?? 0;
            $is_on_offer = $product['is_on_offer'] ?? 0;

            // 2. Construir la lista de clases dinámicamente
            $card_classes = ['product-card'];
            if ($is_recommended) {
                $card_classes[] = 'recommended';
            }
            if ($is_on_offer) {
                $card_classes[] = 'on-offer';
            }
            $card_class = implode(' ', $card_classes);

            // 3. Empezar el div de la tarjeta
            echo '<div class="' . $card_class . '">';

            // 4. Añadir el listón ROJO de Recomendado (si aplica)
            if ($is_recommended) {
                echo '<div class="recommend-badge"><span class="icon"></span> Recomendado</div>';
            }

            // 5. Añadir el listón VERDE de Oferta (si aplica)
            // (Lo ponemos en una esquina diferente para que no se solapen)
            if ($is_on_offer) {
                echo '<div class="offer-badge"><span class="icon"></span> Oferta</div>';
            }

            // --- ==== FIN DE MODIFICACIÓN ==== ---

            echo '
                <div class="image-container" data-full-src="' . $image_url . '">
                    <img src="' . $image_url . '" loading="lazy"
onerror="this.onerror=null; this.src=\'https://via.placeholder.com/280x250.png?text=Sin+Imagen\';"> </div>
                <div class="product-info">
                    <h3>' . $product_name . '</h3>
                    <p><strong>Marca:</strong> ' . htmlspecialchars($product['Marca']) . '</p>
                    <p><strong>Categoría:</strong> ' . htmlspecialchars($product['Categoria']) . '</p>
                    <p class="sku">SKU: ' . htmlspecialchars($product['itemid']) . '</p>
                </div>
            </div>'; // El </div> de .product-card
        }
    } else {
        echo "<div class='message-box'><h3>No se encontraron productos</h3><p>Intenta cambiar los filtros o la búsqueda.</p></div>";
    }
    echo '</div>';

    echo '<nav class="pagination" id="pagination">';
    $total_pages = ceil($total_filtered_items / $items_per_page);

    // Esta parte está bien que use $_GET, porque necesita construir
    // los enlaces de la URL actual para la navegación.
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

// =========================================================================
// NUEVAS FUNCIONES PARA GESTIÓN DE IMÁGENES
// =========================================================================

/**
 * Sube una imagen al Azure Blob Storage
 *
 * @param string $file_path Ruta al archivo local a subir
 * @param string $blob_name Nombre del blob en Azure (ej: "SKU123.jpg")
 * @param string $content_type Tipo de contenido (ej: "image/jpeg")
 * @return array ['success' => bool, 'message' => string, 'url' => string|null]
 */
function upload_image_to_azure($file_path, $blob_name, $content_type = 'image/jpeg') {
    global $azure_connection_string, $azure_container_name, $azure_account_name;

    try {
        // Verificar que el archivo existe
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'message' => 'El archivo no existe',
                'url' => null
            ];
        }

        // Crear cliente de blob
        $blob_client = BlobRestProxy::createBlobService($azure_connection_string);

        // Leer el contenido del archivo
        $content = fopen($file_path, "r");

        // Configurar opciones del blob
        $options = new CreateBlockBlobOptions();
        $options->setContentType($content_type);

        // Subir el blob
        $blob_client->createBlockBlob(
            $azure_container_name,
            $blob_name,
            $content,
            $options
        );

        // Cerrar el archivo
        fclose($content);

        // Limpiar caché de IDs de blobs
        clear_cache('blob_item_ids_list');

        // Construir URL de la imagen
        $image_url = "https://{$azure_account_name}.blob.core.windows.net/{$azure_container_name}/" . rawurlencode($blob_name);

        return [
            'success' => true,
            'message' => 'Imagen subida correctamente',
            'url' => $image_url
        ];

    } catch (ServiceException $e) {
        error_log("Error al subir imagen a Azure: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al subir la imagen: ' . $e->getMessage(),
            'url' => null
        ];
    }
}

/**
 * Elimina una imagen del Azure Blob Storage
 *
 * @param string $blob_name Nombre del blob a eliminar (ej: "SKU123.jpg")
 * @return array ['success' => bool, 'message' => string]
 */
function delete_image_from_azure($blob_name) {
    global $azure_connection_string, $azure_container_name;

    try {
        // Crear cliente de blob
        $blob_client = BlobRestProxy::createBlobService($azure_connection_string);

        // Eliminar el blob
        $blob_client->deleteBlob($azure_container_name, $blob_name);

        // Limpiar caché de IDs de blobs
        clear_cache('blob_item_ids_list');

        return [
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ];

    } catch (ServiceException $e) {
        error_log("Error al eliminar imagen de Azure: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al eliminar la imagen: ' . $e->getMessage()
        ];
    }
}

/**
 * Lista todas las imágenes del contenedor de Azure
 *
 * @param int $max_results Máximo número de resultados (0 = todos)
 * @return array Lista de blobs con información
 */
function list_azure_images($max_results = 0) {
    global $azure_connection_string, $azure_container_name, $azure_account_name;

    try {
        $blob_client = BlobRestProxy::createBlobService($azure_connection_string);
        $images = [];
        $marker = null;

        do {
            $options = new ListBlobsOptions();
            if ($max_results > 0) {
                $options->setMaxResults($max_results);
            }
            if ($marker) {
                $options->setMarker($marker);
            }

            $blob_list = $blob_client->listBlobs($azure_container_name, $options);

            foreach ($blob_list->getBlobs() as $blob) {
                $images[] = [
                    'name' => $blob->getName(),
                    'url' => "https://{$azure_account_name}.blob.core.windows.net/{$azure_container_name}/" . rawurlencode($blob->getName()),
                    'size' => $blob->getProperties()->getContentLength(),
                    'last_modified' => $blob->getProperties()->getLastModified()->format('Y-m-d H:i:s'),
                    'content_type' => $blob->getProperties()->getContentType()
                ];

                if ($max_results > 0 && count($images) >= $max_results) {
                    break 2;
                }
            }

            $marker = $blob_list->getNextMarker();
        } while ($marker && ($max_results == 0 || count($images) < $max_results));

        return $images;

    } catch (ServiceException $e) {
        error_log("Error al listar imágenes de Azure: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica si existe una imagen en Azure
 *
 * @param string $blob_name Nombre del blob a verificar
 * @return bool
 */
function image_exists_in_azure($blob_name) {
    global $azure_connection_string, $azure_container_name;

    try {
        $blob_client = BlobRestProxy::createBlobService($azure_connection_string);
        $blob_client->getBlobProperties($azure_container_name, $blob_name);
        return true;
    } catch (ServiceException $e) {
        return false;
    }
}
