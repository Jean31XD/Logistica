<?php
/**
 * Log Manager - Sistema de gestión y rotación de logs
 * Previene que los logs crezcan indefinidamente
 */

/**
 * Escribe un mensaje al log con rotación automática
 *
 * @param string $message Mensaje a registrar
 * @param string $level Nivel del log (INFO, WARNING, ERROR, CRITICAL)
 * @param string $category Categoría del log (SQL, AUTH, SECURITY, etc.)
 */
function logWithRotation($message, $level = 'INFO', $category = 'GENERAL') {
    $logDir = __DIR__ . '/../logs';

    // Crear directorio de logs si no existe
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }

    // Archivo de log actual
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';

    // Formato del mensaje
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $user = $_SESSION['usuario'] ?? 'GUEST';
    $formattedMessage = "[{$timestamp}] [{$level}] [{$category}] [IP:{$ip}] [User:{$user}] {$message}" . PHP_EOL;

    // Escribir al log
    file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);

    // Rotar logs antiguos (ejecutar ocasionalmente)
    if (rand(1, 100) === 1) { // 1% de probabilidad
        rotateLogs($logDir);
    }
}

/**
 * Rota logs antiguos comprimiéndolos y eliminando los muy antiguos
 *
 * @param string $logDir Directorio de logs
 * @param int $keepDays Días a mantener logs sin comprimir (default: 7)
 * @param int $archiveDays Días a mantener logs archivados (default: 30)
 */
function rotateLogs($logDir, $keepDays = 7, $archiveDays = 30) {
    $now = time();
    $files = glob($logDir . '/app_*.log');

    foreach ($files as $file) {
        $fileAge = $now - filemtime($file);
        $ageDays = floor($fileAge / 86400); // 86400 segundos = 1 día

        // Comprimir logs de más de $keepDays días
        if ($ageDays > $keepDays && !file_exists($file . '.gz')) {
            $compressed = gzopen($file . '.gz', 'w9');
            if ($compressed) {
                gzwrite($compressed, file_get_contents($file));
                gzclose($compressed);
                unlink($file); // Eliminar archivo original
            }
        }

        // Eliminar logs comprimidos de más de $archiveDays días
        $gzFile = $file . '.gz';
        if (file_exists($gzFile)) {
            $gzAge = $now - filemtime($gzFile);
            $gzAgeDays = floor($gzAge / 86400);

            if ($gzAgeDays > $archiveDays) {
                unlink($gzFile);
            }
        }
    }
}

/**
 * Obtiene estadísticas de los logs
 *
 * @param string $logDir Directorio de logs
 * @return array Estadísticas de logs
 */
function getLogStats($logDir = null) {
    if ($logDir === null) {
        $logDir = __DIR__ . '/../logs';
    }

    $stats = [
        'total_logs' => 0,
        'total_size' => 0,
        'compressed_logs' => 0,
        'compressed_size' => 0,
        'oldest_log' => null,
        'newest_log' => null
    ];

    if (!is_dir($logDir)) {
        return $stats;
    }

    $files = glob($logDir . '/{app_*.log,app_*.log.gz}', GLOB_BRACE);

    foreach ($files as $file) {
        $size = filesize($file);
        $stats['total_size'] += $size;
        $stats['total_logs']++;

        if (strpos($file, '.gz') !== false) {
            $stats['compressed_logs']++;
            $stats['compressed_size'] += $size;
        }

        $mtime = filemtime($file);
        if ($stats['oldest_log'] === null || $mtime < $stats['oldest_log']) {
            $stats['oldest_log'] = $mtime;
        }
        if ($stats['newest_log'] === null || $mtime > $stats['newest_log']) {
            $stats['newest_log'] = $mtime;
        }
    }

    return $stats;
}

/**
 * Busca en los logs (incluyendo comprimidos)
 *
 * @param string $pattern Patrón a buscar
 * @param int $days Días hacia atrás a buscar
 * @return array Resultados encontrados
 */
function searchLogs($pattern, $days = 7) {
    $logDir = __DIR__ . '/../logs';
    $results = [];
    $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));

    $files = glob($logDir . '/{app_*.log,app_*.log.gz}', GLOB_BRACE);

    foreach ($files as $file) {
        $fileDate = basename($file);
        if (preg_match('/app_(\d{4}-\d{2}-\d{2})\.log/', $fileDate, $matches)) {
            if ($matches[1] < $cutoffDate) {
                continue;
            }
        }

        if (strpos($file, '.gz') !== false) {
            // Buscar en archivo comprimido
            $gz = gzopen($file, 'r');
            if ($gz) {
                while (!gzeof($gz)) {
                    $line = gzgets($gz);
                    if (stripos($line, $pattern) !== false) {
                        $results[] = ['file' => basename($file), 'line' => $line];
                    }
                }
                gzclose($gz);
            }
        } else {
            // Buscar en archivo normal
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (stripos($line, $pattern) !== false) {
                    $results[] = ['file' => basename($file), 'line' => $line];
                }
            }
        }
    }

    return $results;
}
?>
