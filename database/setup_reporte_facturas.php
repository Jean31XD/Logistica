<?php
/**
 * Script para registrar el módulo "Reporte de Facturas" en el sistema
 * Ejecutar una sola vez para habilitar el módulo
 */

require_once __DIR__ . '/../conexionBD/conexion.php';

echo "<h2>Configurando módulo: Reporte de Facturas</h2>";

// Verificar conexión
if (!$conn) {
    die("<p style='color:red'>Error: No hay conexión a la base de datos</p>");
}

// Obtener usuario admin (pantalla = 0)
$sqlAdmin = "SELECT Usuario FROM usuarios WHERE pantalla = 0";
$stmtAdmin = sqlsrv_query($conn, $sqlAdmin);

if (!$stmtAdmin) {
    die("<p style='color:red'>Error al buscar administradores: " . print_r(sqlsrv_errors(), true) . "</p>");
}

$insertados = 0;
$modulo = 'reporte_facturas';

echo "<h3>Asignando permisos del módulo 'reporte_facturas'...</h3>";

while ($admin = sqlsrv_fetch_array($stmtAdmin, SQLSRV_FETCH_ASSOC)) {
    $adminUser = $admin['Usuario'];

    // Verificar si ya existe el permiso
    $sqlCheck = "SELECT COUNT(*) as cnt FROM usuario_modulos WHERE usuario = ? AND modulo = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$adminUser, $modulo]);
    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

    if ($row['cnt'] == 0) {
        // Insertar permiso
        $sqlInsert = "INSERT INTO usuario_modulos (usuario, modulo, activo) VALUES (?, ?, 1)";
        $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$adminUser, $modulo]);

        if ($stmtInsert) {
            echo "<p style='color:green'>✅ Permiso asignado a: <strong>$adminUser</strong></p>";
            $insertados++;
        } else {
            echo "<p style='color:red'>❌ Error al asignar permiso a: $adminUser</p>";
            echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
        }
    } else {
        echo "<p style='color:orange'>⚠️ El usuario <strong>$adminUser</strong> ya tiene el permiso.</p>";
    }
}

echo "<hr>";
echo "<h3>Resumen:</h3>";
echo "<p>✅ Total de permisos nuevos asignados: <strong>$insertados</strong></p>";
echo "<p>📋 Módulo: <strong>reporte_facturas</strong></p>";
echo "<p>📄 Archivo: <strong>View/modulos/ReporteFacturas.php</strong></p>";

echo "<hr>";
echo "<p><strong>Configuración completada.</strong></p>";
echo "<p><a href='../View/modulos/ReporteFacturas.php'>→ Acceder al Reporte de Facturas</a></p>";
echo "<p><a href='../View/modulos/Gestion_de_usuario.php?tab=permisos'>→ Gestionar Permisos de Usuarios</a></p>";

sqlsrv_close($conn);
?>
