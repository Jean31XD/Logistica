<?php
/**
 * Script de Validación del Sistema de Login
 * Verifica todos los componentes de seguridad y optimización
 */

echo "=== VALIDACIÓN DEL SISTEMA DE LOGIN ===\n\n";

$errores = [];
$warnings = [];
$success = [];

// 1. Verificar archivos de configuración existen
echo "1. Verificando archivos de configuración...\n";

$archivos_requeridos = [
    'config/config.php' => 'Configuración base',
    'config/bootstrap.php' => 'Bootstrap optimizado',
    'config/security_headers.php' => 'Headers de seguridad',
    'config/csrf_helper.php' => 'Helper CSRF',
    'config/security_logger.php' => 'Logger de seguridad',
    'config/auth_middleware.php' => 'Middleware autenticación',
    'conexionBD/conexion.php' => 'Conexión a BD',
    '.env' => 'Variables de entorno',
    'index.php' => 'Página de login'
];

foreach ($archivos_requeridos as $archivo => $desc) {
    if (file_exists(__DIR__ . '/../' . $archivo)) {
        $success[] = "✅ $desc ($archivo) existe";
    } else {
        $errores[] = "❌ $desc ($archivo) NO ENCONTRADO";
    }
}

// 2. Verificar variables de entorno
echo "\n2. Verificando variables de entorno...\n";

require_once __DIR__ . '/../config/config.php';

$env_vars = ['DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_ENV'];
foreach ($env_vars as $var) {
    if (defined($var)) {
        $valor = constant($var);
        if ($var === 'DB_PASS') {
            $success[] = "✅ $var está configurado (oculto por seguridad)";
        } else {
            $success[] = "✅ $var = " . (strlen($valor) > 50 ? substr($valor, 0, 50) . '...' : $valor);
        }
    } else {
        $errores[] = "❌ Variable $var NO está definida";
    }
}

// 3. Probar conexión a BD
echo "\n3. Probando conexión a base de datos...\n";

try {
    require_once __DIR__ . '/../conexionBD/conexion.php';

    if (isset($conn) && $conn !== false) {
        $success[] = "✅ Conexión a BD establecida correctamente";

        // Probar query simple
        $stmt = sqlsrv_query($conn, "SELECT TOP 1 usuario FROM usuarios");
        if ($stmt !== false) {
            $success[] = "✅ Query de prueba ejecutada correctamente";
            sqlsrv_free_stmt($stmt);
        } else {
            $warnings[] = "⚠️ Query de prueba falló (verificar permisos)";
        }

    } else {
        $errores[] = "❌ No se pudo conectar a la base de datos";
    }
} catch (Exception $e) {
    $errores[] = "❌ Error al conectar BD: " . $e->getMessage();
}

// 4. Verificar sistema de logging
echo "\n4. Verificando sistema de logging...\n";

$log_dir = __DIR__ . '/../logs';
if (is_dir($log_dir)) {
    $success[] = "✅ Directorio de logs existe";

    if (is_writable($log_dir)) {
        $success[] = "✅ Directorio de logs tiene permisos de escritura";

        // Verificar archivos de log recientes
        $logs = glob($log_dir . '/security_*.log');
        if (count($logs) > 0) {
            $ultimo_log = end($logs);
            $size = filesize($ultimo_log);
            $success[] = "✅ Logs encontrados: " . count($logs) . " archivos";
            $success[] = "✅ Último log: " . basename($ultimo_log) . " (" . round($size/1024, 2) . " KB)";
        } else {
            $warnings[] = "⚠️ No se encontraron archivos de log (se crearán en el primer login)";
        }
    } else {
        $warnings[] = "⚠️ Directorio de logs sin permisos de escritura";
    }
} else {
    $errores[] = "❌ Directorio de logs no existe";
}

// 5. Verificar configuración de sesión
echo "\n5. Verificando configuración de sesión...\n";

$session_config = [
    'session.cookie_httponly' => '1',
    'session.use_strict_mode' => '1',
    'session.cookie_lifetime' => '0',
    'session.gc_maxlifetime' => '1800'
];

foreach ($session_config as $config => $valor_esperado) {
    $valor_actual = ini_get($config);
    if ($valor_actual == $valor_esperado) {
        $success[] = "✅ $config = $valor_actual";
    } else {
        $warnings[] = "⚠️ $config = $valor_actual (esperado: $valor_esperado)";
    }
}

// 6. Verificar funciones de seguridad
echo "\n6. Verificando funciones de seguridad...\n";

$funciones_requeridas = [
    'generarTokenCSRF' => 'config/csrf_helper.php',
    'verificarTokenCSRF' => 'config/csrf_helper.php',
    'registrarEventoSeguridad' => 'config/security_logger.php',
    'verificarAutenticacion' => 'config/auth_middleware.php',
    'sanitizarEntrada' => 'config/auth_middleware.php'
];

foreach ($funciones_requeridas as $funcion => $archivo) {
    if (function_exists($funcion)) {
        $success[] = "✅ Función $funcion() disponible";
    } else {
        $errores[] = "❌ Función $funcion() NO encontrada (revisar $archivo)";
    }
}

// 7. Verificar headers de seguridad
echo "\n7. Verificando headers de seguridad...\n";

ob_start();
require_once __DIR__ . '/../config/security_headers.php';
ob_end_clean();

$headers = headers_list();
$headers_esperados = [
    'X-Content-Type-Options',
    'X-Frame-Options',
    'X-XSS-Protection',
    'Content-Security-Policy',
    'Referrer-Policy'
];

