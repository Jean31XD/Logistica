<?php
// api_login.php
header('Content-Type: application/json');
session_start();

// Incluir tu archivo de conexión a la base de datos
require_once __DIR__ . '../conexionBD/conexion.php';

// Función para registrar intentos de acceso
function registrarAcceso($conn, $codigo, $exito) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO log_accesos (codigo, ip_address, exito) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $codigo, $ip, $exito);
    $stmt->execute();
    $stmt->close();
}

// Manejo de solicitud POST para login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $codigo = isset($input['codigo']) ? trim($input['codigo']) : '';
    
    if (empty($codigo)) {
        echo json_encode(['success' => false, 'message' => 'Código requerido']);
        exit;
    }
    
    // Validar que el código tenga 4 dígitos
    if (!preg_match('/^\d{4}$/', $codigo)) {
        echo json_encode(['success' => false, 'message' => 'El código debe tener 4 dígitos']);
        exit;
    }
    
    try {
        // Buscar el código en la base de datos
        $stmt = $conn->prepare("SELECT id, codigo, almacen, descripcion, es_admin FROM codigos_acceso WHERE codigo = ? AND activo = 1");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            
            // Actualizar último acceso
            $updateStmt = $conn->prepare("UPDATE codigos_acceso SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $usuario['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Registrar acceso exitoso
            registrarAcceso($conn, $codigo, 1);
            
            // Guardar en sesión
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_codigo'] = $usuario['codigo'];
            $_SESSION['user_almacen'] = $usuario['almacen'];
            $_SESSION['user_es_admin'] = $usuario['es_admin'];
            $_SESSION['user_descripcion'] = $usuario['descripcion'];
            $_SESSION['login_time'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => 'Acceso concedido',
                'data' => [
                    'almacen' => $usuario['almacen'],
                    'es_admin' => (bool)$usuario['es_admin'],
                    'descripcion' => $usuario['descripcion']
                ]
            ]);
        } else {
            // Registrar acceso fallido
            registrarAcceso($conn, $codigo, 0);
            
            echo json_encode(['success' => false, 'message' => 'Código inválido']);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    }
    
    exit;
}

// Manejo de solicitud GET para logout
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
    exit;
}

// Verificar sesión activa
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'data' => [
                'almacen' => $_SESSION['user_almacen'],
                'es_admin' => $_SESSION['user_es_admin'],
                'descripcion' => $_SESSION['user_descripcion']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'logged_in' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>