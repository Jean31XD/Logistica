<?php
/**
 * Verificar Cambios en Códigos de Barras
 * Retorna un hash de la tabla para detectar cambios
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!isset($_SESSION['usuario']) || !tieneModulo('codigos_referencia', $conn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Sin permisos']));
}

try {
    // Obtener un hash de los códigos de barras actuales
    // Usamos CHECKSUM_AGG para crear un hash de todos los registros
    $sql = "SELECT
                COUNT(*) as total,
                CHECKSUM_AGG(CHECKSUM(*)) as checksum_tabla,
                MAX(CAST(id AS VARCHAR(50)) + ISNULL(Codigo_barra, '') + ISNULL(Usuario, '')) as ultimo_cambio
            FROM [dbo].[Arti_codigos]";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }

    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    // Crear un hash único basado en el estado actual de la tabla
    $hashActual = md5(
        $result['total'] . '|' .
        $result['checksum_tabla'] . '|' .
        $result['ultimo_cambio']
    );

    echo json_encode(array(
        'success' => true,
        'hash' => $hashActual,
        'total' => $result['total'],
        'timestamp' => time()
    ));

} catch (Exception $e) {
    error_log("Error en verificar_cambios_codigos.php: " . $e->getMessage());

    echo json_encode(array(
        'success' => false,
        'message' => 'Error al verificar cambios',
        'error' => $e->getMessage()
    ));
}
?>
