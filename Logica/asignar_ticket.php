<?php
// Incluir el archivo de conexión a la base de datos
require_once 'conexion.php'; 

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['usuario'])) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit();
}

$nuevo_usuario = $_SESSION['usuario'];
$tiket = $_POST['tiket'] ?? null;
$password_actual_asignado = $_POST['password_actual'] ?? null;
$password_nuevo_asignado = $_POST['password_nuevo'] ?? null;
$usuario_asignado_actual_nombre = $_POST['asignado_actual'] ?? null;

if (!$tiket || !$password_actual_asignado || !$password_nuevo_asignado) {
    $response['message'] = 'Faltan datos para la asignación.';
    echo json_encode($response);
    exit();
}

$conn = conectarDB();

try {
    // 1. Obtener los datos del usuario actual asignado al ticket desde la base de datos
    $stmt = $conn->prepare("SELECT id_asignacion, password FROM `users` WHERE nombre = ?");
    $stmt->execute([$usuario_asignado_actual_nombre]);
    $usuario_actual_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verificar la contraseña del usuario actualmente asignado
    if ($usuario_actual_info) {
        if (!password_verify($password_actual_asignado, $usuario_actual_info['password'])) {
            $response['message'] = 'La contraseña del usuario actualmente asignado es incorrecta.';
            echo json_encode($response);
            exit();
        }
    } else {
        // En caso de que el ticket no tenga un usuario asignado, no se requiere la verificación de su contraseña.
        // Se puede añadir lógica adicional aquí si es necesario.
        // Por ahora, asumimos que si no hay un usuario asignado, la asignación es libre.
    }
    
    // 3. Verificar la contraseña del usuario que se está auto-asignando (el de la sesión)
    $stmt_nuevo = $conn->prepare("SELECT id_asignacion, password FROM `users` WHERE nombre = ?");
    $stmt_nuevo->execute([$nuevo_usuario]);
    $usuario_nuevo_info = $stmt_nuevo->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_nuevo_info || !password_verify($password_nuevo_asignado, $usuario_nuevo_info['password'])) {
        $response['message'] = 'Tu propia contraseña es incorrecta. No puedes auto-asignarte el ticket.';
        echo json_encode($response);
        exit();
    }

    // 4. Si ambas verificaciones (o la única necesaria) son exitosas, proceder con la asignación
    $stmt_update = $conn->prepare("UPDATE tickets SET estatus = 'En Proceso', asignado_a = ? WHERE tiket = ?");
    if ($stmt_update->execute([$nuevo_usuario, $tiket])) {
        $response['success'] = true;
        $response['message'] = 'Ticket asignado correctamente.';
    } else {
        $response['message'] = 'Error al actualizar el ticket en la base de datos.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Error de la base de datos: ' . $e->getMessage();
}

echo json_encode($response);

$conn = null;