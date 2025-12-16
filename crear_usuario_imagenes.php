<?php
/**
 * Script para crear usuarios con acceso a Gestión de Imágenes
 * MACO Logística - Pantalla 13
 *
 * IMPORTANTE: Ejecuta este script solo UNA VEZ y luego BÓRRALO por seguridad
 */

require_once __DIR__ . '/conexionBD/conexion.php';

// =====================================================
// CONFIGURACIÓN DEL USUARIO A CREAR
// =====================================================
$nuevo_usuario = 'imagenes'; // Cambia esto
$nueva_password = 'Imagenes2025!'; // Cambia esto
$pantalla = 13; // Gestión de Imágenes

// =====================================================
// CREAR USUARIO
// =====================================================

echo "<h1>Crear Usuario para Gestión de Imágenes</h1>";

// Verificar si el usuario ya existe
$check_sql = "SELECT COUNT(*) as count FROM usuarios WHERE usuario = ?";
$check_stmt = sqlsrv_query($conn, $check_sql, [$nuevo_usuario]);

if ($check_stmt === false) {
    die("Error al verificar usuario: " . print_r(sqlsrv_errors(), true));
}

$result = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

if ($result['count'] > 0) {
    echo "<p style='color: orange;'>⚠️ El usuario '<strong>$nuevo_usuario</strong>' ya existe.</p>";
    echo "<p>¿Deseas actualizar su nivel de acceso a Gestión de Imágenes?</p>";

    // Actualizar pantalla del usuario existente
    $update_sql = "UPDATE usuarios SET pantalla = ? WHERE usuario = ?";
    $update_stmt = sqlsrv_query($conn, $update_sql, [$pantalla, $nuevo_usuario]);

    if ($update_stmt === false) {
        die("Error al actualizar usuario: " . print_r(sqlsrv_errors(), true));
    }

    echo "<p style='color: green;'>✅ Usuario actualizado correctamente a pantalla $pantalla (Gestión de Imágenes)</p>";
} else {
    // Crear nuevo usuario
    $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);

    $insert_sql = "INSERT INTO usuarios (usuario, password, pantalla) VALUES (?, ?, ?)";
    $insert_stmt = sqlsrv_query($conn, $insert_sql, [$nuevo_usuario, $password_hash, $pantalla]);

    if ($insert_stmt === false) {
        die("Error al crear usuario: " . print_r(sqlsrv_errors(), true));
    }

    echo "<p style='color: green;'>✅ Usuario creado correctamente!</p>";
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Credenciales:</h3>";
    echo "<p><strong>Usuario:</strong> $nuevo_usuario</p>";
    echo "<p><strong>Contraseña:</strong> $nueva_password</p>";
    echo "<p><strong>Nivel de acceso:</strong> Gestión de Imágenes (Pantalla 13)</p>";
    echo "</div>";
}

// =====================================================
// LISTAR TODOS LOS USUARIOS
// =====================================================
echo "<hr>";
echo "<h2>Usuarios Existentes</h2>";

$list_sql = "SELECT usuario, pantalla FROM usuarios ORDER BY pantalla";
$list_stmt = sqlsrv_query($conn, $list_sql);

if ($list_stmt === false) {
    die("Error al listar usuarios: " . print_r(sqlsrv_errors(), true));
}

$roles = [
    0 => 'Administrador',
    1 => 'Gestión',
    2 => 'Facturas',
    3 => 'CXC',
    4 => 'Reportes',
    5 => 'Panel Admin',
    6 => 'BI',
    8 => 'Etiquetas',
    9 => 'Dashboard',
    10 => 'Inventario Listo',
    11 => 'Códigos de Barras',
    12 => 'Códigos de Referencia',
    13 => 'Gestión de Imágenes'
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #333; color: white;'>";
echo "<th>Usuario</th><th>Pantalla</th><th>Rol</th>";
echo "</tr>";

while ($row = sqlsrv_fetch_array($list_stmt, SQLSRV_FETCH_ASSOC)) {
    $rol_nombre = $roles[$row['pantalla']] ?? 'Desconocido';
    $highlight = ($row['pantalla'] == 13) ? "background: #d1fae5;" : "";
    echo "<tr style='$highlight'>";
    echo "<td><strong>{$row['usuario']}</strong></td>";
    echo "<td>{$row['pantalla']}</td>";
    echo "<td>$rol_nombre</td>";
    echo "</tr>";
}

echo "</table>";

sqlsrv_close($conn);

echo "<hr>";
echo "<p style='color: red; font-weight: bold;'>⚠️ IMPORTANTE: Por seguridad, BORRA este archivo después de usarlo.</p>";
echo "<p>Comando: <code>del " . __FILE__ . "</code></p>";
?>