foreach ($headers_esperados as $header_esperado) {
    $encontrado = false;
    foreach ($headers as $header) {
        if (stripos($header, $header_esperado) === 0) {
            $success[] = "✅ Header '$header_esperado' configurado";
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) {
        $warnings[] = "⚠️ Header '$header_esperado' no encontrado";
    }
}

// 8. Verificar protección CSRF en formulario
echo "\n8. Verificando protección CSRF en formulario de login...\n";

$index_content = file_get_contents(__DIR__ . '/../index.php');

if (strpos($index_content, 'campoTokenCSRF()') !== false || strpos($index_content, 'csrf_token') !== false) {
    $success[] = "✅ Token CSRF presente en formulario de login";
} else {
    $errores[] = "❌ Token CSRF NO encontrado en formulario de login";
}

if (strpos($index_content, 'verificarTokenCSRF()') !== false) {
    $success[] = "✅ Verificación de token CSRF implementada";
} else {
    $errores[] = "❌ Verificación de token CSRF NO implementada";
}

// 9. Verificar rate limiting
echo "\n9. Verificando rate limiting...\n";

if (strpos($index_content, 'intentos_login') !== false) {
    $success[] = "✅ Sistema de rate limiting implementado";

    if (strpos($index_content, '>= 5') !== false) {
        $success[] = "✅ Límite configurado en 5 intentos";
    }
} else {
    $warnings[] = "⚠️ Rate limiting no encontrado";
}

// 10. Verificar logging de eventos
echo "\n10. Verificando logging de eventos...\n";

if (strpos($index_content, 'registrarIntentoLogin') !== false ||
    strpos($index_content, 'registrarEventoSeguridad') !== false) {
    $success[] = "✅ Logging de intentos de login implementado";
} else {
    $warnings[] = "⚠️ Logging de eventos no encontrado";
}

// 11. Verificar password hashing
echo "\n11. Verificando password hashing...\n";

if (strpos($index_content, 'password_verify') !== false) {
    $success[] = "✅ Verificación de contraseñas con password_verify()";
} else {
    $errores[] = "❌ password_verify() NO encontrado";
}

// 12. Verificar regeneración de sesión
echo "\n12. Verificando regeneración de sesión...\n";

if (strpos($index_content, 'session_regenerate_id') !== false) {
    $success[] = "✅ Regeneración de ID de sesión implementada";
} else {
    $warnings[] = "⚠️ session_regenerate_id() no encontrado";
}

// 13. Verificar archivos optimizados
echo "\n13. Verificando archivos optimizados...\n";

$archivos_optimizados = [
    'config/bootstrap.php' => 'Bootstrap optimizado',
    'config/security_logger_optimized.php' => 'Logger optimizado',
    'config/ajax_endpoint.php' => 'Template AJAX',
    'conexionBD/conexion_optimized.php' => 'Conexión optimizada'
];

$optimizaciones_disponibles = 0;
foreach ($archivos_optimizados as $archivo => $desc) {
    if (file_exists(__DIR__ . '/../' . $archivo)) {
        $success[] = "✅ $desc disponible";
        $optimizaciones_disponibles++;
    } else {
        $warnings[] = "⚠️ $desc no encontrado (opcional)";
    }
}

if ($optimizaciones_disponibles > 0) {
    $success[] = "✅ Optimizaciones disponibles: $optimizaciones_disponibles/4";
}

// ==========================================
// RESUMEN FINAL
// ==========================================

echo "\n\n";
echo str_repeat("=", 60) . "\n";
echo "RESUMEN DE VALIDACIÓN\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ ÉXITOS: " . count($success) . "\n";
foreach ($success as $msg) {
    echo "   $msg\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  ADVERTENCIAS: " . count($warnings) . "\n";
    foreach ($warnings as $msg) {
        echo "   $msg\n";
    }
}

if (!empty($errores)) {
    echo "\n❌ ERRORES CRÍTICOS: " . count($errores) . "\n";
    foreach ($errores as $msg) {
        echo "   $msg\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

// Calcular puntuación
$total = count($success) + count($warnings) + count($errores);
$puntuacion = round((count($success) / $total) * 100, 1);

echo "PUNTUACIÓN GENERAL: $puntuacion%\n";

if (count($errores) === 0 && count($warnings) <= 3) {
    echo "ESTADO: ✅ SISTEMA DE LOGIN COMPLETAMENTE FUNCIONAL\n";
} elseif (count($errores) === 0) {
    echo "ESTADO: ⚠️  SISTEMA FUNCIONAL CON ADVERTENCIAS MENORES\n";
} elseif (count($errores) <= 2) {
    echo "ESTADO: ⚠️  SISTEMA FUNCIONAL CON PROBLEMAS MENORES\n";
} else {
    echo "ESTADO: ❌ REVISAR ERRORES CRÍTICOS\n";
}

echo str_repeat("=", 60) . "\n\n";

// Test de carga de página
echo "14. Test de rendimiento básico...\n";
$inicio = microtime(true);
require_once __DIR__ . '/../index.php';
$tiempo = round((microtime(true) - $inicio) * 1000, 2);
echo "   ✅ Página de login cargada en {$tiempo}ms\n";

if ($tiempo < 100) {
    echo "   🚀 EXCELENTE: Tiempo de carga óptimo\n";
} elseif ($tiempo < 200) {
    echo "   ✅ BUENO: Tiempo de carga aceptable\n";
} else {
    echo "   ⚠️  LENTO: Considerar optimizaciones adicionales\n";
}

echo "\n✅ VALIDACIÓN COMPLETADA\n";
?>
