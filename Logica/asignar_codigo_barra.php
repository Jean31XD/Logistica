<?php
/**
 * Asignar Código de Barras
 * Asigna un código de barras escaneado a un artículo y registra el usuario que lo escaneó
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/session_config.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!isset($_SESSION['usuario']) || !tieneModulo('codigos_barras', $conn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Sin permisos']));
}

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'success' => false,
        'message' => 'Método no permitido'
    ));
    exit;
}

// Validar CSRF token
if (!isset($_POST['csrf_token'])) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Token de seguridad requerido'
    ));
    exit;
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Token de seguridad inválido'
    ));
    exit;
}

// Obtener y validar parámetros
$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$codigoBarra = isset($_POST['codigo_barra']) ? trim($_POST['codigo_barra']) : '';
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';

// Validaciones
if (empty($id)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'ID del artículo es requerido'
    ));
    exit;
}

if (empty($codigoBarra)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Código de barras es requerido'
    ));
    exit;
}

if (empty($usuario)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Usuario es requerido'
    ));
    exit;
}

try {
    // Verificar que el artículo existe y no tiene código asignado
    $sqlCheck = "SELECT id, Nombre, Codigo_barra FROM [dbo].[Arti_codigos] WHERE id = ?";
    $paramsCheck = array($id);
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

    if ($stmtCheck === false) {
        throw new Exception("Error al verificar el artículo: " . print_r(sqlsrv_errors(), true));
    }

    $articulo = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCheck);

    if (!$articulo) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Artículo no encontrado'
        ));
        exit;
    }

    // Verificar si el código de barras ya está asignado a otro artículo
    $sqlDuplicate = "SELECT id, Nombre, Codigo_barra
                     FROM [dbo].[Arti_codigos]
                     WHERE Codigo_barra = ?
                     AND id != ?";
    $paramsDuplicate = array($codigoBarra, $id);
    $stmtDuplicate = sqlsrv_query($conn, $sqlDuplicate, $paramsDuplicate);

    if ($stmtDuplicate === false) {
        throw new Exception("Error al verificar duplicados: " . print_r(sqlsrv_errors(), true));
    }

    $duplicado = sqlsrv_fetch_array($stmtDuplicate, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtDuplicate);

    if ($duplicado) {
        // Log del intento de duplicado
        error_log("INTENTO DE DUPLICADO: Código '$codigoBarra' ya asignado al artículo ID {$duplicado['id']} ({$duplicado['Nombre']}). Usuario: $usuario");

        $response = array(
            'success' => false,
            'isDuplicate' => true,
            'message' => '⚠️ CÓDIGO DUPLICADO: Este código ya está asignado al artículo "' . $duplicado['Nombre'] . '" (ID: ' . $duplicado['id'] . ')',
            'duplicateInfo' => array(
                'id' => $duplicado['id'],
                'nombre' => $duplicado['Nombre'],
                'codigo' => $codigoBarra
            )
        );

        error_log("RESPUESTA DUPLICADO: " . json_encode($response));

        echo json_encode($response);
        exit;
    }

    // Actualizar el artículo con el código de barras y el usuario
    $sqlUpdate = "UPDATE [dbo].[Arti_codigos]
                  SET Codigo_barra = ?,
                      Usuario = ?
                  WHERE id = ?";

    $paramsUpdate = array($codigoBarra, $usuario, $id);
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

    if ($stmtUpdate === false) {
        throw new Exception("Error al actualizar el artículo: " . print_r(sqlsrv_errors(), true));
    }

    $rowsAffected = sqlsrv_rows_affected($stmtUpdate);
    sqlsrv_free_stmt($stmtUpdate);

    if ($rowsAffected > 0) {
        echo json_encode(array(
            'success' => true,
            'message' => 'Código de barras asignado correctamente',
            'articulo' => array(
                'id' => $id,
                'nombre' => $articulo['Nombre'],
                'codigo_barra' => $codigoBarra,
                'usuario' => $usuario
            )
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'No se pudo actualizar el artículo'
        ));
    }

} catch (Exception $e) {
    error_log("Error en asignar_codigo_barra.php: " . $e->getMessage());

    echo json_encode(array(
        'success' => false,
        'message' => 'Error al asignar el código de barras',
        'error' => $e->getMessage()
    ));
}
?>
