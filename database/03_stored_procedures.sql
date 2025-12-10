-- =============================================
-- Procedimientos Almacenados de Mantenimiento
-- MACO AppLogística - Base de Datos
-- Fecha: 2025-12-09
-- =============================================

USE [db-apptransportistas-maco];
GO

-- =============================================
-- SP 1: Limpieza Automática de Datos
-- =============================================
PRINT 'Creando procedimiento: sp_LimpiezaAutomatica';
GO

IF OBJECT_ID('sp_LimpiezaAutomatica', 'P') IS NOT NULL
    DROP PROCEDURE sp_LimpiezaAutomatica;
GO

CREATE PROCEDURE sp_LimpiezaAutomatica
    @DiasTickets INT = 180,        -- Días para mantener tickets despachados (default: 6 meses)
    @DiasLogsAcceso INT = 90,      -- Días para mantener logs de acceso (default: 3 meses)
    @ModoDebug BIT = 0             -- 1 = Mostrar información detallada
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @StartTime DATETIME = GETDATE();
    DECLARE @DeletedRows INT;
    DECLARE @TotalDeleted INT = 0;

    IF @ModoDebug = 1
    BEGIN
        PRINT '========================================';
        PRINT 'LIMPIEZA AUTOMÁTICA INICIADA';
        PRINT 'Fecha: ' + CONVERT(VARCHAR(20), @StartTime, 120);
        PRINT '========================================';
        PRINT '';
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Limpiar tickets despachados antiguos
        IF @ModoDebug = 1
            PRINT 'Eliminando tickets despachados con más de ' + CAST(@DiasTickets AS VARCHAR(10)) + ' días...';

        DELETE FROM [log]
        WHERE Estatus IN ('Despachado', 'Se fue')
            AND FechaCreacion < DATEADD(DAY, -@DiasTickets, GETDATE());

        SET @DeletedRows = @@ROWCOUNT;
        SET @TotalDeleted = @TotalDeleted + @DeletedRows;

        IF @ModoDebug = 1
            PRINT '✓ Tickets eliminados: ' + CAST(@DeletedRows AS VARCHAR(10));

        -- Limpiar logs de acceso antiguos
        IF OBJECT_ID('log_accesos') IS NOT NULL
        BEGIN
            IF @ModoDebug = 1
                PRINT 'Eliminando logs de acceso con más de ' + CAST(@DiasLogsAcceso AS VARCHAR(10)) + ' días...';

            DELETE FROM log_accesos
            WHERE fecha_hora < DATEADD(DAY, -@DiasLogsAcceso, GETDATE());

            SET @DeletedRows = @@ROWCOUNT;
            SET @TotalDeleted = @TotalDeleted + @DeletedRows;

            IF @ModoDebug = 1
                PRINT '✓ Logs eliminados: ' + CAST(@DeletedRows AS VARCHAR(10));
        END
        ELSE
        BEGIN
            IF @ModoDebug = 1
                PRINT 'Tabla log_accesos no existe - omitiendo limpieza';
        END

        COMMIT TRANSACTION;

        IF @ModoDebug = 1
        BEGIN
            PRINT '';
            PRINT '✓ LIMPIEZA COMPLETADA EXITOSAMENTE';
            PRINT 'Total de registros eliminados/actualizados: ' + CAST(@TotalDeleted AS VARCHAR(10));
            PRINT 'Duración: ' + CAST(DATEDIFF(SECOND, @StartTime, GETDATE()) AS VARCHAR(10)) + ' segundos';
        END

        RETURN 0; -- Éxito

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        PRINT '✗ ERROR en limpieza automática: ' + @ErrorMessage;

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
        RETURN 1; -- Error

    END CATCH

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento creado: sp_LimpiezaAutomatica';
PRINT '';
GO

-- =============================================
-- SP 2: Optimización Rápida de Índices
-- =============================================
PRINT 'Creando procedimiento: sp_OptimizarIndices';
GO

IF OBJECT_ID('sp_OptimizarIndices', 'P') IS NOT NULL
    DROP PROCEDURE sp_OptimizarIndices;
GO

