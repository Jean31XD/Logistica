-- =============================================
-- Script de Mantenimiento Rutinario
-- MACO AppLogística - Base de Datos
-- Fecha: 2025-12-09
-- Frecuencia Recomendada: Semanal
-- =============================================

USE [db-apptransportistas-maco];
GO

SET NOCOUNT ON;

PRINT '========================================';
PRINT 'INICIANDO MANTENIMIENTO DE BASE DE DATOS';
PRINT 'Fecha: ' + CONVERT(VARCHAR(20), GETDATE(), 120);
PRINT '========================================';
PRINT '';

DECLARE @StartTime DATETIME = GETDATE();
DECLARE @TableName NVARCHAR(128);
DECLARE @SQL NVARCHAR(MAX);
DECLARE @FragmentationPercent FLOAT;

-- =============================================
-- PASO 1: ACTUALIZAR ESTADÍSTICAS
-- =============================================
PRINT '========================================';
PRINT 'PASO 1: ACTUALIZANDO ESTADÍSTICAS';
PRINT '========================================';

BEGIN TRY
    PRINT 'Actualizando estadísticas: usuarios';
    UPDATE STATISTICS usuarios WITH FULLSCAN;
    PRINT '✓ Completado: usuarios';

    PRINT 'Actualizando estadísticas: custinvoicejour';
    UPDATE STATISTICS custinvoicejour WITH FULLSCAN;
    PRINT '✓ Completado: custinvoicejour';

    PRINT 'Actualizando estadísticas: log';
    UPDATE STATISTICS [log] WITH FULLSCAN;
    PRINT '✓ Completado: log';

    PRINT 'Actualizando estadísticas: Facturas_CTE';
    UPDATE STATISTICS Facturas_CTE WITH FULLSCAN;
    PRINT '✓ Completado: Facturas_CTE';

    PRINT 'Actualizando estadísticas: Facturas_lineas';
    UPDATE STATISTICS Facturas_lineas WITH FULLSCAN;
    PRINT '✓ Completado: Facturas_lineas';

    PRINT 'Actualizando estadísticas: codigos_acceso';
    UPDATE STATISTICS codigos_acceso WITH FULLSCAN;
    PRINT '✓ Completado: codigos_acceso';

    PRINT '';
    PRINT '✓ ESTADÍSTICAS ACTUALIZADAS CORRECTAMENTE';
END TRY
BEGIN CATCH
    PRINT '✗ ERROR al actualizar estadísticas: ' + ERROR_MESSAGE();
END CATCH

PRINT '';

-- =============================================
-- PASO 2: REORGANIZAR/RECONSTRUIR ÍNDICES
-- =============================================
PRINT '========================================';
PRINT 'PASO 2: OPTIMIZANDO ÍNDICES';
PRINT '========================================';

BEGIN TRY
    -- Crear tabla temporal para almacenar información de fragmentación
    IF OBJECT_ID('tempdb..#FragmentationInfo') IS NOT NULL
        DROP TABLE #FragmentationInfo;

    CREATE TABLE #FragmentationInfo (
        TableName NVARCHAR(128),
        IndexName NVARCHAR(128),
        FragmentationPercent FLOAT,
        PageCount BIGINT
    );

    -- Obtener información de fragmentación
    INSERT INTO #FragmentationInfo
    SELECT
        OBJECT_NAME(ips.object_id) AS TableName,
        i.name AS IndexName,
        ips.avg_fragmentation_in_percent AS FragmentationPercent,
        ips.page_count AS PageCount
    FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips
    INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
    WHERE ips.avg_fragmentation_in_percent > 5
        AND ips.page_count > 1000
        AND i.name IS NOT NULL
        AND OBJECT_NAME(ips.object_id) IN (
            'usuarios', 'custinvoicejour', 'log', 'Facturas_CTE',
            'Facturas_lineas', 'codigos_acceso'
        );

    -- Procesar índices fragmentados
    DECLARE index_cursor CURSOR FOR
    SELECT TableName, IndexName, FragmentationPercent
    FROM #FragmentationInfo
    ORDER BY FragmentationPercent DESC;

    OPEN index_cursor;
    FETCH NEXT FROM index_cursor INTO @TableName, @SQL, @FragmentationPercent;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        IF @FragmentationPercent >= 30
        BEGIN
            -- Reconstruir índice si fragmentación >= 30%
            SET @SQL = 'ALTER INDEX [' + @SQL + '] ON [' + @TableName + '] REBUILD WITH (ONLINE = OFF, SORT_IN_TEMPDB = ON)';
            PRINT 'Reconstruyendo índice: ' + @SQL + ' (' + CAST(@FragmentationPercent AS VARCHAR(10)) + '% fragmentado)';
            EXEC sp_executesql @SQL;
            PRINT '✓ Reconstruido';
        END
        ELSE IF @FragmentationPercent >= 5
        BEGIN
            -- Reorganizar índice si fragmentación entre 5% y 30%
            SET @SQL = 'ALTER INDEX [' + @SQL + '] ON [' + @TableName + '] REORGANIZE';
            PRINT 'Reorganizando índice: ' + @SQL + ' (' + CAST(@FragmentationPercent AS VARCHAR(10)) + '% fragmentado)';
            EXEC sp_executesql @SQL;
            PRINT '✓ Reorganizado';
        END

        FETCH NEXT FROM index_cursor INTO @TableName, @SQL, @FragmentationPercent;
    END

    CLOSE index_cursor;
    DEALLOCATE index_cursor;

    DROP TABLE #FragmentationInfo;

    PRINT '';
    PRINT '✓ ÍNDICES OPTIMIZADOS CORRECTAMENTE';
