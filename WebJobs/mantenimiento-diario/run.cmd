@echo off
REM =====================================================
REM WebJob de Mantenimiento Diario - MACO
REM Ejecuta el SP de limpieza de facturas duplicadas
REM =====================================================

echo [%date% %time%] Iniciando mantenimiento diario...

REM Llamar al endpoint de mantenimiento
curl -s -X GET "https://%WEBSITE_HOSTNAME%/Logica/ejecutar_mantenimiento.php?key=%MAINTENANCE_KEY%"

echo.
echo [%date% %time%] Mantenimiento completado.
