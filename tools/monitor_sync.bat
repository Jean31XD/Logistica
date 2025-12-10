@echo off
REM =============================================
REM Monitor de Sincronización en Tiempo Real
REM MACO AppLogística
REM =============================================

chcp 65001 > nul
title MACO - Monitor de Sincronización

cd /d "%~dp0"

:MENU
cls
color 0B
echo.
echo ========================================
echo   MACO - Monitor de Sincronización
echo ========================================
echo.
echo [1] Ver logs en tiempo real
echo [2] Ver último log completo
echo [3] Ver estado de la tarea programada
echo [4] Ver estadísticas del día
echo [5] Ejecutar sincronización manual
echo [6] Ver todos los logs disponibles
echo [7] Limpiar logs antiguos (>30 días)
echo [0] Salir
echo.
set /p OPCION="Seleccione una opción: "

if "%OPCION%"=="0" goto FIN
if "%OPCION%"=="1" goto LOGS_TIEMPO_REAL
if "%OPCION%"=="2" goto ULTIMO_LOG
if "%OPCION%"=="3" goto ESTADO_TAREA
if "%OPCION%"=="4" goto ESTADISTICAS
if "%OPCION%"=="5" goto EJECUTAR_MANUAL
if "%OPCION%"=="6" goto TODOS_LOGS
if "%OPCION%"=="7" goto LIMPIAR_LOGS

echo Opción no válida
timeout /t 2 >nul
goto MENU

:LOGS_TIEMPO_REAL
cls
echo ========================================
echo   Logs en Tiempo Real (Ctrl+C para salir)
echo ========================================
echo.
powershell -Command "Get-Content logs\sync_*.log -Tail 50 -Wait 2>$null"
if %ERRORLEVEL% neq 0 (
    echo.
    echo No se encontraron archivos de log
    echo.
    pause
)
goto MENU

:ULTIMO_LOG
cls
echo ========================================
echo   Último Log Completo
echo ========================================
echo.
for /f "delims=" %%f in ('dir /b /od logs\sync_*.log 2^>nul') do set LASTLOG=%%f
if defined LASTLOG (
    echo Archivo: logs\%LASTLOG%
    echo.
    type "logs\%LASTLOG%"
) else (
    echo No se encontraron archivos de log
)
echo.
pause
goto MENU

:ESTADO_TAREA
cls
echo ========================================
echo   Estado de la Tarea Programada
echo ========================================
echo.
schtasks /Query /TN "MACO - Sync Facturas (20 min)" /FO LIST /V 2>nul
if %ERRORLEVEL% neq 0 (
    color 0C
    echo ✗ La tarea programada no existe
    echo.
    echo Para crearla, ejecute: instalar_sync_automatico.bat
) else (
    echo.
    echo ========================================
    echo   Historial de Ejecuciones Recientes
    echo ========================================
    echo.
    schtasks /Query /TN "MACO - Sync Facturas (20 min)" /FO TABLE /V | findstr /C:"MACO" /C:"Hora"
)
echo.
pause
goto MENU

:ESTADISTICAS
cls
echo ========================================
echo   Estadísticas del Día
echo ========================================
echo.

set TODAY=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%
set LOGFILE=logs\sync_%TODAY%.log

if exist "%LOGFILE%" (
    echo Archivo: %LOGFILE%
    echo.

    REM Contar total de ejecuciones
    for /f %%a in ('findstr /C:"Iniciando sincronización" "%LOGFILE%" ^| find /C /V ""') do set TOTAL=%%a

    REM Contar ejecuciones exitosas
    for /f %%a in ('findstr /C:"ejecutado exitosamente" "%LOGFILE%" ^| find /C /V ""') do set EXITO=%%a

    REM Contar errores
    for /f %%a in ('findstr /C:"[ERROR]" "%LOGFILE%" ^| find /C /V ""') do set ERRORES=%%a

    echo Total de ejecuciones: %TOTAL%
    echo Exitosas: %EXITO%
    echo Errores: %ERRORES%
    echo.

    if %ERRORES% gtr 0 (
        color 0E
        echo ⚠ Se detectaron errores. Últimos errores:
        echo.
        findstr /C:"[ERROR]" "%LOGFILE%"
    ) else (
        color 0A
        echo ✓ No se detectaron errores
    )
) else (
    echo No hay log para el día de hoy: %TODAY%
    echo.
    echo Logs disponibles:
    dir /b logs\sync_*.log 2>nul
)

echo.
pause
goto MENU

:EJECUTAR_MANUAL
cls
echo ========================================
echo   Ejecutar Sincronización Manual
echo ========================================
echo.
echo Ejecutando...
echo.

schtasks /Run /TN "MACO - Sync Facturas (20 min)"

if %ERRORLEVEL% equ 0 (
    echo ✓ Tarea iniciada
    echo.
    echo Esperando 5 segundos para que se complete...
    timeout /t 5 /nobreak >nul
    echo.
    echo Resultado:
    for /f "delims=" %%f in ('dir /b /od logs\sync_*.log 2^>nul') do set LASTLOG=%%f
    if defined LASTLOG (
        type "logs\%LASTLOG%" | findstr /C:"[INFO]" /C:"[SUCCESS]" /C:"[ERROR]"
    )
) else (
    color 0C
    echo ✗ Error al ejecutar la tarea
)

echo.
pause
goto MENU

:TODOS_LOGS
cls
echo ========================================
echo   Todos los Logs Disponibles
echo ========================================
echo.

if exist "logs\*.log" (
    for %%f in (logs\sync_*.log) do (
        echo %%~nxf - %%~zf bytes - %%~tf
    )
    echo.
    echo Total de archivos:
    dir /b logs\sync_*.log 2>nul | find /C /V ""
) else (
    echo No se encontraron archivos de log
)

echo.
pause
goto MENU

:LIMPIAR_LOGS
cls
echo ========================================
echo   Limpiar Logs Antiguos
echo ========================================
echo.

echo ⚠ ADVERTENCIA: Se eliminarán logs con más de 30 días
echo.
set /p CONFIRMAR="¿Desea continuar? (S/N): "

if /i not "%CONFIRMAR%"=="S" (
    echo Operación cancelada
    timeout /t 2 >nul
    goto MENU
)

echo.
echo Buscando archivos antiguos...
echo.

REM Usar PowerShell para eliminar archivos antiguos
powershell -Command "Get-ChildItem -Path 'logs\sync_*.log' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item -Force -Verbose"

if %ERRORLEVEL% equ 0 (
    echo.
    echo ✓ Limpieza completada
) else (
    echo.
    echo ⚠ No se encontraron archivos antiguos o ocurrió un error
)

echo.
pause
goto MENU

:FIN
echo.
echo Saliendo...
timeout /t 1 >nul
exit
