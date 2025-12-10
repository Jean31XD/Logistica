-- =============================================
-- Sistema de Sincronización Automática
-- Para Azure SQL Database
-- MACO AppLogística
-- Fecha: 2025-12-10
-- =============================================

-- Azure SQL Database no soporta SQL Server Agent
-- Usar Windows Task Scheduler + PHP o Azure Automation

PRINT '========================================';
PRINT 'CONFIGURANDO SINCRONIZACIÓN PARA AZURE SQL';
PRINT '========================================';
PRINT '';

PRINT 'ℹ️ Azure SQL Database detectado';
PRINT '';
PRINT 'Azure SQL Database NO tiene SQL Server Agent.';
PRINT 'Para ejecutar SyncCustinvoicejour cada 20 minutos, use:';
PRINT '';
PRINT 'OPCIÓN 1 (Recomendada): Windows Task Scheduler + PHP';
PRINT '  - Ejecutar: C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\sync_facturas.bat';
PRINT '  - Frecuencia: Cada 20 minutos';
PRINT '  - Ver: tools\README_SYNC.md para instrucciones completas';
PRINT '';
PRINT 'OPCIÓN 2: Azure Automation Runbook';
PRINT '  - Crear Automation Account en Azure Portal';
PRINT '  - Crear runbook PowerShell con script de conexión';
PRINT '  - Programar cada 20 minutos';
PRINT '';
PRINT 'OPCIÓN 3: Azure Logic Apps';
PRINT '  - Crear Logic App con trigger Recurrence (20 min)';
PRINT '  - Action: SQL Server - Execute stored procedure';
PRINT '';

-- =============================================
-- Crear procedimiento helper
-- =============================================

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
        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        IF @ModoDebug = 1
            PRINT '✗ Error en sincronización: ' + @ErrorMessage;

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
        RETURN 1; -- Error

    END CATCH

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento sp_SyncFacturas creado';
PRINT '';

-- =============================================
-- Crear tabla de log de sincronizaciones (opcional)
-- =============================================

PRINT 'Creando tabla de log: sync_execution_log';

IF OBJECT_ID('sync_execution_log', 'U') IS NULL
BEGIN
    CREATE TABLE sync_execution_log (
        id INT IDENTITY(1,1) PRIMARY KEY,
        execution_time DATETIME NOT NULL DEFAULT GETDATE(),
        status VARCHAR(20) NOT NULL,
        duration_ms INT NULL,
        error_message NVARCHAR(MAX) NULL,
        records_processed INT NULL
    );

    CREATE INDEX IX_sync_execution_log_time
    ON sync_execution_log(execution_time DESC);

    PRINT '✓ Tabla sync_execution_log creada';
END
ELSE
    PRINT '- Tabla sync_execution_log ya existe';

PRINT '';

-- =============================================
-- Procedimiento con logging mejorado
-- =============================================

PRINT 'Creando procedimiento con logging: sp_SyncFacturasWithLog';

IF OBJECT_ID('sp_SyncFacturasWithLog', 'P') IS NOT NULL
    DROP PROCEDURE sp_SyncFacturasWithLog;
GO

