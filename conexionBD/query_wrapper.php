<?php
/**
 * Query Wrapper - Protección automática contra queries colgadas
 * Establece timeouts y límites para cada consulta SQL
 */

/**
 * Ejecuta una consulta SQL con protección de timeout
 * 
 * @param resource $conn Conexión a BD
 * @param string $sql Query SQL
 * @param array $params Parámetros (opcional)
 * @param int $timeoutMs Timeout en milisegundos (default: 30000 = 30s)
 * @return mixed Statement o false en caso de error
 */
function ejecutarQuerySeguro($conn, $sql, $params = [], $timeoutMs = 30000) {
    // Establecer timeout de bloqueo para esta query
    $timeoutQuery = "SET LOCK_TIMEOUT " . intval($timeoutMs);
    @sqlsrv_query($conn, $timeoutQuery);
    
    // Ejecutar query principal
    $options = array(
        "Scrollable" => SQLSRV_CURSOR_FORWARD,
        "QueryTimeout" => intval($timeoutMs / 1000) // En segundos
    );
    
    $stmt = @sqlsrv_query($conn, $sql, $params, $options);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        // Log del error (sin exponer al usuario)
        error_log("Query Error: " . print_r($errors, true));
        error_log("SQL: " . substr($sql, 0, 500)); // Solo primeros 500 chars
    }
    
    return $stmt;
}

/**
 * Ejecuta una consulta con caché opcional
 * 
 * @param resource $conn Conexión a BD
 * @param string $sql Query SQL
 * @param array $params Parámetros
 * @param string $cacheKey Clave de caché única
 * @param int $cacheTTL Tiempo de vida del caché en segundos (0 = sin caché)
 * @return array Resultados
 */
function ejecutarQueryConCache($conn, $sql, $params = [], $cacheKey = '', $cacheTTL = 0) {
    // Si hay caché habilitado, intentar obtener de caché
    if ($cacheTTL > 0 && !empty($cacheKey)) {
        $cacheDir = sys_get_temp_dir() . '/maco_cache/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . md5($cacheKey) . '.json';
        
        // Verificar si existe caché válido
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            if ($cacheData && isset($cacheData['expires']) && $cacheData['expires'] > time()) {
                return $cacheData['data'];
            }
        }
    }
    
    // Ejecutar query
    $stmt = ejecutarQuerySeguro($conn, $sql, $params);
    if ($stmt === false) {
        return [];
    }
    
    // Obtener resultados
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    // Guardar en caché si está habilitado
    if ($cacheTTL > 0 && !empty($cacheKey)) {
        $cacheData = [
            'expires' => time() + $cacheTTL,
            'data' => $results
        ];
        @file_put_contents($cacheFile, json_encode($cacheData));
    }
    
    return $results;
}

/**
 * Limpia el caché expirado
 */
function limpiarCacheExpirado() {
    $cacheDir = sys_get_temp_dir() . '/maco_cache/';
    if (!is_dir($cacheDir)) return;
    
    $files = glob($cacheDir . '*.json');
    $now = time();
    
    foreach ($files as $file) {
        $cacheData = @json_decode(file_get_contents($file), true);
        if (!$cacheData || !isset($cacheData['expires']) || $cacheData['expires'] < $now) {
            @unlink($file);
        }
    }
}

/**
 * Invalida una clave de caché específica
 */
function invalidarCache($cacheKey) {
    $cacheDir = sys_get_temp_dir() . '/maco_cache/';
    $cacheFile = $cacheDir . md5($cacheKey) . '.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}
?>
