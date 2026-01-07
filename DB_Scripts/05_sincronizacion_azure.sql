-- ========================================
-- SINCRONIZACIÓN MANUAL - Azure SQL Database
-- ========================================
-- Azure SQL Database NO soporta SQL Server Agent.
-- Este script proporciona alternativas para ejecutar
-- SyncCustinvoicejour automáticamente.
-- ========================================

PRINT '========================================';
PRINT 'SINCRONIZACIÓN PARA AZURE SQL DATABASE';
PRINT '========================================';
PRINT '';

-- ========================================
-- OPCIÓN 1: Ejecutar Manualmente
-- ========================================
-- Ejecuta este comando cuando necesites sincronizar:

PRINT '1. Ejecutar manualmente:';
PRINT '   EXEC SyncCustinvoicejour;';
PRINT '';

-- ========================================
-- OPCIÓN 2: Azure Logic App (Recomendado)
-- ========================================
PRINT '2. Automatizar con Azure Logic App:';
PRINT '   a) Ve a Azure Portal > Logic Apps > Create';
PRINT '   b) Busca "Recurrence" trigger (programar cada día a las 2 AM)';
PRINT '   c) Agrega acción "SQL Server - Execute stored procedure"';
PRINT '   d) Configura conexión a tu Azure SQL Database';
PRINT '   e) Selecciona el SP: SyncCustinvoicejour';
PRINT '   f) Guarda y activa la Logic App';
PRINT '';

-- ========================================
-- OPCIÓN 3: Azure Automation Runbook
-- ========================================
PRINT '3. Automatizar con Azure Automation:';
PRINT '   a) Crea un Automation Account en Azure Portal';
PRINT '   b) Crea un nuevo Runbook (PowerShell)';
PRINT '   c) Usa el siguiente código PowerShell:';
PRINT '';
PRINT '   ### CÓDIGO POWERSHELL PARA RUNBOOK ###';
PRINT '   $ServerName = "tu-servidor.database.windows.net"';
PRINT '   $DatabaseName = "MACODB"';
PRINT '   $Query = "EXEC SyncCustinvoicejour"';
PRINT '   ';
PRINT '   # Usar Managed Identity o Service Principal';
PRINT '   Invoke-Sqlcmd -ServerInstance $ServerName `';
PRINT '                 -Database $DatabaseName `';
PRINT '                 -Query $Query `';
PRINT '                 -AccessToken (Get-AzAccessToken -ResourceUrl https://database.windows.net).Token';
PRINT '   ### FIN CÓDIGO POWERSHELL ###';
PRINT '';
PRINT '   d) Programa el Runbook en Schedule (2:00 AM diario)';
PRINT '';

-- ========================================
-- OPCIÓN 4: Azure Function (Avanzado)
-- ========================================
PRINT '4. Azure Function con Timer Trigger:';
PRINT '   a) Crea Azure Function App (C# o Python)';
PRINT '   b) Timer Trigger con CRON: "0 0 2 * * *" (2 AM diario)';
PRINT '   c) Conecta a SQL Database usando ADO.NET o SqlClient';
PRINT '   d) Ejecuta: EXEC SyncCustinvoicejour';
PRINT '';

-- ========================================
-- PROCEDIMIENTO DE SINCRONIZACIÓN MANUAL
-- ========================================
PRINT '========================================';
PRINT 'EJECUTAR SINCRONIZACIÓN AHORA (MANUAL)';
PRINT '========================================';
PRINT '';
PRINT 'Ejecutando SyncCustinvoicejour...';
PRINT '';

BEGIN TRY
    EXEC SyncCustinvoicejour;
    PRINT '✓ Sincronización completada exitosamente.';
END TRY
BEGIN CATCH
    PRINT '✗ Error durante la sincronización:';
    PRINT ERROR_MESSAGE();
END CATCH

PRINT '';
PRINT '========================================';
PRINT 'RECOMENDACIÓN FINAL';
PRINT '========================================';
PRINT 'Para Azure SQL Database, se recomienda:';
PRINT '1. Azure Logic App (más fácil, visual)';
PRINT '2. Azure Automation Runbook (más control)';
PRINT '3. Ejecutar manualmente si es poco frecuente';
PRINT '';
GO
