<?php
/**
 * Sistema de Caché Simple - MACO Logística
 * Caché basado en archivos para consultas pesadas
 * Compatible con Azure App Service
 */

class CacheManager {
    private $cacheDir;
    private $defaultTTL = 180; // 3 minutos por defecto
    private $isAzure = false;
    
    public function __construct() {
        // Detectar si estamos en Azure App Service
        $this->isAzure = !empty(getenv('WEBSITE_SITE_NAME')) || 
                         !empty($_SERVER['WEBSITE_SITE_NAME']) ||
                         is_dir('D:\\local\\Temp');
        
        if ($this->isAzure) {
            // Azure App Service: usar directorio temporal local
            // D:\local\Temp es persistente durante la vida de la instancia
            $this->cacheDir = 'D:\\local\\Temp\\maco_cache\\';
            $this->defaultTTL = 60; // Reducir TTL en Azure (múltiples instancias)
        } else {
            // Local/XAMPP: usar directorio dentro del proyecto
            $this->cacheDir = __DIR__ . '/../cache/';
        }
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Genera una clave de caché única basada en parámetros
     */
    public function generateKey(string $prefix, array $params): string {
        $key = $prefix . '_' . md5(json_encode($params));
        return preg_replace('/[^a-zA-Z0-9_]/', '', $key);
    }
    
    /**
     * Obtiene un valor del caché
     */
    public function get(string $key) {
        $file = $this->cacheDir . $key . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        
        $data = @unserialize($content);
        if ($data === false || !isset($data['expires']) || !isset($data['value'])) {
            @unlink($file);
            return null;
        }
        
        // Verificar expiración
        if (time() > $data['expires']) {
            @unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Guarda un valor en el caché
     */
    public function set(string $key, $value, int $ttl = null): bool {
        $ttl = $ttl ?? $this->defaultTTL;
        $file = $this->cacheDir . $key . '.cache';
        
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];
        
        return @file_put_contents($file, serialize($data)) !== false;
    }
    
    /**
     * Elimina una entrada del caché
     */
    public function delete(string $key): bool {
        $file = $this->cacheDir . $key . '.cache';
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }
    
    /**
     * Limpia entradas expiradas del caché
     */
    public function cleanup(): int {
        $deleted = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = @unserialize($content);
                if ($data === false || !isset($data['expires']) || time() > $data['expires']) {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
}

/**
 * Obtiene instancia del CacheManager (singleton)
 */
function getCache(): CacheManager {
    static $cache = null;
    if ($cache === null) {
        $cache = new CacheManager();
    }
    return $cache;
}
?>
