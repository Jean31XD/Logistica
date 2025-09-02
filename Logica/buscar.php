<?php
// --- AÑADE ESTAS LÍNEAS JUSTO AL INICIO ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DEL CÓDIGO A AÑADIR ---

// El resto de tu código continúa aquí abajo
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '../conexionBD/conexion.php';

// Array para almacenar la respuesta final
$response = [
    'success' => true,
    'data' => [],
    'message' => ''
];

try {
    // 3. Obtener y limpiar el término de búsqueda de la URL (?q=...)
    // Se usa el operador de fusión de null para evitar errores si 'q' no existe
    $termino = trim($_GET["q"] ?? "");

    // 4. Ejecutar la búsqueda solo si el término tiene al menos 2 caracteres
    if (strlen($termino) >= 2) {
        // Prepara la consulta SQL para SQL Server
        // TOP 20 limita los resultados para mejorar el rendimiento
        $sql = "SELECT TOP 20 itemid, description, itembarcode, unitid, Inventario_Listo
                FROM listo_inventario
                WHERE itemid LIKE ? OR itembarcode LIKE ?";
        
        // Agrega los comodines (%) al término para buscar coincidencias parciales
        $parametroBusqueda = "%{$termino}%";
        $params = [$parametroBusqueda, $parametroBusqueda];

        // 5. Ejecutar la consulta preparada
        $stmt = sqlsrv_query($conn, $sql, $params);

        // 6. Verificar si la consulta falló
        if ($stmt === false) {
            // Si hay un error, lo registramos (en un sistema real, usarías un log)
            // y preparamos un mensaje de error genérico.
            throw new Exception('Error al ejecutar la consulta en la base de datos.');
        }

        // 7. Recorrer los resultados y guardarlos en el array de datos
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $response['data'][] = $row;
        }

        // Liberar los recursos de la consulta
        sqlsrv_free_stmt($stmt);
    }

} catch (Exception $e) {
    // 8. Manejo de errores
    // Si algo falla en el bloque 'try', se captura el error aquí
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    // En un entorno de producción, podrías querer registrar el error en un archivo:
    // error_log($e->getMessage());
}

// 9. Cerrar la conexión a la base de datos
if (isset($conn)) {
    sqlsrv_close($conn);
}

// 10. Imprimir la respuesta final en formato JSON
// JSON_UNESCAPED_UNICODE asegura que caracteres como 'ñ' y 'á' se muestren correctamente
echo json_encode($response['data']);

?>