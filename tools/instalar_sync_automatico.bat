@echo off
REM =============================================
REM Instalador Automático de Sincronización
REM MACO AppLogística
REM =============================================

chcp 65001 > nul
title MACO - Instalación de Sincronización Automática

color 0A
echo.
echo ========================================
echo   MACO AppLogística
echo   Instalador de Sincronización Automática
echo ========================================
echo.

REM Verificar permisos de administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    color 0C
    echo.
    echo ✗ ERROR: Este script requiere permisos de Administrador
    echo.
    echo Por favor, ejecuta este archivo como Administrador:
    echo 1. Clic derecho en el archivo
    echo 2. Selecciona "Ejecutar como administrador"
    echo.
    pause
    exit /b 1
)

echo ✓ Permisos de administrador verificados
echo.

REM Cambiar al directorio del script
cd /d "%~dp0"

REM =============================================
REM PASO 1: Verificar PHP
REM =============================================

echo [1/5] Verificando instalación de PHP...
echo.

set PHP_PATH=C:\xampp\php\php.exe

if not exist "%PHP_PATH%" (
    color 0E
    echo ⚠ PHP no encontrado en: %PHP_PATH%
    echo.
    set /p PHP_PATH="Ingrese la ruta completa a php.exe: "
)

if not exist "%PHP_PATH%" (
    color 0C
    echo.
    echo ✗ ERROR: PHP no encontrado en la ruta especificada
    echo.
    pause
    exit /b 1
)

echo ✓ PHP encontrado: %PHP_PATH%
echo.

REM =============================================
REM PASO 2: Probar conexión a BD
REM =============================================

echo [2/5] Probando conexión a la base de datos...
echo.

"%PHP_PATH%" -r "echo 'PHP Version: ' . phpversion() . PHP_EOL;"
echo.

"%PHP_PATH%" sync_facturas_cron.php

if %ERRORLEVEL% neq 0 (
    color 0E
    echo.
    echo ⚠ Advertencia: La primera ejecución falló
    echo.
    echo Esto puede ser normal si:
    echo - Es la primera vez que se ejecuta
    echo - La conexión a BD tarda en establecerse
    echo.
    echo ¿Desea continuar de todos modos? (S/N)
    set /p CONTINUAR=
    if /i not "%CONTINUAR%"=="S" (
        echo Instalación cancelada
        pause
        exit /b 1
    )
) else (
    echo ✓ Conexión a BD exitosa
)

echo.

REM =============================================
REM PASO 3: Crear carpeta de logs
REM =============================================

echo [3/5] Creando estructura de carpetas...
echo.

if not exist "logs" (
    mkdir logs
    echo ✓ Carpeta 'logs' creada
) else (
    echo ✓ Carpeta 'logs' ya existe
)

echo.

REM =============================================
REM PASO 4: Crear tarea programada
REM =============================================

echo [4/5] Configurando tarea programada en Windows...
echo.

REM Verificar si la tarea ya existe
schtasks /Query /TN "MACO - Sync Facturas (20 min)" >nul 2>&1

if %ERRORLEVEL% equ 0 (
    echo ⚠ La tarea ya existe. ¿Desea reemplazarla? (S/N)
    set /p REEMPLAZAR=
    if /i "%REEMPLAZAR%"=="S" (
        schtasks /Delete /TN "MACO - Sync Facturas (20 min)" /F
        echo ✓ Tarea anterior eliminada
    ) else (
        echo - Manteniendo tarea existente
        goto SKIP_TASK_CREATION
    )
)

REM Crear la tarea programada
echo Creando tarea programada...

schtasks /Create ^
    /TN "MACO - Sync Facturas (20 min)" ^
    /TR "%~dp0sync_facturas.bat" ^
    /SC MINUTE ^
    /MO 20 ^
    /ST 00:00 ^
    /RU SYSTEM ^
    /RL HIGHEST ^
    /F

if %ERRORLEVEL% equ 0 (
    echo ✓ Tarea programada creada exitosamente
) else (
    color 0C
    echo ✗ Error al crear la tarea programada
    echo.
    pause
    exit /b 1
)

:SKIP_TASK_CREATION

echo.

REM =============================================
REM PASO 5: Verificar instalación
REM =============================================

echo [5/5] Verificando instalación...
echo.

schtasks /Query /TN "MACO - Sync Facturas (20 min)" /FO LIST /V | findstr /C:"Nombre de tarea" /C:"Estado"

if %ERRORLEVEL% equ 0 (
    echo ✓ Tarea programada verificada
) else (
    color 0E
    echo ⚠ Advertencia: No se pudo verificar la tarea
)

echo.

REM =============================================
REM RESUMEN
REM =============================================

color 0A
echo ========================================
echo   ✓ INSTALACIÓN COMPLETADA
echo ========================================
echo.
echo La sincronización automática está configurada:
echo.
echo   • Frecuencia: Cada 20 minutos
echo   • Horario: 24/7 (todos los días)
echo   • Script: %~dp0sync_facturas.bat
echo   • Logs: %~dp0logs\
echo.
echo ========================================
echo   COMANDOS ÚTILES
echo ========================================
echo.
echo Ver estado de la tarea:
echo   schtasks /Query /TN "MACO - Sync Facturas (20 min)" /V
echo.
echo Ejecutar tarea manualmente (para probar):
echo   schtasks /Run /TN "MACO - Sync Facturas (20 min)"
echo.
echo Ver logs en tiempo real:
echo   powershell -Command "Get-Content %~dp0logs\sync_*.log -Tail 50 -Wait"
echo.
echo Deshabilitar sincronización automática:
echo   schtasks /Change /TN "MACO - Sync Facturas (20 min)" /DISABLE
echo.
echo Habilitar sincronización automática:
echo   schtasks /Change /TN "MACO - Sync Facturas (20 min)" /ENABLE
echo.
echo Eliminar sincronización automática:
echo   schtasks /Delete /TN "MACO - Sync Facturas (20 min)" /F
echo.
echo ========================================
echo.
echo ¿Desea ejecutar la tarea ahora para probar? (S/N)
set /p EJECUTAR=

if /i "%EJECUTAR%"=="S" (
    echo.
    echo Ejecutando tarea...
    schtasks /Run /TN "MACO - Sync Facturas (20 min)"
    echo.
    echo Esperando 3 segundos...
    timeout /t 3 /nobreak >nul
    echo.
    echo Ver último log:
    for /f "delims=" %%f in ('dir /b /od "%~dp0logs\sync_*.log" 2^>nul') do set LASTLOG=%%f
    if defined LASTLOG (
        type "%~dp0logs\%LASTLOG%"
    ) else (
        echo No se encontró archivo de log
    )
)

echo.
echo ========================================
echo   Presiona cualquier tecla para salir
echo ========================================
pause >nul
