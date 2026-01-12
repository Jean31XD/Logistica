-- ========================================
-- INDICES DE OPTIMIZACION - Azure SQL
-- ========================================
-- Ejecutar estos scripts en Azure SQL Database
-- IMPORTANTE: Ejecutar en horario de baja carga
-- ========================================

-- 1. Índice para tabla Facturas_lineas (consultas de dashboard)
-- Este índice optimiza las CTEs que agrupan por fecha e inventario
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Facturas_lineas_invoicedate_almacen')
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_lineas_invoicedate_almacen 
    ON Facturas_lineas(invoicedate, inventlocationid) 
    INCLUDE (invoiceid, lineamount, lineamounttax, invoicingname);
    PRINT 'Índice IX_Facturas_lineas_invoicedate_almacen creado';
END
ELSE
    PRINT 'Índice IX_Facturas_lineas_invoicedate_almacen ya existe';
GO

-- 2. Índice para custinvoicejour (consultas de BI y recepción)
-- NOTA: Muchas columnas son TEXT/NVARCHAR(MAX), solo Fecha puede ser key
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_custinvoicejour_Fecha')
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Fecha
    ON custinvoicejour(Fecha);
    PRINT 'Índice IX_custinvoicejour_Fecha creado';
END
ELSE
    PRINT 'Índice IX_custinvoicejour_Fecha ya existe';
GO

-- 3. Índice para Factura_Programa_Despacho_MACOR (consultas de estado)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Factura_Programa_Estado')
BEGIN
    CREATE NONCLUSTERED INDEX IX_Factura_Programa_Estado
    ON Factura_Programa_Despacho_MACOR(Estado) 
    INCLUDE (No_Factura, Fecha_de_Despacho, Fecha_de_Entregado, Despachado_por, Entregado_por);
    PRINT 'Índice IX_Factura_Programa_Estado creado';
END
ELSE
    PRINT 'Índice IX_Factura_Programa_Estado ya existe';
GO

-- 4. Índice para custinvoicejour por recepcion (para filtros CxC)
-- NOTA: Factura es TEXT/NVARCHAR(MAX), no puede ser key column
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_custinvoicejour_recepcion')
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_recepcion
    ON custinvoicejour(recepcion, Usuario_de_recepcion);
    PRINT 'Índice IX_custinvoicejour_recepcion creado';
END
ELSE
    PRINT 'Índice IX_custinvoicejour_recepcion ya existe';
GO

-- ========================================
-- VERIFICAR FRAGMENTACIÓN DE ÍNDICES
-- ========================================
-- Ejecutar periódicamente para mantener rendimiento

SELECT 
    OBJECT_NAME(ips.object_id) AS TableName,
    i.name AS IndexName,
    ips.avg_fragmentation_in_percent AS FragmentationPercent,
    ips.page_count AS PageCount,
    CASE 
        WHEN ips.avg_fragmentation_in_percent > 30 THEN 'REBUILD recomendado'
        WHEN ips.avg_fragmentation_in_percent > 10 THEN 'REORGANIZE recomendado'
        ELSE 'OK'
    END AS Accion
FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips
JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
WHERE ips.avg_fragmentation_in_percent > 5
ORDER BY ips.avg_fragmentation_in_percent DESC;
GO

PRINT '';
PRINT '========================================';
PRINT 'INDICES DE OPTIMIZACIÓN COMPLETADOS';
PRINT '========================================';
GO
