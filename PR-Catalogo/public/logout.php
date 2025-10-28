<?php
// PR-Catalogo/public/logout.php

// --- ARREGLO 1: Iniciar el búfer de salida ---
// Atrapa cualquier "echo" o espacio en blanco accidental
ob_start();

// --- ARREGLO 2: Habilitar reporte de errores (SOLO PARA DEBUG) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 1. Cargar el bootstrap
    // Esto inicia la sesión, carga .env y nos da el objeto $auth
    $auth = require_once __DIR__ . '/../src/bootstrap.php';

    // 2. Cerrar la sesión
    // Esto llamará al método 'logout' en Auth.php,
    // que destruye la sesión Y redirige a index.php
    $auth->logout();

    // 3. Limpiar el búfer y terminar
    // Si $auth->logout() tiene éxito, la redirección ya ocurrió
    // y este código no se ejecutará.
    ob_end_flush(); 
    exit();

} catch (Exception $e) {
    // Si algo falla ANTES de llamar a logout()
    ob_end_clean(); // Limpiar búfer
    error_log('Error en logout.php: ' . $e->getMessage());
    die('<h1>Error Crítico al Cerrar Sesión</h1><p><strong>Detalle:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>');
}
?>