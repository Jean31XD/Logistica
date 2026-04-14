<?php
/**
 * Header Unificado - MACO Design System v3.0
 * Template reutilizable para todas las pantallas
 */

require_once __DIR__ . '/../../src/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

$usuario  = htmlspecialchars($_SESSION['usuario']);
$pantalla = $_SESSION['pantalla'] ?? 0;

$roles = [
    0 => 'Administrador',
    1 => 'Gestión',
    2 => 'Facturas',
    3 => 'CXC',
    4 => 'Reportes',
    5 => 'Panel Admin',
    6 => 'BI',
    8 => 'Etiquetas',
    9 => 'Dashboard',
];

$rol      = $roles[$pantalla] ?? 'Usuario';
$iniciales = strtoupper(substr($usuario, 0, 2));

$scriptPath  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$viewPath    = str_replace('\\', '/', realpath(__DIR__ . '/..'));

if (strpos($scriptPath, '/pantallas') !== false || strpos($scriptPath, '/modulos') !== false) {
    $basePath       = '../..';
    $assetsPath     = '../../assets';
    $viewAssetsPath = '../assets';
    $imgPath        = '../../IMG';
    $logicaPath     = '../../Logica';
    $pantallasPath  = '../pantallas';
    $modulosPath    = '../modulos';
} else {
    $basePath       = '..';
    $assetsPath     = '../assets';
    $viewAssetsPath = 'assets';
    $imgPath        = '../IMG';
    $logicaPath     = '../Logica';
    $pantallasPath  = 'pantallas';
    $modulosPath    = 'modulos';
}

$homeUrl = $pantallasPath . '/Portal.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MACO - Sistema de Logística') ?></title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Fonts: Inter + Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">

    <!-- MACO Design System -->
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/maco-design-system.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/ai-chat.css">

    <!-- Estilos adicionales de la página -->
    <?php if (isset($additionalCSS)): ?>
        <?= $additionalCSS ?>
    <?php endif; ?>

    <style>
        /* Prevenir flash de contenido sin estilo */
        body { opacity: 0; }
        body.loaded { opacity: 1; transition: opacity 0.25s ease; }
    </style>
</head>
<body>

<!-- Skip link: primer elemento enfocable (WCAG 2.2) -->
<a href="#main-content" class="skip-link">Saltar al contenido principal</a>

<div class="maco-app">

    <!-- Header -->
    <header class="maco-header" role="banner">
        <div class="maco-header-content">

            <!-- Logo -->
            <a href="<?= $homeUrl ?>" class="maco-logo" aria-label="MACO Logística — Ir al inicio">
                <img src="<?= $imgPath ?>/LOGO MC - NEGRO.png" alt="MACO Logo">
                <span class="maco-logo-text">MACO Logística</span>
            </a>

            <!-- Controles de usuario -->
            <nav class="maco-user" aria-label="Navegación de usuario">

                <a href="<?= $homeUrl ?>"
                   class="maco-home-btn"
                   aria-label="Ir al menú principal">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <span class="d-none d-md-inline">Inicio</span>
                </a>

                <div class="maco-user-info" aria-hidden="true">
                    <div class="maco-user-name"><?= $usuario ?></div>
                    <div class="maco-user-role"><?= $rol ?></div>
                </div>

                <div class="maco-user-avatar"
                     role="img"
                     aria-label="Usuario: <?= $usuario ?>, Rol: <?= $rol ?>">
                    <?= $iniciales ?>
                </div>

                <a href="<?= $logicaPath ?>/logout.php"
                   class="maco-logout-btn"
                   aria-label="Cerrar sesión"
                   id="logout-btn">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span class="d-none d-md-inline">Salir</span>
                </a>

            </nav>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main id="main-content" class="<?= $containerClass ?? 'maco-container' ?>" role="main" tabindex="-1">
