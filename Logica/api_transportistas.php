<?php
/**
 * API de Transportistas - MACO
 * Operaciones AJAX para crear, editar, eliminar y buscar transportistas
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();
require_once __DIR__ . '/../conexionBD/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Acción no válida'];

switch ($action) {
    case 'list':
        // Listar transportistas con paginación y búsqueda
        $busqueda = trim($_GET['buscar'] ?? '');
        $pagina = max(1, intval($_GET['pagina'] ?? 1));
        $porPagina = 10;
        $offset = ($pagina - 1) * $porPagina;

        // Contar total
        $sqlCount = "SELECT COUNT(*) as total FROM facebd";
        $paramsCount = [];
        if ($busqueda) {
            $sqlCount .= " WHERE Nombres LIKE ? OR Cedula LIKE ? OR Empresa LIKE ? OR Matricula LIKE ?";
            $like = "%$busqueda%";
            $paramsCount = [$like, $like, $like, $like];
        }
        $stmtCount = sqlsrv_query($conn, $sqlCount, $paramsCount);
        $totalRegistros = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
        $totalPaginas = max(1, ceil($totalRegistros / $porPagina));

        // Obtener registros
        $sql = "SELECT Cedula, Nombres, Empresa, RNC, Matricula FROM facebd";
        $params = [];
        if ($busqueda) {
            $sql .= " WHERE Nombres LIKE ? OR Cedula LIKE ? OR Empresa LIKE ? OR Matricula LIKE ?";
            $like = "%$busqueda%";
            $params = [$like, $like, $like, $like];
        }
        $sql .= " ORDER BY Nombres ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = $offset;
        $params[] = $porPagina;

        $transportistas = [];
        $result = sqlsrv_query($conn, $sql, $params);
        if ($result) {
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $transportistas[] = $row;
            }
        }

        $response = [
            'success' => true,
            'data' => $transportistas,
            'total' => $totalRegistros,
            'paginas' => $totalPaginas,
            'pagina' => $pagina,
            'porPagina' => $porPagina
        ];
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'error' => 'Método no permitido'];
            break;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!validarTokenCSRF($csrf)) {
            $response = ['success' => false, 'error' => 'Token CSRF inválido'];
            break;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $empresa = trim($_POST['empresa'] ?? '');
        $rnc = trim($_POST['rnc'] ?? '');
        $matricula = trim($_POST['matricula'] ?? '');
        $usuario = $_SESSION['usuario'] ?? '';

        if (empty($nombre) || empty($cedula)) {
            $response = ['success' => false, 'error' => 'Nombre y cédula son obligatorios'];
            break;
        }

        // Verificar duplicado
        $check = sqlsrv_query($conn, "SELECT Cedula FROM facebd WHERE Cedula = ?", [$cedula]);
        if ($check && sqlsrv_fetch($check)) {
            $response = ['success' => false, 'error' => 'Ya existe un transportista con esa cédula'];
            break;
        }

        $sql = "INSERT INTO facebd (Nombres, Cedula, Empresa, RNC, Matricula, creado_por) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$nombre, $cedula, $empresa, $rnc, $matricula, $usuario]);

        $response = $stmt 
            ? ['success' => true, 'message' => 'Transportista creado exitosamente']
            : ['success' => false, 'error' => 'Error al crear transportista'];
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'error' => 'Método no permitido'];
            break;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!validarTokenCSRF($csrf)) {
            $response = ['success' => false, 'error' => 'Token CSRF inválido'];
            break;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $empresa = trim($_POST['empresa'] ?? '');
        $rnc = trim($_POST['rnc'] ?? '');
        $matricula = trim($_POST['matricula'] ?? '');
        $usuario = $_SESSION['usuario'] ?? '';

        if (empty($cedula)) {
            $response = ['success' => false, 'error' => 'Cédula es obligatoria'];
            break;
        }

        $sql = "UPDATE facebd SET Nombres = ?, Empresa = ?, RNC = ?, Matricula = ?, creado_por = ? WHERE Cedula = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nombre, $empresa, $rnc, $matricula, $usuario, $cedula]);

        $response = $stmt 
            ? ['success' => true, 'message' => 'Transportista actualizado']
            : ['success' => false, 'error' => 'Error al actualizar'];
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $response = ['success' => false, 'error' => 'Método no permitido'];
            break;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!validarTokenCSRF($csrf)) {
            $response = ['success' => false, 'error' => 'Token CSRF inválido'];
            break;
        }

        $cedula = trim($_POST['cedula'] ?? '');
        if (empty($cedula)) {
            $response = ['success' => false, 'error' => 'Cédula es obligatoria'];
            break;
        }

        $sql = "DELETE FROM facebd WHERE Cedula = ?";
        $stmt = sqlsrv_query($conn, $sql, [$cedula]);

        if ($stmt) {
            $rows = sqlsrv_rows_affected($stmt);
            $response = $rows > 0 
                ? ['success' => true, 'message' => 'Transportista eliminado']
                : ['success' => false, 'error' => 'No se encontró el transportista'];
        } else {
            $response = ['success' => false, 'error' => 'Error al eliminar: ' . print_r(sqlsrv_errors(), true)];
        }
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
