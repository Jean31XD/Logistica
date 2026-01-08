<?php
/**
 * Eliminar Código de Barras
 * Elimina (limpia) el código de barras de un artículo específico
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!isset($_SESSION['usuario']) || !tieneModulo('codigos_referencia', $conn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Sin permisos']));
}

try {
    // Verificar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }

    // Validar parámetros
    if (!isset($_POST['id'])) {
        throw new Exception('Parámetros incompletos');
    }

    $id = trim($_POST['id']);

    // Validar que el ID sea válido (ahora es VARCHAR)
    if (empty($id)) {
        throw new Exception('ID inválido');
    }

    // Verificar que el artículo existe y tiene código asignado
    $sqlCheck = "SELECT id, Nombre, Codigo_barra
                 FROM [dbo].[Arti_codigos]
                 WHERE id = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($id));

    if ($stmtCheck === false) {
        throw new Exception('Error al verificar el artículo: ' . print_r(sqlsrv_errors(), true));
    }

    $articulo = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCheck);

    if (!$articulo) {
        throw new Exception('Artículo no encontrado');
    }

    // Verificar que tiene código asignado
    $tieneCodigo = !empty($articulo['Codigo_barra']) && trim($articulo['Codigo_barra']) !== '';

    if (!$tieneCodigo) {
        throw new Exception('El artículo no tiene código asignado');
    }

    $codigoAnterior = $articulo['Codigo_barra'];

    // Eliminar el código de barras (establecer a NULL)
    $sqlDelete = "UPDATE [dbo].[Arti_codigos]
                  SET Codigo_barra = NULL, Usuario = NULL
                  WHERE id = ?";

    $stmtDelete = sqlsrv_query($conn, $sqlDelete, array($id));

    if ($stmtDelete === false) {
        throw new Exception('Error al eliminar el código: ' . print_r(sqlsrv_errors(), true));
    }

    $rowsAffected = sqlsrv_rows_affected($stmtDelete);
    sqlsrv_free_stmt($stmtDelete);

    if ($rowsAffected === 0) {
        throw new Exception('No se realizaron cambios');
    }

    // Log de la acción
    $usuario = $_SESSION['usuario'];
    error_log("Código eliminado - Usuario: {$usuario}, ID: {$id}, Artículo: {$articulo['Nombre']}, Código anterior: {$codigoAnterior}");

    echo json_encode(array(
        'success' => true,
        'message' => 'Código eliminado correctamente',
        'data' => array(
            'id' => $id,
            'nombre' => $articulo['Nombre'],
            'codigo_anterior' => $codigoAnterior
        )
    ));

} catch (Exception $e) {
    error_log("Error en eliminar_codigo.php: " . $e->getMessage());

    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}
?>
