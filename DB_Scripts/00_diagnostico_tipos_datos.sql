-- ========================================
-- SCRIPT DE DIAGNÓSTICO - Verificar Tipos de Datos
-- ========================================
-- Ejecuta este script PRIMERO para identificar qué columnas 
-- tienen tipos de datos problemáticos para índices

USE [MACODB]; -- Reemplazar con el nombre de tu base de datos
GO

PRINT '========================================';
PRINT 'VERIFICACIÓN DE TIPOS DE DATOS';
PRINT '========================================';
PRINT '';

-- Verificar estructura de Facturas_lineas
PRINT '1. Estructura de Facturas_lineas:';
SELECT 
    c.name AS ColumnName,
    t.name AS DataType,
    c.max_length AS MaxLength,
    CASE 
        WHEN t.name IN ('text', 'ntext', 'image', 'xml') THEN 'NO INDEXABLE'
        WHEN t.name IN ('varchar', 'nvarchar', 'varbinary') AND c.max_length = -1 THEN 'NO INDEXABLE (MAX)'
        ELSE 'INDEXABLE'
    END AS IndexCompatibility
FROM sys.columns c
INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('Facturas_lineas')
    AND c.name IN ('invoiceid', 'invoicedate', 'inventlocationid', 'lineamount')
ORDER BY c.column_id;

PRINT '';

-- Verificar estructura de analisis
PRINT '2. Estructura de analisis:';
SELECT 
    c.name AS ColumnName,
    t.name AS DataType,
    c.max_length AS MaxLength,
    CASE 
        WHEN t.name IN ('text', 'ntext', 'image', 'xml') THEN 'NO INDEXABLE'
        WHEN t.name IN ('varchar', 'nvarchar', 'varbinary') AND c.max_length = -1 THEN 'NO INDEXABLE (MAX)'
        ELSE 'INDEXABLE'
    END AS IndexCompatibility
FROM sys.columns c
INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('analisis')
    AND c.name IN ('Factura', 'Asignado', 'Fecha_de_Creacion', 'Empresa')
ORDER BY c.column_id;

PRINT '';

-- Verificar estructura de custinvoicejour
PRINT '3. Estructura de custinvoicejour:';
SELECT 
    c.name AS ColumnName,
    t.name AS DataType,
    c.max_length AS MaxLength,
    CASE 
        WHEN t.name IN ('text', 'ntext', 'image', 'xml') THEN 'NO INDEXABLE'
        WHEN t.name IN ('varchar', 'nvarchar', 'varbinary') AND c.max_length = -1 THEN 'NO INDEXABLE (MAX)'
        ELSE 'INDEXABLE'
    END AS IndexCompatibility
FROM sys.columns c
INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('custinvoicejour')
    AND c.name IN ('Factura', 'Transportista', 'Fecha', 'Validar')
ORDER BY c.column_id;

PRINT '';
PRINT '========================================';
PRINT 'FIN DE DIAGNÓSTICO';
PRINT '========================================';
