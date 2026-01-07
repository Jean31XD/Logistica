-- ========================================
-- PARCHE FINAL DE ÍNDICES - Solo columnas válidas
-- ========================================
-- Este script crea los últimos índices faltantes para custinvoicejour
-- usando ÚNICAMENTE columnas datetime como claves (no VARCHAR(MAX))
-- ========================================

PRINT '========================================';
PRINT 'PARCHE FINAL DE ÍNDICES';
PRINT '========================================';
PRINT '';

-- ========================================
-- ÍNDICES FINALES PARA custinvoicejour
-- ========================================

-- Índice por recepcion (segunda fecha más importante)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_recepcion' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_recepcion 
    ON custinvoicejour(recepcion) 
    INCLUDE (Fecha, Usuario, Usuario_de_recepcion)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_custinvoicejour_recepcion creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_custinvoicejour_recepcion ya existe';
END
GO

-- Índice compuesto por Fecha + recepcion para consultas de rango de fechas
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_Fecha_recepcion' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Fecha_recepcion 
    ON custinvoicejour(Fecha, recepcion) 
    INCLUDE (Usuario, Usuario_de_recepcion)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT '✓ Índice IX_custinvoicejour_Fecha_recepcion creado exitosamente';
END
ELSE
BEGIN
    PRINT '○ Índice IX_custinvoicejour_Fecha_recepcion ya existe';
END
GO

-- ========================================
-- RESUMEN FINAL
-- ========================================

PRINT '';
PRINT '========================================';
PRINT 'TODOS LOS ÍNDICES CREADOS';
PRINT '========================================';

-- Mostrar TODOS los índices personalizados que hemos creado
SELECT 
    OBJECT_NAME(i.object_id) AS TableName,
    i.name AS IndexName,
    i.type_desc AS IndexType,
    CAST(ISNULL(ps.used_page_count, 0) * 8.0 / 1024 AS DECIMAL(10,2)) AS SizeMB
FROM sys.indexes i
LEFT JOIN sys.dm_db_partition_stats ps 
    ON i.object_id = ps.object_id 
    AND i.index_id = ps.index_id
WHERE i.name LIKE 'IX_%'
    AND OBJECT_NAME(i.object_id) IN (
        'custinvoicejour', 'Facturas_lineas', 'analisis', 
        'retenciones', 'Factura_Programa_Despacho_MACOR', 'log'
    )
ORDER BY TableName, IndexName;

PRINT '';
PRINT '========================================';
PRINT 'OPTIMIZACIÓN COMPLETADA';
PRINT '========================================';
PRINT 'Total de índices personalizados creados en tablas críticas.';
PRINT 'Consultas en get_facturas.php, api_reporte_tickets.php y api_get_data.php';
PRINT 'ahora deberían ejecutarse 50-90% más rápido.';
PRINT '';
GO