END TRY
BEGIN CATCH
    PRINT '✗ ERROR al optimizar índices: ' + ERROR_MESSAGE();
    IF CURSOR_STATUS('global', 'index_cursor') >= 0
    BEGIN
        CLOSE index_cursor;
        DEALLOCATE index_cursor;
    END
END CATCH

PRINT '';

-- =============================================
-- PASO 3: LIMPIAR DATOS OBSOLETOS
-- =============================================
PRINT '========================================';
PRINT 'PASO 3: LIMPIANDO DATOS OBSOLETOS';
PRINT '========================================';

DECLARE @DeletedRows INT;

BEGIN TRY
    -- Limpiar tickets despachados con más de 6 meses
    DELETE FROM [log]
    WHERE Estatus IN ('Despachado', 'Se fue')
        AND FechaCreacion < DATEADD(MONTH, -6, GETDATE());

    SET @DeletedRows = @@ROWCOUNT;
    PRINT 'Tickets eliminados (>6 meses): ' + CAST(@DeletedRows AS VARCHAR(10));

    -- Limpiar logs de acceso antiguos (si existe la tabla)
    IF OBJECT_ID('log_accesos') IS NOT NULL
    BEGIN
        DELETE FROM log_accesos
        WHERE fecha_hora < DATEADD(MONTH, -3, GETDATE());

        SET @DeletedRows = @@ROWCOUNT;
        PRINT 'Logs de acceso eliminados (>3 meses): ' + CAST(@DeletedRows AS VARCHAR(10));
    END
    ELSE
    BEGIN
        PRINT 'Tabla log_accesos no existe - omitiendo limpieza';
    END

    PRINT '';
    PRINT '✓ DATOS OBSOLETOS ELIMINADOS';
END TRY
BEGIN CATCH
    PRINT '✗ ERROR al limpiar datos: ' + ERROR_MESSAGE();
END CATCH

PRINT '';

-- =============================================
-- PASO 4: VERIFICAR INTEGRIDAD
-- =============================================
PRINT '========================================';
PRINT 'PASO 4: VERIFICANDO INTEGRIDAD';
PRINT '========================================';

BEGIN TRY
    DBCC CHECKDB ([db-apptransportistas-maco]) WITH NO_INFOMSGS, ALL_ERRORMSGS;
    PRINT '✓ INTEGRIDAD VERIFICADA CORRECTAMENTE';
END TRY
BEGIN CATCH
    PRINT '✗ ERROR en verificación de integridad: ' + ERROR_MESSAGE();
END CATCH

PRINT '';

-- =============================================
-- PASO 5: REDUCIR ARCHIVOS DE LOG (OPCIONAL)
-- =============================================
PRINT '========================================';
PRINT 'PASO 5: OPTIMIZANDO ARCHIVOS DE LOG';
PRINT '========================================';

BEGIN TRY
    -- Hacer checkpoint
    CHECKPOINT;
    PRINT '✓ Checkpoint ejecutado';

    -- Reducir log (solo si es necesario)
    DECLARE @LogSize INT;
    SELECT @LogSize = size * 8 / 1024 -- Tamaño en MB
    FROM sys.database_files
    WHERE type_desc = 'LOG';

    PRINT 'Tamaño actual del log: ' + CAST(@LogSize AS VARCHAR(10)) + ' MB';

    IF @LogSize > 1024 -- Si el log es mayor a 1GB
    BEGIN
        DBCC SHRINKFILE (2, 512); -- Reducir a 512MB
        PRINT '✓ Archivo de log reducido';
    END
    ELSE
    BEGIN
        PRINT 'No es necesario reducir el log';
    END
END TRY
BEGIN CATCH
    PRINT '✗ ERROR al optimizar log: ' + ERROR_MESSAGE();
END CATCH

PRINT '';

-- =============================================
-- PASO 6: REPORTE DE ESPACIO EN DISCO
-- =============================================
PRINT '========================================';
PRINT 'PASO 6: REPORTE DE ESPACIO EN DISCO';
PRINT '========================================';

SELECT
    name AS 'Tabla',
    SUM(reserved_page_count) * 8.0 / 1024 AS 'Espacio Reservado (MB)',
    SUM(used_page_count) * 8.0 / 1024 AS 'Espacio Usado (MB)',
    (SUM(reserved_page_count) - SUM(used_page_count)) * 8.0 / 1024 AS 'Espacio Libre (MB)'
FROM sys.dm_db_partition_stats ps
INNER JOIN sys.objects o ON ps.object_id = o.object_id
WHERE o.type = 'U'
    AND o.name IN (
        'usuarios', 'custinvoicejour', 'log', 'Facturas_CTE',
        'Facturas_lineas', 'codigos_acceso'
    )
GROUP BY name
ORDER BY SUM(reserved_page_count) DESC;

PRINT '';

-- =============================================
-- RESUMEN FINAL
-- =============================================
DECLARE @EndTime DATETIME = GETDATE();
DECLARE @Duration INT = DATEDIFF(SECOND, @StartTime, @EndTime);

PRINT '========================================';
PRINT 'MANTENIMIENTO COMPLETADO';
PRINT '========================================';
PRINT 'Hora de inicio: ' + CONVERT(VARCHAR(20), @StartTime, 120);
PRINT 'Hora de fin: ' + CONVERT(VARCHAR(20), @EndTime, 120);
PRINT 'Duración: ' + CAST(@Duration AS VARCHAR(10)) + ' segundos';
PRINT '========================================';

SET NOCOUNT OFF;
GO
