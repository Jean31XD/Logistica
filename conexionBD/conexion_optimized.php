<?php
/**
 * Conexión Optimizada a Base de Datos
 * - Singleton pattern (una sola conexión)
 * - Lazy connection (solo cuando se necesita)
 * - Cache de resultados
 */

class DatabaseOptimized {
    private static $instance = null;
    private $conn = null;
    private $queryCache = [];
    private $cacheEnabled = true;
    private $cacheTTL = 60; // 60 segundos

    private function __construct() {
        // Constructor privado para Singleton
    }

    /**
     * Obtiene instancia única de la BD
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtiene conexión (lazy loading)
     */
    public function getConnection() {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    /**
     * Conecta a la BD
     */
    private function connect() {
        $connectionInfo = array(
            "Database" => DB_NAME,
            "UID" => DB_USER,
            "PWD" => DB_PASS,
            "TrustServerCertificate" => true,
            "CharacterSet" => "UTF-8",
            "ReturnDatesAsStrings" => true,
            "Encrypt" => true,
            // Opciones de optimización
            "ConnectionPooling" => true,
            "MultipleActiveResultSets" => false
        );

        $this->conn = sqlsrv_connect(DB_SERVER, $connectionInfo);

        if ($this->conn === false) {
            $error_details = print_r(sqlsrv_errors(), true);
            error_log("Error de conexión a BD: " . $error_details);

            if (APP_ENV === 'production') {
                die("<div style='padding:20px;background:#f8d7da;color:#721c24;'>Error de conexión. Contacte al administrador.</div>");
            } else {
                die("<div style='padding:20px;background:#f8d7da;color:#721c24;'><strong>Error BD:</strong><pre>" . htmlspecialchars($error_details) . "</pre></div>");
            }
        }

        // Establecer en global para compatibilidad
        $GLOBALS['conn'] = $this->conn;
    }

    /**
     * Ejecuta query con cache opcional
     */
    public function query($sql, $params = [], $useCache = false) {
        $conn = $this->getConnection();

        // Generar clave de cache
        $cacheKey = null;
        if ($useCache && $this->cacheEnabled) {
            $cacheKey = md5($sql . serialize($params));

            // Verificar cache
            if (isset($this->queryCache[$cacheKey])) {
                $cached = $this->queryCache[$cacheKey];
                if (time() < $cached['expires']) {
                    return $cached['data'];
                }
                unset($this->queryCache[$cacheKey]);
            }
        }

        // Ejecutar query
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Error en query: " . print_r(sqlsrv_errors(), true));
            return false;
        }

        // Si es SELECT y usa cache, guardar resultados
        if ($useCache && stripos(trim($sql), 'SELECT') === 0) {
            $results = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $results[] = $row;
            }

            $this->queryCache[$cacheKey] = [
                'data' => $results,
                'expires' => time() + $this->cacheTTL
            ];

            return $results;
        }

        return $stmt;
    }

    /**
     * Limpia cache de queries
     */
    public function clearCache() {
        $this->queryCache = [];
    }

    /**
     * Cierra conexión
     */
    public function close() {
        if ($this->conn) {
            sqlsrv_close($this->conn);
            $this->conn = null;
            $GLOBALS['conn'] = null;
        }
    }

    public function __destruct() {
        $this->close();
    }
}

// Funciones helper para compatibilidad con código existente
function getDBConnection() {
    return DatabaseOptimized::getInstance()->getConnection();
}

function queryOptimized($sql, $params = [], $useCache = false) {
    return DatabaseOptimized::getInstance()->query($sql, $params, $useCache);
}

// Establecer conexión global para compatibilidad
$db = DatabaseOptimized::getInstance();
$conn = $db->getConnection();
?>