CREATE PROCEDURE sp_OptimizarIndices
    @UmbralFragmentacion FLOAT = 10.0,  -- % mínimo de fragmentación para reorganizar
    @UmbralReconstruccion FLOAT = 30.0, -- % mínimo de fragmentación para reconstruir
    @PaginasMinimas INT = 1000,         -- Número mínimo de páginas
    @ModoDebug BIT = 0                  -- 1 = Mostrar información detallada
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @StartTime DATETIME = GETDATE();
    DECLARE @TableName NVARCHAR(128);
    DECLARE @IndexName NVARCHAR(128);
    DECLARE @SQL NVARCHAR(MAX);
    DECLARE @FragmentationPercent FLOAT;
    DECLARE @IndexesProcessed INT = 0;

    IF @ModoDebug = 1
    BEGIN
        PRINT '========================================';
        PRINT 'OPTIMIZACIÓN DE ÍNDICES INICIADA';
        PRINT 'Fecha: ' + CONVERT(VARCHAR(20), @StartTime, 120);
        PRINT 'Umbral reorganización: ' + CAST(@UmbralFragmentacion AS VARCHAR(10)) + '%';
        PRINT 'Umbral reconstrucción: ' + CAST(@UmbralReconstruccion AS VARCHAR(10)) + '%';
        PRINT '========================================';
        PRINT '';
    END

    BEGIN TRY
        -- Crear tabla temporal
        IF OBJECT_ID('tempdb..#IndexesToOptimize') IS NOT NULL
            DROP TABLE #IndexesToOptimize;

        CREATE TABLE #IndexesToOptimize (
            TableName NVARCHAR(128),
            IndexName NVARCHAR(128),
            FragmentationPercent FLOAT,
            PageCount BIGINT,
            Action VARCHAR(20)
        );

        -- Identificar índices que necesitan optimización
        INSERT INTO #IndexesToOptimize
        SELECT
            OBJECT_NAME(ips.object_id) AS TableName,
            i.name AS IndexName,
            ips.avg_fragmentation_in_percent AS FragmentationPercent,
            ips.page_count AS PageCount,
            CASE
                WHEN ips.avg_fragmentation_in_percent >= @UmbralReconstruccion THEN 'REBUILD'
                WHEN ips.avg_fragmentation_in_percent >= @UmbralFragmentacion THEN 'REORGANIZE'
                ELSE 'SKIP'
            END AS Action
        FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips
        INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
        WHERE ips.avg_fragmentation_in_percent >= @UmbralFragmentacion
            AND ips.page_count > @PaginasMinimas
            AND i.name IS NOT NULL
            AND OBJECT_NAME(ips.object_id) IN (
                'usuarios', 'custinvoicejour', 'log', 'Facturas_CTE',
                'Facturas_lineas', 'codigos_acceso', 'Factura_Programa_Despacho_MACOR'
            );

        -- Procesar índices
        DECLARE index_cursor CURSOR FOR
        SELECT TableName, IndexName, FragmentationPercent, Action
        FROM #IndexesToOptimize
        WHERE Action <> 'SKIP'
        ORDER BY FragmentationPercent DESC;

        OPEN index_cursor;
        FETCH NEXT FROM index_cursor INTO @TableName, @IndexName, @FragmentationPercent, @SQL;

        WHILE @@FETCH_STATUS = 0
        BEGIN
            IF @SQL = 'REBUILD'
            BEGIN
                SET @SQL = 'ALTER INDEX [' + @IndexName + '] ON [' + @TableName + '] REBUILD WITH (ONLINE = OFF, SORT_IN_TEMPDB = ON)';
                IF @ModoDebug = 1
                    PRINT 'Reconstruyendo: ' + @TableName + '.' + @IndexName + ' (' + CAST(@FragmentationPercent AS VARCHAR(10)) + '%)';
            END
            ELSE
            BEGIN
                SET @SQL = 'ALTER INDEX [' + @IndexName + '] ON [' + @TableName + '] REORGANIZE';
                IF @ModoDebug = 1
                    PRINT 'Reorganizando: ' + @TableName + '.' + @IndexName + ' (' + CAST(@FragmentationPercent AS VARCHAR(10)) + '%)';
            END

            EXEC sp_executesql @SQL;
            SET @IndexesProcessed = @IndexesProcessed + 1;

            FETCH NEXT FROM index_cursor INTO @TableName, @IndexName, @FragmentationPercent, @SQL;
        END

        CLOSE index_cursor;
        DEALLOCATE index_cursor;

        DROP TABLE #IndexesToOptimize;

        IF @ModoDebug = 1
        BEGIN
            PRINT '';
            PRINT '✓ OPTIMIZACIÓN COMPLETADA';
            PRINT 'Índices procesados: ' + CAST(@IndexesProcessed AS VARCHAR(10));
            PRINT 'Duración: ' + CAST(DATEDIFF(SECOND, @StartTime, GETDATE()) AS VARCHAR(10)) + ' segundos';
        END

        RETURN 0; -- Éxito

    END TRY
    BEGIN CATCH
        IF CURSOR_STATUS('global', 'index_cursor') >= 0
        BEGIN
            CLOSE index_cursor;
            DEALLOCATE index_cursor;
        END

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        PRINT '✗ ERROR en optimización: ' + @ErrorMessage;

        RAISERROR(@ErrorMessage, 16, 1);
        RETURN 1; -- Error

    END CATCH

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento creado: sp_OptimizarIndices';
PRINT '';
GO

-- =============================================
-- SP 3: Actualización de Estadísticas
-- =============================================
PRINT 'Creando procedimiento: sp_ActualizarEstadisticas';
GO

IF OBJECT_ID('sp_ActualizarEstadisticas', 'P') IS NOT NULL
    DROP PROCEDURE sp_ActualizarEstadisticas;
GO

