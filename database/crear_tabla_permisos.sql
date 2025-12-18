-- =====================================================
-- Script para crear tabla de permisos de módulos
-- Ejecutar en la base de datos del proyecto MACO
-- =====================================================

-- Crear tabla de permisos
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
    
    PRINT 'Tabla usuario_modulos creada exitosamente.';
END
ELSE
BEGIN
    PRINT 'La tabla usuario_modulos ya existe.';
END
GO

-- Insertar permisos por defecto para el usuario admin (si existe)
-- Esto le da acceso a todos los módulos al administrador
DECLARE @adminUser VARCHAR(50);
SET @adminUser = (SELECT TOP 1 Usuario FROM usuarios WHERE pantalla = 0);

IF @adminUser IS NOT NULL
BEGIN
    -- Lista de módulos del sistema
    DECLARE @modulos TABLE (modulo VARCHAR(50));
    INSERT INTO @modulos VALUES 
        ('despacho_factura'),
        ('validacion_facturas'),
        ('recepcion_documentos'),
        ('business_intelligence'),
        ('sistema_etiquetado'),
        ('gestion_usuarios'),
        ('dashboard_general'),
        ('listo_inventario'),
        ('codigos_barras'),
        ('codigos_referencia'),
        ('gestion_imagenes');
    
    INSERT INTO usuario_modulos (usuario, modulo, activo)
    SELECT @adminUser, modulo, 1
    FROM @modulos m
    WHERE NOT EXISTS (
        SELECT 1 FROM usuario_modulos um 
        WHERE um.usuario = @adminUser AND um.modulo = m.modulo
    );
    
    PRINT 'Permisos de admin insertados.';
END
GO
