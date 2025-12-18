<?php
/**
 * Script para ejecutar la creación de la tabla usuario_modulos
 * Ejecutar una sola vez
 */

require_once __DIR__ . '/../conexionBD/conexion.php';

echo "<h2>Creando tabla usuario_modulos...</h2>";

// Verificar conexión
if (!$conn) {
    die("<p style='color:red'>Error: No hay conexión a la base de datos</p>");
}

// 1. Crear tabla si no existe
$sqlCreateTable = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='usuario_modulos' AND xtype='U')
BEGIN
    CREATE TABLE usuario_modulos (
        id INT IDENTITY(1,1) PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL,
        modulo VARCHAR(50) NOT NULL,
        activo BIT DEFAULT 1,
        fecha_asignacion DATETIME DEFAULT GETDATE(),
        CONSTRAINT UQ_usuario_modulo UNIQUE (usuario, modulo)
    );
END
";

$result = sqlsrv_query($conn, $sqlCreateTable);

if ($result === false) {
    echo "<p style='color:red'>Error al crear la tabla: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    echo "<p style='color:green'>✅ Tabla usuario_modulos creada/verificada exitosamente.</p>";
}

// 2. Obtener usuario admin (pantalla = 0)
$sqlAdmin = "SELECT TOP 1 Usuario FROM usuarios WHERE pantalla = 0";
$stmtAdmin = sqlsrv_query($conn, $sqlAdmin);
$admin = sqlsrv_fetch_array($stmtAdmin, SQLSRV_FETCH_ASSOC);

if ($admin) {
    $adminUser = $admin['Usuario'];
    echo "<p>Usuario admin encontrado: <strong>$adminUser</strong></p>";
    
    // 3. Insertar permisos para admin
    $modulos = [
        'despacho_factura',
        'validacion_facturas',
        'recepcion_documentos',
        'business_intelligence',
        'sistema_etiquetado',
        'gestion_usuarios',
        'dashboard_general',
        'listo_inventario',
        'codigos_barras',
        'codigos_referencia',
        'gestion_imagenes'
    ];
    
    $insertados = 0;
    foreach ($modulos as $modulo) {
        // Verificar si ya existe
        $sqlCheck = "SELECT COUNT(*) as cnt FROM usuario_modulos WHERE usuario = ? AND modulo = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$adminUser, $modulo]);
        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        
        if ($row['cnt'] == 0) {
            $sqlInsert = "INSERT INTO usuario_modulos (usuario, modulo, activo) VALUES (?, ?, 1)";
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$adminUser, $modulo]);
            if ($stmtInsert) {
                $insertados++;
            }
        }
    }
    
    echo "<p style='color:green'>✅ Se insertaron <strong>$insertados</strong> permisos nuevos para el admin.</p>";
} else {
    echo "<p style='color:orange'>⚠️ No se encontró un usuario con pantalla = 0 (admin).</p>";
}

echo "<hr>";
echo "<p><strong>Proceso completado.</strong></p>";
echo "<p><a href='../View/modulos/Gestion_de_usuario.php?tab=permisos'>→ Ir a Gestión de Permisos</a></p>";

sqlsrv_close($conn);