CREATE PROCEDURE sp_ActualizarEstadisticas
    @ModoDebug BIT = 0  -- 1 = Mostrar información detallada
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @StartTime DATETIME = GETDATE();
    DECLARE @TableName NVARCHAR(128);
    DECLARE @SQL NVARCHAR(MAX);
    DECLARE @TablesProcessed INT = 0;

    IF @ModoDebug = 1
    BEGIN
        PRINT '========================================';
        PRINT 'ACTUALIZACIÓN DE ESTADÍSTICAS INICIADA';
        PRINT 'Fecha: ' + CONVERT(VARCHAR(20), @StartTime, 120);
        PRINT '========================================';
        PRINT '';
    END

    BEGIN TRY
        DECLARE table_cursor CURSOR FOR
        SELECT name
        FROM sys.tables
        WHERE name IN (
            'usuarios', 'custinvoicejour', 'log', 'Facturas_CTE',
            'Facturas_lineas', 'codigos_acceso', 'Factura_Programa_Despacho_MACOR',
            'inventlocation', 'inventtable', 'listo_inventario'
        );

        OPEN table_cursor;
        FETCH NEXT FROM table_cursor INTO @TableName;

        WHILE @@FETCH_STATUS = 0
        BEGIN
            IF @ModoDebug = 1
                PRINT 'Actualizando: ' + @TableName;

            SET @SQL = 'UPDATE STATISTICS [' + @TableName + '] WITH FULLSCAN';
            EXEC sp_executesql @SQL;

            SET @TablesProcessed = @TablesProcessed + 1;

            FETCH NEXT FROM table_cursor INTO @TableName;
        END

        CLOSE table_cursor;
        DEALLOCATE table_cursor;

        IF @ModoDebug = 1
        BEGIN
            PRINT '';
            PRINT '✓ ACTUALIZACIÓN COMPLETADA';
            PRINT 'Tablas procesadas: ' + CAST(@TablesProcessed AS VARCHAR(10));
            PRINT 'Duración: ' + CAST(DATEDIFF(SECOND, @StartTime, GETDATE()) AS VARCHAR(10)) + ' segundos';
        END

        RETURN 0; -- Éxito

    END TRY
    BEGIN CATCH
        IF CURSOR_STATUS('global', 'table_cursor') >= 0
        BEGIN
            CLOSE table_cursor;
            DEALLOCATE table_cursor;
        END

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        PRINT '✗ ERROR en actualización: ' + @ErrorMessage;

        RAISERROR(@ErrorMessage, 16, 1);
        RETURN 1; -- Error

    END CATCH

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento creado: sp_ActualizarEstadisticas';
PRINT '';
GO

-- =============================================
-- SP 4: Mantenimiento Completo (Ejecuta todo)
-- =============================================
PRINT 'Creando procedimiento: sp_MantenimientoCompleto';
GO

IF OBJECT_ID('sp_MantenimientoCompleto', 'P') IS NOT NULL
    DROP PROCEDURE sp_MantenimientoCompleto;
GO

CREATE PROCEDURE sp_MantenimientoCompleto
    @ModoDebug BIT = 1  -- 1 = Mostrar información detallada
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @StartTime DATETIME = GETDATE();
    DECLARE @Result INT;

    PRINT '========================================';
    PRINT 'MANTENIMIENTO COMPLETO INICIADO';
    PRINT 'Fecha: ' + CONVERT(VARCHAR(20), @StartTime, 120);
    PRINT '========================================';
    PRINT '';

    -- Paso 1: Limpieza automática
    PRINT '→ PASO 1/3: Limpieza automática';
    EXEC @Result = sp_LimpiezaAutomatica @ModoDebug = @ModoDebug;
    IF @Result <> 0
    BEGIN
        PRINT '✗ Error en limpieza automática';
        RETURN 1;
    END
    PRINT '';

    -- Paso 2: Actualizar estadísticas
    PRINT '→ PASO 2/3: Actualización de estadísticas';
    EXEC @Result = sp_ActualizarEstadisticas @ModoDebug = @ModoDebug;
    IF @Result <> 0
    BEGIN
        PRINT '✗ Error en actualización de estadísticas';
        RETURN 1;
    END
    PRINT '';

    -- Paso 3: Optimizar índices
    PRINT '→ PASO 3/3: Optimización de índices';
    EXEC @Result = sp_OptimizarIndices @ModoDebug = @ModoDebug;
    IF @Result <> 0
    BEGIN
        PRINT '✗ Error en optimización de índices';
        RETURN 1;
    END
    PRINT '';

    PRINT '========================================';
    PRINT '✓ MANTENIMIENTO COMPLETO FINALIZADO';
    PRINT 'Duración total: ' + CAST(DATEDIFF(SECOND, @StartTime, GETDATE()) AS VARCHAR(10)) + ' segundos';
    PRINT '========================================';

    RETURN 0;

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento creado: sp_MantenimientoCompleto';
PRINT '';
PRINT '========================================';
PRINT 'TODOS LOS PROCEDIMIENTOS CREADOS';
PRINT '========================================';
GO
