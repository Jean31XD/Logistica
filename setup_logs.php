<?php
/**
 * Script para crear directorio de logs en Azure
 * Ejecutar una sola vez después del despliegue
 */

$logDir = __DIR__ . '/logs';

echo "=== SETUP DE DIRECTORIO DE LOGS ===\n\n";

if (!is_dir($logDir)) {
    echo "Creando directorio: $logDir\n";

    if (mkdir($logDir, 0750, true)) {
        echo "✓ Directorio creado exitosamente\n";
    } else {
        echo "✗ Error al crear directorio\n";
        exit(1);
    }
} else {
    echo "✓ Directorio ya existe\n";
}

// Verificar permisos
if (is_writable($logDir)) {
    echo "✓ Directorio es escribible\n";
} else {
    echo "⚠️  Directorio NO es escribible - Verificar permisos\n";
}

// Crear archivo .gitkeep
$gitkeep = $logDir . '/.gitkeep';
if (!file_exists($gitkeep)) {
    file_put_contents($gitkeep, '');
    echo "✓ Archivo .gitkeep creado\n";
}

// Crear .gitignore para logs
$gitignore = $logDir . '/.gitignore';
if (!file_exists($gitignore)) {
    file_put_contents($gitignore, "# Ignorar todos los logs\n*.log\n*.log.gz\n!.gitkeep\n");
    echo "✓ Archivo .gitignore creado\n";
}

// Probar escritura
$testFile = $logDir . '/test_' . time() . '.log';
if (file_put_contents($testFile, "Test log entry\n")) {
    echo "✓ Test de escritura exitoso\n";
    unlink($testFile);
} else {
    echo "✗ No se puede escribir en el directorio\n";
}

echo "\n=== SETUP COMPLETADO ===\n";
echo "\n⚠️  Eliminar este archivo (setup_logs.php) después de ejecutar\n";
?>
