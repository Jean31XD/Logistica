<?php
/**
 * Script de Sincronización Automática de Facturas
 * MACO AppLogística
 *
 * Este script ejecuta el SP SyncCustinvoicejour
 * Diseñado para ejecutarse cada 20 minutos vía Task Scheduler
 *
 * Uso desde línea de comandos:
 * php C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\sync_facturas_cron.php
 */

// Configuración de zona horaria
date_default_timezone_set('America/Santo_Domingo');

// Incluir archivo de conexión
require_once __DIR__ . '/../conexionBD/conexion.php';

// Configuración de logs
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/sync_' . date('Y-m-d') . '.log';

/**
 * Función para escribir en el log
 */
function writeLog($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // También imprimir en consola si se ejecuta desde CLI
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

/**
 * Función principal de sincronización
 */
function syncFacturas() {
    global $conn;

    $startTime = microtime(true);

    writeLog('========================================');
    writeLog('Iniciando sincronización de facturas');

    try {
        // Verificar conexión
        if (!$conn || $conn === false) {
            throw new Exception('Error de conexión a la base de datos');
        }

        writeLog('Conexión a BD establecida');

        // Ejecutar procedimiento almacenado
        $sql = "{CALL SyncCustinvoicejour}";
        $stmt = sqlsrv_query($conn, $sql);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $errorMsg = 'Error al ejecutar SyncCustinvoicejour: ' . print_r($errors, true);
            throw new Exception($errorMsg);
        }

        writeLog('✓ SyncCustinvoicejour ejecutado exitosamente', 'SUCCESS');

        // Liberar recursos
        sqlsrv_free_stmt($stmt);

        // Calcular tiempo de ejecución
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        writeLog("Duración: {$duration} ms");
        writeLog('========================================');

        return true;

    } catch (Exception $e) {
        writeLog('✗ ERROR: ' . $e->getMessage(), 'ERROR');
        writeLog('========================================');
        return false;

    } finally {
        // Cerrar conexión
        if (isset($conn) && $conn !== false) {
            sqlsrv_close($conn);
        }
    }
}

// Ejecutar sincronización
$result = syncFacturas();

// Código de salida (0 = éxito, 1 = error)
exit($result ? 0 : 1);
