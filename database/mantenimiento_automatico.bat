@echo off
REM =============================================
REM Script de Mantenimiento Automático (Task Scheduler)
REM MACO AppLogística
REM =============================================
REM
REM Para usar con el Programador de Tareas de Windows:
REM 1. Abrir Programador de Tareas
REM 2. Crear Tarea Básica
REM 3. Nombre: "MACO - Mantenimiento DB Semanal"
REM 4. Desencadenador: Semanal, Domingo, 2:00 AM
REM 5. Acción: Iniciar programa
REM 6. Programa: C:\xampp\htdocs\MACO.AppLogistica.Web-1\database\mantenimiento_automatico.bat
REM 7. Ejecutar con los privilegios más altos
REM =============================================

chcp 65001 > nul

REM Configuración
set SERVER=sdb-apptransportistas-maco.privatelink.database.windows.net
set DATABASE=db-apptransportistas-maco
set USERNAME=ServiceAppTrans
set PASSWORD=nZ(#n41LJm)iLmJP

REM Cambiar al directorio del script
cd /d "%~dp0"

REM Crear carpeta de logs si no existe
if not exist "logs" mkdir logs

REM Archivo de log con fecha
set FECHA=%date:~-4,4%%date:~-10,2%%date:~-7,2%
set LOGFILE=logs\auto_%FECHA%.log

echo ======================================== >> %LOGFILE%
echo MANTENIMIENTO AUTOMÁTICO >> %LOGFILE%
echo Inicio: %date% %time% >> %LOGFILE%
echo ======================================== >> %LOGFILE%
echo. >> %LOGFILE%

REM Ejecutar mantenimiento completo
sqlcmd -S %SERVER% -d %DATABASE% -U %USERNAME% -P %PASSWORD% -Q "EXEC sp_MantenimientoCompleto @ModoDebug = 0;" >> %LOGFILE% 2>&1

if %ERRORLEVEL% EQU 0 (
    echo. >> %LOGFILE%
    echo ✓ MANTENIMIENTO COMPLETADO EXITOSAMENTE >> %LOGFILE%
    echo Fin: %date% %time% >> %LOGFILE%
    exit /b 0
) else (
    echo. >> %LOGFILE%
    echo ✗ ERROR AL EJECUTAR MANTENIMIENTO >> %LOGFILE%
    echo Código de error: %ERRORLEVEL% >> %LOGFILE%
    echo Fin: %date% %time% >> %LOGFILE%

    REM Enviar email de notificación (opcional - requiere configurar blat o similar)
    REM blat %LOGFILE% -to admin@maco.com -subject "Error en Mantenimiento DB MACO"

    exit /b 1
)
