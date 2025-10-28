<?php
// PR-Catalogo/src/session.php

/**
 * Gestor de Sesión Centralizado y Robusto
 *
 * Este script soluciona problemas de persistencia de sesión en entornos
 * como XAMPP, forzando el guardado de sesiones en una carpeta local.
 */

// Evitar que se inicie una sesión si ya hay una activa
if (session_status() === PHP_SESSION_NONE) {

    // --- INICIO DE LA SOLUCIÓN DEFINITIVA ---

    // 1. Definir una ruta de guardado de sesiones DENTRO de nuestro proyecto.
    // __DIR__ apunta a la carpeta actual (src), así que subimos un nivel (..)
    // y luego entramos en la carpeta 'sessions' que creamos.
    $sessionPath = __DIR__ . '/../sessions';

    // 2. Asegurarse de que la carpeta exista.
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }

    // 3. Forzar a PHP a usar nuestra ruta antes de iniciar la sesión.
    // Esto anula cualquier configuración incorrecta en php.ini.
    ini_set('session.save_path', $sessionPath);

    // --- FIN DE LA SOLUCIÓN DEFINITIVA ---

    // Establecer parámetros de cookie seguros
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path'     => '/',
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Usar un nombre de sesión personalizado
    session_name('CATALOGO_SESSID');

    // Iniciar la sesión (ahora guardará los archivos en nuestra carpeta)
    session_start();
}