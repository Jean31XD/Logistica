-- ========================================
-- PARCHE DE ÍNDICES - Corrección para custinvoicejour y analisis
-- ========================================
-- Este script corrige los índices que fallaron debido a:
-- 1. Columnas VARCHAR(MAX) en custinvoicejour
-- 2. Columna computada Tiempo en analisis
-- ========================================

USE [MACODB]; -- Reemplazar con el nombre de tu base de datos
GO

PRINT '========================================';
PRINT 'PARCHE DE ÍNDICES - Versión Corregida';
PRINT '========================================';
PRINT '';

-- ========================================
-- 1. ÍNDICES PARA custinvoicejour (CORREGIDO)
-- ========================================
-- Estrategia: Usar FECHA como clave principal, incluir Transportista y Factura en INCLUDE

-- Índice principal por Fecha (la columna más usada en filtros)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_Fecha' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Fecha 
    ON custinvoicejour(Fecha) 
    INCLUDE (Validar, Usuario, Fecha_scanner, recepcion, Usuario_de_recepcion)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_custinvoicejour_Fecha creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_custinvoicejour_Fecha ya existe';
END
GO

-- Índice por Fecha_scanner para búsquedas de facturas recibidas
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_Fecha_scanner' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Fecha_scanner 
    ON custinvoicejour(Fecha_scanner) 
    INCLUDE (Fecha, Usuario, Validar)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_custinvoicejour_Fecha_scanner creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_custinvoicejour_Fecha_scanner ya existe';
END
GO

-- ========================================
-- 2. ÍNDICES PARA analisis (CORREGIDO)
-- ========================================
-- Estrategia: Eliminar la columna Tiempo de los índices

-- Índice por Fecha_de_Creacion (SIN Tiempo)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_analisis_Fecha_Creacion_v2' AND object_id = OBJECT_ID('analisis'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_analisis_Fecha_Creacion_v2 
    ON analisis(Fecha_de_Creacion) 
    INCLUDE (Factura, Asignado, Empresa, Nombre, Fecha_de_Despacho)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_analisis_Fecha_Creacion_v2 creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_analisis_Fecha_Creacion_v2 ya existe';
END
GO

-- Índice por Asignado (SIN Tiempo)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_analisis_Asignado_v2' AND object_id = OBJECT_ID('analisis'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_analisis_Asignado_v2 
    ON analisis(Asignado, Fecha_de_Creacion) 
    INCLUDE (Factura, Empresa)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_analisis_Asignado_v2 creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_analisis_Asignado_v2 ya existe';
END
GO

-- ========================================
-- 3. ÍNDICE ADICIONAL ÚTIL
-- ========================================

-- Índice compuesto para las consultas más comunes en get_facturas.php
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_Fecha_Validar' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Fecha_Validar 
    ON custinvoicejour(Fecha, Validar) 
    INCLUDE (Usuario, Fecha_scanner, recepcion)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_custinvoicejour_Fecha_Validar creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_custinvoicejour_Fecha_Validar ya existe';
END
GO

-- ========================================
-- RESUMEN Y VERIFICACIÓN
-- ========================================

PRINT '';
PRINT '========================================';
PRINT 'RESUMEN DE ÍNDICES CREADOS POR PARCHE';
PRINT '========================================';

-- Mostrar todos los índices del parche
SELECT 
    OBJECT_NAME(i.object_id) AS TableName,
    i.name AS IndexName,
    i.type_desc AS IndexType,
    CAST(ps.used_page_count * 8.0 / 1024 AS DECIMAL(10,2)) AS SizeMB
FROM sys.indexes i
LEFT JOIN sys.dm_db_partition_stats ps 
    ON i.object_id = ps.object_id 
    AND i.index_id = ps.index_id
WHERE i.name IN (
    'IX_custinvoicejour_Fecha',
    'IX_custinvoicejour_Fecha_scanner',
    'IX_custinvoicejour_Fecha_Validar',
    'IX_analisis_Fecha_Creacion_v2',
    'IX_analisis_Asignado_v2'
)
ORDER BY TableName, IndexName;

PRINT '';
PRINT 'Parche completado. Todos los índices críticos ahora están creados.';
GO
