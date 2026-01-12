<?php
/**
 * Rate Limiter Global - Sistema de limitación GLOBAL de peticiones
 * Usa archivos para compartir límites entre TODAS las sesiones
 * Protege el servidor de sobrecarga por peticiones simultáneas
 * Compatible con Azure App Service
 */

class GlobalRateLimiter {
    private $cacheDir;
    private $globalLimitsFile;
    private $isAzure = false;
    
    public function __construct() {
        // Detectar si estamos en Azure App Service
        $this->isAzure = !empty(getenv('WEBSITE_SITE_NAME')) || 
                         !empty($_SERVER['WEBSITE_SITE_NAME']) ||
                         is_dir('D:\\local\\Temp');
        
        if ($this->isAzure) {
            // Azure App Service: usar directorio temporal local
            $this->cacheDir = 'D:\\local\\Temp\\maco_rate_limits\\';
        } else {
            // Local/XAMPP: usar directorio dentro del proyecto
            $this->cacheDir = __DIR__ . '/../cache/rate_limits/';
        }
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        $this->globalLimitsFile = $this->cacheDir . 'global_requests.json';
    }
    
    /**
     * Verifica límite GLOBAL de peticiones al servidor (todas las IPs combinadas)
     * Útil para proteger endpoints pesados
     * 
     * @param string $endpoint Nombre del endpoint (ej: 'api_get_data', 'procesar_filtros')
     * @param int $maxRequests Máximo de peticiones totales permitidas
     * @param int $timeWindow Ventana de tiempo en segundos
     * @return bool true si puede continuar, false si hay sobrecarga
     */
    public function checkGlobalLimit($endpoint, $maxRequests = 50, $timeWindow = 10) {
        $file = $this->cacheDir . 'global_' . md5($endpoint) . '.json';
        $now = time();
        $requests = [];
        
        // Leer requests existentes
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $requests = json_decode($content, true) ?: [];
        }
        
        // Filtrar requests dentro de la ventana de tiempo
        $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // Verificar límite
        if (count($requests) >= $maxRequests) {
            error_log("GLOBAL RATE LIMIT: $endpoint - {$maxRequests} requests en {$timeWindow}s");
            return false;
        }
        
        // Registrar esta petición
        $requests[] = $now;
        @file_put_contents($file, json_encode(array_values($requests)), LOCK_EX);
        
        return true;
    }
    
    /**
     * Verifica límite por IP (archivo en lugar de sesión)
     * Funciona incluso sin sesión iniciada
     * 
     * @param string $action Acción que se está limitando
     * @param int $maxAttempts Máximo de intentos permitidos
     * @param int $timeWindow Ventana de tiempo en segundos
     * @param string $identifier IP o identificador único
     * @return bool true si puede continuar, false si excedió
     */
    public function checkIpLimit($action, $maxAttempts = 30, $timeWindow = 60, $identifier = null) {
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        $key = md5($action . '_' . $identifier);
        $file = $this->cacheDir . 'ip_' . $key . '.json';
        $now = time();
        $attempts = [];
        
        // Leer intentos existentes
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $attempts = json_decode($content, true) ?: [];
        }
        
        // Filtrar intentos dentro de la ventana
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // Verificar límite
        if (count($attempts) >= $maxAttempts) {
            error_log("IP RATE LIMIT: $action - IP: $identifier - Intentos: " . count($attempts));
            return false;
        }
        
        // Registrar este intento
        $attempts[] = $now;
        @file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
        
        return true;
    }
    
    /**
     * Limpieza de archivos de rate limit expirados
     * Llamar periódicamente (ej: cada 5 minutos)
     */
    public function cleanup() {
        $files = glob($this->cacheDir . '*.json');
        $deleted = 0;
        $maxAge = 120; // 2 minutos máximo de edad
        
        foreach ($files as $file) {
            if (filemtime($file) < (time() - $maxAge)) {
                if (@unlink($file)) $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Responde con error 429 y sugiere retry
     */
    public static function tooManyRequests($message = null) {
        if ($message === null) {
            $message = 'Servidor sobrecargado. Por favor espere unos segundos.';
        }
        
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: 5');
        
        die(json_encode([
            'error' => $message,
            'code' => 429,
            'retry_after' => 5
        ]));
    }
}

/**
 * Obtiene instancia del GlobalRateLimiter (singleton)
 */
function getGlobalRateLimiter(): GlobalRateLimiter {
    static $limiter = null;
    if ($limiter === null) {
        $limiter = new GlobalRateLimiter();
    }
    return $limiter;
}

/**
 * Verifica límite global de forma rápida (helper)
 * Ejemplo: checkGlobalRateLimit('api_get_data', 30, 5) - max 30 req en 5 segundos
 */
function checkGlobalRateLimit($endpoint, $maxRequests = 30, $timeWindow = 5) {
    return getGlobalRateLimiter()->checkGlobalLimit($endpoint, $maxRequests, $timeWindow);
}
?>
