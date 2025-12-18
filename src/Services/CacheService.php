<?php
/**
 * CacheService - Servicio de Cache
 * 
 * Proporciona caching en memoria y archivos para queries frecuentes.
 * 
 * @package    MACO\Services
 * @author     MACO Team
 * @version    1.0.0
 */

namespace MACO\Services;

class CacheService
{
    /** @var string Directorio de cache */
    private $cacheDir;
    
    /** @var int TTL por defecto en segundos (5 minutos) */
    private $defaultTTL = 300;
    
    /** @var array Cache en memoria para la petición actual */
    private static $memoryCache = [];

    /**
     * Constructor.
     * 
     * @param string|null $cacheDir Directorio de cache (opcional)
     */
    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? __DIR__ . '/../../cache';
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Obtiene un valor del cache.
     * 
     * @param string $key Clave del cache
     * @return mixed|null Valor cacheado o null si no existe/expirado
     */
    public function get(string $key)
    {
        $hashedKey = $this->hashKey($key);
        
        // Primero intentar cache en memoria
        if (isset(self::$memoryCache[$hashedKey])) {
            $item = self::$memoryCache[$hashedKey];
            if (time() < $item['expires']) {
                return $item['data'];
            }
            unset(self::$memoryCache[$hashedKey]);
        }
        
        // Luego intentar cache en archivo
        $file = $this->getCacheFile($hashedKey);
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $item = @unserialize($content);
                if ($item && time() < $item['expires']) {
                    // Guardar en memoria para futuras consultas
                    self::$memoryCache[$hashedKey] = $item;
                    return $item['data'];
                }
                // Cache expirado, eliminar archivo
                @unlink($file);
            }
        }
        
        return null;
    }

    /**
     * Guarda un valor en el cache.
     * 
     * @param string $key Clave del cache
     * @param mixed $data Datos a cachear
     * @param int|null $ttl TTL en segundos (null = usar default)
     * @return bool True si se guardó correctamente
     */
    public function set(string $key, $data, ?int $ttl = null): bool
    {
        $hashedKey = $this->hashKey($key);
        $expires = time() + ($ttl ?? $this->defaultTTL);
        
        $item = [
            'data' => $data,
            'expires' => $expires,
            'created' => time()
        ];
        
        // Guardar en memoria
        self::$memoryCache[$hashedKey] = $item;
        
        // Guardar en archivo
        $file = $this->getCacheFile($hashedKey);
        return @file_put_contents($file, serialize($item)) !== false;
    }

    /**
     * Elimina un valor del cache.
     * 
     * @param string $key Clave del cache
     * @return bool True si se eliminó
     */
    public function delete(string $key): bool
    {
        $hashedKey = $this->hashKey($key);
        
        // Eliminar de memoria
        unset(self::$memoryCache[$hashedKey]);
        
        // Eliminar archivo
        $file = $this->getCacheFile($hashedKey);
        if (file_exists($file)) {
            return @unlink($file);
        }
        
        return true;
    }

    /**
     * Limpia todo el cache.
     * 
     * @return int Número de archivos eliminados
     */
    public function clear(): int
    {
        self::$memoryCache = [];
        
        $count = 0;
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Limpia cache expirado.
     * 
     * @return int Número de archivos eliminados
     */
    public function clearExpired(): int
    {
        $count = 0;
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $item = @unserialize($content);
                if (!$item || time() >= $item['expires']) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }

    /**
     * Obtiene o calcula un valor (cache-aside pattern).
     * 
     * @param string $key Clave del cache
     * @param callable $callback Función para calcular el valor
     * @param int|null $ttl TTL en segundos
     * @return mixed Valor cacheado o calculado
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }

    /**
     * Genera un hash de la clave.
     * 
     * @param string $key Clave original
     * @return string Hash MD5
     */
    private function hashKey(string $key): string
    {
        return md5($key);
    }

    /**
     * Obtiene la ruta del archivo de cache.
     * 
     * @param string $hashedKey Clave hasheada
     * @return string Ruta completa del archivo
     */
    private function getCacheFile(string $hashedKey): string
    {
        return $this->cacheDir . '/' . $hashedKey . '.cache';
    }
}
