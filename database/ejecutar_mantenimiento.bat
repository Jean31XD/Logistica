@echo off
REM =============================================
REM Script de Ejecución de Mantenimiento
REM MACO AppLogística
REM =============================================

chcp 65001 > nul
title MACO - Mantenimiento de Base de Datos

echo.
echo ========================================
echo   MACO AppLogística
echo   Mantenimiento de Base de Datos
echo ========================================
echo.

REM Configuración de la conexión
set SERVER=sdb-apptransportistas-maco.privatelink.database.windows.net
set DATABASE=db-apptransportistas-maco
set USERNAME=ServiceAppTrans
set PASSWORD=nZ(#n41LJm)iLmJP

REM Crear carpeta de logs si no existe
if not exist "logs" mkdir logs

REM Nombre del archivo de log con fecha y hora
set FECHA=%date:~-4,4%%date:~-10,2%%date:~-7,2%
set HORA=%time:~0,2%%time:~3,2%%time:~6,2%
set HORA=%HORA: =0%
set LOGFILE=logs\mantenimiento_%FECHA%_%HORA%.log

echo Inicio: %date% %time% > %LOGFILE%
echo. >> %LOGFILE%

:MENU
echo.
echo Seleccione el tipo de mantenimiento:
echo.
echo [1] Mantenimiento Completo (Recomendado - 10-15 min)
echo [2] Solo Limpieza de Datos (Rápido - 1-2 min)
echo [3] Solo Optimizar Índices (Medio - 5-10 min)
echo [4] Solo Actualizar Estadísticas (Rápido - 2-3 min)
echo [5] Crear/Actualizar Índices (Primera vez)
echo [6] Crear Procedimientos Almacenados (Primera vez)
echo [7] Ver Fragmentación de Índices
echo [8] Ver Uso de Espacio
echo [0] Salir
echo.

set /p OPCION="Ingrese su opción: "

if "%OPCION%"=="0" goto FIN
if "%OPCION%"=="1" goto COMPLETO
if "%OPCION%"=="2" goto LIMPIEZA
if "%OPCION%"=="3" goto INDICES
if "%OPCION%"=="4" goto ESTADISTICAS
if "%OPCION%"=="5" goto CREAR_INDICES
if "%OPCION%"=="6" goto CREAR_SP
if "%OPCION%"=="7" goto VER_FRAGMENTACION
if "%OPCION%"=="8" goto VER_ESPACIO

echo.
echo Opción no válida. Intente nuevamente.
timeout /t 2 > nul
goto MENU

:COMPLETO
echo.
echo ========================================
echo Ejecutando Mantenimiento Completo...
echo ========================================
echo.
echo Ejecutando: Mantenimiento Completo >> %LOGFILE%
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "EXEC sp_MantenimientoCompleto @ModoDebug = 1;" -o %LOGFILE%
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Mantenimiento completado exitosamente
    echo ✓ Mantenimiento completado >> %LOGFILE%
) else (
    echo.
    echo ✗ Error al ejecutar mantenimiento
    echo ✗ Error al ejecutar mantenimiento >> %LOGFILE%
)
goto MOSTRAR_LOG

:LIMPIEZA
echo.
echo ========================================
echo Ejecutando Limpieza de Datos...
echo ========================================
echo.
echo Ejecutando: Limpieza de Datos >> %LOGFILE%
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "EXEC sp_LimpiezaAutomatica @DiasTickets = 180, @DiasLogsAcceso = 90, @ModoDebug = 1;" -o %LOGFILE%
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Limpieza completada exitosamente
    echo ✓ Limpieza completada >> %LOGFILE%
) else (
    echo.
    echo ✗ Error al ejecutar limpieza
    echo ✗ Error al ejecutar limpieza >> %LOGFILE%
)
goto MOSTRAR_LOG

:INDICES
echo.
echo ========================================
echo Ejecutando Optimización de Índices...
echo ========================================
echo.
echo Ejecutando: Optimización de Índices >> %LOGFILE%
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "EXEC sp_OptimizarIndices @UmbralFragmentacion = 10.0, @UmbralReconstruccion = 30.0, @ModoDebug = 1;" -o %LOGFILE%
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Optimización completada exitosamente
    echo ✓ Optimización completada >> %LOGFILE%
) else (
    echo.
    echo ✗ Error al optimizar índices
    echo ✗ Error al optimizar índices >> %LOGFILE%
)
goto MOSTRAR_LOG

