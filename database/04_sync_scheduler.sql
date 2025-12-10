-- =============================================
-- Sistema de Sincronización Automática
-- Ejecuta SyncCustinvoicejour cada 20 minutos
-- MACO AppLogística
-- Fecha: 2025-12-10
-- =============================================

USE [db-apptransportistas-maco];
GO

PRINT '========================================';
PRINT 'CONFIGURANDO SINCRONIZACIÓN AUTOMÁTICA';
PRINT '========================================';
PRINT '';

-- =============================================
-- OPCIÓN 1: SQL Server Agent Job
-- (Para SQL Server On-Premise o Azure SQL Managed Instance)
-- =============================================

-- Verificar si estamos en Azure SQL Database (no tiene SQL Agent)
IF (SELECT CAST(SERVERPROPERTY('EngineEdition') AS INT)) = 5
BEGIN
    PRINT '⚠ Detectado: Azure SQL Database';
    PRINT 'SQL Server Agent no está disponible.';
    PRINT 'Usar Azure Automation o Elastic Jobs en su lugar.';
    PRINT 'Ver sección de Azure más abajo.';
    PRINT '';
END
ELSE
BEGIN
    PRINT '✓ SQL Server Agent disponible';
    PRINT 'Creando Job para sincronización cada 20 minutos...';
    PRINT '';

    -- Cambiar a base de datos msdb para crear jobs
    USE msdb;
    GO

    -- Eliminar job existente si existe
    IF EXISTS (SELECT job_id FROM msdb.dbo.sysjobs WHERE name = N'MACO - Sync Facturas (20 min)')
    BEGIN
        EXEC msdb.dbo.sp_delete_job @job_name = N'MACO - Sync Facturas (20 min)', @delete_unused_schedule = 1;
        PRINT '✓ Job anterior eliminado';
    END

    -- Crear nuevo job
    DECLARE @jobId BINARY(16);
    EXEC msdb.dbo.sp_add_job
        @job_name = N'MACO - Sync Facturas (20 min)',
        @enabled = 1,
        @description = N'Sincroniza facturas ejecutando SyncCustinvoicejour cada 20 minutos',
        @category_name = N'Database Maintenance',
        @owner_login_name = N'sa',
        @job_id = @jobId OUTPUT;

    PRINT '✓ Job creado';

    -- Agregar paso de ejecución
    EXEC msdb.dbo.sp_add_jobstep
        @job_name = N'MACO - Sync Facturas (20 min)',
        @step_name = N'Ejecutar SyncCustinvoicejour',
        @step_id = 1,
        @subsystem = N'TSQL',
        @database_name = N'db-apptransportistas-maco',
        @command = N'{CALL SyncCustinvoicejour}',
        @retry_attempts = 3,
        @retry_interval = 1,
        @on_success_action = 1, -- Quit with success
        @on_fail_action = 2;    -- Quit with failure

    PRINT '✓ Paso de ejecución agregado';

    -- Crear schedule que se ejecuta cada 20 minutos
    EXEC msdb.dbo.sp_add_schedule
        @schedule_name = N'Cada 20 minutos',
        @enabled = 1,
        @freq_type = 4,              -- Diario
        @freq_interval = 1,          -- Cada día
        @freq_subday_type = 4,       -- Minutos
        @freq_subday_interval = 20,  -- Cada 20 minutos
        @active_start_time = 000000, -- Desde 00:00:00
        @active_end_time = 235959;   -- Hasta 23:59:59

    PRINT '✓ Schedule creado (cada 20 minutos, 24/7)';

    -- Asociar schedule al job
    EXEC msdb.dbo.sp_attach_schedule
        @job_name = N'MACO - Sync Facturas (20 min)',
        @schedule_name = N'Cada 20 minutos';

    PRINT '✓ Schedule asociado al job';

    -- Agregar el job al servidor local
    EXEC msdb.dbo.sp_add_jobserver
        @job_name = N'MACO - Sync Facturas (20 min)',
        @server_name = N'(local)';

    PRINT '✓ Job agregado al servidor';

    -- Volver a la base de datos original
    USE [db-apptransportistas-maco];
    GO

    PRINT '';
    PRINT '========================================';
    PRINT '✓ SINCRONIZACIÓN AUTOMÁTICA CONFIGURADA';
    PRINT 'Frecuencia: Cada 20 minutos';
    PRINT 'Estado: Activo 24/7';
    PRINT '========================================';
