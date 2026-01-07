-- ========================================
-- JOB PROGRAMADO: Sincronización de Facturas
-- ========================================
-- Este script crea un SQL Server Agent Job para ejecutar
-- el procedimiento SyncCustinvoicejour automáticamente
-- cada noche a las 2:00 AM
--
-- NOTA: Requiere permisos de agente SQL Server
-- ========================================

USE msdb;
GO

-- Eliminar el job si ya existe
IF EXISTS (SELECT 1 FROM msdb.dbo.sysjobs WHERE name = 'MACO_SyncCustinvoicejour_Nightly')
BEGIN
    EXEC msdb.dbo.sp_delete_job @job_name = 'MACO_SyncCustinvoicejour_Nightly';
    PRINT 'Job existente eliminado.';
END
GO

-- Crear el job
EXEC msdb.dbo.sp_add_job
    @job_name = 'MACO_SyncCustinvoicejour_Nightly',
    @enabled = 1,
    @description = 'Sincronización nocturna de custinvoicejour para evitar bloqueos durante el día',
    @category_name = 'Database Maintenance';

-- Agregar el paso del job que ejecuta el stored procedure
EXEC msdb.dbo.sp_add_jobstep
    @job_name = 'MACO_SyncCustinvoicejour_Nightly',
    @step_name = 'Ejecutar SyncCustinvoicejour',
    @subsystem = 'TSQL',
    @database_name = 'MACODB', -- !! REEMPLAZAR con el nombre de tu base de datos !!
    @command = 'EXEC SyncCustinvoicejour;',
    @retry_attempts = 3,
    @retry_interval = 5; -- 5 minutos entre reintentos

-- Crear un schedule para ejecutar todos los días a las 2:00 AM
EXEC msdb.dbo.sp_add_schedule
    @schedule_name = 'Diario_2AM',
    @enabled = 1,
    @freq_type = 4, -- Diario
    @freq_interval = 1, -- Cada 1 día
    @active_start_time = 20000; -- 02:00:00 (formato HHMMSS)

-- Asociar el schedule al job
EXEC msdb.dbo.sp_attach_schedule
    @job_name = 'MACO_SyncCustinvoicejour_Nightly',
    @schedule_name = 'Diario_2AM';

-- Agregar el job al servidor local
EXEC msdb.dbo.sp_add_jobserver
    @job_name = 'MACO_SyncCustinvoicejour_Nightly',
    @server_name = '(local)';

PRINT '';
PRINT '========================================';
PRINT 'Job creado exitosamente!';
PRINT 'Nombre: MACO_SyncCustinvoicejour_Nightly';
PRINT 'Horario: Todos los días a las 2:00 AM';
PRINT '========================================';
PRINT '';

-- Verificar que el job fue creado
SELECT 
    j.name AS JobName,
    j.enabled AS Enabled,
    s.name AS ScheduleName,
    CASE s.freq_type
        WHEN 1 THEN 'Una vez'
        WHEN 4 THEN 'Diario'
        WHEN 8 THEN 'Semanal'
        WHEN 16 THEN 'Mensual'
    END AS Frequency,
    STUFF(STUFF(RIGHT('000000' + CAST(s.active_start_time AS VARCHAR(6)), 6), 5, 0, ':'), 3, 0, ':') AS StartTime
FROM msdb.dbo.sysjobs j
INNER JOIN msdb.dbo.sysjobschedules js ON j.job_id = js.job_id
INNER JOIN msdb.dbo.sysschedules s ON js.schedule_id = s.schedule_id
WHERE j.name = 'MACO_SyncCustinvoicejour_Nightly';

GO