:ESTADISTICAS
echo.
echo ========================================
echo Actualizando Estadísticas...
echo ========================================
echo.
echo Ejecutando: Actualización de Estadísticas >> %LOGFILE%
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "EXEC sp_ActualizarEstadisticas @ModoDebug = 1;" -o %LOGFILE%
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Estadísticas actualizadas exitosamente
    echo ✓ Estadísticas actualizadas >> %LOGFILE%
) else (
    echo.
    echo ✗ Error al actualizar estadísticas
    echo ✗ Error al actualizar estadísticas >> %LOGFILE%
)
goto MOSTRAR_LOG

:CREAR_INDICES
echo.
echo ========================================
echo Creando Índices Optimizados...
echo ========================================
echo.
echo ADVERTENCIA: Este proceso puede tardar varios minutos.
echo ¿Desea continuar? (S/N)
set /p CONFIRMAR=
if /i not "%CONFIRMAR%"=="S" goto MENU

echo Ejecutando: Creación de Índices >> %LOGFILE%
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -i "01_create_indexes.sql" -o %LOGFILE%
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Índices creados exitosamente
    echo ✓ Índices creados >> %LOGFILE%
) else (
    echo.
    echo ✗ Error al crear índices
    echo ✗ Error al crear índices >> %LOGFILE%
)
goto MOSTRAR_LOG

:CREAR_SP
echo.
echo ========================================
echo Creando Procedimientos Almacenados...
echo ========================================
echo.
echo Ejecutando: Creación de Procedimientos >> %LOGFILE%
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -i "03_stored_procedures.sql" -o %LOGFILE%
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Procedimientos creados exitosamente
    echo ✓ Procedimientos creados >> %LOGFILE%
) else (
    echo.
    echo ✗ Error al crear procedimientos
    echo ✗ Error al crear procedimientos >> %LOGFILE%
)
goto MOSTRAR_LOG

:VER_FRAGMENTACION
echo.
echo ========================================
echo Consultando Fragmentación de Índices...
echo ========================================
echo.
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "SELECT OBJECT_NAME(ips.object_id) AS Tabla, i.name AS Indice, ips.avg_fragmentation_in_percent AS Fragmentacion, ips.page_count AS Paginas, CASE WHEN ips.avg_fragmentation_in_percent >= 30 THEN 'REBUILD necesario' WHEN ips.avg_fragmentation_in_percent >= 10 THEN 'REORGANIZE recomendado' ELSE 'Óptimo' END AS Estado FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id WHERE OBJECT_NAME(ips.object_id) IN ('usuarios', 'custinvoicejour', 'log', 'Facturas_CTE', 'Facturas_lineas', 'codigos_acceso') AND i.name IS NOT NULL ORDER BY ips.avg_fragmentation_in_percent DESC;"
echo.
pause
goto MENU

:VER_ESPACIO
echo.
echo ========================================
echo Consultando Uso de Espacio...
echo ========================================
echo.
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "SELECT t.name AS Tabla, SUM(p.rows) AS Total_Filas, SUM(a.total_pages) * 8 / 1024 AS Espacio_Total_MB, SUM(a.used_pages) * 8 / 1024 AS Espacio_Usado_MB FROM sys.tables t INNER JOIN sys.indexes i ON t.object_id = i.object_id INNER JOIN sys.partitions p ON i.object_id = p.object_id AND i.index_id = p.index_id INNER JOIN sys.allocation_units a ON p.partition_id = a.container_id WHERE t.name IN ('usuarios', 'custinvoicejour', 'log', 'Facturas_CTE', 'Facturas_lineas', 'codigos_acceso') GROUP BY t.name ORDER BY SUM(a.total_pages) DESC;"
echo.
pause
goto MENU

:MOSTRAR_LOG
echo.
echo ========================================
echo Fin: %date% %time% >> %LOGFILE%
echo.
echo Log guardado en: %LOGFILE%
echo.
echo ¿Desea ver el log completo? (S/N)
set /p VER_LOG=
if /i "%VER_LOG%"=="S" (
    type %LOGFILE%
    echo.
)
pause
goto MENU

:FIN
echo.
echo Saliendo...
echo.
timeout /t 1 > nul
exit
