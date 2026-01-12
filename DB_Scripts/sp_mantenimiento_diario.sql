-- ================================================================
-- STORED PROCEDURE: Eliminar Facturas Duplicadas
-- Ejecutar cada 24 horas para mantener datos limpios
-- ================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE type = 'P' AND name = 'sp_EliminarFacturasDuplicadas')
    DROP PROCEDURE sp_EliminarFacturasDuplicadas;
GO

CREATE PROCEDURE sp_EliminarFacturasDuplicadas
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @filasEliminadas INT = 0;
    DECLARE @fechaEjecucion DATETIME = GETDATE();
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- CTE para encontrar y numerar los duplicados
        -- Mantiene el registro más antiguo (RowNum = 1)
        WITH DuplicatesCTE AS (
            SELECT
                Factura,
                Fecha,
                ROW_NUMBER() OVER(PARTITION BY Factura ORDER BY Fecha ASC) AS RowNum
            FROM [dbo].[custinvoicejour]
        )
        DELETE FROM DuplicatesCTE
        WHERE RowNum > 1;
        
        SET @filasEliminadas = @@ROWCOUNT;
        
        COMMIT TRANSACTION;
        
        -- Retornar resultado
        SELECT 
            @filasEliminadas AS FilasEliminadas,
            @fechaEjecucion AS FechaEjecucion,
            'OK' AS Estado,
            NULL AS Error;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            0 AS FilasEliminadas,
            @fechaEjecucion AS FechaEjecucion,
            'ERROR' AS Estado,
            ERROR_MESSAGE() AS Error;
    END CATCH
END
GO

-- ================================================================
-- TABLA DE LOG (opcional, para historial de ejecuciones)
-- ================================================================

IF NOT EXISTS (SELECT * FROM sys.objects WHERE type = 'U' AND name = 'log_mantenimiento')
BEGIN
    CREATE TABLE log_mantenimiento (
        id INT IDENTITY(1,1) PRIMARY KEY,
        procedimiento VARCHAR(100),
        filas_afectadas INT,
        fecha_ejecucion DATETIME DEFAULT GETDATE(),
        estado VARCHAR(20),
        mensaje VARCHAR(500)
    );
    
    CREATE INDEX IX_log_mantenimiento_fecha ON log_mantenimiento(fecha_ejecucion);
END
GO

-- ================================================================
-- SP CON LOGGING AUTOMÁTICO
-- ================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE type = 'P' AND name = 'sp_MantenimientoDiario')
    DROP PROCEDURE sp_MantenimientoDiario;
GO

CREATE PROCEDURE sp_MantenimientoDiario
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @filasEliminadas INT = 0;
    DECLARE @estado VARCHAR(20) = 'OK';
    DECLARE @mensaje VARCHAR(500) = NULL;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- 1. Eliminar facturas duplicadas
        WITH DuplicatesCTE AS (
            SELECT
                Factura,
                ROW_NUMBER() OVER(PARTITION BY Factura ORDER BY Fecha ASC) AS RowNum
            FROM [dbo].[custinvoicejour]
        )
        DELETE FROM DuplicatesCTE WHERE RowNum > 1;
        
        SET @filasEliminadas = @@ROWCOUNT;
        SET @mensaje = CAST(@filasEliminadas AS VARCHAR) + ' facturas duplicadas eliminadas';
        
        COMMIT TRANSACTION;
        
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        
        SET @estado = 'ERROR';
        SET @mensaje = ERROR_MESSAGE();
    END CATCH
    
    -- Registrar en log
    INSERT INTO log_mantenimiento (procedimiento, filas_afectadas, estado, mensaje)
    VALUES ('sp_MantenimientoDiario', @filasEliminadas, @estado, @mensaje);
    
    -- Retornar resultado
    SELECT @filasEliminadas AS FilasEliminadas, @estado AS Estado, @mensaje AS Mensaje;
END
GO

PRINT 'Stored Procedures creados exitosamente';
PRINT 'Ejecutar: EXEC sp_MantenimientoDiario';
GO
