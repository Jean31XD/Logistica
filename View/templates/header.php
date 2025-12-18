<?php
/**
 * Header Unificado - MACO Design System
 * Template reutilizable para todas las pantallas
 */

// Cargar autoloader del proyecto (helpers y clases)
require_once __DIR__ . '/../../src/autoload.php';
// Asegurar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación (si no viene de una página que ya lo hizo)
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

$usuario = htmlspecialchars($_SESSION['usuario']);
$pantalla = $_SESSION['pantalla'] ?? 0;

// Mapeo de pantallas a roles
$roles = [
    0 => 'Administrador',
    1 => 'Gestión',
    2 => 'Facturas',
    3 => 'CXC',
    4 => 'Reportes',
    5 => 'Panel Admin',
    6 => 'BI',
    8 => 'Etiquetas',
    9 => 'Dashboard'
];

$rol = $roles[$pantalla] ?? 'Usuario';
$iniciales = strtoupper(substr($usuario, 0, 2));

// Calcular la ruta base relativa al directorio View
// Detectar si estamos en una subcarpeta (pantallas/ o modulos/)
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$viewPath = str_replace('\\', '/', realpath(__DIR__ . '/..'));

// Determinar el nivel de profundidad y rutas
if (strpos($scriptPath, '/pantallas') !== false || strpos($scriptPath, '/modulos') !== false) {
    $basePath = '../..'; // Desde View/pantallas/ o View/modulos/
    $assetsPath = '../../assets'; // assets/ en raíz del proyecto
    $viewAssetsPath = '../assets'; // View/assets/
    $imgPath = '../../IMG';
    $logicaPath = '../../Logica';
    $pantallasPath = '../pantallas';
    $modulosPath = '../modulos';
} else {
    $basePath = '..'; // Desde View/
    $assetsPath = '../assets'; // assets/ en raíz del proyecto
    $viewAssetsPath = 'assets'; // View/assets/
    $imgPath = '../IMG';
    $logicaPath = '../Logica';
    $pantallasPath = 'pantallas';
    $modulosPath = 'modulos';
}

// Página de inicio: TODOS los usuarios van a Portal.php
// Ya no hay panel de admin separado, todos ven solo sus módulos asignados
$homeUrl = $pantallasPath . '/Portal.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'MACO - Sistema de Logística' ?></title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- MACO Design System -->
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/maco-design-system.css">

    <!-- Estilos adicionales de la página -->
    <?php if (isset($additionalCSS)): ?>
        <?= $additionalCSS ?>
    <?php endif; ?>

    <style>
        /* Prevenir flash de contenido sin estilo */
        body { opacity: 0; transition: opacity 0.3s; }
        body.loaded { opacity: 1; }
    </style>
</head>
<body>
    <div class="maco-app">
        <!-- Header -->
        <header class="maco-header">
            <div class="maco-header-content">
                <div class="maco-logo">
                    <img src="<?= $imgPath ?>/LOGO MC - NEGRO.png" alt="MACO Logo">
                    <span class="maco-logo-text">MACO Logística</span>
                </div>

                <div class="maco-user">
                    <a href="<?= $homeUrl ?>" class="maco-home-btn" title="Volver al Menú Principal">
                        <i class="fas fa-home"></i>
                        <span class="d-none d-md-inline">Inicio</span>
                    </a>
                    <div class="maco-user-info">
                        <div class="maco-user-name"><?= $usuario ?></div>
                        <div class="maco-user-role"><?= $rol ?></div>
                    </div>
                    <div class="maco-user-avatar">
                        <?= $iniciales ?>
                    </div>
                    <a href="<?= $logicaPath ?>/logout.php" class="maco-logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="d-none d-md-inline">Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="<?= $containerClass ?? 'maco-container' ?>">
