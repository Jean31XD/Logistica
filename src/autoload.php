<?php
/**
 * Autoloader Simple - MACO
 * 
 * Carga automática de clases del proyecto.
 * 
 * @package    MACO
 * @author     MACO Team
 * @version    2.0.0
 */

// Definir la ruta base si no está definida
if (!defined('MACO_BASE_PATH')) {
    define('MACO_BASE_PATH', dirname(__DIR__));
}

/**
 * Autoloader PSR-4 simple para namespaces MACO.
 */
spl_autoload_register(function ($class) {
    $prefix = 'MACO\\';
    $baseDir = MACO_BASE_PATH . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Obtener instancia del servicio de cache.
 * @return \MACO\Services\CacheService
 */
function getCache(): \MACO\Services\CacheService
{
    static $cache = null;
    if ($cache === null) {
        $cache = new \MACO\Services\CacheService();
    }
    return $cache;
}
