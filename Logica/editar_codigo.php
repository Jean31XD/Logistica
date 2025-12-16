<?php
/**
 * Editar Código de Barras
 * Actualiza el código de barras de un artículo específico
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([0, 5, 12]); // Admin, Admin-limitado, Códigos de Referencia
require_once __DIR__ . '/../conexionBD/conexion.php';

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
    if (!isset($_POST['id']) || !isset($_POST['codigo'])) {
        throw new Exception('Parámetros incompletos');
    }

    $id = intval($_POST['id']);
    $codigo = trim($_POST['codigo']);

    // Validar que el ID sea válido
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Validar que el código no esté vacío
    if (empty($codigo)) {
        throw new Exception('El código de barras no puede estar vacío');
    }

    // Verificar que el artículo existe
    $sqlCheck = "SELECT id, Nombre FROM [dbo].[Arti_codigos] WHERE id = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($id));

    if ($stmtCheck === false) {
        throw new Exception('Error al verificar el artículo: ' . print_r(sqlsrv_errors(), true));
    }

    $articulo = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCheck);

    if (!$articulo) {
        throw new Exception('Artículo no encontrado');
    }

    // Verificar que el código no esté siendo usado por otro artículo
    $sqlDuplicate = "SELECT id, Nombre FROM [dbo].[Arti_codigos]
                     WHERE Codigo_barra = ? AND id != ?
                     AND Codigo_barra IS NOT NULL
                     AND Codigo_barra != ''
                     AND LEN(RTRIM(LTRIM(Codigo_barra))) > 0";
    $stmtDuplicate = sqlsrv_query($conn, $sqlDuplicate, array($codigo, $id));

    if ($stmtDuplicate === false) {
        throw new Exception('Error al verificar duplicados: ' . print_r(sqlsrv_errors(), true));
    }

    $duplicado = sqlsrv_fetch_array($stmtDuplicate, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtDuplicate);

    if ($duplicado) {
        throw new Exception('El código de barras ya está asignado a: ' . $duplicado['Nombre']);
    }

    // Actualizar el código de barras
    $sqlUpdate = "UPDATE [dbo].[Arti_codigos]
                  SET Codigo_barra = ?, Usuario = ?
                  WHERE id = ?";

    $usuario = $_SESSION['usuario'];
    $params = array($codigo, $usuario, $id);

    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $params);

    if ($stmtUpdate === false) {
        throw new Exception('Error al actualizar el código: ' . print_r(sqlsrv_errors(), true));
    }

    $rowsAffected = sqlsrv_rows_affected($stmtUpdate);
    sqlsrv_free_stmt($stmtUpdate);

    if ($rowsAffected === 0) {
        throw new Exception('No se realizaron cambios');
    }

    // Log de la acción
    error_log("Código editado - Usuario: {$usuario}, ID: {$id}, Artículo: {$articulo['Nombre']}, Código: {$codigo}");

    echo json_encode(array(
        'success' => true,
        'message' => 'Código actualizado correctamente',
        'data' => array(
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => $articulo['Nombre'],
            'usuario' => $usuario
        )
    ));

} catch (Exception $e) {
    error_log("Error en editar_codigo.php: " . $e->getMessage());

    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}
?>
