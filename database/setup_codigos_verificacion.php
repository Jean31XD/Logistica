<?php
/**
 * Script para crear la tabla de códigos de verificación temporales
 * Para reasignación de tickets con código por correo
 */

require_once __DIR__ . '/../conexionBD/conexion.php';

echo "<h2>Configurando Sistema de Códigos de Verificación</h2>";

if (!$conn) {
    die("<p style='color:red'>Error: No hay conexión a la base de datos</p>");
}

// Crear tabla para códigos de verificación
$sqlCreateTable = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='codigos_verificacion' AND xtype='U')
BEGIN
    CREATE TABLE codigos_verificacion (
        id INT IDENTITY(1,1) PRIMARY KEY,
        codigo VARCHAR(6) NOT NULL,
        usuario VARCHAR(50) NOT NULL,
        ticket VARCHAR(50) NULL,
        creado DATETIME DEFAULT GETDATE(),
        expira DATETIME NOT NULL,
        usado BIT DEFAULT 0,
        ip_solicitud VARCHAR(50) NULL,
        CONSTRAINT IX_codigo_usuario UNIQUE (codigo, usuario)
    );

    CREATE INDEX IX_expira ON codigos_verificacion(expira);
    CREATE INDEX IX_usuario_usado ON codigos_verificacion(usuario, usado);
END
";

$result = sqlsrv_query($conn, $sqlCreateTable);

if ($result === false) {
    echo "<p style='color:red'>❌ Error al crear la tabla:</p>";
    echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
} else {
    echo "<p style='color:green'>✅ Tabla 'codigos_verificacion' creada/verificada exitosamente.</p>";
}

// Crear procedimiento para limpiar códigos expirados
$sqlCreateProc = "
IF EXISTS (SELECT * FROM sys.objects WHERE type = 'P' AND name = 'LimpiarCodigosExpirados')
    DROP PROCEDURE LimpiarCodigosExpirados;
";
sqlsrv_query($conn, $sqlCreateProc);

$sqlCreateProc2 = "
CREATE PROCEDURE LimpiarCodigosExpirados
AS
BEGIN
    DELETE FROM codigos_verificacion
    WHERE expira < GETDATE() OR usado = 1;
END
";

$result2 = sqlsrv_query($conn, $sqlCreateProc2);

if ($result2 === false) {
    echo "<p style='color:orange'>⚠️ No se pudo crear el procedimiento de limpieza (puede ser que ya exista)</p>";
} else {
    echo "<p style='color:green'>✅ Procedimiento 'LimpiarCodigosExpirados' creado exitosamente.</p>";
}

echo "<hr>";
echo "<h3>Resumen:</h3>";
echo "<ul>";
echo "<li>✅ Tabla: <code>codigos_verificacion</code></li>";
echo "<li>✅ Procedimiento: <code>LimpiarCodigosExpirados</code></li>";
echo "<li>📋 Validez de códigos: 5 minutos</li>";
echo "<li>🔐 Formato de código: 6 dígitos numéricos</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Configuración completada.</strong></p>";
echo "<p><a href='../View/modulos/Despacho_factura.php'>→ Ir a Despacho de Facturas</a></p>";

sqlsrv_close($conn);
?>
