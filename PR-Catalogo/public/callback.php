<?php
// PR-Catalogo/public/callback.php

// 1. Cargar el bootstrap
// Esto inicia la sesión, carga .env y nos da el objeto $auth
$auth = require_once __DIR__ . '/../src/bootstrap.php';

try {
    // 2. Manejar el código de respuesta de Microsoft
    // Este método (handleCallback) ya existe en tu src/Auth.php
    $auth->handleCallback();
    
    // 3. Redirigir al inicio (index.php) ya autenticado
    header('Location: ../index.php');
    exit();
    
} catch (Exception $e) {
    // Manejo de error
    error_log('Error en el callback de Azure: ' . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    die('<h1>Error de Autenticación</h1><p>No se pudo completar el inicio de sesión. Por favor, intente de nuevo.</p><p><small>Detalle: ' . htmlspecialchars($e->getMessage()) . '</small></p>');
}
?>