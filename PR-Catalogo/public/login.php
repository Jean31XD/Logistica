<?php
// PR-Catalogo/public/login.php

// --- ARREGLO 1: Iniciar el búfer de salida ---
// Esto captura cualquier "echo" o espacio en blanco accidental
ob_start();

// --- ARREGLO 2: Habilitar reporte de errores (SOLO PARA DEBUG) ---
// Esto nos mostrará si hay un error fatal (ej. "class 'Auth' not found")
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Cargar el bootstrap
// Esto inicia la sesión, carga .env y nos da el objeto $auth
try {
    $auth = require_once __DIR__ . '/../src/bootstrap.php';
} catch (Exception $e) {
    ob_end_clean(); // Limpiar búfer
    die('<h1>Error Fatal al cargar Bootstrap</h1><p>No se pudo cargar src/bootstrap.php.</p><p><strong>Detalle:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// 2. Redirigir a Microsoft para la autenticación
try {
    // Este método (login()) ya existe en tu src/Auth.php
    // e internamente llama a header('Location: ...')
    $auth->login();
    
    // --- ARREGLO 3: Limpiar el búfer y terminar ---
    // Si la redirección tiene éxito, el script termina.
    // Si no (raro), limpiamos el búfer y salimos.
    ob_end_flush(); 
    exit();
    
} catch (Exception $e) {
    // Si algo en $auth->login() falla, lo veremos.
    
    // Limpiamos el búfer (no queremos la redirección fallida)
    ob_end_clean(); 
    
    // Mostrar un error claro
    error_log('Error al intentar iniciar login: ' . $e->getMessage());
    die('<h1>Error Crítico en Login</h1><p>No se pudo redirigir al proveedor de autenticación.</p><p><strong>Detalle:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>');
}
?>