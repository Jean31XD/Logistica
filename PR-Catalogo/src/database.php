<?php
// src/database.php

/**
 * Establece y devuelve una conexión con la base de datos SQL Server.
 *
 * @return resource|false El recurso de conexión si es exitoso, o false si falla.
 */
function connect_to_database() {
    $db_server = "sdb-apptransportistas-maco.privatelink.database.windows.net";
    $db_info = [
        "Database" => "db-apptransportistas-maco",
        "UID" => "ServiceAppTrans",
        "PWD" => "⁠nZ(#n41LJm)iLmJP",
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8"
    ];

    $db_conn = sqlsrv_connect($db_server, $db_info);

    if (!$db_conn) {
        // En una aplicación real, aquí registrarías el error en lugar de mostrarlo
        error_log("Error de conexión a la base de datos: " . print_r(sqlsrv_errors(), true));
        return false;
    }

    return $db_conn;
}


// --- AQUÍ IRÍAN TUS OTRAS FUNCIONES DE BASE DE DATOS ---
// Por ejemplo: fetch_product_data, get_distinct_values, get_inventory_stats, etc.
// Asegúrate de que esas funciones estén definidas aquí.


/**
 * Renderiza solo las tarjetas de producto para el carrusel.
 *
 * @param array $product_data Los datos de los productos.
 * @return string El HTML de las tarjetas de producto.
 */
function render_product_cards($product_data) {
    $html = '';
    $products = $product_data['products'] ?? [];

    if (empty($products)) {
        return '<div class="message-box"><h3>No se encontraron productos</h3><p>Intenta cambiar los filtros o el término de búsqueda.</p></div>';
    }

    foreach ($products as $p) {
        $image_path = htmlspecialchars($p['image_path'] ?? 'img/placeholder.png');
        $full_image_path = htmlspecialchars($p['full_image_path'] ?? $image_path);

        $html .= sprintf(
            '<div class="product-card" data-image="%s" data-full-image="%s">
                <div class="product-info">
                    <h3>%s</h3>
                    <p class="sku">SKU: %s</p>
                    <p>Categoría: %s</p>
                    <p>Marca: %s</p>
                </div>
            </div>',
            $image_path,
            $full_image_path,
            htmlspecialchars($p['ProductName']),
            htmlspecialchars($p['itemid']),
            htmlspecialchars($p['Categoria']),
            htmlspecialchars($p['Marca'])
        );
    }

    return $html;
}