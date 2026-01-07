-- ========================================
-- SCRIPT DE ÍNDICES OPTIMIZADOS - MACO
-- Propósito: Prevenir bloqueos y mejorar rendimiento
-- Fecha: 2026-01-07
-- ========================================

-- IMPORTANTE: Ejecutar fuera de horario laboral si es posible
-- Estos índices pueden tardar varios minutos en crearse

USE [MACODB]; -- Reemplazar con el nombre de tu base de datos
GO

-- ========================================
-- 1. ÍNDICES PARA custinvoicejour
-- ========================================

-- Verificar si existe el índice antes de crearlo
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_Fecha_Transportista' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Fecha_Transportista 
    ON custinvoicejour(Fecha, Transportista) 
    INCLUDE (Factura, Validar, Usuario, Fecha_scanner, recepcion, Usuario_de_recepcion)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_custinvoicejour_Fecha_Transportista creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_custinvoicejour_Fecha_Transportista ya existe';
END
GO

-- Índice para búsqueda por factura
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_Factura' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_Factura 
    ON custinvoicejour(Factura) 
    INCLUDE (Validar, Transportista, Usuario)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_custinvoicejour_Factura creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_custinvoicejour_Factura ya existe';
END
GO

-- ========================================
-- 2. ÍNDICES PARA Facturas_lineas
-- NOTA: invoiceid puede ser VARCHAR(MAX), usamos INCLUDE
-- ========================================

-- Índice por fecha de factura (columna clave) con invoiceid en INCLUDE
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_lineas_invoicedate' AND object_id = OBJECT_ID('Facturas_lineas'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_lineas_invoicedate 
    ON Facturas_lineas(invoicedate, inventlocationid) 
    INCLUDE (lineamount, lineamounttax, invoicingname)
    WITH (ONLINE = OFF, FILLFACTOR = 85);
    
    PRINT 'Índice IX_Facturas_lineas_invoicedate creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_Facturas_lineas_invoicedate ya existe';
END
GO

-- Índice para inventlocationid (almacén)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_lineas_inventlocationid' AND object_id = OBJECT_ID('Facturas_lineas'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_lineas_inventlocationid 
    ON Facturas_lineas(inventlocationid, invoicedate) 
    INCLUDE (lineamount, lineamounttax)
    WITH (ONLINE = OFF, FILLFACTOR = 85);
    
    PRINT 'Índice IX_Facturas_lineas_inventlocationid creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_Facturas_lineas_inventlocationid ya existe';
END
GO

-- ========================================
-- 3. ÍNDICES PARA analisis
-- ========================================

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_analisis_Fecha_Creacion' AND object_id = OBJECT_ID('analisis'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_analisis_Fecha_Creacion 
    ON analisis(Fecha_de_Creacion) 
    INCLUDE (Factura, Asignado, Tiempo, Empresa, Nombre, Fecha_de_Despacho)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_analisis_Fecha_Creacion creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_analisis_Fecha_Creacion ya existe';
END
GO

-- Índice por Asignado (usuario)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_analisis_Asignado' AND object_id = OBJECT_ID('analisis'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_analisis_Asignado 
    ON analisis(Asignado, Fecha_de_Creacion) 
    INCLUDE (Factura, Tiempo, Empresa)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_analisis_Asignado creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_analisis_Asignado ya existe';
END
GO

-- Índice para la columna Factura (para el JOIN con Facturas_lineas)
-- Solo si Factura NO es VARCHAR(MAX)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_analisis_Factura' AND object_id = OBJECT_ID('analisis'))
BEGIN
    BEGIN TRY
        CREATE NONCLUSTERED INDEX IX_analisis_Factura 
        ON analisis(Factura) 
        INCLUDE (Fecha_de_Creacion, Asignado)
        WITH (ONLINE = OFF, FILLFACTOR = 90);
        
        PRINT 'Índice IX_analisis_Factura creado exitosamente';
    END TRY
    BEGIN CATCH
        PRINT 'No se pudo crear IX_analisis_Factura - Tipo de dato incompatible o columna muy grande';
        PRINT ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'Índice IX_analisis_Factura ya existe';
END
GO

-- ========================================
-- 4. ÍNDICES PARA retenciones
-- ========================================

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_retenciones_Fecha_Creacion' AND object_id = OBJECT_ID('retenciones'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_retenciones_Fecha_Creacion 
    ON retenciones(Fecha_de_Creacion) 
    INCLUDE (Tiket, Asignado, Fecha_de_Despacho, Empresa)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_retenciones_Fecha_Creacion creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_retenciones_Fecha_Creacion ya existe';
END
GO

-- Índice por Asignado (usuario)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_retenciones_Asignado' AND object_id = OBJECT_ID('retenciones'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_retenciones_Asignado 
    ON retenciones(Asignado, Fecha_de_Creacion) 
    INCLUDE (Tiket, Fecha_de_Despacho)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_retenciones_Asignado creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_retenciones_Asignado ya existe';
END
GO

-- ========================================
-- 5. ÍNDICES PARA Factura_Programa_Despacho_MACOR
-- ========================================

-- Índice para No_Factura (para JOINs)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Factura_Programa_No_Factura' AND object_id = OBJECT_ID('Factura_Programa_Despacho_MACOR'))
BEGIN
    BEGIN TRY
        CREATE NONCLUSTERED INDEX IX_Factura_Programa_No_Factura 
        ON Factura_Programa_Despacho_MACOR(No_Factura) 
        INCLUDE (Estado, Camion, Fecha_de_Despacho, Fecha_de_Entregado)
        WITH (ONLINE = OFF, FILLFACTOR = 85);
        
        PRINT 'Índice IX_Factura_Programa_No_Factura creado exitosamente';
    END TRY
    BEGIN CATCH
        PRINT 'No se pudo crear IX_Factura_Programa_No_Factura - Tipo de dato incompatible';
        PRINT ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'Índice IX_Factura_Programa_No_Factura ya existe';
END
GO

-- Índice por Estado
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Factura_Programa_Estado' AND object_id = OBJECT_ID('Factura_Programa_Despacho_MACOR'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Factura_Programa_Estado 
    ON Factura_Programa_Despacho_MACOR(Estado, Fecha_de_Registro) 
    INCLUDE (No_Factura, Camion)
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_Factura_Programa_Estado creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_Factura_Programa_Estado ya existe';
END
GO

-- ========================================
-- 6. ÍNDICES PARA [log] (tabla de tickets)
-- ========================================

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_log_Estatus' AND object_id = OBJECT_ID('[log]'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_log_Estatus 
    ON [log](Estatus) 
    WITH (ONLINE = OFF, FILLFACTOR = 90);
    
    PRINT 'Índice IX_log_Estatus creado exitosamente';
END
ELSE
BEGIN
    PRINT 'Índice IX_log_Estatus ya existe';
END
GO

-- ========================================
-- RESUMEN Y VERIFICACIÓN
-- ========================================

PRINT '';
PRINT '========================================';
PRINT 'RESUMEN DE ÍNDICES CREADOS';
PRINT '========================================';

-- Mostrar todos los índices creados
SELECT 
    OBJECT_NAME(i.object_id) AS TableName,
    i.name AS IndexName,
    i.type_desc AS IndexType,
    CAST(ps.used_page_count * 8.0 / 1024 AS DECIMAL(10,2)) AS SizeMB
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
PRINT 'Script completado. Revisa los mensajes anteriores para verificar que todos los índices se crearon correctamente.';
GO
