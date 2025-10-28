<?php
// api/get_products.php

// $db_conn ya está disponible desde index.php

try {
    // 1. Obtener los datos de los productos
    if (!function_exists('fetch_product_data')) {
         throw new Exception('Error interno: La función fetch_product_data() no está definida.');
    }
    if (!function_exists('render_product_area')) {
         throw new Exception('Error interno: La función render_product_area() no está definida.');
    }

    $product_data = fetch_product_data($db_conn);

    // 2. Renderizar el HTML para la cuadrícula y paginación
    echo render_product_area($product_data);

    // --- ARREGLO 3: Cerrar la conexión en las llamadas de API ---
    if (isset($db_conn) && $db_conn) {
        sqlsrv_close($db_conn);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('Error en get_products.php: ' . $e->getMessage());
    echo '<div class="message-box"><h3>Error</h3><p>No se pudo procesar la solicitud de productos. Por favor, intente más tarde.</p></div>';
    
    // --- ARREGLO 3 (Alternativo): Asegurarse de cerrar también si hay error ---
    if (isset($db_conn) && $db_conn) {
        sqlsrv_close($db_conn);
    }
}