END
GO

-- =============================================
-- OPCIÓN 2: Procedimiento Almacenado Helper
-- (Para ejecutar manualmente o desde aplicación)
-- =============================================

PRINT '';
PRINT 'Creando procedimiento helper: sp_SyncFacturas';

IF OBJECT_ID('sp_SyncFacturas', 'P') IS NOT NULL
    DROP PROCEDURE sp_SyncFacturas;
GO

CREATE PROCEDURE sp_SyncFacturas
    @ModoDebug BIT = 0
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @StartTime DATETIME = GETDATE();
    DECLARE @ErrorOccurred BIT = 0;

    IF @ModoDebug = 1
    BEGIN
        PRINT '========================================';
        PRINT 'SINCRONIZACIÓN DE FACTURAS';
        PRINT 'Inicio: ' + CONVERT(VARCHAR(20), @StartTime, 120);
        PRINT '========================================';
    END

    BEGIN TRY
        -- Ejecutar sincronización
        EXEC SyncCustinvoicejour;

        IF @ModoDebug = 1
        BEGIN
            PRINT '✓ Sincronización completada exitosamente';
            PRINT 'Duración: ' + CAST(DATEDIFF(MILLISECOND, @StartTime, GETDATE()) AS VARCHAR(10)) + ' ms';
        END

        RETURN 0; -- Éxito

    END TRY
    BEGIN CATCH
        SET @ErrorOccurred = 1;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        IF @ModoDebug = 1
            PRINT '✗ Error en sincronización: ' + @ErrorMessage;

        -- Log error a tabla si existe
        IF OBJECT_ID('sync_errors_log') IS NOT NULL
        BEGIN
            INSERT INTO sync_errors_log (error_message, error_time)
            VALUES (@ErrorMessage, GETDATE());
        END

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
        RETURN 1; -- Error

    END CATCH

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento sp_SyncFacturas creado';
PRINT '';

-- =============================================
-- VERIFICACIÓN Y COMANDOS ÚTILES
-- =============================================

PRINT '========================================';
PRINT 'COMANDOS ÚTILES';
PRINT '========================================';
PRINT '';
PRINT '-- Ver estado del job:';
PRINT 'SELECT name, enabled, date_created, date_modified';
PRINT 'FROM msdb.dbo.sysjobs';
PRINT 'WHERE name = ''MACO - Sync Facturas (20 min)'';';
PRINT '';
PRINT '-- Ver historial de ejecuciones:';
PRINT 'SELECT TOP 20';
PRINT '    h.run_date,';
PRINT '    h.run_time,';
PRINT '    h.run_duration,';
PRINT '    h.run_status,';
PRINT '    h.message';
PRINT 'FROM msdb.dbo.sysjobhistory h';
PRINT 'INNER JOIN msdb.dbo.sysjobs j ON h.job_id = j.job_id';
PRINT 'WHERE j.name = ''MACO - Sync Facturas (20 min)''';
PRINT 'ORDER BY h.run_date DESC, h.run_time DESC;';
PRINT '';
PRINT '-- Ejecutar manualmente:';
PRINT 'EXEC sp_SyncFacturas @ModoDebug = 1;';
PRINT '';
PRINT '-- Deshabilitar job:';
PRINT 'EXEC msdb.dbo.sp_update_job';
PRINT '    @job_name = ''MACO - Sync Facturas (20 min)'',';
PRINT '    @enabled = 0;';
PRINT '';
PRINT '-- Habilitar job:';
PRINT 'EXEC msdb.dbo.sp_update_job';
PRINT '    @job_name = ''MACO - Sync Facturas (20 min)'',';
PRINT '    @enabled = 1;';
PRINT '';
PRINT '-- Eliminar job:';
PRINT 'EXEC msdb.dbo.sp_delete_job';
PRINT '    @job_name = ''MACO - Sync Facturas (20 min)'';';
PRINT '';
PRINT '========================================';
GO