CREATE PROCEDURE sp_SyncFacturasWithLog
    @ModoDebug BIT = 0
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @StartTime DATETIME = GETDATE();
    DECLARE @EndTime DATETIME;
    DECLARE @Duration INT;
    DECLARE @Status VARCHAR(20);
    DECLARE @ErrorMsg NVARCHAR(MAX) = NULL;

    IF @ModoDebug = 1
    BEGIN
        PRINT '========================================';
        PRINT 'SINCRONIZACIÓN DE FACTURAS CON LOG';
        PRINT 'Inicio: ' + CONVERT(VARCHAR(20), @StartTime, 120);
        PRINT '========================================';
    END

    BEGIN TRY
        -- Ejecutar sincronización
        EXEC SyncCustinvoicejour;

        SET @EndTime = GETDATE();
        SET @Duration = DATEDIFF(MILLISECOND, @StartTime, @EndTime);
        SET @Status = 'SUCCESS';

        IF @ModoDebug = 1
        BEGIN
            PRINT '✓ Sincronización completada exitosamente';
            PRINT 'Duración: ' + CAST(@Duration AS VARCHAR(10)) + ' ms';
        END

    END TRY
    BEGIN CATCH
        SET @EndTime = GETDATE();
        SET @Duration = DATEDIFF(MILLISECOND, @StartTime, @EndTime);
        SET @Status = 'ERROR';
        SET @ErrorMsg = ERROR_MESSAGE();

        IF @ModoDebug = 1
            PRINT '✗ Error en sincronización: ' + @ErrorMsg;

    END CATCH

    -- Registrar en log
    BEGIN TRY
        INSERT INTO sync_execution_log (execution_time, status, duration_ms, error_message)
        VALUES (@StartTime, @Status, @Duration, @ErrorMsg);

        IF @ModoDebug = 1
            PRINT '✓ Registro guardado en log';

    END TRY
    BEGIN CATCH
        IF @ModoDebug = 1
            PRINT '⚠ Advertencia: No se pudo guardar en log: ' + ERROR_MESSAGE();
    END CATCH

    -- Limpiar logs antiguos (mantener solo 30 días)
    BEGIN TRY
        DELETE FROM sync_execution_log
        WHERE execution_time < DATEADD(DAY, -30, GETDATE());

        IF @ModoDebug = 1 AND @@ROWCOUNT > 0
            PRINT '✓ Logs antiguos eliminados: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    END TRY
    BEGIN CATCH
        IF @ModoDebug = 1
            PRINT '⚠ Advertencia: No se pudieron limpiar logs antiguos';
    END CATCH

    IF @Status = 'ERROR'
    BEGIN
        RAISERROR(@ErrorMsg, 16, 1);
        RETURN 1;
    END

    RETURN 0;

    SET NOCOUNT OFF;
END
GO

PRINT '✓ Procedimiento sp_SyncFacturasWithLog creado';
PRINT '';

-- =============================================
-- Comandos útiles
-- =============================================

PRINT '========================================';
PRINT 'COMANDOS ÚTILES';
PRINT '========================================';
PRINT '';
PRINT '-- Ejecutar sincronización manualmente:';
PRINT 'EXEC sp_SyncFacturasWithLog @ModoDebug = 1;';
PRINT '';
PRINT '-- Ver historial de sincronizaciones:';
PRINT 'SELECT TOP 50';
PRINT '    id,';
PRINT '    execution_time AS Fecha_Hora,';
PRINT '    status AS Estado,';
PRINT '    duration_ms AS Duracion_MS,';
PRINT '    error_message AS Error';
PRINT 'FROM sync_execution_log';
PRINT 'ORDER BY execution_time DESC;';
PRINT '';
PRINT '-- Ver estadísticas del día:';
PRINT 'SELECT';
PRINT '    CAST(execution_time AS DATE) AS Fecha,';
PRINT '    COUNT(*) AS Total_Ejecuciones,';
PRINT '    SUM(CASE WHEN status = ''SUCCESS'' THEN 1 ELSE 0 END) AS Exitosas,';
PRINT '    SUM(CASE WHEN status = ''ERROR'' THEN 1 ELSE 0 END) AS Errores,';
PRINT '    AVG(duration_ms) AS Duracion_Promedio_MS,';
PRINT '    MAX(duration_ms) AS Duracion_Maxima_MS';
PRINT 'FROM sync_execution_log';
PRINT 'WHERE execution_time >= CAST(GETDATE() AS DATE)';
PRINT 'GROUP BY CAST(execution_time AS DATE);';
PRINT '';
PRINT '-- Limpiar logs manualmente:';
PRINT 'DELETE FROM sync_execution_log';
PRINT 'WHERE execution_time < DATEADD(DAY, -30, GETDATE());';
PRINT '';

PRINT '========================================';
PRINT '✓ CONFIGURACIÓN COMPLETADA';
PRINT '========================================';
PRINT '';
PRINT 'PRÓXIMOS PASOS:';
PRINT '1. Configurar Windows Task Scheduler';
PRINT '2. Ver: tools\README_SYNC.md para instrucciones';
PRINT '3. Ejecutar: tools\sync_facturas.bat cada 20 minutos';
PRINT '';
GO
