<?php
/**
 * Sistema de Caché Simple basado en archivos
 * MACO Logística
 */

/**
 * Obtiene datos de la caché o ejecuta el callable para obtenerlos
 *
 * @param string $cache_key Identificador único para esta caché
 * @param int $ttl Tiempo de vida en segundos
 * @param callable $callback Función que retorna los datos si no están en caché
 * @return mixed Los datos cacheados o frescos
 */
function get_cached_data($cache_key, $ttl, $callback) {
    $cache_dir = __DIR__ . '/../cache/';

    // Crear directorio si no existe
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $cache_file = $cache_dir . md5($cache_key) . '.cache';

    // Verificar si existe la caché y es válida
    if (file_exists($cache_file)) {
        $cache_data = unserialize(file_get_contents($cache_file));

        // Verificar si la caché no ha expirado
        if (time() - $cache_data['timestamp'] < $ttl) {
            return $cache_data['data'];
        }
    }

    // Si no hay caché válida, ejecutar el callback
    $fresh_data = $callback();

    // Guardar en caché
    $cache_content = serialize([
        'timestamp' => time(),
        'data' => $fresh_data
    ]);

    file_put_contents($cache_file, $cache_content);

    return $fresh_data;
}

/**
 * Limpia toda la caché o una caché específica
 *
 * @param string|null $cache_key Si se proporciona, solo limpia esa caché específica
 * @return bool
 */
function clear_cache($cache_key = null) {
    $cache_dir = __DIR__ . '/../cache/';

    if (!is_dir($cache_dir)) {
        return true;
    }

    if ($cache_key !== null) {
        // Limpiar caché específica
        $cache_file = $cache_dir . md5($cache_key) . '.cache';
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        return true;
    }

    // Limpiar toda la caché
    $files = glob($cache_dir . '*.cache');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    return true;
}
