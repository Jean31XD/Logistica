<?php
/**
 * API de Permisos de Módulos
 * 
 * Gestiona permisos de módulos por usuario.
 * 
 * Endpoints:
 *   GET  ?action=get&usuario=xxx     - Obtener módulos del usuario
 *   GET  ?action=list_users          - Listar usuarios para dropdown
 *   POST action=save                 - Guardar permisos del usuario
 * 
 * @package    MACO\API
 * @author     MACO Team
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([0]); // Solo administradores

// Validar CSRF para POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($csrf)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Token CSRF inválido']));
    }
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexionBD/conexion.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Cargar configuración de módulos desde config/app.php
$config = require __DIR__ . '/../config/app.php';
$modulosDisponibles = $config['modulos'] ?? [];

switch ($action) {
    case 'get':
        // Obtener módulos asignados a un usuario
        $usuario = $_GET['usuario'] ?? '';
        
        if (empty($usuario)) {
            echo json_encode(['success' => false, 'error' => 'Usuario no especificado']);
            exit;
        }
        
        $sql = "SELECT modulo, activo FROM usuario_modulos WHERE usuario = ? ORDER BY modulo";
        $stmt = sqlsrv_query($conn, $sql, [$usuario]);
        
        if ($stmt === false) {
            echo json_encode(['success' => false, 'error' => 'Error al consultar permisos']);
            exit;
        }
        
        $modulosAsignados = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $modulosAsignados[$row['modulo']] = (bool)$row['activo'];
        }
        
        // Combinar con módulos disponibles
        $resultado = [];
        foreach ($modulosDisponibles as $key => $modulo) {
            $resultado[] = [
                'key' => $key,
                'name' => $modulo['name'],
                'icon' => $modulo['icon'] ?? 'fa-cube',
                'asignado' => isset($modulosAsignados[$key]) && $modulosAsignados[$key]
            ];
        }
        
        echo json_encode(['success' => true, 'modulos' => $resultado]);
        break;
        
    case 'list_users':
        // Listar usuarios para el dropdown
        $sql = "SELECT Usuario, Nombre, pantalla FROM usuarios ORDER BY Nombre";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            echo json_encode(['success' => false, 'error' => 'Error al listar usuarios']);
            exit;
        }
        
        $usuarios = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $usuarios[] = [
                'usuario' => $row['Usuario'],
                'nombre' => $row['Nombre'],
                'pantalla' => $row['pantalla']
            ];
        }
        
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        break;
        
    case 'save':
        // Guardar permisos del usuario
        $usuario = $_POST['usuario'] ?? '';
        $modulos = $_POST['modulos'] ?? [];
        
        if (empty($usuario)) {
            echo json_encode(['success' => false, 'error' => 'Usuario no especificado']);
            exit;
        }
        
        // Convertir modulos a array si es string JSON
        if (is_string($modulos)) {
            $modulos = json_decode($modulos, true) ?? [];
        }
        
        // Iniciar transacción
        sqlsrv_begin_transaction($conn);
        
        try {
            // Desactivar todos los permisos del usuario primero
            $sqlUpdate = "UPDATE usuario_modulos SET activo = 0 WHERE usuario = ?";
            sqlsrv_query($conn, $sqlUpdate, [$usuario]);
            
            // Activar/insertar los módulos seleccionados
            foreach ($modulos as $modulo) {
                // Verificar si existe el registro
                $sqlCheck = "SELECT COUNT(*) as cnt FROM usuario_modulos WHERE usuario = ? AND modulo = ?";
                $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$usuario, $modulo]);
                $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
                
                if ($row['cnt'] > 0) {
                    // Actualizar
                    $sqlUpdate = "UPDATE usuario_modulos SET activo = 1, fecha_asignacion = GETDATE() WHERE usuario = ? AND modulo = ?";
                    sqlsrv_query($conn, $sqlUpdate, [$usuario, $modulo]);
                } else {
                    // Insertar
                    $sqlInsert = "INSERT INTO usuario_modulos (usuario, modulo, activo) VALUES (?, ?, 1)";
                    sqlsrv_query($conn, $sqlInsert, [$usuario, $modulo]);
                }
            }
            
            sqlsrv_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Permisos guardados correctamente']);
            
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['success' => false, 'error' => 'Error al guardar permisos: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_user_modules':
        // Obtener módulos activos del usuario actual (para Admin.php)
        $usuario = $_SESSION['usuario'] ?? '';
        $pantalla = $_SESSION['pantalla'] ?? -1;
        
        // Si es admin (pantalla 0), tiene acceso a todo
        if ($pantalla == 0) {
            $resultado = array_keys($modulosDisponibles);
            echo json_encode(['success' => true, 'modulos' => $resultado, 'is_admin' => true]);
            exit;
        }
        
        $sql = "SELECT modulo FROM usuario_modulos WHERE usuario = ? AND activo = 1";
        $stmt = sqlsrv_query($conn, $sql, [$usuario]);
        
        if ($stmt === false) {
            echo json_encode(['success' => false, 'modulos' => []]);
            exit;
        }
        
        $modulos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $modulos[] = $row['modulo'];
        }
        
        echo json_encode(['success' => true, 'modulos' => $modulos, 'is_admin' => false]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}

sqlsrv_close($conn);
