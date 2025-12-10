@echo off
REM =============================================
REM Script de Sincronización Automática
REM Ejecuta SyncCustinvoicejour cada 20 minutos
REM MACO AppLogística
REM =============================================
REM
REM CONFIGURACIÓN EN WINDOWS TASK SCHEDULER:
REM 1. Abrir "Programador de tareas"
REM 2. Crear tarea básica
REM 3. Nombre: "MACO - Sync Facturas (20 min)"
REM 4. Desencadenador: Diario, repetir cada 20 minutos durante 24 horas
REM 5. Acción: Iniciar programa
REM 6. Programa: C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\sync_facturas.bat
REM 7. Iniciar en: C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools
REM 8. Ejecutar tanto si el usuario inició sesión como si no
REM 9. Ejecutar con los privilegios más altos
REM =============================================

chcp 65001 > nul

REM Cambiar al directorio del script
cd /d "%~dp0"

REM Ruta al ejecutable de PHP
set PHP_PATH=C:\xampp\php\php.exe

REM Verificar que PHP existe
if not exist "%PHP_PATH%" (
    echo ERROR: PHP no encontrado en %PHP_PATH%
    echo Por favor, ajustar la ruta en el archivo .bat
    exit /b 1
)

REM Ejecutar script PHP
"%PHP_PATH%" sync_facturas_cron.php

REM El código de salida del script PHP se propaga automáticamente
exit /b %ERRORLEVEL%
